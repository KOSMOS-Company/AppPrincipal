<?php
// ============================================================
//  KOSMOS — Uma LINHA da lista de matérias de exercícios
//  (parte reaproveitada)
//
//  Espera $m (uma matéria de exercicios_util.php).
//
//  Uma matéria é uma LINHA, não um cartão — o mesmo desenho da lista
//  de Resumos: divisor fino embaixo, sem moldura, fundo só no hover,
//  e a linha inteira abrindo a matéria. O círculo da esquerda é bem
//  maior que os pontos do panorama de cima, e acende conforme a
//  quantidade de exercícios (intensidadeQtd).
//
//  Os data-* não são enfeite: é com eles que o js/exercicios.js
//  reordena a lista no navegador, sem pedir nada ao servidor. O
//  desenho tem gêmea lá (criarLinhaMateria) — mexeu aqui, mexa lá.
// ============================================================
if (!isset($m)) {
    return;
}
$qtd = (int) ($m['qtd'] ?? 0);
?>
                <article class="ex-linha<?= $qtd === 0 ? ' ex-linha--vazia' : '' ?> ex-linha--<?= hesc($m['cor']) ?>"
                         data-id="<?= (int) $m['id'] ?>"
                         data-materia="<?= hesc($m['materia']) ?>"
                         data-qtd="<?= $qtd ?>"
                         data-nome="<?= hesc($m['nome']) ?>"
                         data-criado="<?= hesc($m['criado_em'] ?? '') ?>"
                         style="--intensidade: <?= hesc(intensidadeQtd($qtd)) ?>">
                    <a class="ex-linha__link" href="exercicio_materia.php?id=<?= (int) $m['id'] ?>" draggable="false"
<?php if ($m['descricao'] !== ''): ?>
                       title="<?= hesc($m['nome'] . ' — ' . $m['descricao']) ?>"
<?php endif; ?>
                    >
                        <span class="ex-linha__ponto" aria-hidden="true"></span>
                        <h3 class="ex-linha__nome"><?= hesc($m['nome']) ?></h3>
                        <span class="ex-linha__mat"><?= hesc($m['materia']) ?></span>
                        <span class="ex-linha__conta"><?= hesc(rotuloQtdExercicios($qtd)) ?></span>
                    </a>

                    <button type="button" class="ex-linha__editar" data-editar-exercicio-materia="<?= (int) $m['id'] ?>"
                            title="Personalizar esta matéria"
                            aria-label="Personalizar a matéria <?= hesc($m['nome']) ?>">
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 16h3l8-8-3-3-8 8v3Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M12.5 4.5l3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                    </button>

                    <button type="button" class="ex-linha__editar ex-linha__apagar" data-apagar-exercicio-materia="<?= (int) $m['id'] ?>"
                            title="Excluir esta matéria"
                            aria-label="Excluir a matéria <?= hesc($m['nome']) ?>">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 7h14M10 7V5h4v2M7 7l1 12h8l1-12M11 11v5M13 11v5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                </article>
