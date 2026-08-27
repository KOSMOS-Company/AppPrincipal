<?php
// ============================================================
//  KOSMOS — Apaga um resumo
//  Arquivo: backend/php/resumos_excluir.php
//  POST (protegido): id. Só apaga se o resumo for do usuário.
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

    $pdo->prepare('DELETE FROM resumos WHERE id = ? AND usuario_id = ?')
        ->execute([$id, $usuario['id']]);

    apiResponder(['ok' => true, 'msg' => 'Resumo apagado.', 'id' => $id]);
} catch (PDOException $e) {
    apiErro('Não foi possível apagar o resumo.', 500);
}
