<?php
// ============================================================
//  KOSMOS — O seletor de cor (parte reaproveitada)
//
//  Um só desenho para tudo que tem cor: cadernos, matérias de
//  exercícios e baralhos. Na ordem:
//    • sem cor (círculo vazio com um risco) -> tom neutro
//    • as 6 cores da paleta (.avatar-cor--*)
//
//  Espera:
//    $SC_ID      id do grupo (o JS de cada formulário procura por ele)
//    $SC_ROTULO  rótulo para leitor de tela ("Cor do caderno"...)
//    $SC_MARCADA o valor que começa marcado
//  Os botões são [data-cor] com role="radio": os JS dos formulários
//  leem e marcam por esse atributo.
//
//  (A escolha "auto" — cor pela matéria — continua valendo para o que
//  já foi salvo assim, mas não é mais oferecida aqui: ao editar, o
//  formulário marca a cor que ela dava.)
// ============================================================
$SC_MARCADA = $SC_MARCADA ?? 'roxo';
$scOpcoes = array_merge(
    [['nenhuma', 'cad-cor--nenhuma', 'Sem cor', 'Sem cor']],
    array_map(fn($c) => [$c, 'avatar-cor--' . $c, ucfirst($c), 'Cor ' . $c], CORES_CADERNO_KOSMOS)
);
?>
                        <div class="cad-cores" id="<?= hesc($SC_ID) ?>" role="radiogroup" aria-label="<?= hesc($SC_ROTULO) ?>">
<?php foreach ($scOpcoes as [$valor, $classe, $titulo, $rotulo]):
        $marcada = $valor === $SC_MARCADA; ?>
                            <button type="button" class="cad-cor <?= hesc($classe) ?><?= $marcada ? ' escolhido' : '' ?>"
                                    data-cor="<?= hesc($valor) ?>" role="radio"
                                    aria-checked="<?= $marcada ? 'true' : 'false' ?>"
                                    title="<?= hesc($titulo) ?>" aria-label="<?= hesc($rotulo) ?>"></button>
<?php endforeach; ?>
                        </div>
