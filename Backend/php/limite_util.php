<?php
// ============================================================
//  KOSMOS — Limite de tentativas (login e "esqueci a senha")
//  Arquivo: Backend/php/limite_util.php
//
//  Conta duas coisas separadas, na mesma janela de tempo:
//   - por e-mail: barra quem testa senhas contra UMA conta;
//   - por IP: barra quem testa vários e-mails da mesma máquina.
//  Os números ficam em LIMITES, num lugar só.
//
//  Tabela: tentativas_acesso (Backend/sql/2026-10-14_tentativas_acesso.sql)
// ============================================================

const LIMITES = [
    //          janela (min)     máx. por e-mail  máx. por IP
    'login' => ['minutos' => 15, 'email' => 5,    'ip' => 20],
    'reset' => ['minutos' => 60, 'email' => 3,    'ip' => 10],
];

/** IP de quem fez a requisição (REMOTE_ADDR: o X-Forwarded-For é forjável). */
function ipCliente(): string {
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

/**
 * Já passou do limite? Chame ANTES de conferir a senha / gerar o token.
 * Se a tabela ainda não existir (migração não rodada), deixa passar:
 * melhor sem limite do que derrubar o login de todo mundo.
 */
function limiteExcedido(PDO $pdo, string $tipo, string $email): bool {
    $cfg = LIMITES[$tipo];
    $ip  = ipCliente();
    try {
        $stmt = $pdo->prepare(
            'SELECT SUM(email = ?) AS por_email,
                    SUM(ip = ?)    AS por_ip
               FROM tentativas_acesso
              WHERE tipo = ?
                AND (email = ? OR ip = ?)
                AND criado_em > NOW() - INTERVAL ? MINUTE'
        );
        $stmt->execute([$email, $ip, $tipo, $email, $ip, $cfg['minutos']]);
        $row = $stmt->fetch();
    } catch (PDOException $e) {
        error_log('[KOSMOS limite] ' . $e->getMessage());
        return false;
    }

    return (int) ($row['por_email'] ?? 0) >= $cfg['email']
        || (int) ($row['por_ip'] ?? 0) >= $cfg['ip'];
}

/** Guarda uma tentativa (login errado, ou um pedido de reset). */
function registrarTentativa(PDO $pdo, string $tipo, string $email): void {
    try {
        $pdo->prepare('INSERT INTO tentativas_acesso (tipo, email, ip) VALUES (?, ?, ?)')
            ->execute([$tipo, mb_substr($email, 0, 180), ipCliente()]);
        // Faxina: nada aqui interessa depois de um dia
        $pdo->exec('DELETE FROM tentativas_acesso WHERE criado_em < NOW() - INTERVAL 1 DAY');
    } catch (PDOException $e) {
        error_log('[KOSMOS limite] ' . $e->getMessage());
    }
}

/** Login certo: zera as falhas daquele e-mail (as do IP continuam contando). */
function limparTentativas(PDO $pdo, string $tipo, string $email): void {
    try {
        $pdo->prepare('DELETE FROM tentativas_acesso WHERE tipo = ? AND email = ?')
            ->execute([$tipo, $email]);
    } catch (PDOException $e) {
        error_log('[KOSMOS limite] ' . $e->getMessage());
    }
}
