<?php
// ============================================================
//  KOSMOS — Criar / editar / excluir / ordenar uma matéria de exercícios
//  Arquivo: backend/php/exercicios_materia.php
//  POST (protegido), campo "acao":
//    criar    -> nome, materia [, cor, icone, descricao]
//    editar   -> id, nome, materia [, cor, icone, descricao]
//    excluir  -> id
//    ordenar  -> ordem (ids separados por vírgula, na ordem nova)
//
//  Mesmo desenho do resumos_caderno.php — um endpoint, várias ações.
// ============================================================

require_once __DIR__ . '/resumos_comum.php';
require_once __DIR__ . '/exercicios_util.php';
require_once __DIR__ . '/materias.php';

$usuario = exigirLogin();
apiExigirPost();

$acao = $_POST['acao'] ?? '';

try {
    $pdo = conectar();
    liberarSessao();

    switch ($acao) {
        // ---------------------------------------------- criar
        case 'criar': {
            [$nome, $materia, $cor, $icone, $descricao] = apiLerExercicioMateria();

            /* ordem 0 = "nunca arrastado". Com ORDER BY ordem ASC,
               criado_em DESC, a matéria nova aparece na frente. */
            $pdo->prepare('INSERT INTO exercicio_materias
                               (usuario_id, nome, materia, cor, icone, descricao)
                           VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$usuario['id'], $nome, $materia, $cor, $icone, $descricao]);

            apiResponder([
                'ok'      => true,
                'msg'     => 'Matéria criada!',
                'materia' => exercicioMateriaParaTela($pdo, (int) $pdo->lastInsertId(), (int) $usuario['id']),
            ]);
        }

        // --------------------------------------------- editar
        case 'editar': {
            $id = apiId('id');
            if (exercicioMateriaDoUsuario($pdo, $id, (int) $usuario['id']) === null) {
                apiErro('Matéria não encontrada.', 404);
            }

            [$nome, $materia, $cor, $icone, $descricao] = apiLerExercicioMateria();

            $pdo->prepare('UPDATE exercicio_materias
                              SET nome = ?, materia = ?, cor = ?, icone = ?, descricao = ?
                            WHERE id = ? AND usuario_id = ?')
                ->execute([$nome, $materia, $cor, $icone, $descricao, $id, $usuario['id']]);

            apiResponder([
                'ok'      => true,
                'msg'     => 'Matéria atualizada!',
                'materia' => exercicioMateriaParaTela($pdo, $id, (int) $usuario['id']),
            ]);
        }

        // -------------------------------------------- excluir
        case 'excluir': {
            $id = apiId('id');
            if (exercicioMateriaDoUsuario($pdo, $id, (int) $usuario['id']) === null) {
                apiErro('Matéria não encontrada.', 404);
            }

            $pdo->prepare('DELETE FROM exercicio_materias WHERE id = ? AND usuario_id = ?')
                ->execute([$id, $usuario['id']]);

            apiResponder([
                'ok'  => true,
                'id'  => $id,
                'msg' => 'Matéria excluída.',
            ]);
        }

        // -------------------------------------------- ordenar
        case 'ordenar': {
            /* Vem a lista inteira de ids na ordem nova, não um "moveu
               de X para Y": assim uma requisição perdida não deixa a
               estante meio ordenada. */
            $ids = array_filter(array_map('intval', explode(',', (string) ($_POST['ordem'] ?? ''))));
            if (!$ids) {
                apiErro('Ordem vazia.');
            }

            // ids repetidos deixariam duas posições iguais
            $ids = array_values(array_unique($ids));

            $pdo->beginTransaction();
            try {
                // o WHERE usuario_id impede reordenar matéria de outra conta
                $upd = $pdo->prepare('UPDATE exercicio_materias SET ordem = ?
                                       WHERE id = ? AND usuario_id = ?');
                foreach ($ids as $i => $materiaId) {
                    $upd->execute([$i + 1, $materiaId, $usuario['id']]);
                }
                $pdo->commit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                throw $e;
            }

            apiResponder(['ok' => true, 'msg' => 'Ordem salva.']);
        }

        default:
            apiErro('Ação desconhecida.');
    }
} catch (PDOException $e) {
    apiErro('Não foi possível salvar a matéria.', 500);
}

/**
 * Lê e valida os campos da matéria de exercícios no POST.
 * Encerra a requisição se houver erro.
 * Devolve [nome, materia, cor, icone, descricao].
 */
function apiLerExercicioMateria(): array {
    $erros     = [];
    $nome      = apiTexto('nome', 'Nome da matéria', 120, $erros);
    $materia   = apiMateria($erros);
    $descricao = apiTexto('descricao', 'Descrição', 160, $erros, false);

    if ($erros) {
        apiErro($erros[0], 422);
    }

    // cor e ícone só podem sair das listas conhecidas (materias.php):
    // qualquer outra coisa vira o padrão, em vez de derrubar o salvamento
    $cor = (string) ($_POST['cor'] ?? '');
    if (!in_array($cor, CORES_CADERNO_KOSMOS, true)) {
        $cor = CORES_CADERNO_KOSMOS[0];
    }

    $icone = (string) ($_POST['icone'] ?? '');
    if (!in_array($icone, ICONES_CADERNO_KOSMOS, true)) {
        $icone = '';   // sem ícone é uma escolha válida
    }

    return [$nome, $materia, $cor, $icone, $descricao];
}

/** Verifica se a matéria pertence ao usuário. */
function exercicioMateriaDoUsuario(PDO $pdo, int $materiaId, int $usuarioId): ?array {
    $stmt = $pdo->prepare('SELECT id FROM exercicio_materias WHERE id = ? AND usuario_id = ? LIMIT 1');
    $stmt->execute([$materiaId, $usuarioId]);
    return $stmt->fetch() ? ['id' => $materiaId] : null;
}