-- ============================================================
--  KOSMOS — Migração: persistência dos Resumos
--  Data: 2026-08-27
--
--  Rode isto em bancos que JÁ existem (o kosmos.sql completo já
--  traz a tabela nova):
--
--      mysql -u root kosmos < 2026-08-27_resumos.sql
--
--  Datas: gravadas pelo relógio do MySQL (CURRENT_TIMESTAMP /
--  ON UPDATE). Nenhuma data vem do PHP — ver "Problemas
--  conhecidos" no README.
-- ============================================================

CREATE TABLE IF NOT EXISTS `resumos` (
  `id`            int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`    int(10) UNSIGNED NOT NULL,
  `titulo`        varchar(140) NOT NULL,
  `materia`       varchar(40) NOT NULL,
  `corpo`         mediumtext NOT NULL,          -- o texto do resumo
  `criado_em`     datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_resumo_usuario` (`usuario_id`),
  CONSTRAINT `fk_resumo_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
