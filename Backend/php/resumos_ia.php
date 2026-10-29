<?php
// ============================================================
//  KOSMOS — Rascunho de resumo escrito pela IA
//  Arquivo: Backend/php/resumos_ia.php
//  POST (protegido): titulo, materia [, anotacoes]
//  Devolve { ok, texto } — NADA é gravado aqui. O texto vai para o
//  campo do editor e a pessoa revisa e salva pelo resumos_salvar.php,
//  como qualquer resumo: rascunho, não resumo pronto. A IA erra
//  fatos, e escrever com as próprias palavras é metade do estudo.
//
//  Com anotações (o que já estava no campo), a IA organiza e
//  completa o que a pessoa escreveu em vez de partir do zero.
//
//  O texto sai no formato que o resto do app já entende: trechos
//  ==destacados== (Modo revisão) e linhas "Termo: definição" (que a
//  leitura por regra do flashcards_gerar.php vira cartão).
//
//  Pelo fluxo com reserva de IAs (ia_util.php), com a mesma cota por
//  pessoa dos exercícios e flashcards com IA.
// ============================================================

require_once __DIR__ . '/resumos_comum.php';
require_once __DIR__ . '/ia_util.php';
require_once __DIR__ . '/limite_util.php';

/** O que vai de anotação para o prompt. */
const RIA_MAX_ANOTACOES = 3000;

$usuario = exigirLogin();
// a IA leva alguns segundos: sem soltar a sessão, as outras abas esperam
liberarSessao();
apiExigirPost();

$erros     = [];
$titulo    = apiTexto('titulo', 'Tema', RS_MAX_TITULO, $erros);
$materia   = materiaCanonica((string) ($_POST['materia'] ?? ''));
$anotacoes = trim((string) ($_POST['anotacoes'] ?? ''));

if ($erros) {
    apiErro('Escreva o tema do resumo antes de pedir o rascunho.', 422);
}
if (mb_strlen($materia) > RS_MAX_MATERIA) {
    $materia = '';
}
if (!iaDisponivel()) {
    apiErro('O rascunho com IA não está disponível agora.', 503);
}

try {
    $pdo = conectar();
    $chaveLimite = 'usuario:' . (int) $usuario['id'];
    if (limiteExcedido($pdo, 'ia', $chaveLimite)) {
        apiErro('Você usou a IA muitas vezes em pouco tempo. Espere um pouco e tente de novo.', 429);
    }
    registrarTentativa($pdo, 'ia', $chaveLimite);
} catch (PDOException $e) {
    registrarErro($e, 'limite da IA');   // sem banco, segue sem o limite
}

$prompt = 'Escreva um resumo de estudo em portugues, para um aluno do ensino medio, sobre o tema "'
    . $titulo . '"' . ($materia !== '' ? ' (materia: ' . $materia . ')' : '') . '.'
    . ' Formato: texto simples, SEM markdown (nada de #, ** ou tabelas); paragrafos curtos e,'
    . ' quando ajudar, listas com "- " no inicio da linha; entre 150 e 400 palavras.'
    . ' Inclua de 3 a 6 linhas de definicao, cada uma com o proprio termo, dois pontos e a definicao curta'
    . ' (exemplo: "Inercia: tendencia de um corpo manter seu estado de movimento"; nunca escreva a palavra "Termo").'
    . ' Marque de 3 a 8 termos-chave envolvendo cada um com dois sinais de igual, assim: ==termo==.'
    . ' Seja correto e direto; se o tema for amplo, fique no essencial cobrado no ensino medio.'
    . ' Responda apenas com o texto do resumo, sem titulo e sem comentarios antes ou depois.';

if ($anotacoes !== '') {
    $prompt .= ' Use como base as anotacoes do aluno abaixo: organize, corrija o que estiver errado'
        . ' e complete o que faltar, mantendo as ideias dele.'
        . "\n\nAnotacoes do aluno:\n\"\"\"\n" . mb_substr($anotacoes, 0, RIA_MAX_ANOTACOES) . "\n\"\"\"";
}

try {
    $texto = iaPerguntar($prompt, 60);
} catch (RuntimeException $e) {
    if ($e->getCode() === 429) {
        apiErro('A IA está sem cota agora. Tente de novo mais tarde.', 429);
    }
    apiErro('A IA não respondeu agora. Tente de novo em instantes.', 502);
}

$texto = limparRascunhoIA($texto);
if (mb_strlen($texto) < 80) {
    error_log('[KOSMOS IA] rascunho curto demais: ' . mb_substr($texto, 0, 300));
    apiErro('A IA devolveu um rascunho estranho. Tente de novo.', 502);
}

apiResponder(['ok' => true, 'texto' => mb_substr($texto, 0, RS_MAX_CORPO)]);


/**
 * Tira o que a IA põe por hábito mesmo quando pedimos texto simples:
 * cerca de código, títulos "# ...", negrito "**...**", o molde
 * "Termo: x – definição" copiado ao pé da letra (vira "x: definição",
 * que é o que a leitura por regra dos flashcards entende) e linhas em
 * branco demais.
 */
function limparRascunhoIA(string $texto): string {
    $texto = preg_replace('/^```[a-z]*\s*|\s*```$/iu', '', trim($texto));
    $texto = preg_replace('/^#{1,6}\s*/mu', '', $texto);
    $texto = preg_replace('/\*\*(.+?)\*\*/su', '$1', $texto);
    $texto = preg_replace('/^(\s*(?:- )?)Termo:\s*([^:\n–—-]+?)\s*[–—-]\s*/mu', '$1$2: ', $texto);
    $texto = preg_replace('/[ \t]+$/mu', '', $texto);
    $texto = preg_replace('/\n{3,}/u', "\n\n", $texto);
    return trim($texto);
}
