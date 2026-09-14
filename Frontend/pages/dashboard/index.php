<?php
// Porteiro + dados desta página (sem sessão, redireciona antes de
// mandar qualquer HTML). Deixa $USUARIO, $PREF e $PAGINA prontos.
require_once __DIR__ . '/../../../Backend/php/pagina_dashboard.php';
?>
﻿<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../shared/favicon.svg">
    <title>Kosmos — Dashboard</title>
    <link rel="stylesheet" href="./css/dashboard.css">
    <link rel="stylesheet" href="../shared/cosmos.css">
    <link rel="stylesheet" href="./css/pomodoro-aviso.css">
    <link rel="stylesheet" href="./css/cursor.css">
    <link rel="stylesheet" href="../shared/logo.css">
    <link rel="stylesheet" href="../shared/mascote.css">
    <link rel="stylesheet" href="./css/onboarding.css">
    <link rel="stylesheet" href="./css/intro.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
    <script>
        // Mostra o splash imediatamente (sem flash) se o usuário acabou de logar
        if (sessionStorage.getItem("kosmos_intro")) {
            document.documentElement.classList.add("com-intro");
        }
    </script>
</head>
<body>

    <!-- Splash de boas-vindas cinematográfico (mini desenho animado de introdução) -->
    <div class="intro" id="intro" aria-hidden="true">
        <canvas class="intro__ceu" id="introCeu"></canvas>
        <div class="intro__glow"></div>
        <div class="intro__portal"></div>

        <div class="intro__cenario">
            <!-- Ator Mascote 3D em ação animada -->
            <div class="intro__ator-mascote" id="introMascoteHero" title="Clique no mascote!">
                <div class="mascote" id="introMascote" data-mascote aria-hidden="true"></div>

                <!-- Ícones das ferramentas orbitando ao redor do mascote -->
                <div class="intro__orbita-item intro__orbita-item--1" title="Pomodoro">⏱️</div>
                <div class="intro__orbita-item intro__orbita-item--2" title="Flashcards">🎴</div>
                <div class="intro__orbita-item intro__orbita-item--3" title="Resumos">📝</div>

                <!-- Flash reluzente nos óculos -->
                <div class="intro__flash-oculos" id="introFlashOculos">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 0 Q13 11 24 12 Q13 13 12 24 Q11 13 0 12 Q11 11 12 0 Z"/>
                    </svg>
                </div>

                <!-- Balão de fala alegre do mascote -->
                <div class="intro__balao-fala" id="introBalao">Seu espaço de estudos tá pronto! ✦</div>
            </div>

            <!-- LOGO OFICIAL KOSMOS (Exata da 2ª foto: K maiúsculo + planeta no 1º 'o' + smos minúsculo) -->
            <div class="intro__marca-oficial">
                <div class="intro__logo-wrapper">
                    <span class="klogo" role="img" aria-label="Kosmos">K<i class="klogo__o"></i>smos</span>
                </div>
                <p class="intro__tag" id="introTag" data-texto="Estudar nunca foi tão envolvente.">&nbsp;</p>
                <p class="intro__ola" id="introOla"></p>
            </div>
        </div>

        <p class="intro__dica">toque no céu para criar supernovas ✦</p>
        <button class="intro__pular" id="introPular" type="button">
            Pular intro
            <span class="intro__pular-barra" id="introBarra"></span>
        </button>
    </div>

    <?php include __DIR__ . '/partes/fundo.php'; ?>

    <!-- Layout Container -->
    <div class="contGeral">

        <!-- Sidebar (Lateral) -->
        <?php include __DIR__ . '/partes/sidebar.php'; ?>

        <!-- Main Content (Meio) -->
        <main class="contMeio pagina-inicio">

            <!-- ==========================================================
                 ABERTURA

                 Era um hero de landing page dentro do app: pílula, título
                 gigante, parágrafo de venda, dois botões e quatro números
                 soltos — quase uma tela inteira antes de qualquer coisa
                 útil. Mas quem abre o Início já se cadastrou; não precisa
                 ser convencido, precisa saber como está e continuar.

                 Agora é uma linha só, no formato da prévia que está na
                 landing page: saudação à esquerda, sequência à direita.
                 ========================================================== -->
            <header class="ini-hero">
                <div class="ini-hero__texto">
                    <span class="ini-data" id="iniData">Hoje</span>
                    <h1 class="ini-hero__titulo">
                        <span id="iniSaudacao">Bem-vindo</span>,
                        <span class="ini-hero__nome"><?= hesc($USUARIO['primeiro']) ?></span>
                    </h1>
                    <p class="ini-hero__desc">
                        <?= $USUARIO['sequencia'] > 0
                            ? 'Sua sequência está de pé — continue de onde parou.'
                            : 'Uma sessão de foco hoje já começa sua sequência.' ?>
                    </p>
                </div>

                <!-- O selo da sequência. `.ini-stat` continua aqui porque é
                     por ele que o js/inicio.js apaga o número quando zera
                     (`el.closest('.ini-stat')`) — a classe é contrato, não
                     enfeite. -->
                <div class="ini-selo ini-stat<?= $USUARIO['sequencia'] === 0 ? ' ini-stat--vazio' : '' ?>">
                    <!-- Nada de <span> aqui dentro, de propósito. Este bloco
                         precisa da classe `.ini-stat` porque é por ela que o
                         js/inicio.js apaga o número quando a sequência zera
                         (`el.closest('.ini-stat')`) — é contrato, não estilo.
                         Só que junto com a classe vêm as regras genéricas
                         `.ini-stat span { display:block; text-transform:
                         uppercase; margin-top:5px }`, escritas para os
                         números soltos do Flashcards. Elas acertavam a chama
                         e o rótulo e desmontavam o selo. Trocar as tags
                         resolve na raiz; brigar por especificidade só
                         adiaria o problema para o próximo que mexer. -->
                    <i class="ini-selo__icone" aria-hidden="true">🔥</i>
                    <div class="ini-selo__num">
                        <strong data-metrica="sequencia"><?= (int) $USUARIO['sequencia'] ?></strong>
                        <i class="ini-selo__txt"><?= $USUARIO['sequencia'] === 1 ? 'dia seguido' : 'dias seguidos' ?></i>
                    </div>
                </div>
            </header>

            <!-- ==========================================================
                 REVISAR HOJE

                 A fila de revisão atravessando todos os baralhos. É o
                 motivo de voltar amanhã, então fica acima de tudo.

                 Nasce escondida e só aparece quando há o que revisar
                 (js/inicio.js). Um aviso permanente dizendo "0 cartões"
                 vira ruído, e ruído a pessoa aprende a ignorar — junto
                 com o aviso de verdade quando ele chegar.
                 ========================================================== -->
            <a class="ini-revisar" id="iniRevisar" href="revisar.php" hidden>
                <span class="ini-revisar__ico" aria-hidden="true">🃏</span>
                <span class="ini-revisar__txt">
                    <strong data-revisar-n>0</strong>
                    <span data-revisar-txt>cartões esperando revisão</span>
                </span>
                <span class="ini-revisar__cta" aria-hidden="true">Revisar agora →</span>
            </a>

            <!-- ==========================================================
                 ESTA SEMANA — o painel principal

                 É o que a prévia da landing page mostra em primeiro plano,
                 e é a única coisa nesta tela que responde "como eu estou
                 indo". Por isso subiu: antes estava no fim da página.
                 ========================================================== -->
            <section class="ini-painel painel">
                <div class="ini-painel__cabeca">
                    <h2>Esta semana</h2>
                    <span class="ini-painel__nota" data-total-semana>últimos 7 dias</span>
                </div>

                <!-- as colunas são desenhadas pelo inicio.js (CSS puro, sem biblioteca) -->
                <div class="ini-chart" id="iniChart" role="img"
                     aria-label="Minutos estudados nos últimos sete dias"></div>
                <p class="ini-chart__aviso" id="iniChartAviso">
                    Ainda não há sessões registradas. Assim que você estudar com o
                    Pomodoro, seus dias aparecem aqui.
                </p>

                <!-- A meta do dia. A coluna `meta_diaria` existe em
                     usuario_preferencias desde agosto e NENHUM código a
                     lia — salvar um número que nada usa é pior do que não
                     ter o campo, porque promete um ajuste que não ajusta
                     nada. Escondida até os dados chegarem. -->
                <div class="ini-meta" id="iniMeta" role="group" hidden>
                    <progress data-meta-barra value="0" max="60"></progress>
                    <span class="ini-meta__txt" data-meta-texto></span>
                </div>

                <!-- Os outros números vivem AQUI, no rodapé do painel, e não
                     mais soltos no topo da página: eles são contexto do
                     "como estou indo", não manchete. -->
                <div class="ini-numeros">
                    <div class="ini-stat<?= $USUARIO['resumos'] === 0 ? ' ini-stat--vazio' : '' ?>">
                        <strong data-metrica="resumos"><?= $USUARIO['resumos'] > 0 ? (int) $USUARIO['resumos'] : '—' ?></strong>
                        <span><?= $USUARIO['resumos'] === 1 ? 'resumo' : 'resumos' ?></span>
                    </div>
                    <div class="ini-stat<?= $USUARIO['cartoes'] === 0 ? ' ini-stat--vazio' : '' ?>">
                        <strong data-metrica="flashcards"><?= $USUARIO['cartoes'] > 0 ? (int) $USUARIO['cartoes'] : '—' ?></strong>
                        <span>flashcards</span>
                    </div>
                    <div class="ini-stat ini-stat--vazio">
                        <strong data-metrica="exercicios">—</strong>
                        <span>exercícios</span>
                    </div>
                </div>
            </section>

            <!-- ==========================================================
                 ATALHOS

                 Os mesmos quatro destinos de antes. O que saiu foi a
                 moldura de campanha em volta ("O que você quer fazer
                 agora?", com tag de seção e título grande): num app, uma
                 fileira de atalhos não precisa ser anunciada.
                 ========================================================== -->
            <section class="ini-secao">
                <h2 class="ini-titulo">Atalhos</h2>

                <div class="ini-grid">
                    <a class="ini-card" href="resumos.php">
                        <span class="ini-card__ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><rect x="4" y="4" width="16" height="16" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M8 10 H16 M8 14 H12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        </span>
                        <h3 class="ini-card__titulo">Biblioteca</h3>
                        <p class="ini-card__desc">Cadernos por matéria.</p>
                    </a>

                    <a class="ini-card" href="flashcards.php">
                        <span class="ini-card__ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><rect x="3" y="6" width="13" height="12" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M8 4 H19 a2 2 0 0 1 2 2 V16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        </span>
                        <h3 class="ini-card__titulo">Flashcards</h3>
                        <p class="ini-card__desc">Revisão por repetição.</p>
                    </a>

                    <a class="ini-card" href="exercicios.php">
                        <span class="ini-card__ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><path d="M5 7 H19 M5 12 H19 M5 17 H13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        </span>
                        <h3 class="ini-card__titulo">Exercícios <span class="ini-tag">IA</span></h3>
                        <p class="ini-card__desc">Questões geradas na hora.</p>
                    </a>

                    <a class="ini-card" href="pomodoro.php">
                        <span class="ini-card__ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="13" r="8" stroke="currentColor" stroke-width="1.8"/><path d="M12 9 L12 13 L15 15 M9 3 H15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        <h3 class="ini-card__titulo">Pomodoro</h3>
                        <p class="ini-card__desc">25 de foco, 5 de pausa.</p>
                    </a>
                </div>
            </section>

            <!-- ==========================================================
                 PRÓXIMAS PROVAS

                 Também nasce escondida: quem não cadastrou prova nenhuma
                 não precisa de uma seção vazia explicando que está vazia.
                 A contagem de dias vem pronta do servidor (DATEDIFF no
                 MySQL) — o projeto tem PHP e MySQL em fusos diferentes, e
                 data calculada no cliente erraria o dia da virada.
                 ========================================================== -->
            <section class="ini-secao" id="iniProvas">
                <div class="ini-secao__cabeca">
                    <h2 class="ini-titulo">Próximas provas</h2>
                    <button class="dash-btn dash-btn--ghost dash-btn--pequeno"
                            type="button" id="btnNovaProva">+ Prova</button>
                </div>

                <ul class="ini-provas" data-provas-lista></ul>

                <!-- Sem prova cadastrada a seção CONTINUA visível, ao
                     contrário do "Revisar hoje". A diferença: revisar
                     depende de já ter cartões, e anunciar zero seria
                     cobrança; cadastrar prova é uma ação que a pessoa pode
                     fazer agora — esconder seria esconder o recurso. -->
                <p class="ini-provas__vazio" data-provas-vazio>
                    Cadastre suas provas e o Kosmos conta os dias para você.
                </p>
            </section>

            <!-- ==========================================================
                 PRIMEIROS PASSOS
                 Mantido como estava: é a única parte da tela que ensina o
                 caminho a quem acabou de chegar.
                 ========================================================== -->
            <section class="ini-secao">
                <div class="ini-card ini-passos">
                    <div class="ini-card__cabeca">
                        <h3>Primeiros passos</h3>
                        <span class="ini-card__nota" id="iniPassosContador">0 de 3</span>
                    </div>
                    <progress id="iniPassosBarra" value="0" max="3"></progress>
                    <ul class="ini-passos__lista">
                        <li class="ini-passo" data-passo="resumo">
                            <button class="ini-passo__check" type="button" aria-pressed="false"
                                    aria-label="Marcar como feito: criar seu primeiro resumo">
                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12.5 L10 17.5 L19 7" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>
                            <a class="ini-passo__link" href="resumos.php">
                                <strong>Criar seu primeiro resumo</strong>
                                <span>Comece pela matéria que você viu hoje.</span>
                            </a>
                        </li>
                        <li class="ini-passo" data-passo="flashcards">
                            <button class="ini-passo__check" type="button" aria-pressed="false"
                                    aria-label="Marcar como feito: montar um baralho de flashcards">
                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12.5 L10 17.5 L19 7" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>
                            <a class="ini-passo__link" href="flashcards.php">
                                <strong>Montar um baralho</strong>
                                <span>Transforme o resumo em perguntas curtas.</span>
                            </a>
                        </li>
                        <li class="ini-passo" data-passo="pomodoro">
                            <button class="ini-passo__check" type="button" aria-pressed="false"
                                    aria-label="Marcar como feito: fazer uma sessão de foco">
                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12.5 L10 17.5 L19 7" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>
                            <a class="ini-passo__link" href="pomodoro.php">
                                <strong>Fazer 25 minutos de foco</strong>
                                <span>Um ciclo de Pomodoro já conta para a sequência.</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </section>
        </main>

    </div>

    <?php include __DIR__ . '/partes/modal-prova.php'; ?>
    <?php /* O confirmar() do dashboard.js precisa desta parte na página;
             sem ela ele devolve `true` na hora e a prova some sem
             perguntar nada. */ ?>
    <?php include __DIR__ . '/partes/modal-confirma.php'; ?>

    <?php if (empty($PREF['onboarding_completo'])): ?>
        <?php include __DIR__ . '/partes/modal-onboarding.php'; ?>
    <?php endif; ?>

    <script src="./js/dashboard.js"></script>
    <script src="./js/pomodoro-aviso.js"></script>
    <script src="./js/inicio.js"></script>
    <script src="./js/cursor.js"></script>
    <script src="../shared/mascote.js"></script>
    <script src="./js/intro.js"></script>
    <?php if (empty($PREF['onboarding_completo'])): ?>
        <script src="./js/onboarding.js"></script>
    <?php endif; ?>
    <!-- O céu. Os mesmos dois arquivos da landing page: o WebGL tenta
         primeiro e, se não houver placa ou o shader não compilar, ele
         desiste em silêncio e o cosmos.js (Canvas 2D) assume — por isso
         esta ordem importa. Ver partes/fundo.php. -->
    <script src="../shared/cosmos-gl.js"></script>
    <script src="../shared/cosmos.js"></script>
</body>
</html>
