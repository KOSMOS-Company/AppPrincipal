/* ============================================================
   KOSMOS — inicio.js
   O que a landing page inteira usa: header, menu do celular,
   entrada na rolagem, âncoras, a máquina de escrever do hero e o
   motor de rolagem (títulos, saída do hero, parallax leve).

   A seção de ferramentas (a constelação) tem o próprio arquivo:
   js/constelacao.js.
   ============================================================ */

(function () {
    'use strict';

    const reduzMovimento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const lite = document.documentElement.classList.contains('kosmos-lite');

    /* ────────────────────────────────
       Header, barra de progresso e voltar ao topo
       ──────────────────────────────── */
    const header = document.getElementById('header');
    const progresso = document.getElementById('scrollProgress');
    const backTop = document.getElementById('backTop');

    function aoRolar() {
        const y = window.scrollY;
        header.classList.toggle('header--scrolled', y > 20);

        const total = document.documentElement.scrollHeight - window.innerHeight;
        progresso.style.transform = 'scaleX(' + (total > 0 ? y / total : 0).toFixed(4) + ')';

        backTop.classList.toggle('show', y > 600);
    }
    window.addEventListener('scroll', aoRolar, { passive: true });
    aoRolar();

    backTop.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: reduzMovimento ? 'auto' : 'smooth' });
    });

    /* ────────────────────────────────
       Menu do celular
       ──────────────────────────────── */
    const hamburger = document.getElementById('hamburger');
    const mobileNav = document.getElementById('mobileNav');

    function abrirMenu(sim) {
        hamburger.classList.toggle('active', sim);
        mobileNav.classList.toggle('open', sim);
        hamburger.setAttribute('aria-expanded', String(sim));
        hamburger.setAttribute('aria-label', sim ? 'Fechar o menu' : 'Abrir o menu');
    }
    hamburger.addEventListener('click', () => abrirMenu(!mobileNav.classList.contains('open')));
    mobileNav.querySelectorAll('a').forEach(link => link.addEventListener('click', () => abrirMenu(false)));
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && mobileNav.classList.contains('open')) { abrirMenu(false); hamburger.focus(); }
    });

    /* ────────────────────────────────
       Entrada na rolagem
       ──────────────────────────────── */
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15 });
    document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

    /* ────────────────────────────────
       Âncoras
       href="#" (o logo) volta ao topo. Antes ia direto para
       querySelector('#'), que é um seletor inválido e lança erro.
       ──────────────────────────────── */
    document.querySelectorAll('a[href^="#"]').forEach(a => {
        a.addEventListener('click', e => {
            const id = a.getAttribute('href');
            const comportamento = reduzMovimento ? 'auto' : 'smooth';
            if (id === '#') {
                e.preventDefault();
                window.scrollTo({ top: 0, behavior: comportamento });
                return;
            }
            const alvo = document.querySelector(id);
            if (alvo) { e.preventDefault(); alvo.scrollIntoView({ behavior: comportamento }); }
        });
    });

    /* ────────────────────────────────
       Hero: palavra rotativa (máquina de escrever)
       ──────────────────────────────── */
    (function () {
        const alvo = document.getElementById('rotatorWord');
        if (!alvo || reduzMovimento) return;

        /* Rotaciona a DOR. São curtas de propósito: palavra longa empurra
           o título e quebra a linha. */
        const palavras = ['esquecer', 'se perder', 'travar', 'não render'];
        let idx = 0, pos = palavras[0].length, apagando = false;

        /* A caixa da palavra tem a largura da palavra DA VEZ e só muda
           com a linha vazia, deslizando para o novo centro antes de a
           próxima ser digitada. Com a largura da maior palavra fixa, as
           curtas deixavam um vão à direita e a linha centralizada
           entortava. */
        const rotator = alvo.closest('.hero__rotator');
        const titulo = alvo.closest('.hero__title');
        const celular = window.matchMedia('(max-width: 680px)');   // lá a linha quebra livre
        let larguras = [];

        function medirPalavras() {
            const medidor = document.createElement('span');
            medidor.style.cssText = 'position:absolute;visibility:hidden;white-space:nowrap;';
            titulo.appendChild(medidor);
            larguras = palavras.map(p => {
                medidor.textContent = p;
                return medidor.getBoundingClientRect().width;
            });
            medidor.remove();
            aplicarLargura();
        }
        function aplicarLargura() {
            if (celular.matches || !larguras.length) {
                rotator.style.width = '';
                return;
            }
            rotator.style.width = Math.ceil(larguras[idx]) + 12 + 'px';   // +12: o cursor piscando
        }
        medirPalavras();
        if (document.fonts && document.fonts.ready) document.fonts.ready.then(medirPalavras);
        window.addEventListener('resize', medirPalavras);

        function passo() {
            const palavra = palavras[idx];
            if (apagando) {
                pos--;
                alvo.textContent = palavra.slice(0, pos);
                if (pos === 0) {
                    apagando = false;
                    idx = (idx + 1) % palavras.length;
                    aplicarLargura();
                    setTimeout(passo, 420);   // espera o deslize da caixa
                    return;
                }
                setTimeout(passo, 45);
            } else {
                pos++;
                alvo.textContent = palavras[idx].slice(0, pos);
                if (pos >= palavras[idx].length) {
                    apagando = true;
                    setTimeout(passo, 2200);   // segura a palavra completa
                } else {
                    setTimeout(passo, 95);
                }
            }
        }
        setTimeout(() => { apagando = true; passo(); }, 2600);
    })();

    /* ────────────────────────────────
       Motor de rolagem
       Liga html.scrolly (o portão do CSS de movimento). Sem ele, com
       "reduzir movimento" ou no visual Lite, tudo fica no layout comum.
       ──────────────────────────────── */
    if (reduzMovimento || lite) return;
    document.documentElement.classList.add('scrolly');

    const clamp = (v, a, b) => Math.min(b, Math.max(a, v));

    /* Títulos de seção: as palavras sobem uma a uma */
    const obsTitulos = new IntersectionObserver((entries) => {
        entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('visible'); });
    }, { threshold: 0.35 });

    document.querySelectorAll('.section-title').forEach(titulo => {
        const walker = document.createTreeWalker(titulo, NodeFilter.SHOW_TEXT);
        const textos = [];
        while (walker.nextNode()) textos.push(walker.currentNode);
        let wi = 0;
        textos.forEach(no => {
            const frag = document.createDocumentFragment();
            no.textContent.split(/(\s+)/).forEach(p => {
                if (!p) return;
                if (/^\s+$/.test(p)) { frag.appendChild(document.createTextNode(p)); return; }
                const s = document.createElement('span');
                s.className = 'palavra';
                s.style.setProperty('--wi', wi++);
                s.textContent = p;
                frag.appendChild(s);
            });
            no.parentNode.replaceChild(frag, no);
        });
        obsTitulos.observe(titulo);
    });

    // -1..1 conforme o elemento cruza o centro da tela
    function posViewport(el) {
        const r = el.getBoundingClientRect();
        const meio = r.top + r.height / 2 - window.innerHeight / 2;
        return clamp(meio / ((window.innerHeight + r.height) / 2), -1, 1);
    }

    const tarefas = [];

    const hero = document.querySelector('.hero');
    if (hero) {
        tarefas.push(() => {
            const p = clamp(window.scrollY / (hero.offsetHeight * 0.85), 0, 1);
            hero.style.setProperty('--hp', p.toFixed(4));
        });
    }

    [['.cta-final__orb', '.cta-final'], ['.marquee', '.marquee']].forEach(([alvoSel, refSel]) => {
        const alvo = document.querySelector(alvoSel);
        const ref = document.querySelector(refSel);
        if (!alvo || !ref) return;
        tarefas.push(() => alvo.style.setProperty('--pv', posViewport(ref).toFixed(4)));
    });

    /* Recalcula só quando a rolagem ou a janela mudou */
    let ultimoY = -1, ultimoW = -1, ultimoH = -1;
    function loop() {
        if (window.scrollY !== ultimoY || window.innerWidth !== ultimoW || window.innerHeight !== ultimoH) {
            ultimoY = window.scrollY;
            ultimoW = window.innerWidth;
            ultimoH = window.innerHeight;
            tarefas.forEach(fn => fn());
        }
        requestAnimationFrame(loop);
    }
    requestAnimationFrame(loop);
})();
