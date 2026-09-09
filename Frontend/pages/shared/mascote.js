/* ══════════════════════════════════════════════════════════════
   MASCOTE KOSMOS — planetinha acadêmico (Render 3D Fiel)
   100% em código puro (SVG vetorial + gradientes volumétricos 3D).
   Fiel à arte oficial: iluminação esférica suave, capelo chanfrado
   com rim light, óculos volumétricos com reflexos e anel 3D.

   Reações interativas automáticas:
     • As pupilas acompanham o cursor suavemente
     • Pisca naturalmente de tempos em tempos
     • Tapa os olhos quando o usuário digita a senha — e espia de
       volta caso ele ative "mostrar senha"
     • Sorriso aberto e sobrancelhas erguidas com e-mail válido
     • Estado "confuso" na página 404 (data-mascote-humor="confuso")

   Atributos no elemento HTML:
     data-mascote             → ativa a montagem do SVG
     data-mascote-humor="..." → "normal" | "feliz" | "confuso"
     data-mascote-auto="0"    → desliga escuta automática dos campos

   API:
     elemento.mascote = { humor, piscar, olharPara, fecharOlhos }
     window.KosmosMascote = { montar, montarTodos }
   ══════════════════════════════════════════════════════════════ */

(function () {
    'use strict';

    var seq = 0;
    var semMovimento = window.matchMedia('(prefers-reduced-motion: reduce)');
    var ponteiroFino = window.matchMedia('(hover: hover) and (pointer: fine)');

    /* ── Gera o SVG 3D com IDs únicos por instância ─────────────── */
    function desenho(u) {
        return '' +
'<svg class="mascote__svg" viewBox="0 0 240 240" focusable="false" aria-hidden="true">' +
  '<defs>' +
    /* Filtros para luz suave e sombras de oclusão 3D */
    '<filter id="kgGlow' + u + '" x="-20%" y="-20%" width="140%" height="140%">' +
      '<feGaussianBlur stdDeviation="3" result="blur"/>' +
      '<feComposite in="SourceGraphic" in2="blur" operator="over"/>' +
    '</filter>' +
    '<filter id="kgShadow' + u + '" x="-30%" y="-30%" width="160%" height="160%">' +
      '<feGaussianBlur stdDeviation="4"/>' +
    '</filter>' +
    '<filter id="kgOcclusion' + u + '" x="-20%" y="-20%" width="140%" height="140%">' +
      '<feGaussianBlur stdDeviation="2"/>' +
    '</filter>' +

    /* Planeta: Esfera 3D iluminada no topo-esquerdo com sombra profunda */
    '<radialGradient id="kgPlaneta' + u + '" cx="34%" cy="26%" r="76%">' +
      '<stop offset="0%" stop-color="#b673f8"/>' +
      '<stop offset="26%" stop-color="#933cf2"/>' +
      '<stop offset="62%" stop-color="#6b21a8"/>' +
      '<stop offset="86%" stop-color="#4c1d95"/>' +
      '<stop offset="100%" stop-color="#240747"/>' +
    '</radialGradient>' +

    /* Rim Light no topo da esfera */
    '<linearGradient id="kgRim' + u + '" x1="0%" y1="0%" x2="100%" y2="100%">' +
      '<stop offset="0%" stop-color="#e9d5ff" stop-opacity="0.9"/>' +
      '<stop offset="35%" stop-color="#c084fc" stop-opacity="0.4"/>' +
      '<stop offset="70%" stop-color="#9333ea" stop-opacity="0"/>' +
    '</linearGradient>' +

    /* Anel 3D: Frente e Trás */
    '<linearGradient id="kgAnelFrente' + u + '" x1="0%" y1="0%" x2="100%" y2="100%">' +
      '<stop offset="0%" stop-color="#9d4edd"/>' +
      '<stop offset="30%" stop-color="#c084fc"/>' +
      '<stop offset="70%" stop-color="#a855f7"/>' +
      '<stop offset="100%" stop-color="#6b21a8"/>' +
    '</linearGradient>' +
    '<linearGradient id="kgAnelTras' + u + '" x1="0%" y1="0%" x2="100%" y2="100%">' +
      '<stop offset="0%" stop-color="#6b21a8"/>' +
      '<stop offset="50%" stop-color="#8b3fe0"/>' +
      '<stop offset="100%" stop-color="#581c87"/>' +
    '</linearGradient>' +

    /* Capelo 3D */
    '<linearGradient id="kgCapeloTopo' + u + '" x1="25%" y1="10%" x2="75%" y2="90%">' +
      '<stop offset="0%" stop-color="#311c47"/>' +
      '<stop offset="45%" stop-color="#221235"/>' +
      '<stop offset="100%" stop-color="#140822"/>' +
    '</linearGradient>' +
    '<linearGradient id="kgCapeloBase' + u + '" x1="20%" y1="0%" x2="80%" y2="100%">' +
      '<stop offset="0%" stop-color="#382052"/>' +
      '<stop offset="50%" stop-color="#200f33"/>' +
      '<stop offset="100%" stop-color="#10051c"/>' +
    '</linearGradient>' +

    /* Borla Dourada 3D */
    '<linearGradient id="kgTassel' + u + '" x1="0%" y1="0%" x2="100%" y2="0%">' +
      '<stop offset="0%" stop-color="#d97706"/>' +
      '<stop offset="30%" stop-color="#fbbf24"/>' +
      '<stop offset="60%" stop-color="#fef08a"/>' +
      '<stop offset="100%" stop-color="#b45309"/>' +
    '</linearGradient>' +

    /* Armação dos Óculos 3D */
    '<linearGradient id="kgAro' + u + '" x1="30%" y1="0%" x2="70%" y2="100%">' +
      '<stop offset="0%" stop-color="#38224d"/>' +
      '<stop offset="40%" stop-color="#211233"/>' +
      '<stop offset="100%" stop-color="#11051c"/>' +
    '</linearGradient>' +

    /* Clips */
    '<clipPath id="kgClipPlaneta' + u + '"><circle cx="120" cy="130" r="54"/></clipPath>' +
    '<clipPath id="kgClipOlhoE' + u + '"><circle cx="94" cy="124" r="19"/></clipPath>' +
    '<clipPath id="kgClipOlhoD' + u + '"><circle cx="146" cy="124" r="19"/></clipPath>' +
  '</defs>' +

  /* 0. Sombra suave de contato no chão */
  '<ellipse cx="120" cy="208" rx="46" ry="7" fill="#05010a" opacity="0.6" filter="url(#kgShadow' + u + ')"/>' +

  /* 1. Estrelas cósmicas cintilantes */
  '<g class="mascote__estrelas">' +
    '<path class="mascote__estrela" style="--d:0s" d="M188 68 Q189 77 198 78 Q189 79 188 88 Q187 79 178 78 Q187 77 188 68 Z" fill="#d8b4fe" filter="url(#kgGlow' + u + ')"/>' +
    '<path class="mascote__estrela" style="--d:1.2s" d="M182 172 Q183 177 188 178 Q183 179 182 184 Q181 179 176 178 Q181 177 182 172 Z" fill="#c084fc"/>' +
    '<path class="mascote__estrela" style="--d:2.1s" d="M46 136 Q47 142 53 143 Q47 144 46 150 Q45 144 39 143 Q45 142 46 136 Z" fill="#d8b4fe"/>' +
    '<path class="mascote__estrela" style="--d:1.6s" d="M192 150 Q192.5 152.5 195 153 Q192.5 153.5 192 156 Q191.5 153.5 189 153 Q191.5 152.5 192 150 Z" fill="#a855f7"/>' +
    '<circle cx="196" cy="92" r="1.6" fill="#f3e8ff"/>' +
    '<circle cx="42" cy="154" r="1.4" fill="#f3e8ff"/>' +
  '</g>' +

  /* 2. Anel 3D — metade de trás (passa atrás do planeta) */
  '<g transform="rotate(-21 120 137)">' +
    '<path d="M 28 137 A 92 28 0 0 1 212 137 L 186 137 A 66 19 0 0 0 54 137 Z" fill="url(#kgAnelTras' + u + ')"/>' +
    '<path d="M 54 137 A 66 19 0 0 1 186 137" fill="none" stroke="#260840" stroke-width="2.2"/>' +
  '</g>' +

  /* 3. Corpo do planeta (Esfera 3D volumétrica) */
  '<g class="mascote__corpo">' +
    '<circle cx="120" cy="130" r="54" fill="url(#kgPlaneta' + u + ')"/>' +
    '<g clip-path="url(#kgClipPlaneta' + u + ')">' +
      '<ellipse cx="94" cy="94" rx="26" ry="16" fill="#ffffff" opacity="0.22" transform="rotate(-30 94 94)"/>' +
      '<circle cx="82" cy="86" r="12" fill="#ffffff" opacity="0.12"/>' +
      '<circle cx="78" cy="146" r="9" fill="#1b0333" opacity="0.28" filter="url(#kgOcclusion' + u + ')"/>' +
      '<circle cx="148" cy="160" r="11" fill="#150229" opacity="0.35" filter="url(#kgOcclusion' + u + ')"/>' +
      '<circle cx="160" cy="136" r="6" fill="#18032e" opacity="0.25" filter="url(#kgOcclusion' + u + ')"/>' +
      '<circle cx="154" cy="92" r="7" fill="#ffffff" opacity="0.1"/>' +
      '<ellipse cx="150" cy="166" rx="34" ry="20" fill="#110221" opacity="0.4" filter="url(#kgShadow' + u + ')"/>' +
    '</g>' +
    '<circle cx="120" cy="130" r="53.5" fill="none" stroke="url(#kgRim' + u + ')" stroke-width="1.8"/>' +
  '</g>' +

  /* 4. Capelo acadêmico 3D */
  '<g class="mascote__capelo" transform="rotate(-11 118 66)">' +
    '<ellipse cx="118" cy="90" rx="36" ry="10" fill="#100221" opacity="0.45" filter="url(#kgOcclusion' + u + ')"/>' +
    /* Cúpula */
    '<path d="M 76 62 C 76 62, 88 86, 118 86 C 148 86, 160 62, 160 62 L 154 52 L 82 52 Z" fill="url(#kgCapeloBase' + u + ')"/>' +
    '<path d="M 94 56 L 142 56 L 138 80 C 128 83, 108 83, 98 80 Z" fill="#361a52" opacity="0.7"/>' +
    '<path d="M 82 64 Q 118 83 154 64" fill="none" stroke="#4a2770" stroke-width="2.2" stroke-linecap="round"/>' +

    /* Borda 3D chanfrada */
    '<polygon points="38,46 118,74 198,46 198,51 118,79 38,51" fill="#0c0317"/>' +
    '<path d="M 38 51 L 118 79 L 198 51" fill="none" stroke="#1d0a30" stroke-width="1.2"/>' +

    /* Tampo do capelo */
    '<polygon points="118,18 198,46 118,74 38,46" fill="url(#kgCapeloTopo' + u + ')"/>' +
    '<path d="M 39 46 L 118 18 L 197 46" fill="none" stroke="#8c61ba" stroke-width="2.4" stroke-linecap="round"/>' +
    '<circle cx="118" cy="18" r="2.2" fill="#d8b4fe" filter="url(#kgGlow' + u + ')"/>' +

    /* Botão central */
    '<ellipse cx="118" cy="46" rx="5.5" ry="3.8" fill="#fbbf24"/>' +
    '<ellipse cx="118" cy="45.2" rx="4" ry="2.6" fill="#fde047"/>' +
    '<ellipse cx="118" cy="47" rx="5.5" ry="2" fill="#d97706" opacity="0.6"/>' +

    /* Cordão dourado */
    '<path d="M 118 46 C 88 43, 56 52, 46 68" fill="none" stroke="#d97706" stroke-width="4.2" stroke-linecap="round"/>' +
    '<path d="M 118 45.5 C 88 42.5, 56 51.5, 46 67.5" fill="none" stroke="#fbbf24" stroke-width="3" stroke-linecap="round"/>' +
    '<path d="M 118 45 C 88 42, 56 51, 46 67" fill="none" stroke="#fef08a" stroke-width="1.4" stroke-linecap="round"/>' +

    /* Borla pendular */
    '<g transform="translate(46, 68)">' +
      '<g class="mascote__borla">' +
        '<circle cx="0" cy="5" r="4.2" fill="#d97706"/>' +
        '<circle cx="-0.5" cy="4.2" r="3.6" fill="#fbbf24"/>' +
        '<circle cx="-1.2" cy="3.5" r="1.6" fill="#fef08a"/>' +
        '<path d="M -6 8.5 C -7 18, -8 26, -5 32 C -1 35, 4 35, 7 32 C 10 26, 9 18, 8 8.5 Z" fill="url(#kgTassel' + u + ')"/>' +
        '<path d="M -3 12 L -2.5 30" stroke="#b45309" stroke-width="1.4" stroke-linecap="round"/>' +
        '<path d="M 0.5 12 L 1 31" stroke="#fef08a" stroke-width="1.4" stroke-linecap="round"/>' +
        '<path d="M 4 12 L 4.5 30" stroke="#b45309" stroke-width="1.4" stroke-linecap="round"/>' +
        '<ellipse cx="1" cy="32" rx="5.5" ry="2" fill="#92400e" opacity="0.6"/>' +
      '</g>' +
    '</g>' +
  '</g>' +

  /* 5. Anel 3D — metade da frente (passa na frente da barriga) */
  '<g transform="rotate(-21 120 137)">' +
    '<path d="M 28 137 A 92 28 0 0 0 212 137" fill="none" stroke="#120224" stroke-width="12" opacity="0.35" filter="url(#kgOcclusion' + u + ')"/>' +
    '<path d="M 28 137 A 92 28 0 0 0 212 137 L 186 137 A 66 19 0 0 1 54 137 Z" fill="url(#kgAnelFrente' + u + ')"/>' +
    '<path d="M 38 142 A 88 26 0 0 0 202 142" fill="none" stroke="#f3e8ff" stroke-width="2.4" stroke-linecap="round" opacity="0.85"/>' +
    '<path d="M 52 147 A 84 25 0 0 0 188 147" fill="none" stroke="#ffffff" stroke-width="1.2" opacity="0.6" stroke-linecap="round"/>' +
  '</g>' +

  /* 6. Rosto 3D */
  '<g class="mascote__rosto">' +
    '<ellipse cx="94" cy="126" rx="20" ry="18" fill="#140226" opacity="0.4" filter="url(#kgOcclusion' + u + ')"/>' +
    '<ellipse cx="146" cy="126" rx="20" ry="18" fill="#140226" opacity="0.4" filter="url(#kgOcclusion' + u + ')"/>' +

    /* Sobrancelhas */
    '<g class="mascote__sobrancelhas">' +
      '<path d="M 82 100 C 87 94, 97 94, 102 98" fill="none" stroke="#1d0633" stroke-width="4.2" stroke-linecap="round"/>' +
      '<path d="M 138 98 C 143 94, 153 94, 158 100" fill="none" stroke="#1d0633" stroke-width="4.2" stroke-linecap="round"/>' +
    '</g>' +

    /* Olho Esquerdo */
    '<g class="mascote__olho" clip-path="url(#kgClipOlhoE' + u + ')">' +
      '<circle cx="94" cy="124" r="19" fill="#ffffff"/>' +
      '<path d="M 76 122 A 19 19 0 0 1 112 122 Z" fill="#e9d5ff" opacity="0.5"/>' +
      '<g class="mascote__pupila">' +
        '<circle cx="97" cy="123" r="13.2" fill="#0d0217"/>' +
        '<circle cx="92.5" cy="118.5" r="4.8" fill="#ffffff"/>' +
        '<circle cx="101.5" cy="127.5" r="2.1" fill="#ffffff"/>' +
      '</g>' +
      '<g class="mascote__palpebra">' +
        '<rect x="74" y="104" width="40" height="40" fill="#6b21a8"/>' +
        '<path d="M 75 124 Q 94 133 113 124" fill="none" stroke="#18042b" stroke-width="3.6" stroke-linecap="round"/>' +
      '</g>' +
    '</g>' +

    /* Olho Direito */
    '<g class="mascote__olho" clip-path="url(#kgClipOlhoD' + u + ')">' +
      '<circle cx="146" cy="124" r="19" fill="#ffffff"/>' +
      '<path d="M 128 122 A 19 19 0 0 1 164 122 Z" fill="#e9d5ff" opacity="0.5"/>' +
      '<g class="mascote__pupila">' +
        '<circle cx="143" cy="123" r="13.2" fill="#0d0217"/>' +
        '<circle cx="138.5" cy="118.5" r="4.8" fill="#ffffff"/>' +
        '<circle cx="147.5" cy="127.5" r="2.1" fill="#ffffff"/>' +
      '</g>' +
      '<g class="mascote__palpebra">' +
        '<rect x="126" y="104" width="40" height="40" fill="#6b21a8"/>' +
        '<path d="M 127 124 Q 146 133 165 124" fill="none" stroke="#18042b" stroke-width="3.6" stroke-linecap="round"/>' +
      '</g>' +
    '</g>' +

    /* Óculos 3D com aros chanfrados */
    '<circle cx="94" cy="124" r="19" fill="none" stroke="url(#kgAro' + u + ')" stroke-width="4.4"/>' +
    '<circle cx="94" cy="124" r="19" fill="none" stroke="#4a2868" stroke-width="1.2" opacity="0.6"/>' +
    '<path d="M 80 114 A 19 19 0 0 1 108 114" fill="none" stroke="#8b5cf6" stroke-width="1.5" opacity="0.7" stroke-linecap="round"/>' +

    '<circle cx="146" cy="124" r="19" fill="none" stroke="url(#kgAro' + u + ')" stroke-width="4.4"/>' +
    '<circle cx="146" cy="124" r="19" fill="none" stroke="#4a2868" stroke-width="1.2" opacity="0.6"/>' +
    '<path d="M 132 114 A 19 19 0 0 1 160 114" fill="none" stroke="#8b5cf6" stroke-width="1.5" opacity="0.7" stroke-linecap="round"/>' +

    '<path d="M 112 121 C 116 118, 124 118, 128 121" fill="none" stroke="url(#kgAro' + u + ')" stroke-width="4.2" stroke-linecap="round"/>' +
    '<path d="M 113 120 C 117 117.5, 123 117.5, 127 120" fill="none" stroke="#6d28d9" stroke-width="1.2" stroke-linecap="round"/>' +

    '<line x1="75" y1="124" x2="64" y2="128" stroke="url(#kgAro' + u + ')" stroke-width="4" stroke-linecap="round"/>' +
    '<line x1="165" y1="124" x2="176" y2="118" stroke="url(#kgAro' + u + ')" stroke-width="4" stroke-linecap="round"/>' +

    /* Bocas */
    '<path class="mascote__boca mascote__boca--padrao" d="M 114 138 Q 120 144 126 138" fill="none" stroke="#18042b" stroke-width="3.8" stroke-linecap="round"/>' +
    '<path class="mascote__boca mascote__boca--feliz"  d="M 111 137 Q 120 148 129 137" fill="none" stroke="#18042b" stroke-width="4" stroke-linecap="round"/>' +
    '<path class="mascote__boca mascote__boca--confusa" d="M 112 140 Q 116 136 120 140 T 128 139" fill="none" stroke="#18042b" stroke-width="3.8" stroke-linecap="round"/>' +
  '</g>' +

  /* 7. Interrogações flutuantes (404) */
  '<g class="mascote__duvidas">' +
    '<text class="mascote__duvida" style="--d:0s" x="198" y="112" text-anchor="middle">?</text>' +
    '<text class="mascote__duvida" style="--d:1.4s" x="32" y="136" text-anchor="middle">?</text>' +
  '</g>' +
'</svg>';
    }

    /* ── Monta o mascote no elemento ─────────────────────────── */
    function montar(el) {
        if (!el || el.dataset.mascotePronto) return el && el.mascote;
        el.dataset.mascotePronto = '1';
        el.classList.add('mascote');
        el.setAttribute('aria-hidden', 'true');
        el.innerHTML = desenho(++seq);

        var svg     = el.querySelector('.mascote__svg');
        var pupilas = el.querySelectorAll('.mascote__pupila');
        var timers  = [];

        function olharUnidades(dx, dy) {
            for (var i = 0; i < pupilas.length; i++) {
                pupilas[i].style.transform = 'translate(' + dx + 'px,' + dy + 'px)';
            }
        }

        /* Os olhinhos acompanham o cursor */
        function olharPara(x, y) {
            var r = svg.getBoundingClientRect();
            if (!r.width) return;
            var cx  = r.left + r.width * 0.5;
            var cy  = r.top + r.height * 0.54;
            var ang = Math.atan2(y - cy, x - cx);
            var d   = Math.min(3.2, Math.hypot(x - cx, y - cy) / 40);
            olharUnidades(Math.cos(ang) * d, Math.sin(ang) * d);
        }

        function piscar(ms) {
            if (el.classList.contains('mascote--fechado')) return;
            el.classList.add('mascote--pisca');
            timers.push(setTimeout(function () {
                el.classList.remove('mascote--pisca');
            }, ms || 180));
        }

        function humor(nome) {
            el.classList.remove('mascote--feliz', 'mascote--confuso');
            if (nome && nome !== 'normal') el.classList.add('mascote--' + nome);
        }

        function fecharOlhos(sim) {
            el.classList.toggle('mascote--fechado', !!sim);
        }

        el.mascote = {
            humor: humor,
            piscar: piscar,
            olharPara: olharPara,
            fecharOlhos: fecharOlhos
        };

        humor(el.dataset.mascoteHumor);

        if (semMovimento.matches) return el.mascote;

        /* Rastreio do mouse (ponteiro fino) */
        if (ponteiroFino.matches) {
            var alvoX = 0, alvoY = 0, pedido = 0;
            document.addEventListener('mousemove', function (e) {
                alvoX = e.clientX;
                alvoY = e.clientY;
                if (pedido) return;
                pedido = requestAnimationFrame(function () {
                    pedido = 0;
                    if (!el.classList.contains('mascote--fechado')) {
                        olharPara(alvoX, alvoY);
                    }
                });
            }, { passive: true });
        } else {
            timers.push(setInterval(function () {
                if (el.classList.contains('mascote--fechado')) return;
                olharUnidades((Math.random() * 5 - 2.5), (Math.random() * 3.5 - 1.75));
            }, 3200));
        }

        /* Piscadinha periódica sutil */
        (function agendarPiscada() {
            timers.push(setTimeout(function () {
                piscar();
                agendarPiscada();
            }, 3500 + Math.random() * 3000));
        })();

        return el.mascote;
    }

    /* ── Ligações inteligentes aos campos ────────────────────── */
    function ligarCampos(el) {
        if (el.dataset.mascoteAuto === '0') return;

        /* Pálpebras descem ao digitar senha */
        var senhas = [].slice.call(document.querySelectorAll('input[type="password"]'));
        if (senhas.length) {
            var avaliar = function () {
                var ativo = document.activeElement;
                var cobrir = senhas.some(function (i) { return i === ativo && i.type === 'password'; });
                el.classList.toggle('mascote--fechado', cobrir);
            };
            senhas.forEach(function (campo) {
                campo.addEventListener('focus', avaliar);
                campo.addEventListener('blur', avaliar);
                campo.addEventListener('input', avaliar);
                if (window.MutationObserver) {
                    new MutationObserver(avaliar).observe(campo, {
                        attributes: true, attributeFilter: ['type']
                    });
                }
            });
            avaliar();
        }

        /* Sorriso abre ao inserir e-mail válido */
        var emails = document.querySelectorAll('input[type="email"]');
        for (var i = 0; i < emails.length; i++) {
            (function (campo) {
                campo.addEventListener('input', function () {
                    if (el.classList.contains('mascote--confuso')) return;
                    var ok = campo.value.trim() !== '' && campo.checkValidity();
                    el.classList.toggle('mascote--feliz', ok);
                });
            })(emails[i]);
        }
    }

    function montarTodos() {
        var alvos = document.querySelectorAll('[data-mascote]');
        for (var i = 0; i < alvos.length; i++) {
            montar(alvos[i]);
            ligarCampos(alvos[i]);
        }
    }

    window.KosmosMascote = {
        montar: montar,
        montarTodos: montarTodos
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', montarTodos);
    } else {
        montarTodos();
    }
})();
