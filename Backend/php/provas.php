<?php
// ============================================================
//  KOSMOS — Provas do estudante
//  Arquivo: Backend/php/provas.php
//
//  GET  -> tudo que a tela de Provas precisa: as próximas, as que
//          já passaram e o material de estudo de cada matéria
//          envolvida.
//  POST -> acao=criar            : titulo, materia, data, anotacoes, topicos[]
//          acao=editar           : id + os mesmos campos
//          acao=excluir          : id
//          acao=resultado        : id, nota (vazia desfaz o registro)
//          acao=topico_criar     : prova, texto
//          acao=topico_alternar  : id
//          acao=topico_excluir   : id
//
//  Um arquivo para todas as ações, como os resumos_*.php fazem com
//  `acao`: são operações curtas sobre a mesma tabela e o mesmo
//  dono, e um arquivo por ação repetiria o mesmo cabeçalho de
//  guarda sete vezes.
//
//  Toda conta de data é do MySQL (DATEDIFF, CURDATE) — o PHP deste
//  projeto roda em fuso diferente do banco, e "faltam 3 dias"
//  calculado do lado errado erra na virada da meia-noite.
//
//  Toda resposta devolve as listas inteiras, já atualizadas: a tela
//  repinta com o que o servidor diz, em vez de adivinhar onde a
//  linha nova entra na ordem.
// ============================================================

require_once __DIR__ . '/estudo_comum.php';
require_once __DIR__ . '/datas.php';
require_once __DIR__ . '/materias.php';

const PROVA_MAX_TITULO    = 80;
const PROVA_MAX_ANOTACOES = 1000;
const PROVA_MAX_TOPICO    = 120;
const PROVA_MAX_TOPICOS   = 40;   // teto por prova: lista maior que isso é plano de curso, não prova

$usuario = exigirLogin();
$id      = (int) $usuario['id'];

