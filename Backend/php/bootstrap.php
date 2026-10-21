<?php
// ============================================================
//  KOSMOS — Ambiente comum a toda requisição do backend
//  Arquivo: Backend/php/bootstrap.php
//
//  Carregado pelo conexao.php (e, por garantia, pelo sessao.php e
//  pelo api_util.php), então todo endpoint e toda página da
//  dashboard passam por aqui antes de qualquer outra coisa.
//
//  O que ele resolve, num lugar só:
//   1. FUSO HORÁRIO — PHP e MySQL no mesmo relógio (America/Sao_Paulo).
//      Antes o PHP do XAMPP rodava em Europe/Berlin e o MySQL em
//      São Paulo; isso já fez a sequência de dias somar +1 a cada
//      recarga. A regra "datas só no MySQL" continua valendo, mas
//      agora um código novo que use date() não reabre o bug.
//   2. ERROS — nunca na tela (quebrariam o JSON e mostrariam caminhos
//      do servidor), sempre em Backend/logs/php-erros.log.
//   3. CABEÇALHOS DE SEGURANÇA — os mesmos do .htaccess da raiz, para
//      valerem também fora do Apache.
//   4. CONEXÃO — conectar() mora aqui (versionado) e o conexao.php,
//      que não vai para o git, guarda só as credenciais.
// ============================================================

if (defined('KOSMOS_BOOTSTRAP')) {
    return;
}
define('KOSMOS_BOOTSTRAP', true);

// ---------- 1) Fuso horário ----------
/** Fuso do app. O Brasil não tem horário de verão desde 2019. */
const APP_FUSO       = 'America/Sao_Paulo';
/** O mesmo fuso para o MySQL, como deslocamento: as tabelas de fuso
 *  por nome ('America/Sao_Paulo') não vêm carregadas no MariaDB do
 *  XAMPP, e o deslocamento funciona em qualquer servidor. */
const APP_FUSO_MYSQL = '-03:00';

date_default_timezone_set(APP_FUSO);

// ---------- 2) Erros vão para o log ----------
const APP_PASTA_LOGS = __DIR__ . '/../logs';

if (PHP_SAPI !== 'cli') {
    // No terminal (migrar.php, testes) o erro na tela é o que se quer
    ini_set('display_errors', '0');
}
ini_set('log_errors', '1');
if (is_dir(APP_PASTA_LOGS) || @mkdir(APP_PASTA_LOGS, 0775, true)) {
    ini_set('error_log', APP_PASTA_LOGS . '/php-erros.log');
}

/**
 * Registra uma exceção capturada. Chame no catch, antes de responder
 * a mensagem amigável: a pessoa vê "não deu", e quem mantém o app
 * descobre o porquê no log.
 */
function registrarErro(Throwable $e, string $contexto = ''): void {
    error_log(sprintf(
        '[KOSMOS] %s%s: %s em %s:%d | %s %s',
        $contexto !== '' ? $contexto . ' — ' : '',
        get_class($e),
        $e->getMessage(),
        basename($e->getFile()),
        $e->getLine(),
        $_SERVER['REQUEST_METHOD'] ?? 'CLI',
        $_SERVER['REQUEST_URI'] ?? implode(' ', $_SERVER['argv'] ?? [])
    ));
}

// Última rede: exceção que ninguém capturou vira log + resposta 500,
// em vez de uma tela branca (ou do stack trace, com display_errors).
set_exception_handler(function (Throwable $e): void {
    registrarErro($e, 'não capturada');
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $e->getMessage() . PHP_EOL);
        exit(1);
    }
    // Endpoint (Backend/php/) responde JSON; página da dashboard, texto
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $ehApi  = strpos($script, '/Backend/php/') !== false;
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: ' . ($ehApi ? 'application/json' : 'text/html') . '; charset=utf-8');
    }
    echo $ehApi
        ? json_encode(['ok' => false, 'msg' => 'Erro interno. Tente novamente.'])
        : '<p style="font-family:sans-serif">Algo deu errado do nosso lado. Recarregue a página em instantes.</p>';
});

// ---------- 3) Cabeçalhos de segurança ----------
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header("Content-Security-Policy: frame-ancestors 'self'; base-uri 'self'; object-src 'none'; form-action 'self'");
    // HSTS só faz sentido (e só é aceito) sob HTTPS
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

// ---------- 4) Conexão com o banco ----------
// Um conexao.php antigo (de antes deste arquivo) ainda define a
// própria conectar(); nesse caso ela é respeitada, e só o fuso do
// MySQL fica de fora. Atualize copiando de novo o conexao.example.php.
if (!function_exists('conectar')) {
    function conectar(): PDO {
        $dsn = 'mysql:host=' . DB_HOST
             . ';dbname='    . DB_NAME
             . ';charset='   . (defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4');

        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        // NOW(), CURDATE() e FROM_UNIXTIME() no mesmo fuso do PHP,
        // seja qual for o fuso configurado no servidor MySQL
        $pdo->exec("SET time_zone = '" . APP_FUSO_MYSQL . "'");
        return $pdo;
    }
}
