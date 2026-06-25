<?php
// ============================================================
//  KOSMOS — Atualizar perfil (nome e e-mail)
//  Arquivo: backend/php/conta_perfil.php
//  POST (protegido): valida e salva nome/e-mail do usuário logado.
// ============================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/sessao.php';

$usuario = exigirLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método não permitido.']);
    exit;
}

// ---------- Lê e valida (mesmas regras do cadastro) ----------
$nome  = trim($_POST['nome']  ?? '');
$email = trim($_POST['email'] ?? '');

$erros = [];

if ($nome === '') {
    $erros[] = 'O nome é obrigatório.';
} elseif (mb_strlen($nome) < 3) {
    $erros[] = 'O nome deve ter pelo menos 3 caracteres.';
} elseif (mb_strlen($nome) > 120) {
    $erros[] = 'O nome deve ter no máximo 120 caracteres.';
}

if ($email === '') {
    $erros[] = 'O e-mail é obrigatório.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $erros[] = 'Informe um e-mail válido.';
} elseif (mb_strlen($email) > 180) {
    $erros[] = 'E-mail muito longo.';
}

if (!empty($erros)) {
    echo json_encode(['ok' => false, 'msg' => implode(' ', $erros)]);
    exit;
}

try {
    $pdo = conectar();

    // O e-mail novo não pode pertencer a OUTRO usuário
    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? AND id <> ? LIMIT 1');
    $stmt->execute([$email, $usuario['id']]);
    if ($stmt->fetch()) {
        echo json_encode(['ok' => false, 'msg' => 'Este e-mail já está em uso por outra conta.']);
        exit;
    }

    $upd = $pdo->prepare('UPDATE usuarios SET nome = ?, email = ? WHERE id = ?');
    $upd->execute([$nome, $email, $usuario['id']]);

    // Mantém o nome da sessão em dia (aparece no "Bem-vindo, ...")
    iniciarSessao();
    $_SESSION['usuario_nome'] = $nome;

    echo json_encode([
        'ok'    => true,
        'msg'   => 'Perfil atualizado com sucesso!',
        'nome'  => $nome,
        'email' => $email,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Não foi possível salvar as alterações.']);
}
