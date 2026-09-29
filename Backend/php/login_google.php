<?php
// ============================================================
//  KOSMOS — Login com Google
//  Arquivo: backend/php/login_google.php
//  Recebe o ID token (JWT) do "Sign in with Google", verifica
//  no Google, cria/loga o usuário e abre a sessão.
// ============================================================

header('Content-Type: application/json; charset=utf-8');


require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método não permitido.']);
    exit;
}

$token = $_POST['credential'] ?? '';
if ($token === '') {
    echo json_encode(['ok' => false, 'msg' => 'Token do Google ausente.']);
    exit;
}



// ---------- Verifica o token no Google ----------
$ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($token));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($resp === false || $code !== 200) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Não foi possível validar a conta Google.']);
    exit;
}

$info = json_decode($resp, true);

// O token tem que ser para ESTE app (audience = nosso Client ID)
if (!is_array($info) || ($info['aud'] ?? '') !== GOOGLE_CLIENT_ID) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Token do Google inválido para este app.']);
    exit;
}

$emailVerificado = ($info['email_verified'] ?? '') === 'true' || ($info['email_verified'] ?? false) === true;
$email    = trim($info['email'] ?? '');
$googleId = $info['sub'] ?? '';

// sub vazio faria a busca por google_id casar contas com google_id ''
if (!$emailVerificado || $email === '' || $googleId === '') {
    echo json_encode(['ok' => false, 'msg' => 'A conta Google não tem e-mail verificado.']);
    exit;
}

$nome = trim($info['name'] ?? '');
if ($nome === '') {
    $nome = explode('@', $email)[0];
}

// ---------- Cria ou encontra o usuário ----------
try {
    $pdo = conectar();

    // Procura pelo google_id (identificador estável da conta Google) OU pelo
    // e-mail. Se houver as duas coisas em linhas diferentes, vence a do
    // google_id: é a conta que já foi vinculada a este Google antes.
    // (google_id = ?) é 1/0/NULL; em DESC o NULL fica por último no MySQL.
    $stmt = $pdo->prepare('SELECT id, nome, google_id, senha_hash
                             FROM usuarios
                            WHERE google_id = ? OR email = ?
                            ORDER BY (google_id = ?) DESC
                            LIMIT 1');
    $stmt->execute([$googleId, $email, $googleId]);
    $user = $stmt->fetch();

    if ($user) {
        $temSenha = !empty($user['senha_hash']);

        if (empty($user['google_id'])) {
            if ($temSenha) {
                // Conta com senha que nunca usou o Google: pode ter sido
                // pré-cadastrada por um invasor com o e-mail da vítima (o
                // cadastro não confirma o e-mail). Se só vinculássemos, a
                // senha dele continuaria abrindo a conta. Então a senha é
                // descartada, as sessões abertas caem (sessoes_versao) e o
                // dono real — provado pelo Google — cria uma senha nova
                // (precisa_senha abaixo / pagina_dashboard → criar-senha).
                $pdo->prepare('UPDATE usuarios
                                  SET google_id = ?, senha_hash = NULL,
                                      sessoes_versao = sessoes_versao + 1
                                WHERE id = ?')
                    ->execute([$googleId, $user['id']]);
                $temSenha = false;
            } else {
                $pdo->prepare('UPDATE usuarios SET google_id = ? WHERE id = ?')
                    ->execute([$googleId, $user['id']]);
            }
        }
        $id        = (int) $user['id'];
        $nomeFinal = $user['nome'];
    } else {
        // Não existe: cria conta nova (sem senha)
        $ins = $pdo->prepare('INSERT INTO usuarios (nome, email, google_id) VALUES (?, ?, ?)');
        $ins->execute([$nome, $email, $googleId]);
        $id        = (int) $pdo->lastInsertId();
        $nomeFinal = $nome;
        $temSenha  = false;
    }

    // Abre a sessão (igual ao login normal)
    iniciarSessao();
    // ID de sessão novo a cada login (evita session fixation)
    session_regenerate_id(true);
    $_SESSION['usuario_id']   = $id;
    $_SESSION['usuario_nome'] = $nomeFinal;
    registrarAcesso($pdo, $id);

    // Guarda a geração de sessões (ver "sair de todos os dispositivos")
    marcarVersaoSessao($pdo, $id);

    // precisa_senha = conta sem senha própria (só Google). O frontend leva
    // esse usuário para a tela de criação de senha antes do dashboard.
    echo json_encode([
        'ok'            => true,
        'msg'           => 'Login com Google realizado!',
        'nome'          => $nomeFinal,
        'precisa_senha' => !$temSenha,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Erro ao entrar com o Google.']);
}
