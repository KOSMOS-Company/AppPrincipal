<?php
// ============================================================
//  KOSMOS — Confirma o e-mail do cadastro (link do e-mail)
//  Arquivo: Backend/php/confirmar_email.php
//  POST { token }: chamado pela página login/confirmar.html, que lê
//  o token do link. Mesmo esquema da redefinição de senha: o banco
//  guarda só o hash, o link vale 48 h e é de uso único.
//
//  Quem confirma já entra: abrir o link prova que a pessoa é dona do
//  e-mail, e pedir a senha logo depois seria só um passo a mais.
// ============================================================

require_once __DIR__ . '/api_util.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/sessao.php';

apiExigirPost();
exigirMesmaOrigem();

$token = trim((string) ($_POST['token'] ?? ''));

// O token do e-mail é sempre 64 caracteres hexadecimais (32 bytes)
if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
    apiErro('Link inválido ou expirado. Peça um novo na tela de login.', 400);
}

try {
    $pdo = conectar();

    $stmt = $pdo->prepare('SELECT id, nome FROM usuarios
                            WHERE confirma_token = ? AND confirma_expira > NOW() AND email_confirmado = 0
                            LIMIT 1');
    $stmt->execute([hash('sha256', $token)]);
    $user = $stmt->fetch();

    if (!$user) {
        apiErro('Link inválido ou expirado. Peça um novo na tela de login.', 400);
    }

    $pdo->prepare('UPDATE usuarios
                      SET email_confirmado = 1, confirma_token = NULL, confirma_expira = NULL
                    WHERE id = ?')
        ->execute([$user['id']]);

    // ---------- Entra direto (mesmos passos do login.php) ----------
    iniciarSessao();
    session_regenerate_id(true);
    $_SESSION['usuario_id']   = $user['id'];
    $_SESSION['usuario_nome'] = $user['nome'];
    registrarAcesso($pdo, (int) $user['id']);
    marcarVersaoSessao($pdo, (int) $user['id']);

    apiResponder([
        'ok'   => true,
        'msg'  => 'E-mail confirmado! Entrando…',
        'nome' => $user['nome'],
    ]);
} catch (PDOException $e) {
    registrarErro($e, 'confirmar e-mail');
    apiErro('Não foi possível confirmar agora. Tente de novo em alguns minutos.', 500);
}
