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

let temSenha = true;   // false = conta só do Google (modo "criar senha")

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
        configurarSenha(json.tem_senha);
        configurarProvedor(json.tem_google);
    } catch (err) {
        // backend fora do ar — dashboard.js trata o redirecionamento
    }
}

/* Ícones para a etiqueta de método de acesso */
const ICONE_GOOGLE = '<svg width="14" height="14" viewBox="0 0 48 48"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.4 29.3 35 24 35c-6.1 0-11-4.9-11-11s4.9-11 11-11c2.8 0 5.4 1.1 7.3 2.9l5.7-5.7C33.5 6.2 28.1 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.5-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 16 19 13 24 13c2.8 0 5.4 1.1 7.3 2.9l5.7-5.7C33.5 6.2 28.1 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35 26.7 36 24 36c-5.3 0-9.6-2.6-11.3-7l-6.5 5C9.6 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.3-2.3 4.3-4.1 5.6l6.2 5.2C39.9 35.6 44 30.4 44 24c0-1.3-.1-2.5-.4-3.5z"/></svg>';
const ICONE_EMAIL  = '<svg width="14" height="14" viewBox="0 0 20 20" fill="none"><rect x="2.5" y="4" width="15" height="12" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M3 5l7 5 7-5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>';

/* Mostra a etiqueta dizendo como o usuário acessa a conta */
function configurarProvedor(temGoogle) {
    const el = document.getElementById("contaProvedor");
    if (!el) return;
    el.innerHTML = temGoogle
        ? ICONE_GOOGLE + "Google"
        : ICONE_EMAIL + "Email e senha";
}

/* Ajusta o painel de senha conforme a conta tenha ou não senha.
   Contas criadas via Google não têm senha → modo "Criar senha". */
function configurarSenha(possui) {
    temSenha = possui !== false;
    const campoAtual = document.getElementById("campoSenhaAtual");
    const titulo     = document.getElementById("senhaTitulo");
    const sub        = document.getElementById("senhaSub");
    const btn        = document.getElementById("btnSenha");
    const inputAtual = document.getElementById("senhaAtual");

    if (temSenha) {
        campoAtual.hidden = false;
        inputAtual.disabled = false;
        titulo.textContent = "Trocar senha";
        sub.textContent = "Por segurança, confirme sua senha atual antes de definir uma nova.";
        btn.textContent = "Trocar senha";
    } else {
        campoAtual.hidden = true;
        inputAtual.disabled = true;
        titulo.textContent = "Criar senha";
        sub.textContent = "Sua conta usa login do Google. Crie uma senha para também poder entrar com email e senha.";
        btn.textContent = "Criar senha";
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
    if (temSenha) {
        dados.append("senha_atual", document.getElementById("senhaAtual").value);
    }
    dados.append("senha_nova", nova);

    travar(btn, true, temSenha ? "Trocando…" : "Criando…");
    try {
        const resp = await fetch(`${BACKEND}/conta_senha.php`, { method: "POST", body: dados });
        const json = await resp.json();
        msg("msgSenha", json.msg, json.ok ? "sucesso" : "erro");
        if (json.ok) {
            e.target.reset();
            // Agora a conta tem senha: volta para o modo "Trocar senha"
            configurarSenha(true);
        }
    } catch (err) {
        msg("msgSenha", "Não foi possível conectar ao servidor.", "erro");
    } finally {
        // usa o estado atual (configurarSenha pode ter mudado após o sucesso)
        btn.disabled = false;
        btn.textContent = temSenha ? "Trocar senha" : "Criar senha";
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
