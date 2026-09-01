-- ============================================================
--  KOSMOS — Migração: aba Conta (preferências, foto e sessões)
--  Data: 2026-08-26
--
--  Rode isto em bancos que JÁ existem (o kosmos.sql completo já
--  traz tudo isto):
--
--      mysql -u root kosmos < 2026-08-26_conta.sql
--
--  Cria:
--    - usuario_preferencias  -> avatar (cor/foto/enquadramento),
--                               tempos do pomodoro, meta diária,
--                               matérias favoritas e notificações
--    - usuarios.sessoes_versao -> contador usado pelo "sair de todos
--                               os aparelhos": ao somar +1, as sessões
--                               antigas deixam de valer
--
--  Datas: gravadas pelo relógio do MySQL (CURRENT_TIMESTAMP).
--  Nenhuma data vem do PHP — ver "Problemas conhecidos" no README.
-- ============================================================

-- ---------- Preferências do usuário (aba Conta) ----------
CREATE TABLE IF NOT EXISTS `usuario_preferencias` (
  `usuario_id`       int(10) UNSIGNED NOT NULL,
  `avatar_cor`       varchar(16) NOT NULL DEFAULT 'roxo',
  `avatar_arquivo`   varchar(120) DEFAULT NULL,   -- foto de perfil enviada pelo usuario
  `avatar_pos_x`     tinyint(3) UNSIGNED NOT NULL DEFAULT 50,  -- enquadramento da foto (0-100%)
  `avatar_pos_y`     tinyint(3) UNSIGNED NOT NULL DEFAULT 50,
  `pomo_foco`        tinyint(3) UNSIGNED NOT NULL DEFAULT 25,
  `pomo_pausa`       tinyint(3) UNSIGNED NOT NULL DEFAULT 5,
  `pomo_pausa_longa` tinyint(3) UNSIGNED NOT NULL DEFAULT 15,
  `meta_diaria`      smallint(5) UNSIGNED NOT NULL DEFAULT 60,
  `materias`         varchar(255) DEFAULT NULL,
  `notif_lembrete`   tinyint(1) NOT NULL DEFAULT 0,
  `notif_resumo`     tinyint(1) NOT NULL DEFAULT 0,
  `atualizado_em`    datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`usuario_id`),
  CONSTRAINT `fk_pref_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Sair de todos os aparelhos ----------
ALTER TABLE `usuarios`
  ADD COLUMN IF NOT EXISTS `sessoes_versao` int(10) UNSIGNED NOT NULL DEFAULT 0;
