<?php
// ============================================================
//  KOSMOS — Lista os decks do usuário + estatísticas
//  Arquivo: backend/php/flashcards_listar.php
//  GET (protegido). Devolve:
//    { ok, totais:{decks,cartoes,revisados,dominados,revisoes,dominio},
//      decks:[{id,nome,materia,cartoes,revisados,dominados,revisoes,quando}] }
// ============================================================

require_once __DIR__ . '/flashcards_comum.php';

$usuario = exigirLogin();

try {
    $pdo = conectar();

    // Uma query só: cada deck com a contagem dos seus cartões.
    // "dias_revisao" vem do MySQL (DATEDIFF) — o PHP nunca calcula datas aqui.
    $sql = 'SELECT d.id,
                   d.nome,
                   d.materia,
                   d.revisoes,
                   DATEDIFF(CURDATE(), DATE(d.ultima_revisao)) AS dias_revisao,
                   COUNT(c.id)                    AS cartoes,
                   SUM(c.revisoes > 0)            AS revisados,
                   SUM(c.ultimo_resultado = 1)    AS dominados
              FROM flashcard_decks d
         LEFT JOIN flashcard_cartoes c ON c.deck_id = d.id
             WHERE d.usuario_id = ?
          GROUP BY d.id
          ORDER BY d.criado_em DESC, d.id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuario['id']]);

    $decks  = [];
    $totais = ['decks' => 0, 'cartoes' => 0, 'revisados' => 0, 'dominados' => 0, 'revisoes' => 0];

    foreach ($stmt as $linha) {
        $deck = [
            'id'        => (int) $linha['id'],
            'nome'      => $linha['nome'],
            'materia'   => $linha['materia'],
            'cartoes'   => (int) $linha['cartoes'],
            'revisados' => (int) $linha['revisados'],
            'dominados' => (int) $linha['dominados'],
            'revisoes'  => (int) $linha['revisoes'],
            'quando'    => fcQuando($linha['dias_revisao']),
        ];

        $decks[] = $deck;

        $totais['decks']++;
        $totais['cartoes']   += $deck['cartoes'];
        $totais['revisados'] += $deck['revisados'];
        $totais['dominados'] += $deck['dominados'];
        $totais['revisoes']  += $deck['revisoes'];
    }

    // % de domínio geral (cartões acertados na última revisão)
    $totais['dominio'] = $totais['cartoes'] > 0
        ? (int) round($totais['dominados'] * 100 / $totais['cartoes'])
        : 0;

    fcResponder([
        'ok'     => true,
        'totais' => $totais,
        'decks'  => $decks,
    ]);
} catch (PDOException $e) {
    fcErro('Não foi possível carregar seus flashcards.', 500);
}

/**
 * Transforma a diferença de dias (calculada pelo MySQL) num texto curto.
 * Recebe null quando o deck nunca foi estudado.
 */
function fcQuando($dias): string {
    if ($dias === null) {
        return 'Nunca estudado';
    }

    $dias = (int) $dias;

    if ($dias <= 0) return 'Estudado hoje';
    if ($dias === 1) return 'Estudado ontem';
    if ($dias < 30)  return "Estudado há $dias dias";
    if ($dias < 60)  return 'Estudado há 1 mês';

    return 'Estudado há ' . (int) floor($dias / 30) . ' meses';
}
