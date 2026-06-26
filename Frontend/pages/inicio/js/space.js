/* ============================================================
   KOSMOS — space.js
   Fundo interativo de "espaço" no hero: estrelas com parallax
   pelo mouse, deriva lenta e linhas que ligam ao cursor.
   Canvas 2D puro (sem bibliotecas).
   ============================================================ */

(function () {
    const canvas = document.getElementById("heroSpace");
    if (!canvas) return;

    const ctx = canvas.getContext("2d");
    const semMovimento = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    let w = 0, h = 0, estrelas = [];
    let alvoX = 0, alvoY = 0, mx = 0, my = 0;   // parallax (mouse normalizado)
    let curX = -999, curY = -999;               // cursor em px (para as linhas)

    function criarEstrelas() {
        const qtd = Math.min(170, Math.round((w * h) / 9000));
        estrelas = [];
        for (let i = 0; i < qtd; i++) {
            estrelas.push({
                x: Math.random() * w,
                y: Math.random() * h,
                z: Math.random() * 0.9 + 0.2,    // profundidade
                r: Math.random() * 1.3 + 0.4,    // raio base
                roxa: Math.random() < 0.35,      // algumas em roxo
            });
        }
    }

    function redimensionar() {
        w = canvas.width = canvas.offsetWidth;
        h = canvas.height = canvas.offsetHeight;
        criarEstrelas();
    }

    window.addEventListener("mousemove", (e) => {
        alvoX = e.clientX / window.innerWidth - 0.5;
        alvoY = e.clientY / window.innerHeight - 0.5;
        const r = canvas.getBoundingClientRect();
        curX = e.clientX - r.left;
        curY = e.clientY - r.top;
    });
    window.addEventListener("mouseleave", () => { curX = -999; curY = -999; });

    function desenhar() {
        ctx.clearRect(0, 0, w, h);

        // suaviza o parallax
        mx += (alvoX - mx) * 0.05;
        my += (alvoY - my) * 0.05;

        for (const s of estrelas) {
            // deriva lenta para cima
            s.y -= s.z * 0.12;
            if (s.y < 0) s.y = h;

            const px = s.x + mx * s.z * 45;
            const py = s.y + my * s.z * 45;

            // linha até o cursor (efeito constelação)
            if (curX > -900) {
                const dx = px - curX, dy = py - curY;
                const dist = Math.hypot(dx, dy);
                if (dist < 130) {
                    ctx.strokeStyle = "rgba(165,65,255," + (1 - dist / 130) * 0.25 + ")";
                    ctx.lineWidth = 0.6;
                    ctx.beginPath();
                    ctx.moveTo(px, py);
                    ctx.lineTo(curX, curY);
                    ctx.stroke();
                }
            }

            const alpha = 0.3 + s.z * 0.6;
            ctx.beginPath();
            ctx.arc(px, py, s.r * s.z, 0, Math.PI * 2);
            ctx.fillStyle = s.roxa
                ? "rgba(197,124,255," + alpha + ")"
                : "rgba(235,225,255," + alpha + ")";
            ctx.fill();
        }

        requestAnimationFrame(desenhar);
    }

    redimensionar();
    window.addEventListener("resize", redimensionar);

    if (semMovimento) {
        // quadro estático para quem prefere menos movimento
        for (const s of estrelas) {
            ctx.beginPath();
            ctx.arc(s.x, s.y, s.r * s.z, 0, Math.PI * 2);
            ctx.fillStyle = "rgba(235,225,255," + (0.3 + s.z * 0.5) + ")";
            ctx.fill();
        }
    } else {
        requestAnimationFrame(desenhar);
    }
})();
