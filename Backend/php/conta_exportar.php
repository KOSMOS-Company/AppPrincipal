<?php
// ============================================================
//  KOSMOS — Exportar meus dados (LGPD)
//  Arquivo: backend/php/conta_exportar.php
//  GET (protegido): devolve tudo o que o app guarda sobre o
//  usuário em um arquivo JSON para download. Nada de senha:
//  o hash NUNCA sai daqui, só a informação de que existe.
// ============================================================

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/sessao.php';

$usuario = exigirLogin();
$id      = (int) $usuario['id'];

try {
    $pdo = conectar();
    liberarSessao();   // exportar só lê dados

    // ---------- Perfil ----------
    $stmt = $pdo->prepare('SELECT nome, email, criado_em, ultimo_acesso, sequencia,
                                  senha_hash, google_id
                           FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $u = $stmt->fetch();

    if (!$u) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(404);
        echo json_encode(['ok' => false, 'msg' => 'Usuário não encontrado.']);
        exit;
    }

    // ---------- Preferências ----------
    $stmt = $pdo->prepare('SELECT avatar_cor, avatar_arquivo, avatar_pos_x, avatar_pos_y,
                                  pomo_foco, pomo_pausa, pomo_pausa_longa,
                                  meta_diaria, materias, notif_lembrete, notif_resumo,
                                  atualizado_em
                           FROM usuario_preferencias WHERE usuario_id = ? LIMIT 1');
    $stmt->execute([$id]);
    $pref = $stmt->fetch() ?: null;

    // ---------- Flashcards (decks + cartões) ----------
    $stmt = $pdo->prepare('SELECT id, nome, materia, criado_em
                           FROM flashcard_decks WHERE usuario_id = ? ORDER BY id');
    $stmt->execute([$id]);
    $decks = $stmt->fetchAll();

    foreach ($decks as &$deck) {
        $c = $pdo->prepare('SELECT frente, verso, ordem, criado_em, revisoes, acertos,
                                   erros, ultima_revisao, ultimo_resultado
                            FROM flashcard_cartoes WHERE deck_id = ? ORDER BY ordem, id');
        $c->execute([$deck['id']]);
        $deck['cartoes'] = $c->fetchAll();
    }
    unset($deck);

    $dados = [
        'exportado_em' => date('c'),
        'aplicativo'   => 'Kosmos',
        'perfil' => [
            'nome'          => $u['nome'],
            'email'         => $u['email'],
            'criado_em'     => $u['criado_em'],
            'ultimo_acesso' => $u['ultimo_acesso'],
            'sequencia'     => (int) $u['sequencia'],
            'tem_senha'     => !empty($u['senha_hash']),   // o hash não é exportado
            'conta_google'  => !empty($u['google_id']),
        ],
        'preferencias' => $pref,
        'flashcards'   => $decks,
        'observacao'   => 'Resumos e exercícios ainda não são salvos no servidor, '
                        . 'por isso não aparecem aqui.',
    ];

    $nomeArquivo = 'kosmos-meus-dados-' . date('Y-m-d') . '.json';

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
    echo json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (PDOException $e) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Não foi possível exportar seus dados.']);
}
