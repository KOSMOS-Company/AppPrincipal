<?php
// ============================================================
//  KOSMOS — Datas para exibição
//  Arquivo: backend/php/datas.php
//  O PHP nunca interpreta data aqui: recebe dia/mês/ano já
//  separados pelo MySQL (DAY(), MONTH(), YEAR()) e só monta o
//  texto. É assim que o projeto evita o problema de fuso entre
//  PHP e MySQL (ver "Problemas conhecidos" no README).
// ============================================================

const MESES_CURTOS_PT = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun',
                         'jul', 'ago', 'set', 'out', 'nov', 'dez'];

const MESES_PT = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
                  'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

/** "12 jun" */
function dataCurtaPt(?int $dia, ?int $mes): string {
    if (!$dia || !$mes || $mes < 1 || $mes > 12) {
        return '';
    }
    return sprintf('%02d %s', $dia, MESES_CURTOS_PT[$mes - 1]);
}

/** "12 de junho de 2026" */
function dataLongaPt(?int $dia, ?int $mes, ?int $ano): string {
    if (!$dia || !$mes || !$ano || $mes < 1 || $mes > 12) {
        return '—';
    }
    return $dia . ' de ' . MESES_PT[$mes - 1] . ' de ' . $ano;
}
