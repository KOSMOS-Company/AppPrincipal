// ============================================================
//  KOSMOS — combo-select.js
//
//  O <select> de Caderno do modal de resumo com o MESMO design
//  do campo de Matéria (combo-materia.js): o select nativo saía
//  fora do tema — parecia o seletor do sistema, não do Kosmos.
//
//  O select continua sendo a fonte da verdade (value, options,
//  selectedOptions, change) e só fica escondido. Quem aparece no
//  lugar dele é um input readonly com o visual de .campo input +
//  a seta .combo__btn, e a lista .combo__lista mora no body —
//  idêntica à da matéria. Sem JS, o select nativo continua
//  funcionando (fallback).
//
//  Quem mexe no select por fora (resumo-form.js definindo value,
//  definirCadernos() recriando options, caderno.js renomeando a
//  opção) continua sem precisar saber que isto existe: o campo
//  visível acompanha por change, MutationObserver e setter de
//  value reescrito.
// ============================================================
(() => {
    "use strict";

    function montar(select) {
        if (select.dataset.comboSelect) return;
        select.dataset.comboSelect = "1";

        const base   = select.id || "comboSelect";
        const idLista = base + "-lista";
        const idTexto = base + "Texto";

        /* O select fica escondido mas segue no form (value/change). */
        select.hidden = true;

        /* O label passa a apontar para o campo visível: clicar nele
           abre a lista, como num select de verdade. */
        const rotulo = document.querySelector(`label[for="${select.id}"]`);
        if (rotulo) rotulo.htmlFor = idTexto;

        /* Moldura: o select sai do fluxo direto do .campo e ganha a
           seta embaixo dele — visual igual ao da matéria. */
        const volta = document.createElement("div");
        volta.className = "combo";
        select.parentNode.insertBefore(volta, select);
        volta.appendChild(select);

        const campo = document.createElement("input");
        campo.type = "text";
        campo.id = idTexto;
        campo.className = "combo__valor";
        campo.readOnly = true;
        campo.autocomplete = "off";
        campo.setAttribute("role", "combobox");
        campo.setAttribute("aria-haspopup", "listbox");
        campo.setAttribute("aria-expanded", "false");
        campo.setAttribute("aria-controls", idLista);
        volta.appendChild(campo);

        const botao = document.createElement("button");
        botao.type = "button";
        botao.className = "combo__btn";
        botao.tabIndex = -1;               /* quem abre é o campo */
        botao.setAttribute("aria-label", "Ver cadernos");
        botao.innerHTML =
            '<svg viewBox="0 0 12 12" aria-hidden="true"><path d="M2 4.2l4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        volta.appendChild(botao);

        /* A lista mora no body e é posicionada em fixed: o
           .modal__box tem overflow-y: auto e cortaria o dropdown. */
        const lista = document.createElement("ul");
        lista.className = "combo__lista";
        lista.id = idLista;
        lista.setAttribute("role", "listbox");
        lista.hidden = true;
        document.body.appendChild(lista);

        let visiveis = [];
        let ativo = -1;

        /* O texto de cima: o rótulo da opção selecionada. */
        function textoAtual() {
            const opcao = select.selectedOptions[0];
            campo.value = opcao ? opcao.textContent.trim() : "";
        }

        function desenhar() {
            visiveis = [...select.options];
            const escolhida = select.selectedOptions[0];
            lista.innerHTML = "";
            visiveis.forEach((opcao, i) => {
                const item = document.createElement("li");
                item.className = "combo__item";
                item.id = idLista + "-" + i;
                item.setAttribute("role", "option");
                item.textContent = opcao.textContent.trim();
                if (opcao === escolhida) item.setAttribute("aria-selected", "true");
                /* mousedown, não click: acontece ANTES do campo
                   perder o foco, e é o foco que mantém o combo vivo. */
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
                campo.setAttribute("aria-activedescendant", alvo.id);
            } else {
                campo.removeAttribute("aria-activedescendant");
            }
        }

        function posicionar() {
            const r = campo.getBoundingClientRect();
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
            textoAtual();        /* o value pode ter mudado lá fora */
            desenhar();
            lista.hidden = false;
            campo.setAttribute("aria-expanded", "true");
            botao.classList.add("combo__btn--aberto");
            const atual = visiveis.indexOf(select.selectedOptions[0]);
            marcar(atual >= 0 ? atual : 0);
            posicionar();
        }

        function fechar() {
            if (lista.hidden) return;
            lista.hidden = true;
            campo.setAttribute("aria-expanded", "false");
            campo.removeAttribute("aria-activedescendant");
            botao.classList.remove("combo__btn--aberto");
            ativo = -1;
        }

        function escolher(i) {
            const opcao = visiveis[i];
            if (!opcao) return;
            /* focus() antes de fechar: igual ao campo de matéria. */
            campo.focus();
            fechar();
            /* select.value não dispara change sozinho (não é ação
               do usuário): disparamos na mão — é o que o
               aplicarCaderno() do resumo-form.js escuta. */
            select.value = opcao.value;
            select.dispatchEvent(new Event("change", { bubbles: true }));
        }

        campo.addEventListener("focus", abrir);
        campo.addEventListener("click", abrir);

        botao.addEventListener("click", () => {
            if (lista.hidden) { campo.focus(); abrir(); }
            else fechar();
        });

        campo.addEventListener("keydown", (e) => {
            if (e.key === "ArrowDown" || e.key === "ArrowUp") {
                e.preventDefault();
                if (lista.hidden) abrir();
                else if (visiveis.length) {
                    const passo = e.key === "ArrowDown" ? 1 : -1;
                    marcar((ativo + passo + visiveis.length) % visiveis.length);
                }
            } else if (e.key === "Enter" || e.key === " ") {
                e.preventDefault();
                if (lista.hidden) abrir();
                else if (ativo >= 0) escolher(ativo);
            } else if (e.key === "Escape") {
                if (!lista.hidden) {
                    e.stopPropagation();   /* fecha só a lista, não o modal */
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

        /* ------------------------------------------------------
           Sincronia com quem mexe no select por fora
           ------------------------------------------------------ */

        /* escolher() dispara change; resumo-form.js também pode. */
        select.addEventListener("change", textoAtual);

        /* definirCadernos() recria as options e caderno.js renomeia
           uma opção: mutação no DOM, o observer vê. */
        new MutationObserver(textoAtual).observe(select, {
            childList: true, subtree: true, characterData: true,
        });

        /* `.value =` é atribuição de JS e não mexe no DOM — o
           setter do prototype, reescrito só neste select, dá o
           aviso (abrir() do modal, definirCadernos() resetando). */
        const valor = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, "value");
        Object.defineProperty(select, "value", {
            configurable: true,
            get() { return valor.get.call(this); },
            set(v) { valor.set.call(this, v); textoAtual(); },
        });

        textoAtual();
    }

    function iniciar() {
        document.querySelectorAll("select.combo-select").forEach(montar);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", iniciar);
    } else {
        iniciar();
    }
})();
