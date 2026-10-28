<?php
// ============================================================
//  KOSMOS — Recompensa por terminar o tour do Orion
//  Arquivo: Backend/php/tutorial_concluido.php
//  POST (protegido): 75 XP + a conquista "Decolagem" (25 XP) — os
//  100 XP levam a conta nova direto ao nível 2.
//
//  Uma vez por conta: `tutorial_visto` vira 2 ("premiado") num UPDATE
//  que só pega quem ainda está abaixo de 2. Rever o tour pela Conta,
//  ou mandar o pedido duas vezes, não paga de novo.
//  A resposta traz `progresso`: o js/progresso.js mostra o "+XP", a
//  conquista e a subida de nível sozinho.
// ============================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/ProgressoService.php';

$usuario = exigirLogin();
$id      = (int) $usuario['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método não permitido.']);
    exit;
}

try {
    $pdo = conectar();
    liberarSessao();

    $pdo->beginTransaction();
    $pdo->prepare('INSERT IGNORE INTO usuario_preferencias (usuario_id) VALUES (?)')->execute([$id]);
    $marca = $pdo->prepare('UPDATE usuario_preferencias SET tutorial_visto = 2 WHERE usuario_id = ? AND tutorial_visto < 2');
    $marca->execute([$id]);

    if ($marca->rowCount() !== 1) {
        $pdo->commit();
        echo json_encode(['ok' => true, 'premiado' => false]);   // já tinha recebido
        exit;
    }

    $progresso = (new ProgressoService($pdo))->premiar($id, [[
        'acao' => 'tutorial_concluido',
        'xp'   => ProgressoService::XP['tutorial_concluido'],
    ]]);
    $pdo->commit();

    echo json_encode(['ok' => true, 'premiado' => true, 'progresso' => $progresso]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    registrarErro($e);
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Não foi possível entregar a recompensa.']);
}
