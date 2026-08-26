<?php
// ============================================================
//  KOSMOS — Foto de perfil (avatar)
//  Arquivo: backend/php/conta_avatar.php
//  POST (protegido):
//    - com o arquivo "foto"  -> valida, guarda e salva o nome no banco
//    - com acao=remover      -> apaga o arquivo e volta para a inicial
//
//  Segurança: nada aqui confia no navegador. O tipo é detectado
//  pelo conteúdo (finfo + getimagesize), o nome do arquivo é
//  sorteado aqui, a extensão vem do tipo detectado e a pasta de
//  uploads tem .htaccess que desliga execução de código.
//  Se a extensão GD existir, o servidor reduz e regera a imagem
//  (defesa extra) — sem recortar, porque o enquadramento é
//  escolhido pelo usuário na aba Conta. Sem GD, guardamos o
//  arquivo enviado, que o navegador já reduz para 512px no
//  maior lado, mantendo a proporção.
// ============================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/avatar_util.php';

$usuario = exigirLogin();
$id      = (int) $usuario['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método não permitido.']);
    exit;
}

const AVATAR_MAX_BYTES = 2 * 1024 * 1024;          // 2 MB
const AVATAR_LADO      = 512;                       // maior lado permitido (se houver GD)
const AVATAR_TIPOS = [
    IMAGETYPE_JPEG => 'jpg',
    IMAGETYPE_PNG  => 'png',
    IMAGETYPE_WEBP => 'webp',
];

try {
    $pdo = conectar();

    // ---------- Remover a foto ----------
    if (($_POST['acao'] ?? '') === 'remover') {
        apagarAvatarDoUsuario($pdo, $id);
        $pdo->prepare('UPDATE usuario_preferencias SET avatar_arquivo = NULL WHERE usuario_id = ?')
            ->execute([$id]);

        echo json_encode([
            'ok'         => true,
            'msg'        => 'Foto removida.',
            'avatar_url' => null,
        ]);
        exit;
    }

    // ---------- Recebeu arquivo? ----------
    if (!isset($_FILES['foto'])) {
        echo json_encode(['ok' => false, 'msg' => 'Nenhuma imagem enviada.']);
        exit;
    }

    $foto = $_FILES['foto'];

    if ($foto['error'] !== UPLOAD_ERR_OK) {
        $recado = in_array($foto['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            ? 'A imagem é grande demais.'
            : 'Não foi possível receber a imagem.';
        echo json_encode(['ok' => false, 'msg' => $recado]);
        exit;
    }

    if ($foto['size'] <= 0 || $foto['size'] > AVATAR_MAX_BYTES) {
        echo json_encode(['ok' => false, 'msg' => 'A imagem precisa ter até 2 MB.']);
        exit;
    }

    if (!is_uploaded_file($foto['tmp_name'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => 'Envio inválido.']);
        exit;
    }

    // ---------- O conteúdo é realmente uma imagem? ----------
    $info = @getimagesize($foto['tmp_name']);
    if ($info === false || !isset(AVATAR_TIPOS[$info[2]])) {
        echo json_encode(['ok' => false, 'msg' => 'Envie uma imagem JPG, PNG ou WEBP.']);
        exit;
    }

    [$largura, $altura] = $info;
    if ($largura < 32 || $altura < 32) {
        echo json_encode(['ok' => false, 'msg' => 'A imagem é pequena demais (mínimo 32x32).']);
        exit;
    }
    if ($largura > 5000 || $altura > 5000) {
        echo json_encode(['ok' => false, 'msg' => 'A imagem tem dimensões grandes demais.']);
        exit;
    }

    // Confere também pelo mime real do arquivo
    if (function_exists('finfo_open')) {
        $fi   = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($fi, $foto['tmp_name']);
        finfo_close($fi);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            echo json_encode(['ok' => false, 'msg' => 'Formato de imagem não aceito.']);
            exit;
        }
    }

    // ---------- Grava ----------
    $pasta = pastaAvatares();
    if (!is_dir($pasta) && !@mkdir($pasta, 0775, true)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'msg' => 'A pasta de fotos não existe no servidor.']);
        exit;
    }
    if (!is_writable($pasta)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'msg' => 'Sem permissão para salvar a foto no servidor.']);
        exit;
    }

    $extensao = AVATAR_TIPOS[$info[2]];
    $nome     = bin2hex(random_bytes(16)) . '.' . $extensao;
    $destino  = $pasta . DIRECTORY_SEPARATOR . $nome;

    if (!move_uploaded_file($foto['tmp_name'], $destino)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'msg' => 'Não foi possível salvar a imagem.']);
        exit;
    }

    // Com GD disponível, o servidor reduz e regera a imagem (sem recortar:
    // o enquadramento é escolhido pelo usuário na aba Conta)
    limitarTamanho($destino, $info[2], AVATAR_LADO);

    // Sai a antiga, entra a nova
    apagarAvatarDoUsuario($pdo, $id);

    $sql = 'INSERT INTO usuario_preferencias (usuario_id, avatar_arquivo) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE avatar_arquivo = VALUES(avatar_arquivo)';
    $pdo->prepare($sql)->execute([$id, $nome]);

    echo json_encode([
        'ok'         => true,
        'msg'        => 'Foto atualizada!',
        'avatar_url' => urlAvatar($nome),
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Erro ao salvar a foto.']);
}
