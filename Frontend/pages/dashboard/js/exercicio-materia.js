/* ============================================================
   KOSMOS — Página de uma matéria de exercícios
   Arquivo: Frontend/pages/dashboard/js/exercicio-materia.js
   Usado por: exercicio_materia.php

   Lista os exercícios salvos da matéria e liga o botão "Novo
   exercício" ao modal de gerar. Morava num <script> de 220 linhas
   dentro do próprio exercicio_materia.php; aqui ele ganha cache
   (asset() com ?v=) e fica junto dos outros scripts da aba.
   ============================================================ */
(() => {
    const btnNovoExercicio = document.getElementById('btnNovoExercicio');
    const exerciciosSalvos = document.getElementById('exerciciosSalvos');
    const gridExerciciosSalvos = document.getElementById('gridExerciciosSalvos');
    const vazioExerciciosSalvos = document.getElementById('vazioExerciciosSalvos');

    function lerMateria() {
        const dados = document.getElementById('dadosExercicioMateria');
        if (!dados) return null;
        try {
            return JSON.parse(dados.textContent);
        } catch (_) {
            return null;
        }
    }

    if (!btnNovoExercicio) return;

    // Abre o modal de gerar exercícios
    btnNovoExercicio.addEventListener('click', () => {
        const materia = lerMateria();
        if (materia) {
            window.KosmosExercicioGerarForm?.abrir(materia);
        }
    });

    // Personalizar esta matéria (mesmo modal da estante)
    document.getElementById('btnEditarMateria')?.addEventListener('click', () => {
        const materia = lerMateria();
        if (materia) {
            window.KosmosExercicioMateriaForm?.abrir(materia);
        }
    });

    // Ao salvar a personalização, atualiza o cabeçalho na hora
    document.addEventListener('exercicioMateria:salvo', (e) => {
        const materia = e.detail?.materia;
        if (!materia) return;

        const dados = document.getElementById('dadosExercicioMateria');
        if (dados) {
            dados.textContent = JSON.stringify(materia);
        }

        document.getElementById('materiaNomeTitulo').textContent = materia.nome;
        document.getElementById('materiaMateriaTag').textContent = materia.materia;

        const desc = document.getElementById('materiaDesc');
        if (desc) {
            desc.textContent = materia.descricao || '';
            desc.hidden = !materia.descricao;
        }

        const iconeTitulo = document.getElementById('materiaIconeTitulo');
        if (iconeTitulo) {
            iconeTitulo.textContent = materia.icone || '';
            iconeTitulo.hidden = !materia.icone;
        }

        document.body.className = 'tema-caderno tema-caderno--' + materia.cor;
        document.title = materia.nome + ' — Kosmos';
    });

    // Apagou a própria matéria daqui de dentro: esta página deixou de existir, volta à estante
    document.addEventListener('exercicioMateria:apagado', () => location.href = 'exercicios.php');

    // Escuta evento de exercício salvo para recarregar a lista
    document.addEventListener('exercicio:salvo', () => {
        carregarExerciciosSalvos();
    });
    async function carregarExerciciosSalvos() {
        if (!materiaId) return;

        try {
            const resp = await fetch('../../../Backend/php/exercicios_listar.php?materia_id=' + materiaId);
            const json = await resp.json();

            if (json.ok && json.exercicios) {
                renderizarExerciciosSalvos(json.exercicios);
            }
        } catch (e) {
            console.warn('Erro ao carregar exercícios salvos:', e);
        }
    }

    function renderizarExerciciosSalvos(exercicios) {
        if (!gridExerciciosSalvos || !vazioExerciciosSalvos) return;

        if (exercicios.length === 0) {
            gridExerciciosSalvos.style.display = 'none';
            vazioExerciciosSalvos.style.display = 'block';
            return;
        }

        vazioExerciciosSalvos.style.display = 'none';
        gridExerciciosSalvos.style.display = 'grid';

        gridExerciciosSalvos.innerHTML = exercicios.map(ex => {
            let qtdQuestoes = 0;
            try {
                const conteudo = JSON.parse(ex.conteudo);
                qtdQuestoes = conteudo.questoes?.length || 0;
            } catch (_) {}

            // "YYYY-MM-DD HH:MM:SS" do MySQL não é ISO: o Safari devolve Invalid Date sem o "T"
            const data = new Date(String(ex.criado_em).replace(' ', 'T')).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
            const dificuldade = ex.dificuldade || "Médio";

            return `
                <article class="exercicio-salvo-card anim-in">
                    <div class="exercicio-salvo-card__topo">
                        <div class="exercicio-salvo-card__info">
                            <h4 class="exercicio-salvo-card__titulo">${escapeHtml(ex.titulo)}</h4>
                            <p class="exercicio-salvo-card__meta">${qtdQuestoes} ${qtdQuestoes === 1 ? 'questão' : 'questões'} · ${data}</p>
                        </div>
                        <span class="exercicio-salvo-card__badge dif-capsula" data-dificuldade="${escapeHtml(dificuldade)}">${escapeHtml(dificuldade)}</span>
                    </div>
                    <div class="exercicio-salvo-card__trilha" aria-hidden="true">${'<i></i>'.repeat(Math.min(qtdQuestoes, 12))}</div>
                    <div class="exercicio-salvo-card__acoes">
                        <button class="dash-btn dash-btn--outline" data-praticar="${ex.id}">
                            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M8 3.5c.5 3 1.5 4 4.5 4.5-3 .5-4 1.5-4.5 4.5-.5-3-1.5-4-4.5-4.5 3-.5 4-1.5 4.5-4.5Z" fill="currentColor"/><path class="ia-estrela-menor" d="M14.5 11.5c.25 1.5.75 2 2.25 2.25-1.5.25-2 .75-2.25 2.25-.25-1.5-.75-2-2.25-2.25 1.5-.25 2-.75 2.25-2.25Z" fill="currentColor"/></svg>
                            Praticar
                        </button>
                        <button class="dash-btn dash-btn--ghost" data-editar='${JSON.stringify({id: ex.id, titulo: ex.titulo, conteudo: ex.conteudo, dificuldade: ex.dificuldade}).replace(/'/g, '&apos;')}' title="Editar">
                            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 16h3l8-8-3-3-8 8v3Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M12.5 4.5l3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                        </button>
                        <button class="dash-btn dash-btn--danger" data-excluir="${ex.id}" title="Excluir">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 7h14M10 7V5h4v2M7 7l1 12h8l1-12M11 11v5M13 11v5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                    </div>
                </article>
            `;
        }).join('');
    }

    // Pega o materiaId do JSON embutido
    let materiaId = 0;
    const dadosMateria = document.getElementById("dadosExercicioMateria");
    if (dadosMateria) {
        try {
            const m = JSON.parse(dadosMateria.textContent);
            materiaId = m.id || 0;
        } catch (_) {}
    }

    function escapeHtml(str) {
        const map = {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'};
        return String(str ?? '').replace(/[&<>"']/g, c => map[c]);
    }

    // Funções globais para os botões dos cards
    window.abrirExercicio = (id) => {
        window.KosmosExercicioPraticar?.abrir(id);
    };

    // Delegação de evento para os botões dos cards (data-praticar,
    // data-excluir, data-editar). Sem onclick no HTML: a
    // Content-Security-Policy (script-src 'self') bloqueia atributos inline.
    gridExerciciosSalvos?.addEventListener('click', (e) => {
        const praticar = e.target.closest('[data-praticar]');
        if (praticar) { abrirExercicio(Number(praticar.dataset.praticar)); return; }
        const excluir = e.target.closest('[data-excluir]');
        if (excluir) { excluirExercicio(Number(excluir.dataset.excluir)); return; }

        const btn = e.target.closest('[data-editar]');
        if (!btn) return;
        try {
            const data = JSON.parse(btn.dataset.editar);
            editarExercicio(data.id, data.titulo, data.conteudo, data.dificuldade);
        } catch (_) {}
    });

    window.editarExercicio = async (id, titulo, conteudo, dificuldade) => {
        const novoTitulo = await pedir({
            titulo: 'Editar exercício',
            rotulo: 'Título da lista',
            valor: titulo,
            botao: 'Salvar'
        });
        if (novoTitulo === null) return;

        fetch('../../../Backend/php/exercicios_salvar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                acao: 'editar',
                id: id,
                titulo: novoTitulo,
                conteudo: conteudo,
                dificuldade: dificuldade
            })
        }).then(r => r.json()).then(json => {
            if (json.ok) {
                carregarExerciciosSalvos();
            } else {
                alert(json.msg || 'Erro ao editar');
            }
        });
    };

    window.excluirExercicio = async (id) => {
        const ok = await confirmar({
            titulo: 'Excluir exercício',
            texto: 'As questões salvas serão apagadas. Esta ação não pode ser desfeita.',
            botao: 'Excluir',
            perigo: true
        });
        if (!ok) return;

        fetch('../../../Backend/php/exercicios_salvar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ acao: 'excluir', id: id })
        }).then(r => r.json()).then(json => {
            if (json.ok) {
                carregarExerciciosSalvos();
            } else {
                alert(json.msg || 'Erro ao excluir');
            }
        });
    };

    // Carrega exercícios salvos ao entrar na página
    carregarExerciciosSalvos();
})();
