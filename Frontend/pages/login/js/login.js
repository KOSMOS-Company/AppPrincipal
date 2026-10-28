/* ============================================================
   KOSMOS — Login (entrar, esqueci a senha)
   Arquivo: Frontend/pages/login/js/login.js

   Antes era um <script> escrito dentro do HTML; virou arquivo para
   a Content-Security-Policy poder barrar todo script inline
   (script-src 'self').
   ============================================================ */

document.querySelector('.form').addEventListener('submit', async function (e) {
    e.preventDefault();
    const btn   = this.querySelector('button[type="submit"]');
    const btnHtml = btn.innerHTML;
    const email = document.getElementById('email').value.trim();
    const senha = document.getElementById('senha').value.trim();
    document.querySelector('.msg-feedback')?.remove();
    const dados = new FormData();
    dados.append('email', email);
    dados.append('senha', senha);
    btn.disabled    = true;
    btn.textContent = 'Aguarde...';
    try {
        const resposta = await fetch('../../../Backend/php/login.php', {
            method : 'POST',
            body   : dados,
        });
        const json = await resposta.json();
        mostrarMsg(json.msg, json.ok ? 'sucesso' : 'erro');
        if (json.ok) {
            sessionStorage.setItem('kosmos_usuario', json.nome);
            sessionStorage.setItem('kosmos_intro', '1'); // dispara o splash no dashboard
            setTimeout(() => { window.location.href = '../dashboard/index.php'; }, 1200);
        }
    } catch (err) {
        mostrarMsg('Nao foi possivel conectar ao servidor. Verifique se o XAMPP esta rodando.', 'erro');
    } finally {
        btn.disabled  = false;
        btn.innerHTML = btnHtml;
    }
});

function mostrarMsg(texto, tipo) {
    const div = document.createElement('div');
    div.className = 'msg-feedback msg-feedback--' + tipo;
    div.textContent = texto;
    div.style.cssText = 'margin-top:12px;padding:10px 14px;border-radius:8px;font-size:0.875rem;font-weight:500;background:' + (tipo==='sucesso'?'rgba(52,211,153,0.15)':'rgba(239,68,68,0.15)') + ';color:' + (tipo==='sucesso'?'#34d399':'#f87171') + ';border:1px solid ' + (tipo==='sucesso'?'#34d39940':'#f8717140');
    document.querySelector('.form').after(div);
}

/* --- Entrar com o Google: shared/google-login.js (usa o mostrarMsg acima) --- */

/* --- Modal "Esqueceu a senha?" --- */
const modalEsqueci = document.getElementById('modalEsqueci');
document.getElementById('linkEsqueci').addEventListener('click', (e) => {
    e.preventDefault();
    modalEsqueci.classList.add('open');
});
document.getElementById('fecharEsqueci').addEventListener('click', () => modalEsqueci.classList.remove('open'));
modalEsqueci.addEventListener('click', (e) => { if (e.target === modalEsqueci) modalEsqueci.classList.remove('open'); });

document.getElementById('formEsqueci').addEventListener('submit', async function (e) {
    e.preventDefault();
    const btn   = document.getElementById('btnEsqueci');
    const email = document.getElementById('emailEsqueci').value.trim();
    const msgEl = document.getElementById('msgEsqueci');
    btn.disabled = true; btn.textContent = 'Enviando...';
    try {
        const dados = new FormData();
        dados.append('email', email);
        const resp = await fetch('../../../Backend/php/senha_esqueci.php', { method: 'POST', body: dados });
        const json = await resp.json();
        msgEl.hidden = false;
        msgEl.textContent = json.msg;
        msgEl.style.cssText = 'padding:9px 12px;border-radius:8px;font-size:.85rem;margin-bottom:12px;background:' + (json.ok ? 'rgba(52,211,153,.15)' : 'rgba(239,68,68,.15)') + ';color:' + (json.ok ? '#34d399' : '#f87171') + ';';
    } catch (err) {
        msgEl.hidden = false;
        msgEl.textContent = 'Não foi possível conectar ao servidor.';
        msgEl.style.cssText = 'padding:9px 12px;border-radius:8px;font-size:.85rem;margin-bottom:12px;background:rgba(239,68,68,.15);color:#f87171;';
    } finally {
        btn.disabled = false; btn.textContent = 'Enviar link';
    }
});

/* ═══ Interações visuais (não alteram a lógica do formulário) ═══ */

/* --- Mascote: vive em shared/mascote.js (segue o cursor, pisca,
       tapa os olhos na senha e sorri com o e-mail válido) --- */

/* --- Check visual de e-mail válido --- */
(function () {
    const email = document.getElementById('email');
    const field = document.getElementById('fieldEmail');
    email.addEventListener('input', () => {
        field.classList.toggle('field--ok', email.value.trim() !== '' && email.checkValidity());
    });
})();

/* --- Aviso de Caps Lock na senha --- */
(function () {
    const senha = document.getElementById('senha');
    const aviso = document.getElementById('capsAviso');
    function checa(e) {
        if (typeof e.getModifierState === 'function') {
            aviso.hidden = !e.getModifierState('CapsLock');
        }
    }
    senha.addEventListener('keydown', checa);
    senha.addEventListener('keyup', checa);
    senha.addEventListener('blur', () => { aviso.hidden = true; });
})();
