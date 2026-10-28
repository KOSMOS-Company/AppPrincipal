<?php
// ============================================================
//  KOSMOS — Testes que precisam do banco
//  Arquivo: Backend/testes/banco.php
//
//  O rodar.php cobre só a lógica pura. Este cobre o que mora no
//  MySQL: gravação dos ciclos de foco (sobreposição, duplicata),
//  o XP que eles dão e o limite de tentativas de login.
//
//  Cria um banco DESCARTÁVEL (kosmos_teste) a partir do
//  Backend/kosmos.sql, roda os testes e apaga o banco no fim. O
//  banco de verdade (kosmos) nunca é tocado.
//
//  Uso (da raiz do AppPrincipal, com o MySQL do XAMPP ligado):
//      C:\xampp\php\php.exe Backend/testes/banco.php
//  Conexão: localhost / root / sem senha, ou as variáveis
//  TESTE_DB_HOST, TESTE_DB_USER e TESTE_DB_PASS (usadas no CI).
//  Sai com código 1 se algum teste falhar.
// ============================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../php/bootstrap.php';
require_once __DIR__ . '/../php/ProgressoService.php';
require_once __DIR__ . '/../php/pomodoro_util.php';
require_once __DIR__ . '/../php/limite_util.php';

const BANCO_TESTE = 'kosmos_teste';

$falhas = 0;
$total  = 0;

function confere(string $nome, $obtido, $esperado): void {
    global $falhas, $total;
    $total++;
    if ($obtido === $esperado) {
        echo "  ok    $nome\n";
        return;
    }
    $falhas++;
    echo "  FALHA $nome\n"
       . '        esperado: ' . var_export($esperado, true) . "\n"
       . '        obtido:   ' . var_export($obtido, true) . "\n";
}

// ---------------------------------------------------------------
// Monta o banco descartável
// ---------------------------------------------------------------
$host = getenv('TESTE_DB_HOST') ?: 'localhost';
$user = getenv('TESTE_DB_USER') ?: 'root';
$pass = getenv('TESTE_DB_PASS') ?: '';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "Sem conexão com o MySQL ($host): " . $e->getMessage() . "\n");
    exit(1);
}

$sql = file_get_contents(__DIR__ . '/../kosmos.sql');
$sql = str_replace(
    ['CREATE DATABASE IF NOT EXISTS `kosmos`', 'USE `kosmos`'],
    ['CREATE DATABASE `' . BANCO_TESTE . '`',  'USE `' . BANCO_TESTE . '`'],
    $sql
);
if (!str_contains($sql, 'USE `' . BANCO_TESTE . '`')) {
    fwrite(STDERR, "O kosmos.sql mudou de formato: não achei o 'USE `kosmos`' para trocar.\n");
    exit(1);
}

$pdo->exec('DROP DATABASE IF EXISTS `' . BANCO_TESTE . '`');
$importa = $pdo->query($sql);
do { /* percorre cada comando: um erro em qualquer um vira exceção aqui */ } while ($importa->nextRowset());
$importa->closeCursor();
$pdo->exec('USE `' . BANCO_TESTE . '`');
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

register_shutdown_function(function () use ($pdo) {
    $pdo->exec('DROP DATABASE IF EXISTS `' . BANCO_TESTE . '`');
});

$criaUsuario = function (int $id, string $email) use ($pdo): void {
    $pdo->prepare('INSERT INTO usuarios (id, nome, email, criado_em) VALUES (?, ?, ?, NOW() - INTERVAL 30 DAY)')
        ->execute([$id, 'Teste ' . $id, $email]);
};
$um = function (string $sql, array $args = []) use ($pdo) {
    $s = $pdo->prepare($sql);
    $s->execute($args);
    return $s->fetchColumn();
};

// ---------------------------------------------------------------
echo "Ciclos de foco (pomodoro_util.php)\n";

$criaUsuario(1, 'ana@teste.dev');
$fim = time() - 3600;   // um ciclo de 25 min que terminou há uma hora

$r = pomodoroGravar($pdo, 1, 25, $fim);
confere('primeiro ciclo é gravado como novo', $r, ['novo' => true, 'sobrepoe' => false]);

