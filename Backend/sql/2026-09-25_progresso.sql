-- ============================================================
--  KOSMOS — Migração: progressão (XP, níveis e conquistas)
--  Data: 2026-09-25
--
--  O que entra:
--    1. usuario_progresso   — XP, nível, título e sequência de ESTUDO
--    2. niveis_config       — a curva de níveis (50 níveis)
--    3. conquistas          — o catálogo de badges
--    4. usuario_conquistas  — quais badges cada pessoa já tem
--    5. historico_xp        — cada ganho de XP, um por linha (auditoria)
--    6. exercicio_respostas — respostas de exercícios, corrigidas no servidor
--    7. retroativo          — quem já usava o app começa com o XP do
--                             que já fez, e não do zero
--
--  Rode isto em bancos que JÁ existem (pode rodar de novo: é idempotente):
--      mysql -u root kosmos < 2026-09-25_progresso.sql
--
--  Regras de negócio (quanto vale cada ação, tetos diários) moram no
--  Backend/php/ProgressoService.php. Aqui fica só a estrutura e o que
--  é DADO: a curva de níveis e o catálogo de conquistas.
-- ============================================================

-- Os títulos e nomes têm acento ("Satélite Vivo", "Primeira órbita").
-- Sem isto, o cliente mysql do Windows lê o arquivo como latin1 e grava
-- "SatÃ©lite" no banco. Rodar de novo com esta linha conserta.
SET NAMES utf8mb4;


