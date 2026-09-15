<?php
// ============================================================
//  KOSMOS — Gerar flashcards a partir de um resumo
//  Arquivo: Backend/php/flashcards_gerar.php
//
//  POST (JSON), campo "acao":
//    sugerir -> resumo            : lê o resumo e devolve os cartões
//                                   que dá para tirar dele, mais os
//                                   baralhos onde eles podem entrar
//    salvar  -> resumo, deck | deck_nome, cartoes[]
//                                   : grava os cartões que a pessoa
//                                   confirmou
//
//  POR QUE NÃO É IA
//  O projeto tem uma ponte de IA (gerar_exercicios.php, via n8n), e
//  seria natural usá-la aqui. Só que ela depende de rede, de token e
//  de um serviço que hoje devolve 500 — e um botão que às vezes não
//  faz nada é pior do que um botão que faz menos. A leitura é feita
//  por regra, aqui mesmo, em cima de três padrões que já existem no
//  jeito de escrever do app:
//
//    1. ==destaque==      -> o mesmo par que o Modo revisão do
//                            resumo já usa. Vira cartão de completar.
//    2. "Termo: definição" -> vira "O que é Termo?" / definição.
//    3. "Pergunta?"        -> a linha seguinte é a resposta.
//
//  Nada é gravado direto: o servidor SUGERE, a pessoa confere, edita
//  e só então salva. Um cartão errado memorizado é pior do que
//  cartão nenhum, então a última palavra nunca é do algoritmo.
// ============================================================

require_once __DIR__ . '/flashcards_comum.php';

/** Teto do que sai de um resumo só. Baralho gigante ninguém revisa. */
const FCG_MAX_SUGESTOES = 20;
/** Teto do que entra numa gravação (a tela deixa adicionar cartões à mão). */
const FCG_MAX_SALVAR    = 40;

$usuario = exigirLogin();
fcExigirPost();

$corpo = fcgCorpo();
$acao  = (string) ($corpo['acao'] ?? '');

