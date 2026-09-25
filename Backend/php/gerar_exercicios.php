<?php
// ============================================================
//  KOSMOS — Gerar exercícios com IA (via n8n Chat Trigger)
//  Arquivo: backend/php/gerar_exercicios.php
//  POST (protegido): recebe materia/conteudo + plano
//  (JSON [{dificuldade, qtd}...]) e manda um chatInput pro n8n,
//  devolvendo { questoes: [ ... ] } com cada questão já
//  rotulada da dificuldade pedida.
//  Legado (orion-chat): materia/dificuldade/qtd/conteudo.
//  O navegador NUNCA vê a URL do n8n — só este PHP.
//
//  O workflow do n8n é um Chat Trigger: pede JSON puro no prompt
//  e o PHP extrai o objeto de dentro da resposta ({ output }).
// ============================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/config.php';

// Só usuários logados podem gastar a IA
$usuario = exigirLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método não permitido.']);
    exit;
}

// ---------- Lê e valida a entrada ----------
$materia  = trim($_POST['materia']  ?? '');
$conteudo = trim($_POST['conteudo'] ?? '');

if ($materia === '') {
    echo json_encode(['ok' => false, 'msg' => 'Escolha uma matéria.']);
    exit;
}
if ($conteudo === '') {
    echo json_encode(['ok' => false, 'msg' => 'Descreva o conteúdo específico.']);
    exit;
}

// Plano novo: [{dificuldade, qtd}...] — 1+ níveis, soma até 15.
// Legado (orion-chat): uma dificuldade única + qtd.
$planoJson = trim($_POST['plano'] ?? '');
$plano = [];

if ($planoJson !== '') {
    $bruto = json_decode($planoJson, true);
    if (is_array($bruto)) {
        $porNivel = [];
        foreach ($bruto as $item) {
            if (!is_array($item)) {
                continue;
            }
            $d = trim((string) ($item['dificuldade'] ?? ''));
            $q = (int) ($item['qtd'] ?? 0);
            if (!in_array($d, ['Fácil', 'Médio', 'Difícil'], true) || $q < 1) {
                continue;
            }
            $porNivel[$d] = ($porNivel[$d] ?? 0) + $q;
        }
        // ordem canônica: Fácil → Médio → Difícil (mesma do prompt)
        foreach (['Fácil', 'Médio', 'Difícil'] as $d) {
            if (isset($porNivel[$d])) {
                $plano[] = ['dificuldade' => $d, 'qtd' => $porNivel[$d]];
            }
        }
    }
    if (!$plano) {
        echo json_encode(['ok' => false, 'msg' => 'Escolha pelo menos uma dificuldade.']);
        exit;
    }
    $qtd = array_sum(array_column($plano, 'qtd'));
    if ($qtd > 15) {
        echo json_encode(['ok' => false, 'msg' => 'Máximo de 15 questões por vez.']);
        exit;
    }
} else {
    $dificuldade = trim($_POST['dificuldade'] ?? 'Médio');
    $qtd         = (int) ($_POST['qtd']       ?? 3);
    if ($qtd < 1) {
        echo json_encode(['ok' => false, 'msg' => 'Escolha pelo menos 1 questão.']);
        exit;
    }
    if ($qtd > 15) {
        echo json_encode(['ok' => false, 'msg' => 'Máximo de 15 questões por vez.']);
        exit;
    }
    if (!in_array($dificuldade, ['Fácil', 'Médio', 'Difícil'], true)) {
        $dificuldade = 'Médio';
    }
    $plano = [['dificuldade' => $dificuldade, 'qtd' => $qtd]];
}

