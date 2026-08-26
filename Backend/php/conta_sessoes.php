<?php
// ============================================================
//  KOSMOS — Sair de todos os dispositivos
//  Arquivo: backend/php/conta_sessoes.php
//  POST (protegido): incrementa usuarios.sessoes_versao. Toda
//  sessão aberta com a versão antiga passa a ser inválida; a
//  sessão que pediu isso continua valendo (atualizamos a dela).
//  A checagem acontece em usuario_atual.php / conta_dados.php,
//  então as outras sessões caem no próximo acesso.
// ============================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/sessao.php';

$usuario = exigirLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método não permitido.']);
    exit;
}

try {
    $pdo = conectar();
    $id  = (int) $usuario['id'];

    $pdo->prepare('UPDATE usuarios SET sessoes_versao = sessoes_versao + 1 WHERE id = ?')
        ->execute([$id]);

    // A sessão atual acompanha a nova versão e continua válida
    marcarVersaoSessao($pdo, $id);

    echo json_encode([
        'ok'  => true,
        'msg' => 'Os outros dispositivos foram desconectados.',
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Não foi possível encerrar as outras sessões.']);
}
