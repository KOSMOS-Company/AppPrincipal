<?php
// ============================================================
//  KOSMOS — Quem está logado agora?
//  Arquivo: backend/php/usuario_atual.php
//  O frontend chama este endpoint para saber se há sessão ativa
//  e qual o nome do usuário. Usado pelo "porteiro" do dashboard.
// ============================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/avatar_util.php';

$usuario = usuarioLogado();

if ($usuario === null) {
    http_response_code(401); // ninguém logado
    echo json_encode(['ok' => false]);
    exit;
}

// Registra o acesso de hoje e pega a sequência atual de dias.
// Também informa se a conta já tem senha própria: contas criadas pelo
// login com Google começam sem senha, e o dashboard usa isso para levar
// o usuário à tela de criação de senha.
$sequencia = 0;
$temSenha  = true;   // no erro, não empurra ninguém para a tela de senha
$avatarCor = 'roxo';
$avatarUrl = null;
$avatarPos = ['x' => 50, 'y' => 50];
try {
    $pdo = conectar();

    // "Sair de todos os dispositivos" feito em outro lugar invalida esta
    // sessão: o porteiro é o primeiro a perceber, a cada troca de página.
    if (!sessaoAindaValida($pdo, (int) $usuario['id'])) {
        encerrarSessao();
        http_response_code(401);
        echo json_encode(['ok' => false, 'msg' => 'Sua sessão foi encerrada.']);
        exit;
    }

    // A partir daqui não mexemos mais em $_SESSION: solta o lock para
    // as outras chamadas da mesma página não ficarem na fila.
    liberarSessao();

    $sequencia = registrarAcesso($pdo, (int) $usuario['id']);

    $stmt = $pdo->prepare('SELECT senha_hash FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->execute([$usuario['id']]);
    $row = $stmt->fetch();
    if ($row) {
        $temSenha = !empty($row['senha_hash']);
    }

    // Cor do avatar escolhida na aba Conta (a barra lateral usa em todas as abas)
    $stmt = $pdo->prepare('SELECT avatar_cor, avatar_arquivo, avatar_pos_x, avatar_pos_y
                           FROM usuario_preferencias WHERE usuario_id = ? LIMIT 1');
    $stmt->execute([$usuario['id']]);
    $pref = $stmt->fetch();
    if ($pref) {
        $avatarCor = $pref['avatar_cor'];
        $avatarUrl = urlAvatar($pref['avatar_arquivo']);
        $avatarPos = ['x' => (int) $pref['avatar_pos_x'], 'y' => (int) $pref['avatar_pos_y']];
    }
} catch (PDOException $e) {
    $sequencia = 0;
}

echo json_encode([
    'ok'        => true,
    'id'        => $usuario['id'],
    'nome'      => $usuario['nome'],
    'sequencia' => $sequencia,
    'tem_senha' => $temSenha,
    'avatar_cor' => $avatarCor,
    'avatar_url' => $avatarUrl,
    'avatar_pos' => $avatarPos,
]);
