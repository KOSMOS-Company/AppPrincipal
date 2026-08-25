<?php
// ============================================================
//  KOSMOS — Cartões de um deck
//  Arquivo: backend/php/flashcards_cartoes.php
//  GET (protegido): ?deck=ID
//  Devolve { ok, deck:{id,nome,materia}, cartoes:[...] }
//  Usado tanto pela tela "gerenciar cartões" quanto pelo estudo.
// ============================================================

require_once __DIR__ . '/flashcards_comum.php';

$usuario = exigirLogin();

try {
    $pdo  = conectar();
    $deck = fcDeckDoUsuario($pdo, fcId('deck'), (int) $usuario['id']);

    if ($deck === null) {
        fcErro('Deck não encontrado.', 404);
    }

    $stmt = $pdo->prepare(
        'SELECT id, frente, verso, revisoes, acertos, erros, ultimo_resultado
           FROM flashcard_cartoes
          WHERE deck_id = ?
       ORDER BY ordem ASC, id ASC'
    );
    $stmt->execute([$deck['id']]);

    $cartoes = [];
    foreach ($stmt as $linha) {
        $cartoes[] = [
            'id'       => (int) $linha['id'],
            'frente'   => $linha['frente'],
            'verso'    => $linha['verso'],
            'revisoes' => (int) $linha['revisoes'],
            'acertos'  => (int) $linha['acertos'],
            'erros'    => (int) $linha['erros'],
            // null = nunca revisado; true = acertou na última; false = errou
            'ultimo'   => $linha['ultimo_resultado'] === null
                ? null
                : ((int) $linha['ultimo_resultado'] === 1),
        ];
    }

    fcResponder([
        'ok'      => true,
        'deck'    => [
            'id'      => (int) $deck['id'],
            'nome'    => $deck['nome'],
            'materia' => $deck['materia'],
        ],
        'cartoes' => $cartoes,
    ]);
} catch (PDOException $e) {
    fcErro('Não foi possível carregar os cartões.', 500);
}
