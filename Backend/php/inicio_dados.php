<?php
// ============================================================
//  KOSMOS — Os números do Início
//  Arquivo: Backend/php/inicio_dados.php
//  GET (protegido). Devolve num fôlego só o que a tela precisa:
//
//    semana   -> [{data:"YYYY-MM-DD", minutos:N, hoje, futuro}] de domingo a sábado da semana atual
//    hoje     -> minutos estudados hoje
//    meta     -> a meta diária salva em usuario_preferencias
//    metricas -> resumos, flashcards e cartões vencidos para hoje
//    passos   -> os "primeiros passos" já cumpridos (booleanos)
//    missoes  -> as missões da semana (ver missoes.php), ou null
//
//  Uma requisição e não quatro: são consultas leves contra o
//  mesmo usuário, e o custo aqui é a ida e volta, não o SELECT.
//
//  TODA data sai do MySQL pronta — inclusive a lista dos sete
//  dias, dia a dia. O PHP não faz uma conta de data neste
//  arquivo, de propósito: o PHP já rodou em outro fuso (Europe/Berlin) e
//  misturar os dois relógios colocou registro no dia errado neste
//  projeto. Hoje o bootstrap.php alinha os fusos; a regra fica.
// ============================================================

require_once __DIR__ . '/estudo_comum.php';
require_once __DIR__ . '/missoes.php';

$usuario = exigirLogin();
$id      = (int) $usuario['id'];

