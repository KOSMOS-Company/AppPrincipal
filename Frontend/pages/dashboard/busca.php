<?php
// ============================================================
//  KOSMOS — Busca global
//  Procura em resumos, cadernos, baralhos, cartões e exercícios de uma vez.
//
//  Quem estuda não lembra ONDE guardou — lembra do assunto. Uma
//  busca por aba devolveria à pessoa uma decisão que o app pode
//  tomar sozinho.
//
//  Porteiro + dados desta página: deixa $USUARIO, $PREF e $PAGINA
//  prontos e redireciona antes de mandar qualquer HTML.
require_once __DIR__ . '/../../../Backend/php/pagina_dashboard.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../shared/favicon.svg">
    <title>Kosmos — Buscar</title>
    <link rel="stylesheet" href="<?= asset('../shared/tokens.css') ?>">
    <link rel="stylesheet" href="<?= asset('./css/dashboard.css') ?>">
    <link rel="stylesheet" href="<?= asset('../shared/cosmos.css') ?>">
    <link rel="stylesheet" href="<?= asset('./css/pomodoro-aviso.css') ?>">
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
                    <h1>Achar itens de <span class="h-nome">estudo</span></h1>
                    <p id="buscaResumo">Resumos, cadernos, flashcards e exercícios.</p>
                </div>
            </header>

            <!-- `type="search"` e não `text`: o teclado do celular mostra
                 a lupa em vez de "enter". O × é o nosso (SVG no estilo do
                 site); o nativo do navegador é escondido no CSS, porque
                 parece emoji. -->
            <form class="busca-campo" id="buscaForm" role="search">
                <label class="sr-only" for="buscaInput">Buscar</label>
                <div class="busca-campo__caixa">
                    <input type="search" id="buscaInput" autocomplete="off"
                           placeholder="Digite um assunto, título ou pergunta…"
                           autofocus>
                    <button class="busca-limpar" type="button" id="buscaLimpar" aria-label="Limpar busca" title="Limpar busca" hidden>
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M6 6l8 8M14 6l-8 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                    </button>
                </div>
                <button class="dash-btn dash-btn--primary" type="submit">Buscar</button>
            </form>

            <ul class="busca-lista" id="buscaLista"></ul>

            <div class="vazio" id="buscaVazio" hidden>
                <span class="vazio__ico" aria-hidden="true"><svg class="ico" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="6.6"/><path d="M15.9 15.9 20.6 20.6"/></svg></span>
                <h2 id="buscaVazioTitulo">Nada encontrado</h2>
                <p id="buscaVazioTexto">Tente outra palavra.</p>
            </div>
        </main>

    </div>

    <script src="<?= asset('./js/util.js') ?>"></script>
    <script src="<?= asset('./js/dashboard.js') ?>"></script>
    <script src="<?= asset('./js/pomodoro-aviso.js') ?>"></script>
    <script src="<?= asset('./js/busca.js') ?>"></script>
    <script src="<?= asset('../shared/cursor.js') ?>"></script>
    <!-- O céu. Os mesmos dois arquivos da landing page: o WebGL tenta
         primeiro e, se não houver placa ou o shader não compilar, ele
         desiste em silêncio e o cosmos.js (Canvas 2D) assume — por isso
         esta ordem importa. Ver partes/fundo.php. -->
    <script src="<?= asset('../shared/cosmos-gl.js') ?>"></script>
    <script src="<?= asset('../shared/cosmos.js') ?>"></script>
</body>
</html>
