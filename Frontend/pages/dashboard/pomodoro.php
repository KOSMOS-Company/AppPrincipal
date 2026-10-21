<?php
// Porteiro + dados desta página (sem sessão, redireciona antes de
// mandar qualquer HTML). Deixa $USUARIO, $PREF e $PAGINA prontos.
require_once __DIR__ . '/../../../Backend/php/pagina_dashboard.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../shared/favicon.svg">
    <title>Kosmos — Pomodoro</title>
    <link rel="stylesheet" href="<?= asset('../shared/tokens.css') ?>">
    <link rel="stylesheet" href="<?= asset('./css/dashboard.css') ?>">
    <link rel="stylesheet" href="<?= asset('../shared/cosmos.css') ?>">
    <link rel="stylesheet" href="<?= asset('./css/pomodoro-aviso.css') ?>">
    <link rel="stylesheet" href="<?= asset('./css/pomodoro.css') ?>">
    <link rel="stylesheet" href="<?= asset('../shared/cursor.css') ?>">
    <link rel="stylesheet" href="<?= asset('../shared/logo.css') ?>">
    <link rel="preload" href="../shared/fontes/dm-sans-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="../shared/fontes/syne-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= asset('../shared/fontes.css') ?>">
</head>
<body class="dashboard-fundo-estatico">

    <?php include __DIR__ . '/partes/fundo.php'; ?>

    <div class="contGeral">

        <?php include __DIR__ . '/partes/sidebar.php'; ?>

        <main class="contMeio">
            <header class="contCabeca">
                <div class="contCabeca__texto">
                    <h1>Método <span class="h-nome">Pomodoro</span></h1>
                    <p>Foque por blocos de tempo e descanse entre eles.</p>
                </div>
            </header>

            <div class="pomo">
                <!-- Modos -->
                <div class="pomo__modos">
                    <button class="pomo-modo active" data-modo="foco"   data-min="<?= (int) $PREF['pomo_foco'] ?>">Foco</button>
                    <button class="pomo-modo"        data-modo="curta"  data-min="<?= (int) $PREF['pomo_pausa'] ?>">Pausa curta</button>
                    <button class="pomo-modo"        data-modo="longa"  data-min="<?= (int) $PREF['pomo_pausa_longa'] ?>">Pausa longa</button>
                </div>

                <!-- Anel + tempo -->
                <div class="pomo__timer">
                    <svg class="pomo__ring" viewBox="0 0 280 280">
                        <circle class="pomo__ring-bg"  cx="140" cy="140" r="125"/>
                        <circle class="pomo__ring-fg"  cx="140" cy="140" r="125" id="anel"/>
                    </svg>
                    <div class="pomo__centro">
                        <div class="pomo__tempo" id="tempo"><?= sprintf('%02d:00', (int) $PREF['pomo_foco']) ?></div>
                        <div class="pomo__estado" id="estado">Hora de focar</div>
                    </div>
                </div>

                <!-- Controles -->
                <div class="pomo__controles">
                    <button class="dash-btn dash-btn--primary pomo__play" id="btnPlay">Iniciar</button>
                    <button class="dash-btn dash-btn--outline" id="btnReset">Reiniciar</button>
                </div>

                <!-- Ciclos -->
                <div class="pomo__ciclos">
                    Ciclos de foco concluídos: <strong id="ciclos">0</strong>
                </div>

                <!-- meta e durações vêm das preferências da aba Conta -->
                <div class="pomo__meta" id="pomoMeta">Sua meta: <?= (int) $PREF['meta_diaria'] ?> min por dia</div>
            </div>
        </main>
    </div>

    <script src="<?= asset('./js/util.js') ?>"></script>
    <script src="<?= asset('./js/dashboard.js') ?>"></script>
    <script src="<?= asset('./js/pomodoro-aviso.js') ?>"></script>
    <script src="<?= asset('./js/pomodoro.js') ?>"></script>
    <script src="<?= asset('../shared/cursor.js') ?>"></script>
    <!-- O céu. Os mesmos dois arquivos da landing page: o WebGL tenta
         primeiro e, se não houver placa ou o shader não compilar, ele
         desiste em silêncio e o cosmos.js (Canvas 2D) assume — por isso
         esta ordem importa. Ver partes/fundo.php. -->
    <script src="<?= asset('../shared/cosmos-gl.js') ?>"></script>
    <script src="<?= asset('../shared/cosmos.js') ?>"></script>
</body>
</html>
