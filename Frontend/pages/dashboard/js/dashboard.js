/* ============================================================
   KOSMOS — dashboard.js  (compartilhado por todas as abas)
   Só interação: move o marcador do menu, faz a transição entre
   abas, o botão de sair e o brilho dos cartões.

   Quem cuida de "pode ver esta página?" e de preencher nome,
   avatar e preferências é o PHP (Backend/php/pagina_dashboard.php),
   antes de a página sair do servidor. Por isso aqui não há mais
   fetch de sessão nem cache no sessionStorage: a página já chega
   pronta, sem o "pisca" que a versão em JS tinha.
   ============================================================ */

// Caminho do backend a partir de /Frontend/pages/dashboard/
const API = "../../../Backend/php";

document.addEventListener("DOMContentLoaded", () => {
    ativarTransicoes();
    ativarMarcador();
    ativarRecolher();
    ativarMenuConfig();
    ativarMenuMaisMobile();
    ativarSair();
    ativarBrilhoNosCards();
});

/* ------------------------------------------------------------
   Recolher a barra lateral (só no computador)

   Quem PINTA a barra estreita é o CSS (html.lateral-recolhida), e
   quem a restaura no carregamento é um script inline dentro do
   partes/sidebar.php — ele roda antes do <aside> existir, para a
   barra não aparecer aberta e encolher na frente da pessoa. Aqui
   fica só o que depende de clique.
   ------------------------------------------------------------ */
function ativarRecolher() {
    const botao = document.getElementById("lateralAperta");
    if (!botao) return;

    const raiz = document.documentElement;

    const aplicar = (recolhida) => {
        raiz.classList.toggle("lateral-recolhida", recolhida);
        botao.setAttribute("aria-expanded", String(!recolhida));
        botao.setAttribute("aria-label", recolhida ? "Expandir menu" : "Recolher menu");

        try {
            localStorage.setItem("kosmos_lateral", recolhida ? "recolhida" : "aberta");
        } catch (e) { /* navegação privada: vale só para esta visita */ }

        /* O marcador do menu é posicionado por offsetTop, e recolher
           esconde os rótulos de grupo — todos os itens sobem. O
           `ativarMarcador` já escuta `resize` para se recolocar, então
           avisar por ali evita expor a função dele só para isto.
           Depois da transição de largura (.26s), senão ele mede a
           posição do meio do caminho. */
        const remedir = () => window.dispatchEvent(new Event("resize"));
        setTimeout(remedir, 300);
        remedir();
    };

    // o estado inicial veio do script inline; aqui só espelhamos no botão
    aplicar(raiz.classList.contains("lateral-recolhida"));

    botao.addEventListener("click", () => {
        aplicar(!raiz.classList.contains("lateral-recolhida"));
    });
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

    // Posiciona instantaneamente na aba ativa no carregamento (sem vir lá do teto)
    mover(ativo());

    // Ativa a transição suave apenas após o primeiro frame (para os próximos hovers)
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            marca.classList.add("animada");
        });
    });

    nav.querySelectorAll("a").forEach((a) => {
        a.addEventListener("mouseenter", () => mover(a));
    });
    nav.addEventListener("mouseleave", () => mover(ativo()));

    window.addEventListener("resize", () => {
        marca.classList.remove("animada");
        mover(ativo());
        requestAnimationFrame(() => marca.classList.add("animada"));
    });
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
   Confirmação (compartilhada por todas as abas)
   Abre a mini tela e devolve uma promessa: true se a pessoa
   confirmou, false se desistiu. Use antes de qualquer ação que
   mexa em algo de verdade — apagar um resumo, remover a foto,
   encerrar as outras sessões, sair da conta.
   O HTML do modal vem de partes/modal-confirma.php; se a página
   não o incluir, confirmar() devolve true e não trava a ação.
   ------------------------------------------------------------ */
