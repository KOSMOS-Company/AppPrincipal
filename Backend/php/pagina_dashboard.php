<?php
// ============================================================
//  KOSMOS — Porteiro e dados das páginas do dashboard
//  Arquivo: backend/php/pagina_dashboard.php
//
//  Toda página do dashboard começa com:
//      require_once __DIR__ . '/../../../Backend/php/pagina_dashboard.php';
//
//  O que este arquivo faz ANTES de a página existir:
//   1. exige sessão válida — sem ela, redireciona para o login
//      (antes isso era feito em JS, então a página inteira chegava
//      ao navegador e só depois ele era expulso);
//   2. manda a conta do Google que ainda não tem senha para a tela
//      de criação de senha;
//   3. carrega nome, e-mail, sequência, avatar e preferências, para
//      a página já sair pronta do servidor — sem o "pisca" de valores
//      falsos que a versão em JS tinha.
//
//  Depois do require, a página tem à disposição:
//      $USUARIO  (id, nome, primeiro, inicial, email, criado_em,
//                 ultimo_acesso, sequencia, tem_senha, tem_google,
//                 decks, cartoes, resumos)
//      $PREF     (avatar_cor, avatar_url, avatar_pos_x, avatar_pos_y,
//                 pomo_foco, pomo_pausa, pomo_pausa_longa,
//                 meta_diaria, materias[], notif_lembrete, notif_resumo)
//      $PAGINA   (nome do arquivo atual, ex.: "resumos.php")
//      hesc()    (atalho de htmlspecialchars para imprimir com segurança)
// ============================================================

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/avatar_util.php';
require_once __DIR__ . '/materias.php';
require_once __DIR__ . '/datas.php';

/** Escapa texto para imprimir no HTML. */
function hesc(?string $texto): string {
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

/** Manda para outra página e encerra (usado pelo porteiro). */
function irPara(string $destino): void {
    header('Location: ' . $destino);
    exit;
}

$PAGINA = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');

// ---------- 1) Tem sessão? ----------
$sessao = usuarioLogado();
if ($sessao === null) {
    irPara('../login/index.html');
}

// Valores padrão: se o banco cair, a página ainda abre com o mínimo
$USUARIO = [
    'id'            => (int) $sessao['id'],
    'nome'          => $sessao['nome'],
    'email'         => '',
    'criado_em'     => null,
    'ultimo_acesso' => null,
    'sequencia'     => 0,
    'tem_senha'     => true,
    'tem_google'    => false,
    'decks'         => 0,
    'cartoes'       => 0,
    'resumos'       => 0,
];

$PREF = [
    'avatar_cor'       => 'roxo',
    'avatar_url'       => null,
    'avatar_pos_x'     => 50,
    'avatar_pos_y'     => 50,
    'pomo_foco'        => 25,
    'pomo_pausa'       => 5,
    'pomo_pausa_longa' => 15,
    'meta_diaria'      => 60,
    'materias'         => [],
    'notif_lembrete'   => false,
    'notif_resumo'     => false,
];

$ERRO_BANCO = false;

try {
    $pdo = conectar();

    // ---------- 2) A sessão ainda vale? ----------
    // ("sair de todos os dispositivos" feito em outro lugar derruba esta)
    if (!sessaoAindaValida($pdo, $USUARIO['id'])) {
        encerrarSessao();
        irPara('../login/index.html');
    }

    $stmt = $pdo->prepare('SELECT nome, email, criado_em, ultimo_acesso, sequencia,
                                  senha_hash, google_id
                           FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->execute([$USUARIO['id']]);
    $dados = $stmt->fetch();

    if (!$dados) {
        // usuário apagado enquanto a sessão existia
        encerrarSessao();
        irPara('../login/index.html');
    }

    // ---------- 3) Conta Google sem senha vai criar a senha ----------
    if (empty($dados['senha_hash'])) {
        irPara('../login/criar-senha.html');
    }

    $USUARIO['nome']          = $dados['nome'];
    $USUARIO['email']         = $dados['email'];
    $USUARIO['criado_em']     = $dados['criado_em'];
    $USUARIO['ultimo_acesso'] = $dados['ultimo_acesso'];
    $USUARIO['tem_senha']     = true;
    $USUARIO['tem_google']    = !empty($dados['google_id']);

    // sequência de dias (também registra o acesso de hoje)
    $USUARIO['sequencia'] = registrarAcesso($pdo, $USUARIO['id']);

    // ---------- 4) Preferências ----------
    $stmt = $pdo->prepare('SELECT avatar_cor, avatar_arquivo, avatar_pos_x, avatar_pos_y,
                                  pomo_foco, pomo_pausa, pomo_pausa_longa, meta_diaria,
                                  materias, notif_lembrete, notif_resumo
                           FROM usuario_preferencias WHERE usuario_id = ? LIMIT 1');
    $stmt->execute([$USUARIO['id']]);
    $p = $stmt->fetch();

    if (!$p) {
        // primeira visita: cria a linha com os padrões
        $pdo->prepare('INSERT INTO usuario_preferencias (usuario_id) VALUES (?)')
            ->execute([$USUARIO['id']]);
    } else {
        $PREF['avatar_cor']       = $p['avatar_cor'];
        $PREF['avatar_url']       = urlAvatar($p['avatar_arquivo']);
        $PREF['avatar_pos_x']     = (int) $p['avatar_pos_x'];
        $PREF['avatar_pos_y']     = (int) $p['avatar_pos_y'];
        $PREF['pomo_foco']        = (int) $p['pomo_foco'];
        $PREF['pomo_pausa']       = (int) $p['pomo_pausa'];
        $PREF['pomo_pausa_longa'] = (int) $p['pomo_pausa_longa'];
        $PREF['meta_diaria']      = (int) $p['meta_diaria'];
        $PREF['materias']         = ($p['materias'] === null || $p['materias'] === '')
                                        ? []
                                        : explode(',', $p['materias']);
        $PREF['notif_lembrete']   = (bool) $p['notif_lembrete'];
        $PREF['notif_resumo']     = (bool) $p['notif_resumo'];
    }

    // ---------- 5) Números do que o usuário já produziu ----------
    $stmt = $pdo->prepare('SELECT COUNT(*) AS n FROM flashcard_decks WHERE usuario_id = ?');
    $stmt->execute([$USUARIO['id']]);
    $USUARIO['decks'] = (int) ($stmt->fetch()['n'] ?? 0);

    $stmt = $pdo->prepare('SELECT COUNT(c.id) AS n
                           FROM flashcard_cartoes c
                           JOIN flashcard_decks d ON d.id = c.deck_id
                           WHERE d.usuario_id = ?');
    $stmt->execute([$USUARIO['id']]);
    $USUARIO['cartoes'] = (int) ($stmt->fetch()['n'] ?? 0);

    $stmt = $pdo->prepare('SELECT COUNT(*) AS n FROM resumos WHERE usuario_id = ?');
    $stmt->execute([$USUARIO['id']]);
    $USUARIO['resumos'] = (int) ($stmt->fetch()['n'] ?? 0);

    // daqui para frente não escrevemos mais na sessão: solta o lock
    liberarSessao();
} catch (PDOException $e) {
    // Banco fora do ar: a página abre com os padrões acima e avisa.
    $ERRO_BANCO = true;
}

/** Primeiro nome (títulos grandes quebram com o nome completo). */
$USUARIO['primeiro'] = trim(explode(' ', trim($USUARIO['nome']))[0] ?? '');
/** Inicial para o avatar. */
$USUARIO['inicial'] = mb_strtoupper(mb_substr(trim($USUARIO['nome']), 0, 1)) ?: '?';
