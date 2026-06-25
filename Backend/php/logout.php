<?php
// ============================================================
//  KOSMOS — Sair da conta (logout)
//  Arquivo: backend/php/logout.php
//  Apaga a sessão atual do servidor.
// ============================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/sessao.php';

iniciarSessao();

$_SESSION = [];          // limpa os dados da sessão
session_destroy();       // destrói a sessão no servidor

echo json_encode(['ok' => true, 'msg' => 'Logout realizado.']);
