/* ============================================================
   KOSMOS — exercicio-gerar-form.js
   Modal de gerar/salvar exercícios com IA.
   Usado em exercicio_materia.php.

   O usuário marca 1+ dificuldades (chips) e escolhe a distribuição:
   - "Por dificuldade": um stepper por nível (quantas de cada);
   - "Aleatória": um total só, dividido sorteado entre os níveis.
   Nos dois casos o envio é um "plano" [{dificuldade, qtd}] — o
   backend gera em UMA chamada de IA e devolve cada questão já
   com a sua dificuldade.
   ============================================================ */
(() => {
    "use strict";

    const BACKEND = "../../../Backend/php";
    const DIFICULDADES = ["Fácil", "Médio", "Difícil"];
    const modal = document.getElementById("modalExercicioGerar");
    if (!modal) return;

    const el = (id) => document.getElementById(id);

    let materiaId = 0;
    let materiaNome = "";
    let questoesGeradas = [];
    let planoGerado = [];
    let modo = "fixo"; // "fixo" = quantas de cada | "aleatorio" = total sorteado
    let qtds = { "Fácil": 0, "Médio": 5, "Difícil": 0 };
    let timerEstimativa = null;

    document.addEventListener("DOMContentLoaded", () => {
        el("exercicioGerarFechar")?.addEventListener("click", fechar);
        el("exGerarCancelar")?.addEventListener("click", fechar);
        el("exGerarCancelar2")?.addEventListener("click", fechar);
        modal.addEventListener("click", (e) => { if (e.target === modal) fechar(); });
        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape" && modal.classList.contains("open")) fechar();
        });

        el("btnGerarComIA")?.addEventListener("click", gerarComIA);
        el("btnVoltarConfig")?.addEventListener("click", voltarConfig);
        el("btnSalvarExercicio")?.addEventListener("click", salvarExercicio);
        el("exGerarQtdMais")?.addEventListener("click", () => mudarQtd(+1));
        el("exGerarQtdMenos")?.addEventListener("click", () => mudarQtd(-1));

        // Chips de dificuldade (multi-seleção)
        el("exGerarDificuldades")?.addEventListener("click", (e) => {
            const btn = e.target.closest("[data-dificuldade]");
            if (btn) alternarDificuldade(btn);
        });

        // Chips de distribuição (modo)
        el("exGerarModo")?.addEventListener("click", (e) => {
            const btn = e.target.closest("[data-modo]");
            if (btn) definirModo(btn.dataset.modo);
        });

        // Steppers das linhas "quantas de cada"
        const linhas = el("exGerarQtdsDif");
        linhas?.addEventListener("click", (e) => {
            const btn = e.target.closest("[data-passo]");
            if (!btn) return;
            const linha = btn.closest(".ex-qtd-dif__linha");
            const input = linha.querySelector("input");
            let valor = parseInt(input.value, 10);
            if (isNaN(valor)) valor = 1;
            valor = Math.min(15, Math.max(1, valor + parseInt(btn.dataset.passo, 10)));
            input.value = String(valor);
            qtds[linha.dataset.dif] = valor;
            atualizarTotal();
        });
        linhas?.addEventListener("input", (e) => {
            const input = e.target.closest("input");
            if (!input) return;
            const linha = input.closest(".ex-qtd-dif__linha");
            const valor = parseInt(input.value, 10);
            qtds[linha.dataset.dif] = isNaN(valor) ? 0 : valor;
            atualizarTotal();
        });

        resetarFormulario();
    });

    /* ------------------------------------------------------------
       Abrir / fechar
       ------------------------------------------------------------ */
    function abrir(materia) {
        if (!materia) return;
        materiaId = materia.id;
        materiaNome = materia.nome;

        el("exercicioGerarMateriaId").value = materiaId;
        el("modalExercicioGerarTitulo").textContent = "Gerar exercícios para " + materia.nome;
        resetarFormulario();
        esconderMsg("msgExercicioGerar");
        esconderMsg("msgExercicioGerarPreview");

        mostrarEtapa(1);
        modal.classList.add("open");
        el("exGerarTitulo").focus();
    }

    /** Volta o form ao padrão: só "Médio", modo "por dificuldade", 5 questões. */
    function resetarFormulario() {
        pararEstimativa();
        el("exGerarTitulo").value = "";
        el("exGerarConteudo").value = "";
        el("exGerarDificuldades").querySelectorAll("[data-dificuldade]").forEach((b) => {
            b.setAttribute("aria-pressed", String(b.dataset.dificuldade === "Médio"));
        });
        qtds = { "Fácil": 0, "Médio": 5, "Difícil": 0 };
        el("exGerarQtd").value = "5";
        definirModo("fixo");
        atualizarLinhas();
        planoGerado = [];
        questoesGeradas = [];
    }

    function fechar() {
        modal.classList.remove("open");
        questoesGeradas = [];
        planoGerado = [];
        pararEstimativa();
    }

    function mostrarEtapa(num) {
        const etapa1 = el("etapaConfig");
        const etapa2 = el("etapaPreview");
        if (num === 1) {
            etapa1.hidden = false;
            etapa2.hidden = true;
        } else {
            etapa1.hidden = true;
            etapa2.hidden = false;
        }
    }

    function voltarConfig() {
        mostrarEtapa(1);
    }

    /* ------------------------------------------------------------
       Dificuldades (chips) + distribuição (modo)
       ------------------------------------------------------------ */
    function dificuldadesMarcadas() {
        return Array.from(el("exGerarDificuldades").querySelectorAll('[aria-pressed="true"]'))
            .map((b) => b.dataset.dificuldade);
    }

    function alternarDificuldade(btn) {
        const ativo = btn.getAttribute("aria-pressed") === "true";
        if (ativo && dificuldadesMarcadas().length === 1) return; // sempre pelo menos uma
        btn.setAttribute("aria-pressed", String(!ativo));
        atualizarLinhas();
    }

    function definirModo(novo) {
        if (novo !== "fixo" && novo !== "aleatorio") return;
        modo = novo;
        el("exGerarModo").querySelectorAll("[data-modo]").forEach((b) => {
            b.setAttribute("aria-pressed", String(b.dataset.modo === modo));
        });
        el("exGerarPorDificuldade").hidden = modo !== "fixo";
        el("exGerarTotalAleatorio").hidden = modo !== "aleatorio";
        atualizarTotal();
    }

    /** (Re)monta as linhas "quantas de cada" conforme os níveis marcados. */
    function atualizarLinhas() {
        const marcadas = dificuldadesMarcadas();
        DIFICULDADES.forEach((d) => { if (!marcadas.includes(d)) qtds[d] = 0; });
        marcadas.forEach((d) => { if (!(qtds[d] >= 1)) qtds[d] = 1; });

        const menos = '<svg class="ico" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M5 10h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
        const mais = '<svg class="ico" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 5v10M5 10h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';

        el("exGerarQtdsDif").innerHTML = marcadas.map((d) => `
            <div class="ex-qtd-dif__linha" data-dif="${d}">
                <span class="ex-qtd-dif__nome">${d}</span>
                <div class="qtd-stepper">
                    <button type="button" class="qtd-stepper__btn" data-passo="-1" aria-label="Diminuir questões de ${d}">${menos}</button>
                    <input type="number" min="1" max="15" value="${qtds[d]}" inputmode="numeric" aria-label="Quantidade de ${d}">
                    <button type="button" class="qtd-stepper__btn" data-passo="1" aria-label="Aumentar questões de ${d}">${mais}</button>
                </div>
            </div>`).join("");
        atualizarTotal();
    }

    function totalFixo() {
        return dificuldadesMarcadas().reduce((soma, d) => soma + (qtds[d] >= 1 ? qtds[d] : 0), 0);
    }

    function atualizarTotal() {
        const span = el("exGerarTotalDif");
        const total = modo === "fixo" ? totalFixo() : parseInt(el("exGerarQtd").value, 10) || 0;
        span.textContent = `Total: ${total} de 15 questões.`;
        span.classList.toggle("campo__dica--erro", total > 15);
    }

    /** Botões − / + do stepper de quantidade total (modo aleatório). */
    function mudarQtd(delta) {
        const input = el("exGerarQtd");
        if (!input) return;
        let valor = parseInt(input.value, 10);
        if (isNaN(valor)) {
            valor = 5;
        }
        input.value = Math.min(15, Math.max(1, valor + delta));
        atualizarTotal();
    }

    /** Monta o [{dificuldade, qtd}] que vai para o backend. */
    function montarPlano() {
        const marcadas = dificuldadesMarcadas();
        if (modo === "fixo") {
            return marcadas
                .map((d) => ({ dificuldade: d, qtd: qtds[d] || 0 }))
                .filter((p) => p.qtd > 0);
        }
        const total = parseInt(el("exGerarQtd").value, 10);
        return sortearContagens(marcadas, total);
    }

    /** Divide o total entre os níveis marcados: 1 para cada quando
        cabe, o resto sorteado; se não cabe, só um subconjunto recebe. */
    function sortearContagens(marcadas, total) {
        const cont = {};
        marcadas.forEach((d) => { cont[d] = 0; });

        if (total >= marcadas.length) {
            marcadas.forEach((d) => { cont[d] = 1; });
            let sobra = total - marcadas.length;
            while (sobra > 0) {
                const d = marcadas[Math.floor(Math.random() * marcadas.length)];
                cont[d] += 1;
                sobra -= 1;
            }
        } else {
            const baralhado = marcadas.slice().sort(() => Math.random() - 0.5);
            baralhado.slice(0, total).forEach((d) => { cont[d] = 1; });
        }

        return marcadas
            .map((d) => ({ dificuldade: d, qtd: cont[d] }))
            .filter((p) => p.qtd > 0);
    }

    /* ------------------------------------------------------------
       Gerar com IA
       ------------------------------------------------------------ */
    /** Estimativa simples: ~8s + 4s por questão, contando para baixo
        em tempo real enquanto a IA gera. */
    function iniciarEstimativa(plano) {
        pararEstimativa();
        const total = plano.reduce((soma, p) => soma + p.qtd, 0);
        const estimativa = 8 + 4 * total;
        const inicio = Date.now();
        const caixa = el("exGerarEstimativa");

        const tick = () => {
            const restante = estimativa - Math.floor((Date.now() - inicio) / 1000);
            caixa.textContent = restante > 0
                ? `Tempo estimado: ~${restante}s`
                : "Tempo estimado: quase lá…";
            caixa.hidden = false;
        };
        tick();
        timerEstimativa = setInterval(tick, 1000);
    }

    function pararEstimativa() {
        if (timerEstimativa) {
            clearInterval(timerEstimativa);
            timerEstimativa = null;
        }
        const caixa = el("exGerarEstimativa");
        if (caixa) {
            caixa.hidden = true;
            caixa.textContent = "";
        }
    }

    async function gerarComIA() {
        const titulo = el("exGerarTitulo").value.trim();
        const conteudo = el("exGerarConteudo").value.trim();

        if (!conteudo) {
            msg("Descreva o conteúdo específico.", "erro", "msgExercicioGerar");
            return;
        }

        const plano = montarPlano();

        if (modo === "fixo") {
            const marcadas = dificuldadesMarcadas();
            const semQtd = marcadas.find((d) => !(qtds[d] >= 1));
            if (semQtd) {
                msg(`Informe quantas questões de ${semQtd}.`, "erro", "msgExercicioGerar");
                return;
            }
            if (totalFixo() > 15) {
                msg("Máximo de 15 questões por vez.", "erro", "msgExercicioGerar");
                return;
            }
        } else {
            const qtdDigitada = el("exGerarQtd").value.trim();
            if (qtdDigitada === "" || isNaN(parseInt(qtdDigitada, 10))) {
                msg("Informe a quantidade de questões.", "erro", "msgExercicioGerar");
                return;
            }
            if (parseInt(qtdDigitada, 10) > 15) {
                msg("Máximo de 15 questões por vez.", "erro", "msgExercicioGerar");
                return;
            }
            if (parseInt(qtdDigitada, 10) < 1) {
                msg("Escolha pelo menos 1 questão.", "erro", "msgExercicioGerar");
                return;
            }
        }

        if (plano.length === 0) {
            msg("Escolha pelo menos uma dificuldade.", "erro", "msgExercicioGerar");
            return;
        }

        const btn = el("btnGerarComIA");
        const htmlOriginal = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner" style="width:18px;height:18px;border:2px solid currentColor;border-right-color:transparent;border-radius:50%;animation:spin 0.8s linear infinite;display:inline-block;margin-right:8px;vertical-align:middle;"></span> Gerando...';
        iniciarEstimativa(plano);

        try {
            const dados = new FormData();
            dados.append("materia", materiaNome); // usa o nome da matéria base
            dados.append("conteudo", conteudo);
            dados.append("plano", JSON.stringify(plano));

            const resp = await fetch(`${BACKEND}/gerar_exercicios.php`, { method: "POST", body: dados });
            const json = await resp.json();

            if (!json.ok || !Array.isArray(json.questoes) || json.questoes.length === 0) {
                throw new Error(json.msg || "Não foi possível gerar as questões.");
            }

            questoesGeradas = json.questoes.map((q, i) => ({
                ...q,
                numero: i + 1
            }));
            planoGerado = plano;

            renderPreview(titulo || "Exercícios gerados por IA", plano);
            mostrarEtapa(2);

        } catch (err) {
            msg(err.message, "erro", "msgExercicioGerar");
        } finally {
            pararEstimativa();
            btn.disabled = false;
            btn.innerHTML = htmlOriginal;
        }
    }

    /** "1 fácil, 5 médio, 1 difícil" a partir das questões geradas;
        nível único vira só o nome; sem rótulo (backend antigo) cai no plano. */
    function resumoDificuldades(plano) {
        const contagens = {};
        questoesGeradas.forEach((q) => {
            if (DIFICULDADES.includes(q.dificuldade)) {
                contagens[q.dificuldade] = (contagens[q.dificuldade] || 0) + 1;
            }
        });
        const ativos = DIFICULDADES.filter((d) => contagens[d] > 0);
        if (ativos.length === 1) return ativos[0];
        if (ativos.length > 1) {
            return ativos.map((d) => `${contagens[d]} ${d.toLowerCase()}`).join(", ");
        }
        return plano.length === 1
            ? plano[0].dificuldade
            : plano.map((p) => `${p.qtd} ${p.dificuldade.toLowerCase()}`).join(", ");
    }

    function renderPreview(titulo, plano) {
        // Resumo do que a IA ENTREGOU — o preview mostra a verdade
        // antes de salvar (se divergir do pedido, o usuário vê na hora).
        el("previewTitulo").textContent = titulo;
        el("previewMeta").textContent = `${questoesGeradas.length} questões · ${resumoDificuldades(plano)} · ${materiaNome}`;

        const container = el("exGerarQuestoes");
        container.innerHTML = "";

        questoesGeradas.forEach((q) => {
            const div = document.createElement("div");
            div.className = "ex-gerar-questao";
            div.style.cssText = "margin-bottom:20px;padding:16px;background:rgba(255,255,255,.03);border:1px solid rgba(165,65,255,.1);border-radius:var(--radius-lg);";

            let altsHtml = "";
            q.alts.forEach((alt, ai) => {
                const letra = String.fromCharCode(65 + ai);
                altsHtml += `
                    <div class="ex-gerar-alt" style="
                        display:flex;align-items:center;gap:10px;
                        padding:10px 12px;margin:6px 0;
                        border:1px solid rgba(255,255,255,.1);
                        border-radius:var(--radius-sm);
                        background:transparent;
                        color:var(--text);
                    ">
                        <span style="width:22px;height:22px;flex-shrink:0;display:flex;align-items:center;justify-content:center;border:1px solid currentColor;border-radius:50%;font-size:.75rem;font-weight:700;">${letra}</span>
                        <span>${alt}</span>
                    </div>`;
            });

            const badgeDif = q.dificuldade
                ? `<span class="ex-gerar-questao__dif">${q.dificuldade}</span>`
                : "";

            div.innerHTML = `
                <div class="ex-gerar-questao__topo">
                    <span>Questão ${q.numero}</span>
                    ${badgeDif}
                </div>
                <p style="margin:0 0 12px;line-height:1.5;">${q.enunciado}</p>
                <div class="ex-gerar-alts">${altsHtml}</div>
            `;
            container.appendChild(div);
        });
    }

    /* ------------------------------------------------------------
       Salvar exercício
       ------------------------------------------------------------ */
    async function salvarExercicio() {
        const titulo = el("exGerarTitulo").value.trim() || "Exercícios gerados por IA";
        const dificuldade = planoGerado.length === 1 ? planoGerado[0].dificuldade : "Misto";

        if (questoesGeradas.length === 0) {
            msg("Nenhuma questão para salvar.", "erro", "msgExercicioGerarPreview");
            return;
        }

        const btn = el("btnSalvarExercicio");
        const htmlOriginal = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner" style="width:18px;height:18px;border:2px solid currentColor;border-right-color:transparent;border-radius:50%;animation:spin 0.8s linear infinite;display:inline-block;margin-right:8px;vertical-align:middle;"></span> Salvando...';

        try {
            const conteudo = JSON.stringify({
                questoes: questoesGeradas,
                materiaBase: materiaNome
            });

            const resp = await fetch(`${BACKEND}/exercicios_salvar.php`, {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({
                    acao: "criar",
                    materia_id: materiaId,
                    titulo: titulo,
                    conteudo: conteudo,
                    dificuldade: dificuldade
                })
            });
            const json = await resp.json();

            if (!json.ok) throw new Error(json.msg || "Erro ao salvar");

            // Dispara evento para recarregar lista
            document.dispatchEvent(new CustomEvent("exercicio:salvo", { detail: json.exercicio }));

            msg("Exercício salvo com sucesso!", "sucesso", "msgExercicioGerarPreview");
            setTimeout(() => {
                fechar();
            }, 1200);

        } catch (err) {
            msg(err.message, "erro", "msgExercicioGerarPreview");
        } finally {
            btn.disabled = false;
            btn.innerHTML = htmlOriginal;
        }
    }

    function msg(texto, tipo, containerId) {
        const m = el(containerId);
        if (!m) return;
        m.textContent = texto;
        m.className = "msg msg--" + tipo;
        m.hidden = false;
    }

    function esconderMsg(containerId) {
        const m = el(containerId);
        if (m) m.hidden = true;
    }

    window.KosmosExercicioGerarForm = { abrir, fechar };
})();
