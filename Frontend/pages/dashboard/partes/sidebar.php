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

// Classes e estilo do avatar: cor escolhida e, se houver, a foto
// já no enquadramento salvo — nada disso depende de JS agora.
$avatarClasses = 'usuario__avatar avatar-cor--' . hesc($PREF['avatar_cor']);
$avatarEstilo  = '';
if (!empty($PREF['avatar_url'])) {
    $avatarClasses .= ' avatar--foto';
    $avatarEstilo   = 'background-image:url(&quot;' . hesc($PREF['avatar_url']) . '&quot;);'
                    . 'background-position:' . (int) $PREF['avatar_pos_x'] . '% '
                    . (int) $PREF['avatar_pos_y'] . '%;';
}
?>
        <!-- Restaura a barra recolhida ANTES de o <aside> ser lido pelo
             navegador. Se isso rodasse junto com o resto do JS, a barra
             apareceria aberta e encolheria na frente da pessoa a cada
             carregamento — e com a transição de largura ligada, o salto
             seria bem visível. É a mesma razão pela qual o index.php lê
             `kosmos_intro` aqui em cima. -->
        <script>
            try {
                if (localStorage.getItem('kosmos_lateral') === 'recolhida') {
                    document.documentElement.classList.add('lateral-recolhida');
                }
            } catch (e) { /* navegação privada: segue aberta, sem drama */ }
        </script>
        <!-- Camada "Apple" (molas, materiais, gestos). Aqui e não no <head>
             de cada aba de propósito: carregada depois do CSS da página,
             ela vence os empates sem precisar de !important. -->
        <link rel="stylesheet" href="./css/apple.css">

        <aside class="contLateral">
            <!-- A seta de recolher NÃO mora aqui dentro: ela é posicionada
                 na borda direita da barra (ver o CSS). Ficar ao lado da
                 marca a fazia disputar atenção com o próprio nome do app. -->
            <div class="contTopo">
                <!-- Botão de ferramentas no mobile (posição azul no topo-esquerdo) -->
                <?php $isFerramentasAtiva = in_array($PAGINA, ['pomodoro.php', 'flashcards.php', 'exercicios.php', 'provas.php'], true); ?>
                <button type="button" class="topo-btn-ferramentas<?= $isFerramentasAtiva ? ' ativo' : '' ?>" id="btnMaisMobile"
                        aria-label="Mais ferramentas" aria-haspopup="true" aria-expanded="false" title="Mais ferramentas">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="nav-icon">
                        <rect x="3" y="3" width="7" height="7" rx="1.8"/>
                        <rect x="14" y="3" width="7" height="7" rx="1.8"/>
                        <rect x="3" y="14" width="7" height="7" rx="1.8"/>
                        <rect x="14" y="14" width="7" height="7" rx="1.8"/>
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

                <!-- Sequência de dias (só no mobile, no canto direito do topo).
                     Ocupa o lugar do antigo espaçador que só existia para
                     centralizar o logo — e fica em todas as páginas, porque
                     a sequência é da pessoa, não da aba Início. -->
                <?php $seq = (int) $USUARIO['sequencia']; ?>
                <span class="topo-sequencia<?= $seq === 0 ? ' topo-sequencia--vazia' : '' ?>" role="img"
                      aria-label="Sequência: <?= $seq ?> <?= $seq === 1 ? 'dia seguido' : 'dias seguidos' ?>"
                      title="<?= $seq ?> <?= $seq === 1 ? 'dia seguido' : 'dias seguidos' ?>">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21.2a5.6 5.6 0 0 0 5.6-5.6c0-4.6-5.6-9.2-5.6-9.2S6.4 11 6.4 15.6A5.6 5.6 0 0 0 12 21.2Z"/><path d="M12 21.2a2.4 2.4 0 0 0 2.4-2.4c0-2-2.4-4.1-2.4-4.1s-2.4 2.1-2.4 4.1a2.4 2.4 0 0 0 2.4 2.4Z"/></svg>
                    <strong><?= $seq ?></strong>
                </span>
            </div>

            <!-- Só no computador: no celular a navegação é a barra de baixo,
                 onde não existe nada para recolher (o CSS a esconde lá). -->
            <button class="lateral__aperta" id="lateralAperta" type="button"
                    aria-expanded="true" aria-controls="navPrincipal"
                    aria-label="Recolher menu">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M14 7 L9 12 L14 17" stroke="currentColor" stroke-width="2.2"
                          stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>

            <nav class="botoesL" id="navPrincipal" aria-label="Navegação principal">
                <!-- marcador que desliza entre os itens (posicionado pelo dashboard.js) -->
                <span class="nav__marca" aria-hidden="true"></span>

                <!-- `data-rotulo` alimenta a etiqueta que aparece ao lado do
                     ícone quando a barra está recolhida. É CSS puro
                     (content: attr), então funciona no teclado também — não
                     depende de hover. -->
                <a href="index.php" data-rotulo="Início"<?= navAtivo('index.php', $PAGINA) ?>>
                    <svg viewBox="0 0 24 24" fill="none" class="nav-icon"><path d="M4 10 L12 4 L20 10 L20 20 L4 20 Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span class="nav__rotulo">Início</span>
                </a>

                <a href="orion.php" data-rotulo="Orion"<?= navAtivo('orion.php', $PAGINA) ?>>
                    <svg viewBox="0 0 24 24" fill="none" class="nav-icon" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="5.5"/>
                        <path d="M3.5 13.5 C5 9.5 12 7 17.5 9 C20.5 10 21 11.5 20.5 12.5 C19 14.5 12 17 6.5 15 C3.5 14 3 12.5 3.5 11.5"/>
                        <path d="M19 3.5 L19.4 5.2 L21.1 5.6 L19.4 6 L19 7.7 L18.6 6 L16.9 5.6 L18.6 5.2 Z" fill="currentColor" stroke="none"/>
                    </svg>
                    <span class="nav__rotulo">Orion <span class="nav__tag-ia">IA</span></span>
                </a>

                <a href="busca.php" data-rotulo="Buscar"<?= navAtivo('busca.php', $PAGINA) ?>>
                    <svg viewBox="0 0 24 24" fill="none" class="nav-icon"><circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="2"/><path d="M16 16 L21 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    <span class="nav__rotulo">Buscar</span>
                </a>

                <span class="nav__grupo">Estudar</span>
                <a href="resumos.php" data-rotulo="Biblioteca"<?= navAtivo('resumos.php', $PAGINA) ?>>
                    <!-- ícone de estante: a aba guarda cadernos, não folhas soltas -->
                    <svg viewBox="0 0 24 24" fill="none" class="nav-icon"><path d="M4 5a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M11 5a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-3a1 1 0 0 1-1-1V5Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="m18.4 6.2 2.2 13.1" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    <span class="nav__rotulo">Biblioteca</span>
                </a>
                <a href="flashcards.php" data-rotulo="Flashcards"<?= navAtivo('flashcards.php', $PAGINA, 'nav-secundario-mobile') ?>>
                    <svg viewBox="0 0 24 24" fill="none" class="nav-icon"><rect x="3" y="6" width="13" height="12" rx="2" stroke="currentColor" stroke-width="2"/><path d="M8 4 H19 a2 2 0 0 1 2 2 V16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    <span class="nav__rotulo">Flashcards</span>
                </a>
                <a href="exercicios.php" data-rotulo="Exercícios"<?= navAtivo('exercicios.php', $PAGINA, 'nav-secundario-mobile') ?>>
                    <svg viewBox="0 0 24 24" fill="none" class="nav-icon"><path d="M4 6 H20 M4 12 H20 M4 18 H14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    <span class="nav__rotulo">Exercícios</span>
                </a>

                <span class="nav__grupo">Foco</span>
                <a href="pomodoro.php" data-rotulo="Pomodoro"<?= navAtivo('pomodoro.php', $PAGINA, 'nav-secundario-mobile') ?>>
                    <svg viewBox="0 0 24 24" fill="none" class="nav-icon"><circle cx="12" cy="13" r="8" stroke="currentColor" stroke-width="2"/><path d="M12 9 L12 13 L15 15 M9 3 H15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span class="nav__rotulo">Pomodoro</span>
                </a>
                <a href="provas.php" data-rotulo="Provas"<?= navAtivo('provas.php', $PAGINA, 'nav-secundario-mobile') ?>>
                    <!-- ícone de calendário com um dia marcado: o que a aba
                         faz é apontar um dia no futuro e contar até ele -->
                    <svg viewBox="0 0 24 24" fill="none" class="nav-icon"><rect x="3.5" y="5" width="17" height="15" rx="2" stroke="currentColor" stroke-width="2"/><path d="M3.5 10 H20.5 M8 3 V6 M16 3 V6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="15" r="1.6" fill="currentColor"/></svg>
                    <span class="nav__rotulo">Provas</span>
                </a>

                <!-- no desktop a conta vive no rodapé; aqui ela serve à barra do mobile (posição amarela) -->
                <a href="conta.php" data-rotulo="Conta"<?= navAtivo('conta.php', $PAGINA, 'nav-somente-mobile') ?>>
                    <svg viewBox="0 0 24 24" fill="none" class="nav-icon"><circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="2"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    <span class="nav__rotulo">Conta</span>
                </a>
            </nav>

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

                        <button class="conf__item conf__item--sair" type="button" role="menuitem">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 5 H18 a1 1 0 0 1 1 1 V18 a1 1 0 0 1 -1 1 H15 M10 8 L6 12 L10 16 M6 12 H15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Sair da conta
                        </button>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Bottom Sheet com as outras ferramentas organizadas (Mobile) -->
        <div class="sheet-overlay" id="sheetMaisMobile" aria-hidden="true">
            <div class="sheet-painel" role="dialog" aria-labelledby="sheetMaisTitulo">
                <div class="sheet-puxador"></div>
                <div class="sheet-topo">
                    <h3 class="sheet-titulo" id="sheetMaisTitulo">Outras Ferramentas</h3>
                    <button type="button" class="sheet-fechar" id="btnFecharSheetMais" aria-label="Fechar">✕</button>
                </div>
                <div class="sheet-grid">
                    <a href="pomodoro.php" class="sheet-card<?= $PAGINA === 'pomodoro.php' ? ' ativo' : '' ?>">
                        <span class="sheet-card__ico">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="13" r="8"/><path d="M12 9 L12 13 L15 15 M9 3 H15"/></svg>
                        </span>
                        <div class="sheet-card__info">
                            <strong>Pomodoro</strong>
                            <span>Ciclos de foco e pausas</span>
                        </div>
                    </a>
                    <a href="flashcards.php" class="sheet-card<?= $PAGINA === 'flashcards.php' ? ' ativo' : '' ?>">
                        <span class="sheet-card__ico">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="6" width="13" height="12" rx="2"/><path d="M8 4 H19 a2 2 0 0 1 2 2 V16"/></svg>
                        </span>
                        <div class="sheet-card__info">
                            <strong>Flashcards</strong>
                            <span>Repetição espaçada</span>
                        </div>
                    </a>
                    <a href="exercicios.php" class="sheet-card<?= $PAGINA === 'exercicios.php' ? ' ativo' : '' ?>">
                        <span class="sheet-card__ico">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6 H20 M4 12 H20 M4 18 H14"/></svg>
                        </span>
                        <div class="sheet-card__info">
                            <strong>Exercícios</strong>
                            <span>Simulados com IA</span>
                        </div>
                    </a>
                    <a href="provas.php" class="sheet-card<?= $PAGINA === 'provas.php' ? ' ativo' : '' ?>">
                        <span class="sheet-card__ico">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 10 H20.5 M8 3 V6 M16 3 V6"/><circle cx="12" cy="15" r="1.6" fill="currentColor"/></svg>
                        </span>
                        <div class="sheet-card__info">
                            <strong>Provas</strong>
                            <span>Contagem de dias</span>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <script src="./js/apple.js" defer></script>

        <?php if ($PAGINA !== 'orion.php'): ?>
            <?php include __DIR__ . '/orion-chat.php'; ?>
            <link rel="stylesheet" href="../shared/mascote.css">
            <script src="../shared/mascote.js"></script>
            <script src="./js/orion-chat.js" defer></script>
        <?php endif; ?>