try {
    $pdo = conectar();

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        liberarSessao();
        responderComTudo($pdo, $id);
    }

    $corpo = estCorpo();
    $acao  = (string) ($corpo['acao'] ?? '');

    /* ---------------- criar ---------------- */
    if ($acao === 'criar') {
        [$titulo, $materia, $data, $anotacoes] = lerCampos($corpo);

        liberarSessao();

        // Quem decide se a data é passada é o MySQL, no mesmo relógio
        // em que DATEDIFF vai contar depois.
        $stmt = $pdo->prepare('SELECT DATEDIFF(?, CURDATE())');
        $stmt->execute([$data]);
        if ((int) $stmt->fetchColumn() < 0) {
            estErro('Essa data já passou.', 422);
        }

        $pdo->prepare(
            'INSERT INTO provas (usuario_id, titulo, materia, data, anotacoes)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([
            $id,
            $titulo,
            $materia   !== '' ? $materia   : null,
            $data,
            $anotacoes !== '' ? $anotacoes : null,
        ]);

        /* Os assuntos podem vir já no cadastro: quem abre o modal com
           a prova na cabeça costuma saber o que cai, e obrigar a
           salvar primeiro para só então digitar a lista seria um
           passo a mais sem motivo. */
        inserirTopicos($pdo, (int) $pdo->lastInsertId(), $corpo['topicos'] ?? []);

        responderComTudo($pdo, $id, 201);
    }

    /* ---------------- editar ----------------
       Ao contrário do criar, aqui a data PODE ser passada: corrigir
       o dia de uma prova que já aconteceu — para registrar a nota no
       lugar certo — é uso legítimo. */
    if ($acao === 'editar') {
        $provaId = exigirProva($pdo, $corpo['id'] ?? 0, $id);
        [$titulo, $materia, $data, $anotacoes] = lerCampos($corpo);

        liberarSessao();

        $pdo->prepare(
            'UPDATE provas SET titulo = ?, materia = ?, data = ?, anotacoes = ?
              WHERE id = ? AND usuario_id = ?'
        )->execute([
            $titulo,
            $materia   !== '' ? $materia   : null,
            $data,
            $anotacoes !== '' ? $anotacoes : null,
            $provaId,
            $id,
        ]);

        responderComTudo($pdo, $id);
    }

    /* ---------------- excluir ---------------- */
    if ($acao === 'excluir') {
        $provaId = exigirProva($pdo, $corpo['id'] ?? 0, $id);
        liberarSessao();

        // O usuario_id no WHERE é o que impede apagar a prova de outra
        // pessoa trocando o id na requisição. Os assuntos vão junto,
        // por ON DELETE CASCADE.
        $pdo->prepare('DELETE FROM provas WHERE id = ? AND usuario_id = ?')
            ->execute([$provaId, $id]);

        responderComTudo($pdo, $id);
    }

    /* ---------------- resultado (a nota) ----------------
       Nota vazia desfaz o registro: é assim que se corrige um número
       digitado errado, sem precisar de uma ação só para isso. */
    if ($acao === 'resultado') {
        $provaId = exigirProva($pdo, $corpo['id'] ?? 0, $id);

        /* A vírgula é aceita: aqui se escreve nota com vírgula, e o
           <input type="number"> devolve "" quando o navegador não
           entende o que foi digitado — o que apagaria a nota em
           silêncio em vez de gravá-la. */
        $bruta = str_replace(',', '.', trim((string) ($corpo['nota'] ?? '')));
        $nota  = null;

        if (is_numeric($bruta)) {
            $nota = round((float) $bruta, 2);
            // 0–10, 0–100 e os 1000 do ENEM cabem aqui: escola nenhuma
            // combina com outra sobre a escala, então o teto é largo.
            if ($nota < 0 || $nota > 1000) {
                estErro('Nota fora do intervalo.', 422);
            }
        } elseif ($bruta !== '') {
            estErro('Nota inválida.', 422);
        }

        liberarSessao();

        // concluida_em vem do NOW() do MySQL: o mesmo relógio que
        // conta os dias em todo o resto deste arquivo.
        $pdo->prepare(
            'UPDATE provas
                SET nota = ?, concluida_em = ' . ($nota === null ? 'NULL' : 'NOW()') . '
              WHERE id = ? AND usuario_id = ?'
        )->execute([$nota, $provaId, $id]);

        responderComTudo($pdo, $id);
    }

    /* ---------------- assuntos da prova ---------------- */
    if ($acao === 'topico_criar') {
        $provaId = exigirProva($pdo, $corpo['prova'] ?? 0, $id);
        $texto   = estTexto($corpo['texto'] ?? '', PROVA_MAX_TOPICO);
        if ($texto === '') {
            estErro('Escreva o assunto.', 422);
        }

        liberarSessao();
        inserirTopicos($pdo, $provaId, [$texto]);
        responderComTudo($pdo, $id, 201);
    }

    if ($acao === 'topico_alternar' || $acao === 'topico_excluir') {
        $topicoId = filter_var($corpo['id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($topicoId === false) {
            estErro('Assunto inválido.', 422);
        }

        liberarSessao();

        /* O JOIN com `provas` é o que prende o assunto ao dono: sem
           ele, trocar o id na requisição marcaria o assunto da prova
           de outra pessoa. */
        $sql = $acao === 'topico_alternar'
            ? 'UPDATE prova_topicos t JOIN provas p ON p.id = t.prova_id
                  SET t.feito = 1 - t.feito
                WHERE t.id = ? AND p.usuario_id = ?'
            : 'DELETE t FROM prova_topicos t JOIN provas p ON p.id = t.prova_id
                WHERE t.id = ? AND p.usuario_id = ?';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$topicoId, $id]);

        if ($stmt->rowCount() === 0) {
            estErro('Assunto não encontrado.', 404);
        }

        responderComTudo($pdo, $id);
    }

    estErro('Ação desconhecida.', 400);
} catch (PDOException $e) {
    estErro('Não foi possível salvar agora.', 500);
}


/* ============================================================
   VALIDAÇÃO
   ============================================================ */

/**
 * Título, matéria, data e anotações — os quatro campos que criar e
 * editar dividem. Encerra a requisição no primeiro problema.
 */
