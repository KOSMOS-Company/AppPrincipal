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
require_once __DIR__ . '/flashcards_srs.php';   // fcProximaRevisao()
require_once __DIR__ . '/ProgressoService.php';

$usuario = exigirLogin();
fcExigirPost();

/** Teto de segurança: uma sessão não tem centenas de cartões. */
const FC_MAX_RESPOSTAS = 500;

/** Sessão que vale o bônus: pelo menos isto de cartões (ou o deck todo, se for menor). */
const FC_MIN_CARTOES_SESSAO_XP = 5;

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

    /* Só aceita ids de cartões QUE SÃO deste deck (e o deck já é do
       usuário). Traz junto o agendamento atual: a próxima revisão é
       calculada a partir do intervalo e da facilidade que o cartão já
       tem, então precisamos deles aqui — uma consulta para o deck
       inteiro, não uma por cartão respondido. */
    /* `revisado_hoje` é para o XP: um cartão rende uma vez por dia.
       Revisar o mesmo deck dez vezes seguidas é estudo, mas não pode
       ser fábrica de XP. Sai do relógio do MySQL, como as outras datas. */
    $stmt = $pdo->prepare(
        'SELECT id, intervalo, facilidade,
                (ultima_revisao IS NOT NULL AND ultima_revisao >= CURDATE()) AS revisado_hoje
           FROM flashcard_cartoes WHERE deck_id = ?'
    );
    $stmt->execute([$deck['id']]);

    $doDeck = [];
    foreach ($stmt as $c) {
        $doDeck[(int) $c['id']] = [
            'intervalo'     => (int) $c['intervalo'],
            'facilidade'    => (float) $c['facilidade'],
            'revisado_hoje' => (bool) $c['revisado_hoje'],
        ];
    }

    $pdo->beginTransaction();

    /* A data da próxima revisão sai de CURDATE() + INTERVAL, no
       MySQL. Calcular em PHP daria o dia errado na virada: os dois
       rodam em fusos diferentes neste projeto. */
    $atualizar = $pdo->prepare(
        'UPDATE flashcard_cartoes
            SET revisoes         = revisoes + 1,
                acertos          = acertos + ?,
                erros            = erros + ?,
                ultimo_resultado = ?,
                ultima_revisao   = NOW(),
                intervalo        = ?,
                facilidade       = ?,
                proxima_revisao  = CURDATE() + INTERVAL ? DAY
          WHERE id = ? AND deck_id = ?'
    );

    $acertos = 0;
    $erros   = 0;
    $acertosComXp = 0;   // acertos em cartões que ainda não renderam XP hoje
    $vistos  = [];       // o mesmo id duas vezes na lista conta uma só

    foreach ($respostas as $resposta) {
        $id = filter_var($resposta['id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($id === false || !isset($doDeck[(int) $id]) || isset($vistos[(int) $id])) {
            continue; // id inválido, de outro deck ou repetido: ignora em silêncio
        }
        $vistos[(int) $id] = true;

        $acertou = !empty($resposta['acertou']);
        $acertou ? $acertos++ : $erros++;
        if ($acertou && !$doDeck[(int) $id]['revisado_hoje']) {
            $acertosComXp++;
        }

        $antes = $doDeck[(int) $id];
        $agora = fcProximaRevisao($antes['intervalo'], $antes['facilidade'], $acertou);

        $atualizar->execute([
            $acertou ? 1 : 0,
            $acertou ? 0 : 1,
            $acertou ? 1 : 0,
            $agora['intervalo'],
            $agora['facilidade'],
            $agora['intervalo'],
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

    // XP, depois do commit: a revisão já está salva aconteça o que acontecer aqui
    $itens = [];
    if ($acertosComXp > 0) {
        $itens[] = [
            'acao'     => 'flashcard_acerto',
            'xp'       => $acertosComXp * ProgressoService::XP['flashcard_acerto'],
            'rotulo'   => $acertosComXp === 1 ? '1 cartão certo' : "{$acertosComXp} cartões certos",
            'detalhes' => ['deck' => (int) $deck['id'], 'cartoes' => $acertosComXp],
        ];
    }
    if ($acertos + $erros >= min(FC_MIN_CARTOES_SESSAO_XP, count($doDeck))) {
        $itens[] = [
            'acao'     => 'sessao_flashcards',
            'xp'       => ProgressoService::XP['sessao_flashcards'],
            'detalhes' => ['deck' => (int) $deck['id'], 'respostas' => $acertos + $erros],
        ];
    }

    fcResponder([
        'ok'        => true,
        'acertos'   => $acertos,
        'erros'     => $erros,
        'msg'       => 'Revisão registrada!',
        'progresso' => progressoRegistrar($pdo, (int) $usuario['id'], $itens),
    ]);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fcErro('Não foi possível registrar a revisão.', 500);
}
