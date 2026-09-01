/* ============================================================
   KOSMOS — flashcards.js
   Frontend apenas: decks em memória, sem backend (por enquanto).
   ============================================================ */

let decks = [
    {
        nome: "Física — Cinemática", materia: "Física",
        cartoes: [
            { frente: "O que é velocidade média?", verso: "Variação do espaço dividida pela variação do tempo (Δs/Δt)." },
            { frente: "Unidade de aceleração no SI", verso: "Metro por segundo ao quadrado (m/s²)." },
            { frente: "Fórmula da equação de Torricelli", verso: "v² = v₀² + 2·a·Δs" },
        ],
    },
    {
        nome: "Biologia — Citologia", materia: "Biologia",
        cartoes: [
            { frente: "Organela responsável pela respiração celular", verso: "Mitocôndria." },
            { frente: "Onde fica o material genético na célula eucarionte?", verso: "No núcleo." },
            { frente: "Função do ribossomo", verso: "Síntese de proteínas." },
            { frente: "O que diferencia procarionte de eucarionte?", verso: "A presença de núcleo organizado (carioteca)." },
        ],
    },
    {
        nome: "História — Brasil Colônia", materia: "História",
        cartoes: [
            { frente: "Primeiro ciclo econômico do Brasil colonial", verso: "Ciclo do pau-brasil." },
            { frente: "Ano da chegada da Família Real ao Brasil", verso: "1808." },
        ],
    },
];

// ---- Visões ----
const viewDecks   = document.getElementById("viewDecks");
const viewEstudo  = document.getElementById("viewEstudo");
const decksGrid   = document.getElementById("decksGrid");
const filtrosDecks = document.getElementById("filtrosDecks");

let filtroAtual = "todos";

function renderFiltrosDecks() {
    const materias = [...new Set(decks.map((d) => d.materia))].sort();

    filtrosDecks.querySelectorAll(".chip").forEach((c) => c.remove());
    filtrosDecks.appendChild(criarDeckChip("todos", "Todos"));
    materias.forEach((m) => filtrosDecks.appendChild(criarDeckChip(m, m)));
}

function criarDeckChip(materia, texto) {
    const chip = document.createElement("button");
    chip.className = "chip" + (filtroAtual === materia ? " active" : "");
    chip.dataset.materia = materia;
    chip.textContent = texto;
    return chip;
}

function renderDecks() {
    const lista = decks
        .map((d, idx) => ({ ...d, _idx: idx }))
        .filter((d) => filtroAtual === "todos" || d.materia === filtroAtual);

    decksGrid.innerHTML = "";
    lista.forEach((d, i) => {
        const card = document.createElement("article");
        card.className = "deck-card anim-in";
        card.style.animationDelay = `${i * 0.05}s`;
        card.innerHTML = `
            <span class="materia-tag">${d.materia}</span>
            <h3 class="deck-card__nome">${d.nome}</h3>
            <span class="deck-card__qtd"><strong>${d.cartoes.length}</strong> cartões</span>
            <button class="dash-btn dash-btn--ghost" data-deck="${d._idx}" ${d.cartoes.length ? "" : "disabled"}>
                Estudar
            </button>`;
        decksGrid.appendChild(card);
    });
}

decksGrid.addEventListener("click", (e) => {
    const btn = e.target.closest("[data-deck]");
    if (btn) iniciarEstudo(+btn.dataset.deck);
});

// Filtros
filtrosDecks.addEventListener("click", (e) => {
    const chip = e.target.closest(".chip");
    if (!chip) return;
    filtroAtual = chip.dataset.materia;
    filtrosDecks.querySelectorAll(".chip").forEach((c) =>
        c.classList.toggle("active", c === chip)
    );
    renderDecks();
});

// ---- Modo de estudo ----
let deckAtual = null;
let indice = 0;

const flashcard  = document.getElementById("flashcard");
const cardFrente = document.getElementById("cardFrente");
const cardVerso  = document.getElementById("cardVerso");
const titulo     = document.getElementById("estudoTitulo");
const progresso  = document.getElementById("estudoProgresso");
const bar        = document.getElementById("barEstudo");

function iniciarEstudo(i) {
    deckAtual = decks[i];
    indice = 0;
    titulo.textContent = deckAtual.nome;
    viewDecks.hidden = true;
    viewEstudo.hidden = false;
    mostrarCartao();
}

function mostrarCartao() {
    const c = deckAtual.cartoes[indice];
    flashcard.classList.remove("virada");      // sempre começa na frente
    cardFrente.textContent = c.frente;
    cardVerso.textContent  = c.verso;

    const total = deckAtual.cartoes.length;
    progresso.textContent = `Cartão ${indice + 1} de ${total}`;
    bar.style.width = `${((indice + 1) / total) * 100}%`;

    document.getElementById("btnAnterior").disabled = indice === 0;
    document.getElementById("btnProximo").disabled  = indice === total - 1;
}

flashcard.addEventListener("click", () => flashcard.classList.toggle("virada"));

document.getElementById("btnProximo").addEventListener("click", () => {
    if (indice < deckAtual.cartoes.length - 1) { indice++; mostrarCartao(); }
});
document.getElementById("btnAnterior").addEventListener("click", () => {
    if (indice > 0) { indice--; mostrarCartao(); }
});

document.getElementById("btnVoltar").addEventListener("click", () => {
    viewEstudo.hidden = true;
    viewDecks.hidden = false;
});

// ---- Modal: novo deck ----
const modal  = document.getElementById("modal");
const abrir  = () => modal.classList.add("open");
const fechar = () => modal.classList.remove("open");

document.getElementById("btnNovoDeck").addEventListener("click", abrir);
document.getElementById("fechar").addEventListener("click", fechar);
document.getElementById("cancelar").addEventListener("click", fechar);
modal.addEventListener("click", (e) => { if (e.target === modal) fechar(); });

document.getElementById("formDeck").addEventListener("submit", (e) => {
    e.preventDefault();
    const nome = document.getElementById("nomeDeck").value.trim();
    const materia = document.getElementById("materiaDeck").value;
    if (!nome) return;
    decks.unshift({ nome, materia, cartoes: [] });
    filtroAtual = "todos";
    renderFiltrosDecks();
    e.target.reset();
    fechar();
    renderDecks();
});

renderFiltrosDecks();
renderDecks();
