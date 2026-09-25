<?php
// ============================================================
//  KOSMOS — Progressão: XP, níveis, sequência e conquistas
//  Arquivo: Backend/php/ProgressoService.php
//
//  Um serviço só para tudo que mexe no XP de alguém. Os endpoints
//  de estudo (Pomodoro, flashcards, exercícios, resumos, Orion)
//  não somam XP por conta própria: descrevem O QUE aconteceu e este
//  serviço decide QUANTO vale, dentro dos limites.
//
//  Uso típico, DEPOIS de o endpoint gravar a ação dele (fora de
//  qualquer transação aberta):
//
//      require_once __DIR__ . '/ProgressoService.php';
//      $progresso = progressoRegistrar($pdo, $uid, [
//          ['acao' => 'pomodoro_concluido', 'xp' => ProgressoService::XP['pomodoro_concluido']],
//      ]);
//      ... responder([... , 'progresso' => $progresso]);
//
//  O `progresso` que volta é o que o js/progresso.js usa para o aviso
//  de "+50 XP", para a barra e para o modal de subida de nível. Todo
//  endpoint que dá XP devolve essa chave; a tela reage sozinha.
//
//  Garantias:
//   - Atômico: tudo acontece numa transação, com a linha de progresso
//     da pessoa travada (SELECT ... FOR UPDATE). Duas abas ganhando XP
//     ao mesmo tempo entram em fila — os tetos diários não estouram.
//   - Anti-farm: cada origem tem teto por dia e intervalo mínimo entre
//     ganhos. As checagens que dependem da ação (Pomodoro sobreposto,
//     cartão já revisado hoje, questão já respondida hoje) ficam no
//     endpoint, que é quem conhece o contexto.
//   - Datas sempre pelo relógio do MySQL (CURDATE/NOW): PHP e MySQL
//     rodam em fusos diferentes neste projeto.
//   - xp_atual = SUM(historico_xp). Nada soma XP sem deixar linha.
// ============================================================

require_once __DIR__ . '/conquistas_icones.php';

final class ProgressoService
{
    /** Quanto cada ação vale. Os endpoints usam estes valores. */
    public const XP = [
        'pomodoro_concluido' => 50,
        'flashcard_acerto'   => 5,
        'sessao_flashcards'  => 30,
        'exercicio_acerto'   => 20,
        'exercicio_erro'     => 5,    // o esforço também conta
        'conteudo_criado'    => 40,
        'orion_ia'           => 10,
    ];

    /**
     * Limites por origem. Origens do mesmo `grupo` dividem o teto e o
     * intervalo (acertar e errar questão são o mesmo "orçamento").
     *   limite_dia    — XP máximo por dia no grupo
     *   intervalo_seg — tempo mínimo entre dois ganhos do grupo
     */
    public const REGRAS = [
        'pomodoro_concluido' => ['rotulo' => 'Pomodoro concluído',  'grupo' => 'pomodoro',   'limite_dia' => 400, 'intervalo_seg' => 0],
        'flashcard_acerto'   => ['rotulo' => 'Cartões acertados',    'grupo' => 'fc_acerto',  'limite_dia' => 250, 'intervalo_seg' => 0],
        'sessao_flashcards'  => ['rotulo' => 'Sessão de flashcards', 'grupo' => 'fc_sessao',  'limite_dia' => 150, 'intervalo_seg' => 120],
        'exercicio_acerto'   => ['rotulo' => 'Questão certa',        'grupo' => 'exercicios', 'limite_dia' => 300, 'intervalo_seg' => 2],
        'exercicio_erro'     => ['rotulo' => 'Questão respondida',   'grupo' => 'exercicios', 'limite_dia' => 300, 'intervalo_seg' => 2],
        'conteudo_criado'    => ['rotulo' => 'Novo resumo',          'grupo' => 'conteudo',   'limite_dia' => 120, 'intervalo_seg' => 0],
        'orion_ia'           => ['rotulo' => 'Estudo com o Orion',   'grupo' => 'orion',      'limite_dia' => 30,  'intervalo_seg' => 0],
    ];

