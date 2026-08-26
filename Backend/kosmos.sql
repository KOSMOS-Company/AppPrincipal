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

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
