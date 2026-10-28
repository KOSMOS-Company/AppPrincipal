/* ============================================================
   KOSMOS — apple.js  (par do css/apple.css)
   O que o CSS sozinho não faz: o "scroll edge" do topo no celular
   (html.apple-rolou). A gaveta "Outras ferramentas" saiu — a
   navegação do celular agora é a gaveta lateral (dashboard.js).
   ============================================================ */
(() => {
    const raiz = document.documentElement;

    /* ---------- 1. Scroll edge ---------- */
    let pendente = false;
    const marcarRolagem = () => {
        pendente = false;
        raiz.classList.toggle("apple-rolou", window.scrollY > 4);
    };
    window.addEventListener("scroll", () => {
        if (!pendente) { pendente = true; requestAnimationFrame(marcarRolagem); }
    }, { passive: true });

    document.addEventListener("DOMContentLoaded", () => {
        marcarRolagem();
    });
})();
