/* ============================================================
   KOSMOS — cosmos.js
   O espaço por trás da página INTEIRA.

   Esta é a versão de RESERVA, em Canvas 2D. O desenho principal é
   o js/cosmos-gl.js (WebGL); este arquivo assume quando não há
   WebGL na máquina ou quando o shader não compila.

   Substitui o antigo space.js, que desenhava estrelas só dentro
   do hero e ainda desbotava na rolagem — da segunda tela em
   diante a página virava um fundo preto liso. Aqui o campo é um
   canvas fixo atrás de tudo, e ele nunca acaba.

   O que dá a sensação de viagem:
     • três camadas de estrelas em profundidades diferentes, que
       reagem à rolagem em velocidades diferentes (parallax). A
       camada da frente anda ~7x mais que a do fundo — é isso, e
       não a quantidade de estrelas, que cria o volume;
     • as estrelas dão a volta: ao sair por cima, reentram por
       baixo. O campo é infinito, então dá para rolar para sempre;
     • nebulosas enormes e desfocadas que derivam devagar e
       MUDAM DE COR conforme a página avança — cada trecho da
       jornada tem um céu diferente;
     • meteoros de vez em quando, e um leve parallax pelo mouse.

   Sobre desempenho, que é o risco real de um fundo assim:
     • a densidade sai da área da tela, com teto menor no celular;
     • no toque o laço roda em ~30fps (metade dos quadros) — a
       diferença não se vê e o custo cai pela metade;
     • o laço PARA quando a aba sai de foco;
     • com "reduzir movimento" o campo é desenhado uma vez e fica
       parado: continua bonito, sem nada se mexendo.
   ============================================================ */

