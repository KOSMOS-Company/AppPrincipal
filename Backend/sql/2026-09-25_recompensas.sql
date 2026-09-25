-- ============================================================
--  KOSMOS — Migração: trilha de recompensas
--  Data: 2026-09-25 (depois de 2026-09-25_progresso.sql)
--
--  O que entra:
--    1. recompensas — o que cada nível destrava para o avatar:
--         moldura (anel em volta), cor exclusiva e emblema (no lugar
--         da inicial). Os títulos continuam em niveis_config.
--    2. usuario_preferencias.avatar_moldura / avatar_emblema — o que
--       a pessoa equipou (a cor exclusiva usa a avatar_cor que já existe).
--    3. niveis_config.recompensa_descricao — os textos passam a dizer
--       o que o nível destrava de verdade.
--
--  Rode depois da migração de progresso (pode rodar de novo):
--      mysql -u root kosmos < 2026-09-25_recompensas.sql
--
--  Não existe tabela de "recompensas de cada pessoa": uma recompensa
--  está liberada quando o nível dela chega a `nivel_minimo`. É o
--  nível que manda — sem estado duplicado para ficar dessincronizado.
-- ============================================================

-- Nomes com acento ("Órbita", "Galáxia"): sem isto o cliente mysql do
-- Windows lê o arquivo como latin1 e grava o texto quebrado.
SET NAMES utf8mb4;


