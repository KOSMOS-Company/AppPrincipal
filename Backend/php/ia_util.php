<?php
// ============================================================
//  KOSMOS — Ponte com o fluxo "Kosmos — IA" do n8n
//  Arquivo: Backend/php/ia_util.php
//
//  O fluxo tenta Groq, depois Gemini, depois OpenRouter: se uma IA
//  falha ou fica sem cota, a próxima responde. Aqui só existe o
//  pedido: { prompt } vai, { output, modelo } volta. Quem monta o
//  prompt e lê a resposta é cada recurso (exercícios do resumo,
//  flashcards, rascunho de resumo).
//
//  Precisa, no config.php: N8N_IA_URL e o N8N_TOKEN_* de sempre.
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/exercicios_ia_util.php';   // indicioDeLimiteDiario()

/** A URL do fluxo foi configurada? Sem ela os recursos de IA ficam fora. */
function iaDisponivel(): bool {
    return defined('N8N_IA_URL') && N8N_IA_URL !== '';
}

/**
 * Manda o prompt para o fluxo e devolve o texto da IA que respondeu.
 * Falhas viram RuntimeException com o código HTTP que a tela deve
 * receber: 429 (todas sem cota), 502 (falha ou resposta vazia) e
 * 503 (fluxo não configurado).
 */
function iaPerguntar(string $prompt, int $timeoutS = 90): string {
    if (!iaDisponivel()) {
        throw new RuntimeException('IA não configurada', 503);
    }

    $ch = curl_init(N8N_IA_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['prompt' => $prompt], JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            N8N_TOKEN_HEADER . ': ' . N8N_TOKEN_VALOR,
        ],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => $timeoutS,
    ]);
    $resposta = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erroRede = curl_error($ch);
    curl_close($ch);

    $corpo = is_string($resposta) ? $resposta : '';

    if ($resposta === false || $httpCode >= 400) {
        error_log(sprintf('[KOSMOS IA] fluxo respondeu HTTP %d%s: %s',
            $httpCode, $erroRede !== '' ? " ($erroRede)" : '', mb_substr($corpo, 0, 300)));
        throw new RuntimeException('falha na IA', indicioDeLimiteDiario($corpo) ? 429 : 502);
    }

    $dados = json_decode($corpo, true);
    $texto = is_array($dados) && is_string($dados['output'] ?? null) ? trim($dados['output']) : '';

    if ($texto === '') {
        error_log('[KOSMOS IA] resposta sem texto: ' . mb_substr($corpo, 0, 300));
        throw new RuntimeException('resposta vazia', 502);
    }
    if (indicioDeLimiteDiario($texto) && mb_strlen($texto) < 400) {
        // A IA "respondeu" só o aviso de cota, não o conteúdo
        throw new RuntimeException('limite', 429);
    }

    return $texto;
}
