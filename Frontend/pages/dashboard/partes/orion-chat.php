<?php
// ============================================================
//  KOSMOS — Chat flutuante do Orion
//  Arquivo: Frontend/pages/dashboard/partes/orion-chat.php
//  Em todas as abas menos a do Orion (ver sidebar.php). A conversa é
//  a mesma da aba (js/orion-ia.js); o botão de tela cheia leva para lá.
//  Quem dá vida: js/orion-chat.js. Visual: css/orion-chat.css.
// ============================================================
?>
<!-- Botão flutuante: só o Orion no canto -->
<div class="orion-launcher" id="orionChatLauncher" role="button" tabindex="0"
     aria-label="Abrir conversa com o Orion" aria-expanded="false" title="Pergunte ao Orion">
    <div class="orion-launcher__avatar">
        <div class="mascote" id="orionLauncherMascote" data-mascote aria-hidden="true"></div>
    </div>
</div>

<!-- Janela do chat -->
<div class="orion-chat" id="orionChatBox" aria-hidden="true" role="dialog" aria-labelledby="orionChatTitulo">

    <header class="orion-chat__topo">
        <div class="orion-chat__perfil">
            <div class="orion-chat__avatar">
                <div class="mascote" id="orionChatMascote" data-mascote aria-hidden="true"></div>
            </div>
            <div>
                <h3 class="orion-chat__titulo" id="orionChatTitulo">Orion</h3>
                <p class="orion-chat__sub">Copiloto de estudos</p>
            </div>
        </div>

        <div class="orion-chat__acoes">
            <button type="button" class="orion-chat__btn-acao" id="orionChatLimpar" title="Apagar conversa" aria-label="Apagar a conversa">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4.5A1.5 1.5 0 0 1 9.5 3h5A1.5 1.5 0 0 1 16 4.5V6"/><path d="m5.5 6 1 13.5A1.5 1.5 0 0 0 8 21h8a1.5 1.5 0 0 0 1.5-1.5L18.5 6"/><path d="M10 11v6M14 11v6"/></svg>
            </button>
            <a class="orion-chat__btn-acao" href="orion.php" title="Abrir em tela cheia" aria-label="Abrir a conversa em tela cheia">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
            </a>
            <button type="button" class="orion-chat__btn-acao" id="orionChatFechar" title="Fechar" aria-label="Fechar o chat">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
    </header>

    <!-- Mensagens (o orion-chat.js monta) -->
    <div class="orion-chat__mensagens" id="orionChatMsgs" aria-live="polite"></div>

    <!-- "Pensando..." -->
    <div class="orion-chat__digitando" id="orionTyping" style="display: none;" aria-label="O Orion está pensando">
        <span></span><span></span><span></span>
    </div>

    <!-- Sugestões: só com a conversa vazia (o orion-chat.js esconde depois) -->
    <div class="orion-chat__sugestoes" id="orionSugestoes">
        <button type="button" class="orion-chip" data-prompt="O que eu estudo hoje?">O que eu estudo hoje?</button>
        <button type="button" class="orion-chip" data-prompt="Me explica um conteúdo de um jeito simples, com exemplo.">Me explica um conteúdo</button>
        <button type="button" class="orion-chip" data-prompt="Me dá um tour rápido pelas abas do Kosmos.">Tour pelo Kosmos</button>
        <button type="button" class="orion-chip" data-prompt="Tô sem foco. Me ajuda a começar agora?">Tô sem foco</button>
    </div>

    <!-- Caixa de mensagem: cresce com o texto; Enter envia -->
    <form class="orion-chat__rodape" id="orionChatForm" onsubmit="return false;">
        <label class="sr-only" for="orionChatInput">Mensagem para o Orion</label>
        <textarea class="orion-chat__input" id="orionChatInput" rows="1" maxlength="1000"
                  placeholder="Pergunte ao Orion…" autocomplete="off"></textarea>
        <button type="submit" class="orion-chat__btn-enviar btn-ia" id="orionChatEnviar" aria-label="Enviar mensagem" title="Enviar (Enter)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
        </button>
    </form>
</div>
