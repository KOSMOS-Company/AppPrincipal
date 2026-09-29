/* ============================================================
   KOSMOS — exercicios.js
   exercicios.php -> lista as matérias (estante)
   ============================================================ */

const LETRAS = ["A", "B", "C", "D"];
const BACKEND = "../../../Backend/php";

/* ============================================================
   ESTANTE (exercicios.php) — abre/fecha modal, escuta eventos
   ============================================================ */
const btnNovaMateria = document.getElementById("btnNovaMateria");
const gridMaterias = document.getElementById("gridMaterias");
const vazioMaterias = document.getElementById("vazioMaterias");
const filtros = document.getElementById("filtros");

if (btnNovaMateria) {
    btnNovaMateria.addEventListener("click", () => {
        window.KosmosExercicioMateriaForm?.abrir(null);
    });
}

/* Cartões que já vêm do servidor (PHP) também precisam do lápis.
   Delegação: um clique só, para todos — os novos, criados pelo JS,
   repetem a chamada, mas o `?` do optional chaining segura. */
gridMaterias?.addEventListener("click", (ev) => {
    const btn = ev.target.closest("[data-editar-exercicio-materia]");
    if (!btn) return;
    ev.preventDefault();
    ev.stopPropagation();
    const id = Number(btn.dataset.editarExercicioMateria);
    const dados = document.getElementById("dadosExercicioMaterias");
    if (!dados) return;
    let lista = [];
    try {
        lista = JSON.parse(dados.textContent) || [];
    } catch (_) {
        return;
    }
    const materia = lista.find((m) => m.id === id);
    if (materia) {
        window.KosmosExercicioMateriaForm?.abrir(materia);
    }
});

document.addEventListener("exercicioMateria:salvo", (e) => {
    const { materia, novo } = e.detail;
    if (novo) {
        // insere no começo da grade (ordem 1 = primeiro)
        const card = criarCardMateria(materia);
        if (vazioMaterias) vazioMaterias.hidden = true;
        if (gridMaterias) gridMaterias.prepend(card);
        atualizarContadorFiltro(materia.materia, 1);
    } else {
        // atualiza o card existente
        const card = gridMaterias?.querySelector(`[data-id="${materia.id}"]`);
        if (card) {
            const novoCard = criarCardMateria(materia);
            card.replaceWith(novoCard);
        }
    }
});

document.addEventListener("exercicioMateria:apagado", (e) => {
    const { id } = e.detail;
    const card = gridMaterias?.querySelector(`[data-id="${id}"]`);
    if (card) {
        const materia = card.dataset.materia;
        card.remove();
        atualizarContadorFiltro(materia, -1);
        if (gridMaterias && gridMaterias.children.length === 0 && vazioMaterias) {
            vazioMaterias.hidden = false;
        }
    }
});

/* Filtros por matéria (chips) */
if (filtros) {
    filtros.addEventListener("click", (e) => {
        const chip = e.target.closest(".chip");
        if (!chip) return;
        filtros.querySelectorAll(".chip").forEach(c => c.classList.remove("active"));
        chip.classList.add("active");
        const filtro = chip.dataset.materia;
        gridMaterias?.querySelectorAll(".exercicio-materia-card").forEach(card => {
            if (filtro === "todos" || card.dataset.materia === filtro) {
                card.hidden = false;
            } else {
                card.hidden = true;
            }
        });
    });
}

/* Tudo que a pessoa digitou (nome, descrição, ícone) passa por aqui antes
   do innerHTML — inclusive nos atributos, por isso as aspas também. */
function esc(texto) {
    const mapa = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" };
    return String(texto ?? "").replace(/[&<>"']/g, (c) => mapa[c]);
}

function criarCardMateria(m) {
    const id = Number(m.id) || 0;
    const tpl = document.createElement("template");
    tpl.innerHTML = `
        <article class="exercicio-materia-card anim-in exercicio-materia-card--${esc(m.cor)}"
                 data-id="${id}" data-materia="${esc(m.materia)}">
            <a class="exercicio-materia-card__link" href="exercicio_materia.php?id=${id}" draggable="false">
                <span class="exercicio-materia-card__lombada" aria-hidden="true"></span>
                <div class="exercicio-materia-card__body">
                    <div class="exercicio-materia-card__topo">
                        ${m.icone ? `<span class="exercicio-materia-card__icone" aria-hidden="true">${esc(m.icone)}</span>` : ""}
                        <span class="materia-tag">${esc(m.materia)}</span>
                    </div>
                    <h3 class="exercicio-materia-card__nome">${esc(m.nome)}</h3>
                    ${m.descricao ? `<p class="exercicio-materia-card__desc">${esc(m.descricao)}</p>` : ""}
                    <span class="exercicio-materia-card__abrir">Abrir →</span>
                </div>
            </a>
            <button type="button" class="exercicio-materia-card__editar" data-editar-exercicio-materia="${id}"
                    title="Personalizar esta matéria"
                    aria-label="Personalizar a matéria ${esc(m.nome)}">
                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 16h3l8-8-3-3-8 8v3Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M12.5 4.5l3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            </button>
            <span class="exercicio-materia-card__solte" aria-hidden="true">Solte para guardar aqui</span>
        </article>
    `;
    const card = tpl.content.firstElementChild;
    card.querySelector("[data-editar-exercicio-materia]").addEventListener("click", (ev) => {
        ev.preventDefault();
        ev.stopPropagation();
        window.KosmosExercicioMateriaForm?.abrir(m);
    });
    return card;
}

/* Só mexe na contagem de chips que já têm o contador: os chips desta
   página não trazem número, e criar o span aqui grudava "Biologia1"
   no rótulo (sem CSS para .rs-aba__n nesta tela). */
function atualizarContadorFiltro(materia, delta) {
    if (!materia) return;
    const chip = filtros?.querySelector(`[data-materia="${CSS.escape(materia)}"]`);
    const span = chip?.querySelector(".rs-aba__n");
    if (span) {
        const atual = parseInt(span.textContent || "0", 10);
        const novo = Math.max(0, atual + delta);
        span.textContent = novo;
        span.hidden = novo === 0;
    }
}