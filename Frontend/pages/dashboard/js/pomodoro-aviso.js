/* ============================================================
   KOSMOS — pomodoro-aviso.js  (compartilhado por todas as abas)

   Duas coisas, só isso:
   1. Um selo flutuante mostrando que o Pomodoro está correndo e
      quanto falta, em qualquer aba do dashboard. Clicar leva para
      a página do Pomodoro. Na própria página do Pomodoro o selo
      não aparece — o anel já está ali.
   2. Um aviso do navegador (mais o som) quando o ciclo acaba,
      mesmo que a pessoa esteja em outra aba ou em outro programa.

   3. Registra no servidor a sessão de foco que acabou de fechar.

   Por que o registro mora AQUI e não no pomodoro.js: este arquivo
   roda em TODA aba do dashboard, e o pomodoro.js só na página do
   Pomodoro. Gravando lá, a sessão de quem deixou o timer correndo
   e foi escrever um resumo só chegaria ao banco quando a pessoa
   voltasse à página do timer — se voltasse.

   O estado é o mesmo que pomodoro.js guarda em localStorage; aqui
   só se lê. A única coisa que este arquivo escreve é a marca de
   "já avisei sobre este ciclo", numa chave separada, para dois
   pontos do app não dispararem o mesmo aviso duas vezes.
   ============================================================ */

