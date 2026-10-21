<?php
// ============================================================
//  KOSMOS — Disparo dos e-mails de notificação
//  Arquivo: Backend/tarefas/notificacoes.php
//
//  As preferências "Lembrete diário" e "Resumo semanal" (Conta ›
//  Notificações) já eram salvas, mas nada as lia. Este script é a
//  parte que faltava do lado do PHP: escolhe quem deve receber e
//  manda os dados para o n8n, que monta e envia o e-mail (o mesmo
//  esquema da recuperação de senha).
//
//  Uso (da raiz do AppPrincipal), só pela linha de comando:
//      C:\xampp\php\php.exe Backend/tarefas/notificacoes.php lembrete
//      C:\xampp\php\php.exe Backend/tarefas/notificacoes.php resumo
//  Sem opção nenhuma ele SÓ SIMULA: lista quem receberia e o quê.
//  Para enviar de verdade, acrescente --enviar.
//
//  Agendamento sugerido (ver docs/PRODUCAO.md):
//      lembrete -> todo dia às 19h
//      resumo   -> domingo às 18h
//
//  Precisa, no config.php: N8N_NOTIF_URL (webhook do fluxo de
//  notificações) e APP_URL (para o link do e-mail).
//
//  Regras:
//    lembrete -> quem ligou o lembrete E ainda não estudou hoje
//                (nenhum XP ganho hoje: pomodoro, cartão, questão...)
//    resumo   -> quem ligou o resumo; manda os números da semana
//  Datas pelo relógio do MySQL, como no resto do projeto.
// ============================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../php/conexao.php';
require_once __DIR__ . '/../php/config.php';

$tipo   = $argv[1] ?? '';
$enviar = in_array('--enviar', $argv, true);

if (!in_array($tipo, ['lembrete', 'resumo'], true)) {
    fwrite(STDERR, "Uso: php Backend/tarefas/notificacoes.php lembrete|resumo [--enviar]\n");
    exit(2);
}
if ($enviar && (!defined('N8N_NOTIF_URL') || N8N_NOTIF_URL === '' || !defined('APP_URL') || APP_URL === '')) {
    fwrite(STDERR, "Para enviar, defina N8N_NOTIF_URL e APP_URL no config.php (ver config.example.php).\n");
    exit(2);
}

$pdo = conectar();

if ($tipo === 'lembrete') {
    $destinatarios = $pdo->query(
        'SELECT u.id, u.nome, u.email, u.sequencia
           FROM usuarios u
           JOIN usuario_preferencias p ON p.usuario_id = u.id
          WHERE p.notif_lembrete = 1
            AND NOT EXISTS (SELECT 1 FROM historico_xp h
                             WHERE h.usuario_id = u.id
                               AND h.criado_em >= CURDATE())'
    )->fetchAll();
} else {
    $destinatarios = $pdo->query(
        'SELECT u.id, u.nome, u.email, u.sequencia,
                (SELECT COALESCE(SUM(s.minutos), 0) FROM pomodoro_sessoes s
                  WHERE s.usuario_id = u.id AND s.dia > CURDATE() - INTERVAL 7 DAY) AS minutos_semana,
                (SELECT COALESCE(SUM(h.quantidade_xp), 0) FROM historico_xp h
                  WHERE h.usuario_id = u.id AND h.criado_em >= CURDATE() - INTERVAL 6 DAY) AS xp_semana,
                (SELECT COUNT(*) FROM flashcard_cartoes c JOIN flashcard_decks d ON d.id = c.deck_id
                  WHERE d.usuario_id = u.id
                    AND c.proxima_revisao IS NOT NULL
                    AND c.proxima_revisao < CURDATE() + INTERVAL 7 DAY) AS revisar_proxima_semana
           FROM usuarios u
           JOIN usuario_preferencias p ON p.usuario_id = u.id
          WHERE p.notif_resumo = 1'
    )->fetchAll();
}

$link = defined('APP_URL') && APP_URL !== '' ? rtrim(APP_URL, '/') . '/dashboard/index.php' : '';
$ok = 0; $falhas = 0;

foreach ($destinatarios as $d) {
    $dados = ['tipo' => $tipo, 'email' => $d['email'], 'nome' => $d['nome'],
              'sequencia' => (int) $d['sequencia'], 'link' => $link];
    if ($tipo === 'resumo') {
        $dados += [
            'minutos_semana'         => (int) $d['minutos_semana'],
            'xp_semana'              => (int) $d['xp_semana'],
            'revisar_proxima_semana' => (int) $d['revisar_proxima_semana'],
        ];
    }

    if (!$enviar) {
        echo '  [simulação] ', json_encode($dados, JSON_UNESCAPED_UNICODE), "\n";
        continue;
    }

    $ch = curl_init(N8N_NOTIF_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($dados, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', N8N_TOKEN_HEADER . ': ' . N8N_TOKEN_VALOR],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false || $code >= 400) {
        $falhas++;
        error_log("[KOSMOS notificações] falhou para o usuário {$d['id']} (HTTP $code)");
    } else {
        $ok++;
    }
}

$total = count($destinatarios);
echo $enviar
    ? "$tipo: $ok enviados, $falhas falharam (de $total).\n"
    : "$tipo: $total destinatário(s). Nada foi enviado — use --enviar.\n";
exit($falhas > 0 ? 1 : 0);
