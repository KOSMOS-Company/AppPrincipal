<?php
// ============================================================
//  KOSMOS — Modal de criar/personalizar matéria de exercícios (parte reaproveitada)
//  Usado pela estante (exercicios.php) e por dentro de uma matéria
//  (exercicio_materia.php, para mexer no que está aberto).
//  Quem abre, salva e apaga é o js/exercicio-materia-form.js.
//
//  A matéria de exercícios é o "deck" dos exercícios. Tem nome,
//  matéria, identidade visual — cor, ícone, descrição.
//  A cor reaproveita a paleta do avatar (.avatar-cor--*, já no
//  dashboard.css) e o ícone sai de uma lista fechada; as duas listas
//  moram em Backend/php/materias.php, que é também quem valida.
// ============================================================
if (!isset($USUARIO, $PREF)) {
    http_response_code(403);
    exit('Esta página não é acessada direto.');
}
?>
        <div class="modal" id="modalExercicioMateria" role="dialog" aria-modal="true"
             aria-labelledby="modalExercicioMateriaTitulo">
            <div class="modal__box modal__box--caderno">
                <div class="modal__head">
                    <h3 id="modalExercicioMateriaTitulo">Nova matéria</h3>
                    <button class="modal__close" type="button" id="exercicioMateriaFechar" aria-label="Fechar">&times;</button>
                </div>

                <form class="modal__form" id="formExercicioMateria">
                    <input type="hidden" id="exercicioMateriaId" value="">

                    <!-- Prévia: o cartão como ele vai ficar na estante.
                         Muda junto com os campos, para a escolha ser
                         pelo resultado e não pelo nome da cor. -->
                    <div class="cad-previa" id="exercicioMateriaPrevia" aria-hidden="true">
                        <span class="cad-previa__lombada"></span>
                        <div class="cad-previa__body">
                            <div class="cad-previa__topo">
                                <span class="cad-previa__icone" id="exercicioMateriaPreviaIcone" hidden></span>
                                <span class="materia-tag" id="exercicioMateriaPreviaMateria">Matéria</span>
                            </div>
                            <strong id="exercicioMateriaPreviaNome">Nome da matéria</strong>
                            <span class="cad-previa__desc" id="exercicioMateriaPreviaDesc" hidden></span>
                        </div>
                    </div>

                    <div class="campo">
                        <label for="exercicioMateriaNome">Nome da matéria</label>
                        <input id="exercicioMateriaNome" type="text" maxlength="120"
                               placeholder="Ex: Cálculo Diferencial — Derivadas" required>
                    </div>

                    <div class="campo">
                        <label for="exercicioMateriaMateria">Matéria base</label>
                        <select id="exercicioMateriaMateria" required>
<?php foreach (MATERIAS_KOSMOS as $m): ?>
                            <option value="<?= hesc($m) ?>"<?= in_array($m, $PREF['materias'], true) ? ' data-favorita="1"' : '' ?>><?= hesc($m) ?></option>
<?php endforeach; ?>
                        </select>
                        <span class="campo__dica">Os exercícios desta matéria vão usar esta área de conhecimento.</span>
                    </div>

                    <div class="campo">
                        <label for="exercicioMateriaDescricao">Descrição <span class="campo__opcional">(opcional)</span></label>
                        <input id="exercicioMateriaDescricao" type="text" maxlength="160"
                               placeholder="Ex: revisão para a prova do dia 15">
                    </div>

                    <!-- ---------- Cor ---------- -->
                    <fieldset class="campo cad-escolha">
                        <legend>Cor</legend>
                        <div class="cad-cores" id="exercicioMateriaCores" role="radiogroup" aria-label="Cor da matéria">
<?php foreach (CORES_CADERNO_KOSMOS as $i => $cor): ?>
                            <button type="button" class="cad-cor avatar-cor--<?= hesc($cor) ?>"
                                    data-cor="<?= hesc($cor) ?>" role="radio"
                                    aria-checked="<?= $i === 0 ? 'true' : 'false' ?>"
                                    title="<?= hesc(ucfirst($cor)) ?>"
                                    aria-label="Cor <?= hesc($cor) ?>"></button>
<?php endforeach; ?>
                        </div>
                    </fieldset>

                    <!-- ---------- Ícone ---------- -->
                    <fieldset class="campo cad-escolha">
                        <legend>Ícone <span class="campo__opcional">(opcional)</span></legend>
                        <div class="cad-icones" id="exercicioMateriaIcones" role="radiogroup" aria-label="Ícone da matéria">
                            <button type="button" class="cad-icone cad-icone--nenhum" data-icone=""
                                    role="radio" aria-checked="true" title="Sem ícone" aria-label="Sem ícone">—</button>
<?php foreach (ICONES_CADERNO_KOSMOS as $ic): ?>
                            <button type="button" class="cad-icone" data-icone="<?= hesc($ic) ?>"
                                    role="radio" aria-checked="false"
                                    aria-label="Ícone <?= hesc($ic) ?>"><?= hesc($ic) ?></button>
<?php endforeach; ?>
                        </div>
                    </fieldset>

                    <div class="msg" id="msgExercicioMateria" hidden></div>

                    <div class="modal__actions">
                        <button type="button" class="dash-btn dash-btn--danger" id="exercicioMateriaApagar" hidden>Excluir</button>
                        <button type="button" class="dash-btn dash-btn--outline" id="exercicioMateriaCancelar">Cancelar</button>
                        <button type="submit" class="dash-btn dash-btn--primary" id="exercicioMateriaSalvar">Criar matéria</button>
                    </div>
                </form>
            </div>
        </div>