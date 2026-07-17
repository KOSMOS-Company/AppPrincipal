/* ============================================================
   KOSMOS — intro.js  (V2 "viagem espacial")
   Controla o splash de boas-vindas (toca uma vez após o login).
   A classe .com-intro já é adicionada no <head> da index para
   evitar "flash" — aqui rodamos o starfield warp no canvas,
   mostramos a saudação com o nome e agendamos a saída
   (clique ou tecla pula).
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
        let raf = null;

        let vel = 0.002;        // começa lenta…
        let velAlvo = 0.011;    // …acelera de leve durante a intro

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
            vel += (velAlvo - vel) * 0.03;

            // rastro: escurece com transparência em vez de limpar
            ctx.fillStyle = "rgba(6, 0, 12, 0.42)";
            ctx.fillRect(0, 0, larg, alt);

            for (const e of estrelas) {
                const zAntes = e.z;
                e.z -= vel;
                if (e.z <= 0.02) {
                    Object.assign(e, novaEstrela(1));
                    continue;
                }
                const x1 = cx + (e.x / zAntes) * escala;
                const y1 = cy + (e.y / zAntes) * escala;
                const x2 = cx + (e.x / e.z) * escala;
                const y2 = cy + (e.y / e.z) * escala;

                const prox = 1 - e.z; // 0 = longe, 1 = perto
                ctx.strokeStyle = e.roxa
                    ? "rgba(201, 124, 255," + (0.25 + prox * 0.75) + ")"
                    : "rgba(232, 213, 255," + (0.2 + prox * 0.8) + ")";
                ctx.lineWidth = prox * 2.4 + 0.3;
                ctx.lineCap = "round";
                ctx.beginPath();
                ctx.moveTo(x1, y1);
                ctx.lineTo(x2, y2);
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
            turbinar() { velAlvo = 0.11; },       // hyperspace na saída
            parar() {
                if (raf) cancelAnimationFrame(raf);
                window.removeEventListener("resize", medir);
            }
        };
    }

    const canvas = document.getElementById("introCeu");
    const warp = (!reduzMov && canvas) ? criarWarp(canvas) : null;

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

    /* ---------- Saída ---------- */
    function encerrar() {
        if (overlay.dataset.saindo) return;
        overlay.dataset.saindo = "1";
        if (warp) warp.turbinar();
        overlay.classList.add("intro--saindo");
        document.documentElement.classList.remove("com-intro");
        document.documentElement.classList.add("intro-saida"); // reveal do dashboard
        window.removeEventListener("keydown", pularPorTecla);
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

    overlay.addEventListener("click", encerrar); // clique pula
    window.addEventListener("keydown", pularPorTecla);
    setTimeout(encerrar, DURACAO);
})();
