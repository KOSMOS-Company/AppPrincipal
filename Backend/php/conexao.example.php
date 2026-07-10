<?php
// ============================================================
//  KOSMOS — Configuracao da conexao com o banco
//  Arquivo: backend/php/conexao.example.php  (MODELO)
//
//  ▶ Como usar: copie este arquivo para "conexao.php" (na mesma
//    pasta) e ajuste as credenciais do seu ambiente. O conexao.php
//    esta no .gitignore e NAO deve ser commitado.
//
//  Os valores abaixo sao os padroes do XAMPP (usuario "root",
//  senha vazia). Ajuste se o seu MySQL usar credenciais diferentes.
// ============================================================

define('DB_HOST',   'localhost');
define('DB_USER',   'root');       // usuario padrao do XAMPP
define('DB_PASS',   '');           // senha padrao do XAMPP (vazia)
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
