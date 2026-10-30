<?php
// ============================================================
//  KOSMOS — Marca o início de um ciclo de foco
//  Arquivo: Backend/php/pomodoro_inicio.php
//  POST (protegido): aparelho -> id deste aparelho (32 hex, ver
//  js/conta-inicial.js)
//
//  Chamado pelo js/pomodoro.js no Iniciar de um ciclo de FOCO novo
//  (retomar uma pausa não chama). Guarda o instante no relógio do
//  MySQL; o pomodoro_sessao.php só aceita o ciclo quando a duração
//  dele já passou desde aqui. Um Iniciar novo NO MESMO APARELHO
//  substitui o anterior (o ciclo abandonado pelo Reiniciar não conta);
//  em outro aparelho, cada um tem o seu.
// ============================================================

require_once __DIR__ . '/estudo_comum.php';

$usuario = exigirLogin();
estExigirPost();

$aparelho = (string) ($_POST['aparelho'] ?? '');
if (!preg_match('/^[0-9a-f]{32}$/', $aparelho)) {
    estErro('Aparelho inválido.', 422);
}

try {
    $pdo = conectar();
    liberarSessao();

    // Inícios esquecidos (aba fechada no meio do ciclo) não ficam para sempre
    $pdo->prepare('DELETE FROM pomodoro_inicios WHERE usuario_id = ? AND inicio_em < NOW() - INTERVAL 1 DAY')
        ->execute([$usuario['id']]);

    $pdo->prepare(
        'INSERT INTO pomodoro_inicios (usuario_id, aparelho, inicio_em) VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE inicio_em = NOW()'
    )->execute([$usuario['id'], $aparelho]);

    estResponder([]);
} catch (PDOException $e) {
    registrarErro($e);
    estErro('Não foi possível marcar o início do ciclo.', 500);
}
