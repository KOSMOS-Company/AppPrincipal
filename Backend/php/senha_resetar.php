<?php
// ============================================================
//  KOSMOS — Redefinir senha (a partir do link do e-mail)
//  Arquivo: backend/php/senha_resetar.php
//  POST { token, senha_nova }: valida o token (não expirado),
//  grava a nova senha e invalida o token (uso único).
// ============================================================

require_once __DIR__ . '/api_util.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/senha_util.php';

apiExigirPost();
exigirMesmaOrigem();

$token = trim($_POST['token']      ?? '');
$nova  = lerSenha('senha_nova');

// O token do e-mail é sempre 64 caracteres hexadecimais (32 bytes)
if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
    apiErro('Link inválido ou expirado. Solicite um novo.', 400);
}
if (($erro = erroNovaSenha($nova)) !== null) {
    apiErro($erro, 422);
}

try {
    $pdo  = conectar();
    $hash = hash('sha256', $token);

    // Token válido E ainda não expirado
    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE reset_token = ? AND reset_expira > NOW() LIMIT 1');
    $stmt->execute([$hash]);
    $user = $stmt->fetch();

    if (!$user) {
        apiErro('Link inválido ou expirado. Solicite um novo.', 400);
    }

    // Grava a nova senha, invalida o token (uso único) e sobe a geração de
    // sessões: se a senha foi redefinida porque vazou, quem estava logado
    // com ela cai no próximo acesso (ver sessaoAindaValida em sessao.php)
    $upd = $pdo->prepare('UPDATE usuarios
                             SET senha_hash = ?, reset_token = NULL, reset_expira = NULL,
                                 sessoes_versao = sessoes_versao + 1
                           WHERE id = ?');
    $upd->execute([gerarHashSenha($nova), $user['id']]);

    apiResponder(['ok' => true, 'msg' => 'Senha redefinida com sucesso! Você já pode entrar.']);
} catch (PDOException $e) {
    registrarErro($e, 'redefinir senha');
    apiErro('Não foi possível redefinir a senha.', 500);
}
