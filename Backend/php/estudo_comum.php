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

require_once __DIR__ . '/api_util.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/sessao.php';

/* Atalhos com o prefixo da área — só repassam para o api_util.php.
   estResponder() já põe o "ok": true, como sempre pôs. */
function estResponder(array $dados, int $codigo = 200): void { apiResponder(['ok' => true] + $dados, $codigo); }
function estErro(string $msg, int $codigo = 400): void       { apiErro($msg, $codigo); }
function estExigirPost(): void                                { apiExigirPost(); }

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
