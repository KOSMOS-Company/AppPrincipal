/* ============================================================
   KOSMOS — inicio.js
   Interações da landing page: header, menu mobile, reveal,
   FAQ, tilt 3D, botões magnéticos, máquina de escrever,
   contadores, e demos interativas (flashcard, pomodoro, quiz).
   ============================================================ */

(function () {
    'use strict';

    const reduzMovimento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const ponteiroFino = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

    /* ────────────────────────────────
       Header, barra de progresso e voltar ao topo
       ──────────────────────────────── */
    const header = document.getElementById('header');
    const progresso = document.getElementById('scrollProgress');
    const backTop = document.getElementById('backTop');

    window.addEventListener('scroll', () => {
        const y = window.scrollY;
        header.classList.toggle('header--scrolled', y > 20);

        const total = document.documentElement.scrollHeight - window.innerHeight;
        progresso.style.width = (total > 0 ? (y / total) * 100 : 0) + '%';

        backTop.classList.toggle('show', y > 600);
    }, { passive: true });

    backTop.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: reduzMovimento ? 'auto' : 'smooth' });
    });

    /* ────────────────────────────────
       Menu mobile
       ──────────────────────────────── */
    const hamburger = document.getElementById('hamburger');
    const mobileNav = document.getElementById('mobileNav');
    hamburger.addEventListener('click', () => {
        hamburger.classList.toggle('active');
        mobileNav.classList.toggle('open');
    });
    document.querySelectorAll('.mobile-nav__link, .mobile-nav .btn').forEach(link => {
        link.addEventListener('click', () => {
            hamburger.classList.remove('active');
            mobileNav.classList.remove('open');
        });
    });

    /* ────────────────────────────────
       Scroll reveal
       ──────────────────────────────── */
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) entry.target.classList.add('visible');
        });
    }, { threshold: 0.1 });
    document.querySelectorAll('.card, .reveal').forEach(el => observer.observe(el));

    /* ────────────────────────────────
       Rolagem suave nas âncoras
       ──────────────────────────────── */
    document.querySelectorAll('a[href^="#"]').forEach(a => {
        a.addEventListener('click', e => {
            const target = document.querySelector(a.getAttribute('href'));
            if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth' }); }
        });
    });

    /* ────────────────────────────────
       FAQ: accordion
       ──────────────────────────────── */
    document.querySelectorAll('.faq__item').forEach(item => {
        item.querySelector('.faq__pergunta').addEventListener('click', () => {
            const aberto = item.classList.contains('aberto');
            document.querySelectorAll('.faq__item.aberto').forEach(o => o.classList.remove('aberto'));
            if (!aberto) item.classList.add('aberto');
        });
    });

    /* ────────────────────────────────
       Tilt 3D: cards de recursos + imagens (.tilt)
       ──────────────────────────────── */
    function aplicarTilt(el, graus, sobe) {
        el.addEventListener('mousemove', e => {
            const r = el.getBoundingClientRect();
            const x = e.clientX - r.left;
            const y = e.clientY - r.top;
            el.style.setProperty('--mx', x + 'px');
            el.style.setProperty('--my', y + 'px');
            const rx = (0.5 - y / r.height) * graus;
            const ry = (x / r.width - 0.5) * graus;
            el.style.transition = 'transform .08s ease-out';
            el.style.transform = 'perspective(800px) rotateX(' + rx + 'deg) rotateY(' + ry + 'deg)'
                + (sobe ? ' translateY(-6px)' : '');
        });
        el.addEventListener('mouseleave', () => {
            el.style.transition = '';
            el.style.transform = '';
        });
    }
    if (ponteiroFino && !reduzMovimento && !document.documentElement.classList.contains('kosmos-lite')) {
        document.querySelectorAll('.card').forEach(c => aplicarTilt(c, 8, true));
        document.querySelectorAll('.tilt').forEach(t => aplicarTilt(t, 5, false));
    }

    /* ────────────────────────────────
       Hero: palavra rotativa (máquina de escrever)
       ──────────────────────────────── */
    (function () {
        const alvo = document.getElementById('rotatorWord');
        if (!alvo || reduzMovimento) return;

        /* A máquina de escrever agora rotaciona a DOR, não o recurso.
           Antes eram os próprios recursos ('resumos', 'flashcards'), o
           que repetia a lista que já aparece logo abaixo no hero e no
           marquee. Como o título virou "Chega de estudar e ___", cada
           palavra fecha uma frase inteira — e são curtas de propósito:
           a `reservarLargura` abaixo reserva a largura da MAIOR delas,
           então palavra longa empurra o título e quebra a linha. */
        const palavras = ['esquecer', 'se perder', 'travar', 'não render'];
        let idx = 0, pos = palavras[0].length, apagando = false;

        // Reserva a largura da maior palavra para o título não mudar de
        // tamanho (e quebrar linhas diferentes) enquanto digita/apaga.
        const rotator = alvo.closest('.hero__rotator');
        const titulo = alvo.closest('.hero__title');
        function reservarLargura() {
            const medidor = document.createElement('span');
            medidor.style.cssText = 'position:absolute;visibility:hidden;white-space:nowrap;';
            titulo.appendChild(medidor);
            let maior = 0;
            palavras.forEach(p => {
                medidor.textContent = p;
                maior = Math.max(maior, medidor.offsetWidth);
            });
            medidor.remove();
            rotator.style.minWidth = Math.ceil(maior) + 12 + 'px';   // +12: espaço do cursor
        }
        reservarLargura();
        if (document.fonts && document.fonts.ready) document.fonts.ready.then(reservarLargura);
        window.addEventListener('resize', reservarLargura);

        function passo() {
            const palavra = palavras[idx];
            if (apagando) {
                pos--;
                alvo.textContent = palavra.slice(0, pos);
                if (pos === 0) {
                    apagando = false;
                    idx = (idx + 1) % palavras.length;
                }
                setTimeout(passo, 45);
            } else {
                pos++;
                alvo.textContent = palavras[idx].slice(0, pos);
                if (pos >= palavras[idx].length) {
                    apagando = true;
                    setTimeout(passo, 2200);   // segura a palavra completa
                } else {
                    setTimeout(passo, 95);
                }
            }
        }
        setTimeout(() => { apagando = true; passo(); }, 2600);
    })();

    /* ────────────────────────────────
       Contadores animados (hero stats)
       ──────────────────────────────── */
    (function () {
        const els = document.querySelectorAll('[data-count]');
        if (!els.length) return;

        function animar(el) {
            const alvo = parseInt(el.dataset.count, 10);
            if (reduzMovimento) { el.textContent = alvo; return; }
            const duracao = 1400;
            let inicio = null;
            function quadro(t) {
                if (!inicio) inicio = t;
                const p = Math.min((t - inicio) / duracao, 1);
                const suave = 1 - Math.pow(1 - p, 3);   // easeOutCubic
                el.textContent = Math.round(alvo * suave);
                if (p < 1) requestAnimationFrame(quadro);
            }
            requestAnimationFrame(quadro);
        }

        const obs = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animar(entry.target);
                    obs.unobserve(entry.target);
                }
            });
        }, { threshold: 0.6 });
        els.forEach(el => obs.observe(el));
    })();

    /* ────────────────────────────────
       Demo: FlashCards — o mesmo fluxo da tela "Revisar" do
       dashboard: vira o cartão, responde "Ainda não"/"Já sei" e
       vai para o próximo.
       ──────────────────────────────── */
    (function () {
        const card = document.getElementById('flashcard');
        if (!card) return;

        const frente = document.getElementById('fcFront');
        const verso = document.getElementById('fcBack');
        const avaliacao = document.getElementById('fcAvaliacao');
        const contador = document.getElementById('fcContador');
        const barra = document.getElementById('fcBarra');

        const CARDS = [
            { q: 'O que é fotossíntese?', a: 'Processo em que as plantas convertem luz solar em energia, liberando oxigênio.' },
            { q: 'Quanto é 7 × 8?', a: '56 — tabuada do 7: 7, 14, 21, 28, 35, 42, 49, 56.' },
            { q: 'Em que ano os portugueses chegaram ao Brasil?', a: 'Em 1500, com a esquadra de Pedro Álvares Cabral.' },
            { q: 'Qual é a fórmula química da água?', a: 'H₂O — dois átomos de hidrogênio e um de oxigênio.' },
        ];
        let atual = 0;

        function mostrarAvaliacao(sim) {
            avaliacao.classList.toggle('fc-avaliacao--visivel', sim);
            avaliacao.setAttribute('aria-hidden', sim ? 'false' : 'true');
            avaliacao.querySelectorAll('button').forEach(b => { b.tabIndex = sim ? 0 : -1; });
        }

        function pintarProgresso() {
            contador.textContent = (atual + 1) + ' de ' + CARDS.length;
            barra.style.width = ((atual + 1) / CARDS.length * 100) + '%';
        }

        function virar() {
            card.classList.toggle('flipped');
            // como no dashboard: só dá para se avaliar depois de ver a resposta
            if (card.classList.contains('flipped')) mostrarAvaliacao(true);
        }
        card.addEventListener('click', virar);
        card.addEventListener('keydown', e => {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); virar(); }
        });

        // "Ainda não" ou "Já sei": na prévia, os dois levam ao próximo cartão
        avaliacao.querySelectorAll('[data-fc-resposta]').forEach(btn => {
            btn.addEventListener('click', () => {
                atual = (atual + 1) % CARDS.length;
                mostrarAvaliacao(false);
                card.classList.remove('flipped');
                setTimeout(() => {                 // troca no meio do giro de volta
                    frente.textContent = CARDS[atual].q;
                    verso.textContent = CARDS[atual].a;
                    pintarProgresso();
                }, 320);
            });
        });

        pintarProgresso();
    })();

    /* ────────────────────────────────
       Demo: Pomodoro acelerado (1s = 1min)
       ──────────────────────────────── */
    (function () {
        const tempo = document.getElementById('pomoTime');
        if (!tempo) return;

        const caixa = document.getElementById('pomoDemo');
        const anel = document.getElementById('pomoRing');
        const estado = document.getElementById('pomoState');
        const btnToggle = document.getElementById('pomoToggle');
        const btnReset = document.getElementById('pomoReset');
        const elCiclos = document.getElementById('pomoCiclos');
        const modos = caixa.querySelectorAll('.pomo-modo');

        // os mesmos textos da aba Pomodoro do dashboard
        const PARADO = { foco: 'Hora de focar', curta: 'Hora de descansar', longa: 'Hora de descansar' };
        const FIM    = { foco: 'Ciclo concluído!', curta: 'Pausa encerrada!', longa: 'Pausa encerrada!' };

        let modo = 'foco';
        let total = 25 * 60;
        let restante = total;
        let intervalo = null;
        let ciclos = 0;

        function pintar() {
            const m = Math.floor(restante / 60);
            const s = restante % 60;
            tempo.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
            anel.style.strokeDashoffset = 100 - (restante / total) * 100;
        }

        function parar() {
            clearInterval(intervalo);
            intervalo = null;
            btnToggle.textContent = restante === total || restante === 0 ? 'Iniciar' : 'Continuar';
        }

        function tique() {
            restante = Math.max(0, restante - 60);   // acelerado: 1s real = 1min
            pintar();
            if (restante === 0) {
                parar();
                estado.textContent = FIM[modo];
                if (modo === 'foco') elCiclos.textContent = String(++ciclos);
            }
        }

        function zerar() {
            clearInterval(intervalo);
            intervalo = null;
            restante = total;
            estado.textContent = PARADO[modo];
            btnToggle.textContent = 'Iniciar';
            pintar();
        }

        btnToggle.addEventListener('click', () => {
            if (intervalo) {
                parar();
                estado.textContent = 'Pausado';
                return;
            }
            if (restante === 0) { restante = total; pintar(); }
            estado.textContent = PARADO[modo];
            btnToggle.textContent = 'Pausar';
            intervalo = setInterval(tique, 1000);
        });

        btnReset.addEventListener('click', zerar);

        // Foco / Pausa curta / Pausa longa — trocar de modo recomeça o timer
        modos.forEach(btn => {
            btn.addEventListener('click', () => {
                modos.forEach(b => b.classList.toggle('active', b === btn));
                modo = btn.dataset.modo;
                total = (+btn.dataset.min || 25) * 60;
                caixa.classList.toggle('pomo--pausa', modo !== 'foco');
                zerar();
            });
        });

        pintar();
    })();

    /* ────────────────────────────────
       Demo: Quiz estilo "Exercícios com IA"
       ──────────────────────────────── */
    (function () {
        const opcoesWrap = document.getElementById('quizOpcoes');
        if (!opcoesWrap) return;

        const materiaEl = document.getElementById('quizMateria');
        const progressoEl = document.getElementById('quizProgresso');
        const perguntaEl = document.getElementById('quizPergunta');
        const feedbackEl = document.getElementById('quizFeedback');
        const btnNext = document.getElementById('quizNext');
        const btnDica = document.getElementById('quizDicaBtn');
        const rotuloDica = document.getElementById('quizDicaRotulo');
        const dicaEl = document.getElementById('quizDica');

        btnDica.addEventListener('click', () => {
            const aberta = dicaEl.classList.toggle('aberta');
            btnDica.setAttribute('aria-expanded', aberta ? 'true' : 'false');
            rotuloDica.textContent = aberta ? 'Esconder dica' : 'Ver dica';
        });

        const QUIZ = [
            {
                materia: 'Matemática',
                pergunta: 'Qual é o valor de x em 2x + 6 = 14?',
                opcoes: ['x = 3', 'x = 4', 'x = 5', 'x = 8'],
                correta: 1,
                dica: '2x = 14 − 6 = 8, logo x = 4.'
            },
            {
                materia: 'Ciências',
                pergunta: 'Qual é o planeta mais próximo do Sol?',
                opcoes: ['Vênus', 'Terra', 'Mercúrio', 'Marte'],
                correta: 2,
                dica: 'Mercúrio é o primeiro planeta do Sistema Solar.'
            },
            {
                materia: 'Português',
                pergunta: 'Qual palavra está escrita corretamente?',
                opcoes: ['Excessão', 'Exceção', 'Esceção', 'Excesão'],
                correta: 1,
                dica: '"Exceção" se escreve com c e ç.'
            },
        ];
        let atual = 0;

        const letra = i => String.fromCharCode(65 + i);   // 0 → A

        function render() {
            const q = QUIZ[atual];
            materiaEl.textContent = q.materia;
            progressoEl.textContent = 'Questão ' + (atual + 1) + ' de ' + QUIZ.length;
            perguntaEl.textContent = q.pergunta;
            dicaEl.textContent = q.dica;
            dicaEl.classList.remove('aberta');
            btnDica.setAttribute('aria-expanded', 'false');
            rotuloDica.textContent = 'Ver dica';
            feedbackEl.textContent = '';
            feedbackEl.className = 'quiz__feedback';
            btnNext.hidden = true;
            opcoesWrap.innerHTML = '';

            q.opcoes.forEach((texto, i) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'quiz__opcao';
                const l = document.createElement('span');
                l.className = 'quiz__letra';
                l.textContent = letra(i);
                const t = document.createElement('span');
                t.textContent = texto;
                btn.append(l, t);
                btn.addEventListener('click', () => responder(i, btn));
                opcoesWrap.appendChild(btn);
            });
        }

        // mesmas mensagens do "Praticar" do dashboard
        function responder(i, btnClicado) {
            const q = QUIZ[atual];
            const botoes = opcoesWrap.querySelectorAll('.quiz__opcao');
            botoes.forEach(b => { b.disabled = true; });
            botoes[q.correta].classList.add('quiz__opcao--correta');

            if (i === q.correta) {
                feedbackEl.textContent = 'Você acertou!';
                feedbackEl.classList.add('quiz__feedback--ok');
            } else {
                btnClicado.classList.add('quiz__opcao--errada');
                feedbackEl.textContent = 'Você errou. A resposta certa era a ' + letra(q.correta) + '.';
                feedbackEl.classList.add('quiz__feedback--erro');
            }
            btnNext.textContent = atual === QUIZ.length - 1 ? 'Recomeçar' : 'Próxima questão';
            btnNext.hidden = false;
        }

        btnNext.addEventListener('click', () => {
            atual = (atual + 1) % QUIZ.length;
            render();
        });

        render();
    })();

})();

