<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../shared/favicon.svg">
    <title>Kosmos — Dashboard</title>
    <link rel="stylesheet" href="./css/dashboard.css">
    <link rel="stylesheet" href="./css/cursor.css">
    <link rel="stylesheet" href="../shared/logo.css">
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

    <!-- Splash de boas-vindas (toca uma vez após o login) -->
    <div class="intro" id="intro" aria-hidden="true">
        <canvas class="intro__ceu" id="introCeu"></canvas>
        <div class="intro__glow"></div>
        <div class="intro__conteudo">
            <h1 class="intro__nome" aria-label="Kosmos">
                <span class="intro__letra" style="--i:0">K</span>
                <span class="intro__letra intro__letra--planeta" style="--i:1">
                    <!-- planetinha com anel (eco do favicon) no lugar do primeiro "o";
                         os olhinhos seguem o cursor (mesma alma da mascote do login) -->
                    <svg viewBox="0 0 64 64" focusable="false" aria-hidden="true">
                        <defs>
                            <radialGradient id="introPlaneta" cx="35%" cy="35%" r="75%">
                                <stop offset="0"   stop-color="#c97cff"/>
                                <stop offset=".55" stop-color="#a541ff"/>
                                <stop offset="1"   stop-color="#4d1487"/>
                            </radialGradient>
                        </defs>
                        <circle cx="32" cy="32" r="17" fill="url(#introPlaneta)"/>
                        <g class="intro__olhos" id="introOlhos">
                            <circle cx="26" cy="30" r="4.2" fill="#ffffff"/>
                            <circle cx="38" cy="30" r="4.2" fill="#ffffff"/>
                            <g id="introPupilas">
                                <circle cx="26" cy="30" r="2" fill="#2a0845"/>
                                <circle cx="38" cy="30" r="2" fill="#2a0845"/>
                            </g>
                        </g>
                        <g transform="rotate(-22 32 32)">
                            <ellipse class="intro__anel" cx="32" cy="32" rx="28" ry="10" pathLength="100"
                                     fill="none" stroke="#e8d5ff" stroke-width="2.5" stroke-linecap="round"/>
                            <circle r="3" fill="#ffffff">
                                <animateMotion dur="3.2s" repeatCount="indefinite"
                                               path="M60,32 A28,10 0 1,1 4,32 A28,10 0 1,1 60,32 Z"/>
                            </circle>
                        </g>
                    </svg>
                </span>
                <span class="intro__letra" style="--i:2">s</span>
                <span class="intro__letra" style="--i:3">m</span>
                <span class="intro__letra" style="--i:4">o</span>
                <span class="intro__letra" style="--i:5">s</span>
            </h1>
            <p class="intro__tag" id="introTag" data-texto="Estudar nunca foi difícil.">&nbsp;</p>
            <p class="intro__ola" id="introOla"></p>
        </div>
        <p class="intro__dica">toque no céu ✦</p>
        <button class="intro__pular" id="introPular" type="button">
            Pular intro
            <span class="intro__pular-barra" id="introBarra"></span>
        </button>
    </div>

    <!-- Background Efeitos -->
    <div class="bg">
        <div class="bg__orb bg__orb--1"></div>
        <div class="bg__orb bg__orb--2"></div>
        <div class="bg__grid"></div>
    </div>

    <!-- Layout Container -->
    <div class="contGeral">

        <!-- Sidebar (Lateral) -->
        <aside class="contLateral">
            <a class="contLogo" href="index.html">
                <span class="logo__text klogo" role="img" aria-label="Kosmos">K<i class="klogo__o"></i>smos</span>
            </a>

            <nav class="botoesL" aria-label="Navegação principal">
                <!-- marcador que desliza entre os itens (posicionado pelo dashboard.js) -->
                <span class="nav__marca" aria-hidden="true"></span>

                <a href="index.html">
                    <svg viewBox="0 0 24 24" fill="none" class="nav-icon"><path d="M4 10 L12 4 L20 10 L20 20 L4 20 Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Início
                </a>

                <span class="nav__grupo">Estudar</span>
                <a href="resumos.html">
                    <svg viewBox="0 0 24 24" fill="none" class="nav-icon"><rect x="4" y="4" width="16" height="16" rx="2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M8 10 H16 M8 14 H12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    Resumos
                </a>
                <a href="flashcards.html">
                    <svg viewBox="0 0 24 24" fill="none" class="nav-icon"><rect x="3" y="6" width="13" height="12" rx="2" stroke="currentColor" stroke-width="2"/><path d="M8 4 H19 a2 2 0 0 1 2 2 V16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    Flashcards
                </a>
                <a href="exercicios.html">
                    <svg viewBox="0 0 24 24" fill="none" class="nav-icon"><path d="M4 6 H20 M4 12 H20 M4 18 H14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    Exercícios
                </a>

                <span class="nav__grupo">Foco</span>
                <a href="pomodoro.html">
                    <svg viewBox="0 0 24 24" fill="none" class="nav-icon"><circle cx="12" cy="13" r="8" stroke="currentColor" stroke-width="2"/><path d="M12 9 L12 13 L15 15 M9 3 H15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Pomodoro
                </a>

                <!-- no desktop a conta vive no rodapé; aqui ela serve à barra do mobile -->
                <a href="conta.html">
                    <svg viewBox="0 0 24 24" fill="none" class="nav-icon"><circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="2"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    Conta
                </a>
            </nav>

            <div class="contUsuario">
                <a class="usuario" href="conta.html">
                    <span class="usuario__avatar" data-usuario-inicial aria-hidden="true">E</span>
                    <span class="usuario__info">
                        <strong data-usuario class="esqueleto"></strong>
                        <span>Ver conta</span>
                    </span>
                </a>
                <button class="usuario__sair" type="button"
                        title="Sair da conta" aria-label="Sair da conta">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 5 H18 a1 1 0 0 1 1 1 V18 a1 1 0 0 1 -1 1 H15 M10 8 L6 12 L10 16 M6 12 H15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
            </div>
        </aside>

        <!-- Main Content (Meio) -->
        <main class="contMeio pagina-inicio">

            <!-- ==========================================================
                 ABERTURA — eco do hero da landing page: pílula, título
                 grande em Syne com o nome em gradiente, e as estatísticas
                 como números soltos (sem caixa), igual ao hero da LP.
                 ========================================================== -->
            <header class="ini-hero">
                <span class="ini-data" id="iniData">Hoje</span>
                <h1 class="ini-hero__titulo">
                    <span id="iniSaudacao">Bem-vindo</span>,
                    <span class="ini-hero__nome" data-usuario-primeiro>Estudante</span>.
                </h1>
                <p class="ini-hero__desc">
                    Suas ferramentas de estudo em um só lugar. Escolha por onde começar
                    — ou entre direto numa sessão de foco.
                </p>

                <div class="ini-hero__cta">
                    <a href="pomodoro.html" class="dash-btn dash-btn--primary">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="13" r="8" stroke="currentColor" stroke-width="2"/><path d="M12 9 L12 13 L15 15 M9 3 H15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Iniciar foco
                    </a>
                    <a href="resumos.html" class="dash-btn dash-btn--ghost">Escrever um resumo</a>
                </div>

                <!-- Só a sequência é um número real hoje; os outros esperam
                     a persistência (ver js/inicio.js). -->
                <div class="ini-stats" aria-label="Seus números">
                    <div class="ini-stat">
                        <strong data-metrica="sequencia">—</strong>
                        <span>dias de sequência</span>
                    </div>
                    <i class="ini-stat__div" aria-hidden="true"></i>
                    <div class="ini-stat ini-stat--vazio">
                        <strong data-metrica="resumos">—</strong>
                        <span>resumos</span>
                    </div>
                    <i class="ini-stat__div" aria-hidden="true"></i>
                    <div class="ini-stat ini-stat--vazio">
                        <strong data-metrica="flashcards">—</strong>
                        <span>flashcards</span>
                    </div>
                    <i class="ini-stat__div" aria-hidden="true"></i>
                    <div class="ini-stat ini-stat--vazio">
                        <strong data-metrica="exercicios">—</strong>
                        <span>exercícios</span>
                    </div>
                </div>
            </header>

            <!-- ==========================================================
                 FERRAMENTAS — cards com a mesma receita do .card da LP
                 (gradiente 150deg, ícone 52px, brilho seguindo o cursor)
                 ========================================================== -->
            <section class="ini-secao">
                <span class="section-tag">Ferramentas</span>
                <h2 class="section-title">O que você quer fazer <em>agora</em>?</h2>

                <div class="ini-grid">
                    <a class="ini-card" href="resumos.html">
                        <span class="ini-card__ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><rect x="4" y="4" width="16" height="16" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M8 10 H16 M8 14 H12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        </span>
                        <h3 class="ini-card__titulo">Resumos</h3>
                        <p class="ini-card__desc">Escreva com suas palavras o que acabou de estudar.</p>
                    </a>

                    <a class="ini-card" href="flashcards.html">
                        <span class="ini-card__ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><rect x="3" y="6" width="13" height="12" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M8 4 H19 a2 2 0 0 1 2 2 V16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        </span>
                        <h3 class="ini-card__titulo">Flashcards</h3>
                        <p class="ini-card__desc">Revise por repetição: pergunta na frente, resposta atrás.</p>
                    </a>

                    <a class="ini-card" href="exercicios.html">
                        <span class="ini-card__ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><path d="M5 7 H19 M5 12 H19 M5 17 H13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        </span>
                        <h3 class="ini-card__titulo">Exercícios <span class="ini-tag">IA</span></h3>
                        <p class="ini-card__desc">Questões geradas na hora, na matéria e no nível que quiser.</p>
                    </a>

                    <a class="ini-card" href="pomodoro.html">
                        <span class="ini-card__ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="13" r="8" stroke="currentColor" stroke-width="1.8"/><path d="M12 9 L12 13 L15 15 M9 3 H15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        <h3 class="ini-card__titulo">Pomodoro</h3>
                        <p class="ini-card__desc">25 minutos de foco, 5 de descanso. Sem distração.</p>
                    </a>
                </div>
            </section>

            <!-- ==========================================================
                 SEU RITMO — semana + primeiros passos
                 ========================================================== -->
            <section class="ini-secao">
                <span class="section-tag">Seu ritmo</span>
                <h2 class="section-title">Como você está <em>indo</em></h2>

                <div class="ini-duas">
                    <div class="ini-card">
                        <div class="ini-card__cabeca">
                            <h3>Esta semana</h3>
                            <span class="ini-card__nota" data-total-semana>últimos 7 dias</span>
                        </div>
                        <!-- as colunas são desenhadas pelo inicio.js (CSS puro, sem biblioteca) -->
                        <div class="ini-chart" id="iniChart" role="img"
                             aria-label="Minutos estudados nos últimos sete dias"></div>
                        <p class="ini-chart__aviso" id="iniChartAviso">
                            Ainda não há sessões registradas. Assim que você estudar com o
                            Pomodoro, seus dias aparecem aqui.
                        </p>
                    </div>

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
                                <a class="ini-passo__link" href="resumos.html">
                                    <strong>Criar seu primeiro resumo</strong>
                                    <span>Comece pela matéria que você viu hoje.</span>
                                </a>
                            </li>
                            <li class="ini-passo" data-passo="flashcards">
                                <button class="ini-passo__check" type="button" aria-pressed="false"
                                        aria-label="Marcar como feito: montar um baralho de flashcards">
                                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12.5 L10 17.5 L19 7" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </button>
                                <a class="ini-passo__link" href="flashcards.html">
                                    <strong>Montar um baralho</strong>
                                    <span>Transforme o resumo em perguntas curtas.</span>
                                </a>
                            </li>
                            <li class="ini-passo" data-passo="pomodoro">
                                <button class="ini-passo__check" type="button" aria-pressed="false"
                                        aria-label="Marcar como feito: fazer uma sessão de foco">
                                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12.5 L10 17.5 L19 7" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </button>
                                <a class="ini-passo__link" href="pomodoro.html">
                                    <strong>Fazer 25 minutos de foco</strong>
                                    <span>Um ciclo de Pomodoro já conta para a sequência.</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>
        </main>

    </div>

    <script src="./js/dashboard.js"></script>
    <script src="./js/inicio.js"></script>
    <script src="./js/cursor.js"></script>
    <script src="./js/intro.js"></script>
</body>
</html>
