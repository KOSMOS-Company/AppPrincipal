/* ============================================================
   KOSMOS — profundidade.js
   Parallax, estratificação no eixo Z e microinterações espaciais.

   A ideia central: nada aqui é escrito elemento por elemento. O HTML
   DECLARA em que plano cada coisa vive, e este arquivo cuida da
   conta. Antes, cada parallax da página era uma regra própria no
   CSS com um seletor próprio — acrescentar um elemento ao efeito
   exigia escrever CSS novo. Agora é um atributo.

   O vocabulário:

     data-plano="fundo|meio|frente"
         Atalho para os três planos de profundidade. O fundo anda
         pouco, a frente anda muito — é a diferença de velocidade
         que o cérebro lê como distância.

     data-parallax="-60"
         O mesmo, com o deslocamento em pixels dito à mão, para
         quando os três planos não bastam. Negativo sobe.

     data-scrub="#idDoPin"
         Atrela o elemento ao progresso EXATO de uma seção pinada:
         ele recebe --scrub de 0 a 1 conforme a barra de rolagem
         avança ou RETROCEDE. Não é um vídeo que toca sozinho —
         voltar a rolagem desfaz a animação na mesma proporção.

     data-relevo
         Microinteração: o elemento inclina e o brilho especular
         segue o ponteiro (ou a inclinação do aparelho), como um
         material que reflete luz. Reage antes de qualquer clique.

   Todos os cálculos entram em variáveis CSS (--py, --scrub, --lx,
   --ly, --inc-x, --inc-y). Quem decide a APARÊNCIA é o CSS; aqui só
   se mede. É o que permite ajustar um efeito sem tocar em
   JavaScript.

   Nada disso roda com "reduzir movimento" ligado.
   ============================================================ */

