<?php
// Porteiro + dados desta página (sem sessão, redireciona antes de
// mandar qualquer HTML). Deixa $USUARIO, $PREF e $PAGINA prontos.
require_once __DIR__ . '/../../../Backend/php/pagina_dashboard.php';

/* Prévia de um resumo: abre para LER. Editar é uma escolha, num
   botão — antes o clique no cartão já caía no editor.
   O resumo só é carregado se for desta conta (o WHERE usuario_id
   é o que impede abrir o resumo de outra pessoa pela URL).
   Um resumo pode ser texto, imagens do caderno de papel, ou os
   dois — por isso as duas coisas são opcionais na tela. */
$RESUMO = null;
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;

if ($id > 0 && !$ERRO_BANCO) {
    try {
        $stmt = $pdo->prepare('SELECT r.id, r.titulo, r.materia, r.corpo, r.caderno_id,
                                      c.nome AS caderno_nome,
                                      DAY(r.criado_em)       AS dia,
                                      MONTH(r.criado_em)     AS mes,
                                      YEAR(r.criado_em)      AS ano,
                                      DAY(r.atualizado_em)   AS dia_upd,
                                      MONTH(r.atualizado_em) AS mes_upd,
                                      YEAR(r.atualizado_em)  AS ano_upd,
                                      r.criado_em = r.atualizado_em AS nunca_editado
                                 FROM resumos r
                            LEFT JOIN resumo_cadernos c ON c.id = r.caderno_id
                                WHERE r.id = ? AND r.usuario_id = ?
                                LIMIT 1');
        $stmt->execute([$id, $USUARIO['id']]);
        $r = $stmt->fetch();

        if ($r) {
            $RESUMO = [
                'id'           => (int) $r['id'],
                'titulo'       => $r['titulo'],
                'materia'      => $r['materia'],
                'corpo'        => $r['corpo'],
                'caderno_id'   => $r['caderno_id'] === null ? null : (int) $r['caderno_id'],
                'caderno_nome' => $r['caderno_nome'],
                'criado'       => dataLongaPt((int) $r['dia'], (int) $r['mes'], (int) $r['ano']),
                'atualizado'   => dataLongaPt((int) $r['dia_upd'], (int) $r['mes_upd'], (int) $r['ano_upd']),
                'editado'      => !$r['nunca_editado'],
                'palavras'     => str_word_count(strip_tags($r['corpo'])),
                'imagens'      => imagensDoResumo($pdo, (int) $r['id']),
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
    <link rel="stylesheet" href="../shared/cosmos.css">
    <link rel="stylesheet" href="./css/pomodoro-aviso.css">
    <link rel="stylesheet" href="./css/resumos.css">
    <link rel="stylesheet" href="./css/cursor.css">
    <link rel="stylesheet" href="./css/impressao.css" media="print">
    <link rel="stylesheet" href="../shared/logo.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
</head>
<body>

    <?php include __DIR__ . '/partes/fundo.php'; ?>

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
                <a class="dash-btn dash-btn--primary" href="resumos.php">Voltar à biblioteca</a>
            </header>
<?php else: ?>
            <header class="contCabeca">
                <div class="contCabeca__texto">
                    <!-- volta para o caderno de onde o resumo saiu; sem
                         caderno, volta para a estante -->
                    <a class="voltar" id="voltar"
                       href="<?= $RESUMO['caderno_id'] ? 'caderno.php?id=' . (int) $RESUMO['caderno_id'] : 'resumos.php' ?>">
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M16 10H4M9 14l-5-4 5-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <span id="voltarTexto"><?= $RESUMO['caderno_id'] ? hesc($RESUMO['caderno_nome']) : 'Cadernos' ?></span>
                    </a>
                    <h1><?= hesc($RESUMO['titulo']) ?></h1>
                    <p class="resumo-meta">
                        <span class="materia-tag"><?= hesc($RESUMO['materia']) ?></span>
                        <span>Escrito em <?= hesc($RESUMO['criado']) ?></span>
                        <?php if ($RESUMO['editado']): ?>
                        <span>· editado em <?= hesc($RESUMO['atualizado']) ?></span>
                        <?php endif; ?>
                        <span id="metaPalavras"<?= $RESUMO['corpo'] === '' ? ' hidden' : '' ?>>· <?= (int) $RESUMO['palavras'] ?> palavras</span>
                        <?php if ($RESUMO['imagens']): ?>
                        <span id="metaFotos">· <?= count($RESUMO['imagens']) ?> <?= count($RESUMO['imagens']) === 1 ? 'imagem' : 'imagens' ?></span>
                        <?php else: ?>
                        <span id="metaFotos" hidden></span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="resumo-acoes">
                    <!-- Modo revisão: esconde os trechos marcados com ==assim==
                         para a pessoa tentar lembrar antes de ver. Nasce
                         escondido e o js/resumo-revisao.js só o mostra se o
                         texto TIVER marcação — botão que não faz nada é pior
                         do que botão nenhum. -->
                    <button class="dash-btn dash-btn--ghost" id="btnRevisar"
                            type="button" aria-pressed="false" hidden>
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M2 10s3-5 8-5 8 5 8 5-3 5-8 5-8-5-8-5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><circle cx="10" cy="10" r="2.2" stroke="currentColor" stroke-width="1.6"/></svg>
                        <span id="btnRevisarTexto">Modo revisão</span>
                    </button>

                    <!-- Imprimir é também "salvar em PDF": o próprio diálogo
                         do navegador oferece isso em todos os sistemas. Uma
                         biblioteca de PDF no servidor faria o mesmo trabalho
                         pior, e o projeto não usa nenhuma no frontend. -->
                    <button class="dash-btn dash-btn--outline" id="btnImprimir" type="button">
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M6 8V3h8v5" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M6 15H4a1 1 0 0 1-1-1v-4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1h-2" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M6 12h8v5H6z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
                        PDF
                    </button>

                    <!-- Gerar flashcards. Fica ao lado de "PDF" e não no
                         fim: quem acabou de ler um resumo está no melhor
                         momento possível para virá-lo em cartões, e o
                         caminho de hoje (ir ao Flashcards, criar o
                         baralho, redigitar tudo) é longo o bastante para
                         ninguém percorrer. -->
                    <button class="dash-btn dash-btn--outline" id="btnFlashcards" type="button">
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><rect x="2.5" y="5.5" width="11" height="10" rx="1.6" stroke="currentColor" stroke-width="1.6"/><path d="M6.5 3.5H16a1.5 1.5 0 0 1 1.5 1.5v8.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                        Flashcards
                    </button>

                    <button class="dash-btn dash-btn--primary" id="btnEditar">
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 16h3l8-8-3-3-8 8v3Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M12.5 4.5l3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                        Editar
                    </button>
                </div>
            </header>

            <!-- As imagens anexadas. Clicar abre no tamanho grande. -->
            <div class="resumo-galeria" id="galeria"<?= $RESUMO['imagens'] ? '' : ' hidden' ?>>
<?php foreach ($RESUMO['imagens'] as $i => $img): ?>
                <button type="button" class="resumo-galeria__item" data-lupa="<?= (int) $i ?>"
                        aria-label="Ver a imagem <?= (int) $i + 1 ?> em tamanho grande">
                    <img src="<?= hesc($img['url']) ?>" alt="<?= $img['legenda'] !== '' ? hesc($img['legenda']) : 'Imagem ' . ((int) $i + 1) . ' do resumo' ?>" loading="lazy">
                </button>
<?php endforeach; ?>
            </div>

            <!-- O texto sai do banco escapado; as quebras de linha são
                 preservadas por CSS (white-space: pre-wrap). -->
            <article class="ini-card resumo-leitura" id="leitura"<?= $RESUMO['corpo'] === '' ? ' hidden' : '' ?>><?= hesc($RESUMO['corpo']) ?></article>

            <!-- Resumo que é só imagem: nada a ler, e está tudo bem -->
            <p class="resumo-semtexto" id="semTexto"<?= $RESUMO['corpo'] === '' ? '' : ' hidden' ?>>
                Este resumo é só imagem. Use <strong>Editar</strong> se quiser escrever algo junto.
            </p>
<?php endif; ?>
        </main>
    </div>

<?php if ($RESUMO): ?>
    <!-- Imagem em tamanho grande -->
    <div class="lupa" id="lupa" role="dialog" aria-modal="true" aria-label="Imagem do resumo" hidden>
        <button type="button" class="lupa__fechar" id="lupaFechar" aria-label="Fechar">&times;</button>
        <button type="button" class="lupa__nav lupa__nav--ant" id="lupaAnterior" aria-label="Imagem anterior">‹</button>
        <img class="lupa__img" id="lupaImg" src="" alt="">
        <button type="button" class="lupa__nav lupa__nav--prox" id="lupaProxima" aria-label="Próxima imagem">›</button>
        <span class="lupa__conta" id="lupaConta"></span>
    </div>

    <?php include __DIR__ . '/partes/modal-confirma.php'; ?>
    <?php include __DIR__ . '/partes/modal-resumo.php'; ?>
    <?php /* O botão "Flashcards" do cabeçalho abre esta parte. */ ?>
    <?php include __DIR__ . '/partes/modal-flashcards.php'; ?>

    <script type="application/json" id="dadosResumo"><?= json_encode([
        'id'         => $RESUMO['id'],
        'titulo'     => $RESUMO['titulo'],
        'materia'    => $RESUMO['materia'],
        'corpo'      => $RESUMO['corpo'],
        'caderno_id' => $RESUMO['caderno_id'],
        'imagens'    => $RESUMO['imagens'],
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endif; ?>

    <script src="./js/dashboard.js"></script>
    <script src="./js/pomodoro-aviso.js"></script>
    <script src="./js/resumo-form.js"></script>
    <script src="./js/flashcards-gerar.js"></script>
    <script src="./js/resumo.js"></script>
    <script src="./js/resumo-revisao.js"></script>
    <script src="./js/cursor.js"></script>
    <!-- O céu. Os mesmos dois arquivos da landing page: o WebGL tenta
         primeiro e, se não houver placa ou o shader não compilar, ele
         desiste em silêncio e o cosmos.js (Canvas 2D) assume — por isso
         esta ordem importa. Ver partes/fundo.php. -->
    <script src="../shared/cosmos-gl.js"></script>
    <script src="../shared/cosmos.js"></script>
</body>
</html>
