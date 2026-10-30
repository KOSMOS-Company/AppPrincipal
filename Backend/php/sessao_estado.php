<?php
// ============================================================
//  KOSMOS — "Já tem alguém logado neste navegador?"
//  Arquivo: Backend/php/sessao_estado.php
//  GET, sem campos: { ok: true, logado: true|false }
//
//  Usado pelo shared/ja-logado.js na página inicial, no login e no
//  cadastro: quem já está logado (inclusive pelo "Lembrar de mim",
//  que o usuarioLogado() restaura aqui) vai direto para o dashboard,
//  em vez de ver o formulário de novo. Sempre 200 — o usuario_atual.php
//  responde 401 para visitante, e isso sujaria o console de toda
//  visita à página inicial.
// ============================================================

require_once __DIR__ . '/api_util.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/sessao.php';

$usuario = usuarioLogado();
$logado  = false;

if ($usuario !== null) {
    try {
        // "sair de todos os dispositivos" feito em outro lugar derruba esta sessão
        $logado = sessaoAindaValida(conectar(), (int) $usuario['id']);
        if (!$logado) {
            encerrarSessao();
        }
    } catch (PDOException $e) {
        registrarErro($e);   // banco fora: trata como deslogado, o login mostra o erro
    }
}

liberarSessao();
apiResponder(['ok' => true, 'logado' => $logado]);
