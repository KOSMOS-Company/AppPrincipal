<?php
// ============================================================
//  KOSMOS — Modal "nova prova" (parte reaproveitada)
//  Incluído pela página Provas (provas.php). Quem abre, valida e
//  envia é o js/provas.js.
//
//  O MESMO modal cria e edita: com id preenchido, salvar atualiza.
//  Dois modais quase iguais é o tipo de duplicação que sai do ar
//  sozinha — um dia alguém mexe num e esquece o outro.
//
//  A lista de matérias vem de Backend/php/materias.php — a mesma
//  que os cadernos e os baralhos usam — mas o campo também aceita
//  texto livre (input+datalist). Quem evita "Biologia"/"biologia"
//  virarem duas matérias diferentes é materiaCanonica() no servidor,
//  que normaliza para o valor canônico da lista quando os dois
//  batem ignorando maiúsculas. Filtrar por matéria continua
//  funcionando — inclusive a que acha o material de estudo da prova.
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
                    <input type="hidden" id="provaId" value="">

                    <div class="campo">
                        <label for="provaNome">Nome da prova</label>
                        <input type="text" id="provaNome" maxlength="80" required
                               placeholder="Ex.: Prova bimestral de Biologia"
                               autocomplete="off">
                    </div>

                    <div class="campo">
                        <label for="provaMateria">Matéria <span class="campo__opcional">(opcional)</span></label>
                        <!-- input+datalist: escolhe da lista OU digita a sua.
                             Vazio = "Sem matéria" (o campo é opcional). -->
                        <input id="provaMateria" type="text" list="listaMateriasProva"
                               maxlength="40" autocomplete="off"
                               placeholder="Ex: Biologia (ou escreva a sua)">
                        <datalist id="listaMateriasProva">
                            <?php foreach (MATERIAS_KOSMOS as $m): ?>
                            <option value="<?= hesc($m) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <span class="campo__dica">Com a matéria escolhida, a prova já mostra seus cadernos e baralhos dela.</span>
                    </div>

                    <div class="campo">
                        <label for="provaData">Quando é</label>
                        <!-- O `min` é preenchido pelo js/provas.js com a data
                             do NAVEGADOR, não daqui. `date()` em PHP usaria o
                             fuso do servidor — que neste projeto é
                             Europe/Berlin — e à noite, no Brasil, já seria
                             "amanhã" lá: o seletor bloquearia justamente o dia
                             de hoje. O relógio certo para uma escolha da pessoa
                             é o relógio dela.
                             Quem confere de verdade é o servidor, com CURDATE()
                             do MySQL — o `min` é só gentileza da interface.
                             Na edição o `min` sai: consertar a data de uma
                             prova que já passou é uso legítimo. -->
                        <input type="date" id="provaData" required>
                    </div>

                    <!-- Os assuntos que caem. Só aparece ao CRIAR: depois de
                         a prova existir, cada assunto vira uma caixinha na
                         lista dela, e editar aqui desfaria o que já foi
                         marcado como estudado. -->
                    <div class="campo" id="provaTopicosCampo">
                        <label for="provaTopicos">O que cai <span class="campo__opcional">(opcional)</span></label>
                        <textarea id="provaTopicos" rows="4"
                                  placeholder="Um assunto por linha:&#10;Leis de Newton&#10;Trabalho e energia"></textarea>
                        <span class="campo__dica">Um por linha. Depois é só ir marcando o que já estudou.</span>
                    </div>

                    <div class="campo">
                        <label for="provaAnotacoes">Anotações <span class="campo__opcional">(opcional)</span></label>
                        <textarea id="provaAnotacoes" rows="3" maxlength="1000"
                                  placeholder="Páginas do livro, o que o professor falou, peso da nota…"></textarea>
                    </div>

                    <p class="msg" id="provaMsg" hidden></p>

                    <div class="modal__actions">
                        <button type="button" class="dash-btn dash-btn--outline" id="provaCancelar">Cancelar</button>
                        <button type="submit" class="dash-btn dash-btn--primary" id="provaSalvar">Adicionar</button>
                    </div>
                </form>
            </div>
        </div>
