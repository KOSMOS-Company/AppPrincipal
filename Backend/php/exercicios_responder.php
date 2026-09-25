<?php
// ============================================================
//  KOSMOS — Registra a resposta de uma questão de exercício
//  Arquivo: backend/php/exercicios_responder.php
//  POST (protegido):
//    exercicio -> id do exercício salvo
//    questao   -> índice da questão (0, 1, 2...)
//    escolhida -> índice da alternativa marcada (0 = A)
//
//  O quiz continua corrigindo no navegador — é o que dá a resposta
//  na hora, sem esperar a rede. Mas para valer XP a correção tem de
//  ser do SERVIDOR: o cliente poderia dizer "acertei" para tudo.
//  Aqui o gabarito sai do JSON guardado em `exercicios.conteudo`.
//
//  Cada questão rende XP uma vez por DIA (UNIQUE em
//  exercicio_respostas). Refazer a lista amanhã vale; clicar de novo
//  hoje, não. Acerto vale 20, erro vale 5 (o esforço conta).
// ============================================================

require_once __DIR__ . '/resumos_comum.php';
require_once __DIR__ . '/ProgressoService.php';

$usuario = exigirLogin();
apiExigirPost();

$exercicioId = apiId('exercicio');
$questao     = filter_var($_POST['questao'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 200]]);
$escolhida   = filter_var($_POST['escolhida'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 9]]);

if ($exercicioId === 0 || $questao === false || $escolhida === false) {
    apiErro('Resposta inválida.', 422);
}

try {
    $pdo = conectar();
    liberarSessao();

    // O exercício tem de ser desta conta
    $stmt = $pdo->prepare('SELECT conteudo FROM exercicios WHERE id = ? AND usuario_id = ? LIMIT 1');
    $stmt->execute([$exercicioId, $usuario['id']]);
    $conteudo = $stmt->fetchColumn();
    if ($conteudo === false) {
        apiErro('Exercício não encontrado.', 404);
    }

    $json     = json_decode((string) $conteudo, true);
    $questoes = is_array($json['questoes'] ?? null) ? array_values($json['questoes']) : [];
    $q        = $questoes[$questao] ?? null;
    if (!is_array($q) || !isset($q['correta'])) {
        apiErro('Questão não encontrada.', 404);
    }
    $alts = is_array($q['alts'] ?? null) ? $q['alts'] : [];
    if ($escolhida >= count($alts)) {
        apiErro('Alternativa inválida.', 422);
    }

    $correta = (int) $q['correta'];
    $acertou = $escolhida === $correta;

    // INSERT IGNORE: a segunda resposta à mesma questão no mesmo dia não
    // entra (rowCount 0) — e por isso não rende XP de novo.
    $ins = $pdo->prepare(
        'INSERT IGNORE INTO exercicio_respostas (usuario_id, exercicio_id, questao, escolhida, acertou, dia)
         VALUES (?, ?, ?, ?, ?, CURDATE())'
    );
    $ins->execute([$usuario['id'], $exercicioId, $questao, $escolhida, $acertou ? 1 : 0]);
    $primeira = $ins->rowCount() === 1;

    $progresso = null;
    if ($primeira) {
        $acao = $acertou ? 'exercicio_acerto' : 'exercicio_erro';
        $progresso = progressoRegistrar($pdo, (int) $usuario['id'], [[
            'acao'     => $acao,
            'xp'       => ProgressoService::XP[$acao],
            'detalhes' => ['exercicio' => $exercicioId, 'questao' => $questao],
        ]]);
    }

    apiResponder([
        'ok'        => true,
        'acertou'   => $acertou,
        'correta'   => $correta,
        'primeira'  => $primeira,
        'progresso' => $progresso,
    ]);
} catch (PDOException $e) {
    apiErro('Não foi possível registrar a resposta.', 500);
}
