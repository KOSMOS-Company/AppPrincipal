<?php
// ============================================================
//  KOSMOS — Barra lateral do dashboard (parte reaproveitada)
//  Antes este mesmo HTML estava copiado nas 6 páginas: qualquer
//  ajuste exigia editar tudo. Agora é um include só.
//
//  Espera as variáveis de pagina_dashboard.php ($USUARIO, $PREF,
//  $PAGINA). Sem elas, não é para ser aberto direto no navegador.
// ============================================================
if (!isset($USUARIO, $PREF, $PAGINA)) {
    http_response_code(403);
    exit('Esta página não é acessada direto.');
}

/** Marca o link da página atual (o JS só posiciona o marcador).
 *  Recebe as classes fixas do link e devolve UM atributo class: antes
 *  elas iam num class="" separado, o link saía com dois atributos e o
 *  navegador descartava o segundo — a Conta nunca aparecia ativa. */
function navAtivo(string $arquivo, string $atual, string $classes = ''): string {
    $lista = trim($classes . ($arquivo === $atual ? ' active' : ''));
    return $lista === '' ? '' : ' class="' . $lista . '"';
}

/* Sub-abas não têm link próprio no menu — elas nascem de uma seção.
   Sem isto, `$PAGINA` ("exercicio_materia.php") não batia com nenhum
   href e a página ficava sem item ativo: o marcador do menu não
   aparecia e todos os rótulos ficavam com o mesmo brilho, como se
   estivessem todos selecionados. */
$PAGINA_SECAO = [
    'exercicio_materia.php' => 'exercicios.php',
    'resumo.php'            => 'resumos.php',
    'caderno.php'           => 'resumos.php',
    'revisar.php'           => 'index.php',
];
$secaoAtual = $PAGINA_SECAO[$PAGINA] ?? $PAGINA;

// Classes e estilo do avatar: cor escolhida e, se houver, a foto
// já no enquadramento salvo — nada disso depende de JS agora.
$avatarClasses = 'usuario__avatar avatar-cor--' . hesc($PREF['avatar_cor']) . avatarClassesRecompensa($PREF);
$avatarEstilo  = '';
if (!empty($PREF['avatar_url'])) {
    $avatarClasses .= ' avatar--foto';
    $avatarEstilo   = 'background-image:url(&quot;' . hesc($PREF['avatar_url']) . '&quot;);'
                    . 'background-position:' . (int) $PREF['avatar_pos_x'] . '% '
                    . (int) $PREF['avatar_pos_y'] . '%;';
}

// Barra de XP (ver Backend/php/ProgressoService.php). Os data-xp-* são
// os ganchos que o js/progresso.js atualiza quando a pessoa ganha XP
// sem recarregar a página — o HTML daqui é só o estado inicial.
$xpTem    = !empty($PROGRESSO['disponivel']);
$xpPct    = (int) ($PROGRESSO['progresso_pct'] ?? 0);
$xpDica   = $xpTem ? progressoDica($PROGRESSO) : '';
$xpRotulo = 'Nível ' . (int) $PROGRESSO['nivel'] . ' · ' . $PROGRESSO['titulo'];

