<?php
// ===========================================================
//  KOSMOS — Backend de Cadastro
//  Arquivo: Backend/php/cadastro.php
//
//  Proteções:
//   - só do próprio site (exigirMesmaOrigem);
//   - limite de tentativas por e-mail e por IP (limite_util.php):
//     sem ele dava para criar contas em massa e testar, em série,
//     quais e-mails já têm conta;
//   - o UNIQUE do e-mail no banco é a palavra final: dois cadastros
//     simultâneos que passem juntos pela checagem não criam duas contas.
//
//  Sobre "este e-mail já está cadastrado": a mensagem revela que a
//  conta existe, e isso é uma escolha consciente. Escondê-la exige
//  confirmar o e-mail por link (pendente, ver README), senão quem
//  errou de tela ficaria sem saber por que não consegue entrar. O
//  limite acima é o que impede usar isso como lista de e-mails.
// ============================================================

require_once __DIR__ . '/api_util.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/senha_util.php';
require_once __DIR__ . '/limite_util.php';

apiExigirPost();
exigirMesmaOrigem();

// ---------- Lê e valida os campos ----------
$nome  = trim($_POST['nome']  ?? '');
$email = trim($_POST['email'] ?? '');
$senha = lerSenha('senha');

$erros = [];

if ($nome === '') {
    $erros[] = 'O nome é obrigatório.';
} elseif (mb_strlen($nome) < 3) {
    $erros[] = 'O nome deve ter pelo menos 3 caracteres.';
} elseif (mb_strlen($nome) > 120) {
    $erros[] = 'O nome deve ter no máximo 120 caracteres.';
}

if ($email === '') {
    $erros[] = 'O e-mail é obrigatório.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $erros[] = 'Informe um e-mail válido.';
} elseif (mb_strlen($email) > 180) {
    $erros[] = 'E-mail muito longo.';
}

if (($erroSenha = erroNovaSenha($senha)) !== null) {
    $erros[] = $erroSenha;
}

if (!empty($erros)) {
    apiErro(implode(' ', $erros), 422);
}

try {
    $pdo = conectar();

    // Conta toda tentativa válida, deu certo ou não
    if (limiteExcedido($pdo, 'cadastro', $email)) {
        apiErro('Muitas tentativas de cadastro. Aguarde um pouco e tente de novo.', 429);
    }
    registrarTentativa($pdo, 'cadastro', $email);

    // ---------- E-mail já em uso? ----------
    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        apiErro('Este e-mail já está cadastrado. Tente fazer login.', 409);
    }

    // ---------- Cria o hash da senha e insere ----------
    $pdo->prepare('INSERT INTO usuarios (nome, email, senha_hash) VALUES (?, ?, ?)')
        ->execute([$nome, $email, gerarHashSenha($senha)]);

    apiResponder(['ok' => true, 'msg' => 'Conta criada com sucesso! Redirecionando para o login…'], 201);

} catch (PDOException $e) {
    // 23000 = violação do UNIQUE: outro cadastro com o mesmo e-mail
    // entrou entre a checagem acima e o INSERT
    if ($e->getCode() === '23000') {
        apiErro('Este e-mail já está cadastrado. Tente fazer login.', 409);
    }
    registrarErro($e, 'cadastro');
    apiErro('Não foi possível criar a conta. Verifique se o MySQL está rodando e tente novamente.', 500);
}