$r = pomodoroGravar($pdo, 1, 25, $fim);
confere('o mesmo fim de novo (outra aba) não é novo', $r, ['novo' => false, 'sobrepoe' => false]);

pomodoroGravar($pdo, 1, 180, $fim);
confere('reenviar o mesmo fim com 180 min não muda os minutos',
    (int) $um('SELECT minutos FROM pomodoro_sessoes WHERE usuario_id = 1'), 25);

$r = pomodoroGravar($pdo, 1, 25, $fim + 10 * 60);
confere('ciclo que se cruza com outro é recusado', $r, ['novo' => false, 'sobrepoe' => true]);
confere('o ciclo recusado não entra na tabela',
    (int) $um('SELECT COUNT(*) FROM pomodoro_sessoes WHERE usuario_id = 1'), 1);

$r = pomodoroGravar($pdo, 1, 180, $fim - 60);
confere('ciclo longo que engole o anterior também é recusado', $r['sobrepoe'], true);

$r = pomodoroGravar($pdo, 1, 25, $fim + 25 * 60);
confere('ciclo logo em seguida (sem cruzar) é aceito', $r, ['novo' => true, 'sobrepoe' => false]);

$r = pomodoroGravar($pdo, 1, 25, $fim + 25 * 60 + 30 + 25 * 60);
confere('a folga de 1 minuto aceita relógio meio adiantado', $r['novo'], true);

$criaUsuario(2, 'bia@teste.dev');
$r = pomodoroGravar($pdo, 2, 25, $fim + 10 * 60);
confere('o ciclo de outra conta no mesmo horário não conta como sobreposto', $r['novo'], true);

confere('conquistas contam só os ciclos aceitos',
    (int) $um('SELECT COUNT(*) FROM pomodoro_sessoes WHERE usuario_id = 1 AND minutos >= 15'), 3);

// ---------------------------------------------------------------
echo "XP do Pomodoro (ProgressoService.php)\n";

$criaUsuario(3, 'caio@teste.dev');
$xpPomodoro = ProgressoService::XP['pomodoro_concluido'];
$progresso  = progressoRegistrar($pdo, 3, [[
    'acao' => 'pomodoro_concluido', 'xp' => $xpPomodoro, 'detalhes' => ['minutos' => 25],
]]);
confere('registrar um Pomodoro devolve o progresso', is_array($progresso), true);
confere('o XP entra no histórico',
    (int) $um("SELECT COALESCE(SUM(quantidade_xp), 0) FROM historico_xp WHERE usuario_id = 3 AND origem_acao = 'pomodoro_concluido'"),
    $xpPomodoro);
confere('o XP soma no progresso da conta',
    (int) $um('SELECT xp_atual FROM usuario_progresso WHERE usuario_id = 3') >= $xpPomodoro, true);

// ---------------------------------------------------------------
echo "Limite de tentativas (limite_util.php)\n";

$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
$alvo = 'alvo@teste.dev';
confere('sem tentativas, não há limite', limiteExcedido($pdo, 'login', $alvo), false);

for ($i = 0; $i < LIMITES['login']['email']; $i++) {
    registrarTentativa($pdo, 'login', $alvo);
}
confere('5 senhas erradas no mesmo e-mail travam o login', limiteExcedido($pdo, 'login', $alvo), true);
confere('o limite de login não trava a recuperação de senha', limiteExcedido($pdo, 'reset', $alvo), false);

limparTentativas($pdo, 'login', $alvo);
confere('login certo libera aquele e-mail', limiteExcedido($pdo, 'login', $alvo), false);

for ($i = 0; $i < LIMITES['login']['ip']; $i++) {
    registrarTentativa($pdo, 'login', "conta$i@teste.dev");
}
confere('muitas contas tentadas do mesmo IP travam até um e-mail novo',
    limiteExcedido($pdo, 'login', 'novo@teste.dev'), true);

$_SERVER['REMOTE_ADDR'] = '198.51.100.9';
confere('outro IP, com e-mail novo, segue livre', limiteExcedido($pdo, 'login', 'novo@teste.dev'), false);

// ---------------------------------------------------------------
echo "\n" . ($total - $falhas) . " de $total testes passaram.\n";
exit($falhas > 0 ? 1 : 0);
