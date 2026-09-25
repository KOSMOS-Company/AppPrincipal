<?php
// ============================================================
//  KOSMOS — Ícones das conquistas
//  Arquivo: Backend/php/conquistas_icones.php
//
//  `conquistas.icone_svg` guarda só a REFERÊNCIA ('cometa', 'lua'...).
//  O desenho mora aqui, num lugar só: a página de conquistas imprime
//  direto no HTML e o ProgressoService manda junto no payload, para o
//  aviso de "conquista desbloqueada" usar o MESMO ícone sem que o JS
//  tenha uma cópia.
//
//  Mesmo traço dos ícones do resto do app: 24×24, contorno em
//  currentColor (herda a cor do tema), pontas arredondadas.
// ============================================================

function conquistaIconeSvg(string $ref): string
{
    static $desenhos = [
        'relogio'     => '<circle cx="12" cy="13" r="8"/><path d="M12 9v4l3 2M9 3h6"/>',
        'foguete'     => '<path d="M12 3c3.5 2 5 5.5 5 9l-2 4H9l-2-4c0-3.5 1.5-7 5-9Z"/><circle cx="12" cy="10" r="1.8"/><path d="M9 16l-2 4 3-1M15 16l2 4-3-1"/>',
        'raio'        => '<path d="M13 2 5 13h6l-1 9 8-11h-6l1-9Z"/>',
        'chama'       => '<path d="M12 21.2a5.6 5.6 0 0 0 5.6-5.6c0-4.6-5.6-9.2-5.6-9.2S6.4 11 6.4 15.6A5.6 5.6 0 0 0 12 21.2Z"/><path d="M12 21.2a2.4 2.4 0 0 0 2.4-2.4c0-2-2.4-4.1-2.4-4.1s-2.4 2.1-2.4 4.1a2.4 2.4 0 0 0 2.4 2.4Z"/>',
        'cartas'      => '<rect x="3" y="7" width="12" height="13" rx="2"/><path d="M8 4h10a2 2 0 0 1 2 2v11"/>',
        'cometa'      => '<circle cx="15.5" cy="8.5" r="3.5"/><path d="M13 11 4 20M11 8.5 5 14.5M15.5 12.5l-5 5"/>',
        'galaxia'     => '<circle cx="12" cy="12" r="1.6"/><path d="M12 5.5c4 0 6.5 3 6.5 6.5M12 18.5c-4 0-6.5-3-6.5-6.5M18.5 12c0 3.5-3 6-6.5 6M5.5 12c0-3.5 3-6 6.5-6"/><path d="M12 9c1.7 0 3 1.3 3 3M12 15c-1.7 0-3-1.3-3-3"/>',
        'pena'        => '<path d="M20 4c-8 0-13 5-14 12l-2 4"/><path d="M6 16c4 0 9-2 11-7M9 13h5"/>',
        'livros'      => '<path d="M4 5a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5Z"/><path d="M11 5a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-3a1 1 0 0 1-1-1V5Z"/><path d="m18.4 6.2 2.2 13.1"/>',
        'alvo'        => '<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="4.5"/><circle cx="12" cy="12" r="1" fill="currentColor"/>',
        'mira'        => '<circle cx="12" cy="12" r="7"/><path d="M12 2v5M12 17v5M2 12h5M17 12h5"/>',
        'trofeu'      => '<path d="M8 4h8v5a4 4 0 0 1-8 0V4Z"/><path d="M8 6H5a3 3 0 0 0 3 4M16 6h3a3 3 0 0 1-3 4M12 13v4M8.5 20h7M10 17h4"/>',
        'estrela'     => '<path d="m12 3.5 2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.9l-5.2 2.7 1-5.8-4.3-4.1 5.9-.9L12 3.5Z"/>',
        'orion'       => '<circle cx="12" cy="12" r="5.5"/><path d="M3.5 13.5C5 9.5 12 7 17.5 9c3 1 3.5 2.5 3 3.5-1.5 2-8.5 4.5-14 2.5-3-1-3.5-2.5-3-3.5"/>',
        'faisca'      => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M18 6l-2.5 2.5M8.5 15.5 6 18"/>',
        'lua'         => '<path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5Z"/>',
        'constelacao' => '<circle cx="5" cy="17" r="1.6"/><circle cx="10" cy="8" r="1.6"/><circle cx="16" cy="12" r="1.6"/><circle cx="19" cy="5" r="1.6"/><path d="M6.2 15.6 9 9.5M11.5 8.8l3 2.3M17 10.6l1.3-4"/>',
        'subida'      => '<path d="M4 18 10 12l4 4 6-8"/><path d="M15 8h5v5"/>',
        'sol'         => '<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2.5M12 19v2.5M2.5 12H5M19 12h2.5M5.3 5.3l1.8 1.8M16.9 16.9l1.8 1.8M18.7 5.3l-1.8 1.8M7.1 16.9l-1.8 1.8"/>',
        'coroa'       => '<path d="M4 8l4 4 4-7 4 7 4-4-2 11H6L4 8Z"/><path d="M6 19h12"/>',
        'cadeado'     => '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
    ];

    $miolo = $desenhos[$ref] ?? $desenhos['estrela'];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" '
         . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
         . $miolo . '</svg>';
}
