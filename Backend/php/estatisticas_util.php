<?php
// ============================================================
//  KOSMOS — Os números do topo da Biblioteca e de Exercícios
//  Arquivo: Backend/php/estatisticas_util.php
//
//  Mesma ideia da faixa dos Flashcards (baralhos, cartões, revisados,
//  domínio), adaptada a cada aba — e só com o que dá para medir de
//  verdade, sem tabela nova:
//
//    Biblioteca -> resumos, cadernos, escritos na semana e "prontos
//                  para revisão" (com ==destaque==: são os que o Modo
//                  revisão e os flashcards por regra conseguem usar).
//                  Resumo não tem certo/errado, então não há "%".
//    Exercícios -> matérias, questões criadas, respostas e acerto (%).
//
//  A página chama na primeira pintura; o estatisticas.php devolve os
//  mesmos números depois de uma mudança feita sem recarregar.
// ============================================================

/** Os quatro números da Biblioteca. */
function estatisticasBiblioteca(PDO $pdo, int $usuarioId): array {
    // "na semana" = hoje e os 6 dias antes, pelo relógio do MySQL
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)                                          AS resumos,
                COALESCE(SUM(criado_em >= CURDATE() - INTERVAL 6 DAY), 0) AS semana,
                COALESCE(SUM(corpo REGEXP '==[^=]+=='), 0)        AS prontos
           FROM resumos
          WHERE usuario_id = ?"
    );
    $stmt->execute([$usuarioId]);
    $r = $stmt->fetch();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM resumo_cadernos WHERE usuario_id = ?');
    $stmt->execute([$usuarioId]);

    return [
        'resumos'  => (int) $r['resumos'],
        'cadernos' => (int) $stmt->fetchColumn(),
        'semana'   => (int) $r['semana'],
        'prontos'  => (int) $r['prontos'],
    ];
}

/** Os quatro números de Exercícios. `acerto` é null sem nenhuma resposta. */
function estatisticasExercicios(PDO $pdo, int $usuarioId): array {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM exercicio_materias WHERE usuario_id = ?');
    $stmt->execute([$usuarioId]);
    $materias = (int) $stmt->fetchColumn();

    // As questões moram no JSON de cada lista. Contadas aqui, e não com
    // JSON_LENGTH no SQL: um conteúdo malformado no MySQL derrubaria a
    // consulta inteira, aqui só deixa de contar aquela lista.
    $questoes = 0;
    $stmt = $pdo->prepare('SELECT conteudo FROM exercicios WHERE usuario_id = ?');
    $stmt->execute([$usuarioId]);
    foreach ($stmt as $e) {
        $dados = json_decode((string) $e['conteudo'], true);
        if (is_array($dados['questoes'] ?? null)) {
            $questoes += count($dados['questoes']);
        }
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) AS respostas, COALESCE(SUM(acertou), 0) AS acertos
                             FROM exercicio_respostas WHERE usuario_id = ?');
    $stmt->execute([$usuarioId]);
    $r = $stmt->fetch();
    $respostas = (int) $r['respostas'];

    return [
        'materias'  => $materias,
        'questoes'  => $questoes,
        'respostas' => $respostas,
        'acerto'    => $respostas > 0 ? (int) round(100 * (int) $r['acertos'] / $respostas) : null,
    ];
}
