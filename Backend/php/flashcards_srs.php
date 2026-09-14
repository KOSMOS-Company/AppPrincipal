<?php
// ============================================================
//  KOSMOS — Repetição espaçada (SM-2 enxuto)
//  Arquivo: Backend/php/flashcards_srs.php
//
//  A tabela já contava revisões, acertos e erros — isso é
//  HISTÓRICO. O que faltava era AGENDAMENTO: responder "quando
//  mostrar este cartão de novo". É a única diferença entre um
//  baralho de cartões e uma ferramenta de memorização, e é o que
//  o Orion promete na landing page.
//
//  ── O algoritmo, em três frases ──
//  Acertou: o intervalo até a próxima revisão MULTIPLICA (1 dia,
//  depois 3, depois 3 x facilidade...). Errou: volta para o
//  começo, porque um cartão que você esqueceu não é "quase
//  sabido" — é novo de novo. A `facilidade` é o quanto ESTE
//  cartão é fácil PARA ESTA PESSOA: cai rápido a cada erro e sobe
//  devagar a cada acerto, então cartão difícil volta mais vezes
//  mesmo depois de acertado.
//
//  É o SM-2 clássico sem a nota de 0 a 5 — aqui a pessoa só diz
//  "acertei" ou "errei". Pedir nota de esforço numa tela de
//  estudo rápido é atrito que ninguém preenche com sinceridade.
// ============================================================

/** Nunca deixa um cartão virar "fácil demais" e sumir por meses. */
const FC_FACILIDADE_MIN = 1.30;
const FC_FACILIDADE_MAX = 2.80;

/** Teto do intervalo: seis meses. Acima disso o agendamento deixa
    de ser estudo e vira esquecimento programado. */
const FC_INTERVALO_MAX = 180;

/**
 * Calcula o próximo agendamento de um cartão.
 *
 * @param int   $intervalo  dias do intervalo atual (0 = cartão novo)
 * @param float $facilidade fator atual (2.50 é o padrão de início)
 * @param bool  $acertou    resultado desta revisão
 * @return array{intervalo:int, facilidade:float}
 */
function fcProximaRevisao(int $intervalo, float $facilidade, bool $acertou): array {
    if (!$acertou) {
        /* Errou: volta para 1 dia. Não para zero — zero faria o
           cartão reaparecer na MESMA sessão, e acertar dois minutos
           depois não é lembrar, é memória de curto prazo.

           A facilidade cai 0.20: é o que faz um cartão difícil
           continuar voltando mais vezes mesmo depois de você
           começar a acertá-lo. */
        return [
            'intervalo'  => 1,
            'facilidade' => max(FC_FACILIDADE_MIN, $facilidade - 0.20),
        ];
    }

    /* Acertou. Os dois primeiros passos são fixos (1 e 3 dias) —
       é o que o SM-2 faz para não mandar um cartão recém-aprendido
       para daqui a uma semana só porque você acertou uma vez. */
    if ($intervalo <= 0) {
        $novo = 1;
    } elseif ($intervalo === 1) {
        $novo = 3;
    } else {
        $novo = (int) round($intervalo * $facilidade);
    }

    return [
        'intervalo'  => min(FC_INTERVALO_MAX, max(1, $novo)),
        'facilidade' => min(FC_FACILIDADE_MAX, $facilidade + 0.10),
    ];
}
