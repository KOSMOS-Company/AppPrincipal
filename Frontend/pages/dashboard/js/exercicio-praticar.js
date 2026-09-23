/* ============================================================
   KOSMOS — exercicio-praticar.js
   Modal de praticar um exercício salvo (quiz estilo ENEM).
   Usado em exercicio_materia.php.
   O HTML vem de partes/modal-exercicio-praticar.php.

   O exercício é buscado no listar (?id=X) com as questões já
   parseadas; o quiz roda inteiro no cliente. Uma questão por vez,
   sem mostrar a resposta antes da hora. Quem abre chama:
     window.KosmosExercicioPraticar.abrir(exercicioId)
   ============================================================ */
(() => {
    "use strict";

    const BACKEND = "../../../Backend/php";
    const modal = document.getElementById("modalPraticarExercicio");
    if (!modal) return;

    const el = (id) => document.getElementById(id);

    let exercicio = null;
    let questoes  = [];
    let atual     = 0;
    let acertos   = 0;
    let respostas = [];

    document.addEventListener("DOMContentLoaded", () => {
        el("praticarExercicioFechar")?.addEventListener("click", fechar);
        el("praticarFecharQuestao")?.addEventListener("click", fechar);
        el("praticarFecharResultado")?.addEventListener("click", fechar);
        el("praticarDicaBtn")?.addEventListener("click", revelarDica);
        el("praticarProxima")?.addEventListener("click", proxima);
        el("praticarDeNovo")?.addEventListener("click", praticarDeNovo);
        modal.addEventListener("click", (e) => { if (e.target === modal) fechar(); });
        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape" && modal.classList.contains("open")) fechar();
        });
    });

    /* ------------------------------------------------------------
       Abrir / fechar
       ------------------------------------------------------------ */
    async function abrir(exercicioId) {
        if (!exercicioId) return;
        resetar();
        mostrarEstado("Carregando questões…");
        modal.classList.add("open");

        try {
            const resp = await fetch(`${BACKEND}/exercicios_listar.php?id=${Number(exercicioId)}`);
            const json = await resp.json();

            if (!json.ok || !json.exercicio) {
                throw new Error(json.msg || "Não foi possível carregar o exercício.");
            }

            exercicio = json.exercicio;
            questoes  = Array.isArray(json.exercicio.questoes) ? json.exercicio.questoes : [];

            if (questoes.length === 0) {
                mostrarEstado("Este exercício não tem questões para praticar.");
                return;
            }

            iniciarQuiz();
        } catch (err) {
            mostrarEstado(err.message);
        }
    }

    function fechar() {
        modal.classList.remove("open");
        resetar();
    }

    function resetar() {
        exercicio = null;
        questoes  = [];
        atual     = 0;
        acertos   = 0;
        respostas = [];

        el("praticarEstado").hidden = false;
        el("praticarEstado").textContent = "";
        el("praticarQuestao").hidden = true;
        el("praticarResultado").hidden = true;
        esconderMsg();
    }

    /* ------------------------------------------------------------
       Quiz
       ------------------------------------------------------------ */
    function iniciarQuiz() {
        el("modalPraticarExercicioTitulo").textContent = "Praticar — " + (exercicio.titulo || "Exercício");
        el("praticarEstado").hidden = true;
        el("praticarResultado").hidden = true;
        el("praticarQuestao").hidden = false;

        atual     = 0;
        acertos   = 0;
        respostas = questoes.map(() => ({ escolhida: -1, bloqueada: false }));

        renderQuestao();
    }

    function renderQuestao() {
        const q = questoes[atual];

        el("praticarProgresso").textContent = `Questão ${atual + 1} de ${questoes.length}`;
        el("praticarEnunciado").textContent = q.enunciado || "";

        // dica: só aparece quando a questão veio com uma
        const dica = String(q.dica || "").trim();
        const dicaTexto = el("praticarDica");
        dicaTexto.textContent = dica;
        dicaTexto.classList.remove("aberta");
        el("praticarDicaBloco").hidden = dica === "";
        el("praticarDicaBtn").hidden = dica === "";

        // alternativas
        const alts = el("praticarAlts");
        alts.innerHTML = "";
        alts.classList.remove("bloqueado");
        (q.alts || []).forEach((alt, i) => {
            const letra = String.fromCharCode(65 + i);
            const btn = document.createElement("button");
            btn.type = "button";
            btn.className = "ex-praticar-alt";
            btn.dataset.indice = String(i);
            btn.innerHTML = `
                <span class="ex-praticar-alt__letra">${letra}</span>
                <span class="ex-praticar-alt__texto">${escapeHtml(alt)}</span>
            `;
            btn.addEventListener("click", () => responder(i, btn));
            alts.appendChild(btn);
        });

        esconderMsg();
        el("praticarProxima").hidden = true;
    }

    function responder(indice, btn) {
        const r = respostas[atual];
        if (!r || r.bloqueada) return;

        const q        = questoes[atual];
        r.escolhida    = indice;
        r.bloqueada    = true;

        const altsContainer = el("praticarAlts");
        altsContainer.classList.add("bloqueado");
        const botoes = altsContainer.querySelectorAll(".ex-praticar-alt");

        btn.classList.add("escolhida");
        if (indice === q.correta) {
            acertos++;
            btn.classList.add("correta");
            msg("Certo! 🎉", "sucesso");
        } else {
            btn.classList.add("errada");
            botoes[q.correta]?.classList.add("escolhida", "correta");
            msg(`Não foi dessa vez — a certa era a ${letraDe(q.correta)}.`, "erro");
        }

        const ultima = atual === questoes.length - 1;
        const prox = el("praticarProxima");
        prox.textContent = ultima ? "Ver resultado" : "Próxima questão →";
        prox.hidden = false;
    }

    function proxima() {
        const r = respostas[atual];
        if (!r || !r.bloqueada) return;

        if (atual === questoes.length - 1) {
            mostrarResultado();
        } else {
            atual++;
            renderQuestao();
        }
    }

    /* ------------------------------------------------------------
       Dica
       ------------------------------------------------------------ */
    function revelarDica() {
        const dicaTexto = el("praticarDica");
        if (!dicaTexto.textContent.trim()) return;

        dicaTexto.classList.add("aberta");
        el("praticarDicaBtn").hidden = true;
    }

    /* ------------------------------------------------------------
       Resultado
       ------------------------------------------------------------ */
    function mostrarResultado() {
        el("praticarQuestao").hidden = true;
        el("praticarResultado").hidden = false;

        const total = questoes.length;
        const frac  = total > 0 ? acertos / total : 0;

        el("praticarResultadoTitulo").textContent = `Você acertou ${acertos} de ${total}`;
        el("praticarResultadoBadge").textContent =
            frac === 1            ? "🏆" :
            frac >= 0.7           ? "🎉" :
            frac >= 0.5           ? "💪" : "📚";

        const resumo = el("praticarResumo");
        resumo.innerHTML = "";

        questoes.forEach((q, i) => {
            const r = respostas[i];
            const certa = r && r.escolhida === q.correta;

            const item = document.createElement("div");
            item.className = "ex-praticar-resumo-item" + (certa ? " certo" : " errado");

            const detalhe = certa
                ? "Resposta certa"
                : `Resposta certa: ${letraDe(q.correta)} · sua: ${letraDe(r?.escolhida)}`;

            item.innerHTML = `
                <span class="ex-praticar-resumo__status">${certa ? "✓" : "✗"}</span>
                <span class="ex-praticar-resumo__q">Questão ${i + 1}</span>
                <span class="ex-praticar-resumo__resp">${detalhe}</span>
            `;
            resumo.appendChild(item);
        });
    }

    function praticarDeNovo() {
        iniciarQuiz();
    }

    /* ------------------------------------------------------------
       Utilitários
       ------------------------------------------------------------ */
    function mostrarEstado(texto) {
        el("praticarEstado").hidden = false;
        el("praticarEstado").textContent = texto;
        el("praticarQuestao").hidden = true;
        el("praticarResultado").hidden = true;
    }

    function letraDe(indice) {
        return indice >= 0 ? String.fromCharCode(65 + indice) : "—";
    }

    function escapeHtml(str) {
        const map = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" };
        return String(str).replace(/[&<>"']/g, (c) => map[c]);
    }

    function msg(texto, tipo) {
        const m = el("praticarFeedback");
        if (!m) return;
        m.textContent = texto;
        m.className = "msg msg--" + tipo;
        m.hidden = false;
    }

    function esconderMsg() {
        const m = el("praticarFeedback");
        if (m) m.hidden = true;
    }

    window.KosmosExercicioPraticar = { abrir, fechar };
})();