function lerCampos(array $corpo): array {
    $titulo = estTexto($corpo['titulo'] ?? '', PROVA_MAX_TITULO);
    if ($titulo === '') {
        estErro('Dê um nome para a prova.', 422);
    }

    /* A matéria precisa ser uma da lista do projeto. Campo livre aqui
       viraria "Biologia", "biologia" e "Bio" como três matérias
       diferentes — e é pela matéria que esta tela acha os cadernos e
       os baralhos que servem de material de estudo. */
    $materia = estTexto($corpo['materia'] ?? '', 40);
    if ($materia !== '' && !in_array($materia, MATERIAS_KOSMOS, true)) {
        estErro('Matéria desconhecida.', 422);
    }

    /* A data chega do <input type="date">, sempre YYYY-MM-DD.
       Conferimos o formato E se a data existe de verdade: o navegador
       não deixa digitar 31/02, mas a requisição pode vir de qualquer
       lugar. */
    $data = estTexto($corpo['data'] ?? '', 10);
    $d = DateTime::createFromFormat('Y-m-d', $data);
    if (!$d || $d->format('Y-m-d') !== $data) {
        estErro('Data inválida.', 422);
    }

    $anotacoes = estTexto($corpo['anotacoes'] ?? '', PROVA_MAX_ANOTACOES);

    return [$titulo, $materia, $data, $anotacoes];
}

/**
 * Confere que a prova existe E é desta conta. Devolve o id.
 * É o WHERE usuario_id daqui que impede mexer na prova alheia.
 */
function exigirProva(PDO $pdo, $bruto, int $usuarioId): int {
    $provaId = filter_var($bruto, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($provaId === false) {
        estErro('Prova inválida.', 422);
    }

    $stmt = $pdo->prepare('SELECT id FROM provas WHERE id = ? AND usuario_id = ? LIMIT 1');
    $stmt->execute([$provaId, $usuarioId]);

    if (!$stmt->fetch()) {
        estErro('Prova não encontrada.', 404);
    }

    return (int) $provaId;
}

/** Grava assuntos no fim da lista da prova, ignorando os vazios. */
function inserirTopicos(PDO $pdo, int $provaId, $lista): void {
    if (!is_array($lista) || $lista === []) {
        return;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*), COALESCE(MAX(ordem), 0)
                             FROM prova_topicos WHERE prova_id = ?');
    $stmt->execute([$provaId]);
    [$jaTem, $ordem] = array_map('intval', $stmt->fetch(PDO::FETCH_NUM));

    $inserir = $pdo->prepare(
        'INSERT INTO prova_topicos (prova_id, texto, ordem) VALUES (?, ?, ?)'
    );

    foreach ($lista as $bruto) {
        if ($jaTem >= PROVA_MAX_TOPICOS) {
            break;
        }
        // A lista chega do JSON: nada garante que cada item seja texto.
        if (!is_scalar($bruto)) {
            continue;
        }
        $texto = estTexto($bruto, PROVA_MAX_TOPICO);
        if ($texto === '') {
            continue;
        }
        $inserir->execute([$provaId, $texto, ++$ordem]);
        $jaTem++;
    }
}


/* ============================================================
   LEITURA
   ============================================================ */

/** A resposta única desta tela. */
function responderComTudo(PDO $pdo, int $usuarioId, int $codigo = 200): void {
    $proximas = listarProvas($pdo, $usuarioId, true);
    $passadas = listarProvas($pdo, $usuarioId, false);

    estResponder([
        // `provas` guarda o nome antigo: é a lista das próximas, na
        // mesma forma que o inicio_dados.php devolve.
        'provas'    => $proximas,
        'passadas'  => $passadas,
        'materiais' => materiaisDeEstudo($pdo, $usuarioId, array_merge($proximas, $passadas)),
    ], $codigo);
}

/**
 * As provas, com os dias que faltam e os assuntos de cada uma.
 * `$futuras` separa as duas metades da tela: o que ainda vem e o
 * histórico (do mais recente para o mais antigo).
 */
function listarProvas(PDO $pdo, int $usuarioId, bool $futuras): array {
    $stmt = $pdo->prepare(
        'SELECT id, titulo, materia, anotacoes, nota,
                concluida_em IS NOT NULL      AS registrada,
                DATE_FORMAT(data, "%Y-%m-%d") AS data,
                DATEDIFF(data, CURDATE())     AS faltam,
                DAY(data)                     AS dia,
                MONTH(data)                   AS mes,
                YEAR(data)                    AS ano
           FROM provas
          WHERE usuario_id = ? AND data ' . ($futuras ? '>=' : '<') . ' CURDATE()
          ORDER BY data ' . ($futuras ? 'ASC' : 'DESC') . '
          LIMIT 60'
    );
    $stmt->execute([$usuarioId]);

    $lista = [];
    $ids   = [];
    foreach ($stmt as $p) {
        $ids[] = (int) $p['id'];
        $lista[] = [
            'id'          => (int) $p['id'],
            'titulo'      => $p['titulo'],
            'materia'     => $p['materia'],
            'data'        => $p['data'],
            'faltam'      => (int) $p['faltam'],
            'quando'      => dataCurtaPt((int) $p['dia'], (int) $p['mes']),
            'por_extenso' => dataLongaPt((int) $p['dia'], (int) $p['mes'], (int) $p['ano']),
            'anotacoes'   => $p['anotacoes'] ?? '',
            'nota'        => $p['nota'] === null ? null : (float) $p['nota'],
            'registrada'  => (bool) $p['registrada'],
            'topicos'     => [],
            'feitos'      => 0,
        ];
    }

    if ($ids === []) {
        return $lista;
    }

    /* Os assuntos de TODAS as provas numa consulta só. Um SELECT por
       prova dentro do laço acima daria o mesmo resultado e faria N
       idas ao banco para desenhar uma tela — o clássico N+1. */
    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT id, prova_id, texto, feito
           FROM prova_topicos
          WHERE prova_id IN ($marcas)
       ORDER BY ordem, id"
    );
    $stmt->execute($ids);

    $porProva = [];
    foreach ($stmt as $t) {
        $porProva[(int) $t['prova_id']][] = [
            'id'    => (int) $t['id'],
            'texto' => $t['texto'],
            'feito' => (bool) $t['feito'],
        ];
    }

    foreach ($lista as &$prova) {
        $prova['topicos'] = $porProva[$prova['id']] ?? [];
        $prova['feitos']  = count(array_filter($prova['topicos'], fn($t) => $t['feito']));
    }
    unset($prova);

    return $lista;
}

