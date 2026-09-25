/* ============================================================
   KOSMOS — apple.js  (par do css/apple.css)
   Duas coisas que o CSS sozinho não faz:
     1. o "scroll edge" do topo no celular (html.apple-rolou);
     2. o arrasto da gaveta "Outras ferramentas": segue o dedo 1:1,
        resiste elástico além do topo, e ao soltar herda a velocidade
        do dedo e projeta o impulso para decidir se fecha ou volta.
   ============================================================ */
(() => {
    const raiz = document.documentElement;

    /* ---------- 1. Scroll edge ---------- */
    let pendente = false;
    const marcarRolagem = () => {
        pendente = false;
        raiz.classList.toggle("apple-rolou", window.scrollY > 4);
    };
    window.addEventListener("scroll", () => {
        if (!pendente) { pendente = true; requestAnimationFrame(marcarRolagem); }
    }, { passive: true });

    /* ---------- 2. Gaveta arrastável ---------- */

    /* Mola de verdade (massa 1), nos dois parâmetros que a Apple usa:
       amortecimento (1 = sem quique) e resposta (s até chegar — não é
       duração). Começa de onde o elemento ESTÁ e com a velocidade que
       ele TEM, por isso pode ser interrompida a qualquer momento. */
    function mola({ de, para, velocidade = 0, amortecimento = 1, resposta = 0.4, aCada, fim }) {
        const k = (2 * Math.PI / resposta) ** 2;
        const c = 4 * Math.PI * amortecimento / resposta;
        let x = de, v = velocidade, antes = performance.now(), id = 0;

        const passo = (agora) => {
            const dt = Math.min((agora - antes) / 1000, 1 / 30);
            antes = agora;
            const n = Math.max(1, Math.ceil(dt * 240));     // subpassos: estável mesmo a 30fps
            const h = dt / n;
            for (let i = 0; i < n; i++) {
                v += (-k * (x - para) - c * v) * h;
                x += v * h;
            }
            if (Math.abs(v) < 4 && Math.abs(x - para) < 0.4) {
                aCada(para);
                id = 0;
                if (fim) fim();
                return;
            }
            aCada(x);
            id = requestAnimationFrame(passo);
        };
        id = requestAnimationFrame(passo);
        return { parar() { if (id) cancelAnimationFrame(id); id = 0; return x; } };
    }

    /* Projeção de impulso — a função da Apple (a mesma da rolagem):
       para onde o painel IRIA se o dedo o tivesse lançado. */
    const projetar = (v, desaceleracao = 0.998) => (v / 1000) * desaceleracao / (1 - desaceleracao);

    /* Elástico: quanto mais além do limite, menos o painel acompanha */
    const elastico = (alem, dimensao, cte = 0.55) =>
        (alem * dimensao * cte) / (dimensao + cte * Math.abs(alem));

    function ativarGaveta() {
        const overlay = document.getElementById("sheetMaisMobile");
        const painel = overlay && overlay.querySelector(".sheet-painel");
        const fechar = document.getElementById("btnFecharSheetMais");
        if (!painel || !fechar) return;

        let arrasto = null;
        let animacao = null;

        const posicionar = (y) => {
            const h = painel.offsetHeight || 1;
            painel.style.transform = `translateY(${y}px)`;
            overlay.style.setProperty("--sheet-p", String(Math.min(1, Math.max(0, 1 - y / h))));
        };
        // Devolve o painel ao CSS sem animar (a posição final já é a do CSS)
        const devolverAoCss = () => {
            painel.style.transition = "none";
            painel.style.transform = "";
            overlay.style.removeProperty("--sheet-p");
            void painel.offsetHeight;
            painel.style.transition = "";
        };
        // Onde o painel está NA TELA agora — inclusive no meio da
        // transição de abrir. É daí que o arrasto começa, sem salto.
        const yNaTela = () => new DOMMatrixReadOnly(getComputedStyle(painel).transform).m42;

        painel.addEventListener("pointerdown", (e) => {
            if (!overlay.classList.contains("aberto")) return;
            if (e.button !== 0 || e.target.closest(".sheet-fechar")) return;

            const y = animacao ? animacao.parar() : yNaTela();
            animacao = null;
            arrasto = {
                id: e.pointerId,
                origem: e.clientY,
                base: y,
                y,
                preso: false,
                pontos: [{ t: e.timeStamp, y: e.clientY }],
            };
            // Pegou no meio do voo: congela ali, na mão da pessoa
            painel.style.transition = "none";
            posicionar(y);
        });

        painel.addEventListener("pointermove", (e) => {
            if (!arrasto || e.pointerId !== arrasto.id) return;
            const dy = e.clientY - arrasto.origem;

            // Histerese: 10px antes de assumir que é arrasto e não toque.
            // Só então captura o ponteiro — capturar no pointerdown
            // mandaria o clique para o painel e o link não abriria.
            if (!arrasto.preso) {
                if (Math.abs(dy) < 10) return;
                arrasto.preso = true;
                arrasto.origem = e.clientY;          // sem pulo de 10px
                painel.setPointerCapture(e.pointerId);
            }

            const cru = arrasto.base + (e.clientY - arrasto.origem);
            arrasto.y = cru < 0 ? elastico(cru, painel.offsetHeight) : cru;
            posicionar(arrasto.y);

            arrasto.pontos.push({ t: e.timeStamp, y: e.clientY });
            while (arrasto.pontos.length > 2 && e.timeStamp - arrasto.pontos[0].t > 100) arrasto.pontos.shift();
        });

        const soltar = (e) => {
            if (!arrasto || e.pointerId !== arrasto.id) return;
            const a = arrasto;
            arrasto = null;

            if (!a.preso) {             // foi um toque: deixa o clique seguir
                if (!animacao) devolverAoCss();
                return;
            }

            // Velocidade dos últimos ~100ms (px/s)
            const p0 = a.pontos[0], p1 = a.pontos[a.pontos.length - 1];
            const dt = (p1.t - p0.t) / 1000;
            const v = dt > 0 ? (p1.y - p0.y) / dt : 0;

            const h = painel.offsetHeight;
            const destinoProjetado = a.y + projetar(v);
            const fecha = e.type !== "pointercancel" && destinoProjetado > h * 0.5;

            if (fecha) {
                // Sai pelo mesmo caminho por onde entrou, na velocidade do dedo
                animacao = mola({
                    de: a.y, para: h, velocidade: Math.max(v, 0),
                    amortecimento: 1, resposta: 0.35,
                    aCada: posicionar,
                    fim: () => { animacao = null; fechar.click(); requestAnimationFrame(devolverAoCss); },
                });
            } else {
                // Volta com um leve quique: houve impulso, a física pede
                animacao = mola({
                    de: a.y, para: 0, velocidade: v,
                    amortecimento: 0.8, resposta: 0.3,
                    aCada: posicionar,
                    fim: () => { animacao = null; devolverAoCss(); },
                });
            }
        };
        painel.addEventListener("pointerup", soltar);
        painel.addEventListener("pointercancel", soltar);

        // Fechou por outro caminho (X, Esc, toque fora) no meio de uma mola
        new MutationObserver(() => {
            if (!overlay.classList.contains("aberto") && animacao) {
                animacao.parar();
                animacao = null;
                devolverAoCss();
            }
        }).observe(overlay, { attributes: true, attributeFilter: ["class"] });
    }

    document.addEventListener("DOMContentLoaded", () => {
        marcarRolagem();
        ativarGaveta();
    });
})();
