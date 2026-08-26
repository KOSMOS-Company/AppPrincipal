<?php
// ============================================================
//  KOSMOS — Quem está logado agora?
//  Arquivo: backend/php/usuario_atual.php
//  O frontend chama este endpoint para saber se há sessão ativa
//  e qual o nome do usuário. Usado pelo "porteiro" do dashboard.
// ============================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/sessao.php';

$usuario = usuarioLogado();

if ($usuario === null) {
    http_response_code(401); // ninguém logado
    echo json_encode(['ok' => false]);
    exit;
}

// Registra o acesso de hoje e pega a sequência atual de dias.
// Também informa se a conta já tem senha própria: contas criadas pelo
// login com Google começam sem senha, e o dashboard usa isso para levar
// o usuário à tela de criação de senha.
$sequencia = 0;
$temSenha  = true;   // no erro, não empurra ninguém para a tela de senha
try {
    $pdo = conectar();
    $sequencia = registrarAcesso($pdo, (int) $usuario['id']);

    $stmt = $pdo->prepare('SELECT senha_hash FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->execute([$usuario['id']]);
    $row = $stmt->fetch();
    if ($row) {
        $temSenha = !empty($row['senha_hash']);
    }
} catch (PDOException $e) {
    $sequencia = 0;
}

echo json_encode([
    'ok'        => true,
    'id'        => $usuario['id'],
    'nome'      => $usuario['nome'],
    'sequencia' => $sequencia,
    'tem_senha' => $temSenha,
]);
