<?php
// Porteiro + dados desta página (sem sessão, redireciona antes de
// mandar qualquer HTML). Deixa $USUARIO, $PREF e $PAGINA prontos.
require_once __DIR__ . '/../../../Backend/php/pagina_dashboard.php';

// ============================================================
//  PRIMEIROS PASSOS
//  Cada passo é uma LEITURA do que o usuário já fez no site — não
//  existe mais o "marcar como feito" à mão. Antes a lista vivia no
//  localStorage do navegador: ela mentia dos dois lados (quem tinha
//  trinta resumos via a lista zerada em outro aparelho, e quem só
//  clicou nos círculos via tudo pronto sem ter escrito nada).
//
//  Os critérios, um por passo:
//    resumo     -> tem ao menos um resumo
//    flashcards -> tem ao menos um CARTÃO (deck vazio não conta:
//                  o passo é "transformar o resumo em perguntas")
//    pomodoro   -> tem ao menos uma sessão de foco registrada
//
//  Os dois primeiros números já vieram do pagina_dashboard.php; só
//  a sessão de foco precisa de consulta, e ela é um EXISTS.
//
//  Isto aqui é a PRIMEIRA PINTURA. O inicio.js repete a conta com a
//  resposta do inicio_dados.php, para a lista também acertar sozinha
//  quando o Pomodoro termina um ciclo com a aba Início aberta.
// ============================================================
$PASSOS = [
    'resumo'     => (int) $USUARIO['resumos'] > 0,
    'flashcards' => (int) $USUARIO['cartoes'] > 0,
    'pomodoro'   => false,
];

if (!$ERRO_BANCO && isset($pdo)) {
    try {
        $stmt = $pdo->prepare('SELECT EXISTS(SELECT 1 FROM pomodoro_sessoes
                                              WHERE usuario_id = ?) AS fez');
        $stmt->execute([(int) $USUARIO['id']]);
        $PASSOS['pomodoro'] = (bool) ($stmt->fetch()['fez'] ?? 0);
    } catch (PDOException $e) {
        // sem a sessão de foco o passo fica pendente; a tela não quebra
    }
}

$PASSOS_TOTAL  = count($PASSOS);
$PASSOS_FEITOS = count(array_filter($PASSOS));

// ============================================================
//  MISSÕES DA SEMANA
//  Com os três primeiros passos feitos, o cartão vira "Semana 1",
//  "Semana 2"... com quatro missões lidas do que a pessoa fez na
//  semana (ver Backend/php/missoes.php). Antes disso, null.
// ============================================================
require_once __DIR__ . '/../../../Backend/php/missoes.php';
$MISSOES = null;
if ($PASSOS_FEITOS === $PASSOS_TOTAL && !$ERRO_BANCO && isset($pdo)) {
    $MISSOES = missoesEstado($pdo, (int) $USUARIO['id']);
}

/** "termina hoje" / "termina amanhã" / "faltam N dias" */
function missoesPrazo(int $dias): string {
    if ($dias === 0) return 'termina hoje';
    if ($dias === 1) return 'termina amanhã';
    return "faltam {$dias} dias";
}

/** Este passo já está cumprido? (usado três vezes na lista abaixo) */
function passoFeito(array $passos, string $slug): bool {
    return !empty($passos[$slug]);
}
?>
﻿<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../shared/favicon.svg">
    <title>Kosmos — Dashboard</title>
    <link rel="stylesheet" href="<?= asset('../shared/tokens.css') ?>">
    <link rel="stylesheet" href="<?= asset('./css/dashboard.css') ?>">
    <link rel="stylesheet" href="<?= asset('../shared/cosmos.css') ?>">
    <link rel="stylesheet" href="<?= asset('./css/pomodoro-aviso.css') ?>">
    <link rel="stylesheet" href="<?= asset('../shared/cursor.css') ?>">
    <link rel="stylesheet" href="<?= asset('../shared/logo.css') ?>">
    <link rel="stylesheet" href="<?= asset('../shared/mascote.css') ?>">
    <link rel="stylesheet" href="<?= asset('./css/onboarding.css') ?>">
    <link rel="stylesheet" href="<?= asset('./css/intro.css') ?>">
    <link rel="preload" href="../shared/fontes/dm-sans-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="../shared/fontes/syne-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= asset('../shared/fontes.css') ?>">
    <script src="<?= asset('./js/intro-inicial.js') ?>"></script>
