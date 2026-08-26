<?php
// ============================================================
//  KOSMOS — Dados da conta do usuário logado
//  Arquivo: backend/php/conta_dados.php
//  GET (protegido): perfil + resumo de acesso (último acesso,
//  sequência, quantos decks/cartões) para a aba Conta.
// ============================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/avatar_util.php';

// Porteiro: só passa quem está logado (senão responde 401 e encerra)
$usuario = exigirLogin();

try {
    $pdo = conectar();

    // Se outro dispositivo pediu "sair de todos", esta sessão já não vale
    if (!sessaoAindaValida($pdo, (int) $usuario['id'])) {
        encerrarSessao();
        http_response_code(401);
        echo json_encode(['ok' => false, 'msg' => 'Sua sessão foi encerrada.']);
        exit;
    }

    liberarSessao();   // não escrevemos na sessão daqui para frente

    $stmt = $pdo->prepare('SELECT nome, email, criado_em, ultimo_acesso, sequencia,
                                  senha_hash, google_id
                           FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->execute([$usuario['id']]);
    $dados = $stmt->fetch();

    if (!$dados) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'msg' => 'Usuário não encontrado.']);
        exit;
    }

    // Resumo do que o usuário já produziu (hoje só flashcards persistem)
    $stmt = $pdo->prepare('SELECT COUNT(*) AS decks FROM flashcard_decks WHERE usuario_id = ?');
    $stmt->execute([$usuario['id']]);
    $decks = (int) ($stmt->fetch()['decks'] ?? 0);

    $stmt = $pdo->prepare('SELECT COUNT(c.id) AS cartoes
                           FROM flashcard_cartoes c
                           JOIN flashcard_decks d ON d.id = c.deck_id
                           WHERE d.usuario_id = ?');
    $stmt->execute([$usuario['id']]);
    $cartoes = (int) ($stmt->fetch()['cartoes'] ?? 0);

    echo json_encode([
        'ok'            => true,
        'nome'          => $dados['nome'],
        'email'         => $dados['email'],
        'criado_em'     => $dados['criado_em'],
        'ultimo_acesso' => $dados['ultimo_acesso'],
        'sequencia'     => (int) $dados['sequencia'],
        'tem_senha'     => !empty($dados['senha_hash']),  // false = conta só do Google
        'tem_google'    => !empty($dados['google_id']),   // true = vinculada ao Google
        'decks'         => $decks,
        'cartoes'       => $cartoes,
        'avatar_url'    => urlAvatar(avatarDoUsuario($pdo, (int) $usuario['id'])),
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Erro ao carregar os dados da conta.']);
}
