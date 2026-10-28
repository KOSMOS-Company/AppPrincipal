/* ============================================================
   KOSMOS — resumos.js  (a estante)
   A grade de CADERNOS, os filtros por matéria e a seção "Sem
   caderno". Clicar num caderno abre caderno.php; o lápis
   personaliza ali mesmo.

   Dois arrastes convivem nesta tela, e é por isso que o código
   pergunta "o que está vindo?" antes de aceitar uma soltura:
     · resumo  -> soltar em cima de um caderno GUARDA o resumo nele
     · caderno -> soltar entre outros cadernos REORDENA a estante

   Quem fala com o servidor são os módulos compartilhados
   (caderno-form.js, resumo-form.js, resumo-mover.js). Esta página
   só escuta o resultado:
     "caderno:salvo"   / "caderno:apagado"
     "resumo:salvo"    / "resumo:apagado" / "resumo:movido"
   ============================================================ */
(() => {
    "use strict";

    const BACKEND = "../../../Backend/php";

    const gridCadernos  = document.getElementById("gridCadernos");
    const vazioCadernos = document.getElementById("vazioCadernos");
    const secaoSoltos   = document.getElementById("secaoSoltos");
    const gridSoltos    = document.getElementById("gridSoltos");
    const filtros       = document.getElementById("filtros");
    if (!gridCadernos) return;

    const Mover = window.KosmosMover;

    /* As duas listas vieram prontas no HTML (resumos.php) */
    let cadernos = ler("dadosCadernos");
    let soltos   = ler("dadosResumos");

    let filtroAtual = "todos";

    function ler(id) {
        try {
            return JSON.parse(document.getElementById(id)?.textContent || "[]");
        } catch (err) {
            return [];
        }
    }

    document.addEventListener("DOMContentLoaded", () => {
        document.getElementById("btnNovoCaderno")?.addEventListener("click", () => {
            window.KosmosCadernoForm?.abrir(null);
        });

        document.getElementById("btnNovo")?.addEventListener("click", () => {
            window.KosmosResumoForm?.abrir(null);
        });

        // delegação: os cartões são recriados quando a lista muda
        gridCadernos.addEventListener("click", (e) => {
            const lixeira = e.target.closest("[data-apagar-caderno]");
            if (lixeira) {
                e.preventDefault();
                const alvo = cadernos.find((c) => c.id === Number(lixeira.dataset.apagarCaderno));
                if (alvo) window.KosmosCadernoForm?.apagarDireto(alvo.id, alvo.nome);
                return;
            }
            const lapis = e.target.closest("[data-editar-caderno]");
            if (!lapis) return;
            e.preventDefault();
            const caderno = cadernos.find((c) => c.id === Number(lapis.dataset.editarCaderno));
            if (caderno) window.KosmosCadernoForm?.abrir(caderno);
        });

        gridSoltos?.addEventListener("click", cliqueNoResumo);

        ativarFiltros();
        ativarAbas();
        ativarOrdem();
        ligarArrastes();
        aplicarOrdem(ordemAtual);
    });

    /* ------------------------------------------------------------
       Abas do celular
       O CSS é quem mostra/esconde (só abaixo de 768px); aqui só
       marcamos qual está ativa. Assim o computador nunca fica
       dependendo de JS para ver as duas seções.
       ------------------------------------------------------------ */
    function ativarAbas() {
        const abas = document.getElementById("rsAbas");
        if (!abas) return;

        abas.addEventListener("click", (e) => {
            const aba = e.target.closest(".rs-aba");
            if (aba) mostrarAba(aba.dataset.aba);
        });

        // setas andam entre as abas, como um tablist deve fazer
        abas.addEventListener("keydown", (e) => {
            if (e.key !== "ArrowRight" && e.key !== "ArrowLeft") return;
            e.preventDefault();
            const lista = [...abas.querySelectorAll(".rs-aba")];
            const i = lista.indexOf(document.activeElement);
            if (i < 0) return;
            const passo = e.key === "ArrowRight" ? 1 : -1;
            const alvo = lista[(i + passo + lista.length) % lista.length];
            alvo.focus();
            mostrarAba(alvo.dataset.aba);
        });
    }

    function mostrarAba(qual) {
        const abas = document.getElementById("rsAbas");
        if (!abas) return;

        abas.querySelectorAll(".rs-aba").forEach((a) => {
            const ativa = a.dataset.aba === qual;
            a.classList.toggle("active", ativa);
            a.setAttribute("aria-selected", ativa ? "true" : "false");
        });

        // o CSS lê isto no <body> para decidir o que aparece
        document.body.classList.toggle("aba-notas", qual === "notas");
    }

    /** Lápis e menu "⋮" dos cartões de resumo. */
    function cliqueNoResumo(e) {
        // lixeira: confirma e apaga; o "resumo:apagado" tira o cartão da tela
        const lixeira = e.target.closest("[data-apagar-resumo]");
        if (lixeira) {
            e.preventDefault();
            const resumo = soltos.find((r) => r.id === Number(lixeira.dataset.apagarResumo));
            if (resumo) window.KosmosResumoForm?.apagarDireto(resumo);
            return;
        }

        const lapis = e.target.closest("[data-editar]");
        if (lapis) {
            e.preventDefault();
            const resumo = soltos.find((r) => r.id === Number(lapis.dataset.editar));
            if (resumo) window.KosmosResumoForm?.abrir(resumo);
            return;
        }

        const menu = e.target.closest("[data-menu]");
        if (menu) {
            e.preventDefault();
            e.stopPropagation();
            const resumo = soltos.find((r) => r.id === Number(menu.dataset.menu));
            if (resumo) {
                Mover?.abrirMenu(menu, resumo, {
                    aoEditar: () => window.KosmosResumoForm?.abrir(resumo),
                    aoApagar: () => window.KosmosResumoForm?.apagarDireto(resumo),
                    /* Só oferece se o módulo estiver na página (ele só
                       existe onde o partes/modal-flashcards.php foi
                       incluído) — item de menu que abre nada é pior do
                       que item nenhum. */
                    aoFlashcards: window.KosmosGerarFlashcards
                        ? () => window.KosmosGerarFlashcards.abrir(resumo)
                        : null,
                });
            }
        }
    }

    /* ============================================================
       ARRASTAR
       ============================================================ */
    function ligarArrastes() {
        if (!Mover) return;

        // resumo sendo arrastado: cada caderno vira um alvo
        Mover.ligarArrasteDeResumos(gridSoltos);
        ligarAlvosDosCadernos();

        // "Sem caderno" recebe de volta o que veio de um caderno
        // (na estante todo resumo visível já é solto, mas o alvo
        // existe para o arraste vindo de outra aba do navegador
        // não parecer quebrado)
        Mover.ligarAlvoDeResumo(
            secaoSoltos,
            (resumoId) => Mover.mover(resumoId, null),
            (resumoId) => !soltos.some((r) => r.id === resumoId)
        );

        ligarReordenacao();
    }

    /** Cada cartão de caderno aceita receber um resumo. */
    function ligarAlvosDosCadernos() {
        gridCadernos.querySelectorAll(".caderno-card").forEach((card) => {
            const id = Number(card.dataset.id);
            Mover.ligarAlvoDeResumo(
                card,
                (resumoId) => Mover.mover(resumoId, id),
                // já está neste caderno: não acende o alvo
                (resumoId) => {
                    const r = soltos.find((x) => x.id === resumoId);
                    return !r || Number(r.caderno_id || 0) !== id;
                }
            );
        });
    }

    /* ------------------------------------------------------------
       Reordenar a estante arrastando os cadernos
       ------------------------------------------------------------ */
    let cadernoArrastado = null;

    function ligarReordenacao() {
        gridCadernos.addEventListener("dragstart", (e) => {
            const card = e.target.closest(".caderno-card");
            if (!card) return;

            cadernoArrastado = card;
            card.classList.add("arrastando");
            document.body.classList.add("arrastando-caderno");

            Mover.definirArrastando({ tipo: "caderno", id: Number(card.dataset.id) });
            e.dataTransfer.effectAllowed = "move";
            e.dataTransfer.setData("text/plain", card.querySelector(".caderno-card__nome")?.textContent || "");
        });

        gridCadernos.addEventListener("dragover", (e) => {
            if (!cadernoArrastado) return;
            e.preventDefault();
            e.dataTransfer.dropEffect = "move";

            /* Reordenação "ao vivo": o cartão arrastado é movido no DOM
               enquanto se arrasta, então a estante já mostra como vai
               ficar. O que vale é a ordem final dos elementos. */
            const alvo = e.target.closest(".caderno-card");
            if (!alvo || alvo === cadernoArrastado) return;

            const r = alvo.getBoundingClientRect();
            const depois = (e.clientX - r.left) > r.width / 2;
            alvo.parentNode.insertBefore(cadernoArrastado, depois ? alvo.nextSibling : alvo);
        });

        gridCadernos.addEventListener("drop", (e) => {
            if (!cadernoArrastado) return;
            e.preventDefault();
        });

        gridCadernos.addEventListener("dragend", async () => {
            if (!cadernoArrastado) return;

            cadernoArrastado.classList.remove("arrastando");
            document.body.classList.remove("arrastando-caderno");
            cadernoArrastado = null;
            Mover.definirArrastando(null);

            await salvarOrdem();
        });
    }

    /** Manda a ordem inteira, na sequência em que os cartões ficaram. */
    async function salvarOrdem() {
        const ids = [...gridCadernos.querySelectorAll(".caderno-card")].map((c) => Number(c.dataset.id));
        if (!ids.length) return;

        // nada mudou de lugar: não incomoda o servidor
        const antes = cadernos
            .filter((c) => filtroAtual === "todos" || c.materia === filtroAtual)
            .map((c) => c.id);
        if (antes.length === ids.length && antes.every((id, i) => id === ids[i])) return;

        /* Reordena a lista em memória na mesma sequência, senão a
           próxima repintura desfaz o que a pessoa acabou de fazer. */
        const posicao = new Map(ids.map((id, i) => [id, i]));
        cadernos.sort((a, b) => {
            const pa = posicao.has(a.id) ? posicao.get(a.id) : Infinity;
            const pb = posicao.has(b.id) ? posicao.get(b.id) : Infinity;
            return pa - pb;
        });

        try {
            const dados = new FormData();
            dados.append("acao", "ordenar");
            // com filtro ligado, os escondidos vão no fim, mantendo a ordem atual
            const todos = cadernos.map((c) => c.id);
            dados.append("ordem", todos.join(","));

            const resp = await fetch(`${BACKEND}/resumos_caderno.php`, { method: "POST", body: dados });
            const json = await resp.json();
            if (!json.ok) Mover?.avisar(json.msg || "Não foi possível salvar a ordem.", true);
        } catch (err) {
            Mover?.avisar("Não foi possível salvar a ordem.", true);
        }
    }

    /* ============================================================
       RESULTADO DOS EDITORES
       ============================================================ */

    document.addEventListener("caderno:salvo", (e) => {
        const salvo = e.detail?.caderno;
        if (!salvo) return;

        const i = cadernos.findIndex((c) => c.id === salvo.id);
        if (i >= 0) {
            cadernos[i] = { ...cadernos[i], ...salvo };
        } else {
            cadernos.unshift(salvo);
            filtroAtual = "todos";
        }

        Mover?.definirCadernos(cadernos);
        window.KosmosResumoForm?.definirCadernos(cadernos);   // o select do modal de resumo
        desenhar();
        aplicarOrdem(ordemAtual);
    });

    document.addEventListener("caderno:apagado", (e) => {
        const id = e.detail?.id;
        if (!id) return;

        cadernos = cadernos.filter((c) => c.id !== id);
        Mover?.definirCadernos(cadernos);
        window.KosmosResumoForm?.definirCadernos(cadernos);

        /* Os resumos do caderno não foram apagados — voltaram para
           "Sem caderno". A tela não os tem em memória (só o caderno
           sabia deles), então recarrega para trazê-los. */
        if (e.detail?.soltos > 0) {
            window.location.reload();
            return;
        }

        desenhar();
    });

    document.addEventListener("resumo:salvo", (e) => {
        const salvo = e.detail?.resumo;
        if (!salvo) return;

        const i = soltos.findIndex((r) => r.id === salvo.id);
        const eraSolto = i >= 0;
        const virouSolto = !salvo.caderno_id;

        if (eraSolto && virouSolto) {
            // mescla: a remoção de imagem manda um resumo parcial
            soltos[i] = { ...soltos[i], ...salvo };
        } else if (eraSolto) {
            soltos.splice(i, 1);
            mexerContagem(salvo.caderno_id, +1, salvo.fotos || 0);
        } else if (virouSolto) {
            soltos.unshift(salvo);
        } else {
            mexerContagem(salvo.caderno_id, +1, salvo.fotos || 0);
        }

        desenhar();
    });

    document.addEventListener("resumo:apagado", (e) => {
        const id = e.detail?.id;
        if (!id) return;
        soltos = soltos.filter((r) => r.id !== id);
        desenhar();
    });

    /* Arrastou (ou usou o menu): o resumo trocou de lugar */
    document.addEventListener("resumo:movido", (e) => {
        const { resumo, de, para } = e.detail || {};
        if (!resumo) return;

        const fotos = resumo.fotos || 0;

        if (de === null) {
            // saiu de "Sem caderno"
            const i = soltos.findIndex((r) => r.id === resumo.id);
            if (i >= 0) soltos.splice(i, 1);
        } else {
            mexerContagem(de, -1, fotos);
        }

        if (para === null) {
            // voltou para "Sem caderno": entra sem o texto, que não
            // veio no evento — a próxima carga da página traz tudo
            if (!soltos.some((r) => r.id === resumo.id)) {
                soltos.unshift({ ...resumo, quando: "hoje", corpo: "" });
            }
        } else {
            mexerContagem(para, +1, fotos);
        }

        desenhar();
    });

    /** Ajusta o "N resumos" do cartão de um caderno sem ir ao servidor. */
    function mexerContagem(cadernoId, delta, fotos) {
        const caderno = cadernos.find((c) => c.id === Number(cadernoId));
        if (!caderno) return;
        caderno.resumos = Math.max(0, (caderno.resumos || 0) + delta);
        caderno.fotos   = Math.max(0, (caderno.fotos || 0) + (delta > 0 ? fotos : -fotos));
    }

    /* ============================================================
       DESENHO
       Este HTML é o mesmo de partes/caderno-card.php e
       partes/resumo-card.php — o PHP faz a primeira pintura, aqui
       repintamos. Mexeu lá, mexa aqui.
       ============================================================ */
    const ICONE_LAPIS = '<svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 16h3l8-8-3-3-8 8v3Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M12.5 4.5l3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>';
    const ICONE_LIXEIRA = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 7h14M10 7V5h4v2M7 7l1 12h8l1-12M11 11v5M13 11v5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    const ICONE_MENU  = '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><circle cx="10" cy="4" r="1.6"/><circle cx="10" cy="10" r="1.6"/><circle cx="10" cy="16" r="1.6"/></svg>';
    const ICONE_DOC   = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M9 8h6M9 12h6M9 16h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>';
    const ICONE_FOTO  = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.6"/><circle cx="8.5" cy="10" r="1.6" stroke="currentColor" stroke-width="1.4"/><path d="M4 17l5-4 3 2.5 3-2.5 5 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    function desenhar() {
        desenharCadernos();
        desenharSoltos();
        atualizarFiltros();
        atualizarContagem();
        atualizarAbas();
        ligarAlvosDosCadernos();   // os cartões são novos: religa os alvos
    }

    function desenharCadernos() {
        const lista = cadernos.filter(
            (c) => filtroAtual === "todos" || c.materia === filtroAtual
        );

        gridCadernos.innerHTML = lista.map((c, i) => `
            <article class="caderno-card anim-in caderno-card--${escaparHtml(c.cor || "roxo")}"
                     data-id="${c.id}" data-materia="${escaparHtml(c.materia)}"
                     data-nome="${escaparHtml(c.nome)}"
                     data-resumos="${Number(c.resumos) || 0}"
                     data-criado="${escaparHtml(c.criado_em || "")}"
                     data-dica="${escaparHtml(dicaDoCaderno(c))}"
                     draggable="true" style="animation-delay:${(i * 0.04).toFixed(2)}s">
                <a class="caderno-card__link" href="caderno.php?id=${c.id}" draggable="false">
                    <span class="caderno-card__lombada" aria-hidden="true"></span>
                    ${c.capa ? `<span class="caderno-card__capa" aria-hidden="true"
                          data-capa="${escaparHtml(c.capa)}"></span>` : ""}
                    <div class="caderno-card__body">
                        <div class="caderno-card__topo">
                            ${c.icone ? `<span class="caderno-card__icone" aria-hidden="true">${escaparHtml(c.icone)}</span>` : ""}
                            <span class="materia-tag">${escaparHtml(c.materia)}</span>
                        </div>
                        <h3 class="caderno-card__nome">${escaparHtml(c.nome)}</h3>
                        ${c.descricao ? `<p class="caderno-card__desc">${escaparHtml(c.descricao)}</p>` : ""}
                        <span class="caderno-card__qtd">
                            <strong>${c.resumos}</strong> ${c.resumos === 1 ? "resumo" : "resumos"}${
                                c.fotos > 0
                                    ? ` · <strong>${c.fotos}</strong> ${c.fotos === 1 ? "imagem" : "imagens"}`
                                    : ""
                            }
                        </span>
                        <span class="caderno-card__abrir">Abrir</span>
                    </div>
                </a>
                <div class="caderno-card__acoes">
                    <button type="button" class="caderno-card__editar" data-editar-caderno="${c.id}"
                            title="Personalizar este caderno"
                            aria-label="Personalizar o caderno ${escaparHtml(c.nome)}">${ICONE_LAPIS}</button>
                    <button type="button" class="caderno-card__editar caderno-card__apagar" data-apagar-caderno="${c.id}"
                            title="Excluir este caderno"
                            aria-label="Excluir o caderno ${escaparHtml(c.nome)}">${ICONE_LIXEIRA}</button>
                </div>
                <span class="caderno-card__solte" aria-hidden="true">Solte para guardar aqui</span>
            </article>`).join("");

        // "nenhum caderno" é diferente de "nenhum caderno NESTA matéria"
        if (!vazioCadernos) return;
        vazioCadernos.hidden = lista.length > 0;
        if (vazioCadernos.hidden) return;

        const nenhum = cadernos.length === 0;
        // o estado vazio do resumos.php usa <h2>; só "h3" dava null e
        // derrubava o resto do desenho (contagens, abas, alvos de arraste)
        vazioCadernos.querySelector("h2, h3").textContent = nenhum
            ? "Nenhum caderno por aqui"
            : "Nenhum caderno nesta matéria";
        vazioCadernos.querySelector("p").textContent = nenhum
            ? "Crie um caderno para a matéria que você está estudando e guarde os resumos dela dentro."
            : "Troque o filtro acima ou crie um caderno para esta matéria.";
    }

    /** O que a lombada conta no hover: nome, matéria e o que tem dentro. */
    function dicaDoCaderno(c) {
        let texto = `${c.nome} · ${c.materia} · ${c.resumos} ${c.resumos === 1 ? "resumo" : "resumos"}`;
        if (c.fotos > 0) texto += ` · ${c.fotos} ${c.fotos === 1 ? "imagem" : "imagens"}`;
        return texto;
    }

    function desenharSoltos() {
        if (!secaoSoltos || !gridSoltos) return;

        /* A seção fica sempre no HTML: no celular ela é uma aba e
           precisa poder ser aberta mesmo vazia. O atributo diz ao CSS
           que no computador ela não tem por que ocupar espaço. */
        secaoSoltos.toggleAttribute("data-vazia", soltos.length === 0);

        const vazioSoltos = document.getElementById("vazioSoltos");
        if (vazioSoltos) vazioSoltos.hidden = soltos.length > 0;

        gridSoltos.innerHTML = soltos.map((r, i) => cartaoResumo(r, i, true)).join("");
    }

    function cartaoResumo(r, i, comMateria) {
        return `
            <article class="resumo-card anim-in" data-id="${r.id}"
                     data-materia="${escaparHtml(r.materia)}" data-caderno="${r.caderno_id || 0}"
                     draggable="true" style="animation-delay:${(i * 0.04).toFixed(2)}s">
                <a class="resumo-card__link" href="resumo.php?id=${r.id}" draggable="false">
                    <div class="resumo-card__thumb">
                        ${r.fotos > 0
                            ? `${ICONE_FOTO}<span class="resumo-card__fotos">${r.fotos}</span>`
                            : ICONE_DOC}
                    </div>
                    <div class="resumo-card__body">
                        ${comMateria ? `<span class="materia-tag">${escaparHtml(r.materia)}</span>` : ""}
                        <h3 class="resumo-card__title">${escaparHtml(r.titulo)}</h3>
                        <div class="resumo-card__meta">
                            <span>${escaparHtml(r.quando || "hoje")}</span>
                            <span>Ler</span>
                        </div>
                    </div>
                </a>
                <div class="resumo-card__acoes">
                    <button type="button" class="resumo-card__botao" data-menu="${r.id}"
                            title="Mover, editar ou apagar" aria-haspopup="menu" aria-expanded="false"
                            aria-label="Ações do resumo ${escaparHtml(r.titulo)}">${ICONE_MENU}</button>
                    <button type="button" class="resumo-card__botao" data-editar="${r.id}"
                            title="Editar este resumo"
                            aria-label="Editar o resumo ${escaparHtml(r.titulo)}">${ICONE_LAPIS}</button>
                    <button type="button" class="resumo-card__botao resumo-card__botao--apagar" data-apagar-resumo="${r.id}"
                            title="Excluir este resumo"
                            aria-label="Excluir o resumo ${escaparHtml(r.titulo)}">${ICONE_LIXEIRA}</button>
                </div>
            </article>`;
    }

    /* Mantém os chips coerentes com os cadernos que existem agora */
    function atualizarFiltros() {
        if (!filtros) return;
        const materias = [...new Set(cadernos.map((c) => c.materia))].sort();

        if (materias.length < 2) {
            filtros.hidden = true;
            filtros.innerHTML = "";
            filtroAtual = "todos";
            return;
        }

        filtros.hidden = false;
        const atual = materias.includes(filtroAtual) ? filtroAtual : "todos";
        filtroAtual = atual;

        filtros.innerHTML =
            `<button class="chip${atual === "todos" ? " active" : ""}" data-materia="todos">Todos</button>` +
            materias.map((m) =>
                `<button class="chip${m === atual ? " active" : ""}" data-materia="${escaparHtml(m)}">${escaparHtml(m)}</button>`
            ).join("");
    }

    /* "2 cadernos · 7 resumos na sua conta" no cabeçalho */
    function atualizarContagem() {
        const p = document.getElementById("estanteResumo");
        if (!p) return;

        const nc = cadernos.length;
        const nr = soltos.length + cadernos.reduce((s, c) => s + (c.resumos || 0), 0);

        if (nc === 0 && nr === 0) {
            p.textContent = "Monte um caderno por matéria e guarde seus resumos nele.";
            return;
        }

        const partes = [];
        if (nc > 0) partes.push(`${nc} ${nc === 1 ? "caderno" : "cadernos"}`);
        if (nr > 0) partes.push(`${nr} ${nr === 1 ? "resumo" : "resumos"}`);
        p.textContent = partes.join(" · ") + " na sua conta.";
    }

    /** Os números nas abas do celular. */
    function atualizarAbas() {
        const nc = document.getElementById("abaNumCadernos");
        const nn = document.getElementById("abaNumNotas");
        if (nc) nc.textContent = cadernos.length;
        if (nn) nn.textContent = soltos.length;
    }

    function ativarFiltros() {
        if (!filtros) return;
        filtros.addEventListener("click", (e) => {
            const chip = e.target.closest(".chip");
            if (!chip) return;
            filtros.querySelectorAll(".chip").forEach((c) => c.classList.remove("active"));
            chip.classList.add("active");
            filtroAtual = chip.dataset.materia;
        desenhar();
        aplicarOrdem(ordemAtual);
    });
    }


    const ORDEM_CHAVE_BIB = (window.kosmosChaveConta ? window.kosmosChaveConta("kosmos_ordem_biblioteca") : "kosmos_ordem_biblioteca");

    function comparadorOrdemBib(chave) {
        const nome = (el) => el.dataset.nome || "";
        switch (chave) {
            case "alfabetica":
                return (a, b) => nome(a).localeCompare(nome(b), "pt-BR");
            case "resumos":
                return (a, b) =>
                    (Number(b.dataset.resumos) || 0) - (Number(a.dataset.resumos) || 0) ||
                    nome(a).localeCompare(nome(b), "pt-BR");
            case "recentes":
            default:
                return (a, b) =>
                    (b.dataset.criado || "").localeCompare(a.dataset.criado || "") ||
                    Number(a.dataset.id) - Number(b.dataset.id);
        }
    }

    let ordemAtual = (function () {
        try {
            const s = localStorage.getItem(ORDEM_CHAVE_BIB);
            return s === "alfabetica" || s === "resumos" ? s : "recentes";
        } catch (_) {
            return "recentes";
        }
    })();

    function aplicarOrdem(chave) {
        if (!gridCadernos) return;
        const comparar = comparadorOrdemBib(chave);
        const cards = [...gridCadernos.querySelectorAll(".caderno-card")].sort(comparar);
        let anterior = null;
        for (const card of cards) {
            const alvo = anterior ? anterior.nextElementSibling : gridCadernos.firstElementChild;
            if (card !== alvo) gridCadernos.insertBefore(card, alvo);
            anterior = card;
        }
    }

    function lembrarOrdemBib(chave) {
        try {
            localStorage.setItem(ORDEM_CHAVE_BIB, chave);
        } catch (_) {}
    }

    function ativarOrdem() {
        const botao = document.getElementById("ordemBotao");
        const rotulo = document.getElementById("ordemAtual");
        const menu = document.getElementById("ordemMaterias");
        if (!botao || !menu) return;

        const aberto = () => botao.getAttribute("aria-expanded") === "true";
        const fechar = () => {
            menu.hidden = true;
            botao.setAttribute("aria-expanded", "false");
            botao.focus();
        };
        const abrir = () => {
            menu.hidden = false;
            botao.setAttribute("aria-expanded", "true");
            const ativo = menu.querySelector('[aria-checked="true"]');
            ativo?.focus();
        };

        botao.addEventListener("click", (e) => {
            /* Mesmo truque de exercicios.js: sem isto o clique NO
               FILHO do botão (ícone/texto/seta) chega até aqui em
               cima, `e.target !== botao` é verdadeiro e o menu fecha
               no mesmo instante — só a borda do botão funcionava. */
            e.stopPropagation();
            aberto() ? fechar() : abrir();
        });
        document.addEventListener("click", (e) => {
            if (!aberto()) return;
            if (!menu.contains(e.target) && e.target !== botao) {
                menu.hidden = true;
                botao.setAttribute("aria-expanded", "false");
            }
        });
        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape" && aberto()) {
                e.stopPropagation();
                fechar();
            }
        });

        menu.querySelectorAll(".ordem__item").forEach((item) => {
            item.addEventListener("click", () => {
                const chave = item.dataset.ordem;
                menu.querySelectorAll(".ordem__item").forEach((i) => i.setAttribute("aria-checked", "false"));
                item.setAttribute("aria-checked", "true");
                if (rotulo) rotulo.textContent = item.textContent;
                ordemAtual = chave;
                lembrarOrdemBib(chave);
                aplicarOrdem(chave);
                fechar();
            });
        });

        const inicial = menu.querySelector(`[data-ordem="${ordemAtual}"]`);
        if (inicial) {
            menu.querySelectorAll(".ordem__item").forEach((i) => i.setAttribute("aria-checked", "false"));
            inicial.setAttribute("aria-checked", "true");
            if (rotulo) rotulo.textContent = inicial.textContent;
        }
    }
})();

