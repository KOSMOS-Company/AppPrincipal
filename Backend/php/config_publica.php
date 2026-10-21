<?php
// ============================================================
//  KOSMOS — Configuração pública para o frontend
//  Arquivo: Backend/php/config_publica.php
//  GET (público): valores do config.php que o NAVEGADOR precisa
//  saber e que não são segredo.
//
//  Antes o Client ID do Google estava escrito à mão em três lugares
//  (config.php, login/index.html e cadastro/index.html). Trocar de
//  projeto no Google Cloud exigia lembrar dos três, e cada pessoa da
//  equipe com um Client ID próprio tinha de editar os HTMLs. Agora a
//  fonte é só o config.php.
//
//  NUNCA ponha aqui token, senha ou URL do n8n.
// ============================================================

require_once __DIR__ . '/api_util.php';
require_once __DIR__ . '/config.php';

apiResponder([
    'ok'               => true,
    'google_client_id' => defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '',
]);
