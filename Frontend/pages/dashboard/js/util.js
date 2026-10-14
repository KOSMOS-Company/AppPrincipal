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
