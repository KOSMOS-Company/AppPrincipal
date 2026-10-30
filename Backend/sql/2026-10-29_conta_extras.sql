-- ============================================================
--  KOSMOS — Migração: confirmação de e-mail, "Lembrar de mim"
--  e dicas do Orion na conta
--  Data: 2026-10-29 (depois de 2026-10-27_tutorial_recompensa.sql)
--
--  1. usuarios.email_confirmado (+ token): o cadastro por e-mail e
--     senha cria a conta com 0 e manda um link pelo n8n. O padrão é 1
--     — contas que já existem, as do Google (o Google já confirmou o
--     e-mail) e a conta de dev entram confirmadas sem fazer nada.
--  2. tentativas_acesso.tipo ganha 'confirma' (limite do "reenviar").
--  3. sessoes_lembradas: o "Lembrar de mim" do login (sessao.php).
--  4. usuario_preferencias.dicas_vistas: as dicas do Orion que a conta
--     já viu, para não reaparecerem em outro aparelho.
--
--  Pode rodar de novo:
--      mysql -u root kosmos < 2026-10-29_conta_extras.sql
-- ============================================================

SET NAMES utf8mb4;

ALTER TABLE `usuarios`
  ADD COLUMN IF NOT EXISTS `email_confirmado` TINYINT(1) NOT NULL DEFAULT 1 AFTER `sessoes_versao`,
  ADD COLUMN IF NOT EXISTS `confirma_token` CHAR(64) DEFAULT NULL AFTER `email_confirmado`,
  ADD COLUMN IF NOT EXISTS `confirma_expira` DATETIME DEFAULT NULL AFTER `confirma_token`,
  ADD KEY IF NOT EXISTS `idx_confirma_token` (`confirma_token`);

ALTER TABLE `tentativas_acesso`
  MODIFY COLUMN `tipo` ENUM('login','reset','cadastro','ia','orion','confirma') NOT NULL;

CREATE TABLE IF NOT EXISTS `sessoes_lembradas` (
  `seletor`        char(24) NOT NULL,
  `validador_hash` char(64) NOT NULL,
  `usuario_id`     int(10) UNSIGNED NOT NULL,
  `sessoes_versao` int(10) UNSIGNED NOT NULL,
  `expira_em`      datetime NOT NULL,
  `criado_em`      datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`seletor`),
  KEY `idx_usuario` (`usuario_id`),
  CONSTRAINT `fk_lembrada_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `usuario_preferencias`
  ADD COLUMN IF NOT EXISTS `dicas_vistas` VARCHAR(255) NOT NULL DEFAULT '' AFTER `tutorial_visto`;
