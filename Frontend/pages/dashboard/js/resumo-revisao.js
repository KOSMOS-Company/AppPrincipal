/* ============================================================
   KOSMOS — resumo-revisao.js  (só a leitura de um resumo)

   Modo revisão: esconde os trechos que a pessoa marcou e deixa
   ela tentar lembrar antes de ver a resposta. É o mesmo princípio
   dos flashcards, mas sem sair do resumo e sem ter que montar
   cartão nenhum — o material de estudo vira o teste.

   ── A marcação ──
   No editor a pessoa escreve ==assim==. Escolhi esse par por ser
   o mesmo do "realce" em Markdown, que muita gente já usa sem
   saber o nome, e por não aparecer por acidente num texto de
   estudo (ao contrário de aspas, parênteses ou dois-pontos).

   ── Por que nada disso usa innerHTML ──
   O corpo do resumo é texto que a PESSOA digitou. Ele chega
   escapado pelo PHP (hesc) e aqui é lido por textContent e
   remontado com createElement/createTextNode. Em nenhum momento
   um texto do usuário vira HTML — que é a única forma de garantir
   que um resumo sobre tags HTML não vire tags HTML.

   ── Remontar ──
   A montagem é uma função exposta (window.KosmosRevisao.montar)
   porque o resumo.js reescreve o texto depois de uma edição feita
   ali mesmo, na prévia. Antes ela rodava uma vez só: salvar
   apagava os trechos e o texto voltava a mostrar os == crus.
   ============================================================ */
(() => {
    "use strict";

    const leitura = document.getElementById("leitura");
    const botao   = document.getElementById("btnRevisar");
    const rotulo  = document.getElementById("btnRevisarTexto");
    const imprimir = document.getElementById("btnImprimir");

    /* O botão de imprimir não depende de marcação nenhuma, então é
       ligado antes de qualquer saída antecipada. */
    if (imprimir) {
        imprimir.addEventListener("click", () => window.print());
    }

    if (!leitura || !botao) return;

    const MARCA = /==([^=]+)==/g;
    let escondendo = false;

    function definirModo(ligado) {
        escondendo = ligado;
        leitura.classList.toggle("resumo-leitura--revisao", escondendo);
        botao.setAttribute("aria-pressed", String(escondendo));
        if (rotulo) rotulo.textContent = escondendo ? "Mostrar tudo" : "Modo revisão";

        // Saindo do modo, tudo volta a aparecer: senão a pessoa
        // desligaria o teste e continuaria com buracos no texto.
        if (!escondendo) {
            leitura.querySelectorAll(".cloze--aberto")
                   .forEach((c) => c.classList.remove("cloze--aberto"));
        }
    }

    /* ------------------------------------------------------------
       Montagem: lê o texto puro da leitura e troca cada ==trecho==
       por um <button>. Sem marcação, o botão do modo fica escondido.
       ------------------------------------------------------------ */
    function montar() {
        definirModo(false);

        const texto = leitura.textContent;
        MARCA.lastIndex = 0;
        if (!MARCA.test(texto)) {
            botao.hidden = true;
            return;
        }
        MARCA.lastIndex = 0;              // `test` deixa o índice andado; zera antes de usar

        /* Botão e não <span>: o trecho é clicável e precisa do teclado,
           do foco e do papel certo para o leitor de tela — coisas que um
           span com onclick não tem. */
        const fragmento = document.createDocumentFragment();
        let fim = 0;
        let achado;
        let n = 0;

        while ((achado = MARCA.exec(texto)) !== null) {
            if (achado.index > fim) {
                fragmento.appendChild(
                    document.createTextNode(texto.slice(fim, achado.index))
                );
            }

            n++;
            const b = document.createElement("button");
            b.type = "button";
            b.className = "cloze";
            b.textContent = achado[1];
            b.setAttribute("aria-label", `Trecho ${n} — clique para mostrar`);
            b.addEventListener("click", () => {
                // Revelar é individual: a pessoa confere um trecho de cada
                // vez sem perder o teste dos outros.
                b.classList.toggle("cloze--aberto");
            });
            fragmento.appendChild(b);

            fim = MARCA.lastIndex;
        }
        if (fim < texto.length) {
            fragmento.appendChild(document.createTextNode(texto.slice(fim)));
        }

        leitura.textContent = "";
        leitura.appendChild(fragmento);
        botao.hidden = false;
    }

    /* O interruptor (ligado uma vez; montar() pode rodar várias) */
    botao.addEventListener("click", () => definirModo(!escondendo));

    montar();
    window.KosmosRevisao = { montar };
})();
