<?php
// ============================================================
//  KOSMOS — Criar / editar / excluir exercícios salvos
//  Arquivo: backend/php/exercicios_salvar.php
//  POST (protegido), campo "acao":
//    criar   -> materia_id, titulo, conteudo (JSON), dificuldade
//    editar  -> id, titulo, conteudo (JSON), dificuldade
//    excluir -> id
// ============================================================

require_once __DIR__ . '/resumos_comum.php';
require_once __DIR__ . '/exercicios_util.php';

$usuario = exigirLogin();
apiExigirPost();

$acao = $_POST['acao'] ?? '';

try {
    $pdo = conectar();
    liberarSessao();

    switch ($acao) {
        // ---------------------------------------------- criar
        case 'criar': {
            $erros      = [];
            $materiaId  = apiId('materia_id');
            $titulo     = apiTexto('titulo', 'Título', 140, $erros);
            $conteudo   = trim((string) ($_POST['conteudo'] ?? ''));
            $dificuldade = trim((string) ($_POST['dificuldade'] ?? 'Médio'));

            if ($erros) {
                apiErro($erros[0], 422);
            }
            if ($materiaId < 1) {
                apiErro('Matéria inválida.', 422);
            }
            if ($conteudo === '') {
                apiErro('Conteúdo vazio.', 422);
            }
            // Valida se é JSON válido
            json_decode($conteudo);
            if (json_last_error() !== JSON_ERROR_NONE) {
                apiErro('Conteúdo inválido (não é JSON).', 422);
            }
            if (!in_array($dificuldade, ['Fácil', 'Médio', 'Difícil', 'Misto'], true)) {
                $dificuldade = 'Médio';
            }

            // Verifica se a matéria pertence ao usuário
            $stmt = $pdo->prepare('SELECT id FROM exercicio_materias WHERE id = ? AND usuario_id = ? LIMIT 1');
            $stmt->execute([$materiaId, $usuario['id']]);
            if (!$stmt->fetch()) {
                apiErro('Matéria não encontrada.', 404);
            }

            $pdo->prepare('INSERT INTO exercicios
                               (usuario_id, materia_id, titulo, conteudo, dificuldade)
                           VALUES (?, ?, ?, ?, ?)')
                ->execute([$usuario['id'], $materiaId, $titulo, $conteudo, $dificuldade]);

            apiResponder([
                'ok'        => true,
                'msg'       => 'Exercício salvo!',
                'exercicio' => exercicioParaTela($pdo, (int) $pdo->lastInsertId(), (int) $usuario['id']),
            ]);
        }

        // --------------------------------------------- editar
        case 'editar': {
            $id         = apiId('id');
            $exercicio  = exercicioParaTela($pdo, $id, (int) $usuario['id']);
            if ($exercicio === null) {
                apiErro('Exercício não encontrado.', 404);
            }

            $erros      = [];
            $titulo     = apiTexto('titulo', 'Título', 140, $erros);
            $conteudo   = trim((string) ($_POST['conteudo'] ?? ''));
            $dificuldade = trim((string) ($_POST['dificuldade'] ?? 'Médio'));

            if ($erros) {
                apiErro($erros[0], 422);
            }
            if ($conteudo === '') {
                apiErro('Conteúdo vazio.', 422);
            }
            json_decode($conteudo);
            if (json_last_error() !== JSON_ERROR_NONE) {
                apiErro('Conteúdo inválido (não é JSON).', 422);
            }
            if (!in_array($dificuldade, ['Fácil', 'Médio', 'Difícil', 'Misto'], true)) {
                $dificuldade = 'Médio';
            }

            $pdo->prepare('UPDATE exercicios
                               SET titulo = ?, conteudo = ?, dificuldade = ?
                             WHERE id = ? AND usuario_id = ?')
                ->execute([$titulo, $conteudo, $dificuldade, $id, $usuario['id']]);

            apiResponder([
                'ok'        => true,
                'msg'       => 'Exercício atualizado!',
                'exercicio' => exercicioParaTela($pdo, $id, (int) $usuario['id']),
            ]);
        }

        // -------------------------------------------- excluir
        case 'excluir': {
            $id = apiId('id');
            $exercicio = exercicioParaTela($pdo, $id, (int) $usuario['id']);
            if ($exercicio === null) {
                apiErro('Exercício não encontrado.', 404);
            }

            $pdo->prepare('DELETE FROM exercicios WHERE id = ? AND usuario_id = ?')
                ->execute([$id, $usuario['id']]);

            apiResponder(['ok' => true, 'id' => $id, 'msg' => 'Exercício excluído.']);
        }

        default:
            apiErro('Ação desconhecida.');
    }
} catch (PDOException $e) {
    apiErro('Não foi possível salvar o exercício.', 500);
} catch (Throwable $e) {
    apiErro('Erro inesperado ao salvar o exercício.', 500);
}