-- ────────────────────────────────────────────────────────────
--  1. PROGRESSO
--
--  `streak_dias` NÃO é a `usuarios.sequencia`. Aquela conta dias de
--  ACESSO (abrir o app já soma). Esta conta dias de ESTUDO: só avança
--  no primeiro ganho de XP do dia — login + uma ação, como pede a
--  regra da sequência. `ultimo_dia_ativo` é o que permite saber se
--  hoje já contou, sem varrer o histórico.
--
--  Invariante: xp_atual = SUM(historico_xp.quantidade_xp). O
--  histórico é a fonte; esta coluna é o total já somado, para a
--  barra de XP não precisar agregar a tabela a cada página.
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `usuario_progresso` (
  `usuario_id`       int(10) UNSIGNED NOT NULL,
  `xp_atual`         int(10) UNSIGNED NOT NULL DEFAULT 0,
  `nivel_atual`      int(10) UNSIGNED NOT NULL DEFAULT 1,
  `titulo_atual`     varchar(60) NOT NULL DEFAULT 'Poeira Estelar',
  `streak_dias`      int(10) UNSIGNED NOT NULL DEFAULT 0,
  `ultimo_dia_ativo` date DEFAULT NULL,
  `atualizado_em`    timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`usuario_id`),
  CONSTRAINT `fk_progresso_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ────────────────────────────────────────────────────────────
--  2. CURVA DE NÍVEIS
--
--  Custo do nível N (XP para sair dele e chegar ao N+1):
--      round(100 × N^1.5)
--  `xp_necessario` é o total ACUMULADO para alcançar o nível — é o
--  que a consulta precisa ("qual o maior nível cujo piso eu já
--  passei?"). Nível 2 em 100 XP, 5 em 1703, 10 em 11106, 24
--  (Mestre do Cosmos) em 107051. Com um dia de estudo rendendo por
--  volta de 400 XP: começo rápido, topo só com constância.
--
--  Mudar a curva = rodar outro UPDATE aqui. O PHP lê desta tabela.
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `niveis_config` (
  `nivel`                int(10) UNSIGNED NOT NULL,
  `xp_necessario`        int(10) UNSIGNED NOT NULL,
  `titulo`               varchar(60) NOT NULL,
  `recompensa_descricao` varchar(150) NOT NULL DEFAULT '',
  PRIMARY KEY (`nivel`),
  UNIQUE KEY `uk_nivel_xp` (`xp_necessario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `niveis_config` (`nivel`, `xp_necessario`, `titulo`, `recompensa_descricao`) VALUES
  ( 1,       0, 'Poeira Estelar', 'Ponto de partida: toda constelação começa com um grão de poeira.'),
  ( 2,     100, 'Poeira Estelar', 'Destrava a cor Nebulosa.'),
  ( 3,     383, 'Cometa Errante', 'Destrava a moldura Anel de poeira e o título Cometa Errante.'),
  ( 4,     903, 'Cometa Errante', 'Destrava o emblema Foguete.'),
  ( 5,    1703, 'Satélite Vivo', 'Destrava a moldura Órbita e o título Satélite Vivo.'),
  ( 6,    2821, 'Satélite Vivo', 'Destrava a cor Aurora.'),
  ( 7,    4291, 'Satélite Vivo', 'Destrava o emblema Astronauta.'),
  ( 8,    6143, 'Estrela Cadente', 'Destrava a moldura Rastro cadente e o título Estrela Cadente.'),
  ( 9,    8406, 'Estrela Cadente', 'Falta 1 nível para o emblema Saturno.'),
  (10,   11106, 'Estrela Cadente', 'Destrava o emblema Saturno.'),
  (11,   14268, 'Estrela Cadente', 'Falta 1 nível para a moldura Halo de anã branca.'),
  (12,   17916, 'Anã Branca', 'Destrava a moldura Halo de anã branca e o título Anã Branca.'),
  (13,   22073, 'Anã Branca', 'Falta 1 nível para a cor Buraco negro.'),
  (14,   26760, 'Anã Branca', 'Destrava a cor Buraco negro.'),
  (15,   31998, 'Anã Branca', 'Faltam 2 níveis para a moldura Supernova.'),
  (16,   37807, 'Anã Branca', 'Falta 1 nível para a moldura Supernova.'),
  (17,   44207, 'Supernova', 'Destrava a moldura Supernova e o título Supernova.'),
  (18,   51216, 'Supernova', 'Faltam 2 níveis para o emblema Galáxia.'),
  (19,   58853, 'Supernova', 'Falta 1 nível para o emblema Galáxia.'),
  (20,   67135, 'Supernova', 'Destrava o emblema Galáxia.'),
  (21,   76079, 'Supernova', 'Faltam 3 níveis para a moldura Coroa do cosmos.'),
  (22,   85702, 'Supernova', 'Faltam 2 níveis para a moldura Coroa do cosmos.'),
  (23,   96021, 'Supernova', 'Falta 1 nível para a moldura Coroa do cosmos.'),
  (24,  107051, 'Mestre do Cosmos', 'Destrava a moldura Coroa do cosmos e o título Mestre do Cosmos.'),
  (25,  118809, 'Mestre do Cosmos', 'Faltam 5 níveis para a cor Poeira de ouro.'),
  (26,  131309, 'Mestre do Cosmos', 'Faltam 4 níveis para a cor Poeira de ouro.'),
  (27,  144566, 'Mestre do Cosmos', 'Faltam 3 níveis para a cor Poeira de ouro.'),
  (28,  158596, 'Mestre do Cosmos', 'Faltam 2 níveis para a cor Poeira de ouro.'),
  (29,  173412, 'Mestre do Cosmos', 'Falta 1 nível para a cor Poeira de ouro.'),
  (30,  189029, 'Mestre do Cosmos', 'Destrava a cor Poeira de ouro.'),
  (31,  205461, 'Mestre do Cosmos', 'Faltam 9 níveis para o emblema Orion.'),
  (32,  222721, 'Mestre do Cosmos', 'Faltam 8 níveis para o emblema Orion.'),
  (33,  240823, 'Mestre do Cosmos', 'Faltam 7 níveis para o emblema Orion.'),
  (34,  259780, 'Mestre do Cosmos', 'Faltam 6 níveis para o emblema Orion.'),
  (35,  279605, 'Mestre do Cosmos', 'Faltam 5 níveis para o emblema Orion.'),
  (36,  300311, 'Mestre do Cosmos', 'Faltam 4 níveis para o emblema Orion.'),
  (37,  321911, 'Mestre do Cosmos', 'Faltam 3 níveis para o emblema Orion.'),
  (38,  344417, 'Mestre do Cosmos', 'Faltam 2 níveis para o emblema Orion.'),
  (39,  367842, 'Mestre do Cosmos', 'Falta 1 nível para o emblema Orion.'),
  (40,  392197, 'Mestre do Cosmos', 'Destrava o emblema Orion.'),
  (41,  417495, 'Mestre do Cosmos', 'Faltam 9 níveis para a moldura Big Bang.'),
  (42,  443748, 'Mestre do Cosmos', 'Faltam 8 níveis para a moldura Big Bang.'),
  (43,  470967, 'Mestre do Cosmos', 'Faltam 7 níveis para a moldura Big Bang.'),
  (44,  499164, 'Mestre do Cosmos', 'Faltam 6 níveis para a moldura Big Bang.'),
  (45,  528350, 'Mestre do Cosmos', 'Faltam 5 níveis para a moldura Big Bang.'),
  (46,  558537, 'Mestre do Cosmos', 'Faltam 4 níveis para a moldura Big Bang.'),
  (47,  589736, 'Mestre do Cosmos', 'Faltam 3 níveis para a moldura Big Bang.'),
  (48,  621958, 'Mestre do Cosmos', 'Faltam 2 níveis para a moldura Big Bang.'),
  (49,  655213, 'Mestre do Cosmos', 'Falta 1 nível para a moldura Big Bang.'),
  (50,  689513, 'Mestre do Cosmos', 'Destrava a moldura Big Bang.')