/* ============================================================
   CARROSSEIS — "Por que nos escolher?" e "Experimente agora mesmo"
   Antes as duas seções prendiam a rolagem e trocavam os cards /
   as demonstrações sozinhas. Agora quem troca é a pessoa, pelas
   setas nas laterais. Roda em qualquer tela e também com "reduzir
   movimento" (aí só sem a rolagem suave).
   ============================================================ */
(function () {
    'use strict';

    const semMovimento = window.matchMedia('(prefers-reduced-motion: reduce)').matches
        || document.documentElement.classList.contains('kosmos-lite');   // visual leve
    const MOLA = 'cubic-bezier(.2, .7, .2, 1)';

    /* Clique numa seta: um anel roxo pulsa em volta do botão. */
    function pulsar(botao) {
        if (semMovimento || !botao.animate) return;
        botao.animate([
            { boxShadow: '0 0 0 0 rgba(165, 65, 255, .55)' },
            { boxShadow: '0 0 0 12px rgba(165, 65, 255, 0)' },
        ], { duration: 450, easing: 'ease-out' });
    }

    /* ── Recursos: a esteira anda um card por clique ── */
    const esteira = document.getElementById('recursosEsteira');
    const track = document.getElementById('recursosTrack');
    const recAnt = document.getElementById('recursosAnt');
    const recProx = document.getElementById('recursosProx');
    const recBarra = document.getElementById('recursosBarra');

    if (esteira && track && recAnt && recProx) {
        const passo = () => {
            const card = track.querySelector('.card');
            return card ? card.offsetWidth + 24 : esteira.clientWidth * 0.8;   // 24 = gap
        };
        /* Navegou pela seta: os cards que CHEGAM na tela entram
           deslizando um pouco no sentido da navegação, com um leve
           crescimento, um depois do outro (quem marca a chegada é o
           IntersectionObserver lá embaixo). */
        let navegacao = null;          // { lado, ate } — vale só logo depois do clique
        let atrasoChegada = 0;
        function chegar(card) {
            if (semMovimento || !navegacao || Date.now() > navegacao.ate || !card.animate) return;
            card.animate([
                { opacity: .3, transform: 'translateX(' + (28 * navegacao.lado) + 'px) scale(.95)' },
                { opacity: 1, transform: 'translateX(0) scale(1)' },
            ], { duration: 420, easing: MOLA, delay: atrasoChegada, fill: 'backwards' });
            atrasoChegada += 70;
        }

        const andar = (lado, botao) => {
            pulsar(botao);
            navegacao = { lado: lado, ate: Date.now() + 900 };
            atrasoChegada = 0;
            esteira.scrollBy({ left: lado * passo(), behavior: semMovimento ? 'auto' : 'smooth' });
        };

        recAnt.addEventListener('click', () => andar(-1, recAnt));
        recProx.addEventListener('click', () => andar(1, recProx));

        function atualizar() {
            const max = esteira.scrollWidth - esteira.clientWidth;
            recAnt.disabled = esteira.scrollLeft <= 2;
            recProx.disabled = esteira.scrollLeft >= max - 2;
            if (recBarra) {
                // a barra mostra o trecho visível: começa no tamanho da
                // tela e chega a 100% no último card
                const fim = (esteira.scrollLeft + esteira.clientWidth) / esteira.scrollWidth;
                recBarra.style.width = (Math.min(1, fim) * 100).toFixed(2) + '%';
            }
        }
        esteira.addEventListener('scroll', atualizar, { passive: true });
        window.addEventListener('resize', atualizar);
        atualizar();

        /* Foco no que está à vista: o card cortado na borda fica meio
           apagado e acende quando entra inteiro (o CSS faz o fade). */
        if ('IntersectionObserver' in window) {
            const foco = new IntersectionObserver((entradas) => {
                entradas.forEach(e => {
                    const estavaNaBorda = e.target.classList.contains('card--borda');
                    const naBorda = e.intersectionRatio < 0.85;
                    e.target.classList.toggle('card--borda', naBorda);
                    // passou a aparecer inteiro por causa de uma seta: anima a chegada
                    if (estavaNaBorda && !naBorda) chegar(e.target);
                });
            }, { root: esteira, threshold: [0, 0.85, 1] });
            track.querySelectorAll('.card').forEach(c => foco.observe(c));
        }
    }

    /* ── Experimente: abas 1-2-3 + setas nas laterais ── */
    const passos = Array.from(document.querySelectorAll('.demo__passo'));
    const paineis = Array.from(document.querySelectorAll('.demo__stage .demo__panel'));
    const trilho = document.getElementById('demoTrilho');
    const demoAnt = document.getElementById('demoAnt');
    const demoProx = document.getElementById('demoProx');

    if (paineis.length) {
        let atual = Math.max(0, paineis.findIndex(p => p.classList.contains('demo__panel--ativa')));

        /* Transição simples: a demonstração que sai desliza um pouco
           para um lado e some; a que entra vem do outro lado. `lado`
           é +1 indo para a próxima e -1 voltando. */
        function deslizar(sai, entra, lado) {
            if (semMovimento || !entra.animate || sai === entra) return;
            sai.animate([
                { opacity: 1, transform: 'translateX(0) scale(1)' },
                { opacity: 0, transform: 'translateX(' + (-40 * lado) + 'px) scale(.97)' },
            ], { duration: 260, easing: 'ease-in' });
            entra.animate([
                { opacity: 0, transform: 'translateX(' + (40 * lado) + 'px) scale(.97)' },
                { opacity: 1, transform: 'translateX(0) scale(1)' },
            ], { duration: 420, easing: MOLA });
        }

        /* o número do passo que acabou de ficar ativo dá um pulinho */
        function pularNumero(passo) {
            const num = passo && passo.querySelector('.demo__passo-num');
            if (semMovimento || !num || !num.animate) return;
            num.animate([
                { transform: 'scale(1)' },
                { transform: 'scale(1.22)' },
                { transform: 'scale(1)' },
            ], { duration: 360, easing: 'ease-out' });
        }

        function ativar(idx, lado) {
            const antes = paineis[atual];
            const novo = (idx + paineis.length) % paineis.length;   // dá a volta nas pontas
            if (lado === undefined) lado = novo >= atual ? 1 : -1;
            const mudou = novo !== atual;
            atual = novo;
            deslizar(antes, paineis[atual], lado);
            if (mudou) pularNumero(passos[atual]);
            paineis.forEach((p, i) => {
                const ativo = i === atual;
                p.classList.toggle('demo__panel--ativa', ativo);
                p.setAttribute('aria-hidden', ativo ? 'false' : 'true');
                p.inert = !ativo;                                    // tab não entra no painel escondido
            });
            passos.forEach((p, i) => {
                const ativo = i === atual;
                p.classList.toggle('demo__passo--ativo', ativo);
                p.setAttribute('aria-selected', ativo ? 'true' : 'false');
            });
            if (trilho) trilho.style.height = ((atual + 1) / paineis.length * 100).toFixed(2) + '%';
        }

        // os passos à esquerda viram abas clicáveis (e de teclado)
        passos.forEach((p, i) => {
            p.setAttribute('role', 'tab');
            p.tabIndex = 0;
            p.addEventListener('click', () => ativar(i));
            p.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); ativar(i); }
            });
        });
        if (demoAnt) demoAnt.addEventListener('click', () => { pulsar(demoAnt); ativar(atual - 1, -1); });
        if (demoProx) demoProx.addEventListener('click', () => { pulsar(demoProx); ativar(atual + 1, 1); });

        ativar(atual);
    }
})();

