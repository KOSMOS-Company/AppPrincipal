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
