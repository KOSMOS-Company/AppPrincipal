<?php
// Porteiro + dados desta página (sem sessão, redireciona antes de
// mandar qualquer HTML). Deixa $USUARIO, $PREF e $PAGINA prontos.
require_once __DIR__ . '/../../../Backend/php/pagina_dashboard.php';

/* Esta é a estante: mostra os CADERNOS, não os resumos. Dentro de
   cada caderno ficam os temas (caderno.php).

   Os resumos que existiam antes dos cadernos — e qualquer um criado
   sem escolher caderno — aparecem em "Sem caderno", no fim.

   Tudo já sai pronto daqui: a página chega montada, sem depender de
   JS para aparecer. As ações (criar, renomear, apagar) continuam por
   fetch nos endpoints resumos_*.php.
   As datas vêm em pedaços do MySQL — o PHP só monta o texto. */
$CADERNOS = [];
$SOLTOS   = [];

if (!$ERRO_BANCO) {
    try {
        // a consulta dos cadernos (com cor, capa, contagens e ordem)
        // mora em Backend/php/cadernos_util.php, num lugar so
        $CADERNOS = listarCadernos($pdo, (int) $USUARIO['id']);

        // Resumos fora de qualquer caderno
        $stmt = $pdo->prepare('SELECT r.id, r.titulo, r.materia, r.corpo,
                                      DAY(r.criado_em)   AS dia,
                                      MONTH(r.criado_em) AS mes,
                                      (SELECT COUNT(*) FROM resumo_imagens i
                                        WHERE i.resumo_id = r.id) AS fotos
                                 FROM resumos r
                                WHERE r.usuario_id = ? AND r.caderno_id IS NULL
                             ORDER BY r.atualizado_em DESC, r.id DESC');
        $stmt->execute([$USUARIO['id']]);
        foreach ($stmt as $r) {
            $SOLTOS[] = [
                'id'         => (int) $r['id'],
                'titulo'     => $r['titulo'],
                'materia'    => $r['materia'],
                'corpo'      => $r['corpo'],
                'caderno_id' => null,
                'fotos'      => (int) $r['fotos'],
                'quando'     => dataCurtaPt((int) $r['dia'], (int) $r['mes']),
            ];
        }
    } catch (PDOException $e) {
        $CADERNOS = [];
        $SOLTOS   = [];
    }
}

$TOTAL_CADERNOS = count($CADERNOS);
$TOTAL_SOLTOS   = count($SOLTOS);
$TOTAL_RESUMOS  = $TOTAL_SOLTOS + array_sum(array_column($CADERNOS, 'resumos'));

/* Só as matérias que aparecem nos cadernos viram filtro */
$MATERIAS_USADAS = array_values(array_unique(array_column($CADERNOS, 'materia')));
sort($MATERIAS_USADAS);

/** Frase do cabeçalho: o que a pessoa tem hoje. */
function resumoDaEstante(int $cadernos, int $resumos): string {
    if ($cadernos === 0 && $resumos === 0) {
        return 'Monte um caderno por matéria e guarde seus resumos nele.';
    }

    $partes = [];
    if ($cadernos > 0) {
        $partes[] = $cadernos . ($cadernos === 1 ? ' caderno' : ' cadernos');
    }
    if ($resumos > 0) {
        $partes[] = $resumos . ($resumos === 1 ? ' resumo' : ' resumos');
    }

    return implode(' · ', $partes) . ' na sua conta.';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../shared/favicon.svg">
    <title>Kosmos — Biblioteca</title>
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
<body>

    <?php include __DIR__ . '/partes/fundo.php'; ?>

    <div class="contGeral">

        <!-- Sidebar -->
        <?php include __DIR__ . '/partes/sidebar.php'; ?>

        <!-- Main -->
        <main class="contMeio">
            <header class="contCabeca">
                <div class="contCabeca__texto">
                    <span class="section-tag">Biblioteca</span>
                    <h1>Seus <span class="h-nome">Cadernos</span></h1>
                    <p id="estanteResumo"><?= hesc(resumoDaEstante($TOTAL_CADERNOS, $TOTAL_RESUMOS)) ?></p>
                </div>
                <div class="rs-acoes">
                    <button class="dash-btn dash-btn--ghost" id="btnNovo">
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 4v12M4 10h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        Novo resumo
                    </button>
                    <button class="dash-btn dash-btn--primary" id="btnNovoCaderno">
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 4v12M4 10h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        Novo caderno
                    </button>
                </div>
            </header>

            <!-- ============================================================
                 Abas — só no celular.
                 No computador as duas seções cabem uma embaixo da outra;
                 na tela estreita isso vira uma rolagem longa e as notas
                 soltas somem no fim. Então lá as duas viram abas.
                 O CSS esconde este bloco acima de 768px, e é o mesmo CSS
                 que faz a troca — o JS só marca qual está ativa.
                 ============================================================ -->
            <div class="rs-abas" id="rsAbas" role="tablist" aria-label="O que mostrar">
                <button class="rs-aba active" role="tab" id="abaCadernos"
                        data-aba="cadernos" aria-selected="true" aria-controls="gridCadernos">
                    Cadernos
                    <span class="rs-aba__n" id="abaNumCadernos"><?= $TOTAL_CADERNOS ?></span>
                </button>
                <button class="rs-aba" role="tab" id="abaNotas"
                        data-aba="notas" aria-selected="false" aria-controls="secaoSoltos">
                    Notas soltas
                    <span class="rs-aba__n" id="abaNumNotas"><?= $TOTAL_SOLTOS ?></span>
                </button>
            </div>

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

            <!-- Grade de cadernos: já vem pronta do servidor.
                 Arrastar um cartão daqui reordena a estante; arrastar
                 um resumo para cima de um deles guarda o resumo ali. -->
            <div class="cadernos-grid" id="gridCadernos">
<?php foreach ($CADERNOS as $i => $c): ?>
<?php include __DIR__ . '/partes/caderno-card.php'; ?>
<?php endforeach; ?>
            </div>

            <!-- Estado vazio: nenhum caderno -->
            <div class="vazio" id="vazioCadernos"<?= $TOTAL_CADERNOS > 0 ? ' hidden' : '' ?>>
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5Z" stroke="currentColor" stroke-width="1.5"/><path d="M8 3v18M12 8h5M12 12h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                <h2>Nenhum caderno por aqui</h2>
                <p>Crie um caderno para a matéria que você está estudando e guarde os resumos dela dentro.</p>
            </div>

            <!-- ============================================================
                 Resumos fora de qualquer caderno.
                 Também é alvo de soltura: arrastar um resumo para cá
                 é como tirá-lo do caderno em que estava.
                 ============================================================ -->
            <section class="rs-soltos" id="secaoSoltos"<?= $TOTAL_SOLTOS > 0 ? '' : ' data-vazia="1"' ?>>
                <div class="rs-soltos__cabeca">
                    <h2>Sem caderno</h2>
                    <p>Resumos que ainda não estão em nenhum caderno.
                       <span class="rs-soltos__dica">Arraste um deles para cima de um caderno para guardá-lo —
                       ou use o menu <strong>⋮</strong> do cartão.</span></p>
                </div>

                <!-- Estado vazio das notas: no celular esta seção é uma
                     aba, então precisa dizer alguma coisa mesmo vazia -->
                <div class="vazio" id="vazioSoltos"<?= $TOTAL_SOLTOS > 0 ? ' hidden' : '' ?>>
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="4" y="3" width="16" height="18" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M8 8h8M8 12h8M8 16h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                    <h2>Nenhuma nota solta</h2>
                    <p>Está tudo guardado em algum caderno. Um resumo criado sem escolher caderno aparece aqui.</p>
                </div>

                <div class="resumos-grid" id="gridSoltos">
<?php foreach ($SOLTOS as $i => $r): ?>
<?php include __DIR__ . '/partes/resumo-card.php'; ?>
<?php endforeach; ?>
                </div>
            </section>
        </main>

        <?php include __DIR__ . '/partes/modal-confirma.php'; ?>

        <?php include __DIR__ . '/partes/modal-caderno.php'; ?>

        <?php include __DIR__ . '/partes/modal-resumo.php'; ?>

        <?php /* "Gerar flashcards" no menu ⋮ de cada resumo depende desta
                 parte estar na página: sem ela o item nem aparece. */ ?>
        <?php include __DIR__ . '/partes/modal-flashcards.php'; ?>

        <!-- os dados completos para a página se atualizar sem outra ida
             ao servidor (os resumos soltos já vêm com o texto) -->
        <script type="application/json" id="dadosCadernos"><?= json_encode($CADERNOS, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
        <script type="application/json" id="dadosResumos"><?= json_encode($SOLTOS, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

    <script src="./js/dashboard.js"></script>
    <script src="./js/pomodoro-aviso.js"></script>
    <script src="./js/resumo-mover.js"></script>
    <script src="./js/caderno-form.js"></script>
    <script src="./js/resumo-form.js"></script>
    <!-- antes do resumos.js: é ele que pergunta se o módulo existe
         para decidir se põe "Gerar flashcards" no menu ⋮ -->
    <script src="./js/flashcards-gerar.js"></script>
    <script src="./js/resumos.js"></script>
    <script src="./js/cursor.js"></script>
    <!-- O céu. Os mesmos dois arquivos da landing page: o WebGL tenta
         primeiro e, se não houver placa ou o shader não compilar, ele
         desiste em silêncio e o cosmos.js (Canvas 2D) assume — por isso
         esta ordem importa. Ver partes/fundo.php. -->
    <script src="../shared/cosmos-gl.js"></script>
    <script src="../shared/cosmos.js"></script>
</body>
</html>
