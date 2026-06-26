<?php
// ============================================================
//  KOSMOS — Helpers de sessão (autenticação)
//  Arquivo: backend/php/sessao.php
//  Centraliza tudo que envolve "quem está logado".
// ============================================================

/**
 * Inicia a sessão do PHP (apenas uma vez, com segurança).
 * Chame isto antes de ler ou escrever qualquer coisa em $_SESSION.
 */
function iniciarSessao(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Devolve o usuário logado como array ['id' => ..., 'nome' => ...]
 * ou null se ninguém estiver logado nesta sessão.
 */
function usuarioLogado(): ?array {
    iniciarSessao();

    if (empty($_SESSION['usuario_id'])) {
        return null;
    }

    return [
        'id'   => $_SESSION['usuario_id'],
        'nome' => $_SESSION['usuario_nome'] ?? '',
    ];
}

/**
 * Registra o acesso de hoje e atualiza a "sequência de dias".
 * Regra: mesmo dia = mantém; dia seguinte = +1; pulou dia(s) = reinicia em 1.
 * Devolve a sequência atual (em dias). Idempotente no mesmo dia.
 */
function registrarAcesso(PDO $pdo, int $usuarioId): int {
    $stmt = $pdo->prepare('SELECT ultimo_acesso, sequencia FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->execute([$usuarioId]);
    $u = $stmt->fetch();
    if (!$u) {
        return 0;
    }

    $hoje = new DateTime('today');
    $seq  = (int) ($u['sequencia'] ?? 0);

    if (empty($u['ultimo_acesso'])) {
        $seq = 1;                         // primeiro acesso registrado
    } else {
        $ultimo  = new DateTime($u['ultimo_acesso']);
        $difDias = (int) $ultimo->diff($hoje)->format('%r%a'); // com sinal

        if ($difDias === 0) {
            $seq = max($seq, 1);          // já acessou hoje: mantém
        } elseif ($difDias === 1) {
            $seq = $seq + 1;              // acessou ontem: soma 1
        } else {
            $seq = 1;                     // quebrou a sequência: reinicia
        }
    }

    $upd = $pdo->prepare('UPDATE usuarios SET ultimo_acesso = CURDATE(), sequencia = ? WHERE id = ?');
    $upd->execute([$seq, $usuarioId]);
    return $seq;
}

/**
 * "Porteiro" para endpoints protegidos: se não houver login,
 * responde 401 em JSON e encerra o script imediatamente.
 * Se houver login, devolve os dados do usuário.
 */
function exigirLogin(): array {
    $usuario = usuarioLogado();

    if ($usuario === null) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401); // 401 = "não autorizado / não logado"
        echo json_encode(['ok' => false, 'msg' => 'Você precisa estar logado.']);
        exit;
    }

    return $usuario;
}
