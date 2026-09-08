<?php
// ============================================================
//  KOSMOS — Lista os resumos do usuário
//  Arquivo: backend/php/resumos_listar.php
//  GET (protegido). Devolve:
//    { ok, total, resumos:[{id,titulo,materia,corpo,quando,dia,mes}] }
//  O corpo vem junto para a página abrir o resumo sem outra volta
//  ao servidor (são textos curtos de estudo).
// ============================================================

require_once __DIR__ . '/resumos_comum.php';

$usuario = exigirLogin();

try {
    $pdo = conectar();
    liberarSessao();   // só leitura: solta o lock da sessão

    $stmt = $pdo->prepare('SELECT id, titulo, materia, corpo,
                                  DAY(criado_em)   AS dia,
                                  MONTH(criado_em) AS mes
                             FROM resumos
                            WHERE usuario_id = ?
                         ORDER BY atualizado_em DESC, id DESC');
    $stmt->execute([$usuario['id']]);

    $resumos = [];
    foreach ($stmt as $r) {
        $resumos[] = [
            'id'      => (int) $r['id'],
            'titulo'  => $r['titulo'],
            'materia' => $r['materia'],
            'corpo'   => $r['corpo'],
            'quando'  => apiDataCurta((int) $r['dia'], (int) $r['mes']),
        ];
    }

    apiResponder(['ok' => true, 'total' => count($resumos), 'resumos' => $resumos]);
} catch (PDOException $e) {
    apiErro('Não foi possível carregar seus resumos.', 500);
}
