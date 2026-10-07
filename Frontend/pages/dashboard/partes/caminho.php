<?php
if (!isset($USUARIO)) {
    http_response_code(403);
    exit('Esta página não é acessada direto.');
}

$itens = $CAMINHO ?? [];
if ($itens === []) {
    return;
}
?>
<nav class="caminho" aria-label="Você está em">
    <ol>
        <?php
        $total = count($itens);
        for ($i = 0; $i < $total; $i++) {
            [$rotulo, $href] = $itens[$i];
            $ultimo = $i === $total - 1;
            $escRotulo = hesc($rotulo);
            echo '<li class="caminho__item">';
            if ($ultimo || $href === null) {
                echo '<span aria-current="page">' . $escRotulo . '</span>';
            } else {
                echo '<a href="' . hesc($href) . '">' . $escRotulo . '</a>';
            }
            echo '</li>';
        }
        ?>
    </ol>
</nav>