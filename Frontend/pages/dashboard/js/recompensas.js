/* ============================================================
   KOSMOS — recompensas.js  (trilha de recompensas)
   Usado em conquistas.php. Equipa e tira molduras, emblemas e cores
   exclusivas do avatar.

   Quem decide se pode é o servidor (recompensas_equipar.php confere
   o nível). Aqui só se pede, e com a resposta se atualiza TODO avatar
   da página — o da barra lateral, o grande da trilha e as prévias —
   trocando as classes avatar-{tipo}--* que o css/recompensas.css
   desenha. Sem recarregar.
   ============================================================ */
(() => {
    "use strict";

    const BACKEND = "../../../Backend/php";
    const lista = document.getElementById("trilhaLista");
    if (!lista) return;

    const NOMES_TIPO = { moldura: "Moldura", emblema: "Emblema", cor: "Cor" };
    const GENERO = { moldura: "a", emblema: "o", cor: "a" };   // "moldura equipada", "emblema equipado"

    /* Abre a fileira já no próximo nível a destravar (sem animar: é o
       lugar de partida, não um movimento que a pessoa pediu). */
    document.addEventListener("DOMContentLoaded", () => {
        const proximo = lista.querySelector("[data-proximo]");
        if (proximo) {
            const anterior = proximo.previousElementSibling;
            lista.scrollLeft = (anterior || proximo).offsetLeft - lista.offsetLeft - 4;
        }
    });

    /* Troca, num elemento, a classe avatar-{tipo}--* pela nova (ou nenhuma) */
    function trocarClasse(el, tipo, valor) {
        [...el.classList].forEach((c) => { if (c.startsWith(`avatar-${tipo}--`)) el.classList.remove(c); });
        if (valor) el.classList.add(`avatar-${tipo}--${valor}`);
    }

    function aplicarNaPagina(equipado) {
        // Avatares "de verdade": barra lateral, o grande da trilha
        const avatares = [...document.querySelectorAll(".usuario__avatar"), document.getElementById("trAvatar")].filter(Boolean);
        avatares.forEach((el) => {
            trocarClasse(el, "cor", equipado.cor);
            trocarClasse(el, "moldura", equipado.moldura);
            trocarClasse(el, "emblema", equipado.emblema);
        });
        // Prévias de moldura/emblema mostram a recompensa na cor atual da pessoa
        document.querySelectorAll("[data-previa-cor]").forEach((el) => trocarClasse(el, "cor", equipado.cor));

        // Botões: o equipado de cada tipo vira "Em uso"
        lista.querySelectorAll("[data-equipar]").forEach((b) => {
            const emUso = equipado[b.dataset.tipo] === b.dataset.valor;
            b.setAttribute("aria-pressed", String(emUso));
            b.classList.toggle("dash-btn--ghost", emUso);
            b.classList.toggle("dash-btn--primary", !emUso);
            b.textContent = emUso
                ? (b.dataset.tipo === "cor" ? "Em uso" : "Em uso · tirar")
                : `Equipar ${NOMES_TIPO[b.dataset.tipo].toLowerCase()}`;
        });

        // "Seu avatar: Moldura X · Emblema Y · Cor Z"
        const nomeDe = (tipo) => {
            const b = lista.querySelector(`[data-equipar][data-tipo="${tipo}"][aria-pressed="true"]`);
            return b ? b.dataset.nome : null;
        };
        const set = (tipo, vazio) => {
            const el = document.querySelector(`[data-equipado="${tipo}"]`);
            if (el) el.textContent = nomeDe(tipo) || vazio;
        };
        set("moldura", "nenhuma");
        set("emblema", "nenhum");
        set("cor", equipado.cor.charAt(0).toUpperCase() + equipado.cor.slice(1));
    }

    lista.addEventListener("click", async (e) => {
        const botao = e.target.closest("[data-equipar]");
        if (!botao || botao.disabled) return;

        const tipo = botao.dataset.tipo;
        const tirar = botao.getAttribute("aria-pressed") === "true";
        if (tirar && tipo === "cor") return;   // a cor exclusiva sai escolhendo uma básica na Conta

        const dados = new FormData();
        dados.append("tipo", tipo);
        dados.append("slug", tirar ? "" : botao.dataset.slug);

        botao.disabled = true;
        try {
            const resp = await fetch(`${BACKEND}/recompensas_equipar.php`, { method: "POST", body: dados });
            const json = await resp.json();
            if (!json.ok) throw new Error(json.msg || "Não foi possível equipar agora.");
            aplicarNaPagina(json.equipado);
            const g = GENERO[tipo];
            window.KosmosProgresso?.aviso(tirar
                ? `${NOMES_TIPO[tipo]} removid${g} do avatar`
                : `${NOMES_TIPO[tipo]} ${botao.dataset.nome} equipad${g}`);
        } catch (err) {
            window.KosmosProgresso?.aviso(err.message);
        } finally {
            botao.disabled = false;
        }
    });
})();
