-- ============================================================
--  KOSMOS — Migração: limite de mensagens do Orion com IA
--  Data: 2026-10-22 (depois de 2026-10-19_seguranca.sql)
--
--  O Orion passou a responder com IA (Backend/php/orion_chat.php).
--  Cada mensagem gasta a cota do Groq, que é do projeto inteiro,
--  então ela entra no limite de tentativas com um tipo próprio:
--  o "orion" (ver LIMITES em limite_util.php).
--
--  Pode rodar de novo:
--      mysql -u root kosmos < 2026-10-22_orion.sql
-- ============================================================

SET NAMES utf8mb4;

ALTER TABLE `tentativas_acesso`
  MODIFY `tipo` enum('login','reset','cadastro','ia','orion') NOT NULL;
