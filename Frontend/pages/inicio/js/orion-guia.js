/* ============================================================
   KOSMOS — orion-guia.js
   O Orion acompanhando a pessoa pela landing page inteira.

   A ideia: ele não mora numa seção, ele ANDA junto. A cada parte da
   página que entra na tela ele comenta o que é aquilo — do jeito
   que alguém da casa faria mostrando os cômodos.

   Decisões que valem explicação, porque um mascote flutuante é
   fácil de transformar em incômodo:

   • Ele NÃO aparece no hero. A primeira tela é da Kosmos, não dele;
     ele entra quando a pessoa decide descer.
   • Ele SOME na seção da visita guiada, onde já existe um Orion em
     tamanho grande. Dois Orions na tela ao mesmo tempo seria
     estranho.
   • Dá para dispensar. Dispensado, ele encolhe para o planetinha
     quieto e só volta a falar se for chamado. A escolha vale a
     visita (sessionStorage), não para sempre: dispensar uma vez não
     deveria condenar o recurso em toda visita futura.
   • No celular ele não fala sozinho. A tela é pequena e um balão
     surgindo por cima do conteúdo atrapalharia a leitura; lá ele
     espera ser tocado.
   • Quem pediu "reduzir movimento" não recebe nada disso.

   As falas em si vivem em FALAS, uma por seção, para trocar o texto
   sem mexer em lógica nenhuma.
   ============================================================ */

