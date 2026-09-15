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
        msg:         document.getElementById("gerarMsg"),
        salvar:      document.getElementById("gerarSalvar"),
    };

    // Sem o modal na página, o módulo não existe: as outras telas
    // checam `window.KosmosGerarFlashcards?` antes de chamar.
    if (!el.caixa) return;

    /** O resumo aberto agora. Só isso é estado — o resto está no DOM. */
    let resumo = null;

    /* Rótulo de onde cada sugestão saiu. Quem entende a origem
       confia (ou desconfia) do cartão na hora certa. */
    const ORIGENS = {
        destaque:  "do seu ==destaque==",
        definicao: "de uma definição",
        pergunta:  "de uma pergunta sua",
        manual:    "escrito por você",
    };

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
        el.lista.innerHTML = "";
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

        (j.cartoes || []).forEach((c) => novaLinha(c.frente, c.verso, c.origem));
        el.vazio.hidden = (j.cartoes || []).length > 0;

        // Resumo sem nada aproveitável: já entrega uma linha em branco,
        // porque a pessoa veio aqui para fazer cartões de qualquer jeito.
        if (!(j.cartoes || []).length) novaLinha("", "", "manual");

        el.carregando.hidden = true;
        el.conteudo.hidden = false;
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
            `<option value="${d.id}">${esc(d.nome)} · ${esc(d.materia)} (${d.cartoes})</option>`
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

    function novaLinha(frente, verso, origem) {
        const bloco = document.createElement("div");
        bloco.className = "gf-cartao";

        bloco.innerHTML = `
            <div class="gf-cartao__topo">
                <span class="gf-cartao__tag">${esc(ORIGENS[origem] || ORIGENS.manual)}</span>
                <button type="button" class="gf-cartao__x" aria-label="Tirar este cartão">&times;</button>
            </div>
            <label class="gf-cartao__rot">Pergunta</label>
            <textarea class="gf-cartao__campo" data-lado="frente" rows="2" maxlength="600"
                      placeholder="O que aparece na frente do cartão"></textarea>
            <label class="gf-cartao__rot">Resposta</label>
            <textarea class="gf-cartao__campo" data-lado="verso" rows="2" maxlength="600"
                      placeholder="O que aparece no verso"></textarea>`;

        // textContent e não innerHTML: o texto saiu do resumo da pessoa
        bloco.querySelector('[data-lado="frente"]').value = frente || "";
        bloco.querySelector('[data-lado="verso"]').value  = verso  || "";

        bloco.querySelector(".gf-cartao__x").addEventListener("click", () => {
            bloco.remove();
            atualizarConta();
        });

        el.lista.appendChild(bloco);
        return bloco;
    }

    /** Quantos cartões inteiros existem agora (e se dá para salvar). */
    function atualizarConta() {
        const prontos = lerCartoes().length;
        const linhas  = el.lista.children.length;

        el.conta.textContent = linhas === 0
            ? "Cartões"
            : `Cartões (${prontos} de ${linhas} prontos)`;

        el.salvar.disabled = prontos === 0;
        el.salvar.textContent = prontos <= 1 ? "Criar cartão" : `Criar ${prontos} cartões`;
    }

    /** Só as linhas com os dois lados preenchidos viram cartão. */
    function lerCartoes() {
        return [...el.lista.children].map((bloco) => ({
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

    function esc(texto) {
        return String(texto ?? "").replace(/[&<>"']/g, (c) => ({
            "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;",
        }[c]));
    }

    /* ------------------------------------------------------------
       LIGAÇÕES
       ------------------------------------------------------------ */

    el.form.addEventListener("submit", salvar);
    el.deck.addEventListener("change", trocarDeck);
    el.adicionar.addEventListener("click", () => {
        el.vazio.hidden = true;
        novaLinha("", "", "manual").querySelector("textarea").focus();
        atualizarConta();
    });

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
