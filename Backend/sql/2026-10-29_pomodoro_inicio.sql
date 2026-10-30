-- ============================================================
--  KOSMOS — Migração: início do ciclo de foco no servidor
--  Data: 2026-10-29 (depois de 2026-10-29_conta_extras.sql)
--
--  O pomodoro_sessao.php aceitava qualquer ciclo dos últimos 7 dias
--  só com a palavra do navegador (`fim` e `minutos`): um script
--  inventava horas de foco, sequência e conquistas sem estudar.
--
--  Agora o Iniciar de um ciclo novo avisa o servidor
--  (pomodoro_inicio.php), que guarda o instante no relógio do MySQL.
--  O registro só vale se, desde esse início, já passou de verdade a
--  duração declarada — e consome o início (um ciclo por Iniciar).
--
--  Um início POR APARELHO (o js/conta-inicial.js sorteia o id dele):
--  um ciclo no PC e outro no celular ao mesmo tempo não se atropelam.
--
--  Pode rodar de novo:
--      mysql -u root kosmos < 2026-10-29_pomodoro_inicio.sql
-- ============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `pomodoro_inicios` (
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `aparelho`   char(32) NOT NULL,             -- sorteado pelo navegador, guardado no localStorage
  `inicio_em`  datetime NOT NULL,             -- relógio do MySQL
  PRIMARY KEY (`usuario_id`, `aparelho`),
  CONSTRAINT `fk_pomo_inicio_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
