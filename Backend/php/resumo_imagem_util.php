<?php
// ============================================================
//  KOSMOS — Helpers das imagens anexadas aos resumos
//  Arquivo: backend/php/resumo_imagem_util.php
//
//  Mesmo padrão do avatar (avatar_util.php): o arquivo fica em
//  Backend/uploads/resumos/, com nome sorteado aqui, e a pasta de
//  uploads tem .htaccess que desliga execução de código.
//
//  Usado por resumos_imagem.php, resumos_excluir.php (para não
//  deixar arquivo órfão no disco), resumos_caderno.php e
//  conta_excluir.php.
// ============================================================

require_once __DIR__ . '/avatar_util.php';   // reaproveita limitarTamanho()

/** Quantas imagens um resumo pode ter. */
const RS_MAX_IMAGENS = 12;

/** Tamanho máximo por arquivo enviado. */
const RS_IMG_MAX_BYTES = 5 * 1024 * 1024;    // 5 MB

/** Maior lado guardado (foto de caderno não precisa de mais). */
const RS_IMG_LADO = 1600;

/** Tipos aceitos -> extensão usada no disco. */
const RS_IMG_TIPOS = [
    IMAGETYPE_JPEG => 'jpg',
    IMAGETYPE_PNG  => 'png',
    IMAGETYPE_WEBP => 'webp',
];

/** Pasta física onde as imagens dos resumos ficam. */
function pastaResumoImagens(): string {
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'resumos';
}

/**
 * Caminho que o navegador usa, relativo às páginas do frontend
 * (Frontend/pages/<aba>/ -> ../../../Backend/uploads/resumos/x.jpg).
 */
function urlResumoImagem(string $arquivo): string {
    // só o nome do arquivo, nunca um caminho vindo de fora
    return '../../../Backend/uploads/resumos/' . basename($arquivo);
}

/** Cria a pasta de uploads se ainda não existir. Devolve o caminho ou null. */
function garantirPastaResumoImagens(): ?string {
    $pasta = pastaResumoImagens();

    if (!is_dir($pasta) && !@mkdir($pasta, 0775, true)) {
        return null;
    }

    return is_writable($pasta) ? $pasta : null;
}

