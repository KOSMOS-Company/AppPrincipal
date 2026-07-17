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
    if (ponteiroFino && !reduzMovimento) {
        document.querySelectorAll('.card').forEach(c => aplicarTilt(c, 8, true));
        document.querySelectorAll('.tilt').forEach(t => aplicarTilt(t, 5, false));
    }

    /* ────────────────────────────────
       Hero: palavra rotativa (máquina de escrever)
       ──────────────────────────────── */
    (function () {
        const alvo = document.getElementById('rotatorWord');
        if (!alvo || reduzMovimento) return;

        const palavras = ['estudos', 'resumos', 'flashcards', 'horários', 'exercícios'];
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
       Demo: FlashCard (flip + próximo)
       ──────────────────────────────── */
    (function () {
        const card = document.getElementById('flashcard');
        if (!card) return;

        const frente = document.getElementById('fcFront');
        const verso = document.getElementById('fcBack');
        const btnNext = document.getElementById('fcNext');

        const CARDS = [
            { q: 'O que é fotossíntese?', a: 'Processo em que as plantas convertem luz solar em energia, liberando oxigênio.' },
            { q: 'Quanto é 7 × 8?', a: '56 — tabuada do 7: 7, 14, 21, 28, 35, 42, 49, 56.' },
            { q: 'Em que ano os portugueses chegaram ao Brasil?', a: 'Em 1500, com a esquadra de Pedro Álvares Cabral.' },
            { q: 'Qual é a fórmula química da água?', a: 'H₂O — dois átomos de hidrogênio e um de oxigênio.' },
        ];
        let atual = 0;

        function virar() { card.classList.toggle('flipped'); }
        card.addEventListener('click', virar);
        card.addEventListener('keydown', e => {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); virar(); }
        });

        btnNext.addEventListener('click', () => {
            atual = (atual + 1) % CARDS.length;
            const trocar = () => {
                frente.textContent = CARDS[atual].q;
                verso.textContent = CARDS[atual].a;
            };
            if (card.classList.contains('flipped')) {
                card.classList.remove('flipped');
                setTimeout(trocar, 320);   // troca no meio do giro de volta
            } else {
                trocar();
            }
        });
    })();

    /* ────────────────────────────────
       Demo: Pomodoro acelerado (1s = 1min)
       ──────────────────────────────── */
    (function () {
        const tempo = document.getElementById('pomoTime');
        if (!tempo) return;

        const anel = document.getElementById('pomoRing');
        const estado = document.getElementById('pomoState');
        const btnToggle = document.getElementById('pomoToggle');
        const btnReset = document.getElementById('pomoReset');

        const TOTAL = 25 * 60;   // 25 "minutos" exibidos
        let restante = TOTAL;
        let intervalo = null;

        function pintar() {
            const m = Math.floor(restante / 60);
            const s = restante % 60;
            tempo.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
            anel.style.strokeDashoffset = 100 - (restante / TOTAL) * 100;
        }

        function parar() {
            clearInterval(intervalo);
            intervalo = null;
            btnToggle.textContent = restante === TOTAL ? 'Iniciar' : 'Continuar';
        }

        function tique() {
            restante = Math.max(0, restante - 60);   // acelerado: 1s real = 1min
            pintar();
            if (restante === 0) {
                parar();
                btnToggle.textContent = 'Iniciar';
                estado.textContent = 'concluído!';
            }
        }

        btnToggle.addEventListener('click', () => {
            if (intervalo) {
                parar();
                estado.textContent = 'pausado';
                return;
            }
            if (restante === 0) { restante = TOTAL; pintar(); }
            estado.textContent = 'foco';
            btnToggle.textContent = 'Pausar';
            intervalo = setInterval(tique, 1000);
        });

        btnReset.addEventListener('click', () => {
            clearInterval(intervalo);
            intervalo = null;
            restante = TOTAL;
            estado.textContent = 'foco';
            btnToggle.textContent = 'Iniciar';
            pintar();
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
        const perguntaEl = document.getElementById('quizPergunta');
        const feedbackEl = document.getElementById('quizFeedback');
        const btnNext = document.getElementById('quizNext');

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

        function render() {
            const q = QUIZ[atual];
            materiaEl.textContent = q.materia;
            perguntaEl.textContent = q.pergunta;
            feedbackEl.textContent = '';
            feedbackEl.className = 'quiz__feedback';
            btnNext.disabled = true;
            opcoesWrap.innerHTML = '';

            q.opcoes.forEach((texto, i) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'quiz__opcao';
                btn.textContent = texto;
                btn.addEventListener('click', () => responder(i, btn));
                opcoesWrap.appendChild(btn);
            });
        }

        function responder(i, btnClicado) {
            const q = QUIZ[atual];
            const botoes = opcoesWrap.querySelectorAll('.quiz__opcao');
            botoes.forEach(b => { b.disabled = true; });
            botoes[q.correta].classList.add('quiz__opcao--correta');

            if (i === q.correta) {
                feedbackEl.textContent = 'Acertou! ' + q.dica;
                feedbackEl.classList.add('quiz__feedback--ok');
            } else {
                btnClicado.classList.add('quiz__opcao--errada');
                feedbackEl.textContent = 'Quase! ' + q.dica;
                feedbackEl.classList.add('quiz__feedback--erro');
            }
            btnNext.disabled = false;
        }

        btnNext.addEventListener('click', () => {
            atual = (atual + 1) % QUIZ.length;
            render();
        });

        render();
    })();

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

    /* ── RECURSOS: esteira horizontal pinada ── */
    const recPin = document.getElementById('recursosPin');
    const recTrack = document.getElementById('recursosTrack');
    const recBarra = document.getElementById('recursosBarra');
    const recGhost = document.querySelector('.recursos .ghost');
    if (recPin && recTrack) {
        const vista = recPin.querySelector('.recursos__vista');
        tarefas.push(() => {
            if (!mqDesktop.matches) {
                recTrack.style.transform = '';
                return;
            }
            const p = progressoPin(recPin);
            const desloc = Math.max(0, recTrack.scrollWidth - vista.clientWidth);
            recTrack.style.transform = 'translate3d(' + (-p * desloc).toFixed(1) + 'px, 0, 0)';
            if (recBarra) recBarra.style.width = (p * 100).toFixed(2) + '%';
            if (recGhost) recGhost.style.setProperty('--gp', p.toFixed(4));
        });
    }

    /* ── DEMO: scrollytelling em 3 passos ── */
    const demoPin = document.getElementById('demoPin');
    const passos = Array.from(document.querySelectorAll('.demo__passo'));
    const paineis = Array.from(document.querySelectorAll('.demo__stage .demo__panel'));
    const trilho = document.getElementById('demoTrilho');
    const demoGhost = document.querySelector('.ghost--demo');
    if (demoPin && paineis.length === 3) {
        let ativoAtual = 0;
        function ativar(idx) {
            if (idx === ativoAtual) return;
            ativoAtual = idx;
            passos.forEach((p, i) => p.classList.toggle('demo__passo--ativo', i === idx));
            paineis.forEach((p, i) => p.classList.toggle('demo__panel--ativa', i === idx));
        }
        tarefas.push(() => {
            if (!mqDesktop.matches) return;
            const p = progressoPin(demoPin);
            ativar(p < 1 / 3 ? 0 : p < 2 / 3 ? 1 : 2);
            if (trilho) trilho.style.height = (p * 100).toFixed(2) + '%';
            if (demoGhost) demoGhost.style.setProperty('--gp', p.toFixed(4));
        });

        // clicar num passo rola até o trecho correspondente do pin
        passos.forEach((passo, i) => {
            passo.addEventListener('click', () => {
                const topo = demoPin.getBoundingClientRect().top + window.scrollY;
                const total = demoPin.offsetHeight - window.innerHeight;
                const alvo = topo + total * ((i + 0.5) / 3);
                window.scrollTo({ top: alvo, behavior: 'smooth' });
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
