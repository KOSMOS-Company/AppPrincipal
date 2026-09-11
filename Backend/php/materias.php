<?php
// ============================================================
//  KOSMOS — Lista de matérias aceitas
//  Arquivo: backend/php/materias.php
//  Uma lista só, usada pela validação (conta_preferencias.php) e
//  pela página que desenha os chips (conta.php). Antes a lista
//  existia solta no endpoint e o HTML era montado por JS.
// ============================================================

const MATERIAS_KOSMOS = [
    'Matemática', 'Física', 'Química', 'Biologia', 'História', 'Português',
    'Geografia', 'Filosofia', 'Sociologia', 'Inglês', 'Redação',
];

const CORES_AVATAR_KOSMOS = ['roxo', 'azul', 'verde', 'laranja', 'rosa', 'ciano'];

/* A cor do caderno usa a MESMA paleta do avatar: são as classes
   .avatar-cor--* que já existem no dashboard.css, então escolher
   uma cor nova aqui não custa nenhum CSS. */
const CORES_CADERNO_KOSMOS = CORES_AVATAR_KOSMOS;

/**
 * Ícones que o caderno pode usar.
 *
 * É uma lista fechada de propósito, não um campo livre de emoji: o
 * ícone vai para o HTML de várias telas, e uma lista curada garante
 * que ali só entra emoji de verdade — sem depender de adivinhar, por
 * expressão regular, o que é ou não emoji num texto qualquer.
 */
const ICONES_CADERNO_KOSMOS = [
    // cadernos e escrita
    '📕', '📗', '📘', '📙', '📓', '📔', '📝', '✏️',
    // exatas
    '🔢', '📐', '➗', '💻', '⚙️', '🧲',
    // natureza
    '🧪', '🔬', '🧬', '🌱', '🪐', '🌎',
    // humanas
    '🏛️', '🗺️', '⚖️', '🗣️', '🧠', '💡',
    // idiomas e artes
    '🌐', '🇧🇷', '🎨', '🎭', '🎵', '⚽',
];
