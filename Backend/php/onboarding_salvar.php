<?php
// ============================================================
//  KOSMOS — Salva a pesquisa de onboarding do estudante
//  Arquivo: Backend/php/onboarding_salvar.php
//  POST (protegido): grava objetivo, matérias prioritárias,
//                    meta diária e marca onboarding_completo = 1.
// ============================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/materias.php';

$usuario = exigirLogin();
$id      = (int) $usuario['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método não permitido.']);
    exit;
}

// Suporta tanto JSON no body quanto formulário tradicional
$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    $body = $_POST;
}

$pular = !empty($body['pular']);

try {
    $pdo = conectar();
    liberarSessao();

    // Garante que a linha de preferências exista
    $stmt = $pdo->prepare('SELECT usuario_id FROM usuario_preferencias WHERE usuario_id = ? LIMIT 1');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        $pdo->prepare('INSERT INTO usuario_preferencias (usuario_id) VALUES (?)')->execute([$id]);
    }

    if ($pular) {
        // Se o usuário optou por pular, apenas marca como completo
        $stmt = $pdo->prepare('UPDATE usuario_preferencias SET onboarding_completo = 1 WHERE usuario_id = ?');
        $stmt->execute([$id]);
        echo json_encode([
            'ok'  => true,
            'msg' => 'Onboarding ignorado por enquanto.',
        ]);
        exit;
    }

    // 1. Objetivo
    $objetivosValidos = [
        'vestibular_enem' => 'ENEM & Vestibulares',
        'concursos'       => 'Concursos Públicos',
        'ensino_medio'    => 'Reforço no Ensino Médio',
        'faculdade'       => 'Faculdade / Ensino Superior',
        'habito'          => 'Construir Hábito de Estudo',
    ];
    $objetivoRaw = trim((string) ($body['objetivo'] ?? ''));
    $objetivo = array_key_exists($objetivoRaw, $objetivosValidos) ? $objetivoRaw : 'vestibular_enem';

    // 2. Matérias prioritárias (validação contra MATERIAS_KOSMOS)
    $materiasEnviadas = $body['materias'] ?? [];
    if (!is_array($materiasEnviadas)) {
        $materiasEnviadas = is_string($materiasEnviadas) ? explode(',', $materiasEnviadas) : [];
    }
    $materiasValidadas = [];
    foreach ($materiasEnviadas as $m) {
        $m = trim((string) $m);
        if (in_array($m, MATERIAS_KOSMOS, true)) {
            $materiasValidadas[] = $m;
        }
    }
    $materiasStr = !empty($materiasValidadas) ? implode(',', $materiasValidadas) : null;

    // 3. Meta diária (minutos)
    $metaRaw = $body['meta_diaria'] ?? 60;
    $meta = max(15, min(480, (int) $metaRaw));

    // Salva tudo no banco e marca onboarding como concluído
    $stmt = $pdo->prepare('UPDATE usuario_preferencias 
                           SET objetivo = ?, materias = ?, meta_diaria = ?, onboarding_completo = 1 
                           WHERE usuario_id = ?');
    $stmt->execute([$objetivo, $materiasStr, $meta, $id]);

    echo json_encode([
        'ok'   => true,
        'msg'  => 'Perfil de estudos configurado com sucesso!',
        'dados' => [
            'objetivo'    => $objetivo,
            'materias'    => $materiasValidadas,
            'meta_diaria' => $meta,
        ],
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'ok'  => false,
        'msg' => 'Erro ao salvar preferências no banco.',
    ]);
}
