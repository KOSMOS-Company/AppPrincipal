/* ============================================================
   KOSMOS — constelacao.js
   A seção de ferramentas: cada ferramenta é uma estrela, e a
   estrela acesa mostra a ferramenta funcionando em miniatura.

   Três jeitos de acender uma estrela, todos pela mesma função
   (ativar):
     • passar o mouse, tocar ou clicar;
     • o teclado: as estrelas são abas (role="tab"), as setas trocam;
     • a rolagem, só no modo pinado (computador, com movimento): o
       progresso da seção desenha a linha e acende uma por vez.

   No modo pinado o mouse e a rolagem convivem assim: passar o mouse
   acende a estrela na hora, e a rolagem só volta a mandar quando
   chega ao PRÓXIMO trecho. Clicar leva a rolagem até a estrela, e
   enquanto ela viaja as estrelas do caminho não piscam.

   Sem o modo pinado (celular, "reduzir movimento", visual Lite) a
   linha se desenha de uma vez quando a seção aparece.
   ============================================================ */

(function () {
    'use strict';

    const secao = document.getElementById('constelacao');
    if (!secao) return;

    const pin = document.getElementById('constPin');
    const estrelas = Array.from(secao.querySelectorAll('.estrela'));
    const paineis = estrelas.map(e => document.getElementById(e.getAttribute('aria-controls')));
    const guia = document.getElementById('constGuia');
    const traco = document.getElementById('constTraco');
    const numero = document.getElementById('constNum');
    if (!estrelas.length || paineis.some(p => !p)) return;

    const reduzMovimento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const mqDesktop = window.matchMedia('(min-width: 1025px)');
    const clamp = (v, a, b) => Math.min(b, Math.max(a, v));

    // o inicio.js (carregado antes) liga html.scrolly quando há movimento
    const podePinar = () => document.documentElement.classList.contains('scrolly') && mqDesktop.matches;

    /* ------------------------------------------------------------
       A linha da constelação
       As posições vêm do próprio HTML (--x/--y, na escala do viewBox
       400 × 300), então mover uma estrela move a linha junto.
       O percurso passa pelas seis em ordem e fecha voltando à 3ª:
       é o desenho que faz parecer constelação, e não um zigue-zague.
       ------------------------------------------------------------ */
    const pontos = estrelas.map(e => ({
        x: parseFloat(e.style.getPropertyValue('--x')),
        y: parseFloat(e.style.getPropertyValue('--y'))
    }));
    const percurso = [0, 1, 2, 3, 4, 5, 2].map(i => pontos[i]);
    const pontosTxt = percurso.map(p => p.x + ',' + p.y).join(' ');
    guia.setAttribute('points', pontosTxt);
    traco.setAttribute('points', pontosTxt);

    const acumulado = [0];
    for (let i = 1; i < percurso.length; i++) {
        const dx = percurso[i].x - percurso[i - 1].x;
        const dy = percurso[i].y - percurso[i - 1].y;
        acumulado.push(acumulado[i - 1] + Math.hypot(dx, dy));
    }
    const total = acumulado[acumulado.length - 1];
    traco.style.strokeDasharray = total;
    traco.style.strokeDashoffset = total;

    function desenhar(comprimento) {
        traco.style.strokeDashoffset = (total - clamp(comprimento, 0, total)).toFixed(2);
    }

    /* ------------------------------------------------------------
       As miniaturas que precisam de JS (o resto é CSS puro, que só
       roda no painel aceso)
       ------------------------------------------------------------ */
    const miniaturas = {
        'painel-pomodoro': (function () {
            const tempo = document.getElementById('miniPomoTempo');
            const anel = document.getElementById('miniPomoAnel');
            const TOTAL = 25 * 60;     // segundos de mentira
            const ACELERA = 250;       // 25 min passam em 6 s
            let quadro = null, inicio = 0;

            function pintar(restante) {
                const m = Math.floor(restante / 60), s = Math.floor(restante % 60);
                tempo.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
                anel.style.strokeDashoffset = (100 * (1 - restante / TOTAL)).toFixed(2);
            }
            function tique(agora) {
                const passou = ((agora - inicio) / 1000) * ACELERA;
                // segura o 00:00 um instante antes de recomeçar
                if (passou > TOTAL + 300) inicio = agora;
                pintar(Math.max(0, TOTAL - passou));
                quadro = requestAnimationFrame(tique);
            }
            return {
                ligar() {
                    if (reduzMovimento) { pintar(TOTAL * 0.62); return; }
                    cancelAnimationFrame(quadro);
                    inicio = performance.now();
                    quadro = requestAnimationFrame(tique);
                },
                desligar() { cancelAnimationFrame(quadro); }
            };
        })(),

        'painel-provas': (function () {
            const dias = document.getElementById('miniProvaDias');
            const DE = 30, ATE = 12;
            let quadro = null;
            return {
                ligar() {
                    cancelAnimationFrame(quadro);
                    if (reduzMovimento) { dias.textContent = ATE; return; }
                    const inicio = performance.now();
                    (function passo(agora) {
                        const t = clamp((agora - inicio) / 1400, 0, 1);
                        const suave = 1 - Math.pow(1 - t, 3);
                        dias.textContent = Math.round(DE - (DE - ATE) * suave);
                        if (t < 1) quadro = requestAnimationFrame(passo);
                    })(inicio);
                },
                desligar() { cancelAnimationFrame(quadro); }
            };
        })()
    };

    /* O flashcard vira de verdade */
    const cartao = document.getElementById('miniCartao');
    if (cartao) {
        cartao.addEventListener('click', () => {
            const virado = cartao.classList.toggle('virado');
            cartao.setAttribute('aria-pressed', String(virado));
        });
    }

    /* ------------------------------------------------------------
       Acender uma estrela
       ------------------------------------------------------------ */
    let atual = -1;

    function ativar(i) {
        if (i === atual) return;
        const antes = atual;
        atual = i;

        estrelas.forEach((e, j) => {
            const sim = j === i;
            e.classList.toggle('ativa', sim);
            e.setAttribute('aria-selected', String(sim));
            e.tabIndex = sim ? 0 : -1;
        });
        paineis.forEach((p, j) => p.classList.toggle('ativa', j === i));

        if (antes >= 0 && miniaturas[paineis[antes].id]) miniaturas[paineis[antes].id].desligar();
        if (miniaturas[paineis[i].id]) miniaturas[paineis[i].id].ligar();

        // reabrir o painel do flashcard começa pela pergunta
        if (cartao && paineis[i].id !== 'painel-flashcards') {
            cartao.classList.remove('virado');
            cartao.setAttribute('aria-pressed', 'false');
        }

        if (numero) numero.textContent = String(i + 1).padStart(2, '0');
    }

    /* ------------------------------------------------------------
       Modo pinado: a rolagem dirige
       ------------------------------------------------------------ */
    const TRECHOS = estrelas.length;      // um trecho de rolagem por estrela
    let trechoAtual = -1;
    let viajandoPara = null;              // posição de rolagem do clique
    let fimDaViagem = 0;

    function progressoPin() {
        const r = pin.getBoundingClientRect();
        const curso = pin.offsetHeight - window.innerHeight;
        return curso > 0 ? clamp(-r.top / curso, 0, 1) : 0;
    }

    function atualizarPelaRolagem() {
        const t = progressoPin() * TRECHOS;              // 0..6
        const s = Math.min(acumulado.length - 2, Math.floor(t));
        const f = clamp(t - s, 0, 1);
        desenhar(acumulado[s] + (acumulado[s + 1] - acumulado[s]) * f);

        const trecho = Math.min(TRECHOS - 1, Math.floor(t));

        if (viajandoPara !== null) {
            const chegou = Math.abs(window.scrollY - viajandoPara) < 3 || performance.now() > fimDaViagem;
            if (!chegou) return;
            viajandoPara = null;
            trechoAtual = trecho;
            return;
        }
        if (trecho !== trechoAtual) {
            trechoAtual = trecho;
            ativar(trecho);
        }
    }

    function posicaoDaEstrela(i) {
        const curso = pin.offsetHeight - window.innerHeight;
        const topo = pin.getBoundingClientRect().top + window.scrollY;
        return Math.round(topo + curso * ((i + 0.5) / TRECHOS));
    }

    function irPara(i) {
        ativar(i);
        if (!podePinar()) return;
        viajandoPara = posicaoDaEstrela(i);
        fimDaViagem = performance.now() + 1400;
        window.scrollTo({ top: viajandoPara, behavior: 'smooth' });
    }

    /* ------------------------------------------------------------
       Mouse, toque e teclado
       ------------------------------------------------------------ */
    estrelas.forEach((e, i) => {
        e.addEventListener('pointerenter', ev => {
            if (ev.pointerType === 'mouse') ativar(i);
        });
        e.addEventListener('click', () => irPara(i));
    });

    secao.querySelector('[role="tablist"]').addEventListener('keydown', ev => {
        const n = estrelas.length;
        let alvo = null;
        if (ev.key === 'ArrowRight' || ev.key === 'ArrowDown') alvo = (atual + 1) % n;
        else if (ev.key === 'ArrowLeft' || ev.key === 'ArrowUp') alvo = (atual - 1 + n) % n;
        else if (ev.key === 'Home') alvo = 0;
        else if (ev.key === 'End') alvo = n - 1;
        if (alvo === null) return;
        ev.preventDefault();
        ativar(alvo);
        estrelas[alvo].focus();
    });

    /* Os links do rodapé ("Pomodoro", "Flashcards"…) abrem a estrela certa */
    document.querySelectorAll('[data-estrela]').forEach(link => {
        const i = estrelas.findIndex(e => e.id === 'estrela-' + link.dataset.estrela);
        if (i < 0) return;
        link.addEventListener('click', () => {
            if (podePinar()) {
                // depois do scrollIntoView do inicio.js, para este vencer
                setTimeout(() => irPara(i), 0);
            } else {
                ativar(i);
            }
        });
    });

    /* ------------------------------------------------------------
       Ligar
       ------------------------------------------------------------ */
    ativar(0);

    // fora do pin: a linha se desenha de uma vez quando a seção aparece
    const obs = new IntersectionObserver(entradas => {
        entradas.forEach(e => {
            if (!e.isIntersecting || podePinar()) return;
            secao.classList.add('desenhada');
            desenhar(total);
        });
    }, { threshold: 0.25 });
    obs.observe(secao);

    if (reduzMovimento) return;

    let ultimoY = -1, ultimoW = -1, ultimoH = -1, pinadoAntes = null;
    (function loop() {
        if (window.scrollY !== ultimoY || window.innerWidth !== ultimoW || window.innerHeight !== ultimoH) {
            ultimoY = window.scrollY; ultimoW = window.innerWidth; ultimoH = window.innerHeight;
            const pinado = podePinar();
            if (pinado) {
                secao.classList.remove('desenhada');
                atualizarPelaRolagem();
            } else if (pinadoAntes) {
                // saiu do modo pinado (janela estreitou): linha inteira
                desenhar(total);
            }
            pinadoAntes = pinado;
        }
        requestAnimationFrame(loop);
    })();
})();
