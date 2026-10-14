<?php
// ============================================================
//  KOSMOS — Testes da lógica pura (sem banco, sem PHPUnit)
//  Arquivo: Backend/testes/rodar.php
//
//  Cobre o que não depende de banco nem de sessão: repetição
//  espaçada dos flashcards, datas em pt-BR e regras de senha.
//  Lógica que mexe no banco (XP, sequência, limite de tentativas)
//  fica de fora — testar isso pediria um banco só de teste.
//
//  Uso (da raiz do AppPrincipal):
//      C:\xampp\php\php.exe Backend/testes/rodar.php
//  Sai com código 1 se algum teste falhar.
// ============================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../php/flashcards_srs.php';
require_once __DIR__ . '/../php/datas.php';
require_once __DIR__ . '/../php/senha_util.php';

$falhas = 0;
$total  = 0;

function confere(string $nome, $obtido, $esperado): void {
    global $falhas, $total;
    $total++;
    if ($obtido === $esperado) {
        echo "  ok    $nome\n";
        return;
    }
    $falhas++;
    echo "  FALHA $nome\n"
       . '        esperado: ' . var_export($esperado, true) . "\n"
       . '        obtido:   ' . var_export($obtido, true) . "\n";
}

// ---------------------------------------------------------------
echo "Repetição espaçada (flashcards_srs.php)\n";

confere('cartão novo acertado vai para 1 dia',
    fcProximaRevisao(0, 2.5, true)['intervalo'], 1);
confere('segundo acerto vai para 3 dias',
    fcProximaRevisao(1, 2.6, true)['intervalo'], 3);
confere('depois multiplica pela facilidade (3 x 2.7 ≈ 8)',
    fcProximaRevisao(3, 2.7, true)['intervalo'], 8);
confere('acerto sobe a facilidade 0.10',
    fcProximaRevisao(3, 2.5, true)['facilidade'], 2.6);
confere('erro volta para 1 dia, mesmo de um intervalo longo',
    fcProximaRevisao(90, 2.5, false)['intervalo'], 1);
confere('erro derruba a facilidade 0.20',
    fcProximaRevisao(10, 2.5, false)['facilidade'], 2.3);
confere('facilidade nunca passa do mínimo',
    fcProximaRevisao(10, FC_FACILIDADE_MIN, false)['facilidade'], FC_FACILIDADE_MIN);
confere('facilidade nunca passa do máximo',
    fcProximaRevisao(10, FC_FACILIDADE_MAX, true)['facilidade'], FC_FACILIDADE_MAX);
confere('intervalo tem teto de ' . FC_INTERVALO_MAX . ' dias',
    fcProximaRevisao(150, 2.8, true)['intervalo'], FC_INTERVALO_MAX);

// ---------------------------------------------------------------
echo "Datas em pt-BR (datas.php)\n";

confere('data curta', dataCurtaPt(5, 3), '05 mar');
confere('data curta sem mês vira vazio', dataCurtaPt(5, null), '');
confere('data curta com mês inválido vira vazio', dataCurtaPt(5, 13), '');
confere('data longa', dataLongaPt(12, 6, 2026), '12 de junho de 2026');
confere('data longa incompleta vira travessão', dataLongaPt(12, 6, null), '—');

// ---------------------------------------------------------------
echo "Regras de senha (senha_util.php)\n";

confere('senha curta é recusada', erroNovaSenha('1234567') !== null, true);
confere('senha vazia é recusada', erroNovaSenha('') !== null, true);
confere('8 caracteres servem', erroNovaSenha('12345678'), null);
confere('72 bytes servem', erroNovaSenha(str_repeat('a', 72)), null);
confere('73 bytes são recusados (o bcrypt cortaria)', erroNovaSenha(str_repeat('a', 73)) !== null, true);
confere('acentos contam em bytes: 37 "é" = 74 bytes', erroNovaSenha(str_repeat('é', 37)) !== null, true);

$_POST['senha_teste'] = "  gato1234 \n";
confere('lerSenha corta os espaços das pontas', lerSenha('senha_teste'), 'gato1234');
confere('lerSenha mantém espaço do meio', (function () {
    $_POST['senha_teste'] = ' minha senha ';
    return lerSenha('senha_teste');
})(), 'minha senha');
confere('campo ausente vira vazio', lerSenha('nao_existe'), '');

$hash = gerarHashSenha('gato1234');
confere('senha certa confere', senhaConfere('gato1234', $hash), true);
confere('senha errada não confere', senhaConfere('gato12345', $hash), false);

// ---------------------------------------------------------------
echo "\n" . ($total - $falhas) . " de $total testes passaram.\n";
exit($falhas > 0 ? 1 : 0);
