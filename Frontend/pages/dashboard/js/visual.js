/* ============================================================
   KOSMOS — visual.js  (a escolha entre o visual completo e o Lite)

   A preferência é do APARELHO, não da conta: quem estuda num
   notebook potente e num celular antigo quer coisas diferentes em
   cada um. Por isso fica no localStorage (`kosmos_visual`), como a
   barra recolhida.

   Quem LÊ a preferência é o partes/fundo.php, antes da primeira
   pintura. Este arquivo só cuida da troca: a classe no <html>, o
   que fica salvo, e o aviso `kosmos:visual` para quem anima algo
   (o céu, em shared/cosmos-gl.js e cosmos.js) parar ou voltar.

   Controles (qualquer um, em qualquer página):
     [data-visual-opcao="completo|lite"]  role="radio"   — Conta
     [data-visual-alternar]               role="menuitemcheckbox" — engrenagem
   ============================================================ */

(function () {
    const CHAVE = "kosmos_visual";
    const raiz = document.documentElement;

    const atual = () => (raiz.classList.contains("kosmos-lite") ? "lite" : "completo");

    function sincronizar() {
        const modo = atual();
        document.querySelectorAll("[data-visual-opcao]").forEach((el) => {
            const sim = el.dataset.visualOpcao === modo;
            el.setAttribute("aria-checked", String(sim));
            el.tabIndex = sim ? 0 : -1;   // grupo de rádio: Tab entra no marcado
        });
        document.querySelectorAll("[data-visual-alternar]").forEach((el) => {
            el.setAttribute("aria-checked", String(modo === "lite"));
        });
    }

    function definir(modo, salvar = true) {
        if (modo !== "lite") modo = "completo";
        if (modo === atual()) return;

        raiz.classList.toggle("kosmos-lite", modo === "lite");
        if (salvar) {
            try {
                if (modo === "lite") localStorage.setItem(CHAVE, "lite");
                else localStorage.removeItem(CHAVE);
            } catch (e) { /* navegação privada: vale só até fechar a aba */ }
        }

        document.dispatchEvent(new CustomEvent("kosmos:visual", { detail: modo }));
        sincronizar();
    }

    window.KosmosVisual = { atual, definir };

    document.addEventListener("click", (e) => {
        const opcao = e.target.closest("[data-visual-opcao]");
        if (opcao) { definir(opcao.dataset.visualOpcao); return; }
        if (e.target.closest("[data-visual-alternar]")) {
            definir(atual() === "lite" ? "completo" : "lite");
        }
    });

    // Setas no grupo de rádio, como num <input type="radio">
    document.addEventListener("keydown", (e) => {
        const opcao = e.target.closest("[data-visual-opcao]");
        if (!opcao || !["ArrowLeft", "ArrowRight", "ArrowUp", "ArrowDown"].includes(e.key)) return;
        e.preventDefault();
        const grupo = [...opcao.parentElement.querySelectorAll("[data-visual-opcao]")];
        const passo = e.key === "ArrowLeft" || e.key === "ArrowUp" ? -1 : 1;
        const proxima = grupo[(grupo.indexOf(opcao) + passo + grupo.length) % grupo.length];
        definir(proxima.dataset.visualOpcao);
        proxima.focus();
    });

    // Outra aba mudou a escolha: esta acompanha
    window.addEventListener("storage", (e) => {
        if (e.key === CHAVE) definir(e.newValue, false);
    });

    sincronizar();
})();
