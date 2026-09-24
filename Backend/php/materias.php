<?php
// ============================================================
//  KOSMOS — Lista de matérias aceitas
//  Arquivo: backend/php/materias.php
//  Uma lista só, usada pela validação (conta_preferencias.php) e
//  pela página que desenha os chips (conta.php). Antes a lista
//  existia solta no endpoint e o HTML era montado por JS.
// ============================================================

/* Em ordem alfabética (sem considerar acento): é ela que os
   dropdowns de Matéria, os chips da Conta e o onboarding desenham,
   na mesma ordem em todo o app. */
const MATERIAS_KOSMOS = [
    'Biologia', 'Filosofia', 'Física', 'Geografia', 'História', 'Inglês',
    'Literatura', 'Matemática', 'Português', 'Química', 'Redação',
    'Sociologia',
];

/**
 * Aceita matéria pré-definida OU texto livre digitado pelo usuário.
 *
 * Se o texto bater com uma da lista (ignorando maiúsculas/minúsculas),
 * devolve o valor canônico da lista — assim "biologia" e "Biologia"
 * viram a MESMA matéria e os filtros continuam funcionando. Se não
 * bater, devolve o texto como foi digitado (já aparado); o limite de
 * tamanho é de quem chama (40 = coluna `materia` no banco).
 */
function materiaCanonica(string $materia): string {
    $materia = trim($materia);
    if ($materia === '') {
        return '';
    }

    foreach (MATERIAS_KOSMOS as $conhecida) {
        if (mb_strtolower($materia) === mb_strtolower($conhecida)) {
            return $conhecida;
        }
    }

    return $materia;
}

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
