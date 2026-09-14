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
<div class="onb-overlay" id="onboardingModal" aria-modal="true" role="dialog" aria-labelledby="onbTitulo">
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
                            <span class="onb-opcao-icone">🎯</span>
                            <div class="onb-opcao-textos">
                                <strong>ENEM & Vestibulares</strong>
                                <span>Foco em simulados, redação e provas concorridas</span>
                            </div>
                        </div>
                    </label>

                    <label class="onb-opcao-card">
                        <input type="radio" name="onb_objetivo" value="concursos">
                        <div class="onb-opcao-conteudo">
                            <span class="onb-opcao-icone">🏆</span>
                            <div class="onb-opcao-textos">
                                <strong>Concursos Públicos</strong>
                                <span>Constância diária, memorização e questões</span>
                            </div>
                        </div>
                    </label>

                    <label class="onb-opcao-card">
                        <input type="radio" name="onb_objetivo" value="ensino_medio">
                        <div class="onb-opcao-conteudo">
                            <span class="onb-opcao-icone">📚</span>
                            <div class="onb-opcao-textos">
                                <strong>Reforço no Ensino Médio</strong>
                                <span>Melhorar notas e entender matérias difíceis</span>
                            </div>
                        </div>
                    </label>

                    <label class="onb-opcao-card">
                        <input type="radio" name="onb_objetivo" value="faculdade">
                        <div class="onb-opcao-conteudo">
                            <span class="onb-opcao-icone">🎓</span>
                            <div class="onb-opcao-textos">
                                <strong>Faculdade / Ensino Superior</strong>
                                <span>Organizar leituras densas, prazos e resumos</span>
                            </div>
                        </div>
                    </label>

                    <label class="onb-opcao-card">
                        <input type="radio" name="onb_objetivo" value="habito">
                        <div class="onb-opcao-conteudo">
                            <span class="onb-opcao-icone">⚡</span>
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
                            <span class="onb-opcao-icone">🌱</span>
                            <div class="onb-opcao-textos">
                                <strong>30 minutos / dia</strong>
                                <span>Ritmo leve e sustentável para criar o hábito</span>
                            </div>
                        </div>
                    </label>

                    <label class="onb-opcao-card">
                        <input type="radio" name="onb_meta" value="60" checked>
                        <div class="onb-opcao-conteudo">
                            <span class="onb-opcao-icone">⏱️</span>
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
                            <span class="onb-opcao-icone">🔥</span>
                            <div class="onb-opcao-textos">
                                <strong>2 horas / dia</strong>
                                <span>Ritmo acelerado para reta final e editais</span>
                            </div>
                        </div>
                    </label>

                    <label class="onb-opcao-card">
                        <input type="radio" name="onb_meta" value="180">
                        <div class="onb-opcao-conteudo">
                            <span class="onb-opcao-icone">🚀</span>
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
                <button type="button" class="onb-btn onb-btn--ghost" id="onbBtnVoltar" style="display: none;">
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
