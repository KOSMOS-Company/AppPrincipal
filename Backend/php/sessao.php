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
    // IMPORTANTE: toda a lógica usa o MESMO relógio — o do MySQL, via CURDATE().
    // Antes, o código comparava com o "hoje" do PHP (new DateTime('today')) mas
    // gravava com CURDATE() do MySQL. Quando os fusos do PHP e do MySQL diferiam
    // (ex.: PHP em Europe/Berlin e MySQL em America/Sao_Paulo), a diferença dava
    // sempre "1 dia" e a sequência somava +1 a CADA acesso/recarga da página.
    //
    // Agora é um único UPDATE atômico, contando dias de calendário reais:
    //   mesmo dia            -> mantém (idempotente: recarregar não muda nada)
    //   dia seguinte         -> +1
    //   pulou dia / 1º acesso-> reinicia em 1
    $sql = 'UPDATE usuarios
               SET sequencia = CASE
                       WHEN ultimo_acesso = CURDATE()                  THEN GREATEST(sequencia, 1)
                       WHEN ultimo_acesso = CURDATE() - INTERVAL 1 DAY THEN sequencia + 1
                       ELSE 1
                   END,
                   ultimo_acesso = CURDATE()
             WHERE id = ?';
    $pdo->prepare($sql)->execute([$usuarioId]);

    $stmt = $pdo->prepare('SELECT sequencia FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->execute([$usuarioId]);
    $row = $stmt->fetch();
    return $row ? (int) $row['sequencia'] : 0;
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
