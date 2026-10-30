/* ============================================================
   KOSMOS — Quem já está logado vai direto para o dashboard
   Arquivo: Frontend/pages/shared/ja-logado.js

   Carregado na página inicial, no login e no cadastro. Sem isto, quem
   marcou "Lembrar de mim" fechava o navegador, abria o site de novo e
   caía na página inicial — e o "Entrar" mostrava o formulário, como se
   o login não tivesse sido lembrado. O servidor (sessao_estado.php)
   responde se há sessão, restaurando a do "Lembrar de mim" se preciso.

   As três páginas ficam em pages/<pasta>/, então os caminhos
   relativos abaixo valem para todas.
   ============================================================ */
(function () {
    fetch('../../../Backend/php/sessao_estado.php', { credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (json) {
            // replace: o "voltar" do navegador não traz de novo esta página
            if (json && json.logado) window.location.replace('../dashboard/index.php');
        })
        .catch(function () { /* sem servidor: fica na página, como sempre */ });
})();
