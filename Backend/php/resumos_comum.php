<?php
// ============================================================
//  KOSMOS — Helpers dos endpoints de Resumos
//  Arquivo: backend/php/resumos_comum.php
//  Mesmo padrão dos flashcards (flashcards_comum.php), com nomes
//  neutros — estes helpers servem para qualquer feature nova.
//
//  REGRA DE DATA: nenhuma data é calculada em PHP. Gravação usa
//  NOW()/CURRENT_TIMESTAMP e a leitura pede dia/mês/ano ao MySQL —
//  um relógio só. (Ver "Problemas conhecidos": fuso PHP × MySQL.)
// ============================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/materias.php';
require_once __DIR__ . '/datas.php';

/** Limites — batem com as colunas da tabela `resumos`. */
const RS_MAX_TITULO = 140;
const RS_MAX_CORPO  = 20000;     // mediumtext aguenta muito mais; isto é o limite de uso

/** Responde em JSON e encerra. */
function apiResponder(array $dados, int $codigo = 200): void {
    http_response_code($codigo);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Erro simples ("não deu certo, e o motivo"). */
function apiErro(string $msg, int $codigo = 400): void {
    apiResponder(['ok' => false, 'msg' => $msg], $codigo);
}

/** Recusa qualquer coisa que não seja POST. */
function apiExigirPost(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        apiErro('Método não permitido.', 405);
    }
}

/** Lê um campo de texto do POST já validado. */
function apiTexto(string $campo, string $rotulo, int $max, array &$erros, bool $obrigatorio = true): string {
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
function apiId(string $campo): int {
    $valor = $_POST[$campo] ?? $_GET[$campo] ?? 0;
    $id = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $id === false ? 0 : (int) $id;
}

/** A matéria tem de estar na lista conhecida (materias.php). */
function apiMateria(array &$erros): string {
    $materia = trim((string) ($_POST['materia'] ?? ''));

    if ($materia === '') {
        $erros[] = 'Escolha a matéria.';
        return '';
    }
    if (!in_array($materia, MATERIAS_KOSMOS, true)) {
        $erros[] = 'Matéria desconhecida.';
        return '';
    }

    return $materia;
}

/**
 * Busca um resumo GARANTINDO que ele é do usuário logado.
 * É o que impede alguém de abrir/editar/apagar resumo de outra
 * pessoa só trocando o id na requisição.
 */
function resumoDoUsuario(PDO $pdo, int $resumoId, int $usuarioId): ?array {
    if ($resumoId < 1) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id, titulo, materia, corpo
                             FROM resumos
                            WHERE id = ? AND usuario_id = ?
                            LIMIT 1');
    $stmt->execute([$resumoId, $usuarioId]);
    $resumo = $stmt->fetch();

    return $resumo ?: null;
}

/** Atalho para o helper compartilhado (datas.php). */
function apiDataCurta(?int $dia, ?int $mes): string {
    return dataCurtaPt($dia, $mes);
}
