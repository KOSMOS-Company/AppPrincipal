/* ============================================================
   KOSMOS — cursor.js  (cursor do dashboard: anel + ponto)
   ============================================================ */

(() => {
    if (document.querySelector(".dash-cursor")) return;

    const reduzido = window.matchMedia("(prefers-reduced-motion: reduce)");
    const toque    = window.matchMedia("(pointer: coarse)");
    if (reduzido.matches || toque.matches) return;

    // Esconde o cursor nativo apenas quando o efeito está ativo
    document.documentElement.classList.add("has-dash-cursor");

    const anel = document.createElement("div");
    anel.className = "dash-cursor";
    anel.setAttribute("aria-hidden", "true");

    const ponto = document.createElement("div");
    ponto.className = "dash-cursor__dot";
    ponto.setAttribute("aria-hidden", "true");

    document.body.append(anel, ponto);

    let alvoX = window.innerWidth / 2,  alvoY = window.innerHeight / 2;
    let anelX = alvoX,                  anelY = alvoY;

    // Ponto acompanha na hora; anel persegue com easing
    window.addEventListener("mousemove", (e) => {
        alvoX = e.clientX;
        alvoY = e.clientY;
        ponto.style.transform = `translate3d(${alvoX}px, ${alvoY}px, 0)`;
    }, { passive: true });

    const loop = () => {
        anelX += (alvoX - anelX) * 0.18;
        anelY += (alvoY - anelY) * 0.18;
        anel.style.transform = `translate3d(${anelX}px, ${anelY}px, 0)`;
        window.requestAnimationFrame(loop);
    };
    window.requestAnimationFrame(loop);

    // Reação a elementos interativos
    const interativos = 'a, button, input, select, textarea, label, .chip, .alt, .flashcard, .deck-card, .resumo-card, [role="button"]';
    document.addEventListener("mouseover", (e) => {
        if (e.target.closest(interativos)) anel.classList.add("dash-cursor--hover");
    });
    document.addEventListener("mouseout", (e) => {
        if (e.target.closest(interativos)) anel.classList.remove("dash-cursor--hover");
    });

    // Feedback de clique
    document.addEventListener("mousedown", () => anel.classList.add("dash-cursor--down"));
    document.addEventListener("mouseup",   () => anel.classList.remove("dash-cursor--down"));

    // Some ao sair da janela
    document.addEventListener("mouseleave", () => {
        anel.style.opacity = "0";
        ponto.style.opacity = "0";
    });
    document.addEventListener("mouseenter", () => {
        anel.style.opacity = "";
        ponto.style.opacity = "";
    });
})();
