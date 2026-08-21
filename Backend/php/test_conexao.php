<?php
// Teste rápido de conexão ao banco (acesso via navegador)
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/conexao.php';

if (!extension_loaded('pdo_mysql')) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Extensão pdo_mysql não está habilitada no PHP.']);
    exit;
}

try {
    $pdo = conectar();
    echo json_encode(['ok' => true, 'msg' => 'Conexão com MySQL estabelecida.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
