<?php
// ============================================================
//  KOSMOS — Provas
//  A aba de Foco que responde "o que vem aí, e o que falta
//  estudar para isso".
//
//  Antes a funcionalidade morava num pedaço do Início: dava para
//  cadastrar nome + data e ver a contagem regressiva. Só que uma
//  prova não é um aviso — é um plano: tem assunto que cai, tem
//  material para estudar e tem resultado depois. Nada disso cabia
//  numa lista no fim da página inicial.
//
//  A tela nasce vazia e o js/provas.js a preenche com uma
//  requisição só (Backend/php/provas.php). O servidor manda as
//  próximas, as que já passaram e o material de cada matéria.
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
    <title>Kosmos — Provas</title>
    <link rel="stylesheet" href="./css/dashboard.css">
    <link rel="stylesheet" href="../shared/cosmos.css">
    <link rel="stylesheet" href="./css/pomodoro-aviso.css">
    <link rel="stylesheet" href="./css/provas.css">
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
                    <span class="section-tag">Foco</span>
                    <h1>Suas <span class="h-nome">provas</span></h1>
                    <p>O que vem aí, o que cai e o que você já estudou para cada uma.</p>
                </div>
                <button class="dash-btn dash-btn--primary" type="button" id="btnNovaProva">
                    <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 4v12M4 10h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    Nova prova
                </button>
            </header>

            <!-- ==========================================================
                 A PRÓXIMA

                 Uma prova por vez em destaque, com a contagem grande. É
                 a única informação desta tela que a pessoa procura de
                 relance, então ela não pode estar dentro de uma lista
                 igual às outras.

                 Nasce escondida: quem não tem prova marcada não precisa
                 de um painel anunciando que não tem.
                 ========================================================== -->
            <section class="pv-destaque painel" id="pvDestaque" hidden aria-labelledby="pvDestaqueTitulo">
                <div class="pv-destaque__conta">
                    <strong id="pvDestaqueNum">—</strong>
                    <span id="pvDestaqueUnidade">dias</span>
                </div>
                <div class="pv-destaque__texto">
                    <h2 id="pvDestaqueTitulo">—</h2>
                    <p class="resumo-meta">
                        <span class="materia-tag" id="pvDestaqueMateria" hidden></span>
                        <span id="pvDestaqueData"></span>
                    </p>
                    <div class="pv-destaque__barra" id="pvDestaqueProgresso" hidden>
                        <progress id="pvDestaqueBarra" value="0" max="1"></progress>
                        <span id="pvDestaqueTexto"></span>
                    </div>
                </div>
            </section>

            <!-- ---------------------------------------------------------
                 PRÓXIMAS
                 --------------------------------------------------------- -->
            <section class="ini-secao" id="pvProximas">
                <div class="ini-secao__cabeca">
                    <h2 class="ini-titulo">Próximas</h2>
                    <span class="ini-card__nota" id="pvProximasConta"></span>
                </div>

                <ul class="pv-lista" id="pvLista"></ul>

                <!-- Sem prova cadastrada a seção CONTINUA visível: marcar
                     uma prova é uma ação que a pessoa pode fazer agora, e
                     esconder a seção seria esconder o recurso. -->
                <p class="ini-provas__vazio" id="pvVazio">
                    Nenhuma prova marcada. Cadastre a próxima e o Kosmos conta os dias
                    — e guarda o que cai.
                </p>
            </section>

            <!-- ---------------------------------------------------------
                 HISTÓRICO

                 Só aparece quando existe. Uma prova que passou sem nota
                 registrada vira linha morta; com a nota, vira a única
                 evidência de que o estudo funcionou (ou não).
                 --------------------------------------------------------- -->
            <section class="ini-secao" id="pvPassadas" hidden>
                <div class="ini-secao__cabeca">
                    <h2 class="ini-titulo">Já passaram</h2>
                    <span class="ini-card__nota" id="pvMedia"></span>
                </div>

                <ul class="pv-lista pv-lista--passadas" id="pvListaPassadas"></ul>
            </section>
        </main>
    </div>

    <?php include __DIR__ . '/partes/modal-prova.php'; ?>
    <?php /* O confirmar() do dashboard.js precisa desta parte na página;
             sem ela ele devolve `true` na hora e a prova some sem
             perguntar nada. */ ?>
    <?php include __DIR__ . '/partes/modal-confirma.php'; ?>

    <script src="./js/dashboard.js"></script>
    <script src="./js/pomodoro-aviso.js"></script>
    <script src="./js/combo-materia.js"></script>
    <script src="./js/provas.js"></script>
    <script src="./js/cursor.js"></script>
    <!-- O céu. Os mesmos dois arquivos da landing page: o WebGL tenta
         primeiro e, se não houver placa ou o shader não compilar, ele
         desiste em silêncio e o cosmos.js (Canvas 2D) assume — por isso
         esta ordem importa. Ver partes/fundo.php. -->
    <script src="../shared/cosmos-gl.js"></script>
    <script src="../shared/cosmos.js"></script>
</body>
</html>
