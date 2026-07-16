/* ============================================================
   KOSMOS — cursor.js
   Cursor personalizado: ponto sólido (segue na hora) + anel
   que persegue com leve atraso. Realça sobre elementos
   clicáveis e dá feedback no clique. Canvas/JS puro.
   Desativado em toque, telas pequenas e prefers-reduced-motion.
   ============================================================ */

(function () {
    const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const smallScreen = window.matchMedia('(max-width: 768px)').matches;
    if (!finePointer || reduceMotion || smallScreen) return;

    const root = document.documentElement;
    root.classList.add('kcursor');

    const dot = document.createElement('div');
    dot.className = 'kcursor-dot';
    dot.setAttribute('aria-hidden', 'true');

    const ring = document.createElement('div');
    ring.className = 'kcursor-ring';
    ring.setAttribute('aria-hidden', 'true');

    document.body.appendChild(ring);
    document.body.appendChild(dot);

    // posição do mouse (ponto) e posição interpolada (anel)
    let mx = window.innerWidth / 2, my = window.innerHeight / 2;
    let rx = mx, ry = my;
    let visivel = false;

    window.addEventListener('mousemove', (e) => {
        mx = e.clientX;
        my = e.clientY;
        if (!visivel) {
            visivel = true;
            root.classList.remove('kcursor--out');
            // evita "voar" do centro até o cursor no primeiro movimento
            rx = mx; ry = my;
        }
        dot.style.transform = 'translate3d(' + mx + 'px,' + my + 'px,0)';
    }, { passive: true });

    // anel persegue com atraso
    function loop() {
        rx += (mx - rx) * 0.18;
        ry += (my - ry) * 0.18;
        ring.style.transform = 'translate3d(' + rx + 'px,' + ry + 'px,0)';
        requestAnimationFrame(loop);
    }
    requestAnimationFrame(loop);

    // realce sobre elementos clicáveis
    const seletorClicavel = 'a, button, input[type="submit"], input[type="button"], label, select, summary, [role="button"], .btn, .card, .faq__pergunta, .g_id_signin';
    document.addEventListener('mouseover', (e) => {
        if (e.target.closest && e.target.closest(seletorClicavel)) {
            root.classList.add('kcursor--hover');
        }
    });
    document.addEventListener('mouseout', (e) => {
        if (e.target.closest && e.target.closest(seletorClicavel)) {
            root.classList.remove('kcursor--hover');
        }
    });

    // feedback de clique
    document.addEventListener('mousedown', () => root.classList.add('kcursor--down'));
    document.addEventListener('mouseup', () => root.classList.remove('kcursor--down'));

    // some ao sair da janela
    document.addEventListener('mouseleave', () => {
        visivel = false;
        root.classList.add('kcursor--out');
    });
    document.addEventListener('mouseenter', () => root.classList.remove('kcursor--out'));
})();
