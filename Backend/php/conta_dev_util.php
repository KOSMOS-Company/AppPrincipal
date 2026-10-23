<?php
// ============================================================
//  KOSMOS — Conta de desenvolvedor: preencher e renovar
//  Arquivo: Backend/php/conta_dev_util.php
//
//  Usado por:
//   - Backend/tarefas/conta_dev.php (cria a conta pelo terminal);
//   - login.php e pagina_dashboard.php (renovam a conta sozinhos, no
//     primeiro acesso de cada dia — contaDevRenovar()).
//
//  "Renovar" = apagar os dados da conta e preencher de novo com as
//  datas contadas a partir de HOJE. Assim ela nunca perde nada: a
//  sequência não quebra, o gráfico da semana não esvazia, as provas
//  não passam, os cartões vencidos não se acumulam. A conta (o id, o
//  e-mail e a senha) continua a mesma, então ninguém é deslogado.
//  O que for criado nela durante o dia some na renovação seguinte.
//
//  SÓ LIGA SE o config.php tiver CONTA_DEV_EMAIL (o config.php não vai
//  para o git — em produção a constante não existe e nada acontece),
//  se o banco estiver em localhost e se o NOME da conta for o da conta
//  de desenvolvedor. Ver docs/CONFIGURACAO-LOCAL.md.
// ============================================================

