# 🛠️ Configurar o Kosmos no seu PC

Algumas coisas que fazem o Kosmos funcionar **não vão para o GitHub**: elas ficam só
no computador de cada um. Este guia mostra como deixar o seu PC igual ao de quem já
está com tudo funcionando: login, redefinição de senha, exercícios com IA, o Orion
com IA, a IA nos resumos e notificações por e-mail.

**Tempo:** uns 15 minutos.

> ### 🆕 Já seguiu este guia antes? Para ter a IA nos resumos, faça só isto:
> 1. Puxe o código novo (`git pull`).
> 2. No seu `Backend/php/config.php`, acrescente no final:
>    ```php
>    define('N8N_IA_URL', 'https://n8n.srv1779459.hstgr.cloud/webhook/kosmos-ia');
>    ```
> 3. Teste: abra um resumo com texto, clique em **Estudar › Gerar flashcards** e depois
>    em **Gerar**. Os cartões devem aparecer no grupo "Criados pela IA".
>
> Não precisa de migração nem de mexer no n8n, e o token é o mesmo de antes. Essa linha
> liga os três recursos novos: **gerar exercícios a partir do resumo**, **flashcards com
> IA** e **rascunho de resumo com IA** (no editor de resumo). Sem ela, os três dizem
> "não está disponível agora".
>
> Ainda não tem o Orion com IA? Acrescente também a linha `N8N_ORION_URL` da tabela do
> passo 3 e rode o `migrar.php` (passo 4).

## O que fica fora do GitHub (e por quê)

| O quê | Por que não vai no commit | Você precisa... |
|---|---|---|
| `Backend/php/conexao.php` | pode ter a senha do seu MySQL | copiar do modelo (passo 2) |
| `Backend/php/config.php` | tem o token secreto do n8n | copiar do modelo e preencher (passo 3) |
| O banco de dados | cada PC tem o seu MySQL | atualizar com o `migrar.php` (passo 4) |
| Fotos enviadas (`Backend/uploads/`) | são dados de usuários | nada: cada PC tem as suas |
| Fluxos e credenciais do n8n | ficam no servidor do n8n, não no PC | nada: já estão prontos lá |

> Os fluxos do n8n (exercícios, Orion, IA dos resumos, redefinição de senha e
> notificações) rodam no servidor `n8n.srv1779459.hstgr.cloud`, que todo mundo
> compartilha. **Ninguém precisa recriar fluxo nenhum.** Seu PC só precisa saber o
> endereço deles e o
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

Se **já tem**, abra o seu e confira linha por linha com a tabela abaixo. As linhas
`N8N_NOTIF_URL`, `N8N_ORION_URL` e `N8N_IA_URL` são novas, então talvez precise
acrescentá-las.

| Configuração | Valor | Onde conseguir |
|---|---|---|
| `N8N_EXERCICIOS_URL` | endereço do fluxo de exercícios | **peça no privado ao Matheus** |
| `N8N_TOKEN_HEADER` | `X-Kosmos-Token` | já vem no modelo, não mude |
| `N8N_TOKEN_VALOR` | token secreto (48 letras e números) | **peça no privado ao Matheus** |
| `GOOGLE_CLIENT_ID` | `848162979346-58d2pq44nmhbrabsnhl6i1o1j2pnm7mu.apps.googleusercontent.com` | é público, pode copiar daqui |
| `APP_URL` | `http://localhost/NOME-DA-SUA-PASTA/Frontend/pages` | troque `NOME-DA-SUA-PASTA` pela pasta onde o projeto está no seu `htdocs` (ex.: `AppPrincipal`) |
| `N8N_RESET_URL` | `https://n8n.srv1779459.hstgr.cloud/webhook/reset-senha` | pode copiar daqui |
| `N8N_NOTIF_URL` | `https://n8n.srv1779459.hstgr.cloud/webhook/notificacoes` | pode copiar daqui |
| `N8N_ORION_URL` | `https://n8n.srv1779459.hstgr.cloud/webhook/orion` | pode copiar daqui (sem ele, o Orion usa só as respostas prontas) |
| `N8N_IA_URL` | `https://n8n.srv1779459.hstgr.cloud/webhook/kosmos-ia` | pode copiar daqui (sem ele, a IA dos resumos fica desligada) |
| `N8N_CONFIRMA_URL` | `https://n8n.srv1779459.hstgr.cloud/webhook/confirmar-email` | pode copiar daqui (o fluxo já existe; vazio = confirmação de e-mail desligada) |

