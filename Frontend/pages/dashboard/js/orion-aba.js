/* ============================================================
   KOSMOS — orion-aba.js
   Controle e lógica do Chatbot dedicado do Orion.
   - Padrão Chatbot normal: área de mensagens rolável independente.
   - Input fixo no rodapé: NUNCA move ou abaixa a tela do usuário ao digitar.
   - Respostas inteligentes estruturadas e reações de humor do mascote 3D.
   ============================================================ */

(function () {
    'use strict';

    // Base de Conhecimento Inteligente do Orion
    const RESPOSTAS_ORION = [
        {
            chaves: ['pomodoro', 'foco', 'tempo', 'minutos', '25', 'descanso', 'temporizador'],
            humor: 'foco',
            resposta: `O **Método Pomodoro** é a ferramenta ideal no Kosmos para manter foco absoluto e não cansar a mente! ⏱️\n\n` +
                      `✦ **Como aplicar com alta produtividade:**\n` +
                      `1. **25 minutos de Foco Total:** escolha uma matéria e estude sem olhar nenhuma notificação.\n` +
                      `2. **5 minutos de Pausa Curta:** levante, beba água e relaxe os olhos.\n` +
                      `3. **A cada 4 ciclos:** faça uma pausa maior de 15 a 30 minutos.\n\n` +
                      `💡 *Dica:* Inicie o cronômetro na aba **Pomodoro** a qualquer momento!`
        },
        {
            chaves: ['flashcard', 'flashcards', 'repetição', 'memorizar', 'decorar', 'esquecer', 'anki'],
            humor: 'curioso',
            resposta: `Os **Flashcards** ativam a *Recordação Ativa* e a *Repetição Espaçada*, a técnica de memorização mais comprovada pela neurociência! 🎴\n\n` +
                      `✦ **Boas práticas essenciais:**\n` +
                      `• **Perguntas objetivas:** evite blocos longos de texto no verso.\n` +
                      `• **Raciocínio de Causa e Efeito:** crie cards perguntando *"Por que isso ocorre?"* ou *"Qual a fórmula de X?"*.\n` +
                      `• **Consistência diária:** 10 minutos por dia superam horas de estudo acumuladas na véspera da prova.\n\n` +
                      `Acesse a aba **Flashcards** para treinar e criar seus baralhos!`
        },
        {
            chaves: ['resumo', 'resumos', 'caderno', 'cadernos', 'anotar', 'anotação', 'feynman'],
            humor: 'feliz',
            resposta: `Fazer um **resumo poderoso** é sintetizar com suas próprias palavras para fixar o aprendizado! 📝\n\n` +
                      `✦ **Técnica Feynman:**\n` +
                      `1. Leia o tema completo primeiro, sem grifar tudo.\n` +
                      `2. Escreva o resumo como se estivesse explicando o conteúdo para um colega leigo.\n` +
                      `3. Onde você travar ou usar termos difíceis demais, volte ao livro e simplifique.\n\n` +
                      `Na aba **Biblioteca**, você pode organizar seus cadernos e registrar anotações estruturadas!`
        },
        {
            chaves: ['procrastinar', 'procrastinação', 'sem foco', 'preguiça', 'motivação', 'travado', 'começar'],
            humor: 'pensativo',
            resposta: `A procrastinação quase sempre acontece porque a tarefa parece grande demais para a mente encarar. ⚡\n\n` +
                      `✦ **Aplique a Regra dos 2 Minutos:**\n` +
                      `Em vez de pensar em estudar 3 horas, assuma o compromisso de ler apenas **uma página** ou resolver **um exercício**.\n\n` +
                      `Assim que você quebra a inércia inicial, seu cérebro entra em estado de engajamento e tudo flui com facilidade. Bora começar agora?`
        },
        {
            chaves: ['enem', 'vestibular', 'fuvest', 'unicamp', 'redação', 'prova', 'provas', 'concurso'],
            humor: 'foco',
            resposta: `No **ENEM e vestibulares**, estratégia e treino superam a pressa! 🎯\n\n` +
                      `✦ **Pilares fundamentais:**\n` +
                      `• **Matemática básica e Redação:** são as notas que mais alavancam sua média geral.\n` +
                      `• **Simulados com tempo cronometrado:** acostume-se com o cansaço mental do dia do exame.\n` +
                      `• **Aprenda com os erros:** mapeie cada questão que errou e registre nos seus cadernos o motivo.\n\n` +
                      `Confira as contagens regressivas e datas na aba **Provas**!`
        },
        {
            chaves: ['oi', 'olá', 'ola', 'bom dia', 'boa tarde', 'boa noite', 'opa', 'quem é você', 'quem e voce'],
            humor: 'feliz',
            resposta: `Olá! Eu sou o **Orion**, seu copiloto acadêmico do Kosmos! 🪐✦\n\n` +
                      `Estou aqui para responder suas perguntas sobre matérias, técnicas de estudo, memorização e uso das ferramentas da plataforma. O que vamos estudar hoje?`
        },
        {
            chaves: ['obrigado', 'valeu', 'agradeço', 'ajudou', 'perfeito', 'top', 'show'],
            humor: 'timido',
            resposta: `Por nada! É uma alegria enorme poder te apoiar nessa jornada de estudos! 💜✨\n\n` +
                      `Sempre que precisar de ajuda com alguma matéria ou técnica de estudo, estarei por aqui. Bons estudos!`
        }
    ];

    const RESPOSTA_PADRAO = {
        humor: 'curioso',
        resposta: `Excelente pergunta! ✦\n\n` +
                  `Para dominar esse conteúdo com máxima eficiência, recomendo este roteiro:\n` +
                  `1. **Fundamentação:** elabore um resumo conciso na aba **Biblioteca** com os pontos principais.\n` +
                  `2. **Fixação:** monte flashcards com perguntas e respostas diretas para revisar periodicamente.\n` +
                  `3. **Prática:** teste seus conhecimentos cronometrando blocos de estudo no **Pomodoro**.\n\n` +
                  `Logo mais, você poderá conversar diretamente com meu modelo de IA integrado para explicações aprofundadas em tempo real!`
    };

    document.addEventListener('DOMContentLoaded', () => {
        const corpo = document.getElementById('orionChatCorpo');
        const heroInicial = document.getElementById('orionHeroInicial');
        const listaMensagens = document.getElementById('orionListaMensagens');
        const digitando = document.getElementById('orionDigitandoBalao');
        const form = document.getElementById('orionChatForm');
        const input = document.getElementById('orionChatInput');
        const btnEnviar = document.getElementById('orionChatEnviar');
        const btnLimpar = document.getElementById('orionBtnLimpar');
        const mascoteHeader = document.getElementById('orionHeaderMascote');
        const mascoteCentral = document.getElementById('orionMascoteCentral');
        const sugestoes = document.querySelectorAll('.orion-card-sugestao');

        if (!corpo || !listaMensagens || !form || !input) return;

        // Renderiza os avatares 3D do mascote
        if (window.kosmosMascote) {
            if (mascoteHeader) {
                window.kosmosMascote.renderizar(mascoteHeader, { humor: 'normal', tamanho: 30 });
            }
            if (mascoteCentral) {
                window.kosmosMascote.renderizar(mascoteCentral, { humor: 'normal', tamanho: 120 });
            }
        }

        // Histórico de mensagens na sessão
        const STORAGE_KEY = 'kosmos_orion_chat_session';
        let historico = [];

        try {
            const salvo = sessionStorage.getItem(STORAGE_KEY);
            if (salvo) historico = JSON.parse(salvo);
        } catch (e) {}

        function salvarHistorico() {
            try {
                sessionStorage.setItem(STORAGE_KEY, JSON.stringify(historico));
            } catch (e) {}
        }

        function definirHumor(humor) {
            if (window.kosmosMascote) {
                if (mascoteHeader) window.kosmosMascote.definirHumor(mascoteHeader, humor);
                if (mascoteCentral) window.kosmosMascote.definirHumor(mascoteCentral, humor);
            }
        }

        function formatarTexto(txt) {
            return txt
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.*?)\*/g, '<em>$1</em>')
                .replace(/\n\n/g, '</p><p>')
                .replace(/\n/g, '<br>')
                .replace(/^(.+)$/gm, '<p>$1</p>')
                .replace(/<p><\/p>/g, '');
        }

        function scrollParaFim() {
            // Rola APENAS o container interno do chat, NUNCA a janela
            corpo.scrollTo({
                top: corpo.scrollHeight,
                behavior: 'smooth'
            });
        }

        function renderizarMensagem(tipo, conteudo, humor = 'normal') {
            if (heroInicial) heroInicial.style.display = 'none';

            const row = document.createElement('div');
            row.className = `orion-msg-row orion-msg-row--${tipo}`;

            if (tipo === 'orion') {
                const avatar = document.createElement('div');
                avatar.className = 'orion-msg-avatar';
                const mini = document.createElement('div');
                mini.className = 'mascote';
                avatar.appendChild(mini);
                row.appendChild(avatar);

                if (window.kosmosMascote) {
                    window.kosmosMascote.renderizar(mini, { humor: humor, tamanho: 34 });
                }
            }

            const balao = document.createElement('div');
            balao.className = 'orion-msg-balao';
            balao.innerHTML = formatarTexto(conteudo);
            row.appendChild(balao);

            listaMensagens.appendChild(row);
            scrollParaFim();
        }

        // Restaura histórico anterior se houver
        if (historico.length > 0) {
            if (heroInicial) heroInicial.style.display = 'none';
            historico.forEach(m => renderizarMensagem(m.tipo, m.conteudo, m.humor || 'normal'));
            scrollParaFim();
        }

        function buscarResposta(texto) {
            const limpo = texto.toLowerCase().trim();
            for (const item of RESPOSTAS_ORION) {
                const bateu = item.chaves.some(k => limpo.includes(k));
                if (bateu) return item;
            }
            return RESPOSTA_PADRAO;
        }

        function enviarPergunta() {
            const texto = input.value.trim();
            if (!texto) return;

            input.value = '';
            input.focus();

            // Adiciona a pergunta do estudante
            renderizarMensagem('usuario', texto);
            historico.push({ tipo: 'usuario', conteudo: texto });
            salvarHistorico();

            // Reação do mascote (pensativo durante a digitação)
            definirHumor('pensativo');

            // Exibe os três pontinhos de digitação dentro do chat
            if (digitando) {
                digitando.style.display = 'inline-flex';
                listaMensagens.appendChild(digitando);
                scrollParaFim();
            }

            const delay = Math.min(1600, Math.max(600, texto.length * 18));
            setTimeout(() => {
                if (digitando) digitando.style.display = 'none';

                const resultado = buscarResposta(texto);
                definirHumor(resultado.humor || 'feliz');
                renderizarMensagem('orion', resultado.resposta, resultado.humor || 'feliz');

                historico.push({ tipo: 'orion', conteudo: resultado.resposta, humor: resultado.humor || 'feliz' });
                salvarHistorico();

                setTimeout(() => definirHumor('normal'), 4000);
            }, delay);
        }

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            enviarPergunta();
        });

        // Chips de perguntas rápidas
        sugestoes.forEach(card => {
            card.addEventListener('click', () => {
                const p = card.getAttribute('data-pergunta');
                if (p) {
                    input.value = p;
                    enviarPergunta();
                }
            });
        });

        // Limpar conversa
        if (btnLimpar) {
            btnLimpar.addEventListener('click', () => {
                historico = [];
                salvarHistorico();
                listaMensagens.innerHTML = '';
                if (heroInicial) heroInicial.style.display = 'flex';
                definirHumor('normal');
                input.focus();
            });
        }
    });
})();
