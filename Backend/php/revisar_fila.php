<?php
// ============================================================
//  KOSMOS — A fila de revisão de hoje
//  Arquivo: Backend/php/revisar_fila.php
//  GET (protegido). Devolve os cartões vencidos de TODOS os
//  baralhos, misturados numa fila só.
//
//  Por que uma fila única e não "entre no baralho e revise":
//  memorizar funciona quando o cartão volta no dia certo, e o dia
//  certo de cada cartão é diferente. Obrigar a pessoa a escolher o
//  baralho devolve a ela uma decisão que o algoritmo já tomou — e
//  garante que o baralho esquecido continue esquecido.
//
//  Vencido = `proxima_revisao` já chegou OU é NULL (cartão novo,
//  nunca revisado). Mesmo critério do inicio_dados.php.
// ============================================================

require_once __DIR__ . '/estudo_comum.php';

$usuario = exigirLogin();

/* Teto de uma sessão. Não é limite técnico: 60 cartões já são uns
   20 minutos de revisão, e fila infinita desanima antes de começar.
   O que sobrar aparece amanhã — que é como a repetição espaçada
   funciona de qualquer jeito. */
const REV_MAX = 60;

try {
    $pdo = conectar();
    liberarSessao();

    $stmt = $pdo->prepare(
        'SELECT c.id, c.deck_id, c.frente, c.verso,
                d.nome AS deck, d.materia
           FROM flashcard_cartoes c
           JOIN flashcard_decks  d ON d.id = c.deck_id
          WHERE d.usuario_id = ?
            AND (c.proxima_revisao IS NULL OR c.proxima_revisao <= CURDATE())
          ORDER BY
                /* Atrasado primeiro, e quanto mais atrasado, mais na
                   frente. Cartão novo (NULL) vai para o fim: rever o
                   que já se esqueceu vale mais do que aprender algo
                   novo por cima. */
                c.proxima_revisao IS NULL,
                c.proxima_revisao,
                RAND()
          LIMIT ' . REV_MAX
    );
    $stmt->execute([$usuario['id']]);

    $cartoes = [];
    foreach ($stmt as $c) {
        $cartoes[] = [
            'id'      => (int) $c['id'],
            'deck_id' => (int) $c['deck_id'],
            'deck'    => $c['deck'],
            'materia' => $c['materia'],
            'frente'  => $c['frente'],
            'verso'   => $c['verso'],
        ];
    }

    estResponder(['cartoes' => $cartoes]);
} catch (PDOException $e) {
    estErro('Não foi possível montar a fila.', 500);
}
