/* ============================================================
   KOSMOS — conta.js
   Carrega os dados da conta, salva perfil, troca senha e faz logout.
   O "porteiro" (redirecionar se não logado) já é feito por dashboard.js.
   ============================================================ */

// Obs.: dashboard.js já declara "API" no escopo global; usamos outro nome aqui
// para não colidir (os dois scripts são carregados na mesma página).
const BACKEND = "../../../Backend/php";

// Elementos
const avatar     = document.getElementById("contaAvatar");
const elNome     = document.getElementById("contaNome");
const elEmail    = document.getElementById("contaEmail");
const elMembro   = document.getElementById("contaMembro");
const inputNome  = document.getElementById("nome");
const inputEmail = document.getElementById("email");

document.addEventListener("DOMContentLoaded", () => {
    carregarDados();
    document.getElementById("formPerfil").addEventListener("submit", salvarPerfil);
    document.getElementById("formSenha").addEventListener("submit", trocarSenha);
    document.getElementById("btnSair").addEventListener("click", sair);
});

/* ---------- Carregar dados da conta ---------- */
async function carregarDados() {
    try {
        const resp = await fetch(`${BACKEND}/conta_dados.php`);
        if (!resp.ok) return; // sem sessão: dashboard.js já redireciona
        const json = await resp.json();
        if (!json.ok) return;

        preencher(json.nome, json.email);
        elMembro.textContent = "Membro desde " + formatarData(json.criado_em);
    } catch (err) {
        // backend fora do ar — dashboard.js trata o redirecionamento
    }
}

function preencher(nome, email) {
    elNome.textContent  = nome;
    elEmail.textContent = email;
    avatar.textContent  = (nome.trim()[0] || "?").toUpperCase();
    inputNome.value  = nome;
    inputEmail.value = email;
}

/* Converte "2026-06-25 13:57:17" -> "25 de junho de 2026" */
function formatarData(dataSql) {
    if (!dataSql) return "—";
    const d = new Date(dataSql.replace(" ", "T"));
    if (isNaN(d)) return "—";
    return d.toLocaleDateString("pt-BR", { day: "numeric", month: "long", year: "numeric" });
}

/* ---------- Salvar perfil (nome + e-mail) ---------- */
async function salvarPerfil(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const dados = new FormData(e.target);

    travar(btn, true, "Salvando…");
    try {
        const resp = await fetch(`${BACKEND}/conta_perfil.php`, { method: "POST", body: dados });
        const json = await resp.json();
        msg("msgPerfil", json.msg, json.ok ? "sucesso" : "erro");

        if (json.ok) {
            // Atualiza o cabeçalho/avatar e o nome guardado nesta sessão
            preencher(json.nome, json.email);
            document.querySelectorAll("[data-usuario]").forEach((el) => (el.textContent = json.nome));
            sessionStorage.setItem("kosmos_usuario", json.nome);
        }
    } catch (err) {
        msg("msgPerfil", "Não foi possível conectar ao servidor.", "erro");
    } finally {
        travar(btn, false, "Salvar alterações");
    }
}

/* ---------- Trocar senha ---------- */
async function trocarSenha(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');

    const nova     = document.getElementById("senhaNova").value;
    const confirma = document.getElementById("senhaConfirma").value;

    // Validação no cliente antes de chamar o backend
    if (nova !== confirma) {
        msg("msgSenha", "A nova senha e a confirmação não coincidem.", "erro");
        return;
    }

    const dados = new FormData();
    dados.append("senha_atual", document.getElementById("senhaAtual").value);
    dados.append("senha_nova", nova);

    travar(btn, true, "Trocando…");
    try {
        const resp = await fetch(`${BACKEND}/conta_senha.php`, { method: "POST", body: dados });
        const json = await resp.json();
        msg("msgSenha", json.msg, json.ok ? "sucesso" : "erro");
        if (json.ok) e.target.reset();
    } catch (err) {
        msg("msgSenha", "Não foi possível conectar ao servidor.", "erro");
    } finally {
        travar(btn, false, "Trocar senha");
    }
}

/* ---------- Sair (logout) ---------- */
async function sair() {
    try {
        await fetch(`${BACKEND}/logout.php`);
    } catch (err) {
        /* mesmo se falhar, vamos para o login */
    }
    sessionStorage.removeItem("kosmos_usuario");
    window.location.replace("../login/index.html");
}

/* ---------- Helpers ---------- */
function msg(id, texto, tipo) {
    const el = document.getElementById(id);
    el.textContent = texto;
    el.className = "msg msg--" + tipo;
    el.hidden = false;
}

function travar(btn, travado, texto) {
    btn.disabled = travado;
    btn.textContent = texto;
}
