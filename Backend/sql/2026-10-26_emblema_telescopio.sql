-- ============================================================
--  KOSMOS — Migração: o emblema Saturno vira Telescópio
--  Data: 2026-10-26 (depois de 2026-10-22_orion.sql)
--
--  Planeta com anel era quase o mesmo desenho do emblema do Orion.
--  Só muda o que aparece (nome, descrição, texto dos níveis 9 e 10):
--  o slug e o valor continuam 'saturno', então quem já equipou o
--  emblema continua com ele (o desenho novo vem do recompensas.css).
--
--  Pode rodar de novo:
--      mysql -u root kosmos < 2026-10-26_emblema_telescopio.sql
-- ============================================================

SET NAMES utf8mb4;

UPDATE `recompensas`
   SET `nome` = 'Telescópio',
       `descricao` = 'Troca a inicial do avatar por um telescópio apontado para o céu.'
 WHERE `slug` = 'emblema_saturno';

UPDATE `niveis_config` SET `recompensa_descricao` = 'Falta 1 nível para o emblema Telescópio.' WHERE `nivel` = 9;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Destrava o emblema Telescópio.' WHERE `nivel` = 10;
