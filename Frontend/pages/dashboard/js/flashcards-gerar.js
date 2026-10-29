/* ============================================================
   KOSMOS — flashcards-gerar.js
   Transformar um resumo em flashcards, sem sair da tela onde o
   resumo está.

   Usado por três páginas, pela mesma porta:
       window.KosmosGerarFlashcards.abrir({ id, titulo })
     - resumos.php  (menu ⋮ do cartão)
     - caderno.php  (menu ⋮ do cartão)
     - resumo.php   (botão no cabeçalho da leitura)

   O SERVIDOR SUGERE, A PESSOA DECIDE. O Backend lê o resumo e
   devolve os cartões que dá para tirar dele (ver o cabeçalho de
   Backend/php/flashcards_gerar.php — é leitura por regra, não IA,
   porque um botão que depende de um serviço externo instável é um
   botão que às vezes não faz nada). Aqui, cada sugestão vira dois
   campos editáveis: dá para corrigir, apagar e acrescentar antes de
   salvar. Nada vai para o banco sem passar por esta tela.

   O botão "Gerar" (Criar com IA) pede ao servidor a mesma sugestão,
   só que pela IA (ia: true). Os cartões ficam em DOIS grupos na
   tela: "Feitos por você" (à mão ou tirados do resumo por regra) e
   "Criados pela IA". Gerar de novo troca só os da IA que ninguém
   mexeu; o que a pessoa escreveu ou corrigiu é dela.

   Tudo dentro de uma IIFE: o dashboard.js já ocupa o escopo global.
   ============================================================ */
