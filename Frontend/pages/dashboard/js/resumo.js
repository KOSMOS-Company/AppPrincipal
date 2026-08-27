/* ============================================================
   KOSMOS — resumo.js  (só a prévia: resumo.php)
   A página serve para LER. Aqui só ligamos o botão "Editar" ao
   editor compartilhado (resumo-form.js) e reagimos ao resultado:
   salvar atualiza o texto na tela, apagar volta para a lista.
   ============================================================ */
(() => {
    "use strict";

    const cru = document.getElementById("dadosResumo");
    if (!cru) return;                 // página de "não encontrado"

    let resumo;
    try {
        resumo = JSON.parse(cru.textContent);
    } catch (err) {
        return;
    }

    document.addEventListener("DOMContentLoaded", () => {
        document.getElementById("btnEditar")?.addEventListener("click", () => {
            window.KosmosResumoForm?.abrir(resumo);
        });
    });

    /* Salvou: atualiza o que está na tela sem recarregar a página */
    document.addEventListener("resumo:salvo", (e) => {
        const novo = e.detail?.resumo;
        if (!novo || novo.id !== resumo.id) return;

        resumo = novo;

        const titulo = document.querySelector(".contCabeca__texto h1");
        if (titulo) titulo.textContent = novo.titulo;

        const tag = document.querySelector(".resumo-meta .materia-tag");
        if (tag) tag.textContent = novo.materia;

        const leitura = document.getElementById("leitura");
        if (leitura) leitura.textContent = novo.corpo;

        document.title = novo.titulo + " — Kosmos";
    });

    /* Apagou: não há mais o que ler aqui */
    document.addEventListener("resumo:apagado", (e) => {
        if (e.detail?.id === resumo.id) {
            window.location.replace("resumos.php");
        }
    });
})();
