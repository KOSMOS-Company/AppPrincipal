<?php
// ============================================================
//  KOSMOS — Modal de praticar exercício salvo (quiz estilo ENEM)
//  Usado dentro de uma matéria (exercicio_materia.php).
//  Quem abre e controla o quiz é o js/exercicio-praticar.js.
// ============================================================
if (!isset($USUARIO, $PREF)) {
    http_response_code(403);
    exit('Esta página não é acessada direto.');
}
?>
        <!-- ==========================================================
             Praticar: quiz de uma questão por vez com feedback.
             Busca o exercício pronto no listar (?id=X) e roda tudo
             no cliente — nenhuma resposta certa aparece de cara.
             ========================================================== -->
        <div class="modal" id="modalPraticarExercicio" role="dialog" aria-modal="true"
             aria-labelledby="modalPraticarExercicioTitulo">
            <div class="modal__box modal__box--praticar">
                <div class="modal__head">
                    <h3 id="modalPraticarExercicioTitulo">Praticar</h3>
                    <button class="modal__close" type="button" id="praticarExercicioFechar" aria-label="Fechar">&times;</button>
                </div>

                <!-- Carregando / erro -->
                <div class="ex-praticar-estado" id="praticarEstado"></div>

                <!-- Uma questão por vez -->
                <div class="ex-praticar" id="praticarQuestao" hidden>
                    <div class="ex-praticar__progresso" id="praticarProgresso"></div>
                    <h4 class="ex-praticar__enunciado" id="praticarEnunciado"></h4>

                    <div class="ex-praticar__dica" id="praticarDicaBloco" hidden>
                        <button type="button" class="ex-praticar__dica-botao" id="praticarDicaBtn">💡 Ver dica</button>
                        <div class="ex-praticar__dica-texto" id="praticarDica" role="note"></div>
                    </div>

                    <div class="ex-praticar__alts" id="praticarAlts"></div>

                    <div class="msg" id="praticarFeedback" hidden></div>

                    <div class="modal__actions">
                        <button type="button" class="dash-btn dash-btn--outline" id="praticarFecharQuestao">Fechar</button>
                        <button type="button" class="dash-btn dash-btn--primary" id="praticarProxima" hidden>Próxima questão →</button>
                    </div>
                </div>

                <!-- Resultado final -->
                <div class="ex-praticar-resultado" id="praticarResultado" hidden>
                    <div class="ex-praticar-resultado__badge" id="praticarResultadoBadge" aria-hidden="true"></div>
                    <h4 class="ex-praticar-resultado__titulo" id="praticarResultadoTitulo"></h4>
                    <div class="ex-praticar-resumo" id="praticarResumo"></div>
                    <div class="modal__actions">
                        <button type="button" class="dash-btn dash-btn--outline" id="praticarFecharResultado">Fechar</button>
                        <button type="button" class="dash-btn dash-btn--primary" id="praticarDeNovo">Praticar de novo</button>
                    </div>
                </div>
            </div>
        </div>