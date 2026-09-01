/* ============================================================
   KOSMOS — resumos.js
   Frontend apenas: dados em memória, sem backend (por enquanto).
   ============================================================ */

const ICONE_DOC = '<svg viewBox="0 0 24 24" fill="none"><rect x="5" y="3" width="14" height="18" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M9 8h6M9 12h6M9 16h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>';

// Dados mockados iniciais
let resumos = [
    { titulo: "Leis de Newton",            materia: "Física",      data: "12 jun" },
    { titulo: "Funções do 2º grau",        materia: "Matemática",  data: "10 jun" },
    { titulo: "Ligações químicas",         materia: "Química",     data: "08 jun" },
    { titulo: "Revolução Francesa",        materia: "História",    data: "05 jun" },
    { titulo: "Citologia",                 materia: "Biologia",    data: "03 jun" },
    { titulo: "Análise sintática",         materia: "Português",   data: "01 jun" },
    { titulo: "Trigonometria",             materia: "Matemática",  data: "28 mai" },
];

let filtroAtual = "todos";

const grid    = document.getElementById("grid");
const vazio   = document.getElementById("vazio");
const filtros = document.getElementById("filtros");

function renderFiltros() {
    const materias = [...new Set(resumos.map((r) => r.materia))].sort();

    filtros.querySelectorAll(".chip").forEach((c) => c.remove());
    filtros.appendChild(criarChip("todos", "Todos"));
    materias.forEach((m) => filtros.appendChild(criarChip(m, m)));
}

function criarChip(materia, texto) {
    const chip = document.createElement("button");
    chip.className = "chip" + (filtroAtual === materia ? " active" : "");
    chip.dataset.materia = materia;
    chip.textContent = texto;
    return chip;
}

function render() {
    const lista = resumos.filter(
        (r) => filtroAtual === "todos" || r.materia === filtroAtual
    );

    grid.innerHTML = "";
    vazio.hidden = lista.length > 0;

    lista.forEach((r, i) => {
        const card = document.createElement("article");
        card.className = "resumo-card anim-in";
        card.style.animationDelay = `${i * 0.04}s`;
        card.innerHTML = `
            <div class="resumo-card__thumb">${ICONE_DOC}</div>
            <div class="resumo-card__body">
                <span class="materia-tag">${r.materia}</span>
                <h3 class="resumo-card__title">${r.titulo}</h3>
                <div class="resumo-card__meta">
                    <span>${r.data}</span>
                    <span>Abrir →</span>
                </div>
            </div>`;
        grid.appendChild(card);
    });
}

// Filtros
filtros.addEventListener("click", (e) => {
    const chip = e.target.closest(".chip");
    if (!chip) return;
    filtroAtual = chip.dataset.materia;
    filtros.querySelectorAll(".chip").forEach((c) =>
        c.classList.toggle("active", c === chip)
    );
    render();
});

// Modal
const modal    = document.getElementById("modal");
const abrir     = () => modal.classList.add("open");
const fechar    = () => modal.classList.remove("open");

document.getElementById("btnNovo").addEventListener("click", abrir);
document.getElementById("fechar").addEventListener("click", fechar);
document.getElementById("cancelar").addEventListener("click", fechar);
modal.addEventListener("click", (e) => { if (e.target === modal) fechar(); });

document.getElementById("formNovo").addEventListener("submit", (e) => {
    e.preventDefault();
    const titulo  = document.getElementById("titulo").value.trim();
    const materia = document.getElementById("materia").value;
    if (!titulo) return;

    resumos.unshift({ titulo, materia, data: "hoje" });
    // mostra a matéria recém-criada
    filtroAtual = "todos";
    renderFiltros();

    e.target.reset();
    fechar();
    render();
});

renderFiltros();
render();
