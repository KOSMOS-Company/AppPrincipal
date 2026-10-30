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
require_once __DIR__ . '/email_util.php';
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

// O link do e-mail sai de urlDoFrontend() (email_util.php): em produção,
// só do APP_URL — nunca do cabeçalho Host ("reset poisoning").

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

    // Monta o link e pede ao n8n para enviar o e-mail (email_util.php).
    // A pessoa continua vendo a resposta genérica; quem mantém o app
    // fica sabendo pelo log se o e-mail não saiu.
    n8nEnviarEmail(N8N_RESET_URL, [
        'email' => $email,
        'nome'  => $user['nome'],
        'link'  => $base . '/login/redefinir.html?token=' . $token,
    ], 'reset');

    apiResponder($respostaGenerica);
} catch (PDOException $e) {
    // Mesmo em erro interno, não vazamos detalhes
    registrarErro($e, 'esqueci a senha');
    apiResponder($respostaGenerica);
}
