<?php
// ============================================================
//  KOSMOS — Modal de confirmação (parte reaproveitada)
//  Incluído pelas páginas que precisam perguntar antes de agir.
//  Quem preenche o texto e resolve o sim/não é a função
//  confirmar() do dashboard.js.
// ============================================================
if (!isset($USUARIO)) {
    http_response_code(403);
    exit('Esta página não é acessada direto.');
}
?>
        <div class="modal" id="modalConfirma" role="dialog" aria-modal="true" aria-labelledby="confirmaTitulo">
            <div class="modal__box modal__box--confirma">
                <div class="modal__head">
                    <h3 id="confirmaTitulo">Confirmar</h3>
                    <button class="modal__close" type="button" id="confirmaFechar" aria-label="Fechar">&times;</button>
                </div>

                <p class="painel__sub" id="confirmaTexto"></p>

                <div class="modal__actions">
                    <button type="button" class="dash-btn dash-btn--outline" id="confirmaNao">Cancelar</button>
                    <button type="button" class="dash-btn dash-btn--primary" id="confirmaSim">Confirmar</button>
                </div>
            </div>
        </div>
