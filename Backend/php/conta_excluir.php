<?php
// ============================================================
//  KOSMOS — Excluir a conta (LGPD)
//  Arquivo: backend/php/conta_excluir.php
//  POST (protegido): apaga o usuário e, por causa das FKs com
//  ON DELETE CASCADE, também as preferências, os decks e os
//  cartões dele. Ação irreversível.
//
//  Confirmação exigida (decidida aqui, não pelo frontend):
//   - conta com senha  -> tem que enviar a senha correta;
//   - conta só Google  -> tem que enviar confirmacao = EXCLUIR.
// ============================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/avatar_util.php';

$usuario = exigirLogin();
$id      = (int) $usuario['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método não permitido.']);
    exit;
}

$senha        = (string) ($_POST['senha'] ?? '');
$confirmacao  = strtoupper(trim((string) ($_POST['confirmacao'] ?? '')));

try {
    $pdo = conectar();

    $stmt = $pdo->prepare('SELECT senha_hash FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'msg' => 'Usuário não encontrado.']);
        exit;
    }

    $temSenha = !empty($row['senha_hash']);

    if ($temSenha) {
        if ($senha === '') {
            echo json_encode(['ok' => false, 'msg' => 'Digite sua senha para confirmar.']);
            exit;
        }
        if (!password_verify($senha, $row['senha_hash'])) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'msg' => 'Senha incorreta.']);
            exit;
        }
    } elseif ($confirmacao !== 'EXCLUIR') {
        echo json_encode(['ok' => false, 'msg' => 'Digite EXCLUIR para confirmar.']);
        exit;
    }

    // A foto está no disco, fora do banco: o CASCADE não a apagaria
    apagarAvatarDoUsuario($pdo, $id);

    // Apaga o usuário; o CASCADE leva preferências, decks e cartões
    $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);

    encerrarSessao();

    echo json_encode([
        'ok'  => true,
        'msg' => 'Sua conta e seus dados foram apagados.',
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Não foi possível excluir a conta.']);
}
