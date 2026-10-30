/* ============================================================
   KOSMOS — Entrar com o Google (login e cadastro)
   Arquivo: Frontend/pages/shared/google-login.js

   Antes este código estava copiado dentro do login/index.html e do
   cadastro/index.html, cada um com o Client ID escrito à mão. Agora
   é um arquivo só, e o Client ID vem do config.php (pelo
   Backend/php/config_publica.php).

   A página precisa ter:
     - uma função global mostrarMsg(texto, tipo) — tipo "sucesso"|"erro";
     - o botão com a classe .btn--google (o clique é ligado aqui);
     - o script https://accounts.google.com/gsi/client.

   Abre o popup "Escolha uma conta" do Google. O One Tap
   (google.accounts.id.prompt) era silenciosamente bloqueado pelo
   navegador (cooldown/FedCM/cookies), por isso o botão "não fazia nada".
   ============================================================ */
(() => {
    const BACKEND = "../../../Backend/php";

    let clientId = "";
    let googleClient = null;

    /* O Client ID é buscado JÁ no carregamento, não no clique: o
       navegador só deixa abrir o popup como resposta direta ao clique,
       e esperar um fetch no meio faria o popup ser bloqueado. */
    const pronto = fetch(`${BACKEND}/config_publica.php`)
        .then((r) => r.json())
        .then((j) => { clientId = (j && j.google_client_id) || ""; })
        .catch(() => { /* fica vazio: o clique avisa */ });

    function avisar(texto, tipo) {
        document.querySelector(".msg-feedback")?.remove();
        window.mostrarMsg(texto, tipo);
    }

    window.googlePrompt = function () {
        if (typeof google === "undefined" || !google.accounts || !google.accounts.oauth2 || !clientId) {
            avisar("O Google ainda está carregando. Tente de novo em instantes.", "erro");
            pronto.then(() => {
                if (!clientId) console.warn("[KOSMOS] GOOGLE_CLIENT_ID vazio no config.php");
            });
            return;
        }
        if (!googleClient) {
            googleClient = google.accounts.oauth2.initTokenClient({
                client_id: clientId,
                scope: "openid email profile",
                prompt: "select_account",
                callback: (r) => {
                    if (r && r.access_token) entrar({ access_token: r.access_token });
                },
                error_callback: (err) => {
                    if (err && err.type === "popup_failed_to_open") {
                        avisar("O navegador bloqueou o pop-up do Google. Permita pop-ups e tente de novo.", "erro");
                    }
                },
            });
        }
        googleClient.requestAccessToken();
    };

    // Ligado aqui, não por onclick no HTML: a Content-Security-Policy
    // (script-src 'self') bloqueia atributos de evento inline.
    document.querySelectorAll(".btn--google").forEach((btn) => {
        btn.addEventListener("click", () => window.googlePrompt());
    });

    async function entrar(campos) {
        document.querySelector(".msg-feedback")?.remove();
        try {
            const dados = new FormData();
            for (const k in campos) dados.append(k, campos[k]);
            // a caixinha "Lembrar de mim" existe só na tela de login
            if (document.getElementById("lembrar")?.checked) dados.append("lembrar", "1");
            const resposta = await fetch(`${BACKEND}/login_google.php`, { method: "POST", body: dados });
            const json = await resposta.json();
            window.mostrarMsg(json.msg, json.ok ? "sucesso" : "erro");
            if (json.ok) {
                sessionStorage.setItem("kosmos_usuario", json.nome);
                sessionStorage.setItem("kosmos_intro", "1");
                // Conta do Google ainda sem senha: cria a senha antes de entrar.
                // Caminhos valem para login/ e cadastro/ (as duas no mesmo nível).
                const destino = json.precisa_senha
                    ? "../login/criar-senha.html"
                    : "../dashboard/index.php";
                setTimeout(() => { window.location.href = destino; }, 1000);
            }
        } catch (err) {
            window.mostrarMsg("Não foi possível conectar ao servidor.", "erro");
        }
    }
})();