// ---------- Monta o prompt (JSON puro, sem markdown) ----------
if (count($plano) === 1) {
    // Nível único: prompt original — a dificuldade é aplicada em PHP.
    $chatInput = 'Gere ' . $qtd . ' exercicios de ' . $materia
        . ' nivel ' . $plano[0]['dificuldade'] . ' sobre: ' . $conteudo
        . '. Responda apenas com JSON no formato: '
        . '{"questoes":[{"enunciado":"...","alts":["...","...","...","...","..."],"correta":0,"dica":"..."}]}'
        . '. alts com 5 alternativas; correta e o indice 0-4 da alternativa correta;'
        . ' dica e uma frase curta que ajuda a pensar SEM entregar a resposta (opcional, pode vir vazia).';
} else {
    // Vários níveis: UMA chamada, com a distribuição no prompt e o
    // rótulo por questão — o PHP confere/aplica os totais depois.
    $partes = [];
    foreach ($plano as $p) {
        $partes[] = $p['qtd'] . ' de nivel ' . $p['dificuldade'];
    }
    $chatInput = 'Gere ' . $qtd . ' exercicios de ' . $materia . ' sobre: ' . $conteudo
        . '. Distribuicao: ' . implode(', ', $partes) . ' (nesta ordem).'
        . '. Responda apenas com JSON no formato: '
        . '{"questoes":[{"enunciado":"...","alts":["...","...","...","...","..."],"correta":0,"dica":"...","dificuldade":"..."}]}'
        . '. alts com 5 alternativas; correta e o indice 0-4 da alternativa correta;'
        . ' dica e uma frase curta que ajuda a pensar SEM entregar a resposta (opcional, pode vir vazia);'
        . ' dificuldade e exatamente uma de: Facil, Medio, Dificil, seguindo a distribuicao acima na mesma ordem.';
}

$payload = json_encode([
    'action'    => 'sendMessage',
    'sessionId' => 'kosmos-' . bin2hex(random_bytes(8)),
    'chatInput' => $chatInput,
], JSON_UNESCAPED_UNICODE);

// ---------- Chama o Chat Trigger do n8n ----------
$ch = curl_init(N8N_EXERCICIOS_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        // Chat Trigger pode estar com auth none — header extra não atrapalha
        N8N_TOKEN_HEADER . ': ' . N8N_TOKEN_VALOR,
    ],
    CURLOPT_TIMEOUT        => 180,  // a IA pode levar alguns segundos
]);

$resposta = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($resposta === false || $httpCode >= 400) {
    $corpo = is_string($resposta) ? $resposta : '';
    if (indicioDeLimiteDiario($corpo)) {
        http_response_code(429);
        echo json_encode(['ok' => false, 'msg' => 'limite diario atingido']);
        exit;
    }
    http_response_code(502);
    echo json_encode(['ok' => false, 'msg' => 'Não foi possível gerar os exercícios agora. Tente novamente.']);
    exit;
}

// ---------- Extrai as questões ----------
$dados = json_decode($resposta, true);

// Formato Chat Trigger: { "output": "..." } — o JSON pode estar lá dentro
$questoes = null;
$texto = '';
if (is_array($dados)) {
    if (!empty($dados['questoes']) && is_array($dados['questoes'])) {
        $questoes = $dados['questoes'];
    } elseif (isset($dados['output']) && is_string($dados['output'])) {
        $texto = $dados['output'];
    }
}

if ($questoes === null) {
    if (indicioDeLimiteDiario($texto)) {
        http_response_code(429);
        echo json_encode(['ok' => false, 'msg' => 'limite diario atingido']);
        exit;
    }
    $questoes = extrairQuestoes($texto);
}

$questoes = normalizarQuestoes($questoes);

if (!$questoes) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'msg' => 'A IA respondeu em um formato inesperado. Tente novamente.']);
    exit;
}

$questoes = aplicarDificuldades($questoes, $plano);

echo json_encode(['ok' => true, 'questoes' => $questoes], JSON_UNESCAPED_UNICODE);

/* ============================================================
   Helpers
   ============================================================ */

/**
 * O n8n é quem impõe o limite diário de gerações. Quando o
 * workflow bate nesse limite, a resposta (erro HTTP ou o próprio
 * "output") costuma trazer uma mensagem de limite/quota.
 * A busca ignora acentos/caixa — "diário" e "diario" são ambos
 * detectados (strtr em vez de iconv: o iconv do Windows escapa
 * acentos para \'a e estraga a busca).
 */
