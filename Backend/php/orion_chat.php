<?php
// ============================================================
//  KOSMOS — Conversa com o Orion (IA, via n8n)
//  Arquivo: Backend/php/orion_chat.php
//
//  POST (protegido), JSON:
//    mensagem   -> o que a pessoa escreveu (até 1000 caracteres)
//    historico  -> [{autor: 'usuario'|'orion', texto}] (as últimas)
//    pagina     -> a aba onde ela está (ex.: "flashcards.php"), opcional
//    questao    -> opcional, do botão "Por que eu errei?":
//                  {enunciado, alternativas[], correta, escolhida}
//  Devolve { ok, resposta, humor }.
//
//  Quem usa: js/orion-ia.js (a aba Orion e o chat flutuante).
//
//  O caminho é o mesmo dos exercícios: o navegador nunca vê a URL
//  do n8n nem o token. O n8n (fluxo "Kosmos — Orion": Webhook →
//  AI Agent com Groq → Respond to Webhook) só repassa "sistema" e
//  "prompt" para a IA; o que o Orion sabe e como ele se comporta
//  é montado aqui (orion_util.php + orion_manual.md).
//
//  Privacidade: vão para a IA só o primeiro nome e números de estudo
//  (sequência, nível, minutos, cartões vencidos, próximas provas).
//  Nunca o e-mail, nem o texto dos resumos.
// ============================================================

require_once __DIR__ . '/api_util.php';
require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/limite_util.php';
require_once __DIR__ . '/orion_util.php';

/** Quanto esperamos a IA. Uma resposta normal leva poucos segundos. */
const ORION_TIMEOUT_S = 45;

$usuario = exigirLogin();
liberarSessao();
apiExigirPost();

if (!defined('N8N_ORION_URL') || N8N_ORION_URL === '') {
    apiErro('O Orion com IA ainda não foi configurado neste servidor.', 503);
}

// ---------- Entrada ----------
$corpo = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($corpo)) {
    apiErro('Pedido inválido.', 400);
}

$mensagem = trim((string) ($corpo['mensagem'] ?? ''));
if ($mensagem === '') {
    apiErro('Escreva uma mensagem.', 422);
}
if (mb_strlen($mensagem) > ORION_MAX_MENSAGEM) {
    apiErro('Mensagem longa demais: escreva em até ' . ORION_MAX_MENSAGEM . ' caracteres.', 422);
}

$historico = orionHistoricoLimpo($corpo['historico'] ?? []);
$questao   = orionQuestaoLimpa($corpo['questao'] ?? null);
$pagina    = basename((string) ($corpo['pagina'] ?? ''));
$usuarioId = (int) $usuario['id'];

// ---------- Limite de uso + o que a IA precisa saber da pessoa ----------
$contexto = '';
try {
    $pdo = conectar();

    // Cada mensagem gasta a cota da IA, que é do projeto inteiro
    $chave = 'usuario:' . $usuarioId;
    if (limiteExcedido($pdo, 'orion', $chave)) {
        apiErro('Você mandou muitas mensagens em pouco tempo. Respira um pouco e tente de novo daqui a alguns minutos. 🌌', 429);
    }
    registrarTentativa($pdo, 'orion', $chave);

    $contexto = orionContextoTexto(orionDadosDaPessoa($pdo, $usuarioId, (string) $usuario['nome']));
} catch (PDOException $e) {
    registrarErro($e, 'Orion: contexto');
    // Sem banco: o Orion responde do mesmo jeito, só sem os dados da pessoa
}

$manual = (string) @file_get_contents(__DIR__ . '/orion_manual.md');
$manual = preg_replace('/<!--.*?-->/s', '', $manual);   // o comentário é para quem mantém

$payload = json_encode([
    'sistema' => orionSistema($manual, $contexto, $pagina),
    'prompt'  => orionPrompt($historico, $mensagem, $questao),
], JSON_UNESCAPED_UNICODE);

// ---------- Chama o fluxo do n8n ----------
$ch = curl_init(N8N_ORION_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', N8N_TOKEN_HEADER . ': ' . N8N_TOKEN_VALOR],
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => ORION_TIMEOUT_S,
]);
$resposta = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$erroRede = curl_error($ch);
curl_close($ch);

