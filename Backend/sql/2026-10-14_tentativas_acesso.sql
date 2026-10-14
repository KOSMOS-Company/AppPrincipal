-- ============================================================
--  KOSMOS — Migração: limite de tentativas de login / reset
--  Data: 2026-10-14 (depois de 2026-09-29_avatar_extras.sql)
--
--  Sem limite, o login.php aceitava testar senhas sem parar contra
--  um e-mail, e o senha_esqueci.php podia ser usado para lotar a
--  caixa de alguém (e gastar o n8n). Cada tentativa vira uma linha
--  aqui; o limite_util.php conta as recentes antes de deixar passar.
--
--  Linhas com mais de 1 dia são apagadas pelo próprio PHP.
--
--  Pode rodar de novo:
--      mysql -u root kosmos < 2026-10-14_tentativas_acesso.sql
-- ============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `tentativas_acesso` (
  `id`        int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tipo`      enum('login','reset') NOT NULL,
  `email`     varchar(180) NOT NULL,
  `ip`        varchar(45) NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tipo_email` (`tipo`, `email`, `criado_em`),
  KEY `idx_tipo_ip` (`tipo`, `ip`, `criado_em`),
  KEY `idx_criado` (`criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
