<?php
// ============================================================
//  KOSMOS — Os números do Início
//  Arquivo: Backend/php/inicio_dados.php
//  GET (protegido). Devolve num fôlego só o que a tela precisa:
//
//    semana   -> [{data:"YYYY-MM-DD", minutos:N}] dos últimos 7 dias
//    hoje     -> minutos estudados hoje
//    meta     -> a meta diária salva em usuario_preferencias
//    metricas -> resumos, flashcards e cartões vencidos para hoje
//    provas   -> as próximas, já com os dias que faltam
//
//  Uma requisição e não cinco: são cinco consultas leves contra o
//  mesmo usuário, e o custo aqui é a ida e volta, não o SELECT.
//
//  TODA data sai do MySQL pronta — inclusive "quantos dias faltam"
//  e a lista dos sete dias. O PHP não faz uma conta de data neste
//  arquivo, de propósito: ele roda em Europe/Berlin e o MySQL em
//  America/Sao_Paulo, e misturar os dois já colocou registro no dia
//  errado neste projeto antes.
// ============================================================

require_once __DIR__ . '/estudo_comum.php';
require_once __DIR__ . '/datas.php';   // dataCurtaPt() para o rótulo das provas

$usuario = exigirLogin();
$id      = (int) $usuario['id'];

try {
    $pdo = conectar();
    liberarSessao();

    /* ---- Os sete dias ----
       A tabela só tem linha para dia estudado. Se devolvêssemos só
       o que existe, o gráfico teria buracos e o JS teria de
       adivinhar as datas que faltam — cada um com o seu relógio.
       Então a SEQUÊNCIA de dias vem daqui, do MySQL, completa: dia
       sem estudo vem com zero. */
    $semana = [];
    $stmt = $pdo->prepare(
        'SELECT DATE_FORMAT(d.dia, "%Y-%m-%d") AS data,
                COALESCE(SUM(s.minutos), 0)    AS minutos
           FROM (
                  SELECT CURDATE() - INTERVAL 6 DAY AS dia
                  UNION ALL SELECT CURDATE() - INTERVAL 5 DAY
                  UNION ALL SELECT CURDATE() - INTERVAL 4 DAY
                  UNION ALL SELECT CURDATE() - INTERVAL 3 DAY
                  UNION ALL SELECT CURDATE() - INTERVAL 2 DAY
                  UNION ALL SELECT CURDATE() - INTERVAL 1 DAY
                  UNION ALL SELECT CURDATE()
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
    /* Três `?` e o id passado três vezes, e não um `:u` reaproveitado:
       a conexão deste projeto usa ATTR_EMULATE_PREPARES => false, e com
       prepare de verdade o mesmo parâmetro nomeado não pode ser ligado
       mais de uma vez — o driver responde "Invalid parameter number". */
    $stmt = $pdo->prepare(
        'SELECT
            (SELECT COUNT(*) FROM resumos WHERE usuario_id = ?)               AS resumos,
            (SELECT COUNT(*) FROM flashcard_cartoes c
               JOIN flashcard_decks d ON d.id = c.deck_id
              WHERE d.usuario_id = ?)                                         AS cartoes,
            (SELECT COUNT(*) FROM flashcard_cartoes c
               JOIN flashcard_decks d ON d.id = c.deck_id
              WHERE d.usuario_id = ?
                AND (c.proxima_revisao IS NULL OR c.proxima_revisao <= CURDATE())) AS vencidos'
    );
    $stmt->execute([$id, $id, $id]);
    $contagens = $stmt->fetch() ?: [];

    /* ---- Próximas provas ----
       DATEDIFF no MySQL: 0 = hoje, 1 = amanhã. Prova de ontem não
       aparece mais. */
    $provas = [];
    $stmt = $pdo->prepare(
        'SELECT id, titulo, materia,
                DATE_FORMAT(data, "%Y-%m-%d") AS data,
                DATEDIFF(data, CURDATE())     AS faltam,
                DAY(data)                     AS dia,
                MONTH(data)                   AS mes
           FROM provas
          WHERE usuario_id = ? AND data >= CURDATE()
          ORDER BY data
          LIMIT 4'
    );
    $stmt->execute([$id]);
    foreach ($stmt as $p) {
        $provas[] = [
            'id'      => (int) $p['id'],
            'titulo'  => $p['titulo'],
            'materia' => $p['materia'],
            'data'    => $p['data'],
            'faltam'  => (int) $p['faltam'],
            'quando'  => dataCurtaPt((int) $p['dia'], (int) $p['mes']),
        ];
    }

    estResponder([
        'semana'   => $semana,
        'hoje'     => (int) $linha['hoje'],
        'meta'     => (int) $linha['meta'],
        'metricas' => [
            'resumos'    => (int) ($contagens['resumos'] ?? 0),
            'flashcards' => (int) ($contagens['cartoes'] ?? 0),
            'vencidos'   => (int) ($contagens['vencidos'] ?? 0),
        ],
        'provas'   => $provas,
    ]);
} catch (PDOException $e) {
    estErro('Não foi possível carregar os dados.', 500);
}
