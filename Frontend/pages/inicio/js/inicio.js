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
       Botões magnéticos (seguem levemente o cursor)
       ──────────────────────────────── */
    if (ponteiroFino && !reduzMovimento) {
        document.querySelectorAll('.btn--magnetic').forEach(btn => {
            btn.addEventListener('mousemove', e => {
                const r = btn.getBoundingClientRect();
                const x = (e.clientX - r.left - r.width / 2) * 0.22;
                const y = (e.clientY - r.top - r.height / 2) * 0.32;
                btn.style.transform = 'translate(' + x + 'px, ' + y + 'px)';
            });
            btn.addEventListener('mouseleave', () => { btn.style.transform = ''; });
        });
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
                estado.textContent = 'concluído! 🎉';
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
