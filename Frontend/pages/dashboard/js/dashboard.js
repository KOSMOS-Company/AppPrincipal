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
    pintarDoCache();      // antes do fetch: evita a tela trocar de valor na sua frente
    verificarSessao();
    ativarTransicoes();
    ativarMarcador();
    ativarSair();
    ativarBrilhoNosCards();
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

/* ------------------------------------------------------------
   Pinta o que já sabemos desta sessão do navegador ANTES da
   resposta do servidor. Sem isso a página aparece com o texto
   genérico do HTML e troca ~150ms depois — o que parecia
   travamento (e, nos números, parecia "não salvou").
   O fetch em seguida confirma ou corrige.
   ------------------------------------------------------------ */
function pintarDoCache() {
    try {
        const nome = sessionStorage.getItem("kosmos_usuario");
        if (nome) aplicarUsuario(nome);

        const cor = sessionStorage.getItem("kosmos_avatar_cor");
        if (cor) aplicarCorAvatarSidebar(cor);

        const foto = sessionStorage.getItem("kosmos_avatar_url");
        if (foto) aplicarFotoSidebar(foto, sessionStorage.getItem("kosmos_avatar_pos"));
    } catch (err) {
        /* sem sessionStorage (aba privada): segue o fluxo normal */
    }
}

/* Escreve o nome nos três lugares que o usam e tira o esqueleto */
function aplicarUsuario(nome) {
    const primeiro = (nome || "").trim().split(" ")[0];
    const inicial = (nome || "").trim().charAt(0).toUpperCase() || "?";

    document.querySelectorAll("[data-usuario]").forEach((el) => {
        el.textContent = nome;
        el.classList.remove("esqueleto", "esqueleto--largo");
    });
    document.querySelectorAll("[data-usuario-primeiro]").forEach((el) => {
        el.textContent = primeiro;
        el.classList.remove("esqueleto", "esqueleto--largo");
    });
    document.querySelectorAll("[data-usuario-inicial]").forEach((el) => {
        el.textContent = inicial;
    });
}

function aplicarCorAvatarSidebar(cor) {
    document.querySelectorAll(".usuario__avatar").forEach((el) => {
        [...el.classList].forEach((c) => {
            if (c.startsWith("avatar-cor--")) el.classList.remove(c);
        });
        el.classList.add("avatar-cor--" + cor);
    });
}

/* pos = "20% 80%" (enquadramento escolhido na aba Conta) */
function aplicarFotoSidebar(url, pos) {
    document.querySelectorAll(".usuario__avatar").forEach((el) => {
        el.style.backgroundImage = `url("${url}")`;
        if (pos) el.style.backgroundPosition = pos;
        el.classList.add("avatar--foto");
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

        // Conta criada pelo Google que ainda não definiu senha: manda
        // criar antes de usar o app (sem senha ela nunca conseguiria
        // entrar por e-mail e senha).
        if (json.tem_senha === false) {
            window.location.replace("../login/criar-senha.html");
            return;
        }

        // Guarda para a próxima carga pintar na hora (ver pintarDoCache)
        sessionStorage.setItem("kosmos_usuario", json.nome);
        if (json.avatar_cor) sessionStorage.setItem("kosmos_avatar_cor", json.avatar_cor);
        const pos = json.avatar_pos
            ? json.avatar_pos.x + "% " + json.avatar_pos.y + "%"
            : null;
        if (json.avatar_url) {
            sessionStorage.setItem("kosmos_avatar_url", json.avatar_url);
            if (pos) sessionStorage.setItem("kosmos_avatar_pos", pos);
        } else {
            sessionStorage.removeItem("kosmos_avatar_url");
            sessionStorage.removeItem("kosmos_avatar_pos");
        }

        aplicarUsuario(json.nome);

        if (json.avatar_cor) aplicarCorAvatarSidebar(json.avatar_cor);

        // sem foto salva, garante que não sobrou imagem do cache
        if (json.avatar_url) {
            aplicarFotoSidebar(json.avatar_url, pos);
        } else {
            document.querySelectorAll(".usuario__avatar").forEach((el) => {
                el.style.backgroundImage = "";
                el.classList.remove("avatar--foto");
            });
        }

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
   Brilho que segue o cursor + leve inclinação nos cartões.
   Mesmo efeito dos .card da landing page, agora em todas as
   abas. Usa delegação porque resumos.js/flashcards.js criam
   os cartões depois do carregamento da página.
   Só com ponteiro fino e se o usuário não pediu menos movimento.
   ------------------------------------------------------------ */
function ativarBrilhoNosCards() {
    const SELETOR = "a.ini-card, .resumo-card, .deck-card";
    const area = document.querySelector(".contMeio");
    if (!area) return;

    const ponteiroFino = window.matchMedia("(pointer: fine)").matches;
    const reduzMovimento = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    if (!ponteiroFino || reduzMovimento) return;

    let atual = null;
    let proximo = null;
    let agendado = false;

    const limpar = (card) => {
        if (!card) return;
        card.style.transition = "";
        card.style.transform = "";
    };

    const desenhar = () => {
        agendado = false;
        if (!proximo) return;
        const { card, x, y } = proximo;
        const r = card.getBoundingClientRect();
        const px = x - r.left;
        const py = y - r.top;
        card.style.setProperty("--mx", px + "px");
        card.style.setProperty("--my", py + "px");

        const rx = (0.5 - py / r.height) * 8;
        const ry = (px / r.width - 0.5) * 8;
        card.style.transition = "transform .08s ease-out";
        card.style.transform =
            "perspective(800px) rotateX(" + rx + "deg) rotateY(" + ry + "deg) translateY(-6px)";
    };

    area.addEventListener("mousemove", (e) => {
        const card = e.target.closest(SELETOR);
        if (card !== atual) {
            limpar(atual);
            atual = card;
        }
        if (!card) { proximo = null; return; }

        proximo = { card, x: e.clientX, y: e.clientY };
        if (!agendado) {
            agendado = true;
            requestAnimationFrame(desenhar);
        }
    });

    area.addEventListener("mouseleave", () => {
        limpar(atual);
        atual = null;
        proximo = null;
    });
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
