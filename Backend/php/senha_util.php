<?php
// ============================================================
//  KOSMOS — Regras de senha (fonte única)
//  Arquivo: Backend/php/senha_util.php
//
//  Usado por login.php, cadastro.php, conta_senha.php,
//  senha_resetar.php e conta_excluir.php.
//
//  A senha passa por trim() em TODO lugar (cadastro, login, troca):
//  espaço nas pontas quase sempre é acidente — o teclado do celular
//  põe um depois do autocompletar — e sem o corte a pessoa ficaria
//  trancada fora sem saber por quê. Os formulários também cortam.
//
//  Máximo de 72 BYTES: o bcrypt ignora tudo depois disso, então duas
//  senhas que só diferem no 73º byte seriam a mesma. Melhor recusar.
// ============================================================

const SENHA_MIN_CARACTERES = 8;
const SENHA_MAX_BYTES      = 72;

/** Lê a senha do POST, sem os espaços das pontas. */
function lerSenha(string $campo): string {
    return trim((string) ($_POST[$campo] ?? ''));
}

/** Devolve a mensagem de erro, ou null se a senha nova serve. */
function erroNovaSenha(string $senha): ?string {
    if ($senha === '') {
        return 'Informe a senha.';
    }
    if (mb_strlen($senha) < SENHA_MIN_CARACTERES) {
        return 'A senha deve ter no mínimo ' . SENHA_MIN_CARACTERES . ' caracteres.';
    }
    if (strlen($senha) > SENHA_MAX_BYTES) {
        return 'A senha deve ter no máximo ' . SENHA_MAX_BYTES . ' caracteres.';
    }
    return null;
}

function gerarHashSenha(string $senha): string {
    return password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
}

/** Confere a senha (já aparada pelo lerSenha) com o hash gravado. */
function senhaConfere(string $senha, string $hash): bool {
    return password_verify($senha, $hash);
}

/**
 * Hash bcrypt de uma senha aleatória descartada, com o mesmo custo (12)
 * dos de verdade. O login confere contra ele quando o e-mail não
 * existe, para a resposta levar o mesmo tempo nos dois casos. É fixo
 * de propósito: gerá-lo na hora custaria um segundo bcrypt e
 * recriaria a diferença de tempo. Ninguém sabe a senha que o gerou.
 */
function senhaHashFalso(): string {
    return '$2y$12$nNBJ9cnB4G2WiuLILDbGAeXgeYILWieXByzSavOBn3P.hoAhY5J/y';
}
