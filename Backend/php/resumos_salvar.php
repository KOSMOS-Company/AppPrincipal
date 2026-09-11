<?php
// ============================================================
//  KOSMOS — Cria ou atualiza um resumo
//  Arquivo: backend/php/resumos_salvar.php
//  POST (protegido): titulo [, caderno] [, materia] [, corpo] [, id]
//   - sem id  -> cria um resumo novo
//   - com id  -> atualiza, mas só se o resumo for do usuário
//
//  Sobre a matéria: ela é do CADERNO. Se o resumo está num caderno,
//  a matéria vem de lá e o campo "materia" do POST é ignorado —
//  cada resumo guarda a sua cópia só para o filtro e para os
//  resumos soltos ("Sem caderno"), que não têm caderno de onde
//  herdar e por isso escolhem a sua.
//
//  Sobre o corpo: é opcional. Um resumo pode ser só as fotos do
//  caderno de papel (ver resumos_imagem.php). O que não vale é um
//  resumo sem nada: sem texto, sem imagem já anexada e sem imagem
//  a caminho (o campo "com_imagens", que a tela manda quando há
//  arquivos escolhidos esperando o resumo existir para subir).
// ============================================================

require_once __DIR__ . '/resumos_comum.php';

$usuario = exigirLogin();
apiExigirPost();

$erros  = [];
$id     = apiId('id');                                      // 0 = novo
$titulo = apiTexto('titulo', 'Título', RS_MAX_TITULO, $erros);
$corpo  = apiTexto('corpo', 'Resumo', RS_MAX_CORPO, $erros, false);

if ($erros) {
    apiErro($erros[0], 422);
}

try {
    $pdo = conectar();
    liberarSessao();

    $usuarioId = (int) $usuario['id'];
    $caderno   = apiCadernoEscolhido($pdo, $usuarioId);       // null = solto

    // Dentro de um caderno a matéria é a dele; solto, o resumo escolhe
    if ($caderno !== null) {
        $materia = $caderno['materia'];
    } else {
        $materia = apiMateria($erros);
        if ($erros) {
            apiErro($erros[0], 422);
        }
    }

    // Um resumo precisa ter alguma coisa: texto, imagem já anexada,
    // ou imagem a caminho
    $temImagens = ($id > 0 && contarImagensDoResumo($pdo, $id) > 0)
               || !empty($_POST['com_imagens']);

    if ($corpo === '' && !$temImagens) {
        apiErro('Escreva o resumo ou anexe uma imagem.', 422);
    }

    $cadernoId = $caderno === null ? null : (int) $caderno['id'];

    if ($id > 0) {
        // Editar: o resumo tem de ser desta conta
        if (resumoDoUsuario($pdo, $id, $usuarioId) === null) {
            apiErro('Resumo não encontrado.', 404);
        }

        $pdo->prepare('UPDATE resumos
                          SET titulo = ?, materia = ?, corpo = ?, caderno_id = ?
                        WHERE id = ? AND usuario_id = ?')
            ->execute([$titulo, $materia, $corpo, $cadernoId, $id, $usuarioId]);

        $msg = 'Resumo atualizado!';
    } else {
        // Criar: a data é do MySQL (NOW), nunca do PHP
        $pdo->prepare('INSERT INTO resumos (usuario_id, caderno_id, titulo, materia, corpo)
                       VALUES (?, ?, ?, ?, ?)')
            ->execute([$usuarioId, $cadernoId, $titulo, $materia, $corpo]);

        $id  = (int) $pdo->lastInsertId();
        $msg = 'Resumo salvo!';
    }

    // Devolve o resumo como ele ficou, para a tela atualizar sem recarregar
    $stmt = $pdo->prepare('SELECT id, titulo, materia, corpo, caderno_id,
                                  DAY(criado_em) AS dia, MONTH(criado_em) AS mes
                             FROM resumos WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $r = $stmt->fetch();

    $imagens = imagensDoResumo($pdo, $id);

    apiResponder([
        'ok'     => true,
        'msg'    => $msg,
        'resumo' => [
            'id'         => (int) $r['id'],
            'titulo'     => $r['titulo'],
            'materia'    => $r['materia'],
            'corpo'      => $r['corpo'],
            'caderno_id' => $r['caderno_id'] === null ? null : (int) $r['caderno_id'],
            'quando'     => apiDataCurta((int) $r['dia'], (int) $r['mes']),
            'imagens'    => $imagens,
            'fotos'      => count($imagens),
        ],
    ]);
} catch (PDOException $e) {
    apiErro('Não foi possível salvar o resumo.', 500);
}
