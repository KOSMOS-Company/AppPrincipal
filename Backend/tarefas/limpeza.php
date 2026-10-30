<?php
// ============================================================
//  KOSMOS — Faxina diária do banco
//  Arquivo: Backend/tarefas/limpeza.php
//
//  Tira o que venceu e ninguém vai mais usar:
//    - contas que nunca confirmaram o e-mail, criadas há mais de
//      7 dias (o link vale 48 h; depois disso é cadastro abandonado
//      ou alguém que usou o e-mail de outra pessoa);
//    - "Lembrar de mim" vencidos (sessoes_lembradas);
//    - inícios de Pomodoro esquecidos (aba fechada no meio do ciclo).
//
//  Uso (da raiz do AppPrincipal), só pela linha de comando:
//      C:\xampp\php\php.exe Backend/tarefas/limpeza.php            (só mostra)
//      C:\xampp\php\php.exe Backend/tarefas/limpeza.php --apagar   (apaga)
//  Agendado pelo Backend/tarefas/agendar-windows.ps1 (ou pelo cron,
//  ver docs/PRODUCAO.md): todo dia às 3h.
// ============================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../php/conexao.php';

$apagar = in_array('--apagar', $argv, true);
$pdo    = conectar();

$faxinas = [
    'contas sem e-mail confirmado (mais de 7 dias)' =>
        'FROM usuarios WHERE email_confirmado = 0 AND criado_em < NOW() - INTERVAL 7 DAY',
    '"Lembrar de mim" vencidos' =>
        'FROM sessoes_lembradas WHERE expira_em < NOW()',
    'inícios de Pomodoro esquecidos' =>
        'FROM pomodoro_inicios WHERE inicio_em < NOW() - INTERVAL 1 DAY',
];

foreach ($faxinas as $rotulo => $onde) {
    try {
        $n = (int) $pdo->query('SELECT COUNT(*) ' . $onde)->fetchColumn();
        if ($apagar && $n > 0) {
            $pdo->exec('DELETE ' . $onde);   // usuários: o ON DELETE CASCADE leva o resto junto
        }
        echo str_pad((string) $n, 5, ' ', STR_PAD_LEFT), '  ', $rotulo, $apagar ? '' : '  [simulação]', "\n";
    } catch (PDOException $e) {
        // tabela/coluna nova sem a migração: avisa e segue com as outras
        fwrite(STDERR, "  pulei \"$rotulo\": " . $e->getMessage() . "\n");
    }
}

if (!$apagar) {
    echo "Nada foi apagado. Acrescente --apagar para apagar de verdade.\n";
}
