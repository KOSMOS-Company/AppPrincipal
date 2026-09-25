-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 01/07/2026 às 17:42
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `kosmos`
--
CREATE DATABASE IF NOT EXISTS `kosmos` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `kosmos`;

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(120) NOT NULL,
  `email` varchar(180) NOT NULL,
  `senha_hash` varchar(255) DEFAULT NULL,
  `google_id` varchar(64) DEFAULT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expira` datetime DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `ultimo_acesso` date DEFAULT NULL,
  `sequencia` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `sessoes_versao` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;
-- --------------------------------------------------------

--
-- Estrutura para tabelas `flashcard_decks` e `flashcard_cartoes`
-- (acrescentadas em 2026-08-25 — ver Backend/sql/2026-08-25_flashcards.sql)
--

-- ---------- Baralhos (decks) ----------
CREATE TABLE IF NOT EXISTS `flashcard_decks` (
  `id`             int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`     int(10) UNSIGNED NOT NULL,
  `nome`           varchar(120) NOT NULL,
  `materia`        varchar(40)  NOT NULL,
  `criado_em`      datetime NOT NULL DEFAULT current_timestamp(),
  `revisoes`       int(10) UNSIGNED NOT NULL DEFAULT 0,  -- sessões de estudo concluídas
  `ultima_revisao` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_deck_usuario` (`usuario_id`),
  CONSTRAINT `fk_deck_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Cartões ----------
