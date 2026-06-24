/* ============================================================
   KOSMOS — exercicios.js
   Frontend apenas: banco de questões mockado.
   O "Gerar com IA" será ligado ao backend depois.
   ============================================================ */

// Banco de questões por matéria (correta = índice da alternativa certa)
const BANCO = {
    "Matemática": [
        { enunciado: "Qual é o valor de x em 2x + 6 = 14?", alts: ["2", "4", "6", "10"], correta: 1 },
        { enunciado: "A área de um quadrado de lado 5 é:", alts: ["10", "20", "25", "30"], correta: 2 },
        { enunciado: "Quanto é 3! (fatorial de 3)?", alts: ["3", "6", "9", "12"], correta: 1 },
        { enunciado: "O resultado de (-3)² é:", alts: ["-9", "-6", "6", "9"], correta: 3 },
        { enunciado: "A raiz quadrada de 144 é:", alts: ["10", "11", "12", "14"], correta: 2 },
    ],
    "Física": [
        { enunciado: "A unidade de força no SI é:", alts: ["Joule", "Watt", "Newton", "Pascal"], correta: 2 },
        { enunciado: "Velocidade é a razão entre:", alts: ["força e massa", "espaço e tempo", "tempo e massa", "energia e tempo"], correta: 1 },
        { enunciado: "Qual grandeza é vetorial?", alts: ["Massa", "Temperatura", "Tempo", "Aceleração"], correta: 3 },
        { enunciado: "A aceleração da gravidade na Terra é cerca de:", alts: ["5 m/s²", "9,8 m/s²", "15 m/s²", "20 m/s²"], correta: 1 },
        { enunciado: "Energia cinética depende da:", alts: ["altura", "velocidade", "cor", "carga"], correta: 1 },
    ],
    "Biologia": [
        { enunciado: "A organela da respiração celular é a:", alts: ["Mitocôndria", "Ribossomo", "Lisossomo", "Vacúolo"], correta: 0 },
        { enunciado: "O DNA fica armazenado, principalmente, no:", alts: ["Citoplasma", "Núcleo", "Membrana", "Ribossomo"], correta: 1 },
        { enunciado: "Fotossíntese ocorre no(a):", alts: ["Mitocôndria", "Cloroplasto", "Núcleo", "Lisossomo"], correta: 1 },
        { enunciado: "Seres procariontes NÃO possuem:", alts: ["Membrana", "Citoplasma", "Núcleo organizado", "Ribossomos"], correta: 2 },
        { enunciado: "A unidade básica da vida é a:", alts: ["Molécula", "Célula", "Tecido", "Órgão"], correta: 1 },
    ],
};

const LETRAS = ["A", "B", "C", "D"];

let questoesAtuais = [];   // [{ ...questao, escolha: null }]
let corrigido = false;

const cont      = document.getElementById("questoes");
const vazio     = document.getElementById("vazioEx");
const rodape    = document.getElementById("exRodape");
const placar    = document.getElementById("placar");
const btnGerar  = document.getElementById("btnGerar");
const btnCorrigir = document.getElementById("btnCorrigir");

function embaralhar(arr) {
    // cópia embaralhada (Fisher–Yates) — sem persistir o banco original
    const a = [...arr];
    for (let i = a.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [a[i], a[j]] = [a[j], a[i]];
    }
    return a;
}

function gerar() {
    const materia = document.getElementById("gMateria").value;
    const qtd     = +document.getElementById("gQtd").value;

    questoesAtuais = embaralhar(BANCO[materia])
        .slice(0, qtd)
        .map((q) => ({ ...q, escolha: null }));
    corrigido = false;

    render();
}

function render() {
    vazio.hidden = true;
    rodape.hidden = false;
    placar.hidden = true;
    btnCorrigir.disabled = false;
    btnCorrigir.textContent = "Corrigir respostas";

    cont.innerHTML = "";
    questoesAtuais.forEach((q, qi) => {
        const div = document.createElement("div");
        div.className = "questao anim-in";
        div.style.animationDelay = `${qi * 0.06}s`;
        div.innerHTML = `
            <div class="questao__num">Questão ${qi + 1}</div>
            <p class="questao__enunciado">${q.enunciado}</p>
            <div class="questao__alts">
                ${q.alts.map((alt, ai) => `
                    <div class="alt" data-q="${qi}" data-a="${ai}">
                        <span class="alt__letra">${LETRAS[ai]}</span>
                        <span>${alt}</span>
                    </div>`).join("")}
            </div>`;
        cont.appendChild(div);
    });
}

// Seleção de alternativa
cont.addEventListener("click", (e) => {
    if (corrigido) return;
    const alt = e.target.closest(".alt");
    if (!alt) return;

    const qi = +alt.dataset.q;
    const ai = +alt.dataset.a;
    questoesAtuais[qi].escolha = ai;

    alt.parentElement.querySelectorAll(".alt").forEach((a) => a.classList.remove("selecionada"));
    alt.classList.add("selecionada");
});

// Correção
btnCorrigir.addEventListener("click", () => {
    let acertos = 0;

    cont.querySelectorAll(".questao").forEach((qEl, qi) => {
        const q = questoesAtuais[qi];
        qEl.classList.add("corrigida");
        qEl.querySelectorAll(".alt").forEach((alt, ai) => {
            alt.classList.remove("selecionada");
            if (ai === q.correta) alt.classList.add("certa");
            else if (ai === q.escolha) alt.classList.add("errada");
        });
        if (q.escolha === q.correta) acertos++;
    });

    corrigido = true;
    placar.hidden = false;
    placar.innerHTML = `Você acertou <span>${acertos}</span> de <span>${questoesAtuais.length}</span> questões!`;
    btnCorrigir.disabled = true;
    btnCorrigir.textContent = "Corrigido";
});

btnGerar.addEventListener("click", gerar);