/* ============================================================
   KOSMOS — V3 SCROLLYTELLING (estilo Elementor)
   Engine de scroll: pinned sections (recursos em esteira
   horizontal + demo em passos), parallax sutil e títulos
   com split de palavras. Desliga em prefers-reduced-motion;
   pins só em desktop (≥1025px) — mobile mantém o layout normal.
   ============================================================ */
(function () {
    'use strict';

    const reduzMovimento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduzMovimento) return;   // sem scrollytelling — tudo fica no layout padrão
    // visual leve (escolhido no dashboard): também sem — seções comuns, sem parallax
    if (document.documentElement.classList.contains('kosmos-lite')) return;

    document.documentElement.classList.add('scrolly');   // liga o CSS do V3

    const mqDesktop = window.matchMedia('(min-width: 1025px)');
    const clamp = (v, a, b) => Math.min(b, Math.max(a, v));

    /* ── Split de palavras nos títulos de seção ── */
    const obsTitulos = new IntersectionObserver((entries) => {
        entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('visible'); });
    }, { threshold: 0.35 });

    document.querySelectorAll('.section-title').forEach(titulo => {
        const walker = document.createTreeWalker(titulo, NodeFilter.SHOW_TEXT);
        const textos = [];
        while (walker.nextNode()) textos.push(walker.currentNode);
        let wi = 0;
        textos.forEach(no => {
            const partes = no.textContent.split(/(\s+)/);
            const frag = document.createDocumentFragment();
            partes.forEach(p => {
                if (!p) return;
                if (/^\s+$/.test(p)) { frag.appendChild(document.createTextNode(p)); return; }
                const s = document.createElement('span');
                s.className = 'palavra';
                s.style.setProperty('--wi', wi++);
                s.textContent = p;
                frag.appendChild(s);
            });
            no.parentNode.replaceChild(frag, no);
        });
        obsTitulos.observe(titulo);
    });

    /* ── Utilitários de progresso ── */
    // 0..1 conforme a seção pinada é "consumida" pelo scroll
    function progressoPin(el) {
        const r = el.getBoundingClientRect();
        const total = el.offsetHeight - window.innerHeight;
        return total > 0 ? clamp(-r.top / total, 0, 1) : 0;
    }
    // -1..1 conforme o elemento cruza o centro da viewport
    function posViewport(el) {
        const r = el.getBoundingClientRect();
        const meio = r.top + r.height / 2 - window.innerHeight / 2;
        return clamp(meio / ((window.innerHeight + r.height) / 2), -1, 1);
    }

    const tarefas = [];

    /* ── Hero: saída em camadas ── */
    const hero = document.querySelector('.hero');
    if (hero) {
        tarefas.push(() => {
            const p = clamp(window.scrollY / (hero.offsetHeight * 0.85), 0, 1);
            hero.style.setProperty('--hp', p.toFixed(4));
        });
    }

    /* ── Parallax sutil (viewport-centrado) ── */
    [
        ['.sobre__img', '.sobre'],
        ['.historia__img', '.historia'],
        ['.demo__orb', '.demo__vista'],
        ['.cta-final__orb', '.cta-final'],
        ['.marquee', '.marquee'],
    ].forEach(([alvoSel, refSel]) => {
        const alvo = document.querySelector(alvoSel);
        const ref = document.querySelector(refSel);
        if (!alvo || !ref) return;
        tarefas.push(() => {
            alvo.style.setProperty('--pv', posViewport(ref).toFixed(4));
        });
    });

    /* RECURSOS, SOBRE, NOSSA HISTÓRIA e DEMO não prendem mais a
       rolagem: "Por que nos escolher" e "Experimente" viraram
       carrosséis com setas (bloco CARROSSEIS, logo acima deste
       arquivo), e Sobre/História entram só com o .reveal leve. */

    /* ── ORION: a apresentação dirigida pela rolagem ──
       A seção fica pinada por ~3,6 telas e o progresso escolhe qual
       passo está em foco. Quem EXECUTA o comportamento é o
       js/orion.js: aqui só avisamos "agora é o passo 2", pela mesma
       função que o clique usa. Assim rolar e clicar levam ao mesmo
       lugar, e não existem dois caminhos para manter iguais. */
    const orionPin = document.getElementById('orionPin');
    const orionPassos = Array.from(document.querySelectorAll('.orion-passo'));
    const orionTrilho = document.getElementById('orionTrilho');
    const orionCorpoEl = document.getElementById('orionCorpo');

    if (orionPin && orionPassos.length) {
        let orionAtual = -1;
        let orionUltimoY = window.scrollY;
        let orionVisto = false;
        let orionPercorrida = false;   // a pessoa rolou DENTRO da seção (e não pulou por cima dela)
        const orionSecao = orionPin.closest('.orion');

        /* A visita guiada acontece UMA vez por visita ao site: passou do
           fim da seção, ela acaba e só volta quando a pessoa fechar a aba
           e abrir o site de novo (sessionStorage vive até a aba fechar). */
        const CHAVE_ORION_VISTO = 'kosmos:orion-visita-feita';
        try {
            if (sessionStorage.getItem(CHAVE_ORION_VISTO) === '1') {
                orionVisto = true;
                orionSecao.classList.add('orion--visto');
                if (orionTrilho) orionTrilho.style.width = '100%';   // a visita já foi completa
            }
        } catch (e) { /* modo privado: vale só para esta página */ }

        /* Passou pela seção inteira uma vez: ela para de prender a
           rolagem. O pin (520vh) encolhe para uma tela só e a posição
           da rolagem é compensada NO MESMO quadro — o que está na tela
           não se mexe, e voltar para cima passa direto pela seção. */
        function soltarOrion() {
            orionVisto = true;
            // lembra até a aba ser fechada: recarregar não traz a visita de volta
            try { sessionStorage.setItem(CHAVE_ORION_VISTO, '1'); } catch (e) { /* modo privado */ }

            // rolagem rápida: quanto a pessoa já passou do fim do pin
            const r = orionPin.getBoundingClientRect();
            const passou = Math.max(0, -r.top - (orionPin.offsetHeight - window.innerHeight));
            // o palco gruda abaixo do cabeçalho: na tela ele está a `colado` px do topo
            const colado = parseFloat(getComputedStyle(orionVista).top) || 0;

            orionSecao.classList.add('orion--visto');

            /* No fim do pin a vista está colada no topo da tela. Solto,
               ela passa a começar no topo da seção — então a rolagem vai
               PARA LÁ, em posição absoluta. (Não dá para só "descontar a
               diferença": o navegador já reajusta sozinho quando algo
               acima encolhe, e somar os dois jogava a pessoa de volta
               ao começo da seção.) E sem animação: o html tem
               scroll-behavior: smooth, que transformaria o ajuste num
               salto visível. */
            const html = document.documentElement;
            const antes = html.style.scrollBehavior;
            html.style.scrollBehavior = 'auto';
            const topoVista = orionPin.getBoundingClientRect().top + window.scrollY;
            window.scrollTo(0, topoVista - colado + passou);
            html.style.scrollBehavior = antes;

            if (orionTrilho) orionTrilho.style.width = '100%';
        }

        /* Rede de segurança contra o corte: o palco do Orion tem a
           altura da tela e esconde o que passa dela. Se o conteúdo
           não couber (notebook baixo, zoom alto…), a seção deixa de
           prender a rolagem (.orion--sem-pin) e nada fica cortado.
           Mede de novo quando a janela muda e quando tudo carrega. */
        const orionVista = orionPin.querySelector('.orion__vista');
        const orionDicaEl = document.getElementById('orionConversaDica');
        const orionDicaPin = orionDicaEl ? orionDicaEl.textContent.trim() : '';
        let orionSemPin = false;
        function medirOrion() {
            if (!orionVista || orionVisto) return;
            const conteudo = orionVista.firstElementChild;
            if (!conteudo) return;

            /* Mede a versão APERTADA (a do pin), que é a que precisa caber
               numa tela: sem a classe, ela volta por um instante, mede e
               a classe é decidida em seguida — tudo no mesmo quadro. */
            orionSecao.classList.remove('orion--sem-pin');
            conteudo.style.zoom = '';
            // a área útil é a tela menos o espaço do cabeçalho fixo (padding-top do palco)
            const estilo = getComputedStyle(orionVista);
            const util = window.innerHeight - (parseFloat(estilo.top) || 0) - (parseFloat(estilo.paddingTop) || 0);
            // +28: o passo ativo abre a descrição (uma linha) e fica mais alto que os outros
            const precisa = conteudo.offsetHeight + 28;

            /* Não coube? Primeiro REDUZ proporcionalmente (zoom) só o que
               falta — a visita pela rolagem continua funcionando em
               notebook baixo. Só se precisasse encolher mais de 30% (tela
               baixíssima, zoom alto no navegador) a seção é solta. */
            let escala = 1;
            if (mqDesktop.matches && precisa > util) escala = util / precisa;
            const naoCabe = escala < 0.7;
            if (!naoCabe && escala < 1) conteudo.style.zoom = escala.toFixed(3);

            orionSecao.classList.toggle('orion--sem-pin', naoCabe);
            orionSemPin = naoCabe;

            // solta, a rolagem não conduz a visita: a dica fala só do toque
            if (orionDicaEl) {
                orionDicaEl.textContent = naoCabe ? 'Toque num passo para o Orion explicar:' : orionDicaPin;
            }
        }
        medirOrion();
        window.addEventListener('load', medirOrion);

        /* As fontes do site (Syne / DM Sans, do Google Fonts) chegam DEPOIS
           da primeira medida. Medida com a fonte provisória, a seção parecia
           maior do que é e ganhava um zoom à toa — numa aba nova ela abria
           pequena, e só um Ctrl+Shift+R (fontes já em cache) acertava.
           Então: mede de novo a cada fonte que termina de carregar... */
        if (document.fonts) {
            if (document.fonts.ready) document.fonts.ready.then(medirOrion);
            if (document.fonts.addEventListener) document.fonts.addEventListener('loadingdone', medirOrion);
        }
        /* ...e mais uma vez quando a pessoa está chegando na seção (uma
           tela antes), quando tudo já carregou de verdade. */
        if ('IntersectionObserver' in window) {
            new IntersectionObserver((entradas) => {
                if (entradas.some(e => e.isIntersecting)) medirOrion();
            }, { rootMargin: '100% 0px' }).observe(orionSecao);
        }
        let orionResize = null;
        window.addEventListener('resize', () => {
            clearTimeout(orionResize);
            orionResize = setTimeout(medirOrion, 150);
        });

        tarefas.push(() => {
            if (!mqDesktop.matches || orionVisto || orionSemPin) return;

            /* Enquanto a pessoa ainda não passou da seção, os passos seguem
               a rolagem nos DOIS sentidos (sair por cima e voltar funciona).
               Só o "soltar" no fim exige estar descendo. */
            const descendo = window.scrollY >= orionUltimoY;
            orionUltimoY = window.scrollY;

            const p = progressoPin(orionPin);

            /* O primeiro trecho é só respiro: a pessoa acabou de chegar
               e ainda está lendo o título. O roteiro começa depois. */
            const inicio = 0.12;
            const util = Math.max(0, (p - inicio) / (1 - inicio));
            const idx = Math.min(orionPassos.length - 1,
                                 Math.floor(util * orionPassos.length));

            if (idx !== orionAtual && p > inicio) {
                orionAtual = idx;
                // a mesma porta que o clique usa (exposta por js/orion.js)
                if (window.KosmosOrion) {
                    window.KosmosOrion.mostrar(orionPassos[idx].dataset.passo);
                }
            }

            if (orionTrilho) orionTrilho.style.width = (p * 100).toFixed(2) + '%';
            if (orionCorpoEl) {
                // sobe no começo e volta: dá a sensação de aproximação
                orionCorpoEl.style.setProperty('--op', Math.sin(p * Math.PI).toFixed(4));
            }

            // chegou ao fim da seção descendo: solta o pin de vez
            /* Conta como percorrida só quem rolou POR DENTRO da seção. Um
               salto direto para baixo dela (o navegador restaurando a
               rolagem ao recarregar, por exemplo) não encerra a visita. */
            if (p > 0.05 && p < 0.95) orionPercorrida = true;

            if (descendo && orionPercorrida && p >= 0.999) soltarOrion();
        });

        /* Clicar num passo rola até o trecho dele, como nos passos do
           demo: quem prefere clicar não fica preso à rolagem. */
        orionPassos.forEach((passo, i) => {
            passo.addEventListener('click', () => {
                if (!mqDesktop.matches || orionVisto || orionSemPin) return;   // solto: o passo aparece (orion.js) sem rolar
                const topo = orionPin.getBoundingClientRect().top + window.scrollY;
                const total = orionPin.offsetHeight - window.innerHeight;
                const inicio = 0.12;
                const frac = inicio + (1 - inicio) * ((i + 0.5) / orionPassos.length);
                window.scrollTo({ top: topo + total * frac, behavior: 'smooth' });
            });
        });
    }

    /* ── Loop contínuo (rAF): recalcula só quando o scroll/viewport
       mudou — mais confiável que escutar o evento de scroll ── */
    let ultimoY = -1, ultimoW = -1, ultimoH = -1;
    function loop() {
        if (window.scrollY !== ultimoY || window.innerWidth !== ultimoW || window.innerHeight !== ultimoH) {
            ultimoY = window.scrollY;
            ultimoW = window.innerWidth;
            ultimoH = window.innerHeight;
            tarefas.forEach(fn => fn());
        }
        requestAnimationFrame(loop);
    }
    requestAnimationFrame(loop);
})();
