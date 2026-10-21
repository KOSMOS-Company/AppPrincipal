-- ============================================================
--  KOSMOS — Migração: limites novos e índices de segurança
--  Data: 2026-10-19 (depois de 2026-10-14_tentativas_acesso.sql)
--
--  1. tentativas_acesso ganha dois tipos:
--       'cadastro' — o cadastro não tinha limite nenhum (criação de
--                    contas em massa, descoberta de e-mails em série);
--       'ia'       — cada geração de exercícios gasta a cota da IA
--                    do projeto inteiro.
--  2. usuarios.google_id vira UNIQUE: o login com Google procura a
--     conta por ele, e duas linhas com o mesmo google_id fariam a
--     busca escolher uma "ao acaso". Também deixa a busca indexada.
--  3. usuarios.reset_token ganha índice: o senha_resetar.php procura
--     por ele, e sem índice cada redefinição lia a tabela inteira.
--
--  Pode rodar de novo:
--      mysql -u root kosmos < 2026-10-19_seguranca.sql
--  (Se o passo 2 falhar com "Duplicate entry", existem duas contas
--   ligadas ao mesmo Google — resolva à mão antes de reaplicar.)
-- ============================================================

SET NAMES utf8mb4;

-- 1) tipos novos de limite
ALTER TABLE `tentativas_acesso`
  MODIFY `tipo` enum('login','reset','cadastro','ia') NOT NULL;

-- 2) google_id único (NULL pode repetir: contas sem Google)
UPDATE `usuarios` SET `google_id` = NULL WHERE `google_id` = '';
ALTER TABLE `usuarios`
  ADD UNIQUE KEY IF NOT EXISTS `uk_google_id` (`google_id`);

-- 3) busca do token de redefinição
ALTER TABLE `usuarios`
  ADD KEY IF NOT EXISTS `idx_reset_token` (`reset_token`);
