<?php
// ============================================================
//  KOSMOS — O astro da sequência
//  Arquivo: Frontend/pages/dashboard/partes/astro-sequencia.php
//
//  No lugar do fogo, um astro que EVOLUI com os dias seguidos — a
//  mesma ideia da chama que "pega fogo" em outros apps, no tema do
//  Kosmos. Cada fase é um desenho diferente, mais vivo que o anterior:
//
//    fase 0  (0 dias)    estrela apagada, só o contorno
//    fase 1  (1–2)       faísca que pisca
//    fase 2  (3–6)       estrela brilhante com cintilações em volta
//    fase 3  (7–13)      cometa com a cauda correndo
//    fase 4  (14–29)     planeta com anel e uma lua em órbita
//    fase 5  (30+)       galáxia em espiral, girando
//
//  Os movimentos e o brilho moram no dashboard.css (.astro…). As
//  fases NÃO têm nome na tela de propósito: o desenho já conta.
// ============================================================

/** A fase do astro para N dias de sequência (0 a 5). */
function astroFase(int $dias): int
{
    $fase = 0;
    foreach ([1, 3, 7, 14, 30] as $i => $minimo) {
        if ($dias >= $minimo) {
            $fase = $i + 1;
        }
    }
    return $fase;
}

/** O SVG do astro para N dias de sequência. */
function astroSequencia(int $dias): string
{
    $fase = astroFase($dias);

    // Estrela de 4 pontas centrada em (cx, cy) com raio r: cada lado é
    // uma curva que passa perto do centro (o "afinar" é o $k).
    $estrela = static function (float $cx, float $cy, float $r): string {
        $k = round($r * 0.16, 2);
        $p = static fn (float $n): string => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
        return 'M' . $p($cx) . ' ' . $p($cy - $r)
             . 'Q' . $p($cx + $k) . ' ' . $p($cy - $k) . ' ' . $p($cx + $r) . ' ' . $p($cy)
             . 'Q' . $p($cx + $k) . ' ' . $p($cy + $k) . ' ' . $p($cx) . ' ' . $p($cy + $r)
             . 'Q' . $p($cx - $k) . ' ' . $p($cy + $k) . ' ' . $p($cx - $r) . ' ' . $p($cy)
             . 'Q' . $p($cx - $k) . ' ' . $p($cy - $k) . ' ' . $p($cx) . ' ' . $p($cy - $r) . 'Z';
    };
    // cintilação pequena: uma cruzinha
    $cruz = static fn (float $x, float $y, float $r, int $n): string => sprintf(
        '<path class="astro__pisca astro__pisca--%5$d" d="M%1$s %3$sv%4$sM%6$s %2$sh%4$s" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>',
        $x, $y, $y - $r, $r * 2, $n, $x - $r
    );

    $abre = '<svg class="ico astro astro--f' . $fase . '" viewBox="0 0 24 24" fill="none" aria-hidden="true">';

    switch ($fase) {
        case 0:
            return $abre
                . '<path d="' . $estrela(12, 12, 8) . '" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>'
                . '</svg>';

        case 1:
            return $abre
                . '<path class="astro__brilho" d="' . $estrela(12, 12, 7.5) . '" fill="currentColor"/>'
                . '</svg>';

        case 2:
            return $abre
                . '<defs><radialGradient id="astroEstrela" cx="50%" cy="50%" r="55%">'
                . '<stop offset="0" stop-color="#fff"/><stop offset=".45" stop-color="#ead6ff"/><stop offset="1" stop-color="currentColor"/>'
                . '</radialGradient></defs>'
                . '<path class="astro__brilho" d="' . $estrela(11, 13, 9) . '" fill="url(#astroEstrela)"/>'
                . $cruz(19.5, 4.5, 1.8, 2)
                . $cruz(4.2, 4.8, 1.3, 3)
                . '</svg>';

        case 3:
            return $abre
                . '<defs>'
                . '<linearGradient id="astroCauda" x1="16" y1="8" x2="3" y2="21" gradientUnits="userSpaceOnUse">'
                . '<stop offset="0" stop-color="#f3e6ff"/><stop offset=".5" stop-color="currentColor"/><stop offset="1" stop-color="currentColor" stop-opacity="0"/>'
                . '</linearGradient>'
                . '<radialGradient id="astroCabeca" cx="40%" cy="40%" r="60%"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#e2c6ff"/></radialGradient>'
                . '</defs>'
                . '<g stroke="url(#astroCauda)" stroke-linecap="round">'
                . '<path class="astro__cauda" d="M14 10 3.5 20.5" stroke-width="2.6"/>'
                . '<path class="astro__cauda astro__cauda--2" d="M16.2 11.4 8.5 19.1" stroke-width="1.5"/>'
                . '<path class="astro__cauda astro__cauda--3" d="M12.6 7.8 4.9 15.5" stroke-width="1.5"/>'
                . '</g>'
                . '<circle class="astro__halo" cx="16" cy="8" r="5.2" fill="currentColor" opacity=".3"/>'
                . '<circle cx="16" cy="8" r="3.2" fill="url(#astroCabeca)"/>'
                . '</svg>';

        case 4:
            return $abre
                . '<defs><linearGradient id="astroPlaneta" x1="7" y1="6" x2="17" y2="18" gradientUnits="userSpaceOnUse">'
                . '<stop offset="0" stop-color="#ffb3e1"/><stop offset=".55" stop-color="currentColor"/><stop offset="1" stop-color="#6a1fd1"/>'
                . '</linearGradient></defs>'
                . '<g transform="rotate(-22 12 12)">'
                . '<path d="M2 12A10 3.3 0 0 1 22 12" stroke="#e6ccff" stroke-width="1.3" opacity=".55"/>'   // anel atrás
                . '</g>'
                . '<circle cx="12" cy="12" r="6" fill="url(#astroPlaneta)"/>'
                . '<g transform="rotate(-22 12 12)">'
                . '<path d="M2 12A10 3.3 0 0 0 22 12" stroke="#f1e2ff" stroke-width="1.5" stroke-linecap="round"/>'   // anel na frente
                . '</g>'
                . '<g class="astro__orbita"><circle cx="12" cy="1.6" r="1.4" fill="#fff"/></g>'
                . '</svg>';

        default: // 5 — galáxia
            return $abre
                . '<defs>'
                . '<linearGradient id="astroBraco" x1="4" y1="4" x2="20" y2="20" gradientUnits="userSpaceOnUse">'
                . '<stop offset="0" stop-color="#ff9ad5"/><stop offset=".5" stop-color="currentColor"/><stop offset="1" stop-color="#8be3ff"/>'
                . '</linearGradient>'
                . '<radialGradient id="astroNucleo" cx="50%" cy="50%" r="50%"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#f0dcff"/></radialGradient>'
                . '</defs>'
                . '<g class="astro__giro" stroke="url(#astroBraco)" stroke-linecap="round">'
                . '<path d="M12 12c2.4-1.1 3.4-4.2 1.6-6.6-2-2.7-6.3-2.9-8.7-.3" stroke-width="1.9"/>'
                . '<path d="M12 12c-2.4 1.1-3.4 4.2-1.6 6.6 2 2.7 6.3 2.9 8.7.3" stroke-width="1.9"/>'
                . '<path d="M12 12c1.2 2.2 4.2 3 6.4 1.4" stroke-width="1.3" opacity=".8"/>'
                . '<path d="M12 12c-1.2-2.2-4.2-3-6.4-1.4" stroke-width="1.3" opacity=".8"/>'
                . '</g>'
                . '<circle class="astro__halo" cx="12" cy="12" r="4.4" fill="currentColor" opacity=".35"/>'
                . '<circle cx="12" cy="12" r="2.3" fill="url(#astroNucleo)"/>'
                . $cruz(20.4, 3.8, 1.5, 1)
                . $cruz(3.6, 19.8, 1.3, 2)
                . $cruz(20.6, 19.4, 1.1, 3)
                . '</svg>';
    }
}
