-- ============================================================
--  KOSMOS — Migração: tutorial guiado pelo Orion
--  Data: 2026-10-26 (depois de 2026-10-26_emblema_telescopio.sql)
--
--  Depois da intro e da pesquisa de perfil, a conta nova ganha um
--  tour pelas abas com o Orion explicando cada uma (js/tutorial.js).
--  `tutorial_visto` segue a mesma regra do `onboarding_completo`:
--  vira 1 no instante em que o tour COMEÇA, não quando termina —
--  ele aparece uma vez só, tenha a pessoa ido até o fim ou pulado.
--
--  Contas criadas antes de hoje não são novas: entram como vistas
--  (a data é fixa pelo mesmo motivo da 2026-09-15_onboarding_visto).
--  Quem quiser rever usa o botão na Conta.
--
--  Pode rodar de novo:
--      mysql -u root kosmos < 2026-10-26_tutorial_orion.sql
-- ============================================================

SET NAMES utf8mb4;

ALTER TABLE `usuario_preferencias`
  ADD COLUMN IF NOT EXISTS `tutorial_visto` TINYINT(1) NOT NULL DEFAULT 0 AFTER `onboarding_completo`;

INSERT INTO `usuario_preferencias` (`usuario_id`, `tutorial_visto`)
SELECT u.`id`, 1
  FROM `usuarios` u
 WHERE u.`criado_em` < '2026-10-27'
ON DUPLICATE KEY UPDATE `tutorial_visto` = 1;
