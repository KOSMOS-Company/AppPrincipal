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
    <title>Kosmos — Flashcards</title>
    <link rel="stylesheet" href="./css/dashboard.css">
    <link rel="stylesheet" href="./css/flashcards.css">
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

            <!-- ============================================================
                 VISÃO 1 — SEUS DECKS
                 ============================================================ -->
            <section id="viewDecks">
                <header class="contCabeca">
                    <div class="contCabeca__texto">
                        <span class="section-tag">Revisão</span>
                        <h1>Seus <span class="h-nome">Flashcards</span></h1>
                        <p>Monte seus próprios baralhos e veja o que você já domina.</p>
                    </div>
                    <button class="dash-btn dash-btn--primary" id="btnNovoDeck">
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 4v12M4 10h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        Novo deck
                    </button>
                </header>

                <!-- Números soltos, sem caixa — mesma leitura do Início -->
                <div class="ini-stats fc-stats" id="fcStats">
                    <div class="ini-stat ini-stat--vazio">
                        <strong data-stat="decks">—</strong>
                        <span>Decks</span>
                    </div>
                    <span class="ini-stat__div" aria-hidden="true"></span>
                    <div class="ini-stat ini-stat--vazio">
                        <strong data-stat="cartoes">—</strong>
                        <span>Cartões criados</span>
                    </div>
                    <span class="ini-stat__div" aria-hidden="true"></span>
                    <div class="ini-stat ini-stat--vazio">
                        <strong data-stat="revisados">—</strong>
                        <span>Já revisados</span>
                    </div>
                    <span class="ini-stat__div" aria-hidden="true"></span>
                    <div class="ini-stat ini-stat--vazio">
                        <strong data-stat="dominio">—</strong>
                        <span>Domínio</span>
                    </div>
                </div>

                <!-- Filtros por matéria: montados a partir das SUAS matérias -->
                <div class="chips" id="filtros" hidden></div>

                <div class="decks-grid" id="decksGrid" aria-busy="true"></div>

                <div class="vazio" id="vazio" hidden>
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="6" width="13" height="12" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M8 4 H19 a2 2 0 0 1 2 2 V16" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                    <h3>Nenhum deck por aqui</h3>
                    <p>Crie seu primeiro baralho e comece a escrever os cartões do jeito que você estuda.</p>
                </div>
            </section>

            <!-- ============================================================
                 VISÃO 2 — CARTÕES DO DECK (criar / editar / excluir)
                 ============================================================ -->
            <section id="viewCartoes" hidden>
                <header class="contCabeca">
                    <div class="contCabeca__texto">
                        <span class="section-tag" id="cartoesMateria">Deck</span>
                        <h1 id="cartoesTitulo">Deck</h1>
                        <p id="cartoesResumo">Nenhum cartão ainda.</p>
                    </div>
                    <div class="fc-acoes">
                        <button class="dash-btn dash-btn--outline" data-voltar-decks>← Decks</button>
                        <button class="dash-btn dash-btn--ghost" id="btnEstudarDaqui">Estudar</button>
                        <button class="dash-btn dash-btn--primary" id="btnNovoCartao">
                            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 4v12M4 10h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            Novo cartão
                        </button>
                    </div>
                </header>

                <ul class="cartoes-lista" id="cartoesLista"></ul>

                <div class="vazio" id="cartoesVazio" hidden>
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="4" y="5" width="16" height="14" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M8 10h8M8 14h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                    <h3>Este deck ainda não tem cartões</h3>
                    <p>Adicione a primeira pergunta e resposta para poder estudar.</p>
                </div>
            </section>

            <!-- ============================================================
                 VISÃO 3 — MODO DE ESTUDO
                 ============================================================ -->
            <section id="viewEstudo" hidden>
                <header class="contCabeca">
                    <div class="contCabeca__texto">
                        <span class="section-tag">Estudando</span>
                        <h1 id="estudoTitulo">Deck</h1>
                        <p id="estudoProgresso">Cartão 1 de 1</p>
                    </div>
                    <div class="fc-acoes">
                        <button class="dash-btn dash-btn--outline" data-voltar-decks>← Sair do estudo</button>
                        <button class="dash-btn dash-btn--ghost" id="btnEmbaralhar">Embaralhar</button>
                    </div>
                </header>

                <div class="estudo-area">
                    <div class="flashcard" id="flashcard" role="button" tabindex="0"
                         aria-label="Virar o cartão">
                        <div class="flashcard__inner">
                            <div class="flashcard__face flashcard__face--frente">
                                <span class="flashcard__hint">Pergunta</span>
                                <p id="cardFrente">—</p>
                                <span class="flashcard__virar">Clique para ver a resposta</span>
                            </div>
                            <div class="flashcard__face flashcard__face--verso">
                                <span class="flashcard__hint">Resposta</span>
                                <p id="cardVerso">—</p>
                                <span class="flashcard__virar">Clique para voltar</span>
                            </div>
                        </div>
                    </div>

                    <!-- Autoavaliação: aparece depois de virar a carta -->
                    <div class="estudo-avaliacao" id="estudoAvaliacao" hidden>
                        <span class="estudo-avaliacao__pergunta">Você lembrava dessa?</span>
                        <div class="estudo-avaliacao__botoes">
                            <button class="dash-btn fc-btn--errei" data-resposta="0">Ainda não</button>
                            <button class="dash-btn fc-btn--acertei" data-resposta="1">Já sei</button>
                        </div>
                    </div>

                    <div class="estudo-nav">
                        <button class="dash-btn dash-btn--ghost" id="btnAnterior">← Anterior</button>
                        <div class="estudo-progresso" role="progressbar"
                             aria-label="Progresso do estudo" aria-valuemin="0" aria-valuemax="100">
                            <div class="estudo-progresso__bar" id="barEstudo"></div>
                        </div>
                        <button class="dash-btn dash-btn--ghost" id="btnProximo">Pular →</button>
                    </div>

                    <!-- Fim da sessão -->
                    <div class="estudo-fim painel" id="estudoFim" hidden>
                        <h2>Sessão concluída!</h2>
                        <p id="estudoFimResumo">—</p>
                        <div class="estudo-fim__stats">
                            <div class="ini-stat">
                                <strong id="fimAcertos">0</strong>
                                <span>Já sabia</span>
                            </div>
                            <span class="ini-stat__div" aria-hidden="true"></span>
                            <div class="ini-stat">
                                <strong id="fimErros">0</strong>
                                <span>Para revisar</span>
                            </div>
                        </div>
                        <div class="estudo-fim__acoes">
                            <button class="dash-btn dash-btn--outline" data-voltar-decks>Voltar aos decks</button>
                            <button class="dash-btn dash-btn--primary" id="btnEstudarDeNovo">Estudar de novo</button>
                        </div>
                    </div>
                </div>
            </section>

        </main>
    </div>

    <!-- ============================================================
         MODAL — novo/editar deck
         ============================================================ -->
    <div class="modal" id="modalDeck" role="dialog" aria-modal="true" aria-labelledby="modalDeckTitulo">
        <div class="modal__box">
            <div class="modal__head">
                <h3 id="modalDeckTitulo">Novo deck</h3>
                <button class="modal__close" data-fechar-modal aria-label="Fechar">&times;</button>
            </div>
            <form class="modal__form" id="formDeck">
                <div class="campo">
                    <label for="nomeDeck">Nome do deck</label>
                    <input id="nomeDeck" type="text" maxlength="120"
                           placeholder="Ex: Biologia — Genética" required>
                </div>
                <div class="campo">
                    <label for="materiaDeck">Matéria</label>
                    <!-- lista aberta: pode escolher uma sugestão OU digitar a sua -->
                    <input id="materiaDeck" type="text" maxlength="40" list="listaMaterias"
                           placeholder="Ex: Biologia (ou escreva a sua)" required>
                    <datalist id="listaMaterias">
                        <option value="Matemática"></option>
                        <option value="Física"></option>
                        <option value="Química"></option>
                        <option value="Biologia"></option>
                        <option value="História"></option>
                        <option value="Geografia"></option>
                        <option value="Português"></option>
                        <option value="Redação"></option>
                        <option value="Inglês"></option>
                        <option value="Filosofia"></option>
                        <option value="Sociologia"></option>
                    </datalist>
                </div>
                <div class="modal__actions">
                    <button type="button" class="dash-btn dash-btn--outline" data-fechar-modal>Cancelar</button>
                    <button type="submit" class="dash-btn dash-btn--primary" id="btnSalvarDeck">Criar deck</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================================
         MODAL — novo/editar cartão
         ============================================================ -->
    <div class="modal" id="modalCartao" role="dialog" aria-modal="true" aria-labelledby="modalCartaoTitulo">
        <div class="modal__box">
            <div class="modal__head">
                <h3 id="modalCartaoTitulo">Novo cartão</h3>
                <button class="modal__close" data-fechar-modal aria-label="Fechar">&times;</button>
            </div>
            <form class="modal__form" id="formCartao">
                <div class="campo">
                    <label for="frenteCartao">Frente — a pergunta</label>
                    <textarea id="frenteCartao" maxlength="600"
                              placeholder="Ex: O que é velocidade média?" required></textarea>
                </div>
                <div class="campo">
                    <label for="versoCartao">Verso — a resposta</label>
                    <textarea id="versoCartao" maxlength="600"
                              placeholder="Ex: A variação do espaço dividida pela variação do tempo." required></textarea>
                </div>
                <div class="modal__actions">
                    <button type="button" class="dash-btn dash-btn--outline" data-fechar-modal>Cancelar</button>
                    <button type="submit" class="dash-btn dash-btn--ghost" id="btnSalvarMais"
                            value="mais">Salvar e criar outro</button>
                    <button type="submit" class="dash-btn dash-btn--primary" id="btnSalvarCartao">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================================
         MODAL — confirmar exclusão
         ============================================================ -->
    <div class="modal" id="modalConfirma" role="dialog" aria-modal="true" aria-labelledby="modalConfirmaTitulo">
        <div class="modal__box modal__box--estreito">
            <div class="modal__head">
                <h3 id="modalConfirmaTitulo">Tem certeza?</h3>
                <button class="modal__close" data-fechar-modal aria-label="Fechar">&times;</button>
            </div>
            <p class="modal__texto" id="confirmaTexto">Esta ação não pode ser desfeita.</p>
            <div class="modal__actions">
                <button type="button" class="dash-btn dash-btn--outline" data-fechar-modal>Cancelar</button>
                <button type="button" class="dash-btn fc-btn--perigo" id="btnConfirmar">Excluir</button>
            </div>
        </div>
    </div>

    <!-- Aviso curto de sucesso/erro -->
    <div class="fc-aviso" id="aviso" role="status" aria-live="polite" hidden></div>

    <script src="./js/dashboard.js"></script>
    <script src="./js/flashcards.js"></script>
    <script src="./js/cursor.js"></script>
</body>
</html>