    /** Sequência de estudo: 25 XP no dia + 10 por semana completa, até +50. */
    public const STREAK_BASE        = 25;
    public const STREAK_BONUS       = 10;
    public const STREAK_BONUS_TETO  = 50;

    private PDO $pdo;

    /** @var array<int, array{nivel:int, xp:int, titulo:string, recompensa:string}>|null */
    private static ?array $niveis = null;

    /** Catálogo de recompensas (tabela `recompensas`), lido uma vez por requisição. */
    private static ?array $catalogo = null;

    public const TIPOS_RECOMPENSA = ['moldura' => 'Moldura', 'cor' => 'Cor', 'emblema' => 'Emblema'];
    /** Coluna de usuario_preferencias onde cada tipo fica equipado. */
    private const COLUNA_EQUIPADA = ['moldura' => 'avatar_moldura', 'cor' => 'avatar_cor', 'emblema' => 'avatar_emblema'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ============================================================
       API PÚBLICA
       ============================================================ */

    /**
     * Um ganho de XP. É o `adicionarVarios` com um item só.
     * @return array o payload de progresso (ver montarPayload)
     */
    public function adicionarXP(int $usuarioId, int $xp, string $acao, array $detalhes = []): array
    {
        return $this->adicionarVarios($usuarioId, [
            ['acao' => $acao, 'xp' => $xp, 'detalhes' => $detalhes],
        ]);
    }

    /**
     * Vários ganhos da MESMA ação de estudo, numa transação e com um
     * payload só — ex.: a sessão de flashcards rende pelos acertos e
     * pela sessão, e a tela mostra um aviso por item.
     *
     * Cada item: ['acao' => origem, 'xp' => int, 'detalhes' => array,
     *             'rotulo' => texto opcional para o aviso]
     *
     * Chamar este método já é dizer "a pessoa estudou": conta para a
     * sequência do dia mesmo que os tetos zerem o XP dos itens.
     */
    public function adicionarVarios(int $usuarioId, array $itens): array
    {
        foreach ($itens as $item) {
            $acao = $item['acao'] ?? '';
            if (!isset(self::REGRAS[$acao])) {
                throw new InvalidArgumentException("Origem de XP desconhecida: {$acao}");
            }
            if ((int) ($item['xp'] ?? 0) < 0) {
                throw new InvalidArgumentException('XP negativo não entra por aqui.');
            }
        }
        return $this->processar($usuarioId, $itens, true);
    }

    /**
     * Confere conquistas sem registrar estudo (não mexe na sequência).
     * Usado pela página de conquistas: quem já tinha histórico antes do
     * sistema existir recebe as badges na primeira visita.
     */
    public function sincronizar(int $usuarioId): array
    {
        return $this->processar($usuarioId, [], false);
    }

    /** Estado atual para a interface (barra de XP, página de conquistas). */
    public function estado(int $usuarioId): array
    {
        $this->garantirLinha($usuarioId);
        $stmt = $this->pdo->prepare(
            'SELECT xp_atual, streak_dias,
                    (ultimo_dia_ativo IS NOT NULL AND ultimo_dia_ativo >= CURDATE() - INTERVAL 1 DAY) AS streak_vivo,
                    (ultimo_dia_ativo IS NOT NULL AND ultimo_dia_ativo = CURDATE()) AS hoje
               FROM usuario_progresso WHERE usuario_id = ?'
        );
        $stmt->execute([$usuarioId]);
        $p = $stmt->fetch() ?: ['xp_atual' => 0, 'streak_dias' => 0, 'streak_vivo' => 0, 'hoje' => 0];

        // Sequência que já quebrou (último estudo antes de ontem) aparece
        // como 0 — o banco só zera quando a pessoa estudar de novo.
        $streak = $p['streak_vivo'] ? (int) $p['streak_dias'] : 0;
        return $this->montarPayload((int) $p['xp_atual'], $streak, (bool) $p['hoje']);
    }

    /**
     * Catálogo completo com o status desta pessoa: desbloqueadas (com a
     * data) e pendentes (com o progresso até o alvo).
     */
    public function listarConquistas(int $usuarioId): array
    {
        $estado = $this->estado($usuarioId);

        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.slug, c.nome, c.descricao, c.icone_svg, c.xp_bonus, c.categoria,
                    c.metrica, c.alvo,
                    uc.desbloqueado_em,
                    DAY(uc.desbloqueado_em) AS dia, MONTH(uc.desbloqueado_em) AS mes, YEAR(uc.desbloqueado_em) AS ano
               FROM conquistas c
               LEFT JOIN usuario_conquistas uc ON uc.conquista_id = c.id AND uc.usuario_id = ?
              ORDER BY FIELD(c.categoria, "foco", "memoria", "exercicios", "constancia"), c.ordem'
        );
        $stmt->execute([$usuarioId]);

        $metricas = $this->metricas($usuarioId, $estado['streak_dias'], $estado['nivel']);
        $lista = [];
        foreach ($stmt as $c) {
            $feita = $c['desbloqueado_em'] !== null;
            $valor = $feita ? (int) $c['alvo'] : min((int) $c['alvo'], $metricas($c['metrica']));
            $lista[] = $this->conquistaParaTela($c) + [
                'desbloqueada' => $feita,
                'data'         => $feita ? ['dia' => (int) $c['dia'], 'mes' => (int) $c['mes'], 'ano' => (int) $c['ano']] : null,
                'valor'        => $valor,
                'alvo'         => (int) $c['alvo'],
            ];
        }
        return $lista;
    }

