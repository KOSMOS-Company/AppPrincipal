/* ============================================================
   KOSMOS — inicio.js  (só a página Início / index.html)
   Saudação, estatísticas, gráfico da semana e primeiros passos.
   (O brilho/tilt dos cartões vive no dashboard.js, compartilhado.)

   DADOS: vêm de Backend/php/inicio_dados.php numa requisição
   só — série da semana, minutos de hoje, meta diária, contagens
   e a fila de revisão. O gráfico nasce em estado vazio e é
   repintado quando a resposta chega; se a rede falhar, o estado
   vazio permanece e nada quebra.

   As sessões que alimentam o gráfico são gravadas pelo
   js/pomodoro-aviso.js (que roda em toda aba), não por este
   arquivo.

   Tudo dentro de uma IIFE: o dashboard.js e os scripts de
   página dividem o mesmo escopo global, então nada aqui pode
   vazar (ex.: um "const API" duplicado quebraria a página).
   ============================================================ */
(() => {
    "use strict";

    const DIAS_GRAFICO = 7;

    let graficoComDados = false;   // evita que o estado vazio apague dados já desenhados

    document.addEventListener("DOMContentLoaded", () => {
        // Roda só na Início; nas outras abas o script nem tem alvo.
        if (!document.querySelector(".ini-hero")) return;

        mostrarDataESaudacao();
        if (!graficoComDados) renderGrafico(null);   // desenha os 7 dias em estado vazio
        carregarDados();
    });

    /* A sequência (e o que mais existir de real) já vem impressa pelo
       index.php. preencherMetricas() continua exposto para quando os
       outros números passarem a existir no banco. */

    /* ------------------------------------------------------------
       Abertura: data de hoje + saudação pelo horário
       ------------------------------------------------------------ */
    function mostrarDataESaudacao() {
        const agora = new Date();

        const elData = document.getElementById("iniData");
        if (elData) {
            elData.textContent = agora.toLocaleDateString("pt-BR", {
                weekday: "long", day: "numeric", month: "long"
            });
        }

        const elOla = document.getElementById("iniSaudacao");
        if (elOla) {
            const h = agora.getHours();
            elOla.textContent =
                h < 6  ? "Boa madrugada" :
                h < 12 ? "Bom dia"       :
                h < 18 ? "Boa tarde"     : "Boa noite";
        }
    }

    /* ------------------------------------------------------------
       Estatísticas (números soltos, como no hero da LP)
       ------------------------------------------------------------ */
    /**
     * Preenche os números da abertura. Passe só o que tiver:
     *   preencherMetricas({ sequencia: 3, resumos: 12, flashcards: 40, exercicios: 25 })
     * Chave ausente = número continua em "—".
     */
    function preencherMetricas(dados = {}) {
        ["sequencia", "resumos", "flashcards", "exercicios"].forEach((chave) => {
            const valor = dados[chave];
            if (typeof valor !== "number") return;

            const el = document.querySelector(`[data-metrica="${chave}"]`);
            if (!el) return;
            el.textContent = String(valor);

            // Zero fica apagado; a partir de 1 ganha o roxo claro da LP
            el.closest(".ini-stat")?.classList.toggle("ini-stat--vazio", valor === 0);
        });
    }

    /* ------------------------------------------------------------
       Gráfico semanal — barras em CSS puro (sem biblioteca)
       ------------------------------------------------------------ */
    /**
     * Desenha os últimos 7 dias (hoje na ponta direita).
     * @param {Array<{data:string, minutos:number}>|null} serie
     *        `data` no formato "YYYY-MM-DD". Passe null/[] para
     *        o estado vazio. Dias sem entrada valem zero.
     *
     * OBS. p/ quando ligar no backend: mande a data já pronta do
     * servidor e num só relógio (o projeto tem PHP e MySQL em
     * fusos diferentes — ver "Problemas conhecidos" no README).
     */
    function renderGrafico(serie) {
        const chart = document.getElementById("iniChart");
        if (!chart) return;

        const porData = new Map(
            (serie || []).map((d) => [d.data, Number(d.minutos) || 0])
        );

        // Últimos 7 dias, do mais antigo para hoje
        const hoje = new Date();
        const dias = [];
        for (let i = DIAS_GRAFICO - 1; i >= 0; i--) {
            const d = new Date(hoje.getFullYear(), hoje.getMonth(), hoje.getDate() - i);
            dias.push({ data: d, minutos: porData.get(chaveISO(d)) || 0 });
        }

        const maior = Math.max(...dias.map((d) => d.minutos));
        const total = dias.reduce((s, d) => s + d.minutos, 0);
        const vazio = total === 0;
        graficoComDados = !vazio;

        chart.innerHTML = "";
        dias.forEach((dia, i) => {
            const ehHoje = i === dias.length - 1;
            const altura = maior > 0 ? Math.round((dia.minutos / maior) * 100) : 0;

            const col = document.createElement("div");
            col.className = "ini-col"
                + (ehHoje ? " ini-col--hoje" : "")
                + (dia.minutos === 0 ? " ini-col--vazio" : "");
            col.title = `${rotuloCompleto(dia.data)} — `
                + (dia.minutos ? formatarDuracao(dia.minutos) : "sem registro");

            const trilho = document.createElement("div");
            trilho.className = "ini-col__trilho";

            const barra = document.createElement("div");
            barra.className = "ini-col__barra";
            barra.style.setProperty("--h", altura + "%");

            const rotulo = document.createElement("span");
            rotulo.className = "ini-col__dia";
            rotulo.textContent = ehHoje ? "hoje" : rotuloCurto(dia.data);

            trilho.appendChild(barra);
            col.appendChild(trilho);
            col.appendChild(rotulo);
            chart.appendChild(col);
        });

        chart.setAttribute("aria-label", vazio
            ? "Nenhum estudo registrado nos últimos sete dias"
            : `Estudo dos últimos sete dias, ${formatarDuracao(total)} no total`);

        const aviso = document.getElementById("iniChartAviso");
        if (aviso) aviso.hidden = !vazio;

        const elTotal = document.querySelector("[data-total-semana]");
        if (elTotal) {
            elTotal.textContent = vazio
                ? "últimos 7 dias"
                : `${formatarDuracao(total)} nos últimos 7 dias`;
        }
    }

    function chaveISO(d) {
        const mes = String(d.getMonth() + 1).padStart(2, "0");
        const dia = String(d.getDate()).padStart(2, "0");
        return `${d.getFullYear()}-${mes}-${dia}`;
    }

    function rotuloCurto(d) {
        // "seg." -> "seg"
        return d.toLocaleDateString("pt-BR", { weekday: "short" }).replace(".", "");
    }

    function rotuloCompleto(d) {
        return d.toLocaleDateString("pt-BR", { weekday: "long", day: "2-digit", month: "2-digit" });
    }

    function formatarDuracao(min) {
        const h = Math.floor(min / 60);
        const m = min % 60;
        if (!h) return `${m} min`;
        return m ? `${h} h ${m} min` : `${h} h`;
    }

    /* ------------------------------------------------------------
       Primeiros passos
       Não é mais uma checklist que a pessoa marca: cada item é lido
       do que ela FEZ (tem resumo? tem cartão? já fechou um ciclo de
       foco?). O index.php pinta a lista pelo servidor, então ela já
       chega certa na primeira tela — esta função só reaplica quando
       a resposta do inicio_dados.php traz `passos`.

       Por que reaplicar, se a página já veio pronta: o pomodoro-aviso.js
       roda em TODA aba e grava a sessão quando o ciclo fecha. Quem
       deixa a Início aberta num monitor e o Pomodoro em outro veria a
       lista velha até recarregar.

       Ela nunca DESMARCA nada por conta de rede: `passos` ausente
       (resposta antiga, erro, campo faltando) deixa como está.
       ------------------------------------------------------------ */
    function renderPassos(passos) {
        if (!passos || typeof passos !== "object") return;

        const itens = [...document.querySelectorAll(".ini-passo")];
        if (!itens.length) return;

        let feitos = 0;

        itens.forEach((item) => {
            const slug = item.dataset.passo;
            // passo que o servidor não conhece: mantém o que veio no HTML
            const feito = slug in passos ? !!passos[slug] : item.classList.contains("feito");

            item.classList.toggle("feito", feito);

            const estado = item.querySelector(".ini-passo__estado");
            if (estado) estado.textContent = feito ? "Concluído" : "Ainda não feito";

            if (feito) feitos++;
        });

        atualizarProgresso(itens.length, feitos);
    }

    function atualizarProgresso(total, feitos) {
        const barra = document.getElementById("iniPassosBarra");
        if (barra) { barra.max = total; barra.value = feitos; }

        const contador = document.getElementById("iniPassosContador");
        if (contador) {
            contador.textContent = feitos === total ? "tudo pronto ✦" : `${feitos} de ${total}`;
        }
    }


    /* ------------------------------------------------------------
       Os dados de verdade
       ------------------------------------------------------------ */

    /**
     * Uma requisição só traz tudo que a tela precisa.
     *
     * Falha em silêncio de propósito: a página já foi desenhada em
     * estado vazio antes desta chamada, então uma rede ruim deixa o
     * Início com os traços do gráfico e o aviso de "ainda não há
     * sessões" — que é uma tela correta, não uma tela quebrada.
     * Barra de erro vermelha aqui só assustaria por algo que a
     * pessoa não pediu.
     */
    async function carregarDados() {
        let dados;
        try {
            const r = await fetch("../../../Backend/php/inicio_dados.php", {
                credentials: "same-origin",
            });
            if (!r.ok) return;
            dados = await r.json();
            if (!dados || !dados.ok) return;
        } catch {
            return;
        }

        renderGrafico(dados.semana);

        preencherMetricas({
            resumos:    dados.metricas?.resumos,
            flashcards: dados.metricas?.flashcards,
        });

        renderMeta(dados.hoje, dados.meta);
        renderRevisar(dados.metricas?.vencidos);
        renderPassos(dados.passos);
    }

    /** A meta diária. Existia em usuario_preferencias desde agosto e
        nunca era lida por ninguém — salvar um número que nada usa é
        pior do que não ter o campo. */
    function renderMeta(minutos, meta) {
        const caixa = document.getElementById("iniMeta");
        if (!caixa || !(meta > 0)) return;

        const feito = Math.max(0, +minutos || 0);
        const pct   = Math.min(100, Math.round((feito / meta) * 100));

        caixa.hidden = false;
        caixa.querySelector("[data-meta-barra]").value = Math.min(feito, meta);
        caixa.querySelector("[data-meta-barra]").max   = meta;
        caixa.querySelector("[data-meta-texto]").textContent =
            feito >= meta
                ? `Meta do dia batida — ${formatarDuracao(feito)}`
                : `${formatarDuracao(feito)} de ${formatarDuracao(meta)} hoje`;
        caixa.classList.toggle("ini-meta--batida", feito >= meta);
        caixa.setAttribute("aria-label", `Meta diária: ${pct}% concluída`);
    }

    /** O atalho "Revisar hoje" só aparece quando há o que revisar:
        um card anunciando "0 cartões" é ruído. */
    function renderRevisar(vencidos) {
        const el = document.getElementById("iniRevisar");
        if (!el) return;

        const n = +vencidos || 0;
        el.hidden = n === 0;
        if (n === 0) return;

        el.querySelector("[data-revisar-n]").textContent = n;
        el.querySelector("[data-revisar-txt]").textContent =
            n === 1 ? "cartão esperando revisão" : "cartões esperando revisão";
    }

    /* ------------------------------------------------------------
       Ponte: outras telas podem repintar sem recarregar.
       ------------------------------------------------------------ */
    window.KosmosInicio = { preencherMetricas, renderGrafico, renderPassos, carregarDados };
})();
