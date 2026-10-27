<?php
// ============================================================
//  KOSMOS — Conta de desenvolvedor (tudo liberado, dados de exemplo)
//  Arquivo: Backend/tarefas/conta_dev.php
//
//  Cria (ou repreenche) uma conta para testar o app com TUDO
//  preenchido: nível 50 com o XP máximo, sequência de 365 dias,
//  todas as conquistas e recompensas, ~13 meses de Pomodoro com dias
//  de todo tipo, cadernos, resumos, baralhos com cartões novos/
//  vencidos/em dia, provas passadas e futuras, matérias e listas de
//  exercícios com respostas. O que entra está em
//  Backend/php/conta_dev_util.php.
//
//  Uso (da raiz do AppPrincipal), só pelo terminal:
//      C:\xampp\php\php.exe Backend/tarefas/conta_dev.php --senha=minhasenha
//      C:\xampp\php\php.exe Backend/tarefas/conta_dev.php                 (repreenche; senha igual)
//      C:\xampp\php\php.exe Backend/tarefas/conta_dev.php --apagar
//  Sem --senha, vale a CONTA_DEV_SENHA do config.php (gravada sempre);
//  sem ela também, a senha é sorteada na criação e mostrada no fim.
//
//  Para a conta se renovar SOZINHA todo dia (nunca perder a sequência
//  nem nada), ponha no seu config.php:
//      define('CONTA_DEV_EMAIL', 'dev@kosmos.local');
//  (ver docs/CONFIGURACAO-LOCAL.md)
//
//  Segurança: só roda com o banco em localhost, e só mexe numa conta
//  existente se o NOME dela for o da conta de desenvolvedor.
// ============================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../php/conexao.php';
require_once __DIR__ . '/../php/senha_util.php';
require_once __DIR__ . '/../php/conta_dev_util.php';

$opcoes = getopt('', ['email:', 'senha:', 'apagar']);
$email  = $opcoes['email'] ?? (defined('CONTA_DEV_EMAIL') && CONTA_DEV_EMAIL !== '' ? CONTA_DEV_EMAIL : 'dev@kosmos.local');
$senha  = $opcoes['senha'] ?? (defined('CONTA_DEV_SENHA') && CONTA_DEV_SENHA !== '' ? CONTA_DEV_SENHA : null);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "E-mail inválido: $email\n");
    exit(2);
}
if ($senha !== null && ($erro = erroNovaSenha($senha)) !== null) {
    fwrite(STDERR, "$erro\n");
    exit(2);
}
if (!contaDevBancoLocal()) {
    fwrite(STDERR, "Recusado: o banco não está em localhost (" . DB_HOST . "). A conta de desenvolvedor é só para uso local.\n");
    exit(2);
}

$pdo = conectar();

$q = $pdo->prepare('SELECT id, nome FROM usuarios WHERE email = ?');
$q->execute([$email]);
$conta = $q->fetch();
if ($conta && $conta['nome'] !== CONTA_DEV_NOME) {
    fwrite(STDERR, "Recusado: o e-mail $email pertence a \"{$conta['nome']}\", que não é a conta de desenvolvedor.\n");
    exit(2);
}

if (isset($opcoes['apagar'])) {
    if ($conta) {
        $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$conta['id']]);   // CASCADE leva tudo
        echo "Conta de desenvolvedor apagada.\n";
    } else {
        echo "Não havia conta de desenvolvedor com o e-mail $email.\n";
    }
    exit(0);
}

$pdo->beginTransaction();
try {
    if ($conta) {
        $id = (int) $conta['id'];
        if ($senha !== null) {
            $pdo->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?')->execute([gerarHashSenha($senha), $id]);
        }
    } else {
        $senha ??= bin2hex(random_bytes(6));
        $pdo->prepare('INSERT INTO usuarios (nome, email, senha_hash) VALUES (?, ?, ?)')
            ->execute([CONTA_DEV_NOME, $email, gerarHashSenha($senha)]);
        $id = (int) $pdo->lastInsertId();
    }
    $acertosEx = contaDevPreencher($pdo, $id);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Falhou, nada foi gravado: ' . $e->getMessage() . "\n");
    exit(1);
}

$n = fn(string $sql) => (int) $pdo->query(str_replace('?', (string) $id, $sql))->fetchColumn();
echo $conta ? "\nConta de desenvolvedor repreenchida!\n\n" : "\nConta de desenvolvedor criada!\n\n";
echo "  E-mail: $email\n";
echo '  Senha:  ' . ($senha ?? '(a mesma de antes)') . "\n\n";
printf("  Nível %d, %s XP, sequência de %d dias, %d conquistas\n",
    $n('SELECT nivel_atual FROM usuario_progresso WHERE usuario_id = ?'),
    number_format($n('SELECT xp_atual FROM usuario_progresso WHERE usuario_id = ?'), 0, ',', '.'),
    CONTA_DEV_SEQUENCIA, $n('SELECT COUNT(*) FROM usuario_conquistas WHERE usuario_id = ?'));
printf("  %d sessões de Pomodoro em %d dias · %d cadernos · %d resumos\n",
    $n('SELECT COUNT(*) FROM pomodoro_sessoes WHERE usuario_id = ?'), $n('SELECT COUNT(DISTINCT dia) FROM pomodoro_sessoes WHERE usuario_id = ?'),
    $n('SELECT COUNT(*) FROM resumo_cadernos WHERE usuario_id = ?'), $n('SELECT COUNT(*) FROM resumos WHERE usuario_id = ?'));
printf("  %d baralhos, %d cartões (%d para revisar hoje) · %d provas · %d listas de exercícios (%d acertos)\n\n",
    $n('SELECT COUNT(*) FROM flashcard_decks WHERE usuario_id = ?'),
    $n('SELECT COUNT(*) FROM flashcard_cartoes c JOIN flashcard_decks d ON d.id = c.deck_id WHERE d.usuario_id = ?'),
    $n('SELECT COUNT(*) FROM flashcard_cartoes c JOIN flashcard_decks d ON d.id = c.deck_id WHERE d.usuario_id = ? AND (c.proxima_revisao IS NULL OR c.proxima_revisao <= CURDATE())'),
    $n('SELECT COUNT(*) FROM provas WHERE usuario_id = ?'), $n('SELECT COUNT(*) FROM exercicios WHERE usuario_id = ?'), $acertosEx);

echo defined('CONTA_DEV_EMAIL') && strcasecmp(CONTA_DEV_EMAIL, $email) === 0
    ? "  Renovação automática: LIGADA (CONTA_DEV_EMAIL no config.php).\n"
    : "  Renovação automática: desligada. Para ligar, ponha no config.php:\n      define('CONTA_DEV_EMAIL', '$email');\n";