</head>
<body>

    <!-- Splash de boas-vindas cinematográfico (mini desenho animado de introdução) -->
    <div class="intro" id="intro" aria-hidden="true">
        <canvas class="intro__ceu" id="introCeu"></canvas>
        <div class="intro__glow"></div>

        <div class="intro__cenario">
            <!-- O Orion chega voando, sem nada em volta e sem falar -->
            <div class="intro__ator-mascote" id="introMascoteHero">
                <div class="mascote" id="introMascote" data-mascote aria-hidden="true"></div>
            </div>

            <!-- Logo: o planeta no lugar do "o" gira enquanto carrega -->
            <div class="intro__marca-oficial">
                <div class="intro__logo-wrapper">
                    <span class="klogo" role="img" aria-label="Kosmos">K<i class="klogo__o"></i>smos</span>
                </div>
            </div>
        </div>

        <button class="intro__pular" id="introPular" type="button">
            Pular intro
            <span class="intro__pular-barra" id="introBarra"></span>
        </button>
    </div>

    <?php include __DIR__ . '/partes/fundo.php'; ?>

    <!-- Layout Container -->
    <div class="contGeral">

        <!-- Sidebar (Lateral) -->
        <?php include __DIR__ . '/partes/sidebar.php'; ?>

        <!-- Main Content (Meio) -->
        <main class="contMeio pagina-inicio">

            <!-- ==========================================================
                 ABERTURA

                 Era um hero de landing page dentro do app: pílula, título
                 gigante, parágrafo de venda, dois botões e quatro números
                 soltos — quase uma tela inteira antes de qualquer coisa
                 útil. Mas quem abre o Início já se cadastrou; não precisa
                 ser convencido, precisa saber como está e continuar.

                 Agora é uma linha só, no formato da prévia que está na
                 landing page: saudação à esquerda, sequência à direita.
                 ========================================================== -->
            <header class="ini-hero">
                <div class="ini-hero__texto">
                    <span class="ini-data" id="iniData">Hoje</span>
                    <h1 class="ini-hero__titulo">
                        <span id="iniSaudacao">Bem-vindo</span>,
                        <span class="ini-hero__nome"><?= hesc($USUARIO['primeiro']) ?></span>
                    </h1>
                    <p class="ini-hero__desc">
                        <?= $USUARIO['sequencia'] > 0
                            ? 'Continue de onde parou.'
                            : 'Uma sessão de foco hoje já começa sua sequência.' ?>
                    </p>
                </div>

                <!-- O selo da sequência, no topo direito, ao lado da saudação:
                     é a primeira coisa que o olho encontra junto do nome.
                     O astro num quadradinho + número grande EMPILHADO sobre o
                     rótulo. O astro EVOLUI com os dias (estrela → cometa →
                     planeta → galáxia, ver partes/astro-sequencia.php), e o
                     fundo dele e o número ficam mais roxos e fortes (--seq-t: 0 no 1º dia,
                     1 a partir de 30; as cores moram no dashboard.css).

                     `.ini-stat` é contrato: é por ela que o js/inicio.js
                     apaga o número quando zera (`el.closest('.ini-stat')`).
                     Por isso também nada de <span> aqui dentro — as regras
                     genéricas `.ini-stat span` desmontariam o selo. -->
                <?php
                    $seq  = (int) $USUARIO['sequencia'];
                    $seqT = round(min(max($seq - 1, 0), 29) / 29, 2);
                    require_once __DIR__ . '/partes/astro-sequencia.php';
                    $fase = astroFase($seq);   // 0 a 5: qual astro desenhar (ver partes/astro-sequencia.php)
                ?>
                <div class="ini-seq ini-seq--f<?= $fase ?> ini-stat<?= $seq === 0 ? ' ini-stat--vazio' : '' ?>" style="--seq-t: <?= $seqT ?>">
                    <i class="ini-seq__chama" aria-hidden="true"><?= astroSequencia($seq) ?></i>
                    <p class="ini-seq__num">
                        <strong data-metrica="sequencia"><?= $seq ?></strong>
                        <b><?= $seq === 1 ? 'dia de sequência' : 'dias de sequência' ?></b>
                    </p>
                </div>
            </header>

            <!-- ==========================================================
                 ESTA SEMANA — o painel principal

                 É o que a prévia da landing page mostra em primeiro plano,
                 e é a única coisa nesta tela que responde "como eu estou
                 indo". Por isso subiu: antes estava no fim da página.
                 ========================================================== -->
            <section class="ini-painel painel">
                <!-- Duas "páginas" no mesmo painel: a semana (gráfico) e o
                     mês (calendário de frequência). Quem troca é o js/inicio.js;
                     os dados do mês vêm do Backend/php/foco_mensal.php. -->
                <div class="ini-painel__cabeca">
                    <h2 id="iniPainelTitulo">Esta semana</h2>
                    <div class="ini-vista" role="tablist" aria-label="Período">
                        <button type="button" role="tab" class="ini-vista__btn ativo" id="iniVistaSemana"
                                aria-selected="true" aria-controls="iniSemana">Semana</button>
                        <button type="button" role="tab" class="ini-vista__btn" id="iniVistaMes"
                                aria-selected="false" aria-controls="iniMes" tabindex="-1">Mês</button>
                    </div>
                </div>

                <!-- as duas visões moram juntas: é esta caixa que muda de altura na troca -->
                <div class="ini-vistas" id="iniVistas">
                    <div id="iniSemana" role="tabpanel" aria-labelledby="iniVistaSemana">
                        <!-- As colunas são desenhadas pelo inicio.js (CSS puro, sem
                             biblioteca), sempre de domingo a sábado. -->
                        <div class="ini-chart" id="iniChart" role="img"
                             aria-label="Tempo de foco desta semana"></div>
                    </div>

                    <div class="ini-mes" id="iniMes" role="tabpanel" aria-labelledby="iniVistaMes" hidden>
                        <div class="foco-mes__nav">
                            <button type="button" class="foco-mes__seta" id="focoMesAnterior" aria-label="Mês anterior">‹</button>
                            <strong class="foco-mes__titulo" id="focoMesTitulo" aria-live="polite">—</strong>
                            <button type="button" class="foco-mes__seta" id="focoMesProximo" aria-label="Próximo mês">›</button>
                        </div>

                        <div class="ini-mes__corpo">
                            <dl class="foco-mes__resumo">
                                <div><dt>Total no mês</dt><dd data-foco="total">—</dd></div>
                                <div><dt>Dias com foco</dt><dd data-foco="dias">—</dd></div>
                                <div><dt>Média por dia de foco</dt><dd data-foco="media">—</dd></div>
                                <div><dt>Melhor dia</dt><dd data-foco="melhor">—</dd></div>
                            </dl>

                            <div class="ini-mes__calendario">
                                <div class="foco-mes__semana" aria-hidden="true">
                                    <span>dom</span><span>seg</span><span>ter</span><span>qua</span><span>qui</span><span>sex</span><span>sáb</span>
                                </div>
                                <div class="foco-mes__grade" id="focoMesGrade" role="group" aria-label="Tempo de foco por dia"></div>
                                <div class="foco-mes__legenda" aria-hidden="true">
                                    <span>menos</span>
                                    <i class="foco-n0"></i><i class="foco-n1"></i><i class="foco-n2"></i><i class="foco-n3"></i><i class="foco-n4"></i>
                                    <span>mais</span>
                                </div>
                            </div>
                        </div>

                        <p class="foco-mes__erro" id="focoMesErro" hidden>Não foi possível carregar este mês.</p>
                    </div>
                </div>

                <!-- A meta do dia. A coluna `meta_diaria` existe em
                     usuario_preferencias desde agosto e NENHUM código a
                     lia — salvar um número que nada usa é pior do que não
                     ter o campo, porque promete um ajuste que não ajusta
                     nada. Escondida até os dados chegarem. -->
                <div class="ini-meta" id="iniMeta" role="group" hidden>
                    <progress data-meta-barra value="0" max="60"></progress>
                    <span class="ini-meta__txt" data-meta-texto></span>
                </div>

                <!-- Os outros números vivem AQUI, no rodapé do painel, e não
                     mais soltos no topo da página: eles são contexto do
                     "como estou indo", não manchete. -->
                <div class="ini-numeros">
                    <div class="ini-stat<?= $USUARIO['resumos'] === 0 ? ' ini-stat--vazio' : '' ?>">
                        <strong data-metrica="resumos"><?= $USUARIO['resumos'] > 0 ? (int) $USUARIO['resumos'] : '—' ?></strong>
                        <span><?= $USUARIO['resumos'] === 1 ? 'resumo' : 'resumos' ?></span>
                    </div>
                    <div class="ini-stat<?= $USUARIO['cartoes'] === 0 ? ' ini-stat--vazio' : '' ?>">
                        <strong data-metrica="flashcards"><?= $USUARIO['cartoes'] > 0 ? (int) $USUARIO['cartoes'] : '—' ?></strong>
                        <span>flashcards</span>
                    </div>
                    <div class="ini-stat ini-stat--vazio">
                        <strong data-metrica="exercicios">—</strong>
                        <span>exercícios</span>
                    </div>

                    <!-- REVISAR HOJE / REFAZER: no canto direito do rodapé,
                         ao lado dos números. A faixa some quando os dois
                         lembretes estão escondidos. -->
                    <div class="ini-faixa">
                        <!-- REVISAR HOJE: a fila de revisão atravessando todos os
                             baralhos. Nasce escondida e só aparece quando há o que
                             revisar (js/inicio.js) — "0 cartões" seria ruído. -->
                        <a class="ini-revisar" id="iniRevisar" href="revisar.php" hidden>
                            <span class="ini-revisar__ico" aria-hidden="true"><svg class="ico" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="8.4" y="3.2" width="12" height="15.2" rx="2.2"/><path d="M15.6 20.8H6.2a2.6 2.6 0 0 1-2.6-2.6V7.6"/></svg></span>
                            <span class="ini-revisar__txt">
                                <strong data-revisar-n>0</strong>
                                <span data-revisar-txt>cartões para revisar</span>
                            </span>
                            <span class="ini-revisar__cta" aria-hidden="true">Revisar agora</span>
                        </a>
                        <!-- REFAZER EXERCÍCIOS: o mesmo lembrete, para os exercícios
                             com questão errada na última tentativa. Também nasce
                             escondido; leva à matéria quando é uma só (js/inicio.js). -->
                        <a class="ini-revisar ini-revisar--ex" id="iniRevisarEx" href="exercicios.php" hidden>
                            <span class="ini-revisar__ico" aria-hidden="true"><svg class="ico" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="4.5" y="3.2" width="15" height="17.6" rx="2.2"/><path d="M8.5 8.5h7M8.5 12.5h4"/><path d="m13.6 16.4 1.6 1.6 3.2-3.4"/></svg></span>
                            <span class="ini-revisar__txt">
                                <strong data-revisar-ex-n>0</strong>
                                <span data-revisar-ex-txt>exercícios para refazer</span>
                            </span>
                            <span class="ini-revisar__cta" aria-hidden="true">Refazer agora</span>
                        </a>
                    </div>
                </div>
            </section>

            <!-- ==========================================================
                 ATALHOS

                 O que saiu daqui foi a moldura de campanha em volta ("O
                 que você quer fazer agora?", com tag de seção e título
                 grande): num app, uma fileira de atalhos não precisa ser
                 anunciada.

                 Provas entrou nesta fileira quando a funcionalidade saiu
                 do fim desta página e virou uma aba de Foco, ao lado do
                 Pomodoro. Uma prova não é um aviso — é um plano, com
                 assuntos que caem, material para estudar e resultado
                 depois. Nada disso cabia numa lista no rodapé do Início.
                 ========================================================== -->
            <section class="ini-secao">
                <h2 class="ini-titulo">Atalhos</h2>

                <div class="ini-grid">
                    <a class="ini-card" href="resumos.php">
                        <span class="ini-card__ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><rect x="4" y="4" width="16" height="16" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M8 10 H16 M8 14 H12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        </span>
                        <h3 class="ini-card__titulo">Biblioteca</h3>
                        <p class="ini-card__desc">Cadernos por matéria.</p>
                    </a>

                    <a class="ini-card" href="flashcards.php">
                        <span class="ini-card__ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><rect x="3" y="6" width="13" height="12" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M8 4 H19 a2 2 0 0 1 2 2 V16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        </span>
                        <h3 class="ini-card__titulo">Flashcards</h3>
                        <p class="ini-card__desc">Revisão por repetição.</p>
                    </a>

                    <a class="ini-card" href="exercicios.php">
                        <span class="ini-card__ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><path d="M5 7 H19 M5 12 H19 M5 17 H13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        </span>
                        <h3 class="ini-card__titulo">Exercícios <span class="ini-tag">IA</span></h3>
                        <p class="ini-card__desc">Questões geradas na hora.</p>
                    </a>

                    <a class="ini-card" href="pomodoro.php">
                        <span class="ini-card__ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="13" r="8" stroke="currentColor" stroke-width="1.8"/><path d="M12 9 L12 13 L15 15 M9 3 H15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        <h3 class="ini-card__titulo">Pomodoro</h3>
                        <p class="ini-card__desc">25 de foco, 5 de pausa.</p>
                    </a>

                    <a class="ini-card" href="provas.php">
                        <span class="ini-card__ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><rect x="3.5" y="5" width="17" height="15" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M3.5 10 H20.5 M8 3 V6 M16 3 V6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="12" cy="15" r="1.6" fill="currentColor"/></svg>
                        </span>
                        <h3 class="ini-card__titulo">Provas</h3>
                        <p class="ini-card__desc">A contagem e o que cai.</p>
                    </a>
                </div>
            </section>

            <!-- ==========================================================
                 PRIMEIROS PASSOS
                 É a única parte da tela que ensina o caminho a quem acabou
                 de chegar — e agora ela se marca sozinha: cada item é lido
                 do que o usuário fez (ver o bloco $PASSOS no topo deste
                 arquivo). Por isso o círculo é um <span> e não um <button>:
                 não há nada para clicar, ele RELATA um estado.
                 ========================================================== -->
            <?php if ($MISSOES): ?>
            <!-- ==========================================================
                 MISSÕES DA SEMANA
                 Toma o lugar dos primeiros passos quando eles terminam.
                 Cada missão se marca sozinha (o js/inicio.js repinta com
                 a resposta do inicio_dados.php, chave `missoes`).
                 ========================================================== -->
            <section class="ini-secao">
                <div class="ini-card ini-passos ini-missoes<?= $MISSOES['completa'] ? ' ini-missoes--completa' : '' ?>" id="iniMissoes">
                    <div class="ini-card__cabeca ini-missoes__cabeca">
                        <div class="ini-missoes__titulo">
                            <span class="ini-missoes__semana" data-missoes-semana>Semana <?= (int) $MISSOES['semana'] ?></span>
                            <h3>Missões da semana <span class="ini-missoes__nome" data-missoes-nome>· <?= hesc($MISSOES['nome']) ?></span></h3>
                        </div>
                        <span class="ini-card__nota" id="iniMissoesContador"><?=
                            $MISSOES['completa']
                                ? 'semana completa ✦'
                                : $MISSOES['feitas'] . ' de ' . $MISSOES['total'] . ' · ' . missoesPrazo($MISSOES['dias_restantes'])
                        ?></span>
                    </div>
                    <progress id="iniMissoesBarra" value="<?= (int) $MISSOES['feitas'] ?>" max="<?= (int) $MISSOES['total'] ?>"></progress>

                    <ul class="ini-passos__lista">
                        <?php foreach ($MISSOES['missoes'] as $m):
                            $pct = (int) floor(100 * $m['valor'] / max(1, $m['alvo'])); ?>
                        <li class="ini-passo ini-missao<?= $m['feita'] ? ' feito' : '' ?>" data-missao="<?= hesc($m['slug']) ?>">
                            <span class="ini-passo__check" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12.5 L10 17.5 L19 7" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <a class="ini-passo__link ini-missao__link" href="<?= hesc($m['href']) ?>">
                                <strong data-missao-titulo><?= hesc($m['titulo']) ?></strong>
                                <span data-missao-dica><?= hesc($m['dica']) ?></span>
                                <span class="sr-only ini-passo__estado"><?= $m['feita'] ? 'Concluída' : 'Em andamento' ?></span>
                            </a>
                            <span class="ini-missao__prog">
                                <span class="ini-missao__num" data-missao-prog><?= hesc($m['progresso']) ?></span>
                                <span class="ini-missao__barra" aria-hidden="true"><span data-missao-pct style="--pct: <?= $pct ?>%"></span></span>
                            </span>
                        </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if ($MISSOES['historico']): ?>
                    <!-- As semanas anteriores, da mais antiga para a atual -->
                    <ol class="ini-missoes__historico" aria-label="Semanas anteriores">
                        <?php foreach ($MISSOES['historico'] as $h): ?>
                        <li class="ini-missoes__marco<?= $h['completa'] ? ' ini-missoes__marco--ok' : '' ?>"
                            title="Semana <?= (int) $h['semana'] ?>: <?= (int) $h['feitas'] ?> de <?= (int) $h['total'] ?> missões">
                            <span class="sr-only">Semana <?= (int) $h['semana'] ?>: <?= $h['completa'] ? 'completa' : (int) $h['feitas'] . ' de ' . (int) $h['total'] . ' missões' ?></span>
                            <span aria-hidden="true">S<?= (int) $h['semana'] ?><?= $h['completa'] ? ' ✓' : ' · ' . (int) $h['feitas'] . '/' . (int) $h['total'] ?></span>
                        </li>
                        <?php endforeach; ?>
                        <li class="ini-missoes__marco ini-missoes__marco--atual" aria-current="true">
                            <span class="sr-only">Semana <?= (int) $MISSOES['semana'] ?>, atual</span>
                            <span aria-hidden="true">S<?= (int) $MISSOES['semana'] ?> · agora</span>
                        </li>
                    </ol>
                    <?php endif; ?>
                </div>
            </section>
            <?php else: ?>
            <section class="ini-secao">
                <div class="ini-card ini-passos">
                    <div class="ini-card__cabeca">
                        <h3>Primeiros passos</h3>
                        <span class="ini-card__nota" id="iniPassosContador"><?=
                            $PASSOS_FEITOS === $PASSOS_TOTAL
                                ? 'tudo pronto ✦'
                                : $PASSOS_FEITOS . ' de ' . $PASSOS_TOTAL
                        ?></span>
                    </div>
                    <progress id="iniPassosBarra" value="<?= $PASSOS_FEITOS ?>" max="<?= $PASSOS_TOTAL ?>"></progress>
                    <ul class="ini-passos__lista">
                        <li class="ini-passo<?= passoFeito($PASSOS, 'resumo') ? ' feito' : '' ?>" data-passo="resumo">
                            <span class="ini-passo__check" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12.5 L10 17.5 L19 7" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <a class="ini-passo__link" href="resumos.php">
                                <strong>Criar seu primeiro resumo</strong>
                                <span>Comece pela matéria que você viu hoje.</span>
                                <span class="sr-only ini-passo__estado"><?= passoFeito($PASSOS, 'resumo') ? 'Concluído' : 'Ainda não feito' ?></span>
                            </a>
                        </li>
                        <li class="ini-passo<?= passoFeito($PASSOS, 'flashcards') ? ' feito' : '' ?>" data-passo="flashcards">
                            <span class="ini-passo__check" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12.5 L10 17.5 L19 7" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <a class="ini-passo__link" href="flashcards.php">
                                <strong>Montar um baralho</strong>
                                <span>Transforme o resumo em perguntas curtas.</span>
                                <span class="sr-only ini-passo__estado"><?= passoFeito($PASSOS, 'flashcards') ? 'Concluído' : 'Ainda não feito' ?></span>
                            </a>
                        </li>
                        <li class="ini-passo<?= passoFeito($PASSOS, 'pomodoro') ? ' feito' : '' ?>" data-passo="pomodoro">
                            <span class="ini-passo__check" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12.5 L10 17.5 L19 7" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <a class="ini-passo__link" href="pomodoro.php">
                                <strong>Fazer 25 minutos de foco</strong>
                                <span>Um ciclo de Pomodoro já conta para a sequência.</span>
                                <span class="sr-only ini-passo__estado"><?= passoFeito($PASSOS, 'pomodoro') ? 'Concluído' : 'Ainda não feito' ?></span>
                            </a>
                        </li>
                    </ul>
                    <p class="ini-passos__depois">Concluindo os três, começam as <strong>missões da semana</strong>.</p>
                </div>
            </section>
            <?php endif; ?>
        </main>

    </div>

    <?php /* O confirmar() do dashboard.js precisa desta parte na página;
             sem ela ele devolve `true` na hora e a ação acontece sem
             perguntar nada. */ ?>
    <?php include __DIR__ . '/partes/modal-confirma.php'; ?>

    <?php if (!empty($PREF['mostrar_onboarding'])): ?>
        <?php
        /* Marca AQUI, no instante em que o modal entra na página, e não
           no onboarding_salvar.php quando a pessoa envia as respostas.

           É o que garante "uma vez e pronto": responder, pular, fechar a
           aba no meio ou navegar para outra seção — o resultado é o
           mesmo, as perguntas não voltam. Enquanto a marca dependia do
           envio, quem saísse pelo meio revia tudo a cada visita à aba
           Início, que era a reclamação original.

           O onboarding_salvar.php continua gravando 1 no fim; é
           redundante agora, e redundância aqui não custa nada. */
        if (!$ERRO_BANCO && isset($pdo)) {
            try {
                $pdo->prepare('UPDATE usuario_preferencias
                                  SET onboarding_completo = 1
                                WHERE usuario_id = ?')
                    ->execute([(int) $USUARIO['id']]);
            } catch (PDOException $e) {
                // Não deu para marcar: o modal aparece de novo na próxima
                // visita. Preferível a esconder as perguntas de quem nunca
                // as viu.
            }
        }
        ?>
        <?php include __DIR__ . '/partes/modal-onboarding.php'; ?>
    <?php endif; ?>

    <script src="<?= asset('./js/util.js') ?>"></script>
    <script src="<?= asset('./js/dashboard.js') ?>"></script>
    <script src="<?= asset('./js/pomodoro-aviso.js') ?>"></script>
    <script src="<?= asset('./js/inicio.js') ?>"></script>
    <script src="<?= asset('../shared/cursor.js') ?>"></script>
    <?php /* o mascote.js já vem do partes/sidebar.php (antes deste ponto) */ ?>
    <script src="<?= asset('./js/intro.js') ?>"></script>
    <?php if (!empty($PREF['mostrar_onboarding'])): ?>
        <script src="<?= asset('./js/onboarding.js') ?>"></script>
    <?php endif; ?>
    <!-- O céu. Os mesmos dois arquivos da landing page: o WebGL tenta
         primeiro e, se não houver placa ou o shader não compilar, ele
         desiste em silêncio e o cosmos.js (Canvas 2D) assume — por isso
         esta ordem importa. Ver partes/fundo.php. -->
    <script src="<?= asset('../shared/cosmos-gl.js') ?>"></script>
    <script src="<?= asset('../shared/cosmos.js') ?>"></script>
</body>
</html>
