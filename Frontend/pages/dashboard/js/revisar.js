/* ============================================================
   KOSMOS — revisar.js  (só a página Revisar hoje)

   A fila de revisão atravessando todos os baralhos. O desenho é o
   mesmo da aba Flashcards (reaproveita .flashcard e o
   css/flashcards.css); o que muda é de onde vêm os cartões e para
   onde vão as respostas.

   ── Por que as respostas são agrupadas por baralho ──
   O endpoint que registra revisão (flashcards_revisao.php) recebe
   UM baralho por chamada, e valida que todo cartão enviado
   pertence a ele. Essa validação é boa e não vale a pena afrouxar
   só porque a fila agora mistura baralhos — então quem se adapta é
   esta tela: junta as respostas por deck_id e faz uma chamada por
   baralho — assim que o baralho sai da fila (ou a cada LOTE_MAX
   respostas), e o resto no fim ou no pagehide.

   Tudo dentro de uma IIFE: os scripts de página dividem o escopo
   global com o dashboard.js, então nada aqui pode vazar.
   ============================================================ */
(() => {
    "use strict";

    const API = "../../../Backend/php";

    const el = {
        carregando: document.getElementById("revCarregando"),
        vazio:      document.getElementById("revVazio"),
        vazioTexto: document.getElementById("revVazioTexto"),
        area:       document.getElementById("revArea"),
        fim:        document.getElementById("revFim"),
        fimResumo:  document.getElementById("revFimResumo"),
        progresso:  document.getElementById("revProgresso"),
        origem:     document.getElementById("revOrigem"),
        cartao:     document.getElementById("flashcard"),
        frente:     document.getElementById("cardFrente"),
        verso:      document.getElementById("cardVerso"),
        avaliacao:  document.getElementById("estudoAvaliacao"),
        barra:      document.getElementById("barEstudo"),
        acertos:    document.getElementById("fimAcertos"),
        erros:      document.getElementById("fimErros"),
    };

    // Só roda nesta página.
    if (!el.area) return;

    let fila     = [];
    let indice   = 0;
    let virado   = false;
    let acertos  = 0;
    let erros    = 0;
    const respostas = [];   // [{id, deck_id, acertou}] — só as AINDA NÃO enviadas

    /** Acumulou isto sem enviar (fila de um baralho só, longa)? Envia. */
    const LOTE_MAX = 10;

    /* ------------------------------------------------------------
       Carga
       ------------------------------------------------------------ */
    async function carregar() {
        let dados;
        try {
            const r = await fetch(`${API}/revisar_fila.php`, { credentials: "same-origin" });
            dados = await r.json();
        } catch {
            dados = null;
        }

        el.carregando.hidden = true;

        if (!dados || !dados.ok) {
            // Falha de rede não pode virar "você está em dia": a pessoa
            // acreditaria e não revisaria hoje.
            el.vazioTexto.textContent =
                "Não consegui carregar sua fila agora. Tente recarregar a página.";
            el.vazio.hidden = false;
            el.progresso.textContent = "Fila indisponível";
            return;
        }

        fila = Array.isArray(dados.cartoes) ? dados.cartoes : [];

        if (fila.length === 0) {
            el.vazio.hidden = false;
            el.progresso.textContent = "Você está em dia";
            return;
        }

        el.area.hidden = false;
        mostrar();
    }

    /* ------------------------------------------------------------
       O cartão da vez
       ------------------------------------------------------------ */
    function mostrar() {
        const c = fila[indice];
        if (!c) return terminar();

        /* Volta para a frente SEM animar (mesma técnica do flashcards.js):
           com a transição ligada, o giro de volta mostrava por um instante
           o verso do cartão NOVO — a resposta antes da pergunta. */
        virado = false;
        el.cartao.classList.add("sem-anim");
        el.cartao.classList.remove("virada");
        el.avaliacao.hidden = true;

        // textContent e nunca innerHTML: frente e verso são texto que a
        // pessoa digitou.
        el.frente.textContent = c.frente;
        el.verso.textContent  = c.verso;

        void el.cartao.offsetWidth;   // aplica o estado sem transição
        el.cartao.classList.remove("sem-anim");

        el.origem.textContent = c.materia ? `${c.deck} · ${c.materia}` : c.deck;
        el.progresso.textContent = `Cartão ${indice + 1} de ${fila.length}`;

        const pct = Math.round((indice / fila.length) * 100);
        el.barra.style.width = `${pct}%`;
        el.barra.parentElement.setAttribute("aria-valuenow", String(pct));
    }

    function virar() {
        virado = !virado;
        el.cartao.classList.toggle("virada", virado);
        // A autoavaliação só existe depois de ver a resposta.
        if (virado) el.avaliacao.hidden = false;
    }

    function responder(acertou) {
        const c = fila[indice];
        if (!c) return;

        respostas.push({ id: c.id, deck_id: c.deck_id, acertou });
        acertou ? acertos++ : erros++;

        indice++;
        if (indice >= fila.length) {
            terminar();
            return;
        }

        /* Envia aos poucos em vez de tudo no fim: quem fecha a aba no
           meio da fila perdia a revisão inteira. Um baralho vai assim que
           não sobra cartão dele na fila; e, se acumular demais, vai tudo. */
        const deckTerminou = !fila.slice(indice).some((x) => x.deck_id === c.deck_id);
        if (respostas.length >= LOTE_MAX) enviar();
        else if (deckTerminou) enviar(c.deck_id);

        mostrar();
    }

    /* ------------------------------------------------------------
       Fim
       ------------------------------------------------------------ */
    async function terminar() {
        el.area.hidden = true;
        el.fim.hidden  = false;
        el.acertos.textContent = String(acertos);
        el.erros.textContent   = String(erros);
        el.progresso.textContent = "Revisão concluída";

        const total = acertos + erros;
        el.fimResumo.textContent = total === 0
            ? "Nenhum cartão respondido."
            : `Você revisou ${total} ${total === 1 ? "cartão" : "cartões"}. ` +
              "Cada um volta na hora certa — quem errou, amanhã.";

        await enviar();
    }

    /**
     * Tira de `respostas` o que ainda não foi enviado (de um baralho só,
     * ou de todos) e devolve agrupado por deck_id.
     *
     * Tira ANTES de enviar, de propósito: o que saiu daqui nunca volta a
     * ser enviado — nem pelo próximo lote, nem pelo pagehide. Reenviar
     * seria gravar a revisão duas vezes e dar XP em dobro. O preço é que
     * um envio que falha não é refeito; o cartão só continua vencido e
     * reaparece na próxima fila, que é o mesmo destino de antes.
     */
    function retirarPendentes(deckId) {
        const porDeck = new Map();
        for (let i = respostas.length - 1; i >= 0; i--) {
            const r = respostas[i];
            if (deckId !== undefined && r.deck_id !== deckId) continue;
            respostas.splice(i, 1);
            if (!porDeck.has(r.deck_id)) porDeck.set(r.deck_id, []);
            porDeck.get(r.deck_id).unshift({ id: r.id, acertou: r.acertou });
        }
        return porDeck;
    }

    /** O corpo que o flashcards_revisao.php lê de $_POST: `deck` + `respostas` (JSON). */
    function corpoDe(deckId, lista) {
        return new URLSearchParams({
            deck: String(deckId),
            respostas: JSON.stringify(lista),
        });
    }

    /** Uma chamada por baralho (ver o comentário do topo). */
    async function enviar(deckId) {
        const porDeck = retirarPendentes(deckId);

        for (const [id, lista] of porDeck) {
            const corpo = corpoDe(id, lista);
            try {
                await fetch(`${API}/flashcards_revisao.php`, {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    credentials: "same-origin",
                    // keepalive: se a pessoa sair enquanto este envio está no
                    // ar, o navegador termina o POST em vez de cancelá-lo.
                    keepalive: true,
                    body: corpo,
                });
            } catch {
                /* Sem alarme na tela. A sessão de estudo já aconteceu e a
                   pessoa já está vendo o resultado; um erro vermelho aqui
                   não lhe dá nada para fazer. O cartão simplesmente
                   continua vencido e reaparece na próxima fila. */
            }
        }
    }

    /* ------------------------------------------------------------
       Ligações
       ------------------------------------------------------------ */
    el.cartao.addEventListener("click", virar);
    el.cartao.addEventListener("keydown", (e) => {
        // O cartão é role="button": teclado tem que fazer o mesmo que o clique.
        if (e.key === " " || e.key === "Enter") {
            e.preventDefault();
            virar();
        }
    });

    el.avaliacao.querySelectorAll("[data-resposta]").forEach((b) => {
        b.addEventListener("click", () => responder(b.dataset.resposta === "1"));
    });

    /* Saindo da página (fechar aba, navegar, app em segundo plano no
       celular): fetch comum seria cancelado. sendBeacon é feito para
       isto — o navegador entrega depois que a página já foi embora. O
       corpo é o mesmo URLSearchParams do fetch, que o PHP lê em $_POST.
       pagehide e não beforeunload/unload: é o único que dispara com
       confiança no celular e não quebra o bfcache. */
    window.addEventListener("pagehide", () => {
        if (respostas.length === 0 || !navigator.sendBeacon) return;
        for (const [deckId, lista] of retirarPendentes()) {
            navigator.sendBeacon(`${API}/flashcards_revisao.php`, corpoDe(deckId, lista));
        }
    });

    carregar();
})();
