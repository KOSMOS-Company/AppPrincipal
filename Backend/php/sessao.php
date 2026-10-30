<?php
// ============================================================
//  KOSMOS — Helpers de sessão (autenticação)
//  Arquivo: backend/php/sessao.php
//  Centraliza tudo que envolve "quem está logado".
// ============================================================

// exigirLogin() confere a versão da sessão no banco, então precisa de conectar()
require_once __DIR__ . '/conexao.php';
// fuso, log e cabeçalhos — o conexao.php novo já carrega; isto cobre
// quem ainda tem uma cópia antiga dele (ver bootstrap.php)
require_once __DIR__ . '/bootstrap.php';

/* Quanto tempo uma sessão PARADA continua valendo no servidor. O padrão
   do PHP (24 min) é menor que um Pomodoro: quem deixava o timer correndo
   sem mexer no site voltava deslogado, e o ciclo se perdia. */
const SESSAO_DURACAO_SEG = 8 * 3600;

/* "Lembrar de mim": o aparelho fica logado por este tempo, mesmo depois
   de fechar o navegador. Ver criarLembranca() e restaurarLembranca(). */
const LEMBRAR_COOKIE = 'kosmos_lembrar';
const LEMBRAR_DIAS   = 30;

/** O site está em HTTPS? (o cookie só sai com `secure` nesse caso) */
function sessaoEmHttps(): bool {
    return !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
}

/**
 * Inicia a sessão do PHP (apenas uma vez, com segurança).
 * Chame isto antes de ler ou escrever qualquer coisa em $_SESSION.
 */
function iniciarSessao(): void {
    if (session_status() === PHP_SESSION_NONE) {
        /* As sessões do Kosmos moram numa pasta só delas (Backend/sessoes,
           bloqueada na web). Na pasta comum, quem faz a faxina é o
           primeiro script que roda — o phpMyAdmin no XAMPP, o cron do
           Debian/Ubuntu — e ele apaga tudo com mais de 24 min, ignorando
           o SESSAO_DURACAO_SEG daqui. Na nossa pasta, a faxina é nossa. */
        $pasta = __DIR__ . '/../sessoes';
        if (is_dir($pasta) && is_writable($pasta)) {
            session_save_path($pasta);
            ini_set('session.gc_probability', '1');
            ini_set('session.gc_divisor', '100');
        }
        ini_set('session.gc_maxlifetime', (string) SESSAO_DURACAO_SEG);
        // Endurece o cookie antes de abri-lo (só vale se vier antes do session_start):
        //  - httponly: JavaScript (e um eventual XSS) não lê o PHPSESSID;
        //  - samesite Lax: o navegador não manda o cookie em POSTs vindos de
        //    outros sites, o que já barra o CSRF clássico nos endpoints;
        //  - secure só sob HTTPS, senão o login quebraria no XAMPP em http;
        //  - path '/' é o mesmo padrão de antes (php.ini), nada muda para o app.
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => sessaoEmHttps(),
            'path'     => '/',
        ]);
        // strict_mode: o PHP recusa IDs de sessão que ele não criou, o que
        // impede um atacante de "plantar" um ID conhecido (session fixation)
        ini_set('session.use_strict_mode', '1');
        session_start();
    }
}

/**
 * Devolve o usuário logado como array ['id' => ..., 'nome' => ...]
 * ou null se ninguém estiver logado nesta sessão.
 */
function usuarioLogado(): ?array {
    iniciarSessao();

    // Sessão vencida, mas o aparelho foi lembrado: entra de novo sozinho
    if (empty($_SESSION['usuario_id']) && !restaurarLembranca()) {
        return null;
    }

    return [
        'id'   => $_SESSION['usuario_id'],
        'nome' => $_SESSION['usuario_nome'] ?? '',
    ];
}

/**
 * Registra o acesso de hoje e atualiza a "sequência de dias".
 * Regra: mesmo dia = mantém; dia seguinte = +1; pulou dia(s) = reinicia em 1.
 * Devolve a sequência atual (em dias). Idempotente no mesmo dia.
 */
