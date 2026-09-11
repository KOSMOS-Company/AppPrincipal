<?php
// ============================================================
//  KOSMOS — Um cartão de resumo (parte reaproveitada)
//  Espera $r (um resumo) e, opcional, $i (posição, só para a
//  animação) e $mostrarMateria (false dentro de um caderno, onde
//  a matéria é a mesma para todos e repetir só polui).
//
//  O MESMO desenho existe em js/resumos.js e js/caderno.js (função
//  cartaoResumo): o PHP monta a primeira pintura e o JS repinta
//  quando algo muda. Mexeu aqui, mexa lá.
// ============================================================
if (!isset($r)) {
    return;
}
$i = $i ?? 0;
$mostrarMateria = $mostrarMateria ?? true;
?>
                <article class="resumo-card anim-in" data-id="<?= (int) $r['id'] ?>"
                         data-materia="<?= hesc($r['materia']) ?>"
                         data-caderno="<?= (int) ($r['caderno_id'] ?? 0) ?>"
                         draggable="true"
                         style="animation-delay: <?= number_format($i * 0.04, 2, '.', '') ?>s">
                    <!-- o cartão leva para a leitura; editar e mover são passos à parte -->
                    <a class="resumo-card__link" href="resumo.php?id=<?= (int) $r['id'] ?>" draggable="false">
                        <div class="resumo-card__thumb">
<?php if ($r['fotos'] > 0): ?>
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.6"/><circle cx="8.5" cy="10" r="1.6" stroke="currentColor" stroke-width="1.4"/><path d="M4 17l5-4 3 2.5 3-2.5 5 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <span class="resumo-card__fotos"><?= (int) $r['fotos'] ?></span>
<?php else: ?>
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M9 8h6M9 12h6M9 16h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
<?php endif; ?>
                        </div>
                        <div class="resumo-card__body">
<?php if ($mostrarMateria): ?>
                            <span class="materia-tag"><?= hesc($r['materia']) ?></span>
<?php endif; ?>
                            <h3 class="resumo-card__title"><?= hesc($r['titulo']) ?></h3>
                            <div class="resumo-card__meta">
                                <span><?= hesc($r['quando']) ?></span>
                                <span>Ler →</span>
                            </div>
                        </div>
                    </a>

                    <div class="resumo-card__acoes">
                        <!-- o menu é o caminho de quem não arrasta: celular,
                             teclado, leitor de tela -->
                        <button type="button" class="resumo-card__botao" data-menu="<?= (int) $r['id'] ?>"
                                title="Mover, editar ou apagar"
                                aria-haspopup="menu" aria-expanded="false"
                                aria-label="Ações do resumo <?= hesc($r['titulo']) ?>">
                            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><circle cx="10" cy="4" r="1.6"/><circle cx="10" cy="10" r="1.6"/><circle cx="10" cy="16" r="1.6"/></svg>
                        </button>
                        <button type="button" class="resumo-card__botao" data-editar="<?= (int) $r['id'] ?>"
                                title="Editar este resumo"
                                aria-label="Editar o resumo <?= hesc($r['titulo']) ?>">
                            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 16h3l8-8-3-3-8 8v3Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M12.5 4.5l3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                        </button>
                    </div>
                </article>
