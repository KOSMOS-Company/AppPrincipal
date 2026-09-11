<?php
// ============================================================
//  KOSMOS — Modal de escrever/editar resumo (parte reaproveitada)
//  Usado pela lista de cadernos (resumos.php), por dentro de um
//  caderno (caderno.php) e pela prévia (resumo.php).
//  Quem abre, salva e apaga é o js/resumo-form.js.
//
//  Um resumo pode ser texto, fotos do caderno de papel, ou os dois.
//  Só o tema (título) é sempre obrigatório.
//
//  A matéria é do CADERNO, não do resumo: quando há caderno
//  escolhido o campo de matéria fica escondido e o resumo herda a
//  dele. O campo só aparece para resumo solto ("Sem caderno"), que
//  não tem de onde herdar.
// ============================================================
if (!isset($USUARIO, $PREF)) {
    http_response_code(403);
    exit('Esta página não é acessada direto.');
}

/* Os cadernos do usuário para o <select>. Quando a página já os
   carregou (resumos.php, caderno.php), reaproveita; senão busca aqui,
   pelo mesmo helper — para o formato ser sempre o mesmo. */
if (!isset($CADERNOS)) {
    $CADERNOS = [];
    if (empty($ERRO_BANCO)) {
        try {
            $CADERNOS = listarCadernos($pdo, (int) $USUARIO['id']);
        } catch (PDOException $e) {
            $CADERNOS = [];
        }
    }
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
                        <label for="titulo">Tema</label>
                        <input id="titulo" type="text" placeholder="Ex: Leis de Newton" maxlength="140" required>
                    </div>

                    <div class="campo">
                        <label for="resumoCaderno">Caderno</label>
                        <select id="resumoCaderno">
                            <option value="">Sem caderno</option>
<?php foreach ($CADERNOS as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" data-materia="<?= hesc($c['materia']) ?>"><?= $c['icone'] !== '' ? hesc($c['icone']) . ' ' : '' ?><?= hesc($c['nome']) ?> · <?= hesc($c['materia']) ?></option>
<?php endforeach; ?>
                        </select>
                        <span class="campo__dica" id="dicaCaderno" hidden></span>
                    </div>

                    <!-- Só para resumo solto: dentro de um caderno a
                         matéria vem dele (o JS esconde este campo). -->
                    <div class="campo" id="campoMateria">
                        <label for="materia">Matéria</label>
                        <select id="materia">
<?php foreach (MATERIAS_KOSMOS as $m): ?>
                            <option value="<?= hesc($m) ?>"<?= in_array($m, $PREF['materias'], true) ? ' data-favorita="1"' : '' ?>><?= hesc($m) ?></option>
<?php endforeach; ?>
                        </select>
                    </div>

                    <div class="campo">
                        <label for="conteudo">Resumo escrito <span class="campo__opcional">(opcional se anexar imagem)</span></label>
                        <textarea id="conteudo" rows="8" placeholder="Escreva com suas palavras o que você estudou..."></textarea>
                        <span class="campo__dica"><span id="contador">0</span> caracteres</span>
                    </div>

                    <!-- ---------- Imagens ---------- -->
                    <div class="campo">
                        <label for="imagens">Imagens <span class="campo__opcional">(até <?= RS_MAX_IMAGENS ?>; fotos grandes são reduzidas sozinhas)</span></label>

                        <label class="img-solta" for="imagens" id="imgSolta">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 16V4m0 0L8 8m4-4 4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                            <span><strong>Escolher imagens</strong> ou fotografe o seu caderno</span>
                            <small>JPG, PNG ou WEBP</small>
                        </label>
                        <input id="imagens" type="file" accept="image/jpeg,image/png,image/webp"
                               multiple hidden>

                        <!-- Miniaturas: as já salvas e as escolhidas
                             que sobem depois de o resumo existir -->
                        <div class="img-grade" id="imgGrade" hidden></div>
                        <span class="campo__dica" id="imgDica" hidden></span>
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
