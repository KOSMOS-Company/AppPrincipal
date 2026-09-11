<?php
// ============================================================
//  KOSMOS — Modal de criar/personalizar caderno (parte reaproveitada)
//  Usado pela estante (resumos.php) e por dentro de um caderno
//  (caderno.php, para mexer no que está aberto).
//  Quem abre, salva e apaga é o js/caderno-form.js.
//
//  O caderno é o "deck" dos resumos. Além de nome e matéria, ele
//  tem identidade visual — cor, ícone, descrição e capa — porque o
//  que faz achar o caderno certo numa estante cheia é bater o olho,
//  não ler nome por nome.
//
//  A cor reaproveita a paleta do avatar (.avatar-cor--*, já no
//  dashboard.css) e o ícone sai de uma lista fechada; as duas listas
//  moram em Backend/php/materias.php, que é também quem valida.
// ============================================================
if (!isset($USUARIO, $PREF)) {
    http_response_code(403);
    exit('Esta página não é acessada direto.');
}
?>
        <div class="modal" id="modalCaderno" role="dialog" aria-modal="true"
             aria-labelledby="modalCadernoTitulo">
            <div class="modal__box modal__box--caderno">
                <div class="modal__head">
                    <h3 id="modalCadernoTitulo">Novo caderno</h3>
                    <button class="modal__close" type="button" id="cadernoFechar" aria-label="Fechar">&times;</button>
                </div>

                <form class="modal__form" id="formCaderno">
                    <input type="hidden" id="cadernoId" value="">

                    <!-- Prévia: o cartão como ele vai ficar na estante.
                         Muda junto com os campos, para a escolha ser
                         pelo resultado e não pelo nome da cor. -->
                    <div class="cad-previa" id="cadPrevia" aria-hidden="true">
                        <span class="cad-previa__lombada"></span>
                        <span class="cad-previa__capa" id="cadPreviaCapa" hidden></span>
                        <div class="cad-previa__body">
                            <div class="cad-previa__topo">
                                <span class="cad-previa__icone" id="cadPreviaIcone" hidden></span>
                                <span class="materia-tag" id="cadPreviaMateria">Matéria</span>
                            </div>
                            <strong id="cadPreviaNome">Nome do caderno</strong>
                            <span class="cad-previa__desc" id="cadPreviaDesc" hidden></span>
                        </div>
                    </div>

                    <div class="campo">
                        <label for="cadernoNome">Nome do caderno</label>
                        <input id="cadernoNome" type="text" maxlength="120"
                               placeholder="Ex: Biologia — 2º trimestre" required>
                    </div>

                    <div class="campo">
                        <label for="cadernoMateria">Matéria</label>
                        <select id="cadernoMateria" required>
<?php foreach (MATERIAS_KOSMOS as $m): ?>
                            <option value="<?= hesc($m) ?>"<?= in_array($m, $PREF['materias'], true) ? ' data-favorita="1"' : '' ?>><?= hesc($m) ?></option>
<?php endforeach; ?>
                        </select>
                        <span class="campo__dica">Os resumos deste caderno vão herdar esta matéria.</span>
                    </div>

                    <div class="campo">
                        <label for="cadernoDescricao">Descrição <span class="campo__opcional">(opcional)</span></label>
                        <input id="cadernoDescricao" type="text" maxlength="160"
                               placeholder="Ex: tudo que cai na prova de setembro">
                    </div>

                    <!-- ---------- Cor ---------- -->
                    <fieldset class="campo cad-escolha">
                        <legend>Cor</legend>
                        <div class="cad-cores" id="cadCores" role="radiogroup" aria-label="Cor do caderno">
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
                        <div class="cad-icones" id="cadIcones" role="radiogroup" aria-label="Ícone do caderno">
                            <button type="button" class="cad-icone cad-icone--nenhum" data-icone=""
                                    role="radio" aria-checked="true" title="Sem ícone" aria-label="Sem ícone">—</button>
<?php foreach (ICONES_CADERNO_KOSMOS as $ic): ?>
                            <button type="button" class="cad-icone" data-icone="<?= hesc($ic) ?>"
                                    role="radio" aria-checked="false"
                                    aria-label="Ícone <?= hesc($ic) ?>"><?= hesc($ic) ?></button>
<?php endforeach; ?>
                        </div>
                    </fieldset>

                    <!-- ---------- Capa ---------- -->
                    <div class="campo">
                        <label for="cadernoCapa">Capa <span class="campo__opcional">(opcional; a imagem é reduzida sozinha)</span></label>

                        <div class="cad-capa" id="cadCapa">
                            <label class="img-solta img-solta--capa" for="cadernoCapa" id="capaSolta">
                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.8"/><circle cx="8.5" cy="10" r="1.6" stroke="currentColor" stroke-width="1.5"/><path d="M4 17l5-4 3 2.5 3-2.5 5 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                <span><strong>Escolher uma capa</strong></span>
                                <small>JPG, PNG ou WEBP</small>
                            </label>
                            <input id="cadernoCapa" type="file" accept="image/jpeg,image/png,image/webp" hidden>

                            <div class="cad-capa__previa" id="capaPrevia" hidden>
                                <img id="capaImg" src="" alt="">
                                <button type="button" class="cad-capa__x" id="capaRemover"
                                        title="Remover a capa" aria-label="Remover a capa">
                                    <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M6 6l8 8M14 6l-8 8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                </button>
                            </div>
                        </div>
                        <span class="campo__dica" id="capaDica" hidden>A capa sobe quando você salvar.</span>
                    </div>

                    <div class="msg" id="msgCaderno" hidden></div>

                    <div class="modal__actions">
                        <button type="button" class="dash-btn dash-btn--danger" id="cadernoApagar" hidden>Excluir</button>
                        <button type="button" class="dash-btn dash-btn--outline" id="cadernoCancelar">Cancelar</button>
                        <button type="submit" class="dash-btn dash-btn--primary" id="cadernoSalvar">Criar caderno</button>
                    </div>
                </form>
            </div>
        </div>
