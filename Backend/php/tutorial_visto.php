<?php
// ============================================================
//  KOSMOS — Marca o tutorial do Orion como visto
//  Arquivo: Backend/php/tutorial_visto.php
//  POST (protegido): grava tutorial_visto = 1. O js/tutorial.js chama
//  no instante em que o tour começa — assim ele aparece uma vez só,
//  mesmo que a pessoa feche a aba no meio (mesma regra do onboarding).
// ============================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/sessao.php';

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

    $pdo->prepare('INSERT INTO usuario_preferencias (usuario_id, tutorial_visto) VALUES (?, 1)
                   ON DUPLICATE KEY UPDATE tutorial_visto = GREATEST(tutorial_visto, 1)')
        ->execute([$id]);

    echo json_encode(['ok' => true]);
} catch (PDOException $e) {
    registrarErro($e);
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Não foi possível salvar.']);
}
