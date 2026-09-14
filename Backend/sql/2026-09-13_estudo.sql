-- ============================================================
--  KOSMOS — Migração: memória de estudo
--  Data: 2026-09-13
--
--  Até aqui o app tinha memória de CONTEÚDO (resumos, cartões) e
--  nenhuma memória de COMPORTAMENTO: nada registrava que a pessoa
--  havia estudado. Por isso o gráfico "Esta semana" nascia vazio,
--  a `meta_diaria` era salva e nunca lida, e a sequência não tinha
--  como ser conferida.
--
--  Três coisas aqui:
--    1. pomodoro_sessoes  — o que foi estudado, quando e por quanto
--    2. repetição espaçada em flashcard_cartoes
--    3. provas             — o calendário do estudante
-- ============================================================


-- ────────────────────────────────────────────────────────────
--  1. SESSÕES DE ESTUDO
--
--  `dia` é a data desnormalizada. Podia sair de DATE(fim_em) na
--  consulta, mas aí todo agrupamento por dia precisaria de função
--  sobre coluna — o que joga fora o índice. Aqui ele é gravado uma
--  vez e indexado.
--
--  `fim_em` + UNIQUE(usuario_id, fim_em) é o que impede sessão
--  duplicada: o Pomodoro roda pelo relógio do sistema e um mesmo
--  ciclo pode ser "descoberto" por duas abas ao mesmo tempo, ou de
--  novo depois de um recarregamento. Como o fim de um ciclo é um
--  instante único, ele serve de identidade natural — sem precisar
--  inventar um id no cliente.
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `pomodoro_sessoes` (
  `id`          int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`  int(10) UNSIGNED NOT NULL,
  `minutos`     smallint(5) UNSIGNED NOT NULL,
  `materia`     varchar(40) DEFAULT NULL,   -- por enquanto sempre NULL; prepara "matérias mais estudadas"
  `fim_em`      datetime NOT NULL,
  `dia`         date NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sessao_fim` (`usuario_id`, `fim_em`),
  KEY `idx_sessao_dia` (`usuario_id`, `dia`),
  CONSTRAINT `fk_sessao_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ────────────────────────────────────────────────────────────
--  2. REPETIÇÃO ESPAÇADA
--
--  A tabela já contava revisões, acertos e erros — histórico, não
--  agendamento. Faltava o que responde "quando revisar de novo",
--  que é a única coisa que separa um baralho de cartões de uma
--  ferramenta de memorização. E é o que o Orion promete na landing
--  page ("a repetição espaçada é por minha conta").
--
--  O algoritmo é um SM-2 enxuto (ver Backend/php/flashcards_srs.php):
--    intervalo   — dias até a próxima revisão
--    facilidade  — o quanto o cartão é fácil PARA ESTA PESSOA
--                  (2.50 é o padrão do SM-2; cai a cada erro, sobe
--                  devagar a cada acerto, com piso em 1.30)
--    proxima_revisao — a data; NULL = cartão novo, entra na fila hoje
-- ────────────────────────────────────────────────────────────
ALTER TABLE `flashcard_cartoes`
  ADD COLUMN IF NOT EXISTS `intervalo`       smallint(5) UNSIGNED NOT NULL DEFAULT 0    AFTER `ultimo_resultado`,
  ADD COLUMN IF NOT EXISTS `facilidade`      decimal(4,2)     NOT NULL DEFAULT 2.50     AFTER `intervalo`,
  ADD COLUMN IF NOT EXISTS `proxima_revisao` date             DEFAULT NULL              AFTER `facilidade`;

-- A fila "revisar hoje" pergunta sempre a mesma coisa: quais cartões
-- vencem até hoje. Sem este índice a resposta é uma varredura.
ALTER TABLE `flashcard_cartoes`
  ADD INDEX IF NOT EXISTS `idx_cartao_revisao` (`proxima_revisao`);


-- ────────────────────────────────────────────────────────────
--  3. PROVAS
--
--  O calendário do estudante. Guarda a data e a matéria; quem conta
--  os dias que faltam é o MySQL, na consulta — o projeto tem PHP e
--  MySQL em fusos diferentes, e data calculada em PHP já deu
--  problema aqui antes (ver "Problemas conhecidos" no README).
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `provas` (
  `id`         int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `titulo`     varchar(80) NOT NULL,
  `materia`    varchar(40) DEFAULT NULL,
  `data`       date NOT NULL,
  `criado_em`  datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_prova_data` (`usuario_id`, `data`),
  CONSTRAINT `fk_prova_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ────────────────────────────────────────────────────────────
--  4. BUSCA
--
--  Índice FULLTEXT para a busca global. Sem ele a busca vira LIKE
--  '%termo%', que não usa índice nenhum e piora conforme a pessoa
--  escreve mais — exatamente ao contrário do que deveria.
-- ────────────────────────────────────────────────────────────
ALTER TABLE `resumos`
  ADD FULLTEXT INDEX IF NOT EXISTS `ft_resumo` (`titulo`, `corpo`);
