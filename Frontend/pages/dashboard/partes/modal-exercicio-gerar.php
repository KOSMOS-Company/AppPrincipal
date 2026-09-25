<?php
// ============================================================
//  KOSMOS — Modal de gerar/salvar exercícios com IA
//  Usado dentro de uma matéria (exercicio_materia.php).
//  Quem abre, gera e salva é o js/exercicio-gerar-form.js.
// ============================================================
if (!isset($USUARIO, $PREF)) {
    http_response_code(403);
    exit('Esta página não é acessada direto.');
}
?>
        <!-- ==========================================================
             Gerar / Salvar exercícios com IA.
             ========================================================== -->
        <div class="modal" id="modalExercicioGerar" role="dialog" aria-modal="true" aria-labelledby="modalExercicioGerarTitulo">
            <div class="modal__box modal__box--resumo">
                <div class="modal__head">
                    <h3 id="modalExercicioGerarTitulo">Gerar exercícios com IA</h3>
                    <button class="modal__close" type="button" id="exercicioGerarFechar" aria-label="Fechar">&times;</button>
                </div>

                <form class="modal__form" id="formExercicioGerar">
                    <input type="hidden" id="exercicioGerarMateriaId" value="">

                    <!-- Etapa 1: Configuração -->
                    <div class="ex-gerar-etapa" id="etapaConfig" data-etapa="1">
                        <div class="campo">
                            <label for="exGerarTitulo">Título da lista</label>
                            <input id="exGerarTitulo" type="text" placeholder="Ex: Lista 1 — Derivadas" maxlength="140" required>
                        </div>

                        <div class="campo">
                            <label for="exGerarConteudo">Conteúdo específico <span class="campo__opcional">(obrigatório)</span></label>
                            <textarea id="exGerarConteudo" rows="4" placeholder="Descreva o tópico exato para gerar exercícios direcionados...&#10;Ex: Derivadas de funções polinomiais, regra da cadeia, derivadas de funções trigonométricas" required></textarea>
                            <span class="campo__dica">Quanto mais específico você for, melhor a IA acerta no foco.</span>
                        </div>

                        <div class="campo">
                            <label id="exGerarDificuldadeLbl">Dificuldade</label>
                            <div class="ex-dif" id="exGerarDificuldades" role="group" aria-labelledby="exGerarDificuldadeLbl">
                                <button type="button" class="ex-dif__opcao" data-dificuldade="Fácil" aria-pressed="false">Fácil</button>
                                <button type="button" class="ex-dif__opcao" data-dificuldade="Médio" aria-pressed="true">Médio</button>
                                <button type="button" class="ex-dif__opcao" data-dificuldade="Difícil" aria-pressed="false">Difícil</button>
                            </div>
                            <span class="campo__dica">Pode marcar mais de uma — as questões saem misturadas.</span>
                        </div>

                        <div class="campo">
                            <label id="exGerarModoLbl">Distribuição</label>
                            <div class="ex-dif" id="exGerarModo" role="group" aria-labelledby="exGerarModoLbl">
                                <button type="button" class="ex-dif__opcao" data-modo="fixo" aria-pressed="true">Por dificuldade</button>
                                <button type="button" class="ex-dif__opcao" data-modo="aleatorio" aria-pressed="false">Aleatória</button>
                            </div>
                        </div>

                        <div class="campo" id="exGerarPorDificuldade">
                            <label>Quantas de cada</label>
                            <div class="ex-qtd-dif" id="exGerarQtdsDif"></div>
                            <span class="campo__dica" id="exGerarTotalDif">Total: 5 de 15 questões.</span>
                        </div>

                        <div class="campo" id="exGerarTotalAleatorio" hidden>
                            <label for="exGerarQtd">Quantidade total</label>
                            <div class="qtd-stepper">
                                <button type="button" class="qtd-stepper__btn" id="exGerarQtdMenos" aria-label="Diminuir quantidade">
                                    <svg class="ico" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M5 10h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                </button>
                                <input id="exGerarQtd" type="number" min="1" max="15" value="5" inputmode="numeric" aria-label="Quantidade de questões">
                                <button type="button" class="qtd-stepper__btn" id="exGerarQtdMais" aria-label="Aumentar quantidade">
                                    <svg class="ico" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 5v10M5 10h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                </button>
                            </div>
                            <span class="campo__dica">A IA divide o total entre as dificuldades marcadas. Máximo de 15 questões por vez.</span>
                        </div>

                        <div class="msg" id="msgExercicioGerar" hidden></div>

                        <div class="modal__actions">
                            <button type="button" class="dash-btn dash-btn--outline" id="exGerarCancelar">Cancelar</button>
                            <button type="button" class="dash-btn dash-btn--primary" id="btnGerarComIA">
                                Gerar com IA
                            </button>
                        </div>

                        <!-- Contagem regressiva da estimativa (aparece só durante a geração) -->
                        <div class="ex-gerar-estimativa" id="exGerarEstimativa" hidden></div>
                    </div>

                    <!-- Etapa 2: Preview das questões geradas -->
                    <div class="ex-gerar-etapa" id="etapaPreview" data-etapa="2" hidden>
                        <div class="ex-gerar-preview-header">
                            <div class="ex-gerar-preview-info">
                                <strong id="previewTitulo">Título</strong>
                                <span id="previewMeta"></span>
                            </div>
                            <button type="button" class="dash-btn dash-btn--ghost dash-btn--sm" id="btnVoltarConfig">
                                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M16 10H4M9 14l-5-4 5-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                Ajustar
                            </button>
                        </div>

                        <div class="ex-gerar-questoes" id="exGerarQuestoes">
                            <!-- Questões inseridas via JS -->
                        </div>

                        <div class="msg" id="msgExercicioGerarPreview" hidden></div>

                        <div class="modal__actions">
                            <button type="button" class="dash-btn dash-btn--outline" id="exGerarCancelar2">Cancelar</button>
                            <button type="button" class="dash-btn dash-btn--primary" id="btnSalvarExercicio">
                                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M15.83 17.5H4.17A1.67 1.67 0 0 1 2.5 15.83V4.17A1.67 1.67 0 0 1 4.17 2.5h9.16l4.17 4.17v9.16a1.67 1.67 0 0 1-1.67 1.67z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M14.17 17.5v-6.67H5.83v6.67" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/><path d="M5.83 2.5v4.17h6.67" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                Salvar exercício
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>