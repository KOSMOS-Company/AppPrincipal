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
    <link rel="stylesheet" href="./css/dashboard.css">
    <link rel="stylesheet" href="./css/pomodoro.css">
    <link rel="stylesheet" href="./css/cursor.css">
    <link rel="stylesheet" href="../shared/logo.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
</head>
<body>

    <div class="bg">
        <div class="bg__orb bg__orb--1"></div>
        <div class="bg__orb bg__orb--2"></div>
        <div class="bg__grid"></div>
    </div>

    <div class="contGeral">

        <?php include __DIR__ . '/partes/sidebar.php'; ?>

        <main class="contMeio">
            <header class="contCabeca">
                <div class="contCabeca__texto">
                    <span class="section-tag">Foco</span>
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

    <script src="./js/dashboard.js"></script>
    <script src="./js/pomodoro.js"></script>
    <script src="./js/cursor.js"></script>
</body>
</html>
