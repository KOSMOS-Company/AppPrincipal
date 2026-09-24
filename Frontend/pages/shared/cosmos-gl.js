/* ============================================================
   KOSMOS — cosmos-gl.js
   O espaço por trás da página, agora renderizado em WebGL.

   Por que WebGL e não mais Canvas 2D: no 2D cada estrela é uma
   chamada de desenho, e nebulosa é um degradê desfocado — o custo
   cresce com a quantidade e a "névoa" nunca tem textura de verdade.
   Aqui a tela inteira é UM retângulo e quem pinta cada pixel é a
   placa de vídeo. Isso permite o que o 2D não permitia:

     • nebulosas com textura real (ruído fractal em 5 oitavas),
       que se deformam e derivam em vez de serem manchas borradas;
     • três camadas de estrelas com parallax independente, de graça
       — são contas por pixel, não objetos numa lista;
     • luz reagindo ao ponteiro em tempo real: o brilho acompanha o
       cursor e ilumina a névoa em volta antes de qualquer clique.

   SEM BIBLIOTECA. O projeto nunca usou nenhuma no frontend, e um
   fundo não justifica trazer três.js para dentro. São ~40 linhas de
   WebGL cru para montar o programa e um shader de fragmento.

   FALLBACK: se não houver WebGL, ou se o shader não compilar, este
   arquivo desiste em silêncio e o js/cosmos.js (Canvas 2D, que
   continua no projeto) assume. É por isso que ele é carregado
   depois deste e começa perguntando quem já está no ar.
   ============================================================ */

