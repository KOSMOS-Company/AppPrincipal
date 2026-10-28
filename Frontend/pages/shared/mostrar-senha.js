/* ============================================================
   KOSMOS — Mostrar/ocultar senha (login, cadastro, criar e
   redefinir senha)
   Arquivo: Frontend/pages/shared/mostrar-senha.js

   Liga todo botão .field__toggle ao campo da mesma .field__wrap.
   Antes cada página tinha a função togglePwd() copiada e o botão
   chamava por onclick="togglePwd(this)" — atributo inline, que a
   Content-Security-Policy (script-src 'self') bloqueia.
   ============================================================ */
document.querySelectorAll('.field__toggle').forEach((btn) => {
    btn.addEventListener('click', () => {
        const input = btn.closest('.field__wrap').querySelector('input');
        input.type = input.type === 'password' ? 'text' : 'password';
        btn.classList.toggle('ativo', input.type === 'text');
        btn.setAttribute('aria-label', input.type === 'text' ? 'Esconder senha' : 'Mostrar senha');
        input.focus();
    });
});
