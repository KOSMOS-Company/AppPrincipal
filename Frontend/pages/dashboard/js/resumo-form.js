/* ============================================================
   KOSMOS — resumo-form.js
   O editor de resumo (o modal) usado em três lugares: na estante
   (resumos.php), dentro de um caderno (caderno.php) e na prévia
   (resumo.php). O HTML do modal vem de partes/modal-resumo.php.

   Quem usa chama KosmosResumoForm.abrir(resumo | null, opcoes) e escuta:
     document "resumo:salvo"   -> detail = { resumo, novo }
     document "resumo:apagado" -> detail = { id }

   opcoes.caderno = id do caderno já escolhido (é o que faz o botão
   "Novo resumo" de dentro de um caderno já vir com ele preenchido).

   Duas coisas que este arquivo resolve e valem explicação:

   1. MATÉRIA — ela é do caderno, não do resumo. Com um caderno
      escolhido, o campo de matéria desaparece e o resumo herda a
      dele (quem decide isso de verdade é o resumos_salvar.php). O
      campo só aparece para resumo solto, que não tem de onde herdar.

   2. IMAGENS — o resumo pode ser as fotos do caderno de papel, o
      texto, ou os dois. As imagens precisam de um resumo já
      existente para se pendurar, então ao criar um resumo novo o
      fluxo é: salva o resumo, pega o id, sobe as imagens. As que
      estão esperando esse momento ficam em `pendentes`; as já
      salvas no servidor ficam em `salvas`.

      Cada imagem é reduzida AQUI no navegador antes de subir, do
      mesmo jeito que a foto de perfil (conta.js): economiza banda,
      não depende da extensão GD no servidor e — o que mais importa
      para "fotografe o seu caderno" — faz caber uma foto de celular,
      que sai da câmera com 4000px e vários MB. O servidor continua
      validando tipo, tamanho e dimensões por conta própria.
   ============================================================ */
