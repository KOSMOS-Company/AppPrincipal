<?php
// Porteiro + dados desta página (sem sessão, redireciona antes de
// mandar qualquer HTML). Deixa $USUARIO, $PREF e $PAGINA prontos.
require_once __DIR__ . '/../../../Backend/php/pagina_dashboard.php';
require_once __DIR__ . '/../../../Backend/php/exercicios_util.php';
require_once __DIR__ . '/../../../Backend/php/estatisticas_util.php';

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

/* As estrelas se agrupam por matéria (uma constelação cada) */
$MATERIAS_DOS_PONTOS = array_map(fn($m) => (string) ($m['materia'] ?? ''), $MATERIAS);
$POSICOES = posicoesConstelacoes($MATERIAS_DOS_PONTOS);

/* Só as matérias que aparecem viram filtro */
$MATERIAS_USADAS = array_values(array_unique(array_column($MATERIAS, 'materia')));
sort($MATERIAS_USADAS);

/* Os números do topo (partes/estatisticas.php): a faixa dos
   Flashcards adaptada — o acerto é o número que diz se o estudo
   está funcionando, e ganha a cor da faixa (verde/amarelo/vermelho). */
$NUM = ['materias' => 0, 'questoes' => 0, 'respostas' => 0, 'acerto' => null];
if (!$ERRO_BANCO) {
    try {
        $NUM = estatisticasExercicios($pdo, (int) $USUARIO['id']);
    } catch (PDOException $e) {
        // sem os números a página segue: a faixa mostra traços
    }
}
$ESTATS_ID = 'exStats';
$ESTATS = [
    ['materias',  $NUM['materias'],  'Matérias'],
    ['questoes',  $NUM['questoes'],  'Questões criadas'],
    ['respostas', $NUM['respostas'], 'Respostas'],
    // 0% é resultado de verdade (vira "0%", texto); sem respostas, null vira traço
    ['acerto', $NUM['acerto'] === null ? null : $NUM['acerto'] . '%', 'Acerto', $NUM['acerto'] === null ? '' : ($NUM['acerto'] >= 70 ? 'boa' : ($NUM['acerto'] >= 50 ? 'media' : 'baixa'))],
];

/* ============================================================
   A PÁGINA: um panorama em cima, uma lista embaixo
   ============================================================
   A tela tem duas camadas com trabalhos diferentes.

   O PANORAMA (topo, ~150px) é o retrato do que existe: mostra que há
   um céu de matérias e que ele é mais ou menos denso. Os pontos são
   grandes o bastante para se achar com o mouse e levam à matéria que
   a lista embaixo leva — mas o nome deles só aparece quando a pessoa
   pergunta (hover), porque escrever nome no céu seria transformar
   retrato em lista.

   A LISTA (baixo) é o caminho de verdade: uma matéria por linha, com
   o nome do tamanho que ele quiser e a faixa de quantidade
   traduzida na força do círculo. */

/* ============================================================
   O PANORAMA — as matérias como pontos de uma constelação
   ============================================================
   Quatro funções desenham isso, e todas têm gêmea em
   js/exercicios.js: o PHP monta a primeira pintura, o JS redesenha
   quando a prateleira muda (criou, apagou) ou quando a tela muda de
   largura. Se mexer em uma, mexa na outra. */

/** Onde cada ponto fica, em % do painel. Cada MATÉRIA (o campo
 *  "materia": Matemática, Física…) é uma constelação: um aglomerado
 *  com centro próprio, lado a lado no céu; dentro dele os pontos se
 *  espalham numa espiral de Vogel pequena. Recebe a lista de matérias
 *  (uma por ponto, na ordem dos pontos) e devolve [x, y] de cada um. */
function posicoesConstelacoes(array $materias): array {
    $grupos = [];
    $indice = [];
    $membro = [];
    foreach ($materias as $m) {
        if (!isset($indice[$m])) {
            $indice[$m] = count($grupos);
            $grupos[] = 0;
        }
        $g = $indice[$m];
        $membro[] = [$g, $grupos[$g]++];
    }
    $G = max(1, count($grupos));
    $meia = 40 / $G;

    $pos = [];
    foreach ($membro as [$g, $j]) {
        $k = $grupos[$g];
        $cx = 10 + (80 / $G) * ($g + .5);
        $cy = $G > 1 ? 50 + sin($g * 2.1) * 10 : 50;
        $x = $cx;
        $y = $cy;
        if ($k > 1) {
            $rxc = min($G === 1 ? 30 : 18, $meia * .8);
            $ryc = 32 - abs($cy - 50);
            $ang = deg2rad($j * 137.508 + $g * 57);
            $raio = sqrt(($j + .5) / $k);
            $x = $cx + $raio * cos($ang) * $rxc;
            $y = $cy + $raio * sin($ang) * $ryc;
        }
        $pos[] = [round($x, 2), round($y, 2)];
    }

    return $pos;
}

