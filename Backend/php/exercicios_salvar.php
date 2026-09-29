<?php
// ============================================================
//  KOSMOS — Criar / editar / excluir exercícios salvos
//  Arquivo: backend/php/exercicios_salvar.php
//  POST (protegido), campo "acao":
//    criar   -> materia_id, titulo, conteudo (JSON), dificuldade
//    editar  -> id, titulo, dificuldade (conteudo é ignorado)
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

            /* Editar mexe só no título e na dificuldade. As questões (com o
               gabarito) nascem da IA e ficam como estão: aceitar `conteudo`
               aqui deixava o cliente reescrever as respostas certas e farmar
               XP na prática. A tela de edição nunca muda as questões — ela
               só reenviava o que tinha lido —, então ignorar não tira nada. */
            $erros      = [];
            $titulo     = apiTexto('titulo', 'Título', 140, $erros);
            $dificuldade = trim((string) ($_POST['dificuldade'] ?? ''));

            if ($erros) {
                apiErro($erros[0], 422);
            }
            if (!in_array($dificuldade, ['Fácil', 'Médio', 'Difícil', 'Misto'], true)) {
                $dificuldade = $exercicio['dificuldade'];   // sem valor válido, mantém a atual
            }

            $pdo->prepare('UPDATE exercicios
                               SET titulo = ?, dificuldade = ?
                             WHERE id = ? AND usuario_id = ?')
                ->execute([$titulo, $dificuldade, $id, $usuario['id']]);

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