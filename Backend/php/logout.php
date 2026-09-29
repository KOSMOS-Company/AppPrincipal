<?php
// ============================================================
//  KOSMOS — Sair da conta (logout)
//  Arquivo: backend/php/logout.php
//  Apaga a sessão atual do servidor.
// ============================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/sessao.php';

// encerrarSessao() limpa os dados, destrói a sessão no servidor e também
// expira o cookie no navegador (só session_destroy deixava o PHPSESSID lá)
encerrarSessao();

echo json_encode(['ok' => true, 'msg' => 'Logout realizado.']);
