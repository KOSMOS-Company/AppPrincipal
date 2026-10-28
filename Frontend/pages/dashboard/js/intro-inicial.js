/* ============================================================
   KOSMOS — Abertura animada logo depois do login
   Arquivo: Frontend/pages/dashboard/js/intro-inicial.js
   Carregado no <head> do index.php, SEM defer/async: mostra o
   splash imediatamente (sem flash) se o usuário acabou de logar.
   Antes era um <script> inline; virou arquivo para a
   Content-Security-Policy poder barrar todo script inline.
   ============================================================ */
// No visual Lite a abertura animada não roda: é a tela mais cara do app.
try {
    if (sessionStorage.getItem("kosmos_intro")) {
        if (localStorage.getItem("kosmos_visual") === "lite") {
            sessionStorage.removeItem("kosmos_intro");
        } else {
            document.documentElement.classList.add("com-intro");
        }
    }
} catch (e) { /* navegação privada: sem abertura */ }
