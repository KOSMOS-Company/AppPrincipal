/* ============================================================
   KOSMOS — intro.js  (V3 "viagem espacial interativa")
   Controla o splash de boas-vindas (toca uma vez após o login).
   A classe .com-intro já é adicionada no <head> da index para
   evitar "flash". Interações:
     • o warp de estrelas inclina na direção do cursor (parallax)
     • clicar/tocar no céu cria uma supernova + acelera as estrelas
     • as pupilas do planetinha seguem o cursor (alma da mascote)
     • tagline em typewriter; botão "Pular intro" com barra de
       progresso do tempo restante (Esc/Enter/espaço também pulam)
   ============================================================ */

(function () {
    const overlay = document.getElementById("intro");
    if (!overlay) return;

    // Se não veio do login, não mostra nada
    if (!document.documentElement.classList.contains("com-intro")) {
        overlay.remove();
        return;
    }

    sessionStorage.removeItem("kosmos_intro"); // não repete ao recarregar

    const reduzMov = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const DURACAO = reduzMov ? 2600 : 4400; // tempo visível antes de sumir (ms)

    /* ---------- Céu em warp (estrelas viajando na direção da tela) ---------- */
    function criarWarp(canvas) {
        const ctx = canvas.getContext("2d");
        if (!ctx) return null;

        const QTD = 240;
        let larg, alt, cx, cy, escala;
        let estrelas = [];
        let fagulhas = [];      // partículas das supernovas (clique no céu)
        let raf = null;

        let vel = 0.002;        // começa lenta…
        let velAlvo = 0.011;    // …acelera de leve durante a intro
        let impulso = 0;        // "chute" extra a cada supernova (decai)

        // parallax: o ponto de fuga persegue o cursor com suavidade
        let miraX = 0, miraY = 0;   // alvo (deslocamento a partir do centro)
        let desvX = 0, desvY = 0;   // posição atual (eased)

        function medir() {
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            larg = window.innerWidth;
            alt = window.innerHeight;
            canvas.width = larg * dpr;
            canvas.height = alt * dpr;
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            cx = larg / 2;
            cy = alt / 2;
            escala = Math.max(larg, alt) * 0.5;
        }

        function novaEstrela(zInicial) {
            return {
                x: Math.random() * 2 - 1,
                y: Math.random() * 2 - 1,
                z: zInicial !== undefined ? zInicial : Math.random() * 0.9 + 0.1,
                roxa: Math.random() < 0.45 // mistura branco + roxo da marca
            };
        }

        function quadro() {
            vel += (velAlvo + impulso - vel) * 0.03;
            impulso *= 0.92;
            desvX += (miraX - desvX) * 0.05;
            desvY += (miraY - desvY) * 0.05;
            const fx = cx + desvX, fy = cy + desvY; // ponto de fuga da vez

            // rastro: escurece com transparência em vez de limpar
            ctx.fillStyle = "rgba(6, 0, 12, 0.42)";
            ctx.fillRect(0, 0, larg, alt);
            ctx.lineCap = "round";

            for (const e of estrelas) {
                const zAntes = e.z;
                e.z -= vel;
                if (e.z <= 0.02) {
                    Object.assign(e, novaEstrela(1));
                    continue;
                }
                const x1 = fx + (e.x / zAntes) * escala;
                const y1 = fy + (e.y / zAntes) * escala;
                const x2 = fx + (e.x / e.z) * escala;
                const y2 = fy + (e.y / e.z) * escala;

                const prox = 1 - e.z; // 0 = longe, 1 = perto
                ctx.strokeStyle = e.roxa
                    ? "rgba(201, 124, 255," + (0.25 + prox * 0.75) + ")"
                    : "rgba(232, 213, 255," + (0.2 + prox * 0.8) + ")";
                ctx.lineWidth = prox * 2.4 + 0.3;
                ctx.beginPath();
                ctx.moveTo(x1, y1);
                ctx.lineTo(x2, y2);
                ctx.stroke();
            }

            // fagulhas de supernova (voam do ponto do clique e apagam)
            for (let i = fagulhas.length - 1; i >= 0; i--) {
                const f = fagulhas[i];
                const rastroX = f.x, rastroY = f.y;
                f.x += Math.cos(f.ang) * f.v;
                f.y += Math.sin(f.ang) * f.v;
                f.v *= 0.955;
                f.vida -= 0.022;
                if (f.vida <= 0) { fagulhas.splice(i, 1); continue; }
                ctx.strokeStyle = f.roxa
                    ? "rgba(201, 124, 255," + f.vida + ")"
                    : "rgba(255, 255, 255," + f.vida + ")";
                ctx.lineWidth = f.vida * 2.2 + 0.4;
                ctx.beginPath();
                ctx.moveTo(rastroX, rastroY);
                ctx.lineTo(f.x, f.y);
                ctx.stroke();
            }

            raf = requestAnimationFrame(quadro);
        }

        medir();
        window.addEventListener("resize", medir);
        for (let i = 0; i < QTD; i++) estrelas.push(novaEstrela());
        ctx.fillStyle = "#06000c";
        ctx.fillRect(0, 0, larg, alt);
        raf = requestAnimationFrame(quadro);

        return {
            mirar(px, py) {                        // parallax segue o cursor
                miraX = (px - cx) * 0.14;
                miraY = (py - cy) * 0.14;
            },
            explodir(px, py) {                     // supernova no clique
                const n = 26 + Math.floor(Math.random() * 8);
                for (let i = 0; i < n; i++) {
                    fagulhas.push({
                        x: px, y: py,
                        ang: (Math.PI * 2 * i) / n + Math.random() * 0.4,
                        v: 2.5 + Math.random() * 5,
                        vida: 0.9 + Math.random() * 0.1,
                        roxa: Math.random() < 0.5
                    });
                }
                impulso = Math.min(impulso + 0.028, 0.06); // chute de velocidade
            },
            turbinar() { velAlvo = 0.11; },        // hyperspace na saída
            parar() {
                if (raf) cancelAnimationFrame(raf);
                window.removeEventListener("resize", medir);
            }
        };
    }

    const canvas = document.getElementById("introCeu");
    const warp = (!reduzMov && canvas) ? criarWarp(canvas) : null;

    /* ---------- Pupilas do planetinha seguem o cursor ---------- */
    const pupilas = document.getElementById("introPupilas");
    const olhos = document.getElementById("introOlhos");

    function seguirCursor(ev) {
        if (warp) warp.mirar(ev.clientX, ev.clientY);
        if (!pupilas || !olhos || reduzMov) return;
        const r = olhos.getBoundingClientRect();
        if (!r.width) return;
        const dx = ev.clientX - (r.left + r.width / 2);
        const dy = ev.clientY - (r.top + r.height / 2);
        const dist = Math.hypot(dx, dy) || 1;
        const passo = Math.min(dist / 90, 1) * 1.8; // até 1.8 unidades do SVG
        pupilas.style.transform =
            "translate(" + (dx / dist) * passo + "px," + (dy / dist) * passo + "px)";
    }
    if (!reduzMov) window.addEventListener("pointermove", seguirCursor);

    /* ---------- Supernova: clique/toque no céu (fora do botão) ---------- */
    overlay.addEventListener("pointerdown", function (ev) {
        if (ev.target.closest("#introPular")) return; // botão cuida do skip
        if (warp) warp.explodir(ev.clientX, ev.clientY);
    });

    /* ---------- Tagline em typewriter ---------- */
    const tag = document.getElementById("introTag");
    if (tag) {
        const texto = tag.dataset.texto || "";
        if (reduzMov) {
            tag.textContent = texto;
        } else {
            setTimeout(function () {
                tag.textContent = "";
                tag.classList.add("intro__tag--digitando");
                let i = 0;
                (function digitar() {
                    if (i <= texto.length) {
                        tag.textContent = texto.slice(0, i++);
                        setTimeout(digitar, 28);
                    } else {
                        setTimeout(function () {
                            tag.classList.remove("intro__tag--digitando");
                        }, 700);
                    }
                })();
            }, 1100); // entra junto com o fade do .intro__tag
        }
    }

    /* ---------- Saudação: o dashboard.js grava kosmos_usuario (async) ---------- */
    const ola = document.getElementById("introOla");
    let tentativas = 0;
    (function esperarNome() {
        const nome = sessionStorage.getItem("kosmos_usuario");
        if (nome && ola) {
            ola.textContent = "Boas-vindas, " + nome.trim().split(" ")[0] + "!";
            ola.classList.add("intro__ola--on");
            return;
        }
        if (++tentativas < 14) setTimeout(esperarNome, 150); // tenta por ~2s
    })();

    /* ---------- Barra de progresso do botão (tempo restante) ---------- */
    const barra = document.getElementById("introBarra");
    if (barra) barra.style.animationDuration = DURACAO + "ms";

    /* ---------- Saída ---------- */
    function encerrar() {
        if (overlay.dataset.saindo) return;
        overlay.dataset.saindo = "1";
        if (warp) warp.turbinar();
        overlay.classList.add("intro--saindo");
        document.documentElement.classList.remove("com-intro");
        document.documentElement.classList.add("intro-saida"); // reveal do dashboard
        window.removeEventListener("keydown", pularPorTecla);
        window.removeEventListener("pointermove", seguirCursor);
        overlay.addEventListener("transitionend", finalizar, { once: true });
        setTimeout(finalizar, 1100); // fallback alinhado à saída de .9s
    }

    function finalizar() {
        if (warp) warp.parar();
        overlay.remove();
    }

    function pularPorTecla(ev) {
        if (ev.key === "Escape" || ev.key === "Enter" || ev.key === " ") encerrar();
    }

    const botaoPular = document.getElementById("introPular");
    if (botaoPular) botaoPular.addEventListener("click", encerrar);
    window.addEventListener("keydown", pularPorTecla);
    setTimeout(encerrar, DURACAO);
})();
