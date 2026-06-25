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
