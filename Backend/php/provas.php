<?php
// ============================================================
//  KOSMOS — Provas do estudante
//  Arquivo: Backend/php/provas.php
//
//  GET  -> lista as próximas (mesma forma do inicio_dados.php)
//  POST -> acao=criar   : titulo, materia, data (YYYY-MM-DD)
//          acao=excluir : id
//
//  Um arquivo para as três ações, como os resumos_*.php fazem com
//  `acao`: são operações curtas sobre a mesma tabela e o mesmo
//  dono, e três arquivos repetiriam o mesmo cabeçalho de guarda.
//
//  Toda conta de data é do MySQL (DATEDIFF, CURDATE) — o PHP deste
//  projeto roda em fuso diferente do banco, e "faltam 3 dias"
//  calculado do lado errado erra na virada da meia-noite.
// ============================================================

require_once __DIR__ . '/estudo_comum.php';
require_once __DIR__ . '/datas.php';
require_once __DIR__ . '/materias.php';

$usuario = exigirLogin();
$id      = (int) $usuario['id'];

try {
    $pdo = conectar();

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        liberarSessao();
        estResponder(['provas' => listarProvas($pdo, $id)]);
    }

    $corpo = estCorpo();
    $acao  = (string) ($corpo['acao'] ?? '');

    /* ---------------- criar ---------------- */
    if ($acao === 'criar') {
        $titulo = estTexto($corpo['titulo'] ?? '', 80);
        if ($titulo === '') {
            estErro('Dê um nome para a prova.', 422);
        }

        /* A matéria precisa ser uma da lista do projeto. Campo livre
           aqui viraria "Biologia", "biologia" e "Bio" como três
           matérias diferentes, e a filtragem por matéria — que já
           existe nos cadernos — deixaria de funcionar. */
        $materia = estTexto($corpo['materia'] ?? '', 40);
        if ($materia !== '' && !in_array($materia, MATERIAS_KOSMOS, true)) {
            estErro('Matéria desconhecida.', 422);
        }

        /* A data chega do <input type="date">, sempre YYYY-MM-DD.
           Conferimos o formato E se a data existe de verdade: o
           navegador não deixa digitar 31/02, mas a requisição pode
           vir de qualquer lugar. */
        $data = estTexto($corpo['data'] ?? '', 10);
        $d = DateTime::createFromFormat('Y-m-d', $data);
        if (!$d || $d->format('Y-m-d') !== $data) {
            estErro('Data inválida.', 422);
        }

        liberarSessao();

        // Quem decide se a data é passada é o MySQL, no mesmo relógio
        // em que DATEDIFF vai contar depois.
        $stmt = $pdo->prepare('SELECT DATEDIFF(?, CURDATE())');
        $stmt->execute([$data]);
        if ((int) $stmt->fetchColumn() < 0) {
            estErro('Essa data já passou.', 422);
        }

        $pdo->prepare(
            'INSERT INTO provas (usuario_id, titulo, materia, data) VALUES (?, ?, ?, ?)'
        )->execute([$id, $titulo, $materia !== '' ? $materia : null, $data]);

        estResponder(['provas' => listarProvas($pdo, $id)], 201);
    }

    /* ---------------- excluir ---------------- */
    if ($acao === 'excluir') {
        $provaId = filter_var($corpo['id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($provaId === false) {
            estErro('Prova inválida.', 422);
        }

        liberarSessao();

        // O usuario_id no WHERE é o que impede apagar a prova de outra
        // pessoa trocando o id na requisição.
        $stmt = $pdo->prepare('DELETE FROM provas WHERE id = ? AND usuario_id = ?');
        $stmt->execute([$provaId, $id]);

        if ($stmt->rowCount() === 0) {
            estErro('Prova não encontrada.', 404);
        }

        estResponder(['provas' => listarProvas($pdo, $id)]);
    }

    estErro('Ação desconhecida.', 400);
} catch (PDOException $e) {
    estErro('Não foi possível salvar agora.', 500);
}


/**
 * As próximas provas, já com os dias que faltam.
 * Mesma forma do inicio_dados.php — as duas telas desenham a
 * mesma lista, então devolver formatos diferentes só criaria dois
 * jeitos de ler a mesma coisa.
 */
function listarProvas(PDO $pdo, int $usuarioId): array {
    $stmt = $pdo->prepare(
        'SELECT id, titulo, materia,
                DATE_FORMAT(data, "%Y-%m-%d") AS data,
                DATEDIFF(data, CURDATE())     AS faltam,
                DAY(data)                     AS dia,
                MONTH(data)                   AS mes
           FROM provas
          WHERE usuario_id = ? AND data >= CURDATE()
          ORDER BY data
          LIMIT 20'
    );
    $stmt->execute([$usuarioId]);

    $lista = [];
    foreach ($stmt as $p) {
        $lista[] = [
            'id'      => (int) $p['id'],
            'titulo'  => $p['titulo'],
            'materia' => $p['materia'],
            'data'    => $p['data'],
            'faltam'  => (int) $p['faltam'],
            'quando'  => dataCurtaPt((int) $p['dia'], (int) $p['mes']),
        ];
    }
    return $lista;
}