O resultado fica assim (com os seus valores):

```php
define('N8N_EXERCICIOS_URL', '...pedir ao Matheus...');
define('N8N_TOKEN_HEADER', 'X-Kosmos-Token');
define('N8N_TOKEN_VALOR',  '...pedir ao Matheus...');
define('GOOGLE_CLIENT_ID', '848162979346-58d2pq44nmhbrabsnhl6i1o1j2pnm7mu.apps.googleusercontent.com');
define('APP_URL', 'http://localhost/AppPrincipal/Frontend/pages');
define('N8N_RESET_URL', 'https://n8n.srv1779459.hstgr.cloud/webhook/reset-senha');
define('N8N_NOTIF_URL', 'https://n8n.srv1779459.hstgr.cloud/webhook/notificacoes');
define('N8N_ORION_URL', 'https://n8n.srv1779459.hstgr.cloud/webhook/orion');
define('N8N_IA_URL', 'https://n8n.srv1779459.hstgr.cloud/webhook/kosmos-ia');
define('N8N_CONFIRMA_URL', 'https://n8n.srv1779459.hstgr.cloud/webhook/confirmar-email');
```

> **Sobre o `N8N_IA_URL`:** é o fluxo "Kosmos — IA", que tenta três IAs em sequência
> (Groq, depois Gemini, depois OpenRouter): se uma falhar ou ficar sem cota, a próxima
> responde. As chaves dessas IAs ficam nas credenciais do n8n, nunca no seu PC. Diferente
> do fluxo de exercícios, este pede o token, então o endereço pode ficar aqui no guia.

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
   Deve terminar com `75 de 75 testes passaram` (ou mais).
   Com o MySQL ligado, rode também `C:\xampp\php\php.exe Backend/testes/banco.php`
   (cria e apaga um banco `kosmos_teste`).
2. **Site:** abra `http://localhost/NOME-DA-SUA-PASTA/` e você deve cair na página inicial.
3. **Login:** crie uma conta ou entre na sua.
4. **Redefinição de senha:** no login, clique em **Esqueceu a senha?** e use o
   **seu** e-mail (de uma conta que existe no seu banco). O e-mail deve chegar, e o
   botão dele precisa abrir a página de nova senha no **seu** localhost.
5. **IA:** gere um exercício em qualquer matéria.
6. **Orion:** na aba Orion, pergunte algo como "onde vejo os cartões para revisar?". A resposta deve vir da IA, e não uma das respostas prontas.

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

## 🧪 Conta de desenvolvedor (tudo liberado)

Para ver cada tela no estado mais cheio — nível 50 com o XP máximo,
sequência de 365 dias, todas as conquistas e recompensas, ~13 meses de
Pomodoro com dias de todo tipo no gráfico semanal e no calendário,
cadernos, resumos, baralhos, provas e exercícios —, crie a conta de
desenvolvedor no seu banco local:

```bash
C:\xampp\php\php.exe Backend/tarefas/conta_dev.php --senha=uma-senha-sua
```

Depois entre com `dev@kosmos.local` e a senha que você escolheu (sem `--senha`,
o script sorteia uma e mostra no fim).

- **Para ela nunca perder a sequência nem nada,** ponha no seu `config.php`:
  ```php
  define('CONTA_DEV_EMAIL', 'dev@kosmos.local');
  ```
  Com isso, no primeiro acesso de cada dia (login ou abrir a dashboard) a conta é
  repreenchida sozinha, com tudo contado a partir de hoje: sequência, gráfico da
  semana, provas, cartões. Leva ~1 s, uma vez por dia, e ninguém é deslogado.
  O que você criar nela durante o dia some na renovação seguinte.
- **Rodar o script de novo** também repreenche na hora (a senha continua a mesma,
  a menos que você passe `--senha`).