try {
    $pdo = conectar();
    liberarSessao();

    /* ---- A semana: sempre de domingo a sábado ----
       A tabela só tem linha para dia estudado. Se devolvêssemos só
       o que existe, o gráfico teria buracos e o JS teria de
       adivinhar as datas que faltam — cada um com o seu relógio.
       Então a SEQUÊNCIA de dias vem daqui, do MySQL, completa: dia
       sem estudo vem com zero.

       É a semana do calendário (DAYOFWEEK: 1 = domingo), e não "os
       últimos 7 dias": a coluna de cada dia fica sempre no mesmo
       lugar. Os dias que ainda não chegaram vêm com `futuro`. */
    $semana = [];
    $stmt = $pdo->prepare(
        'SELECT DATE_FORMAT(d.dia, "%Y-%m-%d") AS data,
                d.dia = CURDATE()               AS hoje,
                d.dia > CURDATE()               AS futuro,
                COALESCE(SUM(s.minutos), 0)     AS minutos
           FROM (
                  SELECT w.domingo + INTERVAL k.n DAY AS dia
                    FROM (SELECT CURDATE() - INTERVAL (DAYOFWEEK(CURDATE()) - 1) DAY AS domingo) AS w
                   CROSS JOIN (SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3
                               UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6) AS k
                ) AS d
           LEFT JOIN pomodoro_sessoes s
                  ON s.dia = d.dia AND s.usuario_id = ?
          GROUP BY d.dia
          ORDER BY d.dia'
    );
    $stmt->execute([$id]);
    foreach ($stmt as $linha) {
        $semana[] = [
            'data'    => $linha['data'],
            'minutos' => (int) $linha['minutos'],
            'hoje'    => (bool) $linha['hoje'],
            'futuro'  => (bool) $linha['futuro'],
        ];
    }

    /* ---- Meta do dia ---- */
    $stmt = $pdo->prepare(
        'SELECT COALESCE(p.meta_diaria, 60) AS meta,
                (SELECT COALESCE(SUM(minutos), 0)
                   FROM pomodoro_sessoes
                  WHERE usuario_id = u.id AND dia = CURDATE()) AS hoje
           FROM usuarios u
           LEFT JOIN usuario_preferencias p ON p.usuario_id = u.id
          WHERE u.id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $linha = $stmt->fetch() ?: ['meta' => 60, 'hoje' => 0];

    /* ---- Contagens ----
       `vencidos` é a fila de revisão: cartão nunca revisado
       (proxima_revisao NULL) ou com a data já chegada. É o mesmo
       critério do revisar_hoje.php — se mudar lá, muda aqui. */
    /* Quatro `?` e o id passado quatro vezes, e não um `:u` reaproveitado:
       a conexão deste projeto usa ATTR_EMULATE_PREPARES => false, e com
       prepare de verdade o mesmo parâmetro nomeado não pode ser ligado
       mais de uma vez — o driver responde "Invalid parameter number". */
    /* `fez_foco` é EXISTS e não COUNT: a pergunta dos "primeiros
       passos" é "já aconteceu alguma vez?", e o EXISTS pode parar na
       primeira linha em vez de varrer o histórico inteiro de quem
       estuda há meses. */
    $stmt = $pdo->prepare(
        'SELECT
            (SELECT COUNT(*) FROM resumos WHERE usuario_id = ?)               AS resumos,
            (SELECT COUNT(*) FROM flashcard_cartoes c
               JOIN flashcard_decks d ON d.id = c.deck_id
              WHERE d.usuario_id = ?)                                         AS cartoes,
            (SELECT COUNT(*) FROM flashcard_cartoes c
               JOIN flashcard_decks d ON d.id = c.deck_id
              WHERE d.usuario_id = ?
                AND (c.proxima_revisao IS NULL OR c.proxima_revisao <= CURDATE())) AS vencidos,
            (SELECT EXISTS(SELECT 1 FROM pomodoro_sessoes WHERE usuario_id = ?)) AS fez_foco'
    );
    $stmt->execute([$id, $id, $id, $id]);
    $contagens = $stmt->fetch() ?: [];

    /* Questões certas: consulta à parte porque a tabela vem da migração
       de progresso — sem ela o número fica em "—" e o resto segue. */
    $exercicios = null;
    $exRevisar  = 0;      // exercícios com questão para refazer
    $exMateria  = null;   // se todos forem da mesma matéria, o lembrete leva direto a ela
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM exercicio_respostas WHERE usuario_id = ? AND acertou = 1');
        $stmt->execute([$id]);
        $exercicios = (int) $stmt->fetchColumn();

        /* O lembrete de exercícios: um exercício entra quando alguma
           questão dele tem a ÚLTIMA resposta errada (a de maior id é a
           mais recente). Acertou na volta, a questão sai — e quando
           todas saem, o exercício sai do lembrete. */
        $stmt = $pdo->prepare(
            'SELECT r.exercicio_id, e.materia_id
               FROM exercicio_respostas r
               JOIN (SELECT MAX(id) AS id
                       FROM exercicio_respostas
                      WHERE usuario_id = ?
                      GROUP BY exercicio_id, questao) u ON u.id = r.id
               JOIN exercicios e ON e.id = r.exercicio_id AND e.usuario_id = ?
              WHERE r.acertou = 0
              GROUP BY r.exercicio_id, e.materia_id'
        );
        $stmt->execute([$id, $id]);
        $materias = [];
        foreach ($stmt as $linhaEx) {
            $exRevisar++;
            $materias[(int) $linhaEx['materia_id']] = true;
        }
        if (count($materias) === 1) {
            $exMateria = array_key_first($materias);
        }
    } catch (PDOException $e) {
        registrarErro($e);
        $exercicios = null;
    }

    estResponder([
        'semana'   => $semana,
        'hoje'     => (int) $linha['hoje'],
        'meta'     => (int) $linha['meta'],
        'metricas' => [
            'resumos'    => (int) ($contagens['resumos'] ?? 0),
            'flashcards' => (int) ($contagens['cartoes'] ?? 0),
            'vencidos'   => (int) ($contagens['vencidos'] ?? 0),
            'exercicios' => $exercicios,
            'exercicios_revisar' => $exRevisar,
            'exercicios_revisar_materia' => $exMateria,
        ],
        /* Os "primeiros passos" da Início. O index.php já pinta a lista
           pelo servidor; isto aqui é para ela se corrigir sozinha sem
           recarregar — o caso real é o Pomodoro fechando um ciclo com a
           aba Início aberta. O CRITÉRIO de cada passo mora nos dois
           lugares; se mudar um, mude o outro. */
        'passos' => [
            'resumo'     => ((int) ($contagens['resumos'] ?? 0)) > 0,
            'flashcards' => ((int) ($contagens['cartoes'] ?? 0)) > 0,
            'pomodoro'   => ((int) ($contagens['fez_foco'] ?? 0)) > 0,
        ],
        // Depois dos primeiros passos: as missões da semana (null antes disso)
        'missoes' => missoesEstado($pdo, $id),
    ]);
} catch (PDOException $e) {
    registrarErro($e);
    estErro('Não foi possível carregar os dados.', 500);
}
