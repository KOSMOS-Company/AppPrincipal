<?php
// ============================================================
//  KOSMOS — Leitura das matérias de exercícios e exercícios salvos
//  Arquivo: backend/php/exercicios_util.php
// ============================================================

/**
 * A ordem das matérias de exercícios.
 */
const EXERCICIOS_ORDEM = 'ORDER BY CASE WHEN m.ordem = 0 THEN 1 ELSE 0 END,
                                 m.ordem ASC, m.criado_em DESC, m.id DESC';

/**
 * Transforma uma linha de `exercicio_materias` no formato que as telas esperam.
 */
function exercicioMateriaDaLinha(array $m): array {
    return [
        'id'          => (int) $m['id'],
        'nome'        => $m['nome'],
        'materia'     => $m['materia'],
        'cor'         => $m['cor'] ?: 'roxo',
        'icone'       => $m['icone'] ?? '📝',
        'descricao'   => $m['descricao'] ?? '',
        'ordem'       => (int) ($m['ordem'] ?? 0),
    ];
}

/** As colunas das matérias. */
function exercicioMateriasSelect(): string {
    return 'SELECT m.id, m.nome, m.materia, m.cor, m.icone, m.descricao,
                   m.ordem
            FROM exercicio_materias m';
}

/** Todas as matérias de exercícios do usuário, já na ordem. */
function listarExercicioMaterias(PDO $pdo, int $usuarioId): array {
    $stmt = $pdo->prepare(exercicioMateriasSelect()
        . ' WHERE m.usuario_id = ? GROUP BY m.id ' . EXERCICIOS_ORDEM);
    $stmt->execute([$usuarioId]);

    $materias = [];
    foreach ($stmt as $m) {
        $materias[] = exercicioMateriaDaLinha($m);
    }

    return $materias;
}

/**
 * Uma matéria só, no mesmo formato.
 * Devolve null se a matéria não for desta conta.
 */
function exercicioMateriaParaTela(PDO $pdo, int $materiaId, int $usuarioId): ?array {
    $stmt = $pdo->prepare(exercicioMateriasSelect()
        . ' WHERE m.id = ? AND m.usuario_id = ? GROUP BY m.id LIMIT 1');
    $stmt->execute([$materiaId, $usuarioId]);
    $m = $stmt->fetch();

    return $m ? exercicioMateriaDaLinha($m) : null;
}

/* ============================================================
   EXERCÍCIOS SALVOS
   ============================================================ */

/**
 * Transforma uma linha de `exercicios` no formato que as telas esperam.
 */
function exercicioDaLinha(array $e): array {
    return [
        'id'           => (int) $e['id'],
        'usuario_id'   => (int) $e['usuario_id'],
        'materia_id'   => (int) $e['materia_id'],
        'titulo'       => $e['titulo'],
        'conteudo'     => $e['conteudo'],
        'dificuldade'  => $e['dificuldade'],
        'criado_em'    => $e['criado_em'],
        'atualizado_em'=> $e['atualizado_em'],
    ];
}

/** As colunas base dos exercícios. */
function exerciciosSelect(): string {
    return 'SELECT e.id, e.usuario_id, e.materia_id, e.titulo, e.conteudo,
                   e.dificuldade, e.criado_em, e.atualizado_em
            FROM exercicios e';
}

/** Lista exercícios de uma matéria. */
function listarExerciciosDaMateria(PDO $pdo, int $materiaId, int $usuarioId): array {
    $stmt = $pdo->prepare(exerciciosSelect()
        . ' WHERE e.materia_id = ? AND e.usuario_id = ?
          ORDER BY e.atualizado_em DESC, e.id DESC');
    $stmt->execute([$materiaId, $usuarioId]);

    $exercicios = [];
    foreach ($stmt as $e) {
        $exercicios[] = exercicioDaLinha($e);
    }
    return $exercicios;
}

/** Um exercício só. */
function exercicioParaTela(PDO $pdo, int $exercicioId, int $usuarioId): ?array {
    $stmt = $pdo->prepare(exerciciosSelect()
        . ' WHERE e.id = ? AND e.usuario_id = ? LIMIT 1');
    $stmt->execute([$exercicioId, $usuarioId]);
    $e = $stmt->fetch();
    return $e ? exercicioDaLinha($e) : null;
}