    /* ============================================================
       NÚCLEO
       ============================================================ */

    private function processar(int $usuarioId, array $itens, bool $contaComoEstudo): array
    {
        $pdo = $this->pdo;
        $abriu = !$pdo->inTransaction();
        if ($abriu) {
            $pdo->beginTransaction();
        }

        try {
            $this->garantirLinha($usuarioId);

            // A trava: daqui até o COMMIT, outra requisição desta pessoa espera.
            $stmt = $pdo->prepare(
                'SELECT xp_atual, streak_dias,
                        (ultimo_dia_ativo = CURDATE())                  AS hoje,
                        (ultimo_dia_ativo = CURDATE() - INTERVAL 1 DAY) AS ontem
                   FROM usuario_progresso WHERE usuario_id = ? FOR UPDATE'
            );
            $stmt->execute([$usuarioId]);
            $linha = $stmt->fetch();

            $xpAntes    = (int) $linha['xp_atual'];
            $nivelAntes = $this->nivelDe($xpAntes)['nivel'];
            $streak     = (int) $linha['streak_dias'];
            $eventos    = [];
            $ganho      = 0;

            // 1) Os itens da ação, cada um dentro do teto do seu grupo
            foreach ($itens as $item) {
                $acao     = $item['acao'];
                $pedido   = (int) $item['xp'];
                $detalhes = $item['detalhes'] ?? [];
                $dado     = $this->aplicarLimites($usuarioId, $acao, $pedido);

                if ($dado > 0) {
                    $this->registrar($usuarioId, $dado, $acao, $detalhes);
                    $ganho += $dado;
                    $eventos[] = [
                        'tipo'   => 'acao',
                        'xp'     => $dado,
                        'rotulo' => $item['rotulo'] ?? self::REGRAS[$acao]['rotulo'],
                    ];
                }
            }

            // 2) Sequência: o primeiro estudo do dia soma o dia e rende o bônus
            if ($contaComoEstudo && !$linha['hoje']) {
                $streak = $linha['ontem'] ? $streak + 1 : 1;
                $pdo->prepare('UPDATE usuario_progresso SET streak_dias = ?, ultimo_dia_ativo = CURDATE() WHERE usuario_id = ?')
                    ->execute([$streak, $usuarioId]);

                $bonusSemana = min(self::STREAK_BONUS_TETO, self::STREAK_BONUS * intdiv($streak, 7));
                $xpStreak    = self::STREAK_BASE + $bonusSemana;
                $this->registrar($usuarioId, $xpStreak, 'streak_diario', ['dias' => $streak]);
                $ganho += $xpStreak;
                $eventos[] = [
                    'tipo'   => 'streak',
                    'xp'     => $xpStreak,
                    'rotulo' => $streak === 1 ? 'Primeiro dia de estudo' : "Sequência de {$streak} dias",
                ];
            } elseif (!$linha['hoje'] && !$linha['ontem']) {
                $streak = 0;   // só consultando: a sequência já quebrou
            }

            // 3) Conquistas — o bônus delas pode subir o nível, o que pode
            //    destravar as conquistas de nível. Poucas voltas bastam.
            $xp = $xpAntes + $ganho;
            $desbloqueadas = [];
            for ($volta = 0; $volta < 4; $volta++) {
                $novas = $this->desbloquear($usuarioId, $streak, $this->nivelDe($xp)['nivel']);
                if ($novas === []) {
                    break;
                }
                foreach ($novas as $c) {
                    $xp += $c['xp_bonus'];
                    $ganho += $c['xp_bonus'];
                    $desbloqueadas[] = $c;
                    $eventos[] = ['tipo' => 'conquista', 'xp' => $c['xp_bonus'], 'rotulo' => 'Conquista: ' . $c['nome']];
                }
            }

            // 4) Grava o total e o nível
            $nivel = $this->nivelDe($xp);
            $pdo->prepare('UPDATE usuario_progresso SET xp_atual = ?, nivel_atual = ?, titulo_atual = ? WHERE usuario_id = ?')
                ->execute([$xp, $nivel['nivel'], $nivel['titulo'], $usuarioId]);

            if ($abriu) {
                $pdo->commit();
            }

            $estudouHoje = $contaComoEstudo || (bool) $linha['hoje'];
            $payload = $this->montarPayload($xp, $streak, $estudouHoje);
            $subiu   = $nivel['nivel'] > $nivelAntes;
            return $payload + [
                'xp_ganho'                 => $ganho,
                'subiu_de_nivel'           => $subiu,
                'novo_nivel'               => $subiu ? $nivel['nivel'] : null,
                'novo_titulo'              => $subiu ? $nivel['titulo'] : null,
                'titulo_mudou'             => $subiu && $nivel['titulo'] !== $this->nivelDe($xpAntes)['titulo'],
                'recompensa'               => $subiu ? $nivel['recompensa'] : null,
                // O que os níveis atravessados agora destravaram (pode ser mais
                // de um nível de uma vez, com o bônus de uma conquista)
                'recompensas_novas'        => $subiu ? array_values(array_filter(
                    $this->catalogo(),
                    fn($r) => $r['nivel'] > $nivelAntes && $r['nivel'] <= $nivel['nivel']
                )) : [],
                'conquistas_desbloqueadas' => $desbloqueadas,
                'eventos'                  => $eventos,
            ];
        } catch (Throwable $e) {
            if ($abriu && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Quanto do XP pedido cabe hoje no grupo desta origem.
     * Devolve 0 se o teto já foi atingido ou se o último ganho do grupo
     * foi há menos que o intervalo mínimo (clique em rajada).
     */
    private function aplicarLimites(int $usuarioId, string $acao, int $pedido): int
    {
        if ($pedido <= 0) {
            return 0;
        }
        $regra   = self::REGRAS[$acao];
        $origens = array_keys(array_filter(self::REGRAS, fn($r) => $r['grupo'] === $regra['grupo']));
        $marcas  = implode(',', array_fill(0, count($origens), '?'));

        // `>` e não `>=`: criado_em tem precisão de segundo, e com `>=` um
        // ganho 2,1 s depois do anterior ainda caía dentro de "2 segundos".
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(quantidade_xp), 0) AS hoje,
                    COALESCE(SUM(criado_em > NOW() - INTERVAL ? SECOND), 0) AS recentes
               FROM historico_xp
              WHERE usuario_id = ? AND origem_acao IN ({$marcas}) AND criado_em >= CURDATE()"
        );
        $stmt->execute(array_merge([(int) $regra['intervalo_seg'], $usuarioId], $origens));
        $uso = $stmt->fetch();

        if ($regra['intervalo_seg'] > 0 && (int) $uso['recentes'] > 0) {
            return 0;
        }
        return max(0, min($pedido, $regra['limite_dia'] - (int) $uso['hoje']));
    }

    private function registrar(int $usuarioId, int $xp, string $origem, array $detalhes): void
    {
        unset($detalhes['rotulo']);
        $this->pdo->prepare(
            'INSERT INTO historico_xp (usuario_id, quantidade_xp, origem_acao, detalhes_json) VALUES (?, ?, ?, ?)'
        )->execute([
            $usuarioId,
            $xp,
            $origem,
            $detalhes === [] ? null : json_encode($detalhes, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /** Conta que existia antes deste sistema (ou foi criada depois) ganha a linha na hora. */
    private function garantirLinha(int $usuarioId): void
    {
        $this->pdo->prepare('INSERT IGNORE INTO usuario_progresso (usuario_id) VALUES (?)')->execute([$usuarioId]);
    }

    /* ============================================================
       CONQUISTAS
       ============================================================ */

    /** Desbloqueia o que já bateu o alvo; devolve só as NOVAS. */
    private function desbloquear(int $usuarioId, int $streak, int $nivel): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.slug, c.nome, c.descricao, c.icone_svg, c.xp_bonus, c.categoria, c.metrica, c.alvo
               FROM conquistas c
               LEFT JOIN usuario_conquistas uc ON uc.conquista_id = c.id AND uc.usuario_id = ?
              WHERE uc.id IS NULL
              ORDER BY c.categoria, c.ordem'
        );
        $stmt->execute([$usuarioId]);
        $pendentes = $stmt->fetchAll();
        if ($pendentes === []) {
            return [];
        }

        $metricas = $this->metricas($usuarioId, $streak, $nivel);
        $inserir  = $this->pdo->prepare('INSERT IGNORE INTO usuario_conquistas (usuario_id, conquista_id) VALUES (?, ?)');
        $novas    = [];

        foreach ($pendentes as $c) {
            if ($metricas($c['metrica']) < (int) $c['alvo']) {
                continue;
            }
            $inserir->execute([$usuarioId, $c['id']]);
            if ($inserir->rowCount() !== 1) {
                continue;
            }
            if ((int) $c['xp_bonus'] > 0) {
                $this->registrar($usuarioId, (int) $c['xp_bonus'], 'conquista', ['slug' => $c['slug']]);
            }
            $novas[] = $this->conquistaParaTela($c);
        }
        return $novas;
    }

    /**
     * As métricas das regras de conquista. Preguiçosas e memorizadas:
     * uma métrica só é consultada se alguma conquista PENDENTE depende
     * dela — quem já tem todas as de Pomodoro não paga essa consulta.
     */
    private function metricas(int $usuarioId, int $streak, int $nivel): callable
    {
        $pdo   = $this->pdo;
        $cache = ['streak' => $streak, 'nivel' => $nivel];
        $um = function (string $sql) use ($pdo, $usuarioId): int {
            $s = $pdo->prepare($sql);
            $s->execute([$usuarioId]);
            return (int) $s->fetchColumn();
        };

        $calcular = [
            'pomodoros'     => fn() => $um('SELECT COUNT(*) FROM pomodoro_sessoes WHERE usuario_id = ? AND minutos >= 15'),
            'pomodoros_dia' => fn() => $um('SELECT COALESCE(MAX(n), 0) FROM (SELECT COUNT(*) AS n FROM pomodoro_sessoes
                                             WHERE usuario_id = ? AND minutos >= 15 GROUP BY dia) t'),
            'fc_sessoes'    => fn() => $um('SELECT COALESCE(SUM(revisoes), 0) FROM flashcard_decks WHERE usuario_id = ?'),
            'fc_acertos'    => fn() => $um('SELECT COALESCE(SUM(c.acertos), 0) FROM flashcard_cartoes c
                                             JOIN flashcard_decks d ON d.id = c.deck_id WHERE d.usuario_id = ?'),
            'resumos'       => fn() => $um('SELECT COUNT(*) FROM resumos WHERE usuario_id = ?'),
            'ex_acertos'    => fn() => $um('SELECT COUNT(*) FROM exercicio_respostas WHERE usuario_id = ? AND acertou = 1'),
            'orion'         => fn() => $um("SELECT COUNT(*) FROM historico_xp WHERE usuario_id = ? AND origem_acao = 'orion_ia'"),
            'listas_perfeitas' => fn() => $this->listasPerfeitas($usuarioId),
        ];

        return function (string $nome) use (&$cache, $calcular): int {
            if (!array_key_exists($nome, $cache)) {
                $cache[$nome] = isset($calcular[$nome]) ? $calcular[$nome]() : 0;
            }
            return $cache[$nome];
        };
    }

    /** Listas em que todas as questões foram acertadas num mesmo dia. */
    private function listasPerfeitas(int $usuarioId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.melhor, e.conteudo
               FROM (SELECT exercicio_id, MAX(n) AS melhor
                       FROM (SELECT exercicio_id, dia, COUNT(DISTINCT questao) AS n
                               FROM exercicio_respostas
                              WHERE usuario_id = ? AND acertou = 1
                              GROUP BY exercicio_id, dia) r
                      GROUP BY exercicio_id) t
               JOIN exercicios e ON e.id = t.exercicio_id'
        );
        $stmt->execute([$usuarioId]);
        $perfeitas = 0;
        foreach ($stmt as $l) {
            $total = self::totalQuestoes((string) $l['conteudo']);
            if ($total > 0 && (int) $l['melhor'] >= $total) {
                $perfeitas++;
            }
        }
        return $perfeitas;
    }

    /** Quantas questões um exercício tem (o JSON guardado em `exercicios.conteudo`). */
    public static function totalQuestoes(string $conteudo): int
    {
        $json = json_decode($conteudo, true);
        return is_array($json) && isset($json['questoes']) && is_array($json['questoes'])
            ? count($json['questoes'])
            : 0;
    }

    private function conquistaParaTela(array $c): array
    {
        return [
            'slug'      => $c['slug'],
            'nome'      => $c['nome'],
            'descricao' => $c['descricao'],
            'categoria' => $c['categoria'],
            'xp_bonus'  => (int) $c['xp_bonus'],
            'icone'     => $c['icone_svg'],
            'svg'       => conquistaIconeSvg($c['icone_svg']),
        ];
    }

    /* ============================================================
       NÍVEIS
       ============================================================ */

    private function niveis(): array
    {
        if (self::$niveis === null) {
            $rows = $this->pdo->query(
                'SELECT nivel, xp_necessario, titulo, recompensa_descricao FROM niveis_config ORDER BY nivel'
            )->fetchAll();
            self::$niveis = array_map(fn($r) => [
                'nivel'      => (int) $r['nivel'],
                'xp'         => (int) $r['xp_necessario'],
                'titulo'     => $r['titulo'],
                'recompensa' => $r['recompensa_descricao'],
            ], $rows);
            if (self::$niveis === []) {
                throw new RuntimeException('niveis_config vazia — rode a migração 2026-09-25_progresso.sql.');
            }
        }
        return self::$niveis;
    }

    /** O maior nível cujo piso o XP já passou. */
    private function nivelDe(int $xp): array
    {
        $atual = $this->niveis()[0];
        foreach ($this->niveis() as $n) {
            if ($n['xp'] > $xp) {
                break;
            }
            $atual = $n;
        }
        return $atual;
    }

    private function proximoNivel(int $nivel): ?array
    {
        foreach ($this->niveis() as $n) {
            if ($n['nivel'] === $nivel + 1) {
                return $n;
            }
        }
        return null;
    }

    /**
     * O que a tela precisa para desenhar a barra. A porcentagem é DENTRO
     * do nível (a barra zera a cada nível, como nos jogos) — o total
     * sobre o próximo piso faria a barra nascer quase cheia nos níveis
     * altos e mal se mexer.
     */
    private function montarPayload(int $xp, int $streak, bool $estudouHoje = false): array
    {
        $nivel   = $this->nivelDe($xp);
        $proximo = $this->proximoNivel($nivel['nivel']);
        $pct     = $proximo === null
            ? 100
            : (int) floor(100 * ($xp - $nivel['xp']) / max(1, $proximo['xp'] - $nivel['xp']));

        return [
            'xp_total'         => $xp,
            'nivel'            => $nivel['nivel'],
            'titulo'           => $nivel['titulo'],
            'xp_nivel_atual'   => $nivel['xp'],
            'xp_proximo_nivel' => $proximo['xp'] ?? null,
            'progresso_pct'    => max(0, min(100, $pct)),
            'streak_dias'      => $streak,
            'estudou_hoje'     => $estudouHoje,
            'proxima_recompensa' => $this->proximaRecompensa($nivel['nivel']),
        ];
    }

    /* ============================================================
       RECOMPENSAS (moldura, cor e emblema do avatar)
       Liberada = nível da pessoa >= nivel_minimo. Não há tabela de
       "recompensas de cada um": o nível é a única fonte de verdade.
       ============================================================ */

    /** O catálogo, do nível mais baixo ao mais alto. Vazio se a migração
     *  de recompensas ainda não rodou — aí o resto segue sem elas. */
    public function catalogo(): array
    {
        if (self::$catalogo === null) {
            try {
                $rows = $this->pdo->query(
                    'SELECT slug, tipo, nome, descricao, nivel_minimo, valor FROM recompensas ORDER BY nivel_minimo, ordem'
                )->fetchAll();
            } catch (PDOException $e) {
                $rows = [];
            }
            self::$catalogo = array_map(fn($r) => [
                'slug'      => $r['slug'],
                'tipo'      => $r['tipo'],
                'tipo_nome' => self::TIPOS_RECOMPENSA[$r['tipo']] ?? $r['tipo'],
                'nome'      => $r['nome'],
                'descricao' => $r['descricao'],
                'nivel'     => (int) $r['nivel_minimo'],
                'valor'     => $r['valor'],
            ], $rows);
        }
        return self::$catalogo;
    }

    /** Os níveis em que o título muda: [nivel => titulo] (sem o nível 1). */
    public function marcosDeTitulo(): array
    {
        $marcos = [];
        $anterior = null;
        foreach ($this->niveis() as $n) {
            if ($anterior !== null && $n['titulo'] !== $anterior) {
                $marcos[$n['nivel']] = $n['titulo'];
            }
            $anterior = $n['titulo'];
        }
        return $marcos;
    }

    private function proximaRecompensa(int $nivel): ?array
    {
        foreach ($this->catalogo() as $r) {
            if ($r['nivel'] > $nivel) {
                return $r;
            }
        }
        return null;
    }

    /** O catálogo com o status desta pessoa: liberada? equipada? */
    public function recompensas(int $usuarioId): array
    {
        $nivel    = $this->estado($usuarioId)['nivel'];
        $equipado = $this->equipado($usuarioId);
        return array_map(fn($r) => $r + [
            'liberada' => $nivel >= $r['nivel'],
            'equipada' => ($equipado[$r['tipo']] ?? null) === $r['valor'],
        ], $this->catalogo());
    }

    /** O que está equipado agora (a cor pode ser uma das seis básicas). */
    public function equipado(int $usuarioId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT avatar_cor, avatar_moldura, avatar_emblema FROM usuario_preferencias WHERE usuario_id = ?'
            );
            $stmt->execute([$usuarioId]);
            $p = $stmt->fetch();
        } catch (PDOException $e) {
            $p = false;   // colunas ainda não migradas
        }
        return [
            'cor'     => $p['avatar_cor'] ?? 'roxo',
            'moldura' => $p['avatar_moldura'] ?? null,
            'emblema' => $p['avatar_emblema'] ?? null,
        ];
    }

    /**
     * Equipa uma recompensa (ou tira a moldura/emblema, com $slug vazio).
     * A cor exclusiva sai trocando por uma básica na aba Conta.
     * @throws DomainException com a mensagem para a pessoa
     */
    public function equipar(int $usuarioId, string $tipo, string $slug): array
    {
        if (!isset(self::COLUNA_EQUIPADA[$tipo])) {
            throw new DomainException('Tipo de recompensa inválido.');
        }
        $valor = null;
        if ($slug !== '') {
            $r = null;
            foreach ($this->catalogo() as $item) {
                if ($item['slug'] === $slug && $item['tipo'] === $tipo) {
                    $r = $item;
                }
            }
            if ($r === null) {
                throw new DomainException('Recompensa não encontrada.');
            }
            if ($this->estado($usuarioId)['nivel'] < $r['nivel']) {
                throw new DomainException("Chegue ao nível {$r['nivel']} para liberar {$r['nome']}.");
            }
            $valor = $r['valor'];
        } elseif ($tipo === 'cor') {
            throw new DomainException('Para voltar a uma cor básica, escolha na aba Conta.');
        }

        $coluna = self::COLUNA_EQUIPADA[$tipo];   // de uma lista fechada, nunca do cliente
        $this->pdo->prepare('INSERT IGNORE INTO usuario_preferencias (usuario_id) VALUES (?)')->execute([$usuarioId]);
        $this->pdo->prepare("UPDATE usuario_preferencias SET {$coluna} = ? WHERE usuario_id = ?")
            ->execute([$valor, $usuarioId]);
        return $this->equipado($usuarioId);
    }
}

/**
 * Atalho para os endpoints: registra o ganho e devolve o payload, ou
 * null se algo falhar. XP é secundário — um erro aqui (tabela ainda
 * não migrada, por exemplo) nunca pode derrubar a ação principal:
 * o Pomodoro fica gravado mesmo que o XP não entre.
 */
/**
 * A dica da barra de XP: "450 / 903 XP · faltam 453 para o nível 4".
 * O js/progresso.js monta o MESMO texto (função `dica`) quando a barra
 * muda sem recarregar — mexeu num, mexa no outro.
 */
function progressoDica(array $p): string
{
    $fmt = fn(int $n) => number_format($n, 0, ',', '.');
    if ($p['xp_proximo_nivel'] === null) {
        return $fmt($p['xp_total']) . ' XP · nível máximo';
    }
    return $fmt($p['xp_total']) . ' / ' . $fmt($p['xp_proximo_nivel']) . ' XP · faltam '
         . $fmt($p['xp_proximo_nivel'] - $p['xp_total']) . ' para o nível ' . ($p['nivel'] + 1);
}

function progressoRegistrar(PDO $pdo, int $usuarioId, array $itens): ?array
{
    if ($itens === []) {
        return null;
    }
    try {
        return (new ProgressoService($pdo))->adicionarVarios($usuarioId, $itens);
    } catch (Throwable $e) {
        error_log('[KOSMOS progresso] ' . $e->getMessage());
        return null;
    }
}
