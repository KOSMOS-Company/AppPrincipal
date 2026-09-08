<?php
// ============================================================
//  KOSMOS — Cria ou atualiza um resumo
//  Arquivo: backend/php/resumos_salvar.php
//  POST (protegido): titulo, materia, corpo [, id]
//   - sem id  -> cria um resumo novo
//   - com id  -> atualiza, mas só se o resumo for do usuário
// ============================================================

require_once __DIR__ . '/resumos_comum.php';

$usuario = exigirLogin();
apiExigirPost();

$erros   = [];
$id      = apiId('id');                                    // 0 = novo
$titulo  = apiTexto('titulo', 'Título', RS_MAX_TITULO, $erros);
$corpo   = apiTexto('corpo', 'Resumo', RS_MAX_CORPO, $erros);
$materia = apiMateria($erros);

if ($erros) {
    apiErro($erros[0], 422);
}

try {
    $pdo = conectar();
    liberarSessao();

    if ($id > 0) {
        // Editar: o resumo tem de ser desta conta
        if (resumoDoUsuario($pdo, $id, (int) $usuario['id']) === null) {
            apiErro('Resumo não encontrado.', 404);
        }

        $pdo->prepare('UPDATE resumos
                          SET titulo = ?, materia = ?, corpo = ?
                        WHERE id = ? AND usuario_id = ?')
            ->execute([$titulo, $materia, $corpo, $id, $usuario['id']]);

        $msg = 'Resumo atualizado!';
    } else {
        // Criar: a data é do MySQL (NOW), nunca do PHP
        $pdo->prepare('INSERT INTO resumos (usuario_id, titulo, materia, corpo)
                       VALUES (?, ?, ?, ?)')
            ->execute([$usuario['id'], $titulo, $materia, $corpo]);

        $id  = (int) $pdo->lastInsertId();
        $msg = 'Resumo salvo!';
    }

    // Devolve o resumo como ele ficou, para a tela atualizar sem recarregar
    $stmt = $pdo->prepare('SELECT id, titulo, materia, corpo,
                                  DAY(criado_em) AS dia, MONTH(criado_em) AS mes
                             FROM resumos WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $r = $stmt->fetch();

    apiResponder([
        'ok'     => true,
        'msg'    => $msg,
        'resumo' => [
            'id'      => (int) $r['id'],
            'titulo'  => $r['titulo'],
            'materia' => $r['materia'],
            'corpo'   => $r['corpo'],
            'quando'  => apiDataCurta((int) $r['dia'], (int) $r['mes']),
        ],
    ]);
} catch (PDOException $e) {
    apiErro('Não foi possível salvar o resumo.', 500);
}
