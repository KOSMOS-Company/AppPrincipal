/* ============================================================
   KOSMOS — Visual leve (Lite) antes da primeira pintura
   Arquivo: Frontend/pages/shared/visual-lite.js

   Carregado no <head>, SEM defer/async: precisa rodar antes de a
   página aparecer, senão ela pisca no visual completo e depois
   troca. Antes era um <script> escrito dentro de cada página; virou
   arquivo para a Content-Security-Policy poder barrar todo script
   inline (script-src 'self').
   ============================================================ */
try {
    if (localStorage.getItem('kosmos_visual') === 'lite') {
        document.documentElement.classList.add('kosmos-lite');
    }
} catch (e) { /* navegação privada: visual completo */ }
