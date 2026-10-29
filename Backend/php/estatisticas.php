<?php
// ============================================================
//  KOSMOS — Os números do topo de uma aba, recalculados
//  Arquivo: Backend/php/estatisticas.php
//  GET (protegido): ?de=biblioteca | exercicios
//  Devolve { ok, stats } — os mesmos números que a página pinta ao
//  carregar (estatisticas_util.php). A tela pede de novo depois de
//  criar, apagar ou mover algo sem recarregar.
// ============================================================

require_once __DIR__ . '/resumos_comum.php';
require_once __DIR__ . '/estatisticas_util.php';

$usuario = exigirLogin();

try {
    $pdo = conectar();
    liberarSessao();

    $de = (string) ($_GET['de'] ?? '');
    if ($de === 'biblioteca') {
        $stats = estatisticasBiblioteca($pdo, (int) $usuario['id']);
    } elseif ($de === 'exercicios') {
        $stats = estatisticasExercicios($pdo, (int) $usuario['id']);
    } else {
        apiErro('Aba desconhecida.', 422);
    }

    apiResponder(['ok' => true, 'stats' => $stats]);
} catch (PDOException $e) {
    registrarErro($e);
    apiErro('Não foi possível calcular os números agora.', 500);
}
