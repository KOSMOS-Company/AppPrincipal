<?php
// ============================================================
//  KOSMOS — Lista os cadernos e os resumos do usuário
//  Arquivo: backend/php/resumos_listar.php
//  GET (protegido). Sem parâmetros devolve tudo:
//    { ok, total, cadernos:[{id,nome,materia,resumos,fotos}],
//      resumos:[{id,titulo,materia,corpo,caderno_id,fotos,quando}] }
//
//  Com ?caderno=ID devolve só os resumos daquele caderno (e o
//  caderno em si); com ?caderno=0, só os resumos soltos.
//
//  O corpo vem junto para a página abrir o resumo sem outra volta
//  ao servidor (são textos curtos de estudo). As imagens vêm só
//  como contagem — a galeria é carregada na página do resumo.
// ============================================================

require_once __DIR__ . '/resumos_comum.php';

$usuario = exigirLogin();

try {
    $pdo = conectar();
    liberarSessao();

    $usuarioId = (int) $usuario['id'];

    /* Qual recorte? "caderno" ausente = tudo; 0 = só os soltos;
       um id = só aquele caderno (checando que é desta conta). */
    $filtrar = isset($_GET['caderno']);
    $pedido  = $filtrar ? apiId('caderno') : -1;
    $caderno = null;

    if ($pedido > 0) {
        $caderno = cadernoDoUsuario($pdo, $pedido, $usuarioId);
        if ($caderno === null) {
            apiErro('Caderno não encontrado.', 404);
        }
        $caderno = [
            'id'      => (int) $caderno['id'],
            'nome'    => $caderno['nome'],
            'materia' => $caderno['materia'],
        ];
    }

    // ---------- Resumos ----------
    $where  = 'r.usuario_id = ?';
    $params = [$usuarioId];

    if ($pedido === 0) {
        $where .= ' AND r.caderno_id IS NULL';
    } elseif ($pedido > 0) {
        $where .= ' AND r.caderno_id = ?';
        $params[] = $pedido;
    }

    $stmt = $pdo->prepare("SELECT r.id, r.titulo, r.materia, r.corpo, r.caderno_id,
                                  DAY(r.criado_em)   AS dia,
                                  MONTH(r.criado_em) AS mes,
                                  (SELECT COUNT(*) FROM resumo_imagens i
                                    WHERE i.resumo_id = r.id) AS fotos
                             FROM resumos r
                            WHERE $where
                         ORDER BY r.atualizado_em DESC, r.id DESC");
    $stmt->execute($params);

    $resumos = [];
    foreach ($stmt as $r) {
        $resumos[] = [
            'id'         => (int) $r['id'],
            'titulo'     => $r['titulo'],
            'materia'    => $r['materia'],
            'corpo'      => $r['corpo'],
            'caderno_id' => $r['caderno_id'] === null ? null : (int) $r['caderno_id'],
            'fotos'      => (int) $r['fotos'],
            'quando'     => apiDataCurta((int) $r['dia'], (int) $r['mes']),
        ];
    }

    $resposta = ['ok' => true, 'total' => count($resumos), 'resumos' => $resumos];

    if ($caderno !== null) {
        $resposta['caderno'] = $caderno;
    }

    // ---------- Cadernos (só na listagem geral) ----------
    if (!$filtrar) {
        $stmt = $pdo->prepare('SELECT c.id, c.nome, c.materia,
                                      COUNT(r.id) AS resumos,
                                      COALESCE(SUM((SELECT COUNT(*) FROM resumo_imagens i
                                                     WHERE i.resumo_id = r.id)), 0) AS fotos
                                 FROM resumo_cadernos c
                            LEFT JOIN resumos r ON r.caderno_id = c.id
                                WHERE c.usuario_id = ?
                             GROUP BY c.id
                             ORDER BY c.criado_em DESC, c.id DESC');
        $stmt->execute([$usuarioId]);

        $cadernos = [];
        foreach ($stmt as $c) {
            $cadernos[] = [
                'id'      => (int) $c['id'],
                'nome'    => $c['nome'],
                'materia' => $c['materia'],
                'resumos' => (int) $c['resumos'],
                'fotos'   => (int) $c['fotos'],
            ];
        }

        $resposta['cadernos'] = $cadernos;
    }

    apiResponder($resposta);
} catch (PDOException $e) {
    apiErro('Não foi possível carregar seus resumos.', 500);
}
