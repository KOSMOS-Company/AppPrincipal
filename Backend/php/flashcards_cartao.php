<?php
// ============================================================
//  KOSMOS — Criar / editar / excluir um cartão
//  Arquivo: backend/php/flashcards_cartao.php
//  POST (protegido), campo "acao":
//    criar   -> deck, frente, verso
//    editar  -> id, frente, verso
//    excluir -> id
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
            $deck = fcDeckDoUsuario($pdo, fcId('deck'), (int) $usuario['id']);
            if ($deck === null) {
                fcErro('Deck não encontrado.', 404);
            }

            [$frente, $verso] = fcLerCartao();

            // Entra no fim da fila do deck
            $stmt = $pdo->prepare(
                'INSERT INTO flashcard_cartoes (deck_id, frente, verso, ordem)
                 SELECT ?, ?, ?, COALESCE(MAX(ordem), 0) + 1
                   FROM flashcard_cartoes WHERE deck_id = ?'
            );
            $stmt->execute([$deck['id'], $frente, $verso, $deck['id']]);

            fcResponder([
                'ok'  => true,
                'id'  => (int) $pdo->lastInsertId(),
                'msg' => 'Cartão adicionado!',
            ]);
        }

        // --------------------------------------------- editar
        case 'editar': {
            $id = fcId('id');
            if (fcCartaoDoUsuario($pdo, $id, (int) $usuario['id']) === null) {
                fcErro('Cartão não encontrado.', 404);
            }

            [$frente, $verso] = fcLerCartao();

            $stmt = $pdo->prepare('UPDATE flashcard_cartoes SET frente = ?, verso = ? WHERE id = ?');
            $stmt->execute([$frente, $verso, $id]);

            fcResponder(['ok' => true, 'id' => $id, 'msg' => 'Cartão atualizado!']);
        }

        // -------------------------------------------- excluir
        case 'excluir': {
            $id = fcId('id');
            if (fcCartaoDoUsuario($pdo, $id, (int) $usuario['id']) === null) {
                fcErro('Cartão não encontrado.', 404);
            }

            $pdo->prepare('DELETE FROM flashcard_cartoes WHERE id = ?')->execute([$id]);

            fcResponder(['ok' => true, 'msg' => 'Cartão excluído.']);
        }

        default:
            fcErro('Ação desconhecida.');
    }
} catch (PDOException $e) {
    fcErro('Não foi possível salvar o cartão.', 500);
}

/** Lê e valida frente + verso do POST. Encerra a requisição se houver erro. */
function fcLerCartao(): array {
    $erros  = [];
    $frente = fcTexto('frente', 'Pergunta', FC_MAX_TEXTO, $erros);
    $verso  = fcTexto('verso',  'Resposta', FC_MAX_TEXTO, $erros);

    if (!empty($erros)) {
        fcErro(implode(' ', $erros));
    }

    return [$frente, $verso];
}
