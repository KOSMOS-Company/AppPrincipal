/* ============================================================
   KOSMOS — intro.js (Mini Desenho Animado Introdutório)
   Logo Oficial: K maiúsculo + planeta no 1º 'o' + smos minúsculo.
   Roteiro animado:
     • 0.0s: Starfield cósmico suave + portal de luz se expande
     • 0.3s: Mascote 3D entra em voo acrobático com curva e frenagem elástica
     • 1.5s: Flash de estrela na lente dos óculos
     • 1.8s: Logo oficial K<o>smos se materializa com glow púrpura
     • 2.2s: Mascote dá piscadinha, ergue o capelo e balão de fala pop surge
     • 2.4s: Ferramentas de estudo (Pomodoro, Flashcards, Resumos) entram em órbita ao redor dele
     • 2.7s: Mascote abre sorrisão e tagline + saudação personalizada acendem
     • 3.0s: Modo interativo completo (pupilas seguem o cursor, supernovas no céu)
     • Saída: Mascote e universo saltam em dobra espacial (hyperspace dive)
   ============================================================ */

(function () {
    const overlay = document.getElementById("intro");
    if (!overlay) return;

    // Se não veio do login/cadastro, não executa
    if (!document.documentElement.classList.contains("com-intro")) {
        overlay.remove();
        return;
    }

    sessionStorage.removeItem("kosmos_intro"); // não repete ao recarregar (F5)

    const reduzMov = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const DURACAO = reduzMov ? 2600 : 5400; // tempo total do filminho (ms)

    /* ---------- Céu em warp 3D (estrelas viajando em direção à tela) ---------- */
    function criarWarp(canvas) {
        const ctx = canvas.getContext("2d");
        if (!ctx) return null;

        const QTD = 260;
        let larg, alt, cx, cy, escala;
        let estrelas = [];
        let fagulhas = [];      // partículas de supernovas (cliques)
        let raf = null;

        let vel = 0.0025;       // velocidade inicial suave
        let velAlvo = 0.012;    // velocidade de cruzeiro cósmico
        let impulso = 0;        // aceleração dinâmica

        // Parallax suave perseguindo o cursor
        let miraX = 0, miraY = 0;
        let desvX = 0, desvY = 0;

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
                roxa: Math.random() < 0.45
            };
        }

        function quadro() {
            vel += (velAlvo + impulso - vel) * 0.035;
            impulso *= 0.92;
            desvX += (miraX - desvX) * 0.05;
            desvY += (miraY - desvY) * 0.05;
            const fx = cx + desvX, fy = cy + desvY;

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

                const prox = 1 - e.z;
                ctx.strokeStyle = e.roxa
                    ? "rgba(201, 124, 255," + (0.25 + prox * 0.75) + ")"
                    : "rgba(232, 213, 255," + (0.2 + prox * 0.8) + ")";
                ctx.lineWidth = prox * 2.5 + 0.3;
                ctx.beginPath();
                ctx.moveTo(x1, y1);
                ctx.lineTo(x2, y2);
                ctx.stroke();
            }

            // Fagulhas de supernova no clique
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
                ctx.lineWidth = f.vida * 2.4 + 0.4;
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
            mirar(px, py) {
                miraX = (px - cx) * 0.14;
                miraY = (py - cy) * 0.14;
            },
            explodir(px, py) {
                const n = 28 + Math.floor(Math.random() * 10);
                for (let i = 0; i < n; i++) {
                    fagulhas.push({
                        x: px, y: py,
                        ang: (Math.PI * 2 * i) / n + Math.random() * 0.4,
                        v: 2.5 + Math.random() * 5.5,
                        vida: 0.9 + Math.random() * 0.1,
                        roxa: Math.random() < 0.5
                    });
                }
                impulso = Math.min(impulso + 0.032, 0.065);
            },
            turbinar() { velAlvo = 0.12; },
            parar() {
                if (raf) cancelAnimationFrame(raf);
                window.removeEventListener("resize", medir);
            }
        };
    }

    const canvas = document.getElementById("introCeu");
    const warp = (!reduzMov && canvas) ? criarWarp(canvas) : null;

    /* ---------- Parallax do céu perseguindo o mouse ---------- */
    function seguirCursor(ev) {
        if (warp) warp.mirar(ev.clientX, ev.clientY);
    }
    if (!reduzMov) window.addEventListener("pointermove", seguirCursor);

    /* ---------- Roteiro do Mini Desenho Animado ---------- */
    const hero = document.getElementById("introMascoteHero");
    const balao = document.getElementById("introBalao");
    const mascoteEl = document.getElementById("introMascote");

    if (!reduzMov) {
        // Cena 2: Flash de estrela na lente dos óculos (1.5s)
        setTimeout(function () {
            if (hero) hero.classList.add("intro--piscou");
        }, 1500);

        // Cena 3: Ativa órbitas de ferramentas e balão de fala pop (2.2s)
        setTimeout(function () {
            if (overlay) overlay.classList.add("intro--revelou");
            if (hero) hero.classList.add("intro--balao-on");
            if (mascoteEl && mascoteEl.mascote) mascoteEl.mascote.piscar(280);
        }, 2200);

        // Cena 4: Mascote abre sorrisão feliz (2.7s)
        setTimeout(function () {
            if (mascoteEl && mascoteEl.mascote) mascoteEl.mascote.humor("feliz");
        }, 2700);

        // Cena 5: Recolhe o balão suavemente antes da dobra espacial (4.4s)
        setTimeout(function () {
            if (hero) hero.classList.remove("intro--balao-on");
        }, 4400);
    } else {
        if (overlay) overlay.classList.add("intro--revelou");
        if (hero) hero.classList.add("intro--balao-on");
    }

    /* ---------- Interação: clicar no mascote durante a animação ---------- */
    if (hero) {
        hero.addEventListener("pointerdown", function (ev) {
            ev.stopPropagation(); // não dispara supernova
            if (mascoteEl && mascoteEl.mascote) {
                mascoteEl.mascote.humor("feliz");
                mascoteEl.mascote.piscar(280);
                if (balao) {
                    balao.textContent = "Vamos nessa! ✦";
                    hero.classList.add("intro--balao-on");
                }
                setTimeout(function () {
                    if (mascoteEl && mascoteEl.mascote) mascoteEl.mascote.humor("normal");
                }, 1600);
            }
        });
    }

    /* ---------- Supernova: clique no céu estrelado ---------- */
    overlay.addEventListener("pointerdown", function (ev) {
        if (ev.target.closest("#introPular")) return;
        if (warp) warp.explodir(ev.clientX, ev.clientY);
    });

    /* ---------- Tagline em Typewriter ---------- */
    const tag = document.getElementById("introTag");
    if (tag) {
        const texto = tag.dataset.texto || "Estudar nunca foi tão envolvente.";
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
            }, 2300);
        }
    }

    /* ---------- Saudação Personalizada ---------- */
    const ola = document.getElementById("introOla");
    let tentativas = 0;
    (function esperarNome() {
        const nome = sessionStorage.getItem("kosmos_usuario");
        if (nome && ola) {
            ola.textContent = "Boas-vindas ao seu universo, " + nome.trim().split(" ")[0] + "!";
            ola.classList.add("intro__ola--on");
            return;
        }
        if (++tentativas < 16) setTimeout(esperarNome, 150);
    })();

    /* ---------- Barra de Progresso do Tempo Restante ---------- */
    const barra = document.getElementById("introBarra");
    if (barra) barra.style.animationDuration = DURACAO + "ms";

    /* ---------- Saída em Salto Hyperspace (Dobra Espacial) ---------- */
    function encerrar() {
        if (overlay.dataset.saindo) return;
        overlay.dataset.saindo = "1";
        if (warp) warp.turbinar();
        overlay.classList.add("intro--saindo");
        document.documentElement.classList.remove("com-intro");
        document.documentElement.classList.add("intro-saida");
        window.removeEventListener("keydown", pularPorTecla);
        window.removeEventListener("pointermove", seguirCursor);
        overlay.addEventListener("transitionend", finalizar, { once: true });
        setTimeout(finalizar, 1000);
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
