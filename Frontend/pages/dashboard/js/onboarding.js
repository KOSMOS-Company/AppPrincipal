/* ============================================================
   KOSMOS — onboarding.js (Pesquisa de Perfil Inicial do Aluno)
   Controla a navegação por etapas, reações do mascote e
   envio das preferências para o backend.
   ============================================================ */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var modal = document.getElementById('onboardingModal');
        if (!modal) return;

        var passos = modal.querySelectorAll('.onb-passo');
        var barra = document.getElementById('onbBarraProgresso');
        var badge = document.getElementById('onbBadgeEtapa');
        var btnAvancar = document.getElementById('onbBtnAvancar');
        var btnVoltar = document.getElementById('onbBtnVoltar');
        var btnPular = document.getElementById('onbBtnPular');
        var mascoteEl = document.getElementById('onbMascote');

        var etapaAtual = 1;
        var totalEtapas = 3;

        // Se a intro está tocando, espera ela terminar para abrir o modal suavemente
        function abrirModal() {
            var temIntro = document.documentElement.classList.contains('com-intro');
            if (temIntro) {
                var observer = new MutationObserver(function () {
                    if (!document.documentElement.classList.contains('com-intro')) {
                        observer.disconnect();
                        setTimeout(mostrar, 400);
                    }
                });
                observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
            } else {
                setTimeout(mostrar, 200);
            }
        }

        function mostrar() {
            modal.classList.add('onb-overlay--ativo');
            atualizarPasso(1);
        }

        function fechar() {
            modal.classList.remove('onb-overlay--ativo');
            setTimeout(function () {
                modal.remove();
            }, 400);
        }

        function atualizarPasso(n) {
            etapaAtual = n;
            passos.forEach(function (p) {
                p.classList.toggle('onb-passo--ativo', parseInt(p.dataset.etapa, 10) === n);
            });

            // Atualiza barra e badge
            if (barra) barra.style.width = ((n / totalEtapas) * 100) + '%';
            if (badge) badge.textContent = 'Etapa ' + n + ' de ' + totalEtapas;

            // Botão voltar
            if (btnVoltar) {
                btnVoltar.style.display = (n > 1) ? 'inline-block' : 'none';
            }

            // Texto do botão avançar
            if (btnAvancar) {
                if (n === totalEtapas) {
                    btnAvancar.textContent = 'Salvar e começar →';
                } else {
                    btnAvancar.textContent = 'Continuar →';
                }
            }

            // Reação do mascote a cada passo
            if (mascoteEl && mascoteEl.mascote) {
                if (n === 1) {
                    mascoteEl.mascote.humor('normal');
                } else if (n === 2) {
                    mascoteEl.mascote.piscar(250);
                } else if (n === 3) {
                    mascoteEl.mascote.humor('feliz');
                }
            }
        }

        // Avançar ou Salvar
        btnAvancar.addEventListener('click', function () {
            if (etapaAtual < totalEtapas) {
                atualizarPasso(etapaAtual + 1);
            } else {
                salvarRespostas(false);
            }
        });

        // Voltar etapa
        if (btnVoltar) {
            btnVoltar.addEventListener('click', function () {
                if (etapaAtual > 1) {
                    atualizarPasso(etapaAtual - 1);
                }
            });
        }

        // Pular por enquanto
        if (btnPular) {
            btnPular.addEventListener('click', function () {
                salvarRespostas(true);
            });
        }

        // Envio para o backend
        function salvarRespostas(pular) {
            btnAvancar.disabled = true;
            if (btnPular) btnPular.disabled = true;

            var payload = { pular: !!pular };

            if (!pular) {
                var form = document.getElementById('onbForm');
                var formData = new FormData(form);

                payload.objetivo = formData.get('onb_objetivo') || 'vestibular_enem';
                payload.meta_diaria = parseInt(formData.get('onb_meta') || '60', 10);
                payload.materias = formData.getAll('onb_materias[]');
            }

            fetch('../../Backend/php/onboarding_salvar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                if (mascoteEl && mascoteEl.mascote) {
                    mascoteEl.mascote.humor('feliz');
                    mascoteEl.mascote.piscar(300);
                }
                setTimeout(fechar, 300);
            })
            .catch(function () {
                // Em caso de falha de rede, fecha normalmente para não travar o usuário
                fechar();
            });
        }

        abrirModal();
    });
})();
