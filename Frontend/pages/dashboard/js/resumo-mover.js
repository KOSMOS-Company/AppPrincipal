/* ============================================================
   KOSMOS — resumo-mover.js
   Guardar um resumo num caderno, de dois jeitos que fazem a mesma
   coisa: arrastando o cartão, ou pelo menu "⋮" dele.

   Os dois existem de propósito. Arrastar é rápido e óbvio no
   computador, mas não funciona no toque (o HTML5 drag-and-drop
   simplesmente não existe no celular), nem no teclado, nem para
   quem usa leitor de tela. Então o menu é o caminho de verdade e o
   arrastar é o atalho — e não o contrário.

   Usado pela estante (resumos.php) e por dentro de um caderno
   (caderno.php). Quem usa escuta:
     document "resumo:movido" -> detail = { resumo, de, para, msg }

   `de` e `para` são ids de caderno, ou null para "Sem caderno".
   ============================================================ */
(() => {
    "use strict";

    const BACKEND = "../../../Backend/php";

    /* A lista de cadernos vem pronta do servidor, na própria página */
    let cadernos = [];
    try {
        cadernos = JSON.parse(document.getElementById("dadosCadernos")?.textContent || "[]");
    } catch (err) {
        cadernos = [];
    }

    /* ------------------------------------------------------------
       O pedido ao servidor
       ------------------------------------------------------------ */

    /**
     * Move um resumo. `cadernoId` null/"" tira do caderno.
     * Devolve true se deu certo (a tela decide o que fazer depois).
     */
    async function mover(resumoId, cadernoId) {
        const dados = new FormData();
        dados.append("resumo", resumoId);
        dados.append("caderno", cadernoId || "");

        try {
            const resp = await fetch(`${BACKEND}/resumos_mover.php`, { method: "POST", body: dados });
            const json = await resp.json();

            if (!json.ok) {
                avisar(json.msg || "Não foi possível mover o resumo.", true);
                return false;
            }

            // já estava no destino: nada mudou, nada a anunciar
            if (json.mudou === false) return true;

            document.dispatchEvent(new CustomEvent("resumo:movido", {
                detail: {
                    resumo: json.resumo,
                    de: json.de ?? null,
                    para: json.resumo.caderno_id ?? null,
                    msg: json.msg,
                },
            }));
            avisar(json.msg);
            return true;
        } catch (err) {
            avisar("Não foi possível falar com o servidor.", true);
            return false;
        }
    }

    /* ------------------------------------------------------------
       Menu "⋮" — o caminho de quem não arrasta
       ------------------------------------------------------------ */

    let menuAberto = null;

    /* Ícones do menu no traço do site. O emoji só aparece quando o
       próprio usuário escolheu um para o caderno. */
    const ICONE_DOC    = '<svg viewBox="0 0 24 24" fill="none"><rect x="5" y="3" width="14" height="18" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M9 8h6M9 12h6M9 16h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>';
    const ICONE_LIVRO  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4.5A1.5 1.5 0 0 1 6.5 3H19v15H6.5A1.5 1.5 0 0 0 5 19.5v-15Z"/><path d="M5 19.5A1.5 1.5 0 0 0 6.5 21H19v-3"/><path d="M9 7h6"/></svg>';
    const ICONE_LAPIS  = '<svg viewBox="0 0 20 20" fill="none"><path d="M4 16h3l8-8-3-3-8 8v3Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M12.5 4.5l3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>';
    const ICONE_CARTAS = '<svg viewBox="0 0 20 20" fill="none"><rect x="2.5" y="5.5" width="11" height="10" rx="1.6" stroke="currentColor" stroke-width="1.6"/><path d="M6.5 3.5H16a1.5 1.5 0 0 1 1.5 1.5v8.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>';
    const ICONE_LIXO   = '<svg viewBox="0 0 24 24" fill="none"><path d="M5 7h14M10 7V5h4v2M7 7l1 12h8l1-12M11 11v5M13 11v5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    function fecharMenu() {
        if (!menuAberto) return;
        menuAberto.botao?.setAttribute("aria-expanded", "false");
        menuAberto.el.remove();
        menuAberto = null;
        document.removeEventListener("keydown", noEscDoMenu, true);
    }

    function noEscDoMenu(e) {
        if (e.key === "Escape") {
            e.stopPropagation();   // não deixa o Esc fechar um modal atrás
            const botao = menuAberto?.botao;
            fecharMenu();
            botao?.focus();
        }
    }

    /**
     * Abre o menu de um cartão de resumo.
     * `aoEditar`, `aoApagar` e `aoFlashcards` são opcionais: a página
     * passa o que souber fazer, e o que não vier simplesmente não
     * aparece.
     */
    function abrirMenu(botao, resumo, acoes = {}) {
        // clicar de novo no mesmo botão fecha
        if (menuAberto && menuAberto.botao === botao) {
            fecharMenu();
            return;
        }
        fecharMenu();

        const atual = resumo.caderno_id ? Number(resumo.caderno_id) : null;

        const menu = document.createElement("div");
        menu.className = "rs-menu";
        menu.setAttribute("role", "menu");
        menu.setAttribute("aria-label", `Ações do resumo ${resumo.titulo || ""}`);

        const destinos = [{ id: null, nome: "Sem caderno", svg: ICONE_DOC }, ...cadernos];

        menu.innerHTML =
            '<span class="rs-menu__titulo">Mover para…</span>' +
            destinos.map((c) => {
                const aqui = (c.id ?? null) === atual;
                return `
                <button type="button" class="rs-menu__item${aqui ? " rs-menu__item--aqui" : ""}"
                        role="menuitemradio" aria-checked="${aqui}"
                        data-destino="${c.id ?? ""}"${aqui ? " disabled" : ""}>
                    <span class="rs-menu__icone" aria-hidden="true">${c.icone ? escaparHtml(c.icone) : (c.svg || ICONE_LIVRO)}</span>
                    <span class="rs-menu__nome">${escaparHtml(c.nome)}</span>
                    ${aqui ? '<span class="rs-menu__aqui" aria-hidden="true">aqui</span>' : ""}
                </button>`;
            }).join("") +
            ((acoes.aoEditar || acoes.aoApagar || acoes.aoFlashcards) ? '<span class="rs-menu__linha" role="separator"></span>' : "") +
            (acoes.aoEditar ? '<button type="button" class="rs-menu__item" role="menuitem" data-acao="editar"><span class="rs-menu__icone" aria-hidden="true">' + ICONE_LAPIS + '</span><span class="rs-menu__nome">Editar resumo</span></button>' : "") +
            /* "Gerar flashcards" só entra se a página souber fazer isso
               (ou seja, se ela incluiu o modal e o js/flashcards-gerar.js).
               Um item de menu que abre nada é pior do que item nenhum. */
            (acoes.aoFlashcards ? '<button type="button" class="rs-menu__item" role="menuitem" data-acao="flashcards"><span class="rs-menu__icone" aria-hidden="true">' + ICONE_CARTAS + '</span><span class="rs-menu__nome">Gerar flashcards</span></button>' : "") +
            /* No celular o lápis e a lixeira do cartão saem (resumos.css):
               o menu passa a ser o único caminho, então apagar mora aqui. */
            (acoes.aoApagar ? '<button type="button" class="rs-menu__item rs-menu__item--perigo" role="menuitem" data-acao="apagar"><span class="rs-menu__icone" aria-hidden="true">' + ICONE_LIXO + '</span><span class="rs-menu__nome">Excluir resumo</span></button>' : "");

        document.body.appendChild(menu);
        posicionar(menu, botao);
        botao.setAttribute("aria-expanded", "true");
        menuAberto = { el: menu, botao };

        menu.querySelector(".rs-menu__item:not([disabled])")?.focus();
        document.addEventListener("keydown", noEscDoMenu, true);

        menu.addEventListener("click", async (e) => {
            const item = e.target.closest("[data-destino], [data-acao]");
            if (!item) return;

            if (item.dataset.acao === "editar") {
                fecharMenu();
                acoes.aoEditar?.();
                return;
            }

            if (item.dataset.acao === "apagar") {
                fecharMenu();
                acoes.aoApagar?.();
                return;
            }

            if (item.dataset.acao === "flashcards") {
                fecharMenu();
                acoes.aoFlashcards?.();
                return;
            }

            const destino = item.dataset.destino || null;
            fecharMenu();
            await mover(resumo.id, destino);
        });
    }

    /**
     * Um menu curto com a mesma cara do de cima, para quem não é
     * resumo (ex.: o "⋮" do caderno no celular).
     * `itens` = [{ nome, svg, perigo?, aoEscolher }]
     */
    function abrirMenuSimples(botao, rotulo, itens) {
        if (menuAberto && menuAberto.botao === botao) {
            fecharMenu();
            return;
        }
        fecharMenu();

        const menu = document.createElement("div");
        menu.className = "rs-menu";
        menu.setAttribute("role", "menu");
        menu.setAttribute("aria-label", rotulo);
        menu.innerHTML = itens.map((it, i) => `
            <button type="button" class="rs-menu__item${it.perigo ? " rs-menu__item--perigo" : ""}"
                    role="menuitem" data-item="${i}">
                <span class="rs-menu__icone" aria-hidden="true">${it.svg || ""}</span>
                <span class="rs-menu__nome">${escaparHtml(it.nome)}</span>
            </button>`).join("");

        document.body.appendChild(menu);
        posicionar(menu, botao);
        botao.setAttribute("aria-expanded", "true");
        menuAberto = { el: menu, botao };

        menu.querySelector(".rs-menu__item")?.focus();
        document.addEventListener("keydown", noEscDoMenu, true);

        menu.addEventListener("click", (e) => {
            const item = e.target.closest("[data-item]");
            if (!item) return;
            fecharMenu();
            itens[Number(item.dataset.item)]?.aoEscolher?.();
        });
    }

    /** Encosta o menu no botão, sem deixar sair da tela. */
    function posicionar(menu, botao) {
        const r = botao.getBoundingClientRect();
        const largura = menu.offsetWidth || 220;
        const altura = menu.offsetHeight || 260;

        let x = r.right - largura;
        let y = r.bottom + 6;

        if (x < 8) x = 8;
        if (x + largura > window.innerWidth - 8) x = window.innerWidth - largura - 8;
        // não cabe embaixo: abre para cima
        if (y + altura > window.innerHeight - 8) y = Math.max(8, r.top - altura - 6);

        menu.style.left = `${x + window.scrollX}px`;
        menu.style.top  = `${y + window.scrollY}px`;
    }

    // clicar fora, rolar ou redimensionar fecha o menu
    document.addEventListener("click", (e) => {
        if (!menuAberto) return;
        if (menuAberto.el.contains(e.target) || menuAberto.botao.contains(e.target)) return;
        fecharMenu();
    });
    window.addEventListener("resize", fecharMenu);
    window.addEventListener("scroll", fecharMenu, true);

    /* ------------------------------------------------------------
       Arrastar e soltar
       ------------------------------------------------------------ */

    /* O que está sendo arrastado agora. O dataTransfer sozinho não
       serve para decidir se um alvo aceita a soltura: durante o
       dragover o navegador esconde os dados por segurança, e sem
       saber o que vem não dá para acender o alvo certo. */
    let arrastando = null;

    /** Liga o arraste nos cartões de resumo dentro de um container. */
    function ligarArrasteDeResumos(container, aoComecar) {
        if (!container) return;

        container.addEventListener("dragstart", (e) => {
            const card = e.target.closest(".resumo-card[draggable='true']");
            if (!card) return;

            arrastando = { tipo: "resumo", id: Number(card.dataset.id) };
            card.classList.add("arrastando");
            document.body.classList.add("arrastando-resumo");

            e.dataTransfer.effectAllowed = "move";
            // o texto é para outros programas; quem decide aqui é `arrastando`
            e.dataTransfer.setData("text/plain", card.querySelector(".resumo-card__title")?.textContent || "");

            aoComecar?.(arrastando.id);
        });

        container.addEventListener("dragend", (e) => {
            e.target.closest(".resumo-card")?.classList.remove("arrastando");
            document.body.classList.remove("arrastando-resumo");
            arrastando = null;
            document.dispatchEvent(new CustomEvent("resumo:arraste-fim"));
        });
    }

    /**
     * Faz de um elemento um alvo de soltura para resumos.
     * `aoSoltar(resumoId)` é chamado quando a soltura acontece.
     * `aceita(resumoId)` pode recusar (ex.: o resumo já está aqui).
     */
    function ligarAlvoDeResumo(elemento, aoSoltar, aceita) {
        if (!elemento) return;

        elemento.addEventListener("dragover", (e) => {
            if (!arrastando || arrastando.tipo !== "resumo") return;
            if (aceita && !aceita(arrastando.id)) return;

            e.preventDefault();               // sem isto o navegador recusa a soltura
            e.dataTransfer.dropEffect = "move";
            elemento.classList.add("alvo-aceso");
        });

        elemento.addEventListener("dragleave", (e) => {
            // sair para um filho não é sair do alvo
            if (elemento.contains(e.relatedTarget)) return;
            elemento.classList.remove("alvo-aceso");
        });

        elemento.addEventListener("drop", (e) => {
            if (!arrastando || arrastando.tipo !== "resumo") return;
            e.preventDefault();
            elemento.classList.remove("alvo-aceso");
            aoSoltar(arrastando.id);
        });
    }

    /** O que está sendo arrastado, para quem precisar decidir algo. */
    function oQueVemAi() {
        return arrastando;
    }

    /** Usado pelo reordenar de cadernos, que tem o próprio arraste. */
    function definirArrastando(valor) {
        arrastando = valor;
    }

    /* ------------------------------------------------------------
       Aviso curto (mesmo padrão dos flashcards)
       ------------------------------------------------------------ */
    let avisoTimer = null;
    function avisar(texto, erro = false) {
        let caixa = document.getElementById("rsAviso");
        if (!caixa) {
            caixa = document.createElement("div");
            caixa.id = "rsAviso";
            caixa.className = "fc-aviso";
            caixa.setAttribute("role", "status");
            caixa.setAttribute("aria-live", "polite");
            document.body.appendChild(caixa);
        }

        caixa.textContent = texto;
        caixa.classList.toggle("fc-aviso--erro", erro);
        caixa.hidden = false;

        clearTimeout(avisoTimer);
        avisoTimer = setTimeout(() => { caixa.hidden = true; }, erro ? 5000 : 2600);
    }
    window.KosmosMover = {
        mover,
        abrirMenu,
        abrirMenuSimples,
        icones: { lapis: ICONE_LAPIS, lixo: ICONE_LIXO },
        fecharMenu,
        ligarArrasteDeResumos,
        ligarAlvoDeResumo,
        oQueVemAi,
        definirArrastando,
        avisar,
        /** a página troca a lista quando um caderno é criado/apagado */
        definirCadernos: (lista) => { cadernos = lista || []; },
    };
})();
