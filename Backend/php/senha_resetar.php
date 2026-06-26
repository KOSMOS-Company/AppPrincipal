<?php
// ============================================================
//  KOSMOS — Redefinir senha (a partir do link do e-mail)
//  Arquivo: backend/php/senha_resetar.php
//  POST { token, senha_nova }: valida o token (não expirado),
//  grava a nova senha e invalida o token (uso único).
// ============================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método não permitido.']);
    exit;
}

$token = trim($_POST['token']      ?? '');
$nova  = trim($_POST['senha_nova'] ?? '');

if ($token === '') {
    echo json_encode(['ok' => false, 'msg' => 'Token ausente.']);
    exit;
}
if (mb_strlen($nova) < 8) {
    echo json_encode(['ok' => false, 'msg' => 'A nova senha deve ter no mínimo 8 caracteres.']);
    exit;
}

try {
    $pdo  = conectar();
    $hash = hash('sha256', $token);

    // Token válido E ainda não expirado
    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE reset_token = ? AND reset_expira > NOW() LIMIT 1');
    $stmt->execute([$hash]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(['ok' => false, 'msg' => 'Link inválido ou expirado. Solicite um novo.']);
        exit;
    }

    // Grava a nova senha e invalida o token (uso único)
    $novoHash = password_hash($nova, PASSWORD_BCRYPT, ['cost' => 12]);
    $upd = $pdo->prepare('UPDATE usuarios SET senha_hash = ?, reset_token = NULL, reset_expira = NULL WHERE id = ?');
    $upd->execute([$novoHash, $user['id']]);

    echo json_encode(['ok' => true, 'msg' => 'Senha redefinida com sucesso! Você já pode entrar.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Não foi possível redefinir a senha.']);
}