- **Só funciona com o banco em `localhost`,** e só mexe numa conta se ela for mesmo a
  de desenvolvedor. Para apagar a conta: `--apagar`.
- **Nunca preencha `CONTA_DEV_EMAIL` num servidor de verdade.** No modelo
  (`config.example.php`) ele vem vazio, ou seja, desligado.

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
- **Para agendar** (só no computador escolhido): na raiz do projeto, rode
  ```bash
  powershell -ExecutionPolicy Bypass -File Backend	arefas\agendar-windows.ps1
  ```
  Ele cria três tarefas no Agendador do Windows: lembrete (todo dia, 19h), resumo
  (domingo, 18h) e a limpeza do banco (todo dia, 3h). Para desfazer, rode de novo com
  `-Remover` no fim. O PC precisa estar com o XAMPP (MySQL) ligado; se estiver desligado
  no horário, a tarefa roda quando ele ligar.

---

## ✉️ Confirmação de e-mail: como o fluxo do n8n foi criado

> **O fluxo já existe** (`confirmar-email`). Para ligar no seu PC, basta a linha
> `N8N_CONFIRMA_URL` do passo 3. Os passos abaixo ficam como registro, caso seja
> preciso recriar o fluxo.

O site já está pronto: com `N8N_CONFIRMA_URL` preenchido, o cadastro manda um link e o
login só entra depois da confirmação. Falta o fluxo no n8n, que é uma **cópia do fluxo de
redefinição de senha** com duas mudanças:

1. No n8n, abra o fluxo de redefinição de senha (webhook `reset-senha`) e use
   **Duplicate**.
2. No nó **Webhook** da cópia, troque o *Path* para `confirmar-email`. A autenticação
   (Header Auth, `X-Kosmos-Token`) continua a mesma.
3. No nó **Send Email**, troque o assunto para *Confirme seu e-mail no Kosmos* e cole no
   campo HTML todo o conteúdo de `Backend/notes/email-confirmar.html`.
4. **Ative** o fluxo e copie a *Production URL* do Webhook.
5. No `config.php` de cada pessoa (e do servidor), preencha:
   ```php
   define('N8N_CONFIRMA_URL', 'https://n8n.srv1779459.hstgr.cloud/webhook/confirmar-email');
   ```

O PHP manda para o fluxo o mesmo formato do reset: `{ email, nome, link }`. Para testar:
cadastre um e-mail seu, abra o link que chegar e confira se você entra direto no
dashboard. Contas que já existiam, as do Google e a conta de dev não precisam confirmar.
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
| O Orion só dá respostas prontas ("Estou sem sinal com a minha IA") | `N8N_ORION_URL` faltando ou errado, ou token diferente do n8n | confira o passo 3; o motivo aparece em `Backend/logs/php-erros.log` (linha `[KOSMOS Orion]`) |
| O Orion responde "Você mandou muitas mensagens em pouco tempo" | limite de 40 mensagens por hora por conta | é normal: espere alguns minutos |
| Exercícios do resumo, flashcards com IA ou rascunho com IA dizem "não está disponível agora" | falta o `N8N_IA_URL` no `config.php` | passo 3 |
| A IA dos resumos dá "não respondeu agora" | endereço errado, token diferente do n8n ou o fluxo "Kosmos — IA" desativado | confira o passo 3; o motivo aparece em `Backend/logs/php-erros.log` (linha `[KOSMOS IA]`) |
| A IA dos resumos diz que está "sem cota" | as três IAs do fluxo bateram o limite gratuito do dia | é normal: tente mais tarde (os cartões por regra continuam funcionando) |
| "Você usou a IA muitas vezes em pouco tempo" | limite de uso de IA por conta (vale para exercícios, flashcards e rascunho juntos) | espere alguns minutos |
| O log mostra `[KOSMOS limite]` a cada mensagem ao Orion | faltou a migração `2026-10-22_orion.sql` (o Orion funciona, mas sem limite de uso) | rode o `migrar.php` (passo 4) |
| Datas ou sequência de dias estranhas | migração não aplicada | passo 4 |

**Dica:** quase todo erro deixa uma pista em `Backend/logs/php-erros.log`. Abra esse
arquivo antes de pedir ajuda; a última linha costuma dizer o que aconteceu.
