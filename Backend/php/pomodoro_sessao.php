<?php
// ============================================================
//  KOSMOS — Registra uma sessão de foco concluída
//  Arquivo: Backend/php/pomodoro_sessao.php
//  POST (protegido):
//    fim     -> instante em que o ciclo terminou (epoch, SEGUNDOS)
//    minutos -> duração do ciclo
//
//  Chamado pelo js/pomodoro.js quando um ciclo de FOCO fecha —
//  inclusive os que terminaram com a pessoa fora do app.
//
//  Por que o cliente manda o instante em vez de usarmos NOW():
//  o timer roda pelo relógio do sistema e continua correndo com a
//  aba fechada. Quando a pessoa volta, o ciclo pode ter terminado
//  há horas; gravar NOW() jogaria a sessão no dia errado.
//
//  Duplicata não é acidente aqui, é rotina: duas abas abertas
//  descobrem o mesmo fim de ciclo, e um recarregamento redescobre.
//  Quem resolve é o UNIQUE (usuario_id, fim_em) da tabela — o fim
//  de um ciclo é um instante único, então serve de identidade sem
//  precisar inventar um id no cliente. O INSERT ... ON DUPLICATE
//  KEY UPDATE deixa a segunda tentativa passar em silêncio.
// ============================================================

require_once __DIR__ . '/estudo_comum.php';

$usuario = exigirLogin();
estExigirPost();

$corpo = estCorpo();

// Um ciclo de Pomodoro tem entre 1 e 180 minutos. Fora disso é
// engano ou alguém mexendo na requisição — nos dois casos não entra.
$minutos = estInt($corpo['minutos'] ?? null, 1, 180, 0);
if ($minutos === 0) {
    estErro('Duração inválida.', 422);
}

$fim = isset($corpo['fim']) && is_numeric($corpo['fim']) ? (int) $corpo['fim'] : 0;
if ($fim <= 0) {
    estErro('Instante de término ausente.', 422);
}

/* Uma janela de sanidade em volta do agora. Sem ela, um relógio
   errado no computador da pessoa (ou um valor forjado) enfiaria
   sessões em 1999 ou em 2040 e sujaria o histórico para sempre.
   Sete dias para trás cobre "estudei no fim de semana e só abri o
   app agora"; cinco minutos para frente cobre relógio adiantado. */
$agora = time();
if ($fim < $agora - 7 * 24 * 3600 || $fim > $agora + 300) {
    estErro('Instante fora da janela aceita.', 422);
}

try {
    $pdo = conectar();
    liberarSessao();

    /* FROM_UNIXTIME converte no relógio do MYSQL, e o `dia` sai da
       mesma conversão. É a regra do projeto: data se calcula num
       relógio só, e o relógio é o do banco — PHP e MySQL estão em
       fusos diferentes aqui (ver "Problemas conhecidos" no README). */
    $stmt = $pdo->prepare(
        'INSERT INTO pomodoro_sessoes (usuario_id, minutos, fim_em, dia)
              VALUES (?, ?, FROM_UNIXTIME(?), DATE(FROM_UNIXTIME(?)))
         ON DUPLICATE KEY UPDATE minutos = VALUES(minutos)'
    );
    $stmt->execute([$usuario['id'], $minutos, $fim, $fim]);

    // Quanto já foi estudado hoje — é o que a tela usa para a meta.
    $hoje = $pdo->prepare(
        'SELECT COALESCE(SUM(minutos), 0) AS minutos
           FROM pomodoro_sessoes
          WHERE usuario_id = ? AND dia = CURDATE()'
    );
    $hoje->execute([$usuario['id']]);

    estResponder([
        'minutos_hoje' => (int) $hoje->fetchColumn(),
        'novo'         => $stmt->rowCount() === 1,   // 2 = já existia e foi atualizada
    ]);
} catch (PDOException $e) {
    estErro('Não foi possível registrar a sessão.', 500);
}
