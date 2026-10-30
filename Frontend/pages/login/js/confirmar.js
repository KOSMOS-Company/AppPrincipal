/* ============================================================
   KOSMOS — Confirmar o e-mail do cadastro (link do e-mail)
   Arquivo: Frontend/pages/login/js/confirmar.js

   Lê o token do link e confirma sozinho, sem botão: abrir o link já é
   o "sim". Deu certo, a pessoa já entra (confirmar_email.php abre a
   sessão) e vai para o dashboard com a mesma abertura do login.
   ============================================================ */

(async function () {
    const texto = document.getElementById('confirmaTexto');
    const token = new URLSearchParams(location.search).get('token') || '';

    if (!/^[0-9a-f]{64}$/.test(token)) {
        texto.textContent = 'Este link está incompleto. Abra de novo o link do e-mail ou peça um novo na tela de login.';
        return;
    }

    try {
        const dados = new FormData();
        dados.append('token', token);
        const resp = await fetch('../../../Backend/php/confirmar_email.php', { method: 'POST', body: dados });
        const json = await resp.json();
        texto.textContent = json.msg;
        if (json.ok) {
            sessionStorage.setItem('kosmos_usuario', json.nome);
            sessionStorage.setItem('kosmos_intro', '1');   // a abertura do dashboard, como no login
            setTimeout(() => { window.location.href = '../dashboard/index.php'; }, 1200);
        }
    } catch (err) {
        texto.textContent = 'Não foi possível conectar ao servidor. Verifique sua internet e recarregue a página.';
    }
})();
