/* ============================================================
   KOSMOS — tutorial.js (o tour do Orion pelas abas)

   Conta nova: depois da intro e da pesquisa de perfil, o Orion
   aparece GRANDE no canto da tela, como personagem de jogo de
   celular, e fala por um balão. Ele visita cada aba de verdade e,
   nas principais, faz a pessoa criar a primeira coisa (caderno,
   baralho, cartão, matéria) e entrar nela.

   Dois tipos de passo:
     · fala — ele explica; a página fica só de vitrine, "Próximo" avança;
     · acao — o ponto aceso fica clicável e o tour espera a pessoa
       fazer (clicar, criar). Se ela desistir no meio (fechou o
       modal), ele volta para o passo que pede de novo.

   Entre páginas: o passo e o que já foi criado (ids) moram no
   sessionStorage, com a chave da conta. A página que sai fecha a
   luz; a próxima já nasce escura (sidebar.php pinta o véu antes da
   primeira pintura) e abre a luz no alvo novo — a troca de aba vira
   uma transição só. Se a pessoa sair do roteiro sozinha, o tour
   termina quieto.

   Começa: só no Início, para conta que ainda não viu
   (window.KOSMOS_TUTORIAL), depois da intro e da pesquisa de perfil.
   Marca como visto no servidor assim que COMEÇA.
   Rever: window.KosmosTutorial.comecar() ou [data-rever-tutorial].
   ============================================================ */