(function () {
    'use strict';

    var canvas = document.getElementById('cosmos');
    if (!canvas) return;

    var semMovimento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var ponteiroFino = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

    /* MODO CALMO — <canvas id="cosmos" data-calmo>
       Ligado no dashboard, não na landing page. Lá o céu é o assunto e a
       visita dura um minuto; aqui a pessoa fica horas estudando com um
       Pomodoro correndo, e um fundo caro rodando o tempo todo cobra em
       bateria e em atenção. Então no dashboard o MESMO céu roda com menos
       resolução e metade dos quadros. O quanto ele aparece é decisão do
       CSS (a opacidade do canvas), não daqui. */
    var calmo = canvas.hasAttribute('data-calmo');

    /* ------------------------------------------------------------
       O shader
       ------------------------------------------------------------ */
    var VERTICE = [
        'attribute vec2 aPos;',
        'void main() { gl_Position = vec4(aPos, 0.0, 1.0); }'
    ].join('\n');

    var FRAGMENTO = [
        /* highp nao e garantido em fragment shader (GPU movel antiga
           pode nao ter). O guarda e o jeito padrao de pedir o melhor
           disponivel sem quebrar onde nao existe. */
        '#ifdef GL_FRAGMENT_PRECISION_HIGH',
        'precision highp float;',
        '#else',
        'precision mediump float;',
        '#endif',

        'uniform vec2  uRes;',      // tamanho da tela em pixels
        'uniform float uTempo;',    // segundos
        'uniform float uProg;',     // 0..1 = quanto da página já passou
        'uniform float uRolagem;',  // pixels rolados (parallax)
        'uniform vec2  uMouse;',    // -1..1
        'uniform float uVivo;',     // 0 = parado (reduzir movimento)

        /* ---- ruído ---- */
        'float hash(vec2 p) {',
        '    return fract(sin(dot(p, vec2(127.1, 311.7))) * 43758.5453123);',
        '}',

        'float ruido(vec2 p) {',
        '    vec2 i = floor(p);',
        '    vec2 f = fract(p);',
        '    vec2 u = f * f * (3.0 - 2.0 * f);',
        '    return mix(mix(hash(i + vec2(0.0, 0.0)), hash(i + vec2(1.0, 0.0)), u.x),',
        '               mix(hash(i + vec2(0.0, 1.0)), hash(i + vec2(1.0, 1.0)), u.x), u.y);',
        '}',

        /* Quatro oitavas: cada uma com o dobro da frequência e metade
           da força. É o que dá à nebulosa filamentos em vez de borrão. */
        'float fbm(vec2 p) {',
        '    float v = 0.0;',
        '    float a = 0.5;',
        '    for (int i = 0; i < 4; i++) {',
        '        v += a * ruido(p);',
        '        p *= 2.03;',
        '        a *= 0.5;',
        '    }',
        '    return v;',
        '}',

        /* ════════ ESTRELAS ════════
           O que separa "céu" de "neve" não é o número — é a VARIEDADE.
           Neve: tudo do mesmo tamanho, do mesmo brilho, espalhado por
           igual, com a borda macia. Céu: milhares de pontos quase
           invisíveis, algumas dezenas médias, e umas poucas que
           brilham de verdade e têm brilho em volta.

           Então cada camada aqui tem MUITAS estrelas (a tentativa
           anterior errou pelo outro lado: cortei tanto que o espaço
           ficou vazio), mas com faixa dinâmica enorme:
             · o tamanho sai de pow(t, 5) — quase tudo perto de zero;
             · o brilho sai de pow(h, 3) — quase tudo discreto;
             · só as raras acima de 0.82 ganham bloom visível;
             · a cor varia: a maioria branco-azulada, algumas quentes,
               algumas arroxeadas (a cor da marca).
           `ganho` é o que a Via Láctea usa para adensar a faixa. */
        'vec3 camadaEstrelas(vec2 uv, float densidade, float cintila, float ganho) {',
        '    vec2 g = uv * densidade;',
        '    vec2 cel = floor(g);',
        '    vec2 pos = fract(g);',

        '    float sorte = hash(cel);',
        '    if (sorte < 0.34 - ganho * 0.22) return vec3(0.0);',

        '    vec2 centro = vec2(hash(cel + 1.7), hash(cel + 4.3));',
        '    float d = distance(pos, centro);',

        '    float t = hash(cel + 7.3);',
        '    float raio = 0.012 + pow(t, 5.0) * 0.075;',

        // núcleo de queda abrupta: ponto duro, não bolinha macia
        '    float nucleo = pow(max(0.0, 1.0 - d / raio), 4.0);',

        '    float forca = (0.10 + pow(hash(cel + 21.9), 3.0) * 0.90) * (0.75 + ganho * 0.55);',

        // bloom só nas poucas realmente brilhantes — é o que dá vida
        '    float rara = smoothstep(0.82, 0.99, t);',
        '    float bloom = exp(-d * 13.0) * 0.30 * rara;',

        '    float fase = hash(cel + 13.7) * 6.2831;',
        '    float pulso = 0.80 + 0.20 * sin(uTempo * (0.5 + hash(cel + 2.2) * 1.6) + fase);',

        '    float luz = (nucleo + bloom) * forca * mix(1.0, pulso, cintila * uVivo);',

        /* cor: a maioria branco-azulada, algumas quentes, algumas roxas */
        '    float tom = hash(cel + 31.4);',
        '    vec3 cor = vec3(0.86, 0.90, 1.00);',
        '    cor = mix(cor, vec3(1.00, 0.88, 0.74), smoothstep(0.62, 0.86, tom));',
        '    cor = mix(cor, vec3(0.78, 0.58, 1.00), smoothstep(0.88, 1.00, tom));',

        '    return cor * luz;',
        '}',

        /* ════════ ESTRELA CADENTE ════════
           A primeira versão era um risco fino e duro de ponta a ponta —
           parecia um arranhão na tela. Um cometa de verdade tem uma
           CABEÇA pequena e brilhante e uma cauda que afina e apaga
           atrás dela.

           Por isso o pixel é projetado sobre a linha do movimento:
           `aoLongo` diz a que distância da cabeça ele está, `lateral`
           o quanto está fora da linha. A largura e o brilho caem com
           aoLongo — a cauda afina e some sozinha. */
        'float cadente(vec2 p, float semente) {',
        '    if (uVivo < 0.5) return 0.0;',

        // raras de propósito: um cometa a cada ~15-30s é um presente,
        // a cada 3s é poluição
        '    float ciclo = 15.0 + hash(vec2(semente, 3.3)) * 15.0;',
        '    float f = fract((uTempo + semente * 41.0) / ciclo);',
        '    if (f > 0.075) return 0.0;',
        '    float u = f / 0.075;',

        '    vec2 ini = vec2(-0.85 + hash(vec2(semente, 1.1)) * 0.55,',
        '                     0.34 + hash(vec2(semente, 2.2)) * 0.22);',
        '    vec2 dir = normalize(vec2(0.90, -0.34 - hash(vec2(semente, 4.4)) * 0.26));',

        '    vec2 cabeca = ini + dir * (u * 1.9);',
        '    float comp = 0.16 + hash(vec2(semente, 5.5)) * 0.12;',

        '    vec2 rel = p - cabeca;',
        '    float aoLongo = dot(rel, -dir);',                 // >0 = atrás da cabeça
        '    if (aoLongo < 0.0 || aoLongo > comp) return 0.0;',

        '    float lateral = abs(dot(rel, vec2(-dir.y, dir.x)));',
        '    float k = aoLongo / comp;',                        // 0 na cabeça, 1 no fim

        // a cauda afina e apaga
        '    float largura = mix(0.0016, 0.00018, k);',
        '    float rastro = exp(-lateral / largura) * pow(1.0 - k, 2.4);',

        // a cabeça: um ponto pequeno e forte, com brilho em volta
        '    float dc = length(rel);',
        '    float nucleo = exp(-dc * 620.0) * 1.8;',
        '    float halo   = exp(-dc * 90.0) * 0.35;',

        // entra e sai suave, para não piscar nas bordas da tela
        '    float vida = smoothstep(0.0, 0.16, u) * smoothstep(1.0, 0.62, u);',

        '    return (rastro + nucleo + halo) * vida;',
        '}',

        /* ---- a cor do céu neste ponto da jornada ---- */
        'vec3 ceu(float t) {',
        '    vec3 a = vec3(0.51, 0.24, 0.86);',   // roxo da marca
        '    vec3 b = vec3(0.32, 0.15, 0.72);',   // mais fundo e frio
        '    vec3 c = vec3(0.66, 0.20, 0.80);',   // magenta
        '    vec3 d = vec3(0.24, 0.26, 0.84);',   // azul-violeta

        '    float p = clamp(t, 0.0, 1.0) * 3.0;',
        '    vec3 cor = mix(a, b, clamp(p, 0.0, 1.0));',
        '    cor = mix(cor, c, clamp(p - 1.0, 0.0, 1.0));',
        '    cor = mix(cor, d, clamp(p - 2.0, 0.0, 1.0));',
        '    return cor;',
        '}',

        'void main() {',
        '    vec2 uv = gl_FragCoord.xy / uRes.xy;',
        '    float prop = uRes.x / uRes.y;',
        '    vec2 p = vec2((uv.x - 0.5) * prop, uv.y - 0.5);',

        '    float t = uTempo * 0.02 * uVivo;',
        '    vec3 cor = vec3(0.0);',

        /* ════════ A VIA LÁCTEA ════════
           É ela que faz o céu parecer O cosmos, e não pontinhos ao
           acaso: uma faixa atravessando a tela na diagonal, com mais
           estrelas dentro, poeira brilhante e as trilhas escuras que
           cortam a faixa em qualquer foto de céu de verdade. */
        '    vec2 eixo = normalize(vec2(0.82, 0.57));',
        '    vec2 nb = p * 1.5;',
        '    nb.y += uRolagem * 0.00016;',
        '    nb += uMouse * 0.030;',

        /* n1 serve a DUAS coisas: ondular a faixa e formar a nebulosa.
           Uma chamada de fbm a menos por pixel — e são 16 amostras de
           ruído cada uma, então isso pesa de verdade. */
        '    float n1 = fbm(nb + vec2(t, t * 0.6));',

        '    float dBanda = dot(p, vec2(-eixo.y, eixo.x));',        // distância à faixa
        '    float ondula = (n1 - 0.5) * 0.24;',
        '    float banda = exp(-pow(abs(dBanda + ondula) * 3.2, 2.0));',

        // a poeira da faixa
        '    float poeira = fbm(nb * 2.4 + vec2(t * 0.5, -t * 0.3));',
        '    float trilhas = fbm(nb * 4.1 - vec2(t * 0.3, t * 0.2));',
        '    float veu = banda * pow(poeira, 1.7) * (0.45 + 0.55 * smoothstep(0.30, 0.72, trilhas));',

        '    vec3 corCeu = ceu(uProg);',
        '    cor += mix(vec3(0.70, 0.68, 0.92), corCeu, 0.55) * veu * 0.42;',

        /* ---- nebulosas soltas, fora da faixa ---- */
        '    float n2 = fbm(nb * 2.2 - vec2(t * 0.8, t * 0.4) + n1);',
        '    float nevoa = pow(clamp(n1 * 0.62 + n2 * 0.48, 0.0, 1.0), 2.6);',
        '    cor += corCeu * nevoa * 0.44;',
        '    cor += ceu(clamp(uProg + 0.3, 0.0, 1.0)) * pow(n1, 3.4) * 0.26;',

        /* ════════ ESTRELAS: quatro profundidades ════════
           A de trás quase não anda; a da frente anda ~9x mais. É a
           diferença, e não o número, que o cérebro lê como distância.
           O `ganho` da faixa adensa e aviva o que cai dentro dela. */
        '    vec2 uvf = vec2(uv.x * prop, uv.y);',
        '    float g = banda;',

        '    vec2 u0 = uvf + vec2(uMouse.x * 0.002, uRolagem * 0.000018);',
        '    vec2 u1 = uvf + vec2(uMouse.x * 0.006, uRolagem * 0.000055);',
        '    vec2 u2 = uvf + vec2(uMouse.x * 0.013, uRolagem * 0.000130);',
        '    vec2 u3 = uvf + vec2(uMouse.x * 0.024, uRolagem * 0.000280);',

        '    cor += camadaEstrelas(u0, 108.0, 0.0, g) * 0.55;',   // poeira estelar
        '    cor += camadaEstrelas(u1,  64.0, 0.3, g) * 0.80;',
        '    cor += camadaEstrelas(u2,  38.0, 0.7, g) * 1.00;',
        '    cor += camadaEstrelas(u3,  21.0, 1.0, g) * 1.15;',   // as da frente

        /* ---- estrelas cadentes ---- */
        '    float cad = cadente(p, 1.0) + cadente(p, 2.0);',
        '    cor += vec3(1.00, 0.96, 0.98) * cad;',
        '    cor += vec3(0.70, 0.45, 1.00) * cad * 0.45;',

        /* ---- luz do ponteiro: a cena responde antes do clique ---- */
        '    vec2 luz = vec2(uMouse.x * 0.5 * prop, -uMouse.y * 0.5);',
        '    float dl = distance(p, luz);',
        '    float halo = exp(-dl * 3.2) * 0.26;',
        '    cor += corCeu * halo * (0.40 + nevoa + veu);',

        /* ---- acabamento ---- */
        '    float vinheta = smoothstep(1.20, 0.28, length(p));',
        '    cor *= 0.34 + vinheta * 0.66;',
        '    cor += (hash(gl_FragCoord.xy + uTempo) - 0.5) * 0.013;',

        '    gl_FragColor = vec4(cor, 1.0);',
        '}'
    ].join('\n');

    /* ------------------------------------------------------------
       Montagem do WebGL
       ------------------------------------------------------------ */
    var gl = null;
    try {
        var opcoes = { alpha: true, antialias: false, depth: false, stencil: false,
                       powerPreference: 'low-power', failIfMajorPerformanceCaveat: false };
        gl = canvas.getContext('webgl', opcoes) || canvas.getContext('experimental-webgl', opcoes);
    } catch (e) {
        gl = null;
    }
    if (!gl) return;                     // sem WebGL: o cosmos.js 2D assume

    function compilar(tipo, fonte) {
        var s = gl.createShader(tipo);
        gl.shaderSource(s, fonte);
        gl.compileShader(s);
        if (!gl.getShaderParameter(s, gl.COMPILE_STATUS)) {
            // não estoura a página: só desiste e deixa o 2D assumir
            gl.deleteShader(s);
            return null;
        }
        return s;
    }

    var vs = compilar(gl.VERTEX_SHADER, VERTICE);
    var fs = compilar(gl.FRAGMENT_SHADER, FRAGMENTO);
    if (!vs || !fs) return;

    var prog = gl.createProgram();
    gl.attachShader(prog, vs);
    gl.attachShader(prog, fs);
    gl.linkProgram(prog);
    if (!gl.getProgramParameter(prog, gl.LINK_STATUS)) return;

    gl.useProgram(prog);

    // um retângulo cobrindo a tela: dois triângulos, dois valores por vértice
    var buf = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, buf);
    gl.bufferData(gl.ARRAY_BUFFER,
        new Float32Array([-1, -1, 1, -1, -1, 1, -1, 1, 1, -1, 1, 1]), gl.STATIC_DRAW);

    var aPos = gl.getAttribLocation(prog, 'aPos');
    gl.enableVertexAttribArray(aPos);
    gl.vertexAttribPointer(aPos, 2, gl.FLOAT, false, 0, 0);

    var uRes     = gl.getUniformLocation(prog, 'uRes');
    var uTempo   = gl.getUniformLocation(prog, 'uTempo');
    var uProg    = gl.getUniformLocation(prog, 'uProg');
    var uRolagem = gl.getUniformLocation(prog, 'uRolagem');
    var uMouse   = gl.getUniformLocation(prog, 'uMouse');
    var uVivo    = gl.getUniformLocation(prog, 'uVivo');

    /* Este é o dono do canvas: o cosmos.js (2D) pergunta por isto e
       sai de cena se encontrar. */
    window.KosmosCosmos = { modo: 'webgl' };

    /* ------------------------------------------------------------
       Estado e laço
       ------------------------------------------------------------ */
    var L = 0, A = 0, escala = 1;
    var alturaDoc = 1;
    var rolagem = 0, alvoMX = 0, alvoMY = 0, mx = 0, my = 0;
    var rodando = true, quadro = 0;

    /* ------------------------------------------------------------
       Um céu só, de página em página
       Cada página liga o seu próprio canvas. Se o tempo do shader
       começasse do zero a cada uma, trocar de aba no dashboard faria
       o céu pular: a Via Láctea e o pulso das estrelas voltavam ao
       ponto inicial, e o fundo corria até o lugar do mouse. Com a
       View Transition cruzando as duas páginas, isso aparecia como
       dois céus sobrepostos.

       Então o relógio conta desde a PRIMEIRA página desta visita
       (guardado no sessionStorage), e a posição do mouse passa de uma
       página para a outra. Só onde o fragment shader tem highp: em
       mediump, um tempo de horas perde precisão e as estrelas
       travariam — lá cada página continua começando do zero.
       ------------------------------------------------------------ */
    var desvioMs = 0;
    var precisao = gl.getShaderPrecisionFormat(gl.FRAGMENT_SHADER, gl.HIGH_FLOAT);
    var altaPrecisao = !!precisao && precisao.precision >= 23;

    function lerSessao(chave) {
        try { return sessionStorage.getItem(chave); } catch (e) { return null; }
    }
    function gravarSessao(chave, valor) {
        try { sessionStorage.setItem(chave, valor); } catch (e) { /* aba privada */ }
    }

    if (altaPrecisao && performance.timeOrigin) {
        var inicio = Number(lerSessao('kosmos_ceu_t0'));
        if (!inicio || inicio > performance.timeOrigin) {
            inicio = performance.timeOrigin;
            gravarSessao('kosmos_ceu_t0', String(inicio));
        }
        desvioMs = performance.timeOrigin - inicio;
    }

    if (ponteiroFino) {
        var mouseSalvo = (lerSessao('kosmos_ceu_mouse') || '').split(',').map(Number);
        if (mouseSalvo.length === 2 && isFinite(mouseSalvo[0]) && isFinite(mouseSalvo[1])) {
            alvoMX = mx = mouseSalvo[0];
            alvoMY = my = mouseSalvo[1];
        }
        window.addEventListener('pagehide', function () {
            gravarSessao('kosmos_ceu_mouse', mx.toFixed(4) + ',' + my.toFixed(4));
        });
    }

    function medir() {
        L = window.innerWidth;
        A = window.innerHeight;

        /* O shader roda por pixel: em tela cheia com DPR 3 seriam 4x
           mais pixels do que a olho nu se percebe num fundo desfocado.
           Meia resolução no celular, 1.5x no computador. */
        var dpr = window.devicePixelRatio || 1;
        /* O shader ficou mais caro (Via Láctea + 4 camadas de estrelas),
           então renderiza abaixo da resolução da tela e o navegador
           amplia. Num fundo desfocado ninguém percebe, e o custo cai
           com o quadrado da escala. */
        escala = ponteiroFino ? Math.min(dpr, 1.25) : Math.min(dpr, 1.0) * 0.6;
        if (calmo) escala *= 0.72;        // o custo cai com o QUADRADO disto

        canvas.width = Math.max(1, Math.round(L * escala));
        canvas.height = Math.max(1, Math.round(A * escala));
        canvas.style.width = L + 'px';
        canvas.style.height = A + 'px';

        gl.viewport(0, 0, canvas.width, canvas.height);
        gl.uniform2f(uRes, canvas.width, canvas.height);

        alturaDoc = Math.max(1, document.documentElement.scrollHeight - A);
    }

    function desenhar(ms) {
        gl.uniform1f(uTempo, (ms + desvioMs) * 0.001);
        gl.uniform1f(uProg, Math.min(1, Math.max(0, rolagem / alturaDoc)));
        gl.uniform1f(uRolagem, rolagem);
        gl.uniform2f(uMouse, mx, my);
        gl.uniform1f(uVivo, semMovimento ? 0.0 : 1.0);
        gl.drawArrays(gl.TRIANGLES, 0, 6);
    }

    function passo(ms) {
        if (!rodando) return;
        quadro++;

        // no toque — e no modo calmo — metade dos quadros
        if ((!ponteiroFino || calmo) && quadro % 2) {
            requestAnimationFrame(passo);
            return;
        }

        // perseguição suave: o fundo acompanha em vez de saltar
        rolagem += (window.scrollY - rolagem) * 0.08;
        mx += (alvoMX - mx) * 0.05;
        my += (alvoMY - my) * 0.05;

        desenhar(ms);
        requestAnimationFrame(passo);
    }

    /* ------------------------------------------------------------
       Ligações
       ------------------------------------------------------------ */
    var remedir = null;
    window.addEventListener('resize', function () {
        clearTimeout(remedir);
        remedir = setTimeout(function () { medir(); if (semMovimento) desenhar(0); }, 180);
    });

    if (ponteiroFino && !semMovimento) {
        window.addEventListener('mousemove', function (e) {
            alvoMX = (e.clientX / L) * 2 - 1;
            alvoMY = (e.clientY / A) * 2 - 1;
        }, { passive: true });
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            rodando = false;
        } else if (!semMovimento) {
            rodando = true;
            requestAnimationFrame(passo);
        }
    });

    /* As seções pinadas mudam a altura do documento: sem remedir, a
       cor do céu erraria o ponto da jornada. */
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
        rolagem = 0;
        desenhar(0);                     // um quadro bonito e parado
    } else {
        requestAnimationFrame(passo);
    }
})();