-- ────────────────────────────────────────────────────────────
--  1. CATÁLOGO
--
--  `valor` é o sufixo da classe CSS que desenha a recompensa
--  (css/recompensas.css): avatar-moldura--{valor},
--  avatar-cor--{valor}, avatar-emblema--{valor}.
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `recompensas` (
  `id`           int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`         varchar(50) NOT NULL,
  `tipo`         enum('moldura','cor','emblema') NOT NULL,
  `nome`         varchar(60) NOT NULL,
  `descricao`    varchar(160) NOT NULL DEFAULT '',
  `nivel_minimo` int(10) UNSIGNED NOT NULL,
  `valor`        varchar(30) NOT NULL,
  `ordem`        smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_recompensa_slug` (`slug`),
  UNIQUE KEY `uk_recompensa_valor` (`tipo`, `valor`),
  KEY `idx_recompensa_nivel` (`nivel_minimo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `recompensas` (`slug`, `tipo`, `nome`, `descricao`, `nivel_minimo`, `valor`, `ordem`) VALUES
  ('cor_nebulosa', 'cor', 'Nebulosa', 'Um degradê rosa e azul, como gás de estrela nascendo.', 2, 'nebulosa', 10),
  ('moldura_poeira', 'moldura', 'Anel de poeira', 'Um anel pontilhado de poeira estelar em volta do avatar.', 3, 'poeira', 20),
  ('emblema_foguete', 'emblema', 'Foguete', 'Troca a inicial do avatar por um foguete.', 4, 'foguete', 30),
  ('moldura_orbita', 'moldura', 'Órbita', 'Um anel fino com uma lua em órbita.', 5, 'orbita', 40),
  ('cor_aurora', 'cor', 'Aurora', 'Verde, ciano e violeta — a aurora vista do espaço.', 6, 'aurora', 50),
  ('emblema_astronauta', 'emblema', 'Astronauta', 'Troca a inicial do avatar por um capacete de astronauta.', 7, 'astronauta', 60),
  ('moldura_cadente', 'moldura', 'Rastro cadente', 'Um rastro de luz que contorna o avatar.', 8, 'cadente', 70),
  ('emblema_saturno', 'emblema', 'Saturno', 'Troca a inicial do avatar por Saturno e seus anéis.', 10, 'saturno', 80),
  ('moldura_halo', 'moldura', 'Halo de anã branca', 'Um halo branco e brilhante.', 12, 'halo', 90),
  ('cor_buraco_negro', 'cor', 'Buraco negro', 'Um centro escuro com o horizonte de eventos em roxo.', 14, 'buraco-negro', 100),
  ('moldura_supernova', 'moldura', 'Supernova', 'Um anel de cores que gira devagar, como uma explosão estelar.', 17, 'supernova', 110),
  ('emblema_galaxia', 'emblema', 'Galáxia', 'Troca a inicial do avatar por uma galáxia espiral.', 20, 'galaxia', 120),
  ('moldura_cosmos', 'moldura', 'Coroa do cosmos', 'O anel dourado de quem chegou a Mestre do Cosmos.', 24, 'cosmos', 130),
  ('cor_ouro', 'cor', 'Poeira de ouro', 'Dourado, para quem já viu o cosmos quase todo.', 30, 'ouro', 140),
  ('emblema_orion', 'emblema', 'Orion', 'Troca a inicial do avatar pelo planeta do Orion.', 40, 'orion', 150),
  ('moldura_bigbang', 'moldura', 'Big Bang', 'O nível máximo: um anel que pulsa com todas as cores.', 50, 'bigbang', 160)
ON DUPLICATE KEY UPDATE
  `tipo` = VALUES(`tipo`), `nome` = VALUES(`nome`), `descricao` = VALUES(`descricao`),
  `nivel_minimo` = VALUES(`nivel_minimo`), `valor` = VALUES(`valor`), `ordem` = VALUES(`ordem`);


-- ────────────────────────────────────────────────────────────
--  2. O QUE A PESSOA EQUIPOU
--  NULL = nada equipado (avatar como sempre foi).
-- ────────────────────────────────────────────────────────────
ALTER TABLE `usuario_preferencias`
  ADD COLUMN IF NOT EXISTS `avatar_moldura` varchar(30) DEFAULT NULL AFTER `avatar_cor`,
  ADD COLUMN IF NOT EXISTS `avatar_emblema` varchar(30) DEFAULT NULL AFTER `avatar_moldura`;

-- A coluna era do tamanho das seis cores básicas; "buraco-negro" não cabia
ALTER TABLE `usuario_preferencias`
  MODIFY COLUMN `avatar_cor` varchar(20) NOT NULL DEFAULT 'roxo';


-- ────────────────────────────────────────────────────────────
--  3. O QUE CADA NÍVEL DESTRAVA
-- ────────────────────────────────────────────────────────────
UPDATE `niveis_config` SET `recompensa_descricao` = 'Ponto de partida: toda constelação começa com um grão de poeira.' WHERE `nivel` = 1;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Destrava a cor Nebulosa.' WHERE `nivel` = 2;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Destrava a moldura Anel de poeira e o título Cometa Errante.' WHERE `nivel` = 3;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Destrava o emblema Foguete.' WHERE `nivel` = 4;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Destrava a moldura Órbita e o título Satélite Vivo.' WHERE `nivel` = 5;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Destrava a cor Aurora.' WHERE `nivel` = 6;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Destrava o emblema Astronauta.' WHERE `nivel` = 7;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Destrava a moldura Rastro cadente e o título Estrela Cadente.' WHERE `nivel` = 8;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Falta 1 nível para o emblema Saturno.' WHERE `nivel` = 9;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Destrava o emblema Saturno.' WHERE `nivel` = 10;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Falta 1 nível para a moldura Halo de anã branca.' WHERE `nivel` = 11;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Destrava a moldura Halo de anã branca e o título Anã Branca.' WHERE `nivel` = 12;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Falta 1 nível para a cor Buraco negro.' WHERE `nivel` = 13;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Destrava a cor Buraco negro.' WHERE `nivel` = 14;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 2 níveis para a moldura Supernova.' WHERE `nivel` = 15;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Falta 1 nível para a moldura Supernova.' WHERE `nivel` = 16;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Destrava a moldura Supernova e o título Supernova.' WHERE `nivel` = 17;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 2 níveis para o emblema Galáxia.' WHERE `nivel` = 18;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Falta 1 nível para o emblema Galáxia.' WHERE `nivel` = 19;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Destrava o emblema Galáxia.' WHERE `nivel` = 20;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 3 níveis para a moldura Coroa do cosmos.' WHERE `nivel` = 21;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 2 níveis para a moldura Coroa do cosmos.' WHERE `nivel` = 22;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Falta 1 nível para a moldura Coroa do cosmos.' WHERE `nivel` = 23;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Destrava a moldura Coroa do cosmos e o título Mestre do Cosmos.' WHERE `nivel` = 24;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 5 níveis para a cor Poeira de ouro.' WHERE `nivel` = 25;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 4 níveis para a cor Poeira de ouro.' WHERE `nivel` = 26;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 3 níveis para a cor Poeira de ouro.' WHERE `nivel` = 27;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 2 níveis para a cor Poeira de ouro.' WHERE `nivel` = 28;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Falta 1 nível para a cor Poeira de ouro.' WHERE `nivel` = 29;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Destrava a cor Poeira de ouro.' WHERE `nivel` = 30;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 9 níveis para o emblema Orion.' WHERE `nivel` = 31;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 8 níveis para o emblema Orion.' WHERE `nivel` = 32;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 7 níveis para o emblema Orion.' WHERE `nivel` = 33;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 6 níveis para o emblema Orion.' WHERE `nivel` = 34;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 5 níveis para o emblema Orion.' WHERE `nivel` = 35;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 4 níveis para o emblema Orion.' WHERE `nivel` = 36;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 3 níveis para o emblema Orion.' WHERE `nivel` = 37;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 2 níveis para o emblema Orion.' WHERE `nivel` = 38;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Falta 1 nível para o emblema Orion.' WHERE `nivel` = 39;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Destrava o emblema Orion.' WHERE `nivel` = 40;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 9 níveis para a moldura Big Bang.' WHERE `nivel` = 41;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 8 níveis para a moldura Big Bang.' WHERE `nivel` = 42;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 7 níveis para a moldura Big Bang.' WHERE `nivel` = 43;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 6 níveis para a moldura Big Bang.' WHERE `nivel` = 44;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 5 níveis para a moldura Big Bang.' WHERE `nivel` = 45;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 4 níveis para a moldura Big Bang.' WHERE `nivel` = 46;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 3 níveis para a moldura Big Bang.' WHERE `nivel` = 47;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Faltam 2 níveis para a moldura Big Bang.' WHERE `nivel` = 48;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Falta 1 nível para a moldura Big Bang.' WHERE `nivel` = 49;
UPDATE `niveis_config` SET `recompensa_descricao` = 'Destrava a moldura Big Bang.' WHERE `nivel` = 50;
