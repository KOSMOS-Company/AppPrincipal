<?php
// ============================================================
//  KOSMOS — Dados de exemplo para o teste de navegador
//  Arquivo: Backend/testes/navegador/semear.php
//
//  Chamado pelo navegador.js (não rode à mão). Recebe o e-mail da
//  conta de teste que ele acabou de criar e:
//    semear <email>         pula a intro/tour e cria um caderno, resumos,
//                           baralho, cartões, provas e uma matéria —
//                           com HTML nos textos (para pegar falta de
//                           escape) e um título sem espaços (para pegar
//                           página mais larga que o celular);
//    token <email>          põe um token de confirmação conhecido na
//                           conta (o e-mail de verdade não sai no teste).
//  Responde em JSON na saída padrão.
// ============================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../php/conexao.php';
require_once __DIR__ . '/../../php/email_util.php';

[$_, $acao, $email] = $argv + [null, '', ''];
$pdo = conectar();

$stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
$stmt->execute([$email]);
$id = (int) $stmt->fetchColumn();
if ($id === 0) {
    fwrite(STDERR, "Conta $email não existe.\n");
    exit(1);
}

if ($acao === 'token') {
    $token = bin2hex(random_bytes(32));
    $pdo->prepare('UPDATE usuarios SET confirma_token = ?, confirma_expira = NOW() + INTERVAL 1 HOUR WHERE id = ?')
        ->execute([hash('sha256', $token), $id]);
    echo json_encode(['token' => $token]);
    exit;
}

// Texto com HTML: se alguma tela não escapar, aparece um <b class="xssprobe">
$p = '<b class=xssprobe>X</b>"\'';

$pdo->prepare('INSERT INTO usuario_preferencias (usuario_id, onboarding_completo, tutorial_visto)
               VALUES (?, 1, 2) ON DUPLICATE KEY UPDATE onboarding_completo = 1, tutorial_visto = 2')
    ->execute([$id]);

$pdo->prepare('INSERT INTO resumo_cadernos (usuario_id, nome, materia, cor, icone, descricao) VALUES (?, ?, ?, ?, ?, ?)')
    ->execute([$id, "Caderno $p", 'Biologia', 'verde', '🧬', "Descrição $p"]);
$caderno = (int) $pdo->lastInsertId();

$ins = $pdo->prepare('INSERT INTO resumos (usuario_id, caderno_id, titulo, materia, corpo) VALUES (?, ?, ?, ?, ?)');
$ins->execute([$id, $caderno, "Resumo $p", 'Biologia', "# Título\n\nTexto ==destacado== $p <script>x</script>"]);
$resumo = (int) $pdo->lastInsertId();
$ins->execute([$id, null, 'Título com https://www.exemplo.com.br/um/link/colado/muito/comprido/sem/espacos', 'História', 'curto']);
$resumoLongo = (int) $pdo->lastInsertId();

$pdo->prepare('INSERT INTO flashcard_decks (usuario_id, nome, materia, cor) VALUES (?, ?, ?, ?)')
    ->execute([$id, "Baralho $p", 'Química', 'azul']);
$deck = (int) $pdo->lastInsertId();
$card = $pdo->prepare('INSERT INTO flashcard_cartoes (deck_id, frente, verso, ordem, proxima_revisao) VALUES (?, ?, ?, ?, CURDATE())');
$card->execute([$deck, "Frente $p", "Verso $p", 1]);
$card->execute([$deck, 'Fórmula dos alcenos', 'CnH2n', 2]);

$prova = $pdo->prepare('INSERT INTO provas (usuario_id, titulo, materia, data, anotacoes) VALUES (?, ?, ?, CURDATE() + INTERVAL ? DAY, ?)');
$prova->execute([$id, "Prova $p", 'Química', 3, "Anotação $p"]);
$prova->execute([$id, 'Prova passada', 'História', -5, null]);

$pdo->prepare('INSERT INTO exercicio_materias (usuario_id, nome, materia, cor, descricao) VALUES (?, ?, ?, ?, ?)')
    ->execute([$id, "Matéria $p", 'Matemática', 'roxo', "Descrição $p"]);
$materia = (int) $pdo->lastInsertId();

echo json_encode([
    'id'           => $id,
    'caderno'      => $caderno,
    'resumo'       => $resumo,
    'resumo_longo' => $resumoLongo,
    'deck'         => $deck,
    'materia'      => $materia,
    'confirmacao'  => confirmacaoLigada(),
]);
