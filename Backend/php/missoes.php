<?php
// ============================================================
//  KOSMOS — Missões da semana (o que vem depois dos primeiros passos)
//  Arquivo: Backend/php/missoes.php
//
//  Quem já fez os três primeiros passos (resumo, cartão, foco) passa
//  a receber quatro missões por semana: "Semana 1", "Semana 2"... A
//  Semana 1 é a semana (segunda a domingo) em que o último dos
//  primeiros passos aconteceu; daí em diante conta uma por semana de
//  calendário, tenha a pessoa estudado ou não.
//
//  Nada é gravado: cada missão é uma LEITURA do que a pessoa fez na
//  semana, igual aos primeiros passos. Sem tabela nova, sem estado
//  para dessincronizar, e a lista se corrige sozinha.
//
//  Datas: TODAS as contas de dia/semana são feitas pelo MySQL (o PHP
//  deste projeto roda em outro fuso — ver README). O PHP só compara
//  números com alvos.
//
//  Usado por:
//    Frontend/pages/dashboard/index.php  (primeira pintura)
//    Backend/php/inicio_dados.php        (a tela se atualiza sozinha)
// ============================================================

/** Nomes das semanas (a viagem pelo cosmos). Depois da última, repete com número. */
const MISSOES_NOMES_SEMANA = [
    'Decolagem', 'Órbita baixa', 'Cinturão de asteroides', 'Rumo a Marte',
    'Anéis de Saturno', 'Nebulosa', 'Constelação', 'Via Láctea',
];

/** Quantas semanas passadas aparecem na fileira do histórico. */
const MISSOES_HISTORICO = 6;

/**
 * As missões de uma semana. Os alvos crescem devagar até a 8ª semana
 * e param ali — a ideia é criar hábito, não uma escada infinita.
 * Duas missões são fixas (dias e minutos de foco); as outras duas se
 * alternam entre semanas ímpares e pares, para não virar rotina.
 *
 * @return array<int, array{slug:string, metrica:string, alvo:int, titulo:string, dica:string, href:string}>
 */
function missoesDaSemana(int $semana): array
{
    $k = max(1, min($semana, 8)) - 1;   // 0..7

    $dias  = min(3 + intdiv($k, 2), 6);
    $foco  = min(60 + 30 * $k, 300);

    $lista = [
        [
            'slug' => 'dias', 'metrica' => 'dias', 'alvo' => $dias,
            'titulo' => "Estudar em {$dias} dias diferentes",
            'dica' => 'Qualquer estudo conta: foco, flashcards, exercícios ou resumo.',
            'href' => 'pomodoro.php',
        ],
        [
            'slug' => 'foco', 'metrica' => 'foco', 'alvo' => $foco,
            'titulo' => 'Somar ' . missoesDuracao($foco) . ' de foco',
            'dica' => 'Os ciclos do Pomodoro vão se somando ao longo da semana.',
            'href' => 'pomodoro.php',
        ],
    ];

    if ($semana % 2 === 1) {
        $resumos  = min(1 + intdiv($k + 1, 2), 4);
        $sessoes  = min(2 + intdiv($k, 2), 5);
        $lista[] = [
            'slug' => 'resumos', 'metrica' => 'resumos', 'alvo' => $resumos,
            'titulo' => $resumos === 1 ? 'Escrever 1 resumo novo' : "Escrever {$resumos} resumos novos",
            'dica' => 'Revise a matéria da semana com as suas palavras.',
            'href' => 'resumos.php',
        ];
        $lista[] = [
            'slug' => 'sessoes', 'metrica' => 'sessoes', 'alvo' => $sessoes,
            'titulo' => "Terminar {$sessoes} revisões de flashcards",
            'dica' => 'Uma revisão conta quando você passa pelo baralho até o fim.',
            'href' => 'revisar.php',
        ];
    } else {
        $cartoes  = min(10 + 5 * $k, 40);
        $questoes = min(10 + 5 * $k, 50);
        $lista[] = [
            'slug' => 'cartoes', 'metrica' => 'cartoes', 'alvo' => $cartoes,
            'titulo' => "Criar {$cartoes} flashcards",
            'dica' => 'Transforme o que caiu na semana em perguntas curtas.',
            'href' => 'flashcards.php',
        ];
        $lista[] = [
            'slug' => 'questoes', 'metrica' => 'questoes', 'alvo' => $questoes,
            'titulo' => "Responder {$questoes} questões",
            'dica' => 'Gere exercícios com o Orion e pratique.',
            'href' => 'exercicios.php',
        ];
    }

    return $lista;
}

