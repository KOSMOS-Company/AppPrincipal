/* ============================================================
   KOSMOS — exercicio-materia-form.js
   O modal de criar/personalizar/excluir matéria de exercícios,
   usado em dois lugares: na estante (exercicios.php) e dentro
   de uma matéria (exercicio_materia.php).
   O HTML vem de partes/modal-exercicio-materia.php.

   Quem usa chama KosmosExercicioMateriaForm.abrir(materia | null) e escuta:
     document "exercicioMateria:salvo"   -> detail = { materia, novo }
     document "exercicioMateria:apagado" -> detail = { id }

   Duas coisas que valem explicação:

   1. PRÉVIA — a escolha de cor e ícone é mostrada num cartão de
      mentira que muda junto com os campos. Escolher "verde" numa
      lista de nomes não diz nada; ver o cartão ficar verde diz.
   ============================================================ */
(() => {
    "use strict";

    const BACKEND = "../../../Backend/php";
    const modal = document.getElementById("modalExercicioMateria");
    if (!modal) return;               // página sem o modal

    const el = (id) => document.getElementById(id);

    let cor   = "roxo";
    let icone = "";

    document.addEventListener("DOMContentLoaded", () => {
        el("exercicioMateriaFechar")?.addEventListener("click", fechar);
        el("exercicioMateriaCancelar")?.addEventListener("click", fechar);
        modal.addEventListener("click", (e) => { if (e.target === modal) fechar(); });
        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape" && modal.classList.contains("open")) fechar();
        });

        el("formExercicioMateria")?.addEventListener("submit", salvar);
        el("exercicioMateriaApagar")?.addEventListener("click", apagar);

        // prévia ao vivo
        el("exercicioMateriaNome")?.addEventListener("input", pintarPrevia);
        el("exercicioMateriaDescricao")?.addEventListener("input", pintarPrevia);
        el("exercicioMateriaMateria")?.addEventListener("input", pintarPrevia);
        el("exercicioMateriaMateria")?.addEventListener("change", pintarPrevia);

        el("exercicioMateriaCores")?.addEventListener("click", (e) => {
            const botao = e.target.closest("[data-cor]");
            if (!botao) return;
            cor = botao.dataset.cor;
            marcarEscolhido(el("exercicioMateriaCores"), botao);
            pintarPrevia();
        });

        // grade de ícones guardada: abre só quando pede
        el("exercicioMateriaIconesAbrir")?.addEventListener("click", alternarIcones);

        el("exercicioMateriaIcones")?.addEventListener("click", (e) => {
            const botao = e.target.closest("[data-icone]");
            if (!botao) return;
            icone = botao.dataset.icone;
            marcarEscolhido(el("exercicioMateriaIcones"), botao);
            pintarPrevia();
        });

        // setas nos grupos de escolha: é o que um radiogroup deve fazer
        [el("exercicioMateriaCores"), el("exercicioMateriaIcones")].forEach(ligarSetas);
    });

    /* ------------------------------------------------------------
       Abrir / fechar
       ------------------------------------------------------------ */

    /* materia = null -> novo; com materia -> edita */
    function abrir(materia) {
        esconderMsg();

        const nome    = el("exercicioMateriaNome");
        const materiaSelect = el("exercicioMateriaMateria");
        const desc    = el("exercicioMateriaDescricao");

        if (materia) {
            el("modalExercicioMateriaTitulo").textContent = "Personalizar matéria";
            el("exercicioMateriaSalvar").textContent = "Salvar";
            el("exercicioMateriaId").value = materia.id;
            nome.value    = materia.nome;
            materiaSelect.value = materia.materia;
            desc.value    = materia.descricao || "";
            cor           = materia.cor || "roxo";
            icone         = materia.icone || "";
            el("exercicioMateriaApagar").hidden = false;
        } else {
            el("modalExercicioMateriaTitulo").textContent = "Nova matéria";
            el("exercicioMateriaSalvar").textContent = "Criar matéria";
            el("exercicioMateriaId").value = "";
            nome.value = "";
            materiaSelect.value = "";   // vazio: quem cria escolhe a matéria
            desc.value = "";
            cor        = "roxo";
            icone      = "";
            el("exercicioMateriaApagar").hidden = true;
        }

        marcarEscolhido(el("exercicioMateriaCores"), el("exercicioMateriaCores")?.querySelector(`[data-cor="${cor}"]`));
        marcarEscolhido(el("exercicioMateriaIcones"), el("exercicioMateriaIcones")?.querySelector(`[data-icone="${cssEscape(icone)}"]`));
        guardarLayoutIcones();   // modal abre com a grade guardada
        pintarPrevia();

        modal.classList.add("open");
        nome.focus();
    }

    function fechar() {
        modal.classList.remove("open");
        esconderMsg();
    }

    /* ------------------------------------------------------------
       Escolhas (cor e ícone)
       ------------------------------------------------------------ */

    /* A grade de ícones fica guardada atrás do botão: a lista
       inteira de uma vez poluem o modal — quem quer um, abre. */
    function alternarIcones() {
        const grade = el("exercicioMateriaIcones");
        const botao = el("exercicioMateriaIconesAbrir");
        if (!grade || !botao) return;
        const abrindo = grade.hidden;
        grade.hidden = !abrindo;
        botao.setAttribute("aria-expanded", String(abrindo));
        botao.textContent = abrindo ? "Fechar ícones" : "Escolher ícone";
    }

    /** Toda vez que o modal abre, a grade volta a ficar guardada. */
    function guardarLayoutIcones() {
        const grade = el("exercicioMateriaIcones");
        const botao = el("exercicioMateriaIconesAbrir");
        if (grade) grade.hidden = true;
        if (botao) {
            botao.setAttribute("aria-expanded", "false");
            botao.textContent = "Escolher ícone";
        }
    }

    function marcarEscolhido(grupo, botao) {
        if (!grupo) return;
        grupo.querySelectorAll("[role='radio']").forEach((b) => {
            const escolhido = b === botao;
            b.classList.toggle("escolhido", escolhido);
            b.setAttribute("aria-checked", escolhido ? "true" : "false");
            // só o escolhido entra na navegação por Tab (padrão de radiogroup)
            b.tabIndex = escolhido ? 0 : -1;
        });
    }

    /** Setas andam entre as opções, como num grupo de rádio de verdade. */
    function ligarSetas(grupo) {
        if (!grupo) return;

        grupo.addEventListener("keydown", (e) => {
            if (!["ArrowRight", "ArrowLeft", "ArrowDown", "ArrowUp"].includes(e.key)) return;

            const opcoes = [...grupo.querySelectorAll("[role='radio']")];
            const i = opcoes.indexOf(document.activeElement);
            if (i < 0) return;

            e.preventDefault();
            const passo = (e.key === "ArrowRight" || e.key === "ArrowDown") ? 1 : -1;
            const alvo = opcoes[(i + passo + opcoes.length) % opcoes.length];
            alvo.focus();
            alvo.click();
        });
    }

    /* ------------------------------------------------------------
       Prévia
       ------------------------------------------------------------ */
    function pintarPrevia() {
        const previa = el("exercicioMateriaPrevia");
        if (!previa) return;

        previa.className = `cad-previa exercicio-materia-card--${cor}`;

        el("exercicioMateriaPreviaNome").textContent = el("exercicioMateriaNome").value.trim() || "Nome da matéria";
        el("exercicioMateriaPreviaMateria").textContent = el("exercicioMateriaMateria").value || "Matéria";

        const desc = el("exercicioMateriaDescricao").value.trim();
        el("exercicioMateriaPreviaDesc").textContent = desc;
        el("exercicioMateriaPreviaDesc").hidden = desc === "";

        const ic = el("exercicioMateriaPreviaIcone");
        ic.textContent = icone;
        ic.hidden = icone === "";
    }

    /* ------------------------------------------------------------
       Salvar / apagar
       ------------------------------------------------------------ */
    async function salvar(e) {
        e.preventDefault();

        const btn  = el("exercicioMateriaSalvar");
        const id   = el("exercicioMateriaId").value;
        const nome = el("exercicioMateriaNome").value.trim();

        if (!nome) { msg("Dê um nome à matéria.", "erro"); return; }

        const dados = new FormData();
        dados.append("acao", id ? "editar" : "criar");
        if (id) dados.append("id", id);
        dados.append("nome", nome);
        dados.append("materia", el("exercicioMateriaMateria").value);
        dados.append("descricao", el("exercicioMateriaDescricao").value.trim());
        dados.append("cor", cor);
        dados.append("icone", icone);

        const rotulo = btn.textContent;
        btn.disabled = true;
        btn.textContent = "Salvando…";
        try {
            const resp = await fetch(`${BACKEND}/exercicios_materia.php`, { method: "POST", body: dados });
            const json = await resp.json();

            if (!json.ok) { msg(json.msg || "Não foi possível salvar.", "erro"); return; }

            document.dispatchEvent(new CustomEvent("exercicioMateria:salvo", {
                detail: { materia: json.materia, novo: !id },
            }));
            fechar();
        } catch (err) {
            msg("Não foi possível falar com o servidor.", "erro");
        } finally {
            btn.disabled = false;
            btn.textContent = rotulo;
        }
    }

    async function apagar() {
        const id = Number(el("exercicioMateriaId").value);
        if (!id) return;

        const nome = el("exercicioMateriaNome").value.trim();
        // confirmar() é compartilhada (dashboard.js) e usa o modal de
        // partes/modal-confirma.php
        const ok = await confirmar({
            titulo: "Excluir esta matéria?",
            texto: `"${nome}" será excluída.`,
            botao: "Excluir",
            perigo: true,
        });
        if (!ok) return;

        const btn = el("exercicioMateriaApagar");
        btn.disabled = true;
        btn.textContent = "Excluindo…";
        try {
            const dados = new FormData();
            dados.append("acao", "excluir");
            dados.append("id", id);

            const resp = await fetch(`${BACKEND}/exercicios_materia.php`, { method: "POST", body: dados });
            const json = await resp.json();

            if (!json.ok) { msg(json.msg || "Não foi possível excluir.", "erro"); return; }

            document.dispatchEvent(new CustomEvent("exercicioMateria:apagado", {
                detail: { id, msg: json.msg },
            }));
            fechar();
        } catch (err) {
            msg("Não foi possível falar com o servidor.", "erro");
        } finally {
            btn.disabled = false;
            btn.textContent = "Excluir";
        }
    }

    function msg(texto, tipo) {
        const m = el("msgExercicioMateria");
        if (!m) return;
        m.textContent = texto;
        m.className = "msg msg--" + tipo;
        m.hidden = false;
    }

    function esconderMsg() {
        const m = el("msgExercicioMateria");
        if (m) m.hidden = true;
    }

    /* O ícone é emoji e entra num seletor CSS: escapamos as aspas
       para não quebrar o querySelector. */
    function cssEscape(valor) {
        return String(valor).replace(/["\\]/g, "\\$&");
    }

    window.KosmosExercicioMateriaForm = { abrir, fechar };
})();