(() => {
    "use strict";

    const BACKEND = "../../../Backend/php";
    const modal = document.getElementById("modal");
    if (!modal) return;               // página sem o editor

    const MAX_IMAGENS = 12;           // bate com RS_MAX_IMAGENS no PHP
    const MAX_BYTES   = 5 * 1024 * 1024;   // bate com RS_IMG_MAX_BYTES
    const LADO        = 1600;              // bate com RS_IMG_LADO
    /* Teto do arquivo ORIGINAL, antes de reduzir: é só para não
       tentar abrir um arquivo absurdo na memória do navegador. */
    const MAX_ORIGINAL = 30 * 1024 * 1024;

    const el = (id) => document.getElementById(id);

    /** Imagens já no servidor: [{id, url, legenda}] */
    let salvas = [];
    /** Arquivos escolhidos que ainda não subiram: [{arquivo, url}] */
    let pendentes = [];

    const ICONE_X = '<svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M6 6l8 8M14 6l-8 8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';

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
            const contador = el("contador");
            if (contador) contador.textContent = texto.value.length;
            atualizarDicaDestaque();
        });

        // "Destacar": o botão e o atalho Ctrl+E (Cmd+E no Mac)
        const btnDestacar = el("btnDestacar");
        // mousedown sem padrão: clicar no botão não tira o foco (nem a seleção) do texto
        btnDestacar?.addEventListener("mousedown", (e) => e.preventDefault());
        btnDestacar?.addEventListener("click", destacarSelecao);

        el("btnRascunhoIA")?.addEventListener("click", rascunhoIA);
        el("btnDesfazerIA")?.addEventListener("click", desfazerRascunhoIA);
        texto?.addEventListener("keydown", (e) => {
            if ((e.ctrlKey || e.metaKey) && !e.shiftKey && !e.altKey && e.key.toLowerCase() === "e") {
                e.preventDefault();
                destacarSelecao();
            }
        });

        el("resumoCaderno")?.addEventListener("change", aplicarCaderno);
        el("imagens")?.addEventListener("change", escolherImagens);
        el("imgGrade")?.addEventListener("click", cliqueNaGrade);
    });

    /* ------------------------------------------------------------
       Abrir / fechar
       ------------------------------------------------------------ */

    /* ------------------------------------------------------------
       "Destacar" — o jeito de descobrir o Modo revisão
       O Modo revisão (resumo-revisao.js) esconde os trechos ==assim==
       e o "Gerar flashcards" os vira cartões de completar. Mas ninguém
       adivinha o "==": o botão só aparecia em resumo já marcado, e
       quem não sabia da marcação nunca marcava. Agora é o gesto de
       negrito de qualquer editor: seleciona e clica (ou Ctrl+E).
       ------------------------------------------------------------ */

    /** A mesma regra do resumo-revisao.js e do flashcards_gerar.php. */
    const MARCA_DESTAQUE = /==([^=]+)==/g;
    let timerAvisoDestaque = null;

    function destacarSelecao() {
        const t = el("conteudo");
        if (!t) return;
        const valor = t.value;
        let ini = t.selectionStart;
        let fim = t.selectionEnd;
        let sel = valor.slice(ini, fim);

        // Já destacado? Destacar de novo TIRA o destaque.
        // (a) a seleção inclui os == ...
        if (sel.length > 4 && sel.startsWith("==") && sel.endsWith("==")) {
            trocarTexto(t, ini, fim, sel.slice(2, -2));
            t.setSelectionRange(ini, fim - 4);
            return;
        }
        // (b) ... ou está logo dentro deles
        if (valor.slice(ini - 2, ini) === "==" && valor.slice(fim, fim + 2) === "==" && sel !== "") {
            trocarTexto(t, ini - 2, fim + 2, sel);
            t.setSelectionRange(ini - 2, fim - 2);
            return;
        }

        // O destaque não engole os espaços das pontas da seleção
        while (ini < fim && /\s/.test(valor[ini])) ini++;
        while (fim > ini && /\s/.test(valor[fim - 1])) fim--;
        sel = valor.slice(ini, fim);

        if (sel === "") {
            avisarDestaque("Selecione primeiro o trecho que você quer lembrar — depois clique em Destacar.");
            t.focus();
            return;
        }
        if (sel.includes("=")) {
            avisarDestaque("Trechos com o sinal de igual (=) não funcionam no Modo revisão. Destaque só uma parte — o nome da lei ou o resultado, por exemplo.");
            t.focus();
            return;
        }

        trocarTexto(t, ini, fim, "==" + sel + "==");
        t.setSelectionRange(ini + 2, fim + 2);
    }

    /* Troca um pedaço do texto mantendo o Ctrl+Z: o insertText entra
       no histórico de desfazer do navegador (mexer no .value direto
       não entra). Sem ele, cai no setRangeText. */
    function trocarTexto(t, ini, fim, novo) {
        t.focus();
        t.setSelectionRange(ini, fim);
        const foi = typeof document.execCommand === "function" && document.execCommand("insertText", false, novo);
        if (!foi) {
            t.setRangeText(novo, ini, fim, "end");
            t.dispatchEvent(new Event("input", { bubbles: true }));
        }
    }

    function avisarDestaque(texto) {
        const dica = el("destaqueDica");
        if (!dica) return;
        dica.textContent = texto;
        dica.classList.add("destaque-barra__dica--aviso");
        clearTimeout(timerAvisoDestaque);
        timerAvisoDestaque = setTimeout(atualizarDicaDestaque, 5000);
    }

    /** A linha ao lado do botão: ensina enquanto não há destaque, confirma quando há. */
    function atualizarDicaDestaque() {
        const dica = el("destaqueDica");
        const t = el("conteudo");
        if (!dica || !t) return;
        clearTimeout(timerAvisoDestaque);
        dica.classList.remove("destaque-barra__dica--aviso");
        const n = (t.value.match(MARCA_DESTAQUE) || []).length;
        dica.textContent = n === 0
            ? "Selecione o que quer lembrar e destaque. No Modo revisão, esses trechos ficam escondidos para você se testar."
            : `✓ ${n} ${n === 1 ? "trecho destacado" : "trechos destacados"}`;
    }

    /* resumo = null -> novo; com resumo -> edita.
       opcoes.caderno = caderno já escolhido (novo resumo dentro dele) */
    let cadernoOriginal = null;        // o caderno salvo do resumo em edição

    function abrir(resumo, opcoes = {}) {
        esconderMsg();
        limparPendentes();
        textoAntesIA = null;
        if (el("btnDesfazerIA")) el("btnDesfazerIA").hidden = true;
        cadernoOriginal = resumo?.caderno_id ? Number(resumo.caderno_id) : null;

        const titulo  = el("titulo");
        const caderno = el("resumoCaderno");
        const materia = el("materia");
        const texto   = el("conteudo");

        if (resumo) {
            el("modalTitulo").textContent = "Editar resumo";
            el("resumoId").value = resumo.id;
            titulo.value = resumo.titulo;
            texto.value  = resumo.corpo || "";
            salvas = [...(resumo.imagens || [])];
            el("btnApagar").hidden = false;

            if (caderno) caderno.value = resumo.caderno_id ? String(resumo.caderno_id) : "";
            // resumo solto guarda a própria matéria; num caderno, o
            // aplicarCaderno() esconde este campo de qualquer jeito
            if (materia) materia.value = resumo.materia || "";
        } else {
            el("modalTitulo").textContent = "Novo resumo";
            el("resumoId").value = "";
            titulo.value = "";
            texto.value  = "";
            salvas = [];
            el("btnApagar").hidden = true;

            if (caderno) {
                caderno.value = opcoes.caderno ? String(opcoes.caderno) : "";
            }

            // vazio: quem escreve o resumo solto é que escolhe a matéria
            if (materia) materia.value = "";
        }

        const contador = el("contador");
        if (contador) contador.textContent = texto.value.length;
        atualizarDicaDestaque();
        aplicarCaderno();
        desenharImagens();

        modal.classList.add("open");
        titulo.focus();
    }

    function fechar() {
        modal.classList.remove("open");
        limparPendentes();
        esconderMsg();
    }

    /* ------------------------------------------------------------
       Rascunho com IA
       O texto entra no campo, sem salvar: a pessoa confere e ajusta.
       O que estava escrito vai junto como anotações (a IA organiza e
       completa) e fica guardado para o "Desfazer".
       ------------------------------------------------------------ */
    let textoAntesIA = null;

    async function rascunhoIA() {
        const btn    = el("btnRascunhoIA");
        const texto  = el("conteudo");
        const titulo = el("titulo").value.trim();

        if (!titulo) {
            msg("Escreva o tema primeiro: é dele que a IA parte.", "erro");
            el("titulo").focus();
            return;
        }

        const dados = new FormData();
        dados.append("titulo", titulo);
        // num caderno, o aplicarCaderno() já pôs a matéria dele aqui
        dados.append("materia", el("materia")?.value || "");
        dados.append("anotacoes", texto.value.trim());

        const rotulo = btn.querySelector("span");
        btn.disabled = true;
        rotulo.textContent = "Escrevendo…";
        esconderMsg();

        try {
            const resp = await fetch(`${BACKEND}/resumos_ia.php`, { method: "POST", body: dados });
            const json = await resp.json();
            if (!json.ok) throw new Error(json.msg || "Não foi possível escrever o rascunho.");

            textoAntesIA = texto.value;
            texto.value = json.texto;
            texto.dispatchEvent(new Event("input"));   // contador e dica do destaque
            texto.scrollTop = 0;
            el("btnDesfazerIA").hidden = false;
            msg("Rascunho da IA no campo. Confira os fatos e ajuste com suas palavras antes de salvar.", "sucesso");
        } catch (err) {
            msg(err.message || "Não foi possível escrever o rascunho.", "erro");
        } finally {
            btn.disabled = false;
            rotulo.textContent = "Rascunho com IA";
        }
    }

    function desfazerRascunhoIA() {
        if (textoAntesIA === null) return;
        const texto = el("conteudo");
        texto.value = textoAntesIA;
        textoAntesIA = null;
        texto.dispatchEvent(new Event("input"));
        el("btnDesfazerIA").hidden = true;
        esconderMsg();
    }

    /* ------------------------------------------------------------
       Caderno escolhido -> quem manda na matéria
       ------------------------------------------------------------ */
    function aplicarCaderno() {
        const caderno = el("resumoCaderno");
        const campo   = el("campoMateria");
        const dica    = el("dicaCaderno");
        if (!caderno) return;

        const opcao   = caderno.selectedOptions[0];
        const materia = opcao?.dataset.materia || "";
        const dentro  = caderno.value !== "" && materia !== "";

        // dentro de um caderno a matéria vem dele; solto, o resumo escolhe
        if (campo) campo.hidden = dentro;
        if (dentro && el("materia")) el("materia").value = materia;

        if (dica) {
            dica.hidden = !dentro;
            dica.textContent = dentro ? `Este resumo entra na matéria ${materia}.` : "";
        }
    }

    /* ------------------------------------------------------------
       Imagens
       ------------------------------------------------------------ */

    async function escolherImagens(e) {
        const campo = e.target;
        const escolhidos = [...campo.files];
        campo.value = "";   // permite escolher o mesmo arquivo de novo

        if (salvas.length + pendentes.length >= MAX_IMAGENS) {
            msg(`Um resumo aceita até ${MAX_IMAGENS} imagens.`, "erro");
            return;
        }

        const solta = el("imgSolta");
        const rotulo = solta?.querySelector("span")?.innerHTML;
        if (solta && escolhidos.length) {
            solta.querySelector("span").textContent = "Preparando as imagens…";
        }

        let recusadas = 0;

        for (const arquivo of escolhidos) {
            if (salvas.length + pendentes.length >= MAX_IMAGENS) { recusadas++; continue; }
            if (!/^image\/(jpeg|png|webp)$/.test(arquivo.type)) { recusadas++; continue; }
            if (arquivo.size > MAX_ORIGINAL) { recusadas++; continue; }

            let pronta;
            try {
                pronta = await reduzirImagem(arquivo);
            } catch (err) {
                recusadas++;
                continue;
            }

            // mesmo reduzida passou do limite? aí não dá
            if (pronta.size > MAX_BYTES) { recusadas++; continue; }

            // objectURL: prévia local, sem subir nada ainda
            pendentes.push({
                arquivo: pronta,
                nome: arquivo.name || "imagem",
                url: URL.createObjectURL(pronta),
            });
            desenharImagens();
        }

        if (solta && rotulo) solta.querySelector("span").innerHTML = rotulo;
        desenharImagens();

        if (recusadas > 0) {
            msg(recusadas === 1
                ? "1 imagem foi recusada (aceitamos JPG, PNG ou WEBP)."
                : `${recusadas} imagens foram recusadas (aceitamos JPG, PNG ou WEBP).`,
                "erro");
        } else {
            esconderMsg();
        }
    }

    /* Reduz mantendo a proporção (maior lado = LADO). Não recorta: a
       foto de uma página de caderno não pode perder as bordas, que é
       justo onde costuma estar o texto. Mantém PNG/WEBP para não
       perder transparência; o resto vira JPEG. */
    function reduzirImagem(arquivo) {
        return new Promise((resolve, reject) => {
            const url = URL.createObjectURL(arquivo);
            const img = new Image();

            img.onload = () => {
                URL.revokeObjectURL(url);
                try {
                    const maior  = Math.max(img.naturalWidth, img.naturalHeight);
                    const escala = maior > LADO ? LADO / maior : 1;

                    // já está pequena e leve: sobe como veio
                    if (escala === 1 && arquivo.size <= MAX_BYTES) {
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

    function desenharImagens() {
        const grade = el("imgGrade");
        const dica  = el("imgDica");
        if (!grade) return;

        const total = salvas.length + pendentes.length;
        grade.hidden = total === 0;

        grade.innerHTML =
            salvas.map((img) => miniatura(img.url, "salva", img.id, "")).join("") +
            pendentes.map((p, i) => miniatura(p.url, "pendente", i, "A enviar")).join("");

        if (dica) {
            dica.hidden = total === 0;
            dica.textContent = total === 0 ? "" : `${total} de ${MAX_IMAGENS} imagens.`;
        }

        // sem vaga não faz sentido continuar oferecendo o seletor
        const solta = el("imgSolta");
        if (solta) solta.hidden = total >= MAX_IMAGENS;
    }

    function miniatura(url, tipo, chave, selo) {
        return `
        <div class="img-mini" data-tipo="${tipo}" data-chave="${chave}">
            <img src="${escaparHtml(url)}" alt="">
            ${selo ? `<span class="img-mini__selo">${escaparHtml(selo)}</span>` : ""}
            <button type="button" class="img-mini__x" data-remover
                    title="Remover imagem" aria-label="Remover imagem">${ICONE_X}</button>
        </div>`;
    }

    async function cliqueNaGrade(e) {
        const botao = e.target.closest("[data-remover]");
        if (!botao) return;

        const mini = botao.closest(".img-mini");
        const tipo = mini.dataset.tipo;

        // ainda não subiu: basta esquecer o arquivo
        if (tipo === "pendente") {
            const i = Number(mini.dataset.chave);
            const [fora] = pendentes.splice(i, 1);
            if (fora) URL.revokeObjectURL(fora.url);
            desenharImagens();
            return;
        }

        // já está no servidor: apagar é de verdade, então confirma
        const ok = await confirmar({
            titulo: "Remover esta imagem?",
            texto: "A imagem será apagada do resumo. Não tem como desfazer.",
            botao: "Remover",
            perigo: true,
        });
        if (!ok) return;

        const id = Number(mini.dataset.chave);
        botao.disabled = true;
        try {
            const dados = new FormData();
            dados.append("acao", "excluir");
            dados.append("id", id);

            const resp = await fetch(`${BACKEND}/resumos_imagem.php`, { method: "POST", body: dados });
            const json = await resp.json();

            if (!json.ok) { msg(json.msg || "Não foi possível remover.", "erro"); return; }

            salvas = json.imagens || [];
            desenharImagens();
            avisarSalvo();
        } catch (err) {
            msg("Não foi possível falar com o servidor.", "erro");
        } finally {
            botao.disabled = false;
        }
    }

    /** Sobe as imagens pendentes para um resumo que já existe. */
    async function subirPendentes(resumoId) {
        if (!pendentes.length) return true;

        const dados = new FormData();
        dados.append("acao", "enviar");
        dados.append("resumo", resumoId);
        // o blob reduzido não tem nome: mandamos o do arquivo original
        pendentes.forEach((p) => dados.append("imagens[]", p.arquivo, p.nome));

        const resp = await fetch(`${BACKEND}/resumos_imagem.php`, { method: "POST", body: dados });
        const json = await resp.json();

        if (!json.ok) {
            msg(json.msg || "O resumo foi salvo, mas as imagens não subiram.", "erro");
            return false;
        }

        salvas = json.imagens || [];
        limparPendentes();
        desenharImagens();
        return true;
    }

    function limparPendentes() {
        pendentes.forEach((p) => URL.revokeObjectURL(p.url));
        pendentes = [];
    }

    /* ------------------------------------------------------------
       Salvar / apagar
       ------------------------------------------------------------ */

    async function salvar(e) {
        e.preventDefault();

        const btn     = el("btnSalvar");
        const id      = el("resumoId").value;
        const titulo  = el("titulo").value.trim();
        const caderno = el("resumoCaderno")?.value || "";
        const corpo   = el("conteudo").value.trim();

        if (!titulo) { msg("Dê um tema ao resumo.", "erro"); return; }
        if (!corpo && !salvas.length && !pendentes.length) {
            msg("Escreva o resumo ou anexe uma imagem.", "erro");
            return;
        }

        const dados = new FormData();
        if (id) dados.append("id", id);
        dados.append("titulo", titulo);
        dados.append("caderno", caderno);
        dados.append("corpo", corpo);
        // resumo solto escolhe a própria matéria; num caderno, o
        // servidor usa a do caderno e ignora este campo
        if (!caderno) dados.append("materia", el("materia").value);
        // avisa que há imagem a caminho: é o que permite salvar sem texto
        if (pendentes.length) dados.append("com_imagens", "1");

        btn.disabled = true;
        btn.textContent = "Salvando…";
        try {
            const resp = await fetch(`${BACKEND}/resumos_salvar.php`, { method: "POST", body: dados });
            const json = await resp.json();

            if (!json.ok) { msg(json.msg || "Não foi possível salvar.", "erro"); return; }

            const resumo = json.resumo;

            // o resumo agora existe: as imagens escolhidas podem subir
            if (pendentes.length) {
                btn.textContent = "Enviando imagens…";
                const subiu = await subirPendentes(resumo.id);
                resumo.imagens = salvas;
                resumo.fotos = salvas.length;

                if (!subiu) {
                    // o resumo está salvo; a tela precisa saber disso mesmo
                    // que as imagens tenham falhado — e o modal fica aberto
                    // para a pessoa tentar de novo
                    document.dispatchEvent(new CustomEvent("resumo:salvo", {
                        detail: { resumo, novo: !id },
                    }));
                    el("resumoId").value = resumo.id;
                    el("btnApagar").hidden = false;
                    return;
                }
            }

            document.dispatchEvent(new CustomEvent("resumo:salvo", {
                detail: { resumo, novo: !id },
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

        const btn = el("btnApagar");
        const apagou = await excluirResumo(id, el("titulo").value.trim(), salvas.length, {
            aoComecar: () => { btn.disabled = true; btn.textContent = "Excluindo…"; },
            aoTerminar: () => { btn.disabled = false; btn.textContent = "Excluir"; },
            aoErrar: (texto) => msg(texto, "erro"),
        });
        if (apagou) fechar();
    }

    /** Confirma e apaga um resumo. Usada pelo botão "Excluir" do modal
     *  e pela lixeira do cartão (apagarDireto). Devolve true se apagou;
     *  quem estiver na tela fica sabendo pelo evento "resumo:apagado". */
    async function excluirResumo(id, titulo, imagens, ganchos = {}) {
        // confirmar() é compartilhada (dashboard.js) e usa o modal de
        // partes/modal-confirma.php
        const ok = await confirmar({
            titulo: "Excluir este resumo?",
            texto: `"${titulo}" será excluído da sua conta`
                 + (imagens ? `, com as ${imagens === 1 ? "sua imagem" : `suas ${imagens} imagens`}` : "")
                 + ". Não tem como desfazer.",
            botao: "Excluir",
            perigo: true,
        });
        if (!ok) return false;

        const erro = ganchos.aoErrar || ((texto) => alert(texto));
        ganchos.aoComecar?.();
        try {
            const dados = new FormData();
            dados.append("id", id);
            const resp = await fetch(`${BACKEND}/resumos_excluir.php`, { method: "POST", body: dados });
            const json = await resp.json();

            if (!json.ok) { erro(json.msg || "Não foi possível apagar."); return false; }

            document.dispatchEvent(new CustomEvent("resumo:apagado", { detail: { id } }));
            return true;
        } catch (err) {
            erro("Não foi possível falar com o servidor.");
            return false;
        } finally {
            ganchos.aoTerminar?.();
        }
    }

    /** A lixeira do cartão: apaga sem abrir o modal (só pede confirmação). */
    function apagarDireto(resumo) {
        if (!resumo) return;
        return excluirResumo(Number(resumo.id), resumo.titulo || "", Number(resumo.fotos) || 0);
    }

    /* Remover uma imagem já salva mexe no resumo na hora: a tela por
       trás do modal precisa saber, senão fica com a contagem velha. */
    function avisarSalvo() {
        const id = Number(el("resumoId").value);
        if (!id) return;

        document.dispatchEvent(new CustomEvent("resumo:salvo", {
            detail: {
                novo: false,
                resumo: {
                    id,
                    titulo: el("titulo").value.trim(),
                    materia: el("materia")?.value || "",
                    corpo: el("conteudo").value,
                    // o caderno GRAVADO, não o do select: a pessoa pode ter
                    // trocado sem salvar, e isto aqui não salva o caderno
                    caderno_id: cadernoOriginal,
                    imagens: salvas,
                    fotos: salvas.length,
                },
            },
        }));
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
    /* A lista de cadernos do select nasce no PHP (partes/modal-resumo.php).
       Criar, renomear ou apagar um caderno sem recarregar deixava o
       select velho: o caderno novo não aparecia e o apagado ainda
       podia ser escolhido (o servidor respondia 404). As telas que
       mexem em cadernos chamam isto com a lista atual. */
    function definirCadernos(lista) {
        const sel = el("resumoCaderno");
        if (!sel || !Array.isArray(lista)) return;
        const atual = sel.value;

        [...sel.options].forEach((o) => { if (o.value !== "") o.remove(); });
        lista.forEach((c) => {
            const o = document.createElement("option");
            o.value = String(c.id);
            o.dataset.materia = c.materia || "";
            o.textContent = (c.icone ? c.icone + " " : "") + c.nome + " · " + (c.materia || "");
            sel.appendChild(o);
        });
        // o escolhido some se o caderno foi apagado: volta para "Sem caderno"
        sel.value = [...sel.options].some((o) => o.value === atual) ? atual : "";
        aplicarCaderno();
    }

    window.KosmosResumoForm = { abrir, fechar, definirCadernos, apagarDireto };
})();
