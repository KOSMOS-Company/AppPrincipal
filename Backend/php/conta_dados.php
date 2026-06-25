<?php
// ============================================================
//  KOSMOS — Dados da conta do usuário logado
//  Arquivo: backend/php/conta_dados.php
//  GET (protegido): devolve nome, e-mail e data de criação.
// ============================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/sessao.php';

// Porteiro: só passa quem está logado (senão responde 401 e encerra)
$usuario = exigirLogin();

try {
    $pdo  = conectar();
    $stmt = $pdo->prepare('SELECT nome, email, criado_em FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->execute([$usuario['id']]);
    $dados = $stmt->fetch();

    if (!$dados) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'msg' => 'Usuário não encontrado.']);
        exit;
    }

    echo json_encode([
        'ok'        => true,
        'nome'      => $dados['nome'],
        'email'     => $dados['email'],
        'criado_em' => $dados['criado_em'],
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Erro ao carregar os dados da conta.']);
}
