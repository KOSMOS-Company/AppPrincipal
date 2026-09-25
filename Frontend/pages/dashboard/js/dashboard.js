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

/* A troca de aba roda JÁ, e não no DOMContentLoaded: o deslize da pílula
   tem de estar montado antes da primeira pintura — senão a barra aparece
   no lugar final e depois pula para trás para animar. Este script vem no
   fim do <body>, então a barra de baixo já existe aqui. */
ativarTrocaDeAba();

document.addEventListener("DOMContentLoaded", () => {
    ativarTransicoes();
    ativarMarcador();
    ativarRecolher();
    ativarMenuConfig();
    ativarMenuMaisMobile();
    ativarSair();
    ativarBrilhoNosCards();
    ativarTopoRecolhivel();
});

/* ------------------------------------------------------------
   Troca de aba na barra de baixo (só no celular)

   Cada aba é uma página nova, então não há "a mesma barra" para
   animar de um estado a outro. O truque é o FLIP entre páginas:
     1. ao tocar numa aba, guarda onde estava cada item da barra;
     2. na página nova, antes de pintar, põe cada item onde ESTAVA
        (transform) e o solta para o lugar novo.
   A pílula ativa parte da aba anterior e viaja até a nova; os outros
   ícones escorregam para abrir espaço. Só transform, via WAAPI — roda
   no compositor, sem biblioteca.

   Trocar de aba acontece dezenas de vezes por dia: curto (280 ms),
   ease-out forte, sem quique. Quem pediu menos movimento não vê nada
   disso — a barra simplesmente já está no lugar.
   ------------------------------------------------------------ */
/** Onde está cada item visível da barra, relativo à própria barra. */
function medirBarra(nav) {
    const base = nav.getBoundingClientRect();
    const itens = {};
    nav.querySelectorAll("a[href]").forEach((a) => {
        if (!a.offsetWidth) return;                       // os secundários ficam escondidos no celular
        const caixa = a.getBoundingClientRect();
        const icone = (a.querySelector(".nav-icon") || a).getBoundingClientRect();
        itens[a.getAttribute("href")] = {
            x: caixa.left - base.left,                    // a pílula anda pela caixa inteira
            ico: icone.left + icone.width / 2 - base.left // os ícones soltos, pelo centro
        };
    });
    return itens;
}

function ativarTrocaDeAba() {
    // A chave mora aqui dentro (e não num const lá fora) porque esta função
    // é chamada no topo do arquivo, antes de um const de fora existir.
    const CHAVE_ABA = "kosmos_aba_anterior";
    const nav = document.querySelector(".botoesL");
    if (!nav) return;
    const celular = window.matchMedia("(max-width: 768px)");
    const semMovimento = window.matchMedia("(prefers-reduced-motion: reduce)");

    // 1) Tocou numa aba: guarda como a barra estava
    nav.addEventListener("click", (e) => {
        const a = e.target.closest("a[href]");
        if (!a || !celular.matches || a.classList.contains("active")) return;
        const ativo = nav.querySelector("a.active");
        try {
            sessionStorage.setItem(CHAVE_ABA, JSON.stringify({
                t: Date.now(),
                largura: window.innerWidth,
                de: ativo ? ativo.getAttribute("href") : null,
                itens: medirBarra(nav),
            }));
        } catch (err) { /* navegação privada: troca sem animação */ }
    });

    // 2) Página nova: parte de como a barra estava e desliza para o lugar
    let antes = null;
    try {
        antes = JSON.parse(sessionStorage.getItem(CHAVE_ABA));
        sessionStorage.removeItem(CHAVE_ABA);
    } catch (err) { return; }

    // Só vale para a navegação que acabou de acontecer, na mesma largura
    // (voltar pelo histórico horas depois, ou girar a tela, não animam)
    if (!antes || Date.now() - antes.t > 3000 || antes.largura !== window.innerWidth) return;
    if (!celular.matches || semMovimento.matches) return;

    const ativo = nav.querySelector("a.active");
    const origemPilula = antes.de && antes.itens[antes.de];
    const opcoes = { duration: 280, easing: "cubic-bezier(0.23, 1, 0.32, 1)" };

    // Fica na barra até a próxima página: desliga a entrada própria do chip
    // (se a classe saísse no fim, a animação de entrada rodaria DEPOIS do
    // deslize, e o chip daria um pulo) e o põe por cima dos vizinhos.
    nav.classList.add("nav--deslizando");

    /* Cada item parte de onde estava. Devolve as animações criadas. */
    function montar() {
        const agora = medirBarra(nav);
        const criadas = [];
        nav.querySelectorAll("a[href]").forEach((a) => {
            const href = a.getAttribute("href");
            const novo = agora[href];
            if (!novo) return;

            const dx = a === ativo && origemPilula
                ? origemPilula.x - novo.x                                  // a pílula vem da aba anterior
                : (antes.itens[href] ? antes.itens[href].ico - novo.ico : 0); // cada ícone, do lugar dele
            if (Math.abs(dx) < 1) return;

            criadas.push(a.animate([{ transform: `translateX(${dx}px)` }, { transform: "none" }], opcoes));
        });
        return criadas;
    }

    /* As posições dependem da largura do rótulo ("Biblioteca"), e ela
       muda quando a fonte da página termina de carregar. Medir antes
       disso faria a pílula partir fora do lugar. Então: com a fonte
       pronta, desliza já; sem ela, segura a barra no ponto de partida
       (animação pausada no começo — já é o que a primeira pintura mostra),
       e quando a fonte chegar, mede de novo e solta. Rede lenta não
       prende a barra: 150 ms depois solta de qualquer jeito. */
    if (!document.fonts || document.fonts.status === "loaded") {
        montar();
        return;
    }
    const segurando = montar();
    segurando.forEach((an) => an.pause());
    let soltou = false;
    const soltar = () => {
        if (soltou) return;
        soltou = true;
        segurando.forEach((an) => an.cancel());
        montar();
    };
    document.fonts.ready.then(soltar);
    setTimeout(soltar, 150);
}

