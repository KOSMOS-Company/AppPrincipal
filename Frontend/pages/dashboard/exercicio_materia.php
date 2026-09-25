<?php
// Porteiro + dados desta página (sem sessão, redireciona antes de
// mandar qualquer HTML). Deixa $USUARIO, $PREF e $PAGINA prontos.
require_once __DIR__ . '/../../../Backend/php/pagina_dashboard.php';
require_once __DIR__ . '/../../../Backend/php/exercicios_util.php';

/* Dentro de uma matéria de exercícios: os exercícios salvos.
   A matéria só é carregada se for desta conta. */
$MATERIA = null;
$MATERIAS = [];
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;

if ($id > 0 && !$ERRO_BANCO) {
    try {
        $MATERIA = exercicioMateriaParaTela($pdo, $id, (int) $USUARIO['id']);

        if ($MATERIA !== null) {
            // a data de criação não vem no formato compartilhado
            $stmt = $pdo->prepare('SELECT DAY(criado_em) AS dia, MONTH(criado_em) AS mes,
                                          YEAR(criado_em) AS ano
                                     FROM exercicio_materias WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $d = $stmt->fetch();
            $MATERIA['criado'] = dataLongaPt((int) $d['dia'], (int) $d['mes'], (int) $d['ano']);

            /* Todas as matérias: servem ao <select> do modal de matéria. */
            $MATERIAS = listarExercicioMaterias($pdo, (int) $USUARIO['id']);
        }
    } catch (PDOException $e) {
        $MATERIA = null;
    }
}

/* As outras matérias — os destinos possíveis ao arrastar um exercício
   para fora desta. Sem outras matérias, a doca não aparece. */
$OUTRAS = array_values(array_filter($MATERIAS, fn($m) => $m['id'] !== ($MATERIA['id'] ?? 0)));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../shared/favicon.svg">
    <title><?= $MATERIA ? hesc($MATERIA['nome']) . ' — Kosmos' : 'Kosmos — Matéria de Exercícios' ?></title>
    <link rel="stylesheet" href="./css/dashboard.css">
    <link rel="stylesheet" href="../shared/cosmos.css">
    <link rel="stylesheet" href="./css/pomodoro-aviso.css">
    <link rel="stylesheet" href="./css/resumos.css">
    <link rel="stylesheet" href="./css/exercicios.css">
    <link rel="stylesheet" href="./css/cursor.css">
    <link rel="stylesheet" href="../shared/logo.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
</head>
<body<?= $MATERIA ? ' class="tema-caderno tema-caderno--' . hesc($MATERIA['cor']) . '"' : '' ?>>

    <?php include __DIR__ . '/partes/fundo.php'; ?>

    <div class="contGeral">

        <?php include __DIR__ . '/partes/sidebar.php'; ?>

        <main class="contMeio">
<?php if (!$MATERIA): ?>
            <header class="contCabeca">
                <div class="contCabeca__texto">
                    <span class="section-tag">Praticar</span>
                    <h1>Matéria não <span class="h-nome">encontrada</span></h1>
                    <p>Ela pode ter sido apagada, ou o endereço está errado.</p>
                </div>
                <a class="dash-btn dash-btn--primary" href="exercicios.php">Voltar às matérias</a>
            </header>
<?php else: ?>
            <header class="contCabeca">
                <div class="contCabeca__texto">
                    <a class="voltar" href="exercicios.php">
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M16 10H4M9 14l-5-4 5-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Matérias
                    </a>
                    <h1 class="caderno-titulo">
<?php if ($MATERIA['icone'] !== ''): ?>
                        <span class="caderno-titulo__icone" id="materiaIconeTitulo" aria-hidden="true"><?= hesc($MATERIA['icone']) ?></span>
<?php else: ?>
                        <span class="caderno-titulo__icone" id="materiaIconeTitulo" aria-hidden="true" hidden></span>
<?php endif; ?>
                        <span id="materiaNomeTitulo"><?= hesc($MATERIA['nome']) ?></span>
                    </h1>
<?php if ($MATERIA['descricao'] !== ''): ?>
                    <p class="caderno-desc" id="materiaDesc"><?= hesc($MATERIA['descricao']) ?></p>
<?php else: ?>
                    <p class="caderno-desc" id="materiaDesc" hidden></p>
<?php endif; ?>
                    <p class="resumo-meta">
                        <span class="materia-tag" id="materiaMateriaTag"><?= hesc($MATERIA['materia']) ?></span>
                        <span>· criada em <?= hesc($MATERIA['criado']) ?></span>
                    </p>
                </div>
                <div class="rs-acoes">
                    <button class="dash-btn dash-btn--primary" id="btnNovoExercicio">
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 4v12M4 10h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        Gerar novo exercício
                    </button>
                    <button class="dash-btn dash-btn--outline" id="btnEditarMateria">
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 16h3l8-8-3-3-8 8v3Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M12.5 4.5l3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                        Personalizar
                    </button>
                </div>
            </header>

            <!-- Lista de exercícios salvos -->
            <div class="exercicios-salvos" id="exerciciosSalvos">
                <div class="vazio vazio--grande" id="vazioExerciciosSalvos">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 11l2 2 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><rect x="4" y="4" width="16" height="16" rx="2" stroke="currentColor" stroke-width="1.5"/></svg>
                    <h2>Nenhum exercício salvo</h2>
                    <p>Clique em "Gerar novo exercício" para criar e salvar exercícios com IA.</p>
                </div>
                <div class="exercicios-grid" id="gridExerciciosSalvos" style="display:none;"></div>
            </div>
<?php endif; ?>
        </main>
    </div>

<?php if ($MATERIA): ?>
    <!-- ============================================================
         Doca de destinos. Fica escondida e só sobe quando um exercício
         começa a ser arrastado: é para onde se solta o cartão quando
         ele deve sair DESTA matéria. Quem não arrasta usa o menu ⋮
         do cartão, que faz exatamente a mesma coisa.
         ============================================================ -->
    <div class="doca" id="doca" hidden aria-hidden="true">
        <span class="doca__titulo">Solte para mover para…</span>
        <div class="doca__alvos">
            <button type="button" class="doca__alvo doca__alvo--solto" data-destino="">
                <span class="doca__icone" aria-hidden="true">📄</span>
                Sem matéria
            </button>
<?php foreach ($OUTRAS as $o): ?>
            <button type="button" class="doca__alvo exercicio-materia-card--<?= hesc($o['cor']) ?>"
                    data-destino="<?= (int) $o['id'] ?>">
                <span class="doca__icone" aria-hidden="true"><?= $o['icone'] !== '' ? hesc($o['icone']) : '📝' ?></span>
                <?= hesc($o['nome']) ?>
            </button>
<?php endforeach; ?>
        </div>
    </div>

    <?php include __DIR__ . '/partes/modal-confirma.php'; ?>
    <?php include __DIR__ . '/partes/modal-pede.php'; ?>
    <?php include __DIR__ . '/partes/modal-exercicio-materia.php'; ?>
    <?php include __DIR__ . '/partes/modal-exercicio-gerar.php'; ?>
    <?php include __DIR__ . '/partes/modal-exercicio-praticar.php'; ?>

    <script type="application/json" id="dadosExercicioMateria"><?= json_encode($MATERIA, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    <script type="application/json" id="dadosExercicioMaterias"><?= json_encode($MATERIAS, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endif; ?>

    <script src="./js/dashboard.js"></script>
    <script src="./js/pomodoro-aviso.js"></script>
    <script src="./js/combo-materia.js"></script>
    <script src="./js/exercicio-materia-form.js"></script>
    <script src="./js/exercicio-gerar-form.js"></script>
    <script src="./js/exercicio-praticar.js"></script>
    <script src="./js/exercicios.js"></script>
    <script src="./js/cursor.js"></script>
    <!-- O céu. Os mesmos dois arquivos da landing page: o WebGL tenta
         primeiro e, se não houver placa ou o shader não compilar, ele
         desiste em silêncio e o cosmos.js (Canvas 2D) assume — por isso
         esta ordem importa. Ver partes/fundo.php. -->
    <script src="../shared/cosmos-gl.js"></script>
    <script src="../shared/cosmos.js"></script>

    <script>
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

                const data = new Date(ex.criado_em).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });

                return `
                    <article class="exercicio-salvo-card anim-in">
                        <div class="exercicio-salvo-card__topo">
                            <div class="exercicio-salvo-card__info">
                                <h4 class="exercicio-salvo-card__titulo">${escapeHtml(ex.titulo)}</h4>
                                <p class="exercicio-salvo-card__meta">${qtdQuestoes} questão${qtdQuestoes!==1?'ões':''} · ${ex.dificuldade} · ${data}</p>
                            </div>
                            <span class="exercicio-salvo-card__badge">${ex.dificuldade}</span>
                        </div>
                        <div class="exercicio-salvo-card__acoes">
                            <button class="dash-btn dash-btn--outline" onclick="abrirExercicio(${ex.id})">
                                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 2l1.8 4.4L16 8l-4.2 1.6L10 14l-1.8-4.4L4 8l4.2-1.6L10 2z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
                                Praticar
                            </button>
                            <button class="dash-btn dash-btn--ghost" data-editar='${JSON.stringify({id: ex.id, titulo: ex.titulo, conteudo: ex.conteudo, dificuldade: ex.dificuldade}).replace(/'/g, '&apos;')}' title="Editar">
                                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 16h3l8-8-3-3-8 8v3Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M12.5 4.5l3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                            </button>
                            <button class="dash-btn dash-btn--danger" onclick="excluirExercicio(${ex.id})" title="Excluir">
                                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M6 6l8 8M14 6l-8 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
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
            const map = {'&':'&','<':'<','>':'>','"':'"',"'":'&#39;'};
            return str.replace(/[&<>"']/g, c => map[c]);
        }

        // Funções globais para os botões dos cards
        window.abrirExercicio = (id) => {
            window.KosmosExercicioPraticar?.abrir(id);
        };

        // Delegação de evento para botões de editar (usam data-editar)
        gridExerciciosSalvos?.addEventListener('click', (e) => {
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
    </script>
</body>
</html>