/**
 * O material que a pessoa JÁ TEM de cada matéria com prova marcada:
 * os cadernos e os baralhos daquela matéria.
 *
 * É o que transforma a lista de provas em ponto de partida — "tem
 * prova de Biologia em 3 dias, e aqui estão seus dois cadernos e seu
 * baralho de Biologia" — em vez de só um aviso de contagem regressiva.
 *
 * Vai como mapa matéria -> material, e não copiado dentro de cada
 * prova: duas provas da mesma matéria apontam para o mesmo material,
 * e repetir isso seria mandar o mesmo dado duas vezes.
 */
function materiaisDeEstudo(PDO $pdo, int $usuarioId, array $provas): array {
    $materias = array_values(array_unique(array_filter(
        array_column($provas, 'materia'),
        fn($m) => $m !== null && $m !== ''
    )));

    if ($materias === []) {
        return [];
    }

    $mapa   = array_fill_keys($materias, ['cadernos' => [], 'decks' => []]);
    $marcas = implode(',', array_fill(0, count($materias), '?'));

    $stmt = $pdo->prepare(
        "SELECT c.id, c.nome, c.materia, c.icone,
                (SELECT COUNT(*) FROM resumos r WHERE r.caderno_id = c.id) AS resumos
           FROM resumo_cadernos c
          WHERE c.usuario_id = ? AND c.materia IN ($marcas)
       ORDER BY c.nome"
    );
    $stmt->execute(array_merge([$usuarioId], $materias));
    foreach ($stmt as $c) {
        $mapa[$c['materia']]['cadernos'][] = [
            'id'      => (int) $c['id'],
            'nome'    => $c['nome'],
            'icone'   => $c['icone'] ?? '',
            'resumos' => (int) $c['resumos'],
        ];
    }

    $stmt = $pdo->prepare(
        "SELECT d.id, d.nome, d.materia,
                (SELECT COUNT(*) FROM flashcard_cartoes f WHERE f.deck_id = d.id) AS cartoes
           FROM flashcard_decks d
          WHERE d.usuario_id = ? AND d.materia IN ($marcas)
       ORDER BY d.nome"
    );
    $stmt->execute(array_merge([$usuarioId], $materias));
    foreach ($stmt as $d) {
        $mapa[$d['materia']]['decks'][] = [
            'id'      => (int) $d['id'],
            'nome'    => $d['nome'],
            'cartoes' => (int) $d['cartoes'],
        ];
    }

    return $mapa;
}
