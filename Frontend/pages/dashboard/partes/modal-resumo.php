<?php
// ============================================================
//  KOSMOS — Modal de escrever/editar resumo (parte reaproveitada)
//  Usado pela lista (resumos.php) e pela prévia (resumo.php).
//  Quem abre, salva e apaga é o js/resumo-form.js.
// ============================================================
if (!isset($USUARIO)) {
    http_response_code(403);
    exit('Esta página não é acessada direto.');
}
?>
        <!-- ==========================================================
             Escrever / abrir um resumo. O mesmo modal serve para criar
             e para editar: com id preenchido, salvar atualiza.
             ========================================================== -->
        <div class="modal" id="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitulo">
            <div class="modal__box modal__box--resumo">
                <div class="modal__head">
                    <h3 id="modalTitulo">Novo resumo</h3>
                    <button class="modal__close" type="button" id="fechar" aria-label="Fechar">&times;</button>
                </div>

                <form class="modal__form" id="formNovo">
                    <input type="hidden" id="resumoId" value="">

                    <div class="campo">
                        <label for="titulo">Título</label>
                        <input id="titulo" type="text" placeholder="Ex: Leis de Newton" maxlength="140" required>
                    </div>

                    <div class="campo">
                        <label for="materia">Matéria</label>
                        <select id="materia" required>
<?php foreach (MATERIAS_KOSMOS as $m): ?>
                            <option value="<?= hesc($m) ?>"<?= in_array($m, $PREF['materias'], true) ? ' data-favorita="1"' : '' ?>><?= hesc($m) ?></option>
<?php endforeach; ?>
                        </select>
                    </div>

                    <div class="campo">
                        <label for="conteudo">Resumo</label>
                        <textarea id="conteudo" rows="10" placeholder="Escreva com suas palavras o que você estudou..." required></textarea>
                        <span class="campo__dica"><span id="contador">0</span> caracteres</span>
                    </div>

                    <div class="msg" id="msgResumo" hidden></div>

                    <div class="modal__actions">
                        <button type="button" class="dash-btn dash-btn--danger" id="btnApagar" hidden>Apagar</button>
                        <button type="button" class="dash-btn dash-btn--outline" id="cancelar">Cancelar</button>
                        <button type="submit" class="dash-btn dash-btn--primary" id="btnSalvar">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