// Anel do nível no chip do celular: circunferência de r=13
$xpAnelC     = 2 * M_PI * 13;
$xpAnelResto = $xpAnelC * (1 - $xpPct / 100);
$xpProxima   = $PROGRESSO['proxima_recompensa'] ?? null;
?>
        <!-- Restaura a barra recolhida ANTES de o <aside> ser lido pelo
             navegador. Se isso rodasse junto com o resto do JS, a barra
             apareceria aberta e encolheria na frente da pessoa a cada
             carregamento — e com a transição de largura ligada, o salto
             seria bem visível. É a mesma razão pela qual o index.php lê
             `kosmos_intro` aqui em cima. -->
        <script type="application/json" id="dadosConta"><?= json_encode([
            'conta'            => (int) $USUARIO['id'],
            'pagina'           => $PAGINA,
            'tutorial'         => !empty($PREF['mostrar_tutorial']),
            'tutorialPremiado' => !empty($PREF['tutorial_premiado']),
            'tutorialCss'      => asset('./css/tutorial.css'),
            'tutorialJs'       => asset('./js/tutorial.js'),
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
        <script src="<?= asset('./js/conta-inicial.js') ?>"></script>
        <link rel="stylesheet" href="<?= asset('./css/progresso.css') ?>">
        <link rel="stylesheet" href="<?= asset('./css/recompensas.css') ?>">
        <!-- Síncrono e antes de qualquer outro script da página: ele
             embrulha o fetch para perceber o `progresso` que os
             endpoints devolvem e mostrar o "+50 XP" — em qualquer aba,
             sem cada tela precisar lembrar de chamar nada. -->
        <script src="<?= asset('./js/progresso.js') ?>"></script>
        <!-- Camada "Apple" (molas, materiais, gestos). Aqui e não no <head>
             de cada aba de propósito: carregada depois do CSS da página,
             ela vence os empates sem precisar de !important. -->
        <link rel="stylesheet" href="<?= asset('./css/apple.css') ?>">
        <!-- Visual Lite (opcional, por aparelho): depois do apple.css para
             vencer os mesmos empates. Só age com html.kosmos-lite, que o
             partes/fundo.php liga antes da primeira pintura. -->
        <link rel="stylesheet" href="<?= asset('./css/lite.css') ?>">

        <aside class="contLateral">
            <div class="contTopo">
                <!-- Menu do celular: as três barrinhas abrem a gaveta lateral
                     (.lateral__gaveta, mais abaixo) com a mesma navegação do
                     computador. No celular não há mais barra embaixo. -->
                <button type="button" class="topo-btn-ferramentas" id="btnMenuMobile"
                        aria-label="Abrir menu" aria-controls="gavetaMobile" aria-expanded="false" title="Menu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                        <path d="M4 7h16M4 12h16M4 17h16"/>
                    </svg>
                </button>

                <a class="contLogo" href="index.php">
                    <!-- Dois estados da marca, e o CSS escolhe qual aparece.
                         Aberta: a wordmark. Recolhida: o favicon — o mesmo
                         ícone que a pessoa vê na aba do navegador e no
                         atalho da tela inicial, então é ELE que ela
                         reconhece como "o app", não um "K" recortado. -->
                    <span class="logo__text klogo" role="img" aria-label="Kosmos">K<i class="klogo__o"></i>smos</span>
                    <img class="contLogo__icone" src="../shared/favicon.svg" alt="Kosmos"
                         width="34" height="34" decoding="async">
                </a>

                <!-- Recolher/abrir a barra: ao lado da marca, no canto do topo
                     (é onde se procura esse controle em apps de barra lateral).
                     Recolhida, o .contTopo vira coluna e o botão desce para
                     baixo do ícone. Só no computador — no celular a navegação
                     é a barra de baixo, e o CSS o esconde lá. -->
                <button class="lateral__aperta" id="lateralAperta" type="button"
                        aria-expanded="true" aria-controls="navPrincipal"
                        aria-label="Recolher menu" title="Recolher menu">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3.5" y="4.5" width="17" height="15" rx="3"/>
                        <path d="M9.5 4.5v15"/>
                        <path class="lateral__aperta-seta" d="M15.5 10l-2 2 2 2"/>
                    </svg>
                </button>

                <!-- Sequência + nível (só no celular, canto direito do topo).
                     Dois números num chip só: a chama com os dias seguidos e o
                     anel do nível (o anel É a barra de XP, enrolada). Tocar
                     abre o painel que explica os dois — número solto no canto
                     não diz o que é. Sem a progressão disponível, fica só a
                     sequência, como antes. -->
                <?php $seq = (int) $USUARIO['sequencia'];
                      $seqTexto = $seq . ' ' . ($seq === 1 ? 'dia seguido' : 'dias seguidos'); ?>
                <?php if ($xpTem): ?>
                <button type="button" class="topo-sequencia topo-status<?= $seq === 0 ? ' topo-sequencia--vazia' : '' ?>" id="btnStatus"
                        aria-haspopup="dialog" aria-expanded="false" aria-controls="statusPainel"
                        aria-label="<?= $seqTexto ?>, nível <?= (int) $PROGRESSO['nivel'] ?>. Ver progresso">
                    <span class="topo-status__seq">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9.4 20.7C5.9 19.7 3.8 15.5 4.7 10.4 5.8 11.8 7 12.7 8.2 13.1 8.3 9 9.9 6 11.8 3.2 12.3 5.8 16.8 8.4 16.4 13.4 17.2 12.6 18 11.7 18.5 10.5 20.1 15.3 18 19.6 14.3 20.7 15.3 18.4 15.1 15 12 13.1 11 14 10.6 16 10.8 17.4L7.9 16.4C8 18 8.6 19.6 9.4 20.7Z"/></svg>
                        <strong><?= $seq ?></strong>
                    </span>
                    <span class="topo-status__nivel" aria-hidden="true">
                        <svg viewBox="0 0 32 32">
                            <circle class="topo-status__trilho" cx="16" cy="16" r="13"/>
                            <circle class="topo-status__arco" cx="16" cy="16" r="13" data-xp-anel
                                    stroke-dasharray="<?= round($xpAnelC, 2) ?>" stroke-dashoffset="<?= round($xpAnelResto, 2) ?>"/>
                        </svg>
                        <span data-xp-nivel><?= (int) $PROGRESSO['nivel'] ?></span>
                    </span>
                </button>

                <div class="status-painel" id="statusPainel" role="dialog" aria-labelledby="statusTitulo" hidden>
                    <p class="status-painel__titulo" id="statusTitulo">Seu progresso</p>

                    <div class="status-painel__item">
                        <span class="status-painel__ico<?= $seq === 0 ? ' status-painel__ico--apagado' : '' ?>"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9.4 20.7C5.9 19.7 3.8 15.5 4.7 10.4 5.8 11.8 7 12.7 8.2 13.1 8.3 9 9.9 6 11.8 3.2 12.3 5.8 16.8 8.4 16.4 13.4 17.2 12.6 18 11.7 18.5 10.5 20.1 15.3 18 19.6 14.3 20.7 15.3 18.4 15.1 15 12 13.1 11 14 10.6 16 10.8 17.4L7.9 16.4C8 18 8.6 19.6 9.4 20.7Z"/></svg></span>
                        <span class="status-painel__txt">
                            <strong><?= $seqTexto ?></strong>
                            <span data-status-hoje><?= !empty($PROGRESSO['estudou_hoje'])
                                ? 'Você já estudou hoje. Sequência garantida.'
                                : 'Estude hoje: o primeiro estudo do dia vale +' . ProgressoService::STREAK_BASE . ' XP.' ?></span>
                        </span>
                    </div>

                    <div class="status-painel__item">
                        <span class="xp__nivel" data-xp-nivel><?= (int) $PROGRESSO['nivel'] ?></span>
                        <span class="status-painel__txt">
                            <strong>Nível <span data-xp-nivel><?= (int) $PROGRESSO['nivel'] ?></span> · <span data-xp-titulo><?= hesc($PROGRESSO['titulo']) ?></span></strong>
                            <span class="xp__barra" aria-hidden="true"><span class="xp__preench" data-xp-pct style="--xp-pct: <?= $xpPct ?>%"></span></span>
                            <span class="status-painel__dica" data-xp-dica><?= hesc($xpDica) ?></span>
                        </span>
                    </div>

                    <p class="status-painel__proxima" data-status-proxima<?= $xpProxima ? '' : ' hidden' ?>>
                        <?php if ($xpProxima): ?>
                        Próxima recompensa: <strong><?= hesc($xpProxima['nome']) ?></strong>
                        (<?= hesc(mb_strtolower($xpProxima['tipo_nome'])) ?>) no nível <?= (int) $xpProxima['nivel'] ?>
                        <?php endif; ?>
                    </p>

                    <a class="dash-btn dash-btn--primary status-painel__cta" href="conquistas.php#trilha">Trilha e conquistas</a>
                </div>
                <?php else: ?>
                <span class="topo-sequencia<?= $seq === 0 ? ' topo-sequencia--vazia' : '' ?>" role="img"
                      aria-label="Sequência: <?= $seqTexto ?>" title="<?= $seqTexto ?>">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9.4 20.7C5.9 19.7 3.8 15.5 4.7 10.4 5.8 11.8 7 12.7 8.2 13.1 8.3 9 9.9 6 11.8 3.2 12.3 5.8 16.8 8.4 16.4 13.4 17.2 12.6 18 11.7 18.5 10.5 20.1 15.3 18 19.6 14.3 20.7 15.3 18.4 15.1 15 12 13.1 11 14 10.6 16 10.8 17.4L7.9 16.4C8 18 8.6 19.6 9.4 20.7Z"/></svg>
                    <strong><?= $seq ?></strong>
                </span>
                <?php endif; ?>
            </div>

            <!-- A navegação, o nível e o perfil moram juntos aqui. No computador
                 o contêiner não existe para o layout (display: contents) e a barra
                 continua a mesma; no celular ele vira a gaveta que sai da
                 esquerda pelas três barrinhas do topo. -->
            <div class="lateral__gaveta" id="gavetaMobile" aria-label="Menu" tabindex="-1">
            <div class="gaveta__topo">
                <span class="logo__text klogo" role="img" aria-label="Kosmos">K<i class="klogo__o"></i>smos</span>
                <button type="button" class="gaveta__fechar" id="btnFecharGaveta" aria-label="Fechar menu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>

            <nav class="botoesL" id="navPrincipal" aria-label="Navegação principal">
                <!-- marcador que desliza entre os itens (posicionado pelo dashboard.js) -->
                <span class="nav__marca" aria-hidden="true"></span>

                <!-- `data-rotulo` alimenta a etiqueta que aparece ao lado do
                     ícone quando a barra está recolhida. É CSS puro
                     (content: attr), então funciona no teclado também — não
                     depende de hover. -->
                <a href="index.php" data-rotulo="Início"<?= navAtivo('index.php', $secaoAtual) ?>>
                    <svg viewBox="0 0 24 24" fill="none" class="nav-icon nav-icon--outline"><path d="M4 10 L12 4 L20 10 L20 20 L4 20 Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <svg viewBox="0 0 24 24" fill="currentColor" class="nav-icon nav-icon--filled"><path d="M4 10 L12 4 L20 10 L20 20 L4 20 Z"/></svg>
                    <span class="nav__rotulo">Início</span>
                </a>

                <a href="orion.php" data-rotulo="Orion"<?= navAtivo('orion.php', $secaoAtual) ?>>
                    <!-- planeta do Orion usando o capelo (o mesmo do mascote): a
                         tábua larga e achatada no topo, a copa encaixada no planeta
                         e a borla caindo do canto direito. A estrelinha saiu para
                         dar lugar à borla. -->
                    <svg viewBox="0 0 24 24" fill="none" class="nav-icon nav-icon--outline" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="5.5"/>
                        <path d="M3.5 13.5 C5 9.5 12 7 17.5 9 C20.5 10 21 11.5 20.5 12.5 C19 14.5 12 17 6.5 15 C3.5 14 3 12.5 3.5 11.5"/>
                        <path d="M9.2 5.2 V7.3 M14.8 5.2 V7.3" stroke-width="1.6"/>
                        <path d="M6.5 4.2 L12 2 L17.5 4.2 L12 6.2 Z" fill="currentColor" stroke-width="1"/>
                        <path d="M17.2 4.4 V7.6" stroke-width="1.3"/>
                    </svg>
                    <!-- No preenchido o anel vai em traço: preenchido, a curva aberta
                         virava uma mancha por cima do planeta. -->
                    <svg viewBox="0 0 24 24" fill="currentColor" class="nav-icon nav-icon--filled" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="5.5"/>
                        <path d="M3.5 13.5 C5 9.5 12 7 17.5 9 C20.5 10 21 11.5 20.5 12.5 C19 14.5 12 17 6.5 15 C3.5 14 3 12.5 3.5 11.5" fill="none" stroke="currentColor" stroke-width="2"/>
                        <path d="M9.2 5 H14.8 V8 H9.2 Z"/>
                        <path d="M6.5 4.2 L12 2 L17.5 4.2 L12 6.2 Z" stroke="currentColor" stroke-width="1"/>
                        <path d="M17.2 4.4 V7.6" fill="none" stroke="currentColor" stroke-width="1.3"/>
                    </svg>
                    <span class="nav__rotulo">Orion <span class="nav__tag-ia">IA</span></span>
                </a>

                <a href="busca.php" data-rotulo="Buscar"<?= navAtivo('busca.php', $secaoAtual) ?>>
                    <svg viewBox="0 0 24 24" fill="none" class="nav-icon nav-icon--outline"><circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="2"/><path d="M16 16 L21 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    <svg viewBox="0 0 24 24" fill="currentColor" class="nav-icon nav-icon--filled"><circle cx="11" cy="11" r="6.5"/><path d="M16 16 L21 21" stroke-width="2" stroke-linecap="round" stroke="currentColor"/></svg>
                    <span class="nav__rotulo">Buscar</span>
                </a>

                <span class="nav__grupo">Estudar</span>
                <a href="resumos.php" data-rotulo="Biblioteca"<?= navAtivo('resumos.php', $secaoAtual) ?>>
                    <!-- ícone de estante: a aba guarda cadernos, não folhas soltas -->
                    <svg viewBox="0 0 24 24" fill="none" class="nav-icon nav-icon--outline"><path d="M4 5a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M11 5a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-3a1 1 0 0 1-1-1V5Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="m18.4 6.2 2.2 13.1" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    <svg viewBox="0 0 24 24" fill="currentColor" class="nav-icon nav-icon--filled"><path d="M4 5a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5Z"/><path d="M11 5a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-3a1 1 0 0 1-1-1V5Z"/><path d="m18.4 6.2 2.2 13.1" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
                    <span class="nav__rotulo">Biblioteca</span>
                </a>
                <a href="flashcards.php" data-rotulo="Flashcards"<?= navAtivo('flashcards.php', $secaoAtual) ?>>
                    <svg viewBox="0 0 24 24" fill="none" class="nav-icon nav-icon--outline"><rect x="3" y="6" width="13" height="12" rx="2" stroke="currentColor" stroke-width="2"/><path d="M8 4 H19 a2 2 0 0 1 2 2 V16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    <svg viewBox="0 0 24 24" fill="currentColor" class="nav-icon nav-icon--filled"><rect x="3" y="6" width="13" height="12" rx="2"/><path d="M8 4 H19 a2 2 0 0 1 2 2 V16" fill="none" stroke-width="2" stroke-linecap="round" stroke="currentColor"/></svg>
                    <span class="nav__rotulo">Flashcards</span>
                </a>
                <a href="exercicios.php" data-rotulo="Exercícios"<?= navAtivo('exercicios.php', $secaoAtual) ?>>
                    <svg viewBox="0 0 24 24" fill="none" class="nav-icon nav-icon--outline"><path d="M4 6 H20 M4 12 H20 M4 18 H14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    <svg viewBox="0 0 24 24" fill="currentColor" class="nav-icon nav-icon--filled"><path d="M4 6 H20 M4 12 H20 M4 18 H14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    <span class="nav__rotulo">Exercícios</span>
                </a>

                <span class="nav__grupo">Foco</span>
                <a href="pomodoro.php" data-rotulo="Pomodoro"<?= navAtivo('pomodoro.php', $secaoAtual) ?>>
                    <svg viewBox="0 0 24 24" fill="none" class="nav-icon nav-icon--outline"><circle cx="12" cy="13" r="8" stroke="currentColor" stroke-width="2"/><path d="M12 9 L12 13 L15 15 M9 3 H15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <svg viewBox="0 0 24 24" fill="currentColor" class="nav-icon nav-icon--filled"><circle cx="12" cy="13" r="8"/><path d="M12 9 L12 13 L15 15 M9 3 H15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span class="nav__rotulo">Pomodoro</span>
                </a>
                <a href="provas.php" data-rotulo="Provas"<?= navAtivo('provas.php', $secaoAtual) ?>>
                    <!-- ícone de calendário com um dia marcado: o que a aba
                         faz é apontar um dia no futuro e contar até ele -->
                    <svg viewBox="0 0 24 24" fill="none" class="nav-icon nav-icon--outline"><rect x="3.5" y="5" width="17" height="15" rx="2" stroke="currentColor" stroke-width="2"/><path d="M3.5 10 H20.5 M8 3 V6 M16 3 V6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="15" r="1.6" fill="currentColor"/></svg>
                    <svg viewBox="0 0 24 24" fill="currentColor" class="nav-icon nav-icon--filled"><rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 10 H20.5 M8 3 V6 M16 3 V6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="15" r="1.6"/></svg>
                    <span class="nav__rotulo">Provas</span>
                </a>
            </nav>

            <?php if ($xpTem): ?>
            <!-- Nível e XP (só no computador; no celular é o fio do topo e o
                 cartão da gaveta). Leva à página de conquistas. -->
            <a class="xp<?= $PAGINA === 'conquistas.php' ? ' ativa' : '' ?>" href="conquistas.php"
               data-xp data-rotulo="<?= hesc($xpRotulo) ?>" aria-describedby="xpDica">
                <span class="xp__nivel" data-xp-nivel><?= (int) $PROGRESSO['nivel'] ?></span>
                <span class="xp__info">
                    <span class="xp__rotulo">Nível <span data-xp-nivel><?= (int) $PROGRESSO['nivel'] ?></span></span>
                    <strong class="xp__titulo" data-xp-titulo><?= hesc($PROGRESSO['titulo']) ?></strong>
                    <span class="xp__barra" role="progressbar" aria-label="Progresso do nível"
                          aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $xpPct ?>" data-xp-barra>
                        <span class="xp__preench" data-xp-pct style="--xp-pct: <?= $xpPct ?>%"></span>
                    </span>
                </span>
                <span class="xp__dica" id="xpDica" role="tooltip" data-xp-dica><?= hesc($xpDica) ?></span>
            </a>
            <?php endif; ?>

            <?php /* O rodapé faz DUAS coisas diferentes e agora elas estão
                     separadas: o cartão é a pessoa (leva ao Perfil e só a
                     ele), a engrenagem é o ajuste (o resto das seções e o
                     sair). Antes o cartão levava a "conta" em geral e o
                     vizinho dele era uma porta de saída — ação destrutiva
                     a um clique de distância, sem nada entre as duas. */ ?>
            <div class="contUsuario">
                <a class="usuario<?= $PAGINA === 'conta.php' ? ' ativa' : '' ?>" href="conta.php#perfil"
                   data-rotulo="<?= hesc($USUARIO['nome']) ?>">
                    <span class="<?= $avatarClasses ?>" style="<?= $avatarEstilo ?>" aria-hidden="true"><?= hesc($USUARIO['inicial']) ?></span>
                    <span class="usuario__info">
                        <strong><?= hesc($USUARIO['nome']) ?></strong>
                        <span>Ver perfil</span>
                    </span>
                </a>

                <div class="conf">
                    <button class="conf__botao" type="button" id="btnConfig"
                            aria-haspopup="true" aria-expanded="false" aria-controls="menuConfig"
                            title="Configurações" aria-label="Configurações">
                        <!-- Engrenagem de verdade: uma roda DENTADA. A primeira versão
                             eram oito raios saindo de um círculo e, no tamanho em
                             que ela aparece, aquilo lia como um sol, não como um
                             ajuste. Os 32 pontos do contorno foram calculados
                             (raio da ponta 10.5, raio da raiz 7.8, 8 dentes), por
                             isso os decimais. -->
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"><path d="M10.25 4.4L10.45 1.62L13.55 1.62L13.75 4.4L16.13 5.39L18.25 3.56L20.44 5.75L18.61 7.87L19.6 10.25L22.38 10.45L22.38 13.55L19.6 13.75L18.61 16.13L20.44 18.25L18.25 20.44L16.13 18.61L13.75 19.6L13.55 22.38L10.45 22.38L10.25 19.6L7.87 18.61L5.75 20.44L3.56 18.25L5.39 16.13L4.4 13.75L1.62 13.55L1.62 10.45L4.4 10.25L5.39 7.87L3.56 5.75L5.75 3.56L7.87 5.39Z"/><circle cx="12" cy="12" r="3.5"/></svg>
                    </button>

                    <?php /* Menu de verdade (role="menu" + role="menuitem"): para
                             quem usa leitor de tela isto anuncia "menu com 6 itens",
                             e não seis links soltos que apareceram do nada. */ ?>
                    <div class="conf__menu" id="menuConfig" role="menu" aria-labelledby="btnConfig" hidden>
                        <a class="conf__item" role="menuitem" href="conta.php#seguranca">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3l7 3v6c0 4-3 7.5-7 9-4-1.5-7-5-7-9V6l7-3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9.5 12.5l1.8 1.8 3.4-3.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Segurança
                        </a>
                        <a class="conf__item" role="menuitem" href="conta.php#estudo">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="13" r="8" stroke="currentColor" stroke-width="1.8"/><path d="M12 9v4l3 2M9 3h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                            Estudo
                        </a>
                        <a class="conf__item" role="menuitem" href="conta.php#notificacoes">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 9a6 6 0 1 1 12 0c0 4 1.5 5.5 1.5 5.5H4.5S6 13 6 9Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M10 18a2 2 0 0 0 4 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                            Notificações
                        </a>
                        <a class="conf__item" role="menuitem" href="conta.php#privacidade">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="4" y="10" width="16" height="10" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M8 10V7a4 4 0 0 1 8 0v3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                            Privacidade
                        </a>
                        <a class="conf__item" role="menuitem" href="conta.php#sobre">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.8"/><path d="M12 8v.5M12 11v5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
                            Sobre
                        </a>

                        <hr class="conf__divisor" role="separator">

                        <button class="conf__item conf__item--visual" type="button" role="menuitemcheckbox"
                                aria-checked="false" data-visual-alternar
                                title="Menos brilho e movimento, mais leve para o aparelho">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M13 3 5 13.5h6L10 21l8-10.5h-6L13 3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
                            Visual Lite
                            <span class="conf__chave" aria-hidden="true"></span>
                        </button>

                        <hr class="conf__divisor" role="separator">

                        <button class="conf__item conf__item--sair" type="button" role="menuitem">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 5 H18 a1 1 0 0 1 1 1 V18 a1 1 0 0 1 -1 1 H15 M10 8 L6 12 L10 16 M6 12 H15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Sair da conta
                        </button>
                    </div>
                </div>
            </div>
            </div><!-- /.lateral__gaveta -->

            <!-- o escuro atrás da gaveta: tocar nele fecha -->
            <div class="gaveta__veu" id="gavetaVeu" aria-hidden="true"></div>
        </aside>

        <script src="<?= asset('./js/apple.js') ?>" defer></script>
        <script src="<?= asset('./js/visual.js') ?>" defer></script>

        <?php if ($PAGINA !== 'orion.php'): ?>
            <?php include __DIR__ . '/orion-chat.php'; ?>
            <link rel="stylesheet" href="<?= asset('../shared/mascote.css') ?>">
            <script src="<?= asset('../shared/mascote.js') ?>"></script>
            <script src="<?= asset('./js/orion-ia.js') ?>" defer></script>
            <script src="<?= asset('./js/orion-chat.js') ?>" defer></script>
        <?php endif; ?>
        <?php /* O tutorial.js/css do Orion não entram aqui: o carregador lá em
                 cima (perto do KOSMOS_CONTA) só os baixa quando a aba precisa. */ ?>
