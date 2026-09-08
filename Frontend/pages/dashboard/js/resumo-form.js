/* ============================================================
   KOSMOS — resumo-form.js
   O editor de resumo (o modal) usado em dois lugares: na lista
   (resumos.php) e na prévia (resumo.php). O HTML do modal vem de
   partes/modal-resumo.php.

   Quem usa chama KosmosResumoForm.abrir(resumo | null) e escuta:
     document "resumo:salvo"   -> detail = { resumo, novo }
     document "resumo:apagado" -> detail = { id }

   Assim a lista atualiza o grid e a prévia atualiza o texto, cada
   uma do seu jeito, sem duplicar a lógica de salvar/apagar.
   ============================================================ */
(() => {
    "use strict";

    const BACKEND = "../../../Backend/php";
    const modal = document.getElementById("modal");
    if (!modal) return;               // página sem o editor

    const el = (id) => document.getElementById(id);

    document.addEventListener("DOMContentLoaded", () => {
        el("fechar")?.addEventListener("click", fechar);
        el("cancelar")?.addEventListener("click", fechar);
        modal.addEventListener("click", (e) => { if (e.target === modal) fechar(); });
        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape" && modal.classList.contains("open")) fechar();
        });

        el("formNovo")?.addEventListener("submit", salvar);
        el("btnApagar")?.addEventListener("click", apagar);

        const texto = el("conteudo");
        texto?.addEventListener("input", () => {
            el("contador").textContent = texto.value.length;
        });
    });

    /* resumo = null -> novo; com resumo -> edita */
    function abrir(resumo) {
        esconderMsg();

        const titulo = el("titulo");
        const materia = el("materia");
        const texto = el("conteudo");

        if (resumo) {
            el("modalTitulo").textContent = "Editar resumo";
            el("resumoId").value = resumo.id;
            titulo.value = resumo.titulo;
            materia.value = resumo.materia;
            texto.value = resumo.corpo || "";
            el("btnApagar").hidden = false;
        } else {
            el("modalTitulo").textContent = "Novo resumo";
            el("resumoId").value = "";
            titulo.value = "";
            texto.value = "";
            el("btnApagar").hidden = true;

            // começa na matéria favorita, se houver alguma marcada na Conta
            const favorita = materia.querySelector("option[data-favorita]");
            if (favorita) materia.value = favorita.value;
        }

        el("contador").textContent = texto.value.length;
        modal.classList.add("open");
        titulo.focus();
    }

    function fechar() {
        modal.classList.remove("open");
        esconderMsg();
    }

    async function salvar(e) {
        e.preventDefault();

        const btn = el("btnSalvar");
        const id = el("resumoId").value;
        const titulo = el("titulo").value.trim();
        const materia = el("materia").value;
        const corpo = el("conteudo").value.trim();

        if (!titulo) { msg("Dê um título ao resumo.", "erro"); return; }
        if (!corpo)  { msg("Escreva o resumo antes de salvar.", "erro"); return; }

        const dados = new FormData();
        if (id) dados.append("id", id);
        dados.append("titulo", titulo);
        dados.append("materia", materia);
        dados.append("corpo", corpo);

        btn.disabled = true;
        btn.textContent = "Salvando…";
        try {
            const resp = await fetch(`${BACKEND}/resumos_salvar.php`, { method: "POST", body: dados });
            const json = await resp.json();

            if (!json.ok) { msg(json.msg || "Não foi possível salvar.", "erro"); return; }

            document.dispatchEvent(new CustomEvent("resumo:salvo", {
                detail: { resumo: json.resumo, novo: !id },
            }));
            fechar();
        } catch (err) {
            msg("Não foi possível falar com o servidor.", "erro");
        } finally {
            btn.disabled = false;
            btn.textContent = "Salvar";
        }
    }

    async function apagar() {
        const id = Number(el("resumoId").value);
        if (!id) return;

        const titulo = el("titulo").value.trim();
        // confirmar() é compartilhada (dashboard.js) e usa o modal de
        // partes/modal-confirma.php
        const ok = await confirmar({
            titulo: "Apagar este resumo?",
            texto: `"${titulo}" será apagado da sua conta. Não tem como desfazer.`,
            botao: "Apagar",
            perigo: true,
        });
        if (!ok) return;

        const btn = el("btnApagar");
        btn.disabled = true;
        btn.textContent = "Apagando…";
        try {
            const dados = new FormData();
            dados.append("id", id);
            const resp = await fetch(`${BACKEND}/resumos_excluir.php`, { method: "POST", body: dados });
            const json = await resp.json();

            if (!json.ok) { msg(json.msg || "Não foi possível apagar.", "erro"); return; }

            document.dispatchEvent(new CustomEvent("resumo:apagado", { detail: { id } }));
            fechar();
        } catch (err) {
            msg("Não foi possível falar com o servidor.", "erro");
        } finally {
            btn.disabled = false;
            btn.textContent = "Apagar";
        }
    }

    function msg(texto, tipo) {
        const m = el("msgResumo");
        if (!m) return;
        m.textContent = texto;
        m.className = "msg msg--" + tipo;
        m.hidden = false;
    }

    function esconderMsg() {
        const m = el("msgResumo");
        if (m) m.hidden = true;
    }

    window.KosmosResumoForm = { abrir, fechar };
})();