(function () {
    'use strict';

    var semMovimento = window.matchMedia('(prefers-reduced-motion: reduce)');
    if (semMovimento.matches) return;

    var ponteiroFino = window.matchMedia('(hover: hover) and (pointer: fine)');

    /* Os três planos, em pixels de deslocamento ao longo do percurso.
       A proporção entre eles é o que cria a profundidade: a frente
       anda seis vezes mais que o fundo. */
    var PLANOS = { fundo: -18, meio: -55, frente: -110 };

    /* ------------------------------------------------------------
       Coleta
       ------------------------------------------------------------ */
    var paralaxe = [];
    var scrubs = [];

    function coletar() {
        paralaxe = [];
        scrubs = [];

        [].forEach.call(document.querySelectorAll('[data-plano], [data-parallax]'), function (el) {
            var f = el.hasAttribute('data-parallax')
                ? parseFloat(el.getAttribute('data-parallax'))
                : PLANOS[el.getAttribute('data-plano')];

            if (!isFinite(f)) return;
            paralaxe.push({ el: el, f: f });
        });

        [].forEach.call(document.querySelectorAll('[data-scrub]'), function (el) {
            var pin = document.querySelector(el.getAttribute('data-scrub'));
            if (pin) scrubs.push({ el: el, pin: pin });
        });
    }

    /* ------------------------------------------------------------
       Medidas
       ------------------------------------------------------------ */

    /** -1 (abaixo da tela) .. 0 (no centro) .. 1 (acima da tela) */
    function posNaTela(el) {
        var r = el.getBoundingClientRect();
        var meio = r.top + r.height / 2 - window.innerHeight / 2;
        var total = (window.innerHeight + r.height) / 2;
        return Math.max(-1, Math.min(1, meio / total));
    }

    /** 0..1 conforme a seção pinada é consumida pela rolagem. */
    function progressoPin(el) {
        var r = el.getBoundingClientRect();
        var total = el.offsetHeight - window.innerHeight;
        return total > 0 ? Math.max(0, Math.min(1, -r.top / total)) : 0;
    }

    /** Está perto o bastante da tela para valer a conta? */
    function porPerto(el) {
        var r = el.getBoundingClientRect();
        return r.bottom > -window.innerHeight && r.top < window.innerHeight * 2;
    }

    /* ------------------------------------------------------------
       O laço da rolagem
       Só recalcula quando algo de fato mudou: sem isto o navegador
       ficaria refazendo as mesmas contas 60 vezes por segundo com a
       página parada.
       ------------------------------------------------------------ */
    var ultimoY = -1, ultimaL = -1, ultimaA = -1;

    function medirTudo() {
        for (var i = 0; i < paralaxe.length; i++) {
            var p = paralaxe[i];
            if (!porPerto(p.el)) continue;
            p.el.style.setProperty('--py', (posNaTela(p.el) * p.f).toFixed(2) + 'px');
        }

        for (var j = 0; j < scrubs.length; j++) {
            var s = scrubs[j];
            s.el.style.setProperty('--scrub', progressoPin(s.pin).toFixed(4));
        }
    }

    function laco() {
        if (window.scrollY !== ultimoY ||
            window.innerWidth !== ultimaL ||
            window.innerHeight !== ultimaA) {

            ultimoY = window.scrollY;
            ultimaL = window.innerWidth;
            ultimaA = window.innerHeight;
            medirTudo();
        }
        requestAnimationFrame(laco);
    }

    /* ------------------------------------------------------------
       Microinterações: o material reagindo à luz
       ------------------------------------------------------------ */
    var relevos = [];

    function ligarRelevo() {
        relevos = [].slice.call(document.querySelectorAll('[data-relevo]'));
        if (!relevos.length) return;

        /* ---- ponteiro ---- */
        if (ponteiroFino.matches) {
            relevos.forEach(function (el) {
                var pedido = 0, ex = 0, ey = 0;

                el.addEventListener('mousemove', function (e) {
                    ex = e.clientX;
                    ey = e.clientY;
                    if (pedido) return;
                    pedido = requestAnimationFrame(function () {
                        pedido = 0;
                        var r = el.getBoundingClientRect();
                        var x = (ex - r.left) / r.width;     // 0..1
                        var y = (ey - r.top) / r.height;

                        // onde o brilho especular nasce
                        el.style.setProperty('--lx', (x * 100).toFixed(1) + '%');
                        el.style.setProperty('--ly', (y * 100).toFixed(1) + '%');

                        // e a inclinação, que muda o ângulo da sombra
                        el.style.setProperty('--inc-x', ((0.5 - y) * 2).toFixed(3));
                        el.style.setProperty('--inc-y', ((x - 0.5) * 2).toFixed(3));
                        el.classList.add('relevo-ativo');
                    });
                }, { passive: true });

                el.addEventListener('mouseleave', function () {
                    el.classList.remove('relevo-ativo');
                    el.style.setProperty('--inc-x', '0');
                    el.style.setProperty('--inc-y', '0');
                });
            });
            return;
        }

        /* ---- inclinação do aparelho ----
           Só onde já é permitido sem pedir nada. No iOS 13+ isto exige
           uma permissão explícita disparada por um toque; abrir esse
           pedido sozinho, sem a pessoa ter pedido nada, seria invasivo
           — então no iPhone os cartões simplesmente ficam parados. */
        if (typeof window.DeviceOrientationEvent === 'undefined') return;
        if (typeof window.DeviceOrientationEvent.requestPermission === 'function') return;

        var pedidoG = 0, gx = 0, gy = 0;
        window.addEventListener('deviceorientation', function (e) {
            if (e.beta === null || e.gamma === null) return;

            // beta: frente/trás (-180..180). gamma: esquerda/direita (-90..90)
            gy = Math.max(-1, Math.min(1, e.gamma / 35));
            gx = Math.max(-1, Math.min(1, (e.beta - 45) / 35));

            if (pedidoG) return;
            pedidoG = requestAnimationFrame(function () {
                pedidoG = 0;
                relevos.forEach(function (el) {
                    if (!porPerto(el)) return;
                    el.style.setProperty('--lx', (50 + gy * 30).toFixed(1) + '%');
                    el.style.setProperty('--ly', (50 - gx * 30).toFixed(1) + '%');
                    el.style.setProperty('--inc-x', gx.toFixed(3));
                    el.style.setProperty('--inc-y', gy.toFixed(3));
                    el.classList.add('relevo-ativo');
                });
            });
        }, { passive: true });
    }

    /* ------------------------------------------------------------
       Partida
       ------------------------------------------------------------ */
    function iniciar() {
        coletar();
        ligarRelevo();
        medirTudo();
        requestAnimationFrame(laco);

        /* As seções pinadas mudam de altura conforme o CSS entra:
           recolher de novo evita medir com o layout velho. */
        if (window.ResizeObserver) {
            var ro = new ResizeObserver(function () { medirTudo(); });
            ro.observe(document.body);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();
