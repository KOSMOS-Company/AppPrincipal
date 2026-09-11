<?php
// ============================================================
//  KOSMOS — Capa de um caderno
//  Arquivo: backend/php/resumos_capa.php
//  POST (protegido), campo "acao":
//    enviar  -> caderno + arquivo em "capa"
//    remover -> caderno
//
//  Mesma máquina das imagens de resumo (resumo_imagem_util.php):
//  tipo detectado pelo conteúdo, nome sorteado no servidor, pasta
//  com .htaccess que desliga execução de código. Só muda a pasta —
//  capas ficam em Backend/uploads/cadernos.
// ============================================================

require_once __DIR__ . '/resumos_comum.php';

$usuario = exigirLogin();
apiExigirPost();

$acao = $_POST['acao'] ?? '';

try {
    $pdo = conectar();
    liberarSessao();

    $usuarioId = (int) $usuario['id'];
    $caderno   = cadernoDoUsuario($pdo, apiId('caderno'), $usuarioId);

    if ($caderno === null) {
        apiErro('Caderno não encontrado.', 404);
    }
    $cadernoId = (int) $caderno['id'];

    switch ($acao) {
        // ---------------------------------------------- remover
        case 'remover': {
            apagarCapaDoCaderno($pdo, $cadernoId);

            $pdo->prepare('UPDATE resumo_cadernos SET capa_arquivo = NULL
                            WHERE id = ? AND usuario_id = ?')
                ->execute([$cadernoId, $usuarioId]);

            apiResponder([
                'ok'      => true,
                'msg'     => 'Capa removida.',
                'caderno' => cadernoParaTela($pdo, $cadernoId, $usuarioId),
            ]);
        }

        // ----------------------------------------------- enviar
        case 'enviar': {
            if (!isset($_FILES['capa'])) {
                apiErro('Nenhuma imagem enviada.');
            }

            $erro = null;
            $tipo = validarUploadResumoImagem($_FILES['capa'], $erro);
            if ($tipo === null) {
                apiErro($erro, 422);
            }

            $pasta = garantirPastaCadernoCapas();
            if ($pasta === null) {
                apiErro('Sem permissão para salvar a capa no servidor.', 500);
            }

            $nome = guardarUploadResumoImagem($_FILES['capa'], $tipo, $pasta, $erro, RS_CAPA_LADO);
            if ($nome === null) {
                apiErro($erro, 500);
            }

            // sai a antiga, entra a nova
            apagarCapaDoCaderno($pdo, $cadernoId);

            $pdo->prepare('UPDATE resumo_cadernos SET capa_arquivo = ?
                            WHERE id = ? AND usuario_id = ?')
                ->execute([$nome, $cadernoId, $usuarioId]);

            apiResponder([
                'ok'      => true,
                'msg'     => 'Capa atualizada!',
                'caderno' => cadernoParaTela($pdo, $cadernoId, $usuarioId),
            ]);
        }

        default:
            apiErro('Ação desconhecida.');
    }
} catch (PDOException $e) {
    apiErro('Não foi possível salvar a capa.', 500);
}
