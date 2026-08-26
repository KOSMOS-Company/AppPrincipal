<?php
// ============================================================
//  KOSMOS — Helpers da foto de perfil
//  Arquivo: backend/php/avatar_util.php
//  Usado por conta_avatar.php, conta_dados.php, usuario_atual.php
//  e conta_excluir.php (para não deixar arquivo órfão no disco).
// ============================================================

/** Pasta física onde as fotos ficam. */
function pastaAvatares(): string {
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'avatares';
}

/**
 * Caminho que o navegador usa, relativo às páginas do frontend
 * (Frontend/pages/<aba>/ -> ../../../Backend/uploads/avatares/x.jpg).
 * Devolve null quando não há foto.
 */
function urlAvatar(?string $arquivo): ?string {
    if ($arquivo === null || $arquivo === '') {
        return null;
    }
    // só o nome do arquivo, nunca um caminho vindo de fora
    $seguro = basename($arquivo);
    return '../../../Backend/uploads/avatares/' . $seguro;
}

/** Nome do arquivo de avatar salvo para este usuário (ou null). */
function avatarDoUsuario(PDO $pdo, int $usuarioId): ?string {
    $stmt = $pdo->prepare('SELECT avatar_arquivo FROM usuario_preferencias WHERE usuario_id = ? LIMIT 1');
    $stmt->execute([$usuarioId]);
    $row = $stmt->fetch();
    $nome = $row['avatar_arquivo'] ?? null;
    return ($nome === null || $nome === '') ? null : $nome;
}

/**
 * Apaga do disco a foto atual do usuário (se houver).
 * Não mexe no banco — quem chama decide o que gravar depois.
 */
function apagarAvatarDoUsuario(PDO $pdo, int $usuarioId): void {
    $nome = avatarDoUsuario($pdo, $usuarioId);
    if ($nome === null) {
        return;
    }
    $caminho = pastaAvatares() . DIRECTORY_SEPARATOR . basename($nome);
    if (is_file($caminho)) {
        @unlink($caminho);
    }
}

/**
 * Reduz a imagem (mantendo a proporção) se ela passar do lado máximo,
 * e a regera — o que também descarta qualquer coisa embutida no arquivo.
 *
 * NÃO recorta: o enquadramento é escolhido pelo usuário na aba Conta
 * (avatar_pos_x / avatar_pos_y viram background-position), então cortar
 * aqui jogaria fora a parte que ele talvez queira mostrar.
 *
 * Só roda se a extensão GD existir — sem ela o arquivo fica como veio
 * (o navegador já o envia reduzido). Em qualquer erro, mantém o
 * original: é melhor uma foto grande do que nenhuma.
 */
function limitarTamanho(string $caminho, int $tipo, int $lado): void {
    if (!extension_loaded('gd') || !function_exists('imagecreatetruecolor')) {
        return;
    }

    $origem = match ($tipo) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($caminho),
        IMAGETYPE_PNG  => @imagecreatefrompng($caminho),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($caminho) : false,
        default        => false,
    };
    if (!$origem) {
        return;
    }

    $lo = imagesx($origem);
    $al = imagesy($origem);
    $maior = max($lo, $al);

    // já está dentro do limite: nada a fazer
    if ($maior <= $lado) {
        imagedestroy($origem);
        return;
    }

    $escala = $lado / $maior;
    $novaL = max(1, (int) round($lo * $escala));
    $novaA = max(1, (int) round($al * $escala));

    $novo = imagecreatetruecolor($novaL, $novaA);

    // preserva transparência de PNG/WEBP
    if ($tipo === IMAGETYPE_PNG || $tipo === IMAGETYPE_WEBP) {
        imagealphablending($novo, false);
        imagesavealpha($novo, true);
        $transparente = imagecolorallocatealpha($novo, 0, 0, 0, 127);
        imagefilledrectangle($novo, 0, 0, $novaL, $novaA, $transparente);
    }

    imagecopyresampled($novo, $origem, 0, 0, 0, 0, $novaL, $novaA, $lo, $al);

    switch ($tipo) {
        case IMAGETYPE_JPEG: @imagejpeg($novo, $caminho, 88); break;
        case IMAGETYPE_PNG:  @imagepng($novo, $caminho, 8);   break;
        case IMAGETYPE_WEBP: if (function_exists('imagewebp')) @imagewebp($novo, $caminho, 88); break;
    }

    imagedestroy($novo);
    imagedestroy($origem);
}
