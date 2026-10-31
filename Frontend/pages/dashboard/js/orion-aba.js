/* ============================================================
   KOSMOS — orion-aba.js
   Controle e lógica do Chatbot dedicado do Orion.
   - Padrão Chatbot normal: área de mensagens rolável independente.
   - Input fixo no rodapé: NUNCA move ou abaixa a tela do usuário ao digitar.
   - Respostas inteligentes estruturadas e reações de humor do mascote 3D.
   - Conversas salvas: o painel "Conversas" lista, abre e apaga as
     conversas da conta; "Nova conversa" começa outra sem apagar a atual.
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
                  `Estou sem sinal com a minha IA neste momento 🛰️ — pergunte de novo daqui a pouco para uma explicação completa.`
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

        /* Monta os mascotes. A API real do shared/mascote.js é
           window.KosmosMascote.montar(el) (K maiúsculo); o humor fica
           depois em el.mascote.humor(nome) e o tamanho vem do CSS. Os dois
           já têm data-mascote no HTML, então o montarTodos() do mascote.js
           pode tê-los montado antes — montar() de novo só devolve o que
           já existe, sem desenhar duas vezes. */
        if (window.KosmosMascote) {
            if (mascoteHeader) window.KosmosMascote.montar(mascoteHeader);
            if (mascoteCentral) window.KosmosMascote.montar(mascoteCentral);
        }

        /* A conversa é a MESMA do chat flutuante das outras abas: mora no
           orion-ia.js, num formato só ({autor: 'usuario'|'orion', texto, humor}). */
        const conversa = window.KosmosOrionIA ? window.KosmosOrionIA.historico : null;
        let historico = conversa ? conversa.ler() : [];

        function salvarHistorico() {
            if (conversa) conversa.salvar(historico);
        }

        function definirHumor(humor) {
            [mascoteHeader, mascoteCentral].forEach(m => {
                if (m && m.mascote) m.mascote.humor(humor);
            });
        }

        /* Escapa o texto ANTES de aplicar a formatação. Sem isso, a
           pergunta digitada (ou um histórico adulterado no sessionStorage)
           ia crua para o innerHTML: "<img src=x onerror=...>" rodava na
           página. Escapando primeiro, o ** e o * das respostas do Orion
           continuam virando <strong>/<em> — esses caracteres não mudam. */
        function formatarTexto(txt) {
            return escaparHtml(txt)
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

        /* A resposta do Orion é só o texto, como nas IAs de sempre: o
           mascote fica uma vez só, no topo (e é ele que muda de humor —
           definirHumor()). Antes cada resposta trazia um mini Orion ao
           lado, e a tela ficava cheia de Orions. */
        function renderizarMensagem(tipo, conteudo) {
            if (heroInicial) heroInicial.style.display = 'none';

            const row = document.createElement('div');
            row.className = `orion-msg-row orion-msg-row--${tipo}`;

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
            historico.forEach(m => renderizarMensagem(m.autor === 'usuario' ? 'usuario' : 'orion', m.texto));
            scrollParaFim();
        }

        /* Tira acentos (NFD + remove as marcas) e passa para minúsculas:
           "Olá", "ola" e "OLÁ" viram a mesma coisa, dos dois lados. */
        function normalizar(txt) {
            return String(txt).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
        }

        /* Casa por PALAVRA INTEIRA. includes() casava pedaço: "oi" dentro
           de "oito"/"depois", "top" em "tópico"... e a pergunta ia para a
           resposta errada. \b só funciona com ASCII — por isso o texto e a
           chave passam por normalizar() antes. */
        function temPalavra(texto, chave) {
            const k = normalizar(chave).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            return new RegExp('\\b' + k + '\\b').test(texto);
        }

        function buscarResposta(texto) {
            const limpo = normalizar(texto);
            for (const item of RESPOSTAS_ORION) {
                const bateu = item.chaves.some(k => temPalavra(limpo, k));
                if (bateu) return item;
            }
            return RESPOSTA_PADRAO;
        }

        /* Uma pergunta por vez: enquanto a IA pensa, um segundo envio
           embaralharia a ordem das respostas na conversa. */
        let aguardando = false;

        /* Pergunta à IA (orion-ia.js). Se ela estiver fora do ar, cai
           nas respostas prontas de RESPOSTAS_ORION, que antes eram as
           únicas: o Orion nunca fica mudo. Limite de uso, mensagem longa
           e sessão expirada aparecem como a mensagem do servidor. */
        async function obterResposta(texto, anteriores, extras) {
            if (!window.KosmosOrionIA) {
                return buscarResposta(texto);
            }
            try {
                const r = await window.KosmosOrionIA.perguntar(texto, window.KosmosOrionIA.paraIA(anteriores), extras);
                return { resposta: r.resposta, humor: r.humor };
            } catch (erro) {
                if (erro.doServidor) {
                    return { resposta: erro.message, humor: 'confuso' };
                }
                return buscarResposta(texto);
            }
        }

        /* Sem argumentos, envia o que está na caixa. Com textoPronto,
           envia esse texto (e a questão em extras) — é o caminho da
           pergunta que chegou de outra aba (ver o fim do arquivo). */
        async function enviarPergunta(textoPronto, extras) {
            const veioPronto = typeof textoPronto === 'string';
            const texto = (veioPronto ? textoPronto : input.value).trim();
            if (!texto || aguardando) return;
            aguardando = true;

            if (!veioPronto) input.value = '';
            input.focus();

            // A conversa ANTES desta pergunta (é o contexto que vai para a IA)
            const anteriores = historico.slice();

            // Adiciona a pergunta do estudante
            renderizarMensagem('usuario', texto);
            historico.push({ autor: 'usuario', texto });
            salvarHistorico();

            // Reação do mascote (pensativo enquanto a IA responde)
            definirHumor('pensativo');

            // Exibe os três pontinhos de digitação dentro do chat
            if (digitando) {
                digitando.style.display = 'inline-flex';
                listaMensagens.appendChild(digitando);
                scrollParaFim();
            }

            const resultado = await obterResposta(texto, anteriores, extras);

            if (digitando) digitando.style.display = 'none';
            const humor = resultado.humor || 'feliz';
            definirHumor(humor);
            renderizarMensagem('orion', resultado.resposta);

            historico.push({ autor: 'orion', texto: resultado.resposta, humor });
            salvarHistorico();
            aguardando = false;

            setTimeout(() => definirHumor('normal'), 4000);
        }

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            enviarPergunta();
            ajustarAltura();
        });

        /* A caixa é um <textarea> que cresce com o texto (até o
           max-height do CSS, depois rola por dentro). Enter envia;
           Shift+Enter quebra a linha. isComposing: no celular, o Enter
           que fecha uma palavra sugerida pelo teclado não deve enviar. */
        function ajustarAltura() {
            input.style.height = 'auto';
            input.style.height = input.scrollHeight + 'px';
            // rola por dentro só quando bateu no max-height do CSS
            input.classList.toggle('orion-chat-input--rola', input.scrollHeight > input.clientHeight + 1);
        }
        input.addEventListener('input', ajustarAltura);

        /* No celular a caixa é fixa no rodapé (orion-aba.css) e a conversa
           precisa saber quanto reservar embaixo para a última mensagem não
           ficar atrás dela — inclusive quando a caixa cresce com o texto. */
        const rodape = document.querySelector('.orion-rodape');
        if (rodape && 'ResizeObserver' in window) {
            new ResizeObserver(() => {
                document.documentElement.style.setProperty('--orion-rodape-h', rodape.offsetHeight + 'px');
            }).observe(rodape);
        }
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
                e.preventDefault();
                enviarPergunta();
                ajustarAltura();
            }
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

        /* Nova conversa: a tela volta para o começo. A conversa anterior
           não é apagada — ela está salva e aparece em "Conversas". */
        function novaConversa() {
            if (conversa) conversa.limpar();
            historico = [];
            listaMensagens.innerHTML = '';
            if (heroInicial) heroInicial.style.display = 'flex';
            definirHumor('normal');
            marcarAtual();
        }

        if (btnLimpar) {
            btnLimpar.addEventListener('click', () => {
                if (aguardando) return;
                novaConversa();
                fecharPainel();
                input.focus();
            });
        }

        /* ------------------------------------------------------------
           Conversas salvas (como no ChatGPT): o painel da direita lista
           as conversas da conta, agrupadas por quando foram usadas.
           Quem fala com o servidor é o orion-ia.js (KosmosOrionIA.conversas).
           ------------------------------------------------------------ */
        const salvas = window.KosmosOrionIA ? window.KosmosOrionIA.conversas : null;
        const painel = document.getElementById('orionConversas');
        const veu = document.getElementById('orionConversasVeu');
        const listaConversas = document.getElementById('orionConversasLista');
        const btnConversas = document.getElementById('orionBtnConversas');
        const btnFecharPainel = document.getElementById('orionConversasFechar');
        let listaDesatualizada = true;

        function painelAberto() {
            return painel && !painel.hidden;
        }

        function abrirPainel() {
            if (!painel || !salvas) return;
            painel.hidden = false;
            if (veu) veu.hidden = false;
            if (btnConversas) btnConversas.setAttribute('aria-expanded', 'true');
            if (listaDesatualizada) carregarLista();
            (painel.querySelector('[aria-current="true"]') || btnFecharPainel || painel).focus();
        }

        function fecharPainel(devolverFoco) {
            if (!painelAberto()) return;
            painel.hidden = true;
            if (veu) veu.hidden = true;
            if (btnConversas) {
                btnConversas.setAttribute('aria-expanded', 'false');
                if (devolverFoco) btnConversas.focus();
            }
        }

        /* "Hoje", "Ontem", "Últimos 7 dias", "Mais antigas": a conta é
           feita contra o "hoje" do MySQL, que vem junto da lista (regra
           de datas: um relógio só). Datas como AAAA-MM-DD, em UTC, para
           o horário de verão não tirar uma hora do dia. */
        function grupoDaData(dataHora, hoje) {
            const dia = (s) => {
                const [a, m, d] = String(s).slice(0, 10).split('-').map(Number);
                return Date.UTC(a, m - 1, d);
            };
            const dias = Math.round((dia(hoje) - dia(dataHora)) / 86400000);
            if (dias <= 0) return 'Hoje';
            if (dias === 1) return 'Ontem';
            if (dias < 7) return 'Últimos 7 dias';
            return 'Mais antigas';
        }

        function mensagemNaLista(texto) {
            listaConversas.innerHTML = '';
            const p = document.createElement('p');
            p.className = 'orion-conversas__vazio';
            p.textContent = texto;
            listaConversas.appendChild(p);
        }

        async function carregarLista() {
            if (!salvas || !listaConversas) return;
            if (!listaConversas.children.length) mensagemNaLista('Carregando…');
            try {
                const { conversas: itens, hoje } = await salvas.listar();
                listaDesatualizada = false;
                desenharLista(itens || [], hoje);
            } catch (erro) {
                mensagemNaLista(erro.status === 0
                    ? 'Sem conexão. Tente de novo em instantes.'
                    : 'Não foi possível carregar as conversas agora.');
            }
        }

        const ICONE_APAGAR = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4.5A1.5 1.5 0 0 1 9.5 3h5A1.5 1.5 0 0 1 16 4.5V6"/><path d="m5.5 6 1 13.5A1.5 1.5 0 0 0 8 21h8a1.5 1.5 0 0 0 1.5-1.5L18.5 6"/></svg>';

        function desenharLista(itens, hoje) {
            listaConversas.innerHTML = '';
            if (!itens.length) {
                mensagemNaLista('Nenhuma conversa salva ainda. Faça uma pergunta e ela aparece aqui.');
                return;
            }
            let grupoAtual = '';
            itens.forEach((c) => {
                const grupo = grupoDaData(c.atualizada_em, hoje);
                if (grupo !== grupoAtual) {
                    grupoAtual = grupo;
                    const h = document.createElement('h3');
                    h.className = 'orion-conversas__grupo';
                    h.textContent = grupo;
                    listaConversas.appendChild(h);
                }

                const item = document.createElement('div');
                item.className = 'orion-conversa';
                item.dataset.id = c.id;

                const abrir = document.createElement('button');
                abrir.type = 'button';
                abrir.className = 'orion-conversa__abrir';
                abrir.textContent = c.titulo;
                abrir.title = c.titulo;
                abrir.addEventListener('click', () => abrirConversa(c.id));

                const apagar = document.createElement('button');
                apagar.type = 'button';
                apagar.className = 'orion-conversa__apagar';
                apagar.innerHTML = ICONE_APAGAR;
                apagar.setAttribute('aria-label', 'Apagar a conversa "' + c.titulo + '"');
                apagar.title = 'Apagar conversa';
                apagar.addEventListener('click', () => apagarConversa(c.id, c.titulo));

                item.append(abrir, apagar);
                listaConversas.appendChild(item);
            });
            marcarAtual();
        }

        /** Destaca na lista a conversa que está aberta na tela. */
        function marcarAtual() {
            if (!listaConversas) return;
            const atual = salvas ? salvas.atual() : null;
            listaConversas.querySelectorAll('.orion-conversa').forEach((el) => {
                const sim = Number(el.dataset.id) === atual;
                el.classList.toggle('orion-conversa--atual', sim);
                el.querySelector('.orion-conversa__abrir').setAttribute('aria-current', String(sim));
            });
        }

        async function abrirConversa(id) {
            if (aguardando) return;
            if (salvas.atual() === id) { fecharPainel(); return; }
            try {
                historico = await salvas.abrir(id);
            } catch (erro) {
                await confirmar({ titulo: 'Não foi possível abrir', texto: erro.message, botao: 'Entendi' });
                if (erro.status === 404) carregarLista();   // apagada em outro aparelho
                return;
            }
            listaMensagens.innerHTML = '';
            if (historico.length) {
                historico.forEach(m => renderizarMensagem(m.autor === 'usuario' ? 'usuario' : 'orion', m.texto));
            } else if (heroInicial) {
                heroInicial.style.display = 'flex';
            }
            definirHumor('normal');
            marcarAtual();
            fecharPainel();
            input.focus();
        }

        async function apagarConversa(id, titulo) {
            const ok = await confirmar({
                titulo: 'Apagar conversa?',
                texto: '"' + titulo + '" some de vez, em todos os aparelhos.',
                botao: 'Apagar',
                perigo: true,
            });
            if (!ok) return;
            const eraAtual = salvas.atual() === id;
            try {
                await salvas.excluir(id);
            } catch (erro) {
                if (erro.status !== 404) {
                    await confirmar({ titulo: 'Não foi possível apagar', texto: erro.message, botao: 'Entendi' });
                    return;
                }
            }
            if (eraAtual && !aguardando) novaConversa();
            carregarLista();
        }

        if (btnConversas) {
            btnConversas.addEventListener('click', () => (painelAberto() ? fecharPainel(true) : abrirPainel()));
        }
        if (btnFecharPainel) btnFecharPainel.addEventListener('click', () => fecharPainel(true));
        if (veu) veu.addEventListener('click', () => fecharPainel(true));
        document.addEventListener('keydown', (e) => {
            // o modal de confirmação tem o Esc dele: aqui só se ele não está aberto
            if (e.key === 'Escape' && painelAberto() && !document.querySelector('.modal.open')) fecharPainel(true);
        });

        // Cada resposta salva mexe na lista (conversa nova, ordem, "Hoje")
        document.addEventListener('kosmos:orion-salvo', () => {
            listaDesatualizada = true;
            if (painelAberto()) carregarLista();
            else marcarAtual();
        });

        /* Pergunta que chegou de outra aba: no modo foco, o "?"
           (Questionar erro) dos exercícios não tem o chat flutuante
           para abrir — ele guarda a pergunta e manda a pessoa para cá
           (orion-chat.js → orion-ia.js → pendente). */
        const chegou = window.KosmosOrionIA ? window.KosmosOrionIA.pendente.consumir() : null;
        if (chegou) {
            enviarPergunta(chegou.texto, chegou.questao ? { questao: chegou.questao } : undefined);
        }
    });
})();
