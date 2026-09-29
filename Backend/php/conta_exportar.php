<?php
// ============================================================
//  KOSMOS — Exportar meus dados (LGPD)
//  Arquivo: backend/php/conta_exportar.php
//  GET (protegido): devolve tudo o que o app guarda sobre o
//  usuário em um arquivo JSON para download. Nada de senha:
//  o hash NUNCA sai daqui, só a informação de que existe.
// ============================================================

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/sessao.php';

$usuario = exigirLogin();
$id      = (int) $usuario['id'];

try {
    $pdo = conectar();
    liberarSessao();   // exportar só lê dados

    // ---------- Perfil ----------
    $stmt = $pdo->prepare('SELECT nome, email, criado_em, ultimo_acesso, sequencia,
                                  senha_hash, google_id
                           FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $u = $stmt->fetch();

    if (!$u) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(404);
        echo json_encode(['ok' => false, 'msg' => 'Usuário não encontrado.']);
        exit;
    }

    // ---------- Preferências ----------
    $stmt = $pdo->prepare('SELECT avatar_cor, avatar_arquivo, avatar_pos_x, avatar_pos_y,
                                  pomo_foco, pomo_pausa, pomo_pausa_longa,
                                  meta_diaria, materias, notif_lembrete, notif_resumo,
                                  atualizado_em
                           FROM usuario_preferencias WHERE usuario_id = ? LIMIT 1');
    $stmt->execute([$id]);
    $pref = $stmt->fetch() ?: null;

    // ---------- Flashcards (decks + cartões) ----------
    $stmt = $pdo->prepare('SELECT id, nome, materia, criado_em
                           FROM flashcard_decks WHERE usuario_id = ? ORDER BY id');
    $stmt->execute([$id]);
    $decks = $stmt->fetchAll();

    foreach ($decks as &$deck) {
        $c = $pdo->prepare('SELECT frente, verso, ordem, criado_em, revisoes, acertos,
                                   erros, ultima_revisao, ultimo_resultado
                            FROM flashcard_cartoes WHERE deck_id = ? ORDER BY ordem, id');
        $c->execute([$deck['id']]);
        $deck['cartoes'] = $c->fetchAll();
    }
    unset($deck);

    // Consulta de uma seção da exportação. Tabelas de features mais novas
    // podem não existir num banco que ainda não rodou a migração: aí a
    // seção sai vazia em vez de derrubar a exportação inteira.
    $secao = function (string $sql, array $params) use ($pdo): array {
        try {
            $st = $pdo->prepare($sql);
            $st->execute($params);
            return $st->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    };

    // Colunas JSON guardadas como texto saem como objeto no arquivo,
    // para a pessoa conseguir ler (se não for JSON válido, vai como está)
    $decodificar = function (array $linhas, string $coluna): array {
        foreach ($linhas as &$l) {
            if (isset($l[$coluna]) && is_string($l[$coluna])) {
                $v = json_decode($l[$coluna], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $l[$coluna] = $v;
                }
            }
        }
        unset($l);
        return $linhas;
    };

    // ---------- Resumos e cadernos ----------
    $cadernos = $secao('SELECT id, nome, materia, cor, icone, descricao, ordem, criado_em
                          FROM resumo_cadernos WHERE usuario_id = ? ORDER BY ordem, id', [$id]);
    $resumos  = $secao('SELECT id, caderno_id, titulo, materia, corpo, criado_em, atualizado_em
                          FROM resumos WHERE usuario_id = ? ORDER BY id', [$id]);

    // ---------- Provas (+ tópicos) ----------
    $provas = $secao('SELECT id, titulo, materia, data, anotacoes, nota, concluida_em, criado_em
                        FROM provas WHERE usuario_id = ? ORDER BY data, id', [$id]);
    // Tópicos não têm usuario_id: o dono vem pela prova (join)
    $topicos = $secao('SELECT t.prova_id, t.texto, t.feito, t.ordem, t.criado_em
                         FROM prova_topicos t
                         JOIN provas p ON p.id = t.prova_id
                        WHERE p.usuario_id = ?
                        ORDER BY t.prova_id, t.ordem, t.id', [$id]);
    $topicosPorProva = [];
    foreach ($topicos as $t) {
        $topicosPorProva[$t['prova_id']][] = $t;
    }
    foreach ($provas as &$p) {
        $p['topicos'] = $topicosPorProva[$p['id']] ?? [];
    }
    unset($p);

    // ---------- Exercícios ----------
    $exMaterias  = $secao('SELECT id, nome, materia, cor, icone, descricao, ordem, criado_em
                             FROM exercicio_materias WHERE usuario_id = ? ORDER BY ordem, id', [$id]);
    $exercicios  = $decodificar($secao('SELECT id, materia_id, titulo, conteudo, dificuldade,
                                               criado_em, atualizado_em
                                          FROM exercicios WHERE usuario_id = ? ORDER BY id', [$id]),
                                'conteudo');
    $exRespostas = $secao('SELECT exercicio_id, questao, escolhida, acertou, dia, respondido_em
                             FROM exercicio_respostas WHERE usuario_id = ?
                            ORDER BY respondido_em, id', [$id]);

    // ---------- Estudo e progressão ----------
    $pomodoros  = $secao('SELECT minutos, materia, fim_em, dia
                            FROM pomodoro_sessoes WHERE usuario_id = ? ORDER BY fim_em', [$id]);
    $progresso  = $secao('SELECT xp_atual, nivel_atual, titulo_atual, streak_dias,
                                 ultimo_dia_ativo, atualizado_em
                            FROM usuario_progresso WHERE usuario_id = ? LIMIT 1', [$id]);
    $historico  = $decodificar($secao('SELECT quantidade_xp, origem_acao, detalhes_json, criado_em
                                         FROM historico_xp WHERE usuario_id = ?
                                        ORDER BY criado_em, id', [$id]),
                               'detalhes_json');
    $conquistas = $secao('SELECT c.slug, c.nome, uc.desbloqueado_em
                            FROM usuario_conquistas uc
                            JOIN conquistas c ON c.id = uc.conquista_id
                           WHERE uc.usuario_id = ?
                           ORDER BY uc.desbloqueado_em', [$id]);

    // Data da exportação pelo relógio do MySQL (o PHP roda em outro fuso)
    $agora = $pdo->query('SELECT NOW() AS agora, CURDATE() AS hoje')->fetch();

    $dados = [
        'exportado_em' => $agora['agora'],
        'aplicativo'   => 'Kosmos',
        'perfil' => [
            'nome'          => $u['nome'],
            'email'         => $u['email'],
            'criado_em'     => $u['criado_em'],
            'ultimo_acesso' => $u['ultimo_acesso'],
            'sequencia'     => (int) $u['sequencia'],
            'tem_senha'     => !empty($u['senha_hash']),   // o hash não é exportado
            'conta_google'  => !empty($u['google_id']),
        ],
        'preferencias' => $pref,
        'flashcards'   => $decks,
        'resumos' => [
            'cadernos' => $cadernos,
            'resumos'  => $resumos,
        ],
        'provas' => $provas,
        'exercicios' => [
            'materias'   => $exMaterias,
            'listas'     => $exercicios,
            'respostas'  => $exRespostas,
        ],
        'pomodoro_sessoes' => $pomodoros,
        'progresso' => [
            'resumo'       => $progresso[0] ?? null,
            'historico_xp' => $historico,
            'conquistas'   => $conquistas,
        ],
    ];

    $nomeArquivo = 'kosmos-meus-dados-' . $agora['hoje'] . '.json';

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
    echo json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (PDOException $e) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Não foi possível exportar seus dados.']);
}
