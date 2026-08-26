<?php
// ============================================================
//  KOSMOS — Preferências da conta
//  Arquivo: backend/php/conta_preferencias.php
//  GET  (protegido): devolve as preferências do usuário.
//                    Se ainda não existirem, cria com os padrões.
//  POST (protegido): salva as preferências enviadas.
//  Tabela: usuario_preferencias (1 linha por usuário, FK CASCADE).
// ============================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/avatar_util.php';

$usuario = exigirLogin();
$id      = (int) $usuario['id'];

// Valores padrão (também usados quando a linha ainda não existe)
const PREF_PADRAO = [
    'avatar_cor'       => 'roxo',
    'avatar_pos_x'     => 50,
    'avatar_pos_y'     => 50,
    'pomo_foco'        => 25,
    'pomo_pausa'       => 5,
    'pomo_pausa_longa' => 15,
    'meta_diaria'      => 60,
    'materias'         => '',
    'notif_lembrete'   => 0,
    'notif_resumo'     => 0,
];

// Listas fechadas: nada que venha do navegador entra sem passar por aqui
const CORES_AVATAR = ['roxo', 'azul', 'verde', 'laranja', 'rosa', 'ciano'];
const MATERIAS_OK  = ['Matemática', 'Física', 'Química', 'Biologia', 'História', 'Português',
                      'Geografia', 'Filosofia', 'Sociologia', 'Inglês', 'Redação'];

/** Mantém um número dentro de um intervalo, com fallback se vier lixo. */
function faixa($valor, int $min, int $max, int $padrao): int {
    if ($valor === null || $valor === '' || !is_numeric($valor)) {
        return $padrao;
    }
    $n = (int) $valor;
    return max($min, min($max, $n));
}

