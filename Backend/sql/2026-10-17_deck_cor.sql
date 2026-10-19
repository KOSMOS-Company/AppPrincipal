-- ============================================================
--  KOSMOS — Migração: cor dos baralhos de flashcards
--  Data: 2026-10-17
--
--  A moldura do baralho (e dos cartões e da carta de estudo) usa
--  uma cor. Até aqui ela saía da matéria, sozinha; agora a pessoa
--  pode escolher. Mesma paleta de 6 cores dos cadernos e do avatar.
--
--    cor -> 'roxo' | 'azul' | 'verde' | 'laranja' | 'rosa' | 'ciano'
--           NULL = automática (continua saindo da matéria)
--
--  Rode isto em bancos que JÁ existem:
--      mysql -u root kosmos < 2026-10-17_deck_cor.sql
--  (ou: C:\xampp\php\php.exe Backend/sql/migrar.php)
-- ============================================================

ALTER TABLE `flashcard_decks`
  ADD COLUMN IF NOT EXISTS `cor` varchar(16) DEFAULT NULL AFTER `materia`;
