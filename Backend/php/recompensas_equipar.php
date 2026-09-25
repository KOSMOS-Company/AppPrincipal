<?php
// ============================================================
//  KOSMOS — Equipar uma recompensa da trilha
//  Arquivo: backend/php/recompensas_equipar.php
//  POST (protegido):
//    tipo -> moldura | cor | emblema
//    slug -> a recompensa (vazio = tirar a moldura / o emblema)
//
//  Quem decide se pode é o servidor: a recompensa tem de existir e o
//  nível da pessoa tem de ter chegado ao dela (ProgressoService::
//  equipar). Mandar o slug de uma moldura do nível 50 pelo console
//  não equipa nada.
// ============================================================

require_once __DIR__ . '/resumos_comum.php';
require_once __DIR__ . '/ProgressoService.php';

$usuario = exigirLogin();
apiExigirPost();

$tipo = trim((string) ($_POST['tipo'] ?? ''));
$slug = trim((string) ($_POST['slug'] ?? ''));

try {
    $pdo = conectar();
    liberarSessao();

    $equipado = (new ProgressoService($pdo))->equipar((int) $usuario['id'], $tipo, $slug);

    apiResponder([
        'ok'       => true,
        'msg'      => $slug === '' ? 'Removido do avatar.' : 'Equipado!',
        'equipado' => $equipado,
    ]);
} catch (DomainException $e) {
    apiErro($e->getMessage(), 422);
} catch (PDOException $e) {
    apiErro('Não foi possível equipar agora.', 500);
}
