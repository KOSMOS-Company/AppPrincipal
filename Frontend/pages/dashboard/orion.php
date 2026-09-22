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
    <link rel="stylesheet" href="./css/dashboard.css">
    <link rel="stylesheet" href="../shared/cosmos.css">
    <link rel="stylesheet" href="./css/pomodoro-aviso.css">
    <link rel="stylesheet" href="../shared/mascote.css">
    <link rel="stylesheet" href="./css/orion-aba.css">
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
            <div class="orion-chat-app" id="orionChatApp">

                <!-- 1. HEADER FIXO DO CHATBOT -->
                <header class="orion-chat-header">
                    <div class="orion-chat-header__info">
                        <div class="orion-chat-header__avatar">
                            <div class="mascote" id="orionHeaderMascote" data-mascote="" aria-hidden="true"></div>
                        </div>
                        <div class="orion-chat-header__textos">
                            <h2 class="orion-chat-header__titulo">
                                Orion <span class="orion-chat-header__tag">Copiloto de Estudos</span>
                            </h2>
                            <span class="orion-chat-header__status">Online</span>
                        </div>
                    </div>

                    <button type="button" class="orion-chat-header__btn-limpar" id="orionBtnLimpar" title="Limpar conversa">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Limpar conversa
                    </button>
                </header>

                <!-- 2. CORPO ROLÁVEL (MENSAGENS & ESTADO INICIAL) -->
                <div class="orion-chat-corpo" id="orionChatCorpo">

                    <!-- Palco Inicial no Centro (some quando o chat inicia) -->
                    <div class="orion-hero-inicial" id="orionHeroInicial">
                        <div class="orion-hero-mascote-box">
                            <div class="mascote" id="orionMascoteCentral" data-mascote="" aria-hidden="true"></div>
                        </div>

                        <!-- Tag escrita de forma limpa (sem pílula/badge envolvente) -->
                        <span class="section-tag">Copiloto Inteligente</span>

                        <h1 class="orion-hero-titulo">
                            Como posso te ajudar hoje, <span class="h-nome"><?= hesc($primeiroNome) ?></span>?
                        </h1>
                        <p class="orion-hero-desc">
                            Tire dúvidas conceituais, receba estratégias de memorização, formule resumos ou turbine sua rotina de estudos no Kosmos.
                        </p>

                        <!-- Sugestões de perguntas rápidas -->
                        <div class="orion-sugestoes-grid">
                            <button type="button" class="orion-card-sugestao" data-pergunta="Como funciona a técnica Pomodoro no Kosmos?">
                                <span class="orion-card-sugestao__ico">⏱️</span>
                                <div>
                                    <strong>Método Pomodoro</strong>
                                    <span>Ciclos de foco e pausas</span>
                                </div>
                            </button>
                            <button type="button" class="orion-card-sugestao" data-pergunta="Como criar flashcards eficientes para não esquecer a matéria?">
                                <span class="orion-card-sugestao__ico">🎴</span>
                                <div>
                                    <strong>Flashcards e Revisão</strong>
                                    <span>Repetição espaçada ativa</span>
                                </div>
                            </button>
                            <button type="button" class="orion-card-sugestao" data-pergunta="Qual a melhor técnica para fazer resumos de matérias difíceis?">
                                <span class="orion-card-sugestao__ico">📝</span>
                                <div>
                                    <strong>Resumos e Feynman</strong>
                                    <span>Traduzir assuntos complexos</span>
                                </div>
                            </button>
                            <button type="button" class="orion-card-sugestao" data-pergunta="Estou com dificuldade para focar e começando a procrastinar, o que fazer?">
                                <span class="orion-card-sugestao__ico">⚡</span>
                                <div>
                                    <strong>Foco Imediato</strong>
                                    <span>Regra dos 2 minutos e inércia</span>
                                </div>
                            </button>
                            <button type="button" class="orion-card-sugestao" data-pergunta="Quais as melhores estratégias e dicas para as provas do ENEM?">
                                <span class="orion-card-sugestao__ico">🎯</span>
                                <div>
                                    <strong>Dicas pro ENEM</strong>
                                    <span>Priorização e simulados</span>
                                </div>
                            </button>
                            <button type="button" class="orion-card-sugestao" data-pergunta="Olá Orion, quem é você e como pode me ajudar?">
                                <span class="orion-card-sugestao__ico">🪐</span>
                                <div>
                                    <strong>Conhecer o Orion</strong>
                                    <span>Recursos e auxílio no app</span>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Lista de Mensagens do Chat -->
                    <div class="orion-lista-mensagens" id="orionListaMensagens"></div>

                    <!-- Indicador de Digitação -->
                    <div class="orion-digitando-balao" id="orionDigitandoBalao">
                        <span></span><span></span><span></span>
                    </div>

                </div>

                <!-- 3. BARRA DE DIGITAÇÃO FIXA NO RODAPÉ -->
                <footer class="orion-chat-rodape">
                    <form class="orion-chat-form" id="orionChatForm" onsubmit="return false;">
                        <input type="text" class="orion-chat-input" id="orionChatInput" placeholder="Pergunte ao Orion... (ex: me explique o método Feynman)" autocomplete="off" maxlength="400">
                        <button type="submit" class="orion-chat-btn-enviar" id="orionChatEnviar" aria-label="Enviar pergunta" title="Enviar pergunta">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                        </button>
                    </form>
                    <div class="orion-chat-rodape__info">
                        Pressione <strong>Enter ↵</strong> para enviar • O Orion tira dúvidas e organiza sua rotina de estudos
                    </div>
                </footer>

            </div>
        </main>

    </div>

    <script src="./js/dashboard.js"></script>
    <script src="../shared/mascote.js"></script>
    <script src="./js/orion-aba.js"></script>
    <script src="../shared/cosmos-gl.js" defer></script>
    <script src="./js/cursor.js" defer></script>
    <script src="./js/pomodoro-aviso.js"></script>
</body>
</html>
