<?php
// ============================================================
//  KOSMOS — Gerar exercícios com IA (via n8n)
//  Arquivo: backend/php/gerar_exercicios.php
//  POST (protegido): recebe materia/dificuldade/qtd, chama o
//  webhook do n8n (servidor->servidor) e devolve as questões.
//  O navegador NUNCA vê a URL do n8n nem o token — só este PHP.
// ============================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/config.php';

// Só usuários logados podem gastar a IA
$usuario = exigirLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método não permitido.']);
    exit;
}

// ---------- Lê e valida a entrada ----------
$materia     = trim($_POST['materia']     ?? '');
$dificuldade = trim($_POST['dificuldade'] ?? 'Médio');
$qtd         = (int) ($_POST['qtd']       ?? 3);

if ($materia === '') {
    echo json_encode(['ok' => false, 'msg' => 'Escolha uma matéria.']);
    exit;
}
if ($qtd < 1 || $qtd > 10) {
    $qtd = 3; // limita para não estourar custo/tempo
}
if (!in_array($dificuldade, ['Fácil', 'Médio', 'Difícil'], true)) {
    $dificuldade = 'Médio';
}

// ---------- Chama o webhook do n8n ----------
$payload = json_encode([
    'materia'     => $materia,
    'dificuldade' => $dificuldade,
    'qtd'         => $qtd,
], JSON_UNESCAPED_UNICODE);

$ch = curl_init(N8N_EXERCICIOS_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        N8N_TOKEN_HEADER . ': ' . N8N_TOKEN_VALOR,
    ],
    CURLOPT_TIMEOUT        => 60,  // a IA pode levar alguns segundos
]);

$resposta = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($resposta === false || $httpCode >= 400) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'msg' => 'Não foi possível gerar os exercícios agora. Tente novamente.']);
    exit;
}

// ---------- Valida o formato esperado: { "questoes": [ ... ] } ----------
$dados = json_decode($resposta, true);

if (!is_array($dados) || empty($dados['questoes']) || !is_array($dados['questoes'])) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'msg' => 'A IA respondeu em um formato inesperado.']);
    exit;
}

echo json_encode(['ok' => true, 'questoes' => $dados['questoes']], JSON_UNESCAPED_UNICODE);
