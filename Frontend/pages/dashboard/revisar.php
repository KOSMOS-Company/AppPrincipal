<?php
// ============================================================
//  KOSMOS — Revisar hoje
//  A fila de revisão atravessando TODOS os baralhos.
//
//  Reaproveita inteira a interação de cartão da aba Flashcards
//  (.flashcard, .estudo-area, .estudo-avaliacao, .estudo-nav,
//  .estudo-fim) e o css/flashcards.css — é o mesmo gesto, virar
//  uma carta e dizer se lembrava. Copiar o desenho aqui só criaria
//  dois lugares para ajustar quando ele mudar.
//
//  A diferença está nos DADOS: lá a pessoa escolhe um baralho,
//  aqui quem escolhe é o agendamento (Backend/php/revisar_fila.php).
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
    <title>Kosmos — Revisar hoje</title>
    <link rel="stylesheet" href="./css/dashboard.css">
    <link rel="stylesheet" href="../shared/cosmos.css">
    <link rel="stylesheet" href="./css/pomodoro-aviso.css">
    <link rel="stylesheet" href="./css/flashcards.css">
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
                    <span class="section-tag">Revisão</span>
                    <h1>Revisar <span class="h-nome">hoje</span></h1>
                    <p id="revProgresso">Montando sua fila…</p>
                </div>
                <div class="fc-acoes">
                    <a class="dash-btn dash-btn--outline" href="index.php">← Voltar ao Início</a>
                </div>
            </header>

            <!-- Carregando: um texto só. A fila costuma vir em
                 milissegundos; esqueleto animado aqui piscaria mais do
                 que informaria. -->
            <p class="rev-carregando" id="revCarregando">Carregando os cartões…</p>

            <!-- Nada vencido hoje. NÃO é um erro nem uma tela vazia por
                 falta de conteúdo: é a resposta certa, e a pessoa
                 precisa saber que terminou, não achar que quebrou. -->
            <div class="vazio" id="revVazio" hidden>
                <span class="vazio__ico" aria-hidden="true"><svg class="ico" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8.8"/><path d="M8 12.4 10.8 15.2 16 9.6"/></svg></span>
                <h3>Nada para revisar agora</h3>
                <p id="revVazioTexto">
                    Você está em dia. Os cartões voltam sozinhos quando chegar
                    a hora de cada um.
                </p>
                <a class="dash-btn dash-btn--primary" href="flashcards.php">Ir para os Flashcards</a>
            </div>

            <div class="estudo-area" id="revArea" hidden>
                <span class="rev-origem" id="revOrigem"></span>

                <div class="flashcard" id="flashcard" role="button" tabindex="0"
                     aria-label="Virar o cartão">
                    <div class="flashcard__inner">
                        <div class="flashcard__face flashcard__face--frente">
                            <span class="flashcard__hint">Pergunta</span>
                            <p id="cardFrente">—</p>
                            <span class="flashcard__virar">Clique para ver a resposta</span>
                        </div>
                        <div class="flashcard__face flashcard__face--verso">
                            <span class="flashcard__hint">Resposta</span>
                            <p id="cardVerso">—</p>
                            <span class="flashcard__virar">Clique para voltar</span>
                        </div>
                    </div>
                </div>

                <!-- Só aparece depois de virar: responder "já sei" sem ter
                     visto a resposta não é autoavaliação, é adivinhação. -->
                <div class="estudo-avaliacao" id="estudoAvaliacao" hidden>
                    <span class="estudo-avaliacao__pergunta">Você lembrava dessa?</span>
                    <div class="estudo-avaliacao__botoes">
                        <button class="dash-btn fc-btn--errei" data-resposta="0">Ainda não</button>
                        <button class="dash-btn fc-btn--acertei" data-resposta="1">Já sei</button>
                    </div>
                </div>

                <div class="estudo-nav">
                    <div class="estudo-progresso" role="progressbar"
                         aria-label="Progresso da revisão" aria-valuemin="0" aria-valuemax="100">
                        <div class="estudo-progresso__bar" id="barEstudo"></div>
                    </div>
                </div>
            </div>

            <div class="estudo-fim painel" id="revFim" hidden>
                <h2>Revisão concluída!</h2>
                <p id="revFimResumo">—</p>
                <div class="estudo-fim__stats">
                    <div class="ini-stat">
                        <strong id="fimAcertos">0</strong>
                        <span>Já sabia</span>
                    </div>
                    <span class="ini-stat__div" aria-hidden="true"></span>
                    <div class="ini-stat">
                        <strong id="fimErros">0</strong>
                        <span>Ainda não</span>
                    </div>
                </div>
                <div class="fc-acoes">
                    <a class="dash-btn dash-btn--primary" href="index.php">Voltar ao Início</a>
                </div>
            </div>
        </main>

    </div>

    <script src="./js/dashboard.js"></script>
    <script src="./js/pomodoro-aviso.js"></script>
    <script src="./js/revisar.js"></script>
    <script src="./js/cursor.js"></script>
    <!-- O céu. Os mesmos dois arquivos da landing page: o WebGL tenta
         primeiro e, se não houver placa ou o shader não compilar, ele
         desiste em silêncio e o cosmos.js (Canvas 2D) assume — por isso
         esta ordem importa. Ver partes/fundo.php. -->
    <script src="../shared/cosmos-gl.js"></script>
    <script src="../shared/cosmos.js"></script>
</body>
</html>
