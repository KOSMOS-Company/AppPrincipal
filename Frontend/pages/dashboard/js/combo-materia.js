// ============================================================
//  KOSMOS — combo-materia.js
//  Campo de Matéria com lista + texto livre.
//
//  O HTML chega como <input list="listaMateriasX"> + <datalist>
//  (renderizado pelo PHP com MATERIAS_KOSMOS). Aqui cada um vira
//  um combobox com o dropdown NO ESTILO DO APP: o popup nativo do
//  datalist não aceita CSS e saía fora do tema — parecia o seletor
//  do sistema, não parte do Kosmos.
//
//  O <datalist> é a fonte das opções; depois de montar, o atributo
//  `list` sai do input para o popup nativo não aparecer em dobro.
//  Sem JS, o datalist nativo continua funcionando (fallback).
//
//  Comportamento: clique/foco abre a lista; digitando filtra
//  (sem acento/maiúsculas); setas navegam; Enter escolhe — e,
//  se o que ficou digitado não for da lista, é exatamente a
//  matéria livre que vai pro servidor. Esc/clique fora fecha.
// ============================================================
(function () {
    "use strict";

    let seq = 0;

    /* "Matemática" e "matematica" filtram igual. */
    function plano(txt) {
        return txt.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
    }

    function montar(input) {
        if (input.dataset.comboMateria) return;
        input.dataset.comboMateria = "1";

        const datalist = document.getElementById(input.getAttribute("list") || "");
        if (!datalist) return;

        const opcoes = [...datalist.querySelectorAll("option")]
            .map((o) => o.value)
            .filter(Boolean);
        if (!opcoes.length) return;

        /* O popup daqui pra cima é o nosso; o nativo sairia em dobro. */
        input.removeAttribute("list");
        input.setAttribute("role", "combobox");
        input.setAttribute("aria-autocomplete", "list");
        input.setAttribute("aria-expanded", "false");
        input.setAttribute("autocomplete", "off");

        const idLista = "comboMaterias" + (++seq);
        input.setAttribute("aria-controls", idLista);

        /* Moldura: o input sai do fluxo direto do .campo e ganha a
           seta embaixo dele — visual igual ao <select> de antes. */
        const volta = document.createElement("div");
        volta.className = "combo";
        input.parentNode.insertBefore(volta, input);
        volta.appendChild(input);

        const botao = document.createElement("button");
        botao.type = "button";
        botao.className = "combo__btn";
        botao.tabIndex = -1;               /* quem abre é o campo */
        botao.setAttribute("aria-label", "Ver matérias");
        botao.innerHTML =
            '<svg viewBox="0 0 12 12" aria-hidden="true"><path d="M2 4.2l4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        volta.appendChild(botao);

        /* A lista mora no body e é posicionada em fixed: o .modal__box
           tem overflow-y: auto e cortaria o dropdown (na sua imagem ele
           vaza por baixo do modal, como num select de verdade). */
        const lista = document.createElement("ul");
        lista.className = "combo__lista";
        lista.id = idLista;
        lista.setAttribute("role", "listbox");
        lista.hidden = true;
        document.body.appendChild(lista);

        let visiveis = opcoes.slice();
        let ativo = -1;
        let escolhendo = false;   /* verdadeiro só durante o dispatch
                                     de escolher(): evita que o próprio
                                     evento "input" reabra a lista. */

        function desenhar() {
            const filtro = plano(input.value.trim());
            visiveis = opcoes.filter((o) => !filtro || plano(o).includes(filtro));

            lista.innerHTML = "";
            if (!visiveis.length) {
                const vazio = document.createElement("li");
                vazio.className = "combo__vazio";
                vazio.textContent = "Sem correspondência — o que você digitou vira a matéria";
                lista.appendChild(vazio);
                return;
            }
            visiveis.forEach((valor, i) => {
                const item = document.createElement("li");
                item.className = "combo__item";
                item.id = idLista + "-" + i;
                item.setAttribute("role", "option");
                item.textContent = valor;
                /* mousedown, não click: acontece ANTES do input perder
                   o foco, e é o foco que mantém o campo decente. */
                item.addEventListener("mousedown", (e) => {
                    e.preventDefault();
                    escolher(i);
                });
                lista.appendChild(item);
            });
        }

        function marcar(i) {
            ativo = i;
            const itens = lista.querySelectorAll(".combo__item");
            itens.forEach((el, k) => el.classList.toggle("is-ativo", k === i));
            const alvo = i >= 0 ? itens[i] : null;
            /* Rola SÓ a lista, na mão: scrollIntoView também rola o
               .modal__box, e o listener de scroll fecharia o popup. */
            if (alvo) {
                const topo = alvo.offsetTop;
                const base = topo + alvo.offsetHeight;
                if (topo < lista.scrollTop) lista.scrollTop = topo;
                else if (base > lista.scrollTop + lista.clientHeight) {
                    lista.scrollTop = base - lista.clientHeight;
                }
                input.setAttribute("aria-activedescendant", alvo.id);
            } else {
                input.removeAttribute("aria-activedescendant");
            }
        }

        function posicionar() {
            const r = input.getBoundingClientRect();
            lista.style.width = r.width + "px";
            lista.style.left = r.left + "px";
            lista.style.top = "";

            /* Pouco espaço embaixo? Sobe, igual o select nativo. */
            const alt = lista.offsetHeight;
            if (window.innerHeight - r.bottom < alt + 12 && r.top > alt + 12) {
                lista.style.top = Math.max(8, r.top - alt - 6) + "px";
            } else {
                lista.style.top = r.bottom + 6 + "px";
            }
        }

        function abrir() {
            if (!lista.hidden) return;
            desenhar();
            lista.hidden = false;
            input.setAttribute("aria-expanded", "true");
            botao.classList.add("combo__btn--aberto");
            marcar(visiveis.length ? 0 : -1);
            posicionar();
        }

        function fechar() {
            if (lista.hidden) return;
            lista.hidden = true;
            input.setAttribute("aria-expanded", "false");
            input.removeAttribute("aria-activedescendant");
            botao.classList.remove("combo__btn--aberto");
            ativo = -1;
        }

        function escolher(i) {
            const valor = visiveis[i];
            if (valor === undefined) return;
            input.value = valor;
            /* focus() antes de fechar: se ele chegar a disparar o
               handler de focus (abrindo a lista), o fechar() logo
               abaixo fecha de novo — o campo nunca fica aberto
               depois de escolher. */
            input.focus();
            fechar();
            /* Dispara os mesmos eventos do select: as prévias de
               caderno/matéria escutam "input"/"change". */
            escolhendo = true;
            input.dispatchEvent(new Event("input", { bubbles: true }));
            input.dispatchEvent(new Event("change", { bubbles: true }));
            escolhendo = false;
        }

        input.addEventListener("focus", abrir);
        input.addEventListener("click", abrir);

        input.addEventListener("input", () => {
            if (escolhendo) return;
            if (lista.hidden) { abrir(); return; }
            desenhar();
            marcar(visiveis.length ? 0 : -1);
            posicionar();
        });

        botao.addEventListener("click", () => {
            if (lista.hidden) { input.focus(); abrir(); }
            else fechar();
        });

        input.addEventListener("keydown", (e) => {
            if (e.key === "ArrowDown") {
                e.preventDefault();
                if (lista.hidden) abrir();
                else if (visiveis.length) marcar((ativo + 1) % visiveis.length);
            } else if (e.key === "ArrowUp") {
                e.preventDefault();
                if (lista.hidden) abrir();
                else if (visiveis.length) marcar((ativo - 1 + visiveis.length) % visiveis.length);
            } else if (e.key === "Enter") {
                if (lista.hidden) return;          /* submete o form */
                if (ativo >= 0) {
                    e.preventDefault();            /* não submete o form */
                    escolher(ativo);
                } else {
                    /* só texto livre (nada na lista): deixa o Enter
                       passar — o form submete com o que foi digitado */
                    fechar();
                }
            } else if (e.key === "Escape") {
                if (!lista.hidden) {
                    e.stopPropagation();
                    fechar();
                }
            } else if (e.key === "Tab") {
                fechar();
            }
        });

        document.addEventListener("mousedown", (e) => {
            if (lista.hidden) return;
            if (volta.contains(e.target) || lista.contains(e.target)) return;
            fechar();
        });

        window.addEventListener("resize", fechar);
        window.addEventListener("blur", fechar);
        /* Captura: fecha quando o .modal__box rola por baixo — mas
           NÃO quando quem rola é a própria lista (overflow interno). */
        window.addEventListener("scroll", (e) => {
            if (e.target === lista || lista.contains(e.target)) return;
            fechar();
        }, true);
    }

    function iniciar() {
        document.querySelectorAll("input[list]").forEach(montar);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", iniciar);
    } else {
        iniciar();
    }
})();
