<?php
// Porteiro + dados desta página (sem sessão, redireciona antes de
// mandar qualquer HTML). Deixa $USUARIO, $PREF e $PAGINA prontos.
require_once __DIR__ . '/../../../Backend/php/pagina_dashboard.php';
require_once __DIR__ . '/../../../Backend/php/exercicios_util.php';

/* Esta é a estante: mostra as MATÉRIAS DE EXERCÍCIOS.
   Dentro de cada matéria ficam os exercícios (exercicio_materia.php).

   Tudo já sai pronto daqui: a página chega montada, sem depender de
   JS para aparecer. As ações (criar, renomear, apagar) continuam por
   fetch nos endpoints exercicios_*.php. */
$MATERIAS = [];

if (!$ERRO_BANCO) {
    try {
        // a consulta das matérias (com cor, ícone, contagens e ordem)
        // mora em Backend/php/exercicios_util.php, num lugar só
        $MATERIAS = listarExercicioMaterias($pdo, (int) $USUARIO['id']);
    } catch (PDOException $e) {
        $MATERIAS = [];
    }
}

$TOTAL_MATERIAS = count($MATERIAS);

/* Só as matérias que aparecem viram filtro */
$MATERIAS_USADAS = array_values(array_unique(array_column($MATERIAS, 'materia')));
sort($MATERIAS_USADAS);

/** Frase do cabeçalho: o que a pessoa tem hoje. */
function resumoDaEstanteExercicios(int $materias): string {
    if ($materias === 0) {
        return 'Crie uma matéria para começar a gerar exercícios.';
    }

    return $materias . ($materias === 1 ? ' matéria' : ' matérias') . ' na sua conta.';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../shared/favicon.svg">
    <title>Kosmos — Exercícios</title>
    <link rel="stylesheet" href="./css/dashboard.css">
    <link rel="stylesheet" href="../shared/cosmos.css">
    <link rel="stylesheet" href="./css/pomodoro-aviso.css">
    <link rel="stylesheet" href="./css/exercicios.css">
    <link rel="stylesheet" href="./css/cursor.css">
    <link rel="stylesheet" href="../shared/logo.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
</head>
<body>

    <?php include __DIR__ . '/partes/fundo.php'; ?>

    <div class="contGeral">

        <!-- Sidebar -->
        <?php include __DIR__ . '/partes/sidebar.php'; ?>

        <!-- Main -->
        <main class="contMeio">
            <header class="contCabeca">
                <div class="contCabeca__texto">
                    <span class="section-tag">Praticar</span>
                    <h1>Suas <span class="h-nome">Matérias</span></h1>
                    <p id="estanteResumo"><?= hesc(resumoDaEstanteExercicios($TOTAL_MATERIAS)) ?></p>
                </div>
                <div class="rs-acoes">
                    <button class="dash-btn dash-btn--primary" id="btnNovaMateria">
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 4v12M4 10h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        Nova matéria
                    </button>
                </div>
            </header>

            <!-- Filtros: só as matérias que o usuário realmente tem -->
<?php if (count($MATERIAS_USADAS) > 1): ?>
            <div class="chips" id="filtros">
                <button class="chip active" data-materia="todos">Todos</button>
<?php foreach ($MATERIAS_USADAS as $mat): ?>
                <button class="chip" data-materia="<?= hesc($mat) ?>"><?= hesc($mat) ?></button>
<?php endforeach; ?>
            </div>
<?php else: ?>
            <div class="chips" id="filtros" hidden></div>
<?php endif; ?>

            <!-- Grade de matérias: já vem pronta do servidor.
                 Arrastar um cartão daqui reordena a estante. -->
            <div class="exercicio-materias-grid" id="gridMaterias">
<?php foreach ($MATERIAS as $i => $m): ?>
<?php include __DIR__ . '/partes/exercicio-materia-card.php'; ?>
<?php endforeach; ?>
            </div>

            <!-- Estado vazio: nenhuma matéria -->
            <div class="vazio" id="vazioMaterias"<?= $TOTAL_MATERIAS > 0 ? ' hidden' : '' ?>>
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5Z" stroke="currentColor" stroke-width="1.5"/><path d="M8 3v18M12 8h5M12 12h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                <h2>Nenhuma matéria por aqui</h2>
                <p>Crie uma matéria para o conteúdo que você quer praticar e gere exercícios dentro dela.</p>
            </div>
        </main>

        <?php include __DIR__ . '/partes/modal-confirma.php'; ?>

        <?php include __DIR__ . '/partes/modal-exercicio-materia.php'; ?>

        <!-- os dados completos para a página se atualizar sem outra ida
             ao servidor -->
        <script type="application/json" id="dadosExercicioMaterias"><?= json_encode($MATERIAS, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

    <script src="./js/dashboard.js"></script>
    <script src="./js/pomodoro-aviso.js"></script>
    <script src="./js/combo-materia.js"></script>
    <script src="./js/exercicio-materia-form.js"></script>
    <script src="./js/exercicios.js"></script>
    <script src="./js/cursor.js"></script>
    <!-- O céu. Os mesmos dois arquivos da landing page: o WebGL tenta
         primeiro e, se não houver placa ou o shader não compilar, ele
         desiste em silêncio e o cosmos.js (Canvas 2D) assume — por isso
         esta ordem importa. Ver partes/fundo.php. -->
    <script src="../shared/cosmos-gl.js"></script>
    <script src="../shared/cosmos.js"></script>
</body>
</html>