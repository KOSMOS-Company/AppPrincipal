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

---

## 📁 Estrutura do projeto

```
KOSMOS/
├── .htaccess               # config do Apache (ErrorDocument 404 → página de erro personalizada)
└── AppPrincipal/
    ├── Backend/
    │   ├── kosmos.sql          # dump do banco (estrutura da tabela "usuarios")
    │   ├── notes/              # anotações de desenvolvimento
    │   └── php/                # endpoints PHP (login, cadastro, sessão, conta, etc.)
    │       ├── conexao.php     # conexão com o banco  (NÃO versionado — ver .example)
    │       └── config.php      # segredos/integrações  (NÃO versionado — ver .example)
    └── Frontend/
        └── pages/
            ├── 404.html        # página de erro 404 personalizada (usada pelo .htaccess)
            ├── inicio/         # landing page (ponto de entrada)
            ├── login/          # login + redefinição de senha
            ├── cadastro/       # cadastro + termos de uso + política de privacidade
            ├── dashboard/      # área logada (resumos, flashcards, exercícios, pomodoro, conta)
            └── shared/         # recursos comuns (favicon, imagem de compartilhamento, cursor animado)
```

Cada pasta de página é autocontida, com seus próprios `css/`, `js/` e `imagens/`.

---

## 🚀 Como rodar localmente

1. **Instale o [XAMPP](https://www.apachefriends.org/)** e inicie **Apache** e **MySQL** no painel de controle.

2. **Coloque o projeto** dentro de `C:\xampp\htdocs\` (ex.: `C:\xampp\htdocs\KOSMOS\AppPrincipal`).

3. **Crie o banco de dados:**
   - Abra o phpMyAdmin (`http://localhost/phpmyadmin`).
   - Crie um banco chamado `kosmos`.
   - Importe o arquivo `Backend/kosmos.sql`.

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

## ✅ Status das funcionalidades

| Funcionalidade | Status |
|---|---|
| Cadastro (bcrypt, validações, e-mail único) | ✅ Funcional |
| Login por e-mail/senha | ✅ Funcional |
| Login com Google (OAuth) | ✅ Funcional |
| Sessões reais + proteção da dashboard | ✅ Funcional |
| Redefinição de senha (e-mail via n8n) | ✅ Funcional |
| Página de conta (editar perfil, trocar senha, logout) | ✅ Funcional |
| Sequência de dias (streak) | ✅ Funcional |
| Pomodoro | ✅ Funcional |
| Flashcards / Resumos / Exercícios | 🟡 UI pronta, sem persistência |
| Estatísticas / gráfico da dashboard | 🟡 Dados mockados |
| Exercícios com IA (n8n + Groq) | 🟡 Em integração |

---

## ⚠️ Problemas conhecidos (a resolver)

- **Fuso horário do PHP × MySQL divergem.** No XAMPP, o PHP roda em `Europe/Berlin` e o MySQL em `America/Sao_Paulo` (`@@system_time_zone`), então `new DateTime('today')` (PHP) e `CURDATE()` (MySQL) podem cair em **dias diferentes**. Isso já causou um bug na **sequência de dias (streak)**, que somava +1 a cada recarga; foi **contornado** fazendo o cálculo 100% no MySQL em `Backend/php/sessao.php`. **Mas a causa raiz continua:** qualquer nova funcionalidade que misture datas do PHP e do MySQL pode quebrar.
  - **A resolver:** padronizar o fuso — ex.: `date_default_timezone_set('America/Sao_Paulo')` num bootstrap do PHP **e** `SET time_zone = '-03:00'` (ou `America/Sao_Paulo`) na conexão MySQL.
  - *Obs.: os valores de `usuarios.sequencia` que haviam inflado por causa desse bug já foram resetados (2026-07-15).*

---

## 🔒 Configuração e segredos

- `config.php` e `conexao.php` **não são versionados** (estão no `.gitignore`).
- Use os arquivos `*.example.php` como modelo.
- Nunca commite tokens, senhas ou chaves de API.

---

## 👥 Autores

Projeto desenvolvido por estudantes do curso técnico de T.I. como Trabalho de Conclusão de Curso.
