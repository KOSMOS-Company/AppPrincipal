<?php
// ============================================================
//  KOSMOS — Listar exercícios salvos
//  Arquivo: backend/php/exercicios_listar.php
//  GET (protegido): ?materia_id=X
// ============================================================

require_once __DIR__ . '/resumos_comum.php';
require_once __DIR__ . '/exercicios_util.php';

$usuario = exigirLogin();

$materiaId = filter_input(INPUT_GET, 'materia_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$id        = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;

try {
    $pdo = conectar();
    liberarSessao();

    // ?id=X -> um exercício só, já com as questões parseadas do conteudo
    if ($id > 0) {
        $e = exercicioParaTela($pdo, $id, (int) $usuario['id']);
        if ($e === null) {
            apiErro('Exercício não encontrado.', 404);
        }

        $conteudo = json_decode($e['conteudo'], true);
        $e['questoes']    = (is_array($conteudo) && isset($conteudo['questoes']) && is_array($conteudo['questoes']))
            ? array_values($conteudo['questoes'])
            : [];

        apiResponder(['ok' => true, 'exercicio' => $e]);
    }

    if ($materiaId < 1) {
        apiErro('materia_id inválido.', 422);
    }

    // Verifica se a matéria pertence ao usuário
    $stmt = $pdo->prepare('SELECT id FROM exercicio_materias WHERE id = ? AND usuario_id = ? LIMIT 1');
    $stmt->execute([$materiaId, $usuario['id']]);
    if (!$stmt->fetch()) {
        apiErro('Matéria não encontrada.', 404);
    }

    $exercicios = listarExerciciosDaMateria($pdo, $materiaId, (int) $usuario['id']);

    apiResponder(['ok' => true, 'exercicios' => $exercicios]);
} catch (PDOException $e) {
    apiErro('Não foi possível listar os exercícios.', 500);
}