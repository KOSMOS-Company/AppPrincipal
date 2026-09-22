<?php
// ============================================================
//  KOSMOS — Mini Chat do Orion ("Pergunte ao Orion!")
//  Arquivo: Frontend/pages/dashboard/partes/orion-chat.php
//  Widget flutuante de assistência aos estudantes.
// ============================================================
?>
<!-- Botão Flutuante (Launcher) -->
<div class="orion-launcher" id="orionChatLauncher" role="button" aria-label="Abrir chat com Orion" aria-expanded="false" title="Pergunte ao Orion!">
    <div class="orion-launcher__avatar">
        <div class="mascote" id="orionLauncherMascote" data-mascote aria-hidden="true"></div>
        <span class="orion-launcher__status" aria-hidden="true"></span>
    </div>
    <span class="orion-launcher__rotulo">Pergunte ao Orion!</span>
</div>

<!-- Janela do Chat (Flyout) -->
<div class="orion-chat" id="orionChatBox" aria-hidden="true" role="dialog" aria-labelledby="orionChatTitulo">
    <!-- Topo / Header do Chat -->
    <header class="orion-chat__topo">
        <div class="orion-chat__perfil">
            <div class="orion-chat__avatar-wrap">
                <div class="mascote" id="orionChatMascote" data-mascote aria-hidden="true"></div>
                <span class="orion-chat__status-dot" title="Online"></span>
            </div>
            <div class="orion-chat__info">
                <h3 class="orion-chat__titulo" id="orionChatTitulo">Pergunte ao Orion!</h3>
                <span class="orion-chat__status-texto">Assistente de Estudos • Kosmos</span>
            </div>
        </div>

        <div class="orion-chat__acoes">
            <button type="button" class="orion-chat__btn-acao" id="orionChatLimpar" title="Limpar conversa" aria-label="Limpar conversa">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <button type="button" class="orion-chat__btn-acao" id="orionChatFechar" title="Fechar chat" aria-label="Fechar chat">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
        </div>
    </header>

    <!-- Corpo das Mensagens -->
    <div class="orion-chat__mensagens" id="orionChatMsgs">
        <!-- Mensagens dinâmicas inseridas pelo JS -->
    </div>

    <!-- Indicador de digitação -->
    <div class="orion-chat__digitando" id="orionTyping" style="display: none;">
        <span></span><span></span><span></span>
    </div>

    <!-- Sugestões Rápidas (Quick Prompts) -->
    <div class="orion-chat__sugestoes" id="orionSugestoes">
        <button type="button" class="orion-chip" data-prompt="Como funciona o Pomodoro?">⏱️ Como funciona o Pomodoro?</button>
        <button type="button" class="orion-chip" data-prompt="Dicas para usar Flashcards">🎴 Dicas de Flashcards</button>
        <button type="button" class="orion-chip" data-prompt="Como criar um bom resumo?">📝 Como criar um bom resumo?</button>
        <button type="button" class="orion-chip" data-prompt="Estou sem foco, o que fazer?">⚡ Tô sem foco, me ajuda!</button>
        <button type="button" class="orion-chip" data-prompt="Como organizar estudos pro ENEM?">🎯 Dicas pro ENEM / Vestibulares</button>
    </div>

    <!-- Rodapé com Input -->
    <form class="orion-chat__rodape" id="orionChatForm" onsubmit="return false;">
        <input type="text" class="orion-chat__input" id="orionChatInput"
               placeholder="Pergunte ao Orion..." autocomplete="off" maxlength="300">
        <button type="submit" class="orion-chat__btn-enviar" id="orionChatEnviar" aria-label="Enviar mensagem">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
    </form>
</div>
