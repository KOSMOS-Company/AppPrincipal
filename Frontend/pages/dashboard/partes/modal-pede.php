<?php
// ============================================================
//  KOSMOS — Modal de pedir um texto (parte reaproveitada)
//  Mini caixa com um campo, para renomear algo no meio da
//  página sem sair do design. Quem abre e devolve o valor é
//  a função pedir() do dashboard.js.
// ============================================================
if (!isset($USUARIO)) {
    http_response_code(403);
    exit('Esta página não é acessada direto.');
}
?>
        <div class="modal" id="modalPede" role="dialog" aria-modal="true" aria-labelledby="pedeTitulo">
            <div class="modal__box modal__box--pede">
                <div class="modal__head">
                    <h3 id="pedeTitulo">Editar</h3>
                    <button class="modal__close" type="button" id="pedeFechar" aria-label="Fechar">&times;</button>
                </div>

                <div class="campo">
                    <label for="pedeInput" id="pedeRotulo">Título</label>
                    <input type="text" id="pedeInput" maxlength="150" autocomplete="off">
                    <div class="msg" id="pedeErro" hidden></div>
                </div>

                <div class="modal__actions">
                    <button type="button" class="dash-btn dash-btn--outline" id="pedeNao">Cancelar</button>
                    <button type="button" class="dash-btn dash-btn--primary" id="pedeSim">Salvar</button>
                </div>
            </div>
        </div>
