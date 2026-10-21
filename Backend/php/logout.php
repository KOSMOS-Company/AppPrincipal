<?php
// ============================================================
//  KOSMOS — Sair da conta (logout)
//  Arquivo: backend/php/logout.php
//  POST: apaga a sessão atual do servidor.
//
//  Só POST, e só do próprio site: antes qualquer página da internet
//  podia deslogar a pessoa com um simples <img src=".../logout.php">
//  (o navegador manda o cookie em GETs vindos de outro site).
// ============================================================

require_once __DIR__ . '/api_util.php';
require_once __DIR__ . '/sessao.php';

apiExigirPost();
exigirMesmaOrigem();

// encerrarSessao() limpa os dados, destrói a sessão no servidor e também
// expira o cookie no navegador (só session_destroy deixava o PHPSESSID lá)
encerrarSessao();

apiResponder(['ok' => true, 'msg' => 'Logout realizado.']);
