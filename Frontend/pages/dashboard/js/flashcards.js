/* ============================================================
   KOSMOS — flashcards.js
   Agora com backend: os decks e os cartões são do usuário logado
   e ficam no MySQL. Nada aqui é mockado.

   Três visões na mesma página:
     1. decks    — seus baralhos + estatísticas
     2. cartoes  — criar/editar/excluir os cartões de um deck
     3. estudo   — revisar, dizendo o que já sabe

   Tudo roda dentro de uma IIFE: o dashboard.js já ocupa o escopo
   global (é ele quem declara "API"), então nada é declarado solto
   aqui para não haver colisão de nomes entre os scripts.
   ============================================================ */

(() => {
    "use strict";

    // ---------- Estado da página ----------
    const estado = {
        decks: [],
        totais: null,
        filtro: "todos",
        deck: null,      // deck aberto nas visões 2 e 3
        cartoes: [],     // cartões do deck aberto
        estudo: null,    // sessão de estudo em andamento
    };

    // ---------- Atalhos de DOM ----------
    const $ = (sel) => document.querySelector(sel);

    const views = {
        decks:   $("#viewDecks"),
        cartoes: $("#viewCartoes"),
        estudo:  $("#viewEstudo"),
    };

    const decksGrid    = $("#decksGrid");
    const vazio        = $("#vazio");
    const filtros      = $("#filtros");
    const fcStats      = $("#fcStats");
    const cartoesLista = $("#cartoesLista");
    const cartoesVazio = $("#cartoesVazio");

    const ICONE_EDITAR  = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 20h4l10-10a2.8 2.8 0 0 0-4-4L4 16v4Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>';
    const ICONE_EXCLUIR = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 7h14M10 7V5h4v2M7 7l1 12h8l1-12M11 11v5M13 11v5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    /* ============================================================
       CONVERSA COM O BACKEND
       ============================================================ */

    /**
     * Chama um endpoint de flashcards.
     * Sem "dados" faz GET; com "dados" faz POST (FormData).
     * Devolve o JSON já pronto ou lança um Error com a mensagem
     * do servidor — quem chama decide o que mostrar.
     */
    async function pedir(arquivo, dados) {
        let resposta;

        try {
            resposta = await fetch(`${API}/${arquivo}`, dados
                ? { method: "POST", body: paraFormData(dados) }
                : undefined);
        } catch {
            throw new Error("Sem conexão com o servidor.");
        }

        // Sessão caiu no meio do caminho: volta para o login
        if (resposta.status === 401) {
            window.location.replace("../login/index.html");
            throw new Error("Sessão encerrada.");
        }

        let json = null;
        try {
            json = await resposta.json();
        } catch {
            throw new Error("Resposta inesperada do servidor.");
        }

        if (!json || json.ok !== true) {
            throw new Error(json?.msg || "Não foi possível concluir a ação.");
        }

        return json;
    }

    function paraFormData(dados) {
        const fd = new FormData();
        Object.entries(dados).forEach(([chave, valor]) => fd.append(chave, valor));
        return fd;
    }

    /* ============================================================
       VISÃO 1 — DECKS
       ============================================================ */

    async function carregarDecks() {
        decksGrid.setAttribute("aria-busy", "true");
        if (!estado.decks.length) {
            decksGrid.innerHTML = '<p class="fc-carregando">Carregando seus decks…</p>';
        }

        try {
            const json = await pedir("flashcards_listar.php");
            estado.decks  = json.decks;
            estado.totais = json.totais;

            // um filtro que não existe mais (última matéria removida) volta pra "todos"
            const materias = materiasDoUsuario();
            if (estado.filtro !== "todos" && !materias.includes(estado.filtro)) {
                estado.filtro = "todos";
            }

            renderStats();
            renderFiltros(materias);
            renderDecks();
        } catch (erro) {
            decksGrid.innerHTML = "";
            vazio.hidden = false;
            aviso(erro.message, true);
        } finally {
            decksGrid.setAttribute("aria-busy", "false");
        }
    }

    function materiasDoUsuario() {
        return [...new Set(estado.decks.map((d) => d.materia))]
            .sort((a, b) => a.localeCompare(b, "pt-BR"));
    }

    function renderStats() {
        const t = estado.totais;
        const valores = {
            decks:     t.decks,
            cartoes:   t.cartoes,
            revisados: t.revisados,
            dominio:   t.cartoes ? `${t.dominio}%` : 0,
        };

        Object.entries(valores).forEach(([chave, valor]) => {
            const alvo = fcStats.querySelector(`[data-stat="${chave}"]`);
            if (!alvo) return;

            const zerado = !valor || valor === "0%";
            alvo.textContent = zerado ? "—" : valor;
            alvo.closest(".ini-stat").classList.toggle("ini-stat--vazio", zerado);
        });
    }

    function renderFiltros(materias) {
        // com uma matéria só, filtrar não ajuda em nada
        filtros.hidden = materias.length < 2;
        if (filtros.hidden) {
            filtros.innerHTML = "";
            return;
        }

        const chip = (valor, rotulo) =>
            `<button class="chip${estado.filtro === valor ? " active" : ""}" data-materia="${esc(valor)}">${esc(rotulo)}</button>`;

        filtros.innerHTML = chip("todos", "Todos") + materias.map((m) => chip(m, m)).join("");
    }

    function renderDecks() {
        const lista = estado.decks.filter(
            (d) => estado.filtro === "todos" || d.materia === estado.filtro
        );

        decksGrid.innerHTML = lista.map((d, i) => {
            const dominio = d.cartoes ? Math.round((d.dominados / d.cartoes) * 100) : 0;
            const semCartoes = d.cartoes === 0;

            return `
            <article class="deck-card anim-in" style="animation-delay:${i * 0.05}s">
                <div class="deck-card__topo">
                    <span class="materia-tag">${esc(d.materia)}</span>
                    <div class="deck-card__acoes">
                        <button class="icone-btn" data-editar-deck="${d.id}"
                                title="Renomear deck" aria-label="Renomear o deck ${esc(d.nome)}">${ICONE_EDITAR}</button>
                        <button class="icone-btn icone-btn--perigo" data-excluir-deck="${d.id}"
                                title="Excluir deck" aria-label="Excluir o deck ${esc(d.nome)}">${ICONE_EXCLUIR}</button>
                    </div>
                </div>

                <h3 class="deck-card__nome">${esc(d.nome)}</h3>

                <span class="deck-card__qtd">
                    <strong>${d.cartoes}</strong> ${plural(d.cartoes, "cartão", "cartões")}
                    ${semCartoes ? "" : ` · <strong>${d.dominados}</strong> que você já sabe`}
                </span>

                ${semCartoes ? "" : `<div class="deck-card__barra" role="img"
                        aria-label="${dominio}% do deck dominado"><span style="width:${dominio}%"></span></div>`}

                <span class="deck-card__quando">${esc(d.quando)}</span>

                <div class="deck-card__botoes">
                    <button class="dash-btn dash-btn--outline" data-cartoes="${d.id}">Cartões</button>
                    <button class="dash-btn dash-btn--ghost" data-estudar="${d.id}"
                            ${semCartoes ? "disabled title='Adicione cartões primeiro'" : ""}>Estudar</button>
                </div>
            </article>`;
        }).join("");

        // "nenhum deck" é diferente de "nenhum deck NESTE filtro"
        const nenhum = estado.decks.length === 0;
        vazio.hidden = lista.length > 0;
        if (!vazio.hidden) {
            vazio.querySelector("h3").textContent = nenhum
                ? "Nenhum deck por aqui"
                : "Nenhum deck nesta matéria";
            vazio.querySelector("p").textContent = nenhum
                ? "Crie seu primeiro baralho e comece a escrever os cartões do jeito que você estuda."
                : "Troque o filtro acima ou crie um deck para esta matéria.";
        }
    }

    /* ============================================================
       VISÃO 2 — CARTÕES DE UM DECK
       ============================================================ */

    async function abrirCartoes(deckId, mudarDeView = true) {
        try {
            const json = await pedir(`flashcards_cartoes.php?deck=${deckId}`);
            estado.deck    = json.deck;
            estado.cartoes = json.cartoes;

            $("#cartoesMateria").textContent = json.deck.materia;
            $("#cartoesTitulo").textContent  = json.deck.nome;

            renderCartoes();
            if (mudarDeView) mostrarView("cartoes");
        } catch (erro) {
            aviso(erro.message, true);
        }
    }

    function renderCartoes() {
        const total     = estado.cartoes.length;
        const dominados = estado.cartoes.filter((c) => c.ultimo === true).length;

        $("#cartoesResumo").textContent = total === 0
            ? "Nenhum cartão ainda."
            : `${total} ${plural(total, "cartão", "cartões")} · `
              + (dominados === 0 ? "nenhum revisado ainda" : `${dominados} que você já sabe`);

        $("#btnEstudarDaqui").disabled = total === 0;
        cartoesVazio.hidden = total > 0;

        cartoesLista.innerHTML = estado.cartoes.map((c, i) => {
            const estadoClasse = c.ultimo === null ? "" : (c.ultimo ? " cartao-item__estado--ok" : " cartao-item__estado--rev");
            const estadoTitulo = c.ultimo === null
                ? "Ainda não revisado"
                : (c.ultimo ? "Você acertou na última revisão" : "Marcado para revisar");

            return `
            <li class="cartao-item anim-in" style="animation-delay:${Math.min(i, 12) * 0.03}s">
                <span class="cartao-item__estado${estadoClasse}" title="${estadoTitulo}"
                      role="img" aria-label="${estadoTitulo}"></span>
                <div class="cartao-item__texto">
                    <strong>${esc(c.frente)}</strong>
                    <span>${esc(c.verso)}</span>
                </div>
                <span class="cartao-item__nums">${
                    c.revisoes ? `${c.revisoes} ${plural(c.revisoes, "revisão", "revisões")} · ${c.acertos}/${c.revisoes}` : "novo"
                }</span>
                <div class="cartao-item__acoes">
                    <button class="icone-btn" data-editar-cartao="${c.id}"
                            title="Editar cartão" aria-label="Editar cartão">${ICONE_EDITAR}</button>
                    <button class="icone-btn icone-btn--perigo" data-excluir-cartao="${c.id}"
                            title="Excluir cartão" aria-label="Excluir cartão">${ICONE_EXCLUIR}</button>
                </div>
            </li>`;
        }).join("");
    }

    /* ============================================================
       VISÃO 3 — ESTUDO
       ============================================================ */

    const flashcard   = $("#flashcard");
    const avaliacao   = $("#estudoAvaliacao");
    const estudoFim   = $("#estudoFim");
    const barEstudo   = $("#barEstudo");
    const estudoNav   = $(".estudo-nav");

    async function iniciarEstudo(deckId) {
        // sempre busca do banco: assim o estudo já sai com o deck atualizado
        await abrirCartoes(deckId, false);

        if (!estado.cartoes.length) {
            aviso("Este deck ainda não tem cartões.", true);
            return;
        }

        estado.estudo = {
            deckId,
            ordem: estado.cartoes.map((_, i) => i),
            i: 0,
            respostas: new Map(),
        };

        $("#estudoTitulo").textContent = estado.deck.nome;
        estudoFim.hidden  = true;
        flashcard.hidden  = false;
        estudoNav.hidden  = false;
        $("#btnEmbaralhar").hidden = false;

        mostrarView("estudo");
        mostrarCartao();
    }

    function cartaoAtual() {
        const s = estado.estudo;
        return estado.cartoes[s.ordem[s.i]];
    }

    function mostrarCartao() {
        const s = estado.estudo;
        const c = cartaoAtual();
        const total = s.ordem.length;

        flashcard.classList.remove("virada");   // sempre começa na frente
        avaliacao.hidden = true;

        $("#cardFrente").textContent = c.frente;
        $("#cardVerso").textContent  = c.verso;

        const respondido = s.respostas.has(c.id);
        $("#estudoProgresso").textContent =
            `Cartão ${s.i + 1} de ${total}${respondido ? " · já respondido" : ""}`;

        const pct = Math.round(((s.i + 1) / total) * 100);
        barEstudo.style.width = `${pct}%`;
        barEstudo.parentElement.setAttribute("aria-valuenow", pct);

        $("#btnAnterior").disabled = s.i === 0;
        $("#btnProximo").textContent = s.i === total - 1 ? "Concluir →" : "Pular →";
    }

    function virarCartao() {
        flashcard.classList.toggle("virada");
        // a autoavaliação só faz sentido depois de ver a resposta
        avaliacao.hidden = !flashcard.classList.contains("virada");
    }

    function responder(acertou) {
        estado.estudo.respostas.set(cartaoAtual().id, acertou);
        avancar();
    }

    function avancar() {
        const s = estado.estudo;
        if (s.i < s.ordem.length - 1) {
            s.i++;
            mostrarCartao();
        } else {
            finalizarEstudo();
        }
    }

    async function finalizarEstudo() {
        const s = estado.estudo;
        const respondidos = s.respostas.size;
        const acertos = [...s.respostas.values()].filter(Boolean).length;
        const erros   = respondidos - acertos;

        flashcard.hidden = true;
        estudoNav.hidden = true;
        avaliacao.hidden = true;
        estudoFim.hidden = false;
        // embaralhar não faz sentido com a sessão encerrada
        $("#btnEmbaralhar").hidden = true;
        $("#estudoProgresso").textContent = "Sessão concluída";

        $("#fimAcertos").textContent = acertos;
        $("#fimErros").textContent   = erros;
        $("#estudoFimResumo").textContent = respondidos === 0
            ? "Você passou por todos os cartões sem se avaliar — nada foi registrado."
            : `Você avaliou ${respondidos} de ${s.ordem.length} ${plural(s.ordem.length, "cartão", "cartões")}.`;

        await salvarRevisao();
        carregarDecks();   // atualiza as estatísticas em segundo plano
    }

    /** Envia a sessão para o banco. Silencioso quando não há o que salvar. */
    async function salvarRevisao() {
        const s = estado.estudo;
        if (!s || s.respostas.size === 0) return;

        const respostas = [...s.respostas].map(([id, acertou]) => ({ id, acertou }));

        try {
            await pedir("flashcards_revisao.php", {
                deck: s.deckId,
                respostas: JSON.stringify(respostas),
            });
            s.respostas.clear();   // só agora: evita gravar a mesma sessão duas vezes
            aviso("Revisão registrada!");
        } catch (erro) {
            // mantém as respostas na memória para uma nova tentativa ao sair
            aviso(erro.message, true);
        }
    }

    /* ============================================================
       MODAIS
       ============================================================ */

    const modalDeck     = $("#modalDeck");
    const modalCartao   = $("#modalCartao");
    const modalConfirma = $("#modalConfirma");

    let deckEditando   = null;   // id ou null (= criando)
    let cartaoEditando = null;
    let aoConfirmar    = null;   // callback do modal de exclusão

    function abrirModal(modal, focar) {
        modal.classList.add("open");
        focar?.focus();
    }

    function fecharModais() {
        [modalDeck, modalCartao, modalConfirma].forEach((m) => m.classList.remove("open"));
    }

    function abrirModalDeck(deck) {
        deckEditando = deck ? deck.id : null;
        $("#modalDeckTitulo").textContent = deck ? "Editar deck" : "Novo deck";
        $("#btnSalvarDeck").textContent   = deck ? "Salvar" : "Criar deck";
        $("#nomeDeck").value    = deck ? deck.nome : "";
        $("#materiaDeck").value = deck ? deck.materia : "";
        abrirModal(modalDeck, $("#nomeDeck"));
    }

    function abrirModalCartao(cartao) {
        cartaoEditando = cartao ? cartao.id : null;
        $("#modalCartaoTitulo").textContent = cartao ? "Editar cartão" : "Novo cartão";
        $("#frenteCartao").value = cartao ? cartao.frente : "";
        $("#versoCartao").value  = cartao ? cartao.verso  : "";
        // "salvar e criar outro" só faz sentido criando
        $("#btnSalvarMais").hidden = !!cartao;
        abrirModal(modalCartao, $("#frenteCartao"));
    }

    function confirmar(titulo, texto, callback) {
        $("#modalConfirmaTitulo").textContent = titulo;
        $("#confirmaTexto").innerHTML = texto;
        aoConfirmar = callback;
        abrirModal(modalConfirma, $("#btnConfirmar"));
    }

    /* ============================================================
       AÇÕES (formulários e botões)
       ============================================================ */

    $("#formDeck").addEventListener("submit", async (e) => {
        e.preventDefault();
        const botao = $("#btnSalvarDeck");
        const rotulo = botao.textContent;
        botao.disabled = true;
        botao.textContent = "Salvando…";

        try {
            const dados = {
                acao: deckEditando ? "editar" : "criar",
                nome: $("#nomeDeck").value.trim(),
                materia: $("#materiaDeck").value.trim(),
            };
            if (deckEditando) dados.id = deckEditando;

            const json = await pedir("flashcards_deck.php", dados);
            fecharModais();
            aviso(json.msg);

            // o deck aberto pode ter sido renomeado enquanto estávamos nele
            if (estado.deck && estado.deck.id === deckEditando) {
                estado.deck.nome    = dados.nome;
                estado.deck.materia = dados.materia;
                $("#cartoesTitulo").textContent  = dados.nome;
                $("#cartoesMateria").textContent = dados.materia;
            }

            await carregarDecks();

            // criou do zero: já abre para escrever os cartões
            if (!deckEditando) abrirCartoes(json.id);
        } catch (erro) {
            aviso(erro.message, true);
        } finally {
            botao.disabled = false;
            botao.textContent = rotulo;
        }
    });

    $("#formCartao").addEventListener("submit", async (e) => {
        e.preventDefault();
        const criarOutro = e.submitter && e.submitter.id === "btnSalvarMais";
        const botao = e.submitter || $("#btnSalvarCartao");
        const rotulo = botao.textContent;
        botao.disabled = true;
        botao.textContent = "Salvando…";

        try {
            const dados = {
                acao: cartaoEditando ? "editar" : "criar",
                frente: $("#frenteCartao").value.trim(),
                verso: $("#versoCartao").value.trim(),
            };
            if (cartaoEditando) dados.id   = cartaoEditando;
            else                dados.deck = estado.deck.id;

            const json = await pedir("flashcards_cartao.php", dados);
            aviso(json.msg);

            if (criarOutro) {
                // fica no modal, pronto para o próximo cartão
                $("#formCartao").reset();
                $("#frenteCartao").focus();
            } else {
                fecharModais();
            }

            await abrirCartoes(estado.deck.id, false);
            carregarDecks();
        } catch (erro) {
            aviso(erro.message, true);
        } finally {
            botao.disabled = false;
            botao.textContent = rotulo;
        }
    });

    $("#btnConfirmar").addEventListener("click", async () => {
        const acao = aoConfirmar;
        aoConfirmar = null;
        fecharModais();
        if (acao) await acao();
    });

    async function excluirDeck(deck) {
        try {
            const json = await pedir("flashcards_deck.php", { acao: "excluir", id: deck.id });
            aviso(json.msg);

            // se o deck aberto era esse, volta para a lista
            if (estado.deck && estado.deck.id === deck.id) {
                estado.deck = null;
                estado.cartoes = [];
                mostrarView("decks");
            }
            carregarDecks();
        } catch (erro) {
            aviso(erro.message, true);
        }
    }

    async function excluirCartao(cartao) {
        try {
            const json = await pedir("flashcards_cartao.php", { acao: "excluir", id: cartao.id });
            aviso(json.msg);
            await abrirCartoes(estado.deck.id, false);
            carregarDecks();
        } catch (erro) {
            aviso(erro.message, true);
        }
    }

    /* ============================================================
       LIGAÇÕES DE EVENTOS
       ============================================================ */

    // --- Visão 1: grade de decks (delegação: os cartões são recriados) ---
    decksGrid.addEventListener("click", (e) => {
        const alvo = e.target.closest("[data-estudar], [data-cartoes], [data-editar-deck], [data-excluir-deck]");
        if (!alvo) return;

        const id = Number(alvo.dataset.estudar || alvo.dataset.cartoes
                       || alvo.dataset.editarDeck || alvo.dataset.excluirDeck);
        const deck = estado.decks.find((d) => d.id === id);

        if (alvo.dataset.estudar)     return iniciarEstudo(id);
        if (alvo.dataset.cartoes)     return abrirCartoes(id);
        if (alvo.dataset.editarDeck)  return abrirModalDeck(deck);
        if (alvo.dataset.excluirDeck) {
            confirmar(
                "Excluir deck?",
                deck.cartoes === 0
                    ? `O deck <strong>${esc(deck.nome)}</strong> será apagado. Não dá para desfazer.`
                    : `O deck <strong>${esc(deck.nome)}</strong> e os seus ${deck.cartoes} `
                      + `${plural(deck.cartoes, "cartão", "cartões")} serão apagados. Não dá para desfazer.`,
                () => excluirDeck(deck)
            );
        }
    });

    filtros.addEventListener("click", (e) => {
        const chip = e.target.closest(".chip");
        if (!chip) return;
        estado.filtro = chip.dataset.materia;
        filtros.querySelectorAll(".chip").forEach((c) => c.classList.toggle("active", c === chip));
        renderDecks();
    });

    $("#btnNovoDeck").addEventListener("click", () => abrirModalDeck(null));

    // --- Visão 2: lista de cartões ---
    cartoesLista.addEventListener("click", (e) => {
        const alvo = e.target.closest("[data-editar-cartao], [data-excluir-cartao]");
        if (!alvo) return;

        const id = Number(alvo.dataset.editarCartao || alvo.dataset.excluirCartao);
        const cartao = estado.cartoes.find((c) => c.id === id);
        if (!cartao) return;

        if (alvo.dataset.editarCartao) return abrirModalCartao(cartao);

        confirmar(
            "Excluir cartão?",
            `O cartão <strong>${esc(recortar(cartao.frente, 60))}</strong> será apagado.`,
            () => excluirCartao(cartao)
        );
    });

    $("#btnNovoCartao").addEventListener("click", () => abrirModalCartao(null));
    $("#btnEstudarDaqui").addEventListener("click", () => iniciarEstudo(estado.deck.id));

    // --- Visão 3: estudo ---
    flashcard.addEventListener("click", virarCartao);
    flashcard.addEventListener("keydown", (e) => {
        if (e.key === " " || e.key === "Enter") {
            e.preventDefault();
            virarCartao();
        }
    });

    avaliacao.addEventListener("click", (e) => {
        const botao = e.target.closest("[data-resposta]");
        if (botao) responder(botao.dataset.resposta === "1");
    });

    $("#btnProximo").addEventListener("click", avancar);
    $("#btnAnterior").addEventListener("click", () => {
        const s = estado.estudo;
        if (s.i > 0) { s.i--; mostrarCartao(); }
    });

    $("#btnEmbaralhar").addEventListener("click", async () => {
        await salvarRevisao();      // não perde o que já foi avaliado
        const s = estado.estudo;
        s.ordem = embaralhar(s.ordem);
        s.i = 0;
        mostrarCartao();
        aviso("Cartões embaralhados.");
    });

    $("#btnEstudarDeNovo").addEventListener("click", () => iniciarEstudo(estado.deck.id));

    // Sair do estudo / voltar aos decks (vários botões usam o mesmo atributo)
    document.querySelectorAll("[data-voltar-decks]").forEach((botao) => {
        botao.addEventListener("click", async () => {
            await salvarRevisao();   // guarda o que foi respondido antes de sair
            estado.estudo = null;
            mostrarView("decks");
            carregarDecks();
        });
    });

    // --- Modais ---
    document.querySelectorAll("[data-fechar-modal]").forEach((botao) => {
        botao.addEventListener("click", fecharModais);
    });
    [modalDeck, modalCartao, modalConfirma].forEach((modal) => {
        modal.addEventListener("click", (e) => { if (e.target === modal) fecharModais(); });
    });

    // --- Teclado ---
    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") {
            fecharModais();
            return;
        }

        // atalhos do estudo: só quando o estudo está na tela, sem modal
        // aberto e sem estar digitando num campo
        const estudando = !views.estudo.hidden && estado.estudo && estudoFim.hidden;
        const digitando = e.target.matches("input, textarea, select");
        const modalAberto = document.querySelector(".modal.open");
        if (!estudando || digitando || modalAberto) return;

        if (e.key === "ArrowRight") { e.preventDefault(); avancar(); }
        if (e.key === "ArrowLeft" && estado.estudo.i > 0) {
            e.preventDefault();
            estado.estudo.i--;
            mostrarCartao();
        }
        if (!avaliacao.hidden && (e.key === "1" || e.key === "2")) {
            e.preventDefault();
            responder(e.key === "2");
        }
    });

    /* ============================================================
       UTILIDADES
       ============================================================ */

    function mostrarView(nome) {
        Object.entries(views).forEach(([chave, secao]) => { secao.hidden = chave !== nome; });
        window.scrollTo({ top: 0, behavior: "smooth" });
    }

    /** Escapa texto do usuário antes de entrar em innerHTML. */
    function esc(texto) {
        return String(texto ?? "").replace(/[&<>"']/g, (c) => ({
            "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;",
        }[c]));
    }

    function recortar(texto, max) {
        return texto.length > max ? `${texto.slice(0, max)}…` : texto;
    }

    function plural(n, singular, plural) {
        return n === 1 ? singular : plural;
    }

    /** Fisher-Yates: devolve uma cópia embaralhada. */
    function embaralhar(lista) {
        const copia = [...lista];
        for (let i = copia.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [copia[i], copia[j]] = [copia[j], copia[i]];
        }
        return copia;
    }

    let avisoTimer = null;
    function aviso(texto, erro = false) {
        const caixa = $("#aviso");
        caixa.textContent = texto;
        caixa.classList.toggle("fc-aviso--erro", erro);
        caixa.hidden = false;

        clearTimeout(avisoTimer);
        avisoTimer = setTimeout(() => { caixa.hidden = true; }, erro ? 5000 : 2600);
    }

    // ---------- Começo de tudo ----------
    carregarDecks();
})();
