/* ============================================================
   KOSMOS — dashboard.js  (compartilhado por todas as abas)
   ============================================================ */

document.addEventListener("DOMContentLoaded", () => {
    // Nome do usuário (vindo do login)
    const nomeStorage = sessionStorage.getItem("kosmos_usuario");
    document.querySelectorAll("[data-usuario]").forEach((el) => {
        if (nomeStorage) el.textContent = nomeStorage;
    });

    // Marca o link ativo da sidebar com base no arquivo atual
    const atual = location.pathname.split("/").pop() || "index.html";
    document.querySelectorAll(".botoesL a").forEach((a) => {
        const alvo = a.getAttribute("href");
        a.classList.toggle("active", alvo === atual);
    });
});
