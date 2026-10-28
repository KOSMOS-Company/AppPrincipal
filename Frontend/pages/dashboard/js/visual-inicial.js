/* ============================================================
   KOSMOS — Visual Lite e modo foco antes da primeira pintura
   Arquivo: Frontend/pages/dashboard/js/visual-inicial.js
   Carregado pelo partes/fundo.php, SEM defer/async: a classe tem
   que entrar antes do canvas e do resto da página (ver o
   comentário lá). Antes era um <script> inline; virou arquivo para
   a Content-Security-Policy poder barrar todo script inline.
   ============================================================ */
try {
    if (localStorage.getItem('kosmos_visual') === 'lite') {
        document.documentElement.classList.add('kosmos-lite');
    }
    // Modo foco (some o Orion flutuante e os avisos de XP) — js/visual.js
    if (localStorage.getItem('kosmos_foco') === '1') {
        document.documentElement.classList.add('kosmos-foco');
    }
} catch (e) { /* navegação privada: visual completo */ }