(function () {
    'use strict';

    var canvas = document.getElementById('cosmos');
    if (!canvas) return;

    /* O js/cosmos-gl.js roda antes e tenta WebGL. Se conseguiu, ele é
       o dono do canvas e este arquivo sai de cena. Esta versão em
       Canvas 2D continua no projeto de propósito: é o que aparece em
       máquina sem WebGL ou quando o shader não compila. */
    if (window.KosmosCosmos && window.KosmosCosmos.modo === 'webgl') return;
    window.KosmosCosmos = { modo: '2d' };

    var ctx = canvas.getContext('2d', { alpha: true });
    if (!ctx) return;

    var semMovimento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var ponteiroFino = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

    /* ── Estado ── */
    var L = 0, A = 0;            // largura e altura em CSS pixels
    var dpr = 1;
    var estrelas = [];
    var nebulosas = [];
    var meteoros = [];
    var esperaMeteoro = 140;

    var alvoMX = 0, alvoMY = 0;  // mouse normalizado (-1..1)
    var mx = 0, my = 0;          // valor suavizado
    var rolagem = 0;             // scrollY suavizado
    var alturaDoc = 1;

    var rodando = true;
    var quadro = 0;

    /* As camadas: quanto mais perto, mais rápido anda e maior aparece.
       Os fatores são o coração do efeito de profundidade. */
    var CAMADAS = [
        { parallax: 0.018, raio: [0.5, 1.0], brilho: [0.22, 0.50], mouse: 4  },
        { parallax: 0.055, raio: [0.8, 1.7], brilho: [0.38, 0.75], mouse: 10 },
        { parallax: 0.130, raio: [1.2, 2.4], brilho: [0.60, 1.00], mouse: 20 }
    ];

    /* Os céus pelos quais a página passa. A cor caminha de um para
       o outro conforme se rola — não troca de repente. */
    var CEUS = [
        { r: 130, g:  60, b: 220 },   // topo: roxo da marca
        { r:  90, g:  40, b: 190 },   // meio: mais fundo, mais frio
        { r: 160, g:  50, b: 200 },   // adiante: magenta
        { r:  70, g:  60, b: 210 }    // fim: azul-violeta
    ];

    /* ------------------------------------------------------------
       Montagem
       ------------------------------------------------------------ */
    function medir() {
        L = window.innerWidth;
        A = window.innerHeight;

        // acima de 2 o ganho não se vê e o custo dobra
        dpr = Math.min(window.devicePixelRatio || 1, 2);

        canvas.width = Math.round(L * dpr);
        canvas.height = Math.round(A * dpr);
        canvas.style.width = L + 'px';
        canvas.style.height = A + 'px';
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

        alturaDoc = Math.max(1, document.documentElement.scrollHeight - A);

        criarEstrelas();
        criarNebulosas();
    }

    function criarEstrelas() {
        /* A densidade vem da área, não de um número fixo: numa tela
           grande o céu ficaria ralo, e num celular, sobrecarregado. */
        var area = L * A;
        var teto = ponteiroFino ? 320 : 150;
        var total = Math.max(60, Math.min(teto, Math.round(area / 4200)));

        estrelas = [];
        for (var i = 0; i < total; i++) {
            var c = i % 3;                     // distribui entre as camadas
            var camada = CAMADAS[c];
            estrelas.push({
                c: c,
                x: Math.random() * L,
                // nasce espalhada por 2 telas: é o que a rolagem consome
                y: Math.random() * A * 2,
                r: camada.raio[0] + Math.random() * (camada.raio[1] - camada.raio[0]),
                b: camada.brilho[0] + Math.random() * (camada.brilho[1] - camada.brilho[0]),
                // algumas em roxo, para o céu não ser só branco
                roxa: Math.random() < 0.34,
                // cada uma cintila no seu ritmo
                fase: Math.random() * Math.PI * 2,
                vel: 0.4 + Math.random() * 0.9
            });
        }
    }

    function criarNebulosas() {
        var grande = Math.max(L, A);
        nebulosas = [
            { x: 0.22, y: 0.18, r: grande * 0.55, op: 0.30, vx: 0.6, vy: 0.35, ceu: 0 },
            { x: 0.80, y: 0.55, r: grande * 0.48, op: 0.24, vx: -0.4, vy: 0.5, ceu: 1 },
            { x: 0.48, y: 0.88, r: grande * 0.62, op: 0.20, vx: 0.3, vy: -0.45, ceu: 2 }
        ];
    }

    /* ------------------------------------------------------------
       A cor do céu neste ponto da jornada
       ------------------------------------------------------------ */
    function ceuAgora(desvio) {
        var p = Math.min(1, Math.max(0, rolagem / alturaDoc));
        // caminha pela lista de céus: 0..1 vira 0..(n-1)
        var pos = p * (CEUS.length - 1) + (desvio || 0);
        var i = Math.floor(pos);
        var t = pos - i;

        var a = CEUS[Math.max(0, Math.min(CEUS.length - 1, i))];
        var b = CEUS[Math.max(0, Math.min(CEUS.length - 1, i + 1))];

        return {
            r: Math.round(a.r + (b.r - a.r) * t),
            g: Math.round(a.g + (b.g - a.g) * t),
            b: Math.round(a.b + (b.b - a.b) * t)
        };
    }

    /* ------------------------------------------------------------
       Meteoros
       ------------------------------------------------------------ */
    function criarMeteoro() {
        var v = 6 + Math.random() * 5;
        var ang = (Math.PI / 180) * (18 + Math.random() * 16);
        var paraDireita = Math.random() < 0.5;

        meteoros.push({
            x: paraDireita ? -60 : L + 60,
            y: Math.random() * A * 0.55,
            vx: (paraDireita ? 1 : -1) * Math.cos(ang) * v,
            vy: Math.sin(ang) * v,
            vida: 1
        });
    }

    /* ------------------------------------------------------------
       Desenho
       ------------------------------------------------------------ */
    function desenhar(t) {
        ctx.clearRect(0, 0, L, A);

        // ── nebulosas (o "ar" do espaço) ──
        for (var n = 0; n < nebulosas.length; n++) {
            var neb = nebulosas[n];
            var cor = ceuAgora(neb.ceu * 0.35);

            // deriva lenta + um empurrão da rolagem, para respirarem juntas
            var nx = neb.x * L + Math.sin(t * 0.00004 * neb.vx) * L * 0.10 + mx * 26;
            var ny = neb.y * A + Math.cos(t * 0.00004 * neb.vy) * A * 0.10
                   - (rolagem * 0.03) % (A * 2) + my * 18;

            var g = ctx.createRadialGradient(nx, ny, 0, nx, ny, neb.r);
            g.addColorStop(0, 'rgba(' + cor.r + ',' + cor.g + ',' + cor.b + ',' + neb.op + ')');
            g.addColorStop(0.55, 'rgba(' + cor.r + ',' + cor.g + ',' + cor.b + ',' + (neb.op * 0.28) + ')');
            g.addColorStop(1, 'rgba(0,0,0,0)');

            ctx.fillStyle = g;
            ctx.fillRect(0, 0, L, A);
        }

        // ── estrelas ──
        for (var i = 0; i < estrelas.length; i++) {
            var s = estrelas[i];
            var camada = CAMADAS[s.c];

            /* A rolagem empurra a estrela para cima; o resto da divisão
               faz ela reentrar por baixo. É o que torna o campo infinito. */
            var altura = A * 2;
            var y = (s.y - rolagem * camada.parallax) % altura;
            if (y < 0) y += altura;
            y -= A * 0.5;                       // margem para não "nascer" na borda

            if (y < -20 || y > A + 20) continue;

            var x = s.x + mx * camada.mouse;

            // cintilação: só as camadas da frente, para não virar pisca-pisca
            var cintila = semMovimento || s.c === 0
                ? 1
                : 0.72 + Math.sin(t * 0.001 * s.vel + s.fase) * 0.28;

            var alfa = s.b * cintila;
            ctx.beginPath();
            ctx.arc(x, y, s.r, 0, Math.PI * 2);
            ctx.fillStyle = s.roxa
                ? 'rgba(201,124,255,' + alfa + ')'
                : 'rgba(255,255,255,' + alfa + ')';
            ctx.fill();

            // as maiores ganham um halo: é o que dá a impressão de perto
            if (s.c === 2 && s.r > 1.8) {
                var h = ctx.createRadialGradient(x, y, 0, x, y, s.r * 4);
                h.addColorStop(0, 'rgba(201,124,255,' + (alfa * 0.30) + ')');
                h.addColorStop(1, 'rgba(201,124,255,0)');
                ctx.fillStyle = h;
                ctx.fillRect(x - s.r * 4, y - s.r * 4, s.r * 8, s.r * 8);
            }
        }

        // ── meteoros ──
        for (var m = meteoros.length - 1; m >= 0; m--) {
            var me = meteoros[m];
            me.x += me.vx;
            me.y += me.vy;
            me.vida -= 0.012;

            if (me.vida <= 0 || me.y > A + 80 || me.x < -120 || me.x > L + 120) {
                meteoros.splice(m, 1);
                continue;
            }

            var cauda = 18;
            var g2 = ctx.createLinearGradient(
                me.x, me.y, me.x - me.vx * cauda, me.y - me.vy * cauda
            );
            g2.addColorStop(0, 'rgba(255,255,255,' + (0.9 * me.vida) + ')');
            g2.addColorStop(0.4, 'rgba(201,124,255,' + (0.5 * me.vida) + ')');
            g2.addColorStop(1, 'rgba(201,124,255,0)');

            ctx.strokeStyle = g2;
            ctx.lineWidth = 1.8;
            ctx.lineCap = 'round';
            ctx.beginPath();
            ctx.moveTo(me.x, me.y);
            ctx.lineTo(me.x - me.vx * cauda, me.y - me.vy * cauda);
            ctx.stroke();
        }
    }

    /* ------------------------------------------------------------
       O laço
       ------------------------------------------------------------ */
    function passo(t) {
        if (!rodando) return;
        quadro++;

        // no toque, metade dos quadros: não se vê a diferença e custa metade
        if (!ponteiroFino && quadro % 2) {
            requestAnimationFrame(passo);
            return;
        }

        // suavização: o fundo persegue o scroll e o mouse em vez de saltar
        rolagem += (window.scrollY - rolagem) * 0.08;
        mx += (alvoMX - mx) * 0.05;
        my += (alvoMY - my) * 0.05;

        if (--esperaMeteoro <= 0) {
            criarMeteoro();
            esperaMeteoro = 260 + Math.random() * 520;
        }

        desenhar(t);
        requestAnimationFrame(passo);
    }

    /* ------------------------------------------------------------
       Ligações
       ------------------------------------------------------------ */
    var remedir = null;
    window.addEventListener('resize', function () {
        clearTimeout(remedir);
        remedir = setTimeout(medir, 180);
    });

    if (ponteiroFino && !semMovimento) {
        window.addEventListener('mousemove', function (e) {
            alvoMX = (e.clientX / L) * 2 - 1;
            alvoMY = (e.clientY / A) * 2 - 1;
        }, { passive: true });
    }

    /* Aba escondida: não faz sentido desenhar para ninguém. */
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            rodando = false;
        } else if (!semMovimento) {
            rodando = true;
            requestAnimationFrame(passo);
        }
    });

    /* A altura do documento muda quando as seções pinadas entram
       (elas têm 300vh+): sem remedir, a cor do céu erraria o ponto. */
    if (window.ResizeObserver) {
        new ResizeObserver(function () {
            alturaDoc = Math.max(1, document.documentElement.scrollHeight - A);
        }).observe(document.body);
    }

    /* ------------------------------------------------------------
       Partida
       ------------------------------------------------------------ */
    medir();

    if (semMovimento) {
        // um céu bonito e parado: nada se mexe, mas o espaço continua lá
        rolagem = 0;
        desenhar(0);
    } else {
        requestAnimationFrame(passo);
    }
})();
