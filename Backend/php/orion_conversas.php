<?php
// ============================================================
//  KOSMOS — Conversas salvas do Orion
//  Arquivo: Backend/php/orion_conversas.php
//
//  GET         -> a lista: { conversas: [{id, titulo, atualizada_em}] },
//                 a mais recente primeiro (as últimas ORION_MAX_LISTA)
//  GET ?id=ID  -> uma conversa inteira:
//                 { conversa: {id, titulo}, mensagens: [{autor, texto, humor}] }
//  POST        -> acao=excluir : id
//
//  Quem grava as mensagens é o orion_chat.php (depois que a IA
//  responde); aqui só se lê e apaga. Quem usa: js/orion-ia.js.
//
//  Toda consulta leva o usuario_id no WHERE: trocar o id na
//  requisição não abre nem apaga a conversa de outra pessoa (404).
// ============================================================

require_once __DIR__ . '/estudo_comum.php';

/** Quantas conversas a lista mostra. As mais antigas continuam salvas. */
const ORION_MAX_LISTA = 100;

$usuario = exigirLogin();
$id      = (int) $usuario['id'];
liberarSessao();

try {
    $pdo = conectar();

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        if (isset($_GET['id'])) {
            responderConversa($pdo, $id, $_GET['id']);
        }

        $q = $pdo->prepare('SELECT id, titulo, atualizada_em
                              FROM orion_conversas
                             WHERE usuario_id = ?
                          ORDER BY atualizada_em DESC, id DESC
                             LIMIT ' . ORION_MAX_LISTA);
        $q->execute([$id]);
        $conversas = array_map(fn ($c) => [
            'id'            => (int) $c['id'],
            'titulo'        => $c['titulo'],
            'atualizada_em' => $c['atualizada_em'],
        ], $q->fetchAll());

        // "hoje" pelo relógio do MySQL (regra de datas, ver README): a
        // tela agrupa em Hoje / Ontem / antes comparando com ele.
        estResponder(['conversas' => $conversas, 'hoje' => $pdo->query('SELECT CURDATE()')->fetchColumn()]);
    }

    $corpo = estCorpo();
    $acao  = (string) ($corpo['acao'] ?? '');

    if ($acao === 'excluir') {
        // As mensagens vão junto, por ON DELETE CASCADE
        $q = $pdo->prepare('DELETE FROM orion_conversas WHERE id = ? AND usuario_id = ?');
        $q->execute([idConversa($corpo['id'] ?? 0), $id]);
        if ($q->rowCount() === 0) {
            estErro('Conversa não encontrada.', 404);
        }
        estResponder([]);
    }

    estErro('Ação desconhecida.', 400);
} catch (PDOException $e) {
    registrarErro($e, 'Orion: conversas');
    estErro('Não foi possível carregar as conversas agora.', 500);
}

function idConversa($bruto): int {
    $n = filter_var($bruto, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($n === false) {
        estErro('Conversa inválida.', 422);
    }
    return $n;
}

function responderConversa(PDO $pdo, int $usuarioId, $bruto): void {
    $conversaId = idConversa($bruto);

    $q = $pdo->prepare('SELECT id, titulo FROM orion_conversas WHERE id = ? AND usuario_id = ?');
    $q->execute([$conversaId, $usuarioId]);
    $conversa = $q->fetch();
    if (!$conversa) {
        estErro('Conversa não encontrada.', 404);
    }

    $q = $pdo->prepare('SELECT autor, texto, humor FROM orion_mensagens WHERE conversa_id = ? ORDER BY id');
    $q->execute([$conversaId]);
    $mensagens = array_map(fn ($m) => array_filter([
        'autor' => $m['autor'],
        'texto' => $m['texto'],
        'humor' => $m['humor'],
    ], fn ($v) => $v !== null), $q->fetchAll());

    estResponder([
        'conversa'  => ['id' => (int) $conversa['id'], 'titulo' => $conversa['titulo']],
        'mensagens' => $mensagens,
    ]);
}
