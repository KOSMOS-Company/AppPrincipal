<?php
// ============================================================
//  KOSMOS — Leitura dos cadernos de resumos
//  Arquivo: backend/php/cadernos_util.php
//
//  O formato do caderno "como a tela usa" mora AQUI, num lugar só.
//  Antes a mesma consulta estava copiada em resumos.php, caderno.php,
//  resumos_listar.php e no modal — e bastava acrescentar uma coluna
//  (foi o caso de cor/ícone/capa) para as quatro saírem do ar uma de
//  cada vez.
//
//  Este arquivo é de propósito SEM efeito colateral: não manda
//  header, não abre sessão, não imprime nada. É por isso que tanto
//  as páginas (via pagina_dashboard.php) quanto os endpoints (via
//  resumos_comum.php) podem incluí-lo.
// ============================================================

require_once __DIR__ . '/resumo_imagem_util.php';   // urlCadernoCapa()

/**
 * A ordem da estante.
 *
 * `ordem` = 0 quer dizer "nunca foi arrastado": esses ficam no fim
 * da fila dos arrastados, mas entre si aparecem do mais novo para o
 * mais antigo. Arrastar grava 1..N, então quem foi posicionado à mão
 * sempre vem primeiro. O CASE é o que coloca o 0 por último sem
 * precisar de uma segunda coluna.
 */
const CADERNOS_ORDEM = 'ORDER BY CASE WHEN c.ordem = 0 THEN 1 ELSE 0 END,
                                 c.ordem ASC, c.criado_em DESC, c.id DESC';

/**
 * Transforma uma linha de `resumo_cadernos` no formato que as telas
 * e o JavaScript esperam. Um lugar só para: o nome dos campos, a URL
 * da capa e a conversão dos números.
 */
function cadernoDaLinha(array $c): array {
    return [
        'id'        => (int) $c['id'],
        'nome'      => $c['nome'],
        'materia'   => $c['materia'],
        'cor'       => $c['cor'] ?: 'roxo',
        'icone'     => $c['icone'] ?? '',
        'descricao' => $c['descricao'] ?? '',
        'capa'      => urlCadernoCapa($c['capa_arquivo'] ?? null),
        'ordem'     => (int) ($c['ordem'] ?? 0),
        'resumos'   => (int) ($c['resumos'] ?? 0),
        'fotos'     => (int) ($c['fotos'] ?? 0),
    ];
}

/** As colunas + as contagens. Usado pelas duas consultas abaixo. */
function cadernosSelect(): string {
    return 'SELECT c.id, c.nome, c.materia, c.cor, c.icone, c.descricao,
                   c.capa_arquivo, c.ordem,
                   COUNT(r.id) AS resumos,
                   COALESCE(SUM((SELECT COUNT(*) FROM resumo_imagens i
                                  WHERE i.resumo_id = r.id)), 0) AS fotos
              FROM resumo_cadernos c
         LEFT JOIN resumos r ON r.caderno_id = c.id';
}

/** Todos os cadernos do usuário, já na ordem da estante. */
function listarCadernos(PDO $pdo, int $usuarioId): array {
    $stmt = $pdo->prepare(cadernosSelect()
        . ' WHERE c.usuario_id = ? GROUP BY c.id ' . CADERNOS_ORDEM);
    $stmt->execute([$usuarioId]);

    $cadernos = [];
    foreach ($stmt as $c) {
        $cadernos[] = cadernoDaLinha($c);
    }

    return $cadernos;
}

/**
 * Um caderno só, no mesmo formato — para o endpoint devolver à tela
 * o caderno exatamente como ele ficou depois de salvar.
 * Devolve null se o caderno não for desta conta.
 */
function cadernoParaTela(PDO $pdo, int $cadernoId, int $usuarioId): ?array {
    $stmt = $pdo->prepare(cadernosSelect()
        . ' WHERE c.id = ? AND c.usuario_id = ? GROUP BY c.id LIMIT 1');
    $stmt->execute([$cadernoId, $usuarioId]);
    $c = $stmt->fetch();

    return $c ? cadernoDaLinha($c) : null;
}
