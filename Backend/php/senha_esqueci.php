<?php
// ============================================================
//  KOSMOS — Esqueci a senha (gera token e pede e-mail ao n8n)
//  Arquivo: backend/php/senha_esqueci.php
//  POST { email }: se o e-mail existir, gera um token de reset,
//  salva (hasheado, com validade) e chama o webhook do n8n que
//  envia o e-mail com o link de redefinição.
// ============================================================

require_once __DIR__ . '/api_util.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/limite_util.php';

apiExigirPost();
exigirMesmaOrigem();

$email = trim($_POST['email'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    apiErro('Informe um e-mail válido.', 422);
}

// Resposta SEMPRE genérica (não revela se o e-mail existe ou não)
$respostaGenerica = [
    'ok'  => true,
    'msg' => 'Se este e-mail estiver cadastrado, enviamos um link para redefinir a senha.',
];

/**
 * Endereço das páginas do frontend, para montar o link do e-mail.
 *
 * Em produção o APP_URL do config.php é OBRIGATÓRIO. Montar o link a
 * partir do cabeçalho Host da requisição deixaria um atacante pedir o
 * reset da vítima com Host: site-dele.com — e o e-mail verdadeiro
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
        error_log('[KOSMOS reset] APP_URL vazio no config.php fora do localhost: e-mail não enviado.');
        return null;
    }
    // .../Backend/php/senha_esqueci.php -> raiz do app
    $raiz = dirname(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')), 3);
    $esquema = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
    return $esquema . '://' . $host . rtrim($raiz, '/') . '/Frontend/pages';
}

try {
    $pdo = conectar();

    // Limite de pedidos (ver limite_util.php): sem isso dava para lotar a
    // caixa de alguém de e-mails. Conta mesmo quando o e-mail não existe,
    // e a resposta continua genérica para não revelar se a conta existe.
    if (limiteExcedido($pdo, 'reset', $email)) {
        apiResponder($respostaGenerica);
    }
    registrarTentativa($pdo, 'reset', $email);

    $stmt = $pdo->prepare('SELECT id, nome FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Se não existe, respondemos genérico (sem fazer nada)
    $base = urlDoFrontend();
    if (!$user || $base === null) {
        apiResponder($respostaGenerica);
    }

    // Gera o token: o valor "puro" vai no link; no banco guardamos só o hash
    $token = bin2hex(random_bytes(32));
    $hash  = hash('sha256', $token);

    // Validade de 1 hora pelo relógio do MySQL (o senha_resetar.php
    // compara com NOW() do banco — um relógio só)
    $upd = $pdo->prepare('UPDATE usuarios SET reset_token = ?, reset_expira = NOW() + INTERVAL 1 HOUR WHERE id = ?');
    $upd->execute([$hash, $user['id']]);

    // Monta o link e pede ao n8n para enviar o e-mail
    $payload = json_encode([
        'email' => $email,
        'nome'  => $user['nome'],
        'link'  => $base . '/login/redefinir.html?token=' . $token,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init(N8N_RESET_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
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
    // A pessoa continua vendo a resposta genérica; quem mantém o app
    // fica sabendo pelo log que o e-mail não saiu
    if ($resp === false || $code >= 400) {
        error_log("[KOSMOS reset] n8n não enviou o e-mail (HTTP $code).");
    }

    apiResponder($respostaGenerica);
} catch (PDOException $e) {
    // Mesmo em erro interno, não vazamos detalhes
    registrarErro($e, 'esqueci a senha');
    apiResponder($respostaGenerica);
}
