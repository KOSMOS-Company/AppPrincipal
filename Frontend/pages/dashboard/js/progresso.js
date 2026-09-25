/* ============================================================
   KOSMOS — progresso.js  (XP, avisos e subida de nível)
   Carregado por partes/sidebar.php, síncrono e antes dos outros
   scripts da página.

   A regra é simples: todo endpoint que dá XP devolve a chave
   `progresso` na resposta (ver Backend/php/ProgressoService.php).
   Este arquivo embrulha o fetch, percebe essa chave em QUALQUER
   resposta do backend e reage — atualiza a barra, mostra o "+50 XP",
   anuncia a conquista, abre o modal de nível. Nenhuma tela precisa
   lembrar de chamar nada: um endpoint novo que der XP já funciona.

   API: window.KosmosProgresso.receber(payload)
   ============================================================ */
(() => {
    "use strict";

    const semMovimento = window.matchMedia("(prefers-reduced-motion: reduce)");
    const fmt = (n) => Number(n || 0).toLocaleString("pt-BR");

    /* ---------- 1. O fetch embrulhado ----------
       A resposta volta intacta para quem chamou: lemos uma CÓPIA
       (clone) e só de JSON do nosso backend. */
    const fetchOriginal = window.fetch.bind(window);
    window.fetch = function (entrada, opcoes) {
        return fetchOriginal(entrada, opcoes).then((resp) => {
            try {
                const url = typeof entrada === "string" ? entrada : (entrada && entrada.url) || "";
                const doBackend = new URL(url, location.href).pathname.includes("/Backend/php/");
                const ehJson = (resp.headers.get("content-type") || "").includes("application/json");
                if (doBackend && ehJson) {
                    resp.clone().json()
                        .then((j) => { if (j && j.progresso) receber(j.progresso); })
                        .catch(() => {});
                }
            } catch (e) { /* XP é detalhe: nunca atrapalha a requisição */ }
            return resp;
        });
    };

    /* ---------- 2. Receber um payload ---------- */
    function receber(p) {
        if (!p || typeof p !== "object") return;
        atualizarBarras(p);

        const eventos = Array.isArray(p.eventos) ? p.eventos.filter((e) => e.tipo !== "conquista") : [];
        const conquistas = Array.isArray(p.conquistas_desbloqueadas) ? p.conquistas_desbloqueadas : [];

        eventos.forEach((e, i) => setTimeout(() => avisoXP(e), i * 140));
        conquistas.forEach((c, i) => setTimeout(() => avisoConquista(c), (eventos.length + i) * 140 + 250));

        if (p.subiu_de_nivel) {
            // Depois dos avisos começarem: primeiro "o que você fez", depois a festa
            setTimeout(() => abrirSubidaDeNivel(p), 900);
        } else if (eventos.length || conquistas.length) {
            tocar(conquistas.length ? "conquista" : "xp");
        }
    }

    /* ---------- 3. As barras de XP da página ---------- */

    // Mesmo texto do progressoDica() do PHP — mexeu num, mexa no outro
    function dica(p) {
        if (p.xp_proximo_nivel == null) return `${fmt(p.xp_total)} XP · nível máximo`;
        return `${fmt(p.xp_total)} / ${fmt(p.xp_proximo_nivel)} XP · faltam ` +
               `${fmt(p.xp_proximo_nivel - p.xp_total)} para o nível ${p.nivel + 1}`;
    }

    function atualizarBarras(p) {
        if (p.nivel == null) return;
        document.querySelectorAll("[data-xp-nivel]").forEach((el) => { el.textContent = p.nivel; });
        document.querySelectorAll("[data-xp-titulo]").forEach((el) => { el.textContent = p.titulo; });
        document.querySelectorAll("[data-xp-dica]").forEach((el) => { el.textContent = dica(p); });
        document.querySelectorAll("[data-xp-barra]").forEach((el) => el.setAttribute("aria-valuenow", p.progresso_pct));
        // O anel do chip do celular é a mesma barra, enrolada (r = 13)
        document.querySelectorAll("[data-xp-anel]").forEach((el) => {
            const c = 2 * Math.PI * 13;
            el.style.strokeDashoffset = String(c * (1 - p.progresso_pct / 100));
        });
        if (p.estudou_hoje) {
            document.querySelectorAll("[data-status-hoje]").forEach((el) => {
                el.textContent = "Você já estudou hoje. Sequência garantida.";
            });
        }
        document.querySelectorAll("[data-status-proxima]").forEach((el) => {
            const r = p.proxima_recompensa;
            el.hidden = !r;
            if (!r) return;
            el.textContent = "";
            el.append("Próxima recompensa: ");
            const nome = document.createElement("strong");
            nome.textContent = r.nome;
            el.append(nome, ` (${String(r.tipo_nome || "").toLowerCase()}) no nível ${r.nivel}`);
        });
        document.querySelectorAll("[data-xp]").forEach((el) => {
            if (el.dataset.rotulo !== undefined) el.dataset.rotulo = `Nível ${p.nivel} · ${p.titulo}`;
        });

        // Subiu de nível: a barra vai até o fim, zera e enche o que sobrou.
        // Sem isso ela ANDARIA PARA TRÁS de 90% para 10% — parece perda.
        const barras = document.querySelectorAll("[data-xp-pct]");
        if (p.subiu_de_nivel && !semMovimento.matches) {
            barras.forEach((el) => el.style.setProperty("--xp-pct", "100%"));
            setTimeout(() => {
                barras.forEach((el) => {
                    el.style.transition = "none";
                    el.style.setProperty("--xp-pct", "0%");
                    void el.offsetWidth;
                    el.style.transition = "";
                    el.style.setProperty("--xp-pct", p.progresso_pct + "%");
                });
            }, 950);
        } else {
            barras.forEach((el) => el.style.setProperty("--xp-pct", p.progresso_pct + "%"));
        }

        const alvos = document.querySelectorAll("[data-xp], .xp-fio");
        alvos.forEach((el) => {
            el.classList.remove("xp--ganhou");
            void el.offsetWidth;
            el.classList.add("xp--ganhou");
        });
        setTimeout(() => alvos.forEach((el) => el.classList.remove("xp--ganhou")), 1600);
    }

    /* ---------- 4. Avisos ---------- */
    let pilha = null;

    function pilhaDeAvisos() {
        if (pilha && document.body.contains(pilha)) return pilha;
        pilha = document.createElement("div");
        pilha.className = "xp-avisos";
        pilha.setAttribute("role", "status");
        pilha.setAttribute("aria-live", "polite");
        document.body.appendChild(pilha);
        return pilha;
    }

    function mostrar(aviso, duracao) {
        const p = pilhaDeAvisos();
        p.appendChild(aviso);
        // No máximo quatro na tela: o mais antigo abre espaço
        while (p.children.length > 4) p.firstElementChild.remove();
        setTimeout(() => {
            aviso.classList.add("xp-aviso--saindo");
            aviso.addEventListener("animationend", () => aviso.remove(), { once: true });
            setTimeout(() => aviso.remove(), 500);   // se a animação não rodar
        }, duracao);
    }

    function avisoXP(e) {
        if (!e || !e.xp) return;
        const a = document.createElement("div");
        a.className = "xp-aviso";
        const xp = document.createElement("span");
        xp.className = "xp-aviso__xp";
        xp.textContent = `+${fmt(e.xp)} XP`;
        const txt = document.createElement("span");
        txt.className = "xp-aviso__txt";
        txt.textContent = e.rotulo || "";
        a.append(xp, txt);
        mostrar(a, 3200);
    }

    /** Aviso sem XP — "Moldura equipada", por exemplo. */
    function aviso(texto, destaque) {
        const a = document.createElement("div");
        a.className = "xp-aviso xp-aviso--simples";
        const txt = document.createElement("span");
        txt.className = "xp-aviso__txt";
        txt.textContent = texto;
        if (destaque) {
            const tag = document.createElement("span");
            tag.className = "xp-aviso__xp";
            tag.textContent = destaque;
            a.append(tag);
        }
        a.append(txt);
        mostrar(a, 2600);
    }

    function avisoConquista(c) {
        const a = document.createElement("div");
        a.className = "xp-aviso xp-aviso--conquista";
        a.innerHTML = `
            <span class="xp-aviso__ico">${c.svg || ""}</span>
            <span class="xp-aviso__linhas">
                <span class="xp-aviso__sobre">Conquista desbloqueada</span>
                <span class="xp-aviso__nome"></span>
            </span>
            <span class="xp-aviso__bonus"></span>`;
        // O SVG é nosso (vem do servidor); o nome vai como texto, nunca HTML
        a.querySelector(".xp-aviso__nome").textContent = c.nome || "";
        a.querySelector(".xp-aviso__bonus").textContent = c.xp_bonus ? `+${fmt(c.xp_bonus)} XP` : "";
        mostrar(a, 4600);
    }

    /* ---------- 5. Som ----------
       Sintetizado na hora (Web Audio): sem arquivo para baixar. Volume
       baixo e curto. O navegador só deixa tocar depois de a pessoa ter
       interagido com a página — um Pomodoro que termina com a aba
       parada fica em silêncio, e tudo bem. */
    let audio = null;
    const NOTAS = {
        xp:        [880, 1318.5],
        conquista: [784, 987.8, 1318.5],
        nivel:     [523.3, 659.3, 784, 1046.5, 1318.5],
    };

    function tocar(tipo) {
        try {
            audio = audio || new (window.AudioContext || window.webkitAudioContext)();
        } catch (e) { return; }
        const tocarAgora = () => {
            const notas = NOTAS[tipo] || NOTAS.xp;
            notas.forEach((freq, i) => {
                const osc = audio.createOscillator();
                const vol = audio.createGain();
                const t = audio.currentTime + i * (tipo === "nivel" ? 0.1 : 0.08);
                osc.type = "sine";
                osc.frequency.value = freq;
                vol.gain.setValueAtTime(0, t);
                vol.gain.linearRampToValueAtTime(0.05, t + 0.015);
                vol.gain.exponentialRampToValueAtTime(0.0001, t + 0.55);
                osc.connect(vol).connect(audio.destination);
                osc.start(t);
                osc.stop(t + 0.6);
            });
        };
        if (audio.state === "suspended") audio.resume().then(tocarAgora).catch(() => {});
        else tocarAgora();
    }

    /* ---------- 6. Subiu de nível ---------- */
    const CORES = ["#ffffff", "#e7b8ff", "#c97cff", "#a541ff", "#ffd6f5"];
    const ESTRELA = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 2.5 2.9 6 6.6 1-4.8 4.6 1.1 6.6L12 17.6l-5.9 3.1 1.1-6.6-4.8-4.6 6.6-1L12 2.5Z"/></svg>';
    let modalAberto = null;

    function faiscas(qtd, atraso) {
        let html = "";
        for (let i = 0; i < qtd; i++) {
            const estrela = Math.random() < 0.3;
            html += `<span class="lvl__p${estrela ? " lvl__p--estrela" : ""}" style="` +
                `--a:${Math.round(Math.random() * 360)}deg;` +
                `--d:${Math.round(90 + Math.random() * 150)}px;` +
                `--s:${(estrela ? 9 : 4) + Math.round(Math.random() * 5)}px;` +
                `--c:${CORES[i % CORES.length]};` +
                `--t:${(atraso + Math.random() * 0.25).toFixed(2)}s;` +
                `--dur:${(1.1 + Math.random() * 0.7).toFixed(2)}s"></span>`;
        }
        return html;
    }

    function abrirSubidaDeNivel(p) {
        if (modalAberto) fecharSubidaDeNivel(true);
        tocar("nivel");

        const anterior = document.activeElement;
        const m = document.createElement("div");
        m.className = "lvl";
        m.setAttribute("role", "dialog");
        m.setAttribute("aria-modal", "true");
        m.setAttribute("aria-labelledby", "lvlTitulo");
        m.innerHTML = `
            <div class="lvl__fundo"></div>
            <div class="lvl__caixa">
                <div class="lvl__raios" aria-hidden="true"></div>
                <div class="lvl__particulas" aria-hidden="true" style="top:0">${faiscas(34, 0.2) + faiscas(22, 0.75)}</div>
                <div class="lvl__orion" aria-hidden="true"></div>
                <p class="lvl__selo">Subiu de nível!</p>
                <h2 class="lvl__nivel" id="lvlTitulo"><span>Nível</span><strong></strong></h2>
                <p class="lvl__titulo"></p>
                <p class="lvl__recompensa"></p>
                <ul class="lvl__premios" hidden></ul>
                <p class="lvl__xp"></p>
                <div class="lvl__acoes">
                    <a class="dash-btn dash-btn--ghost lvl__trilha" href="conquistas.php#trilha" hidden>Ver na trilha</a>
                    <button type="button" class="dash-btn dash-btn--primary lvl__ok">Continuar</button>
                </div>
            </div>`;

        m.querySelector(".lvl__nivel strong").textContent = p.novo_nivel;
        const titulo = m.querySelector(".lvl__titulo");
        if (p.titulo_mudou) {
            const novo = document.createElement("span");
            novo.className = "lvl__novo";
            novo.textContent = "Novo título";
            titulo.before(novo);
        }
        titulo.textContent = p.novo_titulo || p.titulo || "";
        m.querySelector(".lvl__recompensa").textContent = p.recompensa || "";
        m.querySelector(".lvl__xp").textContent = `${fmt(p.xp_total)} XP no total`;

        // Recompensas destravadas: prévia no avatar (moldura/emblema/cor)
        const novas = Array.isArray(p.recompensas_novas) ? p.recompensas_novas : [];
        if (novas.length) {
            const lista = m.querySelector(".lvl__premios");
            const avatar = document.querySelector(".usuario__avatar");
            const inicial = avatar ? avatar.textContent.trim() : "";
            const corAtual = avatar ? ([...avatar.classList].find((c) => c.startsWith("avatar-cor--")) || "avatar-cor--roxo") : "avatar-cor--roxo";
            novas.forEach((r) => {
                const li = document.createElement("li");
                const previa = document.createElement("span");
                const classe = `avatar-${r.tipo}--${r.valor}`;
                previa.className = `tr-avatar tr-avatar--mini ${r.tipo === "cor" ? classe : corAtual + " " + classe}`;
                previa.textContent = inicial;
                previa.setAttribute("aria-hidden", "true");
                const txt = document.createElement("span");
                txt.className = "lvl__premio-txt";
                const tipo = document.createElement("small");
                tipo.textContent = r.tipo_nome;
                const nome = document.createElement("strong");
                nome.textContent = r.nome;
                txt.append(tipo, nome);
                li.append(previa, txt);
                lista.append(li);
            });
            lista.hidden = false;
            m.querySelector(".lvl__trilha").hidden = false;
        }

        // O Orion comemorando — quando o mascote está na página
        const palco = m.querySelector(".lvl__orion");
        if (window.KosmosMascote && typeof window.KosmosMascote.montar === "function") {
            const orion = document.createElement("div");
            orion.className = "mascote";
            orion.dataset.mascote = "";
            orion.dataset.mascoteHumor = "comemorando";
            palco.appendChild(orion);
            window.KosmosMascote.montar(orion);
        } else {
            palco.innerHTML = `<span class="lvl__estrela">${ESTRELA}</span>`;
        }

        const fechar = () => fecharSubidaDeNivel(false);
        m.querySelector(".lvl__ok").addEventListener("click", fechar);
        m.querySelector(".lvl__fundo").addEventListener("click", fechar);
        m.addEventListener("keydown", (e) => {
            if (e.key === "Escape") { e.stopPropagation(); fechar(); }
            if (e.key === "Tab") { e.preventDefault(); m.querySelector(".lvl__ok").focus(); }  // um botão só: o foco fica nele
        });

        modalAberto = { el: m, anterior };
        document.body.appendChild(m);
        m.querySelector(".lvl__ok").focus({ preventScroll: true });
    }

    function fecharSubidaDeNivel(imediato) {
        if (!modalAberto) return;
        const { el, anterior } = modalAberto;
        modalAberto = null;
        const remover = () => {
            el.remove();
            if (anterior && typeof anterior.focus === "function") anterior.focus({ preventScroll: true });
        };
        if (imediato || semMovimento.matches) { remover(); return; }
        el.classList.add("lvl--saindo");
        setTimeout(remover, 300);
    }

    /* ---------- 7. Painel do chip de sequência + nível (celular) ---------- */
    function ativarPainelStatus() {
        const botao = document.getElementById("btnStatus");
        const painel = document.getElementById("statusPainel");
        if (!botao || !painel) return;

        const aberto = () => botao.getAttribute("aria-expanded") === "true";
        const abrir = (sim) => {
            botao.setAttribute("aria-expanded", String(sim));
            painel.hidden = !sim;
        };

        botao.addEventListener("click", (e) => {
            e.stopPropagation();
            abrir(!aberto());
        });
        document.addEventListener("click", (e) => {
            if (aberto() && !painel.contains(e.target)) abrir(false);
        });
        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape" && aberto()) {
                abrir(false);
                botao.focus();
            }
        });
        // O topo começou a sumir com a rolagem (dashboard.js): o painel,
        // que mora nele, fecha em vez de ficar boiando sem o chip
        document.addEventListener("kosmos:topo-escondido", () => { if (aberto()) abrir(false); });
        // Virou computador (girou o tablet, redimensionou): o painel some
        window.matchMedia("(min-width: 769px)").addEventListener("change", (m) => { if (m.matches) abrir(false); });
    }

    /* ---------- 8. Payload que a página já trouxe ----------
       A página de conquistas confere tudo ao abrir e pode ter
       desbloqueado badges de quem já tinha histórico. */
    document.addEventListener("DOMContentLoaded", () => {
        ativarPainelStatus();
        if (window.KOSMOS_PROGRESSO_INICIAL) receber(window.KOSMOS_PROGRESSO_INICIAL);
    });

    window.KosmosProgresso = { receber, aviso };
})();
