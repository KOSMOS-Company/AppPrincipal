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
//  Confirmação do e-mail (com N8N_CONFIRMA_URL no config.php): a conta
//  nasce com email_confirmado = 0 e o n8n manda um link
//  (confirmar_email.php). Até confirmar, o login não entra. Uma conta
//  que nunca confirmou pode ser recadastrada — ver abaixo.
//
//  Sobre "este e-mail já está cadastrado": a mensagem revela que a
//  conta existe, e isso é uma escolha consciente: quem errou de tela
//  precisa saber por que não consegue se cadastrar. O limite acima é
//  o que impede usar isso como lista de e-mails.
// ============================================================

require_once __DIR__ . '/api_util.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/senha_util.php';
require_once __DIR__ . '/limite_util.php';
require_once __DIR__ . '/email_util.php';

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

    // Confirmação do e-mail ligada? (N8N_CONFIRMA_URL no config.php)
    $confirmar = confirmacaoLigada();

    // ---------- E-mail já em uso? ----------
    $stmt = $pdo->prepare($confirmar
        ? 'SELECT id, email_confirmado FROM usuarios WHERE email = ? LIMIT 1'
        : 'SELECT id FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $existente = $stmt->fetch();

    if ($existente && (!$confirmar || (int) $existente['email_confirmado'] === 1)) {
        apiErro('Este e-mail já está cadastrado. Tente fazer login.', 409);
    }

    if ($existente) {
        /* Conta que nunca confirmou o e-mail: pode ser alguém que usou
           o e-mail de outra pessoa. Quem se cadastra de novo assume a
           conta (nome e senha novos) e o link vai para a caixa de
           entrada — só o dono de verdade consegue confirmar. Sem isto,
           bastava "reservar" o e-mail de alguém para ele nunca mais
           conseguir se cadastrar. */
        $id = (int) $existente['id'];
        $pdo->prepare('UPDATE usuarios
                          SET nome = ?, senha_hash = ?, sessoes_versao = sessoes_versao + 1
                        WHERE id = ? AND email_confirmado = 0')
            ->execute([$nome, gerarHashSenha($senha), $id]);
    } elseif ($confirmar) {
        $pdo->prepare('INSERT INTO usuarios (nome, email, senha_hash, email_confirmado) VALUES (?, ?, ?, 0)')
            ->execute([$nome, $email, gerarHashSenha($senha)]);
        $id = (int) $pdo->lastInsertId();
    } else {
        // ---------- Sem confirmação: cria o hash da senha e insere ----------
        $pdo->prepare('INSERT INTO usuarios (nome, email, senha_hash) VALUES (?, ?, ?)')
            ->execute([$nome, $email, gerarHashSenha($senha)]);
    }

    if ($confirmar) {
        // Se o n8n falhar, o login oferece "Reenviar link" (confirmar_reenviar.php)
        enviarConfirmacao($pdo, $id, $email, $nome);
        apiResponder([
            'ok'        => true,
            'confirmar' => true,
            'msg'       => "Conta criada! Enviamos um link de confirmação para $email. Abra o e-mail e clique no link para entrar.",
        ], 201);
    }

    apiResponder(['ok' => true, 'msg' => 'Conta criada com sucesso! Redirecionando para o login…'], 201);

} catch (PDOException $e) {
    // 23000 = violação do UNIQUE: outro cadastro com o mesmo e-mail
    // entrou entre a checagem acima e o INSERT
    if ($e->getCode() === '23000') {
        apiErro('Este e-mail já está cadastrado. Tente fazer login.', 409);
    }
    registrarErro($e, 'cadastro');
    apiErro('Não foi possível criar a conta agora. Tente novamente em alguns minutos.', 500);
}
