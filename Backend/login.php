<?php
// ============================================================
//  KOSMOS — Autentica o login
//  Arquivo: backend/login.php
// ============================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once __DIR__ . '/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método não permitido.']);
    exit;
}

$email = trim($_POST['email'] ?? '');
$senha = trim($_POST['senha'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $senha === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'msg' => 'Preencha e-mail e senha.']);
    exit;
}

try {
    $pdo  = conectar();
    $stmt = $pdo->prepare('SELECT id, nome, senha_hash FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($senha, $user['senha_hash'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'msg' => 'E-mail ou senha incorretos.']);
        exit;
    }

    echo json_encode([
        'ok'   => true,
        'msg'  => 'Login realizado!',
        'nome' => $user['nome'],
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Erro interno. Tente novamente.']);
}
