<?php
// ============================================================
//  KOSMOS — Modal de Pesquisa / Onboarding Inicial
//  Arquivo: Frontend/pages/dashboard/partes/modal-onboarding.php
//  Apresentado aos novos alunos para calibrar seu universo de estudos:
//    1. Objetivo principal (ENEM, Concursos, Reforço...)
//    2. Matérias de maior foco
//    3. Meta diária de estudos
// ============================================================
?>
<div class="onb-overlay" id="onboardingModal" aria-modal="true" role="dialog" aria-label="Pesquisa de perfil inicial">
    <div class="onb-card">
        <!-- Barra de Progresso Superior -->
        <div class="onb-topo">
            <div class="onb-progresso-trilha">
                <div class="onb-progresso-barra" id="onbBarraProgresso" style="width: 33.33%;"></div>
            </div>
            <div class="onb-etapa-badge" id="onbBadgeEtapa">Etapa 1 de 3</div>
        </div>

        <!-- Mascote Guia -->
        <div class="onb-mascote-area">
            <div class="mascote" id="onbMascote" data-mascote aria-hidden="true"></div>
        </div>

        <form class="onb-form" id="onbForm" onsubmit="return false;">
            <!-- ETAPA 1: OBJETIVO PRINCIPAL -->
            <div class="onb-passo onb-passo--ativo" data-etapa="1">
                <h2 class="onb-titulo" id="onbTitulo">Qual é o seu objetivo principal?</h2>
                <p class="onb-sub">Vamos personalizar suas recomendações e ferramentas com base no seu foco.</p>

                <div class="onb-opcoes-grid onb-opcoes-grid--objetivo">
                    <label class="onb-opcao-card">
                        <input type="radio" name="onb_objetivo" value="vestibular_enem" checked>
                        <div class="onb-opcao-conteudo">
                            <span class="onb-opcao-icone"><svg class="ico" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8.6"/><circle cx="12" cy="12" r="4.6"/><circle cx="12" cy="12" r="1.1" fill="currentColor" stroke="none"/></svg></span>
                            <div class="onb-opcao-textos">
                                <strong>ENEM & Vestibulares</strong>
                                <span>Foco em simulados, redação e provas concorridas</span>
                            </div>
                        </div>
                    </label>

                    <label class="onb-opcao-card">
                        <input type="radio" name="onb_objetivo" value="concursos">
                        <div class="onb-opcao-conteudo">
                            <span class="onb-opcao-icone"><svg class="ico" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3.8h8v5.4a4 4 0 0 1-8 0V3.8Z"/><path d="M8 5.4H5.4v1.4A3.2 3.2 0 0 0 8 9.9M16 5.4h2.6v1.4A3.2 3.2 0 0 1 16 9.9"/><path d="M12 13.2v3.4M9.4 20.2h5.2l-.7-3.6H10.1l-.7 3.6Z"/></svg></span>
                            <div class="onb-opcao-textos">
                                <strong>Concursos Públicos</strong>
                                <span>Constância diária, memorização e questões</span>
                            </div>
                        </div>
                    </label>

                    <label class="onb-opcao-card">
                        <input type="radio" name="onb_objetivo" value="ensino_medio">
                        <div class="onb-opcao-conteudo">
                            <span class="onb-opcao-icone"><svg class="ico" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6.4A1.6 1.6 0 0 1 5.6 4.8H9a2 2 0 0 1 2 2v11.4a1.7 1.7 0 0 0-1.7-1.7H5.6A1.6 1.6 0 0 1 4 14.9V6.4Z"/><path d="M20 6.4a1.6 1.6 0 0 0-1.6-1.6H15a2 2 0 0 0-2 2v11.4a1.7 1.7 0 0 1 1.7-1.7h3.7a1.6 1.6 0 0 0 1.6-1.6V6.4Z"/></svg></span>
                            <div class="onb-opcao-textos">
                                <strong>Reforço no Ensino Médio</strong>
                                <span>Melhorar notas e entender matérias difíceis</span>
                            </div>
                        </div>
                    </label>

                    <label class="onb-opcao-card">
                        <input type="radio" name="onb_objetivo" value="faculdade">
                        <div class="onb-opcao-conteudo">
                            <span class="onb-opcao-icone"><svg class="ico" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4 2.6 8.4 12 12.8l9.4-4.4L12 4Z"/><path d="M6.6 10.6v4.8c0 1.6 2.4 2.9 5.4 2.9s5.4-1.3 5.4-2.9v-4.8M21.4 8.4v5.2"/></svg></span>
                            <div class="onb-opcao-textos">
                                <strong>Faculdade / Ensino Superior</strong>
                                <span>Organizar leituras densas, prazos e resumos</span>
                            </div>
                        </div>
                    </label>

                    <label class="onb-opcao-card">
                        <input type="radio" name="onb_objetivo" value="habito">
                        <div class="onb-opcao-conteudo">
                            <span class="onb-opcao-icone"><svg class="ico" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M13.4 2.6 5.2 13.4h5.4L10.6 21.4 18.8 10.6h-5.4l0-8Z"/></svg></span>
                            <div class="onb-opcao-textos">
                                <strong>Criar Hábito de Estudo</strong>
                                <span>Estudar um pouco todos os dias sem procrastinar</span>
                            </div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- ETAPA 2: MATÉRIAS PRIORITÁRIAS -->
            <div class="onb-passo" data-etapa="2">
                <h2 class="onb-titulo">Quais matérias você quer focar?</h2>
                <p class="onb-sub">Selecione as disciplinas que você mais estuda ou precisa reforçar.</p>

                <div class="onb-materias-grid">
                    <?php foreach (MATERIAS_KOSMOS as $mat): ?>
                        <label class="onb-materia-chip">
                            <input type="checkbox" name="onb_materias[]" value="<?= hesc($mat) ?>"
                                   <?= in_array($mat, $PREF['materias'], true) ? 'checked' : '' ?>>
                            <span class="onb-materia-nome"><?= hesc($mat) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <span class="onb-dica-selecao">Você pode escolher quantas quiser (ou ajustar depois).</span>
            </div>

            <!-- ETAPA 3: META DIÁRIA DE FOCO -->
            <div class="onb-passo" data-etapa="3">
                <h2 class="onb-titulo">Qual é a sua meta diária de estudos?</h2>
                <p class="onb-sub">Defina o tempo de dedicação por dia para acompanhar sua sequência de estudos.</p>

                <div class="onb-opcoes-grid onb-opcoes-grid--meta">
                    <label class="onb-opcao-card">
                        <input type="radio" name="onb_meta" value="30">
                        <div class="onb-opcao-conteudo">
                            <span class="onb-opcao-icone"><svg class="ico" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21.2v-6.8"/><path d="M12 14.4c0-3.4 2.5-6.1 5.6-6.1 0 3.4-2.5 6.1-5.6 6.1Z"/><path d="M12 16.6c0-2.8-2-5.1-4.9-5.1 0 2.8 2 5.1 4.9 5.1Z"/></svg></span>
                            <div class="onb-opcao-textos">
                                <strong>30 minutos / dia</strong>
                                <span>Ritmo leve e sustentável para criar o hábito</span>
                            </div>
                        </div>
                    </label>

                    <label class="onb-opcao-card">
                        <input type="radio" name="onb_meta" value="60" checked>
                        <div class="onb-opcao-conteudo">
                            <span class="onb-opcao-icone"><svg class="ico" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8.6"/><path d="M12 7.2V12l3.1 2"/></svg></span>
                            <div class="onb-opcao-textos">
                                <strong>1 hora / dia</strong>
                                <span>O equilíbrio ideal entre rendimento e foco (Recomendado)</span>
                            </div>
                            <span class="onb-tag-destaque">Recomendado</span>
                        </div>
                    </label>

                    <label class="onb-opcao-card">
                        <input type="radio" name="onb_meta" value="120">
                        <div class="onb-opcao-conteudo">
                            <span class="onb-opcao-icone"><svg class="ico" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21.2a5.6 5.6 0 0 0 5.6-5.6c0-4.6-5.6-9.2-5.6-9.2S6.4 11 6.4 15.6A5.6 5.6 0 0 0 12 21.2Z"/><path d="M12 21.2a2.4 2.4 0 0 0 2.4-2.4c0-2-2.4-4.1-2.4-4.1s-2.4 2.1-2.4 4.1a2.4 2.4 0 0 0 2.4 2.4Z"/></svg></span>
                            <div class="onb-opcao-textos">
                                <strong>2 horas / dia</strong>
                                <span>Ritmo acelerado para reta final e editais</span>
                            </div>
                        </div>
                    </label>

                    <label class="onb-opcao-card">
                        <input type="radio" name="onb_meta" value="180">
                        <div class="onb-opcao-conteudo">
                            <span class="onb-opcao-icone"><svg class="ico" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.6c3.1 2.3 4.9 5.7 4.9 9.5l-1.9 4.4H9L7.1 12.1c0-3.8 1.8-7.2 4.9-9.5Z"/><circle cx="12" cy="10.1" r="2"/><path d="M9 16.5 5.9 18.4l1.4-4.7M15 16.5l3.1 1.9-1.4-4.7M10.8 19.6c0 1 1.2 1.9 1.2 1.9s1.2-.9 1.2-1.9"/></svg></span>
                            <div class="onb-opcao-textos">
                                <strong>3+ horas / dia</strong>
                                <span>Modo imersão profunda para vestibulandos dedicados</span>
                            </div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Rodapé de Ações do Modal -->
            <div class="onb-rodape">
                <button type="button" class="onb-btn onb-btn--ghost" id="onbBtnVoltar" hidden>
                    ← Voltar
                </button>
                <div class="onb-rodape-espaco"></div>
                <button type="button" class="onb-btn onb-btn--pular" id="onbBtnPular">
                    Pular por enquanto
                </button>
                <button type="button" class="onb-btn onb-btn--primary" id="onbBtnAvancar">
                    Continuar →
                </button>
            </div>
        </form>
    </div>
</div>
