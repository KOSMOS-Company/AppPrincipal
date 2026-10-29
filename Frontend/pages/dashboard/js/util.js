/* ============================================================
   KOSMOS — Utilitários compartilhados da dashboard
   Arquivo: dashboard/js/util.js

   Carregado ANTES de todos os outros scripts da página, então o
   que está aqui vale como global em qualquer arquivo.
   ============================================================ */

/**
 * Escapa texto para colar dentro de HTML montado com template string.
 *
 * Fonte única: antes cada arquivo tinha a sua cópia, e a versão
 * "div.textContent → innerHTML" (usada em resumos, caderno...) NÃO
 * escapa aspas. Dentro de um atributo — aria-label="${...}" — um
 * título com " fechava o atributo e deixava injetar outro.
 * Esta escapa os cinco caracteres, então serve em texto e em atributo.
 */
function escaparHtml(texto) {
    return String(texto ?? "").replace(/[&<>"']/g, (c) => ({
        "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;",
    }[c]));
}

/* ------------------------------------------------------------
   CORES DOS ITENS (cadernos, matérias de exercícios, baralhos)
   A escolha gravada pode ser uma das 6 cores, "auto" (sai da
   matéria: a mesma matéria tem sempre a mesma cor) ou "nenhuma"
   (tom neutro). Gêmeo de corDoItem() no Backend/php/materias.php.
   ------------------------------------------------------------ */
const CORES_KOSMOS = ["roxo", "azul", "verde", "laranja", "rosa", "ciano"];
const HEX_DAS_CORES = {
    roxo: "#a541ff", azul: "#60a5fa", verde: "#34d399",
    laranja: "#fbbf24", rosa: "#f472b6", ciano: "#22d3ee",
    nenhuma: "#b9adc9",
};

/** A cor automática de uma matéria. */
function corDaMateria(materia) {
    let h = 0;
    for (const ch of String(materia || "")) h = (h * 31 + ch.codePointAt(0)) >>> 0;
    return CORES_KOSMOS[h % CORES_KOSMOS.length];
}

/** A escolha gravada -> a cor que a tela pinta. */
function corDoItem(escolha, materia) {
    if (escolha === "nenhuma") return "nenhuma";
    if (CORES_KOSMOS.includes(escolha)) return escolha;
    if (escolha === "auto" || !escolha) return corDaMateria(materia);
    return "roxo";
}

/* ------------------------------------------------------------
   OS NÚMEROS DO TOPO (Biblioteca, Exercícios)
   Gêmeo do partes/estatisticas.php: a página chega com os números
   prontos e, depois de criar, apagar ou mover algo sem recarregar,
   pede ao servidor os números novos e repinta só os <strong>.
   ------------------------------------------------------------ */

/** A cor do acerto: verde a partir de 70%, amarelo de 50 a 69, vermelho abaixo. */
function faixaDeAcerto(pct) {
    if (pct === null || pct === undefined) return "";
    return pct >= 70 ? "boa" : pct >= 50 ? "media" : "baixa";
}

/** Busca ?de=biblioteca|exercicios e repinta a faixa #caixaId. */
async function recarregarEstatisticas(caixaId, de) {
    const caixa = document.getElementById(caixaId);
    if (!caixa) return;
    try {
        const r = await fetch(`../../../Backend/php/estatisticas.php?de=${encodeURIComponent(de)}`,
                              { credentials: "same-origin" });
        const j = await r.json();
        if (!j.ok) return;

        Object.entries(j.stats).forEach(([chave, valor]) => {
            const alvo = caixa.querySelector(`[data-stat="${chave}"]`);
            if (!alvo) return;
            // 0% de acerto é um resultado de verdade; só "sem respostas" (null) vira traço
            const zerado = valor === null || (chave !== "acerto" && valor === 0);
            alvo.textContent = zerado ? "—" : (chave === "acerto" ? `${valor}%` : valor);

            const stat = alvo.closest(".ini-stat");
            stat.classList.toggle("ini-stat--vazio", zerado);
            if (chave === "acerto") {
                const faixa = faixaDeAcerto(valor);
                if (faixa) stat.dataset.faixa = faixa;
                else delete stat.dataset.faixa;
            }
        });
    } catch {
        // sem conexão: os números de antes ficam na tela
    }
}
