<?php
// ============================================================
//  KOSMOS — Helpers de sessão (autenticação)
//  Arquivo: backend/php/sessao.php
//  Centraliza tudo que envolve "quem está logado".
// ============================================================

/**
 * Inicia a sessão do PHP (apenas uma vez, com segurança).
 * Chame isto antes de ler ou escrever qualquer coisa em $_SESSION.
 */
function iniciarSessao(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Devolve o usuário logado como array ['id' => ..., 'nome' => ...]
 * ou null se ninguém estiver logado nesta sessão.
 */
function usuarioLogado(): ?array {
    iniciarSessao();

    if (empty($_SESSION['usuario_id'])) {
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
 * "Porteiro" para endpoints protegidos: se não houver login,
 * responde 401 em JSON e encerra o script imediatamente.
 * Se houver login, devolve os dados do usuário.
 */
function exigirLogin(): array {
    $usuario = usuarioLogado();

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
}

/**
 * A sessão atual ainda vale? Fica falsa quando o usuário pede
 * "sair de todos os dispositivos" em OUTRO lugar: lá a coluna
 * sessoes_versao é incrementada e as sessões antigas ficam para trás.
 *
 * Obs.: a checagem acontece onde este helper é chamado — hoje no
 * usuario_atual.php (que o dashboard consulta a cada página) e no
 * conta_dados.php. Sessões antigas caem no próximo acesso, não no
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
