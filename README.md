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
AppPrincipal/
├── Backend/
│   ├── kosmos.sql          # dump do banco (estrutura da tabela "usuarios")
│   ├── notes/              # anotações de desenvolvimento
│   └── php/                # endpoints PHP (login, cadastro, sessão, conta, etc.)
│       ├── conexao.php     # conexão com o banco  (NÃO versionado — ver .example)
│       └── config.php      # segredos/integrações  (NÃO versionado — ver .example)
└── Frontend/
    └── pages/
        ├── inicio/         # landing page (ponto de entrada)
        ├── login/          # login + redefinição de senha
        ├── cadastro/       # cadastro + termos
        ├── dashboard/      # área logada (resumos, flashcards, exercícios, pomodoro, conta)
        └── shared/         # recursos comuns (favicon, cursor animado)
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

## 🔒 Configuração e segredos

- `config.php` e `conexao.php` **não são versionados** (estão no `.gitignore`).
- Use os arquivos `*.example.php` como modelo.
- Nunca commite tokens, senhas ou chaves de API.

---

## 👥 Autores

Projeto desenvolvido por estudantes do curso técnico de T.I. como Trabalho de Conclusão de Curso.
