/* ============================================================
   KOSMOS — resumos.js  (a lista)
   Cuida do grid e dos filtros. Clicar no cartão vai para a prévia
   (resumo.php); o lápis abre o editor aqui mesmo.

   Quem escreve/salva/apaga é o resumo-form.js (compartilhado com a
   prévia); esta lista só escuta o resultado:
     "resumo:salvo"   -> insere/atualiza o cartão
     "resumo:apagado" -> tira o cartão
   ============================================================ */
(() => {
    "use strict";

    const grid    = document.getElementById("grid");
    const vazio   = document.getElementById("vazio");
    const filtros = document.getElementById("filtros");
    if (!grid) return;

    /* A lista completa veio pronta no HTML (resumos.php) */
    let resumos = [];
    try {
        resumos = JSON.parse(document.getElementById("dadosResumos")?.textContent || "[]");
    } catch (err) {
        resumos = [];
    }

    let filtroAtual = "todos";

    document.addEventListener("DOMContentLoaded", () => {
        document.getElementById("btnNovo")?.addEventListener("click", () => {
            window.KosmosResumoForm?.abrir(null);
        });

        // o lápis de cada cartão abre o editor (delegação: os cartões
        // são recriados quando a lista muda)
        grid.addEventListener("click", (e) => {
            const lapis = e.target.closest("[data-editar]");
            if (!lapis) return;
            e.preventDefault();
            const id = Number(lapis.dataset.editar);
            const resumo = resumos.find((r) => r.id === id);
            if (resumo) window.KosmosResumoForm?.abrir(resumo);
        });

        ativarFiltros();
    });

    /* ------------------------------------------------------------
       Resultado do editor
       ------------------------------------------------------------ */
    document.addEventListener("resumo:salvo", (e) => {
        const salvo = e.detail?.resumo;
        if (!salvo) return;

        const i = resumos.findIndex((r) => r.id === salvo.id);
        if (i >= 0) {
            resumos[i] = salvo;
        } else {
            resumos.unshift(salvo);
            filtroAtual = "todos";
        }
        desenhar();
    });

    document.addEventListener("resumo:apagado", (e) => {
        const id = e.detail?.id;
        if (!id) return;
        resumos = resumos.filter((r) => r.id !== id);
        desenhar();
    });

    /* ------------------------------------------------------------
       Grid
       ------------------------------------------------------------ */
    const ICONE_DOC = '<svg viewBox="0 0 24 24" fill="none"><rect x="5" y="3" width="14" height="18" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M9 8h6M9 12h6M9 16h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>';
    const ICONE_LAPIS = '<svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 16h3l8-8-3-3-8 8v3Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M12.5 4.5l3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>';

    function desenhar() {
        const lista = resumos.filter(
            (r) => filtroAtual === "todos" || r.materia === filtroAtual
        );

        grid.innerHTML = "";
        vazio.hidden = lista.length > 0;

        lista.forEach((r, i) => {
            const card = document.createElement("article");
            card.className = "resumo-card anim-in";
            card.style.animationDelay = `${i * 0.04}s`;
            card.dataset.id = r.id;
            card.dataset.materia = r.materia;
            card.innerHTML = `
                <a class="resumo-card__link" href="resumo.php?id=${r.id}">
                    <div class="resumo-card__thumb">${ICONE_DOC}</div>
                    <div class="resumo-card__body">
                        <span class="materia-tag">${escapar(r.materia)}</span>
                        <h3 class="resumo-card__title">${escapar(r.titulo)}</h3>
                        <div class="resumo-card__meta">
                            <span>${escapar(r.quando || "hoje")}</span>
                            <span>Ler →</span>
                        </div>
                    </div>
                </a>
                <button type="button" class="resumo-card__editar" data-editar="${r.id}"
                        title="Editar este resumo" aria-label="Editar o resumo ${escapar(r.titulo)}">
                    ${ICONE_LAPIS}
                </button>`;
            grid.appendChild(card);
        });

        atualizarFiltros();
        atualizarContagem();
    }

    /* Mantém os chips coerentes com o que existe agora */
    function atualizarFiltros() {
        if (!filtros) return;
        const materias = [...new Set(resumos.map((r) => r.materia))].sort();

        if (materias.length < 2) {
            filtros.hidden = true;
            filtroAtual = "todos";
            return;
        }

        filtros.hidden = false;
        const atual = materias.includes(filtroAtual) ? filtroAtual : "todos";
        filtroAtual = atual;

        filtros.innerHTML =
            `<button class="chip${atual === "todos" ? " active" : ""}" data-materia="todos">Todos</button>` +
            materias.map((m) =>
                `<button class="chip${m === atual ? " active" : ""}" data-materia="${escapar(m)}">${escapar(m)}</button>`
            ).join("");
    }

    /* "3 resumos salvos na sua conta" no cabeçalho */
    function atualizarContagem() {
        const p = document.querySelector(".contCabeca__texto p");
        if (!p) return;
        const n = resumos.length;
        p.textContent = n === 0
            ? "Escreva o que você estudou — fica salvo na sua conta."
            : `${n} ${n === 1 ? "resumo salvo" : "resumos salvos"} na sua conta.`;
    }

    function ativarFiltros() {
        if (!filtros) return;
        filtros.addEventListener("click", (e) => {
            const chip = e.target.closest(".chip");
            if (!chip) return;
            filtros.querySelectorAll(".chip").forEach((c) => c.classList.remove("active"));
            chip.classList.add("active");
            filtroAtual = chip.dataset.materia;
            desenhar();
        });
    }

    /* O texto vem do banco: escapamos antes de jogar em innerHTML */
    function escapar(texto) {
        const div = document.createElement("div");
        div.textContent = texto ?? "";
        return div.innerHTML;
    }
})();