try {
    $pdo = conectar();

    /* ---------------- sugerir ---------------- */
    if ($acao === 'sugerir') {
        $resumo = fcgResumoDoUsuario($pdo, $corpo['resumo'] ?? 0, (int) $usuario['id']);

        liberarSessao();

        $decks = fcgDecks($pdo, (int) $usuario['id']);

        /* O baralho sugerido é o primeiro da mesma matéria do resumo:
           quem já tem "Biologia" não quer um segundo baralho de
           Biologia por resumo escrito — quer os cartões novos caindo
           onde os antigos estão, para a revisão continuar sendo uma
           fila só. */
        $sugerido = 0;
        foreach ($decks as $d) {
            if ($d['materia'] === $resumo['materia']) {
                $sugerido = $d['id'];
                break;
            }
        }

        fcResponder([
            'ok'      => true,
            'resumo'  => [
                'id'      => (int) $resumo['id'],
                'titulo'  => $resumo['titulo'],
                'materia' => $resumo['materia'],
            ],
            'cartoes'       => fcgSugerir((string) $resumo['corpo']),
            'decks'         => $decks,
            'deck_sugerido' => $sugerido,
            // Nome do baralho novo, caso ela prefira separar: o tema do
            // resumo já é o recorte certo do assunto.
            'nome_sugerido' => mb_substr($resumo['titulo'], 0, FC_MAX_NOME),
        ]);
    }

    /* ---------------- salvar ---------------- */
    if ($acao === 'salvar') {
        $resumo  = fcgResumoDoUsuario($pdo, $corpo['resumo'] ?? 0, (int) $usuario['id']);
        $cartoes = fcgLerCartoes($corpo['cartoes'] ?? []);

        // Baralho: um que já existe (e é desta conta) ou um novo.
        $deckId = filter_var($corpo['deck'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $novo   = false;

        if ($deckId === false || $deckId < 1) {
            $nome = trim((string) ($corpo['deck_nome'] ?? ''));
            if ($nome === '') {
                fcErro('Escolha um baralho ou dê um nome ao novo.', 422);
            }
            if (mb_strlen($nome) > FC_MAX_NOME) {
                fcErro('O nome do baralho é longo demais.', 422);
            }
            $novo = true;
        } elseif (fcDeckDoUsuario($pdo, $deckId, (int) $usuario['id']) === null) {
            fcErro('Baralho não encontrado.', 404);
        }

        liberarSessao();

        /* Uma transação porque criar o baralho e encher de cartões é
           UMA ação para quem clicou. Falhar no meio deixaria um
           baralho vazio na estante que a pessoa não pediu. */
        $pdo->beginTransaction();
        try {
            if ($novo) {
                // A matéria é a do resumo: o baralho nasce da mesma
                // fonte, e escolher matéria de novo seria perguntar o
                // que o app já sabe.
                $pdo->prepare('INSERT INTO flashcard_decks (usuario_id, nome, materia) VALUES (?, ?, ?)')
                    ->execute([$usuario['id'], $nome, $resumo['materia']]);
                $deckId = (int) $pdo->lastInsertId();
            }

            $stmt = $pdo->prepare('SELECT COALESCE(MAX(ordem), 0) FROM flashcard_cartoes WHERE deck_id = ?');
            $stmt->execute([$deckId]);
            $ordem = (int) $stmt->fetchColumn();

            $inserir = $pdo->prepare(
                'INSERT INTO flashcard_cartoes (deck_id, frente, verso, ordem) VALUES (?, ?, ?, ?)'
            );
            foreach ($cartoes as $c) {
                $inserir->execute([$deckId, $c['frente'], $c['verso'], ++$ordem]);
            }

            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw $e;
        }

        $n = count($cartoes);
        fcResponder([
            'ok'      => true,
            'deck'    => $deckId,
            'criados' => $n,
            'msg'     => $n === 1 ? '1 cartão criado!' : "$n cartões criados!",
        ], 201);
    }

    fcErro('Ação desconhecida.');
} catch (PDOException $e) {
    fcErro('Não foi possível gerar os cartões agora.', 500);
}


/* ============================================================
   ENTRADA
   ============================================================ */

/** Corpo da requisição em JSON (a lista de cartões não cabe bem em form). */
function fcgCorpo(): array {
    $bruto = json_decode((string) file_get_contents('php://input'), true);
    return is_array($bruto) ? $bruto : $_POST;
}

/**
 * O resumo, GARANTIDO desta conta. É o WHERE usuario_id que impede
 * ler o resumo de outra pessoa só trocando o id na requisição.
 */
function fcgResumoDoUsuario(PDO $pdo, $bruto, int $usuarioId): array {
    $id = filter_var($bruto, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        fcErro('Resumo inválido.', 422);
    }

    $stmt = $pdo->prepare('SELECT id, titulo, materia, corpo
                             FROM resumos WHERE id = ? AND usuario_id = ? LIMIT 1');
    $stmt->execute([$id, $usuarioId]);
    $resumo = $stmt->fetch();

    if (!$resumo) {
        fcErro('Resumo não encontrado.', 404);
    }

    return $resumo;
}

/** Os baralhos do usuário, para o seletor da tela. */
function fcgDecks(PDO $pdo, int $usuarioId): array {
    $stmt = $pdo->prepare(
        'SELECT d.id, d.nome, d.materia,
                (SELECT COUNT(*) FROM flashcard_cartoes c WHERE c.deck_id = d.id) AS cartoes
           FROM flashcard_decks d
          WHERE d.usuario_id = ?
       ORDER BY d.nome'
    );
    $stmt->execute([$usuarioId]);

    $decks = [];
    foreach ($stmt as $d) {
        $decks[] = [
            'id'      => (int) $d['id'],
            'nome'    => $d['nome'],
            'materia' => $d['materia'],
            'cartoes' => (int) $d['cartoes'],
        ];
    }
    return $decks;
}

/** Valida a lista que voltou da tela (a pessoa pode ter editado tudo). */
function fcgLerCartoes($lista): array {
    if (!is_array($lista) || $lista === []) {
        fcErro('Nenhum cartão para salvar.', 422);
    }
    if (count($lista) > FCG_MAX_SALVAR) {
        fcErro('São cartões demais de uma vez.', 422);
    }

    $limpos = [];
    foreach ($lista as $c) {
        if (!is_array($c)) {
            continue;
        }
        $frente = trim((string) ($c['frente'] ?? ''));
        $verso  = trim((string) ($c['verso']  ?? ''));

        // Cartão pela metade não vira cartão: é só ignorado, porque a
        // pessoa pode ter esvaziado uma linha de propósito.
        if ($frente === '' || $verso === '') {
            continue;
        }
        if (mb_strlen($frente) > FC_MAX_TEXTO || mb_strlen($verso) > FC_MAX_TEXTO) {
            fcErro('Um dos cartões passou de ' . FC_MAX_TEXTO . ' caracteres.', 422);
        }

        $limpos[] = ['frente' => $frente, 'verso' => $verso];
    }

    if ($limpos === []) {
        fcErro('Preencha pelo menos um cartão inteiro.', 422);
    }

    return $limpos;
}


/* ============================================================
   A LEITURA DO RESUMO
   ============================================================ */

/**
 * Tira do texto do resumo os cartões que dá para tirar.
 *
 * Linha a linha, de propósito: o texto do resumo é escrito em
 * linhas curtas (é resumo, não redação), e quebrar por frase juntaria
 * itens de lista que não têm relação nenhuma entre si.
 *
 * `origem` acompanha cada sugestão só para a tela poder dizer de onde
 * o cartão veio — quem entende a origem confia (ou desconfia) na
 * hora certa.
 */
function fcgSugerir(string $corpo): array {
    $linhas = preg_split('/\R/u', $corpo) ?: [];
    $linhas = array_map('fcgLimparLinha', $linhas);

    $cartoes = [];
    $vistos  = [];
    $total   = count($linhas);

    for ($i = 0; $i < $total; $i++) {
        if (count($cartoes) >= FCG_MAX_SUGESTOES) {
            break;
        }

        $linha = $linhas[$i];
        if ($linha === '' || mb_strlen($linha) < 8) {
            continue;
        }

        $cartao = fcgDeDestaque($linha)
               ?? fcgDeDefinicao($linha)
               ?? fcgDePergunta($linha, $linhas, $i);

        if ($cartao === null) {
            continue;
        }

        // Resumo costuma repetir o mesmo termo em lugares diferentes;
        // dois cartões com a mesma pergunta só atrapalham a revisão.
        $chave = mb_strtolower($cartao['frente']);
        if (isset($vistos[$chave])) {
            continue;
        }
        $vistos[$chave] = true;

        $cartoes[] = $cartao;
    }

    return $cartoes;
}

/** Tira marcador de lista e espaços das pontas. */
function fcgLimparLinha(string $linha): string {
    $linha = trim($linha);
    // "- ", "* ", "• ", "1. ", "1) " — marcadores, não conteúdo
    return trim(preg_replace('/^([-*•●▪]|\d{1,2}[.)])\s+/u', '', $linha));
}

/**
 * 1) ==destaque== -> cartão de completar.
 * O mesmo par que o Modo revisão do resumo já entende, então o que a
 * pessoa marcou para treinar lá vira cartão aqui sem ela aprender
 * nenhuma sintaxe nova.
 */
function fcgDeDestaque(string $linha): ?array {
    if (!preg_match_all('/==([^=]{1,120})==/u', $linha, $achados)) {
        return null;
    }

    $respostas = array_map('trim', $achados[1]);
    $respostas = array_values(array_filter($respostas, fn($r) => $r !== ''));
    if ($respostas === []) {
        return null;
    }

    $frente = preg_replace('/==([^=]{1,120})==/u', '______', $linha);
    $frente = 'Complete: ' . trim($frente);
    $verso  = implode(' · ', $respostas);

    return fcgMontar($frente, $verso, 'destaque');
}

/**
 * 2) "Termo: definição" -> "O que é Termo?" / definição.
 * Também vale com travessão e com "=", que é como muita gente escreve
 * definição em resumo de caderno.
 */
function fcgDeDefinicao(string $linha): ?array {
    if (!preg_match('/^(.{3,70}?)\s*(?::|—|–|\s-\s|=)\s*(.{12,})$/u', $linha, $m)) {
        return null;
    }

    $termo     = trim($m[1], " \t.;:-—–=");
    $definicao = trim($m[2]);

    // O lado esquerdo tem de parecer um termo: se já é uma frase
    // inteira (ou termina em pontuação forte), o que veio depois dos
    // dois-pontos não é a definição dele.
    if ($termo === '' || mb_strlen($termo) < 3 || preg_match('/[.?!]$/u', $termo)) {
        return null;
    }

    $palavras = preg_split('/\s+/u', $termo);
    $frente = count($palavras) <= 5
        ? 'O que é ' . $termo . '?'
        : 'Explique: ' . $termo;

    return fcgMontar($frente, $definicao, 'definicao');
}

/**
 * 3) "Pergunta?" + a linha de baixo como resposta.
 * Quem já escreveu o resumo em forma de pergunta fez metade do
 * trabalho de um flashcard sem saber.
 */
function fcgDePergunta(string $linha, array $linhas, int $i): ?array {
    if (!str_ends_with($linha, '?')) {
        return null;
    }

    // A resposta é a próxima linha com conteúdo — e ela não pode ser
    // outra pergunta, senão o cartão responderia com uma dúvida.
    for ($j = $i + 1; $j < count($linhas) && $j <= $i + 2; $j++) {
        $possivel = $linhas[$j];
        if ($possivel === '') {
            continue;
        }
        if (str_ends_with($possivel, '?')) {
            return null;
        }
        return fcgMontar($linha, $possivel, 'pergunta');
    }

    return null;
}

/** Corta no limite da coluna e recusa o que ficou curto demais. */
function fcgMontar(string $frente, string $verso, string $origem): ?array {
    $frente = mb_substr(trim($frente), 0, FC_MAX_TEXTO);
    $verso  = mb_substr(trim($verso),  0, FC_MAX_TEXTO);

    if (mb_strlen($frente) < 5 || mb_strlen($verso) < 2) {
        return null;
    }

    return ['frente' => $frente, 'verso' => $verso, 'origem' => $origem];
}
