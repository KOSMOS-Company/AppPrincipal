<?php
// Porteiro + dados desta página (sem sessão, redireciona antes de
// mandar qualquer HTML). Deixa $USUARIO, $PREF e $PAGINA prontos.
require_once __DIR__ . '/../../../Backend/php/pagina_dashboard.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../shared/favicon.svg">
    <title>Kosmos — Exercícios</title>
    <link rel="stylesheet" href="./css/dashboard.css">
    <link rel="stylesheet" href="./css/exercicios.css">
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

        <?php include __DIR__ . '/partes/sidebar.php'; ?>

        <main class="contMeio">
            <header class="contCabeca">
                <div class="contCabeca__texto">
                    <span class="section-tag">Praticar</span>
                    <h1>Exercícios com <span class="h-nome">IA</span></h1>
                    <p>Gere questões personalizadas e teste seu conhecimento.</p>
                </div>
            </header>

            <!-- Gerador -->
            <div class="painel gerador">
                <div class="gerador__campos">
                    <div class="campo">
                        <label for="gMateria">Matéria</label>
                        <select id="gMateria">
                            <option value="Matemática">Matemática</option>
                            <option value="Física">Física</option>
                            <option value="Biologia">Biologia</option>
                        </select>
                    </div>
                    <div class="campo">
                        <label for="gDificuldade">Dificuldade</label>
                        <select id="gDificuldade">
                            <option value="Fácil">Fácil</option>
                            <option value="Médio" selected>Médio</option>
                            <option value="Difícil">Difícil</option>
                        </select>
                    </div>
                    <div class="campo">
                        <label for="gQtd">Quantidade</label>
                        <select id="gQtd">
                            <option value="3">3 questões</option>
                            <option value="5">5 questões</option>
                        </select>
                    </div>
                </div>
                <button class="dash-btn dash-btn--primary" id="btnGerar">
                    <svg viewBox="0 0 20 20" fill="none"><path d="M10 2l1.8 4.4L16 8l-4.2 1.6L10 14l-1.8-4.4L4 8l4.2-1.6L10 2z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
                    Gerar exercícios
                </button>
            </div>

            <!-- Resultado -->
            <div id="questoes" class="questoes"></div>

            <div class="vazio" id="vazioEx">
                <svg viewBox="0 0 24 24" fill="none"><path d="M9 11l2 2 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><rect x="4" y="4" width="16" height="16" rx="2" stroke="currentColor" stroke-width="1.5"/></svg>
                <h3>Pronto para praticar?</h3>
                <p>Escolha a matéria e a dificuldade, depois clique em "Gerar exercícios".</p>
            </div>

            <!-- Ações finais -->
            <div class="ex-rodape" id="exRodape" hidden>
                <div class="ex-placar" id="placar" hidden></div>
                <button class="dash-btn dash-btn--primary" id="btnCorrigir">Corrigir respostas</button>
            </div>
        </main>
    </div>

    <script src="./js/dashboard.js"></script>
    <script src="./js/exercicios.js"></script>
    <script src="./js/cursor.js"></script>
</body>
</html>
