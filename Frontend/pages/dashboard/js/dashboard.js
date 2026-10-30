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
    ativarGavetaMobile();
    ativarSair();
    ativarBrilhoNosCards();
});

/* ------------------------------------------------------------
   Steppers numéricos (− / +) — compartilhado por todas as abas

   Todo campo type=number com .qtd-stepper nasce com os dois
   botões; quem os liga é este único ouvinte delegado. O passo
   respeita min/max/step do próprio input (a nota usa 0.01, a
   meta diária usa 5), e o valor muda via "input" — é o mesmo
   evento de digitação, então quem escuta o campo (Conta,
   Exercícios, notas de Provas) nem percebe que foram os botões.
   ------------------------------------------------------------ */
document.addEventListener("click", (e) => {
    const btn = e.target.closest(".qtd-stepper__btn");
    if (!btn) return;

    const input = btn.closest(".qtd-stepper")?.querySelector('input[type="number"]');
    if (!input) return;
    e.preventDefault();

    const passo = Number(btn.dataset.passo) || 1;
    const min   = input.min !== "" ? parseFloat(input.min) : 0;
    const max   = input.max !== "" ? parseFloat(input.max) : Infinity;
    const step  = input.step !== "" && input.step !== "any" ? parseFloat(input.step) : 1;

    let valor = parseFloat(String(input.value).replace(",", "."));
    if (!Number.isFinite(valor)) {
        // vazio: diminuir não faz sentido (não há de onde descer)
        if (passo < 0) return;
        valor = min;
    }

    valor = Math.min(max, Math.max(min, valor + passo * step));
    // volta ao passo exato: evita 0.30000000000000004 no 0.01 da nota
    valor = Math.round(valor / step) * step;
    input.value = String(Number(valor.toFixed(10)));
    input.dispatchEvent(new Event("input", { bubbles: true }));
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
        botao.title = recolhida ? "Expandir menu" : "Recolher menu";

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

    const mover = (alvo) => {
        if (!alvo || !alvo.offsetHeight) {
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
    /* .resumo-card NÃO entra aqui: desde que os resumos viraram lista,
       ele é uma LINHA da largura toda, e girar uma linha inteira em
       3D deforma o texto em vez de levantar o cartão. O realce da
       lista é o do próprio css (fundo e filete no hover). */
    const SELETOR = "a.ini-card, .deck-card";
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
   Pedir um texto (compartilhada por todas as abas)
   Abre a mini caixa com um campo e devolve uma promessa: o texto
   (já aparado) se a pessoa salvou, null se desistiu. Um vazio não
   fecha — avisa dentro do próprio modal, sem alert nativo.
   Serve para renomear sem sair da página (ex.: título de lista).
   O HTML do modal vem de partes/modal-pede.php; se a página não
   o incluir, cai no prompt nativo para não travar a ação.
   ------------------------------------------------------------ */
function pedir({ titulo, rotulo, valor = "", botao = "Salvar" }) {
    const modal = document.getElementById("modalPede");
    const input = document.getElementById("pedeInput");
    const erro = document.getElementById("pedeErro");
    const sim = document.getElementById("pedeSim");
    const nao = document.getElementById("pedeNao");
    const fechar = document.getElementById("pedeFechar");

    // sem o modal na página (outra aba), usa o prompt nativo
    if (!modal || !sim) {
        const resposta = window.prompt(rotulo, valor);
        return Promise.resolve(resposta === null ? null : resposta.trim());
    }

    document.getElementById("pedeTitulo").textContent = titulo;
    document.getElementById("pedeRotulo").textContent = rotulo;
    input.value = valor;
    erro.hidden = true;
    erro.textContent = "";
    sim.textContent = botao;
    modal.classList.add("open");
    input.focus();
    input.select();

    return new Promise((resolve) => {
        const encerrar = (resposta) => {
            modal.classList.remove("open");
            sim.onclick = null;
            nao.onclick = null;
            fechar.onclick = null;
            modal.onclick = null;
            input.onkeydown = null;
            document.removeEventListener("keydown", noEsc);
            resolve(resposta);
        };
        const noEsc = (e) => { if (e.key === "Escape") encerrar(null); };
        const salvar = () => {
            const texto = input.value.trim();
            if (!texto) {
                erro.textContent = "O título não pode ficar vazio.";
                erro.className = "msg msg--erro";
                erro.hidden = false;
                input.focus();
                return;
            }
            encerrar(texto);
        };

        sim.onclick = salvar;
        nao.onclick = () => encerrar(null);
        fechar.onclick = () => encerrar(null);
        modal.onclick = (e) => { if (e.target === modal) encerrar(null); };
        input.onkeydown = (e) => { if (e.key === "Enter") salvar(); };
        document.addEventListener("keydown", noEsc);
    });
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
   Gaveta lateral do celular (as três barrinhas do topo)
   No celular não há barra embaixo: a navegação inteira — a mesma do
   computador, na mesma ordem — sai da esquerda. Fecha pelo X, tocando
   no escuro ao lado, pelo Esc ou arrastando a gaveta para a esquerda
   (ela segue o dedo e, ao soltar, decide pela distância e pelo
   impulso). Quem mexe no visual é html.gaveta-aberta (dashboard.css).
   ------------------------------------------------------------ */
function ativarGavetaMobile() {
    const botao = document.getElementById("btnMenuMobile");
    const gaveta = document.getElementById("gavetaMobile");
    const veu = document.getElementById("gavetaVeu");
    const fechar = document.getElementById("btnFecharGaveta");
    if (!botao || !gaveta) return;

    const raiz = document.documentElement;
    const celular = window.matchMedia("(max-width: 768px)");
    const aberta = () => raiz.classList.contains("gaveta-aberta");

    function abrir(sim) {
        raiz.classList.toggle("gaveta-aberta", sim);
        raiz.style.removeProperty("--gaveta-dx");
        botao.setAttribute("aria-expanded", sim ? "true" : "false");
        if (sim) {
            // o foco entra na gaveta (leitor de tela e Tab começam por ela)
            // sem acender o contorno de foco num item ao abrir pelo toque
            gaveta.focus({ preventScroll: true });
        } else if (gaveta.contains(document.activeElement)) {
            botao.focus({ preventScroll: true });
        }
    }

    botao.addEventListener("click", () => abrir(!aberta()));
    fechar?.addEventListener("click", () => abrir(false));
    veu?.addEventListener("click", () => abrir(false));
    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && aberta()) abrir(false);
    });
    // girou o aparelho para o tamanho do computador: a gaveta não existe lá
    celular.addEventListener("change", () => { if (!celular.matches && aberta()) abrir(false); });
    // voltou pelo histórico (página guardada em memória): chega fechada
    window.addEventListener("pageshow", () => { if (aberta()) abrir(false); });

    /* Arrastar para a esquerda fecha. 10px de folga antes de decidir que
       é arrasto (e não um toque num link) e que é de lado (e não a
       rolagem da própria gaveta, quando ela é mais alta que a tela). */
    let arrasto = null;
    let ignorarClique = false;
    gaveta.addEventListener("pointerdown", (e) => {
        if (!aberta() || e.pointerType === "mouse") return;
        ignorarClique = false;
        arrasto = { id: e.pointerId, x: e.clientX, y: e.clientY, t: e.timeStamp, dx: 0, preso: false };
    });
    gaveta.addEventListener("pointermove", (e) => {
        if (!arrasto || e.pointerId !== arrasto.id) return;
        const dx = e.clientX - arrasto.x;
        const dy = e.clientY - arrasto.y;
        if (!arrasto.preso) {
            if (Math.abs(dx) < 10 && Math.abs(dy) < 10) return;
            if (Math.abs(dy) > Math.abs(dx) || dx > 0) { arrasto = null; return; }
            arrasto.preso = true;
            raiz.classList.add("gaveta-arrastando");
        }
        arrasto.dx = Math.min(0, dx);
        arrasto.v = arrasto.dx / Math.max(1, e.timeStamp - arrasto.t);   // px/ms
        raiz.style.setProperty("--gaveta-dx", arrasto.dx + "px");
    });
    const soltar = () => {
        if (!arrasto) return;
        const { preso, dx, v = 0 } = arrasto;
        arrasto = null;
        if (!preso) return;
        raiz.classList.remove("gaveta-arrastando");
        ignorarClique = true;
        // passou de um terço da largura, ou foi um peteleco rápido: fecha
        if (dx < -gaveta.offsetWidth / 3 || v < -0.5) abrir(false);
        else raiz.style.removeProperty("--gaveta-dx");
    };
    gaveta.addEventListener("pointerup", soltar);
    gaveta.addEventListener("pointercancel", soltar);
    // o clique que vem depois de um arrasto não pode abrir um link
    gaveta.addEventListener("click", (e) => {
        if (ignorarClique) { e.preventDefault(); ignorarClique = false; }
    }, true);
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
            await fetch(`${API}/logout.php`, { method: "POST" });
        } catch (err) {
            /* mesmo se falhar, vamos para o login */
        }
        sessionStorage.removeItem("kosmos_usuario");
        sessionStorage.removeItem("kosmos_intro");
        window.location.replace("../login/index.html");
    });
}

