/* ============================================================
   KOSMOS — dashboard.js  (compartilhado por todas as abas)
   Faz o papel de "porteiro": só deixa ver o dashboard quem
   tiver sessão ativa no backend. Também preenche o nome real.
   ============================================================ */

// Caminho do backend a partir de /Frontend/pages/dashboard/
const API = "../../../Backend/php";

document.addEventListener("DOMContentLoaded", () => {
    marcarLinkAtivo();
    verificarSessao();
});

/* Marca o link ativo da sidebar com base no arquivo atual */
function marcarLinkAtivo() {
    const atual = location.pathname.split("/").pop() || "index.html";
    document.querySelectorAll(".botoesL a").forEach((a) => {
        const alvo = a.getAttribute("href");
        a.classList.toggle("active", alvo === atual);
    });
}

/* Pergunta ao servidor "quem sou eu?".
   - 401  -> não logado: volta para o login
   - 200  -> logado: mostra o nome real do usuário */
async function verificarSessao() {
    try {
        const resposta = await fetch(`${API}/usuario_atual.php`);

        if (!resposta.ok) {
            // Sem sessão válida: expulsa para o login
            window.location.replace("../login/index.html");
            return;
        }

        const json = await resposta.json();

        // Guarda o nome para uso rápido nesta sessão do navegador
        sessionStorage.setItem("kosmos_usuario", json.nome);

        document.querySelectorAll("[data-usuario]").forEach((el) => {
            el.textContent = json.nome;
        });
    } catch (err) {
        // Backend fora do ar (ex.: abriu sem XAMPP). Volta ao login.
        window.location.replace("../login/index.html");
    }
}
