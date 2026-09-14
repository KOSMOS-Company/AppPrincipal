<?php
// ============================================================
//  KOSMOS — Helpers dos endpoints de estudo
//  Arquivo: Backend/php/estudo_comum.php
//
//  Mesma forma dos flashcards_comum.php / resumos_comum.php: um
//  prefixo por área para os atalhos de resposta e validação, para
//  os nomes não colidirem quando duas áreas forem carregadas na
//  mesma requisição.
//
//  Serve: pomodoro_sessao.php, inicio_dados.php, revisar_hoje.php,
//  provas.php e busca.php.
// ============================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/sessao.php';

/** Resposta de sucesso. */
function estResponder(array $dados, int $codigo = 200): void {
    http_response_code($codigo);
    echo json_encode(['ok' => true] + $dados, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Resposta de erro. */
function estErro(string $msg, int $codigo = 400): void {
    http_response_code($codigo);
    echo json_encode(['ok' => false, 'msg' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function estExigirPost(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        estErro('Método não permitido.', 405);
    }
}

/**
 * Corpo da requisição, venha ele como JSON ou como formulário.
 * O onboarding_salvar.php já aceitava os dois; aqui isso vira um
 * lugar só, porque todo endpoint novo precisa da mesma coisa.
 */
function estCorpo(): array {
    $bruto = json_decode((string) file_get_contents('php://input'), true);
    return is_array($bruto) ? $bruto : $_POST;
}

/** Inteiro dentro de uma faixa, com padrão. */
function estInt($valor, int $min, int $max, int $padrao): int {
    if (!is_numeric($valor)) {
        return $padrao;
    }
    $n = (int) $valor;
    return $n < $min ? $min : ($n > $max ? $max : $n);
}

/** Texto aparado e limitado; devolve '' se vier vazio. */
function estTexto($valor, int $max): string {
    $t = trim((string) $valor);
    // mb_substr porque o limite é de CARACTERES: cortar por bytes
    // parte acentuada no meio e gera "ó" quebrado no banco.
    return mb_substr($t, 0, $max);
}
