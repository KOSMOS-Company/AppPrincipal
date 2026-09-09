/* ============================================================
   KOSMOS — pomodoro.js
   Timer funcional (frontend). O estado fica em localStorage e a
   contagem é derivada do relógio do sistema, então o ciclo continua
   correndo quando o usuário troca de aba, navega para outra página
   do dashboard ou fecha o navegador e volta.
   ============================================================ */

const CIRC = 2 * Math.PI * 125;   // circunferência do anel (r = 125)
const CHAVE = "kosmos:pomodoro";  // onde a sessão fica guardada

const ESTADOS = {
    foco:  { texto: "Hora de focar",     pausa: false },
    curta: { texto: "Pausa curta",       pausa: true  },
    longa: { texto: "Pausa longa",       pausa: true  },
};

const elTempo  = document.getElementById("tempo");
const elEstado = document.getElementById("estado");
const elAnel   = document.getElementById("anel");
const elCiclos = document.getElementById("ciclos");
const btnPlay  = document.getElementById("btnPlay");
const pomo     = document.querySelector(".pomo");
const modos    = [...document.querySelectorAll(".pomo-modo")];

/* Durações vindas do servidor (pomodoro.php imprime data-min em cada
   modo com o valor escolhido na aba Conta). São a fonte da verdade
   quando o timer não está no meio de um ciclo. */
const minutosDo = {};
modos.forEach((b) => { minutosDo[b.dataset.modo] = +b.dataset.min || 25; });

let modoAtual = "foco";
let totalSeg  = minutosDo.foco * 60;
let restante  = totalSeg;
let rodando   = false;
let fimEm     = null;   // timestamp (ms) do fim do ciclo em andamento
let encerrado = false;  // ciclo chegou a zero e ainda não foi reiniciado
let ciclos    = 0;
let intervalo = null;

/* ------------------------------------------------------------
   Persistência
   ------------------------------------------------------------ */

function hoje() {
    return new Date().toISOString().slice(0, 10);
}

function salvar() {
    try {
        localStorage.setItem(CHAVE, JSON.stringify({
            modo: modoAtual,
            totalSeg,
            restante,
            rodando,
            fimEm,
            encerrado,
            ciclos,
            dia: hoje(),
        }));
    } catch (e) { /* storage indisponível — o timer só perde a memória */ }
}

/** Lê a sessão guardada e reconstrói o estado a partir do relógio. */
function restaurar() {
    let s = null;
    try { s = JSON.parse(localStorage.getItem(CHAVE) || "null"); }
    catch (e) { s = null; }

    if (!s || !ESTADOS[s.modo]) return false;

    // A contagem de ciclos é do dia; virou o dia, começa de novo.
    ciclos = s.dia === hoje() ? (+s.ciclos || 0) : 0;

    modoAtual = s.modo;
    totalSeg  = +s.totalSeg > 0 ? +s.totalSeg : minutosDo[modoAtual] * 60;
    encerrado = !!s.encerrado;

    if (s.rodando && s.fimEm) {
        const faltam = Math.ceil((s.fimEm - Date.now()) / 1000);
        if (faltam > 0) {
            // Ainda no meio do ciclo: retoma de onde o relógio está.
            rodando  = true;
            fimEm    = s.fimEm;
            restante = Math.min(faltam, totalSeg);
        } else {
            // Passou do fim enquanto o usuário estava fora.
            rodando  = false;
            fimEm    = null;
            restante = 0;
            if (!encerrado) contabilizarFim();
        }
    } else {
        rodando  = false;
        fimEm    = null;
        const guardado = +s.restante;
        restante = Number.isFinite(guardado)
            ? Math.max(0, Math.min(guardado, totalSeg))
            : totalSeg;
    }

    /* Fora de um ciclo em andamento, as preferências do servidor mandam:
       se o usuário mudou a duração na aba Conta, vale a nova. */
    if (!rodando && !encerrado && restante === totalSeg) {
        totalSeg = restante = minutosDo[modoAtual] * 60;
    }

    return true;
}

/* ------------------------------------------------------------
   Tela
   ------------------------------------------------------------ */

function formatar(seg) {
    const m = String(Math.floor(seg / 60)).padStart(2, "0");
    const s = String(seg % 60).padStart(2, "0");
    return `${m}:${s}`;
}

