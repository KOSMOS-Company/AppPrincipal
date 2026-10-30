<?php
// ============================================================
//  KOSMOS — Gravar um ciclo de foco (regra num lugar só)
//  Arquivo: Backend/php/pomodoro_util.php
//
//  Usado pelo pomodoro_sessao.php (o endpoint) e pelos testes com
//  banco (Backend/testes/banco.php). Fica fora do endpoint para dar
//  para testar sem HTTP nem sessão.
// ============================================================

/**
 * O ciclo declarado cabe no tempo que passou desde o Iniciar?
 *
 * $decorridos são os segundos desde o início guardado pelo
 * pomodoro_inicio.php, medidos no relógio do MySQL (null = nenhum
 * início guardado). Comparar com o relógio do SERVIDOR, e não com o
 * `fim` que o navegador manda, deixa a conta imune a um computador
 * com a hora errada. Pausas só aumentam o tempo decorrido, então
 * nunca reprovam um ciclo de verdade. A folga de 1 minuto absorve a
 * demora da requisição do Iniciar.
 */
function pomodoroCabeNoInicio(?int $decorridos, int $minutos): bool {
    return $decorridos !== null && $decorridos >= $minutos * 60 - 60;
}

/**
 * Grava o ciclo que terminou no instante $fim (epoch, segundos).
 *
 * Devolve 'sobrepoe' => true (e não grava nada) quando ele se cruza
 * no tempo com outro ciclo já gravado: duas sessões "terminando" em
 * horários que se cruzam não são duas sessões de estudo, são uma
 * forjada. Antes isso só tirava o XP, mas a sessão entrava e contava
 * para conquistas, missões e a frequência do mês.
 *
 * 'novo' => false quando o ciclo já existia (mesmo fim): duas abas
 * descobrem o mesmo fim, e um recarregamento redescobre. Os minutos
 * já gravados NÃO mudam — reenviar o mesmo fim com "minutos: 180"
 * transformava um ciclo de 15 min em 3 horas.
 *
 * @return array{novo: bool, sobrepoe: bool}
 */
function pomodoroGravar(PDO $pdo, int $usuarioId, int $minutos, int $fim): array {
    /* Conferir a sobreposição e gravar são um passo só: a linha do
       usuário fica travada até o commit, então duas requisições
       disparadas juntas não passam as duas pela conferência. */
    $pdo->beginTransaction();
    try {
        $pdo->prepare('SELECT id FROM usuarios WHERE id = ? FOR UPDATE')->execute([$usuarioId]);

        // Outro ciclo (fim diferente) que se cruza com este? A folga de
        // 1 minuto em cada ponta absorve relógio impreciso.
        $cruza = $pdo->prepare(
            'SELECT COUNT(*) FROM pomodoro_sessoes
              WHERE usuario_id = ?
                AND fim_em <> FROM_UNIXTIME(?)
                AND fim_em > FROM_UNIXTIME(?) - INTERVAL ? MINUTE + INTERVAL 1 MINUTE
                AND fim_em - INTERVAL minutos MINUTE < FROM_UNIXTIME(?) - INTERVAL 1 MINUTE'
        );
        $cruza->execute([$usuarioId, $fim, $fim, $minutos, $fim]);
        if ((int) $cruza->fetchColumn() > 0) {
            $pdo->rollBack();
            return ['novo' => false, 'sobrepoe' => true];
        }

        /* FROM_UNIXTIME converte no relógio do MYSQL, e o `dia` sai da
           mesma conversão. É a regra do projeto: data se calcula num
           relógio só, e o relógio é o do banco (ver "Regra de datas" no
           README). Na duplicata, `id = id` não muda nada: rowCount() 0. */
        $stmt = $pdo->prepare(
            'INSERT INTO pomodoro_sessoes (usuario_id, minutos, fim_em, dia)
                  VALUES (?, ?, FROM_UNIXTIME(?), DATE(FROM_UNIXTIME(?)))
             ON DUPLICATE KEY UPDATE id = id'
        );
        $stmt->execute([$usuarioId, $minutos, $fim, $fim]);
        $novo = $stmt->rowCount() === 1;
        $pdo->commit();

        return ['novo' => $novo, 'sobrepoe' => false];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
