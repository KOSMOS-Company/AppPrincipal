/* ============================================================
   KOSMOS — caderno.js  (dentro de um caderno: caderno.php)
   A grade dos temas guardados neste caderno. "Novo resumo" já abre
   o editor com este caderno escolhido; "Personalizar" mexe no
   caderno aberto.

   Para TIRAR um resumo daqui existe a doca: ela fica escondida e
   sobe quando um cartão começa a ser arrastado, mostrando os
   outros cadernos e o "Sem caderno" como destinos. Quem não
   arrasta (celular, teclado, leitor de tela) usa o menu ⋮ do
   cartão, que faz exatamente a mesma coisa.

   Quem fala com o servidor são os módulos compartilhados. Esta
   página só reage:
     "resumo:salvo" / "resumo:apagado" / "resumo:movido"
     "caderno:salvo" / "caderno:apagado"
   ============================================================ */
(() => {
    "use strict";

    const grid  = document.getElementById("grid");
    const vazio = document.getElementById("vazio");
    const doca  = document.getElementById("doca");
    if (!grid) return;              // página de "não encontrado"

    const Mover = window.KosmosMover;

    let caderno;
    try {
        caderno = JSON.parse(document.getElementById("dadosCaderno").textContent);
    } catch (err) {
        return;
    }

    let resumos = [];
    try {
        resumos = JSON.parse(document.getElementById("dadosResumos")?.textContent || "[]");
    } catch (err) {
        resumos = [];
    }

    document.addEventListener("DOMContentLoaded", () => {
        // novo resumo já nasce dentro deste caderno
        document.getElementById("btnNovo")?.addEventListener("click", () => {
            window.KosmosResumoForm?.abrir(null, { caderno: caderno.id });
        });

        document.getElementById("btnEditarCaderno")?.addEventListener("click", () => {
            window.KosmosCadernoForm?.abrir(caderno);
        });

        grid.addEventListener("click", cliqueNoResumo);

        ligarArrastes();
    });

    /** Lápis e menu "⋮" dos cartões. */
    function cliqueNoResumo(e) {
        const lapis = e.target.closest("[data-editar]");
        if (lapis) {
            e.preventDefault();
            const resumo = resumos.find((r) => r.id === Number(lapis.dataset.editar));
            if (resumo) window.KosmosResumoForm?.abrir(resumo);
            return;
        }

        const menu = e.target.closest("[data-menu]");
        if (menu) {
            e.preventDefault();
            e.stopPropagation();
            const resumo = resumos.find((r) => r.id === Number(menu.dataset.menu));
            if (resumo) {
                Mover?.abrirMenu(menu, resumo, {
                    aoEditar: () => window.KosmosResumoForm?.abrir(resumo),
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
       ARRASTAR — a doca de destinos
       ============================================================ */
    function ligarArrastes() {
        if (!Mover || !doca) return;

        // começou a arrastar: a doca sobe
        Mover.ligarArrasteDeResumos(grid, () => mostrarDoca(true));
        document.addEventListener("resumo:arraste-fim", () => mostrarDoca(false));

        doca.querySelectorAll(".doca__alvo").forEach((alvo) => {
            const destino = alvo.dataset.destino || null;

            Mover.ligarAlvoDeResumo(alvo, async (resumoId) => {
                mostrarDoca(false);
                await Mover.mover(resumoId, destino);
            });

            /* A doca é feita de <button>: clicar também move o último
               cartão focado não faria sentido, então o clique só
               explica o que ela é. Mover sem arrastar é pelo menu ⋮. */
            alvo.addEventListener("click", () => {
                Mover.avisar("Arraste um resumo até aqui — ou use o menu ⋮ do cartão.");
            });
        });
    }

    function mostrarDoca(mostrar) {
        if (!doca) return;
        doca.hidden = !mostrar;
        doca.setAttribute("aria-hidden", mostrar ? "false" : "true");
        document.body.classList.toggle("com-doca", mostrar);
    }

    /* ============================================================
       RESULTADO DOS EDITORES
       ============================================================ */

    document.addEventListener("resumo:salvo", (e) => {
        const salvo = e.detail?.resumo;
        if (!salvo) return;

        const i = resumos.findIndex((r) => r.id === salvo.id);
        const daqui = Number(salvo.caderno_id) === caderno.id;

        if (i >= 0 && daqui) {
            // mescla: a remoção de imagem manda um resumo parcial
            resumos[i] = { ...resumos[i], ...salvo };
        } else if (i >= 0) {
            resumos.splice(i, 1);      // foi movido para outro caderno
        } else if (daqui) {
            resumos.unshift(salvo);
        } else {
            return;                     // resumo de outro caderno: não é nosso
        }

        desenhar();
    });

    document.addEventListener("resumo:apagado", (e) => {
        const id = e.detail?.id;
        if (!id) return;
        resumos = resumos.filter((r) => r.id !== id);
        desenhar();
    });

    /* Arrastou para a doca (ou usou o menu): saiu daqui */
    document.addEventListener("resumo:movido", (e) => {
        const { resumo, para } = e.detail || {};
        if (!resumo) return;

        if (para === caderno.id) {
            if (!resumos.some((r) => r.id === resumo.id)) {
                resumos.unshift({ ...resumo, quando: "hoje", corpo: "" });
            }
        } else {
            resumos = resumos.filter((r) => r.id !== resumo.id);
        }

        desenhar();
    });

    document.addEventListener("caderno:salvo", (e) => {
        const salvo = e.detail?.caderno;
        if (!salvo || salvo.id !== caderno.id) return;

        caderno = { ...caderno, ...salvo };

        const nome = document.getElementById("cadernoNomeTitulo");
        if (nome) nome.textContent = caderno.nome;

        const ic = document.getElementById("cadernoIconeTitulo");
        if (ic) {
            ic.textContent = caderno.icone || "";
            ic.hidden = !caderno.icone;
        }

        const desc = document.getElementById("cadernoDesc");
        if (desc) {
            desc.textContent = caderno.descricao || "";
            desc.hidden = !caderno.descricao;
        }

        const tag = document.getElementById("cadernoMateriaTag");
        if (tag) tag.textContent = caderno.materia;

        // a cor do caderno tinge a página inteira
        document.body.className = document.body.className
            .replace(/\btema-caderno--\S+/g, "")
            .trim() + ` tema-caderno--${caderno.cor}`;

        // a matéria é do caderno: os resumos de dentro acompanham
        resumos = resumos.map((r) => ({ ...r, materia: caderno.materia }));

        document.title = caderno.nome + " — Kosmos";
        desenhar();
    });

    /* Excluiu o caderno: os resumos dele voltaram para "Sem caderno",
       que fica na estante — não há mais nada para ver aqui. */
    document.addEventListener("caderno:apagado", (e) => {
        if (e.detail?.id === caderno.id) {
            window.location.replace("resumos.php");
        }
    });

    /* ============================================================
       DESENHO
       Mesmo HTML de partes/resumo-card.php (com $mostrarMateria =
       false): o PHP faz a primeira pintura, aqui repintamos.
       ============================================================ */
    const ICONE_LAPIS = '<svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 16h3l8-8-3-3-8 8v3Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M12.5 4.5l3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>';
    const ICONE_MENU  = '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><circle cx="10" cy="4" r="1.6"/><circle cx="10" cy="10" r="1.6"/><circle cx="10" cy="16" r="1.6"/></svg>';
    const ICONE_DOC   = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M9 8h6M9 12h6M9 16h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>';
    const ICONE_FOTO  = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.6"/><circle cx="8.5" cy="10" r="1.6" stroke="currentColor" stroke-width="1.4"/><path d="M4 17l5-4 3 2.5 3-2.5 5 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    function desenhar() {
        if (vazio) vazio.hidden = resumos.length > 0;

        grid.innerHTML = resumos.map((r, i) => `
            <article class="resumo-card anim-in" data-id="${r.id}"
                     data-materia="${escapar(r.materia)}" data-caderno="${r.caderno_id || 0}"
                     draggable="true" style="animation-delay:${(i * 0.04).toFixed(2)}s">
                <a class="resumo-card__link" href="resumo.php?id=${r.id}" draggable="false">
                    <div class="resumo-card__thumb">
                        ${r.fotos > 0
                            ? `${ICONE_FOTO}<span class="resumo-card__fotos">${r.fotos}</span>`
                            : ICONE_DOC}
                    </div>
                    <div class="resumo-card__body">
                        <h3 class="resumo-card__title">${escapar(r.titulo)}</h3>
                        <div class="resumo-card__meta">
                            <span>${escapar(r.quando || "hoje")}</span>
                            <span>Ler →</span>
                        </div>
                    </div>
                </a>
                <div class="resumo-card__acoes">
                    <button type="button" class="resumo-card__botao" data-menu="${r.id}"
                            title="Mover, editar ou apagar" aria-haspopup="menu" aria-expanded="false"
                            aria-label="Ações do resumo ${escapar(r.titulo)}">${ICONE_MENU}</button>
                    <button type="button" class="resumo-card__botao" data-editar="${r.id}"
                            title="Editar este resumo"
                            aria-label="Editar o resumo ${escapar(r.titulo)}">${ICONE_LAPIS}</button>
                </div>
            </article>`).join("");

        atualizarContagem();
    }

    /* "4 resumos · 6 imagens" no cabeçalho */
    function atualizarContagem() {
        const span = document.getElementById("cadernoContagem");
        if (!span) return;

        const n = resumos.length;
        const f = resumos.reduce((s, r) => s + (r.fotos || 0), 0);

        let texto = `${n} ${n === 1 ? "resumo" : "resumos"}`;
        if (f > 0) texto += ` · ${f} ${f === 1 ? "imagem" : "imagens"}`;
        span.textContent = texto;
    }

    /* O texto vem do banco: escapamos antes de jogar em innerHTML */
    function escapar(texto) {
        const div = document.createElement("div");
        div.textContent = texto ?? "";
        return div.innerHTML;
    }
})();
