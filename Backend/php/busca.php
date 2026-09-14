<?php
// ============================================================
//  KOSMOS — Busca global
//  Arquivo: Backend/php/busca.php
//  GET (protegido): ?q=termo
//
//  Procura em resumos, cadernos, baralhos e cartões de uma vez.
//  Quem estuda não lembra ONDE guardou — lembra do assunto. Fazer
//  a pessoa escolher a aba antes de procurar devolve a ela uma
//  decisão que o app pode tomar sozinho.
//
//  ── Por que FULLTEXT nos resumos e LIKE no resto ──
//  O corpo de um resumo é texto longo: `LIKE '%termo%'` ali não
//  usa índice nenhum e fica mais lento conforme a pessoa escreve
//  mais — exatamente ao contrário do que deveria. O índice
//  ft_resumo (migração 2026-09-13) resolve isso.
//  Nome de caderno, de baralho e frente de cartão são campos
//  curtos, com poucas linhas por usuário: ali o LIKE é honesto e
//  ainda acha pedaço de palavra, que num nome curto importa mais
//  do que relevância.
// ============================================================

require_once __DIR__ . '/estudo_comum.php';

$usuario = exigirLogin();
$id      = (int) $usuario['id'];

$q = estTexto($_GET['q'] ?? '', 80);

/* Menos de 2 caracteres não é busca, é a pessoa ainda digitando —
   e uma consulta por tecla com "a" devolveria a biblioteca inteira. */
if (mb_strlen($q) < 2) {
    estResponder(['q' => $q, 'resultados' => []]);
}

const BUSCA_MAX = 30;

try {
    $pdo = conectar();
    liberarSessao();

    $resultados = [];
    $curinga = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';

    /* ---- Resumos (FULLTEXT + LIKE) ----
       Os dois juntos de propósito: o FULLTEXT acha por relevância
       mas só casa PALAVRA INTEIRA e ignora palavra com menos de 4
       letras (ft_min_word_len). Quem digita "fotoss" não acharia
       "fotossíntese" — e é assim que gente busca. O LIKE cobre esse
       buraco; o UNION tira a duplicata. */
    $stmt = $pdo->prepare(
        'SELECT id, titulo, materia, corpo FROM (
             SELECT r.id, r.titulo, r.materia, r.corpo, 2 AS peso
               FROM resumos r
              WHERE r.usuario_id = ?
                AND MATCH(r.titulo, r.corpo) AGAINST (? IN NATURAL LANGUAGE MODE)
             UNION
             SELECT r.id, r.titulo, r.materia, r.corpo, 1 AS peso
               FROM resumos r
              WHERE r.usuario_id = ?
                AND (r.titulo LIKE ? OR r.corpo LIKE ?)
         ) AS achados
         GROUP BY id, titulo, materia, corpo
         ORDER BY MAX(peso) DESC
         LIMIT ' . BUSCA_MAX
    );
    $stmt->execute([$id, $q, $id, $curinga, $curinga]);

    foreach ($stmt as $r) {
        $resultados[] = [
            'tipo'    => 'resumo',
            'id'      => (int) $r['id'],
            'titulo'  => $r['titulo'],
            'materia' => $r['materia'],
            'trecho'  => trechoComTermo($r['corpo'], $q),
            'url'     => 'resumo.php?id=' . (int) $r['id'],
        ];
    }

    /* ---- Cadernos ---- */
    $stmt = $pdo->prepare(
        'SELECT id, nome, materia FROM resumo_cadernos
          WHERE usuario_id = ? AND (nome LIKE ? OR descricao LIKE ?)
          ORDER BY nome LIMIT 10'
    );
    $stmt->execute([$id, $curinga, $curinga]);
    foreach ($stmt as $c) {
        $resultados[] = [
            'tipo'    => 'caderno',
            'id'      => (int) $c['id'],
            'titulo'  => $c['nome'],
            'materia' => $c['materia'],
            'trecho'  => '',
            'url'     => 'caderno.php?id=' . (int) $c['id'],
        ];
    }

    /* ---- Baralhos ---- */
    $stmt = $pdo->prepare(
        'SELECT id, nome, materia FROM flashcard_decks
          WHERE usuario_id = ? AND nome LIKE ?
          ORDER BY nome LIMIT 10'
    );
    $stmt->execute([$id, $curinga]);
    foreach ($stmt as $d) {
        $resultados[] = [
            'tipo'    => 'baralho',
            'id'      => (int) $d['id'],
            'titulo'  => $d['nome'],
            'materia' => $d['materia'],
            'trecho'  => '',
            'url'     => 'flashcards.php?deck=' . (int) $d['id'],
        ];
    }

    /* ---- Cartões ----
       Leva para o baralho, não para o cartão: não existe tela de
       cartão avulso, e mandar para um lugar que não existe é pior
       do que não achar. */
    $stmt = $pdo->prepare(
        'SELECT c.id, c.frente, c.verso, d.id AS deck_id, d.nome AS deck, d.materia
           FROM flashcard_cartoes c
           JOIN flashcard_decks  d ON d.id = c.deck_id
          WHERE d.usuario_id = ? AND (c.frente LIKE ? OR c.verso LIKE ?)
          ORDER BY c.id DESC LIMIT 10'
    );
    $stmt->execute([$id, $curinga, $curinga]);
    foreach ($stmt as $c) {
        $resultados[] = [
            'tipo'    => 'cartao',
            'id'      => (int) $c['id'],
            'titulo'  => $c['frente'],
            'materia' => $c['materia'],
            'trecho'  => 'em ' . $c['deck'],
            'url'     => 'flashcards.php?deck=' . (int) $c['deck_id'],
        ];
    }

    estResponder(['q' => $q, 'resultados' => $resultados]);
} catch (PDOException $e) {
    estErro('A busca falhou.', 500);
}


/**
 * Um pedaço do texto em volta do termo, para a pessoa reconhecer o
 * resumo sem abrir. Devolve TEXTO PURO — quem destaca o termo é o
 * JS da tela, criando elementos; marcar aqui com <mark> obrigaria
 * a página a confiar em HTML vindo do banco.
 */
function trechoComTermo(string $corpo, string $termo): string {
    $corpo = trim(preg_replace('/\s+/u', ' ', $corpo));
    if ($corpo === '') {
        return '';
    }

    $pos = mb_stripos($corpo, $termo);
    if ($pos === false) {
        return mb_substr($corpo, 0, 140);
    }

    // 50 caracteres antes do termo dão contexto sem virar parágrafo.
    $ini = max(0, $pos - 50);
    $t   = mb_substr($corpo, $ini, 160);

    return ($ini > 0 ? '…' : '') . $t . '…';
}
