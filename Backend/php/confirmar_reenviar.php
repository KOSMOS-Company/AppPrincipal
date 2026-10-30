<?php
// ============================================================
//  KOSMOS — Reenvia o link de confirmação do e-mail
//  Arquivo: Backend/php/confirmar_reenviar.php
//  POST { email }: botão "Reenviar link" da tela de login, que
//  aparece quando a senha está certa mas o e-mail não foi confirmado.
//
//  Resposta sempre genérica (não revela se a conta existe nem se já
//  foi confirmada) e com limite próprio ('confirma' no limite_util.php):
//  sem ele, dava para lotar a caixa de alguém de e-mails.
// ============================================================

require_once __DIR__ . '/api_util.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/email_util.php';
require_once __DIR__ . '/limite_util.php';

apiExigirPost();
exigirMesmaOrigem();

$email = trim((string) ($_POST['email'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    apiErro('Informe um e-mail válido.', 422);
}

$respostaGenerica = [
    'ok'  => true,
    'msg' => 'Se este e-mail tiver uma conta esperando confirmação, enviamos um link novo. Confira também a caixa de spam.',
];

if (!confirmacaoLigada()) {
    apiResponder($respostaGenerica);
}

try {
    $pdo = conectar();

    if (limiteExcedido($pdo, 'confirma', $email)) {
        apiResponder($respostaGenerica);
    }
    registrarTentativa($pdo, 'confirma', $email);

    $stmt = $pdo->prepare('SELECT id, nome FROM usuarios WHERE email = ? AND email_confirmado = 0 LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        enviarConfirmacao($pdo, (int) $user['id'], $email, $user['nome']);
    }
    apiResponder($respostaGenerica);
} catch (PDOException $e) {
    registrarErro($e, 'reenviar confirmação');
    apiResponder($respostaGenerica);
}
