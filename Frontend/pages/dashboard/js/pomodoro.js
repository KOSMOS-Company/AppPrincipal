/* ============================================================
   KOSMOS — pomodoro.js
   Timer funcional (frontend). Sem persistência por enquanto.
   ============================================================ */

const CIRC = 2 * Math.PI * 125;   // circunferência do anel (r = 125)

const ESTADOS = {
    foco:  { texto: "Hora de focar",     pausa: false },
    curta: { texto: "Pausa curta",       pausa: true  },
    longa: { texto: "Pausa longa",       pausa: true  },
};

let modoAtual = "foco";
let totalSeg  = 25 * 60;
let restante  = totalSeg;
let rodando   = false;
let intervalo = null;
let ciclos    = 0;

const elTempo  = document.getElementById("tempo");
const elEstado = document.getElementById("estado");
const elAnel   = document.getElementById("anel");
const elCiclos = document.getElementById("ciclos");
const btnPlay  = document.getElementById("btnPlay");
const pomo     = document.querySelector(".pomo");

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
}

function definirModo(modo, min) {
    pausar();
    modoAtual = modo;
    totalSeg = min * 60;
    restante = totalSeg;
    elEstado.textContent = ESTADOS[modo].texto;
    pomo.classList.toggle("pomo--pausa", ESTADOS[modo].pausa);
    atualizarDisplay();
}

function tick() {
    if (restante > 0) {
        restante--;
        atualizarDisplay();
        if (restante === 0) concluir();
    }
}

function tocar() {
    pausar();
    rodando = true;
    btnPlay.textContent = "Pausar";
    intervalo = setInterval(tick, 1000);
}

function pausar() {
    rodando = false;
    btnPlay.textContent = "Iniciar";
    clearInterval(intervalo);
    intervalo = null;
}

function concluir() {
    pausar();
    if (modoAtual === "foco") {
        ciclos++;
        elCiclos.textContent = ciclos;
    }
    elEstado.textContent = "Tempo encerrado!";
    beep();
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
    } catch (e) { /* áudio indisponível — ignora */ }
}

// Controles
btnPlay.addEventListener("click", () => (rodando ? pausar() : tocar()));
document.getElementById("btnReset").addEventListener("click", () => {
    pausar();
    restante = totalSeg;
    elEstado.textContent = ESTADOS[modoAtual].texto;
    atualizarDisplay();
});

// Troca de modo
document.querySelectorAll(".pomo-modo").forEach((btn) => {
    btn.addEventListener("click", () => {
        document.querySelectorAll(".pomo-modo").forEach((b) => b.classList.remove("active"));
        btn.classList.add("active");
        definirModo(btn.dataset.modo, +btn.dataset.min);
    });
});

atualizarDisplay();
