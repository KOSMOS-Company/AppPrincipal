<?php
// ============================================================
//  KOSMOS — Frequência mensal de foco
//  Arquivo: Backend/php/foco_mensal.php
//  GET (protegido). ?mes=YYYY-MM (opcional; sem ele, o mês atual).
//
//  Devolve o calendário do mês para o modal que abre ao clicar no
//  gráfico "Esta semana" da Início:
//
//    mes       -> "YYYY-MM" do mês devolvido
//    titulo    -> "outubro de 2026"
//    dias      -> [{dia:N, minutos:N}] do dia 1 ao último, sem buracos
//    inicio    -> coluna do dia 1 no calendário (0 = domingo)
//    hoje      -> o dia de hoje, se for este mês (senão null)
//    anterior  -> "YYYY-MM" do mês anterior, ou null se for antes da conta
//    proximo   -> "YYYY-MM" do próximo mês, ou null se for o futuro
//    resumo    -> total, dias_com_foco, media (dos dias com foco), melhor {dia, minutos}
//
//  Mesma regra do inicio_dados.php: TODA conta de data é do MySQL.
//  O PHP só conta de 1 até o número de dias que o MySQL mandou (um
//  relógio só; os fusos do PHP e do MySQL já divergiram, ver README).
// ============================================================

require_once __DIR__ . '/estudo_comum.php';
require_once __DIR__ . '/datas.php';

$usuario = exigirLogin();
$id      = (int) $usuario['id'];

// "YYYY-MM" ou nada. Qualquer outra coisa cai no mês atual.
$pedido = (string) ($_GET['mes'] ?? '');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $pedido)) {
    $pedido = '';
}

try {
    $pdo = conectar();
    liberarSessao();

    /* ---- O mês ----
       Pedido no futuro vira o mês atual; pedido antes da criação da
       conta vira o mês da criação — não há o que mostrar fora disso. */
    $stmt = $pdo->prepare(
        'SELECT DATE_FORMAT(m.ini, "%Y-%m-%d")                  AS ini,
                DATE_FORMAT(m.ini, "%Y-%m")                     AS mes,
                YEAR(m.ini)                                     AS ano,
                MONTH(m.ini)                                    AS mes_num,
                DAY(LAST_DAY(m.ini))                            AS total_dias,
                DAYOFWEEK(m.ini) - 1                            AS inicio,
                IF(m.ini = m.atual, DAY(CURDATE()), NULL)       AS hoje,
                IF(m.ini > m.criacao,
                   DATE_FORMAT(m.ini - INTERVAL 1 MONTH, "%Y-%m"), NULL) AS anterior,
                IF(m.ini < m.atual,
                   DATE_FORMAT(m.ini + INTERVAL 1 MONTH, "%Y-%m"), NULL) AS proximo
           FROM (
                  SELECT LEAST(GREATEST(COALESCE(STR_TO_DATE(CONCAT(?, "-01"), "%Y-%m-%d"), x.atual),
                                        x.criacao),
                               x.atual) AS ini,
                         x.atual, x.criacao
                    FROM (
                           SELECT DATE_FORMAT(CURDATE(), "%Y-%m-01") + INTERVAL 0 DAY AS atual,
                                  DATE_FORMAT(u.criado_em, "%Y-%m-01") + INTERVAL 0 DAY AS criacao
                             FROM usuarios u
                            WHERE u.id = ?
                         ) AS x
                ) AS m'
    );
    $stmt->execute([$pedido === '' ? null : $pedido, $id]);
    $mes = $stmt->fetch();
    if (!$mes) {
        estErro('Usuário não encontrado.', 404);
    }

    /* ---- Os minutos de cada dia do mês ---- */
    $stmt = $pdo->prepare(
        'SELECT DAY(dia) AS dia, SUM(minutos) AS minutos
           FROM pomodoro_sessoes
          WHERE usuario_id = ?
            AND dia >= ?
            AND dia <  ? + INTERVAL 1 MONTH
          GROUP BY dia'
    );
    $stmt->execute([$id, $mes['ini'], $mes['ini']]);
    $porDia = [];
    foreach ($stmt as $linha) {
        $porDia[(int) $linha['dia']] = (int) $linha['minutos'];
    }

    $dias   = [];
    $total  = 0;
    $comFoco = 0;
    $melhor = null;
    for ($d = 1; $d <= (int) $mes['total_dias']; $d++) {
        $min = $porDia[$d] ?? 0;
        $dias[] = ['dia' => $d, 'minutos' => $min];
        $total += $min;
        if ($min > 0) {
            $comFoco++;
            if ($melhor === null || $min > $melhor['minutos']) {
                $melhor = ['dia' => $d, 'minutos' => $min];
            }
        }
    }

    estResponder([
        'mes'      => $mes['mes'],
        'titulo'   => MESES_PT[(int) $mes['mes_num'] - 1] . ' de ' . (int) $mes['ano'],
        'dias'     => $dias,
        'inicio'   => (int) $mes['inicio'],
        'hoje'     => $mes['hoje'] === null ? null : (int) $mes['hoje'],
        'anterior' => $mes['anterior'],
        'proximo'  => $mes['proximo'],
        'resumo'   => [
            'total'         => $total,
            'dias_com_foco' => $comFoco,
            'media'         => $comFoco > 0 ? (int) round($total / $comFoco) : 0,
            'melhor'        => $melhor,
        ],
    ]);
} catch (PDOException $e) {
    registrarErro($e);
    estErro('Não foi possível carregar a frequência do mês.', 500);
}
