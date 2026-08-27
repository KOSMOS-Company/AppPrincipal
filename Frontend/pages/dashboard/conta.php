<?php
// Porteiro + dados desta página (sem sessão, redireciona antes de
// mandar qualquer HTML). Deixa $USUARIO, $PREF e $PAGINA prontos.
require_once __DIR__ . '/../../../Backend/php/pagina_dashboard.php';

/* Datas em pt-br, montadas pelos números para não escorregar de dia
   (o projeto tem PHP e MySQL em fusos diferentes). */
function dataBonita(?string $sql): string {
    if (!$sql) return '—';
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $sql, $p)) return '—';
    $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
              'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    return ((int) $p[3]) . ' de ' . $meses[((int) $p[2]) - 1] . ' de ' . $p[1];
}

// avatar grande: cor, e a foto no enquadramento salvo (se existir)
$avatarClasse = 'conta-avatar avatar-cor--' . hesc($PREF['avatar_cor']);
$avatarStyle  = '';
if (!empty($PREF['avatar_url'])) {
    $avatarClasse .= ' avatar--foto';
    $avatarStyle   = 'background-image:url(&quot;' . hesc($PREF['avatar_url']) . '&quot;);'
                   . 'background-position:' . (int) $PREF['avatar_pos_x'] . '% '
                   . (int) $PREF['avatar_pos_y'] . '%;';
}
$temFoto = !empty($PREF['avatar_url']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../shared/favicon.svg">
    <title>Kosmos — Conta</title>
    <link rel="stylesheet" href="./css/dashboard.css">
    <link rel="stylesheet" href="./css/conta.css">
    <link rel="stylesheet" href="./css/cursor.css">
    <link rel="stylesheet" href="../shared/logo.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
</head>
<body>

    <div class="bg">
        <div class="bg__orb bg__orb--1"></div>
        <div class="bg__orb bg__orb--2"></div>
        <div class="bg__grid"></div>
    </div>

    <div class="contGeral">

        <!-- Sidebar -->
        <?php include __DIR__ . '/partes/sidebar.php'; ?>

        <!-- Main -->
        <main class="contMeio pagina-conta"
              data-tem-senha="<?= $USUARIO['tem_senha'] ? '1' : '0' ?>"
              data-tem-google="<?= $USUARIO['tem_google'] ? '1' : '0' ?>"
              data-avatar-url="<?= hesc((string) $PREF['avatar_url']) ?>"
              data-avatar-pos-x="<?= (int) $PREF['avatar_pos_x'] ?>"
              data-avatar-pos-y="<?= (int) $PREF['avatar_pos_y'] ?>">
            <header class="contCabeca">
                <div class="contCabeca__texto">
                    <span class="section-tag">Sua conta</span>
                    <h1>Minha <span class="h-nome">Conta</span></h1>
                    <p>Perfil, segurança, preferências de estudo e privacidade.</p>
                </div>
            </header>

            <!-- some assim que a pessoa salva; o navegador também avisa
                 se ela tentar sair com algo pendente -->
            <p class="conta-pendente" id="avisoPendente" hidden>
                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 3.5 18 16.5H2L10 3.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M10 8.5v3M10 13.6v.4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                Você tem alterações que ainda não foram salvas.
            </p>

            <div class="conta-layout">

                <!-- ==========================================================
                     Sub-navegação: cada item mostra uma seção (o conta.js
                     troca o painel e guarda a escolha no #hash da URL)
                     ========================================================== -->
                <nav class="conta-nav" aria-label="Seções da conta">
                    <button type="button" class="conta-nav__item active" data-secao="perfil">
                        <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.8"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        Perfil
                    </button>
                    <button type="button" class="conta-nav__item" data-secao="seguranca">
                        <svg viewBox="0 0 24 24" fill="none"><path d="M12 3l7 3v6c0 4-3 7.5-7 9-4-1.5-7-5-7-9V6l7-3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9.5 12.5l1.8 1.8 3.4-3.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Segurança
                    </button>
                    <button type="button" class="conta-nav__item" data-secao="estudo">
                        <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="13" r="8" stroke="currentColor" stroke-width="1.8"/><path d="M12 9v4l3 2M9 3h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        Estudo
                    </button>
                    <button type="button" class="conta-nav__item" data-secao="notificacoes">
                        <svg viewBox="0 0 24 24" fill="none"><path d="M6 9a6 6 0 1 1 12 0c0 4 1.5 5.5 1.5 5.5H4.5S6 13 6 9Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M10 18a2 2 0 0 0 4 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        Notificações
                    </button>
                    <button type="button" class="conta-nav__item" data-secao="privacidade">
                        <svg viewBox="0 0 24 24" fill="none"><rect x="4" y="10" width="16" height="10" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M8 10V7a4 4 0 0 1 8 0v3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        Privacidade
                    </button>
                    <button type="button" class="conta-nav__item" data-secao="sobre">
                        <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.8"/><path d="M12 8v.5M12 11v5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
                        Sobre
                    </button>
                </nav>

                <div class="conta-corpo">

                    <!-- ============ PERFIL ============ -->
                    <section class="conta-secao" data-painel="perfil">
                        <div class="ini-card conta-cartao">
                            <div class="conta-perfil__topo">
                                <div class="<?= $avatarClasse ?>" id="contaAvatar" style="<?= $avatarStyle ?>"><?= hesc($USUARIO['inicial']) ?></div>
                                <div class="conta-perfil__info">
                                    <h3 id="contaNome"><?= hesc($USUARIO['nome']) ?></h3>
                                    <p id="contaEmail"><?= hesc($USUARIO['email']) ?></p>
                                    <span class="conta-membro" id="contaMembro">Membro desde <?= dataBonita($USUARIO['criado_em']) ?></span>
                                </div>
                            </div>

                            <!-- Foto de perfil: o conta.js reduz a imagem no
                                 navegador antes de enviar para o servidor -->
                            <div class="conta-foto">
                                <span class="conta-cores__rotulo">Foto de perfil</span>
                                <div class="conta-foto__acoes">
                                    <input type="file" id="inputFoto" accept="image/jpeg,image/png,image/webp" hidden>
                                    <button type="button" class="dash-btn dash-btn--ghost" id="btnFoto">
                                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 14V5m0 0L6.5 8.5M10 5l3.5 3.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 16.5h12" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                                        Enviar foto
                                    </button>
                                    <button type="button" class="dash-btn dash-btn--ghost" id="btnAjustarFoto"<?= $temFoto ? '' : ' hidden' ?>>
                                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 10a6 6 0 1 0 12 0 6 6 0 0 0-12 0Z" stroke="currentColor" stroke-width="1.6"/><path d="M8 8.5h4M8 11.5h4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                                        Ajustar posição
                                    </button>
                                    <button type="button" class="dash-btn dash-btn--outline" id="btnRemoverFoto"<?= $temFoto ? '' : ' hidden' ?>>
                                        Remover foto
                                    </button>
                                    <span class="campo__dica">JPG, PNG ou WEBP, até 2 MB.</span>
                                </div>

                                <!-- o enquadramento é escolhido arrastando a foto no
                                     editor (modalFoto, no fim desta página) -->

                                <div class="msg" id="msgFoto" hidden></div>
                            </div>

                            <div class="conta-cores">
                                <span class="conta-cores__rotulo" id="rotuloCores"><?= $temFoto ? 'Cor do avatar (usada quando não há foto)' : 'Cor do seu avatar' ?></span>
                                <!-- lista fixa (a mesma whitelist do conta_preferencias.php):
                                     já vem no HTML para dar para clicar antes do servidor
                                     responder. O conta.js só marca a cor ativa. -->
                                <div class="conta-cores__lista" id="listaCores">
                                    <button type="button" class="conta-cor avatar-cor--roxo<?= $PREF['avatar_cor'] === 'roxo' ? ' ativa' : '' ?>" data-cor="roxo" title="Usar a cor roxo" aria-label="Usar a cor roxo"></button>
                                    <button type="button" class="conta-cor avatar-cor--azul<?= $PREF['avatar_cor'] === 'azul' ? ' ativa' : '' ?>" data-cor="azul" title="Usar a cor azul" aria-label="Usar a cor azul"></button>
                                    <button type="button" class="conta-cor avatar-cor--verde<?= $PREF['avatar_cor'] === 'verde' ? ' ativa' : '' ?>" data-cor="verde" title="Usar a cor verde" aria-label="Usar a cor verde"></button>
                                    <button type="button" class="conta-cor avatar-cor--laranja<?= $PREF['avatar_cor'] === 'laranja' ? ' ativa' : '' ?>" data-cor="laranja" title="Usar a cor laranja" aria-label="Usar a cor laranja"></button>
                                    <button type="button" class="conta-cor avatar-cor--rosa<?= $PREF['avatar_cor'] === 'rosa' ? ' ativa' : '' ?>" data-cor="rosa" title="Usar a cor rosa" aria-label="Usar a cor rosa"></button>
                                    <button type="button" class="conta-cor avatar-cor--ciano<?= $PREF['avatar_cor'] === 'ciano' ? ' ativa' : '' ?>" data-cor="ciano" title="Usar a cor ciano" aria-label="Usar a cor ciano"></button>
                                </div>
                            </div>
                        </div>

                        <div class="ini-card conta-cartao">
                            <h2 class="painel__titulo">Dados do perfil</h2>
                            <p class="painel__sub">Altere seu nome ou o e-mail de acesso.</p>
                            <form class="conta-form" id="formPerfil" novalidate>
                                <div class="conta-dupla">
                                    <div class="campo">
                                        <label for="nome">Nome completo</label>
                                        <input id="nome" name="nome" type="text" autocomplete="name" placeholder="Seu nome completo" value="<?= hesc($USUARIO['nome']) ?>">
                                    </div>
                                    <div class="campo">
                                        <label for="email">E-mail</label>
                                        <input id="email" name="email" type="email" autocomplete="email" placeholder="seu@email.com" value="<?= hesc($USUARIO['email']) ?>">
                                    </div>
                                </div>
                                <div class="msg" id="msgPerfil" hidden></div>
                                <div class="conta-form__acoes">
                                    <button type="submit" class="dash-btn dash-btn--primary">Salvar alterações</button>
                                </div>
                            </form>
                        </div>
                    </section>

                    <!-- ============ SEGURANÇA ============ -->
                    <section class="conta-secao" data-painel="seguranca" hidden>
                        <div class="ini-card conta-cartao">
                            <h2 class="painel__titulo" id="senhaTitulo">Trocar senha</h2>
                            <p class="painel__sub" id="senhaSub">Por segurança, confirme sua senha atual antes de definir uma nova.</p>
                            <form class="conta-form" id="formSenha" novalidate>
                                <div class="campo" id="campoSenhaAtual">
                                    <label for="senhaAtual">Senha atual</label>
                                    <input id="senhaAtual" name="senha_atual" type="password" autocomplete="current-password" placeholder="••••••••">
                                </div>
                                <div class="conta-dupla">
                                    <div class="campo">
                                        <label for="senhaNova">Nova senha</label>
                                        <input id="senhaNova" name="senha_nova" type="password" autocomplete="new-password" placeholder="Mín. 8 caracteres">
                                    </div>
                                    <div class="campo">
                                        <label for="senhaConfirma">Confirmar nova senha</label>
                                        <input id="senhaConfirma" name="senha_confirma" type="password" autocomplete="new-password" placeholder="Repita a nova senha">
                                    </div>
                                </div>
                                <div class="msg" id="msgSenha" hidden></div>
                                <div class="conta-form__acoes">
                                    <button type="submit" class="dash-btn dash-btn--primary" id="btnSenha">Trocar senha</button>
                                </div>
                            </form>
                        </div>

                        <div class="ini-card conta-cartao">
                            <h2 class="painel__titulo">Seu acesso</h2>
                            <p class="painel__sub">Um resumo de como e quando você entra no Kosmos.</p>
                            <div class="conta-info">
                                <div class="conta-info__linha">
                                    <span class="conta-info__rotulo">Método de acesso</span>
                                    <span class="conta-info__valor" id="contaProvedor"><?= $USUARIO['tem_google'] ? 'Google' : 'E-mail e senha' ?></span>
                                </div>
                                <div class="conta-info__linha">
                                    <span class="conta-info__rotulo">Último acesso</span>
                                    <span class="conta-info__valor" id="contaUltimoAcesso"><?= dataBonita($USUARIO['ultimo_acesso']) ?></span>
                                </div>
                                <div class="conta-info__linha">
                                    <span class="conta-info__rotulo">Sequência de dias</span>
                                    <span class="conta-info__valor"><strong id="contaSequencia"><?= (int) $USUARIO['sequencia'] ?> <?= $USUARIO['sequencia'] === 1 ? 'dia' : 'dias' ?></strong></span>
                                </div>
                                <div class="conta-info__linha">
                                    <span class="conta-info__rotulo">Resumos escritos</span>
                                    <span class="conta-info__valor"><strong id="contaResumos"><?= (int) $USUARIO['resumos'] ?></strong></span>
                                </div>
                                <div class="conta-info__linha">
                                    <span class="conta-info__rotulo">Baralhos e cartões</span>
                                    <span class="conta-info__valor"><strong id="contaDecks"><?= (int) $USUARIO['decks'] ?></strong> baralhos · <strong id="contaCartoes"><?= (int) $USUARIO['cartoes'] ?></strong> cartões</span>
                                </div>
                            </div>
                        </div>

                        <div class="ini-card conta-cartao">
                            <h2 class="painel__titulo">Sessões abertas</h2>
                            <p class="painel__sub">
                                Usou o Kosmos em um computador que não é seu? Encerre o acesso nos
                                outros aparelhos — este continua conectado.
                            </p>
                            <div class="conta-acoes">
                                <button type="button" class="dash-btn dash-btn--outline" id="btnSessoes">
                                    Sair de todos os outros dispositivos
                                </button>
                                <div class="msg" id="msgSessoes" hidden></div>
                            </div>
                        </div>

                        <div class="ini-card conta-cartao painel--sair">
                            <div>
                                <h2 class="painel__titulo">Sair da conta</h2>
                                <p class="painel__sub">Encerra sua sessão neste dispositivo.</p>
                            </div>
                            <button type="button" class="dash-btn dash-btn--danger" id="btnSair">
                                <svg viewBox="0 0 20 20" fill="none"><path d="M13 4h2a1 1 0 011 1v10a1 1 0 01-1 1h-2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M9 13l3-3-3-3M12 10H3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                Sair
                            </button>
                        </div>
                    </section>

                    <!-- ============ ESTUDO ============ -->
                    <section class="conta-secao" data-painel="estudo" hidden>
                        <div class="ini-card conta-cartao">
                            <h2 class="painel__titulo">Seu Pomodoro</h2>
                            <p class="painel__sub">Estes tempos são usados na aba Pomodoro.</p>
                            <div class="conta-dupla">
                                <div class="campo">
                                    <label for="pomoFoco">Foco (min)</label>
                                    <input id="pomoFoco" type="number" min="5" max="90" step="1" placeholder="25" value="<?= (int) $PREF['pomo_foco'] ?>">
                                    <span class="campo__dica">entre 5 e 90</span>
                                </div>
                                <div class="campo">
                                    <label for="pomoPausa">Pausa curta (min)</label>
                                    <input id="pomoPausa" type="number" min="1" max="30" step="1" placeholder="5" value="<?= (int) $PREF['pomo_pausa'] ?>">
                                    <span class="campo__dica">entre 1 e 30</span>
                                </div>
                                <div class="campo">
                                    <label for="pomoPausaLonga">Pausa longa (min)</label>
                                    <input id="pomoPausaLonga" type="number" min="5" max="60" step="1" placeholder="15" value="<?= (int) $PREF['pomo_pausa_longa'] ?>">
                                    <span class="campo__dica">entre 5 e 60</span>
                                </div>
                            </div>
                        </div>

                        <div class="ini-card conta-cartao">
                            <h2 class="painel__titulo">Meta diária</h2>
                            <p class="painel__sub">Quantos minutos por dia você quer estudar.</p>
                            <div class="campo" style="max-width:220px">
                                <label for="metaDiaria">Minutos por dia</label>
                                <input id="metaDiaria" type="number" min="10" max="600" step="5" placeholder="60" value="<?= (int) $PREF['meta_diaria'] ?>">
                                <span class="campo__dica">entre 10 e 600</span>
                            </div>
                        </div>

                        <div class="ini-card conta-cartao">
                            <h2 class="painel__titulo">Matérias que você estuda</h2>
                            <p class="painel__sub">Escolha as suas — elas aparecem primeiro ao criar resumos, baralhos e exercícios.</p>
                            <!-- os chips são criados pelo conta.js com a lista do backend -->
                            <div class="chips" id="chipsMaterias">
<?php foreach (MATERIAS_KOSMOS as $materia):
    $marcada = in_array($materia, $PREF['materias'], true); ?>
                                <button type="button" class="chip<?= $marcada ? ' active' : '' ?>" aria-pressed="<?= $marcada ? 'true' : 'false' ?>"><?= hesc($materia) ?></button>
<?php endforeach; ?>
                            </div>
                        </div>

                        <div class="conta-acoes">
                            <button type="button" class="dash-btn dash-btn--primary" id="btnSalvarEstudo">
                                Salvar preferências
                            </button>
                            <div class="msg" id="msgEstudo" hidden></div>
                        </div>
                    </section>

                    <!-- ============ NOTIFICAÇÕES ============ -->
                    <section class="conta-secao" data-painel="notificacoes" hidden>
                        <div class="ini-card conta-cartao">
                            <h2 class="painel__titulo">E-mails do Kosmos</h2>
                            <p class="painel__sub">Escolha o que você quer receber. Salva na hora.</p>

                            <div class="conta-toggle">
                                <div class="conta-toggle__texto">
                                    <strong>Lembrete diário de estudo</strong>
                                    <span>Um empurrãozinho no fim do dia se você ainda não estudou.</span>
                                </div>
                                <button type="button" class="switch" id="swLembrete" role="switch" aria-checked="<?= $PREF['notif_lembrete'] ? 'true' : 'false' ?>"
                                        aria-label="Lembrete diário de estudo"></button>
                            </div>

                            <div class="conta-toggle">
                                <div class="conta-toggle__texto">
                                    <strong>Resumo semanal</strong>
                                    <span>Quanto você estudou na semana e o que revisar na próxima.</span>
                                </div>
                                <button type="button" class="switch" id="swResumo" role="switch" aria-checked="<?= $PREF['notif_resumo'] ? 'true' : 'false' ?>"
                                        aria-label="Resumo semanal por e-mail"></button>
                            </div>

                            <div class="msg" id="msgNotif" hidden></div>
                            <p class="conta-nota">
                                Sua escolha já fica salva. O envio usa o mesmo serviço de e-mail da
                                recuperação de senha e ainda está sendo ligado — quando estiver pronto,
                                nada muda aqui.
                            </p>
                        </div>
                    </section>

                    <!-- ============ PRIVACIDADE ============ -->
                    <section class="conta-secao" data-painel="privacidade" hidden>
                        <div class="ini-card conta-cartao">
                            <h2 class="painel__titulo">Baixar meus dados</h2>
                            <p class="painel__sub">
                                Um arquivo JSON com seu perfil, suas preferências e seus baralhos de
                                flashcards. Sua senha não vai no arquivo.
                            </p>
                            <div class="conta-acoes">
                                <a class="dash-btn dash-btn--ghost" id="btnExportar"
                                   href="../../../Backend/php/conta_exportar.php">
                                    <svg viewBox="0 0 20 20" fill="none"><path d="M10 3v9m0 0l-3.5-3.5M10 12l3.5-3.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 15.5h12" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                                    Exportar meus dados
                                </a>
                            </div>
                        </div>

                        <div class="ini-card conta-cartao conta-perigo">
                            <h2 class="painel__titulo">Excluir minha conta</h2>
                            <p class="painel__sub">
                                Apaga sua conta, suas preferências e todos os seus baralhos e cartões.
                                <strong>Não tem como desfazer.</strong>
                            </p>
                            <div class="conta-acoes">
                                <button type="button" class="dash-btn dash-btn--danger" id="btnExcluir">
                                    Excluir minha conta
                                </button>
                            </div>
                        </div>
                    </section>

                    <!-- ============ SOBRE ============ -->
                    <section class="conta-secao" data-painel="sobre" hidden>
                        <div class="ini-card conta-cartao">
                            <h2 class="painel__titulo">Sobre o Kosmos</h2>
                            <p class="painel__sub">
                                Plataforma gratuita de organização de estudos, feita como Trabalho de
                                Conclusão de Curso por estudantes de T.I.
                            </p>
                            <div class="conta-info">
                                <div class="conta-info__linha">
                                    <span class="conta-info__rotulo">Versão</span>
                                    <span class="conta-info__valor">1.0 (beta)</span>
                                </div>
                                <div class="conta-info__linha">
                                    <span class="conta-info__rotulo">Termos de uso</span>
                                    <span class="conta-info__valor"><a href="../termos.html" target="_blank" rel="noopener">Ler os termos</a></span>
                                </div>
                                <div class="conta-info__linha">
                                    <span class="conta-info__rotulo">Política de privacidade</span>
                                    <span class="conta-info__valor"><a href="../politica.html" target="_blank" rel="noopener">Ler a política</a></span>
                                </div>
                                <div class="conta-info__linha">
                                    <span class="conta-info__rotulo">Falar com a gente</span>
                                    <span class="conta-info__valor"><a href="mailto:tccinfo3bemlt@gmail.com">tccinfo3bemlt@gmail.com</a></span>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

        </main>
    </div>

        <?php include __DIR__ . '/partes/modal-confirma.php'; ?>

        <!-- ==========================================================
             Editor da foto de perfil: arraste a imagem para escolher
             o que aparece dentro do círculo. O que se vê aqui é
             exatamente o que fica no avatar (mesmo background-size
             e background-position).
             ========================================================== -->
        <div class="modal" id="modalFoto" role="dialog" aria-modal="true" aria-labelledby="tituloFoto">
            <div class="modal__box modal__box--foto">
                <div class="modal__head">
                    <h3 id="tituloFoto">Ajustar a foto</h3>
                    <button class="modal__close" type="button" id="fecharEditorFoto" aria-label="Fechar">&times;</button>
                </div>

                <p class="painel__sub">Arraste a imagem para escolher a parte que aparece no seu avatar.</p>

                <div class="recorte" id="recorte" tabindex="0"
                     role="application"
                     aria-label="Área de enquadramento: arraste a imagem, ou use as setas do teclado">
                    <div class="recorte__img" id="recorteImg"></div>
                    <div class="recorte__mascara" aria-hidden="true"></div>
                </div>

                <p class="recorte__dica">Você também pode usar as setas do teclado.</p>

                <div class="modal__actions">
                    <button type="button" class="conta-enquadre__centro" id="centralizarFoto">Centralizar</button>
                    <button type="button" class="dash-btn dash-btn--outline" id="cancelarFoto">Cancelar</button>
                    <button type="button" class="dash-btn dash-btn--primary" id="salvarPosFoto">Salvar posição</button>
                </div>
            </div>
        </div>

        <!-- ==========================================================
             Confirmação da exclusão da conta
             ========================================================== -->
        <div class="modal" id="modalExcluir" role="dialog" aria-modal="true" aria-labelledby="tituloExcluir">
            <div class="modal__box">
                <div class="modal__head">
                    <h3 id="tituloExcluir">Excluir a conta?</h3>
                    <button class="modal__close" type="button" id="fecharExcluir" aria-label="Fechar">&times;</button>
                </div>

                <p class="painel__sub">
                    Isto apaga sua conta, suas preferências e todos os seus baralhos e cartões.
                    <strong>A ação é definitiva.</strong> Se quiser guardar uma cópia, baixe seus
                    dados antes.
                </p>

                <form class="modal__form" id="formExcluir" novalidate>
                    <div class="campo" id="campoSenhaExcluir">
                        <label for="senhaExcluir">Digite sua senha para confirmar</label>
                        <input id="senhaExcluir" type="password" autocomplete="current-password" placeholder="••••••••">
                    </div>
                    <div class="campo" id="campoConfirmaExcluir" hidden>
                        <label for="confirmaExcluir">Digite <strong>EXCLUIR</strong> para confirmar</label>
                        <input id="confirmaExcluir" type="text" autocomplete="off" placeholder="EXCLUIR">
                    </div>

                    <div class="msg" id="msgExcluir" hidden></div>

                    <div class="modal__actions">
                        <button type="button" class="dash-btn dash-btn--outline" id="cancelarExcluir">Cancelar</button>
                        <button type="submit" class="dash-btn dash-btn--danger" id="confirmarExcluir">Excluir para sempre</button>
                    </div>
                </form>
            </div>
        </div>

    <script src="./js/dashboard.js"></script>
    <script src="./js/conta.js"></script>
    <script src="./js/cursor.js"></script>
</body>
</html>
