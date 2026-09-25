<?php
// ============================================================
//  KOSMOS — Conquistas e progresso
//  Nível, título, XP e o catálogo de badges: coloridas as que a
//  pessoa já tem, em silhueta com cadeado (e o quanto falta) as
//  que ainda não.
//
//  Ao abrir, a página CONFERE as conquistas (ProgressoService::
//  sincronizar). É o que entrega as badges a quem já tinha histórico
//  antes do sistema existir: a migração dá o XP retroativo, e as
//  conquistas desse histórico aparecem aqui, com o aviso de cada uma.
//
//  Porteiro + dados desta página: deixa $USUARIO, $PREF, $PROGRESSO
//  e $PAGINA prontos e redireciona antes de mandar qualquer HTML.
require_once __DIR__ . '/../../../Backend/php/pagina_dashboard.php';

$CONQUISTAS  = [];
$NOVIDADES   = null;   // payload para o js/progresso.js anunciar o que destravou agora
$RECOMPENSAS = [];
$MARCOS      = [];     // [nivel => título] — os títulos entram na trilha também

if ($PROGRESSO['disponivel']) {
    try {
        $svc  = new ProgressoService(conectar());
        $sinc = $svc->sincronizar($USUARIO['id']);
        if ($sinc['conquistas_desbloqueadas'] !== []) {
            $NOVIDADES = $sinc;
        }
        $PROGRESSO  = ['disponivel' => true] + $svc->estado($USUARIO['id']);
        $CONQUISTAS = $svc->listarConquistas($USUARIO['id']);
        $RECOMPENSAS = $svc->recompensas($USUARIO['id']);
        $MARCOS      = $svc->marcosDeTitulo();
    } catch (Throwable $e) {
        error_log('[KOSMOS conquistas] ' . $e->getMessage());
    }
}

$CATEGORIAS = [
    'foco'       => ['nome' => 'Foco',       'desc' => 'Ciclos de Pomodoro concluídos.'],
    'memoria'    => ['nome' => 'Memória',    'desc' => 'Flashcards e resumos.'],
    'exercicios' => ['nome' => 'Exercícios', 'desc' => 'Questões resolvidas e o Orion.'],
    'constancia' => ['nome' => 'Constância', 'desc' => 'Dias seguidos de estudo e níveis.'],
];
$porCategoria = [];
foreach ($CONQUISTAS as $c) {
    $porCategoria[$c['categoria']][] = $c;
}
$feitas = count(array_filter($CONQUISTAS, fn($c) => $c['desbloqueada']));

// Anel do nível: circunferência de r=52
$ANEL_C   = 2 * M_PI * 52;
$anelResto = $ANEL_C * (1 - $PROGRESSO['progresso_pct'] / 100);

// ---- Trilha: um nó por nível que destrava alguma coisa ----
$nivelAtual = (int) $PROGRESSO['nivel'];
$NOS = [];
foreach ($RECOMPENSAS as $r) {
    $NOS[$r['nivel']]['recompensas'][] = $r;
}
foreach ($MARCOS as $nivel => $titulo) {
    $NOS[$nivel]['titulo'] = $titulo;
}
ksort($NOS);
$proximoNo = null;
foreach (array_keys($NOS) as $nivel) {
    if ($nivel > $nivelAtual) { $proximoNo = $nivel; break; }
}

// O avatar da pessoa, como ele está agora (cor, moldura, emblema e foto)
$corAtual   = 'avatar-cor--' . hesc($PREF['avatar_cor']);
$trClasses  = 'tr-avatar ' . $corAtual . avatarClassesRecompensa($PREF);
$trEstilo   = '';
if (!empty($PREF['avatar_url'])) {
    $trClasses .= ' avatar--foto';
    $trEstilo   = 'background-image:url(&quot;' . hesc($PREF['avatar_url']) . '&quot;);'
                . 'background-position:' . (int) $PREF['avatar_pos_x'] . '% ' . (int) $PREF['avatar_pos_y'] . '%;';
}
$nomeEquipado = function (string $tipo) use ($RECOMPENSAS): ?string {
    foreach ($RECOMPENSAS as $r) {
        if ($r['tipo'] === $tipo && $r['equipada']) return $r['nome'];
    }
    return null;
};
$liberadas = count(array_filter($RECOMPENSAS, fn($r) => $r['liberada']));

