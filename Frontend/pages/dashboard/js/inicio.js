/* ============================================================
   KOSMOS — inicio.js  (só a página Início / index.html)
   Saudação, estatísticas, gráfico da semana e primeiros passos.
   (O brilho/tilt dos cartões vive no dashboard.js, compartilhado.)

   IMPORTANTE — dados: hoje o único número real é a SEQUÊNCIA
   (vem de usuario_atual.php via dashboard.js). Resumos,
   flashcards, exercícios e o gráfico ficam em estado vazio
   porque ainda não existe persistência no banco.
   Quando os endpoints existirem, NÃO é preciso mexer no HTML:
   basta chamar as funções expostas em window.KosmosInicio
   (ver o fim do arquivo) com os dados vindos do backend.

   Tudo dentro de uma IIFE: o dashboard.js e os scripts de
   página dividem o mesmo escopo global, então nada aqui pode
   vazar (ex.: um "const API" duplicado quebraria a página).
   ============================================================ */
(() => {
    "use strict";

    const CHAVE_PASSOS = "kosmos_passos";   // localStorage: onboarding (sem backend)
    const DIAS_GRAFICO = 7;

    let graficoComDados = false;   // evita que o estado vazio apague dados já desenhados

    document.addEventListener("DOMContentLoaded", () => {
        // Roda só na Início; nas outras abas o script nem tem alvo.
        if (!document.querySelector(".ini-hero")) return;

        mostrarDataESaudacao();
        if (!graficoComDados) renderGrafico(null);   // desenha os 7 dias em estado vazio
        iniciarPassos();
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
       Primeiros passos — checklist de onboarding
       Guardado no localStorage (é preferência de exibição, não
       dado de estudo; por isso não precisa de backend).
       ------------------------------------------------------------ */
    function iniciarPassos() {
        const itens = [...document.querySelectorAll(".ini-passo")];
        if (!itens.length) return;

        const feitos = new Set(lerPassos());

        itens.forEach((item) => {
            const slug = item.dataset.passo;
            const botao = item.querySelector(".ini-passo__check");

            aplicar(item, botao, feitos.has(slug));

            botao?.addEventListener("click", () => {
                const agoraFeito = !feitos.has(slug);
                agoraFeito ? feitos.add(slug) : feitos.delete(slug);
                aplicar(item, botao, agoraFeito);
                gravarPassos([...feitos]);
                atualizarProgresso(itens.length, feitos.size);
            });
        });

        atualizarProgresso(itens.length, feitos.size);
    }

    function aplicar(item, botao, feito) {
        item.classList.toggle("feito", feito);
        botao?.setAttribute("aria-pressed", String(feito));
    }

    function atualizarProgresso(total, feitos) {
        const barra = document.getElementById("iniPassosBarra");
        if (barra) { barra.max = total; barra.value = feitos; }

        const contador = document.getElementById("iniPassosContador");
        if (contador) {
            contador.textContent = feitos === total ? "tudo pronto ✦" : `${feitos} de ${total}`;
        }
    }

    function lerPassos() {
        try {
            const bruto = localStorage.getItem(CHAVE_PASSOS);
            const lista = bruto ? JSON.parse(bruto) : [];
            return Array.isArray(lista) ? lista : [];
        } catch {
            return [];   // modo privado / storage bloqueado
        }
    }

    function gravarPassos(lista) {
        try {
            localStorage.setItem(CHAVE_PASSOS, JSON.stringify(lista));
        } catch { /* sem storage: vale só nesta visita */ }
    }


    /* ------------------------------------------------------------
       Ponte para quando a persistência existir.
       Exemplo de uso (num futuro estatisticas.php):
           const r = await fetch(`${API}/estatisticas.php`);
           const j = await r.json();
           KosmosInicio.preencherMetricas(j.totais);
           KosmosInicio.renderGrafico(j.semana);
       ------------------------------------------------------------ */
    window.KosmosInicio = { preencherMetricas, renderGrafico };
})();
