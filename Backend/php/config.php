<?php
// ============================================================
//  KOSMOS — Configurações de integrações externas (n8n)
//  Arquivo: backend/php/config.php
//  ⚠️  Contém segredos: NÃO suba este arquivo para repositórios
//      públicos (adicione-o ao .gitignore).
// ============================================================

// URL de PRODUÇÃO do webhook do n8n (copie do nó "Webhook" depois
// de ATIVAR o workflow). Ex.: https://seu-dominio.com/webhook/gerar-exercicios
define('N8N_EXERCICIOS_URL', 'https://n8n.srv1779459.hstgr.cloud/webhook/gerar-exercicios');

// Token secreto compartilhado entre o PHP e o n8n.
// Gere um valor aleatório (ex.: 32+ caracteres) e configure o MESMO
// no n8n (Webhook → Authentication → Header Auth).
define('N8N_TOKEN_HEADER', 'X-Kosmos-Token');
define('N8N_TOKEN_VALOR',  'YwNbC9T0N2wJcaFoxh4iNZr1DnLh9y2W');

// ---------- Login com Google ----------
// Client ID do Google Cloud Console (OAuth 2.0 → Web application).
// Use o MESMO valor no data-client_id dos botões em login/cadastro.
define('GOOGLE_CLIENT_ID', '848162979346-58d2pq44nmhbrabsnhl6i1o1j2pnm7mu.apps.googleusercontent.com');
