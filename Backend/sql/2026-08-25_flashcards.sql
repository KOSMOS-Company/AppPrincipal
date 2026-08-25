-- ============================================================
--  KOSMOS — Migração: persistência dos Flashcards
--  Data: 2026-08-25
--
--  Cria as duas tabelas dos flashcards. Rode isto em bancos que
--  JÁ existem (o kosmos.sql completo já traz as tabelas novas).
--
--      mysql -u root kosmos < 2026-08-25_flashcards.sql
--
--  Observação sobre datas: todas as colunas de data são gravadas
--  pelo relógio do MySQL (CURRENT_TIMESTAMP / NOW()). Nenhuma data
--  vem do PHP — é o que evita o problema de fuso PHP × MySQL
--  descrito no README ("Problemas conhecidos").
-- ============================================================

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
