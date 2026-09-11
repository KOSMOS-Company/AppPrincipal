<?php
// ============================================================
//  KOSMOS — Move um resumo para um caderno (ou para fora dele)
//  Arquivo: backend/php/resumos_mover.php
//  POST (protegido): resumo, caderno
//    caderno = id  -> guarda o resumo naquele caderno
//    caderno vazio -> tira do caderno ("Sem caderno")
//
//  Existe separado do resumos_salvar.php de propósito: arrastar um
//  cartão não deve precisar reenviar título, texto e imagens só para
//  trocar a gaveta. Assim o arrastar-e-soltar manda dois números e
//  pronto — e não há risco de uma corrida entre arrastar e editar
//  sobrescrever o texto do resumo com o que estava na tela.
//
//  A matéria acompanha: ela é do caderno (ver resumos_salvar.php).
// ============================================================

require_once __DIR__ . '/resumos_comum.php';

$usuario = exigirLogin();
apiExigirPost();

try {
    $pdo = conectar();
    liberarSessao();

    $usuarioId = (int) $usuario['id'];

    $resumo = resumoDoUsuario($pdo, apiId('resumo'), $usuarioId);
    if ($resumo === null) {
        apiErro('Resumo não encontrado.', 404);
    }

    // null = tirar do caderno; um id só passa se for desta conta
    $caderno   = apiCadernoEscolhido($pdo, $usuarioId);
    $cadernoId = $caderno === null ? null : (int) $caderno['id'];

    // já estava lá: não mexe no banco (e não suja o atualizado_em)
    $de = $resumo['caderno_id'] === null ? null : (int) $resumo['caderno_id'];
    if ($de === $cadernoId) {
        apiResponder([
            'ok'      => true,
            'msg'     => 'O resumo já estava aqui.',
            'mudou'   => false,
            'resumo'  => ['id' => (int) $resumo['id'], 'caderno_id' => $cadernoId],
        ]);
    }

    /* Num caderno, a matéria é a dele. Fora de um caderno, o resumo
       fica com a última que tinha — é a única pista de matéria que
       sobra para o filtro da seção "Sem caderno". */
    if ($cadernoId === null) {
        $pdo->prepare('UPDATE resumos SET caderno_id = NULL WHERE id = ? AND usuario_id = ?')
            ->execute([$resumo['id'], $usuarioId]);
        $materia = $resumo['materia'];
    } else {
        $materia = $caderno['materia'];
        $pdo->prepare('UPDATE resumos SET caderno_id = ?, materia = ? WHERE id = ? AND usuario_id = ?')
            ->execute([$cadernoId, $materia, $resumo['id'], $usuarioId]);
    }

    apiResponder([
        'ok'     => true,
        'mudou'  => true,
        'msg'    => $cadernoId === null
            ? 'Resumo tirado do caderno.'
            : 'Resumo movido para ' . $caderno['nome'] . '.',
        'de'     => $de,
        'resumo' => [
            'id'         => (int) $resumo['id'],
            'titulo'     => $resumo['titulo'],
            'materia'    => $materia,
            'caderno_id' => $cadernoId,
            'fotos'      => contarImagensDoResumo($pdo, (int) $resumo['id']),
        ],
    ]);
} catch (PDOException $e) {
    apiErro('Não foi possível mover o resumo.', 500);
}
