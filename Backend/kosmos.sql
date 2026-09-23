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

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