function confirmar({ titulo, texto, botao = "Confirmar", perigo = false }) {
    const modal = document.getElementById("modalConfirma");
    const sim = document.getElementById("confirmaSim");
    const nao = document.getElementById("confirmaNao");
    const fechar = document.getElementById("confirmaFechar");

    // sem o modal na página (outra aba), não trava a ação
    if (!modal || !sim) return Promise.resolve(true);

    texto_(titulo, texto, botao, perigo);
    modal.classList.add("open");
    sim.focus();

    return new Promise((resolve) => {
        const encerrar = (resposta) => {
            modal.classList.remove("open");
            sim.onclick = null;
            nao.onclick = null;
            fechar.onclick = null;
            modal.onclick = null;
            document.removeEventListener("keydown", noEsc);
            resolve(resposta);
        };
        const noEsc = (e) => { if (e.key === "Escape") encerrar(false); };

        sim.onclick = () => encerrar(true);
        nao.onclick = () => encerrar(false);
        fechar.onclick = () => encerrar(false);
        modal.onclick = (e) => { if (e.target === modal) encerrar(false); };
        document.addEventListener("keydown", noEsc);
    });

    function texto_(t, msgTexto, rotulo, ehPerigo) {
        document.getElementById("confirmaTitulo").textContent = t;
        document.getElementById("confirmaTexto").textContent = msgTexto;
        sim.textContent = rotulo;
        sim.className = "dash-btn " + (ehPerigo ? "dash-btn--danger" : "dash-btn--primary");
    }
}

/* ------------------------------------------------------------
   O menu da engrenagem (rodapé da barra lateral)

   Guarda o que sobrou da Conta depois que o cartão do usuário passou
   a levar direto ao Perfil: as outras seções e o sair.

   Fecha por clique fora, por Escape e ao escolher um item. O Escape
   devolve o foco à engrenagem — quem abriu por teclado precisa voltar
   para onde estava, senão o foco cai no começo da página.
   ------------------------------------------------------------ */
function ativarMenuConfig() {
    const botao = document.getElementById("btnConfig");
    const menu  = document.getElementById("menuConfig");
    if (!botao || !menu) return;

    const aberto = () => botao.getAttribute("aria-expanded") === "true";

    function abrir(sim) {
        botao.setAttribute("aria-expanded", String(sim));
        menu.hidden = !sim;
    }

    botao.addEventListener("click", (e) => {
        e.stopPropagation();   // senão o clique fecha no mesmo instante
        abrir(!aberto());
    });

    // Um item escolhido é uma decisão tomada: o menu sai da frente.
    menu.addEventListener("click", (e) => {
        if (e.target.closest(".conf__item")) abrir(false);
    });

    document.addEventListener("click", (e) => {
        if (!aberto()) return;
        if (!menu.contains(e.target) && e.target !== botao) abrir(false);
    });

    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && aberto()) {
            abrir(false);
            botao.focus();
        }
    });
}

/* ------------------------------------------------------------
   Menu "Mais Ferramentas" no Mobile (Bottom Sheet).
   Abre a gaveta com Exercícios, Provas, Buscar e Conta
   mantendo a barra inferior com apenas 5 botões limpos.
   ------------------------------------------------------------ */
function ativarMenuMaisMobile() {
    const btn = document.getElementById("btnMaisMobile");
    const sheet = document.getElementById("sheetMaisMobile");
    const fechar = document.getElementById("btnFecharSheetMais");
    if (!btn || !sheet) return;

    const abrir = (sim) => {
        sheet.classList.toggle("aberto", sim);
        btn.setAttribute("aria-expanded", sim ? "true" : "false");
        sheet.setAttribute("aria-hidden", sim ? "false" : "true");
    };

    btn.addEventListener("click", () => abrir(true));
    if (fechar) fechar.addEventListener("click", () => abrir(false));
    sheet.addEventListener("click", (e) => {
        if (e.target === sheet) abrir(false);
    });
    window.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && sheet.classList.contains("aberto")) abrir(false);
    });
}

/* ------------------------------------------------------------
   Sair da conta (último item do menu da engrenagem).
   Mesmo caminho usado pela página Conta.
   ------------------------------------------------------------ */
function ativarSair() {
    // seletor por classe: a página Conta tem o próprio botão com id="btnSair"
    const botao = document.querySelector(".conf__item--sair");
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
