<?php
// ============================================================
//  KOSMOS — Registra uma sessão de foco concluída
//  Arquivo: Backend/php/pomodoro_sessao.php
//  POST (protegido):
//    fim     -> instante em que o ciclo terminou (epoch, SEGUNDOS)
//    minutos -> duração do ciclo
//
//  Chamado pelo js/pomodoro.js quando um ciclo de FOCO fecha —
//  inclusive os que terminaram com a pessoa fora do app.
//
//  Só vale com um Iniciar guardado pelo pomodoro_inicio.php, no mesmo
//  `aparelho`, há pelo menos `minutos` (relógio do MySQL); gravar o
//  ciclo consome esse início. Sem ele, responde 409.
//
//  Por que o cliente manda o instante em vez de usarmos NOW():
//  o timer roda pelo relógio do sistema e continua correndo com a
//  aba fechada. Quando a pessoa volta, o ciclo pode ter terminado
//  há horas; gravar NOW() jogaria a sessão no dia errado.
//
//  Duplicata não é acidente aqui, é rotina: duas abas abertas
//  descobrem o mesmo fim de ciclo, e um recarregamento redescobre.
//  Quem resolve é o UNIQUE (usuario_id, fim_em) da tabela — o fim
//  de um ciclo é um instante único, então serve de identidade sem
//  precisar inventar um id no cliente. O INSERT ... ON DUPLICATE
//  KEY UPDATE deixa a segunda tentativa passar em silêncio — e sem
//  mexer nos minutos já gravados (reenviar o mesmo fim com
//  "minutos: 180" transformava um ciclo de 15 min em 3 horas).
//
//  Ciclos não podem se sobrepor no tempo: duas sessões "terminando"
//  em horários que se cruzam não são duas sessões de estudo, são uma
//  forjada. Antes isso só tirava o XP, mas a sessão era gravada e
//  contava para conquistas, missões e a frequência do mês. Agora ela
//  nem entra.
// ============================================================

require_once __DIR__ . '/estudo_comum.php';
require_once __DIR__ . '/ProgressoService.php';
require_once __DIR__ . '/pomodoro_util.php';

/* XP: um ciclo curto demais não é um Pomodoro (um timer de 1 minuto
   viraria fábrica de XP). */
const POMO_MIN_MINUTOS_XP = 15;

$usuario = exigirLogin();
estExigirPost();

$corpo = estCorpo();

// Um ciclo de Pomodoro tem entre 1 e 180 minutos. Fora disso é
// engano ou alguém mexendo na requisição — nos dois casos não entra.
// Recusa em vez de ajustar: o estInt levava 9999 para 180 e o ciclo
// forjado ainda rendia XP.
$brutoMin = $corpo['minutos'] ?? null;
$minutos = (is_int($brutoMin) || is_string($brutoMin))
    ? filter_var($brutoMin, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 180]])
    : false;
if ($minutos === false) {
    estErro('Duração inválida.', 422);
}

$fim = isset($corpo['fim']) && is_numeric($corpo['fim']) ? (int) $corpo['fim'] : 0;
if ($fim <= 0) {
    estErro('Instante de término ausente.', 422);
}

/* Uma janela de sanidade em volta do agora. Sem ela, um relógio
   errado no computador da pessoa (ou um valor forjado) enfiaria
   sessões em 1999 ou em 2040 e sujaria o histórico para sempre.
   Sete dias para trás cobre "estudei no fim de semana e só abri o
   app agora"; cinco minutos para frente cobre relógio adiantado. */
$agora = time();
if ($fim < $agora - 7 * 24 * 3600 || $fim > $agora + 300) {
    estErro('Instante fora da janela aceita.', 422);
}

/* O ciclo é DE UMA CONTA. O navegador guarda o timer por conta e
   manda junto o id de quem o iniciou: se quem está logado agora é
   outra pessoa (fechou a aba e entrou com outra conta no mesmo
   navegador), a sessão não é dela e não entra. Sem `conta` (estado
   salvo antes desta regra), vale a checagem da data de criação. */
