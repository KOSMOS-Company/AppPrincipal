# 🚀 Colocando o KOSMOS em produção

O XAMPP é para desenvolvimento: usuário `root` sem senha, `http` e erros
detalhados. Este guia lista o que muda num servidor de verdade.

## 1. Requisitos

| Item | Versão / observação |
|---|---|
| PHP | 8.1 ou mais novo (o projeto é testado no 8.2) |
| Extensões do PHP | `pdo_mysql`, `mbstring`, `curl`, `fileinfo`, `json` — declaradas no `composer.json` (`composer check-platform-reqs` confere) |
| Extensão opcional | `gd`: o servidor também reduz as fotos de perfil (sem ela, o navegador já reduz) |
| Banco | MySQL 8 ou MariaDB 10.4+ |
| Servidor web | Apache 2.4 com `mod_rewrite` e `mod_headers` (e, recomendado, `mod_deflate` e `mod_expires`) e `AllowOverride All` na pasta do app |
| Serviços externos | n8n (geração de exercícios, e-mail de senha e notificações) e um Client ID OAuth do Google |

> **Sem Apache (ex.: nginx):** os `.htaccess` não valem. Reproduza na
> configuração do servidor: negar `Backend/` inteiro **exceto** `Backend/php/*.php`
> e `Backend/uploads/` (só leitura, sem executar PHP); negar arquivos que começam
> com ponto (`.git`); desligar a listagem de pastas; e enviar
> `Frontend/pages/404.php` para os caminhos que não existem. Os cabeçalhos de
> segurança das respostas PHP já saem do `bootstrap.php`.

## 2. Instalação (Linux)

```bash
git clone https://github.com/KOSMOS-Company/AppPrincipal.git /var/www/kosmos
cd /var/www/kosmos
cp Backend/php/conexao.example.php Backend/php/conexao.php
cp Backend/php/config.example.php  Backend/php/config.php
# edite os dois (ver os passos 3 e 4)

mysql -u root -p -e "CREATE DATABASE kosmos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -p kosmos < Backend/kosmos.sql
php Backend/sql/migrar.php --marcar-todas

# o PHP (usuário do Apache) precisa escrever nas fotos e nos logs
chown -R www-data:www-data Backend/uploads Backend/logs
chmod -R 750 Backend/uploads Backend/logs
```

O `kosmos.sql` cria o banco com `CREATE DATABASE kosmos`. Se o servidor usar outro
nome, crie o banco à mão e importe sem essa linha.

## 3. Usuário do banco com o mínimo de permissão

Nunca use `root` no `conexao.php` de produção. O app só lê e escreve dados; quem
muda a estrutura são as migrações, rodadas por quem administra:

```sql
-- usuário do APP: só dados
CREATE USER 'kosmos_app'@'localhost' IDENTIFIED BY 'troque-por-uma-senha-longa';
GRANT SELECT, INSERT, UPDATE, DELETE ON kosmos.* TO 'kosmos_app'@'localhost';

-- usuário das MIGRAÇÕES: também muda estrutura (use só no terminal)
CREATE USER 'kosmos_migra'@'localhost' IDENTIFIED BY 'outra-senha-longa';
GRANT ALL PRIVILEGES ON kosmos.* TO 'kosmos_migra'@'localhost';
FLUSH PRIVILEGES;
```

No `conexao.php`, use `kosmos_app`. Para rodar uma migração, troque por um
momento para `kosmos_migra` (ou mantenha uma cópia do `conexao.php` só para o
terminal).

## 4. `config.php` de produção

| Constante | Em produção |
|---|---|
| `APP_URL` | **obrigatória**, ex.: `https://kosmos.exemplo.com/Frontend/pages`. Vazia fora do `localhost`, o e-mail de redefinição **não é enviado**: montar o link a partir do cabeçalho `Host` permitiria desviar o token para outro site |
| `GOOGLE_CLIENT_ID` | o do projeto de produção no Google Cloud, com a origem `https://kosmos.exemplo.com` autorizada |
| `N8N_*` | URLs de produção dos webhooks e um `N8N_TOKEN_VALOR` aleatório com 32+ caracteres |
| `N8N_NOTIF_URL` | webhook do fluxo de notificações (opcional) |
| `N8N_IA_URL` | webhook do fluxo "Kosmos — IA" (Groq → Gemini → OpenRouter), usado pelos exercícios do resumo, flashcards com IA e rascunho de resumo. Vazia, esses três ficam desligados |

## 5. HTTPS

Obrigatório: senha, cookie de sessão e token de redefinição trafegam nas requisições.

```bash
sudo certbot --apache -d kosmos.exemplo.com
```

Com HTTPS ativo, o projeto passa a:
- marcar o cookie de sessão como `Secure` sozinho (`sessao.php`);
- enviar `Strict-Transport-Security` (`bootstrap.php`).

Redirecione todo o `http` para `https` no VirtualHost.

## 6. Agendamentos

| O quê | Quando | Comando |
|---|---|---|
| Lembrete diário | todo dia, 19h | `php Backend/tarefas/notificacoes.php lembrete --enviar` |
| Resumo semanal | domingo, 18h | `php Backend/tarefas/notificacoes.php resumo --enviar` |
| Limpeza | todo dia, 3h | `php Backend/tarefas/limpeza.php --apagar` |

```cron
0 19 * * *  cd /var/www/kosmos && php Backend/tarefas/notificacoes.php lembrete --enviar
0 18 * * 0  cd /var/www/kosmos && php Backend/tarefas/notificacoes.php resumo   --enviar
0 3  * * *  cd /var/www/kosmos && php Backend/tarefas/limpeza.php --apagar
```

No Windows, o script `Backend\tarefas\agendar-windows.ps1` cria as três tarefas no
Agendador (e `-Remover` as apaga):

```powershell
powershell -ExecutionPolicy Bypass -File Backend\tarefas\agendar-windows.ps1
```

Sem `--enviar` (ou `--apagar`, na limpeza) os scripts só **simulam**. Rode assim primeiro.

**Sessões:** o PHP guarda os logins em `Backend/sessoes/` (não na pasta comum do
servidor, onde o cron do Debian/Ubuntu apagaria tudo com mais de 24 min). O usuário
do Apache precisa poder escrever nela, e ela não pode ser servida: o `.htaccess` dela
já nega o acesso no Apache; no nginx, bloqueie `/Backend/` inteiro.

## 7. Logs

Erros do PHP vão para `Backend/logs/php-erros.log` (fora do git e bloqueado no
navegador). Configure a rotação do arquivo para ele não crescer sem limite:

```
/var/www/kosmos/Backend/logs/*.log {
    weekly
    rotate 8
    compress
    missingok
    notifempty
}
```

## 8. Checklist antes de abrir

- [ ] `conexao.php` com `kosmos_app`, nunca `root`
- [ ] `APP_URL` preenchida com `https://`
- [ ] HTTPS ativo e `http` redirecionando
- [ ] `https://seu-dominio/.git/config` responde 403
- [ ] `https://seu-dominio/Backend/kosmos.sql` responde 403
- [ ] `https://seu-dominio/Backend/uploads/avatares/` responde 403 (sem listagem)
- [ ] uma URL inexistente mostra a página 404 do Kosmos
- [ ] `php Backend/testes/rodar.php` passa no servidor
- [ ] `composer check-platform-reqs` sem pendências
- [ ] Client ID do Google de produção, com a origem `https` autorizada
