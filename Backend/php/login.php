<?php
// ============================================================
//  KOSMOS — Autentica o login
//  Arquivo: backend/php/login.php
// ============================================================

require_once __DIR__ . '/api_util.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/senha_util.php';
require_once __DIR__ . '/limite_util.php';
require_once __DIR__ . '/conta_dev_util.php';

apiExigirPost();
// Login vindo de outro site é recusado: sem isso, uma página maliciosa
// podia logar a vítima na conta do ATACANTE (login CSRF) e ler o que
// ela estudasse depois. O SameSite não ajuda aqui — o cookie está
// sendo criado, não enviado.
exigirMesmaOrigem();

$email = trim($_POST['email'] ?? '');
$senha = lerSenha('senha');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $senha === '') {
    apiErro('Preencha e-mail e senha.', 422);
}

try {
    $pdo = conectar();

    // Muitas falhas seguidas: nem confere a senha (ver limite_util.php)
    if (limiteExcedido($pdo, 'login', $email)) {
        apiErro('Muitas tentativas. Aguarde alguns minutos e tente de novo.', 429);
    }

    $stmt = $pdo->prepare('SELECT id, nome, senha_hash FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Confere a senha mesmo quando o e-mail não existe (contra um hash
    // de mentira): o bcrypt leva ~0,2 s, e sem isso a resposta rápida
    // denunciava quais e-mails têm conta.
    $hash   = ($user && !empty($user['senha_hash'])) ? $user['senha_hash'] : senhaHashFalso();
    $confere = senhaConfere($senha, $hash);

    // senha_hash vazio = conta criada só com o Google
    if (!$user || empty($user['senha_hash']) || !$confere) {
        registrarTentativa($pdo, 'login', $email);
        apiErro('E-mail ou senha incorretos.', 401);
    }

    limparTentativas($pdo, 'login', $email);

    // ---------- Login válido: grava a sessão ----------
    // A partir daqui o SERVIDOR "lembra" quem é este usuário.
    // O PHP envia um cookie (PHPSESSID) e, nas próximas requisições,
    // conseguimos recuperar o id sem pedir e-mail/senha de novo.
    iniciarSessao();
    // Troca o ID da sessão no momento do login: se alguém tiver conseguido
    // fixar um ID na vítima antes, ele não vale mais depois de autenticar
    session_regenerate_id(true);
    $_SESSION['usuario_id']   = $user['id'];
    $_SESSION['usuario_nome'] = $user['nome'];

    // Conta de desenvolvedor (só no PC de quem desenvolve): repreenche tudo
    // no primeiro acesso do dia, ANTES da sequência ser conferida abaixo
    contaDevRenovar($pdo, (int) $user['id'], $email, $user['nome']);

    // Atualiza a sequência de dias de acesso
    registrarAcesso($pdo, (int) $user['id']);

    // Guarda a geração de sessões (ver "sair de todos os dispositivos")
    marcarVersaoSessao($pdo, (int) $user['id']);

    apiResponder([
        'ok'   => true,
        'msg'  => 'Login realizado!',
        'nome' => $user['nome'],
    ]);

} catch (PDOException $e) {
    registrarErro($e, 'login');
    apiErro('Erro interno. Tente novamente.', 500);
}
