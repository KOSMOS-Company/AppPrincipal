-- ============================================================
--  KOSMOS — Migração: estilos livres do avatar
--  Data: 2026-09-29 (depois de 2026-09-25_recompensas.sql)
--
--  Até aqui toda moldura, emblema e cor especial vinha de nível —
--  quem acabou de chegar não tinha nada para escolher além das seis
--  cores básicas. Estas oito entram no nível 1: aparecem na aba
--  Conta (Perfil → Estilo do avatar) liberadas desde o primeiro dia.
--  A trilha de recompensas (conquistas.php) continua mostrando só o
--  que o nível destrava, então elas não aparecem lá.
--
--  O desenho de cada uma é CSS, em css/recompensas.css
--  (avatar-moldura--{valor}, avatar-emblema--{valor}, avatar-cor--{valor}).
--
--  Pode rodar de novo:
--      mysql -u root kosmos < 2026-09-29_avatar_extras.sql
-- ============================================================

-- Nomes com acento: sem isto o cliente mysql do Windows lê como latin1
SET NAMES utf8mb4;

INSERT INTO `recompensas` (`slug`, `tipo`, `nome`, `descricao`, `nivel_minimo`, `valor`, `ordem`) VALUES
  ('moldura_fio',     'moldura', 'Fio de luz',  'Um anel fino e claro em volta do avatar.',            1, 'fio',        1),
  ('moldura_neon',    'moldura', 'Neon',        'Um anel roxo que brilha como letreiro.',              1, 'neon',       2),
  ('moldura_duplo',   'moldura', 'Anel duplo',  'Dois anéis finos, um dentro do outro.',               1, 'duplo',      3),
  ('emblema_estrela', 'emblema', 'Estrela',     'Troca a inicial do avatar por uma estrela.',          1, 'estrela',    4),
  ('emblema_lua',     'emblema', 'Lua',         'Troca a inicial do avatar por uma lua crescente.',    1, 'lua',        5),
  ('emblema_cometa',  'emblema', 'Cometa',      'Troca a inicial do avatar por um cometa.',            1, 'cometa',     6),
  ('cor_meia_noite',  'cor',     'Meia-noite',  'Índigo profundo, o céu logo depois do pôr do sol.',  1, 'meia-noite', 7),
  ('cor_coral',       'cor',     'Coral',       'Laranja e rosa, quente como uma anã vermelha.',       1, 'coral',      8)
ON DUPLICATE KEY UPDATE
  `tipo` = VALUES(`tipo`), `nome` = VALUES(`nome`), `descricao` = VALUES(`descricao`),
  `nivel_minimo` = VALUES(`nivel_minimo`), `valor` = VALUES(`valor`), `ordem` = VALUES(`ordem`);
