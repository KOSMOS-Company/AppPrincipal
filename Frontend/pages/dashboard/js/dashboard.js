/* ============================================================
   KOSMOS — dashboard.js  (compartilhado por todas as abas)
   Faz o papel de "porteiro": só deixa ver o dashboard quem
   tiver sessão ativa no backend. Também preenche o nome real,
   move o marcador do menu e cuida do botão de sair.
   ============================================================ */

// Caminho do backend a partir de /Frontend/pages/dashboard/
const API = "../../../Backend/php";

document.addEventListener("DOMContentLoaded", () => {
    marcarLinkAtivo();
    verificarSessao();
    ativarTransicoes();
    ativarMarcador();
    ativarSair();
});

/* Marca o link ativo da sidebar com base no arquivo atual */
function marcarLinkAtivo() {
    const atual = location.pathname.split("/").pop() || "index.html";
    document.querySelectorAll(".botoesL a").forEach((a) => {
        const alvo = a.getAttribute("href");
        a.classList.toggle("active", alvo === atual);
    });

    // no desktop o link Conta fica oculto: quem indica "você está aqui"
    // é o cartão do rodapé
    document.querySelector(".usuario")
        ?.classList.toggle("ativa", atual === "conta.html");
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

        // Só o primeiro nome (títulos grandes quebram com nome completo)
        const primeiro = (json.nome || "").trim().split(" ")[0];
        document.querySelectorAll("[data-usuario-primeiro]").forEach((el) => {
            el.textContent = primeiro;
        });

        // Inicial do avatar no rodapé do menu
        const inicial = (json.nome || "").trim().charAt(0).toUpperCase();
        document.querySelectorAll("[data-usuario-inicial]").forEach((el) => {
            el.textContent = inicial || "?";
        });

        // Avisa os scripts de página (ex.: inicio.js) que o usuário chegou,
        // para não precisarem repetir o fetch de usuario_atual.php.
        document.dispatchEvent(new CustomEvent("kosmos:usuario", { detail: json }));
    } catch (err) {
        // Backend fora do ar (ex.: abriu sem XAMPP). Volta ao login.
        window.location.replace("../login/index.html");
    }
}

/* ------------------------------------------------------------
   Marcador do menu: um retângulo que desliza entre os itens.
   Fica sobre a página atual e acompanha o cursor no hover.
   No mobile a barra é horizontal e o marcador some (CSS).
   ------------------------------------------------------------ */
function ativarMarcador() {
    const nav = document.querySelector(".botoesL");
    const marca = nav && nav.querySelector(".nav__marca");
    if (!nav || !marca) return;

    const noMobile = () => window.matchMedia("(max-width: 768px)").matches;

    const mover = (alvo) => {
        if (!alvo || noMobile() || !alvo.offsetHeight) {
            marca.classList.remove("pronta");
            return;
        }
        marca.style.setProperty("--y", alvo.offsetTop + "px");
        marca.style.setProperty("--h", alvo.offsetHeight + "px");
        marca.classList.add("pronta");
    };

    const ativo = () => nav.querySelector("a.active");

    nav.querySelectorAll("a").forEach((a) => {
        a.addEventListener("mouseenter", () => mover(a));
    });
    nav.addEventListener("mouseleave", () => mover(ativo()));

    mover(ativo());
    window.addEventListener("resize", () => mover(ativo()));
}

/* ------------------------------------------------------------
   Sair da conta (botão no rodapé do menu).
   Mesmo caminho usado pela página Conta.
   ------------------------------------------------------------ */
function ativarSair() {
    // seletor por classe: a página Conta tem o próprio botão com id="btnSair"
    const botao = document.querySelector(".usuario__sair");
    if (!botao) return;

    botao.addEventListener("click", async () => {
        botao.disabled = true;
        try {
            await fetch(`${API}/logout.php`);
        } catch (err) {
            /* mesmo se falhar, vamos para o login */
        }
        sessionStorage.removeItem("kosmos_usuario");
        sessionStorage.removeItem("kosmos_intro");
        window.location.replace("../login/index.html");
    });
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
