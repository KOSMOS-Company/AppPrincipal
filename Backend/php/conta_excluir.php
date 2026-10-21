<?php
// ============================================================
//  KOSMOS — Excluir a conta (LGPD)
//  Arquivo: backend/php/conta_excluir.php
//  POST (protegido): apaga o usuário e, por causa das FKs com
//  ON DELETE CASCADE, também as preferências, os decks e os
//  cartões dele. Ação irreversível.
//
//  Confirmação exigida (decidida aqui, não pelo frontend):
//   - conta com senha  -> tem que enviar a senha correta;
//   - conta só Google  -> tem que enviar confirmacao = EXCLUIR.
// ============================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/api_util.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/avatar_util.php';
require_once __DIR__ . '/resumo_imagem_util.php';
require_once __DIR__ . '/senha_util.php';

$usuario = exigirLogin();
$id      = (int) $usuario['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiErro('Método não permitido.', 405);
}

// lerSenha() corta as pontas, igual ao login/cadastro/troca de senha:
// lá a senha foi gravada e conferida sem espaços, então aqui também
$senha        = lerSenha('senha');
$confirmacao  = strtoupper(trim((string) ($_POST['confirmacao'] ?? '')));

try {
    $pdo = conectar();

    $stmt = $pdo->prepare('SELECT senha_hash FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row) {
        apiErro('Usuário não encontrado.', 404);
    }

    $temSenha = !empty($row['senha_hash']);

    if ($temSenha) {
        if ($senha === '') {
            apiErro('Digite sua senha para confirmar.', 422);
        }
        if (!senhaConfere($senha, $row['senha_hash'])) {
            apiErro('Senha incorreta.', 422);
        }
    } elseif ($confirmacao !== 'EXCLUIR') {
        apiErro('Digite EXCLUIR para confirmar.', 422);
    }

    // A foto e as imagens dos resumos estão no disco, fora do banco:
    // o CASCADE apaga as linhas, mas nunca os arquivos
    apagarAvatarDoUsuario($pdo, $id);
    apagarImagensDoUsuario($pdo, $id);
    apagarCapasDoUsuario($pdo, $id);

    // Apaga o usuário; o CASCADE leva preferências, decks e cartões
    $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);

    encerrarSessao();

    echo json_encode([
        'ok'  => true,
        'msg' => 'Sua conta e seus dados foram apagados.',
    ]);
} catch (PDOException $e) {
    registrarErro($e);
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Não foi possível excluir a conta.']);
}
