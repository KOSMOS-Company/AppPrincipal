<?php
// ============================================================
//  KOSMOS — Grava o resultado de uma sessão de estudo
//  Arquivo: backend/php/flashcards_revisao.php
//  POST (protegido):
//    deck      -> id do deck estudado
//    respostas -> JSON: [{"id":12,"acertou":true}, ...]
//
//  Atualiza cada cartão respondido (contadores + último resultado)
//  e o próprio deck (nº de sessões + data da última). Tudo dentro
//  de uma transação: ou grava a sessão inteira, ou não grava nada.
//  As datas vêm de NOW() (relógio do MySQL), nunca do PHP.
// ============================================================

require_once __DIR__ . '/flashcards_comum.php';

$usuario = exigirLogin();
fcExigirPost();

/** Teto de segurança: uma sessão não tem centenas de cartões. */
const FC_MAX_RESPOSTAS = 500;

try {
    $pdo  = conectar();
    $deck = fcDeckDoUsuario($pdo, fcId('deck'), (int) $usuario['id']);

    if ($deck === null) {
        fcErro('Deck não encontrado.', 404);
    }

    $respostas = json_decode((string) ($_POST['respostas'] ?? ''), true);

    if (!is_array($respostas) || $respostas === []) {
        fcErro('Nenhuma resposta para registrar.');
    }
    if (count($respostas) > FC_MAX_RESPOSTAS) {
        fcErro('Sessão grande demais.');
    }

    // Só aceita ids de cartões QUE SÃO deste deck (e o deck já é do usuário).
    $stmt = $pdo->prepare('SELECT id FROM flashcard_cartoes WHERE deck_id = ?');
    $stmt->execute([$deck['id']]);
    $doDeck = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    $pdo->beginTransaction();

    $atualizar = $pdo->prepare(
        'UPDATE flashcard_cartoes
            SET revisoes         = revisoes + 1,
                acertos          = acertos + ?,
                erros            = erros + ?,
                ultimo_resultado = ?,
                ultima_revisao   = NOW()
          WHERE id = ? AND deck_id = ?'
    );

    $acertos = 0;
    $erros   = 0;

    foreach ($respostas as $resposta) {
        $id = filter_var($resposta['id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($id === false || !in_array((int) $id, $doDeck, true)) {
            continue; // id inválido ou de outro deck: ignora em silêncio
        }

        $acertou = !empty($resposta['acertou']);
        $acertou ? $acertos++ : $erros++;

        $atualizar->execute([
            $acertou ? 1 : 0,
            $acertou ? 0 : 1,
            $acertou ? 1 : 0,
            (int) $id,
            $deck['id'],
        ]);
    }

    if ($acertos + $erros === 0) {
        $pdo->rollBack();
        fcErro('Nenhuma resposta válida para registrar.');
    }

    $pdo->prepare(
        'UPDATE flashcard_decks
            SET revisoes = revisoes + 1, ultima_revisao = NOW()
          WHERE id = ? AND usuario_id = ?'
    )->execute([$deck['id'], $usuario['id']]);

    $pdo->commit();

    fcResponder([
        'ok'      => true,
        'acertos' => $acertos,
        'erros'   => $erros,
        'msg'     => 'Revisão registrada!',
    ]);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fcErro('Não foi possível registrar a revisão.', 500);
}
