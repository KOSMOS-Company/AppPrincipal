<?php
// ============================================================
//  KOSMOS — Um cartão de caderno (parte reaproveitada)
//  Espera $c (um caderno de cadernos_util.php) e, opcional, $i
//  (a posição, só para escalonar a animação de entrada).
//
//  O MESMO desenho existe em js/resumos.js (função cartaoCaderno):
//  o PHP monta a primeira pintura, para a estante chegar pronta do
//  servidor, e o JS repinta quando algo muda sem recarregar. Mexeu
//  aqui, mexa lá — os dois estão marcados com este aviso.
// ============================================================
if (!isset($c)) {
    return;
}
$i = $i ?? 0;

/* A dica que a lombada mostra no hover. Numa tira de 54px não cabe
   a matéria nem a contagem, então o texto inteiro vai num atributo e
   o CSS o desenha (ver .caderno-card::after, em css/resumos.css).
   O mesmo texto é montado em js/resumos.js — os dois desenhos
   precisam dizer a mesma coisa. */
$dica = $c['nome'] . ' · ' . $c['materia']
      . ' · ' . (int) $c['resumos'] . ((int) $c['resumos'] === 1 ? ' resumo' : ' resumos');
if ($c['fotos'] > 0) {
    $dica .= ' · ' . (int) $c['fotos'] . ((int) $c['fotos'] === 1 ? ' imagem' : ' imagens');
}
?>
                <article class="caderno-card anim-in caderno-card--<?= hesc($c['cor']) ?>"
                         data-id="<?= (int) $c['id'] ?>"
                         data-materia="<?= hesc($c['materia']) ?>"
                         data-nome="<?= hesc($c['nome']) ?>"
                         data-resumos="<?= (int) $c['resumos'] ?>"
                         data-criado="<?= isset($c['criado_em']) ? hesc($c['criado_em']) : '' ?>"
                         data-dica="<?= hesc($dica) ?>"
                         draggable="true"
                         style="animation-delay: <?= number_format($i * 0.04, 2, '.', '') ?>s">
                    <a class="caderno-card__link" href="caderno.php?id=<?= (int) $c['id'] ?>" draggable="false">
                        <span class="caderno-card__lombada" aria-hidden="true"></span>

<?php if ($c['capa']): ?>
                        <span class="caderno-card__capa" aria-hidden="true"
                              style="background-image:url('<?= hesc($c['capa']) ?>')"></span>
<?php endif; ?>

                        <div class="caderno-card__body">
                            <div class="caderno-card__topo">
<?php if ($c['icone'] !== ''): ?>
                                <span class="caderno-card__icone" aria-hidden="true"><?= hesc($c['icone']) ?></span>
<?php endif; ?>
                                <span class="materia-tag"><?= hesc($c['materia']) ?></span>
                            </div>

                            <h3 class="caderno-card__nome"><?= hesc($c['nome']) ?></h3>

<?php if ($c['descricao'] !== ''): ?>
                            <p class="caderno-card__desc"><?= hesc($c['descricao']) ?></p>
<?php endif; ?>

                            <span class="caderno-card__qtd">
                                <strong><?= (int) $c['resumos'] ?></strong>
                                <?= $c['resumos'] === 1 ? 'resumo' : 'resumos' ?><?php
                                if ($c['fotos'] > 0): ?> · <strong><?= (int) $c['fotos'] ?></strong>
                                <?= $c['fotos'] === 1 ? 'imagem' : 'imagens' ?><?php endif; ?>
                            </span>
                            <span class="caderno-card__abrir">Abrir →</span>
                        </div>
                    </a>

                    <div class="caderno-card__acoes">
                        <button type="button" class="caderno-card__editar" data-editar-caderno="<?= (int) $c['id'] ?>"
                                title="Personalizar este caderno"
                                aria-label="Personalizar o caderno <?= hesc($c['nome']) ?>">
                            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 16h3l8-8-3-3-8 8v3Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M12.5 4.5l3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                        </button>

                        <button type="button" class="caderno-card__editar caderno-card__apagar" data-apagar-caderno="<?= (int) $c['id'] ?>"
                                title="Excluir este caderno"
                                aria-label="Excluir o caderno <?= hesc($c['nome']) ?>">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 7h14M10 7V5h4v2M7 7l1 12h8l1-12M11 11v5M13 11v5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                    </div>

                    <!-- aparece quando um resumo está sendo arrastado por cima -->
                    <span class="caderno-card__solte" aria-hidden="true">Solte para guardar aqui</span>
                </article>