require_once __DIR__ . '/bootstrap.php';
if (is_file(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

const CONTA_DEV_NOME      = 'Dev Kosmos';
const CONTA_DEV_DIAS      = 400;      // até onde vai o histórico (dias atrás)
const CONTA_DEV_SEQUENCIA = 365;      // dias seguidos de estudo
const CONTA_DEV_META      = 120;      // meta diária de foco (minutos)

/** O banco é local? A conta de desenvolvedor nunca existe fora dele. */
function contaDevBancoLocal(): bool {
    return defined('DB_HOST') && in_array(DB_HOST, ['localhost', '127.0.0.1', '::1'], true);
}

/**
 * Chame ANTES de registrarAcesso(), no login e nas páginas da dashboard.
 * Se a conta for a de desenvolvedor e ainda não tiver sido renovada
 * hoje, repreenche tudo. Devolve true se renovou.
 * Para qualquer outra conta é só uma comparação de texto: não consulta nada.
 */
function contaDevRenovar(PDO $pdo, int $id, string $email, string $nome): bool {
    if (!defined('CONTA_DEV_EMAIL') || CONTA_DEV_EMAIL === ''
        || strcasecmp($email, CONTA_DEV_EMAIL) !== 0
        || $nome !== CONTA_DEV_NOME
        || !contaDevBancoLocal()) {
        return false;
    }

    $q = $pdo->prepare('SELECT ultimo_acesso IS NULL OR ultimo_acesso < CURDATE() FROM usuarios WHERE id = ?');
    $q->execute([$id]);
    if (!(int) $q->fetchColumn()) {
        return false;   // já renovada hoje
    }

    $pdo->beginTransaction();
    try {
        contaDevPreencher($pdo, $id);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        registrarErro($e, 'conta de desenvolvedor');
        return false;
    }
}

/**
 * Apaga os dados da conta $id e preenche tudo de novo, com as datas
 * a partir de hoje. Não abre transação (quem chama abre). Devolve o
 * número de questões certas, para o resumo do script.
 */
function contaDevPreencher(PDO $pdo, int $id): int {
    // ---------- Limpa (os filhos vão junto pelo ON DELETE CASCADE) ----------
    foreach (['resumos', 'resumo_cadernos', 'flashcard_decks', 'provas', 'exercicio_respostas',
              'exercicio_materias', 'historico_xp', 'pomodoro_sessoes', 'usuario_conquistas',
              'usuario_preferencias', 'usuario_progresso'] as $tabela) {
        $pdo->prepare("DELETE FROM `$tabela` WHERE usuario_id = ?")->execute([$id]);
    }
    $pdo->prepare('UPDATE usuarios SET ultimo_acesso = CURDATE(), sequencia = ?,
                                       criado_em = NOW() - INTERVAL ? DAY
                    WHERE id = ?')
        ->execute([CONTA_DEV_SEQUENCIA, CONTA_DEV_DIAS, $id]);

    mt_srand(20261023);   // mesma semente = mesmos dados a cada renovação
    // ---------- Preferências (onboarding feito, recompensas máximas equipadas) ----------
    // Notificações desligadas: o script de e-mails não deve escrever para .local
    $pdo->prepare("INSERT INTO usuario_preferencias
                     (usuario_id, avatar_cor, avatar_moldura, avatar_emblema, pomo_foco, pomo_pausa,
                      pomo_pausa_longa, meta_diaria, objetivo, ano_escolar, onboarding_completo,
                      materias, notif_lembrete, notif_resumo)
                   VALUES (?, 'ouro', 'bigbang', 'orion', 25, 5, 15, ?, 'vestibular_enem', '3º ano', 1,
                           'Matemática,Biologia,Química,História', 0, 0)")
        ->execute([$id, CONTA_DEV_META]);

    // ---------- Pomodoro: ~13 meses de dias de todo tipo ----------
    $minutosPorDia = contaDevPlanoDeFoco();
    $sessoes = [];   // [diasAtras, minutos, minutoDoDia | null (=hoje)]
    foreach ($minutosPorDia as $diasAtras => $total) {
        $hora = 8 * 60 + mt_rand(0, 90);           // começa entre 8h e 9h30
        $i = 0;
        while ($total > 0) {
            $m = $total >= 50 && mt_rand(0, 2) === 0 ? 50 : min(25, $total);
            if ($m < 15 && $total > 15) $m = 15;
            $sessoes[] = [$diasAtras, $m, $diasAtras === 0 ? null : $hora + $m];
            $hora += $m + mt_rand(5, 40);
            $total -= $m;
            $i++;
        }
    }
    $insere = $pdo->prepare('INSERT INTO pomodoro_sessoes (usuario_id, minutos, fim_em, dia)
                             VALUES (?, ?, ?, DATE(?))');
    $totalHoje = count(array_filter($sessoes, fn($s) => $s[0] === 0));
    $posHoje = 0;
    $agora = (int) $pdo->query('SELECT UNIX_TIMESTAMP()')->fetchColumn();
    foreach ($sessoes as $s) {
        if ($s[2] === null) {
            // hoje: sessões que já terminaram, a última 5 min atrás e as
            // anteriores de 35 em 35 min (cada uma num horário diferente)
            $fim = date('Y-m-d H:i:s', $agora - 60 * (5 + 35 * ($totalHoje - 1 - $posHoje)));
            $posHoje++;
            if (date('Y-m-d', strtotime($fim)) !== date('Y-m-d', $agora)) continue;   // madrugada: não vira ontem
        } else {
            $fim = date('Y-m-d', $agora - 86400 * $s[0]) . ' ' . sprintf('%02d:%02d:00', intdiv($s[2], 60) % 24, $s[2] % 60);
        }
        $insere->execute([$id, $s[1], $fim, $fim]);
    }

    // ---------- Cadernos e resumos ----------
    $cadernos = [
        ['Funções e Gráficos', 'Matemática', 'azul',    '📐', 'Do 1º grau à exponencial'],
        ['Citologia',          'Biologia',   'verde',   '🧬', 'Célula, organelas e divisão'],
        ['Química Orgânica',   'Química',    'rosa',    '🧪', 'Funções orgânicas e isomeria'],
        ['Brasil Império',     'História',   'laranja', '🏛️', '1822 a 1889'],
        ['Redação ENEM',       'Redação',    'roxo',    '📝', 'Estrutura e repertório'],
        ['Mecânica',           'Física',     'ciano',   '🧲', 'Cinemática e leis de Newton'],
    ];
    $cadernoIds = [];
    $insCad = $pdo->prepare('INSERT INTO resumo_cadernos (usuario_id, nome, materia, cor, icone, descricao, ordem, criado_em)
                             VALUES (?, ?, ?, ?, ?, ?, ?, NOW() - INTERVAL ? DAY)');
    foreach ($cadernos as $i => $c) {
        $insCad->execute([$id, $c[0], $c[1], $c[2], $c[3], $c[4], $i + 1, 380 - $i * 30]);
        $cadernoIds[$c[1]] = (int) $pdo->lastInsertId();
    }
    $insRes = $pdo->prepare('INSERT INTO resumos (usuario_id, caderno_id, titulo, materia, corpo, criado_em, atualizado_em)
                             VALUES (?, ?, ?, ?, ?, NOW() - INTERVAL ? DAY, NOW() - INTERVAL ? DAY)');
    foreach (contaDevResumos() as $i => [$materia, $titulo, $corpo, $solto]) {
        $dias = 360 - $i * 22;
        $insRes->execute([$id, $solto ? null : ($cadernoIds[$materia] ?? null), $titulo, $materia, $corpo, $dias, max(0, $dias - 10)]);
    }

    // ---------- Flashcards: cartões novos, vencidos, em dia e dominados ----------
    $insDeck = $pdo->prepare('INSERT INTO flashcard_decks (usuario_id, nome, materia, cor, criado_em, revisoes, ultima_revisao)
                              VALUES (?, ?, ?, ?, NOW() - INTERVAL ? DAY, ?, NOW() - INTERVAL ? DAY)');
    $insCartao = $pdo->prepare('INSERT INTO flashcard_cartoes
                                  (deck_id, frente, verso, ordem, criado_em, revisoes, acertos, erros,
                                   ultima_revisao, ultimo_resultado, intervalo, facilidade, proxima_revisao)
                                VALUES (?, ?, ?, ?, NOW() - INTERVAL ? DAY, ?, ?, ?, ?, ?, ?, ?,
                                        IF(? IS NULL, NULL, CURDATE() + INTERVAL ? DAY))');
    foreach (contaDevBaralhos() as $d => [$nome, $materia, $cor, $cartoes]) {
        $insDeck->execute([$id, $nome, $materia, $cor, 350 - $d * 40, 30 + $d * 12, $d]);
        $deckId = (int) $pdo->lastInsertId();
        foreach ($cartoes as $o => [$frente, $verso]) {
            // ciclo de estados: novo, vencido, em dia (perto), dominado (longe)
            $estado = $o % 4;
            if ($estado === 0 && $o < 8) {   // os cartões 1 e 5 de cada baralho nunca foram estudados
                $insCartao->execute([$deckId, $frente, $verso, $o + 1, 20, 0, 0, 0, null, null, 0, 2.50, null, 0]);
                continue;
            }
            $revisoes = mt_rand(6, 20);
            $acertos  = (int) round($revisoes * ($estado === 3 ? .9 : .7));
            $proxima  = [1 => -mt_rand(0, 3), 2 => mt_rand(1, 6), 3 => mt_rand(20, 120), 0 => mt_rand(2, 10)][$estado];
            $intervalo = max(1, abs($proxima));
            $facil = [1 => 2.1, 2 => 2.5, 3 => 2.8, 0 => 2.4][$estado];
            $ultima = date('Y-m-d H:i:s', $agora - 86400 * mt_rand(1, 15));
            $insCartao->execute([$deckId, $frente, $verso, $o + 1, 300, $revisoes, $acertos, $revisoes - $acertos,
                                 $ultima, $estado === 1 ? 0 : 1, $intervalo, $facil, 1, $proxima]);
        }
    }

    // ---------- Provas: hoje, amanhã, em dias, e as que já passaram ----------
    $insProva = $pdo->prepare('INSERT INTO provas (usuario_id, titulo, materia, data, anotacoes, nota, concluida_em, criado_em)
                               VALUES (?, ?, ?, CURDATE() + INTERVAL ? DAY, ?, ?, ?, NOW() - INTERVAL 30 DAY)');
    $insTopico = $pdo->prepare('INSERT INTO prova_topicos (prova_id, texto, feito, ordem) VALUES (?, ?, ?, ?)');
    foreach (contaDevProvas() as [$titulo, $materia, $em, $anot, $nota, $topicos]) {
        $concluida = $em < 0 && $nota !== null ? date('Y-m-d H:i:s', $agora + 86400 * $em + 7200) : null;
        $insProva->execute([$id, $titulo, $materia, $em, $anot, $nota, $concluida]);
        $provaId = (int) $pdo->lastInsertId();
        foreach ($topicos as $o => [$texto, $feito]) {
            $insTopico->execute([$provaId, $texto, $feito ? 1 : 0, $o + 1]);
        }
    }

    // ---------- Exercícios: matérias, listas e respostas ----------
    $insMat = $pdo->prepare('INSERT INTO exercicio_materias (usuario_id, nome, materia, cor, icone, descricao, ordem, criado_em)
                             VALUES (?, ?, ?, ?, ?, ?, ?, NOW() - INTERVAL ? DAY)');
    $insEx = $pdo->prepare('INSERT INTO exercicios (usuario_id, materia_id, titulo, conteudo, dificuldade, criado_em)
                            VALUES (?, ?, ?, ?, ?, NOW() - INTERVAL ? DAY)');
    $insResp = $pdo->prepare('INSERT INTO exercicio_respostas (usuario_id, exercicio_id, questao, escolhida, acertou, dia, respondido_em)
                              VALUES (?, ?, ?, ?, ?, CURDATE() - INTERVAL ? DAY, NOW() - INTERVAL ? DAY)');
    $acertosEx = 0;
    foreach (contaDevExercicios() as $m => [$nome, $materia, $cor, $icone, $desc, $listas]) {
        $insMat->execute([$id, $nome, $materia, $cor, $icone, $desc, $m + 1, 300 - $m * 50]);
        $matId = (int) $pdo->lastInsertId();
        foreach ($listas as $l => [$titulo, $dificuldade, $questoes]) {
            $conteudo = json_encode(['questoes' => array_map(fn($q, $n) => $q + ['numero' => $n + 1, 'dificuldade' => $dificuldade],
                                                             $questoes, array_keys($questoes))], JSON_UNESCAPED_UNICODE);
            $insEx->execute([$id, $matId, $titulo, $conteudo, $dificuldade, 200 - $l * 30]);
            $exId = (int) $pdo->lastInsertId();
            // a lista foi praticada em vários dias; no primeiro deles, sem errar nada (lista perfeita)
            foreach ([90, 80, 70, 60, 52, 45, 38, 30, 24, 18, 12, 7, 4, 2, 1] as $k => $diasAtras) {
                foreach ($questoes as $qi => $q) {
                    $acertou = $k === 0 || mt_rand(0, 9) < 8;
                    $escolhida = $acertou ? $q['correta'] : ($q['correta'] + 1) % count($q['alts']);
                    $insResp->execute([$id, $exId, $qi, $escolhida, $acertou ? 1 : 0, $diasAtras + $m, $diasAtras + $m]);
                    $acertosEx += $acertou ? 1 : 0;
                }
            }
        }
    }

    // ---------- XP: um histórico de ~13 meses que soma o máximo ----------
    $xpTotal = contaDevHistoricoXp($pdo, $id, $minutosPorDia, $agora);

    $nivelMax = $pdo->query('SELECT nivel, titulo FROM niveis_config ORDER BY nivel DESC LIMIT 1')->fetch();
    $pdo->prepare('INSERT INTO usuario_progresso (usuario_id, xp_atual, nivel_atual, titulo_atual, streak_dias, ultimo_dia_ativo)
                   VALUES (?, ?, ?, ?, ?, CURDATE())')
        ->execute([$id, $xpTotal, $nivelMax['nivel'], $nivelMax['titulo'], CONTA_DEV_SEQUENCIA]);

    // ---------- Todas as conquistas, desbloqueadas ao longo do tempo ----------
    $pdo->prepare('INSERT INTO usuario_conquistas (usuario_id, conquista_id, desbloqueado_em)
                   SELECT ?, id, NOW() - INTERVAL (? - ordem * 15) DAY FROM conquistas')
        ->execute([$id, CONTA_DEV_DIAS - 30]);

    return $acertosEx;
}

/* ============================================================
   Os dados
   ============================================================ */

/**
 * Minutos de foco por dia, de CONTA_DEV_DIAS dias atrás até hoje (chave =
 * dias atrás). Tem de tudo: dias vazios, leves, na meta, acima dela e
 * maratonas. A semana atual é montada à mão para o gráfico "Esta
 * semana" mostrar cada caso lado a lado.
 */
function contaDevPlanoDeFoco(): array {
    $dias = [];
    for ($d = CONTA_DEV_DIAS; $d >= 1; $d--) {
        $sorteio = mt_rand(1, 100);
        $dias[$d] = match (true) {
            $sorteio <= 12 => 0,                               // dia sem foco
            $sorteio <= 30 => 25,                              // um Pomodoro só
            $sorteio <= 60 => [50, 75, 90][mt_rand(0, 2)],     // abaixo da meta
            $sorteio <= 85 => [120, 125, 150][mt_rand(0, 2)],  // na meta
            default        => [200, 250, 300][mt_rand(0, 2)],  // maratona
        };
    }
    // Esta semana (domingo a hoje): um de cada
    $diaDaSemana = (int) date('w');          // 0 = domingo
    $semana = [0, 25, 120, 300, 75, 150, 50];
    for ($i = 0; $i < $diaDaSemana; $i++) {
        $dias[$diaDaSemana - $i] = $semana[$i];
    }
    $dias[0] = 45;                            // hoje: começou, ainda longe da meta
    return $dias;
}

/**
 * Grava o histórico de XP dia a dia e devolve o total. O total passa
 * um pouco do XP do nível máximo (a barra fica cheia). Cada dia tem as
 * origens de verdade (sequência, pomodoro, cartões, exercícios...),
 * para as telas que contam por origem mostrarem algo coerente.
 */
function contaDevHistoricoXp(PDO $pdo, int $id, array $minutosPorDia, int $agora): int {
    $xpMax = (int) $pdo->query('SELECT MAX(xp_necessario) FROM niveis_config')->fetchColumn();
    $alvo  = $xpMax + 4321;

    $linhas = [];
    foreach ($minutosPorDia as $d => $minutos) {
        $quando = $d === 0
            ? date('Y-m-d H:i:s', $agora - 60)
            : date('Y-m-d', $agora - 86400 * $d) . ' 20:00:00';
        if ($d < CONTA_DEV_SEQUENCIA) {
            $linhas[] = [25 + min(50, 10 * intdiv(CONTA_DEV_SEQUENCIA - $d, 7)), 'streak_diario', $quando];
        }
        if ($minutos >= 15) {
            $linhas[] = [min(400, 50 * intdiv($minutos, 25)), 'pomodoro_concluido', $quando];
        }
        $linhas[] = [mt_rand(20, 50) * 5, 'flashcard_acerto', $quando];
        $linhas[] = [30, 'sessao_flashcards', $quando];
        $linhas[] = [mt_rand(5, 15) * 20, 'exercicio_acerto', $quando];
        if ($d % 9 === 0)  $linhas[] = [40, 'conteudo_criado', $quando];
        if ($d % 4 === 0)  $linhas[] = [10, 'orion_ia', $quando];
    }
    $soma = array_sum(array_column($linhas, 0));

    // O que falta para o alvo vai, igualmente, para as questões certas
    // (a origem com o maior teto diário no app)
    $falta = max(0, $alvo - $soma);
    $porDia = intdiv($falta, count($minutosPorDia));
    foreach ($minutosPorDia as $d => $_) {
        $quando = $d === 0 ? date('Y-m-d H:i:s', $agora - 30) : date('Y-m-d', $agora - 86400 * $d) . ' 21:00:00';
        $linhas[] = [$porDia, 'exercicio_acerto', $quando];
    }
    $linhas[] = [$alvo - $soma - $porDia * count($minutosPorDia), 'exercicio_acerto', date('Y-m-d H:i:s', $agora - 20)];

    foreach (array_chunk($linhas, 400) as $lote) {
        $sql = 'INSERT INTO historico_xp (usuario_id, quantidade_xp, origem_acao, detalhes_json, criado_em) VALUES '
             . implode(',', array_fill(0, count($lote), '(?, ?, ?, \'{"dev":true}\', ?)'));
        $valores = [];
        foreach ($lote as [$xp, $origem, $quando]) {
            array_push($valores, $id, $xp, $origem, $quando);
        }
        $pdo->prepare($sql)->execute($valores);
    }
    return $alvo;
}

/** [matéria, título, corpo, solto (sem caderno)?] — o ==destaque== alimenta o Modo revisão. */
function contaDevResumos(): array {
    return [
        ['Matemática', 'Função do 1º grau', "Forma geral: f(x) = ax + b, com a ≠ 0.\n\n• a é o ==coeficiente angular== (inclinação da reta)\n• b é o ==coeficiente linear== (onde a reta corta o eixo y)\n• Raiz: x = -b/a\n\nSe a > 0 a função é ==crescente==; se a < 0, ==decrescente==.", false],
        ['Matemática', 'Função exponencial', "f(x) = aˣ, com a > 0 e a ≠ 1.\n\nSe a > 1, cresce; se 0 < a < 1, decresce.\nO gráfico nunca toca o eixo x: ele é uma ==assíntota horizontal==.", false],
        ['Biologia', 'Organelas celulares', "• ==Mitocôndria==: respiração celular, produz ATP\n• ==Ribossomo==: síntese de proteínas\n• ==Complexo de Golgi==: empacota e secreta substâncias\n• Lisossomo: digestão intracelular\n• Cloroplasto (só em plantas): fotossíntese", false],
        ['Biologia', 'Mitose x Meiose', "Mitose: 1 célula → ==2 células iguais== (2n → 2n). Crescimento e reparo.\n\nMeiose: 1 célula → ==4 células com metade dos cromossomos== (2n → n). Forma gametas e gera variabilidade genética (crossing-over).", false],
        ['Química', 'Funções orgânicas', "• Álcool: ==OH ligado a carbono saturado==\n• Aldeído: carbonila na ponta da cadeia\n• Cetona: carbonila no meio da cadeia\n• Ácido carboxílico: ==COOH==\n• Éster: ácido + álcool → éster + água", false],
        ['Química', 'Isomeria', "Mesma fórmula molecular, estruturas diferentes.\n\nPlana: de cadeia, posição, função, metameria, tautomeria.\nEspacial: ==geométrica (cis/trans)== e ==óptica (carbono quiral)==.", false],
        ['História', 'Independência do Brasil', "7 de setembro de 1822, às margens do Ipiranga.\n\nCausas: volta de D. João VI a Portugal, tentativa de ==recolonização== pelas Cortes e o ==Dia do Fico== (9/1/1822).\nO Brasil virou monarquia, com D. Pedro I.", false],
        ['História', 'Segundo Reinado', "1840 a 1889 — ==Golpe da Maioridade== coloca D. Pedro II no trono aos 14 anos.\n\nCafé no Vale do Paraíba, Guerra do Paraguai (1864–1870), leis abolicionistas e a ==Lei Áurea== (1888).", false],
        ['Redação', 'Estrutura da dissertação', "Introdução: tema + ==tese==\nDesenvolvimento 1 e 2: um argumento cada, com repertório\nConclusão: ==proposta de intervenção== com agente, ação, meio, finalidade e detalhamento.", false],
        ['Redação', 'Repertórios coringa', "• Constituição Federal (art. 6º: direitos sociais)\n• Zygmunt Bauman — ==modernidade líquida==\n• Hannah Arendt — banalidade do mal\n• Durkheim — fato social", false],
        ['Física', 'Leis de Newton', "1ª: ==inércia== — sem força resultante, o corpo mantém o estado.\n2ª: ==F = m · a==\n3ª: ==ação e reação== — mesma intensidade, sentidos opostos, corpos diferentes.", false],
        ['Física', 'MRUV', "v = v₀ + at\nS = S₀ + v₀t + at²/2\n==Torricelli==: v² = v₀² + 2aΔS", false],
        ['Sociologia', 'Durkheim, Weber e Marx', "Durkheim: ==fato social== (exterior, coercitivo, geral)\nWeber: ==ação social== e tipos ideais\nMarx: ==luta de classes== e mais-valia", true],
        ['Inglês', 'Present perfect', "have/has + particípio.\nUsa-se para ações com ==ligação com o presente==: I have studied a lot this week.", true],
        ['Geografia', 'Climas do Brasil', "Equatorial (Amazônia), Tropical, ==Semiárido== (sertão nordestino), Tropical de altitude e ==Subtropical== (Sul).", true],
    ];
}

/** [nome, matéria, cor, [[frente, verso], ...]] */
function contaDevBaralhos(): array {
    return [
        ['Citologia', 'Biologia', 'verde', [
            ['Função da mitocôndria?', 'Respiração celular (produz ATP)'], ['Função do ribossomo?', 'Síntese de proteínas'],
            ['O que faz o lisossomo?', 'Digestão intracelular'], ['Onde ocorre a fotossíntese?', 'No cloroplasto'],
            ['Mitose gera quantas células?', '2, iguais à original'], ['Meiose gera quantas células?', '4, com metade dos cromossomos'],
            ['O que é crossing-over?', 'Troca de trechos entre cromossomos homólogos na meiose'], ['Função do Golgi?', 'Empacotar e secretar substâncias'],
            ['Célula procarionte tem núcleo?', 'Não: o DNA fica disperso no citoplasma'], ['O que é osmose?', 'Passagem de água do meio menos para o mais concentrado'],
            ['Retículo rugoso tem o quê?', 'Ribossomos aderidos'], ['Membrana plasmática é...', 'Semipermeável (bicamada lipídica)'],
        ]],
        ['Funções orgânicas', 'Química', 'rosa', [
            ['Grupo do álcool?', '–OH em carbono saturado'], ['Grupo do ácido carboxílico?', '–COOH'],
            ['Aldeído x cetona?', 'Aldeído: carbonila na ponta; cetona: no meio'], ['Ácido + álcool dá...', 'Éster + água (esterificação)'],
            ['O que é carbono quiral?', 'Carbono com 4 ligantes diferentes'], ['Isomeria cis/trans exige...', 'Ligação dupla ou ciclo com ligantes diferentes'],
            ['Fórmula do metano?', 'CH₄'], ['Amina deriva de...', 'Amônia (NH₃)'],
            ['Fenol tem –OH em...', 'Anel aromático'], ['Hidrocarboneto tem só...', 'Carbono e hidrogênio'],
        ]],
        ['Verbos irregulares', 'Inglês', 'azul', [
            ['go', 'went — gone'], ['take', 'took — taken'], ['write', 'wrote — written'], ['see', 'saw — seen'],
            ['begin', 'began — begun'], ['bring', 'brought — brought'], ['think', 'thought — thought'], ['know', 'knew — known'],
            ['speak', 'spoke — spoken'], ['choose', 'chose — chosen'], ['forget', 'forgot — forgotten'], ['drive', 'drove — driven'],
        ]],
        ['Brasil Império', 'História', 'laranja', [
            ['Data da Independência?', '7 de setembro de 1822'], ['O que foi o Dia do Fico?', 'D. Pedro decide ficar no Brasil (9/1/1822)'],
            ['Golpe da Maioridade?', 'D. Pedro II assume aos 14 anos (1840)'], ['Lei Áurea?', 'Abolição da escravidão, 13/5/1888'],
            ['Lei Eusébio de Queirós?', 'Fim do tráfico negreiro (1850)'], ['Guerra do Paraguai?', '1864 a 1870'],
            ['Proclamação da República?', '15 de novembro de 1889'], ['Período Regencial?', '1831 a 1840'],
        ]],
        ['Fórmulas de Física', 'Física', 'ciano', [
            ['2ª lei de Newton', 'F = m · a'], ['Velocidade no MRUV', 'v = v₀ + at'], ['Torricelli', 'v² = v₀² + 2aΔS'],
            ['Energia cinética', 'Ec = m·v²/2'], ['Energia potencial gravitacional', 'Ep = m·g·h'], ['Peso', 'P = m · g'],
            ['Trabalho', 'τ = F · d · cos θ'], ['Potência', 'P = τ / Δt'],
        ]],
    ];
}

/** [título, matéria, dias até (negativo = já passou), anotações, nota, [[tópico, feito], ...]] */
function contaDevProvas(): array {
    return [
        ['Simulado de Matemática', 'Matemática', 0, 'Levar calculadora? Não pode.', null,
            [['Função do 1º grau', true], ['Função exponencial', true], ['Logaritmo', false]]],
        ['Prova de Biologia', 'Biologia', 1, 'Capítulos 3 e 4 do livro.', null,
            [['Organelas', true], ['Mitose e meiose', true], ['Membrana plasmática', false], ['Osmose', false]]],
        ['Química — 2º bimestre', 'Química', 3, null, null,
            [['Funções orgânicas', true], ['Isomeria plana', false], ['Isomeria óptica', false]]],
        ['Redação', 'Redação', 7, 'Tema provável: meio ambiente.', null,
            [['Estrutura', true], ['Proposta de intervenção', false]]],
        ['História do Brasil', 'História', 14, null, null,
            [['Independência', true], ['Primeiro Reinado', false], ['Regências', false], ['Segundo Reinado', false]]],
        ['ENEM — 1º dia', 'Redação', 30, 'Linguagens, Humanas e Redação.', null,
            [['Simulado completo', false], ['Revisar repertórios', false]]],
        ['Física — Mecânica', 'Física', -5, null, 8.5,
            [['Leis de Newton', true], ['MRUV', true]]],
        ['Inglês', 'Inglês', -20, null, 6.0,
            [['Present perfect', true], ['Verbos irregulares', true]]],
        ['Geografia', 'Geografia', -40, 'Ainda sem nota.', null,
            [['Climas do Brasil', true]]],
    ];
}

/** [nome, matéria, cor, ícone, descrição, [[título, dificuldade, [questão...]], ...]] */
function contaDevExercicios(): array {
    $q = fn($e, $alts, $c, $dica = '') => ['enunciado' => $e, 'alts' => $alts, 'correta' => $c, 'dica' => $dica];
    return [
        ['Funções', 'Matemática', 'azul', '📐', 'Funções do 1º e 2º grau', [
            ['Lista 1 — Função afim', 'Fácil', [
                $q('Qual é a raiz de f(x) = 2x − 8?', ['2', '4', '8', '−4', '16'], 1, 'Iguale f(x) a zero.'),
                $q('Em f(x) = −3x + 5, a função é:', ['Crescente', 'Decrescente', 'Constante', 'Quadrática', 'Exponencial'], 1),
                $q('Onde f(x) = x + 7 corta o eixo y?', ['(0, 7)', '(7, 0)', '(−7, 0)', '(0, −7)', '(1, 8)'], 0),
                $q('Se f(x) = 5x, quanto vale f(3)?', ['8', '15', '35', '53', '0,6'], 1),
            ]],
            ['Lista 2 — Função quadrática', 'Médio', [
                $q('Quantas raízes reais tem x² + 1 = 0?', ['Nenhuma', 'Uma', 'Duas', 'Três', 'Infinitas'], 0, 'Olhe o sinal do delta.'),
                $q('O vértice de x² − 4x + 3 está em x =', ['1', '2', '3', '4', '−2'], 1),
                $q('A parábola de −x² + 2 tem concavidade para:', ['Cima', 'Baixo', 'Direita', 'Esquerda', 'Não tem'], 1),
            ]],
        ]],
        ['Citologia', 'Biologia', 'verde', '🧬', 'Célula e divisão celular', [
            ['Organelas', 'Fácil', [
                $q('Qual organela produz ATP?', ['Ribossomo', 'Mitocôndria', 'Lisossomo', 'Golgi', 'Centríolo'], 1),
                $q('Onde ocorre a síntese de proteínas?', ['Ribossomo', 'Lisossomo', 'Vacúolo', 'Núcleo', 'Golgi'], 0),
                $q('Qual organela existe só em células vegetais?', ['Mitocôndria', 'Ribossomo', 'Cloroplasto', 'Lisossomo', 'Golgi'], 2),
            ]],
        ]],
        ['Brasil Império', 'História', 'laranja', '🏛️', '1822 a 1889', [
            ['Independência e Império', 'Médio', [
                $q('Em que ano foi proclamada a Independência?', ['1808', '1815', '1822', '1831', '1889'], 2),
                $q('A Lei Áurea foi assinada em:', ['1850', '1871', '1885', '1888', '1889'], 3),
                $q('O Golpe da Maioridade colocou no trono:', ['D. João VI', 'D. Pedro I', 'D. Pedro II', 'Deodoro', 'Isabel'], 2),
            ]],
        ]],
        ['Orgânica', 'Química', 'rosa', '🧪', 'Funções e isomeria', [
            ['Funções orgânicas', 'Difícil', [
                $q('O grupo –COOH caracteriza:', ['Álcool', 'Aldeído', 'Ácido carboxílico', 'Éster', 'Cetona'], 2),
                $q('Ácido + álcool produz:', ['Éter', 'Éster', 'Amina', 'Cetona', 'Fenol'], 1),
                $q('Carbono quiral tem:', ['2 ligantes iguais', '4 ligantes diferentes', 'Ligação tripla', 'Só hidrogênios', 'Carga positiva'], 1),
            ]],
        ]],
    ];
}
