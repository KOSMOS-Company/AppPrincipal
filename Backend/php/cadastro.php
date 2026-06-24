<?php
// ===========================================================
//  KOSMOS — Backend de Cadastro
//  Caminho sugerido: BackEnd/php/cadastro.php
// ============================================================
 
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
 
// ---------- Configurações do banco ----------
define('DB_HOST', 'localhost');
define('DB_NAME', 'kosmos');
define('DB_USER', 'root');   // usuário padrão do XAMPP
define('DB_PASS', '');       // senha padrão do XAMPP (vazia)
define('DB_PORT', 3306);
 
// ---------- Só aceita POST ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método não permitido.']);
    exit;
}
 
// ---------- Lê e valida os campos ----------
$nome  = trim($_POST['nome']  ?? '');
$email = trim($_POST['email'] ?? '');
$senha = trim($_POST['senha'] ?? '');
 
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
 
if ($senha === '') {
    $erros[] = 'A senha é obrigatória.';
} elseif (mb_strlen($senha) < 8) {
    $erros[] = 'A senha deve ter no mínimo 8 caracteres.';
}
 
if (!empty($erros)) {
    echo json_encode(['ok' => false, 'msg' => implode(' ', $erros)]);
    exit;
}
 
// ---------- Conecta ao banco ----------
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Erro ao conectar ao banco de dados. Verifique se o MySQL está rodando.']);
    exit;
}
 
// ---------- Verifica se o e-mail já existe ----------
try {
    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
 
    if ($stmt->fetch()) {
        echo json_encode(['ok' => false, 'msg' => 'Este e-mail já está cadastrado. Tente fazer login.']);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Erro ao verificar e-mail.']);
    exit;
}
 
// ---------- Cria o hash da senha e insere ----------
$senhaHash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
 
try {
    $insert = $pdo->prepare(
        'INSERT INTO usuarios (nome, email, senha_hash) VALUES (?, ?, ?)'
    );
    $insert->execute([$nome, $email, $senhaHash]);
 
    echo json_encode(['ok' => true, 'msg' => 'Conta criada com sucesso! Redirecionando para o login…']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Não foi possível criar a conta. Tente novamente.']);
}
