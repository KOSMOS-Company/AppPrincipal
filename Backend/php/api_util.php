<?php
// ============================================================
//  KOSMOS — Respostas JSON dos endpoints (fonte única)
//  Arquivo: Backend/php/api_util.php
//
//  Todo endpoint responde no mesmo formato:
//      sucesso -> HTTP 2xx  { "ok": true,  ...dados }
//      erro    -> HTTP 4xx/5xx { "ok": false, "msg": "texto para a pessoa" }
//
//  Códigos usados (ver docs/API.md):
//      400 pedido malformado        401 sem login
//      403 origem/permissão         404 não encontrado (ou não é seu)
//      405 método errado            409 conflito (ex.: e-mail em uso)
//      422 dado inválido            429 tentativas demais
//      500 erro nosso               502 serviço externo (n8n) falhou
//
//  Antes havia três cópias destes atalhos (api* nos resumos, fc* nos
//  flashcards, est* no estudo) e os endpoints mais antigos (login,
//  cadastro, senha, conta) montavam o JSON à mão — alguns devolviam
//  erro com HTTP 200. Os prefixos antigos continuam existindo, mas
//  agora só repassam para estas funções.
// ============================================================

require_once __DIR__ . '/bootstrap.php';

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

/** Responde em JSON e encerra. */
function apiResponder(array $dados, int $codigo = 200): void {
    http_response_code($codigo);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Erro simples ("não deu certo, e o motivo"). */
function apiErro(string $msg, int $codigo = 400, array $extra = []): void {
    apiResponder(['ok' => false, 'msg' => $msg] + $extra, $codigo);
}

/** Recusa qualquer coisa que não seja POST. */
function apiExigirPost(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        apiErro('Método não permitido.', 405);
    }
}
