/* ============================================================
   KOSMOS — provas.js  (só a página Provas / provas.php)

   A tela toda sai de UMA requisição a Backend/php/provas.php, e
   toda ação devolve as listas inteiras já atualizadas. Por isso
   não existe estado paralelo aqui: o servidor é a verdade, e
   `pintar()` redesenha o que ele mandou. Foi de propósito — a
   alternativa (remendar o array local a cada clique) inventa uma
   segunda fonte de verdade que cedo ou tarde discorda da primeira.

   NENHUMA DATA É CALCULADA AQUI. "faltam 3 dias" e "12 jun" chegam
   prontos do MySQL (DATEDIFF, DAY, MONTH) porque o PHP e o banco
   deste projeto rodam em fusos diferentes — ver "Problemas
   conhecidos" no README. A única data que sai do relógio do
   navegador é o `min` do seletor, que é escolha da pessoa.

   Tudo dentro de uma IIFE: o dashboard.js e os scripts de página
   dividem o mesmo escopo global.
   ============================================================ */
(() => {
    "use strict";

    const API = "../../../Backend/php/provas.php";

    /* O que o servidor mandou da última vez. Só para reabrir o
       modal de edição com os valores certos e para saber o que a
       confirmação de apagar deve dizer. */
    let proximas  = [];
    let passadas  = [];
    let materiais = {};

    /* Quais provas estão com os detalhes abertos. Como a lista é
       redesenhada inteira a cada ação, sem isto o painel fecharia
       na cara da pessoa toda vez que ela marcasse um assunto. */
    const abertas = new Set();

    const el = {
        lista:     document.getElementById("pvLista"),
        vazio:     document.getElementById("pvVazio"),
        conta:     document.getElementById("pvProximasConta"),
        passadas:  document.getElementById("pvPassadas"),
        listaPass: document.getElementById("pvListaPassadas"),
        media:     document.getElementById("pvMedia"),
        destaque:  document.getElementById("pvDestaque"),
    };

    const modal = {
        caixa:     document.getElementById("modalProva"),
        form:      document.getElementById("provaForm"),
        id:        document.getElementById("provaId"),
        titulo:    document.getElementById("provaTitulo"),
        nome:      document.getElementById("provaNome"),
        materia:   document.getElementById("provaMateria"),
        data:      document.getElementById("provaData"),
        topicos:   document.getElementById("provaTopicos"),
        topCampo:  document.getElementById("provaTopicosCampo"),
        anotacoes: document.getElementById("provaAnotacoes"),
        msg:       document.getElementById("provaMsg"),
        salvar:    document.getElementById("provaSalvar"),
    };

    document.addEventListener("DOMContentLoaded", () => {
        if (!el.lista) return;    // outra aba: o script nem tem alvo

        document.getElementById("btnNovaProva")?.addEventListener("click", () => abrirModal(null));
        document.getElementById("provaFechar")?.addEventListener("click", fecharModal);
        document.getElementById("provaCancelar")?.addEventListener("click", fecharModal);
        modal.form?.addEventListener("submit", salvarProva);
        modal.caixa?.addEventListener("click", (e) => { if (e.target === modal.caixa) fecharModal(); });

        // Delegação nas duas listas: os cartões são redesenhados o
        // tempo todo, e religar ouvinte por botão a cada pintura é
        // trabalho repetido (e vazamento fácil de esquecer).
        el.lista.addEventListener("click", noClique);
        el.listaPass.addEventListener("click", noClique);
        el.lista.addEventListener("submit", noEnvio);
        el.listaPass.addEventListener("submit", noEnvio);
        el.lista.addEventListener("toggle", noAbrirFechar, true);
        el.listaPass.addEventListener("toggle", noAbrirFechar, true);

        carregar();
    });

    /* ------------------------------------------------------------
       CONVERSA COM O SERVIDOR
       ------------------------------------------------------------ */

    /** GET inicial. */
    async function carregar() {
        try {
            const r = await fetch(API, { credentials: "same-origin" });
            const j = await r.json();
            if (j.ok) guardarEPintar(j);
        } catch {
            avisar("Sem conexão com o servidor.", true);
        }
    }

    /**
     * Uma ação qualquer. Devolve o JSON (ou null se falhou) para
     * quem chamou decidir o resto — o modal, por exemplo, só fecha
     * quando o servidor confirmou.
     */
    async function acao(corpo) {
        try {
            const r = await fetch(API, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                credentials: "same-origin",
                body: JSON.stringify(corpo),
            });
            const j = await r.json();

            if (!j.ok) {
                avisar(j.msg || "Não foi possível salvar.", true);
                return null;
            }

            guardarEPintar(j);
            return j;
        } catch {
            avisar("Sem conexão com o servidor.", true);
            return null;
        }
    }

    function guardarEPintar(j) {
        /* A lista é redesenhada inteira, então o elemento que estava
           com o foco deixa de existir — e quem navega pelo teclado
           seria jogado para o começo da página a cada caixinha
           marcada. Guardamos como achá-lo de novo depois. */
        const foco = marcaDoFoco();

        proximas  = Array.isArray(j.provas)   ? j.provas   : [];
        passadas  = Array.isArray(j.passadas) ? j.passadas : [];
        materiais = j.materiais || {};
        pintar();

        if (foco) document.querySelector(foco)?.focus();
    }

    /** Um seletor que reencontra o elemento focado depois da pintura. */
    function marcaDoFoco() {
        const alvo = document.activeElement;
        if (!alvo || alvo === document.body) return null;

        // ids são numéricos e vêm do banco: cabem num seletor sem escape
        if (alvo.dataset?.topico)   return `[data-topico="${alvo.dataset.topico}"]`;
        if (alvo.dataset?.topicoX)  return `[data-topico-x="${alvo.dataset.topicoX}"]`;

        const add = alvo.closest?.(".pv-add");
        if (add) return `.pv-add[data-prova="${add.dataset.prova}"] input`;

        return null;
    }

    /* ------------------------------------------------------------
       DESENHO
       ------------------------------------------------------------ */

    function pintar() {
        el.vazio.hidden = proximas.length > 0;
        el.conta.textContent = proximas.length
            ? `${proximas.length} ${proximas.length === 1 ? "marcada" : "marcadas"}`
            : "";

        el.lista.innerHTML = proximas.map((p) => cartao(p, false)).join("");

        el.passadas.hidden = passadas.length === 0;
        el.listaPass.innerHTML = passadas.map((p) => cartao(p, true)).join("");
        el.media.textContent = textoDaMedia();

        pintarDestaque();
    }

    /** A próxima prova, grande, no topo. */
    function pintarDestaque() {
        const p = proximas[0];
        el.destaque.hidden = !p;
        if (!p) return;

        const [num, unidade] = contagem(p.faltam);
        document.getElementById("pvDestaqueNum").textContent      = num;
        document.getElementById("pvDestaqueUnidade").textContent  = unidade;
        document.getElementById("pvDestaqueTitulo").textContent   = p.titulo;
        document.getElementById("pvDestaqueData").textContent     = p.por_extenso;

        const tag = document.getElementById("pvDestaqueMateria");
        tag.textContent = p.materia || "";
        tag.hidden = !p.materia;

        // Faltando uma semana ou menos, a contagem acende. É a única
        // diferença visual do painel — e a que importa.
        el.destaque.classList.toggle("pv-destaque--perto", p.faltam <= 7);

        const caixa = document.getElementById("pvDestaqueProgresso");
        const total = p.topicos.length;
        caixa.hidden = total === 0;
        if (total > 0) {
            const barra = document.getElementById("pvDestaqueBarra");
            barra.max   = total;
            barra.value = p.feitos;
            document.getElementById("pvDestaqueTexto").textContent =
                p.feitos === total
                    ? "tudo estudado ✦"
                    : `${p.feitos} de ${total} assuntos estudados`;
        }
    }

    /** Um cartão de prova. `passada` troca a contagem pelo resultado. */
    function cartao(p, passada) {
        const [num, unidade] = contagem(p.faltam);
        const aberta = abertas.has(p.id);
        const total  = p.topicos.length;

        const selo = passada
            ? (p.nota === null
                ? '<span class="pv-card__conta pv-card__conta--fria">sem nota</span>'
                : `<span class="pv-card__conta pv-card__conta--nota">${esc(formatarNota(p.nota))}</span>`)
            : `<span class="pv-card__conta"><strong>${esc(num)}</strong><i>${esc(unidade)}</i></span>`;

        return `
        <li class="pv-card${!passada && p.faltam <= 7 ? " pv-card--perto" : ""}" data-id="${p.id}">
            <details class="pv-card__det"${aberta ? " open" : ""}>
                <summary class="pv-card__topo">
                    ${selo}
                    <span class="pv-card__texto">
                        <span class="pv-card__nome">${esc(p.titulo)}</span>
                        <span class="pv-card__meta">
                            ${p.materia ? `<span class="materia-tag">${esc(p.materia)}</span>` : ""}
                            <span>${esc(p.por_extenso)}</span>
                            ${total ? `<span class="pv-card__prog">${p.feitos}/${total} assuntos</span>` : ""}
                        </span>
                    </span>
                    <span class="pv-card__seta" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="none"><path d="M6 8l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                </summary>

                <div class="pv-card__corpo">
                    ${blocoAssuntos(p)}
                    ${blocoAnotacoes(p)}
                    ${blocoMaterial(p)}
                    ${passada ? blocoNota(p) : ""}

                    <div class="pv-card__acoes">
                        <button type="button" class="dash-btn dash-btn--outline dash-btn--pequeno" data-editar="${p.id}">Editar</button>
                        <button type="button" class="dash-btn dash-btn--danger dash-btn--pequeno" data-apagar="${p.id}">Apagar</button>
                    </div>
                </div>
            </details>
        </li>`;
    }

    /** A lista do que cai — o coração do cartão. */
    function blocoAssuntos(p) {
        const itens = p.topicos.map((t) => `
            <li class="pv-top${t.feito ? " pv-top--feito" : ""}">
                <button type="button" class="pv-top__check" data-topico="${t.id}"
                        aria-pressed="${t.feito}"
                        aria-label="${t.feito ? "Desmarcar" : "Marcar como estudado"}: ${esc(t.texto)}">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12.5 L10 17.5 L19 7" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
                <span class="pv-top__texto">${esc(t.texto)}</span>
                <button type="button" class="pv-top__x" data-topico-x="${t.id}"
                        aria-label="Tirar da lista: ${esc(t.texto)}">&times;</button>
            </li>`).join("");

        return `
        <div class="pv-bloco">
            <h4 class="pv-bloco__titulo">O que cai</h4>
            ${p.topicos.length
                ? `<ul class="pv-tops">${itens}</ul>`
                : '<p class="pv-bloco__vazio">Nada listado ainda. Escreva o primeiro assunto abaixo.</p>'}
            <form class="pv-add" data-prova="${p.id}">
                <label class="sr-only" for="pvAdd${p.id}">Novo assunto</label>
                <input type="text" id="pvAdd${p.id}" maxlength="120" autocomplete="off"
                       placeholder="Adicionar assunto…">
                <button type="submit" class="dash-btn dash-btn--ghost dash-btn--pequeno">Adicionar</button>
            </form>
        </div>`;
    }

    function blocoAnotacoes(p) {
        if (!p.anotacoes) return "";
        return `
        <div class="pv-bloco">
            <h4 class="pv-bloco__titulo">Anotações</h4>
            <p class="pv-anotacoes">${esc(p.anotacoes)}</p>
        </div>`;
    }

    /**
     * O material que a pessoa já tem daquela matéria. É o que separa
     * esta tela de um calendário: em vez de dizer só "tem prova em 3
     * dias", ela mostra por onde começar.
     */
    function blocoMaterial(p) {
        const m = p.materia ? materiais[p.materia] : null;
        if (!m) return "";

        const cadernos = (m.cadernos || []).map((c) => `
            <a class="pv-mat" href="caderno.php?id=${c.id}">
                <span class="pv-mat__ico" aria-hidden="true">${esc(c.icone || "📙")}</span>
                <span class="pv-mat__nome">${esc(c.nome)}</span>
                <span class="pv-mat__n">${c.resumos} ${c.resumos === 1 ? "resumo" : "resumos"}</span>
            </a>`).join("");

        const decks = (m.decks || []).map((d) => `
            <a class="pv-mat" href="flashcards.php?deck=${d.id}">
                <span class="pv-mat__ico" aria-hidden="true"><svg class="ico" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="8.4" y="3.2" width="12" height="15.2" rx="2.2"/><path d="M15.6 20.8H6.2a2.6 2.6 0 0 1-2.6-2.6V7.6"/></svg></span>
                <span class="pv-mat__nome">${esc(d.nome)}</span>
                <span class="pv-mat__n">${d.cartoes} ${d.cartoes === 1 ? "cartão" : "cartões"}</span>
            </a>`).join("");

        if (!cadernos && !decks) {
            return `
            <div class="pv-bloco">
                <h4 class="pv-bloco__titulo">Material de ${esc(p.materia)}</h4>
                <p class="pv-bloco__vazio">Você ainda não tem cadernos nem baralhos de ${esc(p.materia)}.</p>
            </div>`;
        }

        return `
        <div class="pv-bloco">
            <h4 class="pv-bloco__titulo">Material de ${esc(p.materia)}</h4>
            <div class="pv-mats">${cadernos}${decks}</div>
        </div>`;
    }

    /** O depois: a nota. Só nas provas que já passaram. */
    function blocoNota(p) {
        return `
        <div class="pv-bloco">
            <h4 class="pv-bloco__titulo">Como foi</h4>
            <form class="pv-nota" data-nota="${p.id}">
                <label class="sr-only" for="pvNota${p.id}">Nota da prova</label>
                <input type="number" id="pvNota${p.id}" step="0.01" min="0" max="1000"
                       inputmode="decimal" placeholder="Nota"
                       value="${p.nota === null ? "" : esc(formatarNota(p.nota))}">
                <button type="submit" class="dash-btn dash-btn--ghost dash-btn--pequeno">Salvar nota</button>
            </form>
            <span class="campo__dica">Deixe em branco e salve para apagar a nota.</span>
        </div>`;
    }

    /* ------------------------------------------------------------
       CLIQUES E ENVIOS (delegados)
       ------------------------------------------------------------ */

    function noAbrirFechar(e) {
        const det = e.target;
        if (!det.matches?.(".pv-card__det")) return;
        const id = Number(det.closest(".pv-card")?.dataset.id);
        if (!id) return;
        det.open ? abertas.add(id) : abertas.delete(id);
    }

    async function noClique(e) {
        const check = e.target.closest("[data-topico]");
        if (check) {
            check.disabled = true;
            await acao({ acao: "topico_alternar", id: Number(check.dataset.topico) });
            return;
        }

        const tirar = e.target.closest("[data-topico-x]");
        if (tirar) {
            tirar.disabled = true;
            await acao({ acao: "topico_excluir", id: Number(tirar.dataset.topicoX) });
            return;
        }

        const editar = e.target.closest("[data-editar]");
        if (editar) {
            abrirModal(acharProva(Number(editar.dataset.editar)));
            return;
        }

        const apagar = e.target.closest("[data-apagar]");
        if (apagar) {
            await apagarProva(acharProva(Number(apagar.dataset.apagar)));
        }
    }

    async function noEnvio(e) {
        e.preventDefault();
        const form = e.target;

        if (form.matches(".pv-add")) {
            const campo = form.querySelector("input");
            const texto = campo.value.trim();
            if (!texto) return;

            campo.disabled = true;
            const ok = await acao({
                acao: "topico_criar",
                prova: Number(form.dataset.prova),
                texto,
            });
            // Deu errado: o campo volta com o que a pessoa escreveu —
            // ninguém quer digitar de novo por causa de uma rede ruim.
            if (!ok) { campo.disabled = false; campo.focus(); }
            return;
        }

        if (form.matches(".pv-nota")) {
            const campo = form.querySelector("input");
            const j = await acao({
                acao: "resultado",
                id: Number(form.dataset.nota),
                nota: campo.value.trim(),
            });
            if (j) avisar(campo.value.trim() ? "Nota registrada." : "Nota apagada.");
        }
    }

    async function apagarProva(p) {
        if (!p) return;

        /* confirmar() é o modal compartilhado do dashboard.js. Apagar
           leva junto os assuntos da prova, e isso não volta. */
        const ok = window.confirmar
            ? await window.confirmar({
                  titulo: "Apagar prova",
                  texto: `"${p.titulo}" sai da sua lista, com os assuntos marcados. Isso não volta.`,
                  botao: "Apagar",
                  perigo: true,
              })
            : true;
        if (!ok) return;

        abertas.delete(p.id);
        const j = await acao({ acao: "excluir", id: p.id });
        if (j) avisar("Prova apagada.");
    }

    /* ------------------------------------------------------------
       O MODAL — cria e edita
       ------------------------------------------------------------ */

    function abrirModal(prova) {
        if (!modal.caixa) return;

        modal.form.reset();
        modal.msg.hidden = true;
        modal.id.value = prova ? String(prova.id) : "";

        modal.titulo.textContent = prova ? "Editar prova" : "Nova prova";
        modal.salvar.textContent = prova ? "Salvar" : "Adicionar";

        /* Os assuntos só entram no cadastro. Depois que a prova
           existe, cada um deles é uma caixinha na lista — reescrever
           tudo por um textarea apagaria o que já foi marcado. */
        modal.topCampo.hidden = !!prova;

        if (prova) {
            modal.nome.value      = prova.titulo;
            modal.materia.value   = prova.materia || "";
            modal.data.value      = prova.data;
            modal.anotacoes.value = prova.anotacoes || "";
            // Editando, a data pode ser passada: corrigir o dia de uma
            // prova que já aconteceu é uso legítimo.
            modal.data.removeAttribute("min");
        } else {
            /* O `min` sai do relógio do NAVEGADOR, não do servidor (ver
               o comentário em partes/modal-prova.php): o PHP deste
               projeto roda em Europe/Berlin e à noite, no Brasil,
               bloquearia o dia de hoje. `sv-SE` porque esse locale
               formata como YYYY-MM-DD, que é o que o <input type="date">
               espera — e faz isso no fuso local, ao contrário de
               toISOString(), que converte para UTC e erra o dia perto
               da meia-noite. */
            modal.data.min = new Date().toLocaleDateString("sv-SE");
        }

        modal.caixa.classList.add("open");
        modal.nome.focus();
    }

    function fecharModal() {
        modal.caixa?.classList.remove("open");
    }

    function avisoModal(texto) {
        modal.msg.textContent = texto;
        modal.msg.className = "msg msg--erro";
        modal.msg.hidden = false;
    }

    async function salvarProva(e) {
        e.preventDefault();

        const titulo = modal.nome.value.trim();
        if (!titulo)           return avisoModal("Dê um nome para a prova.");
        if (!modal.data.value) return avisoModal("Escolha a data da prova.");

        const id = Number(modal.id.value) || 0;

        const corpo = {
            acao: id ? "editar" : "criar",
            titulo,
            materia: modal.materia.value,
            data: modal.data.value,
            anotacoes: modal.anotacoes.value.trim(),
        };
        if (id) {
            corpo.id = id;
        } else {
            corpo.topicos = modal.topicos.value
                .split("\n")
                .map((t) => t.trim())
                .filter(Boolean);
        }

        modal.salvar.disabled = true;
        const j = await acao(corpo);
        modal.salvar.disabled = false;

        if (!j) return;   // a mensagem do servidor já foi mostrada no aviso

        // Prova nova nasce com os detalhes abertos: quem acabou de
        // cadastrar costuma querer completar a lista do que cai.
        if (!id && proximas.length) {
            const nova = proximas.find((p) => p.titulo === titulo && p.data === corpo.data);
            if (nova) { abertas.add(nova.id); pintar(); }
        }

        fecharModal();
        avisar(id ? "Prova atualizada." : "Prova marcada.");
    }

    /* ------------------------------------------------------------
       MIUDEZAS
       ------------------------------------------------------------ */

    function acharProva(id) {
        return proximas.find((p) => p.id === id) || passadas.find((p) => p.id === id) || null;
    }

    /** "hoje" / "amanhã" / "12 dias" — a partir do DATEDIFF do MySQL. */
    function contagem(faltam) {
        if (faltam === 0) return ["hoje", ""];
        if (faltam === 1) return ["amanhã", ""];
        if (faltam < 0)   return [String(Math.abs(faltam)), Math.abs(faltam) === 1 ? "dia atrás" : "dias atrás"];
        return [String(faltam), "dias"];
    }

    /** 8.50 -> "8,5" ; 8.00 -> "8" (vírgula, que é como se escreve nota aqui) */
    function formatarNota(nota) {
        return Number(nota).toLocaleString("pt-BR", { maximumFractionDigits: 2 });
    }

    /** A média do histórico. Só conta prova com nota registrada. */
    function textoDaMedia() {
        const comNota = passadas.filter((p) => p.nota !== null);
        if (comNota.length < 2) return "";

        const soma = comNota.reduce((s, p) => s + p.nota, 0);
        return `média ${formatarNota(soma / comNota.length)} em ${comNota.length} provas`;
    }

    /* Aviso curto, no mesmo padrão dos flashcards e dos resumos. */
    let avisoTimer = null;
    function avisar(texto, erro = false) {
        let caixa = document.getElementById("pvAviso");
        if (!caixa) {
            caixa = document.createElement("div");
            caixa.id = "pvAviso";
            caixa.className = "fc-aviso";
            caixa.setAttribute("role", "status");
            caixa.setAttribute("aria-live", "polite");
            document.body.appendChild(caixa);
        }

        caixa.textContent = texto;
        caixa.classList.toggle("fc-aviso--erro", erro);
        caixa.hidden = false;

        clearTimeout(avisoTimer);
        caixa.style.display = "";
        avisoTimer = setTimeout(() => { caixa.hidden = true; }, erro ? 5000 : 2600);
    }

    /** Texto do usuário antes de entrar em innerHTML. */
    function esc(texto) {
        return String(texto ?? "").replace(/[&<>"']/g, (c) => ({
            "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;",
        }[c]));
    }
})();