(function () {
    'use strict';

    var guia = document.getElementById('guia');
    if (!guia) return;

    var corpo = document.getElementById('guiaCorpo');
    var desenho = document.getElementById('guiaMascote');
    var balao = document.getElementById('guiaBalao');
    var fala = document.getElementById('guiaFala');
    var fechar = document.getElementById('guiaFechar');
    var ponto = document.getElementById('guiaPonto');
    if (!corpo || !desenho) return;

    var semMovimento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (semMovimento) return;                 // nada de acompanhante

    var ponteiroFino = window.matchMedia('(hover: hover) and (pointer: fine)');

    var orion = (window.KosmosMascote && window.KosmosMascote.montar(desenho)) || desenho.mascote;
    if (!orion) return;

    /* ------------------------------------------------------------
       O que ele diz em cada parte da casa
       `humor` é o estado real do mascote (shared/mascote.js).
       ------------------------------------------------------------ */
    var FALAS = {
        sobre: {
            texto: 'Aqui eles contam por que a Kosmos existe. Resumindo: foi gente como você que fez.',
            humor: 'normal'
        },
        recursos: {
            texto: 'Estes são os cômodos da casa. Passa o olho com calma — eu espero.',
            humor: 'normal'
        },
        demo: {
            texto: 'Esta parte é pra mexer, não pra ler. Vira um cartão, liga o cronômetro. Pode.',
            humor: 'feliz'
        },
        historia: {
            texto: 'Quatro estudantes, um TCC e muita noite mal dormida. É essa a história.',
            humor: 'normal'
        },
        faq: {
            texto: 'Ficou com dúvida? Provavelmente já perguntaram antes. Dá uma olhada.',
            humor: 'normal'
        },
        fim: {
            texto: 'Chegamos ao fim da visita. Agora é criar a sua conta — e é de graça mesmo.',
            humor: 'feliz'
        }
    };

    /* A seção onde ele já aparece grande: ali o acompanhante se cala. */
    var SECAO_DELE = 'orion';

    /* ------------------------------------------------------------
       Estado
       ------------------------------------------------------------ */
    var CHAVE = 'kosmos:orion-guia-dispensado';
    var dispensado = false;
    try { dispensado = sessionStorage.getItem(CHAVE) === '1'; } catch (e) { /* modo privado */ }

    var atual = null;          // qual seção ele está comentando
    var visivel = false;
    var escrevendo = null;

    /* ------------------------------------------------------------
       Falar
       ------------------------------------------------------------ */
    function dizer(texto) {
        clearInterval(escrevendo);
        balao.hidden = false;

        // escreve letra a letra, como o balão da seção grande
        fala.textContent = '';
        var i = 0;
        escrevendo = setInterval(function () {
            fala.textContent = texto.slice(0, ++i);
            if (i >= texto.length) clearInterval(escrevendo);
        }, 18);
    }

    function calar() {
        clearInterval(escrevendo);
        balao.hidden = true;
    }

    /** Mostra a fala de uma seção. `porToque` = a pessoa pediu. */
    function comentar(nome, porToque) {
        var f = FALAS[nome];
        if (!f) return;

        orion.humor(f.humor);
        orion.piscar();

        /* Dispensado, ele só fala se for chamado. No celular também:
           lá o balão nunca aparece sozinho. */
        if (!porToque && (dispensado || !ponteiroFino.matches)) {
            // continua sinalizando que tem algo a dizer
            if (ponto) ponto.hidden = false;
            return;
        }

        if (ponto) ponto.hidden = true;
        dizer(f.texto);
    }

    /* ------------------------------------------------------------
       Aparecer e sumir
       ------------------------------------------------------------ */
    function mostrar(sim) {
        if (sim === visivel) return;
        visivel = sim;

        if (sim) {
            guia.hidden = false;
            // o próximo quadro, para a transição de entrada acontecer
            requestAnimationFrame(function () { guia.classList.add('dentro'); });
        } else {
            guia.classList.remove('dentro');
            calar();
            setTimeout(function () { if (!visivel) guia.hidden = true; }, 320);
        }
    }

    /* ------------------------------------------------------------
       Quem está na tela agora
       A faixa do meio decide: com estas margens, só a seção que
       cruza o centro da viewport conta como "intersecting". É o que
       evita ele ficar trocando de assunto na fronteira entre duas.
       ------------------------------------------------------------ */
    var secoes = [];
    ['sobre', 'recursos', 'demo', 'historia', 'orion', 'faq'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) secoes.push({ nome: id, el: el });
    });

    var fimEl = document.querySelector('.cta-final');
    if (fimEl) secoes.push({ nome: 'fim', el: fimEl });

    if (!secoes.length) return;

    var observador = new IntersectionObserver(function (entradas) {
        entradas.forEach(function (e) {
            if (!e.isIntersecting) return;

            var nome = null;
            for (var i = 0; i < secoes.length; i++) {
                if (secoes[i].el === e.target) { nome = secoes[i].nome; break; }
            }
            if (!nome || nome === atual) return;
            atual = nome;

            // na seção dele, o acompanhante sai de cena
            if (nome === SECAO_DELE) {
                mostrar(false);
                return;
            }

            mostrar(true);
            comentar(nome, false);
        });
    }, { rootMargin: '-45% 0px -45% 0px', threshold: 0 });

    secoes.forEach(function (s) { observador.observe(s.el); });

    /* ------------------------------------------------------------
       Controles
       ------------------------------------------------------------ */

    /* Tocar nele: mostra o que ele tem a dizer sobre esta parte —
       e é o único caminho no celular e depois de dispensado. */
    corpo.addEventListener('click', function () {
        if (!balao.hidden) { calar(); return; }   // clicar de novo fecha
        if (atual) comentar(atual, true);
    });

    if (fechar) {
        fechar.addEventListener('click', function (e) {
            e.stopPropagation();
            calar();
            dispensado = true;
            try { sessionStorage.setItem(CHAVE, '1'); } catch (err) { /* modo privado */ }

            // fica o planetinha, com o pontinho avisando que ele ainda tem o que dizer
            if (ponto) ponto.hidden = false;
            guia.classList.add('quieto');
        });
    }

    /* Rolar fecha o balão: ele comenta e sai da frente, em vez de
       ficar pendurado sobre o conteúdo que a pessoa foi ler. */
    var fechaAoRolar = null;
    window.addEventListener('scroll', function () {
        if (balao.hidden) return;
        clearTimeout(fechaAoRolar);
        fechaAoRolar = setTimeout(calar, 2600);
    }, { passive: true });

    /* Esc fecha o balão, como qualquer coisa que aparece por cima. */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !balao.hidden) calar();
    });
})();