/** "90 min" -> "1 h 30 min" (mesmo formato do inicio.js). */
function missoesDuracao(int $min): string
{
    $h = intdiv($min, 60);
    $m = $min % 60;
    if ($h === 0) return "{$m} min";
    return $m ? "{$h} h {$m} min" : "{$h} h";
}

/**
 * O estado das missões para a tela, ou null se a pessoa ainda não
 * terminou os primeiros passos (a Início mostra a lista antiga).
 *
 * @return array{semana:int, nome:string, dias_restantes:int, feitas:int, total:int,
 *               completa:bool, missoes:array, historico:array}|null
 */
function missoesEstado(PDO $pdo, int $usuarioId): ?array
{
    // ---- 1) Quando os primeiros passos terminaram? ----
    // O último dos três a acontecer. Faltando qualquer um, NULL.
    $stmt = $pdo->prepare(
        'SELECT inicio,
                DATE_FORMAT(inicio - INTERVAL WEEKDAY(inicio) DAY, "%Y-%m-%d") AS segunda,
                FLOOR(DATEDIFF(CURDATE(), inicio - INTERVAL WEEKDAY(inicio) DAY) / 7) + 1 AS semana,
                6 - WEEKDAY(CURDATE()) AS restantes
           FROM (
                SELECT GREATEST(
                    (SELECT DATE(MIN(criado_em)) FROM resumos WHERE usuario_id = ?),
                    (SELECT DATE(MIN(c.criado_em)) FROM flashcard_cartoes c
                       JOIN flashcard_decks d ON d.id = c.deck_id WHERE d.usuario_id = ?),
                    (SELECT MIN(dia) FROM pomodoro_sessoes WHERE usuario_id = ?)
                ) AS inicio
           ) AS t'
    );
    try {
        $stmt->execute([$usuarioId, $usuarioId, $usuarioId]);
        $base = $stmt->fetch();
    } catch (PDOException $e) {
        return null;   // tabela de estudo ausente: fica nos primeiros passos
    }
    if (!$base || $base['inicio'] === null) {
        return null;
    }

    $semanaAtual = max(1, (int) $base['semana']);
    $segunda     = $base['segunda'];              // segunda-feira da Semana 1 (texto do MySQL)
    $primeira    = max(1, $semanaAtual - MISSOES_HISTORICO);

    // ---- 2) As métricas, agrupadas por semana (1, 2, 3...) ----
    // Uma consulta por fonte; cada uma devolve [semana => valor] só
    // das semanas que interessam (histórico + atual).
    $porSemana = function (string $sql, array $params) use ($pdo): array {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $saida = [];
            foreach ($stmt as $l) {
                $saida[(int) $l['semana']] = (int) $l['valor'];
            }
            return $saida;
        } catch (PDOException $e) {
            return [];   // tabela opcional ausente: a missão fica em zero
        }
    };
    // "semana" de uma data: FLOOR(DATEDIFF(data, segunda) / 7) + 1
    $sem  = fn(string $col) => "FLOOR(DATEDIFF({$col}, ?) / 7) + 1";
    $faixa = fn(string $col) => "{$col} >= ? + INTERVAL ? WEEK";

    $inicioFaixa = $primeira - 1;   // semanas depois da segunda-feira inicial

    $metricas = [
        'foco' => $porSemana(
            "SELECT {$sem('dia')} AS semana, SUM(minutos) AS valor
               FROM pomodoro_sessoes
              WHERE usuario_id = ? AND {$faixa('dia')}
              GROUP BY semana",
            [$segunda, $usuarioId, $segunda, $inicioFaixa]
        ),
        'resumos' => $porSemana(
            "SELECT {$sem('DATE(criado_em)')} AS semana, COUNT(*) AS valor
               FROM resumos
              WHERE usuario_id = ? AND {$faixa('DATE(criado_em)')}
              GROUP BY semana",
            [$segunda, $usuarioId, $segunda, $inicioFaixa]
        ),
        'cartoes' => $porSemana(
            "SELECT {$sem('DATE(c.criado_em)')} AS semana, COUNT(*) AS valor
               FROM flashcard_cartoes c
               JOIN flashcard_decks d ON d.id = c.deck_id
              WHERE d.usuario_id = ? AND {$faixa('DATE(c.criado_em)')}
              GROUP BY semana",
            [$segunda, $usuarioId, $segunda, $inicioFaixa]
        ),
        'questoes' => $porSemana(
            "SELECT {$sem('dia')} AS semana, COUNT(*) AS valor
               FROM exercicio_respostas
              WHERE usuario_id = ? AND {$faixa('dia')}
              GROUP BY semana",
            [$segunda, $usuarioId, $segunda, $inicioFaixa]
        ),
        // Revisão terminada = a linha de XP "sessao_flashcards"
        'sessoes' => $porSemana(
            "SELECT {$sem('DATE(criado_em)')} AS semana, COUNT(*) AS valor
               FROM historico_xp
              WHERE usuario_id = ? AND origem_acao = 'sessao_flashcards' AND {$faixa('DATE(criado_em)')}
              GROUP BY semana",
            [$segunda, $usuarioId, $segunda, $inicioFaixa]
        ),
        // Dia de estudo: teve foco OU qualquer ação que conta para a
        // sequência de estudo (a linha "streak_diario" sai uma vez por dia)
        'dias' => $porSemana(
            "SELECT {$sem('d')} AS semana, COUNT(DISTINCT d) AS valor
               FROM (
                    SELECT dia AS d FROM pomodoro_sessoes WHERE usuario_id = ?
                    UNION
                    SELECT DATE(criado_em) FROM historico_xp WHERE usuario_id = ? AND origem_acao = 'streak_diario'
               ) AS x
              WHERE {$faixa('d')}
              GROUP BY semana",
            [$segunda, $usuarioId, $usuarioId, $segunda, $inicioFaixa]
        ),
    ];

    // Se o historico_xp não existir, "dias" cai inteiro no catch; tenta só com o Pomodoro
    if ($metricas['dias'] === []) {
        $metricas['dias'] = $porSemana(
            "SELECT {$sem('dia')} AS semana, COUNT(DISTINCT dia) AS valor
               FROM pomodoro_sessoes
              WHERE usuario_id = ? AND {$faixa('dia')}
              GROUP BY semana",
            [$segunda, $usuarioId, $segunda, $inicioFaixa]
        );
    }

    // ---- 3) Compara com os alvos ----
    $avaliar = function (int $semana) use ($metricas): array {
        $itens = [];
        foreach (missoesDaSemana($semana) as $m) {
            $valor = $metricas[$m['metrica']][$semana] ?? 0;
            $itens[] = $m + [
                'valor' => min($valor, $m['alvo']),
                'feita' => $valor >= $m['alvo'],
                'progresso' => $m['metrica'] === 'foco'
                    ? missoesDuracao(min($valor, $m['alvo'])) . ' de ' . missoesDuracao($m['alvo'])
                    : min($valor, $m['alvo']) . ' de ' . $m['alvo'],
            ];
        }
        return $itens;
    };

    $atuais = $avaliar($semanaAtual);
    $feitas = count(array_filter($atuais, fn($m) => $m['feita']));

    $historico = [];
    for ($s = $primeira; $s < $semanaAtual; $s++) {
        $itens = $avaliar($s);
        $ok = count(array_filter($itens, fn($m) => $m['feita']));
        $historico[] = ['semana' => $s, 'feitas' => $ok, 'total' => count($itens), 'completa' => $ok === count($itens)];
    }

    $nomes = MISSOES_NOMES_SEMANA;
    return [
        'semana'         => $semanaAtual,
        'nome'           => $nomes[($semanaAtual - 1) % count($nomes)],
        'dias_restantes' => max(0, (int) $base['restantes']),
        'feitas'         => $feitas,
        'total'          => count($atuais),
        'completa'       => $feitas === count($atuais),
        'missoes'        => $atuais,
        'historico'      => $historico,
    ];
}