/** Os traços: a árvore mínima (Prim) entre os pontos da MESMA
 *  matéria. $ar é a proporção largura/altura do painel (aqui um
 *  palpite; o JS recalcula com a medida de verdade). */
function linhasConstelacoes(array $materias, array $pos, float $ar = 5): array {
    $porMateria = [];
    foreach ($materias as $i => $m) {
        $porMateria[$m][] = $i;
    }

    $linhas = [];
    foreach ($porMateria as $idx) {
        $dentro = [array_shift($idx)];
        while ($idx) {
            $melhor = null;
            foreach ($dentro as $a) {
                foreach ($idx as $f => $b) {
                    $dx = ($pos[$a][0] - $pos[$b][0]) * $ar;
                    $dy = $pos[$a][1] - $pos[$b][1];
                    $d = $dx * $dx + $dy * $dy;
                    if ($melhor === null || $d < $melhor[3]) {
                        $melhor = [$a, $b, $f, $d];
                    }
                }
            }
            $linhas[] = [$melhor[0], $melhor[1]];
            $dentro[] = $melhor[1];
            unset($idx[$melhor[2]]);
        }
    }

    return $linhas;
}

/** O tamanho do ponto é a quantidade de exercícios guardados nela.
 *  São as mesmas faixas de intensidadeQtd(), só aplicadas ao tamanho
 *  em vez da cor — é a mesma informação dizendo a mesma coisa nos
 *  dois lugares. */
function tamanhoEstrela(int $qtd): int {
    if ($qtd <= 0)  return 13;
    if ($qtd <= 4)  return 15;
    if ($qtd <= 11) return 17;
    if ($qtd <= 29) return 19;

    return 22;
}

/** O quanto o círculo da lista acende: a mesma faixa de quantidade,
 *  desta vez na intensidade da cor. Muita matéria acende, matéria
 *  parada empalidece — assim dá para caçar a matéria cheia de longe,
 *  sem ler nome nenhum. */
function intensidadeQtd(int $qtd): string {
    if ($qtd <= 0)  return '.34';
    if ($qtd <= 4)  return '.52';
    if ($qtd <= 11) return '.72';
    if ($qtd <= 29) return '.88';

    return '1';
}

/** O que fica escrito na ponta da linha da lista. */
function rotuloQtdExercicios(int $qtd): string {
    if ($qtd === 0) {
        return 'vazia';
    }

    return $qtd === 1 ? '1 exercício' : $qtd . ' exercícios';
}

/** O quanto a faixa cresce: um pouco, e só. O panorama é retrato, não
 *  mapa — se ele precisasse de espaço para caber, a lista embaixo é que
 *  teria de resolver. O piso acompanha o ponto: com o raio em 6,5px a
 *  faixa não pode ser a de antes, senão o ponto encosta nas bordas. */
function alturaMapa(int $materias): string {
    return max(150, min(200, 140 + $materias * 4)) . 'px';
}

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../shared/favicon.svg">
    <title>Kosmos — Exercícios</title>
    <link rel="stylesheet" href="<?= asset('../shared/tokens.css') ?>">
    <link rel="stylesheet" href="<?= asset('./css/dashboard.css') ?>">
    <link rel="stylesheet" href="<?= asset('../shared/cosmos.css') ?>">
    <link rel="stylesheet" href="<?= asset('./css/pomodoro-aviso.css') ?>">
    <link rel="stylesheet" href="<?= asset('./css/exercicios.css') ?>">
    <link rel="stylesheet" href="<?= asset('../shared/cursor.css') ?>">
    <link rel="stylesheet" href="<?= asset('../shared/logo.css') ?>">
    <link rel="preload" href="../shared/fontes/dm-sans-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="../shared/fontes/syne-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= asset('../shared/fontes.css') ?>">
