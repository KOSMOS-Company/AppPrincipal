-- ============================================================
--  KOSMOS — Migração: o onboarding é só para conta nova
--  Data: 2026-09-15
--
--  A pesquisa de perfil deve aparecer UMA vez, no primeiro login de
--  quem acabou de criar a conta. Quem decide isso agora é a coluna
--  `onboarding_completo`, e o sentido dela mudou de "já respondeu"
--  para "já foi apresentado": o index.php a marca no instante em que
--  põe o modal na tela, não quando as respostas chegam. Sem isso,
--  quem fechasse o modal sem responder revia as perguntas a cada
--  visita à aba Início.
--
--  Esta migração trata só do passado: as contas que JÁ EXISTIAM não
--  são novas e não podem receber as perguntas agora. Duas situações,
--  e as duas precisam virar "visto":
--
--    1. quem já tem linha em usuario_preferencias com a flag em 0;
--    2. quem nunca teve linha nenhuma — ela nasceria com o padrão 0
--       na próxima visita ao dashboard e o modal abriria.
--
--  O CORTE é `criado_em < '2026-09-15'`: conta criada antes do dia em
--  que isto entrou não é nova. A data é fixa, e não CURDATE(), para a
--  migração dar o mesmo resultado em qualquer dia que rode — com
--  CURDATE() ela marcaria como "visto" justamente as contas criadas
--  hoje, que são as que precisam ver o onboarding.
--
--  Idempotente: rodar de novo não muda mais nada.
-- ============================================================

INSERT INTO `usuario_preferencias` (`usuario_id`, `onboarding_completo`)
SELECT u.`id`, 1
  FROM `usuarios` u
 WHERE u.`criado_em` < '2026-09-15'
ON DUPLICATE KEY UPDATE `onboarding_completo` = 1;
