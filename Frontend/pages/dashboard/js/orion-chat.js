/* ============================================================
   KOSMOS — orion-chat.js ("Pergunte ao Orion!")
   Chat interativo com o assistente acadêmico Orion.
   Responde com inteligência contextual e reações emocionais 3D,
   com arquitetura pronta para conexão direta com IA (Groq / LLM).
   ============================================================ */

(function () {
    'use strict';

    // Flag de expansão para quando a API da IA estiver ativa
    var CONFIG_IA_ATIVA = false;
    // Relativo à PÁGINA (Frontend/pages/dashboard/*.php), não a este .js:
    // são três níveis até a raiz, como o API do revisar.js.
    var ENDPOINT_IA = '../../../Backend/php/gerar_exercicios.php';

    var STORAGE_KEY = 'kosmos_orion_chat_historico';

    document.addEventListener('DOMContentLoaded', function () {
        var launcher = document.getElementById('orionChatLauncher');
        var chatBox = document.getElementById('orionChatBox');
        var btnFechar = document.getElementById('orionChatFechar');
        var btnLimpar = document.getElementById('orionChatLimpar');
        var form = document.getElementById('orionChatForm');
        var input = document.getElementById('orionChatInput');
        var msgsContainer = document.getElementById('orionChatMsgs');
        var typingIndicator = document.getElementById('orionTyping');
        var sugestoes = document.getElementById('orionSugestoes');

        var mascoteHeader = document.getElementById('orionChatMascote');
        var mascoteLauncher = document.getElementById('orionLauncherMascote');

        if (!launcher || !chatBox) return;

        // Monta os mascotes no launcher e no header do chat
        if (window.KosmosMascote) {
            if (mascoteLauncher) window.KosmosMascote.montar(mascoteLauncher);
            if (mascoteHeader) window.KosmosMascote.montar(mascoteHeader);
        }

        // Histórico de mensagens mantido na sessão de navegação
        var historico = [];
        try {
            var salvo = sessionStorage.getItem(STORAGE_KEY);
            if (salvo) historico = JSON.parse(salvo);
        } catch (e) { historico = []; }

        function alternarChat(abrir) {
            var deveAbrir = (typeof abrir === 'boolean') ? abrir : !chatBox.classList.contains('orion-chat--aberto');
            chatBox.classList.toggle('orion-chat--aberto', deveAbrir);
            launcher.setAttribute('aria-expanded', deveAbrir ? 'true' : 'false');
            chatBox.setAttribute('aria-hidden', deveAbrir ? 'false' : 'true');

            if (deveAbrir) {
                /* Testa a TELA, não o histórico: a saudação é enviada com
                   salvar=false, então o histórico continuava vazio e ela se
                   repetia a cada abertura. Container vazio = nada na conversa.
                   `children` e não hasChildNodes(): o HTML tem um comentário
                   e espaços lá dentro, que contam como nós. */
                if (msgsContainer && msgsContainer.children.length === 0) {
                    enviarMensagemOrion('Olá! Sou o Orion, seu assistente de estudos no Kosmos. ✦\nComo posso te ajudar hoje?', 'feliz', false);
                }
                setTimeout(function () { if (input) input.focus(); }, 250);
                rolarFim();
            }
        }

        launcher.addEventListener('click', function () { alternarChat(); });
        if (btnFechar) btnFechar.addEventListener('click', function () { alternarChat(false); });

        /* Auto-abertura para teste se solicitado. Em try: com o storage
           bloqueado (aba anônima em alguns navegadores, cookies de terceiros
           desligados) o getItem LANÇA, e a exceção aqui abortava o resto
           deste handler — nenhum listener abaixo era ligado. */
        var abrirParaTeste = false;
        try {
            abrirParaTeste = !!sessionStorage.getItem('kosmos_test_open_chat');
            if (abrirParaTeste) sessionStorage.removeItem('kosmos_test_open_chat');
        } catch (e) { abrirParaTeste = false; }
        if (abrirParaTeste) {
            setTimeout(function () { alternarChat(true); }, 200);
        }

        if (btnLimpar) {
            btnLimpar.addEventListener('click', function () {
                historico = [];
                try { sessionStorage.removeItem(STORAGE_KEY); } catch (e) {}
                if (msgsContainer) msgsContainer.innerHTML = '';
                enviarMensagemOrion('Histórico limpo! Pronto para uma nova dúvida ou sessão de foco. ✦', 'normal', false);
            });
        }

        function escaparHtml(txt) {
            return String(txt)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function rolarFim() {
            if (msgsContainer) {
                msgsContainer.scrollTop = msgsContainer.scrollHeight;
            }
        }

        function adicionarBolha(texto, autor) {
            if (!msgsContainer) return;
            var msgDiv = document.createElement('div');
            msgDiv.className = 'orion-msg orion-msg--' + autor;

            var balao = document.createElement('div');
            balao.className = 'orion-msg__balao';
            /* Escapa ANTES de converter as quebras de linha em <br>: a
               bolha do usuário (e todo o histórico, que volta do
               sessionStorage e pode ter sido adulterado) ia crua para o
               innerHTML. As respostas do bot são texto puro com \n, então
               continuam iguais. */
            balao.innerHTML = escaparHtml(texto).replace(/\n/g, '<br>');

            msgDiv.appendChild(balao);
            msgsContainer.appendChild(msgDiv);
            rolarFim();
        }

        function setHumor(humorNome) {
            if (mascoteHeader && mascoteHeader.mascote) {
                mascoteHeader.mascote.humor(humorNome);
                mascoteHeader.mascote.piscar(250);
            }
            if (mascoteLauncher && mascoteLauncher.mascote) {
                mascoteLauncher.mascote.humor(humorNome);
            }
        }

        function enviarMensagemOrion(texto, humor, salvar) {
            if (salvar !== false) {
                historico.push({ autor: 'bot', texto: texto, humor: humor });
                try { sessionStorage.setItem(STORAGE_KEY, JSON.stringify(historico)); } catch (e) {}
            }
            setHumor(humor || 'normal');
            adicionarBolha(texto, 'bot');
        }

        function enviarMensagemUsuario(texto) {
            if (!texto || !texto.trim()) return;
            texto = texto.trim();

            historico.push({ autor: 'user', texto: texto });
            try { sessionStorage.setItem(STORAGE_KEY, JSON.stringify(historico)); } catch (e) {}
            adicionarBolha(texto, 'user');

            if (input) input.value = '';

            // Indicador de digitação
            if (typingIndicator) typingIndicator.style.display = 'inline-flex';
            rolarFim();
            setHumor('pensativo');

            // Processa resposta pré-definida inteligente
            setTimeout(function () {
                if (typingIndicator) typingIndicator.style.display = 'none';
                responder(texto);
            }, 600 + Math.random() * 400);
        }

        // Renderiza histórico existente ao carregar
        if (historico.length > 0) {
            historico.forEach(function (m) {
                adicionarBolha(m.texto, m.autor);
            });
        }

        // Eventos de envio
        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                enviarMensagemUsuario(input ? input.value : '');
            });
        }

        // Chips rápidos de sugestão
        if (sugestoes) {
            sugestoes.addEventListener('click', function (e) {
                var chip = e.target.closest('.orion-chip');
                if (chip && chip.dataset.prompt) {
                    enviarMensagemUsuario(chip.dataset.prompt);
                }
            });
        }

        /* ── Cérebro de Respostas Contextuais do Orion ── */
        /* Tira acentos (NFD + remove as marcas) e passa para minúsculas:
           "Olá", "ola" e "OLÁ" viram a mesma coisa. */
        function normalizar(txt) {
            return String(txt).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
        }

        /* Casa por PALAVRA INTEIRA. includes() casava pedaço de palavra:
           'ia' dentro de "matéria"/"dia", 'oi' em "oito", 'top' em
           "tópico" — e a pergunta caía na resposta errada. Chave que
           termina em '*' é um radical: casa o começo da palavra
           ('cansad*' → cansado, cansada). \b só entende ASCII, por isso
           texto e chave passam por normalizar() antes. */
        function tem(q, chaves) {
            return chaves.some(function (chave) {
                var radical = chave.slice(-1) === '*';
                var k = normalizar(radical ? chave.slice(0, -1) : chave)
                    .replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                return new RegExp('\\b' + k + (radical ? '' : '\\b')).test(q);
            });
        }

        function responder(pergunta) {
            var q = normalizar(pergunta);

            // 1. Pomodoro
            if (tem(q, ['pomodoro', 'tempo', 'timer', 'foco'])) {
                enviarMensagemOrion(
                    'O Pomodoro do Kosmos divide seu estudo em blocos de alta concentração:\n\n' +
                    '⏱️ 25 minutos de FOCO TOTAL (sem celular ou distrações)\n' +
                    '☕ 5 minutos de PAUSA para esticar o corpo\n\n' +
                    'A cada 4 ciclos, você ganha uma pausa longa de 15 minutos! Você pode iniciar direto na aba "Pomodoro".',
                    'foco'
                );
                return;
            }

            // 2. Flashcards & Repetição Espaçada
            if (tem(q, ['flashcard*', 'cartao', 'cartoes', 'memoriz*'])) {
                enviarMensagemOrion(
                    'Os Flashcards são a arma secreta da memorização ativa! 🎴\n\n' +
                    '1. Crie cartões com uma PERGUNTA na frente e a RESPOSTA no verso.\n' +
                    '2. Tente lembrar ANTES de virar o cartão.\n' +
                    '3. O Kosmos usa repetição espaçada: cartões que você erra aparecem com mais frequência até você dominar!',
                    'curioso'
                );
                return;
            }

            // 3. Resumos & Cadernos
            if (tem(q, ['resumo*', 'caderno*', 'biblioteca', 'anota*'])) {
                enviarMensagemOrion(
                    'Na Biblioteca você organiza cadernos separados por matéria! 📝\n\n' +
                    '• Pode escrever resumos em texto formatado.\n' +
                    '• Pode tirar foto do seu caderno físico e anexar!\n' +
                    '• Dica de ouro: transformar tópicos do seu resumo em Flashcards para revisar depois.',
                    'feliz'
                );
                return;
            }

            // 4. Sem foco / Cansaço / Procrastinação
            if (tem(q, ['sem foco', 'cansad*', 'pregui*', 'procrastin*', 'ajuda*'])) {
                enviarMensagemOrion(
                    'Respira fundo! É super normal bater cansaço cósmico às vezes. 🌌\n\n' +
                    'Experimente a regra dos 5 minutos: comprometa-se a estudar apenas 5 minutos de um assunto leve. Quase sempre o cérebro engrena!\n' +
                    'Se estiver exausto, beba água e faça uma pausa de 10 min. Qual matéria vamos começar devagarzinho?',
                    'sono'
                );
                return;
            }

            // 5. ENEM / Vestibulares / Concursos
            if (tem(q, ['enem', 'vestibular*', 'concurso*', 'prova*'])) {
                enviarMensagemOrion(
                    'Para mandar bem no ENEM e Vestibulares: 🎯\n\n' +
                    '1. Resoluções práticas valem mais que teoria pura.\n' +
                    '2. Não negligencie a Redação (vale até 1000 pontos!).\n' +
                    '3. Use a aba "Flashcards" para fórmulas de física, datas de história e regras gramaticais.\n' +
                    'Mantenha sua meta diária ativa para acumular sequência!',
                    'foco'
                );
                return;
            }

            // 6. Saudações & Apresentação
            if (tem(q, ['oi', 'ola', 'bom dia', 'boa tarde', 'boa noite'])) {
                enviarMensagemOrion('E aí! Muito bom ter você por aqui. O que estamos estudando agora? ✦', 'feliz');
                return;
            }

            if (tem(q, ['quem e voce', 'seu nome', 'orion'])) {
                enviarMensagemOrion('Sou o Orion, o mascote e assistente cósmico do Kosmos! Meu objetivo é te ajudar a manter a disciplina, organizar matérias e atingir sua melhor performance nos estudos. 🚀', 'curioso');
                return;
            }

            // 7. Agradecimentos
            if (tem(q, ['obrigad*', 'valeu', 'top', 'legal', 'amei'])) {
                enviarMensagemOrion('Tamo junto! Qualquer coisa, é só me chamar. Bons estudos e foco total! ✦', 'timido');
                return;
            }

            // 8. Exercícios / IA
            if (tem(q, ['ia', 'inteligencia', 'exercicio*'])) {
                enviarMensagemOrion(
                    'Nosso módulo de Exercícios com IA está sendo calibrado! Em breve você poderá gerar simulados adaptativos que atacam exatamente suas matérias com mais erros. Fique de olho!',
                    'surpreso'
                );
                return;
            }

            // 9. Resposta Padrão / Fora do Escopo Pré-Definido
            enviarMensagemOrion(
                'Excelente pergunta! Estou em fase de treinamento cósmico e muito em breve serei conectado à nossa IA completa para tirar dúvidas específicas de todas as disciplinas e corrigir redações.\n\n' +
                'Enquanto isso, posso te ajudar a dominar as técnicas de Pomodoro, organizar seus Flashcards ou planejar sua meta de estudos. O que acha?',
                'pensativo'
            );
        }
    });
})();
