<?php
// Porteiro + dados desta página (sem sessão, redireciona antes de
// mandar qualquer HTML). Deixa $USUARIO, $PREF e $PAGINA prontos.
require_once __DIR__ . '/../../../Backend/php/pagina_dashboard.php';

/* Prévia de um resumo: abre para LER. Editar é uma escolha, num
   botão — antes o clique no cartão já caía no editor.
   O resumo só é carregado se for desta conta (o WHERE usuario_id
   é o que impede abrir o resumo de outra pessoa pela URL). */
$RESUMO = null;
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;

if ($id > 0 && !$ERRO_BANCO) {
    try {
        $stmt = $pdo->prepare('SELECT id, titulo, materia, corpo,
                                      DAY(criado_em)       AS dia,
                                      MONTH(criado_em)     AS mes,
                                      YEAR(criado_em)      AS ano,
                                      DAY(atualizado_em)   AS dia_upd,
                                      MONTH(atualizado_em) AS mes_upd,
                                      YEAR(atualizado_em)  AS ano_upd,
                                      criado_em = atualizado_em AS nunca_editado
                                 FROM resumos
                                WHERE id = ? AND usuario_id = ?
                                LIMIT 1');
        $stmt->execute([$id, $USUARIO['id']]);
        $r = $stmt->fetch();

        if ($r) {
            $RESUMO = [
                'id'          => (int) $r['id'],
                'titulo'      => $r['titulo'],
                'materia'     => $r['materia'],
                'corpo'       => $r['corpo'],
                'criado'      => dataLongaPt((int) $r['dia'], (int) $r['mes'], (int) $r['ano']),
                'atualizado'  => dataLongaPt((int) $r['dia_upd'], (int) $r['mes_upd'], (int) $r['ano_upd']),
                'editado'     => !$r['nunca_editado'],
                'palavras'    => str_word_count(strip_tags($r['corpo'])),
            ];
        }
    } catch (PDOException $e) {
        $RESUMO = null;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../shared/favicon.svg">
    <title><?= $RESUMO ? hesc($RESUMO['titulo']) . ' — Kosmos' : 'Kosmos — Resumo' ?></title>
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

        <?php include __DIR__ . '/partes/sidebar.php'; ?>

        <main class="contMeio">
<?php if (!$RESUMO): ?>
            <header class="contCabeca">
                <div class="contCabeca__texto">
                    <span class="section-tag">Biblioteca</span>
                    <h1>Resumo não <span class="h-nome">encontrado</span></h1>
                    <p>Ele pode ter sido apagado, ou o endereço está errado.</p>
                </div>
                <a class="dash-btn dash-btn--primary" href="resumos.php">Voltar aos resumos</a>
            </header>
<?php else: ?>
            <header class="contCabeca">
                <div class="contCabeca__texto">
                    <a class="voltar" href="resumos.php">
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M16 10H4M9 14l-5-4 5-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Resumos
                    </a>
                    <h1><?= hesc($RESUMO['titulo']) ?></h1>
                    <p class="resumo-meta">
                        <span class="materia-tag"><?= hesc($RESUMO['materia']) ?></span>
                        <span>Escrito em <?= hesc($RESUMO['criado']) ?></span>
                        <?php if ($RESUMO['editado']): ?>
                        <span>· editado em <?= hesc($RESUMO['atualizado']) ?></span>
                        <?php endif; ?>
                        <span>· <?= (int) $RESUMO['palavras'] ?> palavras</span>
                    </p>
                </div>
                <button class="dash-btn dash-btn--primary" id="btnEditar">
                    <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 16h3l8-8-3-3-8 8v3Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M12.5 4.5l3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                    Editar
                </button>
            </header>

            <!-- O texto sai do banco escapado; as quebras de linha são
                 preservadas por CSS (white-space: pre-wrap). -->
            <article class="ini-card resumo-leitura" id="leitura"><?= hesc($RESUMO['corpo']) ?></article>
<?php endif; ?>
        </main>
    </div>

<?php if ($RESUMO): ?>
    <?php include __DIR__ . '/partes/modal-confirma.php'; ?>
    <?php include __DIR__ . '/partes/modal-resumo.php'; ?>

    <script type="application/json" id="dadosResumo"><?= json_encode([
        'id'      => $RESUMO['id'],
        'titulo'  => $RESUMO['titulo'],
        'materia' => $RESUMO['materia'],
        'corpo'   => $RESUMO['corpo'],
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endif; ?>

    <script src="./js/dashboard.js"></script>
    <script src="./js/resumo-form.js"></script>
    <script src="./js/resumo.js"></script>
    <script src="./js/cursor.js"></script>
</body>
</html>