(() => {
    const CHAVE   = "kosmos:pomodoro";
    const CHAVE_AV = "kosmos:pomodoro:avisado";   // fimEm do último ciclo avisado

    const ROTULOS = {
        foco:  "Foco",
        curta: "Pausa curta",
        longa: "Pausa longa",
    };

    const naPaginaDoPomodoro = /\/pomodoro\.php$/.test(location.pathname);

    /* ------------------------------------------------------------
       Leitura do estado
       ------------------------------------------------------------ */

    function ler() {
        try {
            const s = JSON.parse(localStorage.getItem(CHAVE) || "null");
            return s && ROTULOS[s.modo] ? s : null;
        } catch (e) {
            return null;   // storage bloqueado ou lixo gravado
        }
    }

    function restanteDe(s) {
        if (s.rodando && s.fimEm) return Math.max(0, Math.ceil((s.fimEm - Date.now()) / 1000));
        return Math.max(0, +s.restante || 0);
    }

    function formatar(seg) {
        const m = String(Math.floor(seg / 60)).padStart(2, "0");
        const s = String(seg % 60).padStart(2, "0");
        return `${m}:${s}`;
    }

    /* ------------------------------------------------------------
       Aviso do navegador
       ------------------------------------------------------------ */

    const temNotificacao = "Notification" in window;

    /** Pede a permissão. Só funciona a partir de um clique, então
        quem chama é o botão Iniciar (em pomodoro.js). */
    function pedirPermissao() {
        if (!temNotificacao || Notification.permission !== "default") return;
        try { Notification.requestPermission(); } catch (e) { /* ignora */ }
    }

    function beep() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.type = "sine";
            osc.frequency.value = 660;
            gain.gain.setValueAtTime(0.0001, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.2, ctx.currentTime + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.8);
            osc.start();
            osc.stop(ctx.currentTime + 0.8);
        } catch (e) { /* áudio indisponível (ou sem gesto do usuário) — ignora */ }
    }

    /** Já avisamos sobre o ciclo que terminava em `fimEm`? A marca
        vive fora do estado do timer para pomodoro.js não sobrescrevê-la. */
    function jaAvisou(fimEm) {
        try { return localStorage.getItem(CHAVE_AV) === String(fimEm); }
        catch (e) { return false; }
    }

    function marcarAvisado(fimEm) {
        try { localStorage.setItem(CHAVE_AV, String(fimEm)); } catch (e) { /* ignora */ }
    }

    function avisarFim(s) {
        const foiFoco = s.modo === "foco";
        const titulo = foiFoco ? "Ciclo de foco concluído!" : "Pausa encerrada!";
        const corpo  = foiFoco
            ? "Hora de respirar um pouco. Volte ao Kosmos para começar a pausa."
            : "Descanso terminado. Volte ao Kosmos para retomar o foco.";

        beep();

        if (!temNotificacao || Notification.permission !== "granted") return;
        try {
            const n = new Notification(titulo, {
                body: corpo,
                icon: "../shared/favicon.svg",
                // a tag evita empilhar avisos se houver várias abas abertas
                tag: "kosmos-pomodoro",
                renotify: true,
            });
            n.onclick = () => {
                window.focus();
                if (!naPaginaDoPomodoro) location.href = "pomodoro.php";
                n.close();
            };
        } catch (e) { /* navegador recusou o aviso — o beep já saiu */ }
    }

    /* ------------------------------------------------------------
       Selo flutuante
       ------------------------------------------------------------ */

    let selo = null;
    let seloTempo = null;
    let seloModo = null;

    function montarSelo() {
        if (selo || naPaginaDoPomodoro) return;

        selo = document.createElement("a");
        selo.className = "pomo-selo";
        selo.href = "pomodoro.php";
        selo.hidden = true;
        selo.setAttribute("aria-live", "off");
        selo.innerHTML =
            '<span class="pomo-selo__pulso" aria-hidden="true"></span>' +
            '<span class="pomo-selo__txt">' +
                '<strong class="pomo-selo__tempo">00:00</strong>' +
                '<span class="pomo-selo__modo">Foco</span>' +
            "</span>";

        document.body.appendChild(selo);
        seloTempo = selo.querySelector(".pomo-selo__tempo");
        seloModo  = selo.querySelector(".pomo-selo__modo");
    }

    function pintarSelo(s, restante) {
        if (!selo) return;

        // só interessa enquanto há um ciclo de verdade em andamento
        const mostrar = !!s && s.rodando && restante > 0;
        selo.hidden = !mostrar;
        if (!mostrar) return;

        seloTempo.textContent = formatar(restante);
        seloModo.textContent  = ROTULOS[s.modo];
        selo.classList.toggle("pomo-selo--pausa", s.modo !== "foco");
        selo.title = `${ROTULOS[s.modo]} — ${formatar(restante)} restantes. Abrir o Pomodoro.`;
    }

    /* ------------------------------------------------------------
       Laço
       ------------------------------------------------------------ */

    /* Um ciclo que acabou há muito tempo (navegador fechado a tarde
       inteira) não rende aviso: avisar às 22h sobre o foco das 14h só
       assusta. Ainda assim marcamos, para não tentar de novo. */
    const TOLERANCIA = 5 * 60 * 1000;

    /* ------------------------------------------------------------
       Registro da sessão
       ------------------------------------------------------------ */
    const API = "../../../Backend/php";

    /**
     * Manda para o servidor um ciclo de FOCO que terminou.
     *
     * Pausa não conta: o gráfico da semana e a meta diária medem
     * tempo ESTUDADO, e somar os cinco minutos de descanso inflaria
     * o número justamente para quem descansa mais.
     *
     * Manda o instante do fim em vez de deixar o servidor usar
     * NOW(): o ciclo pode ter fechado há horas, com o navegador
     * fechado, e gravar "agora" jogaria a sessão no dia errado.
     *
     * Não trata falha de propósito. Se a rede cair, a sessão se
     * perde — e tudo bem: é melhor perder um registro em silêncio
     * do que interromper quem está estudando com um aviso de erro
     * sobre algo que ela não pediu. O servidor ignora duplicata
     * (UNIQUE em usuario_id + fim_em), então reenviar nunca soma
     * duas vezes.
     */
    function registrarSessao(s) {
        if (!s || s.modo !== "foco") return;

        const minutos = Math.round((+s.totalSeg || 0) / 60);
        if (!(minutos > 0) || !s.fimEm) return;

        fetch(`${API}/pomodoro_sessao.php`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "same-origin",
            body: JSON.stringify({
                fim: Math.floor(s.fimEm / 1000),   // o PHP espera SEGUNDOS
                minutos,
            }),
            keepalive: true,   // sobrevive se a pessoa fechar a aba agora
        }).catch(() => { /* ver o comentário acima */ });
    }

    function verificar() {
        const s = ler();
        const restante = s ? restanteDe(s) : 0;

        /* Chegou a zero: avisa uma vez só, não a cada tique. Vale para
           os dois jeitos de o ciclo terminar — o timer estourou e nada
           atualizou o estado ainda (rodando), ou a página do Pomodoro
           já fechou as contas (encerrado). */
        const acabou = !!s && !!s.fimEm && (s.rodando ? restante === 0 : !!s.encerrado);
        if (acabou && !jaAvisou(s.fimEm)) {
            marcarAvisado(s.fimEm);

            /* Aqui dentro roda UMA vez por ciclo, em qualquer aba: o
               `jaAvisou` é a trava. Por isso é o lugar do registro —
               e não junto do aviso sonoro logo abaixo, que é pulado
               quando o ciclo terminou há muito tempo. Sessão antiga
               não deve tocar sino, mas deve entrar no gráfico. */
            registrarSessao(s);

            if (Date.now() - s.fimEm < TOLERANCIA) avisarFim(s);
        }

        pintarSelo(s, restante);
    }

    montarSelo();
    verificar();
    setInterval(verificar, 1000);

    /* Voltando para a aba, acerta na hora: em segundo plano o
       navegador estrangula o setInterval e o tempo fica atrasado. */
    document.addEventListener("visibilitychange", () => {
        if (!document.hidden) verificar();
    });
    window.addEventListener("focus", verificar);

    // Outra aba mexeu no timer (ou a própria página do Pomodoro).
    window.addEventListener("storage", (e) => {
        if (e.key === CHAVE) verificar();
    });

    // pomodoro.js usa isto no clique do Iniciar para pedir a permissão.
    window.KosmosPomoAviso = { pedirPermissao, verificar };
})();
