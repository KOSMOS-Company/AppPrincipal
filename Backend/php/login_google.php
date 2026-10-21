<?php
// ============================================================
//  KOSMOS — Login com Google
//  Arquivo: backend/php/login_google.php
//  Recebe o ID token (JWT) do "Sign in with Google", verifica
//  no Google, cria/loga o usuário e abre a sessão.
// ============================================================

require_once __DIR__ . '/api_util.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/config.php';

apiExigirPost();
// Mesmo motivo do login.php: sem isso, outro site podia logar a vítima
// na conta Google do atacante (login CSRF)
exigirMesmaOrigem();

// Aceita o ID token (credential) ou o access token do popup de
// "escolher conta" (google.accounts.oauth2 — usado pelos botões).
$idToken     = $_POST['credential'] ?? '';
$accessToken = $_POST['access_token'] ?? '';
if ($idToken === '' && $accessToken === '') {
    apiErro('Token do Google ausente.', 400);
}

function googleGet(string $url, array $headers = []): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resp === false || $code !== 200) {
        return null;
    }
    $json = json_decode($resp, true);
    return is_array($json) ? $json : null;
}

// ---------- Verifica o token no Google ----------
$info = $idToken !== ''
    ? googleGet('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken))
    : googleGet('https://oauth2.googleapis.com/tokeninfo?access_token=' . urlencode($accessToken));

if ($info === null) {
    apiErro('Não foi possível validar a conta Google.', 401);
}

// O token tem que ser para ESTE app (audience = nosso Client ID).
// Sem isso, um access token emitido para outro site seria aceito aqui.
if (($info['aud'] ?? '') !== GOOGLE_CLIENT_ID) {
    apiErro('Token do Google inválido para este app.', 401);
}

// O tokeninfo de access token não traz o nome: busca no userinfo
if ($accessToken !== '' && $idToken === '') {
    $perfil = googleGet('https://openidconnect.googleapis.com/v1/userinfo', [
        'Authorization: Bearer ' . $accessToken,
    ]);
    if ($perfil !== null && ($perfil['sub'] ?? '') === ($info['sub'] ?? '')) {
        $info['name'] = $perfil['name'] ?? '';
    }
}

$emailVerificado = ($info['email_verified'] ?? '') === 'true' || ($info['email_verified'] ?? false) === true;
$email    = trim($info['email'] ?? '');
$googleId = $info['sub'] ?? '';

// sub vazio faria a busca por google_id casar contas com google_id ''
if (!$emailVerificado || $email === '' || $googleId === '') {
    apiErro('A conta Google não tem e-mail verificado.', 422);
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
    apiResponder([
        'ok'            => true,
        'msg'           => 'Login com Google realizado!',
        'nome'          => $nomeFinal,
        'precisa_senha' => !$temSenha,
    ]);
} catch (PDOException $e) {
    registrarErro($e, 'login Google');
    apiErro('Erro ao entrar com o Google.', 500);
}
