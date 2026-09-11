/* ============================================================
   KOSMOS — orion.js
   A visita guiada da landing page (#orion).

   O Orion não é o assunto da seção: ele é o ANFITRIÃO. A Kosmos é
   que está sendo apresentada, e ele conduz — fala, reage e leva a
   pessoa de um canto ao outro da plataforma.

   Regra que guia este arquivo: aqui NÃO se inventa animação
   nenhuma. O Orion já sabe olhar, piscar, sorrir, tapar os olhos e
   ficar confuso — isso mora em shared/mascote.js e é o mesmo código
   que roda no login, no cadastro e no 404. Esta seção só aciona
   esses comportamentos e narra o que está acontecendo. É o que faz
   a demonstração ser honesta: o que a pessoa vê aqui é exatamente
   o que ela vai encontrar depois.

   O roteiro tem sete passos. No desktop a rolagem os percorre (a
   seção fica pinada — ver js/inicio.js); em qualquer tela, clicar
   num passo leva ao mesmo lugar, pela mesma função. Não existem
   dois caminhos para manter iguais.

   Fora do roteiro: dois campos de mentira (e-mail e senha) ligados
   aos mesmos gatilhos do formulário de verdade.
   ============================================================ */

(function () {
    'use strict';

    var secao = document.getElementById('orion');
    if (!secao) return;                         // outra página

    var corpo = document.getElementById('orionCorpo');     // o botão (recebe clique e foco)
    var desenho = document.getElementById('orionMascote'); // o SVG (decoração, aria-hidden)
    var fala = document.getElementById('orionFala');
    var roteiro = document.getElementById('orionRoteiro');
    var dica = document.getElementById('orionDica');
    var email = document.getElementById('orionEmail');
    var senha = document.getElementById('orionSenha');
    var olho = document.getElementById('orionOlho');
    if (!corpo || !desenho) return;

    var semMovimento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* O mascote.js monta sozinho no DOMContentLoaded, mas este
       arquivo pode rodar antes: garantimos a montagem aqui. */
    var orion = (window.KosmosMascote && window.KosmosMascote.montar(desenho)) || desenho.mascote;
    if (!orion) return;

    /* ------------------------------------------------------------
       O balão de fala
       ------------------------------------------------------------ */
    var FRASE_PADRAO = 'Oi! Eu sou o Orion. Vem que eu te mostro a Kosmos.';

    var escrevendo = null;
    var voltarTimer = null;

    /** Escreve a frase letra a letra. Com movimento reduzido, só troca. */
    function dizer(texto) {
        clearInterval(escrevendo);

        if (semMovimento) {
            fala.textContent = texto;
            return;
        }

        fala.textContent = '';
        var i = 0;
        escrevendo = setInterval(function () {
            fala.textContent = texto.slice(0, ++i);
            if (i >= texto.length) clearInterval(escrevendo);
        }, 22);
    }

    /** Volta ao estado de repouso depois de um tempo sem interação. */
    function agendarVolta(ms) {
        clearTimeout(voltarTimer);
        voltarTimer = setTimeout(function () {
            orion.humor('normal');
            orion.fecharOlhos(false);
            marcarPasso(null);
            dizer(FRASE_PADRAO);
        }, ms || 6000);
    }

    /* ------------------------------------------------------------
       Os roteiro
       Cada um é o comportamento REAL, chamado pela mesma API que o
       login usa — não uma imitação feita só para a vitrine.
       ------------------------------------------------------------ */
    /* Os sete passos da visita. Cada um traz a fala do anfitrião e o
       que ele faz — e o que ele faz é sempre a API real do mascote,
       nunca uma imitação montada só para a vitrine. */
    var ROTEIRO = {
        chegada: {
            fala: 'Oi! Eu sou o Orion. Vem que eu te mostro a Kosmos.',
            fazer: function () {
                orion.humor('normal');
                orion.fecharOlhos(false);
                orion.piscar();
            }
        },
        kosmos: {
            fala: 'Esta é a Kosmos: tudo que você estuda num lugar só. E é de graça.',
            fazer: function () {
                orion.fecharOlhos(false);
                orion.humor('feliz');
            }
        },
        biblioteca: {
            fala: 'Na Biblioteca você monta cadernos por matéria — ou só fotografa o seu caderno de papel.',
            fazer: function () {
                orion.fecharOlhos(false);
                orion.humor('normal');
            }
        },
        revisao: {
            fala: 'Nos Flashcards você vira o cartão e me diz se lembrava. Eu guardo o resto.',
            fazer: function () {
                orion.fecharOlhos(false);
                orion.humor('normal');
                orion.piscar();
            }
        },
        foco: {
            fala: 'No Pomodoro são 25 minutos de cada vez — e eu te aviso quando acabar.',
            fazer: function () {
                orion.fecharOlhos(false);
                orion.humor('normal');
            }
        },
        junto: {
            fala: 'E eu fico do seu lado o tempo todo. Na hora da senha, tapo os olhos.',
            fazer: function () {
                orion.humor('normal');
                orion.fecharOlhos(true);
            }
        },
        perdido: {
            fala: 'Se você se perder numa página que não existe, eu me perco junto.',
            fazer: function () {
                orion.fecharOlhos(false);
                orion.humor('confuso');
            }
        }
    };

    function marcarPasso(botao) {
        if (!roteiro) return;
        var todos = roteiro.querySelectorAll('.orion-passo');
        for (var i = 0; i < todos.length; i++) {
            todos[i].classList.toggle('ativo', todos[i] === botao);
        }
    }

    /**
     * Aciona um passo do roteiro.
     * `daRolagem` = veio da seção pinada. Nesse caso não agendamos a
     * volta ao repouso: quem manda no estado é a posição da rolagem, e
     * um timer apagando o passo no meio da leitura pareceria um bug.
     */
    function acionar(nome, botao, daRolagem) {
        var t = ROTEIRO[nome];
        if (!t) return;

        t.fazer();
        dizer(t.fala);
        marcarPasso(botao || botaoDoPasso(nome));
        esconderDica();

        if (daRolagem) {
            clearTimeout(voltarTimer);
        } else {
            agendarVolta(nome === 'junto' ? 4000 : 6000);
        }
    }

    /** Acha o botão de um passo pelo nome (a rolagem não passa o elemento). */
    function botaoDoPasso(nome) {
        return roteiro ? roteiro.querySelector('[data-passo="' + nome + '"]') : null;
    }

    if (roteiro) {
        roteiro.addEventListener('click', function (e) {
            var botao = e.target.closest('.orion-passo');
            if (botao) acionar(botao.dataset.passo, botao);
        });

        /* No computador, passar o mouse já mostra o passo: descobrir a
           seção não deveria exigir clicar em tudo. O clique continua
           valendo — e é o único caminho no celular.
           A guarda existe porque o mouseover borbulha dos <span> de
           dentro: sem ela, andar com o mouse dentro do mesmo botão
           reiniciaria a frase sem parar. */
        var ultimoHover = null;
        roteiro.addEventListener('mouseover', function (e) {
            var botao = e.target.closest('.orion-passo');
            if (!botao || botao === ultimoHover || semMovimento) return;
            ultimoHover = botao;
            acionar(botao.dataset.passo, botao);
        });
        roteiro.addEventListener('mouseleave', function () { ultimoHover = null; });
    }

    /* ------------------------------------------------------------
       Clicar no próprio Orion
       ------------------------------------------------------------ */
    var CUMPRIMENTOS = [
        'Bora? A Kosmos tá te esperando.',
        'Já fez seu Pomodoro hoje?',
        'Se travar num resumo, me chama.',
        'Dica: flashcard curto gruda mais.',
        'Tudo isso aqui é de graça, viu.',
        'Eu também tenho prova amanhã.'
    ];
    var proximoOi = 0;

    function cumprimentar() {
        orion.humor('feliz');
        orion.piscar();
        // em ordem, não sorteado: sorteio repete e parece travado
        dizer(CUMPRIMENTOS[proximoOi]);
        proximoOi = (proximoOi + 1) % CUMPRIMENTOS.length;
        marcarPasso(null);
        esconderDica();
        agendarVolta(5000);
    }

    // é um <button> de verdade: Enter e Espaço já funcionam sozinhos
    corpo.addEventListener('click', cumprimentar);

    /** A dica "Clique nele" some depois da primeira interação. */
    function esconderDica() {
        if (dica && !dica.classList.contains('sumiu')) {
            dica.classList.add('sumiu');
        }
    }

    /* ------------------------------------------------------------
       Os campos de mentira
       Mesmos gatilhos do formulário de verdade — por isso a seção
       desliga o data-mascote-auto: ligamos aqui, só a estes campos,
       para o Orion não reagir a um campo de outra parte da página.
       ------------------------------------------------------------ */
    if (senha) {
        var avaliarSenha = function () {
            // "mostrar senha" faz o campo virar text: aí ele espia de volta
            var cobrir = document.activeElement === senha && senha.type === 'password';
            orion.fecharOlhos(cobrir);

            if (cobrir) {
                dizer('Sem espiar. Palavra de mascote.');
                marcarPasso(null);
                esconderDica();
                clearTimeout(voltarTimer);
            } else {
                agendarVolta(2500);
            }
        };

        senha.addEventListener('focus', avaliarSenha);
        senha.addEventListener('blur', avaliarSenha);
        senha.addEventListener('input', avaliarSenha);
    }

    if (olho && senha) {
        olho.addEventListener('click', function () {
            var mostrando = senha.type === 'text';
            senha.type = mostrando ? 'password' : 'text';

            olho.setAttribute('aria-pressed', mostrando ? 'false' : 'true');
            olho.setAttribute('aria-label', mostrando ? 'Mostrar a senha' : 'Esconder a senha');
            olho.classList.toggle('ativo', !mostrando);

            senha.focus();
            orion.fecharOlhos(senha.type === 'password');

            dizer(mostrando
                ? 'Escondeu de novo. Volto a tapar os olhos.'
                : 'Ah, pode mostrar? Então eu dou uma espiada.');
            esconderDica();
            clearTimeout(voltarTimer);
        });
    }

    if (email) {
        email.addEventListener('input', function () {
            var valido = email.value.trim() !== '' && email.checkValidity();

            // com a senha em foco, tapar os olhos manda mais que sorrir
            if (senha && document.activeElement === senha) return;

            orion.humor(valido ? 'feliz' : 'normal');
            if (valido) {
                dizer('E-mail certinho! Já pode entrar.');
                esconderDica();
                agendarVolta(5000);
            }
        });
    }

    /* ------------------------------------------------------------
       A porta para o motor de rolagem (js/inicio.js)
       Rolar e clicar acabam exatamente no mesmo lugar — é o que evita
       dois caminhos para manter iguais.
       ------------------------------------------------------------ */
    window.KosmosOrion = {
        mostrar: function (nome) { acionar(nome, null, true); }
    };

    /* ------------------------------------------------------------
       Entrada em cena
       Ao chegar na seção, o Orion dá um oi — uma vez só, para não
       ficar repetindo a cada rolagem.
       ------------------------------------------------------------ */
    if ('IntersectionObserver' in window) {
        var jaApresentou = false;
        var obs = new IntersectionObserver(function (entradas) {
            entradas.forEach(function (entrada) {
                if (!entrada.isIntersecting || jaApresentou) return;
                jaApresentou = true;

                setTimeout(function () {
                    orion.piscar(260);
                    dizer(FRASE_PADRAO);
                }, 420);

                obs.disconnect();
            });
        }, { threshold: 0.35 });

        obs.observe(secao);
    }
})();