/** Imagens de um resumo, na ordem de exibição. */
function imagensDoResumo(PDO $pdo, int $resumoId): array {
    $stmt = $pdo->prepare('SELECT id, arquivo, legenda, ordem
                             FROM resumo_imagens
                            WHERE resumo_id = ?
                         ORDER BY ordem ASC, id ASC');
    $stmt->execute([$resumoId]);

    $imagens = [];
    foreach ($stmt as $img) {
        $imagens[] = [
            'id'      => (int) $img['id'],
            'legenda' => $img['legenda'],
            'url'     => urlResumoImagem($img['arquivo']),
        ];
    }

    return $imagens;
}

/** Apaga um arquivo de imagem do disco (aceita só o nome). */
function apagarArquivoResumoImagem(?string $arquivo): void {
    if ($arquivo === null || $arquivo === '') {
        return;
    }

    $caminho = pastaResumoImagens() . DIRECTORY_SEPARATOR . basename($arquivo);
    if (is_file($caminho)) {
        @unlink($caminho);
    }
}

/**
 * Apaga do disco as imagens de um resumo. NÃO mexe no banco: as
 * linhas de `resumo_imagens` somem pelo ON DELETE CASCADE quando o
 * resumo é apagado. Chame ANTES do DELETE, para ainda haver o que ler.
 */
function apagarImagensDoResumo(PDO $pdo, int $resumoId): void {
    $stmt = $pdo->prepare('SELECT arquivo FROM resumo_imagens WHERE resumo_id = ?');
    $stmt->execute([$resumoId]);

    foreach ($stmt as $img) {
        apagarArquivoResumoImagem($img['arquivo']);
    }
}

/**
 * Confere se o arquivo enviado é mesmo uma imagem aceita.
 * Nada aqui confia no navegador: o tipo sai do conteúdo do arquivo
 * (getimagesize + finfo), nunca do nome ou do Content-Type.
 *
 * Devolve o IMAGETYPE_* detectado, ou null pondo o motivo em $erro.
 */
function validarUploadResumoImagem(array $arquivo, ?string &$erro): ?int {
    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        $erro = in_array($arquivo['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            ? 'A imagem é grande demais.'
            : 'Não foi possível receber a imagem.';
        return null;
    }

    if ($arquivo['size'] <= 0 || $arquivo['size'] > RS_IMG_MAX_BYTES) {
        $erro = 'Cada imagem precisa ter até 5 MB.';
        return null;
    }

    if (!is_uploaded_file($arquivo['tmp_name'])) {
        $erro = 'Envio inválido.';
        return null;
    }

    $info = @getimagesize($arquivo['tmp_name']);
    if ($info === false || !isset(RS_IMG_TIPOS[$info[2]])) {
        $erro = 'Envie uma imagem JPG, PNG ou WEBP.';
        return null;
    }

    [$largura, $altura] = $info;
    if ($largura < 32 || $altura < 32) {
        $erro = 'A imagem é pequena demais.';
        return null;
    }
    if ($largura > 10000 || $altura > 10000) {
        $erro = 'A imagem tem dimensões grandes demais.';
        return null;
    }

    // Confere também pelo mime real do arquivo
    if (function_exists('finfo_open')) {
        $fi   = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($fi, $arquivo['tmp_name']);
        finfo_close($fi);

        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $erro = 'Formato de imagem não aceito.';
            return null;
        }
    }

    $erro = null;
    return $info[2];
}

/**
 * Move o arquivo enviado para a pasta de uploads com um nome novo e
 * sorteado, reduzindo a imagem se houver GD. Devolve o nome gravado
 * ou null pondo o motivo em $erro.
 */
function guardarUploadResumoImagem(array $arquivo, int $tipo, string $pasta, ?string &$erro, int $lado = RS_IMG_LADO): ?string {
    $nome    = bin2hex(random_bytes(16)) . '.' . RS_IMG_TIPOS[$tipo];
    $destino = $pasta . DIRECTORY_SEPARATOR . $nome;

    if (!move_uploaded_file($arquivo['tmp_name'], $destino)) {
        $erro = 'Não foi possível salvar a imagem.';
        return null;
    }

    // Com GD, o servidor reduz e regera a imagem — o que também
    // descarta qualquer coisa embutida no arquivo original.
    limitarTamanho($destino, $tipo, $lado);

    $erro = null;
    return $nome;
}

/**
 * Apaga do disco TODAS as imagens de resumo de um usuário. Usado ao
 * excluir a conta: o ON DELETE CASCADE limpa as linhas do banco, mas
 * nunca os arquivos. Chame ANTES do DELETE do usuário.
 */
function apagarImagensDoUsuario(PDO $pdo, int $usuarioId): void {
    $stmt = $pdo->prepare('SELECT i.arquivo
                             FROM resumo_imagens i
                             JOIN resumos r ON r.id = i.resumo_id
                            WHERE r.usuario_id = ?');
    $stmt->execute([$usuarioId]);

    foreach ($stmt as $img) {
        apagarArquivoResumoImagem($img['arquivo']);
    }
}

/* ------------------------------------------------------------
   CAPA DO CADERNO
   Mesma validação e mesma gravação das imagens de resumo (as
   funções acima já recebem a pasta por parâmetro), só que numa
   pasta própria — assim apagar um caderno nunca esbarra numa
   imagem de resumo.
   ------------------------------------------------------------ */

/** Maior lado guardado para a capa (ela aparece pequena no cartão). */
const RS_CAPA_LADO = 900;

/** Pasta física onde as capas de caderno ficam. */
function pastaCadernoCapas(): string {
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'cadernos';
}

/** Caminho que o navegador usa, relativo às páginas do frontend. */
function urlCadernoCapa(?string $arquivo): ?string {
    if ($arquivo === null || $arquivo === '') {
        return null;
    }
    // só o nome do arquivo, nunca um caminho vindo de fora
    return '../../../Backend/uploads/cadernos/' . basename($arquivo);
}

/** Cria a pasta das capas se ainda não existir. Devolve o caminho ou null. */
function garantirPastaCadernoCapas(): ?string {
    $pasta = pastaCadernoCapas();

    if (!is_dir($pasta) && !@mkdir($pasta, 0775, true)) {
        return null;
    }

    return is_writable($pasta) ? $pasta : null;
}

/** Apaga do disco o arquivo de uma capa (aceita só o nome). */
function apagarArquivoCadernoCapa(?string $arquivo): void {
    if ($arquivo === null || $arquivo === '') {
        return;
    }

    $caminho = pastaCadernoCapas() . DIRECTORY_SEPARATOR . basename($arquivo);
    if (is_file($caminho)) {
        @unlink($caminho);
    }
}

/**
 * Apaga do disco a capa de um caderno, lendo o nome no banco.
 * Chame ANTES do DELETE do caderno, para ainda haver o que ler.
 */
function apagarCapaDoCaderno(PDO $pdo, int $cadernoId): void {
    $stmt = $pdo->prepare('SELECT capa_arquivo FROM resumo_cadernos WHERE id = ?');
    $stmt->execute([$cadernoId]);
    $linha = $stmt->fetch();

    if ($linha) {
        apagarArquivoCadernoCapa($linha['capa_arquivo']);
    }
}

/**
 * Apaga do disco TODAS as capas de caderno de um usuário. Usado ao
 * excluir a conta: o ON DELETE CASCADE limpa as linhas do banco, mas
 * nunca os arquivos. Chame ANTES do DELETE do usuário.
 */
function apagarCapasDoUsuario(PDO $pdo, int $usuarioId): void {
    $stmt = $pdo->prepare('SELECT capa_arquivo FROM resumo_cadernos
                            WHERE usuario_id = ? AND capa_arquivo IS NOT NULL');
    $stmt->execute([$usuarioId]);

    foreach ($stmt as $linha) {
        apagarArquivoCadernoCapa($linha['capa_arquivo']);
    }
}
