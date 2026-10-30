<?php
// ============================================================
//  KOSMOS — E-mails com link (redefinição de senha e confirmação)
//  Arquivo: Backend/php/email_util.php
//
//  Os dois fluxos fazem a mesma coisa: montam um link para uma página
//  do frontend e pedem ao n8n (servidor → servidor, com o token
//  secreto) que envie o e-mail. Antes isso morava dentro do
//  senha_esqueci.php; com a confirmação de e-mail virou um lugar só.
// ============================================================

require_once __DIR__ . '/config.php';

/**
 * Endereço das páginas do frontend, para montar o link do e-mail.
 *
 * Em produção o APP_URL do config.php é OBRIGATÓRIO. Montar o link a
 * partir do cabeçalho Host da requisição deixaria um atacante pedir o
 * e-mail da vítima com Host: site-dele.com — e o e-mail verdadeiro
 * levaria o token para lá ("reset poisoning").
 *
 * Em desenvolvimento (localhost) o APP_URL pode ficar vazio: o link é
 * descoberto pela pasta onde o app está, em qualquer instalação.
 */
function urlDoFrontend(): ?string {
    if (defined('APP_URL') && APP_URL !== '') {
        return rtrim(APP_URL, '/');
    }
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if (!preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$/i', $host)) {
        error_log('[KOSMOS e-mail] APP_URL vazio no config.php fora do localhost: e-mail não enviado.');
        return null;
    }
    // .../Backend/php/<endpoint>.php -> raiz do app
    $raiz = dirname(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')), 3);
    $esquema = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
    return $esquema . '://' . $host . rtrim($raiz, '/') . '/Frontend/pages';
}

/**
 * Pede ao fluxo do n8n em $url que envie um e-mail com $dados
 * (email, nome, link). Devolve se o n8n aceitou. Falha não aparece
 * para a pessoa (as respostas são genéricas); vai para o log com o
 * $rotulo, para quem mantém o app saber que o e-mail não saiu.
 */
function n8nEnviarEmail(string $url, array $dados, string $rotulo): bool {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($dados, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            N8N_TOKEN_HEADER . ': ' . N8N_TOKEN_VALOR,
        ],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false || $code >= 400) {
        error_log("[KOSMOS $rotulo] n8n não enviou o e-mail (HTTP $code).");
        return false;
    }
    return true;
}

/* ============================================================
   CONFIRMAÇÃO DO E-MAIL DO CADASTRO
   Ligada quando o config.php tem o N8N_CONFIRMA_URL (o fluxo do n8n
   que envia o link). Vazio = desligada: o cadastro cria a conta já
   confirmada, como era antes — o site não trava esperando um e-mail
   que nunca vai sair.
   ============================================================ */

/** Quanto tempo o link de confirmação vale. */
const CONFIRMA_HORAS = 48;

function confirmacaoLigada(): bool {
    return defined('N8N_CONFIRMA_URL') && N8N_CONFIRMA_URL !== '';
}

/**
 * Gera um token novo para a conta e manda o link de confirmação.
 * Igual ao da senha: o token puro vai só no link; o banco guarda o
 * hash. Um link novo invalida o anterior.
 */
function enviarConfirmacao(PDO $pdo, int $usuarioId, string $email, string $nome): bool {
    $base = urlDoFrontend();
    if ($base === null || !confirmacaoLigada()) {
        return false;
    }

    $token = bin2hex(random_bytes(32));
    $pdo->prepare('UPDATE usuarios
                      SET confirma_token = ?, confirma_expira = NOW() + INTERVAL ' . CONFIRMA_HORAS . ' HOUR
                    WHERE id = ?')
        ->execute([hash('sha256', $token), $usuarioId]);

    return n8nEnviarEmail(N8N_CONFIRMA_URL, [
        'email' => $email,
        'nome'  => $nome,
        'link'  => $base . '/login/confirmar.html?token=' . $token,
    ], 'confirmação');
}