if (isset($corpo['conta']) && (int) $corpo['conta'] !== (int) $usuario['id']) {
    estErro('Esta sessão de foco é de outra conta.', 409);
}

try {
    $pdo = conectar();
    liberarSessao();

    /* Uma sessão que terminou ANTES de a conta existir não pode ser
       dela — foi exatamente o que acontecia quando o timer de uma
       conta era registrado na conta nova que entrou depois. */
    $stmt = $pdo->prepare('SELECT UNIX_TIMESTAMP(criado_em) FROM usuarios WHERE id = ?');
    $stmt->execute([$usuario['id']]);
    $criadaEm = (int) $stmt->fetchColumn();
    if ($criadaEm > 0 && $fim < $criadaEm) {
        estErro('Esta sessão de foco é anterior à criação da conta.', 409);
    }

    /* O ciclo precisa de um Iniciar de verdade (pomodoro_inicio.php),
       NESTE aparelho, e do tempo dele passado no relógio do banco. Sem
       isto, bastava um POST com `fim` e `minutos` para inventar horas
       de foco. O reenvio de um ciclo já gravado (outra aba,
       recarregamento) pula a conferência: o início já foi consumido
       por ele, e o pomodoroGravar() responde "não é novo" sem mudar nada. */
    $aparelho = (string) ($corpo['aparelho'] ?? '');
    $stmt = $pdo->prepare('SELECT 1 FROM pomodoro_sessoes WHERE usuario_id = ? AND fim_em = FROM_UNIXTIME(?)');
    $stmt->execute([$usuario['id'], $fim]);
    if (!$stmt->fetchColumn()) {
        $decorridos = null;
        if (preg_match('/^[0-9a-f]{32}$/', $aparelho)) {
            $stmt = $pdo->prepare(
                'SELECT TIMESTAMPDIFF(SECOND, inicio_em, NOW())
                   FROM pomodoro_inicios WHERE usuario_id = ? AND aparelho = ?'
            );
            $stmt->execute([$usuario['id'], $aparelho]);
            $lido = $stmt->fetchColumn();
            $decorridos = $lido === false ? null : (int) $lido;
        }
        if (!pomodoroCabeNoInicio($decorridos, $minutos)) {
            estErro('Este ciclo não tem um início registrado que o comporte.', 409);
        }
    }

    // Sobreposição e duplicata: regras e motivos no pomodoro_util.php
    $gravado = pomodoroGravar($pdo, (int) $usuario['id'], $minutos, $fim);
    if ($gravado['sobrepoe']) {
        estErro('Este ciclo se sobrepõe a outro já registrado.', 409);
    }
    $novo = $gravado['novo'];

    // Um início vale um ciclo: sem consumir, ele cobriria vários
    if ($novo) {
        $pdo->prepare('DELETE FROM pomodoro_inicios WHERE usuario_id = ? AND aparelho = ?')
            ->execute([$usuario['id'], $aparelho]);
    }

    // Quanto já foi estudado hoje — é o que a tela usa para a meta.
    $hoje = $pdo->prepare(
        'SELECT COALESCE(SUM(minutos), 0) AS minutos
           FROM pomodoro_sessoes
          WHERE usuario_id = ? AND dia = CURDATE()'
    );
    $hoje->execute([$usuario['id']]);

    $minutosHoje = (int) $hoje->fetchColumn();

    // XP só na PRIMEIRA gravação do ciclo (a segunda aba que descobre o
    // mesmo fim não ganha de novo). A sobreposição já foi barrada acima.
    $progresso = null;
    if ($novo && $minutos >= POMO_MIN_MINUTOS_XP) {
        $progresso = progressoRegistrar($pdo, (int) $usuario['id'], [[
            'acao'     => 'pomodoro_concluido',
            'xp'       => ProgressoService::XP['pomodoro_concluido'],
            'detalhes' => ['minutos' => $minutos, 'fim' => $fim],
        ]]);
    }

    estResponder([
        'minutos_hoje' => $minutosHoje,
        'novo'         => $novo,
        'progresso'    => $progresso,
    ]);
} catch (PDOException $e) {
    registrarErro($e);
    estErro('Não foi possível registrar a sessão.', 500);
}
