/* ============================================================
   KOSMOS — Criar senha (conta que entrou pelo Google)
   Arquivo: Frontend/pages/login/js/criar-senha.js

   Antes era um <script> escrito dentro do HTML; virou arquivo para
   a Content-Security-Policy poder barrar todo script inline
   (script-src 'self').
   ============================================================ */

const BACKEND = '../../../Backend/php';
const form = document.getElementById('formCriar');

/* Esta tela só faz sentido para quem está logado E ainda não tem
   senha. Sem sessão volta para o login; com senha já criada segue
   direto para o dashboard. */
(async function conferirConta() {
    try {
        const resp = await fetch(`${BACKEND}/conta_dados.php`);

        if (resp.status === 401) {
            window.location.replace('index.html');
            return;
        }

        const json = await resp.json();

        if (json.ok && json.tem_senha) {
            window.location.replace('../dashboard/index.php');
            return;
        }

        if (json.ok && json.nome) {
            const primeiro = json.nome.trim().split(' ')[0];
            document.getElementById('saudacao').textContent =
                `Falta só um passo, ${primeiro}.`;
        }
    } catch (err) {
        mostrarMsg('Não foi possível falar com o servidor. Tente recarregar a página.', 'erro');
    }
})();

/* Medidor de força (mesma regra do cadastro) */
(function medidor() {
    const senha  = document.getElementById('senhaNova');
    const extra  = document.getElementById('senhaExtra');
    const barra  = document.getElementById('senhaBarraFill');
    const reqLen = document.getElementById('reqLen');
    const reqLet = document.getElementById('reqLetra');
    const reqNum = document.getElementById('reqNum');

    senha.addEventListener('input', () => {
        const v = senha.value;
        extra.hidden = v.length === 0;

        const temLen   = v.length >= 8;
        const temLetra = /[a-zA-ZÀ-ÿ]/.test(v);
        const temNum   = /[0-9]/.test(v);
        reqLen.classList.toggle('ok', temLen);
        reqLet.classList.toggle('ok', temLetra);
        reqNum.classList.toggle('ok', temNum);

        const pontos = [temLen, temLetra, temNum].filter(Boolean).length;
        const cores  = ['#f87171', '#f87171', '#fbbf24', '#34d399'];
        barra.style.width = (pontos / 3) * 100 + '%';
        barra.style.background = cores[pontos];
    });
})();

/* Salva a senha (o backend grava o hash em usuarios.senha_hash) */
form.addEventListener('submit', async function (e) {
    e.preventDefault();

    const btn      = document.getElementById('btnSalvar');
    const nova     = document.getElementById('senhaNova').value.trim();
    const confirma = document.getElementById('senhaConfirma').value.trim();
    document.querySelector('.msg-feedback')?.remove();

    if (nova.length < 8)   { mostrarMsg('A senha deve ter no mínimo 8 caracteres.', 'erro'); return; }
    if (nova !== confirma) { mostrarMsg('As senhas não coincidem.', 'erro'); return; }

    btn.disabled = true;
    const textoBtn = btn.innerHTML;
    btn.textContent = 'Salvando...';

    try {
        const dados = new FormData();
        dados.append('senha_nova', nova);
        // conta_senha.php cria a senha sem pedir a atual quando a
        // conta ainda não tem uma (decisão feita no backend).
        const resp = await fetch(`${BACKEND}/conta_senha.php`, { method: 'POST', body: dados });
        const json = await resp.json();

        mostrarMsg(json.msg, json.ok ? 'sucesso' : 'erro');

        if (json.ok) {
            form.style.display = 'none';
            setTimeout(() => { window.location.replace('../dashboard/index.php'); }, 1400);
            return;
        }
    } catch (err) {
        mostrarMsg('Não foi possível conectar ao servidor.', 'erro');
    }

    btn.disabled = false;
    btn.innerHTML = textoBtn;
});

/* Sair sem criar senha (evita ficar preso nesta tela) */
document.getElementById('btnSair').addEventListener('click', async () => {
    try {
        await fetch(`${BACKEND}/logout.php`, { method: 'POST' });
    } catch (err) {
        /* mesmo se falhar, volta para o login */
    }
    sessionStorage.removeItem('kosmos_usuario');
    sessionStorage.removeItem('kosmos_intro');
    window.location.replace('index.html');
});

function mostrarMsg(texto, tipo) {
    document.querySelector('.msg-feedback')?.remove();
    const div = document.createElement('div');
    div.className = 'msg-feedback';
    div.textContent = texto;
    div.style.cssText = 'margin-top:12px;padding:10px 14px;border-radius:8px;font-size:0.875rem;font-weight:500;background:' + (tipo==='sucesso'?'rgba(52,211,153,0.15)':'rgba(239,68,68,0.15)') + ';color:' + (tipo==='sucesso'?'#34d399':'#f87171') + ';border:1px solid ' + (tipo==='sucesso'?'#34d39940':'#f8717140');
    form.after(div);
}