</head>
<body class="dashboard-fundo-estatico">

    <?php include __DIR__ . '/partes/fundo.php'; ?>

    <div class="contGeral">

        <!-- Sidebar -->
        <?php include __DIR__ . '/partes/sidebar.php'; ?>

        <!-- Main -->
        <main class="contMeio pagina-exercicios">
            <header class="contCabeca">
                <div class="contCabeca__texto">
                    <?php $CAMINHO = [['Exercícios', null]]; require __DIR__ . '/partes/caminho.php'; ?>
                    <h1>Suas <span class="h-nome">Matérias</span></h1>
                </div>
                <div class="rs-acoes">
                    <button class="dash-btn dash-btn--primary" id="btnNovaMateria">
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 4v12M4 10h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        Nova matéria
                    </button>
                </div>
            </header>

            <?php require __DIR__ . '/partes/estatisticas.php'; ?>

            <!-- O PANORAMA: uma faixa de constelação com as matérias
                 como pontos, para dar contexto de quanto existe aqui.
                 Os pontos são clicáveis (levam à mesma matéria da
                 lista) e mostram o nome no hover, mas não escrevem
                 nada no céu: quem navega é a lista de baixo. Já vem
                 pintado pelo servidor; o SVG das linhas é o mesmo
                 desenho que o js/exercicios.js redesenha quando a
                 prateleira muda. -->
            <div class="ex-mapa<?= $TOTAL_MATERIAS === 0 ? ' ex-mapa--vazio' : '' ?>" id="gridMaterias" style="--altura-mapa: <?= hesc(alturaMapa($TOTAL_MATERIAS)) ?>">
                <svg class="ex-mapa__linhas" id="exMapaLinhas" viewBox="0 0 100 100" preserveAspectRatio="none" focusable="false" aria-hidden="true">
<?php foreach (linhasConstelacoes($MATERIAS_DOS_PONTOS, $POSICOES) as [$a, $b]):
        $de = $POSICOES[$a];
        $para = $POSICOES[$b]; ?>
                    <line x1="<?= $de[0] ?>" y1="<?= $de[1] ?>" x2="<?= $para[0] ?>" y2="<?= $para[1] ?>" vector-effect="non-scaling-stroke" />
<?php endforeach; ?>
                </svg>
<?php foreach ($MATERIAS as $i => $m): ?>
<?php include __DIR__ . '/partes/exercicio-mapa-ponto.php'; ?>
<?php endforeach; ?>
            </div>

            <!-- Os dois controles mexem na lista, então moram juntos
                 logo acima dela: o filtro diz quais matérias entram, a
                 ordem diz em que ordem elas entram. -->
            <div class="ex-controles" id="exControles"<?= $TOTAL_MATERIAS === 0 ? ' hidden' : '' ?>>

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

                <!-- Ordem: preferência do navegador, não da conta. As três
                     opções cabem atrás de um botão só — o filtro já
                     ocupa a esquerda da linha, e três chips de ordem
                     disputavam o espaço com ele sem informar nada.
                     O botão mostra a escolha em vigor; o JS escreve
                     nela a preferência lembrada antes da primeira
                     pintura. -->
                <div class="ordem">
                    <button type="button" class="ordem__botao" id="ordemBotao"
                            aria-haspopup="true" aria-expanded="false" aria-controls="ordemMaterias">
                        <svg class="ordem__icone" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M3.5 5.5h7M3.5 10h4.5M3.5 14.5h2M14.5 4v11m0 0 2.5-2.5M14.5 15 12 12.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <span class="ordem__prefixo">Ordenar</span>
                        <span class="ordem__atual" id="ordemAtual">Mais recentes</span>
                        <svg class="ordem__seta" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M5 8l5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>

                    <div class="ordem__menu" id="ordemMaterias" role="menu" aria-labelledby="ordemBotao" hidden>
                        <button type="button" class="ordem__item" role="menuitemradio" aria-checked="true" data-ordem="recentes">Mais recentes</button>
                        <button type="button" class="ordem__item" role="menuitemradio" aria-checked="false" data-ordem="alfabetica">Alfabética</button>
                        <button type="button" class="ordem__item" role="menuitemradio" aria-checked="false" data-ordem="exercicios">Mais exercícios</button>
                    </div>
                </div>
            </div>

            <!-- A LISTA: uma matéria por linha, como em Resumos — a
                 navegação de verdade. -->
            <div class="ex-lista" id="listaMaterias">
<?php foreach ($MATERIAS as $m): ?>
<?php include __DIR__ . '/partes/exercicio-materia-linha.php'; ?>
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

    <script src="<?= asset('./js/util.js') ?>"></script>
    <script src="<?= asset('./js/dashboard.js') ?>"></script>
    <script src="<?= asset('./js/pomodoro-aviso.js') ?>"></script>
    <script src="<?= asset('./js/combo-materia.js') ?>"></script>
    <script src="<?= asset('./js/exercicio-materia-form.js') ?>"></script>
    <script src="<?= asset('./js/exercicios.js') ?>"></script>
    <script src="<?= asset('../shared/cursor.js') ?>"></script>
    <!-- O céu. Os mesmos dois arquivos da landing page: o WebGL tenta
         primeiro e, se não houver placa ou o shader não compilar, ele
         desiste em silêncio e o cosmos.js (Canvas 2D) assume — por isso
         esta ordem importa. Ver partes/fundo.php. -->
    <script src="<?= asset('../shared/cosmos-gl.js') ?>"></script>
    <script src="<?= asset('../shared/cosmos.js') ?>"></script>
</body>
</html>