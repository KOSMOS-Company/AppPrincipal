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
    ativarTransicoes();
    posicionarSequencia();
});
window.addEventListener("resize", posicionarSequencia);

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

        atualizarSequencia(json.sequencia ?? 0);
    } catch (err) {
        // Backend fora do ar (ex.: abriu sem XAMPP). Volta ao login.
        window.location.replace("../login/index.html");
    }
}

/* Atualiza o widget "Sequência" com os dias reais */
function atualizarSequencia(dias) {
    const linha = document.querySelector(".contSequencia .linhaBaixo");
    if (linha) linha.textContent = dias + (dias === 1 ? " dia" : " dias");
    const prog = document.getElementById("sequencia");
    if (prog) prog.value = Math.min(dias, 7) / 7 * 100;
}

/* No mobile, move o card de Sequência para dentro do conteúdo (após o
   cabeçalho "Bem-vindo"); no desktop, mantém na barra lateral.
   Só existe na index, então em outras páginas não faz nada. */
function posicionarSequencia() {
    const seq = document.querySelector(".contSequencia");
    if (!seq) return;
    const aside  = document.querySelector(".contLateral");
    const main   = document.querySelector(".contMeio");
    const header = main && main.querySelector(".contCabeca");
    const mobile = window.matchMedia("(max-width: 768px)").matches;

    if (mobile && header && seq.parentElement !== main) {
        header.insertAdjacentElement("afterend", seq);   // logo após o "Bem-vindo"
    } else if (!mobile && aside && seq.parentElement !== aside) {
        aside.appendChild(seq);                            // volta para a barra lateral
    }
}

/* Transição suave ao trocar de aba: faz o conteúdo sair (fade-out)
   e só então navega. A entrada (fade-in) é feita por CSS em .contMeio. */
function ativarTransicoes() {
    document.querySelectorAll(".botoesL a").forEach((a) => {
        a.addEventListener("click", (e) => {
            const href = a.getAttribute("href");
            if (!href || href.startsWith("#")) return;

            const destino = new URL(href, location.href);
            if (destino.pathname === location.pathname) return; // já está nesta aba

            e.preventDefault();
            document.body.classList.add("saindo");
            setTimeout(() => { window.location.href = href; }, 260);
        });
    });
}
