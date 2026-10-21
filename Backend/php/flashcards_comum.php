<?php
// ============================================================
//  KOSMOS — Helpers dos endpoints de Flashcards
//  Arquivo: backend/php/flashcards_comum.php
//  Tudo que os quatro endpoints de flashcards têm em comum:
//  resposta em JSON, validação de texto e checagem de dono.
//
//  REGRA DE DATA: nenhuma data sai do PHP. Gravação usa NOW() e
//  leitura usa DATEDIFF()/DATE_FORMAT() do MySQL — um relógio só.
//  (Ver "Problemas conhecidos" no README: fuso PHP × MySQL.)
// ============================================================

require_once __DIR__ . '/api_util.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/sessao.php';

/** Limites de tamanho — batem com o VARCHAR das tabelas. */
const FC_MAX_NOME    = 120;
const FC_MAX_MATERIA = 40;
const FC_MAX_TEXTO   = 600;

/* Atalhos com o prefixo da área — só repassam para o api_util.php,
   onde mora o formato único de resposta. */
function fcResponder(array $dados, int $codigo = 200): void { apiResponder($dados, $codigo); }
function fcErro(string $msg, int $codigo = 400): void       { apiErro($msg, $codigo); }
function fcExigirPost(): void                                { apiExigirPost(); }

/**
 * Lê um campo de texto do POST já validado.
 * Devolve o texto limpo; acrescenta o problema em $erros se houver.
 */
function fcTexto(string $campo, string $rotulo, int $max, array &$erros, bool $obrigatorio = true): string {
    $valor = trim((string) ($_POST[$campo] ?? ''));

    if ($valor === '') {
        if ($obrigatorio) {
            $erros[] = "O campo \"$rotulo\" é obrigatório.";
        }
        return '';
    }

    if (mb_strlen($valor) > $max) {
        $erros[] = "O campo \"$rotulo\" deve ter no máximo $max caracteres.";
    }

    return $valor;
}

/** Lê um id inteiro positivo do POST/GET. Devolve 0 se inválido. */
function fcId(string $campo): int {
    $valor = $_POST[$campo] ?? $_GET[$campo] ?? 0;
    $id = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $id === false ? 0 : (int) $id;
}

/**
 * Busca um deck GARANTINDO que ele pertence ao usuário logado.
 * É o que impede alguém de mexer no deck de outra pessoa só
 * trocando o id na requisição. Devolve null se não for dele.
 */
function fcDeckDoUsuario(PDO $pdo, int $deckId, int $usuarioId): ?array {
    if ($deckId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, nome, materia, cor FROM flashcard_decks WHERE id = ? AND usuario_id = ? LIMIT 1'
    );
    $stmt->execute([$deckId, $usuarioId]);
    $deck = $stmt->fetch();

    return $deck ?: null;
}

/**
 * Mesma ideia para um cartão: ele só é "do usuário" se o deck dele for.
 * Devolve null se o cartão não existir ou for de outra conta.
 */
function fcCartaoDoUsuario(PDO $pdo, int $cartaoId, int $usuarioId): ?array {
    if ($cartaoId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT c.id, c.deck_id
           FROM flashcard_cartoes c
           JOIN flashcard_decks d ON d.id = c.deck_id
          WHERE c.id = ? AND d.usuario_id = ?
          LIMIT 1'
    );
    $stmt->execute([$cartaoId, $usuarioId]);
    $cartao = $stmt->fetch();

    return $cartao ?: null;
}