ON DUPLICATE KEY UPDATE
  `xp_necessario`        = VALUES(`xp_necessario`),
  `titulo`               = VALUES(`titulo`),
  `recompensa_descricao` = VALUES(`recompensa_descricao`);


-- ────────────────────────────────────────────────────────────
--  3. CONQUISTAS
--
--  `icone_svg` guarda a REFERÊNCIA do ícone (ex.: 'cometa'); o SVG
--  em si mora em Backend/php/conquistas_icones.php, num lugar só,
--  usado pela página de conquistas e pelo aviso de desbloqueio.
--
--  `metrica` + `alvo` tornam a regra dado, não código: "desbloqueia
--  quando a métrica X chega a Y". As métricas são calculadas no
--  ProgressoService (pomodoros, fc_acertos, streak, nivel...). Uma
--  conquista nova sobre uma métrica que já existe é só um INSERT.
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `conquistas` (
  `id`         int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`       varchar(50) NOT NULL,
  `nome`       varchar(80) NOT NULL,
  `descricao`  text NOT NULL,
  `icone_svg`  varchar(40) NOT NULL,
  `xp_bonus`   int(10) UNSIGNED NOT NULL DEFAULT 0,
  `categoria`  enum('foco','memoria','exercicios','constancia') NOT NULL,
  `metrica`    varchar(30) NOT NULL,
  `alvo`       int(10) UNSIGNED NOT NULL DEFAULT 1,
  `ordem`      smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_conquista_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `conquistas` (`slug`, `nome`, `descricao`, `icone_svg`, `xp_bonus`, `categoria`, `metrica`, `alvo`, `ordem`) VALUES
  ('pomodoro_first', 'Primeira órbita', 'Conclua o seu primeiro ciclo de foco de pelo menos 15 minutos.', 'relogio', 25, 'foco', 'pomodoros', 1, 10),
  ('pomodoro_10', 'Foco em órbita', 'Conclua 10 ciclos de foco.', 'foguete', 75, 'foco', 'pomodoros', 10, 20),
  ('pomodoro_50', 'Motor de dobra', 'Conclua 50 ciclos de foco.', 'raio', 200, 'foco', 'pomodoros', 50, 30),
  ('pomodoro_maratona', 'Maratona estelar', 'Conclua 4 ciclos de foco no mesmo dia.', 'chama', 100, 'foco', 'pomodoros_dia', 4, 40),
  ('flashcard_first', 'Primeira revisão', 'Termine a sua primeira sessão de flashcards.', 'cartas', 25, 'memoria', 'fc_sessoes', 1, 10),
  ('flashcard_master_100', 'Memória de cometa', 'Acerte 100 cartões nas revisões.', 'cometa', 100, 'memoria', 'fc_acertos', 100, 20),
  ('flashcard_master_500', 'Mente galáctica', 'Acerte 500 cartões nas revisões.', 'galaxia', 250, 'memoria', 'fc_acertos', 500, 30),
  ('resumo_first', 'Primeiras anotações', 'Escreva o seu primeiro resumo.', 'pena', 25, 'memoria', 'resumos', 1, 40),
  ('resumo_10', 'Biblioteca estelar', 'Escreva 10 resumos.', 'livros', 75, 'memoria', 'resumos', 10, 50),
  ('exercicio_first', 'Primeiro acerto', 'Acerte a sua primeira questão de exercício.', 'alvo', 25, 'exercicios', 'ex_acertos', 1, 10),
  ('exercicio_50', 'Caçador de questões', 'Acerte 50 questões de exercícios.', 'mira', 100, 'exercicios', 'ex_acertos', 50, 20),
  ('exercicio_200', 'Mestre das provas', 'Acerte 200 questões de exercícios.', 'trofeu', 250, 'exercicios', 'ex_acertos', 200, 30),
  ('lista_perfeita', 'Lista impecável', 'Acerte todas as questões de uma lista no mesmo dia.', 'estrela', 100, 'exercicios', 'listas_perfeitas', 1, 40),
  ('orion_first', 'Primeiro contato', 'Gere exercícios com o Orion pela primeira vez.', 'orion', 25, 'exercicios', 'orion', 1, 50),
  ('streak_3_days', 'Aquecendo motores', 'Estude 3 dias seguidos.', 'faisca', 30, 'constancia', 'streak', 3, 10),
  ('streak_7_days', 'Semana em órbita', 'Estude 7 dias seguidos.', 'lua', 100, 'constancia', 'streak', 7, 20),
  ('streak_30_days', 'Constelação fiel', 'Estude 30 dias seguidos.', 'constelacao', 400, 'constancia', 'streak', 30, 30),
  ('nivel_5', 'Ascensão', 'Alcance o nível 5.', 'subida', 50, 'constancia', 'nivel', 5, 40),
  ('nivel_10', 'Brilho próprio', 'Alcance o nível 10.', 'sol', 150, 'constancia', 'nivel', 10, 50),
  ('nivel_24', 'Mestre do Cosmos', 'Alcance o nível 24 e o título máximo.', 'coroa', 500, 'constancia', 'nivel', 24, 60)
ON DUPLICATE KEY UPDATE
  `nome` = VALUES(`nome`), `descricao` = VALUES(`descricao`), `icone_svg` = VALUES(`icone_svg`),
  `xp_bonus` = VALUES(`xp_bonus`), `categoria` = VALUES(`categoria`),
  `metrica` = VALUES(`metrica`), `alvo` = VALUES(`alvo`), `ordem` = VALUES(`ordem`);


-- ────────────────────────────────────────────────────────────
--  4. CONQUISTAS DE CADA PESSOA
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `usuario_conquistas` (
  `id`              int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`      int(10) UNSIGNED NOT NULL,
  `conquista_id`    int(10) UNSIGNED NOT NULL,
  `desbloqueado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_usuario_conquista` (`usuario_id`, `conquista_id`),
  CONSTRAINT `fk_uconq_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_uconq_conquista`
    FOREIGN KEY (`conquista_id`) REFERENCES `conquistas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ────────────────────────────────────────────────────────────
--  5. HISTÓRICO DE XP (auditoria e anti-fraude)
--
--  Uma linha por ganho. Os tetos diários são conferidos AQUI
--  ("quanto esta origem já rendeu hoje?"), por isso o índice começa
--  por usuário + origem + instante.
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `historico_xp` (
  `id`            bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`    int(10) UNSIGNED NOT NULL,
  `quantidade_xp` int(11) NOT NULL,
  `origem_acao`   varchar(50) NOT NULL,
  `detalhes_json` json DEFAULT NULL,
  `criado_em`     timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_xp_origem_dia` (`usuario_id`, `origem_acao`, `criado_em`),
  CONSTRAINT `fk_xp_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ────────────────────────────────────────────────────────────
--  6. RESPOSTAS DE EXERCÍCIOS
--
--  O quiz corrigia tudo no navegador — não havia nada no servidor
--  para "exercício com acerto" valer XP sem confiar no cliente. Agora
--  cada resposta passa por exercicios_responder.php, que corrige com
--  o gabarito guardado no banco.
--
--  UNIQUE por (pessoa, exercício, questão, DIA): a mesma questão rende
--  XP uma vez por dia. Refazer a lista amanhã vale (é estudar de novo);
--  clicar mil vezes hoje, não.
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `exercicio_respostas` (
  `id`            int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`    int(10) UNSIGNED NOT NULL,
  `exercicio_id`  int(10) UNSIGNED NOT NULL,
  `questao`       smallint(5) UNSIGNED NOT NULL,
  `escolhida`     tinyint(3) UNSIGNED NOT NULL,
  `acertou`       tinyint(1) NOT NULL,
  `dia`           date NOT NULL,
  `respondido_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_resposta_dia` (`usuario_id`, `exercicio_id`, `questao`, `dia`),
  KEY `idx_resposta_acerto` (`usuario_id`, `acertou`),
  CONSTRAINT `fk_resposta_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_resposta_exercicio`
    FOREIGN KEY (`exercicio_id`) REFERENCES `exercicios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ────────────────────────────────────────────────────────────
--  7. QUEM JÁ USAVA O APP
--
--  a) Toda conta ganha a sua linha de progresso. A sequência de estudo
--     herda a de acesso, ajustada para a PRÓXIMA ação continuar dela:
--     quem entrou hoje fica como "ativo até ontem" — a primeira ação de
--     hoje soma o dia e rende o bônus, em vez de a pessoa perder a
--     sequência que já tinha.
--  b) XP retroativo pelo que já está registrado, com os MESMOS tetos
--     diários das regras novas (senão quem tinha histórico começaria
--     num nível que as regras nunca permitiriam):
--       Pomodoro >= 15 min: 50 cada, até 400 por dia
--       resumo (>= 200 caracteres) ou caderno: 40 cada, até 120 por dia
--       flashcards: 5 por acerto + 30 por sessão, até 1500 no total
--                   (o banco não guarda o dia de cada acerto, então o
--                   teto é global)
--     Entra como UMA linha 'retroativo' no histórico, e só uma vez.
--  c) Nível e título saem da curva, pelo XP total.
--  As conquistas não são calculadas aqui: o ProgressoService confere
--  todas na primeira visita à página de conquistas ou no primeiro XP.
-- ────────────────────────────────────────────────────────────
START TRANSACTION;

