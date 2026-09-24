<?php
// Porteiro + dados desta página (sem sessão, redireciona antes de
// mandar qualquer HTML). Deixa $USUARIO, $PREF e $PAGINA prontos.
require_once __DIR__ . '/../../../Backend/php/pagina_dashboard.php';

/* Dentro de um caderno: os temas guardados nele.
   O caderno só é carregado se for desta conta — é o cadernoParaTela()
   (com o WHERE usuario_id) que impede abrir o caderno de outra
   pessoa só trocando o id na URL. */
$CADERNO = null;
$RESUMOS = [];
$CADERNOS = [];
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;

if ($id > 0 && !$ERRO_BANCO) {
    try {
        $CADERNO = cadernoParaTela($pdo, $id, (int) $USUARIO['id']);

        if ($CADERNO !== null) {
            // a data de criação não vem no formato compartilhado
            $stmt = $pdo->prepare('SELECT DAY(criado_em) AS dia, MONTH(criado_em) AS mes,
                                          YEAR(criado_em) AS ano
                                     FROM resumo_cadernos WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $d = $stmt->fetch();
            $CADERNO['criado'] = dataLongaPt((int) $d['dia'], (int) $d['mes'], (int) $d['ano']);

            $stmt = $pdo->prepare('SELECT r.id, r.titulo, r.materia, r.corpo, r.caderno_id,
                                          DAY(r.criado_em)   AS dia,
                                          MONTH(r.criado_em) AS mes,
                                          (SELECT COUNT(*) FROM resumo_imagens i
                                            WHERE i.resumo_id = r.id) AS fotos
                                     FROM resumos r
                                    WHERE r.caderno_id = ? AND r.usuario_id = ?
                                 ORDER BY r.atualizado_em DESC, r.id DESC');
            $stmt->execute([$id, $USUARIO['id']]);
            foreach ($stmt as $r) {
                $RESUMOS[] = [
                    'id'         => (int) $r['id'],
                    'titulo'     => $r['titulo'],
                    'materia'    => $r['materia'],
                    'corpo'      => $r['corpo'],
                    'caderno_id' => (int) $r['caderno_id'],
                    'fotos'      => (int) $r['fotos'],
                    'quando'     => dataCurtaPt((int) $r['dia'], (int) $r['mes']),
                ];
            }

            /* Todos os cadernos: servem ao <select> do modal de resumo,
               ao menu "Mover para…" e à doca de destinos do arrastar. */
            $CADERNOS = listarCadernos($pdo, (int) $USUARIO['id']);
        }
    } catch (PDOException $e) {
        $CADERNO = null;
        $RESUMOS = [];
    }
}

$TOTAL = count($RESUMOS);
$FOTOS = array_sum(array_column($RESUMOS, 'fotos'));

/* Os outros cadernos — os destinos possíveis ao arrastar um resumo
   para fora deste. Sem outros cadernos, a doca não aparece. */
$OUTROS = array_values(array_filter($CADERNOS, fn($c) => $c['id'] !== ($CADERNO['id'] ?? 0)));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../shared/favicon.svg">
    <title><?= $CADERNO ? hesc($CADERNO['nome']) . ' — Kosmos' : 'Kosmos — Caderno' ?></title>
    <link rel="stylesheet" href="./css/dashboard.css">
    <link rel="stylesheet" href="../shared/cosmos.css">
    <link rel="stylesheet" href="./css/pomodoro-aviso.css">
    <link rel="stylesheet" href="./css/resumos.css">
    <link rel="stylesheet" href="./css/cursor.css">
    <link rel="stylesheet" href="../shared/logo.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
</head>
<body<?= $CADERNO ? ' class="tema-caderno tema-caderno--' . hesc($CADERNO['cor']) . '"' : '' ?>>

    <?php include __DIR__ . '/partes/fundo.php'; ?>

    <div class="contGeral">

        <?php include __DIR__ . '/partes/sidebar.php'; ?>

        <main class="contMeio">
<?php if (!$CADERNO): ?>
            <header class="contCabeca">
                <div class="contCabeca__texto">
                    <span class="section-tag">Biblioteca</span>
                    <h1>Caderno não <span class="h-nome">encontrado</span></h1>
                    <p>Ele pode ter sido apagado, ou o endereço está errado.</p>
                </div>
                <a class="dash-btn dash-btn--primary" href="resumos.php">Voltar aos cadernos</a>
            </header>
<?php else: ?>
            <header class="contCabeca">
                <div class="contCabeca__texto">
                    <a class="voltar" href="resumos.php">
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M16 10H4M9 14l-5-4 5-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Cadernos
                    </a>
                    <h1 class="caderno-titulo">
<?php if ($CADERNO['icone'] !== ''): ?>
                        <span class="caderno-titulo__icone" id="cadernoIconeTitulo" aria-hidden="true"><?= hesc($CADERNO['icone']) ?></span>
<?php else: ?>
                        <span class="caderno-titulo__icone" id="cadernoIconeTitulo" aria-hidden="true" hidden></span>
<?php endif; ?>
                        <span id="cadernoNomeTitulo"><?= hesc($CADERNO['nome']) ?></span>
                    </h1>
<?php if ($CADERNO['descricao'] !== ''): ?>
                    <p class="caderno-desc" id="cadernoDesc"><?= hesc($CADERNO['descricao']) ?></p>
<?php else: ?>
                    <p class="caderno-desc" id="cadernoDesc" hidden></p>
<?php endif; ?>
                    <p class="resumo-meta">
                        <span class="materia-tag" id="cadernoMateriaTag"><?= hesc($CADERNO['materia']) ?></span>
                        <span id="cadernoContagem"><?= $TOTAL ?> <?= $TOTAL === 1 ? 'resumo' : 'resumos' ?><?php
                            if ($FOTOS > 0): ?> · <?= $FOTOS ?> <?= $FOTOS === 1 ? 'imagem' : 'imagens' ?><?php endif; ?></span>
                        <span>· criado em <?= hesc($CADERNO['criado']) ?></span>
                    </p>
                </div>
                <div class="rs-acoes">
                    <button class="dash-btn dash-btn--outline" id="btnEditarCaderno">
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 16h3l8-8-3-3-8 8v3Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M12.5 4.5l3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                        Personalizar
                    </button>
                    <button class="dash-btn dash-btn--primary" id="btnNovo">
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 4v12M4 10h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        Novo resumo
                    </button>
                </div>
            </header>

            <!-- Grade de temas: já vem pronta do servidor -->
            <div class="resumos-grid" id="grid">
<?php $mostrarMateria = false; ?>
<?php foreach ($RESUMOS as $i => $r): ?>
<?php include __DIR__ . '/partes/resumo-card.php'; ?>
<?php endforeach; ?>
            </div>

            <!-- Estado vazio -->
            <div class="vazio" id="vazio"<?= $TOTAL > 0 ? ' hidden' : '' ?>>
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="4" y="3" width="16" height="18" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M8 8h8M8 12h8M8 16h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                <h2>Este caderno ainda está vazio</h2>
                <p>Escreva o primeiro tema, ou fotografe a página do seu caderno de papel e anexe aqui.</p>
            </div>
<?php endif; ?>
        </main>
    </div>

<?php if ($CADERNO): ?>
    <!-- ============================================================
         Doca de destinos. Fica escondida e só sobe quando um resumo
         começa a ser arrastado: é para onde se solta o cartão quando
         ele deve sair DESTE caderno. Quem não arrasta usa o menu ⋮
         do cartão, que faz exatamente a mesma coisa.
         ============================================================ -->
    <div class="doca" id="doca" hidden aria-hidden="true">
        <span class="doca__titulo">Solte para mover para…</span>
        <div class="doca__alvos">
            <button type="button" class="doca__alvo doca__alvo--solto" data-destino="">
                <span class="doca__icone" aria-hidden="true">📄</span>
                Sem caderno
            </button>
<?php foreach ($OUTROS as $o): ?>
            <button type="button" class="doca__alvo caderno-card--<?= hesc($o['cor']) ?>"
                    data-destino="<?= (int) $o['id'] ?>">
                <span class="doca__icone" aria-hidden="true"><?= $o['icone'] !== '' ? hesc($o['icone']) : '📙' ?></span>
                <?= hesc($o['nome']) ?>
            </button>
<?php endforeach; ?>
        </div>
    </div>

    <?php include __DIR__ . '/partes/modal-confirma.php'; ?>
    <?php include __DIR__ . '/partes/modal-caderno.php'; ?>
    <?php include __DIR__ . '/partes/modal-resumo.php'; ?>
    <?php /* "Gerar flashcards" no menu ⋮ de cada resumo depende desta
             parte estar na página: sem ela o item nem aparece. */ ?>
    <?php include __DIR__ . '/partes/modal-flashcards.php'; ?>

    <script type="application/json" id="dadosCaderno"><?= json_encode($CADERNO, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    <script type="application/json" id="dadosCadernos"><?= json_encode($CADERNOS, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    <script type="application/json" id="dadosResumos"><?= json_encode($RESUMOS, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endif; ?>

    <script src="./js/dashboard.js"></script>
    <script src="./js/pomodoro-aviso.js"></script>
    <script src="./js/resumo-mover.js"></script>
    <script src="./js/combo-materia.js"></script>
    <script src="./js/caderno-form.js"></script>
    <script src="./js/resumo-form.js"></script>
    <!-- antes do caderno.js: é ele que pergunta se o módulo existe
         para decidir se põe "Gerar flashcards" no menu ⋮ -->
    <script src="./js/flashcards-gerar.js"></script>
    <script src="./js/caderno.js"></script>
    <script src="./js/cursor.js"></script>
    <!-- O céu. Os mesmos dois arquivos da landing page: o WebGL tenta
         primeiro e, se não houver placa ou o shader não compilar, ele
         desiste em silêncio e o cosmos.js (Canvas 2D) assume — por isso
         esta ordem importa. Ver partes/fundo.php. -->
    <script src="../shared/cosmos-gl.js"></script>
    <script src="../shared/cosmos.js"></script>
</body>
</html>
