-- ============================================================
--  KOSMOS — Banco de Dados
--  Execute este script no phpMyAdmin (aba SQL)
-- ============================================================

-- 1. Cria o banco
CREATE DATABASE IF NOT EXISTS kosmos
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE kosmos;

-- 2. Tabela de usuários
CREATE TABLE IF NOT EXISTS usuarios (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nome        VARCHAR(120)    NOT NULL,
    email       VARCHAR(180)    NOT NULL UNIQUE,
    senha_hash  VARCHAR(255)    NOT NULL,
    criado_em   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
