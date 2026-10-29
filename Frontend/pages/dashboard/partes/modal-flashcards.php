<?php
// ============================================================
//  KOSMOS — Modal "gerar flashcards de um resumo" (reaproveitada)
//  Usada pela estante (resumos.php), por dentro de um caderno
//  (caderno.php) e pela leitura de um resumo (resumo.php) — as três
//  telas de onde se chega a um resumo.
//  Quem preenche, edita e envia é o js/flashcards-gerar.js.
//
//  A LISTA NASCE VAZIA de propósito. O servidor lê o resumo e
//  sugere os cartões (Backend/php/flashcards_gerar.php), mas nada é
//  gravado antes de a pessoa conferir: um cartão errado memorizado
//  é pior do que cartão nenhum, então a última palavra é sempre
//  dela — dá para editar os dois lados, tirar o que não presta e
//  acrescentar o que faltou.
// ============================================================
if (!isset($USUARIO)) {
    http_response_code(403);
    exit('Esta página não é acessada direto.');
}
?>
        <div class="modal" id="modalGerar" role="dialog" aria-modal="true" aria-labelledby="gerarTitulo">
            <div class="modal__box modal__box--gerar">
                <div class="modal__head">
                    <h3 id="gerarTitulo">Gerar flashcards</h3>
                    <button class="modal__close" type="button" id="gerarFechar" aria-label="Fechar">&times;</button>
                </div>

                <form class="modal__form" id="gerarForm" novalidate>
                    <p class="gf-fonte" id="gerarFonte"></p>

                    <!-- Estado de espera: a leitura do resumo é rápida, mas
                         "rápida" não é "instantânea" numa máquina de escola. -->
                    <p class="gf-carregando" id="gerarCarregando">Lendo seu resumo…</p>

                    <div id="gerarConteudo" hidden>
                        <div class="campo">
                            <label for="gerarDeck">Guardar em</label>
                            <select id="gerarDeck" class="combo-select">
                                <!-- preenchido pelo JS com os baralhos da conta -->
                            </select>
                        </div>

                        <!-- Só aparece quando a escolha é "Criar um baralho
                             novo". A matéria não é perguntada: o baralho
                             nasce do resumo e herda a matéria dele. -->
                        <div class="campo" id="gerarNovoCampo" hidden>
                            <label for="gerarNovoNome">Nome do baralho novo</label>
                            <input type="text" id="gerarNovoNome" maxlength="120" autocomplete="off">
                        </div>

                        <!-- Gerar com IA, numa linha: dificuldade, quantidade e o
                             botão. Aparece só quando o resumo tem texto e a IA
                             está configurada (o servidor avisa). Mesmos chips e
                             stepper dos exercícios. -->
                        <div class="gf-ia" id="gerarIAPainel" hidden>
                            <p class="gf-ia__titulo">
                                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M8 3.5c.5 3 1.5 4 4.5 4.5-3 .5-4 1.5-4.5 4.5-.5-3-1.5-4-4.5-4.5 3-.5 4-1.5 4.5-4.5Z" fill="currentColor"/><path d="M14.5 11.5c.25 1.5.75 2 2.25 2.25-1.5.25-2 .75-2.25 2.25-.25-1.5-.75-2-2.25-2.25 1.5-.25 2-.75 2.25-2.25Z" fill="currentColor"/></svg>
                                Criar com IA
                            </p>
                            <div class="gf-ia__linha">
                                <div class="ex-dif" id="gerarIADificuldades" role="group" aria-label="Dificuldade dos cartões">
                                    <button type="button" class="ex-dif__opcao" data-dificuldade="Fácil" aria-pressed="false">Fácil</button>
                                    <button type="button" class="ex-dif__opcao" data-dificuldade="Médio" aria-pressed="true">Médio</button>
                                    <button type="button" class="ex-dif__opcao" data-dificuldade="Difícil" aria-pressed="false">Difícil</button>
                                </div>
                                <div class="gf-ia__qtd">
                                    <div class="qtd-stepper">
                                        <button type="button" class="qtd-stepper__btn" data-passo="-1" aria-label="Menos cartões">
                                            <svg class="ico" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M5 10h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                        </button>
                                        <input id="gerarIAQtd" type="number" min="1" max="20" value="10" inputmode="numeric" aria-label="Quantos cartões (até 20)">
                                        <button type="button" class="qtd-stepper__btn" data-passo="1" aria-label="Mais cartões">
                                            <svg class="ico" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 5v10M5 10h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                        </button>
                                    </div>
                                    <span>cartões</span>
                                </div>
                                <button type="button" class="dash-btn dash-btn--pequeno btn-ia gf-ia__gerar" id="gerarComIA">
                                    <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M8 3.5c.5 3 1.5 4 4.5 4.5-3 .5-4 1.5-4.5 4.5-.5-3-1.5-4-4.5-4.5 3-.5 4-1.5 4.5-4.5Z" fill="currentColor"/><path class="ia-estrela-menor" d="M14.5 11.5c.25 1.5.75 2 2.25 2.25-1.5.25-2 .75-2.25 2.25-.25-1.5-.75-2-2.25-2.25 1.5-.25 2-.75 2.25-2.25Z" fill="currentColor"/></svg>
                                    <span>Gerar</span>
                                </button>
                            </div>
                        </div>

                        <div class="gf-cabeca">
                            <h4 id="gerarConta">Cartões</h4>
                            <button type="button" class="gf-adicionar" id="gerarAdicionar">
                                + Cartão em branco
                            </button>
                        </div>

                        <!-- Os cartões em DOIS grupos, para nunca restar dúvida de
                             quem escreveu o quê: o que veio da pessoa (à mão ou
                             tirado do resumo dela — destaque, definição, pergunta)
                             e o que a IA criou. Cada grupo recolhe e se esvazia
                             de uma vez; vinte cartões soltos numa lista só eram
                             uma parede de campos. Quem preenche: flashcards-gerar.js. -->
                        <div class="gf-lista" id="gerarLista">
                            <section class="gf-grupo gf-grupo--voce" data-grupo="voce" hidden>
                                <header class="gf-grupo__cabeca">
                                    <button type="button" class="gf-grupo__abrir" aria-expanded="true">
                                        <svg class="gf-grupo__seta" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M6 8l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        <span class="gf-grupo__selo" aria-hidden="true">
                                            <svg viewBox="0 0 20 20" fill="none"><path d="M4 16h3l8-8-3-3-8 8v3Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>
                                        </span>
                                        Feitos por você
                                        <span class="gf-grupo__conta">0</span>
                                    </button>
                                    <button type="button" class="gf-grupo__limpar">Remover todos</button>
                                </header>
                                <div class="gf-grupo__cartoes"></div>
                            </section>

                            <section class="gf-grupo gf-grupo--ia" data-grupo="ia" hidden>
                                <header class="gf-grupo__cabeca">
                                    <button type="button" class="gf-grupo__abrir" aria-expanded="true">
                                        <svg class="gf-grupo__seta" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M6 8l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        <span class="gf-grupo__selo" aria-hidden="true">
                                            <svg viewBox="0 0 20 20" fill="none"><path d="M8 3.5c.5 3 1.5 4 4.5 4.5-3 .5-4 1.5-4.5 4.5-.5-3-1.5-4-4.5-4.5 3-.5 4-1.5 4.5-4.5Z" fill="currentColor"/><path d="M14.5 11.5c.25 1.5.75 2 2.25 2.25-1.5.25-2 .75-2.25 2.25-.25-1.5-.75-2-2.25-2.25 1.5-.25 2-.75 2.25-2.25Z" fill="currentColor"/></svg>
                                        </span>
                                        Criados pela IA
                                        <span class="gf-grupo__conta">0</span>
                                    </button>
                                    <button type="button" class="gf-grupo__limpar">Remover todos</button>
                                </header>
                                <div class="gf-grupo__cartoes"></div>
                            </section>
                        </div>

                        <p class="gf-vazio" id="gerarVazio" hidden>
                            Não consegui tirar cartões deste resumo automaticamente.
                            Ele funciona melhor com linhas no formato
                            <code>Termo: definição</code>, com perguntas terminadas em
                            <code>?</code> ou com trechos marcados entre <code>==</code>.
                            Você pode escrever os cartões à mão aqui mesmo<span id="gerarVazioIA" hidden>
                            ou usar <strong>Gerar com IA</strong>, que entende qualquer texto</span>.
                        </p>
                    </div>

                    <p class="msg" id="gerarMsg" hidden></p>

                    <div class="modal__actions">
                        <button type="button" class="dash-btn dash-btn--outline" id="gerarCancelar">Cancelar</button>
                        <button type="submit" class="dash-btn dash-btn--primary" id="gerarSalvar" disabled>
                            Criar cartões
                        </button>
                    </div>
                </form>
            </div>
        </div>