INSERT IGNORE INTO `usuario_progresso` (`usuario_id`, `streak_dias`, `ultimo_dia_ativo`)
SELECT u.id,
       CASE
         WHEN u.ultimo_acesso = CURDATE()                  THEN GREATEST(u.sequencia, 1) - 1
         WHEN u.ultimo_acesso = CURDATE() - INTERVAL 1 DAY THEN u.sequencia
         ELSE 0
       END,
       CASE
         WHEN u.ultimo_acesso = CURDATE()                  THEN CURDATE() - INTERVAL 1 DAY
         WHEN u.ultimo_acesso = CURDATE() - INTERVAL 1 DAY THEN u.ultimo_acesso
         ELSE NULL
       END
  FROM `usuarios` u;

INSERT INTO `historico_xp` (`usuario_id`, `quantidade_xp`, `origem_acao`, `detalhes_json`)
SELECT r.usuario_id,
       r.pomodoro + r.conteudo + r.flashcards,
       'retroativo',
       JSON_OBJECT('pomodoro', r.pomodoro, 'conteudo', r.conteudo, 'flashcards', r.flashcards)
  FROM (
        SELECT u.id AS usuario_id,
               COALESCE(p.xp, 0) AS pomodoro,
               COALESCE(c.xp, 0) AS conteudo,
               LEAST(COALESCE(fa.acertos, 0) * 5 + COALESCE(fs.sessoes, 0) * 30, 1500) AS flashcards
          FROM `usuarios` u
          LEFT JOIN (SELECT usuario_id, SUM(LEAST(n * 50, 400)) AS xp
                       FROM (SELECT usuario_id, dia, COUNT(*) AS n
                               FROM `pomodoro_sessoes` WHERE minutos >= 15
                              GROUP BY usuario_id, dia) d
                      GROUP BY usuario_id) p ON p.usuario_id = u.id
          LEFT JOIN (SELECT usuario_id, SUM(LEAST(n * 40, 120)) AS xp
                       FROM (SELECT usuario_id, DATE(criado_em) AS dia, COUNT(*) AS n
                               FROM (SELECT usuario_id, criado_em FROM `resumos` WHERE CHAR_LENGTH(corpo) >= 200
                                     UNION ALL
                                     SELECT usuario_id, criado_em FROM `resumo_cadernos`) x
                              GROUP BY usuario_id, DATE(criado_em)) d
                      GROUP BY usuario_id) c ON c.usuario_id = u.id
          LEFT JOIN (SELECT d.usuario_id, SUM(k.acertos) AS acertos
                       FROM `flashcard_cartoes` k JOIN `flashcard_decks` d ON d.id = k.deck_id
                      GROUP BY d.usuario_id) fa ON fa.usuario_id = u.id
          LEFT JOIN (SELECT usuario_id, SUM(revisoes) AS sessoes
                       FROM `flashcard_decks` GROUP BY usuario_id) fs ON fs.usuario_id = u.id
       ) r
 WHERE r.pomodoro + r.conteudo + r.flashcards > 0
   AND NOT EXISTS (SELECT 1 FROM `historico_xp` h
                    WHERE h.usuario_id = r.usuario_id AND h.origem_acao = 'retroativo');

-- O total sai do histórico (a invariante), então rodar de novo não duplica nada
UPDATE `usuario_progresso` p
  LEFT JOIN (SELECT usuario_id, SUM(quantidade_xp) AS total
               FROM `historico_xp` GROUP BY usuario_id) h ON h.usuario_id = p.usuario_id
   SET p.xp_atual = GREATEST(COALESCE(h.total, 0), 0);

UPDATE `usuario_progresso` p
   SET p.nivel_atual  = (SELECT MAX(n.nivel) FROM `niveis_config` n WHERE n.xp_necessario <= p.xp_atual),
       p.titulo_atual = (SELECT n.titulo FROM `niveis_config` n
                          WHERE n.xp_necessario <= p.xp_atual ORDER BY n.nivel DESC LIMIT 1);

COMMIT;
