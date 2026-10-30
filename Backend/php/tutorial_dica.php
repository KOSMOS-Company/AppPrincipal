<?php
// ============================================================
//  KOSMOS — Guarda as dicas do Orion que a conta já viu
//  Arquivo: Backend/php/tutorial_dica.php
//  POST (protegido): dicas -> ids separados por vírgula (ex.: "provas,editor")
//
//  Chamado pelo js/tutorial.js quando uma dica aparece, e uma vez pelo
//  js/conta-inicial.js para subir as que este aparelho já tinha visto
//  antes (quando elas moravam só no localStorage). Só ACRESCENTA: a
//  lista guardada nunca encolhe. O pagina_dashboard.php devolve a lista
//  em toda aba, então a dica vista no celular não reaparece no PC.
// ============================================================

require_once __DIR__ . '/estudo_comum.php';

$usuario = exigirLogin();
estExigirPost();

// ids das DICAS do tutorial.js: letras minúsculas e hífen
$novas = array_filter(
    explode(',', (string) ($_POST['dicas'] ?? '')),
    fn($d) => preg_match('/^[a-z][a-z-]{0,23}$/', $d) === 1
);
if ($novas === []) {
    estErro('Nenhuma dica válida.', 422);
}

try {
    $pdo = conectar();
    liberarSessao();

    $pdo->beginTransaction();
    $pdo->prepare('INSERT IGNORE INTO usuario_preferencias (usuario_id) VALUES (?)')->execute([$usuario['id']]);
    $stmt = $pdo->prepare('SELECT dicas_vistas FROM usuario_preferencias WHERE usuario_id = ? FOR UPDATE');
    $stmt->execute([$usuario['id']]);
    $atual = (string) $stmt->fetchColumn();

    $lista = array_values(array_unique(array_merge($atual === '' ? [] : explode(',', $atual), $novas)));
    $texto = implode(',', $lista);
    if (strlen($texto) > 255) {
        $pdo->rollBack();
        estErro('Lista de dicas cheia.', 422);   // só com ids inventados: as dicas reais somam bem menos
    }

    $pdo->prepare('UPDATE usuario_preferencias SET dicas_vistas = ? WHERE usuario_id = ?')
        ->execute([$texto, $usuario['id']]);
    $pdo->commit();

    estResponder(['dicas' => $lista]);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    registrarErro($e);
    estErro('Não foi possível salvar a dica.', 500);
}
