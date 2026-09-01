# 🌌 KOSMOS

Plataforma web de organização de estudos para estudantes do ensino médio / vestibular.
Projeto de **TCC** (curso técnico de T.I.), 100% gratuito e com interface em português.

---

## 🧰 Tecnologias

| Camada | Stack |
|---|---|
| **Frontend** | HTML, CSS e JavaScript puro (sem framework/bundler) |
| **Backend** | PHP + MySQL |
| **Servidor** | XAMPP (Apache + MySQL) |
| **Integrações** | n8n (self-hosted) para IA e envio de e-mails |

Identidade visual: tema escuro "cósmico" roxo, fontes Syne (títulos) + DM Sans (corpo).

A **parte pública** (landing page, login, cadastro) é HTML estático que conversa com o
PHP por `fetch`. A **dashboard** é renderizada no servidor: as páginas são `.php` e já
chegam ao navegador com os dados do usuário dentro do HTML.

---

## 📁 Estrutura do projeto

```
KOSMOS/
└── AppPrincipal/
    ├── .htaccess                   # config do Apache (ErrorDocument 404 → página personalizada)
    ├── Backend/
    │   ├── kosmos.sql              # dump do banco (estrutura de todas as tabelas)
    │   ├── sql/                    # migrações para bancos que já existem
    │   ├── notes/                  # anotações de desenvolvimento
    │   ├── uploads/
    │   │   ├── .htaccess           # blinda a pasta: nada aqui pode ser executado
    │   │   └── avatares/           # fotos de perfil enviadas pelos usuários
    │   └── php/                    # endpoints + páginas do servidor
    │       ├── conexao.php         # conexão com o banco  (NÃO versionado — ver .example)
    │       ├── config.php          # segredos/integrações  (NÃO versionado — ver .example)
    │       ├── pagina_dashboard.php # "porteiro" das páginas da dashboard (ver abaixo)
    │       ├── materias.php        # lista de matérias e cores do avatar (fonte única)
    │       ├── datas.php           # formata datas em pt-BR (sem calcular nada)
    │       ├── login*.php, cadastro.php, sessao.php, senha_*.php
    │       ├── conta_*.php         # perfil, senha, preferências, sessões, avatar, LGPD
    │       ├── flashcards_*.php    # decks, cartões e revisões
    │       ├── resumos_*.php       # listar / salvar / excluir resumos
    │       └── gerar_exercicios.php # ponte para o n8n (exercícios com IA)
    └── Frontend/
        └── pages/
            ├── 404.html            # página de erro 404 personalizada (usada pelo .htaccess)
            ├── inicio/             # landing page (ponto de entrada)
            ├── login/              # login, redefinição de senha e criar-senha.html
            ├── cadastro/           # cadastro + termos de uso + política de privacidade
            ├── dashboard/          # área logada — páginas .php
            │   ├── index.php       # Início
            │   ├── resumos.php     # lista de resumos
            │   ├── resumo.php      # prévia/leitura de um resumo (?id=)
            │   ├── flashcards.php  # baralhos e cartões
            │   ├── exercicios.php  # exercícios com IA
            │   ├── pomodoro.php    # timer
            │   ├── conta.php       # conta (6 seções)
            │   └── partes/         # pedaços reaproveitados (sidebar e modais)
            └── shared/             # recursos comuns (favicon, imagem de compartilhamento, cursor)
```

Cada pasta de página é autocontida, com seus próprios `css/`, `js/` e `imagens/`.

> **📣 Para a equipe:** as páginas da dashboard mudaram de `.html` para `.php`
> (ex.: `dashboard/index.html` → `dashboard/index.php`). Links antigos salvos no
> navegador vão dar 404 — atualize os favoritos.

---

## 🚀 Como rodar localmente

