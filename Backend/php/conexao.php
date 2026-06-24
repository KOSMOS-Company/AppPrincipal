<?php
// ============================================================
//  KOSMOS — Configuração da conexão com o banco
//  Arquivo: backend/php/conexao.php
// ============================================================

define('DB_HOST',   'localhost');
define('DB_USER',   'root');       // usuário padrão do XAMPP
define('DB_PASS',   '');           // senha padrão do XAMPP (vazia)
define('DB_NAME',   'kosmos');
define('DB_CHARSET','utf8mb4');

function conectar(): PDO {
    $dsn = 'mysql:host=' . DB_HOST
         . ';dbname='    . DB_NAME
         . ';charset='   . DB_CHARSET;

    $opcoes = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    return new PDO($dsn, DB_USER, DB_PASS, $opcoes);
}
