<?php
// ============================================================
//  KOSMOS — Apaga um resumo
//  Arquivo: backend/php/resumos_excluir.php
//  POST (protegido): id. Só apaga se o resumo for do usuário.
//
//  As linhas de `resumo_imagens` somem pelo ON DELETE CASCADE, mas
//  os ARQUIVOS no disco não: por isso eles são apagados aqui, antes
//  do DELETE, enquanto ainda dá para ler quais eram.
// ============================================================

require_once __DIR__ . '/resumos_comum.php';

$usuario = exigirLogin();
apiExigirPost();

$id = apiId('id');
if ($id < 1) {
    apiErro('Resumo inválido.');
}

try {
    $pdo = conectar();
    liberarSessao();

    if (resumoDoUsuario($pdo, $id, (int) $usuario['id']) === null) {
        apiErro('Resumo não encontrado.', 404);
    }

    // antes do DELETE: depois não há mais como saber quais eram
    apagarImagensDoResumo($pdo, $id);

    $pdo->prepare('DELETE FROM resumos WHERE id = ? AND usuario_id = ?')
        ->execute([$id, $usuario['id']]);

    apiResponder(['ok' => true, 'msg' => 'Resumo apagado.', 'id' => $id]);
} catch (PDOException $e) {
    apiErro('Não foi possível apagar o resumo.', 500);
}
