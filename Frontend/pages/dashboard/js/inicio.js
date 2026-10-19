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

    // A semana do gráfico é SEMPRE esta, nesta ordem (domingo primeiro)
    const DIAS_SEMANA = ["dom", "seg", "ter", "qua", "qui", "sex", "sáb"];

    // Altura das colunas: 3 h enchem a coluna. É uma escala FIXA, e não
    // "o maior dia da semana = 100%": assim 20 min é sempre uma coluna
    // baixinha e 3 h uma coluna cheia. Quem passar de 3 h num dia
    // estica a escala (de hora em hora) para nada vazar do gráfico.
    const ESCALA_MIN_MINUTOS = 180;

    let graficoComDados = false;   // evita que o estado vazio apague dados já desenhados

    document.addEventListener("DOMContentLoaded", () => {
        // Roda só na Início; nas outras abas o script nem tem alvo.
        if (!document.querySelector(".ini-hero")) return;

        mostrarDataESaudacao();
        if (!graficoComDados) renderGrafico(null);   // desenha os 7 dias em estado vazio
        ligarFocoMensal();
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
     * Desenha a semana de domingo a sábado — sempre nessa ordem,
     * qualquer que seja o dia de hoje.
     * @param {Array<{data:string, minutos:number, hoje:boolean, futuro:boolean}>|null} serie
     *        Os 7 dias, de domingo a sábado, como o inicio_dados.php
     *        manda (as datas vêm do MySQL — o projeto tem PHP e MySQL
     *        em fusos diferentes, ver README). Passe null para o
     *        estado vazio: aí o "hoje" sai do relógio do navegador.
     */
    function renderGrafico(serie) {
        const chart = document.getElementById("iniChart");
        if (!chart) return;

        let dias;
        if (Array.isArray(serie) && serie.length === 7) {
            dias = serie.map((d) => ({
                minutos: Number(d.minutos) || 0,
                hoje:    !!d.hoje,
                futuro:  !!d.futuro,
            }));
        } else {
            const hoje = new Date().getDay();               // 0 = domingo
            dias = DIAS_SEMANA.map((_, i) => ({ minutos: 0, hoje: i === hoje, futuro: i > hoje }));
        }

        const maior  = Math.max(...dias.map((d) => d.minutos));
        const escala = Math.max(ESCALA_MIN_MINUTOS, Math.ceil(maior / 60) * 60);
        const total  = dias.reduce((s, d) => s + d.minutos, 0);
        const vazio  = total === 0;
        graficoComDados = !vazio;

        chart.innerHTML = "";
        dias.forEach((dia, i) => {
            const altura = Math.min(100, Math.round((dia.minutos / escala) * 100));

            const col = document.createElement("div");
            col.className = "ini-col"
                + (dia.hoje ? " ini-col--hoje" : "")
                + (dia.futuro ? " ini-col--futuro" : "")
                + (dia.minutos === 0 ? " ini-col--vazio" : "");

            const trilho = document.createElement("div");
            trilho.className = "ini-col__trilho";

            const barra = document.createElement("div");
            barra.className = "ini-col__barra";
            barra.style.setProperty("--h", altura + "%");

            // O "Tempo de foco" de cada coluna (aparece ao passar o mouse).
            // Dia que ainda não chegou não tem o que dizer.
            if (!dia.futuro) {
                const dica = document.createElement("span");
                dica.className = "ini-col__tempo";
                dica.textContent = `Tempo de foco: ${duracaoExtenso(dia.minutos)}`;
                col.appendChild(dica);
            }

            const rotulo = document.createElement("span");
            rotulo.className = "ini-col__dia";
            rotulo.textContent = DIAS_SEMANA[i];

            trilho.appendChild(barra);
            col.appendChild(trilho);
            col.appendChild(rotulo);
            chart.appendChild(col);
        });

        chart.setAttribute("aria-label", vazio
            ? "Nenhum tempo de foco nesta semana"
            : `Tempo de foco desta semana: ${duracaoExtenso(total)}`);

        const aviso = document.getElementById("iniChartAviso");
        if (aviso) aviso.hidden = !vazio;
    }

    /** "1 hora e 20 minutos", "45 minutos", "2 horas", "0 minutos" */
    function duracaoExtenso(min) {
        const h = Math.floor(min / 60);
        const m = min % 60;
        const horas   = `${h} ${h === 1 ? "hora" : "horas"}`;
        const minutos = `${m} ${m === 1 ? "minuto" : "minutos"}`;
        if (!h) return minutos;
        return m ? `${horas} e ${minutos}` : horas;
    }

    /* ------------------------------------------------------------
       Frequência mensal de foco
       Uma segunda "página" dentro do próprio painel: o botão "Mês"
       do cabeçalho troca o gráfico da semana pelo calendário do mês,
       com a cor de cada dia pelo tempo de foco; "Semana" volta.
       Os dados vêm do foco_mensal.php — datas pelo MySQL.
       ------------------------------------------------------------ */
    const MESES_ABREV = ["jan", "fev", "mar", "abr", "mai", "jun", "jul", "ago", "set", "out", "nov", "dez"];
    let focoMesPedido = 0;          // descarta resposta atrasada de um mês que já foi trocado
    let focoMesCarregado = false;   // busca o mês só na primeira vez que a pessoa abre

    function ligarFocoMensal() {
        const btnSemana = document.getElementById("iniVistaSemana");
        const btnMes    = document.getElementById("iniVistaMes");
        if (!btnSemana || !btnMes) return;

        const vistas = [
            { btn: btnSemana, painel: document.getElementById("iniSemana"), titulo: "Esta semana" },
            { btn: btnMes,    painel: document.getElementById("iniMes"),    titulo: "Este mês" },
        ];
        const titulo = document.getElementById("iniPainelTitulo");
        let atual = vistas[0];

        const mostrar = (escolhida) => {
            if (escolhida === atual) return;
            const de = atual;
            atual = escolhida;

            vistas.forEach((v) => {
                const ativa = v === escolhida;
                v.btn.classList.toggle("ativo", ativa);
                v.btn.setAttribute("aria-selected", ativa ? "true" : "false");
                v.btn.tabIndex = ativa ? 0 : -1;
            });

            // Semana → Mês desliza para a esquerda; a volta, para a direita
            const lado = vistas.indexOf(escolhida) > vistas.indexOf(de) ? 1 : -1;
            trocarVista(de.painel, escolhida.painel, lado, titulo, escolhida.titulo);
        };

        // O mês já vem buscado: quando a pessoa clicar em "Mês" ele está
        // pronto, sem aparecer vazio e "pular" quando os dados chegam.
        focoMesCarregado = true;
        carregarFocoMes(null);

        vistas.forEach((v) => v.btn.addEventListener("click", () => mostrar(v)));

        // setas ← → trocam de aba, como em qualquer tablist
        vistas.forEach((v, i) => v.btn.addEventListener("keydown", (e) => {
            if (e.key !== "ArrowLeft" && e.key !== "ArrowRight") return;
            const outra = vistas[(i + 1) % vistas.length];
            mostrar(outra);
            outra.btn.focus();
        }));

        document.getElementById("focoMesAnterior").addEventListener("click", (e) => {
            if (e.currentTarget.dataset.mes) carregarFocoMes(e.currentTarget.dataset.mes);
        });
        document.getElementById("focoMesProximo").addEventListener("click", (e) => {
            if (e.currentTarget.dataset.mes) carregarFocoMes(e.currentTarget.dataset.mes);
        });
    }

    /* ------------------------------------------------------------
       Transição entre Semana e Mês
       A visão de saída some deslizando para um lado, a de entrada vem
       do outro, e a caixa (#iniVistas) muda de altura suavemente em
       vez de pular — o mês é mais alto que o gráfico. Quem pediu
       "reduzir movimento" no sistema recebe a troca seca.
       ------------------------------------------------------------ */
    // sem animação para quem pediu "reduzir movimento" OU está no visual leve
    // (função: o Lite pode ser ligado na hora, pela engrenagem)
    const REDUZ_MOVIMENTO = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const semMovimento = () => REDUZ_MOVIMENTO || document.documentElement.classList.contains("kosmos-lite");
    const MOLA = "cubic-bezier(.2, .7, .2, 1)";

    /** Muda o conteúdo da caixa com `mudar()` e anima a altura dela do
        tamanho antigo para o novo. */
    function animarAltura(caixa, mudar, duracao = 300) {
        const antes = caixa.offsetHeight;
        mudar();
        const depois = caixa.offsetHeight;
        if (semMovimento() || !caixa.animate || antes === depois) return Promise.resolve();

        caixa.style.overflow = "hidden";   // nada vaza enquanto a caixa cresce/encolhe
        const anim = caixa.animate(
            [{ height: `${antes}px` }, { height: `${depois}px` }],
            { duration: duracao, easing: MOLA }
        );
        return anim.finished.catch(() => {}).then(() => { caixa.style.overflow = ""; });
    }

    let trocaAtual = 0;                     // clique rápido: só a última troca mexe na tela

    async function trocarVista(sai, entra, lado, elTitulo, novoTitulo) {
        const caixa = document.getElementById("iniVistas");
        const minhaTroca = ++trocaAtual;

        if (semMovimento() || !caixa || !sai.animate) {
            sai.hidden = true;
            entra.hidden = false;
            elTitulo.textContent = novoTitulo;
            return;
        }

        // cancela o que ainda estiver rodando (clique rápido nas duas abas)
        [sai, entra, caixa, elTitulo].forEach((el) => el.getAnimations().forEach((a) => a.cancel()));
        caixa.style.overflow = "";

        // 1) sai: desliza um pouco e some
        const saida = sai.animate(
            [{ opacity: 1, transform: "none" }, { opacity: 0, transform: `translateX(${-24 * lado}px)` }],
            { duration: 160, easing: "ease-in", fill: "forwards" }
        );
        elTitulo.animate([{ opacity: 1 }, { opacity: 0 }], { duration: 160, fill: "forwards" });
        await saida.finished.catch(() => {});
        if (minhaTroca !== trocaAtual) return;     // outra troca já assumiu

        // 2) troca, com a altura da caixa indo de uma para a outra
        const altura = animarAltura(caixa, () => {
            sai.hidden = true;
            entra.hidden = false;
        });
        saida.cancel();                     // tira o "forwards" da que saiu (ela já está escondida)
        elTitulo.textContent = novoTitulo;
        elTitulo.getAnimations().forEach((a) => a.cancel());
        elTitulo.animate([{ opacity: 0 }, { opacity: 1 }], { duration: 220 });

        // 3) entra: vem do outro lado
        const entrada = entra.animate(
            [{ opacity: 0, transform: `translateX(${24 * lado}px)` }, { opacity: 1, transform: "none" }],
            { duration: 280, easing: MOLA }
        );
        await Promise.all([altura, entrada.finished.catch(() => {})]);
    }

    async function carregarFocoMes(mes) {
        const pedido = ++focoMesPedido;
        const erro = document.getElementById("focoMesErro");
        erro.hidden = true;
        document.getElementById("focoMesGrade").classList.add("foco-mes__grade--carregando");

        let dados = null;
        try {
            const url = "../../../Backend/php/foco_mensal.php" + (mes ? `?mes=${encodeURIComponent(mes)}` : "");
            const r = await fetch(url, { credentials: "same-origin" });
            if (r.ok) dados = await r.json();
        } catch {
            dados = null;
        }
        if (pedido !== focoMesPedido) return;      // a pessoa já trocou de mês

        document.getElementById("focoMesGrade").classList.remove("foco-mes__grade--carregando");
        if (!dados || !dados.ok) {
            erro.hidden = false;
            return;
        }
        // Trocar de mês pode mudar o calendário de 5 para 6 semanas: com
        // o mês à vista, a caixa acompanha suave em vez de pular.
        const caixa = document.getElementById("iniVistas");
        if (caixa && !document.getElementById("iniMes").hidden) {
            animarAltura(caixa, () => renderFocoMes(dados), 240);
        } else {
            renderFocoMes(dados);
        }
    }

    function renderFocoMes(d) {
        const titulo = d.titulo.charAt(0).toUpperCase() + d.titulo.slice(1);
        document.getElementById("focoMesTitulo").textContent = titulo;

        const ant = document.getElementById("focoMesAnterior");
        const prox = document.getElementById("focoMesProximo");
        ant.disabled  = !d.anterior;
        prox.disabled = !d.proximo;
        ant.dataset.mes  = d.anterior || "";
        prox.dataset.mes = d.proximo || "";

        const mesNum = Number(d.mes.slice(5, 7));
        const r = d.resumo;
        const set = (chave, txt) => { document.querySelector(`[data-foco="${chave}"]`).textContent = txt; };
        set("total", r.total ? formatarDuracao(r.total) : "—");
        set("dias", `${r.dias_com_foco} de ${d.hoje ?? d.dias.length}`);
        set("media", r.media ? formatarDuracao(r.media) : "—");
        set("melhor", r.melhor
            ? `${r.melhor.dia} ${MESES_ABREV[mesNum - 1]} · ${formatarDuracao(r.melhor.minutos)}`
            : "—");

        const grade = document.getElementById("focoMesGrade");
        grade.innerHTML = "";
        for (let i = 0; i < d.inicio; i++) {
            const vazio = document.createElement("span");
            vazio.className = "foco-dia foco-dia--fora";
            grade.appendChild(vazio);
        }
        d.dias.forEach(({ dia, minutos }) => {
            const futuro = d.hoje !== null && dia > d.hoje;
            const cel = document.createElement("span");
            cel.className = `foco-dia foco-n${nivelFoco(minutos)}`
                + (dia === d.hoje ? " foco-dia--hoje" : "")
                + (futuro ? " foco-dia--futuro" : "");
            cel.textContent = dia;
            cel.title = futuro
                ? `${dia} de ${d.titulo.split(" ")[0]}`
                : `${dia} de ${d.titulo.split(" ")[0]} — Tempo de foco: ${duracaoExtenso(minutos)}`;
            grade.appendChild(cel);
        });
    }

    /** Intensidade da cor no calendário (mesma da legenda do modal) */
    function nivelFoco(min) {
        if (min <= 0)  return 0;
        if (min < 30)  return 1;
        if (min < 60)  return 2;
        if (min < 120) return 3;
        return 4;
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

        // as missões da semana reaproveitam o visual .ini-passo, mas têm a
        // própria função (renderMissoes) — não entram nesta conta
        const itens = [...document.querySelectorAll(".ini-passo:not(.ini-missao)")];
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
       Missões da semana (depois dos primeiros passos)
       O index.php pinta o cartão; aqui só se reaplica o progresso de
       cada missão quando a resposta do inicio_dados.php chega — o
       mesmo motivo dos primeiros passos (Pomodoro fechando um ciclo
       em outra aba). Se a semana virou com a aba aberta, o conjunto
       de missões muda: aí não dá para remendar item a item, e a
       própria tela se recarrega na próxima visita.
       ------------------------------------------------------------ */
    function renderMissoes(m) {
        const cartao = document.getElementById("iniMissoes");
        if (!cartao || !m || !Array.isArray(m.missoes)) return;

        const semanaTela = cartao.querySelector("[data-missoes-semana]")?.textContent.replace(/\D/g, "");
        if (semanaTela && +semanaTela !== +m.semana) return;

        m.missoes.forEach((missao) => {
            const item = cartao.querySelector(`[data-missao="${CSS.escape(missao.slug)}"]`);
            if (!item) return;
            item.classList.toggle("feito", !!missao.feita);
            const prog = item.querySelector("[data-missao-prog]");
            if (prog) prog.textContent = missao.progresso;
            const pct = item.querySelector("[data-missao-pct]");
            if (pct) pct.style.setProperty("--pct", Math.floor(100 * missao.valor / Math.max(1, missao.alvo)) + "%");
            const estado = item.querySelector(".ini-passo__estado");
            if (estado) estado.textContent = missao.feita ? "Concluída" : "Em andamento";
        });

        const barra = document.getElementById("iniMissoesBarra");
        if (barra) { barra.max = m.total; barra.value = m.feitas; }

        const contador = document.getElementById("iniMissoesContador");
        if (contador) {
            const d = +m.dias_restantes || 0;
            const prazo = d === 0 ? "termina hoje" : d === 1 ? "termina amanhã" : `faltam ${d} dias`;
            contador.textContent = m.completa ? "semana completa ✦" : `${m.feitas} de ${m.total} · ${prazo}`;
        }
        cartao.classList.toggle("ini-missoes--completa", !!m.completa);
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
            exercicios: dados.metricas?.exercicios,
        });

        renderMeta(dados.hoje, dados.meta);
        renderRevisar(dados.metricas?.vencidos);
        renderRevisarExercicios(dados.metricas?.exercicios_revisar, dados.metricas?.exercicios_revisar_materia);
        renderPassos(dados.passos);
        renderMissoes(dados.missoes);
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

    /** O mesmo lembrete para os exercícios: só aparece quando algum tem
        questão errada na última tentativa. Se todos são da mesma
        matéria, leva direto a ela; senão, à lista de matérias. */
    function renderRevisarExercicios(n, materiaId) {
        const el = document.getElementById("iniRevisarEx");
        if (!el) return;

        n = +n || 0;
        el.hidden = n === 0;
        if (n === 0) return;

        el.href = materiaId ? `exercicio_materia.php?id=${+materiaId}` : "exercicios.php";
        el.querySelector("[data-revisar-ex-n]").textContent = n;
        el.querySelector("[data-revisar-ex-txt]").textContent =
            n === 1 ? "exercício com questões para refazer" : "exercícios com questões para refazer";
    }

    /* ------------------------------------------------------------
       Ponte: outras telas podem repintar sem recarregar.
       ------------------------------------------------------------ */
    window.KosmosInicio = { preencherMetricas, renderGrafico, renderPassos, renderMissoes, carregarDados };
})();
