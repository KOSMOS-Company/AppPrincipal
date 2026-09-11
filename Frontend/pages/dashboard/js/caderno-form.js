/* ============================================================
   KOSMOS — caderno-form.js
   O modal de criar/personalizar/excluir caderno, usado em dois
   lugares: na estante (resumos.php) e dentro de um caderno
   (caderno.php). O HTML vem de partes/modal-caderno.php.

   Quem usa chama KosmosCadernoForm.abrir(caderno | null) e escuta:
     document "caderno:salvo"   -> detail = { caderno, novo }
     document "caderno:apagado" -> detail = { id, soltos }

   Duas coisas que valem explicação:

   1. PRÉVIA — a escolha de cor e ícone é mostrada num cartão de
      mentira que muda junto com os campos. Escolher "verde" numa
      lista de nomes não diz nada; ver o cartão ficar verde diz.

   2. CAPA — a imagem precisa de um caderno já existente para se
      pendurar, então ao criar um caderno o fluxo é: salva o
      caderno, pega o id, sobe a capa. Ela é reduzida aqui no
      navegador antes de subir, como as imagens de resumo e a foto
      de perfil — sem depender da extensão GD no servidor.
   ============================================================ */
(() => {
    "use strict";

    const BACKEND = "../../../Backend/php";
    const modal = document.getElementById("modalCaderno");
    if (!modal) return;               // página sem o modal

    const CAPA_LADO    = 900;              // bate com RS_CAPA_LADO no PHP
    const CAPA_MAX     = 5 * 1024 * 1024;  // bate com RS_IMG_MAX_BYTES
    const CAPA_ORIGEM  = 30 * 1024 * 1024; // teto do arquivo antes de reduzir

    const el = (id) => document.getElementById(id);

    let cor   = "roxo";
    let icone = "";
    /** capa já salva (url) e capa escolhida esperando o salvamento */
    let capaSalva = null;
    let capaNova  = null;   // { blob, url }
    /** o usuário pediu para tirar a capa que estava salva */
    let capaApagar = false;

    document.addEventListener("DOMContentLoaded", () => {
        el("cadernoFechar")?.addEventListener("click", fechar);
        el("cadernoCancelar")?.addEventListener("click", fechar);
        modal.addEventListener("click", (e) => { if (e.target === modal) fechar(); });
        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape" && modal.classList.contains("open")) fechar();
        });

        el("formCaderno")?.addEventListener("submit", salvar);
        el("cadernoApagar")?.addEventListener("click", apagar);

        // prévia ao vivo
        el("cadernoNome")?.addEventListener("input", pintarPrevia);
        el("cadernoDescricao")?.addEventListener("input", pintarPrevia);
        el("cadernoMateria")?.addEventListener("change", pintarPrevia);

        el("cadCores")?.addEventListener("click", (e) => {
            const botao = e.target.closest("[data-cor]");
            if (!botao) return;
            cor = botao.dataset.cor;
            marcarEscolhido(el("cadCores"), botao);
            pintarPrevia();
        });

        el("cadIcones")?.addEventListener("click", (e) => {
            const botao = e.target.closest("[data-icone]");
            if (!botao) return;
            icone = botao.dataset.icone;
            marcarEscolhido(el("cadIcones"), botao);
            pintarPrevia();
        });

        // setas nos grupos de escolha: é o que um radiogroup deve fazer
        [el("cadCores"), el("cadIcones")].forEach(ligarSetas);

        el("cadernoCapa")?.addEventListener("change", escolherCapa);
        el("capaRemover")?.addEventListener("click", removerCapa);
    });

    /* ------------------------------------------------------------
       Abrir / fechar
       ------------------------------------------------------------ */

    /* caderno = null -> novo; com caderno -> edita */
    function abrir(caderno) {
        esconderMsg();
        limparCapaNova();
        capaApagar = false;

        const nome    = el("cadernoNome");
        const materia = el("cadernoMateria");
        const desc    = el("cadernoDescricao");

        if (caderno) {
            el("modalCadernoTitulo").textContent = "Personalizar caderno";
            el("cadernoSalvar").textContent = "Salvar";
            el("cadernoId").value = caderno.id;
            nome.value    = caderno.nome;
            materia.value = caderno.materia;
            desc.value    = caderno.descricao || "";
            cor           = caderno.cor || "roxo";
            icone         = caderno.icone || "";
            capaSalva     = caderno.capa || null;
            el("cadernoApagar").hidden = false;
        } else {
            el("modalCadernoTitulo").textContent = "Novo caderno";
            el("cadernoSalvar").textContent = "Criar caderno";
            el("cadernoId").value = "";
            nome.value = "";
            desc.value = "";
            cor        = "roxo";
            icone      = "";
            capaSalva  = null;
            el("cadernoApagar").hidden = true;

            // começa na matéria favorita, se houver alguma marcada na Conta
            const favorita = materia.querySelector("option[data-favorita]");
            if (favorita) materia.value = favorita.value;
        }

        marcarEscolhido(el("cadCores"), el("cadCores")?.querySelector(`[data-cor="${cor}"]`));
        marcarEscolhido(el("cadIcones"), el("cadIcones")?.querySelector(`[data-icone="${cssEscape(icone)}"]`));
        desenharCapa();
        pintarPrevia();

        modal.classList.add("open");
        nome.focus();
    }

    function fechar() {
        modal.classList.remove("open");
        limparCapaNova();
        esconderMsg();
    }

    /* ------------------------------------------------------------
       Escolhas (cor e ícone)
       ------------------------------------------------------------ */
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
        const previa = el("cadPrevia");
        if (!previa) return;

        previa.className = `cad-previa caderno-card--${cor}`;

        el("cadPreviaNome").textContent = el("cadernoNome").value.trim() || "Nome do caderno";
        el("cadPreviaMateria").textContent = el("cadernoMateria").value || "Matéria";

        const desc = el("cadernoDescricao").value.trim();
        el("cadPreviaDesc").textContent = desc;
        el("cadPreviaDesc").hidden = desc === "";

        const ic = el("cadPreviaIcone");
        ic.textContent = icone;
        ic.hidden = icone === "";

        const capa = capaNova?.url || (capaApagar ? null : capaSalva);
        const alvo = el("cadPreviaCapa");
        alvo.hidden = !capa;
        alvo.style.backgroundImage = capa ? `url("${capa}")` : "";
    }

    /* ------------------------------------------------------------
       Capa
       ------------------------------------------------------------ */
    async function escolherCapa(e) {
        const arquivo = e.target.files && e.target.files[0];
        e.target.value = "";               // permite reenviar o mesmo arquivo
        if (!arquivo) return;

        if (!/^image\/(jpeg|png|webp)$/.test(arquivo.type)) {
            msg("Use uma imagem JPG, PNG ou WEBP.", "erro");
            return;
        }
        if (arquivo.size > CAPA_ORIGEM) {
            msg("Essa imagem é grande demais.", "erro");
            return;
        }

        try {
            const blob = await reduzirImagem(arquivo);
            if (blob.size > CAPA_MAX) {
                msg("Não deu para reduzir essa imagem o bastante.", "erro");
                return;
            }

            limparCapaNova();
            capaNova = { blob, url: URL.createObjectURL(blob), nome: arquivo.name || "capa" };
            capaApagar = false;
            esconderMsg();
            desenharCapa();
            pintarPrevia();
        } catch (err) {
            msg("Não foi possível ler essa imagem.", "erro");
        }
    }

    function removerCapa() {
        if (capaNova) {
            // só descarta a escolha, sem mexer no que está salvo
            limparCapaNova();
        } else if (capaSalva) {
            // a remoção de verdade acontece ao salvar
            capaApagar = true;
        }
        desenharCapa();
        pintarPrevia();
    }

    function desenharCapa() {
        const capa = capaNova?.url || (capaApagar ? null : capaSalva);

        el("capaPrevia").hidden = !capa;
        el("capaSolta").hidden  = !!capa;
        if (capa) el("capaImg").src = capa;

        const dica = el("capaDica");
        if (dica) {
            if (capaNova)        { dica.hidden = false; dica.textContent = "A capa sobe quando você salvar."; }
            else if (capaApagar) { dica.hidden = false; dica.textContent = "A capa será removida quando você salvar."; }
            else                 { dica.hidden = true;  dica.textContent = ""; }
        }
    }

    function limparCapaNova() {
        if (capaNova) URL.revokeObjectURL(capaNova.url);
        capaNova = null;
    }

    /* Reduz mantendo a proporção — mesma receita da foto de perfil
       (conta.js) e das imagens de resumo. */
    function reduzirImagem(arquivo) {
        return new Promise((resolve, reject) => {
            const url = URL.createObjectURL(arquivo);
            const img = new Image();

            img.onload = () => {
                URL.revokeObjectURL(url);
                try {
                    const maior  = Math.max(img.naturalWidth, img.naturalHeight);
                    const escala = maior > CAPA_LADO ? CAPA_LADO / maior : 1;

                    if (escala === 1 && arquivo.size <= CAPA_MAX) {
                        resolve(arquivo);
                        return;
                    }

                    const larg = Math.max(1, Math.round(img.naturalWidth * escala));
                    const alt  = Math.max(1, Math.round(img.naturalHeight * escala));

                    const canvas = document.createElement("canvas");
                    canvas.width  = larg;
                    canvas.height = alt;
                    const ctx = canvas.getContext("2d");
                    ctx.imageSmoothingQuality = "high";
                    ctx.drawImage(img, 0, 0, larg, alt);

                    const tipo = arquivo.type === "image/png" || arquivo.type === "image/webp"
                        ? arquivo.type
                        : "image/jpeg";

                    canvas.toBlob((b) => (b ? resolve(b) : reject(new Error("canvas"))), tipo, 0.88);
                } catch (err) {
                    reject(err);
                }
            };
            img.onerror = () => { URL.revokeObjectURL(url); reject(new Error("imagem inválida")); };
            img.src = url;
        });
    }

    /** Sobe (ou remove) a capa de um caderno que já existe. */
    async function acertarCapa(cadernoId) {
        if (!capaNova && !capaApagar) return null;

        const dados = new FormData();
        dados.append("caderno", cadernoId);

        if (capaNova) {
            dados.append("acao", "enviar");
            dados.append("capa", capaNova.blob, capaNova.nome);
        } else {
            dados.append("acao", "remover");
        }

        const resp = await fetch(`${BACKEND}/resumos_capa.php`, { method: "POST", body: dados });
        const json = await resp.json();

        if (!json.ok) {
            msg(json.msg || "O caderno foi salvo, mas a capa não subiu.", "erro");
            return null;
        }

        capaSalva = json.caderno?.capa || null;
        capaApagar = false;
        limparCapaNova();
        desenharCapa();
        return json.caderno;
    }

    /* ------------------------------------------------------------
       Salvar / apagar
       ------------------------------------------------------------ */
    async function salvar(e) {
        e.preventDefault();

        const btn  = el("cadernoSalvar");
        const id   = el("cadernoId").value;
        const nome = el("cadernoNome").value.trim();

        if (!nome) { msg("Dê um nome ao caderno.", "erro"); return; }

        const dados = new FormData();
        dados.append("acao", id ? "editar" : "criar");
        if (id) dados.append("id", id);
        dados.append("nome", nome);
        dados.append("materia", el("cadernoMateria").value);
        dados.append("descricao", el("cadernoDescricao").value.trim());
        dados.append("cor", cor);
        dados.append("icone", icone);

        const rotulo = btn.textContent;
        btn.disabled = true;
        btn.textContent = "Salvando…";
        try {
            const resp = await fetch(`${BACKEND}/resumos_caderno.php`, { method: "POST", body: dados });
            const json = await resp.json();

            if (!json.ok) { msg(json.msg || "Não foi possível salvar.", "erro"); return; }

            let caderno = json.caderno;

            // o caderno agora existe: a capa escolhida pode subir
            if (capaNova || capaApagar) {
                btn.textContent = "Enviando a capa…";
                const comCapa = await acertarCapa(caderno.id);

                if (comCapa) {
                    caderno = comCapa;
                } else {
                    /* O caderno está salvo mesmo que a capa tenha
                       falhado: a tela precisa saber disso, e o modal
                       fica aberto para tentar a capa de novo. */
                    document.dispatchEvent(new CustomEvent("caderno:salvo", {
                        detail: { caderno, novo: !id },
                    }));
                    el("cadernoId").value = caderno.id;
                    el("cadernoApagar").hidden = false;
                    return;
                }
            }

            document.dispatchEvent(new CustomEvent("caderno:salvo", {
                detail: { caderno, novo: !id },
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
        const id = Number(el("cadernoId").value);
        if (!id) return;

        const nome = el("cadernoNome").value.trim();
        // confirmar() é compartilhada (dashboard.js) e usa o modal de
        // partes/modal-confirma.php
        const ok = await confirmar({
            titulo: "Excluir este caderno?",
            texto: `"${nome}" será excluído. Os resumos guardados nele NÃO são apagados: `
                 + `eles voltam para "Sem caderno".`,
            botao: "Excluir",
            perigo: true,
        });
        if (!ok) return;

        const btn = el("cadernoApagar");
        btn.disabled = true;
        btn.textContent = "Excluindo…";
        try {
            const dados = new FormData();
            dados.append("acao", "excluir");
            dados.append("id", id);

            const resp = await fetch(`${BACKEND}/resumos_caderno.php`, { method: "POST", body: dados });
            const json = await resp.json();

            if (!json.ok) { msg(json.msg || "Não foi possível excluir.", "erro"); return; }

            document.dispatchEvent(new CustomEvent("caderno:apagado", {
                detail: { id, soltos: json.soltos || 0, msg: json.msg },
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
        const m = el("msgCaderno");
        if (!m) return;
        m.textContent = texto;
        m.className = "msg msg--" + tipo;
        m.hidden = false;
    }

    function esconderMsg() {
        const m = el("msgCaderno");
        if (m) m.hidden = true;
    }

    /* O ícone é emoji e entra num seletor CSS: escapamos as aspas
       para não quebrar o querySelector. */
    function cssEscape(valor) {
        return String(valor).replace(/["\\]/g, "\\$&");
    }

    window.KosmosCadernoForm = { abrir, fechar };
})();
