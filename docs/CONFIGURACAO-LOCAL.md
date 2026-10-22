# 🛠️ Configurar o Kosmos no seu PC

Algumas coisas que fazem o Kosmos funcionar **não vão para o GitHub**: elas ficam só
no computador de cada um. Este guia mostra como deixar o seu PC igual ao de quem já
está com tudo funcionando: login, redefinição de senha, exercícios com IA e
notificações por e-mail.

**Tempo:** uns 15 minutos.

## O que fica fora do GitHub (e por quê)

| O quê | Por que não vai no commit | Você precisa... |
|---|---|---|
| `Backend/php/conexao.php` | pode ter a senha do seu MySQL | copiar do modelo (passo 2) |
| `Backend/php/config.php` | tem o token secreto do n8n | copiar do modelo e preencher (passo 3) |
| O banco de dados | cada PC tem o seu MySQL | atualizar com o `migrar.php` (passo 4) |
| Fotos enviadas (`Backend/uploads/`) | são dados de usuários | nada: cada PC tem as suas |
| Fluxos e credenciais do n8n | ficam no servidor do n8n, não no PC | nada: já estão prontos lá |

> Os fluxos do n8n (exercícios, redefinição de senha e notificações) rodam no
> servidor `n8n.srv1779459.hstgr.cloud`, que todo mundo compartilha. **Ninguém
> precisa recriar fluxo nenhum.** Seu PC só precisa saber o endereço deles e o
> token (passo 3).

---

## 1. Atualize o código

No VS Code, troque para a branch em que você vai trabalhar e puxe as novidades do GitHub
(**Sincronizar alterações** / `git pull`).

Todas as branches (`main`, `Frontend` e `Backend`) já têm as mudanças necessárias.

## 2. `conexao.php` (credenciais do banco)

Ele mudou de formato: agora guarda **só** as credenciais. Substitua o seu pelo modelo:

```bash
cd Backend/php
copy conexao.example.php conexao.php
```

Se o seu MySQL tem senha (no XAMPP padrão, não tem), abra o `conexao.php` e coloque
a senha em `DB_PASS`.

> Se você pular este passo, o site continua abrindo, mas o fuso horário do banco
> não é ajustado.

## 3. `config.php` (integrações)

Se você **não tem** um `config.php`, crie a partir do modelo:

```bash
cd Backend/php
copy config.example.php config.php
```

Se **já tem**, abra o seu e confira linha por linha com a tabela abaixo. A linha
`N8N_NOTIF_URL` é nova, então talvez precise acrescentá-la.

| Configuração | Valor | Onde conseguir |
|---|---|---|
| `N8N_EXERCICIOS_URL` | endereço do fluxo de exercícios | **peça no privado ao Matheus** |
| `N8N_TOKEN_HEADER` | `X-Kosmos-Token` | já vem no modelo, não mude |
| `N8N_TOKEN_VALOR` | token secreto (48 letras e números) | **peça no privado ao Matheus** |
| `GOOGLE_CLIENT_ID` | `848162979346-58d2pq44nmhbrabsnhl6i1o1j2pnm7mu.apps.googleusercontent.com` | é público, pode copiar daqui |
| `APP_URL` | `http://localhost/NOME-DA-SUA-PASTA/Frontend/pages` | troque `NOME-DA-SUA-PASTA` pela pasta onde o projeto está no seu `htdocs` (ex.: `AppPrincipal`) |
| `N8N_RESET_URL` | `https://n8n.srv1779459.hstgr.cloud/webhook/reset-senha` | pode copiar daqui |
| `N8N_NOTIF_URL` | `https://n8n.srv1779459.hstgr.cloud/webhook/notificacoes` | pode copiar daqui |

O resultado fica assim (com os seus valores):

```php
define('N8N_EXERCICIOS_URL', '...pedir ao Matheus...');
define('N8N_TOKEN_HEADER', 'X-Kosmos-Token');
define('N8N_TOKEN_VALOR',  '...pedir ao Matheus...');
define('GOOGLE_CLIENT_ID', '848162979346-58d2pq44nmhbrabsnhl6i1o1j2pnm7mu.apps.googleusercontent.com');
define('APP_URL', 'http://localhost/AppPrincipal/Frontend/pages');
define('N8N_RESET_URL', 'https://n8n.srv1779459.hstgr.cloud/webhook/reset-senha');
define('N8N_NOTIF_URL', 'https://n8n.srv1779459.hstgr.cloud/webhook/notificacoes');
```

