/* ============================================================
   KOSMOS — orion-ia.js
   A ponte entre as telas do Orion e a IA (Backend/php/orion_chat.php).

   Usado pela aba Orion (orion-aba.js) e pelo chat flutuante
   (orion-chat.js): as duas guardam a conversa de jeitos diferentes,
   então cada uma converte a sua para o formato daqui antes de chamar.

   window.KosmosOrionIA.perguntar(mensagem, historico, extras)
     mensagem  -> texto da pessoa
     historico -> [{autor: 'usuario'|'orion', texto}] — as anteriores
     extras    -> { questao } do botão "Por que eu errei?" (opcional)
   Devolve uma Promise com { resposta, humor }.
   Se falhar, rejeita com um Error que tem:
     .status   -> o código HTTP (0 = sem conexão)
     .doServidor -> true quando a mensagem é para mostrar à pessoa
                    (limite de uso, mensagem longa, sessão expirada);
                    false quando a tela deve cair nas respostas prontas
                    (IA fora do ar, sem internet, não configurada).
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

    window.KosmosOrionIA = { perguntar };
})();
