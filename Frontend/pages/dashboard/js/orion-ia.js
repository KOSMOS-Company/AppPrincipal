/* ============================================================
   KOSMOS — orion-ia.js
   A ponte entre as telas do Orion e a IA (Backend/php/orion_chat.php).

   Usado pela aba Orion (orion-aba.js) e pelo chat flutuante
   (orion-chat.js): as duas guardam a conversa de jeitos diferentes,
   então cada uma converte a sua para o formato daqui antes de chamar.

   window.KosmosOrionIA.perguntar(mensagem, historico, extras)
     mensagem  -> texto da pessoa
     historico -> [{autor: 'usuario'|'orion', texto}] — as anteriores
     extras    -> { questao } do botão "Questionar erro" dos exercícios (opcional)
   Devolve uma Promise com { resposta, humor }.
   Se falhar, rejeita com um Error que tem:
     .status   -> o código HTTP (0 = sem conexão)
     .doServidor -> true quando a mensagem é para mostrar à pessoa
                    (limite de uso, mensagem longa, sessão expirada);
                    false quando a tela deve cair nas respostas prontas
                    (IA fora do ar, sem internet, não configurada).

   window.KosmosOrionIA.historico.ler() / .salvar(lista) / .limpar()
     A conversa única das duas telas (ver o bloco no fim do arquivo).

   window.KosmosOrionIA.pendente.guardar({texto, questao}) / .consumir()
     Uma pergunta que vai ser enviada pela aba Orion ao carregar
     (o "?" dos exercícios no modo foco, ver o fim do arquivo).
   ============================================================ */
(function () {
    "use strict";

    // Relativo à PÁGINA (Frontend/pages/dashboard/*.php): três níveis até a raiz
    const ENDPOINT = "../../../Backend/php/orion_chat.php";

    /** Códigos em que a mensagem do servidor deve aparecer para a pessoa. */
    const MOSTRAR_MSG = [400, 401, 422, 429];

    function paginaAtual() {
        return window.location.pathname.split("/").pop() || "";
    }

    async function perguntar(mensagem, historico, extras) {
        const corpo = {
            mensagem: String(mensagem || "").trim(),
            historico: (historico || []).slice(-8),
            pagina: paginaAtual(),
        };
        if (extras && extras.questao) corpo.questao = extras.questao;

        let resp;
        try {
            resp = await fetch(ENDPOINT, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(corpo),
            });
        } catch (e) {
            throw falha("Sem conexão com o servidor.", 0, false);
        }

        let json = null;
        try { json = await resp.json(); } catch (e) { /* resposta não-JSON */ }

        if (resp.ok && json && json.ok && json.resposta) {
            return { resposta: json.resposta, humor: json.humor || "feliz" };
        }
        const msg = (json && json.msg) || "O Orion não conseguiu responder agora.";
        throw falha(msg, resp.status, MOSTRAR_MSG.includes(resp.status));
    }

    function falha(msg, status, doServidor) {
        const e = new Error(msg);
        e.status = status;
        e.doServidor = doServidor;
        return e;
    }

    /* ------------------------------------------------------------
       A conversa, compartilhada entre a aba Orion e o chat flutuante.
       Antes cada um guardava a sua (chaves e formatos diferentes), e
       o que se falava num não aparecia no outro.

       Formato único: [{autor: 'usuario'|'orion', texto, humor?}]
       Fica no sessionStorage (some ao fechar a aba do navegador) e
       por conta (kosmosChaveConta, da sidebar.php), como antes.
       ------------------------------------------------------------ */
    const BASE_CHAVE = "kosmos_orion_conversa";
    const MAX_MENSAGENS = 60;

    function chave(base) {
        return window.kosmosChaveConta ? window.kosmosChaveConta(base) : base;
    }

    function valida(m) {
        return m && (m.autor === "usuario" || m.autor === "orion") && typeof m.texto === "string";
    }

    /* Aproveita uma conversa salva no formato antigo (uma vez só):
       aba Orion = {tipo, conteudo, humor}; chat flutuante = {autor: 'user'|'bot', texto}. */
    function migrarAntigas() {
        const antigas = [
            [chave("kosmos_orion_chat_session"),   (m) => ({ autor: m.tipo === "usuario" ? "usuario" : "orion", texto: m.conteudo, humor: m.humor })],
            [chave("kosmos_orion_chat_historico"), (m) => ({ autor: m.autor === "user" ? "usuario" : "orion", texto: m.texto, humor: m.humor })],
        ];
        let lista = [];
        for (const [k, converter] of antigas) {
            try {
                const salvo = JSON.parse(sessionStorage.getItem(k) || "[]");
                if (!lista.length && Array.isArray(salvo) && salvo.length) {
                    lista = salvo.map(converter).filter(valida);
                }
                sessionStorage.removeItem(k);
            } catch (e) { /* storage bloqueado ou lixo: ignora */ }
        }
        return lista;
    }

    const historico = {
        ler() {
            try {
                const salvo = sessionStorage.getItem(chave(BASE_CHAVE));
                if (salvo !== null) {
                    const lista = JSON.parse(salvo);
                    return Array.isArray(lista) ? lista.filter(valida) : [];
                }
            } catch (e) {
                return [];
            }
            const migrada = migrarAntigas();
            if (migrada.length) historico.salvar(migrada);
            return migrada;
        },
        salvar(lista) {
            try {
                sessionStorage.setItem(chave(BASE_CHAVE), JSON.stringify(lista.slice(-MAX_MENSAGENS)));
            } catch (e) { /* navegação privada: a conversa só não sobrevive à troca de página */ }
        },
        limpar() {
            try { sessionStorage.removeItem(chave(BASE_CHAVE)); } catch (e) { /* idem */ }
        },
    };

    /** A conversa no formato que vai para o servidor (sem o humor). */
    function paraIA(lista) {
        return lista.map((m) => ({ autor: m.autor, texto: m.texto }));
    }

    /* ------------------------------------------------------------
       Pergunta "a caminho" da aba Orion.
       No modo foco o chat flutuante fica escondido (lite.css). Aí o
       "?" (Questionar erro) dos exercícios guarda a pergunta aqui e
       abre a aba Orion, que a envia sozinha ao carregar (orion-aba.js).
       Lida uma vez só: consumir() já apaga.
       ------------------------------------------------------------ */
    const CHAVE_PENDENTE = "kosmos_orion_pendente";
    const pendente = {
        guardar(dados) {
            try { sessionStorage.setItem(chave(CHAVE_PENDENTE), JSON.stringify(dados)); } catch (e) { /* sem storage */ }
        },
        consumir() {
            try {
                const salvo = sessionStorage.getItem(chave(CHAVE_PENDENTE));
                sessionStorage.removeItem(chave(CHAVE_PENDENTE));
                const dados = salvo ? JSON.parse(salvo) : null;
                return dados && typeof dados.texto === "string" && dados.texto.trim() ? dados : null;
            } catch (e) {
                return null;
            }
        },
    };

    window.KosmosOrionIA = { perguntar, historico, paraIA, pendente };
})();
