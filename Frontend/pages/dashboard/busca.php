<?php
// ============================================================
//  KOSMOS — Busca global
//  Procura em resumos, cadernos, baralhos e cartões de uma vez.
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
    <link rel="stylesheet" href="./css/dashboard.css">
    <link rel="stylesheet" href="../shared/cosmos.css">
    <link rel="stylesheet" href="./css/pomodoro-aviso.css">
    <link rel="stylesheet" href="./css/cursor.css">
    <link rel="stylesheet" href="../shared/logo.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
</head>
<body>

    <?php include __DIR__ . '/partes/fundo.php'; ?>

    <div class="contGeral">

        <?php include __DIR__ . '/partes/sidebar.php'; ?>

        <main class="contMeio">
            <header class="contCabeca">
                <div class="contCabeca__texto">
                    <span class="section-tag">Busca</span>
                    <h1>Achar <span class="h-nome">qualquer coisa</span></h1>
                    <p id="buscaResumo">Resumos, cadernos, baralhos e cartões.</p>
                </div>
            </header>

            <!-- `type="search"` e não `text`: o teclado do celular mostra
                 a lupa em vez de "enter", e o navegador oferece o × para
                 limpar de graça. -->
            <form class="busca-campo" id="buscaForm" role="search">
                <label class="sr-only" for="buscaInput">Buscar</label>
                <input type="search" id="buscaInput" autocomplete="off"
                       placeholder="Digite um assunto, título ou pergunta…"
                       autofocus>
                <button class="dash-btn dash-btn--primary" type="submit">Buscar</button>
            </form>

            <ul class="busca-lista" id="buscaLista"></ul>

            <div class="vazio" id="buscaVazio" hidden>
                <span class="vazio__ico" aria-hidden="true">🔍</span>
                <h3 id="buscaVazioTitulo">Nada encontrado</h3>
                <p id="buscaVazioTexto">Tente outra palavra.</p>
            </div>
        </main>

    </div>

    <script src="./js/dashboard.js"></script>
    <script src="./js/pomodoro-aviso.js"></script>
    <script src="./js/busca.js"></script>
    <script src="./js/cursor.js"></script>
    <!-- O céu. Os mesmos dois arquivos da landing page: o WebGL tenta
         primeiro e, se não houver placa ou o shader não compilar, ele
         desiste em silêncio e o cosmos.js (Canvas 2D) assume — por isso
         esta ordem importa. Ver partes/fundo.php. -->
    <script src="../shared/cosmos-gl.js"></script>
    <script src="../shared/cosmos.js"></script>
</body>
</html>
