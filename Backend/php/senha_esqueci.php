<?php
// ============================================================
//  KOSMOS — Esqueci a senha (gera token e pede e-mail ao n8n)
//  Arquivo: backend/php/senha_esqueci.php
//  POST { email }: se o e-mail existir, gera um token de reset,
//  salva (hasheado, com validade) e chama o webhook do n8n que
//  envia o e-mail com o link de redefinição.
// ============================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método não permitido.']);
    exit;
}

$email = trim($_POST['email'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'msg' => 'Informe um e-mail válido.']);
    exit;
}

// Resposta SEMPRE genérica (não revela se o e-mail existe ou não)
$respostaGenerica = [
    'ok'  => true,
    'msg' => 'Se este e-mail estiver cadastrado, enviamos um link para redefinir a senha.',
];

try {
    $pdo  = conectar();
    $stmt = $pdo->prepare('SELECT id, nome FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Se não existe, respondemos genérico (sem fazer nada)
    if (!$user) {
        echo json_encode($respostaGenerica);
        exit;
    }

    // Gera o token: o valor "puro" vai no link; no banco guardamos só o hash
    $token   = bin2hex(random_bytes(32));
    $hash    = hash('sha256', $token);
    $expira  = date('Y-m-d H:i:s', time() + 3600); // validade de 1 hora

    $upd = $pdo->prepare('UPDATE usuarios SET reset_token = ?, reset_expira = ? WHERE id = ?');
    $upd->execute([$hash, $expira, $user['id']]);

    // Monta o link e pede ao n8n para enviar o e-mail
    $link = APP_URL . '/login/redefinir.html?token=' . $token;

    $payload = json_encode([
        'email' => $email,
        'nome'  => $user['nome'],
        'link'  => $link,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init(N8N_RESET_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            N8N_TOKEN_HEADER . ': ' . N8N_TOKEN_VALOR,
        ],
        CURLOPT_TIMEOUT        => 20,
    ]);
    curl_exec($ch);   // se falhar, ignoramos (resposta continua genérica)
    curl_close($ch);

    echo json_encode($respostaGenerica);
} catch (PDOException $e) {
    // Mesmo em erro interno, não vazamos detalhes
    echo json_encode($respostaGenerica);
}
