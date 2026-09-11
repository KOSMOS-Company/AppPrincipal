-- ============================================================
--  KOSMOS — Migração: Cadernos de resumos (+ imagens anexadas)
--  Data: 2026-09-09
--
--  O que entra:
--    1. `resumo_cadernos` — o agrupador dos resumos. É o
--       equivalente ao deck dos flashcards: tem nome e matéria.
--    2. `resumos.caderno_id` — a que caderno o resumo pertence.
--       NULL = resumo solto ("Sem caderno"), que é como ficam
--       todos os resumos que já existiam antes desta migração.
--    3. `resumo_imagens` — as fotos anexadas a um resumo (o
--       usuário pode fotografar o caderno de papel em vez de,
--       ou além de, digitar o texto).
--
--  Rode isto em bancos que JÁ existem:
--      mysql -u root kosmos < 2026-09-09_resumo_cadernos.sql
--
--  Datas: gravadas pelo relógio do MySQL (CURRENT_TIMESTAMP).
--  Nenhuma data vem do PHP — ver "Problemas conhecidos" no README.
--
--  Requer MariaDB 10.2+ / MySQL 8+ pelos "IF NOT EXISTS" do ALTER
--  (é o que deixa a migração poder rodar duas vezes sem quebrar).
-- ============================================================

-- ---------- Cadernos ----------
CREATE TABLE IF NOT EXISTS `resumo_cadernos` (
  `id`         int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `nome`       varchar(120) NOT NULL,
  `materia`    varchar(40)  NOT NULL,
  `criado_em`  datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_caderno_usuario` (`usuario_id`),
  CONSTRAINT `fk_caderno_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Resumo -> caderno ----------
--  ON DELETE SET NULL, e não CASCADE: apagar um caderno é
--  arrumação, não é jogar fora o que a pessoa escreveu. Os
--  resumos voltam para "Sem caderno".
ALTER TABLE `resumos`
  ADD COLUMN IF NOT EXISTS `caderno_id` int(10) UNSIGNED DEFAULT NULL AFTER `usuario_id`;

ALTER TABLE `resumos`
  ADD KEY IF NOT EXISTS `idx_resumo_caderno` (`caderno_id`);

ALTER TABLE `resumos`
  ADD CONSTRAINT `fk_resumo_caderno`
    FOREIGN KEY IF NOT EXISTS (`caderno_id`) REFERENCES `resumo_cadernos` (`id`) ON DELETE SET NULL;

-- O corpo agora é opcional: um resumo pode ser só as fotos do
-- caderno de papel. Continua NOT NULL para não haver dois jeitos
-- de dizer "vazio" (NULL e ''), mas ganha o padrão vazio.
ALTER TABLE `resumos`
  MODIFY COLUMN `corpo` mediumtext NOT NULL DEFAULT '';

-- ---------- Imagens anexadas ----------
CREATE TABLE IF NOT EXISTS `resumo_imagens` (
  `id`        int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `resumo_id` int(10) UNSIGNED NOT NULL,
  `arquivo`   varchar(80) NOT NULL,   -- só o nome; a pasta é fixa no PHP
  `legenda`   varchar(140) NOT NULL DEFAULT '',
  `ordem`     int(10) UNSIGNED NOT NULL DEFAULT 0,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_imagem_resumo` (`resumo_id`),
  CONSTRAINT `fk_imagem_resumo`
    FOREIGN KEY (`resumo_id`) REFERENCES `resumos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
