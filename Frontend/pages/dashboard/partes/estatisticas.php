<?php
// ============================================================
//  KOSMOS — A faixa de números do topo de uma aba
//  O mesmo desenho da dos Flashcards (.ini-stats: números soltos,
//  sem caixa), usado na Biblioteca e em Exercícios.
//
//  Espera $ESTATS = [ [chave, valor, rótulo, faixa?], ... ] e $ESTATS_ID.
//  Valor 0/null aparece como "—", apagado: para quem acabou de chegar,
//  um traço convida; um "0%" desanima. A faixa ("boa", "media",
//  "baixa") pinta o número do acerto. Quem repinta depois de uma
//  mudança sem recarregar é o pintarEstatisticas() do js/util.js.
// ============================================================
if (!isset($ESTATS, $ESTATS_ID)) {
    return;
}
?>
            <div class="ini-stats pg-stats" id="<?= hesc($ESTATS_ID) ?>">
<?php foreach ($ESTATS as $i => $s):
    [$chave, $valor, $rotulo] = $s;
    $faixa  = $s[3] ?? '';
    $zerado = $valor === null || $valor === 0 || $valor === '0';
?>
<?php if ($i > 0): ?>
                <span class="ini-stat__div" aria-hidden="true"></span>
<?php endif; ?>
                <div class="ini-stat<?= $zerado ? ' ini-stat--vazio' : '' ?>"<?= $faixa !== '' ? ' data-faixa="' . hesc($faixa) . '"' : '' ?>>
                    <strong data-stat="<?= hesc($chave) ?>"><?= $zerado ? '—' : hesc((string) $valor) ?></strong>
                    <span><?= hesc($rotulo) ?></span>
                </div>
<?php endforeach; ?>
            </div>
