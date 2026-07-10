<?php
// ============================================================
//  KOSMOS — Configurações de integrações externas (n8n)
//  Arquivo: backend/php/config.example.php  (MODELO)
//
//  ▶ Como usar: copie este arquivo para "config.php" (na mesma
//    pasta) e preencha os valores reais. O config.php está no
//    .gitignore e NÃO deve ser commitado (contém segredos).
// ============================================================

// URL de PRODUÇÃO do webhook do n8n (copie do nó "Webhook" depois
// de ATIVAR o workflow). Ex.: https://seu-dominio.com/webhook/gerar-exercicios
define('N8N_EXERCICIOS_URL', 'https://SEU-N8N/webhook/gerar-exercicios');

// Token secreto compartilhado entre o PHP e o n8n.
// Gere um valor aleatório (ex.: 32+ caracteres) e configure o MESMO
// no n8n (Webhook -> Authentication -> Header Auth).
define('N8N_TOKEN_HEADER', 'X-Kosmos-Token');
define('N8N_TOKEN_VALOR',  'COLE_AQUI_UM_TOKEN_ALEATORIO');

// ---------- Login com Google ----------
// Client ID do Google Cloud Console (OAuth 2.0 -> Web application).
// Use o MESMO valor no data-client_id dos botoes em login/cadastro.
define('GOOGLE_CLIENT_ID', 'SEU_GOOGLE_CLIENT_ID.apps.googleusercontent.com');

// ---------- Recuperacao de senha ----------
// URL base do frontend (para montar o link do e-mail de redefinicao).
define('APP_URL', 'http://localhost/kosmos/AppPrincipal/Frontend/pages');

// Webhook do n8n que ENVIA o e-mail de redefinicao (usa o mesmo Header Auth do n8n acima).
define('N8N_RESET_URL', 'https://SEU-N8N/webhook/reset-senha');
