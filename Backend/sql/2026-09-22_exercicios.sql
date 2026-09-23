-- ============================================================
--  KOSMOS — Migração: Exercícios salvos
--  Data: 2026-09-22
--
--  O que entra:
--    1. `exercicios` — exercícios salvos pelo usuário, vinculados a uma matéria de exercícios
--       É o equivalente aos resumos nos cadernos.
--
--  Rode isto em bancos que JÁ existem:
--      mysql -u root kosmos < 2026-09-22_exercicios.sql
--
--  Datas: gravadas pelo relógio do MySQL (CURRENT_TIMESTAMP).
-- ============================================================

CREATE TABLE IF NOT EXISTS `exercicios` (
  `id`            int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`    int(10) UNSIGNED NOT NULL,
  `materia_id`    int(10) UNSIGNED NOT NULL,
  `titulo`        varchar(140) NOT NULL,
  `conteudo`      mediumtext NOT NULL,          -- JSON com as questões
  `dificuldade`   varchar(20) NOT NULL DEFAULT 'Médio',
  `criado_em`     datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_exercicio_usuario` (`usuario_id`),
  KEY `idx_exercicio_materia` (`materia_id`),
  CONSTRAINT `fk_exercicio_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_exercicio_materia`
    FOREIGN KEY (`materia_id`) REFERENCES `exercicio_materias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;