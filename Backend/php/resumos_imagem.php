<?php
// ============================================================
//  KOSMOS — Imagens anexadas a um resumo
//  Arquivo: backend/php/resumos_imagem.php
//  POST (protegido), campo "acao":
//    enviar  -> resumo + arquivos em "imagens[]"
//    excluir -> id (da imagem)
//
//  Segurança: nada aqui confia no navegador. O tipo sai do
//  conteúdo do arquivo, o nome no disco é sorteado no servidor, e
//  a pasta de uploads tem .htaccess que desliga execução de
//  código. A checagem de dono é sempre pelo resumo: uma imagem só
//  é "sua" se o resumo dela for. Ver resumo_imagem_util.php.
// ============================================================

require_once __DIR__ . '/resumos_comum.php';

$usuario = exigirLogin();
apiExigirPost();

$acao = $_POST['acao'] ?? '';

try {
    $pdo = conectar();
    liberarSessao();

    switch ($acao) {
        // ---------------------------------------------- enviar
        case 'enviar': {
            $resumo = resumoDoUsuario($pdo, apiId('resumo'), (int) $usuario['id']);
            if ($resumo === null) {
                apiErro('Resumo não encontrado.', 404);
            }

            $resumoId = (int) $resumo['id'];
            $enviados = normalizarUploads($_FILES['imagens'] ?? null);

            if (!$enviados) {
                apiErro('Nenhuma imagem enviada.');
            }

            $jaTem = contarImagensDoResumo($pdo, $resumoId);
            $vagas = RS_MAX_IMAGENS - $jaTem;
            if ($vagas <= 0) {
                apiErro('Este resumo já tem ' . RS_MAX_IMAGENS . ' imagens.', 422);
            }
            if (count($enviados) > $vagas) {
                apiErro($vagas === 1
                    ? 'Ainda cabe só 1 imagem neste resumo.'
                    : "Ainda cabem só $vagas imagens neste resumo.", 422);
            }

            $pasta = garantirPastaResumoImagens();
            if ($pasta === null) {
                apiErro('Sem permissão para salvar imagens no servidor.', 500);
            }

            // A ordem continua de onde parou, para as novas entrarem no fim
            $stmt = $pdo->prepare('SELECT COALESCE(MAX(ordem), 0) AS m FROM resumo_imagens WHERE resumo_id = ?');
            $stmt->execute([$resumoId]);
            $ordem = (int) ($stmt->fetch()['m'] ?? 0);

            $inserir = $pdo->prepare('INSERT INTO resumo_imagens (resumo_id, arquivo, ordem)
                                      VALUES (?, ?, ?)');

            foreach ($enviados as $arquivo) {
                $erro = null;

                $tipo = validarUploadResumoImagem($arquivo, $erro);
                if ($tipo === null) {
                    apiErro($erro, 422);
                }

                $nome = guardarUploadResumoImagem($arquivo, $tipo, $pasta, $erro);
                if ($nome === null) {
                    apiErro($erro, 500);
                }

                $inserir->execute([$resumoId, $nome, ++$ordem]);
            }

            $imagens = imagensDoResumo($pdo, $resumoId);
            $novas   = count($enviados);

            apiResponder([
                'ok'      => true,
                'msg'     => $novas === 1 ? 'Imagem anexada!' : "$novas imagens anexadas!",
                'imagens' => $imagens,
            ]);
        }

        // --------------------------------------------- excluir
        case 'excluir': {
            $id = apiId('id');

            // Uma imagem só é "sua" se o resumo dela for
            $stmt = $pdo->prepare('SELECT i.id, i.arquivo, i.resumo_id
                                     FROM resumo_imagens i
                                     JOIN resumos r ON r.id = i.resumo_id
                                    WHERE i.id = ? AND r.usuario_id = ?
                                    LIMIT 1');
            $stmt->execute([$id, $usuario['id']]);
            $imagem = $stmt->fetch();

            if (!$imagem) {
                apiErro('Imagem não encontrada.', 404);
            }

            $pdo->prepare('DELETE FROM resumo_imagens WHERE id = ?')->execute([$id]);
            apagarArquivoResumoImagem($imagem['arquivo']);

            apiResponder([
                'ok'      => true,
                'msg'     => 'Imagem removida.',
                'imagens' => imagensDoResumo($pdo, (int) $imagem['resumo_id']),
            ]);
        }

        default:
            apiErro('Ação desconhecida.');
    }
} catch (PDOException $e) {
    apiErro('Não foi possível salvar as imagens.', 500);
}

/**
 * O PHP entrega um upload múltiplo como arrays paralelos
 * ($_FILES['imagens']['name'][0], ['tmp_name'][0]...). Aqui isso
 * volta a ser uma lista de arquivos, um array por arquivo, aceitando
 * também o caso de um arquivo só.
 */
function normalizarUploads($campo): array {
    if (!is_array($campo) || !isset($campo['tmp_name'])) {
        return [];
    }

    // upload de um arquivo só (name="imagens", sem os colchetes)
    if (!is_array($campo['tmp_name'])) {
        return $campo['error'] === UPLOAD_ERR_NO_FILE ? [] : [$campo];
    }

    $lista = [];
    foreach (array_keys($campo['tmp_name']) as $i) {
        if (($campo['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;   // campo vazio no formulário: não é erro
        }

        $lista[] = [
            'name'     => $campo['name'][$i]     ?? '',
            'type'     => $campo['type'][$i]     ?? '',
            'tmp_name' => $campo['tmp_name'][$i] ?? '',
            'error'    => $campo['error'][$i]    ?? UPLOAD_ERR_NO_FILE,
            'size'     => $campo['size'][$i]     ?? 0,
        ];
    }

    return $lista;
}