/* Transição suave ao trocar de aba: faz o conteúdo sair (fade-out)
   e só então navega. A entrada (fade-in) é feita por CSS em .contMeio.

   Onde há View Transition entre páginas (o CSS liga com
   @view-transition), o próprio navegador faz a saída — e segurar o
   clique 260ms só somaria espera. Então este fade fica apenas para
   os navegadores sem ela. `onpagereveal` chegou junto com esse
   recurso, por isso serve de teste. */
function ativarTransicoes() {
    const nativa = "onpagereveal" in window;
    const semMovimento = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    if (nativa || semMovimento) return;

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

/* ------------------------------------------------------------
   Capas sob demanda (economia de dados)
   As capas dos cadernos são imagem de fundo, e imagem de fundo não
   tem loading="lazy": a lista inteira baixava todas de uma vez,
   inclusive as que estavam bem abaixo da tela. Agora o card traz a
   URL em data-capa e a imagem só entra quando ele chega perto da
   tela (300px antes, para já estar pronta quando aparecer).
   Vale para os cards pintados pelo PHP e para os que o JS cria
   depois (busca, filtros, novo caderno) — por isso o
   MutationObserver.
   ------------------------------------------------------------ */
(function () {
    const aplicar = (el) => {
        const url = el.getAttribute("data-capa");
        if (!url) return;
        el.style.backgroundImage = "url(" + JSON.stringify(url) + ")";
        el.removeAttribute("data-capa");
    };

    // sem IntersectionObserver (navegador muito antigo): carrega tudo, como antes
    if (!("IntersectionObserver" in window)) {
        const tudo = () => document.querySelectorAll("[data-capa]").forEach(aplicar);
        document.addEventListener("DOMContentLoaded", tudo);
        new MutationObserver(tudo).observe(document.documentElement, { childList: true, subtree: true });
        return;
    }

    const io = new IntersectionObserver((entradas) => {
        entradas.forEach((e) => {
            if (!e.isIntersecting) return;
            aplicar(e.target);
            io.unobserve(e.target);
        });
    }, { rootMargin: "300px 0px" });

    const vigiar = (raiz) => {
        if (raiz.nodeType !== 1) return;
        if (raiz.hasAttribute("data-capa")) io.observe(raiz);
        raiz.querySelectorAll("[data-capa]").forEach((el) => io.observe(el));
    };

    const iniciar = () => {
        vigiar(document.body);
        new MutationObserver((mudancas) => {
            mudancas.forEach((m) => m.addedNodes.forEach(vigiar));
        }).observe(document.body, { childList: true, subtree: true });
    };
    if (document.body) iniciar();
    else document.addEventListener("DOMContentLoaded", iniciar);
})();