function registrarAcesso(PDO $pdo, int $usuarioId): int {
    // IMPORTANTE: toda a lógica usa o MESMO relógio — o do MySQL, via CURDATE().
    // Antes, o código comparava com o "hoje" do PHP (new DateTime('today')) mas
    // gravava com CURDATE() do MySQL. Quando os fusos do PHP e do MySQL diferiam
    // (ex.: PHP em Europe/Berlin e MySQL em America/Sao_Paulo), a diferença dava
    // sempre "1 dia" e a sequência somava +1 a CADA acesso/recarga da página.
    //
    // Agora é um único UPDATE atômico, contando dias de calendário reais:
    //   mesmo dia            -> mantém (idempotente: recarregar não muda nada)
    //   dia seguinte         -> +1
    //   pulou dia / 1º acesso-> reinicia em 1
    $sql = 'UPDATE usuarios
               SET sequencia = CASE
                       WHEN ultimo_acesso = CURDATE()                  THEN GREATEST(sequencia, 1)
                       WHEN ultimo_acesso = CURDATE() - INTERVAL 1 DAY THEN sequencia + 1
                       ELSE 1
                   END,
                   ultimo_acesso = CURDATE()
             WHERE id = ?';
    $pdo->prepare($sql)->execute([$usuarioId]);

    $stmt = $pdo->prepare('SELECT sequencia FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->execute([$usuarioId]);
    $row = $stmt->fetch();
    return $row ? (int) $row['sequencia'] : 0;
}

/**
 * Segunda camada contra CSRF, além do cookie SameSite=Lax: um POST
 * que o navegador diz vir de OUTRO site (cabeçalho Origin) é recusado.
 * O fetch do próprio app manda Origin igual ao Host, então nada muda
 * para ele. Sem Origin (curl, navegador antigo) passa: aí quem
 * protege é o SameSite.
 */
function exigirMesmaOrigem(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || empty($_SERVER['HTTP_ORIGIN'])) {
        return;
    }
    $origem = parse_url((string) $_SERVER['HTTP_ORIGIN']);
    $host   = ($origem['host'] ?? '') . (isset($origem['port']) ? ':' . $origem['port'] : '');

    if (strcasecmp($host, (string) ($_SERVER['HTTP_HOST'] ?? '')) !== 0) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['ok' => false, 'msg' => 'Requisição de outra origem recusada.']);
        exit;
    }
}

/**
 * "Porteiro" para endpoints protegidos: se não houver login,
 * responde 401 em JSON e encerra o script imediatamente.
 * Se houver login, devolve os dados do usuário.
 *
 * Também confere a "geração" da sessão: sem isso, "sair de todos os
 * dispositivos" (e a troca de senha) só derrubava as outras sessões nas
 * páginas do dashboard — a API continuava aceitando o cookie antigo.
 * A checagem roda aqui, antes de qualquer liberarSessao() do endpoint,
 * porque encerrar a sessão exige que ela ainda esteja aberta.
 */
function exigirLogin(): array {
    exigirMesmaOrigem();
    $usuario = usuarioLogado();

    if ($usuario !== null) {
        $valida = true;
        try {
            $valida = sessaoAindaValida(conectar(), (int) $usuario['id']);
        } catch (Throwable $e) {
            registrarErro($e);
            // Banco fora do ar: não derruba o login por isso — o próprio
            // endpoint vai falhar ao consultar e responder o erro dele.
            $valida = true;
        }

        if (!$valida) {
            encerrarSessao();
            $usuario = null;
        }
    }

    if ($usuario === null) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401); // 401 = "não autorizado / não logado"
        echo json_encode(['ok' => false, 'msg' => 'Você precisa estar logado.']);
        exit;
    }

    return $usuario;
}

/**
 * Marca nesta sessão qual a "geração" de sessões do usuário.
 * Chame logo depois de gravar usuario_id no login.
 */
