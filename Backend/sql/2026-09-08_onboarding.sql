-- ============================================================
--  KOSMOS — Migração: Pesquisa de Onboarding ao Entrar no App
--  Data: 2026-09-08
--
--  Adiciona colunas para a pesquisa de perfil inicial ao logar:
--    - objetivo: foco principal (ENEM/Vestibulares, Concursos, etc.)
--    - ano_escolar: nível atual de estudos
--    - onboarding_completo: flag (1 = já respondeu, 0 = novo aluno)
-- ============================================================

ALTER TABLE `usuario_preferencias`
  ADD COLUMN IF NOT EXISTS `objetivo` VARCHAR(50) DEFAULT NULL AFTER `meta_diaria`,
  ADD COLUMN IF NOT EXISTS `ano_escolar` VARCHAR(50) DEFAULT NULL AFTER `objetivo`,
  ADD COLUMN IF NOT EXISTS `onboarding_completo` TINYINT(1) NOT NULL DEFAULT 0 AFTER `ano_escolar`;
