/* ============================================================
   KOSMOS — O que precisa rodar antes da barra lateral aparecer
   Arquivo: Frontend/pages/dashboard/js/conta-inicial.js

   Carregado pelo partes/sidebar.php, SEM defer/async, logo antes do
   <aside> (ver o comentário lá). Os dados que vêm do PHP (id da
   conta, página, tutorial) chegam pelo bloco JSON #dadosConta, que
   não é executado. Antes tudo isto era um <script> inline com PHP
   no meio; virou arquivo para a Content-Security-Policy poder
   barrar todo script inline (script-src 'self').
   ============================================================ */
(function () {
    var dados = {};
    try { dados = JSON.parse(document.getElementById('dadosConta').textContent); } catch (e) { /* sem dados */ }

    try {
        if (localStorage.getItem('kosmos_lateral') === 'recolhida') {
            document.documentElement.classList.add('lateral-recolhida');
        }
    } catch (e) { /* navegação privada: segue aberta, sem drama */ }

    /* Tudo que é DA CONTA e fica guardado no navegador (o Pomodoro
       em andamento, a conversa com o Orion, a ordem das listas) usa
       uma chave com o id da conta. Sem isso, duas contas no mesmo
       navegador dividiam os dados — e um ciclo de Pomodoro iniciado
       numa conta era registrado na outra que entrasse depois.
       (Preferências do APARELHO — barra recolhida, modo leve,
       posição do contador — continuam valendo para todos.) */
    window.KOSMOS_CONTA = dados.conta | 0;
    window.kosmosChaveConta = function (base) { return base + ':conta' + window.KOSMOS_CONTA; };

    /* Identificador DESTE aparelho (32 letras hexadecimais, sorteado
       uma vez). O Pomodoro manda junto do Iniciar e do fim do ciclo:
       o servidor guarda um início por aparelho, então um ciclo no PC
       e outro no celular ao mesmo tempo não se atropelam. Sem
       localStorage (aba privada), vale até fechar a aba. */
    var aparelho = '';
    window.kosmosAparelho = function () {
        if (aparelho) return aparelho;
        try { aparelho = localStorage.getItem('kosmos_aparelho') || ''; } catch (e) { /* privado */ }
        if (!/^[0-9a-f]{32}$/.test(aparelho)) {
            var bytes = new Uint8Array(16);
            crypto.getRandomValues(bytes);
            aparelho = Array.prototype.map.call(bytes, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
            try { localStorage.setItem('kosmos_aparelho', aparelho); } catch (e) { /* privado */ }
        }
        return aparelho;
    };

    /* ---- Orion: o tour e as dicas da primeira vez (js/tutorial.js) ----
       O tutorial.js + tutorial.css (~50 KB) só descem quando há algo
       para mostrar NESTA aba: o tour em andamento, o tour para
       começar (conta nova, no Início) ou uma dica ainda não vista.
       Quem já viu tudo não baixa nem roda nada além destas linhas.
       DICAS_POR_ABA espelha os `paginas` das DICAS do tutorial.js. */
    window.KOSMOS_PAGINA = dados.pagina || '';
    window.KOSMOS_TUTORIAL = !!dados.tutorial;
    window.KOSMOS_TUTORIAL_PREMIADO = !!dados.tutorialPremiado;

    var CHAVE_TOUR = window.kosmosChaveConta('kosmos_tutorial');
    var DICAS_POR_ABA = {
        'resumos.php': ['editor'], 'caderno.php': ['editor'], 'resumo.php': ['resumo'],
        'flashcards.php': ['estudo'], 'revisar.php': ['estudo'], 'exercicio_materia.php': ['praticar'],
        'provas.php': ['provas', 'prova-form']
    };
    var emTour = false, precisa = window.KOSMOS_TUTORIAL;
    try { emTour = !!sessionStorage.getItem(CHAVE_TOUR); } catch (e) { /* privado */ }
    if (emTour) {
        /* Tour no meio do caminho (veio de outra aba): a página já
           nasce escura, no mesmo véu em que a anterior terminou —
           senão piscaria clara até o tutorial.js abrir a luz no
           alvo novo. O @view-transition mistura as duas (Chrome/Edge). */
        document.documentElement.classList.add('tut-chegando');
        var veu = document.createElement('style');
        veu.id = 'tutVT';
        veu.textContent = '@view-transition{navigation:auto}'
            + 'html.tut-chegando body::after{content:"";position:fixed;inset:0;z-index:8999;background:rgba(6,0,14,.78)}';
        document.head.appendChild(veu);
        precisa = true;
    }
    /* Dicas já vistas: as da CONTA (servidor, valem em qualquer aparelho)
       mais as deste navegador. As que só este aparelho conhecia (de
       antes de a lista ir para o servidor) sobem uma vez. */
    var dicasServidor = Array.isArray(dados.dicasVistas) ? dados.dicasVistas : [];
    var dicasLocais = [];
    try { dicasLocais = JSON.parse(localStorage.getItem(window.kosmosChaveConta('kosmos_dicas')) || '[]'); } catch (e) { /* nada */ }
    if (!Array.isArray(dicasLocais)) dicasLocais = [];
    var soLocais = dicasLocais.filter(function (d) { return dicasServidor.indexOf(d) < 0; });
    window.KOSMOS_DICAS_VISTAS = dicasServidor.concat(soLocais);
    if (soLocais.length && window.KOSMOS_CONTA) {
        var corpo = new FormData();
        corpo.append('dicas', soLocais.join(','));
        fetch('../../../Backend/php/tutorial_dica.php', { method: 'POST', body: corpo, credentials: 'same-origin' })
            .catch(function () { /* tenta de novo no próximo carregamento */ });
    }

    if (!precisa && DICAS_POR_ABA[window.KOSMOS_PAGINA]) {
        precisa = DICAS_POR_ABA[window.KOSMOS_PAGINA].some(function (d) { return window.KOSMOS_DICAS_VISTAS.indexOf(d) < 0; });
    }
    if (precisa) {
        var css = document.createElement('link');
        css.rel = 'stylesheet';
        css.id = 'tutCss';
        css.href = dados.tutorialCss;
        document.head.appendChild(css);
        var js = document.createElement('script');
        js.src = dados.tutorialJs;
        document.head.appendChild(js);
    }

    // "Rever o tour" (Conta → Sobre): não precisa do tutorial.js
    // aqui — marca o passo 0 e vai ao Início, que carrega e começa
    document.addEventListener('click', function (e) {
        var b = e.target.closest && e.target.closest('[data-rever-tutorial]');
        if (!b) return;
        e.preventDefault();
        try { sessionStorage.setItem(CHAVE_TOUR, JSON.stringify({ passo: 0, dados: {} })); } catch (err) { /* privado */ }
        window.location.href = 'index.php';
    });

    // As chaves antigas eram compartilhadas: não dá para saber de qual
    // conta é o ciclo guardado nelas, então saem (uma vez só).
    try {
        localStorage.removeItem('kosmos:pomodoro');
        localStorage.removeItem('kosmos:pomodoro:avisado');
        localStorage.removeItem('kosmos_ordem_flashcards');
        localStorage.removeItem('kosmos_ordem_exercicios');
        localStorage.removeItem('kosmos_ordem_biblioteca');
        sessionStorage.removeItem('kosmos_orion_chat_historico');
        sessionStorage.removeItem('kosmos_orion_chat_session');
    } catch (e) { /* navegação privada */ }
})();
