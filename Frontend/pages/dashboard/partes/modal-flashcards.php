<?php
// ============================================================
//  KOSMOS — Modal "gerar flashcards de um resumo" (reaproveitada)
//  Usada pela estante (resumos.php), por dentro de um caderno
//  (caderno.php) e pela leitura de um resumo (resumo.php) — as três
//  telas de onde se chega a um resumo.
//  Quem preenche, edita e envia é o js/flashcards-gerar.js.
//
//  A LISTA NASCE VAZIA de propósito. O servidor lê o resumo e
//  sugere os cartões (Backend/php/flashcards_gerar.php), mas nada é
//  gravado antes de a pessoa conferir: um cartão errado memorizado
//  é pior do que cartão nenhum, então a última palavra é sempre
//  dela — dá para editar os dois lados, tirar o que não presta e
//  acrescentar o que faltou.
// ============================================================
if (!isset($USUARIO)) {
    http_response_code(403);
    exit('Esta página não é acessada direto.');
}
?>
        <div class="modal" id="modalGerar" role="dialog" aria-modal="true" aria-labelledby="gerarTitulo">
            <div class="modal__box modal__box--gerar">
                <div class="modal__head">
                    <h3 id="gerarTitulo">Gerar flashcards</h3>
                    <button class="modal__close" type="button" id="gerarFechar" aria-label="Fechar">&times;</button>
                </div>

                <form class="modal__form" id="gerarForm" novalidate>
                    <p class="gf-fonte" id="gerarFonte"></p>

                    <!-- Estado de espera: a leitura do resumo é rápida, mas
                         "rápida" não é "instantânea" numa máquina de escola. -->
                    <p class="gf-carregando" id="gerarCarregando">Lendo seu resumo…</p>

                    <div id="gerarConteudo" hidden>
                        <div class="campo">
                            <label for="gerarDeck">Guardar em</label>
                            <select id="gerarDeck">
                                <!-- preenchido pelo JS com os baralhos da conta -->
                            </select>
                        </div>

                        <!-- Só aparece quando a escolha é "Criar um baralho
                             novo". A matéria não é perguntada: o baralho
                             nasce do resumo e herda a matéria dele. -->
                        <div class="campo" id="gerarNovoCampo" hidden>
                            <label for="gerarNovoNome">Nome do baralho novo</label>
                            <input type="text" id="gerarNovoNome" maxlength="120" autocomplete="off">
                        </div>

                        <div class="gf-cabeca">
                            <h4 id="gerarConta">Cartões</h4>
                            <button type="button" class="dash-btn dash-btn--ghost dash-btn--pequeno" id="gerarAdicionar">
                                + Cartão em branco
                            </button>
                        </div>

                        <div class="gf-lista" id="gerarLista"></div>

                        <p class="gf-vazio" id="gerarVazio" hidden>
                            Não consegui tirar cartões deste resumo automaticamente.
                            Ele funciona melhor com linhas no formato
                            <code>Termo: definição</code>, com perguntas terminadas em
                            <code>?</code> ou com trechos marcados entre <code>==</code>.
                            Você pode escrever os cartões à mão aqui mesmo.
                        </p>
                    </div>

                    <p class="msg" id="gerarMsg" hidden></p>

                    <div class="modal__actions">
                        <button type="button" class="dash-btn dash-btn--outline" id="gerarCancelar">Cancelar</button>
                        <button type="submit" class="dash-btn dash-btn--primary" id="gerarSalvar" disabled>Criar cartões</button>
                    </div>
                </form>
            </div>
        </div>