// "Como ganhar XP": sai das MESMAS constantes que o servidor usa
$R  = ProgressoService::REGRAS;
$XP = ProgressoService::XP;
$COMO = [
    ['Ciclo de Pomodoro concluído (15 min ou mais)', '+' . $XP['pomodoro_concluido'], 'até ' . $R['pomodoro_concluido']['limite_dia'] . ' XP por dia'],
    ['Cartão de flashcard acertado', '+' . $XP['flashcard_acerto'], 'cada cartão uma vez por dia · até ' . $R['flashcard_acerto']['limite_dia'] . ' XP'],
    ['Sessão de flashcards terminada', '+' . $XP['sessao_flashcards'], 'até ' . $R['sessao_flashcards']['limite_dia'] . ' XP por dia'],
    ['Questão de exercício certa', '+' . $XP['exercicio_acerto'], 'errada vale +' . $XP['exercicio_erro'] . ' · até ' . $R['exercicio_acerto']['limite_dia'] . ' XP por dia'],
    ['Resumo novo ou caderno criado', '+' . $XP['conteudo_criado'], 'até ' . $R['conteudo_criado']['limite_dia'] . ' XP por dia'],
    ['Exercícios gerados com o Orion', '+' . $XP['orion_ia'], 'até ' . intdiv($R['orion_ia']['limite_dia'], $XP['orion_ia']) . ' vezes por dia'],
    ['Primeiro estudo do dia (sequência)', '+' . ProgressoService::STREAK_BASE, '+' . ProgressoService::STREAK_BONUS . ' por semana seguida, até +' . ProgressoService::STREAK_BONUS_TETO],
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../shared/favicon.svg">
    <title>Kosmos — Conquistas</title>
    <link rel="stylesheet" href="./css/dashboard.css">
    <link rel="stylesheet" href="../shared/cosmos.css">
    <link rel="stylesheet" href="./css/pomodoro-aviso.css">
    <link rel="stylesheet" href="./css/cursor.css">
    <link rel="stylesheet" href="../shared/logo.css">
    <link rel="stylesheet" href="./css/conquistas.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
    <?php if ($NOVIDADES): ?>
    <script>
        window.KOSMOS_PROGRESSO_INICIAL = <?= json_encode($NOVIDADES, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    </script>
    <?php endif; ?>
</head>
<body>

    <?php include __DIR__ . '/partes/fundo.php'; ?>

    <div class="contGeral">

        <?php include __DIR__ . '/partes/sidebar.php'; ?>

        <main class="contMeio">
            <header class="contCabeca">
                <div class="contCabeca__texto">
                    <span class="section-tag">Progresso</span>
                    <h1>Suas <span class="h-nome">conquistas</span></h1>
                    <p>Cada sessão de estudo te leva mais longe no cosmos.</p>
                </div>
            </header>

            <?php if (!$PROGRESSO['disponivel']): ?>
                <div class="vazio">
                    <h2>Progresso indisponível</h2>
                    <p>Não foi possível carregar o seu progresso agora. Tente de novo em instantes.</p>
                </div>
            <?php else: ?>

            <!-- Nível atual -->
            <section class="painel cq-hero" aria-labelledby="cqNivelTitulo">
                <div class="cq-anel" role="img"
                     aria-label="Nível <?= (int) $PROGRESSO['nivel'] ?>, <?= (int) $PROGRESSO['progresso_pct'] ?>% do caminho até o próximo">
                    <svg viewBox="0 0 120 120" aria-hidden="true">
                        <defs>
                            <linearGradient id="cqAnelGrad" x1="0" y1="0" x2="1" y2="1">
                                <stop offset="0" stop-color="#e7b8ff"/>
                                <stop offset=".5" stop-color="#a541ff"/>
                                <stop offset="1" stop-color="#6a00c9"/>
                            </linearGradient>
                        </defs>
                        <circle class="cq-anel__trilho" cx="60" cy="60" r="52"/>
                        <circle class="cq-anel__arco" cx="60" cy="60" r="52"
                                stroke-dasharray="<?= round($ANEL_C, 2) ?>"
                                stroke-dashoffset="<?= round($anelResto, 2) ?>"/>
                    </svg>
                    <span class="cq-anel__miolo">
                        <span class="cq-anel__rotulo">Nível</span>
                        <strong data-xp-nivel><?= (int) $PROGRESSO['nivel'] ?></strong>
                    </span>
                </div>

                <div class="cq-hero__texto">
                    <h2 class="cq-hero__titulo" id="cqNivelTitulo" data-xp-titulo><?= hesc($PROGRESSO['titulo']) ?></h2>
                    <p class="cq-hero__xp" data-xp-dica><?= hesc(progressoDica($PROGRESSO)) ?></p>
                    <span class="xp__barra cq-hero__barra" role="progressbar" aria-label="Progresso do nível"
                          aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) $PROGRESSO['progresso_pct'] ?>" data-xp-barra>
                        <span class="xp__preench" data-xp-pct style="--xp-pct: <?= (int) $PROGRESSO['progresso_pct'] ?>%"></span>
                    </span>

                    <dl class="cq-numeros">
                        <div>
                            <dt>Sequência de estudo</dt>
                            <dd><?= (int) $PROGRESSO['streak_dias'] ?> <?= (int) $PROGRESSO['streak_dias'] === 1 ? 'dia' : 'dias' ?></dd>
                        </div>
                        <div>
                            <dt>Conquistas</dt>
                            <dd><?= $feitas ?> de <?= count($CONQUISTAS) ?></dd>
                        </div>
                        <div>
                            <dt>XP total</dt>
                            <dd><?= number_format((int) $PROGRESSO['xp_total'], 0, ',', '.') ?></dd>
                        </div>
                    </dl>
                </div>
            </section>

            <?php if ($NOS): ?>
            <!-- Trilha de recompensas: o que cada nível destrava para o avatar.
                 Equipar é do js/recompensas.js; quem confere o nível é o
                 servidor (recompensas_equipar.php). -->
            <section class="trilha" id="trilha" aria-labelledby="trilhaTitulo">
                <div class="cq-secao__cabeca">
                    <h2 id="trilhaTitulo">Trilha de recompensas</h2>
                    <span class="cq-secao__conta"><?= $liberadas ?>/<?= count($RECOMPENSAS) ?></span>
                    <p>Cada nível destrava algo novo para o seu avatar: molduras, emblemas e cores exclusivas.</p>
                </div>

                <div class="painel trilha__topo">
                    <span class="<?= $trClasses ?>" id="trAvatar" style="<?= $trEstilo ?>" aria-hidden="true"><?= hesc($USUARIO['inicial']) ?></span>
                    <div class="trilha__eu">
                        <h3>Seu avatar</h3>
                        <p id="trResumo">
                            Moldura: <strong data-equipado="moldura"><?= hesc($nomeEquipado('moldura') ?? 'nenhuma') ?></strong> ·
                            Emblema: <strong data-equipado="emblema"><?= hesc($nomeEquipado('emblema') ?? 'nenhum') ?></strong> ·
                            Cor: <strong data-equipado="cor"><?= hesc($nomeEquipado('cor') ?? ucfirst($PREF['avatar_cor'])) ?></strong>
                        </p>
                    </div>
                </div>

                <ol class="trilha__lista" id="trilhaLista">
                    <?php foreach ($NOS as $nivel => $no):
                        $liberado = $nivel <= $nivelAtual;
                        $classeNo = 'trilha__no' . ($liberado ? ' trilha__no--liberado' : '') . ($nivel === $proximoNo ? ' trilha__no--proximo' : ''); ?>
                    <li class="<?= $classeNo ?>"<?= $nivel === $proximoNo ? ' data-proximo' : '' ?>>
                        <span class="trilha__marca" aria-hidden="true"><span class="trilha__nivel">Nível <?= $nivel ?></span></span>
                        <div class="trilha__card">
                            <span class="sr-only">Nível <?= $nivel ?><?= $liberado ? ', liberado' : ', bloqueado' ?>.</span>

                            <?php if (isset($no['titulo'])): ?>
                            <div class="trilha__rec">
                                <span class="trilha__titulo-ico" aria-hidden="true"><?= conquistaIconeSvg('coroa') ?></span>
                                <span class="trilha__tipo">Título</span>
                                <p class="trilha__nome"><?= hesc($no['titulo']) ?></p>
                            </div>
                            <?php endif; ?>

                            <?php foreach ($no['recompensas'] ?? [] as $r):
                                $classe = 'avatar-' . $r['tipo'] . '--' . hesc($r['valor']);
                                $previa = $r['tipo'] === 'cor' ? $classe : $corAtual . ' ' . $classe; ?>
                            <div class="trilha__rec">
                                <span class="tr-avatar tr-avatar--mini <?= $previa ?>"<?= $r['tipo'] !== 'cor' ? ' data-previa-cor' : '' ?> aria-hidden="true"><?= hesc($USUARIO['inicial']) ?></span>
                                <span class="trilha__tipo"><?= hesc($r['tipo_nome']) ?></span>
                                <p class="trilha__nome"><?= hesc($r['nome']) ?></p>
                                <p class="trilha__desc"><?= hesc($r['descricao']) ?></p>
                            </div>
                            <?php endforeach; ?>

                            <div class="trilha__acao">
                                <?php if (!$liberado): ?>
                                    <span class="trilha__travada"><?= conquistaIconeSvg('cadeado') ?> Chegue ao nível <?= $nivel ?></span>
                                <?php elseif (!empty($no['recompensas'])): ?>
                                    <?php foreach ($no['recompensas'] as $r): ?>
                                    <button type="button" class="dash-btn <?= $r['equipada'] ? 'dash-btn--ghost' : 'dash-btn--primary' ?>"
                                            data-equipar data-tipo="<?= hesc($r['tipo']) ?>" data-slug="<?= hesc($r['slug']) ?>"
                                            data-valor="<?= hesc($r['valor']) ?>" data-nome="<?= hesc($r['nome']) ?>"
                                            aria-pressed="<?= $r['equipada'] ? 'true' : 'false' ?>">
                                        <?= $r['equipada'] ? ($r['tipo'] === 'cor' ? 'Em uso' : 'Em uso · tirar') : 'Equipar ' . hesc(mb_strtolower($r['tipo_nome'])) ?>
                                    </button>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="trilha__auto">Título conquistado</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ol>
            </section>
            <?php endif; ?>

            <!-- Catálogo, por categoria -->
            <?php foreach ($CATEGORIAS as $slug => $cat):
                $lista = $porCategoria[$slug] ?? [];
                if ($lista === []) continue;
                $feitasCat = count(array_filter($lista, fn($c) => $c['desbloqueada'])); ?>
            <section class="cq-secao cq-secao--<?= $slug ?>" aria-labelledby="cq-<?= $slug ?>">
                <div class="cq-secao__cabeca">
                    <h2 id="cq-<?= $slug ?>"><?= $cat['nome'] ?></h2>
                    <span class="cq-secao__conta"><?= $feitasCat ?>/<?= count($lista) ?></span>
                    <p><?= $cat['desc'] ?></p>
                </div>

                <ul class="cq-grade">
                    <?php foreach ($lista as $c): ?>
                    <li class="cq-card<?= $c['desbloqueada'] ? '' : ' cq-card--travada' ?>">
                        <span class="cq-card__ico">
                            <?= $c['svg'] ?>
                            <?php if (!$c['desbloqueada']): ?>
                                <span class="cq-card__cadeado"><?= conquistaIconeSvg('cadeado') ?></span>
                            <?php endif; ?>
                        </span>
                        <div class="cq-card__texto">
                            <h3><?= hesc($c['nome']) ?></h3>
                            <p><?= hesc($c['descricao']) ?></p>
                            <?php if ($c['desbloqueada']): ?>
                                <span class="cq-card__rodape">
                                    <span class="cq-card__data">Desbloqueada em <?= hesc(dataCurtaPt($c['data']['dia'], $c['data']['mes'])) ?></span>
                                    <span class="cq-card__bonus">+<?= (int) $c['xp_bonus'] ?> XP</span>
                                </span>
                            <?php else: ?>
                                <span class="cq-card__progresso">
                                    <span class="xp__barra" aria-hidden="true">
                                        <span class="xp__preench" style="--xp-pct: <?= (int) floor(100 * $c['valor'] / max(1, $c['alvo'])) ?>%"></span>
                                    </span>
                                    <span class="cq-card__conta">
                                        <span class="sr-only">Progresso:</span>
                                        <?= (int) $c['valor'] ?>/<?= (int) $c['alvo'] ?>
                                    </span>
                                    <span class="cq-card__bonus">+<?= (int) $c['xp_bonus'] ?> XP</span>
                                </span>
                            <?php endif; ?>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endforeach; ?>

            <!-- As regras, às claras: quanto vale cada coisa e os limites -->
            <section class="cq-secao" aria-labelledby="cqComo">
                <div class="cq-secao__cabeca">
                    <h2 id="cqComo">Como ganhar XP</h2>
                    <p>Os limites diários existem para o XP medir estudo de verdade — não cliques.</p>
                </div>
                <ul class="painel cq-regras">
                    <?php foreach ($COMO as [$acao, $valor, $limite]): ?>
                    <li>
                        <span class="cq-regras__acao"><?= hesc($acao) ?></span>
                        <span class="cq-regras__limite"><?= hesc($limite) ?></span>
                        <strong class="cq-regras__xp"><?= hesc($valor) ?> XP</strong>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </section>

            <?php endif; ?>
        </main>

    </div>

    <script src="./js/dashboard.js"></script>
    <script src="./js/recompensas.js"></script>
    <script src="./js/pomodoro-aviso.js"></script>
    <script src="./js/cursor.js"></script>
    <!-- O céu. Os mesmos dois arquivos da landing page: o WebGL tenta
         primeiro e, se não houver placa ou o shader não compilar, ele
         desiste em silêncio e o cosmos.js (Canvas 2D) assume — por isso
         esta ordem importa. Ver partes/fundo.php. -->
    <script src="../shared/cosmos-gl.js"></script>
    <script src="../shared/cosmos.js"></script>
</body>
</html>