(function () {
    'use strict';

    var CHAVE  = window.kosmosChaveConta ? window.kosmosChaveConta('kosmos_tutorial') : 'kosmos_tutorial';
    var PAGINA = window.KOSMOS_PAGINA || '';
    var semMovimento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var visivelSel = function (sel) {
        var el = document.querySelector(sel);
        return !!el && visivel(el);
    };
    var modalAberto = function (id) {
        var m = document.getElementById(id);
        return !!m && m.classList.contains('open');
    };

    /* ============================================================
       O ROTEIRO — 15 etapas em 6 capítulos
       alvo:   seletor, lista de seletores (vale o 1º visível) ou
               função (dados) → seletor. Sem alvo = tela toda escura.
       cap:    o capítulo (CAPITULOS), que é o que o balão mostra
               ("Biblioteca · 2 de 6") — progresso por aba, não por fala.
       humor:  rosto do mascote (shared/mascote.js).
       anim:   gesto do corpo (acena, pula, aponta, gira, treme, pop).
       requer: só aparece se a pessoa criou aquilo (dados[requer]).
       Textos: curtos, dizendo O QUE é e COMO se usa, com o nome
       exato do botão da tela entre aspas. O detalhe fino (editor,
       primeira revisão, primeiro exercício) fica para as DICAS.
       ============================================================ */
    var CAPITULOS = ['Início', 'Biblioteca', 'Flashcards', 'Exercícios', 'Pomodoro', 'Menu e Conta'];

    var PASSOS = [
        // ---------------- INÍCIO ----------------
        { id: 'oi', cap: 0, pagina: 'index.php', humor: 'feliz', anim: 'acena',
          titulo: 'Oi! Eu sou o Orion ✦',
          texto: 'Vou te mostrar o Kosmos aba por aba — e, no caminho, você já cria seu primeiro caderno, baralho e matéria.' },
        { id: 'painel', cap: 0, pagina: 'index.php', alvo: '.ini-painel', humor: 'curioso', anim: 'aponta',
          titulo: 'Seu painel da semana',
          texto: 'Cada barra é um dia de estudo no Pomodoro. Embaixo ficam seus resumos, flashcards e exercícios, e os avisos do que revisar.' },

        // ---------------- BIBLIOTECA ----------------
        { id: 'bib-criar', cap: 1, pagina: 'resumos.php', alvo: '#btnNovoCaderno', tipo: 'acao', ate: 'clique',
          saltar: 'fc-criar', humor: 'determinado', anim: 'pula',
          titulo: 'Biblioteca',
          texto: 'Seus resumos ficam em cadernos, um por matéria. Clique em “Novo caderno” para criar o primeiro.' },
        { id: 'bib-form', cap: 1, pagina: 'resumos.php', alvo: '#modalCaderno .modal__box', tipo: 'acao',
          evento: 'caderno:salvo',
          guardar: function (e, d) { d.caderno = e.detail && e.detail.caderno && e.detail.caderno.id; },
          volta: function () { return !modalAberto('modalCaderno'); }, voltaPara: 'bib-criar',
          saltar: 'fc-criar', humor: 'foco', anim: 'pop',
          titulo: 'Preencha o caderno',
          texto: 'Dê um nome (ex.: Biologia), escolha a matéria e a cor. Depois clique em “Criar caderno”.' },
        { id: 'cad', cap: 1, pagina: 'caderno.php', requer: 'caderno',
          url: function (d) { return 'caderno.php?id=' + encodeURIComponent(d.caderno); },
          alvo: '.contCabeca', humor: 'comemorando', anim: 'treme',
          titulo: 'Caderno criado! 🎉',
          texto: 'Você está dentro dele. Em “Novo resumo” você escreve; em “Personalizar”, troca o nome, a cor e a capa.' },

        // ---------------- FLASHCARDS ----------------
        { id: 'fc-criar', cap: 2, pagina: 'flashcards.php', alvo: '#btnNovoDeck', tipo: 'acao', ate: 'clique',
          saltar: 'ex-criar', humor: 'determinado', anim: 'pula',
          titulo: 'Flashcards',
          texto: 'Cartões de pergunta e resposta, em baralhos, com lembrete do dia certo de revisar. Clique em “Novo baralho”.' },
        { id: 'fc-form', cap: 2, pagina: 'flashcards.php', alvo: '#modalDeck .modal__box', tipo: 'acao',
          quando: function () { return visivelSel('#viewCartoes'); },
          guardar: function (e, d) { d.deck = true; },
          volta: function () { return !modalAberto('modalDeck') && !visivelSel('#viewCartoes'); }, voltaPara: 'fc-criar',
          saltar: 'ex-criar', humor: 'foco', anim: 'pop',
          titulo: 'Preencha o baralho',
          texto: 'Dê um nome, escolha a matéria e a cor. Depois clique em “Criar baralho”.' },
        { id: 'fc-dentro', cap: 2, pagina: 'flashcards.php', requer: 'deck', alvo: '#viewCartoes .contCabeca',
          humor: 'comemorando', anim: 'pula',
          titulo: 'Você entrou no baralho!',
          texto: 'Em “Novo cartão” você escreve a pergunta e a resposta. Em “Estudar”, revisa — e o Kosmos marca sozinho a próxima vez.' },

        // ---------------- EXERCÍCIOS ----------------
        { id: 'ex-criar', cap: 3, pagina: 'exercicios.php', alvo: '#btnNovaMateria', tipo: 'acao', ate: 'clique',
          saltar: 'pomo', humor: 'feliz', anim: 'pula',
          titulo: 'Exercícios',
          texto: 'Aqui você treina com questões, separadas por matéria. Clique em “Nova matéria”.' },
        { id: 'ex-form', cap: 3, pagina: 'exercicios.php', alvo: '#modalExercicioMateria .modal__box', tipo: 'acao',
          evento: 'exercicioMateria:salvo',
          guardar: function (e, d) { d.materia = e.detail && e.detail.materia && e.detail.materia.id; },
          volta: function () { return !modalAberto('modalExercicioMateria'); }, voltaPara: 'ex-criar',
          saltar: 'pomo', humor: 'foco', anim: 'pop',
          titulo: 'Preencha a matéria',
          texto: 'Escolha a matéria, a cor e o ícone. Depois clique em “Criar matéria”.' },
        { id: 'mat', cap: 3, pagina: 'exercicio_materia.php', requer: 'materia',
          url: function (d) { return 'exercicio_materia.php?id=' + encodeURIComponent(d.materia); },
          alvo: '.contCabeca', humor: 'comemorando', anim: 'gira',
          titulo: 'Dentro da matéria 🪐',
          texto: 'Aqui ficam as listas dessa matéria. Em “Gerar novo exercício”, você escolhe o assunto e eu crio as questões.' },

        // ---------------- POMODORO ----------------
        { id: 'pomo', cap: 4, pagina: 'pomodoro.php', alvo: '.pomo', humor: 'foco', anim: 'pop',
          titulo: 'Pomodoro',
          texto: 'Clique em “Iniciar” e estude em blocos com pausas. Cada bloco vale XP e mantém sua sequência de dias 🔥' },

        // ---------------- MENU E CONTA ----------------
        // As abas simples não precisam de visita: o Orion acende o menu
        // e explica cada uma numa linha (4 páginas a menos para carregar)
        { id: 'menu', cap: 5, pagina: 'pomodoro.php', alvo: ['.contLateral', '.botoesL'], humor: 'curioso', anim: 'aponta',
          titulo: 'O resto está no menu',
          texto: '“Buscar” acha qualquer coisa sua. “Provas” conta os dias até cada prova. Em “Orion”, você conversa comigo. E seu nível leva às conquistas.' },
        { id: 'visual', cap: 5, pagina: 'conta.php', url: function () { return 'conta.php#estudo'; },
          alvo: '.visual-opcoes', humor: 'pensativo', anim: 'aponta',
          titulo: 'Modo Lite e modo foco',
          texto: 'Aparelho lento? Escolha “Lite”, sem brilhos e animações. Com ele, o modo foco ainda esconde avisos e distrações.' },

        // ---------------- FIM ----------------
        // Quem termina pela 1ª vez ganha 100 XP (tutorial_concluido.php) e
        // sobe para o nível 2. Quem revê o tour (já premiado) lê só a despedida.
        { id: 'fim', cap: 5, pagina: 'index.php', alvo: '.orion-launcher', fim: true, humor: 'vitoria', anim: 'acena',
          titulo: function () { return window.KOSMOS_TUTORIAL_PREMIADO ? 'Eu fico aqui no cantinho' : 'Tour completo! 🏆'; },
          texto: function () {
              return window.KOSMOS_TUTORIAL_PREMIADO
                  ? 'Estou neste botão em todas as abas. Precisou de ajuda? É só clicar em mim. Bons estudos! ✦'
                  : 'De presente, 100 XP: você já sobe para o nível 2! Daqui pra frente eu fico neste botão, em todas as abas.';
          },
          botao: function () { return window.KOSMOS_TUTORIAL_PREMIADO ? 'Começar a estudar' : 'Pegar recompensa'; } }
    ];

    /* ============================================================
       AS DICAS DA PRIMEIRA VEZ
       Fora do tour, o Orion aparece rapidinho (2–3 falas) na primeira
       vez que a pessoa chega numa tela que o tour não detalha. Cada uma
       aparece uma vez por conta neste aparelho (localStorage).
       paginas: onde ela pode aparecer — o carregador do sidebar.php tem
                o mesmo mapa (DICAS_POR_ABA) para só baixar este arquivo
                nas abas que ainda têm dica.
       quando:  a tela chegou ao ponto em que a dica faz sentido.
       ============================================================ */
    var DICAS = [
        { id: 'editor', paginas: ['resumos.php', 'caderno.php'],
          quando: function () { return modalAberto('modal') && visivelSel('#conteudo'); },
          falas: [
            { alvo: '#conteudo', humor: 'feliz', anim: 'acena',
              titulo: 'Seu primeiro resumo!',
              texto: 'Escreva aqui com as suas palavras. Resumir é o que faz a matéria entrar na cabeça.' },
            { alvo: '#btnDestacar', humor: 'curioso', anim: 'aponta',
              titulo: 'Destaque o importante',
              texto: 'Selecione um trecho e clique em “Destacar”. No modo revisão, eu escondo esses trechos para você testar a memória.' },
            { alvo: '#btnSalvar', humor: 'determinado', anim: 'pop',
              titulo: 'Não esqueça de salvar',
              texto: 'Terminou? Clique em “Salvar”. Dá para voltar e editar quando quiser.' }
          ] },
        { id: 'resumo', paginas: ['resumo.php'],
          quando: function () { return visivelSel('.resumo-acoes'); },
          falas: [
            { alvo: ['#btnRevisar', '.resumo-acoes'], humor: 'curioso', anim: 'aponta',
              titulo: 'Modo revisão',
              texto: 'Com trechos destacados, “Modo revisão” esconde cada um. Tente lembrar e toque para conferir.' },
            { alvo: '#btnFlashcards', humor: 'feliz', anim: 'pula',
              titulo: 'Vire flashcards',
              texto: 'Leu tudo? Clique em “Flashcards” e eu transformo este resumo em cartões de pergunta e resposta.' }
          ] },
        { id: 'estudo', paginas: ['flashcards.php', 'revisar.php'],
          quando: function () { return visivelSel('#flashcard'); },
          falas: [
            { alvo: '#flashcard', humor: 'pensativo', anim: 'aponta',
              titulo: 'Primeiro tente lembrar',
              texto: 'Pense na resposta antes de olhar. Depois, toque no cartão para virar.' },
            { humor: 'feliz', anim: 'acena',
              titulo: 'Seja sincero',
              texto: 'Virou? Diga se lembrou. Acertou, o cartão volta daqui a uns dias; errou, ele volta logo.' }
          ] },
        // Provas não tem visita no tour (só uma linha no passo do menu):
        // a explicação de verdade vem aqui, na primeira vez na aba
        { id: 'provas', paginas: ['provas.php'],
          quando: function () { return visivelSel('#btnNovaProva'); },
          falas: [
            { alvo: '#btnNovaProva', humor: 'pensativo', anim: 'aponta',
              titulo: 'Suas provas',
              texto: 'Em “Nova prova”, você marca a data e o que vai cair. Eu conto os dias para nada te pegar de surpresa.' },
            { alvo: '#pvProximas', humor: 'curioso', anim: 'pop',
              titulo: 'Estudo marcado',
              texto: 'Cada assunto da prova vira uma caixinha: marque o que já estudou. Depois da prova, anote a nota para ver sua evolução.' }
          ] },
        // o campo "O que cai" só existe ao CRIAR: é ele que diz que o modal é de prova nova
        { id: 'prova-form', paginas: ['provas.php'],
          quando: function () { return modalAberto('modalProva') && visivelSel('#provaTopicosCampo'); },
          falas: [
            { alvo: '#provaData', humor: 'foco', anim: 'aponta',
              titulo: 'Quando é?',
              texto: 'Escolha o dia da prova. A contagem regressiva começa na hora.' },
            { alvo: '#provaTopicosCampo', humor: 'determinado', anim: 'pop',
              titulo: 'O que cai',
              texto: 'Escreva um assunto por linha. Depois, na prova, é só ir marcando o que já estudou.' }
          ] },
        { id: 'praticar', paginas: ['exercicio_materia.php'],
          quando: function () { return modalAberto('modalPraticarExercicio') && visivelSel('#praticarAlts'); },
          falas: [
            { alvo: '#praticarAlts', humor: 'determinado', anim: 'aponta',
              titulo: 'Escolha uma alternativa',
              texto: 'Clique na que você acha certa: a correção aparece na hora.' },
            { alvo: ['#praticarDicaBtn', '#praticarAlts'], humor: 'curioso', anim: 'pop',
              titulo: 'Travou?',
              texto: 'Clique em “Ver dica”: ela ajuda sem entregar a resposta. Errou? Depois você pode me perguntar o porquê.' }
          ] }
    ];

    // o motor percorre um roteiro: o tour (PASSOS) ou as falas de uma dica
    var ROTEIRO = PASSOS, dicaAtual = null;
    var CHAVE_DICAS = window.kosmosChaveConta ? window.kosmosChaveConta('kosmos_dicas') : 'kosmos_dicas';
    var valor = function (v) { return typeof v === 'function' ? v(dados) : v; };

    function indice(id) {
        for (var i = 0; i < PASSOS.length; i++) if (PASSOS[i].id === id) return i;
        return -1;
    }

    /* ---------- estado guardado: { passo, dados } ---------- */
    function lerEstado() {
        try {
            var e = JSON.parse(sessionStorage.getItem(CHAVE) || 'null');
            return e && typeof e.passo === 'number' ? e : null;
        } catch (err) { return null; }
    }
    function gravarEstado() {
        if (dicaAtual) return;   // dica não atravessa páginas: não há o que guardar
        try { sessionStorage.setItem(CHAVE, JSON.stringify({ passo: passoAtual, dados: dados })); }
        catch (err) { /* navegação privada: o tour para na troca de aba */ }
    }
    function limparEstado() {
        try { sessionStorage.removeItem(CHAVE); } catch (err) { /* nada */ }
    }

    // Transição entre documentos (Chrome/Edge): a página antiga e a nova
    // se misturam em vez de piscar. Onde não existe, fica o véu escuro.
    function ligarTransicaoDePagina() {
        if (document.getElementById('tutVT')) return;
        var s = document.createElement('style');
        s.id = 'tutVT';
        s.textContent = '@view-transition{navigation:auto}';
        document.head.appendChild(s);
    }

    /* ============================================================
       A CAMADA
       ============================================================ */
    var raiz, luz, blocos, personagem, orionEl, mascoteEl, balao, elTitulo, elTexto, elFantasma,
        elContador, elBarra, btnProximo, elDica, btnSaltar, btnPular;
    var passoAtual = -1, dados = {}, alvoAtual = null, limpezas = [], digitando = null;
    var saindo = false;
    var canto = { lado: 'dir', alt: 'baixo', compacto: false }, trocandoCanto = false, cobrindoSeguidos = 0;

    function montarCamada() {
        if (raiz) return;
        ultimaLuz = '';   // camada nova: tudo precisa ser escrito de novo
        raiz = document.createElement('div');
        raiz.className = 'tut';
        raiz.innerHTML =
            // quatro faixas em volta do recorte + uma sobre ele: nos passos
            // de "fala" bloqueiam tudo; nos de "ação" o recorte fica livre
            '<div class="tut__bloco" data-b="cima"></div><div class="tut__bloco" data-b="baixo"></div>' +
            '<div class="tut__bloco" data-b="esq"></div><div class="tut__bloco" data-b="dir"></div>' +
            '<div class="tut__bloco" data-b="meio"></div>' +
            // as duas <i> são as ondas que saem da borda nos passos de ação
            '<div class="tut__luz" aria-hidden="true"><i class="tut__onda"></i><i class="tut__onda"></i></div>' +
            '<div class="tut__personagem">' +
                '<div class="tut__balao" role="dialog" aria-modal="true" aria-labelledby="tutTitulo" aria-describedby="tutTexto">' +
                    '<button type="button" class="tut__pular" title="Pular o tutorial">Pular</button>' +
                    '<p class="tut__nome">Orion <span class="tut__contador"></span></p>' +
                    '<h2 class="tut__titulo" id="tutTitulo"></h2>' +
                    // o fantasma guarda o texto inteiro (invisível): o balão já
                    // nasce no tamanho final e não cresce enquanto "digita"
                    '<p class="tut__texto" id="tutTexto"><span class="tut__fantasma" aria-hidden="true"></span><span class="tut__digitado" aria-live="polite"></span></p>' +
                    '<div class="tut__acoes">' +
                        '<span class="tut__dica" hidden>Faça no destaque</span>' +
                        '<button type="button" class="tut__saltar" hidden>pular etapa</button>' +
                        '<button type="button" class="dash-btn dash-btn--primary tut__proximo"><span>Próximo</span>' +
                            '<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M7.5 4.5 13 10l-5.5 5.5" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>' +
                        '</button>' +
                    '</div>' +
                    '<div class="tut__progresso" aria-hidden="true"><span></span></div>' +
                '</div>' +
                '<div class="tut__orion"><div class="tut__orion-corpo"><div class="mascote" id="tutMascote" data-mascote aria-hidden="true"></div></div></div>' +
            '</div>';
        document.body.appendChild(raiz);

        var q = function (s) { return raiz.querySelector(s); };
        luz = q('.tut__luz');
        blocos = {};
        raiz.querySelectorAll('.tut__bloco').forEach(function (b) { blocos[b.dataset.b] = b; });
        personagem = q('.tut__personagem'); orionEl = q('.tut__orion'); mascoteEl = q('#tutMascote');
        balao = q('.tut__balao'); elTitulo = q('.tut__titulo');
        elTexto = q('.tut__digitado'); elFantasma = q('.tut__fantasma');
        elContador = q('.tut__contador'); elBarra = q('.tut__progresso span');
        btnProximo = q('.tut__proximo'); elDica = q('.tut__dica'); btnSaltar = q('.tut__saltar'); btnPular = q('.tut__pular');

        if (window.KosmosMascote) window.KosmosMascote.montar(mascoteEl);

        btnProximo.addEventListener('click', function () {
            if (digitando) { digitando(); return; }   // 1º toque completa o texto, como nos jogos
            avancar();
        });
        btnSaltar.addEventListener('click', saltarEtapa);
        btnPular.addEventListener('click', function () { fechar(); });
        orionEl.addEventListener('click', function () { animar(['pula', 'gira', 'treme'][Math.floor(Math.random() * 3)]); });
        document.addEventListener('keydown', teclas, true);   // captura: Esc e Enter não vazam para o modal de baixo
        window.addEventListener('resize', aoRedimensionar);
        window.addEventListener('scroll', aoRolar, true);

        // a luz nasce fechada (tela toda escura) e abre no alvo: o véu do
        // sidebar.php sai por baixo sem degrau nenhum
        semTransicao(luz, function () { definirLuz(null); });

        document.documentElement.classList.add('tutorial-ativo');
        if (!dicaAtual) ligarTransicaoDePagina();   // dica não troca de página
        requestAnimationFrame(function () {
            if (!raiz) return;
            raiz.classList.add('tut--visivel');
            document.documentElement.classList.remove('tut-chegando');
        });
    }

    function semTransicao(el, fn) {
        el.style.transition = 'none';
        fn();
        void el.offsetWidth;
        el.style.transition = '';
    }

    function teclas(e) {
        if (!raiz || saindo) return;
        var p = ROTEIRO[passoAtual];
        if (p && p.tipo === 'acao') return;   // a pessoa está digitando no formulário
        if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); fechar(); }
        else if (e.key === 'ArrowRight' || e.key === 'Enter') {
            if (document.activeElement && document.activeElement !== btnProximo && document.activeElement.tagName === 'BUTTON') return;
            e.preventDefault(); e.stopPropagation(); btnProximo.click();
        }
    }

    /* ---------- o rosto e o corpo ---------- */
    var GESTOS = ['acena', 'pula', 'aponta', 'gira', 'treme', 'pop'];
    function animar(nome) {
        if (!nome || semMovimento) return;
        // o gesto vai no "corpo" de dentro: o de fora fica só com o voo final
        var corpo = orionEl.firstChild;
        GESTOS.forEach(function (g) { corpo.classList.remove('tut-anim--' + g); });
        void corpo.offsetWidth;
        corpo.classList.add('tut-anim--' + nome);
    }

    // Texto digitado letra a letra, com a boca mexendo enquanto "fala".
    // Deixa em `digitando` a função que completa na hora (1º toque no Próximo).
    function falar(texto) {
        if (digitando) digitando();
        elFantasma.textContent = texto;
        if (semMovimento) { elTexto.textContent = texto; return; }
        var i = 0, fim = false;
        elTexto.textContent = '';
        orionEl.classList.add('tut--falando');
        var t = setInterval(function () {
            i += texto.charAt(i) === ' ' ? 2 : 1;
            elTexto.textContent = texto.slice(0, i);
            if (i >= texto.length) completar();
        }, 20);
        function completar() {
            if (fim) return;
            fim = true;
            clearInterval(t);
            elTexto.textContent = texto;
            orionEl.classList.remove('tut--falando');
            digitando = null;
        }
        digitando = completar;
    }

    /* ---------- achar o alvo ---------- */
    function visivel(el) {
        var r = el.getBoundingClientRect();
        return r.width > 0 && r.height > 0 && getComputedStyle(el).visibility !== 'hidden';
    }
    function resolverAlvo(p) {
        var a = typeof p.alvo === 'function' ? p.alvo(dados) : p.alvo;
        if (!a) return null;
        var lista = Array.isArray(a) ? a : [a];
        for (var i = 0; i < lista.length; i++) {
            var els = document.querySelectorAll(lista[i]);
            for (var j = 0; j < els.length; j++) if (visivel(els[j])) return els[j];
        }
        return null;
    }

    /* ============================================================
       MOSTRAR UM PASSO
       ============================================================ */
    function limparPasso() {
        limpezas.forEach(function (f) { f(); });
        limpezas = [];
    }

    function mostrar(n) {
        var p = ROTEIRO[n];
        if (!p) { fechar(); return; }
        if (p.requer && !dados[p.requer]) { irPara(n + 1); return; }   // não criou: segue (talvez em outra aba)

        limparPasso();
        passoAtual = n;
        saindo = false;
        gravarEstado();
        var primeiro = !raiz;
        montarCamada();

        var acao = p.tipo === 'acao';
        raiz.classList.toggle('tut--acao', acao);

        var ultimo = n === ROTEIRO.length - 1;
        elTitulo.textContent = valor(p.titulo);
        falar(valor(p.texto));
        // tour: o capítulo ("Biblioteca · 2 de 6"); dica: as falas dela
        elContador.textContent = dicaAtual
            ? 'dica · ' + (n + 1) + '/' + ROTEIRO.length
            : CAPITULOS[p.cap] + ' · ' + (p.cap + 1) + ' de ' + CAPITULOS.length;
        elBarra.style.width = Math.round((n + 1) / ROTEIRO.length * 100) + '%';

        btnProximo.hidden = acao;
        btnProximo.disabled = false;
        elDica.hidden = !acao;
        btnSaltar.hidden = !(acao && p.saltar);
        btnPular.hidden = !!p.fim || (dicaAtual && ultimo);
        btnPular.textContent = dicaAtual ? 'Fechar' : 'Pular';
        btnProximo.querySelector('span').textContent =
            p.botao ? valor(p.botao) : dicaAtual && ultimo ? 'Entendi' : 'Próximo';
        document.documentElement.classList.toggle('tut-mostra-launcher', !!p.fim);

        if (mascoteEl.mascote) {
            mascoteEl.mascote.humor(p.humor || 'feliz');
            mascoteEl.mascote.piscar(220);
        }
        animar(p.anim);

        balao.classList.remove('tut__balao--entra');
        void balao.offsetWidth;   // reinicia a entrada do balão
        balao.classList.add('tut__balao--entra');

        alvoAtual = resolverAlvo(p);
        var rolou = false;
        if (alvoAtual) {
            var a = alvoAtual.getBoundingClientRect();
            rolou = a.top < 70 || a.bottom > window.innerHeight - 70;
            if (rolou) alvoAtual.scrollIntoView({ block: 'center', behavior: semMovimento ? 'auto' : 'smooth' });
        }

        // O canto do Orion é escolhido UMA vez por passo, e só depois que a
        // tela assentou: a rolagem até o alvo terminou e o modal acabou de
        // abrir. Decidir logo de cara (com o alvo ainda no lugar antigo) e
        // de novo depois era o "vai para um lado e já volta para o outro".
        // Até lá ele fica onde está; na 1ª fala da página, fica invisível
        // e entra direto no canto certo.
        cobrindoSeguidos = 0;
        travarCanto(9999);
        posicionar();
        if (primeiro) raiz.classList.add('tut--aguardando');
        var assentar = semMovimento ? 0 : (rolou ? 520 : p.tipo === 'acao' ? 360 : 220);
        var t = setTimeout(function () {
            if (!raiz) return;
            posicionar();
            if (primeiro) {
                aplicarCanto(melhorCanto(), true);
                raiz.classList.remove('tut--aguardando');
            } else {
                escolherCanto();
            }
            travarCanto(1200);
        }, assentar);
        limpezas.push(function () { clearTimeout(t); });

        // alvos que mexem (modal abrindo, lista carregando): reposiciona sempre
        var vigia = setInterval(function () {
            if (!alvoAtual || !document.contains(alvoAtual) || !visivel(alvoAtual)) {
                alvoAtual = resolverAlvo(p);
            }
            posicionar();
            vigiarCanto();
        }, 200);
        limpezas.push(function () { clearInterval(vigia); });

        if (acao) esperarAcao(p);
        // sem focar o "Próximo": Enter e → já avançam (teclas()), e o foco
        // por script fazia o navegador desenhar um anel em volta dele
        else if (document.activeElement && raiz.contains(document.activeElement)) document.activeElement.blur();
    }

    /* Passo de ação: espera a pessoa fazer, e volta se ela desistir */
    function esperarAcao(p) {
        var feito = false;
        function concluir(e) {
            if (feito) return;
            feito = true;
            if (p.guardar) p.guardar(e, dados);
            gravarEstado();
            if (mascoteEl.mascote) mascoteEl.mascote.humor('comemorando');
            var t = setTimeout(avancar, p.ate === 'clique' ? 380 : 650);
            limpezas.push(function () { clearTimeout(t); });
        }

        if (p.ate === 'clique') {
            var tentar = setInterval(function () {
                if (!alvoAtual) return;
                clearInterval(tentar);
                var el = alvoAtual;
                el.addEventListener('click', concluir);
                limpezas.push(function () { el.removeEventListener('click', concluir); });
            }, 100);
            limpezas.push(function () { clearInterval(tentar); });
        }
        if (p.evento) {
            document.addEventListener(p.evento, concluir);
            limpezas.push(function () { document.removeEventListener(p.evento, concluir); });
        }
        if (p.quando || p.volta) {
            // "volta" só vale depois de um tempo firme: entre fechar o modal
            // e a tela nova aparecer há um instante em que os dois são falsos
            var seguidos = 0;
            var vigia = setInterval(function () {
                if (feito) return;
                if (p.quando && p.quando()) { concluir(null); return; }
                if (p.volta && p.volta()) {
                    if (++seguidos >= 6) {
                        feito = true;
                        if (mascoteEl.mascote) mascoteEl.mascote.humor('confuso');
                        mostrar(indice(p.voltaPara));
                    }
                } else {
                    seguidos = 0;
                }
            }, 250);
            limpezas.push(function () { clearInterval(vigia); });
        }
    }

    /* ============================================================
       POSIÇÕES: luz e faixas
       ============================================================ */
    var retAlvo = null;   // o recorte atual (para o canto do Orion)
    var ultimaLuz = '';   // o que foi escrito por último (ver definirLuz)

    function definirLuz(r) {
        var vw = window.innerWidth, vh = window.innerHeight;
        var x, y, w, h;
        if (!r) { x = vw / 2; y = vh / 2; w = 0; h = 0; }
        else { x = r.left; y = r.top; w = r.width; h = r.height; }

        var p = ROTEIRO[passoAtual] || {};
        var furo = !!r && p.tipo === 'acao';

        // A vigia chama isto 5 vezes por segundo, e quase sempre nada mudou:
        // reescrever seis estilos à toa fazia o navegador recalcular a
        // camada (e a sombra gigante da luz) o tempo todo.
        var chave = [Math.round(x), Math.round(y), Math.round(w), Math.round(h), vw, vh, furo].join();
        if (chave === ultimaLuz) return;
        ultimaLuz = chave;

        luz.style.left = x + 'px'; luz.style.top = y + 'px';
        luz.style.width = w + 'px'; luz.style.height = h + 'px';
        // faixas em volta do recorte
        place(blocos.cima, 0, 0, vw, y);
        place(blocos.baixo, 0, y + h, vw, vh - y - h);
        place(blocos.esq, 0, y, x, h);
        place(blocos.dir, x + w, y, vw - x - w, h);
        place(blocos.meio, x, y, furo ? 0 : w, furo ? 0 : h);
    }
    function place(el, x, y, w, h) {
        el.style.left = x + 'px'; el.style.top = y + 'px';
        el.style.width = Math.max(w, 0) + 'px'; el.style.height = Math.max(h, 0) + 'px';
    }

    function posicionar() {
        if (!raiz || saindo) return;
        var vw = window.innerWidth, vh = window.innerHeight;
        var r = null;

        if (alvoAtual && document.contains(alvoAtual)) {
            // folga generosa: botões que crescem no hover não encostam na borda
            var a = alvoAtual.getBoundingClientRect(), f = 10;
            var x1 = Math.max(a.left - f, 8), y1 = Math.max(a.top - f, 8);
            var x2 = Math.min(a.right + f, vw - 8), y2 = Math.min(a.bottom + f, vh - 8);
            if (x2 > x1 && y2 > y1) r = { left: x1, top: y1, width: x2 - x1, height: y2 - y1 };
        }
        retAlvo = r;
        definirLuz(r);
        raiz.classList.toggle('tut--sem-alvo', !r);

        // Passo de ação num botão: a borda dourada pulsa, soltando ondas
        // para fora — "é aqui que você clica". Num alvo grande (o modal
        // inteiro) as ondas seriam barulho: lá fica só a borda.
        var p = ROTEIRO[passoAtual] || {};
        raiz.classList.toggle('tut--pulsa', !!r && p.tipo === 'acao' && r.height < vh * 0.5);
    }

    function aoRolar() { posicionar(); }
    // o resize dispara dezenas de vezes seguidas: o canto só é refeito quando ele para
    var esperaResize = 0;
    function aoRedimensionar() {
        posicionar();
        clearTimeout(esperaResize);
        esperaResize = setTimeout(function () { posicionar(); escolherCanto(); travarCanto(1200); }, 220);
    }

    /* ============================================================
       O CANTO DO ORION
       Só embaixo: à direita (o normal) ou à esquerda — vale o lado que
       menos cobre o alvo. (Os cantos de cima ficaram de fora: o Orion
       subindo para o topo da tela tirava a atenção do que ele mostrava.)
       Se os dois cobrem (alvo enorme, como um modal), ele encolhe e fica
       no que cobre menos.
       ============================================================ */
    function tamanhoCheio() {
        // tamanho do Orion + balão sem o modo compacto (desfaz a conta se está)
        var c = canto.compacto;
        return { w: personagem.offsetWidth / (c ? 0.8 : 1), h: personagem.offsetHeight / (c ? 0.75 : 1) };
    }
    function cobre(c, t) {
        var r = retAlvo;
        if (!r) return 0;
        var vw = window.innerWidth, vh = window.innerHeight;
        var w = c.compacto ? t.w * 0.8 : t.w, h = c.compacto ? t.h * 0.75 : t.h;
        var m = vw < 640 ? 10 : 18;
        var x = c.lado === 'dir' ? vw - m - w : m;
        var y = c.alt === 'baixo' ? vh - m - h : m;
        var ix = Math.max(0, Math.min(x + w, r.left + r.width) - Math.max(x, r.left));
        var iy = Math.max(0, Math.min(y + h, r.top + r.height) - Math.max(y, r.top));
        return ix * iy;
    }
    function melhorCanto() {
        var t = tamanhoCheio();
        var opcoes = [];
        [false, true].forEach(function (compacto) {
            [['dir', 'baixo'], ['esq', 'baixo']].forEach(function (o) {
                opcoes.push({ lado: o[0], alt: o[1], compacto: compacto });
            });
        });
        var melhor = opcoes[0], menor = Infinity;
        for (var i = 0; i < opcoes.length; i++) {
            var area = cobre(opcoes[i], t);
            if (area === 0) return opcoes[i];
            if (area < menor) { menor = area; melhor = opcoes[i]; }
        }
        return { lado: melhor.lado, alt: melhor.alt, compacto: true };
    }
    function escolherCanto() {
        if (!raiz || saindo) return;
        var t = tamanhoCheio();
        // ainda livre como está? fica (e, se estava compacto sem precisar, cresce)
        if (cobre({ lado: canto.lado, alt: canto.alt, compacto: false }, t) === 0) {
            if (canto.compacto) aplicarCanto({ lado: canto.lado, alt: canto.alt, compacto: false });
            return;
        }
        aplicarCanto(melhorCanto());
    }
    // Depois de escolher ou mover, o canto fica travado um tempo: nada de
    // reconsiderar enquanto o alvo ainda está se mexendo.
    var cantoTravadoAte = 0;
    function travarCanto(ms) { cantoTravadoAte = Date.now() + ms; }

    // Chamado a cada vigia (200 ms): só troca se ele cobrir o alvo por
    // ~1 s seguido, fora da trava — um modal que cresce ou uma lista que
    // carrega no meio da fala não fazem mais ele ficar indo e vindo.
    function vigiarCanto() {
        if (trocandoCanto || Date.now() < cantoTravadoAte) { cobrindoSeguidos = 0; return; }
        if (cobre(canto, tamanhoCheio()) > 0) {
            if (++cobrindoSeguidos >= 6) { cobrindoSeguidos = 0; escolherCanto(); travarCanto(1200); }
        } else {
            cobrindoSeguidos = 0;
        }
    }

    /* Trocar de canto DESLIZA: o Orion e o balão viajam da posição
       antiga até a nova (técnica FLIP — mede antes, aplica o canto novo,
       mede depois e anima a diferença com transform). left/right não
       animam, então antes ele sumia num lado e surgia no outro, como
       teletransporte. Se outra troca chega no meio do caminho, a nova
       parte de onde ele está naquele instante: o retângulo medido já
       inclui o transform em andamento. */
    var movimento = 0;
    function aplicarCanto(novo, direto) {
        if (novo.lado === canto.lado && novo.alt === canto.alt && novo.compacto === canto.compacto) return;

        function aplicar() {
            canto = novo;
            raiz.classList.toggle('tut--esq', novo.lado === 'esq');
            raiz.classList.toggle('tut--cima', novo.alt === 'cima');
            raiz.classList.toggle('tut--compacto', novo.compacto);
        }
        if (direto || semMovimento) { aplicar(); return; }

        var pecas = [orionEl, balao];
        var antes = pecas.map(function (el) { return el.getBoundingClientRect(); });
        aplicar();
        pecas.forEach(function (el, i) {
            var a = antes[i];
            el.style.transition = 'none';
            el.style.transform = '';
            var d = el.getBoundingClientRect();
            // o Orion também muda de tamanho (compacto); o balão só de lugar
            var s = el === orionEl && d.width ? a.width / d.width : 1;
            el.style.transformOrigin = '0 0';
            el.style.transform = 'translate(' + (a.left - d.left) + 'px,' + (a.top - d.top) + 'px) scale(' + s + ')';
        });
        void personagem.offsetWidth;

        var este = ++movimento;
        trocandoCanto = true;
        raiz.classList.add('tut--movendo');
        requestAnimationFrame(function () {
            pecas.forEach(function (el) {
                el.style.transition = 'transform .6s cubic-bezier(.22, 1, .36, 1)';
                el.style.transform = '';
            });
        });
        setTimeout(function () {
            if (este !== movimento) return;   // outra troca assumiu
            pecas.forEach(function (el) { el.style.transition = ''; el.style.transformOrigin = ''; });
            if (raiz) raiz.classList.remove('tut--movendo');
            trocandoCanto = false;
        }, 640);
    }

    /* ============================================================
       AVANÇAR, SALTAR, IR DE ABA EM ABA
       ============================================================ */
    function proximoValido(n) {
        while (ROTEIRO[n] && ROTEIRO[n].requer && !dados[ROTEIRO[n].requer]) n++;
        return n;
    }

    function irPara(n) {
        n = proximoValido(n);
        var q = ROTEIRO[n];
        if (!q) { fechar(); return; }

        var url = q.url ? q.url(dados) : q.pagina;
        if (q.pagina === PAGINA && !q.url) { mostrar(n); return; }
        // mesma página, só outra seção (conta.php#estudo): troca o hash e segue
        if (q.pagina === PAGINA && url.indexOf(PAGINA + '#') === 0) {
            location.hash = url.slice(PAGINA.length);
            setTimeout(function () { mostrar(n); }, 120);
            return;
        }
        // outra página, ou a mesma com outro id (o caderno recém-criado): navega
        limparPasso();
        passoAtual = n;
        gravarEstado();
        sair(function () { window.location.href = url; });
    }

    function avancar() {
        var p = ROTEIRO[passoAtual];
        if (!p || saindo) return;
        if (p.fim) { terminar(); return; }
        // dica: tudo na mesma tela, e a última fala fecha
        if (dicaAtual) {
            if (passoAtual >= ROTEIRO.length - 1) fechar();
            else mostrar(passoAtual + 1);
            return;
        }
        irPara(passoAtual + 1);
    }

    function saltarEtapa() {
        var p = ROTEIRO[passoAtual];
        if (p && p.saltar && !saindo) irPara(indice(p.saltar));
    }

    // a luz fecha, o balão some e o Orion abaixa: aí troca de página.
    // `saindo` trava cliques duplos e a vigia (que reabriria a luz).
    function sair(depois) {
        if (digitando) digitando();
        if (!raiz) { depois(); return; }
        saindo = true;
        btnProximo.disabled = true;
        raiz.classList.add('tut--saindo');
        alvoAtual = null;
        definirLuz(null);
        raiz.classList.remove('tut--pulsa');
        setTimeout(depois, semMovimento ? 0 : 420);
    }

    function fechar() {
        limparPasso();
        if (dicaAtual) {
            // acabou a dica: o motor volta a ser do tour e a vigia das
            // outras dicas desta tela continua
            dicaAtual = null;
            ROTEIRO = PASSOS;
            vigiarDicas();
        } else {
            limparEstado();
        }
        if (digitando) digitando();
        saindo = false;
        document.removeEventListener('keydown', teclas, true);
        window.removeEventListener('resize', aoRedimensionar);
        window.removeEventListener('scroll', aoRolar, true);
        document.documentElement.classList.remove('tutorial-ativo', 'tut-chegando', 'tut-mostra-launcher');
        if (!raiz) return;
        var r = raiz;
        raiz = null;
        r.classList.remove('tut--visivel');
        setTimeout(function () { r.remove(); }, semMovimento ? 0 : 350);
    }

    // Voltar/avançar do navegador traz a página do cache já com o tour
    // fechando: reabre do passo guardado em vez de ficar escura.
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) return;
        if (raiz) { raiz.remove(); raiz = null; }
        saindo = false;
        dicaAtual = null;
        ROTEIRO = PASSOS;
        ultimaLuz = '';
        canto = { lado: 'dir', alt: 'baixo', compacto: false };
        iniciar();
    });

    /* Fim: o Orion grande voa e encolhe até o botão do canto — é
       ali que ele vai ficar. Sem o botão (modo foco), só some. */
    function terminar() {
        // o prêmio sai depois que a camada some: o "+XP" e a subida de
        // nível do js/progresso.js aparecem com a tela já livre
        setTimeout(receberPremio, semMovimento ? 0 : 900);

        var destino = document.querySelector('.orion-launcher');
        if (!destino || !visivel(destino) || semMovimento) { fechar(); return; }

        limparPasso();
        limparEstado();
        saindo = true;
        raiz.classList.add('tut--voando');
        var de = orionEl.getBoundingClientRect(), para = destino.getBoundingClientRect();
        var esc = para.width / de.width;
        var dx = (para.left + para.width / 2) - (de.left + de.width / 2);
        var dy = (para.top + para.height / 2) - (de.top + de.height / 2);
        orionEl.style.transform = 'translate(' + dx + 'px,' + dy + 'px) scale(' + esc + ')';
        definirLuz(null);
        setTimeout(function () {
            destino.classList.add('tut-pousou');
            setTimeout(function () { destino.classList.remove('tut-pousou'); }, 900);
            fechar();
        }, 750);
    }

    /* ============================================================
       COMEÇAR
       ============================================================ */
    function comecar() {
        dados = {};
        passoAtual = 0;
        gravarEstado();
        if (PAGINA === 'index.php') mostrar(0);
        else window.location.href = 'index.php';
    }

    // 100 XP e a conquista "Decolagem", uma vez por conta (o servidor
    // confere). A resposta traz `progresso`, e o fetch embrulhado do
    // js/progresso.js já mostra o "+XP", a conquista e o nível novo.
    function receberPremio() {
        if (window.KOSMOS_TUTORIAL_PREMIADO) return;
        window.KOSMOS_TUTORIAL_PREMIADO = true;
        fetch('../../../Backend/php/tutorial_concluido.php', { method: 'POST' })
            .catch(function () { /* sem rede: a pessoa pode rever o tour e receber depois */ });
    }

    function marcarVisto() {
        window.KOSMOS_TUTORIAL = false;
        // Caminho a partir de Frontend/pages/dashboard/: três níveis até a raiz
        fetch('../../../Backend/php/tutorial_visto.php', { method: 'POST' })
            .catch(function () { /* sem rede: aparece de novo na próxima visita, melhor que nunca */ });
    }

    // A intro (html.com-intro) e a pesquisa de perfil (#onboardingModal)
    // precisam ter saído da tela. Sem evento comum entre as duas, uma
    // checagem leve a cada 300 ms resolve — e para sozinha.
    function quandoLivre(fn) {
        (function checar() {
            var ocupado = document.documentElement.classList.contains('com-intro')
                       || document.getElementById('onboardingModal');
            if (ocupado) { setTimeout(checar, 300); return; }
            fn();
        })();
    }

    /* ---------- as dicas da primeira vez ---------- */
    function dicasVistas() {
        try { return JSON.parse(localStorage.getItem(CHAVE_DICAS) || '[]'); } catch (err) { return []; }
    }
    function marcarDica(id) {
        var v = dicasVistas();
        if (v.indexOf(id) < 0) v.push(id);
        try { localStorage.setItem(CHAVE_DICAS, JSON.stringify(v)); } catch (err) { /* privado: pode repetir */ }
    }

    // Uma checagem leve a cada 700 ms, só nas abas que ainda têm dica, e
    // que para sozinha quando não sobra nenhuma. Nada durante o tour nem
    // no modo foco (que existe justamente para tirar as distrações).
    var vigiaDicas = 0;
    function vigiarDicas() {
        clearInterval(vigiaDicas);
        var vistas = dicasVistas();
        var pendentes = DICAS.filter(function (d) {
            return d.paginas.indexOf(PAGINA) >= 0 && vistas.indexOf(d.id) < 0;
        });
        if (!pendentes.length) return;
        vigiaDicas = setInterval(function () {
            if (raiz || lerEstado()) return;   // algo do Orion já está na tela
            if (document.documentElement.classList.contains('kosmos-foco')) return;
            for (var i = 0; i < pendentes.length; i++) {
                if (pendentes[i].quando()) {
                    clearInterval(vigiaDicas);
                    abrirDica(pendentes[i]);
                    return;
                }
            }
        }, 700);
    }
    function abrirDica(d) {
        marcarDica(d.id);   // marca já: fechando no meio, também não volta
        dicaAtual = d;
        ROTEIRO = d.falas;
        dados = {};
        // um instante para o modal ou o cartão terminar de aparecer
        setTimeout(function () { mostrar(0); }, semMovimento ? 0 : 350);
    }

    function iniciar() {
        var e = lerEstado();
        vigiarDicas();

        if (e && ROTEIRO[e.passo]) {
            dados = e.dados || {};
            if (ROTEIRO[e.passo].pagina === PAGINA) {
                // espera a página assentar (listas que o JS desenha)
                quandoLivre(function () { setTimeout(function () { mostrar(e.passo); }, 250); });
            } else {
                limparEstado();   // a pessoa saiu do roteiro
                document.documentElement.classList.remove('tut-chegando');
            }
            return;
        }
        document.documentElement.classList.remove('tut-chegando');

        if (window.KOSMOS_TUTORIAL && PAGINA === 'index.php') {
            quandoLivre(function () {
                setTimeout(function () {
                    marcarVisto();
                    dados = {};
                    mostrar(0);
                }, semMovimento ? 0 : 500);
            });
        }
    }

    // (o botão "Rever o tour" da Conta é do carregador do sidebar.php:
    //  lá este arquivo nem é baixado)
    window.KosmosTutorial = { comecar: comecar };

    // Este arquivo chega sob demanda (sidebar.php): espera o DOM e também
    // o tutorial.css, senão a camada apareceria um instante sem estilo.
    function quandoPronto(fn) {
        var css = document.getElementById('tutCss');
        var depoisDoCss = function () {
            if (!css || css.sheet) { fn(); return; }
            css.addEventListener('load', fn, { once: true });
            css.addEventListener('error', fn, { once: true });
        };
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', depoisDoCss);
        else depoisDoCss();
    }
    quandoPronto(iniciar);
})();