(() => {
    "use strict";

    const API = "../../../Backend/php/flashcards_gerar.php";

    const el = {
        caixa:       document.getElementById("modalGerar"),
        form:        document.getElementById("gerarForm"),
        fonte:       document.getElementById("gerarFonte"),
        carregando:  document.getElementById("gerarCarregando"),
        conteudo:    document.getElementById("gerarConteudo"),
        deck:        document.getElementById("gerarDeck"),
        novoCampo:   document.getElementById("gerarNovoCampo"),
        novoNome:    document.getElementById("gerarNovoNome"),
        conta:       document.getElementById("gerarConta"),
        lista:       document.getElementById("gerarLista"),
        vazio:       document.getElementById("gerarVazio"),
        adicionar:   document.getElementById("gerarAdicionar"),
        ia:          document.getElementById("gerarComIA"),
        iaPainel:    document.getElementById("gerarIAPainel"),
        iaDifs:      document.getElementById("gerarIADificuldades"),
        iaQtd:       document.getElementById("gerarIAQtd"),
        vazioIA:     document.getElementById("gerarVazioIA"),
        msg:         document.getElementById("gerarMsg"),
        salvar:      document.getElementById("gerarSalvar"),
    };

    // Sem o modal na página, o módulo não existe: as outras telas
    // checam `window.KosmosGerarFlashcards?` antes de chamar.
    if (!el.caixa) return;

    /** O resumo aberto agora. Só isso é estado — o resto está no DOM. */
    let resumo = null;

    /* Detalhe de onde saiu cada cartão "seu" (o grupo já diz quem fez).
       Quem entende a origem confia (ou desconfia) do cartão na hora certa. */
    const ORIGENS = {
        destaque:  ["destaque",  "Do trecho que você marcou com ==destaque=="],
        definicao: ["definição", "De uma linha \"Termo: definição\" do seu resumo"],
        pergunta:  ["pergunta",  "De uma pergunta que você escreveu no resumo"],
        manual:    ["",          ""],   // escrito aqui: o grupo já diz, sem etiqueta
    };

    /** Os dois grupos da lista: { voce, ia } -> <section> */
    const grupos = {};
    el.lista.querySelectorAll("[data-grupo]").forEach((s) => { grupos[s.dataset.grupo] = s; });
    const grupoDe = (origem) => (origem === "ia" ? grupos.ia : grupos.voce);
    const todosCartoes = () => [...el.lista.querySelectorAll(".gf-cartao")];

    /** O campo cresce com o texto (sem barra de rolagem dentro do cartão). */
    function ajustarAltura(t) {
        t.style.height = "auto";
        t.style.height = t.scrollHeight + 2 + "px";
    }

    /* ------------------------------------------------------------
       ABRIR
       ------------------------------------------------------------ */

    /**
     * @param {{id:number, titulo?:string}} oResumo
     */
    async function abrir(oResumo) {
        const id = Number(oResumo?.id);
        if (!id) return;

        resumo = { id, titulo: oResumo.titulo || "" };

        el.form.reset();
        el.msg.hidden = true;
        Object.values(grupos).forEach((g) => {
            g.querySelector(".gf-grupo__cartoes").innerHTML = "";
            abrirGrupo(g, true);
        });
        el.vazio.hidden = true;
        el.conteudo.hidden = true;
        el.carregando.hidden = false;
        el.salvar.disabled = true;
        el.fonte.textContent = resumo.titulo ? `De: ${resumo.titulo}` : "";

        el.caixa.classList.add("open");

        const j = await pedir({ acao: "sugerir", resumo: id });
        if (!j) {
            // O erro já apareceu na faixa; a pessoa fecha e tenta de novo.
            el.carregando.hidden = true;
            return;
        }

        resumo.titulo  = j.resumo.titulo;
        resumo.materia = j.resumo.materia;
        el.fonte.textContent = `De: ${j.resumo.titulo} · ${j.resumo.materia}`;

        montarDecks(j.decks, j.deck_sugerido, j.nome_sugerido);

        el.iaPainel.hidden = !j.ia_disponivel;
        el.vazioIA.hidden = !j.ia_disponivel;
        el.ia.querySelector("span").textContent = "Gerar";

        (j.cartoes || []).forEach((c) => novaLinha(c.frente, c.verso, c.origem));
        el.vazio.hidden = (j.cartoes || []).length > 0;

        // Resumo sem nada aproveitável: já entrega uma linha em branco,
        // porque a pessoa veio aqui para fazer cartões de qualquer jeito.
        if (!(j.cartoes || []).length) novaLinha("", "", "manual");

        el.carregando.hidden = true;
        el.conteudo.hidden = false;
        // só agora os campos têm tamanho: antes, o conteúdo estava escondido
        el.lista.querySelectorAll(".gf-cartao__campo").forEach(ajustarAltura);
        atualizarConta();

        el.lista.querySelector("textarea")?.focus();
    }

    function fechar() {
        el.caixa.classList.remove("open");
        resumo = null;
    }

    /** O seletor de baralho, com a opção de criar um novo no fim. */
    function montarDecks(decks, sugerido, nomeSugerido) {
        el.deck.innerHTML = (decks || []).map((d) =>
            `<option value="${d.id}">${escaparHtml(d.nome)} · ${escaparHtml(d.materia)} (${d.cartoes})</option>`
        ).join("") + '<option value="0">＋ Criar um baralho novo</option>';

        /* Cai no baralho da mesma matéria quando existe: quem já tem
           "Biologia" quer os cartões novos onde os antigos estão, para
           a revisão continuar sendo uma fila só. Sem nenhum baralho, a
           única saída é criar. */
        el.deck.value = sugerido ? String(sugerido) : "0";
        el.novoNome.value = nomeSugerido || resumo.titulo || "";
        trocarDeck();
    }

    function trocarDeck() {
        const novo = el.deck.value === "0";
        el.novoCampo.hidden = !novo;
        if (novo) el.novoNome.focus({ preventScroll: true });
    }

    /* ------------------------------------------------------------
       AS LINHAS
       ------------------------------------------------------------ */

    function novaLinha(frente, verso, origem, dificuldade) {
        const bloco = document.createElement("div");
        bloco.className = "gf-cartao";
        bloco.dataset.origem = origem;

        bloco.innerHTML = `
            <span class="gf-cartao__num"></span>
            <div class="gf-cartao__campos">
                <textarea class="gf-cartao__campo gf-cartao__campo--frente" data-lado="frente" rows="1"
                          maxlength="600" placeholder="Pergunta (frente do cartão)" aria-label="Pergunta"></textarea>
                <textarea class="gf-cartao__campo gf-cartao__campo--verso" data-lado="verso" rows="1"
                          maxlength="600" placeholder="Resposta (verso)" aria-label="Resposta"></textarea>
            </div>
            <div class="gf-cartao__lado">
                <button type="button" class="gf-cartao__x" aria-label="Tirar este cartão">&times;</button>
                <span class="gf-cartao__tag"></span>
            </div>`;

        // A etiqueta: o nível, na cor dele (IA), ou de onde saiu (você)
        const tag = bloco.querySelector(".gf-cartao__tag");
        if (origem === "ia") {
            tag.textContent = dificuldade || "IA";
            if (dificuldade) tag.dataset.dificuldade = dificuldade;
            tag.title = "Criado pela IA" + (dificuldade ? `, nível ${dificuldade}` : "");
        } else {
            const [curto, longo] = ORIGENS[origem] || ORIGENS.manual;
            tag.textContent = curto;
            tag.title = longo;
            tag.hidden = curto === "";
        }

        // .value e não innerHTML: o texto saiu do resumo da pessoa
        bloco.querySelector('[data-lado="frente"]').value = frente || "";
        bloco.querySelector('[data-lado="verso"]').value  = verso  || "";

        bloco.querySelector(".gf-cartao__x").addEventListener("click", () => {
            bloco.remove();
            atualizarConta();
        });
        // Mexeu, é dela: gerar de novo não troca mais este cartão
        bloco.addEventListener("input", (e) => {
            if (!bloco.dataset.mexido && origem === "ia") tag.textContent += " · editado";
            bloco.dataset.mexido = "1";
            if (e.target.matches("textarea")) ajustarAltura(e.target);
        });

        const grupo = grupoDe(origem);
        grupo.querySelector(".gf-grupo__cartoes").appendChild(bloco);
        grupo.hidden = false;
        abrirGrupo(grupo, true);
        bloco.querySelectorAll("textarea").forEach(ajustarAltura);
        return bloco;
    }

    /* ------------------------------------------------------------
       OS GRUPOS
       ------------------------------------------------------------ */

    /** Abre (true) ou recolhe (false) um grupo; sem valor, alterna. */
    function abrirGrupo(grupo, abrirMesmo) {
        const botao = grupo.querySelector(".gf-grupo__abrir");
        const aberto = abrirMesmo ?? botao.getAttribute("aria-expanded") !== "true";
        botao.setAttribute("aria-expanded", String(aberto));
        grupo.classList.toggle("is-fechado", !aberto);
        if (aberto) grupo.querySelectorAll(".gf-cartao__campo").forEach(ajustarAltura);
    }

    function cliqueNoGrupo(e) {
        const grupo = e.target.closest("[data-grupo]");
        if (!grupo) return;
        if (e.target.closest(".gf-grupo__abrir")) abrirGrupo(grupo);
        if (e.target.closest(".gf-grupo__limpar")) {
            grupo.querySelector(".gf-grupo__cartoes").innerHTML = "";
            atualizarConta();
        }
    }

    /* ------------------------------------------------------------
       GERAR COM IA
       ------------------------------------------------------------ */

    /** Chips de dificuldade: vários podem ficar marcados, nunca nenhum. */
    function alternarDificuldade(e) {
        const chip = e.target.closest("[data-dificuldade]");
        if (!chip) return;
        const marcado = chip.getAttribute("aria-pressed") === "true";
        if (marcado && el.iaDifs.querySelectorAll('[aria-pressed="true"]').length === 1) return;
        chip.setAttribute("aria-pressed", String(!marcado));
    }

    async function gerarComIA() {
        if (!resumo || el.ia.disabled) return;

        const qtd = parseInt(el.iaQtd.value, 10);
        if (!(qtd >= 1 && qtd <= 20)) {
            erro("Escolha de 1 a 20 cartões.");
            el.iaQtd.focus();
            return;
        }
        const dificuldades = [...el.iaDifs.querySelectorAll('[aria-pressed="true"]')]
            .map((b) => b.dataset.dificuldade);

        const rotulo = el.ia.querySelector("span");
        el.ia.disabled = true;
        rotulo.textContent = "Gerando…";
        el.msg.hidden = true;

        const j = await pedir({ acao: "sugerir", resumo: resumo.id, ia: true, qtd, dificuldades });

        el.ia.disabled = false;
        if (!j) {
            rotulo.textContent = "Gerar";
            return;   // o erro já está na faixa; os cartões de antes ficam
        }

        // Sai o que a IA fez antes e ninguém tocou, e as linhas em branco.
        // Os cartões "seus" (à mão ou do resumo) ficam: são outro grupo.
        todosCartoes().forEach((bloco) => {
            const vazio = [...bloco.querySelectorAll("textarea")].every((t) => !t.value.trim());
            if (vazio || (bloco.dataset.origem === "ia" && !bloco.dataset.mexido)) bloco.remove();
        });
        (j.cartoes || []).forEach((c) => novaLinha(c.frente, c.verso, c.origem, c.dificuldade));

        // Os seus recolhem para os da IA aparecerem; um clique abre de novo
        if (grupos.voce.querySelector(".gf-cartao")) abrirGrupo(grupos.voce, false);

        el.vazio.hidden = true;
        rotulo.textContent = "Gerar de novo";
        atualizarConta();
        grupos.ia.scrollIntoView({ block: "nearest" });
    }

    /** Quantos cartões inteiros existem agora (e se dá para salvar). */
    function atualizarConta() {
        const prontos = lerCartoes().length;
        const linhas  = todosCartoes().length;

        el.conta.textContent = linhas === 0
            ? "Cartões"
            : `Cartões · ${prontos} de ${linhas} prontos`;

        // Cada grupo: some quando vazio, conta e numera os seus
        Object.values(grupos).forEach((g) => {
            const cartoes = [...g.querySelectorAll(".gf-cartao")];
            g.hidden = cartoes.length === 0;
            g.querySelector(".gf-grupo__conta").textContent = cartoes.length;
            cartoes.forEach((c, i) => { c.querySelector(".gf-cartao__num").textContent = i + 1; });
        });

        el.salvar.disabled = prontos === 0;
        el.salvar.textContent = prontos <= 1 ? "Criar cartão" : `Criar ${prontos} cartões`;
    }

    /** Só as linhas com os dois lados preenchidos viram cartão. */
    function lerCartoes() {
        return todosCartoes().map((bloco) => ({
            frente: bloco.querySelector('[data-lado="frente"]').value.trim(),
            verso:  bloco.querySelector('[data-lado="verso"]').value.trim(),
        })).filter((c) => c.frente && c.verso);
    }

    /* ------------------------------------------------------------
       SALVAR
       ------------------------------------------------------------ */

    async function salvar(e) {
        e.preventDefault();
        if (!resumo) return;

        const cartoes = lerCartoes();
        if (!cartoes.length) return erro("Preencha os dois lados de pelo menos um cartão.");

        const novo = el.deck.value === "0";
        if (novo && !el.novoNome.value.trim()) {
            return erro("Dê um nome ao baralho novo.");
        }

        el.salvar.disabled = true;
        const j = await pedir({
            acao: "salvar",
            resumo: resumo.id,
            deck: novo ? 0 : Number(el.deck.value),
            deck_nome: el.novoNome.value.trim(),
            cartoes,
        });
        el.salvar.disabled = false;

        if (!j) return;

        // Guardados ANTES de fechar: fechar() zera o resumo aberto.
        const deckId   = j.deck;
        const resumoId = resumo.id;
        fechar();

        /* A página avisa quem quiser saber (o Início repinta o número
           de flashcards sem recarregar, por exemplo). */
        document.dispatchEvent(new CustomEvent("flashcards:gerados", {
            detail: { resumo: resumoId, deck: deckId, quantos: j.criados },
        }));

        // Um caminho até o resultado: sem isso, "5 cartões criados" é
        // uma promessa que a pessoa tem de ir conferir sozinha.
        oferecerAtalho(deckId, j.criados);
    }

    /** Aviso com link para o baralho recém-enchido. */
    function oferecerAtalho(deckId, quantos) {
        const caixa = caixaDeAviso();
        caixa.textContent = "";
        caixa.classList.remove("fc-aviso--erro");

        const texto = document.createElement("span");
        texto.textContent = `${quantos} ${quantos === 1 ? "cartão criado" : "cartões criados"} · `;

        const link = document.createElement("a");
        link.href = `flashcards.php?deck=${deckId}`;
        link.textContent = "ver o baralho";
        link.className = "fc-aviso__link";

        caixa.append(texto, link);
        caixa.hidden = false;

        clearTimeout(avisoTimer);
        avisoTimer = setTimeout(() => { caixa.hidden = true; }, 6000);
    }

    /* ------------------------------------------------------------
       SERVIDOR
       ------------------------------------------------------------ */

    async function pedir(corpo) {
        try {
            const r = await fetch(API, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                credentials: "same-origin",
                body: JSON.stringify(corpo),
            });
            const j = await r.json();

            if (!j.ok) { erro(j.msg || "Não deu certo."); return null; }
            return j;
        } catch {
            erro("Sem conexão com o servidor.");
            return null;
        }
    }

    /* ------------------------------------------------------------
       MIUDEZAS
       ------------------------------------------------------------ */

    function erro(texto) {
        el.msg.textContent = texto;
        el.msg.className = "msg msg--erro";
        el.msg.hidden = false;
    }

    let avisoTimer = null;

    function caixaDeAviso() {
        let caixa = document.getElementById("gfAviso");
        if (!caixa) {
            caixa = document.createElement("div");
            caixa.id = "gfAviso";
            caixa.className = "fc-aviso";
            caixa.setAttribute("role", "status");
            caixa.setAttribute("aria-live", "polite");
            document.body.appendChild(caixa);
        }
        return caixa;
    }

    /* ------------------------------------------------------------
       LIGAÇÕES
       ------------------------------------------------------------ */

    el.form.addEventListener("submit", salvar);
    el.deck.addEventListener("change", trocarDeck);
    el.ia.addEventListener("click", gerarComIA);
    el.iaDifs.addEventListener("click", alternarDificuldade);
    // Enter na quantidade gera, em vez de enviar o formulário (que salvaria os cartões)
    el.iaQtd.addEventListener("keydown", (e) => {
        if (e.key === "Enter") { e.preventDefault(); gerarComIA(); }
    });
    el.adicionar.addEventListener("click", () => {
        el.vazio.hidden = true;
        const bloco = novaLinha("", "", "manual");
        atualizarConta();
        bloco.scrollIntoView({ block: "nearest" });
        bloco.querySelector("textarea").focus();
    });
    el.lista.addEventListener("click", cliqueNoGrupo);

    // A altura dos campos depende da largura: girar o celular ou mudar
    // o tamanho da janela quebra as linhas de outro jeito
    let larguraLista = 0;
    new ResizeObserver(() => {
        const largura = el.lista.clientWidth;
        if (!largura || largura === larguraLista) return;
        larguraLista = largura;
        el.lista.querySelectorAll(".gf-cartao__campo").forEach(ajustarAltura);
    }).observe(el.lista);

    // A contagem acompanha a digitação: o botão diz quantos cartões
    // vão ser criados AGORA, não quantos foram sugeridos.
    el.lista.addEventListener("input", atualizarConta);

    document.getElementById("gerarFechar").addEventListener("click", fechar);
    document.getElementById("gerarCancelar").addEventListener("click", fechar);
    el.caixa.addEventListener("click", (e) => { if (e.target === el.caixa) fechar(); });
    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && el.caixa.classList.contains("open")) fechar();
    });

    window.KosmosGerarFlashcards = { abrir, fechar };
})();
