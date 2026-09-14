/* ============================================================
   KOSMOS — inicio.js  (só a página Início / index.html)
   Saudação, estatísticas, gráfico da semana e primeiros passos.
   (O brilho/tilt dos cartões vive no dashboard.js, compartilhado.)

   DADOS: vêm de Backend/php/inicio_dados.php numa requisição
   só — série da semana, minutos de hoje, meta diária, contagens
   e próximas provas. O gráfico nasce em estado vazio e é
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

    const CHAVE_PASSOS = "kosmos_passos";   // localStorage: onboarding (sem backend)
    const DIAS_GRAFICO = 7;

    let graficoComDados = false;   // evita que o estado vazio apague dados já desenhados

    document.addEventListener("DOMContentLoaded", () => {
        // Roda só na Início; nas outras abas o script nem tem alvo.
        if (!document.querySelector(".ini-hero")) return;

        mostrarDataESaudacao();
        if (!graficoComDados) renderGrafico(null);   // desenha os 7 dias em estado vazio
        iniciarPassos();
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
        renderProvas(dados.provas);
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

    /** As próximas provas. A contagem de dias vem PRONTA do servidor
        (DATEDIFF no MySQL) — este arquivo não calcula data, porque o
        PHP e o MySQL do projeto estão em fusos diferentes. */
    function renderProvas(provas) {
        const secao = document.getElementById("iniProvas");
        if (!secao) return;

        const lista = Array.isArray(provas) ? provas : [];
        const alvo  = secao.querySelector("[data-provas-lista]");
        const vazio = secao.querySelector("[data-provas-vazio]");

        if (vazio) vazio.hidden = lista.length > 0;
        alvo.innerHTML = "";
        if (lista.length === 0) return;

        lista.forEach((p) => {
            const li = document.createElement("li");
            li.className = "ini-prova";
            if (p.faltam <= 7) li.classList.add("ini-prova--perto");

            const quantos = document.createElement("strong");
            quantos.textContent = p.faltam === 0 ? "hoje"
                                : p.faltam === 1 ? "amanhã"
                                : `${p.faltam} dias`;

            const nome = document.createElement("span");
            nome.className = "ini-prova__nome";
            // textContent, não innerHTML: o título vem do que a pessoa digitou
            nome.textContent = p.titulo;

            const quando = document.createElement("span");
            quando.className = "ini-prova__quando";
            quando.textContent = p.quando;

            const apagar = document.createElement("button");
            apagar.type = "button";
            apagar.className = "ini-prova__apagar";
            apagar.setAttribute("aria-label", `Apagar a prova ${p.titulo}`);
            apagar.textContent = "×";
            apagar.addEventListener("click", () => excluirProva(p));

            li.append(quantos, nome, quando, apagar);
            alvo.appendChild(li);
        });
    }

    /* ------------------------------------------------------------
       PROVAS — cadastro e exclusão
       ------------------------------------------------------------ */
    const modal = {
        caixa:    document.getElementById("modalProva"),
        form:     document.getElementById("provaForm"),
        nome:     document.getElementById("provaNome"),
        materia:  document.getElementById("provaMateria"),
        data:     document.getElementById("provaData"),
        msg:      document.getElementById("provaMsg"),
        salvar:   document.getElementById("provaSalvar"),
    };

    function abrirProva() {
        if (!modal.caixa) return;

        modal.form.reset();
        modal.msg.hidden = true;

        /* O `min` sai do relógio do NAVEGADOR, não do servidor (ver o
           comentário em partes/modal-prova.php): o PHP deste projeto
           roda em Europe/Berlin e à noite, no Brasil, bloquearia o dia
           de hoje. `sv-SE` porque esse locale formata como YYYY-MM-DD,
           que é o que o <input type="date"> espera — e faz isso no fuso
           local, ao contrário de toISOString(), que converte para UTC e
           erra o dia perto da meia-noite. */
        modal.data.min = new Date().toLocaleDateString("sv-SE");

        modal.caixa.classList.add("open");
        modal.nome.focus();
    }

    function fecharProva() {
        modal.caixa?.classList.remove("open");
    }

    function avisoProva(texto, erro = true) {
        modal.msg.textContent = texto;
        modal.msg.className = `msg ${erro ? "msg--erro" : "msg--sucesso"}`;
        modal.msg.hidden = false;
    }

    async function salvarProva(e) {
        e.preventDefault();

        const titulo = modal.nome.value.trim();
        if (!titulo)            return avisoProva("Dê um nome para a prova.");
        if (!modal.data.value)  return avisoProva("Escolha a data da prova.");

        modal.salvar.disabled = true;
        try {
            const r = await fetch("../../../Backend/php/provas.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                credentials: "same-origin",
                body: JSON.stringify({
                    acao: "criar",
                    titulo,
                    materia: modal.materia.value,
                    data: modal.data.value,
                }),
            });
            const j = await r.json();

            if (!j.ok) return avisoProva(j.msg || "Não foi possível salvar.");

            // O servidor devolve a lista já atualizada: nada de recarregar
            // a página nem de adivinhar onde a nova entra na ordem.
            renderProvas(j.provas);
            fecharProva();
        } catch {
            avisoProva("Sem conexão com o servidor.");
        } finally {
            modal.salvar.disabled = false;
        }
    }

    async function excluirProva(prova) {
        /* confirmar() é o modal compartilhado do dashboard.js. Apagar
           é irreversível e um clique errado no "×" é fácil. */
        const ok = window.confirmar
            ? await window.confirmar({
                  titulo: "Apagar prova",
                  texto: `"${prova.titulo}" sai da sua lista. Isso não volta.`,
                  botao: "Apagar",
                  perigo: true,
              })
            : true;
        if (!ok) return;

        try {
            const r = await fetch("../../../Backend/php/provas.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                credentials: "same-origin",
                body: JSON.stringify({ acao: "excluir", id: prova.id }),
            });
            const j = await r.json();
            if (j.ok) renderProvas(j.provas);
        } catch { /* a linha continua na tela; recarregar resolve */ }
    }

    document.getElementById("btnNovaProva")?.addEventListener("click", abrirProva);
    document.getElementById("provaFechar")?.addEventListener("click", fecharProva);
    document.getElementById("provaCancelar")?.addEventListener("click", fecharProva);
    modal.form?.addEventListener("submit", salvarProva);

    /* ------------------------------------------------------------
       Ponte: outras telas podem repintar sem recarregar.
       ------------------------------------------------------------ */
    window.KosmosInicio = { preencherMetricas, renderGrafico, carregarDados };
})();