CREATE TABLE IF NOT EXISTS `flashcard_cartoes` (
  `id`             int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `deck_id`        int(10) UNSIGNED NOT NULL,
  `frente`         varchar(600) NOT NULL,
  `verso`          varchar(600) NOT NULL,
  `ordem`          int(10) UNSIGNED NOT NULL DEFAULT 0,
  `criado_em`      datetime NOT NULL DEFAULT current_timestamp(),
  `revisoes`       int(10) UNSIGNED NOT NULL DEFAULT 0,  -- quantas vezes o cartão já foi respondido
  `acertos`        int(10) UNSIGNED NOT NULL DEFAULT 0,
  `erros`          int(10) UNSIGNED NOT NULL DEFAULT 0,
  `ultima_revisao` datetime DEFAULT NULL,
  `ultimo_resultado` tinyint(1) DEFAULT NULL,  -- 1 = acertou na última revisão, 0 = errou, NULL = nunca revisado
  PRIMARY KEY (`id`),
  KEY `idx_cartao_deck` (`deck_id`),
  CONSTRAINT `fk_cartao_deck`
    FOREIGN KEY (`deck_id`) REFERENCES `flashcard_decks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------- Preferencias do usuario (aba Conta) ----------
CREATE TABLE IF NOT EXISTS `usuario_preferencias` (
  `usuario_id`       int(10) UNSIGNED NOT NULL,
  `avatar_cor`       varchar(16) NOT NULL DEFAULT 'roxo',
  `avatar_arquivo`   varchar(120) DEFAULT NULL,   -- foto de perfil enviada pelo usuario
  `avatar_pos_x`     tinyint(3) UNSIGNED NOT NULL DEFAULT 50,  -- enquadramento da foto (0-100%)
  `avatar_pos_y`     tinyint(3) UNSIGNED NOT NULL DEFAULT 50,
  `pomo_foco`        tinyint(3) UNSIGNED NOT NULL DEFAULT 25,
  `pomo_pausa`       tinyint(3) UNSIGNED NOT NULL DEFAULT 5,
  `pomo_pausa_longa` tinyint(3) UNSIGNED NOT NULL DEFAULT 15,
  `meta_diaria`      smallint(5) UNSIGNED NOT NULL DEFAULT 60,
  `materias`         varchar(255) DEFAULT NULL,
  `notif_lembrete`   tinyint(1) NOT NULL DEFAULT 0,
  `notif_resumo`     tinyint(1) NOT NULL DEFAULT 0,
  `atualizado_em`    datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`usuario_id`),
  CONSTRAINT `fk_pref_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------- Resumos ----------
CREATE TABLE IF NOT EXISTS `resumos` (
  `id`            int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`    int(10) UNSIGNED NOT NULL,
  `titulo`        varchar(140) NOT NULL,
  `materia`       varchar(40) NOT NULL,
  `corpo`         mediumtext NOT NULL,          -- o texto do resumo (o que faltava)
  `criado_em`     datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_resumo_usuario` (`usuario_id`),
  CONSTRAINT `fk_resumo_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura: colunas de onboarding em `usuario_preferencias`
-- (acrescentadas em 2026-09-08 — ver Backend/sql/2026-09-08_onboarding.sql)
--
ALTER TABLE `usuario_preferencias`
  ADD COLUMN IF NOT EXISTS `objetivo` VARCHAR(50) DEFAULT NULL AFTER `meta_diaria`,
  ADD COLUMN IF NOT EXISTS `ano_escolar` VARCHAR(50) DEFAULT NULL AFTER `objetivo`,
  ADD COLUMN IF NOT EXISTS `onboarding_completo` TINYINT(1) NOT NULL DEFAULT 0 AFTER `ano_escolar`;

-- --------------------------------------------------------

--
-- Estrutura: cadernos de resumos e imagens anexadas
-- (acrescentadas em 2026-09-09 — ver Backend/sql/2026-09-09_resumo_cadernos.sql)
--
CREATE TABLE IF NOT EXISTS `resumo_cadernos` (
  `id`         int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `nome`       varchar(120) NOT NULL,
  `materia`    varchar(40)  NOT NULL,
  `criado_em`  datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_caderno_usuario` (`usuario_id`),
  CONSTRAINT `fk_caderno_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Resumo -> caderno (ON DELETE SET NULL: apagar caderno devolve o resumo a "Sem caderno")
ALTER TABLE `resumos`
  ADD COLUMN IF NOT EXISTS `caderno_id` int(10) UNSIGNED DEFAULT NULL AFTER `usuario_id`;

ALTER TABLE `resumos`
  ADD KEY IF NOT EXISTS `idx_resumo_caderno` (`caderno_id`);

ALTER TABLE `resumos`
  ADD CONSTRAINT `fk_resumo_caderno`
    FOREIGN KEY IF NOT EXISTS (`caderno_id`) REFERENCES `resumo_cadernos` (`id`) ON DELETE SET NULL;

-- O corpo agora é opcional: um resumo pode ser só as fotos do caderno de papel
ALTER TABLE `resumos`
  MODIFY COLUMN `corpo` mediumtext NOT NULL DEFAULT '';

CREATE TABLE IF NOT EXISTS `resumo_imagens` (
  `id`        int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `resumo_id` int(10) UNSIGNED NOT NULL,
  `arquivo`   varchar(80) NOT NULL,   -- só o nome; a pasta é fixa no PHP
  `legenda`   varchar(140) NOT NULL DEFAULT '',
  `ordem`     int(10) UNSIGNED NOT NULL DEFAULT 0,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_imagem_resumo` (`resumo_id`),
  CONSTRAINT `fk_imagem_resumo`
    FOREIGN KEY (`resumo_id`) REFERENCES `resumos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura: personalização e ordem dos cadernos
-- (acrescentadas em 2026-09-11 — ver Backend/sql/2026-09-11_caderno_personalizacao.sql)
--
ALTER TABLE `resumo_cadernos`
  ADD COLUMN IF NOT EXISTS `cor`          varchar(16)  NOT NULL DEFAULT 'roxo' AFTER `materia`,
  ADD COLUMN IF NOT EXISTS `icone`        varchar(16)  NOT NULL DEFAULT ''     AFTER `cor`,
  ADD COLUMN IF NOT EXISTS `descricao`    varchar(160) NOT NULL DEFAULT ''     AFTER `icone`,
  ADD COLUMN IF NOT EXISTS `capa_arquivo` varchar(80)  DEFAULT NULL            AFTER `descricao`;

-- Posição na estante. Tudo começa em 0: com ORDER BY ordem ASC,
-- criado_em DESC, um caderno ainda não arrastado se comporta como antes.
ALTER TABLE `resumo_cadernos`
  ADD COLUMN IF NOT EXISTS `ordem` int(10) UNSIGNED NOT NULL DEFAULT 0 AFTER `capa_arquivo`;

ALTER TABLE `resumo_cadernos`
  ADD KEY IF NOT EXISTS `idx_caderno_ordem` (`usuario_id`, `ordem`);

-- --------------------------------------------------------

--
-- Estrutura: memória de estudo (sessões, repetição espaçada, provas, busca)
-- (acrescentadas em 2026-09-13 — ver Backend/sql/2026-09-13_estudo.sql)
--
CREATE TABLE IF NOT EXISTS `pomodoro_sessoes` (
  `id`          int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`  int(10) UNSIGNED NOT NULL,
  `minutos`     smallint(5) UNSIGNED NOT NULL,
  `materia`     varchar(40) DEFAULT NULL,   -- por enquanto sempre NULL; prepara "matérias mais estudadas"
  `fim_em`      datetime NOT NULL,
  `dia`         date NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sessao_fim` (`usuario_id`, `fim_em`),
  KEY `idx_sessao_dia` (`usuario_id`, `dia`),
  CONSTRAINT `fk_sessao_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Repetição espaçada (SM-2 enxuto): intervalo, facilidade e próxima revisão
ALTER TABLE `flashcard_cartoes`
  ADD COLUMN IF NOT EXISTS `intervalo`       smallint(5) UNSIGNED NOT NULL DEFAULT 0    AFTER `ultimo_resultado`,
  ADD COLUMN IF NOT EXISTS `facilidade`      decimal(4,2)     NOT NULL DEFAULT 2.50     AFTER `intervalo`,
  ADD COLUMN IF NOT EXISTS `proxima_revisao` date             DEFAULT NULL              AFTER `facilidade`;

ALTER TABLE `flashcard_cartoes`
  ADD INDEX IF NOT EXISTS `idx_cartao_revisao` (`proxima_revisao`);

-- O calendário do estudante
CREATE TABLE IF NOT EXISTS `provas` (
  `id`         int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `titulo`     varchar(80) NOT NULL,
  `materia`    varchar(40) DEFAULT NULL,
  `data`       date NOT NULL,
  `criado_em`  datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_prova_data` (`usuario_id`, `data`),
  CONSTRAINT `fk_prova_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índice FULLTEXT para a busca global
ALTER TABLE `resumos`
  ADD FULLTEXT INDEX IF NOT EXISTS `ft_resumo` (`titulo`, `corpo`);

-- --------------------------------------------------------

--
-- Estrutura: acompanhamento de provas e tópicos
-- (acrescentadas em 2026-09-14 — ver Backend/sql/2026-09-14_provas.sql)
--
ALTER TABLE `provas`
  ADD COLUMN IF NOT EXISTS `anotacoes`    text         DEFAULT NULL AFTER `data`,
  ADD COLUMN IF NOT EXISTS `nota`         decimal(5,2) DEFAULT NULL AFTER `anotacoes`,
  ADD COLUMN IF NOT EXISTS `concluida_em` datetime     DEFAULT NULL AFTER `nota`;

CREATE TABLE IF NOT EXISTS `prova_topicos` (
  `id`        int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `prova_id`  int(10) UNSIGNED NOT NULL,
  `texto`     varchar(120) NOT NULL,
  `feito`     tinyint(1) NOT NULL DEFAULT 0,
  `ordem`     smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_topico_prova` (`prova_id`, `ordem`),
  CONSTRAINT `fk_topico_prova`
    FOREIGN KEY (`prova_id`) REFERENCES `provas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura: matérias e exercícios salvos
-- (acrescentadas em 2026-09-22 — ver Backend/sql/2026-09-22_exercicio_materias.sql
--  e Backend/sql/2026-09-22_exercicios.sql)
--
CREATE TABLE IF NOT EXISTS `exercicio_materias` (
  `id`         int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `nome`       varchar(120) NOT NULL,
  `materia`    varchar(40)  NOT NULL,
  `cor`        varchar(20)  NOT NULL DEFAULT 'roxo',
  `icone`      varchar(10)  NOT NULL DEFAULT '📝',
  `descricao`  varchar(280) NOT NULL DEFAULT '',
  `ordem`      int(10) UNSIGNED NOT NULL DEFAULT 0,
  `criado_em`  datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_exercicio_materia_usuario` (`usuario_id`),
  CONSTRAINT `fk_exercicio_materia_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `exercicios` (
  `id`            int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`    int(10) UNSIGNED NOT NULL,
  `materia_id`    int(10) UNSIGNED NOT NULL,
  `titulo`        varchar(140) NOT NULL,
  `conteudo`      mediumtext NOT NULL,          -- JSON com as questões
  `dificuldade`   varchar(20) NOT NULL DEFAULT 'Médio',
  `criado_em`     datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_exercicio_usuario` (`usuario_id`),
  KEY `idx_exercicio_materia` (`materia_id`),
  CONSTRAINT `fk_exercicio_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_exercicio_materia`
    FOREIGN KEY (`materia_id`) REFERENCES `exercicio_materias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  Progressão: XP, níveis, conquistas e recompensas (2026-09-25)
--  O mesmo das migrações sql/2026-09-25_progresso.sql e
--  sql/2026-09-25_recompensas.sql, sem o XP retroativo (num banco
--  novo não há ninguém com histórico).
-- ============================================================

-- ────────────────────────────────────────────────────────────
--  1. PROGRESSO
--
--  `streak_dias` NÃO é a `usuarios.sequencia`. Aquela conta dias de
--  ACESSO (abrir o app já soma). Esta conta dias de ESTUDO: só avança
--  no primeiro ganho de XP do dia — login + uma ação, como pede a
--  regra da sequência. `ultimo_dia_ativo` é o que permite saber se
--  hoje já contou, sem varrer o histórico.
--
--  Invariante: xp_atual = SUM(historico_xp.quantidade_xp). O
--  histórico é a fonte; esta coluna é o total já somado, para a
--  barra de XP não precisar agregar a tabela a cada página.
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `usuario_progresso` (
  `usuario_id`       int(10) UNSIGNED NOT NULL,
  `xp_atual`         int(10) UNSIGNED NOT NULL DEFAULT 0,
  `nivel_atual`      int(10) UNSIGNED NOT NULL DEFAULT 1,
  `titulo_atual`     varchar(60) NOT NULL DEFAULT 'Poeira Estelar',
  `streak_dias`      int(10) UNSIGNED NOT NULL DEFAULT 0,
  `ultimo_dia_ativo` date DEFAULT NULL,
  `atualizado_em`    timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`usuario_id`),
  CONSTRAINT `fk_progresso_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ────────────────────────────────────────────────────────────
--  2. CURVA DE NÍVEIS
--
--  Custo do nível N (XP para sair dele e chegar ao N+1):
--      round(100 × N^1.5)
--  `xp_necessario` é o total ACUMULADO para alcançar o nível — é o
--  que a consulta precisa ("qual o maior nível cujo piso eu já
--  passei?"). Nível 2 em 100 XP, 5 em 1703, 10 em 11106, 24
--  (Mestre do Cosmos) em 107051. Com um dia de estudo rendendo por
--  volta de 400 XP: começo rápido, topo só com constância.
--
--  Mudar a curva = rodar outro UPDATE aqui. O PHP lê desta tabela.
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `niveis_config` (
  `nivel`                int(10) UNSIGNED NOT NULL,
  `xp_necessario`        int(10) UNSIGNED NOT NULL,
  `titulo`               varchar(60) NOT NULL,
  `recompensa_descricao` varchar(150) NOT NULL DEFAULT '',
  PRIMARY KEY (`nivel`),
  UNIQUE KEY `uk_nivel_xp` (`xp_necessario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `niveis_config` (`nivel`, `xp_necessario`, `titulo`, `recompensa_descricao`) VALUES
  ( 1,       0, 'Poeira Estelar', 'Ponto de partida: toda constelação começa com um grão de poeira.'),
  ( 2,     100, 'Poeira Estelar', 'Destrava a cor Nebulosa.'),
  ( 3,     383, 'Cometa Errante', 'Destrava a moldura Anel de poeira e o título Cometa Errante.'),
  ( 4,     903, 'Cometa Errante', 'Destrava o emblema Foguete.'),
  ( 5,    1703, 'Satélite Vivo', 'Destrava a moldura Órbita e o título Satélite Vivo.'),
  ( 6,    2821, 'Satélite Vivo', 'Destrava a cor Aurora.'),
  ( 7,    4291, 'Satélite Vivo', 'Destrava o emblema Astronauta.'),
  ( 8,    6143, 'Estrela Cadente', 'Destrava a moldura Rastro cadente e o título Estrela Cadente.'),
  ( 9,    8406, 'Estrela Cadente', 'Falta 1 nível para o emblema Saturno.'),
  (10,   11106, 'Estrela Cadente', 'Destrava o emblema Saturno.'),
  (11,   14268, 'Estrela Cadente', 'Falta 1 nível para a moldura Halo de anã branca.'),
  (12,   17916, 'Anã Branca', 'Destrava a moldura Halo de anã branca e o título Anã Branca.'),
  (13,   22073, 'Anã Branca', 'Falta 1 nível para a cor Buraco negro.'),
  (14,   26760, 'Anã Branca', 'Destrava a cor Buraco negro.'),
  (15,   31998, 'Anã Branca', 'Faltam 2 níveis para a moldura Supernova.'),
  (16,   37807, 'Anã Branca', 'Falta 1 nível para a moldura Supernova.'),
  (17,   44207, 'Supernova', 'Destrava a moldura Supernova e o título Supernova.'),
  (18,   51216, 'Supernova', 'Faltam 2 níveis para o emblema Galáxia.'),
  (19,   58853, 'Supernova', 'Falta 1 nível para o emblema Galáxia.'),
  (20,   67135, 'Supernova', 'Destrava o emblema Galáxia.'),
  (21,   76079, 'Supernova', 'Faltam 3 níveis para a moldura Coroa do cosmos.'),
  (22,   85702, 'Supernova', 'Faltam 2 níveis para a moldura Coroa do cosmos.'),
  (23,   96021, 'Supernova', 'Falta 1 nível para a moldura Coroa do cosmos.'),
  (24,  107051, 'Mestre do Cosmos', 'Destrava a moldura Coroa do cosmos e o título Mestre do Cosmos.'),
  (25,  118809, 'Mestre do Cosmos', 'Faltam 5 níveis para a cor Poeira de ouro.'),
  (26,  131309, 'Mestre do Cosmos', 'Faltam 4 níveis para a cor Poeira de ouro.'),
  (27,  144566, 'Mestre do Cosmos', 'Faltam 3 níveis para a cor Poeira de ouro.'),
  (28,  158596, 'Mestre do Cosmos', 'Faltam 2 níveis para a cor Poeira de ouro.'),
  (29,  173412, 'Mestre do Cosmos', 'Falta 1 nível para a cor Poeira de ouro.'),
  (30,  189029, 'Mestre do Cosmos', 'Destrava a cor Poeira de ouro.'),
  (31,  205461, 'Mestre do Cosmos', 'Faltam 9 níveis para o emblema Orion.'),
  (32,  222721, 'Mestre do Cosmos', 'Faltam 8 níveis para o emblema Orion.'),
  (33,  240823, 'Mestre do Cosmos', 'Faltam 7 níveis para o emblema Orion.'),
  (34,  259780, 'Mestre do Cosmos', 'Faltam 6 níveis para o emblema Orion.'),
  (35,  279605, 'Mestre do Cosmos', 'Faltam 5 níveis para o emblema Orion.'),
  (36,  300311, 'Mestre do Cosmos', 'Faltam 4 níveis para o emblema Orion.'),
  (37,  321911, 'Mestre do Cosmos', 'Faltam 3 níveis para o emblema Orion.'),
  (38,  344417, 'Mestre do Cosmos', 'Faltam 2 níveis para o emblema Orion.'),
  (39,  367842, 'Mestre do Cosmos', 'Falta 1 nível para o emblema Orion.'),
  (40,  392197, 'Mestre do Cosmos', 'Destrava o emblema Orion.'),
  (41,  417495, 'Mestre do Cosmos', 'Faltam 9 níveis para a moldura Big Bang.'),
  (42,  443748, 'Mestre do Cosmos', 'Faltam 8 níveis para a moldura Big Bang.'),
  (43,  470967, 'Mestre do Cosmos', 'Faltam 7 níveis para a moldura Big Bang.'),
  (44,  499164, 'Mestre do Cosmos', 'Faltam 6 níveis para a moldura Big Bang.'),
  (45,  528350, 'Mestre do Cosmos', 'Faltam 5 níveis para a moldura Big Bang.'),
  (46,  558537, 'Mestre do Cosmos', 'Faltam 4 níveis para a moldura Big Bang.'),
  (47,  589736, 'Mestre do Cosmos', 'Faltam 3 níveis para a moldura Big Bang.'),
  (48,  621958, 'Mestre do Cosmos', 'Faltam 2 níveis para a moldura Big Bang.'),
  (49,  655213, 'Mestre do Cosmos', 'Falta 1 nível para a moldura Big Bang.'),
  (50,  689513, 'Mestre do Cosmos', 'Destrava a moldura Big Bang.')
ON DUPLICATE KEY UPDATE
  `xp_necessario`        = VALUES(`xp_necessario`),
  `titulo`               = VALUES(`titulo`),
  `recompensa_descricao` = VALUES(`recompensa_descricao`);


-- ────────────────────────────────────────────────────────────
--  3. CONQUISTAS
--
--  `icone_svg` guarda a REFERÊNCIA do ícone (ex.: 'cometa'); o SVG
--  em si mora em Backend/php/conquistas_icones.php, num lugar só,
--  usado pela página de conquistas e pelo aviso de desbloqueio.
--
--  `metrica` + `alvo` tornam a regra dado, não código: "desbloqueia
--  quando a métrica X chega a Y". As métricas são calculadas no
--  ProgressoService (pomodoros, fc_acertos, streak, nivel...). Uma
--  conquista nova sobre uma métrica que já existe é só um INSERT.
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `conquistas` (
  `id`         int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`       varchar(50) NOT NULL,
  `nome`       varchar(80) NOT NULL,
  `descricao`  text NOT NULL,
  `icone_svg`  varchar(40) NOT NULL,
  `xp_bonus`   int(10) UNSIGNED NOT NULL DEFAULT 0,
  `categoria`  enum('foco','memoria','exercicios','constancia') NOT NULL,
  `metrica`    varchar(30) NOT NULL,
  `alvo`       int(10) UNSIGNED NOT NULL DEFAULT 1,
  `ordem`      smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_conquista_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `conquistas` (`slug`, `nome`, `descricao`, `icone_svg`, `xp_bonus`, `categoria`, `metrica`, `alvo`, `ordem`) VALUES
  ('pomodoro_first', 'Primeira órbita', 'Conclua o seu primeiro ciclo de foco de pelo menos 15 minutos.', 'relogio', 25, 'foco', 'pomodoros', 1, 10),
  ('pomodoro_10', 'Foco em órbita', 'Conclua 10 ciclos de foco.', 'foguete', 75, 'foco', 'pomodoros', 10, 20),
  ('pomodoro_50', 'Motor de dobra', 'Conclua 50 ciclos de foco.', 'raio', 200, 'foco', 'pomodoros', 50, 30),
  ('pomodoro_maratona', 'Maratona estelar', 'Conclua 4 ciclos de foco no mesmo dia.', 'chama', 100, 'foco', 'pomodoros_dia', 4, 40),
  ('flashcard_first', 'Primeira revisão', 'Termine a sua primeira sessão de flashcards.', 'cartas', 25, 'memoria', 'fc_sessoes', 1, 10),
  ('flashcard_master_100', 'Memória de cometa', 'Acerte 100 cartões nas revisões.', 'cometa', 100, 'memoria', 'fc_acertos', 100, 20),
  ('flashcard_master_500', 'Mente galáctica', 'Acerte 500 cartões nas revisões.', 'galaxia', 250, 'memoria', 'fc_acertos', 500, 30),
  ('resumo_first', 'Primeiras anotações', 'Escreva o seu primeiro resumo.', 'pena', 25, 'memoria', 'resumos', 1, 40),
  ('resumo_10', 'Biblioteca estelar', 'Escreva 10 resumos.', 'livros', 75, 'memoria', 'resumos', 10, 50),
  ('exercicio_first', 'Primeiro acerto', 'Acerte a sua primeira questão de exercício.', 'alvo', 25, 'exercicios', 'ex_acertos', 1, 10),
  ('exercicio_50', 'Caçador de questões', 'Acerte 50 questões de exercícios.', 'mira', 100, 'exercicios', 'ex_acertos', 50, 20),
  ('exercicio_200', 'Mestre das provas', 'Acerte 200 questões de exercícios.', 'trofeu', 250, 'exercicios', 'ex_acertos', 200, 30),
  ('lista_perfeita', 'Lista impecável', 'Acerte todas as questões de uma lista no mesmo dia.', 'estrela', 100, 'exercicios', 'listas_perfeitas', 1, 40),
  ('orion_first', 'Primeiro contato', 'Gere exercícios com o Orion pela primeira vez.', 'orion', 25, 'exercicios', 'orion', 1, 50),
  ('streak_3_days', 'Aquecendo motores', 'Estude 3 dias seguidos.', 'faisca', 30, 'constancia', 'streak', 3, 10),
  ('streak_7_days', 'Semana em órbita', 'Estude 7 dias seguidos.', 'lua', 100, 'constancia', 'streak', 7, 20),
  ('streak_30_days', 'Constelação fiel', 'Estude 30 dias seguidos.', 'constelacao', 400, 'constancia', 'streak', 30, 30),
  ('nivel_5', 'Ascensão', 'Alcance o nível 5.', 'subida', 50, 'constancia', 'nivel', 5, 40),
  ('nivel_10', 'Brilho próprio', 'Alcance o nível 10.', 'sol', 150, 'constancia', 'nivel', 10, 50),
  ('nivel_24', 'Mestre do Cosmos', 'Alcance o nível 24 e o título máximo.', 'coroa', 500, 'constancia', 'nivel', 24, 60)
ON DUPLICATE KEY UPDATE
  `nome` = VALUES(`nome`), `descricao` = VALUES(`descricao`), `icone_svg` = VALUES(`icone_svg`),
  `xp_bonus` = VALUES(`xp_bonus`), `categoria` = VALUES(`categoria`),
  `metrica` = VALUES(`metrica`), `alvo` = VALUES(`alvo`), `ordem` = VALUES(`ordem`);


-- ────────────────────────────────────────────────────────────
--  4. CONQUISTAS DE CADA PESSOA
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `usuario_conquistas` (
  `id`              int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`      int(10) UNSIGNED NOT NULL,
  `conquista_id`    int(10) UNSIGNED NOT NULL,
  `desbloqueado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_usuario_conquista` (`usuario_id`, `conquista_id`),
  CONSTRAINT `fk_uconq_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_uconq_conquista`
    FOREIGN KEY (`conquista_id`) REFERENCES `conquistas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ────────────────────────────────────────────────────────────
--  5. HISTÓRICO DE XP (auditoria e anti-fraude)
--
--  Uma linha por ganho. Os tetos diários são conferidos AQUI
--  ("quanto esta origem já rendeu hoje?"), por isso o índice começa
--  por usuário + origem + instante.
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `historico_xp` (
  `id`            bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`    int(10) UNSIGNED NOT NULL,
  `quantidade_xp` int(11) NOT NULL,
  `origem_acao`   varchar(50) NOT NULL,
  `detalhes_json` json DEFAULT NULL,
  `criado_em`     timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_xp_origem_dia` (`usuario_id`, `origem_acao`, `criado_em`),
  CONSTRAINT `fk_xp_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ────────────────────────────────────────────────────────────
--  6. RESPOSTAS DE EXERCÍCIOS
--
--  O quiz corrigia tudo no navegador — não havia nada no servidor
--  para "exercício com acerto" valer XP sem confiar no cliente. Agora
--  cada resposta passa por exercicios_responder.php, que corrige com
--  o gabarito guardado no banco.
--
--  UNIQUE por (pessoa, exercício, questão, DIA): a mesma questão rende
--  XP uma vez por dia. Refazer a lista amanhã vale (é estudar de novo);
--  clicar mil vezes hoje, não.
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `exercicio_respostas` (
  `id`            int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`    int(10) UNSIGNED NOT NULL,
  `exercicio_id`  int(10) UNSIGNED NOT NULL,
  `questao`       smallint(5) UNSIGNED NOT NULL,
  `escolhida`     tinyint(3) UNSIGNED NOT NULL,
  `acertou`       tinyint(1) NOT NULL,
  `dia`           date NOT NULL,
  `respondido_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_resposta_dia` (`usuario_id`, `exercicio_id`, `questao`, `dia`),
  KEY `idx_resposta_acerto` (`usuario_id`, `acertou`),
  CONSTRAINT `fk_resposta_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_resposta_exercicio`
    FOREIGN KEY (`exercicio_id`) REFERENCES `exercicios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────
--  7. RECOMPENSAS: CATÁLOGO
--
--  `valor` é o sufixo da classe CSS que desenha a recompensa
--  (css/recompensas.css): avatar-moldura--{valor},
--  avatar-cor--{valor}, avatar-emblema--{valor}.
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `recompensas` (
  `id`           int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`         varchar(50) NOT NULL,
  `tipo`         enum('moldura','cor','emblema') NOT NULL,
  `nome`         varchar(60) NOT NULL,
  `descricao`    varchar(160) NOT NULL DEFAULT '',
  `nivel_minimo` int(10) UNSIGNED NOT NULL,
  `valor`        varchar(30) NOT NULL,
  `ordem`        smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_recompensa_slug` (`slug`),
  UNIQUE KEY `uk_recompensa_valor` (`tipo`, `valor`),
  KEY `idx_recompensa_nivel` (`nivel_minimo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `recompensas` (`slug`, `tipo`, `nome`, `descricao`, `nivel_minimo`, `valor`, `ordem`) VALUES
  ('cor_nebulosa', 'cor', 'Nebulosa', 'Um degradê rosa e azul, como gás de estrela nascendo.', 2, 'nebulosa', 10),
  ('moldura_poeira', 'moldura', 'Anel de poeira', 'Um anel pontilhado de poeira estelar em volta do avatar.', 3, 'poeira', 20),
  ('emblema_foguete', 'emblema', 'Foguete', 'Troca a inicial do avatar por um foguete.', 4, 'foguete', 30),
  ('moldura_orbita', 'moldura', 'Órbita', 'Um anel fino com uma lua em órbita.', 5, 'orbita', 40),
  ('cor_aurora', 'cor', 'Aurora', 'Verde, ciano e violeta — a aurora vista do espaço.', 6, 'aurora', 50),
  ('emblema_astronauta', 'emblema', 'Astronauta', 'Troca a inicial do avatar por um capacete de astronauta.', 7, 'astronauta', 60),
  ('moldura_cadente', 'moldura', 'Rastro cadente', 'Um rastro de luz que contorna o avatar.', 8, 'cadente', 70),
  ('emblema_saturno', 'emblema', 'Saturno', 'Troca a inicial do avatar por Saturno e seus anéis.', 10, 'saturno', 80),
  ('moldura_halo', 'moldura', 'Halo de anã branca', 'Um halo branco e brilhante.', 12, 'halo', 90),
  ('cor_buraco_negro', 'cor', 'Buraco negro', 'Um centro escuro com o horizonte de eventos em roxo.', 14, 'buraco-negro', 100),
  ('moldura_supernova', 'moldura', 'Supernova', 'Um anel de cores que gira devagar, como uma explosão estelar.', 17, 'supernova', 110),
  ('emblema_galaxia', 'emblema', 'Galáxia', 'Troca a inicial do avatar por uma galáxia espiral.', 20, 'galaxia', 120),
  ('moldura_cosmos', 'moldura', 'Coroa do cosmos', 'O anel dourado de quem chegou a Mestre do Cosmos.', 24, 'cosmos', 130),
  ('cor_ouro', 'cor', 'Poeira de ouro', 'Dourado, para quem já viu o cosmos quase todo.', 30, 'ouro', 140),
  ('emblema_orion', 'emblema', 'Orion', 'Troca a inicial do avatar pelo planeta do Orion.', 40, 'orion', 150),
  ('moldura_bigbang', 'moldura', 'Big Bang', 'O nível máximo: um anel que pulsa com todas as cores.', 50, 'bigbang', 160)
ON DUPLICATE KEY UPDATE
  `tipo` = VALUES(`tipo`), `nome` = VALUES(`nome`), `descricao` = VALUES(`descricao`),
  `nivel_minimo` = VALUES(`nivel_minimo`), `valor` = VALUES(`valor`), `ordem` = VALUES(`ordem`);


-- ────────────────────────────────────────────────────────────
--  8. RECOMPENSAS: O QUE A PESSOA EQUIPOU
--  NULL = nada equipado (avatar como sempre foi).
-- ────────────────────────────────────────────────────────────
ALTER TABLE `usuario_preferencias`
  ADD COLUMN IF NOT EXISTS `avatar_moldura` varchar(30) DEFAULT NULL AFTER `avatar_cor`,
  ADD COLUMN IF NOT EXISTS `avatar_emblema` varchar(30) DEFAULT NULL AFTER `avatar_moldura`;

-- A coluna era do tamanho das seis cores básicas; "buraco-negro" não cabia
ALTER TABLE `usuario_preferencias`
  MODIFY COLUMN `avatar_cor` varchar(20) NOT NULL DEFAULT 'roxo';


-- ────────────────────────────────────────────────────────────
--  9. O QUE CADA NÍVEL DESTRAVA
-- ────────────────────────────────────────────────────────────
UPDATE `niveis_config` SET `recompensa_descricao` = 'Ponto de partida: toda constelação começa com um grão de poeira.' WHERE `nivel` = 1;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Destrava a cor Nebulosa.' WHERE `nivel` = 2;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Destrava a moldura Anel de poeira e o título Cometa Errante.' WHERE `nivel` = 3;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Destrava o emblema Foguete.' WHERE `nivel` = 4;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Destrava a moldura Órbita e o título Satélite Vivo.' WHERE `nivel` = 5;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Destrava a cor Aurora.' WHERE `nivel` = 6;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Destrava o emblema Astronauta.' WHERE `nivel` = 7;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Destrava a moldura Rastro cadente e o título Estrela Cadente.' WHERE `nivel` = 8;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Falta 1 nível para o emblema Saturno.' WHERE `nivel` = 9;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Destrava o emblema Saturno.' WHERE `nivel` = 10;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Falta 1 nível para a moldura Halo de anã branca.' WHERE `nivel` = 11;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Destrava a moldura Halo de anã branca e o título Anã Branca.' WHERE `nivel` = 12;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Falta 1 nível para a cor Buraco negro.' WHERE `nivel` = 13;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Destrava a cor Buraco negro.' WHERE `nivel` = 14;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 2 níveis para a moldura Supernova.' WHERE `nivel` = 15;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Falta 1 nível para a moldura Supernova.' WHERE `nivel` = 16;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Destrava a moldura Supernova e o título Supernova.' WHERE `nivel` = 17;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 2 níveis para o emblema Galáxia.' WHERE `nivel` = 18;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Falta 1 nível para o emblema Galáxia.' WHERE `nivel` = 19;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Destrava o emblema Galáxia.' WHERE `nivel` = 20;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 3 níveis para a moldura Coroa do cosmos.' WHERE `nivel` = 21;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 2 níveis para a moldura Coroa do cosmos.' WHERE `nivel` = 22;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Falta 1 nível para a moldura Coroa do cosmos.' WHERE `nivel` = 23;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Destrava a moldura Coroa do cosmos e o título Mestre do Cosmos.' WHERE `nivel` = 24;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 5 níveis para a cor Poeira de ouro.' WHERE `nivel` = 25;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 4 níveis para a cor Poeira de ouro.' WHERE `nivel` = 26;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 3 níveis para a cor Poeira de ouro.' WHERE `nivel` = 27;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 2 níveis para a cor Poeira de ouro.' WHERE `nivel` = 28;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Falta 1 nível para a cor Poeira de ouro.' WHERE `nivel` = 29;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Destrava a cor Poeira de ouro.' WHERE `nivel` = 30;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 9 níveis para o emblema Orion.' WHERE `nivel` = 31;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 8 níveis para o emblema Orion.' WHERE `nivel` = 32;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 7 níveis para o emblema Orion.' WHERE `nivel` = 33;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 6 níveis para o emblema Orion.' WHERE `nivel` = 34;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 5 níveis para o emblema Orion.' WHERE `nivel` = 35;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 4 níveis para o emblema Orion.' WHERE `nivel` = 36;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 3 níveis para o emblema Orion.' WHERE `nivel` = 37;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 2 níveis para o emblema Orion.' WHERE `nivel` = 38;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Falta 1 nível para o emblema Orion.' WHERE `nivel` = 39;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Destrava o emblema Orion.' WHERE `nivel` = 40;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 9 níveis para a moldura Big Bang.' WHERE `nivel` = 41;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 8 níveis para a moldura Big Bang.' WHERE `nivel` = 42;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 7 níveis para a moldura Big Bang.' WHERE `nivel` = 43;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 6 níveis para a moldura Big Bang.' WHERE `nivel` = 44;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 5 níveis para a moldura Big Bang.' WHERE `nivel` = 45;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 4 níveis para a moldura Big Bang.' WHERE `nivel` = 46;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 3 níveis para a moldura Big Bang.' WHERE `nivel` = 47;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 2 níveis para a moldura Big Bang.' WHERE `nivel` = 48;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Falta 1 nível para a moldura Big Bang.' WHERE `nivel` = 49;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Destrava a moldura Big Bang.' WHERE `nivel` = 50;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
