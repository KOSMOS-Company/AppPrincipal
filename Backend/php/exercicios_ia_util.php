<?php
// ============================================================
//  KOSMOS — Leitura da resposta da IA (exercícios)
//  Arquivo: Backend/php/exercicios_ia_util.php
//
//  Funções puras (sem rede, sem banco, sem sessão) que transformam o
//  texto devolvido pelo n8n nas questões que a tela espera. Moravam
//  dentro do gerar_exercicios.php; aqui elas podem ser testadas
//  (Backend/testes/rodar.php) sem chamar a IA de verdade.
// ============================================================

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
        // O rótulo da IA precisa sobreviver até aplicarDificuldades —
        // já canônico ("Facil" → "Fácil"); o que não reconhecer vira ''.
        $rotulo = rotuloDificuldade((string) ($q['dificuldade'] ?? ''));
        if ($rotulo !== '') {
            $questao['dificuldade'] = $rotulo;
        }
        $fora[] = $questao;
    }
    return $fora;
}

/**
 * O prompt pede "Facil, Medio, Dificil" sem acento (o modelo erra
 * menos), mas o plano usa os rótulos acentuados. Compara sem caixa
 * nem acento e devolve o rótulo canônico, ou '' se não reconhecer.
 * strtr em vez de iconv pelo mesmo motivo de indicioDeLimiteDiario.
 */
function rotuloDificuldade(string $bruto): string {
    $t = strtr(mb_strtolower(trim($bruto), 'UTF-8'), ['á' => 'a', 'é' => 'e', 'í' => 'i']);
    $canonicos = ['facil' => 'Fácil', 'medio' => 'Médio', 'dificil' => 'Difícil'];
    return $canonicos[$t] ?? '';
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
        $d = rotuloDificuldade((string) ($q['dificuldade'] ?? ''));
        $questoes[$k]['dificuldade'] = $d;
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
