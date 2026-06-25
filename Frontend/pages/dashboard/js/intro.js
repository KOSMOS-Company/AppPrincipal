/* ============================================================
   KOSMOS — intro.js
   Controla o splash de boas-vindas (toca uma vez após o login).
   A classe .com-intro já é adicionada no <head> da index para
   evitar "flash" — aqui só agendamos a saída e o clique-para-pular.
   ============================================================ */

(function () {
    const overlay = document.getElementById("intro");
    if (!overlay) return;

    // Se não veio do login, não mostra nada
    if (!document.documentElement.classList.contains("com-intro")) {
        overlay.remove();
        return;
    }

    sessionStorage.removeItem("kosmos_intro"); // não repete ao recarregar

    const DURACAO = 2600; // tempo visível antes de sumir (ms)

    function encerrar() {
        if (overlay.dataset.saindo) return;
        overlay.dataset.saindo = "1";
        overlay.classList.add("intro--saindo");
        document.documentElement.classList.remove("com-intro");
        overlay.addEventListener("transitionend", () => overlay.remove(), { once: true });
        setTimeout(() => overlay.remove(), 1100); // fallback alinhado à saída de .9s
    }

    overlay.addEventListener("click", encerrar); // clique pula
    setTimeout(encerrar, DURACAO);
})();
