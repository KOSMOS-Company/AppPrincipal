<?php
// ============================================================
//  KOSMOS — Criar / editar / excluir / ordenar um caderno
//  Arquivo: backend/php/resumos_caderno.php
//  POST (protegido), campo "acao":
//    criar    -> nome, materia [, cor, icone, descricao]
//    editar   -> id, nome, materia [, cor, icone, descricao]
//    excluir  -> id
//    ordenar  -> ordem (ids separados por vírgula, na ordem nova)
//
//  Apagar o caderno NÃO apaga os resumos: a FK é ON DELETE SET
//  NULL, então eles voltam para "Sem caderno". Arrumar a estante
//  não é jogar fora o que a pessoa escreveu. A capa (imagem) é do
//  resumos_capa.php; aqui só a apagamos do disco junto com o caderno.
//
//  Mesmo desenho do flashcards_deck.php — um endpoint, várias ações.
// ============================================================

require_once __DIR__ . '/resumos_comum.php';

$usuario = exigirLogin();
apiExigirPost();

require_once __DIR__ . '/ProgressoService.php';

$acao = $_POST['acao'] ?? '';

try {
    $pdo = conectar();
    liberarSessao();

    switch ($acao) {
        // ---------------------------------------------- criar
        case 'criar': {
            [$nome, $materia, $cor, $icone, $descricao] = apiLerCaderno();

            /* ordem 0 = "nunca arrastado". Com ORDER BY ordem ASC,
               criado_em DESC, o caderno novo aparece na frente. */
            $pdo->prepare('INSERT INTO resumo_cadernos
                               (usuario_id, nome, materia, cor, icone, descricao)
                           VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$usuario['id'], $nome, $materia, $cor, $icone, $descricao]);

            $cadernoId = (int) $pdo->lastInsertId();

            apiResponder([
                'ok'        => true,
                'msg'       => 'Caderno criado!',
                'caderno'   => cadernoParaTela($pdo, $cadernoId, (int) $usuario['id']),
                // Mesmo orçamento dos resumos novos (teto diário no ProgressoService)
                'progresso' => progressoRegistrar($pdo, (int) $usuario['id'], [[
                    'acao'     => 'conteudo_criado',
                    'xp'       => ProgressoService::XP['conteudo_criado'],
                    'rotulo'   => 'Novo caderno',
                    'detalhes' => ['caderno' => $cadernoId],
                ]]),
            ]);
        }

        // --------------------------------------------- editar
        case 'editar': {
            $id = apiId('id');
            if (cadernoDoUsuario($pdo, $id, (int) $usuario['id']) === null) {
                apiErro('Caderno não encontrado.', 404);
            }

            [$nome, $materia, $cor, $icone, $descricao] = apiLerCaderno();

            $pdo->prepare('UPDATE resumo_cadernos
                              SET nome = ?, materia = ?, cor = ?, icone = ?, descricao = ?
                            WHERE id = ? AND usuario_id = ?')
                ->execute([$nome, $materia, $cor, $icone, $descricao, $id, $usuario['id']]);

            /* A matéria é do caderno, mas cada resumo guarda a sua cópia
               (é o que faz o filtro por matéria e os resumos soltos
               continuarem funcionando). Trocou a do caderno, os resumos
               de dentro acompanham. */
            $pdo->prepare('UPDATE resumos SET materia = ? WHERE caderno_id = ? AND usuario_id = ?')
                ->execute([$materia, $id, $usuario['id']]);

            apiResponder([
                'ok'      => true,
                'msg'     => 'Caderno atualizado!',
                'caderno' => cadernoParaTela($pdo, $id, (int) $usuario['id']),
            ]);
        }

        // -------------------------------------------- excluir
        case 'excluir': {
            $id = apiId('id');
            if (cadernoDoUsuario($pdo, $id, (int) $usuario['id']) === null) {
                apiErro('Caderno não encontrado.', 404);
            }

            $soltos = contarResumosDoCaderno($pdo, $id, (int) $usuario['id']);

            // a capa está no disco, fora do banco: some junto
            apagarCapaDoCaderno($pdo, $id);

            // Os resumos ficam: a FK é ON DELETE SET NULL.
            $pdo->prepare('DELETE FROM resumo_cadernos WHERE id = ? AND usuario_id = ?')
                ->execute([$id, $usuario['id']]);

            apiResponder([
                'ok'     => true,
                'id'     => $id,
                'soltos' => $soltos,
                'msg'    => $soltos === 0
                    ? 'Caderno excluído.'
                    : 'Caderno excluído. ' . ($soltos === 1
                        ? 'O resumo dele foi para "Sem caderno".'
                        : "Os $soltos resumos dele foram para \"Sem caderno\"."),
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
                // o WHERE usuario_id impede reordenar caderno de outra conta
                $upd = $pdo->prepare('UPDATE resumo_cadernos SET ordem = ?
                                       WHERE id = ? AND usuario_id = ?');
                foreach ($ids as $i => $cadernoId) {
                    $upd->execute([$i + 1, $cadernoId, $usuario['id']]);
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
    apiErro('Não foi possível salvar o caderno.', 500);
}

/**
 * Lê e valida os campos do caderno no POST.
 * Encerra a requisição se houver erro.
 * Devolve [nome, materia, cor, icone, descricao].
 */
function apiLerCaderno(): array {
    $erros     = [];
    $nome      = apiTexto('nome', 'Nome do caderno', RS_MAX_CADERNO, $erros);
    $materia   = apiMateria($erros);
    $descricao = apiTexto('descricao', 'Descrição', RS_MAX_DESCRICAO, $erros, false);

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

/** Quantos resumos estão dentro deste caderno. */
function contarResumosDoCaderno(PDO $pdo, int $cadernoId, int $usuarioId): int {
    $stmt = $pdo->prepare('SELECT COUNT(*) AS n FROM resumos WHERE caderno_id = ? AND usuario_id = ?');
    $stmt->execute([$cadernoId, $usuarioId]);

    return (int) ($stmt->fetch()['n'] ?? 0);
}
