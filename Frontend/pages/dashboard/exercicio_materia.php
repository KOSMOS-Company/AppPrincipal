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
    <link rel="stylesheet" href="<?= asset('../shared/tokens.css') ?>">
    <link rel="stylesheet" href="<?= asset('./css/dashboard.css') ?>">
    <link rel="stylesheet" href="<?= asset('../shared/cosmos.css') ?>">
    <link rel="stylesheet" href="<?= asset('./css/pomodoro-aviso.css') ?>">
    <link rel="stylesheet" href="<?= asset('./css/resumos.css') ?>">
    <link rel="stylesheet" href="<?= asset('./css/exercicios.css') ?>">
    <link rel="stylesheet" href="<?= asset('../shared/cursor.css') ?>">
    <link rel="stylesheet" href="<?= asset('../shared/logo.css') ?>">
    <link rel="preload" href="../shared/fontes/dm-sans-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="../shared/fontes/syne-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= asset('../shared/fontes.css') ?>">
</head>
<body<?= $MATERIA ? ' class="dashboard-fundo-estatico tema-caderno tema-caderno--' . hesc($MATERIA['cor']) . '"' : ' class="dashboard-fundo-estatico"' ?>>

    <?php include __DIR__ . '/partes/fundo.php'; ?>

    <div class="contGeral">

        <?php include __DIR__ . '/partes/sidebar.php'; ?>

        <main class="contMeio pagina-materia">
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
                    <?php
                    $CAMINHO = [
                        ['Exercícios', 'exercicios.php'],
                        [$MATERIA['nome'], null],
                    ];
                    require __DIR__ . '/partes/caminho.php';
                    ?>
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
                    <button class="dash-btn dash-btn--primary btn-ia" id="btnNovoExercicio">
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M8 3.5c.5 3 1.5 4 4.5 4.5-3 .5-4 1.5-4.5 4.5-.5-3-1.5-4-4.5-4.5 3-.5 4-1.5 4.5-4.5Z" fill="currentColor"/><path class="ia-estrela-menor" d="M14.5 11.5c.25 1.5.75 2 2.25 2.25-1.5.25-2 .75-2.25 2.25-.25-1.5-.75-2-2.25-2.25 1.5-.25 2-.75 2.25-2.25Z" fill="currentColor"/></svg>
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

    <script src="<?= asset('./js/util.js') ?>"></script>
    <script src="<?= asset('./js/dashboard.js') ?>"></script>
    <script src="<?= asset('./js/pomodoro-aviso.js') ?>"></script>
    <script src="<?= asset('./js/combo-materia.js') ?>"></script>
    <script src="<?= asset('./js/exercicio-materia-form.js') ?>"></script>
    <script src="<?= asset('./js/exercicio-gerar-form.js') ?>"></script>
    <script src="<?= asset('./js/exercicio-praticar.js') ?>"></script>
    <script src="<?= asset('./js/exercicios.js') ?>"></script>
    <script src="<?= asset('../shared/cursor.js') ?>"></script>
    <!-- O céu. Os mesmos dois arquivos da landing page: o WebGL tenta
         primeiro e, se não houver placa ou o shader não compilar, ele
         desiste em silêncio e o cosmos.js (Canvas 2D) assume — por isso
         esta ordem importa. Ver partes/fundo.php. -->
    <script src="<?= asset('../shared/cosmos-gl.js') ?>"></script>
    <script src="<?= asset('../shared/cosmos.js') ?>"></script>

    <script src="<?= asset('./js/exercicio-materia.js') ?>"></script>
</body>
</html>