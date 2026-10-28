# 🌌 KOSMOS

[![CI](https://github.com/KOSMOS-Company/AppPrincipal/actions/workflows/ci.yml/badge.svg)](https://github.com/KOSMOS-Company/AppPrincipal/actions/workflows/ci.yml)

Plataforma web de organização de estudos para estudantes do ensino médio / vestibular.
Projeto de **TCC** (curso técnico de T.I.), 100% gratuito e com interface em português.

---

## 🧰 Tecnologias

| Camada | Stack |
|---|---|
| **Frontend** | HTML, CSS e JavaScript puro (sem framework/bundler) |
| **Backend** | PHP 8.1+ e MySQL/MariaDB |
| **Servidor** | Apache 2.4 (no desenvolvimento, o XAMPP) |
| **Integrações** | n8n (self-hosted) para IA e envio de e-mails; Google OAuth |

Identidade visual: tema escuro "cósmico" roxo, com as fontes Syne (títulos) e DM Sans (corpo),
servidas pelo próprio projeto (`Frontend/pages/shared/fontes/`).

A **parte pública** (landing page, login, cadastro) é HTML estático que conversa com o
PHP por `fetch`. A **dashboard** é renderizada no servidor: as páginas são `.php` e já
chegam ao navegador com os dados do usuário dentro do HTML.

### 📚 Documentação

| Documento | O quê |
|---|---|
| [docs/CONFIGURACAO-LOCAL.md](docs/CONFIGURACAO-LOCAL.md) | **comece aqui:** o que configurar no seu PC (o que não vem do GitHub) |
| [docs/API.md](docs/API.md) | todos os endpoints: método, entrada, códigos de erro e limites |
| [docs/BANCO.md](docs/BANCO.md) | diagrama do banco, as 21 tabelas e como fazer uma migração |
| [docs/PRODUCAO.md](docs/PRODUCAO.md) | publicar em servidor: HTTPS, usuário de banco restrito, agendamentos, checklist |

---

## 📁 Estrutura do projeto

```
AppPrincipal/
├── .htaccess                   # 404 portátil, bloqueio do .git, sem listagem de pastas,
│                               # cabeçalhos de segurança, compressão e cache
├── composer.json               # declara a versão do PHP e as extensões (sem pacotes)
├── .github/workflows/ci.yml    # integração contínua: sintaxe PHP/JS + testes
├── docs/                       # API, banco e produção
├── Backend/
│   ├── .htaccess               # nega .sql/.md/.txt/.log pelo navegador
│   ├── kosmos.sql              # estrutura completa do banco (importação inicial)
│   ├── sql/                    # migrações datadas + migrar.php (só pelo terminal)
│   ├── testes/rodar.php        # testes da lógica pura (sem banco)
│   ├── testes/banco.php        # testes com banco (cria e apaga um kosmos_teste)
│   ├── tarefas/                # scripts agendados (notificações por e-mail)
│   ├── logs/                   # php-erros.log (fora do git, bloqueado na web)
│   ├── uploads/                # fotos (avatares, resumos, capas) — nada aqui executa
│   └── php/
│       ├── bootstrap.php       # fuso, log de erros, cabeçalhos e conectar() — roda em toda requisição
│       ├── api_util.php        # formato único das respostas JSON
│       ├── conexao.php         # credenciais do banco  (NÃO versionado — ver .example)
│       ├── config.php          # segredos/integrações  (NÃO versionado — ver .example)
│       ├── sessao.php          # login, sessão, "sair de todos os aparelhos", CSRF
│       ├── pagina_dashboard.php # "porteiro" das páginas da dashboard (ver abaixo)
│       ├── limite_util.php     # limite de tentativas (login, cadastro, senha, IA)
│       ├── senha_util.php      # regras de senha (tamanho, hash, conferência)
│       ├── ProgressoService.php # XP, níveis, conquistas e recompensas
│       ├── exercicios_ia_util.php # leitura da resposta da IA (testada)
│       └── ...                 # endpoints — lista completa em docs/API.md
└── Frontend/
    └── pages/
        ├── 404.php             # página 404 (descobre sozinha onde o app está instalado)
        ├── inicio/             # landing page (ponto de entrada)
        ├── login/              # login, redefinição de senha e criar-senha.html
        ├── cadastro/           # cadastro + termos de uso + política de privacidade
        ├── dashboard/          # área logada — páginas .php
        │   ├── js/util.js      # escaparHtml() — carregado antes de todos os scripts
        │   └── partes/         # pedaços reaproveitados (sidebar, modais)
        └── shared/             # o que todas as páginas usam:
            ├── tokens.css      #   cores, raios e sombras (fonte única)
            ├── fontes.css      #   Syne e DM Sans locais
            ├── cursor.*        #   cursor do site inteiro
            ├── google-login.js #   "Entrar com o Google" (login e cadastro)
            └── cosmos*, mascote*, logo.css ...
```

Cada pasta de página é autocontida, com seus próprios `css/`, `js/` e `imagens/`.

---

## 🚀 Como rodar localmente

> **Já tem o projeto rodando e só precisa se atualizar?** Siga o
> [docs/CONFIGURACAO-LOCAL.md](docs/CONFIGURACAO-LOCAL.md): ele lista tudo o que fica
> fora do GitHub (token do n8n, endereços dos fluxos, banco) e como configurar.

> **📣 Para a equipe (2026-10-19):** o `conexao.php` mudou de formato e agora guarda
> **só as credenciais**. A função `conectar()` passou para o `bootstrap.php`, que é
> versionado. Copie de novo o `conexao.example.php` para `conexao.php` e reponha a
> sua senha, se tiver uma. Um `conexao.php` antigo continua funcionando, mas sem o
> fuso do MySQL. Rode também `migrar.php`: há a migração nova `2026-10-19_seguranca.sql`.

1. **Instale o [XAMPP](https://www.apachefriends.org/)** (Windows, macOS ou Linux) e
   inicie **Apache** e **MySQL**. Em Linux sem XAMPP, veja [docs/PRODUCAO.md](docs/PRODUCAO.md).

2. **Coloque o projeto** dentro de `htdocs` (ex.: `C:\xampp\htdocs\AppPrincipal` ou
   `/opt/lampp/htdocs/AppPrincipal`). **Qualquer nome de pasta funciona:** nenhum caminho
   do projeto é fixo.

3. **Crie o banco de dados:**
   - Abra o phpMyAdmin (`http://localhost/phpmyadmin`).
   - Importe o arquivo `Backend/kosmos.sql`. Ele já cria o banco `kosmos` e todas as tabelas.
   - Depois de importar, registre que o banco já está em dia (o `kosmos.sql` já
     contém tudo o que as migrações fazem):
     ```bash
     C:\xampp\php\php.exe Backend/sql/migrar.php --marcar-todas
     ```
   - *Já tinha o banco criado antes?* **Não importe o `kosmos.sql` por cima** (ele falha
     em "Table 'usuarios' already exists" e para no meio). Use o executor de migrações,
     que guarda na tabela `migracoes` o que já rodou e aplica só o que falta, em ordem:
     ```bash
     C:\xampp\php\php.exe Backend/sql/migrar.php --status   # o que está pendente
     C:\xampp\php\php.exe Backend/sql/migrar.php            # aplica as pendentes
     ```
     Reaplicar tudo às cegas não é seguro: a `2026-09-25_progresso.sql` credita XP retroativo.
   - Toda migração nova vai em `Backend/sql/` com a data no começo do nome **e** é
     replicada no `kosmos.sql` (passo a passo em [docs/BANCO.md](docs/BANCO.md)).

4. **Configure os arquivos de ambiente** (não vêm no repositório porque contêm segredos):
   ```bash
   cd Backend/php
   cp conexao.example.php conexao.php
   cp config.example.php  config.php
   ```
   O `conexao.php` já vem com os padrões do XAMPP (usuário `root`, senha vazia). No
   `config.php`, preencha o token do n8n e o Google Client ID. O `APP_URL` pode ficar
   **vazio** no `localhost`: o app descobre sozinho a pasta onde está instalado.

5. **Acesse pelo navegador** (sempre via `http://localhost`, nunca via Live Server, porque o
   PHP só roda no Apache):
   ```
   http://localhost/AppPrincipal/Frontend/pages/inicio/index.html
   ```

6. **Rode os testes**:
   ```bash
   C:\xampp\php\php.exe Backend/testes/rodar.php
   C:\xampp\php\php.exe Backend/testes/banco.php
   ```
   O primeiro não precisa do banco. O segundo precisa do MySQL ligado: ele cria
   um banco descartável (`kosmos_teste`) a partir do `kosmos.sql`, testa o que
   mora no banco (ciclos de foco, XP, limite de tentativas) e apaga o banco no
   fim — o `kosmos` nunca é tocado.

   **Scripts:** a Content-Security-Policy (`.htaccess` e `bootstrap.php`) só aceita scripts de
   arquivos `.js` do próprio site. `<script>` com código dentro do HTML e atributos como
   `onclick="..."` **não rodam** — use um arquivo `.js` e `addEventListener`. Dados do PHP
   para o JS vão num `<script type="application/json">` (ele não é executado).

   Os mesmos testes, mais a checagem de sintaxe de todo PHP e JS, rodam no GitHub a cada push.

**Dependências**: PHP 8.1+ com `pdo_mysql`, `mbstring`, `curl` e `fileinfo` (o XAMPP já traz
todas) e Apache com `mod_rewrite` e `mod_headers`. Para comprimir e fazer cache dos arquivos,
ligue também `mod_deflate` e `mod_expires`, que vêm desligados no XAMPP: tire o `#` das
linhas `LoadModule` deles no `httpd.conf`. A lista formal está no `composer.json`.

---

## 🔐 Como a dashboard funciona

Toda página da dashboard começa assim:

```php
require_once __DIR__ . '/../../../Backend/php/pagina_dashboard.php';
```

Esse "porteiro" roda **antes de qualquer HTML** e cuida de:

- **barrar quem não está logado**: redireciona (302) para o login, em vez de mostrar a
  tela e esconder depois com JavaScript;
- **derrubar sessões antigas**: compara a sessão com `usuarios.sessoes_versao` (é assim
  que o "sair de todos os aparelhos" funciona);
- **exigir senha de quem entrou com o Google**: manda para `login/criar-senha.html`;
- **entregar os dados prontos** em `$USUARIO` (nome, inicial, avatar, sequência,
  contagem de decks, cartões e resumos), `$PREF` (preferências) e `$PROGRESSO` (XP),
  além de `hesc()` para escapar o que vai para a tela.

A sidebar e os modais ficam em `dashboard/partes/` e são incluídos com `include`.
Existe **uma** cópia de cada, não seis.

**Regra de datas:** nenhuma data é calculada no PHP. O MySQL devolve `DAY()`, `MONTH()`
e `YEAR()`, e o PHP só formata (`datas.php`). Desde 2026-10-19 os dois relógios também estão
alinhados (ver "Resolvidos" abaixo), e a regra continua como segunda proteção.

---

## ✅ Status das funcionalidades

| Funcionalidade | Status |
|---|---|
| Cadastro (bcrypt, validações, e-mail único, limite de tentativas) | ✅ Funcional |
| Login por e-mail/senha (limite de tentativas) | ✅ Funcional |
| Login com Google (OAuth) | ✅ Funcional |
| Senha obrigatória depois do login com Google | ✅ Funcional |
| Sessões reais + proteção da dashboard **no servidor** | ✅ Funcional |
| Redefinição de senha (e-mail via n8n) | ✅ Funcional |
| **Conta** (perfil, segurança, estudo, notificações, privacidade, sobre) | ✅ Funcional |
| **Foto de perfil** (upload + escolher o enquadramento) | ✅ Funcional |
| LGPD: exportar meus dados / excluir a conta (apaga tudo junto) | ✅ Funcional |
| Onboarding (pesquisa de perfil no primeiro acesso) | ✅ Funcional |
| Sequência de dias (streak) | ✅ Funcional |
| **Pomodoro** (sessões gravadas, gráfico "Esta semana", calendário do mês) | ✅ Funcional |
| **Primeiros passos e missões da semana** (lidos do banco, não do navegador) | ✅ Funcional |
| **Resumos e cadernos** (texto, imagens, capas, arrastar entre cadernos) | ✅ Funcional |
| **Flashcards** com repetição espaçada + fila "Revisar hoje" | ✅ Funcional |
| **Gerar flashcards de um resumo** (o app sugere, você confere e salva) | ✅ Funcional |
| **Provas** (contagem, assuntos que caem, material da matéria e a nota) | ✅ Funcional |
| **Progressão** (XP, 50 níveis, conquistas, molduras e emblemas) | ✅ Funcional |
| **Busca** em resumos, cadernos, baralhos, cartões e exercícios | ✅ Funcional |
| Exercícios: matérias, listas salvas, prática com correção no servidor | ✅ Funcional |
| **Orion com IA** (guia do site, tutor de matérias, "o que estudo hoje?", "Por que eu errei?") | ✅ Funcional |
| Geração de exercícios com IA (n8n + Groq) | ✅ Funcional |
| E-mails de notificação (lembrete diário, resumo semanal) | 🟡 Envio funcionando (fluxo no n8n); falta agendar em um computador ou servidor |
| Confirmação de e-mail no cadastro | 🔴 Não existe — ver abaixo |

---

## ⚠️ Problemas conhecidos (a resolver)

- **E-mails de notificação ainda não saem sozinhos.** O fluxo do n8n envia os dois tipos
  (testado), mas alguém precisa agendar o `Backend/tarefas/notificacoes.php` em **um**
  computador ou servidor (ver [docs/PRODUCAO.md](docs/PRODUCAO.md) e o aviso em
  [docs/CONFIGURACAO-LOCAL.md](docs/CONFIGURACAO-LOCAL.md)).

- **A cota gratuita do Groq é compartilhada.** A geração de exercícios e o Orion usam a
  mesma conta. Cada pessoa tem um limite por hora (ver [docs/API.md](docs/API.md)), mas
  com muita gente usando ao mesmo tempo o Groq pode recusar pedidos por alguns minutos. Nesse
  caso, o Orion cai nas respostas prontas, e a geração de exercícios mostra um aviso.

- **O e-mail do cadastro não é confirmado.** Qualquer pessoa pode criar uma conta com o
  e-mail de outra. Já existem duas proteções: o limite de tentativas, e o login com Google
  descarta a senha de uma conta "pré-cadastrada" quando o dono de verdade entra. Pela mesma
  razão, o cadastro ainda responde "este e-mail já está cadastrado", porque sem link de
  confirmação não há outro jeito honesto de avisar quem errou de tela. A solução é enviar um
  link de confirmação pelo n8n (o mesmo esquema da redefinição de senha).

- **Sem a extensão GD no PHP local.** Por isso a foto de perfil é **reduzida no navegador**
  antes do upload, e o servidor só valida (tipo real, tamanho e dimensões).

- **CSS sem minificação.** O projeto não tem etapa de build, de propósito. Com o
  `mod_deflate` ligado (ver "Dependências"), o Apache comprime os arquivos no envio.

### Resolvidos em 2026-10-19

- **Fuso horário do PHP × MySQL.** O PHP do XAMPP rodava em `Europe/Berlin` e o MySQL em
  `America/Sao_Paulo`, e isso já tinha feito a sequência de dias somar +1 a cada recarga.
  Agora o `bootstrap.php` põe os dois no mesmo fuso, em toda requisição.
- **Caminho fixo `/KOSMOS/AppPrincipal/`.** A página 404 e o `APP_URL` só funcionavam numa
  máquina. Agora nada depende da pasta de instalação.
- **Onboarding não salvava.** O JS chamava `../../Backend/...` (um nível a menos), recebia
  404 e fechava o modal sem avisar.
- **Erros não iam para log nenhum.** Agora todo `catch` registra em `Backend/logs/php-erros.log`.
- **Fontes do Google atrasavam a primeira pintura.** Agora são servidas pelo projeto.
- **`.git` e listagem de pastas expostos no navegador.** Agora o `.htaccess` da raiz bloqueia os dois.

---

## 🔒 Segurança

| Proteção | Onde |
|---|---|
| Consultas com parâmetros preparados (sem SQL montado com texto do usuário) | todo o backend |
| Senhas em bcrypt (custo 12), limite de 72 bytes | `senha_util.php` |
| Cookie de sessão `HttpOnly`, `SameSite=Lax`, `Secure` sob HTTPS, ID renovado no login | `sessao.php` |
| POST vindo de outro site é recusado, inclusive em login, cadastro e logout | `exigirMesmaOrigem()` |
| Logout só por POST (um `<img>` de outro site não desloga mais ninguém) | `logout.php` |
| Limite de tentativas: login, cadastro, "esqueci a senha" e geração por IA | `limite_util.php` |
| Login leva o mesmo tempo exista o e-mail ou não (não revela contas) | `login.php` |
| Token de redefinição: aleatório, guardado como hash, uso único, 1 hora | `senha_*.php` |
| Link do e-mail nunca montado a partir do cabeçalho `Host` em produção | `senha_esqueci.php` |
| Login com Google conferido no Google, com o `aud` (Client ID) | `login_google.php` |
| Uploads: tipo detectado pelo conteúdo, nome sorteado, pasta que não executa código | `conta_avatar.php`, `uploads/.htaccess` |
| Cabeçalhos: `nosniff`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, CSP `frame-ancestors` (e HSTS sob HTTPS) | `.htaccess` + `bootstrap.php` |
| Bloqueados na web: `.git`, listagem de pastas, `.sql`, notas, logs, scripts de terminal | `.htaccess` |
| Erros nunca aparecem na tela, só no log | `bootstrap.php` |

**Configuração e segredos**

- `config.php` e `conexao.php` **não são versionados** (estão no `.gitignore`, e o CI
  falha se algum deles for commitado).
- Use os arquivos `*.example.php` como modelo.
- Nunca commite tokens, senhas ou chaves de API. O Client ID do Google não é segredo
  e chega ao navegador por `config_publica.php`.
- Para publicar: HTTPS e um usuário de banco só com `SELECT/INSERT/UPDATE/DELETE`
  (ver [docs/PRODUCAO.md](docs/PRODUCAO.md)).

---

## 👥 Autores

Projeto desenvolvido por estudantes do curso técnico de T.I. como Trabalho de Conclusão de Curso.