function indicioDeLimiteDiario(string $texto): bool {
    if (trim($texto) === '') {
        return false;
    }
    $acentos = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c',
    ];
    $t = strtr(mb_strtolower($texto, 'UTF-8'), $acentos);
    $sinais = [
        'limite diari', 'diario atingid', 'diaria atingid',
        'limite de gera', 'limite de uso', 'limite de utiliza',
        'excedeu o limite', 'excedeu a cota', 'atingiu o limite',
        'atingiu a cota', 'chegou no limite', 'chegou ao limite',
        'sem geracoes', 'sem geracao',
        'daily limit', 'daily quota', 'generation limit',
        'limit reached', 'limit exceeded', 'quota exceeded',
        'you have reached', 'no more generations',
    ];
    foreach ($sinais as $s) {
        if (strpos($t, $s) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Puxa o array de questões de dentro de um texto que pode conter
 * JSON puro, ```json ... ``` ou prosa em volta do objeto.
 */
function extrairQuestoes(string $texto): array {
    $texto = trim($texto);
    if ($texto === '') {
        return [];
    }

    // 1) JSON puro
    $j = json_decode($texto, true);
    if (is_array($j) && !empty($j['questoes']) && is_array($j['questoes'])) {
        return $j['questoes'];
    }

    // 2) Bloco ```json ... ```
    if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $texto, $m)) {
        $j = json_decode($m[1], true);
        if (is_array($j) && !empty($j['questoes']) && is_array($j['questoes'])) {
            return $j['questoes'];
        }
    }

    // 3) Primeiro objeto {...} que tenha "questoes"
    if (preg_match('/\{.*"questoes".*\}/s', $texto, $m)) {
        $j = json_decode($m[0], true);
        if (is_array($j) && !empty($j['questoes']) && is_array($j['questoes'])) {
            return $j['questoes'];
        }
    }

    // 4) Array solto [ {...}, ... ]
    if (preg_match('/\[.*\]/s', $texto, $m)) {
        $j = json_decode($m[0], true);
        if (is_array($j) && $j !== [] && isset($j[0]) && is_array($j[0])) {
            return $j;
        }
    }

    return [];
}

/**
 * Garante o formato que o front espera:
 * { enunciado, alts: [5 strings], correta: 0-4, dica?: string }
 * "dica" só entra quando vem preenchida — senão o botão da dica
 * na tela de prática nem aparece.
 */
function normalizarQuestoes(array $questoes): array {
    $fora = [];
    foreach ($questoes as $q) {
        if (!is_array($q)) {
            continue;
        }
        $enunciado = trim((string) ($q['enunciado'] ?? $q['questao'] ?? $q['pergunta'] ?? ''));
        $alts      = $q['alts'] ?? $q['alternativas'] ?? $q['opcoes'] ?? [];
        $correta   = $q['correta'] ?? $q['resposta'] ?? $q['correto'] ?? 0;
        $dica      = trim((string) ($q['dica'] ?? ''));

        if ($enunciado === '' || !is_array($alts) || count($alts) < 2) {
            continue;
        }
        $alts = array_values(array_map('strval', $alts));
        $correta = (int) $correta;
        if ($correta < 0 || $correta >= count($alts)) {
            $correta = 0;
        }

        $questao = [
            'enunciado' => $enunciado,
            'alts'      => $alts,
            'correta'   => $correta,
        ];
        if ($dica !== '') {
            $questao['dica'] = $dica;
        }
        $fora[] = $questao;
    }
    return $fora;
}

/**
 * Cada questão fica com a dificuldade pedida no plano.
 * O que a IA já rotulou (e ainda cabe no saldo) vale; o resto
 * é preenchido na ordem do plano (Fácil → Médio → Difícil), então
 * os totais por nível batem com o que o usuário escolheu.
 */
function aplicarDificuldades(array $questoes, array $plano): array {
    $ordem = array_column($plano, 'dificuldade');
    if (!$ordem) {
        return $questoes;
    }
    $saldo = [];
    foreach ($plano as $p) {
        $saldo[$p['dificuldade']] = (int) $p['qtd'];
    }

    // 1) valida o rótulo vindo da IA e consome o saldo
    foreach ($questoes as $k => $q) {
        $d = (string) ($q['dificuldade'] ?? '');
        if (!in_array($d, $ordem, true)) {
            $questoes[$k]['dificuldade'] = '';
        } elseif ($saldo[$d] > 0) {
            $saldo[$d] -= 1;
        }
        // rótulo válido acima do plano: mantém (excedente)
    }

    // 2) preenche as sem rótulo na ordem do plano
    foreach ($questoes as $k => $q) {
        if (($q['dificuldade'] ?? '') !== '') {
            continue;
        }
        foreach ($ordem as $d) {
            if ($saldo[$d] > 0) {
                $questoes[$k]['dificuldade'] = $d;
                $saldo[$d] -= 1;
                break;
            }
        }
        if (($questoes[$k]['dificuldade'] ?? '') === '') {
            // plano esgotado (IA devolveu a mais): neutro
            $questoes[$k]['dificuldade'] = 'Médio';
        }
    }

    return $questoes;
}
