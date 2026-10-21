<?php
// ============================================================
//  KOSMOS — Credenciais do banco
//  Arquivo: backend/php/conexao.example.php  (MODELO)
//
//  ▶ Como usar: copie este arquivo para "conexao.php" (na mesma
//    pasta) e ajuste as credenciais do seu ambiente. O conexao.php
//    esta no .gitignore e NAO deve ser commitado.
//
//  Os valores abaixo sao os padroes do XAMPP (usuario "root",
//  senha vazia). Em producao, use um usuario so do app, com o
//  minimo de permissao (ver docs/PRODUCAO.md).
//
//  A funcao conectar() (e o fuso, o log de erros e os cabecalhos de
//  seguranca) ficam no bootstrap.php, que e versionado: assim uma
//  melhoria na conexao chega a todo mundo sem mexer neste arquivo.
// ============================================================

define('DB_HOST',   'localhost');
define('DB_USER',   'root');       // usuario padrao do XAMPP
define('DB_PASS',   '');           // senha padrao do XAMPP (vazia)
define('DB_NAME',   'kosmos');
define('DB_CHARSET','utf8mb4');

require_once __DIR__ . '/bootstrap.php';
