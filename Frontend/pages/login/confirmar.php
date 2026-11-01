<?php
// ============================================================
//  KOSMOS — Confirmar o e-mail do cadastro (botão do e-mail)
//  Arquivo: Frontend/pages/login/confirmar.php
//
//  O botão "Ativar minha conta" abre esta página com ?token=... e ela
//  confirma ali mesmo, no servidor, antes de mostrar qualquer coisa:
//  não depende de JavaScript nem de abrir o site. Funciona igual no
//  navegador do celular, dentro do app de e-mail.
//
//  Antes era confirmar.html + confirmar.js chamando o
//  confirmar_email.php (POST), que também abria a sessão. Agora a
//  página só confirma e oferece o botão de entrar: no celular a sessão
//  ficaria presa no navegador do app de e-mail, que ninguém usa depois.
//
//  O token NÃO é apagado ao confirmar (vence sozinho em 48 h): o Gmail
//  e o Outlook às vezes abrem o link antes da pessoa, para checar se é
//  golpe, e aí o clique dela daria "link inválido". Assim, abrir de
//  novo continua mostrando "confirmado". Ele não serve para mais nada:
//  só confirma, não entra na conta. Um link novo substitui o antigo.
// ============================================================

require_once __DIR__ . '/../../../Backend/php/conexao.php';

$token = trim((string) ($_GET['token'] ?? ''));
$ok    = false;
$texto = 'Este link é inválido ou já venceu. Na tela de login, tente entrar e peça um link novo.';

// O token do e-mail é sempre 64 caracteres hexadecimais (32 bytes)
if (preg_match('/^[0-9a-f]{64}$/', $token)) {
    try {
        $pdo  = conectar();
        $stmt = $pdo->prepare('SELECT id, nome FROM usuarios
                                WHERE confirma_token = ? AND confirma_expira > NOW()
                                LIMIT 1');
        $stmt->execute([hash('sha256', $token)]);
        $user = $stmt->fetch();

        if ($user) {
            $pdo->prepare('UPDATE usuarios SET email_confirmado = 1 WHERE id = ? AND email_confirmado = 0')
                ->execute([$user['id']]);
            $ok    = true;
            $nome  = explode(' ', trim((string) $user['nome']))[0];
            $texto = "Tudo certo, $nome! Sua conta está ativa e você já pode entrar no Kosmos.";
        }
    } catch (PDOException $e) {
        registrarErro($e, 'confirmar e-mail');
        $texto = 'Não foi possível confirmar agora. Tente abrir o link de novo em alguns minutos.';
    }
}

if (!$ok) {
    http_response_code(400);
}
$h = fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Kosmos — <?= $ok ? 'E-mail confirmado' : 'Confirmar e-mail' ?></title>
    <!-- Visual leve: a mesma escolha do dashboard (salva neste aparelho),
         lida antes de pintar. Os scripts de shared/ já respeitam a classe. -->
    <script src="../shared/visual-lite.js"></script>
    <link rel="stylesheet" href="../shared/tokens.css">
    <link rel="stylesheet" href="./css/login.css">
    <link rel="stylesheet" href="../shared/mouse-tracker.css">
    <link rel="stylesheet" href="../shared/cursor.css">
    <link rel="stylesheet" href="../shared/logo.css">
    <link rel="stylesheet" href="../shared/mascote.css">
    <script src="../shared/mascote.js" defer></script>
    <link rel="preload" href="../shared/fontes/dm-sans-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="../shared/fontes/syne-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="../shared/fontes.css">
    <link rel="stylesheet" href="../shared/cosmos.css">
    <style>
        .confirma-btn { text-decoration: none; margin-top: 22px; }
    </style>
</head>
<body>

    <!-- O céu: as mesmas regras e scripts da landing page e do dashboard -->
    <canvas class="cosmos" id="cosmos" aria-hidden="true"></canvas>
    <div class="veu" aria-hidden="true"></div>

    <div class="page--min">
        <div class="form-card form-card--estreito">
            <div class="mascote" data-mascote <?= $ok ? '' : 'data-mascote-humor="confuso"' ?> aria-hidden="true"></div>
            <div class="form-card__header">
                <a href="../inicio/index.html" class="logo">
                    <span class="logo__text klogo" role="img" aria-label="Kosmos">K<i class="klogo__o"></i>smos</span>
                </a>
                <h1 class="form-card__title form-card__title--espacado"><?= $ok ? 'E-mail confirmado ✓' : 'Link inválido' ?></h1>
                <p class="form-card__sub"><?= $h($texto) ?></p>
            </div>
            <a href="index.html" class="btn-submit confirma-btn"><?= $ok ? 'Entrar no Kosmos' : 'Ir para o login' ?></a>
        </div>
    </div>

    <script src="../shared/mouse-tracker.js"></script>
    <script src="../shared/cursor.js"></script>
    <!-- O WebGL tenta primeiro; sem placa ou com shader que não compila,
         ele desiste em silêncio e o Canvas 2D assume. A ordem importa. -->
    <script src="../shared/cosmos-gl.js"></script>
    <script src="../shared/cosmos.js"></script>
</body>
</html>