/* ------------------------------------------------------------
   Topo que some ao rolar (só no celular), como no Instagram

   Rolou para baixo: o topo sai junto com o conteúdo, 1:1 com o dedo.
   Rolou para cima (qualquer quantidade): ele volta, também 1:1. Quando
   a rolagem para no meio do caminho, ele termina o movimento na direção
   em que a pessoa rolava — subindo, abre; descendo, fecha. Nunca fica
   meio topo pendurado, e uma rolada curta para cima já basta para vê-lo.

   Como: o topo é `position: sticky` e escondê-lo é pôr um `top`
   negativo. Sticky com top = -X fica na maior entre a posição normal
   e -X, então no alto da página ele rola naturalmente e depois fica
   exatamente X pixels escondido. Nada de transform — ver o comentário
   do .contLateral no dashboard.css (a barra de baixo mora dentro dele).
   ------------------------------------------------------------ */
function ativarTopoRecolhivel() {
    const topo = document.querySelector(".contLateral");
    if (!topo) return;

    const celular = window.matchMedia("(max-width: 768px)");
    const semMovimento = window.matchMedia("(prefers-reduced-motion: reduce)");

    let altura = 0;        // quanto o topo precisa subir para sumir inteiro
    let escondido = 0;     // 0 = todo visível ... altura = todo escondido
    let ultimoY = 0;
    let subindo = false;   // a direção da última rolada decide o encaixe
    let agendado = false;
    let parou = 0;

    const aplicar = () => { topo.style.top = escondido ? -escondido + "px" : ""; };
    const medir = () => { altura = topo.offsetHeight; };

    // O painel do chip (sequência + nível) não pode ficar boiando sozinho
    const avisarQueSumiu = () => document.dispatchEvent(new CustomEvent("kosmos:topo-escondido"));

    function assentar(alvo) {
        if (!semMovimento.matches) {
            topo.classList.add("topo--assentando");
            setTimeout(() => topo.classList.remove("topo--assentando"), 280);
        }
        escondido = alvo;
        aplicar();
        if (alvo) avisarQueSumiu();
    }

    function aoRolar() {
        agendado = false;
        if (!celular.matches) return;

        // Rubber band do iOS (y < 0 ou além do fim) não conta como rolagem
        const max = document.documentElement.scrollHeight - window.innerHeight;
        const y = Math.min(Math.max(window.scrollY, 0), Math.max(max, 0));
        const delta = y - ultimoY;
        ultimoY = y;
        if (!delta) return;
        subindo = delta < 0;

        topo.classList.remove("topo--assentando");
        const antes = escondido;
        escondido = Math.min(Math.max(escondido + delta, 0), altura);
        if (escondido !== antes) {
            aplicar();
            if (escondido > 0 && antes === 0) avisarQueSumiu();
        }

        // Parou no meio? Termina o movimento na direção em que rolava.
        clearTimeout(parou);
        parou = setTimeout(() => {
            if (escondido > 0 && escondido < altura) {
                // no alto da página não há o que esconder: fica aberto
                assentar(subindo || window.scrollY < altura ? 0 : altura);
            }
        }, 140);
    }

    window.addEventListener("scroll", () => {
        if (!agendado) { agendado = true; requestAnimationFrame(aoRolar); }
    }, { passive: true });

    // Quem navega por teclado e entra no topo precisa enxergá-lo
    topo.addEventListener("focusin", () => { if (escondido) assentar(0); });

    const reiniciar = () => {
        clearTimeout(parou);
        escondido = 0;
        aplicar();                 // no computador o topo é a barra lateral: sem `top` inline
        medir();
        ultimoY = Math.max(window.scrollY, 0);
    };
    celular.addEventListener("change", reiniciar);
    window.addEventListener("resize", medir);
    reiniciar();
}

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
