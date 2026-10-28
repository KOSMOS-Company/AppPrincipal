/* ============================================================
   KOSMOS — Termos e Política (barra de leitura e revelar ao rolar)
   Arquivo: Frontend/pages/cadastro/js/legal.js

   Antes era um <script> escrito dentro do HTML; virou arquivo para
   a Content-Security-Policy poder barrar todo script inline
   (script-src 'self').
   ============================================================ */

/* Barra de progresso de scroll */
(function () {
    const barra = document.getElementById('scrollProgress');
    if (barra) {
        window.addEventListener('scroll', () => {
            const total = document.documentElement.scrollHeight - window.innerHeight;
            barra.style.width = (total > 0 ? (window.scrollY / total) * 100 : 0) + '%';
        }, { passive: true });
    }

    /* Reveal ao rolar */
    const semMovimento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (semMovimento) {
        document.querySelectorAll('.reveal').forEach(el => el.classList.add('visible'));
        return;
    }
    const obs = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                obs.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });
    document.querySelectorAll('.reveal').forEach(el => obs.observe(el));
})();
