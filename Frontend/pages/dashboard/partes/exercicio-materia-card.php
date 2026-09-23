<?php
// ============================================================
//  KOSMOS — Um cartão de matéria de exercícios (parte reaproveitada)
//  Espera $m (uma matéria de exercicios_util.php) e, opcional, $i
//  (a posição, só para escalonar a animação de entrada).
//
//  O MESMO desenho existe em js/exercicios.js (função cartaoExercicioMateria):
//  o PHP monta a primeira pintura, para a estante chegar pronta do
//  servidor, e o JS repinta quando algo muda sem recarregar. Mexeu
//  aqui, mexa lá — os dois estão marcados com este aviso.
// ============================================================
if (!isset($m)) {
    return;
}
$i = $i ?? 0;
?>
                <article class="exercicio-materia-card anim-in exercicio-materia-card--<?= hesc($m['cor']) ?>"
                         data-id="<?= (int) $m['id'] ?>"
                         data-materia="<?= hesc($m['materia']) ?>"
                         draggable="true"
                         style="animation-delay: <?= number_format($i * 0.04, 2, '.', '') ?>s">
                    <a class="exercicio-materia-card__link" href="exercicio_materia.php?id=<?= (int) $m['id'] ?>" draggable="false">
                        <span class="exercicio-materia-card__lombada" aria-hidden="true"></span>

                        <div class="exercicio-materia-card__body">
                            <div class="exercicio-materia-card__topo">
<?php if ($m['icone'] !== ''): ?>
                                <span class="exercicio-materia-card__icone" aria-hidden="true"><?= hesc($m['icone']) ?></span>
<?php endif; ?>
                                <span class="materia-tag"><?= hesc($m['materia']) ?></span>
                            </div>

                            <h3 class="exercicio-materia-card__nome"><?= hesc($m['nome']) ?></h3>

<?php if ($m['descricao'] !== ''): ?>
                            <p class="exercicio-materia-card__desc"><?= hesc($m['descricao']) ?></p>
<?php endif; ?>

                            <span class="exercicio-materia-card__abrir">Abrir →</span>
                        </div>
                    </a>

                    <button type="button" class="exercicio-materia-card__editar" data-editar-exercicio-materia="<?= (int) $m['id'] ?>"
                            title="Personalizar esta matéria"
                            aria-label="Personalizar a matéria <?= hesc($m['nome']) ?>">
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 16h3l8-8-3-3-8 8v3Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M12.5 4.5l3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                    </button>

                    <!-- aparece quando um exercício está sendo arrastado por cima -->
                    <span class="exercicio-materia-card__solte" aria-hidden="true">Solte para guardar aqui</span>
                </article>