function marcarVersaoSessao(PDO $pdo, int $usuarioId): void {
    iniciarSessao();
    $stmt = $pdo->prepare('SELECT sessoes_versao FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->execute([$usuarioId]);
    $row = $stmt->fetch();
    $_SESSION['sessoes_versao'] = $row ? (int) $row['sessoes_versao'] : 0;

    // O "Lembrar de mim" deste aparelho acompanha: no "sair de todos os
    // dispositivos" e na troca de senha, só os OUTROS ficam para trás
    $lembranca = lerCookieLembranca();
    if ($lembranca !== null) {
        try {
            $pdo->prepare('UPDATE sessoes_lembradas SET sessoes_versao = ? WHERE seletor = ? AND usuario_id = ?')
                ->execute([$_SESSION['sessoes_versao'], $lembranca[0], $usuarioId]);
        } catch (PDOException $e) {
            registrarErro($e);   // migração pendente: o login segue, só sem lembrança
        }
    }
}

/* ============================================================
   "LEMBRAR DE MIM"
   O cookie leva "seletor:validador". O seletor acha a linha em
   sessoes_lembradas; do validador o banco guarda só o hash — se o
   banco vazar, ninguém entra com o que está lá (mesma ideia do token
   de redefinição de senha). A linha também guarda a "geração" de
   sessões da conta: o "sair de todos os dispositivos" sobe a da
   conta, e as lembranças antigas param de valer sem precisar apagar.
   ============================================================ */

/** [seletor, validador] do cookie deste aparelho, ou null. */
function lerCookieLembranca(): ?array {
    $bruto = (string) ($_COOKIE[LEMBRAR_COOKIE] ?? '');
    if (!preg_match('/^([0-9a-f]{24}):([0-9a-f]{64})$/', $bruto, $m)) {
        return null;
    }
    return [$m[1], $m[2]];
}

function gravarCookieLembranca(string $valor, int $expira): void {
    setcookie(LEMBRAR_COOKIE, $valor, [
        'expires'  => $expira,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => sessaoEmHttps(),
    ]);
}

/** Liga o "Lembrar de mim" neste aparelho (chame depois do marcarVersaoSessao()). */
function criarLembranca(PDO $pdo, int $usuarioId): void {
    esquecerEsteAparelho($pdo);   // uma lembrança por aparelho
    $seletor   = bin2hex(random_bytes(12));
    $validador = bin2hex(random_bytes(32));

    $pdo->prepare('DELETE FROM sessoes_lembradas WHERE usuario_id = ? AND expira_em < NOW()')
        ->execute([$usuarioId]);
    $pdo->prepare(
        'INSERT INTO sessoes_lembradas (seletor, validador_hash, usuario_id, sessoes_versao, expira_em)
         SELECT ?, ?, id, sessoes_versao, NOW() + INTERVAL ' . LEMBRAR_DIAS . ' DAY FROM usuarios WHERE id = ?'
    )->execute([$seletor, hash('sha256', $validador), $usuarioId]);

    gravarCookieLembranca($seletor . ':' . $validador, time() + LEMBRAR_DIAS * 86400);
}

/** Desliga o "Lembrar de mim" deste aparelho (logout, login sem marcar). */
function esquecerEsteAparelho(?PDO $pdo): void {
    $lembranca = lerCookieLembranca();
    if ($lembranca !== null && $pdo !== null) {
        $pdo->prepare('DELETE FROM sessoes_lembradas WHERE seletor = ?')->execute([$lembranca[0]]);
    }
    if (isset($_COOKIE[LEMBRAR_COOKIE])) {
        gravarCookieLembranca('', time() - 42000);
        unset($_COOKIE[LEMBRAR_COOKIE]);
    }
}

/**
 * Sem sessão, mas com o cookie do "Lembrar de mim": confere e, se
 * valer, abre a sessão de novo, como um login. Devolve se entrou.
 *
 * O validador NÃO é trocado a cada uso: o dashboard dispara várias
 * requisições juntas, e com troca a 2ª chegava com o valor antigo e
 * derrubava o login. A validade é que se renova (30 dias desde o
 * último uso).
 */
function restaurarLembranca(): bool {
    $lembranca = lerCookieLembranca();
    if ($lembranca === null) {
        return false;
    }
    [$seletor, $validador] = $lembranca;

    try {
        $pdo  = conectar();
        $stmt = $pdo->prepare(
            'SELECT l.validador_hash, l.sessoes_versao AS versao_lembranca,
                    u.id, u.nome, u.sessoes_versao AS versao_conta
               FROM sessoes_lembradas l
               JOIN usuarios u ON u.id = l.usuario_id
              WHERE l.seletor = ? AND l.expira_em > NOW()
              LIMIT 1'
        );
        $stmt->execute([$seletor]);
        $l = $stmt->fetch();

        if (!$l || !hash_equals($l['validador_hash'], hash('sha256', $validador))
            || (int) $l['versao_lembranca'] < (int) $l['versao_conta']) {
            esquecerEsteAparelho($pdo);
            return false;
        }

        // Mesmo cuidado do login: ID de sessão novo ao autenticar
        session_regenerate_id(true);
        $_SESSION['usuario_id']     = (int) $l['id'];
        $_SESSION['usuario_nome']   = $l['nome'];
        $_SESSION['sessoes_versao'] = (int) $l['versao_conta'];

        $pdo->prepare('UPDATE sessoes_lembradas SET expira_em = NOW() + INTERVAL ' . LEMBRAR_DIAS . ' DAY WHERE seletor = ?')
            ->execute([$seletor]);
        gravarCookieLembranca($seletor . ':' . $validador, time() + LEMBRAR_DIAS * 86400);

        registrarAcesso($pdo, (int) $l['id']);   // conta para a sequência, como um login
        return true;
    } catch (Throwable $e) {
        // sem a tabela (migração pendente) ou banco fora: segue deslogado
        registrarErro($e);
        return false;
    }
}

/**
 * A sessão atual ainda vale? Fica falsa quando o usuário pede
 * "sair de todos os dispositivos" em OUTRO lugar: lá a coluna
 * sessoes_versao é incrementada e as sessões antigas ficam para trás.
 *
 * Obs.: a checagem acontece onde este helper é chamado — em todo
 * endpoint via exigirLogin(), no pagina_dashboard.php e no
 * usuario_atual.php. Sessões antigas caem no próximo acesso, não no
 * mesmo instante.
 */
function sessaoAindaValida(PDO $pdo, int $usuarioId): bool {
    iniciarSessao();

    $stmt = $pdo->prepare('SELECT sessoes_versao FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->execute([$usuarioId]);
    $row = $stmt->fetch();

    if (!$row) {
        return false;   // usuário apagado (ex.: excluiu a conta)
    }

    $atual = (int) $row['sessoes_versao'];
    $daSessao = isset($_SESSION['sessoes_versao']) ? (int) $_SESSION['sessoes_versao'] : 0;

    return $daSessao >= $atual;
}

/**
 * Encerra a sessão atual por completo (usado no logout e quando a
 * sessão é invalidada por outro dispositivo ou pela exclusão da conta).
 */
function encerrarSessao(): void {
    iniciarSessao();
    // sair também desliga o "Lembrar de mim" deste aparelho
    if (lerCookieLembranca() !== null) {
        try {
            esquecerEsteAparelho(conectar());
        } catch (Throwable $e) {
            registrarErro($e);
            esquecerEsteAparelho(null);   // o cookie sai mesmo assim
        }
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * Fecha o arquivo da sessão sem encerrar o login.
 *
 * Por que isso importa: enquanto uma requisição mantém a sessão
 * aberta, o PHP guarda um lock exclusivo no arquivo dela — e as
 * outras requisições DO MESMO usuário ficam na fila esperando.
 * A aba Conta chama três endpoints ao mesmo tempo, então sem isso
 * eles se enfileiram e a página parece travar ao carregar.
 *
 * Chame depois de ler o que precisa de $_SESSION e antes das
 * consultas ao banco. Só não use em quem ESCREVE na sessão
 * (login, "sair de todos os dispositivos", logout).
 */
function liberarSessao(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}
