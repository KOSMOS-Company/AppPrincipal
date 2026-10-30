/* ============================================================
   KOSMOS — Cadastro
   Arquivo: Frontend/pages/cadastro/js/cadastro.js

   Antes era um <script> escrito dentro do HTML; virou arquivo para
   a Content-Security-Policy poder barrar todo script inline
   (script-src 'self').
   ============================================================ */

/* --- Envio do formulário para o backend PHP --- */
document.querySelector('.form').addEventListener('submit', async function (e) {
    e.preventDefault();

    const btn    = this.querySelector('.btn-submit');
    const btnHtml = btn.innerHTML;
    const nome   = document.getElementById('nome').value.trim();
    const email  = document.getElementById('email').value.trim();
    const senha  = document.getElementById('senha').value.trim();

    // Remove mensagem anterior
    document.querySelector('.msg-feedback')?.remove();

    // Monta os dados do formulário
    const dados = new FormData();
    dados.append('nome',  nome);
    dados.append('email', email);
    dados.append('senha', senha);

    btn.disabled    = true;
    btn.textContent = 'Aguarde…';

    try {
        const resposta = await fetch('../../../Backend/php/cadastro.php', {
            method : 'POST',
            body   : dados,
        });

        const json = await resposta.json();
        mostrarMsg(json.msg, json.ok ? 'sucesso' : 'erro');

        if (json.ok) {
            this.reset();
            // Com confirmação de e-mail, a pessoa precisa abrir o e-mail
            // antes de entrar: a mensagem fica na tela, sem redirecionar
            if (!json.confirmar) {
                setTimeout(() => {
                    window.location.href = '../login/index.html';
                }, 1800);
            }
        }
    } catch (err) {
        mostrarMsg('Não foi possível conectar ao servidor. Verifique sua internet e tente novamente.', 'erro');
    } finally {
        btn.disabled  = false;
        btn.innerHTML = btnHtml;
    }
});

/* --- Exibe mensagem de feedback --- */
function mostrarMsg(texto, tipo) {
    const div = document.createElement('div');
    div.className = 'msg-feedback msg-feedback--' + tipo;
    div.textContent = texto;
    div.style.cssText = `
        margin-top: 12px;
        padding: 10px 14px;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        background: ${tipo === 'sucesso' ? 'rgba(52,211,153,0.15)' : 'rgba(239,68,68,0.15)'};
        color: ${tipo === 'sucesso' ? '#34d399' : '#f87171'};
        border: 1px solid ${tipo === 'sucesso' ? '#34d39940' : '#f8717140'};
    `;
    document.querySelector('.form').after(div);
}

/* --- Entrar com o Google: shared/google-login.js (usa o mostrarMsg acima) --- */

/* ═══ Interações visuais (não alteram a lógica do formulário) ═══ */

/* --- Mascote: vive em shared/mascote.js (segue o cursor, pisca,
       tapa os olhos na senha e sorri com o e-mail válido) --- */

/* --- Validação visual + progresso do cadastro --- */
(function () {
    const nome  = document.getElementById('nome');
    const email = document.getElementById('email');
    const senha = document.getElementById('senha');
    const fNome  = document.getElementById('fieldNome');
    const fEmail = document.getElementById('fieldEmail');
    const fSenha = document.getElementById('fieldSenha');
    const fill   = document.getElementById('cadFill');
    const rocket = document.getElementById('cadRocket');
    const label  = document.getElementById('cadLabel');

    const extra    = document.getElementById('senhaExtra');
    const barra    = document.getElementById('senhaBarraFill');
    const reqLen   = document.getElementById('reqLen');
    const reqLetra = document.getElementById('reqLetra');
    const reqNum   = document.getElementById('reqNum');

    function nomeOk()  { return nome.value.trim().length >= 3; }
    function emailOk() { return email.value.trim() !== '' && email.checkValidity(); }
    function senhaOk() { return senha.value.length >= 8; }

    function atualizaProgresso() {
        fNome.classList.toggle('field--ok', nomeOk());
        fEmail.classList.toggle('field--ok', emailOk());
        fSenha.classList.toggle('field--ok', senhaOk());

        const validos = [nomeOk(), emailOk(), senhaOk()].filter(Boolean).length;
        const pct = (validos / 3) * 100;
        fill.style.width = pct + '%';
        rocket.style.left = pct + '%';
        label.textContent = validos === 3
            ? 'Tudo pronto para decolar!'
            : validos + ' de 3 campos preenchidos';
    }

    function atualizaSenha() {
        const v = senha.value;
        extra.hidden = v.length === 0;

        const temLen   = v.length >= 8;
        const temLetra = /[a-zA-ZÀ-ÿ]/.test(v);
        const temNum   = /[0-9]/.test(v);
        reqLen.classList.toggle('ok', temLen);
        reqLetra.classList.toggle('ok', temLetra);
        reqNum.classList.toggle('ok', temNum);

        const pontos = [temLen, temLetra, temNum].filter(Boolean).length;
        const cores  = ['#f87171', '#f87171', '#fbbf24', '#34d399'];
        barra.style.width = (pontos / 3) * 100 + '%';
        barra.style.background = cores[pontos];
    }

    nome.addEventListener('input', atualizaProgresso);
    email.addEventListener('input', atualizaProgresso);
    senha.addEventListener('input', () => { atualizaSenha(); atualizaProgresso(); });
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
