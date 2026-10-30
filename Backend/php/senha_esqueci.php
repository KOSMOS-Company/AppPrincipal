<?php
// ============================================================
//  KOSMOS — Esqueci a senha (gera token e pede e-mail ao n8n)
//  Arquivo: backend/php/senha_esqueci.php
//  POST { email }: se o e-mail existir, gera um token de reset,
//  salva (hasheado, com validade) e chama o webhook do n8n que
//  envia o e-mail com o link de redefinição.
//
//  E-mail sem conta responde 404 "não encontramos" (antes a resposta
//  era sempre "se estiver cadastrado, enviamos", e quem errou uma letra
//  ficava esperando um e-mail que nunca vinha). Esconder isso não
//  protegia nada: o cadastro já diz "este e-mail já está cadastrado".
//  O que impede testar e-mails em série é o limite de tentativas,
//  que conta todo pedido, exista a conta ou não.
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

// O link do e-mail sai de urlDoFrontend() (email_util.php): em produção,
// só do APP_URL — nunca do cabeçalho Host ("reset poisoning").

try {
    $pdo = conectar();

    // Limite de pedidos (ver limite_util.php): sem isso dava para lotar a
    // caixa de alguém de e-mails, ou testar em série quais e-mails têm
    // conta. Conta ANTES de procurar, exista o e-mail ou não.
    if (limiteExcedido($pdo, 'reset', $email)) {
        apiErro('Muitos pedidos. Tente mais tarde.', 429);
    }
    registrarTentativa($pdo, 'reset', $email);

    $stmt = $pdo->prepare('SELECT id, nome FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        apiErro('E-mail não cadastrado.', 404);
    }

    $base = urlDoFrontend();
    if ($base === null) {
        apiErro('Não foi possível enviar. Tente mais tarde.', 503);
    }

    // Gera o token: o valor "puro" vai no link; no banco guardamos só o hash
    $token = bin2hex(random_bytes(32));
    $hash  = hash('sha256', $token);

    // Validade de 1 hora pelo relógio do MySQL (o senha_resetar.php
    // compara com NOW() do banco — um relógio só)
    $upd = $pdo->prepare('UPDATE usuarios SET reset_token = ?, reset_expira = NOW() + INTERVAL 1 HOUR WHERE id = ?');
    $upd->execute([$hash, $user['id']]);

    // Monta o link e pede ao n8n para enviar o e-mail (email_util.php).
    // Se o n8n falhar, a pessoa fica sabendo (e o motivo vai para o log).
    $enviou = n8nEnviarEmail(N8N_RESET_URL, [
        'email' => $email,
        'nome'  => $user['nome'],
        'link'  => $base . '/login/redefinir.html?token=' . $token,
    ], 'reset');
    if (!$enviou) {
        apiErro('Não foi possível enviar. Tente mais tarde.', 502);
    }

    apiResponder([
        'ok'  => true,
        'msg' => 'Link enviado! Confira seu e-mail.',
    ]);
} catch (PDOException $e) {
    // Erro interno: sem detalhes para a pessoa, o motivo vai para o log
    registrarErro($e, 'esqueci a senha');
    apiErro('Erro no servidor. Tente mais tarde.', 500);
}