try {
    $pdo = conectar();
    liberarSessao();   // este endpoint não escreve na sessão

    // ---------- Salvar ----------
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Base = o que já está salvo (ou os padrões). Assim um POST que
        // manda só parte dos campos — por exemplo apenas as notificações —
        // não zera o resto.
        $stmt = $pdo->prepare('SELECT avatar_cor, avatar_pos_x, avatar_pos_y, pomo_foco,
                                      pomo_pausa, pomo_pausa_longa, meta_diaria, materias,
                                      notif_lembrete, notif_resumo
                               FROM usuario_preferencias WHERE usuario_id = ? LIMIT 1');
        $stmt->execute([$id]);
        $base = $stmt->fetch() ?: PREF_PADRAO;

        $cor = trim($_POST['avatar_cor'] ?? '');
        if (!in_array($cor, CORES_AVATAR, true)) {
            $cor = $base['avatar_cor'];
        }

        // enquadramento da foto: porcentagem do background-position
        $posX = faixa($_POST['avatar_pos_x'] ?? null, 0, 100, (int) $base['avatar_pos_x']);
        $posY = faixa($_POST['avatar_pos_y'] ?? null, 0, 100, (int) $base['avatar_pos_y']);

        $foco       = faixa($_POST['pomo_foco']        ?? null, 5,  90,  (int) $base['pomo_foco']);
        $pausa      = faixa($_POST['pomo_pausa']       ?? null, 1,  30,  (int) $base['pomo_pausa']);
        $pausaLonga = faixa($_POST['pomo_pausa_longa'] ?? null, 5,  60,  (int) $base['pomo_pausa_longa']);
        $meta       = faixa($_POST['meta_diaria']      ?? null, 10, 600, (int) $base['meta_diaria']);

        // matérias chegam como "Física,Matemática"; só as conhecidas passam.
        // Campo ausente = mantém o que já estava; campo vazio = limpa.
        if (!isset($_POST['materias'])) {
            $materiasTxt = (string) $base['materias'];
            $materias    = $materiasTxt === '' ? [] : explode(',', $materiasTxt);
        } else {
            $materias = [];
            foreach (explode(',', (string) $_POST['materias']) as $m) {
                $m = trim($m);
                if ($m !== '' && in_array($m, MATERIAS_OK, true) && !in_array($m, $materias, true)) {
                    $materias[] = $m;
                }
            }
            $materiasTxt = mb_substr(implode(',', $materias), 0, 255);
        }

        $lembrete = isset($_POST['notif_lembrete'])
            ? (!empty($_POST['notif_lembrete']) && $_POST['notif_lembrete'] !== '0' ? 1 : 0)
            : (int) $base['notif_lembrete'];
        $resumo = isset($_POST['notif_resumo'])
            ? (!empty($_POST['notif_resumo']) && $_POST['notif_resumo'] !== '0' ? 1 : 0)
            : (int) $base['notif_resumo'];

        $sql = 'INSERT INTO usuario_preferencias
                    (usuario_id, avatar_cor, avatar_pos_x, avatar_pos_y, pomo_foco,
                     pomo_pausa, pomo_pausa_longa, meta_diaria, materias,
                     notif_lembrete, notif_resumo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    avatar_cor = VALUES(avatar_cor),
                    avatar_pos_x = VALUES(avatar_pos_x),
                    avatar_pos_y = VALUES(avatar_pos_y),
                    pomo_foco = VALUES(pomo_foco),
                    pomo_pausa = VALUES(pomo_pausa),
                    pomo_pausa_longa = VALUES(pomo_pausa_longa),
                    meta_diaria = VALUES(meta_diaria),
                    materias = VALUES(materias),
                    notif_lembrete = VALUES(notif_lembrete),
                    notif_resumo = VALUES(notif_resumo)';

        $pdo->prepare($sql)->execute([
            $id, $cor, $posX, $posY, $foco, $pausa, $pausaLonga, $meta, $materiasTxt,
            $lembrete, $resumo,
        ]);

        echo json_encode([
            'ok'            => true,
            'msg'           => 'Preferências salvas!',
            'preferencias'  => [
                'avatar_cor'       => $cor,
                'avatar_pos_x'     => $posX,
                'avatar_pos_y'     => $posY,
                'pomo_foco'        => $foco,
                'pomo_pausa'       => $pausa,
                'pomo_pausa_longa' => $pausaLonga,
                'meta_diaria'      => $meta,
                'materias'         => $materias,
                'notif_lembrete'   => (bool) $lembrete,
                'notif_resumo'     => (bool) $resumo,
            ],
        ]);
        exit;
    }

    // ---------- Ler ----------
    $stmt = $pdo->prepare('SELECT avatar_cor, avatar_pos_x, avatar_pos_y, pomo_foco,
                                  pomo_pausa, pomo_pausa_longa, meta_diaria, materias,
                                  notif_lembrete, notif_resumo
                           FROM usuario_preferencias WHERE usuario_id = ? LIMIT 1');
    $stmt->execute([$id]);
    $p = $stmt->fetch();

    if (!$p) {
        // Primeira visita: cria a linha com os padrões
        $pdo->prepare('INSERT INTO usuario_preferencias (usuario_id) VALUES (?)')->execute([$id]);
        $p = PREF_PADRAO;
    }

    echo json_encode([
        'ok'           => true,
        'preferencias' => [
            'avatar_cor'       => $p['avatar_cor'],
            'avatar_pos_x'     => (int) $p['avatar_pos_x'],
            'avatar_pos_y'     => (int) $p['avatar_pos_y'],
            'pomo_foco'        => (int) $p['pomo_foco'],
            'pomo_pausa'       => (int) $p['pomo_pausa'],
            'pomo_pausa_longa' => (int) $p['pomo_pausa_longa'],
            'meta_diaria'      => (int) $p['meta_diaria'],
            'materias'         => $p['materias'] === '' || $p['materias'] === null
                                    ? []
                                    : explode(',', $p['materias']),
            'notif_lembrete'   => (bool) $p['notif_lembrete'],
            'notif_resumo'     => (bool) $p['notif_resumo'],
            'avatar_url'       => urlAvatar(avatarDoUsuario($pdo, $id)),
        ],
        'materias_disponiveis' => MATERIAS_OK,
        'cores_disponiveis'    => CORES_AVATAR,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Não foi possível carregar/salvar as preferências.']);
}
