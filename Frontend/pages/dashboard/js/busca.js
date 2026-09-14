/* ============================================================
   KOSMOS — busca.js  (só a página Buscar)

   Busca em resumos, cadernos, baralhos e cartões de uma vez.

   ── Duas coisas que este arquivo faz de propósito ──

   1. Busca enquanto se digita, mas ESPERA 280 ms parada. Sem essa
      espera, "fotossíntese" dispara doze consultas e as respostas
      podem chegar fora de ordem — a tela acabaria mostrando o
      resultado de "fotos" depois do de "fotossíntese".

   2. Nunca monta resultado com innerHTML. Título e trecho são
      texto que a pessoa escreveu; tudo aqui é createElement e
      createTextNode. O destaque do termo (<mark>) é criado como
      elemento, não colado como string — que é a única forma de um
      resumo sobre HTML não virar HTML.
   ============================================================ */
(() => {
    "use strict";

    const form   = document.getElementById("buscaForm");
    const campo  = document.getElementById("buscaInput");
    const lista  = document.getElementById("buscaLista");
    const vazio  = document.getElementById("buscaVazio");
    const vTitulo = document.getElementById("buscaVazioTitulo");
    const vTexto  = document.getElementById("buscaVazioTexto");
    const resumo = document.getElementById("buscaResumo");

    if (!form || !campo) return;

    const ROTULO = {
        resumo:  "Resumo",
        caderno: "Caderno",
        baralho: "Baralho",
        cartao:  "Cartão",
    };

    let timer = null;
    let pedido = 0;   // descarta resposta de busca antiga

    /* ------------------------------------------------------------
       A consulta
       ------------------------------------------------------------ */
    async function buscar(termo) {
        const meu = ++pedido;

        if (termo.trim().length < 2) {
            lista.innerHTML = "";
            vazio.hidden = true;
            resumo.textContent = "Resumos, cadernos, baralhos e cartões.";
            return;
        }

        let dados;
        try {
            const r = await fetch(
                `../../../Backend/php/busca.php?q=${encodeURIComponent(termo)}`,
                { credentials: "same-origin" }
            );
            dados = await r.json();
        } catch {
            dados = null;
        }

        // Chegou atrasada: já existe uma busca mais nova em andamento.
        if (meu !== pedido) return;

        if (!dados || !dados.ok) {
            mostrarVazio("A busca falhou", "Tente de novo em um instante.");
            return;
        }

        desenhar(dados.resultados, termo);
    }

    function mostrarVazio(titulo, texto) {
        lista.innerHTML = "";
        vTitulo.textContent = titulo;
        vTexto.textContent = texto;
        vazio.hidden = false;
    }

    /* ------------------------------------------------------------
       O desenho
       ------------------------------------------------------------ */
    function desenhar(resultados, termo) {
        lista.innerHTML = "";

        if (!resultados.length) {
            resumo.textContent = "Nenhum resultado";
            mostrarVazio(
                "Nada encontrado",
                `Não achei nada com "${termo}". Tente outra palavra.`
            );
            return;
        }

        vazio.hidden = true;
        resumo.textContent =
            `${resultados.length} ${resultados.length === 1 ? "resultado" : "resultados"}`;

        resultados.forEach((r) => {
            const li = document.createElement("li");

            const a = document.createElement("a");
            a.className = "busca-item";
            a.href = r.url;

            const topo = document.createElement("div");
            topo.className = "busca-item__topo";

            const tipo = document.createElement("span");
            tipo.className = "busca-item__tipo";
            tipo.textContent = ROTULO[r.tipo] || r.tipo;

            const titulo = document.createElement("span");
            titulo.className = "busca-item__titulo";
            titulo.append(destacar(r.titulo, termo));

            topo.append(tipo, titulo);
            a.appendChild(topo);

            if (r.trecho) {
                const t = document.createElement("span");
                t.className = "busca-item__trecho";
                t.append(destacar(r.trecho, termo));
                a.appendChild(t);
            }

            li.appendChild(a);
            lista.appendChild(li);
        });
    }

    /**
     * Devolve um fragmento com o termo envolvido em <mark>.
     * Compara sem acento e sem caixa (localeCompare com
     * sensitivity "base"), porque quem digita "fotossintese" quer
     * achar "fotossíntese" — cobrar o acento numa busca é cobrar
     * do leitor o que o computador sabe fazer.
     */
    function destacar(texto, termo) {
        const frag = document.createDocumentFragment();
        const t = (texto || "").toString();

        const semAcento = (s) =>
            s.normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase();

        const alvo = semAcento(t);
        const busca = semAcento(termo.trim());
        if (!busca) {
            frag.appendChild(document.createTextNode(t));
            return frag;
        }

        let de = 0;
        let pos = alvo.indexOf(busca);

        while (pos !== -1) {
            if (pos > de) frag.appendChild(document.createTextNode(t.slice(de, pos)));

            const m = document.createElement("mark");
            // Fatiamos o texto ORIGINAL: o destaque sai com acento e
            // caixa como a pessoa escreveu, não como ela buscou.
            m.textContent = t.slice(pos, pos + busca.length);
            frag.appendChild(m);

            de = pos + busca.length;
            pos = alvo.indexOf(busca, de);
        }
        if (de < t.length) frag.appendChild(document.createTextNode(t.slice(de)));

        return frag;
    }

    /* ------------------------------------------------------------
       Ligações
       ------------------------------------------------------------ */
    campo.addEventListener("input", () => {
        clearTimeout(timer);
        timer = setTimeout(() => buscar(campo.value), 280);
    });

    form.addEventListener("submit", (e) => {
        // Enter busca na hora, sem esperar os 280 ms.
        e.preventDefault();
        clearTimeout(timer);
        buscar(campo.value);
    });

    /* Chegou com ?q= na URL (um link compartilhado, o histórico do
       navegador): já busca. */
    const inicial = new URLSearchParams(location.search).get("q");
    if (inicial) {
        campo.value = inicial;
        buscar(inicial);
    }
})();
