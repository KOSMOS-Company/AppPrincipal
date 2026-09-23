-- ============================================================
--  KOSMOS — Migração: Matérias de exercícios
--  Data: 2026-09-22
--
--  O que entra:
--    1. `exercicio_materias` — o agrupador dos exercícios por matéria.
--       É o equivalente ao caderno dos resumos: tem nome e matéria.
--    2. Permite criar múltiplas matérias de exercícios por usuário.
--
--  Rode isto em bancos que JÁ existem:
--      mysql -u root kosmos < 2026-09-22_exercicio_materias.sql
--
--  Datas: gravadas pelo relógio do MySQL (CURRENT_TIMESTAMP).
--  Nenhuma data vem do PHP.
-- ============================================================

-- ---------- Matérias de exercícios ----------
CREATE TABLE IF NOT EXISTS `exercicio_materias` (
  `id`         int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `nome`       varchar(120) NOT NULL,
  `materia`    varchar(40)  NOT NULL,
  `cor`        varchar(20)  NOT NULL DEFAULT 'roxo',
  `icone`      varchar(10)  NOT NULL DEFAULT '📝',
  `descricao`  varchar(280) NOT NULL DEFAULT '',
  `ordem`      int(10) UNSIGNED NOT NULL DEFAULT 0,
  `criado_em`  datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_exercicio_materia_usuario` (`usuario_id`),
  CONSTRAINT `fk_exercicio_materia_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;