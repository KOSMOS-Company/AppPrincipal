<?php
// ============================================================
//  KOSMOS — Criar / renomear / excluir um deck
//  Arquivo: backend/php/flashcards_deck.php
//  POST (protegido), campo "acao":
//    criar   -> nome, materia
//    editar  -> id, nome, materia
//    excluir -> id   (os cartões vão junto, por ON DELETE CASCADE)
// ============================================================

require_once __DIR__ . '/flashcards_comum.php';

$usuario = exigirLogin();
fcExigirPost();

$acao = $_POST['acao'] ?? '';

try {
    $pdo = conectar();

    switch ($acao) {
        // ---------------------------------------------- criar
        case 'criar': {
            [$nome, $materia] = fcLerDeck();

            $stmt = $pdo->prepare(
                'INSERT INTO flashcard_decks (usuario_id, nome, materia) VALUES (?, ?, ?)'
            );
            $stmt->execute([$usuario['id'], $nome, $materia]);

            fcResponder([
                'ok'  => true,
                'id'  => (int) $pdo->lastInsertId(),
                'msg' => 'Deck criado!',
            ]);
        }

        // --------------------------------------------- editar
        case 'editar': {
            $id = fcId('id');
            if (fcDeckDoUsuario($pdo, $id, (int) $usuario['id']) === null) {
                fcErro('Deck não encontrado.', 404);
            }

            [$nome, $materia] = fcLerDeck();

            $stmt = $pdo->prepare(
                'UPDATE flashcard_decks SET nome = ?, materia = ? WHERE id = ? AND usuario_id = ?'
            );
            $stmt->execute([$nome, $materia, $id, $usuario['id']]);

            fcResponder(['ok' => true, 'id' => $id, 'msg' => 'Deck atualizado!']);
        }

        // -------------------------------------------- excluir
        case 'excluir': {
            $id = fcId('id');
            if (fcDeckDoUsuario($pdo, $id, (int) $usuario['id']) === null) {
                fcErro('Deck não encontrado.', 404);
            }

            // Os cartões somem junto: a FK do cartão é ON DELETE CASCADE.
            $stmt = $pdo->prepare('DELETE FROM flashcard_decks WHERE id = ? AND usuario_id = ?');
            $stmt->execute([$id, $usuario['id']]);

            fcResponder(['ok' => true, 'msg' => 'Deck excluído.']);
        }

        default:
            fcErro('Ação desconhecida.');
    }
} catch (PDOException $e) {
    fcErro('Não foi possível salvar o deck.', 500);
}

/** Lê e valida nome + matéria do POST. Encerra a requisição se houver erro. */
function fcLerDeck(): array {
    $erros   = [];
    $nome    = fcTexto('nome',    'Nome do deck', FC_MAX_NOME,    $erros);
    $materia = fcTexto('materia', 'Matéria',      FC_MAX_MATERIA, $erros);

    if (!empty($erros)) {
        fcErro(implode(' ', $erros));
    }

    return [$nome, $materia];
}
