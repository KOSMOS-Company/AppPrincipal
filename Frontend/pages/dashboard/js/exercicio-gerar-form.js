/* ============================================================
   KOSMOS — exercicio-gerar-form.js
   Modal de gerar/salvar exercícios com IA.
   Usado em exercicio_materia.php.
   ============================================================ */
(() => {
    "use strict";

    const BACKEND = "../../../Backend/php";
    const modal = document.getElementById("modalExercicioGerar");
    if (!modal) return;

    const el = (id) => document.getElementById(id);

    let materiaId = 0;
    let materiaNome = "";
    let questoesGeradas = [];

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
        el("exGerarTitulo").value = "";
        el("exGerarConteudo").value = "";
        el("exGerarDificuldade").value = "Médio";
        el("exGerarQtd").value = "5";
        esconderMsg("msgExercicioGerar");
        esconderMsg("msgExercicioGerarPreview");

        mostrarEtapa(1);
        modal.classList.add("open");
        el("exGerarTitulo").focus();
    }

    function fechar() {
        modal.classList.remove("open");
        questoesGeradas = [];
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

    /** Botões − / + do stepper de quantidade. Respeita o 1..15 do input. */
    function mudarQtd(delta) {
        const input = el("exGerarQtd");
        if (!input) return;
        let valor = parseInt(input.value, 10);
        if (isNaN(valor)) {
            valor = 5;
        }
        input.value = Math.min(15, Math.max(1, valor + delta));
    }

    /* ------------------------------------------------------------
       Gerar com IA
       ------------------------------------------------------------ */
    async function gerarComIA() {
        const titulo = el("exGerarTitulo").value.trim();
        const conteudo = el("exGerarConteudo").value.trim();
        const dificuldade = el("exGerarDificuldade").value;
        const qtd = parseInt(el("exGerarQtd").value, 10);

        if (!conteudo) {
            msg("Descreva o conteúdo específico.", "erro", "msgExercicioGerar");
            return;
        }

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

        const btn = el("btnGerarComIA");
        const htmlOriginal = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner" style="width:18px;height:18px;border:2px solid currentColor;border-right-color:transparent;border-radius:50%;animation:spin 0.8s linear infinite;display:inline-block;margin-right:8px;vertical-align:middle;"></span> Gerando...';

        try {
            const dados = new FormData();
            dados.append("materia", materiaNome); // usa o nome da matéria base
            dados.append("conteudo", conteudo);
            dados.append("dificuldade", dificuldade);
            dados.append("qtd", String(qtd));

            const resp = await fetch(`${BACKEND}/gerar_exercicios.php`, { method: "POST", body: dados });
            const json = await resp.json();

            if (!json.ok || !Array.isArray(json.questoes) || json.questoes.length === 0) {
                throw new Error(json.msg || "Não foi possível gerar as questões.");
            }

            questoesGeradas = json.questoes.map((q, i) => ({
                ...q,
                numero: i + 1
            }));

            renderPreview(titulo || "Exercícios gerados por IA", dificuldade, qtd);
            mostrarEtapa(2);

        } catch (err) {
            msg(err.message, "erro", "msgExercicioGerar");
        } finally {
            btn.disabled = false;
            btn.innerHTML = htmlOriginal;
        }
    }

    function renderPreview(titulo, dificuldade, qtd) {
        el("previewTitulo").textContent = titulo;
        el("previewMeta").textContent = `${qtd} questões · ${dificuldade} · ${materiaNome}`;

        const container = el("exGerarQuestoes");
        container.innerHTML = "";

        questoesGeradas.forEach((q, i) => {
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

            div.innerHTML = `
                <div style="font-weight:600;margin-bottom:10px;color:var(--accent-lt);">Questão ${q.numero}</div>
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
        const dificuldade = el("exGerarDificuldade").value;

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