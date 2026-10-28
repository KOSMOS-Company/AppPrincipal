-- ============================================================
--  KOSMOS — Migração: recompensa do tour do Orion
--  Data: 2026-10-27 (depois de 2026-10-26_tutorial_orion.sql)
--
--  Terminar o tour vale 75 XP (Backend/php/tutorial_concluido.php)
--  mais esta conquista, de 25: os 100 XP levam a conta nova direto
--  ao nível 2, o primeiro desbloqueio (a cor Nebulosa).
--
--  `tutorial_visto` ganha um terceiro valor, sem mudar a coluna:
--    0 = ainda não viu · 1 = viu (o tour começou) · 2 = terminou e foi premiado
--
--  Pode rodar de novo:
--      mysql -u root kosmos < 2026-10-27_tutorial_recompensa.sql
-- ============================================================

SET NAMES utf8mb4;

INSERT INTO `conquistas` (`slug`, `nome`, `descricao`, `icone_svg`, `xp_bonus`, `categoria`, `metrica`, `alvo`, `ordem`) VALUES
  ('tour_orion', 'Decolagem', 'Faça o tour pelo Kosmos com o Orion até o fim.', 'foguete', 25, 'constancia', 'tutorial', 1, 5)
ON DUPLICATE KEY UPDATE
  `nome` = VALUES(`nome`), `descricao` = VALUES(`descricao`), `icone_svg` = VALUES(`icone_svg`),
  `xp_bonus` = VALUES(`xp_bonus`), `categoria` = VALUES(`categoria`), `metrica` = VALUES(`metrica`),
  `alvo` = VALUES(`alvo`), `ordem` = VALUES(`ordem`);
