<?php
// ============================================================
//  KOSMOS — Autentica o login
//  Arquivo: backend/php/login.php
// ============================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/senha_util.php';
require_once __DIR__ . '/limite_util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método não permitido.']);
    exit;
}

$email = trim($_POST['email'] ?? '');
$senha = lerSenha('senha');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $senha === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'msg' => 'Preencha e-mail e senha.']);
    exit;
}

try {
    $pdo = conectar();

    // Muitas falhas seguidas: nem confere a senha (ver limite_util.php)
    if (limiteExcedido($pdo, 'login', $email)) {
        http_response_code(429);
        echo json_encode(['ok' => false, 'msg' => 'Muitas tentativas. Aguarde alguns minutos e tente de novo.']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT id, nome, senha_hash FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // senha_hash vazio = conta criada só com o Google
    if (!$user || empty($user['senha_hash']) || !senhaConfere($senha, $user['senha_hash'])) {
        registrarTentativa($pdo, 'login', $email);
        http_response_code(401);
        echo json_encode(['ok' => false, 'msg' => 'E-mail ou senha incorretos.']);
        exit;
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

    // Atualiza a sequência de dias de acesso
    registrarAcesso($pdo, (int) $user['id']);

    // Guarda a geração de sessões (ver "sair de todos os dispositivos")
    marcarVersaoSessao($pdo, (int) $user['id']);

    echo json_encode([
        'ok'   => true,
        'msg'  => 'Login realizado!',
        'nome' => $user['nome'],
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Erro interno. Tente novamente.']);
}
