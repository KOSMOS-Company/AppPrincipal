<?php
// Porteiro + dados desta página (sem sessão, redireciona antes de
// mandar qualquer HTML). Deixa $USUARIO, $PREF e $PAGINA prontos.
require_once __DIR__ . '/../../../Backend/php/pagina_dashboard.php';

/* Os resumos do usuário já saem prontos daqui: a página chega com a
   lista montada, sem depender de JS para aparecer. As ações (salvar,
   apagar) continuam por fetch nos endpoints resumos_*.php.
   As datas vêm em pedaços do MySQL — o PHP só monta o texto. */
$RESUMOS = [];
if (!$ERRO_BANCO) {
    try {
        $stmt = $pdo->prepare('SELECT id, titulo, materia, corpo,
                                      DAY(criado_em)   AS dia,
                                      MONTH(criado_em) AS mes
                                 FROM resumos
                                WHERE usuario_id = ?
                             ORDER BY atualizado_em DESC, id DESC');
        $stmt->execute([$USUARIO['id']]);
        foreach ($stmt as $r) {
            $RESUMOS[] = [
                'id'      => (int) $r['id'],
                'titulo'  => $r['titulo'],
                'materia' => $r['materia'],
                'corpo'   => $r['corpo'],
                'quando'  => dataCurtaPt((int) $r['dia'], (int) $r['mes']),
            ];
        }
    } catch (PDOException $e) {
        $RESUMOS = [];
    }
}
$TOTAL_RESUMOS = count($RESUMOS);

/* Só as matérias que aparecem nos resumos viram filtro */
$MATERIAS_USADAS = array_values(array_unique(array_column($RESUMOS, 'materia')));
sort($MATERIAS_USADAS);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../shared/favicon.svg">
    <title>Kosmos — Resumos</title>
    <link rel="stylesheet" href="./css/dashboard.css">
    <link rel="stylesheet" href="./css/resumos.css">
    <link rel="stylesheet" href="./css/cursor.css">
    <link rel="stylesheet" href="../shared/logo.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
</head>
<body>

    <!-- Background Efeitos -->
    <div class="bg">
        <div class="bg__orb bg__orb--1"></div>
        <div class="bg__orb bg__orb--2"></div>
        <div class="bg__grid"></div>
    </div>

    <div class="contGeral">

        <!-- Sidebar -->
        <?php include __DIR__ . '/partes/sidebar.php'; ?>

        <!-- Main -->
        <main class="contMeio">
            <header class="contCabeca">
                <div class="contCabeca__texto">
                    <span class="section-tag">Biblioteca</span>
                    <h1>Seus <span class="h-nome">Resumos</span></h1>
                    <p>
<?php if ($TOTAL_RESUMOS === 0): ?>
                        Escreva o que você estudou — fica salvo na sua conta.
<?php else: ?>
                        <?= $TOTAL_RESUMOS ?> <?= $TOTAL_RESUMOS === 1 ? 'resumo salvo' : 'resumos salvos' ?> na sua conta.
<?php endif; ?>
                    </p>
                </div>
                <button class="dash-btn dash-btn--primary" id="btnNovo">
                    <svg viewBox="0 0 20 20" fill="none"><path d="M10 4v12M4 10h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    Novo resumo
                </button>
            </header>

            <!-- Filtros: só as matérias que o usuário realmente tem -->
<?php if (count($MATERIAS_USADAS) > 1): ?>
            <div class="chips" id="filtros">
                <button class="chip active" data-materia="todos">Todos</button>
<?php foreach ($MATERIAS_USADAS as $m): ?>
                <button class="chip" data-materia="<?= hesc($m) ?>"><?= hesc($m) ?></button>
<?php endforeach; ?>
            </div>
<?php else: ?>
            <div class="chips" id="filtros" hidden></div>
<?php endif; ?>

            <!-- Grid: os cartões já vêm prontos do servidor -->
            <div class="resumos-grid" id="grid">
<?php foreach ($RESUMOS as $i => $r): ?>
                <article class="resumo-card anim-in" data-id="<?= (int) $r['id'] ?>"
                         data-materia="<?= hesc($r['materia']) ?>"
                         style="animation-delay: <?= number_format($i * 0.04, 2, '.', '') ?>s">
                    <!-- o cartão leva para a leitura; editar é um passo à parte -->
                    <a class="resumo-card__link" href="resumo.php?id=<?= (int) $r['id'] ?>">
                        <div class="resumo-card__thumb">
                            <svg viewBox="0 0 24 24" fill="none"><rect x="5" y="3" width="14" height="18" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M9 8h6M9 12h6M9 16h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                        </div>
                        <div class="resumo-card__body">
                            <span class="materia-tag"><?= hesc($r['materia']) ?></span>
                            <h3 class="resumo-card__title"><?= hesc($r['titulo']) ?></h3>
                            <div class="resumo-card__meta">
                                <span><?= hesc($r['quando']) ?></span>
                                <span>Ler →</span>
                            </div>
                        </div>
                    </a>
                    <button type="button" class="resumo-card__editar" data-editar="<?= (int) $r['id'] ?>"
                            title="Editar este resumo" aria-label="Editar o resumo <?= hesc($r['titulo']) ?>">
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 16h3l8-8-3-3-8 8v3Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M12.5 4.5l3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                    </button>
                </article>
<?php endforeach; ?>
            </div>

            <!-- Estado vazio -->
            <div class="vazio" id="vazio"<?= $TOTAL_RESUMOS > 0 ? ' hidden' : '' ?>>
                <svg viewBox="0 0 24 24" fill="none"><rect x="4" y="3" width="16" height="18" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M8 8h8M8 12h8M8 16h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                <h3>Nenhum resumo aqui</h3>
                <p>Crie um novo resumo para começar a organizar essa matéria.</p>
            </div>
        </main>

        <?php include __DIR__ . '/partes/modal-confirma.php'; ?>

        <?php include __DIR__ . '/partes/modal-resumo.php'; ?>

        <!-- os resumos completos (com o texto) para a página abrir sem
             outra ida ao servidor -->
        <script type="application/json" id="dadosResumos"><?= json_encode($RESUMOS, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

    <script src="./js/dashboard.js"></script>
    <script src="./js/resumo-form.js"></script>
    <script src="./js/resumos.js"></script>
    <script src="./js/cursor.js"></script>
</body>
</html>