function atualizarDisplay() {
    elTempo.textContent = formatar(restante);
    document.title = `${formatar(restante)} — Kosmos Pomodoro`;
    const elapsed = totalSeg - restante;
    elAnel.style.strokeDashoffset = CIRC * (elapsed / totalSeg);
    btnPlay.textContent = rodando ? "Pausar" : "Iniciar";
    elCiclos.textContent = ciclos;
    elEstado.textContent = encerrado ? "Tempo encerrado!" : ESTADOS[modoAtual].texto;
    pomo.classList.toggle("pomo--pausa", ESTADOS[modoAtual].pausa);
    modos.forEach((b) => b.classList.toggle("active", b.dataset.modo === modoAtual));
}

/* ------------------------------------------------------------
   Contagem
   ------------------------------------------------------------ */

/** Recalcula o restante pelo relógio — imune a aba em segundo plano,
    onde o navegador estrangula os timers. */
function sincronizar() {
    if (!rodando) return;

    restante = Math.max(0, Math.ceil((fimEm - Date.now()) / 1000));
    if (restante === 0) {
        concluir();
        return;
    }
    atualizarDisplay();
    salvar();
}

function tocar() {
    if (rodando) return;
    /* A permissão de aviso só pode ser pedida a partir de um clique —
       este é o clique. Quem avisa de verdade é pomodoro-aviso.js, que
       roda em todas as abas e por isso alcança a pessoa fora daqui. */
    if (window.KosmosPomoAviso) window.KosmosPomoAviso.pedirPermissao();
    if (restante <= 0) restante = totalSeg;   // recomeça depois de encerrado
    rodando   = true;
    encerrado = false;
    fimEm     = Date.now() + restante * 1000;
    clearInterval(intervalo);
    intervalo = setInterval(sincronizar, 250);
    atualizarDisplay();
    salvar();
}

function pausar() {
    if (rodando) restante = Math.max(0, Math.ceil((fimEm - Date.now()) / 1000));
    rodando = false;
    fimEm   = null;
    clearInterval(intervalo);
    intervalo = null;
    atualizarDisplay();
    salvar();
}

/** Fecha as contas do ciclo sem tocar na tela — serve tanto para o fim
    ao vivo quanto para o fim que aconteceu com o usuário fora. */
function contabilizarFim() {
    encerrado = true;
    if (modoAtual === "foco") ciclos++;
}

function concluir() {
    rodando = false;
    // fimEm fica: é por ele que pomodoro-aviso.js identifica de qual
    // ciclo se trata e não avisa duas vezes sobre o mesmo.
    clearInterval(intervalo);
    intervalo = null;
    restante  = 0;
    contabilizarFim();
    atualizarDisplay();
    salvar();
    /* O som e o aviso do navegador ficam em pomodoro-aviso.js: ele
       roda em toda aba do dashboard, então avisa uma vez só, esteja
       a pessoa aqui ou não. Aqui só empurramos a verificação para
       não esperar o tique dele. */
    if (window.KosmosPomoAviso) window.KosmosPomoAviso.verificar();
}

function definirModo(modo) {
    clearInterval(intervalo);
    intervalo = null;
    rodando   = false;
    fimEm     = null;
    encerrado = false;
    modoAtual = modo;
    totalSeg  = restante = minutosDo[modo] * 60;
    atualizarDisplay();
    salvar();
}

/* ------------------------------------------------------------
   Controles
   ------------------------------------------------------------ */

btnPlay.addEventListener("click", () => (rodando ? pausar() : tocar()));

document.getElementById("btnReset").addEventListener("click", () => {
    definirModo(modoAtual);
});

modos.forEach((btn) => {
    btn.addEventListener("click", () => definirModo(btn.dataset.modo));
});

/* Ao voltar para a aba, acerta o relógio na hora em vez de esperar o
   próximo tick (que pode ter sido estrangulado em segundo plano). */
document.addEventListener("visibilitychange", () => {
    if (!document.hidden) sincronizar();
});
window.addEventListener("focus", sincronizar);

// Guarda o estado antes de sair da página (navegação no menu, fechar aba).
window.addEventListener("pagehide", salvar);

/* Se outra aba do Kosmos mexer no timer, esta acompanha. */
window.addEventListener("storage", (e) => {
    if (e.key !== CHAVE) return;
    clearInterval(intervalo);
    intervalo = null;
    restaurar();
    if (rodando) intervalo = setInterval(sincronizar, 250);
    atualizarDisplay();
});

/* ------------------------------------------------------------
   Arranque
   ------------------------------------------------------------ */

restaurar();
atualizarDisplay();
if (rodando) intervalo = setInterval(sincronizar, 250);
salvar();
