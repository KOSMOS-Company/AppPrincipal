<?php
// Porteiro + dados desta página (sem sessão, redireciona antes de mandar qualquer HTML).
// Deixa $USUARIO, $PREF e $PAGINA prontos.
require_once __DIR__ . '/../../../Backend/php/pagina_dashboard.php';
$primeiroNome = explode(' ', trim($USUARIO['nome'] ?? 'Estudante'))[0];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../shared/favicon.svg">
    <title>Kosmos — Pergunte ao Orion</title>
    <link rel="stylesheet" href="<?= asset('../shared/tokens.css') ?>">
    <link rel="stylesheet" href="<?= asset('./css/dashboard.css') ?>">
    <link rel="stylesheet" href="<?= asset('../shared/cosmos.css') ?>">
    <link rel="stylesheet" href="<?= asset('./css/pomodoro-aviso.css') ?>">
    <link rel="stylesheet" href="<?= asset('../shared/mascote.css') ?>">
    <link rel="stylesheet" href="<?= asset('./css/orion-aba.css') ?>">
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
            <div class="orion-app">

                <!-- Topo leve: quem é o Orion + nova conversa -->
                <header class="orion-topo">
                    <div class="orion-topo__id">
                        <div class="orion-topo__mascote">
                            <div class="mascote" id="orionHeaderMascote" data-mascote="" aria-hidden="true"></div>
                        </div>
                        <div>
                            <h1 class="orion-topo__nome">Orion</h1>
                            <p class="orion-topo__sub">Copiloto de estudos</p>
                        </div>
                    </div>

                    <button type="button" class="orion-topo__nova" id="orionBtnLimpar" title="Apagar esta conversa">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4.5A1.5 1.5 0 0 1 9.5 3h5A1.5 1.5 0 0 1 16 4.5V6"/><path d="m5.5 6 1 13.5A1.5 1.5 0 0 0 8 21h8a1.5 1.5 0 0 0 1.5-1.5L18.5 6"/><path d="M10 11v6M14 11v6"/></svg>
                        Apagar conversa
                    </button>
                </header>

                <!-- Corpo rolável: tela inicial e, depois, a conversa -->
                <div class="orion-corpo" id="orionChatCorpo">

                    <!-- Tela inicial (some quando a conversa começa) -->
                    <section class="orion-hero" id="orionHeroInicial">
                        <div class="orion-hero__astro" aria-hidden="true">
                            <span class="orion-hero__orbita"></span>
                            <div class="mascote" id="orionMascoteCentral" data-mascote="" aria-hidden="true"></div>
                        </div>

                        <h2 class="orion-hero__titulo">
                            Oi, <span class="h-nome"><?= hesc($primeiroNome) ?></span>. Por onde começamos?
                        </h2>
                        <p class="orion-hero__desc">
                            Pergunte sobre qualquer matéria, peça um plano de estudo ou descubra onde fica cada coisa no Kosmos.
                        </p>

                        <!-- Sugestões: o orion-aba.js envia o data-pergunta -->
                        <div class="orion-sugestoes">
                            <button type="button" class="orion-card-sugestao" data-pergunta="O que eu estudo hoje?">
                                <span class="orion-card-sugestao__ico" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.2" fill="currentColor"/></svg>
                                </span>
                                <span class="orion-card-sugestao__txt">
                                    <strong>O que eu estudo hoje?</strong>
                                    <span>Usa seus cartões, provas e meta</span>
                                </span>
                            </button>
                            <button type="button" class="orion-card-sugestao" data-pergunta="Me explica o que é função do 1º grau, com um exemplo simples.">
                                <span class="orion-card-sugestao__ico" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 5.5A1.5 1.5 0 0 1 3.5 4H9a3 3 0 0 1 3 3v13a2.5 2.5 0 0 0-2.5-2.5H2Z"/><path d="M22 5.5A1.5 1.5 0 0 0 20.5 4H15a3 3 0 0 0-3 3v13a2.5 2.5 0 0 1 2.5-2.5H22Z"/></svg>
                                </span>
                                <span class="orion-card-sugestao__txt">
                                    <strong>Me explica um conteúdo</strong>
                                    <span>Do jeito simples, com exemplo</span>
                                </span>
                            </button>
                            <button type="button" class="orion-card-sugestao" data-pergunta="Monta um plano de estudo até a minha próxima prova.">
                                <span class="orion-card-sugestao__ico" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4.5" width="18" height="16" rx="2.5"/><path d="M16 2.5v4M8 2.5v4M3 10h18"/><path d="m9 15 2 2 4-4"/></svg>
                                </span>
                                <span class="orion-card-sugestao__txt">
                                    <strong>Plano até a prova</strong>
                                    <span>Divide os assuntos pelos dias</span>
                                </span>
                            </button>
                            <button type="button" class="orion-card-sugestao" data-pergunta="Onde eu revejo os flashcards que estão vencidos?">
                                <span class="orion-card-sugestao__ico" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="13" height="13" rx="2"/><path d="M8 4h11a2 2 0 0 1 2 2v11"/></svg>
                                </span>
                                <span class="orion-card-sugestao__txt">
                                    <strong>Revisar flashcards</strong>
                                    <span>Onde e como revisar hoje</span>
                                </span>
                            </button>
                            <button type="button" class="orion-card-sugestao" data-pergunta="Me dá um tour rápido pelas abas do Kosmos: o que cada uma faz?">
                                <span class="orion-card-sugestao__ico" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5Z"/></svg>
                                </span>
                                <span class="orion-card-sugestao__txt">
                                    <strong>Tour pelo Kosmos</strong>
                                    <span>O que cada aba faz</span>
                                </span>
                            </button>
                            <button type="button" class="orion-card-sugestao" data-pergunta="Tô sem foco e procrastinando. Me ajuda a começar agora?">
                                <span class="orion-card-sugestao__ico" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 4 14h7l-1 8 9-12h-7Z"/></svg>
                                </span>
                                <span class="orion-card-sugestao__txt">
                                    <strong>Sem foco hoje</strong>
                                    <span>Um empurrão para começar</span>
                                </span>
                            </button>
                        </div>
                    </section>

                    <!-- A conversa (o orion-aba.js monta as mensagens aqui) -->
                    <div class="orion-lista-mensagens" id="orionListaMensagens" aria-live="polite"></div>

                    <!-- "Pensando..." -->
                    <div class="orion-digitando-balao" id="orionDigitandoBalao" aria-label="O Orion está pensando">
                        <span></span><span></span><span></span>
                    </div>
                </div>

                <!-- Caixa de mensagem: cresce com o texto; Enter envia, Shift+Enter quebra a linha -->
                <footer class="orion-rodape">
                    <form class="orion-chat-form" id="orionChatForm">
                        <label class="sr-only" for="orionChatInput">Mensagem para o Orion</label>
                        <textarea class="orion-chat-input" id="orionChatInput" rows="1" maxlength="1000"
                                  placeholder="Pergunte qualquer coisa ao Orion…" autocomplete="off"></textarea>
                        <button type="submit" class="orion-chat-btn-enviar btn-ia" id="orionChatEnviar" aria-label="Enviar mensagem" title="Enviar (Enter)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                        </button>
                    </form>
                    <p class="orion-rodape__info">
                        <kbd>Enter</kbd> envia · <kbd>Shift</kbd>+<kbd>Enter</kbd> quebra a linha · O Orion pode errar: confira o que for importante.
                    </p>
                </footer>

            </div>
        </main>

    </div>

    <script src="<?= asset('./js/util.js') ?>"></script>
    <script src="<?= asset('./js/dashboard.js') ?>"></script>
    <script src="<?= asset('../shared/mascote.js') ?>"></script>
    <script src="<?= asset('./js/orion-ia.js') ?>"></script>
    <script src="<?= asset('./js/orion-aba.js') ?>"></script>
    <!-- O fundo tenta WebGL (cosmos-gl.js); se a máquina não tem ou o shader
         não compila, ele desiste em silêncio e o cosmos.js (Canvas 2D) assume.
         Os dois com defer aqui: scripts defer rodam na ordem do HTML, então o
         cosmos.js continua rodando DEPOIS do cosmos-gl.js, como nas outras
         páginas — sem defer ele rodaria antes e nunca veria o WebGL ligado. -->
    <script src="<?= asset('../shared/cosmos-gl.js') ?>" defer></script>
    <script src="<?= asset('../shared/cosmos.js') ?>" defer></script>
    <script src="<?= asset('../shared/cursor.js') ?>" defer></script>
    <script src="<?= asset('./js/pomodoro-aviso.js') ?>"></script>
</body>
</html>
