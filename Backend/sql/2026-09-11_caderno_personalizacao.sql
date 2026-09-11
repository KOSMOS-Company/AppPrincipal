-- ============================================================
--  KOSMOS — Migração: personalização e ordem dos cadernos
--  Data: 2026-09-11
--
--  Complementa a 2026-09-09_resumo_cadernos.sql. O caderno deixa
--  de ser só "nome + matéria" e ganha identidade visual, para a
--  pessoa achar o que quer de relance em vez de ler nome por nome:
--
--    cor          -> a lombada e a capa ganham cor (mesma paleta
--                    de 6 cores já usada no avatar da conta)
--    icone        -> um emoji da lista curada (ver materias.php)
--    descricao    -> uma linha abaixo do nome
--    capa_arquivo -> foto de capa (fica em Backend/uploads/cadernos)
--    ordem        -> a posição escolhida arrastando na estante
--
--  Rode isto em bancos que JÁ existem:
--      mysql -u root kosmos < 2026-09-11_caderno_personalizacao.sql
--
--  Requer MariaDB 10.2+ / MySQL 8+ pelos "IF NOT EXISTS" do ALTER
--  (é o que deixa a migração poder rodar duas vezes sem quebrar).
-- ============================================================

ALTER TABLE `resumo_cadernos`
  ADD COLUMN IF NOT EXISTS `cor`          varchar(16)  NOT NULL DEFAULT 'roxo' AFTER `materia`,
  ADD COLUMN IF NOT EXISTS `icone`        varchar(16)  NOT NULL DEFAULT ''     AFTER `cor`,
  ADD COLUMN IF NOT EXISTS `descricao`    varchar(160) NOT NULL DEFAULT ''     AFTER `icone`,
  ADD COLUMN IF NOT EXISTS `capa_arquivo` varchar(80)  DEFAULT NULL            AFTER `descricao`;

-- Posição na estante. Tudo começa em 0: com ORDER BY ordem ASC,
-- criado_em DESC, um caderno ainda não arrastado se comporta como
-- antes (o mais novo primeiro). Arrastar grava 1..N.
ALTER TABLE `resumo_cadernos`
  ADD COLUMN IF NOT EXISTS `ordem` int(10) UNSIGNED NOT NULL DEFAULT 0 AFTER `capa_arquivo`;

ALTER TABLE `resumo_cadernos`
  ADD KEY IF NOT EXISTS `idx_caderno_ordem` (`usuario_id`, `ordem`);
