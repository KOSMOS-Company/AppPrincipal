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
   baralho no fim.

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
    const respostas = [];   // [{id, deck_id, acertou}]

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

        virado = false;
        el.cartao.classList.remove("virada");
        el.avaliacao.hidden = true;

        // textContent e nunca innerHTML: frente e verso são texto que a
        // pessoa digitou.
        el.frente.textContent = c.frente;
        el.verso.textContent  = c.verso;

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
        if (indice >= fila.length) terminar();
        else mostrar();
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

    /** Uma chamada por baralho (ver o comentário do topo). */
    async function enviar() {
        const porDeck = new Map();
        respostas.forEach((r) => {
            if (!porDeck.has(r.deck_id)) porDeck.set(r.deck_id, []);
            porDeck.get(r.deck_id).push({ id: r.id, acertou: r.acertou });
        });

        for (const [deckId, lista] of porDeck) {
            const corpo = new URLSearchParams({
                deck: String(deckId),
                respostas: JSON.stringify(lista),
            });
            try {
                await fetch(`${API}/flashcards_revisao.php`, {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    credentials: "same-origin",
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

    carregar();
})();