/* ============================================================
   A DICA DA LOMBADA (nome · matéria · o que tem dentro)
   Era um ::after DENTRO da estante — e a estante rola de lado, então
   recortava a dica: cortada no alto, e nas pontas presa à borda do
   card, crescendo por cima dos vizinhos (com dois cadernos, a do
   último saía cortada pela esquerda e a do primeiro parecia ser do
   outro). Agora ela é um elemento só, solto no <body>, com position:
   fixed: nada a recorta, fica sempre centrada no caderno dono e só
   encosta na borda da TELA quando precisa.
   No toque não existe hover: lá ela não aparece (como antes).
   ============================================================ */
(() => {
    if (!window.matchMedia("(hover: hover)").matches) return;

    const MARGEM_TELA = 8;   // distância mínima da borda da janela
    const VAO = 10;          // entre a dica e o caderno

    const dica = document.createElement("div");
    dica.className = "dica-lombada";
    dica.setAttribute("role", "tooltip");
    dica.hidden = true;
    document.body.appendChild(dica);

    let atual = null;

    /* O caderno sobe 14px no hover (resumos.css) — menos no Lite e
       com "menos movimento", em que ele não sobe. A dica já nasce na
       posição de destino, em vez de ser medida no meio da subida. */
    function subida() {
        const lite = document.documentElement.classList.contains("kosmos-lite");
        const quieto = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
        return lite || quieto ? 0 : 14;
    }

    /** Quanto o elemento está deslocado na vertical pelo transform, agora. */
    function deslocamentoY(el) {
        const t = window.getComputedStyle(el).transform;
        return t && t !== "none" ? new DOMMatrixReadOnly(t).m42 : 0;
    }

    function mostrar(card) {
        const texto = card.dataset.dica;
        if (!texto || document.body.classList.contains("arrastando-resumo")) return;
        atual = card;
        dica.textContent = texto;
        dica.hidden = false;

        /* Onde o topo do caderno VAI ficar: o quanto ele já subiu agora
           (pode estar no meio da animação) sai da conta, e entra a
           subida final do hover. */
        const r = card.getBoundingClientRect();
        const topoCard = r.top - deslocamentoY(card) - subida();
        const largura = dica.offsetWidth;
        const centro = r.left + r.width / 2;
        const esquerda = Math.min(
            Math.max(centro - largura / 2, MARGEM_TELA),
            window.innerWidth - largura - MARGEM_TELA
        );
        dica.style.left = esquerda + "px";
        dica.style.top = (topoCard - VAO - dica.offsetHeight) + "px";
        // a seta aponta para o centro do caderno, mesmo com a caixa empurrada pela borda
        dica.style.setProperty("--seta-x", (centro - esquerda) + "px");
        dica.classList.add("dica-lombada--visivel");
    }

    function esconder() {
        atual = null;
        dica.classList.remove("dica-lombada--visivel");
        dica.hidden = true;
    }

    document.addEventListener("mouseover", (e) => {
        const card = e.target.closest(".caderno-card[data-dica]");
        if (card && card !== atual) mostrar(card);
    });
    document.addEventListener("mouseout", (e) => {
        if (!atual) return;
        const para = e.relatedTarget;
        if (!para || !atual.contains(para)) esconder();
    });
    document.addEventListener("focusin", (e) => {
        const card = e.target.closest(".caderno-card[data-dica]");
        if (card) mostrar(card); else if (atual) esconder();
    });
    document.addEventListener("focusout", (e) => {
        if (atual && !atual.contains(e.relatedTarget)) esconder();
    });
    // rolar a estante ou a página, arrastar ou redimensionar: a dica sai
    document.addEventListener("scroll", esconder, true);
    window.addEventListener("resize", esconder);
    document.addEventListener("dragstart", esconder);
})();
