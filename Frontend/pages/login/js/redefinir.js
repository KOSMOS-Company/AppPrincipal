/* ============================================================
   KOSMOS — Redefinir senha (link do e-mail)
   Arquivo: Frontend/pages/login/js/redefinir.js

   Antes era um <script> escrito dentro do HTML; virou arquivo para
   a Content-Security-Policy poder barrar todo script inline
   (script-src 'self').
   ============================================================ */

const token = new URLSearchParams(location.search).get('token') || '';
const form  = document.getElementById('formReset');

if (!token) {
    mostrarMsg('Link inválido. Solicite uma nova redefinição na tela de login.', 'erro');
    form.querySelector('button[type="submit"]').disabled = true;
}

form.addEventListener('submit', async function (e) {
    e.preventDefault();
    const btn      = this.querySelector('button[type="submit"]');
    const nova     = document.getElementById('senhaNova').value.trim();
    const confirma = document.getElementById('senhaConfirma').value.trim();
    document.querySelector('.msg-feedback')?.remove();

    if (nova !== confirma) { mostrarMsg('As senhas não coincidem.', 'erro'); return; }
    if (nova.length < 8)   { mostrarMsg('A senha deve ter no mínimo 8 caracteres.', 'erro'); return; }

    btn.disabled = true; btn.textContent = 'Aguarde...';
    try {
        const dados = new FormData();
        dados.append('token', token);
        dados.append('senha_nova', nova);
        const resp = await fetch('../../../Backend/php/senha_resetar.php', { method: 'POST', body: dados });
        const json = await resp.json();
        mostrarMsg(json.msg, json.ok ? 'sucesso' : 'erro');
        if (json.ok) {
            form.reset();
            form.style.display = 'none';
            setTimeout(() => { window.location.href = 'index.html'; }, 1800);
        }
    } catch (err) {
        mostrarMsg('Não foi possível conectar ao servidor.', 'erro');
    } finally {
        btn.disabled = false; btn.textContent = 'Redefinir senha';
    }
});

function mostrarMsg(texto, tipo) {
    document.querySelector('.msg-feedback')?.remove();
    const div = document.createElement('div');
    div.className = 'msg-feedback';
    div.textContent = texto;
    div.style.cssText = 'margin-top:12px;padding:10px 14px;border-radius:8px;font-size:0.875rem;font-weight:500;background:' + (tipo==='sucesso'?'rgba(52,211,153,0.15)':'rgba(239,68,68,0.15)') + ';color:' + (tipo==='sucesso'?'#34d399':'#f87171') + ';border:1px solid ' + (tipo==='sucesso'?'#34d39940':'#f8717140');
    form.after(div);
}
