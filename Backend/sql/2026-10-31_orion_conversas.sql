-- ============================================================
--  KOSMOS — Migração: conversas salvas do Orion
--  Data: 2026-10-31 (depois de 2026-10-29_pomodoro_inicio.sql)
--
--  A conversa com o Orion morava só no sessionStorage do navegador:
--  fechou a aba, sumiu, e só existia uma por vez. Agora cada conversa
--  fica no banco, por conta, e a aba Orion lista as antigas para a
--  pessoa voltar a elas (como no ChatGPT).
--
--  Quem grava é o orion_chat.php, depois que a IA responde: a
--  pergunta e a resposta entram juntas. As respostas prontas (IA
--  fora do ar) não são gravadas — elas não são o Orion de verdade.
--  Quem lista, abre e apaga é o orion_conversas.php.
--
--  Pode rodar de novo:
--      mysql -u root kosmos < 2026-10-31_orion_conversas.sql
-- ============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `orion_conversas` (
  `id`            int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`    int(10) UNSIGNED NOT NULL,
  `titulo`        varchar(80) NOT NULL,          -- o começo da primeira pergunta
  `criada_em`     datetime NOT NULL DEFAULT current_timestamp(),
  `atualizada_em` datetime NOT NULL DEFAULT current_timestamp(),   -- a última mensagem: ordena a lista
  PRIMARY KEY (`id`),
  KEY `idx_usuario_recente` (`usuario_id`, `atualizada_em`),
  CONSTRAINT `fk_orion_conversa_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `orion_mensagens` (
  `id`          int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversa_id` int(10) UNSIGNED NOT NULL,
  `autor`       enum('usuario','orion') NOT NULL,
  `texto`       text NOT NULL,
  `humor`       varchar(20) DEFAULT NULL,        -- só nas do Orion: a expressão do mascote
  `criada_em`   datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_conversa` (`conversa_id`, `id`),
  CONSTRAINT `fk_orion_mensagem_conversa`
    FOREIGN KEY (`conversa_id`) REFERENCES `orion_conversas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
