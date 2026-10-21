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

require_once __DIR__ . '/api_util.php';
require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/limite_util.php';
require_once __DIR__ . '/exercicios_ia_util.php';

/** Tamanho máximo do que vai para o prompt. A matéria pode vir como
 *  "Matemática — Nome livre da matéria" (40 + 120 caracteres). */
const IA_MAX_MATERIA  = 200;
const IA_MAX_CONTEUDO = 1000;
/** Quanto esperamos o n8n. Antes eram 180 s, prendendo um processo
 *  do Apache por três minutos a cada geração travada; uma geração de
 *  15 questões que funciona termina bem antes disso. */
const IA_TIMEOUT_S    = 90;

// Só usuários logados podem gastar a IA
$usuario = exigirLogin();
// A chamada à IA pode levar até IA_TIMEOUT_S: sem soltar o lock da
// sessão, todas as outras requisições da pessoa ficam enfileiradas.
liberarSessao();
apiExigirPost();

// ---------- Lê e valida a entrada ----------
$materia  = trim($_POST['materia']  ?? '');
$conteudo = trim($_POST['conteudo'] ?? '');

if ($materia === '') {
    apiErro('Escolha uma matéria.', 422);
}
if ($conteudo === '') {
    apiErro('Descreva o conteúdo específico.', 422);
}
if (mb_strlen($materia) > IA_MAX_MATERIA) {
    apiErro('O nome da matéria está longo demais.', 422);
}
if (mb_strlen($conteudo) > IA_MAX_CONTEUDO) {
    apiErro('Descreva o conteúdo em até ' . IA_MAX_CONTEUDO . ' caracteres.', 422);
}

// ---------- Limite de uso ----------
// Cada geração gasta a cota da IA (Groq), que é do projeto inteiro:
// sem teto, uma conta só podia esgotá-la para todo mundo. A conta é
// por pessoa (não por e-mail digitado), ver LIMITES em limite_util.php.
try {
    $pdo = conectar();
    $chaveLimite = 'usuario:' . (int) $usuario['id'];
    if (limiteExcedido($pdo, 'ia', $chaveLimite)) {
        apiErro('Você gerou muitos exercícios em pouco tempo. Espere um pouco e tente de novo.', 429);
    }
    registrarTentativa($pdo, 'ia', $chaveLimite);
} catch (PDOException $e) {
    registrarErro($e, 'limite da IA');
    $pdo = null;   // sem banco não há limite nem XP, mas a geração segue
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
        apiErro('Escolha pelo menos uma dificuldade.', 422);
    }
    $qtd = array_sum(array_column($plano, 'qtd'));
    if ($qtd > 15) {
        apiErro('Máximo de 15 questões por vez.', 422);
    }
} else {
    $dificuldade = trim($_POST['dificuldade'] ?? 'Médio');
    $qtd         = (int) ($_POST['qtd']       ?? 3);
    if ($qtd < 1) {
        apiErro('Escolha pelo menos 1 questão.', 422);
    }
    if ($qtd > 15) {
        apiErro('Máximo de 15 questões por vez.', 422);
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
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => IA_TIMEOUT_S,
]);

$resposta = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$erroRede = curl_error($ch);
curl_close($ch);

if ($resposta === false || $httpCode >= 400) {
    $corpo = is_string($resposta) ? $resposta : '';
    // Sem isto a única pista do "500 Error in workflow" era o n8n
    error_log(sprintf('[KOSMOS IA] n8n respondeu HTTP %d%s: %s',
        $httpCode, $erroRede !== '' ? " ($erroRede)" : '', mb_substr($corpo, 0, 300)));
    if (indicioDeLimiteDiario($corpo)) {
        apiErro('limite diario atingido', 429);
    }
    apiErro('Não foi possível gerar os exercícios agora. Tente novamente.', 502);
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
        apiErro('limite diario atingido', 429);
    }
    $questoes = extrairQuestoes($texto);
}

$questoes = normalizarQuestoes($questoes);

if (!$questoes) {
    error_log('[KOSMOS IA] resposta sem questões aproveitáveis: ' . mb_substr($texto !== '' ? $texto : (string) $resposta, 0, 300));
    apiErro('A IA respondeu em um formato inesperado. Tente novamente.', 502);
}

$questoes = aplicarDificuldades($questoes, $plano);

/* XP de "estudo com o Orion": a IA respondeu com questões de verdade.
   Até 3 vezes por dia (teto no ProgressoService). */
require_once __DIR__ . '/ProgressoService.php';
$progresso = null;
if ($pdo !== null) {
    try {
        $progresso = progressoRegistrar($pdo, (int) $usuario['id'], [[
            'acao'     => 'orion_ia',
            'xp'       => ProgressoService::XP['orion_ia'],
            'detalhes' => ['questoes' => count($questoes)],
        ]]);
    } catch (PDOException $e) {
        registrarErro($e, 'XP da IA');
        $progresso = null;   // sem banco, sem XP — as questões saem do mesmo jeito
    }
}

apiResponder(['ok' => true, 'questoes' => $questoes, 'progresso' => $progresso]);