if ($resposta === false || $httpCode >= 400) {
    error_log(sprintf('[KOSMOS Orion] n8n respondeu HTTP %d%s: %s',
        $httpCode, $erroRede !== '' ? " ($erroRede)" : '', mb_substr((string) $resposta, 0, 300)));
    apiErro('O Orion está sem sinal agora. Tente de novo em instantes.', 502);
}

// O Respond to Webhook devolve o item do AI Agent: {"output": "..."}.
// Aceita também {"text": ...} e a lista [{...}], caso o nó mude.
$dados = json_decode((string) $resposta, true);
if (is_array($dados) && isset($dados[0]) && is_array($dados[0])) {
    $dados = $dados[0];
}
$texto = is_array($dados) ? (string) ($dados['output'] ?? $dados['text'] ?? '') : '';

$saida = orionSepararHumor($texto);
if ($saida['resposta'] === '') {
    error_log('[KOSMOS Orion] resposta vazia ou em formato inesperado: ' . mb_substr((string) $resposta, 0, 300));
    apiErro('O Orion se embananou na resposta. Pode perguntar de novo?', 502);
}

apiResponder(['ok' => true, 'resposta' => $saida['resposta'], 'humor' => $saida['humor']]);

/* ============================================================
   Dados da pessoa para o Orion (só números e o primeiro nome)
   ============================================================ */
function orionDadosDaPessoa(PDO $pdo, int $id, string $nome): array {
    $d = ['primeiro_nome' => trim(explode(' ', trim($nome))[0] ?? '')];

    $q = $pdo->prepare('SELECT u.sequencia, p.meta_diaria, p.objetivo
                          FROM usuarios u
                     LEFT JOIN usuario_preferencias p ON p.usuario_id = u.id
                         WHERE u.id = ?');
    $q->execute([$id]);
    if ($r = $q->fetch()) {
        $d['sequencia']   = (int) $r['sequencia'];
        $d['meta_diaria'] = (int) ($r['meta_diaria'] ?? 60);
        $objetivos = [
            'vestibular_enem' => 'ENEM e vestibulares', 'concursos' => 'concursos públicos',
            'ensino_medio' => 'reforço no ensino médio', 'faculdade' => 'faculdade',
            'habito' => 'criar o hábito de estudar',
        ];
        $d['objetivo'] = $objetivos[$r['objetivo'] ?? ''] ?? '';
    }

    $q = $pdo->prepare('SELECT COALESCE(SUM(minutos), 0) FROM pomodoro_sessoes WHERE usuario_id = ? AND dia = CURDATE()');
    $q->execute([$id]);
    $d['minutos_hoje'] = (int) $q->fetchColumn();

    // Mesmo critério do "Revisar hoje" (revisar_fila.php)
    $q = $pdo->prepare('SELECT COUNT(*) FROM flashcard_cartoes c
                          JOIN flashcard_decks d ON d.id = c.deck_id
                         WHERE d.usuario_id = ?
                           AND (c.proxima_revisao IS NULL OR c.proxima_revisao <= CURDATE())');
    $q->execute([$id]);
    $d['cartoes_vencidos'] = (int) $q->fetchColumn();

    $q = $pdo->prepare('SELECT (SELECT COUNT(*) FROM resumos WHERE usuario_id = ?) AS resumos,
                               (SELECT COUNT(*) FROM flashcard_decks WHERE usuario_id = ?) AS baralhos');
    $q->execute([$id, $id]);
    $r = $q->fetch();
    $d['resumos']  = (int) $r['resumos'];
    $d['baralhos'] = (int) $r['baralhos'];

    $q = $pdo->prepare('SELECT titulo, materia, DATEDIFF(data, CURDATE()) AS dias
                          FROM provas
                         WHERE usuario_id = ? AND data >= CURDATE()
                      ORDER BY data
                         LIMIT 3');
    $q->execute([$id]);
    $d['provas'] = $q->fetchAll();

    // Progressão: tabela de uma migração própria; sem ela, segue sem nível
    try {
        $q = $pdo->prepare('SELECT nivel_atual, titulo_atual FROM usuario_progresso WHERE usuario_id = ?');
        $q->execute([$id]);
        if ($r = $q->fetch()) {
            $d['nivel']  = (int) $r['nivel_atual'];
            $d['titulo'] = (string) $r['titulo_atual'];
        }
    } catch (PDOException $e) {
        registrarErro($e, 'Orion: progresso');
    }

    return $d;
}
