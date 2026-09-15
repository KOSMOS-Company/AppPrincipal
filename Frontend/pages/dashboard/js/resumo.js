/* ============================================================
   KOSMOS — resumo.js  (só a prévia: resumo.php)
   A página serve para LER. Aqui ligamos o botão "Editar" ao editor
   compartilhado (resumo-form.js), reagimos ao resultado (salvar
   atualiza o texto e as imagens na tela, apagar volta para a lista)
   e cuidamos da lupa — a imagem em tamanho grande.
   ============================================================ */
(() => {
    "use strict";

    const cru = document.getElementById("dadosResumo");
    if (!cru) return;                 // página de "não encontrado"

    let resumo;
    try {
        resumo = JSON.parse(cru.textContent);
    } catch (err) {
        return;
    }

    const galeria = document.getElementById("galeria");

    document.addEventListener("DOMContentLoaded", () => {
        document.getElementById("btnEditar")?.addEventListener("click", () => {
            window.KosmosResumoForm?.abrir(resumo);
        });

        /* Virar o resumo em flashcards. O botão sai da tela se o módulo
           não estiver aqui (página sem o partes/modal-flashcards.php):
           um botão que não faz nada é pior do que botão nenhum — a mesma
           regra do "Modo revisão" logo ao lado. */
        const btnFc = document.getElementById("btnFlashcards");
        if (btnFc) {
            if (window.KosmosGerarFlashcards) {
                btnFc.addEventListener("click", () => {
                    window.KosmosGerarFlashcards.abrir(resumo);
                });
            } else {
                btnFc.hidden = true;
            }
        }

        // delegação: a galeria é redesenhada quando as imagens mudam
        galeria?.addEventListener("click", (e) => {
            const item = e.target.closest("[data-lupa]");
            if (item) abrirLupa(Number(item.dataset.lupa));
        });

        ligarLupa();
    });

    /* ------------------------------------------------------------
       Resultado do editor
       ------------------------------------------------------------ */

    /* Salvou: atualiza o que está na tela sem recarregar a página */
    document.addEventListener("resumo:salvo", (e) => {
        const novo = e.detail?.resumo;
        if (!novo || novo.id !== resumo.id) return;

        // mescla: a remoção de uma imagem manda um resumo parcial
        resumo = { ...resumo, ...novo };

        const titulo = document.querySelector(".contCabeca__texto h1");
        if (titulo) titulo.textContent = resumo.titulo;

        const tag = document.querySelector(".resumo-meta .materia-tag");
        if (tag) tag.textContent = resumo.materia;

        // texto: pode ter sumido (resumo que virou só imagem)
        const corpo = resumo.corpo || "";
        const leitura = document.getElementById("leitura");
        if (leitura) {
            leitura.textContent = corpo;
            leitura.hidden = corpo === "";
        }
        const semTexto = document.getElementById("semTexto");
        if (semTexto) semTexto.hidden = corpo !== "";

        const palavras = document.getElementById("metaPalavras");
        if (palavras) {
            const n = corpo.trim() === "" ? 0 : corpo.trim().split(/\s+/).length;
            palavras.hidden = n === 0;
            palavras.textContent = `· ${n} ${n === 1 ? "palavra" : "palavras"}`;
        }

        /* O resumo pode ter mudado de caderno: o link de voltar tem
           de ir para o lugar novo. O nome do caderno não vem no
           evento, então mostramos um rótulo genérico até a próxima
           carga da página, que traz o nome de verdade. */
        const voltar = document.getElementById("voltar");
        const voltarTexto = document.getElementById("voltarTexto");
        if (voltar && voltarTexto) {
            const antes = voltar.getAttribute("href");
            const agora = resumo.caderno_id ? `caderno.php?id=${resumo.caderno_id}` : "resumos.php";
            if (antes !== agora) {
                voltar.setAttribute("href", agora);
                voltarTexto.textContent = resumo.caderno_id ? "Caderno" : "Cadernos";
            }
        }

        desenharGaleria();
        document.title = resumo.titulo + " — Kosmos";
    });

    /* Apagou: não há mais o que ler aqui */
    document.addEventListener("resumo:apagado", (e) => {
        if (e.detail?.id !== resumo.id) return;
        window.location.replace(
            resumo.caderno_id ? `caderno.php?id=${resumo.caderno_id}` : "resumos.php"
        );
    });

    /* ------------------------------------------------------------
       Galeria
       ------------------------------------------------------------ */
    function desenharGaleria() {
        if (!galeria) return;

        const imagens = resumo.imagens || [];
        galeria.hidden = imagens.length === 0;

        galeria.innerHTML = imagens.map((img, i) => `
            <button type="button" class="resumo-galeria__item" data-lupa="${i}"
                    aria-label="Ver a imagem ${i + 1} em tamanho grande">
                <img src="${escapar(img.url)}" alt="${escapar(img.legenda || `Imagem ${i + 1} do resumo`)}" loading="lazy">
            </button>`).join("");

        const meta = document.getElementById("metaFotos");
        if (meta) {
            meta.hidden = imagens.length === 0;
            meta.textContent = `· ${imagens.length} ${imagens.length === 1 ? "imagem" : "imagens"}`;
        }
    }

    /* ------------------------------------------------------------
       Lupa (imagem em tamanho grande)
       ------------------------------------------------------------ */
    const lupa = document.getElementById("lupa");
    let atual = 0;

    function ligarLupa() {
        if (!lupa) return;

        document.getElementById("lupaFechar")?.addEventListener("click", fecharLupa);
        document.getElementById("lupaAnterior")?.addEventListener("click", () => andar(-1));
        document.getElementById("lupaProxima")?.addEventListener("click", () => andar(1));

        // clicar no fundo (fora da imagem e dos botões) fecha
        lupa.addEventListener("click", (e) => { if (e.target === lupa) fecharLupa(); });

        document.addEventListener("keydown", (e) => {
            if (lupa.hidden) return;
            if (e.key === "Escape")     { e.preventDefault(); fecharLupa(); }
            if (e.key === "ArrowLeft")  { e.preventDefault(); andar(-1); }
            if (e.key === "ArrowRight") { e.preventDefault(); andar(1); }
        });
    }

    function abrirLupa(i) {
        const imagens = resumo.imagens || [];
        if (!lupa || !imagens.length) return;

        atual = i;
        pintarLupa();
        lupa.hidden = false;
        document.body.classList.add("sem-rolagem");
        document.getElementById("lupaFechar")?.focus();
    }

    function fecharLupa() {
        if (!lupa) return;
        lupa.hidden = true;
        document.body.classList.remove("sem-rolagem");
    }

    /** Anda na galeria dando a volta: da última vai para a primeira. */
    function andar(passo) {
        const total = (resumo.imagens || []).length;
        if (total < 2) return;
        atual = (atual + passo + total) % total;
        pintarLupa();
    }

    function pintarLupa() {
        const imagens = resumo.imagens || [];
        const img = imagens[atual];
        if (!img) { fecharLupa(); return; }

        const alvo = document.getElementById("lupaImg");
        alvo.src = img.url;
        alvo.alt = img.legenda || `Imagem ${atual + 1} do resumo`;

        const conta = document.getElementById("lupaConta");
        if (conta) {
            conta.hidden = imagens.length < 2;
            conta.textContent = `${atual + 1} / ${imagens.length}`;
        }

        // com uma imagem só, as setas não têm para onde ir
        const sozinha = imagens.length < 2;
        document.getElementById("lupaAnterior").hidden = sozinha;
        document.getElementById("lupaProxima").hidden  = sozinha;
    }

    /* URLs e legendas entram em innerHTML: escapamos antes */
    function escapar(texto) {
        const div = document.createElement("div");
        div.textContent = texto ?? "";
        return div.innerHTML;
    }
})();