1. **Instale o [XAMPP](https://www.apachefriends.org/)** e inicie **Apache** e **MySQL** no painel de controle.

2. **Coloque o projeto** dentro de `C:\xampp\htdocs\` (ex.: `C:\xampp\htdocs\KOSMOS\AppPrincipal`).

3. **Crie o banco de dados:**
   - Abra o phpMyAdmin (`http://localhost/phpmyadmin`).
   - Importe o arquivo `Backend/kosmos.sql` — ele já cria o banco `kosmos` e todas as tabelas.
   - *Já tinha o banco criado antes?* **Não importe o `kosmos.sql` por cima** (ele falha
     em "Table 'usuarios' already exists" e para no meio). Rode só as migrações que
     faltam, em ordem:
     ```bash
     cd Backend/sql
     mysql -u root kosmos < 2026-08-25_flashcards.sql   # decks e cartões
     mysql -u root kosmos < 2026-08-26_conta.sql        # preferências, avatar e sessoes_versao
     mysql -u root kosmos < 2026-08-27_resumos.sql      # resumos
     ```
     Todas usam `IF NOT EXISTS`, então rodar de novo por engano não quebra nada.

4. **Configure os arquivos de ambiente** (eles não vêm no repositório por conterem segredos):
   ```bash
   cd Backend/php
   cp conexao.example.php conexao.php
   cp config.example.php  config.php
   ```
   Depois, edite `config.php` e preencha o token do n8n, o Google Client ID e as URLs.
   O `conexao.php` já vem com os padrões do XAMPP (usuário `root`, senha vazia).

5. **Acesse pelo navegador** (sempre via `http://localhost`, nunca via Live Server — o PHP só roda no Apache):
   ```
   http://localhost/KOSMOS/AppPrincipal/Frontend/pages/inicio/index.html
   ```
   > Se você colocar o projeto em outra pasta, ajuste esse caminho e a constante `APP_URL` no `config.php`.

---

## 🗄️ Tabelas do banco

| Tabela | Para quê |
|---|---|
| `usuarios` | conta, senha (bcrypt), Google, sequência de dias, `sessoes_versao` |
| `usuario_preferencias` | avatar (cor, foto e enquadramento), tempos do pomodoro, meta diária, matérias favoritas, notificações |
| `flashcard_decks` / `flashcard_cartoes` | baralhos, cartões e estatísticas de revisão |
| `resumos` | título, matéria e o texto do resumo |

Todas as tabelas filhas apontam para `usuarios` com `ON DELETE CASCADE`: apagar a conta
apaga os dados junto. E **toda** consulta filtra por `usuario_id` — é isso que impede
uma pessoa de abrir o conteúdo de outra trocando o `?id=` na URL.

---

## 🔐 Como a dashboard funciona

Toda página da dashboard começa assim:

```php
require_once __DIR__ . '/../../../Backend/php/pagina_dashboard.php';
```

Esse "porteiro" roda **antes de qualquer HTML** e cuida de:

- **barrar quem não está logado** — redireciona (302) para o login, em vez de mostrar a
  tela e esconder depois com JavaScript;
- **derrubar sessões antigas** — compara a sessão com `usuarios.sessoes_versao` (é assim
  que o "sair de todos os aparelhos" funciona);
- **exigir senha de quem entrou com o Google** — manda para `login/criar-senha.html`;
- **entregar os dados prontos** em `$USUARIO` (nome, inicial, avatar, sequência,
  contagem de decks, cartões e resumos) e `$PREF` (preferências), além de `hesc()` para
  escapar o que vai para a tela.

A sidebar e os modais ficam em `dashboard/partes/` e são incluídos com `include` —
existe **uma** cópia de cada, não seis.

**Regra de datas:** nenhuma data é calculada no PHP. O MySQL devolve `DAY()`, `MONTH()`
e `YEAR()`, e o PHP só formata (`datas.php`). Motivo no "Problemas conhecidos" abaixo.

---

## ✅ Status das funcionalidades

| Funcionalidade | Status |
|---|---|
| Cadastro (bcrypt, validações, e-mail único) | ✅ Funcional |
| Login por e-mail/senha | ✅ Funcional |
| Login com Google (OAuth) | ✅ Funcional |
| Senha obrigatória depois do login com Google | ✅ Funcional |
| Sessões reais + proteção da dashboard **no servidor** | ✅ Funcional |
| Redefinição de senha (e-mail via n8n) | ✅ Funcional |
| **Conta modular** (perfil, segurança, estudo, notificações, privacidade, sobre) | ✅ Funcional |
| **Foto de perfil** (upload + escolher o enquadramento) | ✅ Funcional |
| LGPD: exportar meus dados / excluir a conta | ✅ Funcional |
| Sequência de dias (streak) | ✅ Funcional |
| Pomodoro (com os tempos salvos nas preferências) | ✅ Funcional |
| **Flashcards** (decks, cartões, estudo e estatísticas) | ✅ Funcional |
| **Resumos** (escrever, editar, apagar, prévia de leitura) | ✅ Funcional |
| Preferências de e-mail de notificação | 🟡 Salvam, mas o envio ainda não existe |
| Gráfico "Esta semana" da Início | 🟡 Estado vazio honesto — falta gravar as sessões do pomodoro |
| "Primeiros passos" da Início | 🟡 Marcado só no navegador (`localStorage`), não na conta |
| Exercícios com IA (n8n + Groq) | 🔴 Travado — ver abaixo |

---

## ⚠️ Problemas conhecidos (a resolver)

- **Fuso horário do PHP × MySQL divergem.** No XAMPP, o PHP roda em `Europe/Berlin` e o
  MySQL em `America/Sao_Paulo` (`@@system_time_zone`), então `new DateTime('today')` (PHP)
  e `CURDATE()` (MySQL) podem cair em **dias diferentes**. Isso já causou um bug na
  **sequência de dias (streak)**, que somava +1 a cada recarga; foi **contornado** fazendo
  o cálculo 100% no MySQL em `Backend/php/sessao.php`. **Mas a causa raiz continua:**
  qualquer nova funcionalidade que misture datas do PHP e do MySQL pode quebrar.
  - **A resolver:** padronizar o fuso — ex.: `date_default_timezone_set('America/Sao_Paulo')`
    num bootstrap do PHP **e** `SET time_zone = '-03:00'` (ou `America/Sao_Paulo`) na
    conexão MySQL.
  - *Obs.: os valores de `usuarios.sequencia` que haviam inflado por causa desse bug já
    foram resetados (2026-07-15).*

- **Exercícios com IA: o webhook responde `500 {"message":"Error in workflow"}`.** O lado
  do PHP está certo (o webhook de produção está registrado e recusa token errado com 403),
  então o erro é **dentro do fluxo do n8n** — suspeitas: a chave/modelo do Groq ou o nó de
  Code. A tela já trata a falha, mas nenhuma questão é gerada.

- **As sessões do pomodoro não são gravadas.** Sem uma tabela `pomodoro_sessoes`, o
  gráfico "Esta semana" fica sempre vazio, a meta diária não tem como avançar e a
  métrica de exercícios da Início continua em "—".

- **E-mails de notificação não são enviados.** As preferências (`notif_lembrete`,
  `notif_resumo`) são salvas, mas falta o gatilho que dispara o envio pelo n8n.

- **Sem a extensão GD no PHP local.** Por isso a foto de perfil é **reduzida no navegador**
  antes do upload, e o servidor só valida (tipo real, tamanho e dimensões). A pasta
  `Backend/uploads/` tem um `.htaccess` que bloqueia execução em três camadas.

- **Fontes vindas do Google Fonts** bloqueiam a primeira renderização (170–240 ms medidos).
  A resolver: servir Syne e DM Sans do próprio projeto.

---

## 🔒 Configuração e segredos

- `config.php` e `conexao.php` **não são versionados** (estão no `.gitignore`).
- Use os arquivos `*.example.php` como modelo.
- Nunca commite tokens, senhas ou chaves de API.
- As fotos enviadas (`Backend/uploads/avatares/`) também ficam fora do repositório.

---

## 👥 Autores

Projeto desenvolvido por estudantes do curso técnico de T.I. como Trabalho de Conclusão de Curso.
