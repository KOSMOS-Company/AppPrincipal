<?php
// ============================================================
//  KOSMOS — Trocar senha
//  Arquivo: backend/php/conta_senha.php
//  POST (protegido): confere a senha atual e grava a nova.
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

$atual = trim($_POST['senha_atual'] ?? '');
$nova  = trim($_POST['senha_nova']  ?? '');

if ($nova === '') {
    echo json_encode(['ok' => false, 'msg' => 'Informe a nova senha.']);
    exit;
}
if (mb_strlen($nova) < 8) {
    echo json_encode(['ok' => false, 'msg' => 'A nova senha deve ter no mínimo 8 caracteres.']);
    exit;
}

try {
    $pdo  = conectar();
    $stmt = $pdo->prepare('SELECT senha_hash FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->execute([$usuario['id']]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'msg' => 'Usuário não encontrado.']);
        exit;
    }

    $temSenha = !empty($row['senha_hash']);

    if ($temSenha) {
        // Conta com senha: precisa confirmar a senha atual
        if ($atual === '') {
            echo json_encode(['ok' => false, 'msg' => 'Informe a senha atual.']);
            exit;
        }
        if (!password_verify($atual, $row['senha_hash'])) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'msg' => 'A senha atual está incorreta.']);
            exit;
        }
        if (password_verify($nova, $row['senha_hash'])) {
            echo json_encode(['ok' => false, 'msg' => 'A nova senha deve ser diferente da atual.']);
            exit;
        }
    }
    // Conta só do Google (sem senha): cria a senha sem pedir a atual

    $novoHash = password_hash($nova, PASSWORD_BCRYPT, ['cost' => 12]);
    // Além da senha: descarta um link de "esqueci a senha" pendente (senão ele
    // ainda trocaria a senha nova) e sobe a geração de sessões, derrubando
    // os outros dispositivos logados com a senha antiga
    $upd = $pdo->prepare('UPDATE usuarios
                             SET senha_hash = ?, reset_token = NULL, reset_expira = NULL,
                                 sessoes_versao = sessoes_versao + 1
                           WHERE id = ?');
    $upd->execute([$novoHash, $usuario['id']]);

    // ...mas quem trocou a senha continua logado aqui
    marcarVersaoSessao($pdo, (int) $usuario['id']);

    $msg = $temSenha
        ? 'Senha alterada com sucesso!'
        : 'Senha criada! Agora você também pode entrar com e-mail e senha.';

    echo json_encode(['ok' => true, 'msg' => $msg]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Não foi possível salvar a senha.']);
}
