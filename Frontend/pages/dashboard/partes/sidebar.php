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

/** Marca o link da página atual (o JS só posiciona o marcador). */
function navAtivo(string $arquivo, string $atual): string {
    return $arquivo === $atual ? ' class="active"' : '';
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
        <aside class="contLateral">
            <a class="contLogo" href="index.php">
                <span class="logo__text klogo" role="img" aria-label="Kosmos">K<i class="klogo__o"></i>smos</span>
            </a>

            <nav class="botoesL" aria-label="Navegação principal">
                <!-- marcador que desliza entre os itens (posicionado pelo dashboard.js) -->
                <span class="nav__marca" aria-hidden="true"></span>

                <a href="index.php"<?= navAtivo('index.php', $PAGINA) ?>>
                    <svg viewBox="0 0 24 24" fill="none" class="nav-icon"><path d="M4 10 L12 4 L20 10 L20 20 L4 20 Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Início
                </a>

                <span class="nav__grupo">Estudar</span>
                <a href="resumos.php"<?= navAtivo('resumos.php', $PAGINA) ?>>
                    <svg viewBox="0 0 24 24" fill="none" class="nav-icon"><rect x="4" y="4" width="16" height="16" rx="2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M8 10 H16 M8 14 H12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    Resumos
                </a>
                <a href="flashcards.php"<?= navAtivo('flashcards.php', $PAGINA) ?>>
                    <svg viewBox="0 0 24 24" fill="none" class="nav-icon"><rect x="3" y="6" width="13" height="12" rx="2" stroke="currentColor" stroke-width="2"/><path d="M8 4 H19 a2 2 0 0 1 2 2 V16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    Flashcards
                </a>
                <a href="exercicios.php"<?= navAtivo('exercicios.php', $PAGINA) ?>>
                    <svg viewBox="0 0 24 24" fill="none" class="nav-icon"><path d="M4 6 H20 M4 12 H20 M4 18 H14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    Exercícios
                </a>

                <span class="nav__grupo">Foco</span>
                <a href="pomodoro.php"<?= navAtivo('pomodoro.php', $PAGINA) ?>>
                    <svg viewBox="0 0 24 24" fill="none" class="nav-icon"><circle cx="12" cy="13" r="8" stroke="currentColor" stroke-width="2"/><path d="M12 9 L12 13 L15 15 M9 3 H15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Pomodoro
                </a>

                <!-- no desktop a conta vive no rodapé; aqui ela serve à barra do mobile -->
                <a href="conta.php"<?= navAtivo('conta.php', $PAGINA) ?>>
                    <svg viewBox="0 0 24 24" fill="none" class="nav-icon"><circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="2"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    Conta
                </a>
            </nav>

            <div class="contUsuario">
                <a class="usuario<?= $PAGINA === 'conta.php' ? ' ativa' : '' ?>" href="conta.php">
                    <span class="<?= $avatarClasses ?>" style="<?= $avatarEstilo ?>" aria-hidden="true"><?= hesc($USUARIO['inicial']) ?></span>
                    <span class="usuario__info">
                        <strong><?= hesc($USUARIO['nome']) ?></strong>
                        <span>Ver conta</span>
                    </span>
                </a>
                <button class="usuario__sair" type="button"
                        title="Sair da conta" aria-label="Sair da conta">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 5 H18 a1 1 0 0 1 1 1 V18 a1 1 0 0 1 -1 1 H15 M10 8 L6 12 L10 16 M6 12 H15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
            </div>
        </aside>
