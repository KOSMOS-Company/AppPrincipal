-- ============================================================
--  KOSMOS — Migração: provas viram uma tela de verdade
--  Data: 2026-09-14
--
--  A tabela `provas` (migração de 13/09) guardava só o mínimo para
--  a contagem regressiva do Início: título, matéria e data. Isso
--  responde "quando é", que é a pergunta fácil. A difícil — "o que
--  eu ainda preciso estudar para ela" — não tinha onde morar.
--
--  Duas coisas aqui:
--    1. campos de acompanhamento em `provas` (anotações, nota,
--       quando foi concluída)
--    2. `prova_topicos` — a lista de assuntos de cada prova
-- ============================================================


-- ────────────────────────────────────────────────────────────
--  1. ACOMPANHAMENTO DA PROVA
--
--  `anotacoes` — o que cai, o que o professor falou, a página do
--  livro. Texto livre porque é rascunho, não dado estruturado.
--
--  `nota` e `concluida_em` — o depois. Uma prova que passou sem
--  registro nenhum vira linha morta na lista; com a nota, ela vira
--  histórico, e o histórico é o que mostra se o estudo funcionou.
--  decimal(5,2) cobre tanto 0–10 quanto 0–100 (e o 950 do ENEM),
--  porque escola nenhuma combina com outra sobre isso.
-- ────────────────────────────────────────────────────────────
ALTER TABLE `provas`
  ADD COLUMN IF NOT EXISTS `anotacoes`    text         DEFAULT NULL AFTER `data`,
  ADD COLUMN IF NOT EXISTS `nota`         decimal(5,2) DEFAULT NULL AFTER `anotacoes`,
  ADD COLUMN IF NOT EXISTS `concluida_em` datetime     DEFAULT NULL AFTER `nota`;


-- ────────────────────────────────────────────────────────────
--  2. OS ASSUNTOS DE CADA PROVA
--
--  Tabela própria, e não um campo de texto com uma linha por item,
--  porque cada assunto precisa ser marcado como feito sozinho — e
--  "feito" é o que alimenta a barra de progresso da prova. Guardar
--  isso dentro de um texto obrigaria a reescrever a lista inteira a
--  cada clique numa caixinha.
--
--  `ordem` mantém a sequência em que a pessoa escreveu: a ordem de
--  um conteúdo de prova costuma ser a ordem da matéria, e ordenar
--  por id daria o mesmo resultado só enquanto ninguém reordenar.
--
--  A FK aponta para `provas`, que por sua vez aponta para
--  `usuarios` com ON DELETE CASCADE: apagar a conta apaga a prova,
--  que apaga os tópicos. Nenhum órfão sobra no banco.
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `prova_topicos` (
  `id`        int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `prova_id`  int(10) UNSIGNED NOT NULL,
  `texto`     varchar(120) NOT NULL,
  `feito`     tinyint(1) NOT NULL DEFAULT 0,
  `ordem`     smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_topico_prova` (`prova_id`, `ordem`),
  CONSTRAINT `fk_topico_prova`
    FOREIGN KEY (`prova_id`) REFERENCES `provas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
