<?php
// ============================================================
//  KOSMOS — Modal "nova prova" (parte reaproveitada)
//  Incluído pelo Início. Quem abre, valida e envia é o
//  js/inicio.js (bloco PROVAS).
//
//  A lista de matérias vem de Backend/php/materias.php — a mesma
//  que os cadernos e os baralhos usam. Campo livre aqui criaria
//  "Biologia", "biologia" e "Bio" como três matérias diferentes,
//  e a filtragem por matéria que já existe no app deixaria de
//  funcionar.
// ============================================================
if (!isset($USUARIO)) {
    http_response_code(403);
    exit('Esta página não é acessada direto.');
}
require_once __DIR__ . '/../../../../Backend/php/materias.php';
?>
        <div class="modal" id="modalProva" role="dialog" aria-modal="true" aria-labelledby="provaTitulo">
            <div class="modal__box">
                <div class="modal__head">
                    <h3 id="provaTitulo">Nova prova</h3>
                    <button class="modal__close" type="button" id="provaFechar" aria-label="Fechar">&times;</button>
                </div>

                <form class="modal__form" id="provaForm" novalidate>
                    <div class="campo">
                        <label for="provaNome">Nome da prova</label>
                        <input type="text" id="provaNome" maxlength="80" required
                               placeholder="Ex.: Prova bimestral de Biologia"
                               autocomplete="off">
                    </div>

                    <div class="campo">
                        <label for="provaMateria">Matéria <span class="campo__opcional">(opcional)</span></label>
                        <select id="provaMateria">
                            <option value="">Sem matéria</option>
                            <?php foreach (MATERIAS_KOSMOS as $m): ?>
                            <option value="<?= hesc($m) ?>"><?= hesc($m) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="campo">
                        <label for="provaData">Quando é</label>
                        <!-- O `min` é preenchido pelo js/inicio.js com a data
                             do NAVEGADOR, não daqui. `date()` em PHP usaria o
                             fuso do servidor — que neste projeto é
                             Europe/Berlin — e à noite, no Brasil, já seria
                             "amanhã" lá: o seletor bloquearia justamente o dia
                             de hoje. O relógio certo para uma escolha da pessoa
                             é o relógio dela.
                             Quem confere de verdade é o servidor, com CURDATE()
                             do MySQL — o `min` é só gentileza da interface. -->
                        <input type="date" id="provaData" required>
                    </div>

                    <p class="msg" id="provaMsg" hidden></p>

                    <div class="modal__actions">
                        <button type="button" class="dash-btn dash-btn--outline" id="provaCancelar">Cancelar</button>
                        <button type="submit" class="dash-btn dash-btn--primary" id="provaSalvar">Adicionar</button>
                    </div>
                </form>
            </div>
        </div>
