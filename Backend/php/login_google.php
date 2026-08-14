<?php
// ============================================================
//  KOSMOS — Login com Google
//  Arquivo: backend/php/login_google.php
//  Recebe o ID token (JWT) do "Sign in with Google", verifica
//  no Google, cria/loga o usuário e abre a sessão.
// ============================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método não permitido.']);
    exit;
}

$token = $_POST['credential'] ?? '';
if ($token === '') {
    echo json_encode(['ok' => false, 'msg' => 'Token do Google ausente.']);
    exit;
}

if (!extension_loaded('curl')) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Extensão curl não está habilitada no PHP.']);
    exit;
}

// ---------- Verifica o token no Google ----------
$ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($token));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($resp === false || $code !== 200) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Não foi possível validar a conta Google.']);
    exit;
}

$info = json_decode($resp, true);

// O token tem que ser para ESTE app (audience = nosso Client ID)
if (!is_array($info) || ($info['aud'] ?? '') !== GOOGLE_CLIENT_ID) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Token do Google inválido para este app.']);
    exit;
}

$emailVerificado = ($info['email_verified'] ?? '') === 'true' || ($info['email_verified'] ?? false) === true;
$email    = trim($info['email'] ?? '');
$googleId = $info['sub'] ?? '';

if (!$emailVerificado || $email === '') {
    echo json_encode(['ok' => false, 'msg' => 'A conta Google não tem e-mail verificado.']);
    exit;
}

$nome = trim($info['name'] ?? '');
if ($nome === '') {
    $nome = explode('@', $email)[0];
}

// ---------- Cria ou encontra o usuário ----------
try {
    $pdo = conectar();

    $stmt = $pdo->prepare('SELECT id, nome, google_id FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Já existe: vincula o google_id se ainda não tiver
        if (empty($user['google_id'])) {
            $pdo->prepare('UPDATE usuarios SET google_id = ? WHERE id = ?')
                ->execute([$googleId, $user['id']]);
        }
        $id        = (int) $user['id'];
        $nomeFinal = $user['nome'];
    } else {
        // Não existe: cria conta nova (sem senha)
        $ins = $pdo->prepare('INSERT INTO usuarios (nome, email, google_id) VALUES (?, ?, ?)');
        $ins->execute([$nome, $email, $googleId]);
        $id        = (int) $pdo->lastInsertId();
        $nomeFinal = $nome;
    }

    // Abre a sessão (igual ao login normal)
    iniciarSessao();
    $_SESSION['usuario_id']   = $id;
    $_SESSION['usuario_nome'] = $nomeFinal;
    registrarAcesso($pdo, $id);

    echo json_encode(['ok' => true, 'msg' => 'Login com Google realizado!', 'nome' => $nomeFinal]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Erro ao entrar com o Google.']);
}
