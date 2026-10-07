<?php
// ============================================================
//  KOSMOS — Um ponto do panorama de matérias de exercícios
//  (parte reaproveitada)
//
//  Espera $m (uma matéria de exercicios_util.php) e, opcional, $i
//  (a posição, para o lugar na espiral e o escalonamento da chegada).
//
//  O panorama é retrato, não a navegação: o ponto não escreve o nome da
//  matéria no céu, mas é clicável — leva à mesma matéria da lista e
//  mostra o nome num balão no hover (data-nome, lido pelo CSS). O
//  nome continua morando na lista, que é onde se lê.
//
//  A posição e o tamanho saem das funções do exercicios.php
//  (posicaoEstrela, tamanhoEstrela) — o mesmo desenho existe em
//  js/exercicios.js (criarPontoMateria), onde o PHP monta a primeira
//  pintura e o JS repinta quando a prateleira muda.
//  Mexeu aqui, mexa lá — os dois estão marcados com este aviso.
// ============================================================
if (!isset($m)) {
    return;
}
$i = $i ?? 0;
$pos = posicaoEstrela($i, $TOTAL_MATERIAS ?? ($i + 1));
$qtd = (int) ($m['qtd'] ?? 0);
$tamanho = tamanhoEstrela($qtd);
?>
                <a class="ex-ponto<?= $qtd === 0 ? ' ex-ponto--vazia' : '' ?> ex-ponto--<?= hesc($m['cor']) ?>"
                   href="exercicio_materia.php?id=<?= (int) $m['id'] ?>"
                   data-id="<?= (int) $m['id'] ?>"
                   data-qtd="<?= $qtd ?>"
                   data-nome="<?= hesc($m['nome']) ?>"
                   data-x="<?= $pos[0] ?>"
                   data-y="<?= $pos[1] ?>"
                   data-tamanho="<?= $tamanho ?>"
                   aria-label="<?= hesc($m['nome'] . ' — ' . rotuloQtdExercicios($qtd)) ?>"
                   style="--x: <?= $pos[0] ?>%; --y: <?= $pos[1] ?>%; --tamanho: <?= $tamanho ?>px; animation-delay: <?= number_format($i * 0.04, 2, '.', '') ?>s"></a>