> ⚠️ **O token e o endereço dos exercícios são segredos.** Peça por mensagem privada,
> nunca coloque num arquivo que vai para o GitHub, num print ou no grupo. Com o token,
> qualquer pessoa consegue mandar e-mails em nome do Kosmos. O fluxo de exercícios
> não pede token, então quem tiver o endereço dele consegue gastar a cota de IA do
> projeto.
>
> O token precisa ser **exatamente igual** ao que está cadastrado no n8n. Um caractere
> diferente e o n8n recusa tudo.

## 4. Banco de dados

Com o **MySQL ligado** no XAMPP, rode na pasta do projeto:

```bash
C:\xampp\php\php.exe Backend/sql/migrar.php --status
C:\xampp\php\php.exe Backend/sql/migrar.php
```

O primeiro mostra o que falta e o segundo aplica. No fim, tudo deve aparecer como `[ok]`.

**Nunca criou o banco?** Importe o `Backend/kosmos.sql` no phpMyAdmin e depois rode:

```bash
C:\xampp\php\php.exe Backend/sql/migrar.php --marcar-todas
```

## 5. Teste se está tudo certo

1. **Testes automáticos** (não precisam do banco):
   ```bash
   C:\xampp\php\php.exe Backend/testes/rodar.php
   ```
   Deve terminar com `54 de 54 testes passaram` (ou mais).
2. **Site:** abra `http://localhost/NOME-DA-SUA-PASTA/` e você deve cair na página inicial.
3. **Login:** crie uma conta ou entre na sua.
4. **Redefinição de senha:** no login, clique em **Esqueceu a senha?** e use o
   **seu** e-mail (de uma conta que existe no seu banco). O e-mail deve chegar, e o
   botão dele precisa abrir a página de nova senha no **seu** localhost.
5. **IA:** gere um exercício em qualquer matéria.

## 6. (Opcional) Deixar o site mais leve

O `.htaccess` do projeto comprime os arquivos e guarda fontes e imagens em cache, mas
o XAMPP vem com esses dois módulos desligados. Para ligar:

1. No painel do XAMPP, clique em **Config** do Apache → **Apache (httpd.conf)**.
2. Tire o `#` do começo destas duas linhas:
   ```
   #LoadModule deflate_module modules/mod_deflate.so
   #LoadModule expires_module modules/mod_expires.so
   ```
3. Salve e reinicie o Apache.

---

## 📬 Notificações por e-mail: leia antes de mexer

O lembrete diário e o resumo semanal saem pelo script `Backend/tarefas/notificacoes.php`.

- **Ele usa o banco de quem roda.** No seu PC, só os usuários do **seu** banco recebem.
- **Só UM computador deve ter o envio agendado.** Se cada pessoa da equipe agendar,
  quem tiver conta em mais de um banco recebe o mesmo e-mail várias vezes.
  Combinem quem fica responsável (ou deixem para o servidor, quando publicarem; ver
  [PRODUCAO.md](PRODUCAO.md)).
- **Para só ver quem receberia**, sem enviar nada:
  ```bash
  C:\xampp\php\php.exe Backend/tarefas/notificacoes.php resumo
  C:\xampp\php\php.exe Backend/tarefas/notificacoes.php lembrete
  ```
  Acrescente `--enviar` no fim para enviar de verdade.
- **Para receber os seus:** ligue as opções em **Conta › Notificações**. O lembrete só
  vai para quem **ainda não estudou no dia**. Se você já estudou hoje, ele não te
  seleciona, e isso é o esperado.

---

## 🆘 Deu problema?

| O que acontece | Causa provável | Como resolver |
|---|---|---|
| "Você não tem permissão" ao abrir o site | cache antigo do navegador | recarregue com **Ctrl+F5** |
| Erro com `bootstrap.php` em tudo | código desatualizado | faça o `git pull` (passo 1) |
| "Esqueci a senha" diz que enviou, mas o e-mail não chega | `N8N_RESET_URL` errado, token diferente do n8n, ou o e-mail não tem conta no seu banco | confira o passo 3; o motivo aparece em `Backend/logs/php-erros.log` |
| O link do e-mail abre uma página que não existe | `APP_URL` com o nome errado da pasta | confira o `APP_URL` (passo 3) |
| Botão do Google fica em "ainda está carregando" | `GOOGLE_CLIENT_ID` vazio | passo 3 |
| "Gerar exercícios" dá erro | `N8N_EXERCICIOS_URL` errado ou vazio | peça o endereço ao Matheus |
| Datas ou sequência de dias estranhas | migração não aplicada | passo 4 |

**Dica:** quase todo erro deixa uma pista em `Backend/logs/php-erros.log`. Abra esse
arquivo antes de pedir ajuda; a última linha costuma dizer o que aconteceu.
