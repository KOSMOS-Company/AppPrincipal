/* ============================================================
   KOSMOS — conta.js
   Aba "Conta" em módulos: perfil, segurança, estudo,
   notificações, privacidade e sobre.
   O "porteiro" (redirecionar se não logado) é do dashboard.js.

   Tudo dentro de uma IIFE porque dashboard.js e os scripts de
   página dividem o mesmo escopo global.
   ============================================================ */
(() => {
    "use strict";

    const BACKEND = "../../../Backend/php";

    let temSenha = true;      // false = conta só do Google (modo "criar senha")
    let prefs    = null;      // preferências atuais (o que veio do banco)
    let materiasEscolhidas = new Set();

    /* Tudo em que o usuário já mexeu nesta visita. As respostas do
       servidor chegam depois do carregamento e não podem desfazer o
       que a pessoa acabou de escolher ou digitar (era o bug de
       "salvei e voltou ao valor antigo"). */
    const tocado = new Set();

    document.addEventListener("DOMContentLoaded", () => {
        if (!document.querySelector(".conta-layout")) return;

        ativarNavegacao();
        carregarDados();
        carregarPreferencias();

        document.getElementById("formPerfil").addEventListener("submit", salvarPerfil);
        document.getElementById("formSenha").addEventListener("submit", trocarSenha);
        document.getElementById("btnSair").addEventListener("click", sair);
        document.getElementById("btnSessoes").addEventListener("click", sairDeTodos);
        document.getElementById("btnSalvarEstudo").addEventListener("click", salvarEstudo);
        document.getElementById("swLembrete").addEventListener("click", () => alternarNotificacao("swLembrete", "notif_lembrete"));
        document.getElementById("swResumo").addEventListener("click", () => alternarNotificacao("swResumo", "notif_resumo"));

        ativarExclusao();
        ativarFoto();
        marcarCamposTocados();
        ativarCores();
        aplicarPrefsDoCache();   // valores certos já no primeiro instante
        aplicarDadosDoCache();
        ativarEditorFoto();
    });

    const CHAVE_PREFS = "kosmos_prefs";   // cache só desta sessão do navegador

    /* Preenche a tela com as preferências guardadas na visita anterior,
       antes de o servidor responder. Evita ver um número e ele mudar
       depois (o que parecia "não salvou"). */
    function aplicarPrefsDoCache() {
        try {
            const bruto = sessionStorage.getItem(CHAVE_PREFS);
            if (!bruto) return;
            const p = JSON.parse(bruto);

            valor("pomoFoco", p.pomo_foco);
            valor("pomoPausa", p.pomo_pausa);
            valor("pomoPausaLonga", p.pomo_pausa_longa);
            valor("metaDiaria", p.meta_diaria);
            marcarCor(p.avatar_cor);
            if (p.avatar_url) aplicarFoto(p.avatar_url, p.avatar_pos_x, p.avatar_pos_y);
            marcarSwitch("swLembrete", p.notif_lembrete);
            marcarSwitch("swResumo", p.notif_resumo);
        } catch (err) {
            /* cache inválido: o fetch resolve */
        }
    }

    const CHAVE_DADOS = "kosmos_conta";

    /* Mesmo atalho para os dados do perfil: mostra o que já era
       verdade na visita anterior e deixa o fetch confirmar. */
    function aplicarDadosDoCache() {
        try {
            const bruto = sessionStorage.getItem(CHAVE_DADOS);
            if (!bruto) return;
            const d = JSON.parse(bruto);
            mostrarDados(d);
        } catch (err) { /* cache inválido: o fetch resolve */ }
    }

    function guardarDadosNoCache(d) {
        try {
            sessionStorage.setItem(CHAVE_DADOS, JSON.stringify({
                nome: d.nome, email: d.email, criado_em: d.criado_em,
                ultimo_acesso: d.ultimo_acesso, sequencia: d.sequencia,
                tem_senha: d.tem_senha, tem_google: d.tem_google,
                decks: d.decks, cartoes: d.cartoes
            }));
        } catch (err) { /* sem storage: só não há atalho na próxima */ }
    }

    function guardarPrefsNoCache(p) {
        try {
            sessionStorage.setItem(CHAVE_PREFS, JSON.stringify(p));
        } catch (err) { /* sem storage: só não há atalho na próxima */ }
    }

    /* Assim que o usuário digita num campo, ele passa a ser "dele":
       nem o carregamento inicial nem uma resposta atrasada sobrescrevem. */
    function marcarCamposTocados() {
        const doPerfil = ["nome", "email"];
        const doEstudo = ["pomoFoco", "pomoPausa", "pomoPausaLonga", "metaDiaria"];

        [...doPerfil, ...doEstudo].forEach((id) => {
            document.getElementById(id)?.addEventListener("input", () => {
                tocado.add(id);
                // marca que existe coisa digitada e ainda não salva
                marcarPendente(doPerfil.includes(id) ? "perfil" : "estudo");
            });
        });

        ativarAvisoDeSaida();
    }

    /* ------------------------------------------------------------
       Navegação entre as seções (a escolhida fica no #hash da URL,
       então dá para voltar/recarregar sem perder o lugar)
       ------------------------------------------------------------ */
    function ativarNavegacao() {
        const itens = [...document.querySelectorAll(".conta-nav__item")];

        const mostrar = (secao) => {
            const existe = itens.some((i) => i.dataset.secao === secao);
            const alvo = existe ? secao : "perfil";

            itens.forEach((i) => i.classList.toggle("active", i.dataset.secao === alvo));
            document.querySelectorAll(".conta-secao").forEach((s) => {
                s.hidden = s.dataset.painel !== alvo;
            });
        };

        itens.forEach((item) => {
            item.addEventListener("click", () => {
                const secao = item.dataset.secao;
                history.replaceState(null, "", "#" + secao);
                mostrar(secao);
            });
        });

        mostrar((location.hash || "#perfil").slice(1));
        window.addEventListener("hashchange", () => mostrar((location.hash || "#perfil").slice(1)));
    }

    /* ------------------------------------------------------------
       Dados da conta (perfil + resumo de acesso)
       ------------------------------------------------------------ */
    async function carregarDados() {
        try {
            const resp = await fetch(`${BACKEND}/conta_dados.php`);
            if (!resp.ok) return;   // sem sessão: dashboard.js redireciona
            const json = await resp.json();
            if (!json.ok) return;

            guardarDadosNoCache(json);
            mostrarDados(json);
        } catch (err) {
            /* backend fora do ar — dashboard.js trata o redirecionamento */
        }
    }

    /* Joga na tela os dados do perfil (vem do cache ou do servidor) */
    function mostrarDados(d) {
        preencher(d.nome, d.email);
        texto("contaMembro", "Membro desde " + formatarData(d.criado_em));
        texto("contaUltimoAcesso", formatarData(d.ultimo_acesso) || "—");
        texto("contaSequencia", (d.sequencia ?? 0) + (d.sequencia === 1 ? " dia" : " dias"));
        texto("contaDecks", d.decks ?? 0);
        texto("contaCartoes", d.cartoes ?? 0);

        configurarSenha(d.tem_senha);
        configurarProvedor(d.tem_google);
        configurarCamposExclusao(d.tem_senha);
    }

    const ICONE_GOOGLE = '<svg width="14" height="14" viewBox="0 0 48 48"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.4 29.3 35 24 35c-6.1 0-11-4.9-11-11s4.9-11 11-11c2.8 0 5.4 1.1 7.3 2.9l5.7-5.7C33.5 6.2 28.1 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.5-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 16 19 13 24 13c2.8 0 5.4 1.1 7.3 2.9l5.7-5.7C33.5 6.2 28.1 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35 26.7 36 24 36c-5.3 0-9.6-2.6-11.3-7l-6.5 5C9.6 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.3-2.3 4.3-4.1 5.6l6.2 5.2C39.9 35.6 44 30.4 44 24c0-1.3-.1-2.5-.4-3.5z"/></svg>';
    const ICONE_EMAIL  = '<svg width="14" height="14" viewBox="0 0 20 20" fill="none"><rect x="2.5" y="4" width="15" height="12" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M3 5l7 5 7-5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>';

    function configurarProvedor(temGoogle) {
        const el = document.getElementById("contaProvedor");
        if (!el) return;
        el.innerHTML = temGoogle ? ICONE_GOOGLE + "Google" : ICONE_EMAIL + "E-mail e senha";
        el.classList.remove("esqueleto", "esqueleto--largo");
    }

    /* Painel de senha muda de cara quando a conta não tem senha */
    function configurarSenha(possui) {
        temSenha = possui !== false;
        const campoAtual = document.getElementById("campoSenhaAtual");
        const inputAtual = document.getElementById("senhaAtual");

        campoAtual.hidden   = !temSenha;
        inputAtual.disabled = !temSenha;
        texto("senhaTitulo", temSenha ? "Trocar senha" : "Criar senha");
        texto("senhaSub", temSenha
            ? "Por segurança, confirme sua senha atual antes de definir uma nova."
            : "Sua conta usa login do Google. Crie uma senha para também poder entrar com e-mail e senha.");
        document.getElementById("btnSenha").textContent = temSenha ? "Trocar senha" : "Criar senha";
    }

    /* forcar = true depois de salvar (aí o servidor é a verdade);
       false no carregamento (aí respeitamos o que o usuário digitou). */
    function preencher(nome, email, forcar = false) {
        texto("contaNome", nome);
        texto("contaEmail", email);
        document.getElementById("contaAvatar").textContent = (nome.trim()[0] || "?").toUpperCase();
        valor("nome", nome, forcar);
        valor("email", email, forcar);
    }

    /* ------------------------------------------------------------
       Preferências (avatar, pomodoro, meta, matérias, notificações)
       ------------------------------------------------------------ */
    async function carregarPreferencias() {
        try {
            const resp = await fetch(`${BACKEND}/conta_preferencias.php`);
            if (!resp.ok) return;
            const json = await resp.json();
            if (!json.ok) return;

            prefs = json.preferencias;
            guardarPrefsNoCache(prefs);
            materiasEscolhidas = new Set(prefs.materias || []);

            // a cor/matérias/foto só vêm do servidor se o usuário ainda
            // não tiver escolhido outra coisa enquanto isto carregava
            marcarCor(prefs.avatar_cor);
            if (!tocado.has("cor")) aplicarCorAvatar(prefs.avatar_cor);
            if (!tocado.has("foto")) {
                aplicarFoto(prefs.avatar_url || null, prefs.avatar_pos_x, prefs.avatar_pos_y);
            }
            montarMaterias(json.materias_disponiveis || []);

            valor("pomoFoco", prefs.pomo_foco);
            valor("pomoPausa", prefs.pomo_pausa);
            valor("pomoPausaLonga", prefs.pomo_pausa_longa);
            valor("metaDiaria", prefs.meta_diaria);

            marcarSwitch("swLembrete", prefs.notif_lembrete);
            marcarSwitch("swResumo", prefs.notif_resumo);
        } catch (err) {
            /* sem preferências: a tela fica com os valores padrão do HTML */
        }
    }

    /* Liga o clique das bolinhas que já estão no HTML. Feito na carga
       da página: assim a pessoa pode escolher a cor antes de o servidor
       responder, sem o clique cair no vazio. */
    function ativarCores() {
        document.querySelectorAll(".conta-cor").forEach((b) => {
            b.addEventListener("click", () => escolherCor(b.dataset.cor));
        });
    }

    /* Marca qual cor está em uso (não recria nada). Respeita a escolha
       que o usuário acabou de fazer. */
    function marcarCor(atual) {
        if (tocado.has("cor")) return;
        document.querySelectorAll(".conta-cor").forEach((b) => {
            b.classList.toggle("ativa", b.dataset.cor === atual);
        });
    }

    /* Clicar na cor salva na hora (é uma mudança pequena e visível) */
    async function escolherCor(cor) {
        tocado.add("cor");
        if (prefs) { prefs.avatar_cor = cor; guardarPrefsNoCache(prefs); }
        document.querySelectorAll(".conta-cor").forEach((b) => {
            b.classList.toggle("ativa", b.dataset.cor === cor);
        });
        aplicarCorAvatar(cor);

        const dados = new FormData();
        dados.append("avatar_cor", cor);
        try {
            await fetch(`${BACKEND}/conta_preferencias.php`, { method: "POST", body: dados });
        } catch (err) {
            /* fica salvo na próxima tentativa */
        }
    }

    /* Aplica a cor no avatar grande e no cartão da barra lateral */
    function aplicarCorAvatar(cor) {
        const limpar = (el) => {
            [...el.classList].forEach((c) => {
                if (c.startsWith("avatar-cor--")) el.classList.remove(c);
            });
            el.classList.add("avatar-cor--" + cor);
        };
        const grande = document.getElementById("contaAvatar");
        if (grande) limpar(grande);
        document.querySelectorAll(".usuario__avatar").forEach(limpar);
    }

    function montarMaterias(disponiveis) {
        const caixa = document.getElementById("chipsMaterias");
        if (!caixa) return;
        // se o usuário já mexeu nos chips, mantém a seleção da tela
        if (tocado.has("materias") && caixa.children.length) return;
        caixa.innerHTML = "";

        disponiveis.forEach((materia) => {
            const chip = document.createElement("button");
            chip.type = "button";
            chip.className = "chip" + (materiasEscolhidas.has(materia) ? " active" : "");
            chip.textContent = materia;
            chip.setAttribute("aria-pressed", String(materiasEscolhidas.has(materia)));
            chip.addEventListener("click", () => {
                tocado.add("materias");
                const marcado = !materiasEscolhidas.has(materia);
                marcado ? materiasEscolhidas.add(materia) : materiasEscolhidas.delete(materia);
                chip.classList.toggle("active", marcado);
                chip.setAttribute("aria-pressed", String(marcado));
            });
            caixa.appendChild(chip);
        });
    }

    /* Salvar a seção "Estudo" (pomodoro + meta + matérias) */
    async function salvarEstudo() {
        const btn = document.getElementById("btnSalvarEstudo");
        const dados = new FormData();
        dados.append("pomo_foco", document.getElementById("pomoFoco").value);
        dados.append("pomo_pausa", document.getElementById("pomoPausa").value);
        dados.append("pomo_pausa_longa", document.getElementById("pomoPausaLonga").value);
        dados.append("meta_diaria", document.getElementById("metaDiaria").value);
        dados.append("materias", [...materiasEscolhidas].join(","));

        travar(btn, true, "Salvando…");
        try {
            const resp = await fetch(`${BACKEND}/conta_preferencias.php`, { method: "POST", body: dados });
            const json = await resp.json();
            msg("msgEstudo", json.msg || "Preferências salvas!", json.ok ? "sucesso" : "erro");

            if (json.ok && json.preferencias) {
                // o backend pode ter ajustado valores fora da faixa:
                // aqui ele é a verdade, então forçamos
                prefs = json.preferencias;
                guardarPrefsNoCache(prefs);
                valor("pomoFoco", prefs.pomo_foco, true);
                valor("pomoPausa", prefs.pomo_pausa, true);
                valor("pomoPausaLonga", prefs.pomo_pausa_longa, true);
                valor("metaDiaria", prefs.meta_diaria, true);
                marcarPendente("estudo", false);
            }
        } catch (err) {
            msg("msgEstudo", "Não foi possível conectar ao servidor.", "erro");
        } finally {
            travar(btn, false, "Salvar preferências");
        }
    }

    /* Notificações: cada interruptor salva sozinho */
    function marcarSwitch(id, ligado) {
        if (tocado.has(id)) return;      // o usuário já alternou este
        const el = document.getElementById(id);
        if (el) el.setAttribute("aria-checked", String(!!ligado));
    }

    async function alternarNotificacao(id, campo) {
        tocado.add(id);
        const el = document.getElementById(id);
        const novo = el.getAttribute("aria-checked") !== "true";
        el.setAttribute("aria-checked", String(novo));

        const dados = new FormData();
        dados.append(campo, novo ? "1" : "0");
        try {
            const resp = await fetch(`${BACKEND}/conta_preferencias.php`, { method: "POST", body: dados });
            const json = await resp.json();
            if (!json.ok) throw new Error();
            msg("msgNotif", novo ? "Ativado. Salvo!" : "Desativado. Salvo!", "sucesso");
        } catch (err) {
            el.setAttribute("aria-checked", String(!novo));   // desfaz
            msg("msgNotif", "Não foi possível salvar agora.", "erro");
        }
    }

    /* ------------------------------------------------------------
       Perfil e senha
       ------------------------------------------------------------ */
    async function salvarPerfil(e) {
        e.preventDefault();
        const btn = e.target.querySelector('button[type="submit"]');
        const dados = new FormData(e.target);

        // trocar o e-mail muda o login: vale confirmar antes
        const emailNovo = (document.getElementById("email").value || "").trim();
        const emailAtual = (document.getElementById("contaEmail").textContent || "").trim();
        if (emailNovo && emailAtual && emailNovo !== emailAtual) {
            const ok = await confirmar({
                titulo: "Trocar o e-mail de acesso?",
                texto: `Você passará a entrar no Kosmos com ${emailNovo} em vez de ${emailAtual}. ` +
                       "Sua senha continua a mesma.",
                botao: "Trocar e-mail",
            });
            if (!ok) return;
        }

        travar(btn, true, "Salvando…");
        try {
            const resp = await fetch(`${BACKEND}/conta_perfil.php`, { method: "POST", body: dados });
            const json = await resp.json();
            msg("msgPerfil", json.msg, json.ok ? "sucesso" : "erro");

            if (json.ok) {
                tocado.delete("nome");
                tocado.delete("email");
                preencher(json.nome, json.email, true);
                try {
                    const bruto = sessionStorage.getItem(CHAVE_DADOS);
                    if (bruto) {
                        const d = JSON.parse(bruto);
                        d.nome = json.nome;
                        d.email = json.email;
                        sessionStorage.setItem(CHAVE_DADOS, JSON.stringify(d));
                    }
                } catch (err) { /* segue sem atalho */ }
                document.querySelectorAll("[data-usuario]").forEach((el) => (el.textContent = json.nome));
                const primeiro = json.nome.trim().split(" ")[0];
                document.querySelectorAll("[data-usuario-primeiro]").forEach((el) => (el.textContent = primeiro));
                document.querySelectorAll("[data-usuario-inicial]").forEach((el) => {
                    el.textContent = (json.nome.trim()[0] || "?").toUpperCase();
                });
                sessionStorage.setItem("kosmos_usuario", json.nome);
                marcarPendente("perfil", false);
            }
        } catch (err) {
            msg("msgPerfil", "Não foi possível conectar ao servidor.", "erro");
        } finally {
            travar(btn, false, "Salvar alterações");
        }
    }

    async function trocarSenha(e) {
        e.preventDefault();
        const btn = document.getElementById("btnSenha");

        const nova     = document.getElementById("senhaNova").value;
        const confirma = document.getElementById("senhaConfirma").value;

        if (nova !== confirma) {
            msg("msgSenha", "A nova senha e a confirmação não coincidem.", "erro");
            return;
        }

        const dados = new FormData();
        if (temSenha) {
            dados.append("senha_atual", document.getElementById("senhaAtual").value);
        }
        dados.append("senha_nova", nova);

        travar(btn, true, temSenha ? "Trocando…" : "Criando…");
        try {
            const resp = await fetch(`${BACKEND}/conta_senha.php`, { method: "POST", body: dados });
            const json = await resp.json();
            msg("msgSenha", json.msg, json.ok ? "sucesso" : "erro");
            if (json.ok) {
                e.target.reset();
                configurarSenha(true);
                configurarCamposExclusao(true);
            }
        } catch (err) {
            msg("msgSenha", "Não foi possível conectar ao servidor.", "erro");
        } finally {
            btn.disabled = false;
            btn.textContent = temSenha ? "Trocar senha" : "Criar senha";
        }
    }

    /* ------------------------------------------------------------
       Sessões
       ------------------------------------------------------------ */
    async function sairDeTodos() {
        const ok = await confirmar({
            titulo: "Encerrar as outras sessões?",
            texto: "Quem estiver logado na sua conta em outro computador ou celular " +
                   "vai precisar entrar de novo. Este dispositivo continua conectado.",
            botao: "Encerrar as outras",
            perigo: true,
        });
        if (!ok) return;

        const btn = document.getElementById("btnSessoes");
        travar(btn, true, "Encerrando…");
        try {
            const resp = await fetch(`${BACKEND}/conta_sessoes.php`, { method: "POST" });
            const json = await resp.json();
            msg("msgSessoes", json.msg, json.ok ? "sucesso" : "erro");
        } catch (err) {
            msg("msgSessoes", "Não foi possível conectar ao servidor.", "erro");
        } finally {
            travar(btn, false, "Sair de todos os outros dispositivos");
        }
    }

    async function sair() {
        const ok = await confirmar({
            titulo: "Sair da conta?",
            texto: pendentes.size
                ? "Você tem alterações que ainda não foram salvas — elas serão perdidas."
                : "Você vai precisar entrar de novo para usar o Kosmos.",
            botao: "Sair",
            perigo: true,
        });
        if (!ok) return;

        pendentes.clear();   // já confirmou a perda; não avisa de novo ao navegar

        try {
            await fetch(`${BACKEND}/logout.php`);
        } catch (err) {
            /* mesmo se falhar, vamos para o login */
        }
        sessionStorage.removeItem("kosmos_usuario");
        sessionStorage.removeItem("kosmos_intro");
        window.location.replace("../login/index.html");
    }

    /* ------------------------------------------------------------
       Excluir a conta (irreversível — pede confirmação)
       ------------------------------------------------------------ */
    function configurarCamposExclusao(possuiSenha) {
        // conta com senha confirma pela senha; conta só do Google digita EXCLUIR
        document.getElementById("campoSenhaExcluir").hidden    = possuiSenha === false;
        document.getElementById("campoConfirmaExcluir").hidden = possuiSenha !== false;
    }

    function ativarExclusao() {
        const modal = document.getElementById("modalExcluir");
        const abrir  = () => { modal.classList.add("open"); };
        const fechar = () => {
            modal.classList.remove("open");
            document.getElementById("msgExcluir").hidden = true;
        };

        document.getElementById("btnExcluir").addEventListener("click", abrir);
        document.getElementById("fecharExcluir").addEventListener("click", fechar);
        document.getElementById("cancelarExcluir").addEventListener("click", fechar);
        modal.addEventListener("click", (e) => { if (e.target === modal) fechar(); });
        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape" && modal.classList.contains("open")) fechar();
        });

        document.getElementById("formExcluir").addEventListener("submit", async (e) => {
            e.preventDefault();
            const btn = document.getElementById("confirmarExcluir");
            const dados = new FormData();

            if (temSenha) {
                dados.append("senha", document.getElementById("senhaExcluir").value);
            } else {
                dados.append("confirmacao", document.getElementById("confirmaExcluir").value);
            }

            travar(btn, true, "Excluindo…");
            try {
                const resp = await fetch(`${BACKEND}/conta_excluir.php`, { method: "POST", body: dados });
                const json = await resp.json();

                if (json.ok) {
                    sessionStorage.clear();
                    // volta para a página inicial pública
                    window.location.replace("../inicio/index.html");
                    return;
                }
                msg("msgExcluir", json.msg, "erro");
            } catch (err) {
                msg("msgExcluir", "Não foi possível conectar ao servidor.", "erro");
            } finally {
                travar(btn, false, "Excluir para sempre");
            }
        });
    }

    /* ------------------------------------------------------------
       Foto de perfil
       A imagem é reduzida aqui no navegador (256x256, recorte no
       centro) antes de subir: economiza banda, padroniza o avatar e
       evita depender da extensão GD no servidor. O backend ainda
       valida tipo, tamanho e dimensões por conta própria.
       ------------------------------------------------------------ */
    const FOTO_LADO      = 512;   // limite do maior lado (sem recorte)
    const FOTO_MAX_BYTES = 2 * 1024 * 1024;

    function ativarFoto() {
        const input = document.getElementById("inputFoto");
        const btn = document.getElementById("btnFoto");
        const btnRemover = document.getElementById("btnRemoverFoto");
        if (!input || !btn) return;

        btn.addEventListener("click", () => input.click());
        // clicar no próprio avatar também abre o seletor
        document.getElementById("contaAvatar")?.addEventListener("click", () => input.click());

        input.addEventListener("change", async () => {
            const arquivo = input.files && input.files[0];
            input.value = "";                 // permite reenviar o mesmo arquivo
            if (!arquivo) return;

            if (!/^image\/(jpeg|png|webp)$/.test(arquivo.type)) {
                msg("msgFoto", "Use uma imagem JPG, PNG ou WEBP.", "erro");
                return;
            }
            if (arquivo.size > FOTO_MAX_BYTES) {
                msg("msgFoto", "A imagem precisa ter até 2 MB.", "erro");
                return;
            }

            travar(btn, true, "Enviando…");
            try {
                const blob = await reduzirImagem(arquivo);
                const dados = new FormData();
                dados.append("foto", blob, "avatar");

                const resp = await fetch(`${BACKEND}/conta_avatar.php`, { method: "POST", body: dados });
                const json = await resp.json();
                msg("msgFoto", json.msg, json.ok ? "sucesso" : "erro");
                if (json.ok) {
                    tocado.add("foto");
                    // foto nova entra centralizada e o editor abre para ajustar
                    aplicarFoto(json.avatar_url, 50, 50);
                    abrirEditorFoto();
                }
            } catch (err) {
                msg("msgFoto", "Não foi possível enviar a imagem.", "erro");
            } finally {
                travar(btn, false, "Enviar foto");
                btn.innerHTML = "Enviar foto";
            }
        });

        btnRemover?.addEventListener("click", async () => {
            const ok = await confirmar({
                titulo: "Remover a foto?",
                texto: "Seu avatar volta a ser a inicial do seu nome, na cor escolhida. " +
                       "A imagem é apagada do servidor.",
                botao: "Remover foto",
                perigo: true,
            });
            if (!ok) return;

            travar(btnRemover, true, "Removendo…");
            try {
                const dados = new FormData();
                dados.append("acao", "remover");
                const resp = await fetch(`${BACKEND}/conta_avatar.php`, { method: "POST", body: dados });
                const json = await resp.json();
                msg("msgFoto", json.msg, json.ok ? "sucesso" : "erro");
                if (json.ok) { tocado.add("foto"); aplicarFoto(null); }
            } catch (err) {
                msg("msgFoto", "Não foi possível remover a foto.", "erro");
            } finally {
                travar(btnRemover, false, "Remover foto");
            }
        });
    }

    /* Reduz a imagem mantendo a proporção (maior lado = FOTO_LADO).
       NÃO recorta: o enquadramento é escolhido depois, nos sliders —
       se cortássemos no centro aqui, não sobraria nada para ajustar.
       Mantém PNG/WEBP para não perder transparência. */
    function reduzirImagem(arquivo) {
        return new Promise((resolve, reject) => {
            const url = URL.createObjectURL(arquivo);
            const img = new Image();

            img.onload = () => {
                URL.revokeObjectURL(url);
                try {
                    const maior = Math.max(img.naturalWidth, img.naturalHeight);
                    const escala = maior > FOTO_LADO ? FOTO_LADO / maior : 1;
                    const larg = Math.max(1, Math.round(img.naturalWidth * escala));
                    const alt = Math.max(1, Math.round(img.naturalHeight * escala));

                    const canvas = document.createElement("canvas");
                    canvas.width = larg;
                    canvas.height = alt;
                    const ctx = canvas.getContext("2d");
                    ctx.imageSmoothingQuality = "high";
                    ctx.drawImage(img, 0, 0, larg, alt);

                    const tipo = arquivo.type === "image/png" || arquivo.type === "image/webp"
                        ? arquivo.type
                        : "image/jpeg";

                    canvas.toBlob((b) => (b ? resolve(b) : reject(new Error("canvas"))), tipo, 0.9);
                } catch (e) {
                    reject(e);
                }
            };
            img.onerror = () => { URL.revokeObjectURL(url); reject(new Error("imagem inválida")); };
            img.src = url;
        });
    }

    /* Mostra (ou tira) a foto no avatar grande e no cartão da barra
       lateral, no enquadramento escolhido (x% y%). */
    function aplicarFoto(url, posX, posY) {
        const x = Number.isFinite(+posX) ? +posX : 50;
        const y = Number.isFinite(+posY) ? +posY : 50;
        const alvos = [document.getElementById("contaAvatar"), ...document.querySelectorAll(".usuario__avatar")];

        alvos.forEach((el) => {
            if (!el) return;
            if (url) {
                el.style.backgroundImage = `url("${url}")`;
                el.style.backgroundPosition = x + "% " + y + "%";
                el.classList.add("avatar--foto");
            } else {
                el.style.backgroundImage = "";
                el.style.backgroundPosition = "";
                el.classList.remove("avatar--foto");
            }
        });

        // guarda o estado atual para o editor abrir já no lugar certo
        fotoAtual = url ? { url, x, y } : null;

        const btnAjustar = document.getElementById("btnAjustarFoto");
        if (btnAjustar) btnAjustar.hidden = !url;

        const btnRemover = document.getElementById("btnRemoverFoto");
        if (btnRemover) btnRemover.hidden = !url;

        // com foto, a cor do avatar só vale se ela for removida
        const rotulo = document.getElementById("rotuloCores");
        if (rotulo) {
            rotulo.textContent = url
                ? "Cor do avatar (usada quando não há foto)"
                : "Cor do seu avatar";
        }
    }

    /* ------------------------------------------------------------
       Editor da foto (mini tela com máscara circular)
       O palco do editor usa o mesmo modelo do avatar — background-size
       cover + background-position em % — então o que a pessoa enquadra
       arrastando é exatamente o que aparece depois no avatar.
       ------------------------------------------------------------ */
    let fotoAtual = null;              // { url, x, y } do que está salvo
    let editando = null;               // { x, y } enquanto o modal está aberto
    let dimFoto = null;                // tamanho natural da imagem

    function ativarEditorFoto() {
        const modal = document.getElementById("modalFoto");
        const palco = document.getElementById("recorte");
        if (!modal || !palco) return;

        document.getElementById("btnAjustarFoto")?.addEventListener("click", () => abrirEditorFoto());
        document.getElementById("fecharEditorFoto")?.addEventListener("click", fecharEditorFoto);
        document.getElementById("cancelarFoto")?.addEventListener("click", fecharEditorFoto);
        modal.addEventListener("click", (e) => { if (e.target === modal) fecharEditorFoto(); });

        document.getElementById("centralizarFoto")?.addEventListener("click", () => {
            editando = { x: 50, y: 50 };
            pintarPalco();
        });

        document.getElementById("salvarPosFoto")?.addEventListener("click", salvarEnquadramento);

        /* ---- arrastar (mouse, caneta e toque com Pointer Events) ---- */
        let arrastando = false;
        let ultimo = { x: 0, y: 0 };

        palco.addEventListener("pointerdown", (e) => {
            if (!editando) return;
            arrastando = true;
            ultimo = { x: e.clientX, y: e.clientY };
            palco.setPointerCapture(e.pointerId);
        });

        palco.addEventListener("pointermove", (e) => {
            if (!arrastando || !editando) return;
            moverEnquadramento(e.clientX - ultimo.x, e.clientY - ultimo.y);
            ultimo = { x: e.clientX, y: e.clientY };
        });

        const soltar = (e) => {
            if (!arrastando) return;
            arrastando = false;
            try { palco.releasePointerCapture(e.pointerId); } catch (err) { /* já solto */ }
        };
        palco.addEventListener("pointerup", soltar);
        palco.addEventListener("pointercancel", soltar);

        /* ---- teclado: setas movem de 2% em 2% ---- */
        palco.addEventListener("keydown", (e) => {
            if (!editando) return;
            const passo = 2;
            const mapa = {
                ArrowLeft:  [passo, 0],
                ArrowRight: [-passo, 0],
                ArrowUp:    [0, passo],
                ArrowDown:  [0, -passo],
            };
            const mov = mapa[e.key];
            if (!mov) return;
            e.preventDefault();
            editando.x = limitarPercent(editando.x - mov[0]);
            editando.y = limitarPercent(editando.y - mov[1]);
            pintarPalco();
        });

        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape" && modal.classList.contains("open")) fecharEditorFoto();
        });
    }

    /* Abre a mini tela já mostrando a foto no enquadramento salvo */
    function abrirEditorFoto() {
        if (!fotoAtual) return;

        editando = { x: fotoAtual.x, y: fotoAtual.y };
        dimFoto = null;

        // precisamos do tamanho natural para converter arrasto em %
        const img = new Image();
        img.onload = () => {
            dimFoto = { w: img.naturalWidth, h: img.naturalHeight };
        };
        img.src = fotoAtual.url;

        pintarPalco();
        document.getElementById("modalFoto").classList.add("open");
        document.getElementById("recorte")?.focus();
    }

    function fecharEditorFoto() {
        document.getElementById("modalFoto")?.classList.remove("open");
        editando = null;
    }

    function pintarPalco() {
        const palcoImg = document.getElementById("recorteImg");
        if (!palcoImg || !editando || !fotoAtual) return;
        palcoImg.style.backgroundImage = `url("${fotoAtual.url}")`;
        palcoImg.style.backgroundPosition = editando.x + "% " + editando.y + "%";
    }

    /* Converte o arrasto em pixels para porcentagem de background-position.
       Com background-size: cover, só a sobra da imagem se move — então o
       quanto 1px vale depende dessa sobra. */
    function moverEnquadramento(dxPx, dyPx) {
        const palco = document.getElementById("recorte");
        if (!palco || !editando || !dimFoto) return;

        const r = palco.getBoundingClientRect();
        const escala = Math.max(r.width / dimFoto.w, r.height / dimFoto.h);
        const sobraX = dimFoto.w * escala - r.width;
        const sobraY = dimFoto.h * escala - r.height;

        if (sobraX > 0) editando.x = limitarPercent(editando.x - (dxPx / sobraX) * 100);
        if (sobraY > 0) editando.y = limitarPercent(editando.y - (dyPx / sobraY) * 100);

        pintarPalco();
    }

    function limitarPercent(v) {
        return Math.max(0, Math.min(100, Math.round(v)));
    }

    async function salvarEnquadramento() {
        if (!editando || !fotoAtual) return;
        const btn = document.getElementById("salvarPosFoto");
        const { x, y } = editando;

        travar(btn, true, "Salvando…");
        try {
            const dados = new FormData();
            dados.append("avatar_pos_x", x);
            dados.append("avatar_pos_y", y);
            const resp = await fetch(`${BACKEND}/conta_preferencias.php`, { method: "POST", body: dados });
            const json = await resp.json();

            if (json.ok) {
                tocado.add("pos");
                aplicarFoto(fotoAtual.url, x, y);       // avatar grande + barra lateral
                if (prefs) {
                    prefs.avatar_pos_x = x;
                    prefs.avatar_pos_y = y;
                    guardarPrefsNoCache(prefs);
                }
                msg("msgFoto", "Posição salva!", "sucesso");
                fecharEditorFoto();
                return;
            }
            msg("msgFoto", json.msg || "Não foi possível salvar a posição.", "erro");
        } catch (err) {
            msg("msgFoto", "Não foi possível salvar a posição.", "erro");
        } finally {
            travar(btn, false, "Salvar posição");
        }
    }

    /* ------------------------------------------------------------
       Confirmação
       Abre a mini tela e devolve uma promessa: true se a pessoa
       confirmou, false se desistiu. Usada nas ações que mexem em algo
       de verdade — remover a foto, encerrar as outras sessões, sair da
       conta e trocar o e-mail de acesso.
       ------------------------------------------------------------ */
    function confirmar({ titulo, texto, botao = "Confirmar", perigo = false }) {
        const modal = document.getElementById("modalConfirma");
        const sim = document.getElementById("confirmaSim");
        const nao = document.getElementById("confirmaNao");
        const fechar = document.getElementById("confirmaFechar");

        // sem o modal na página (outra aba), não trava a ação
        if (!modal || !sim) return Promise.resolve(true);

        texto_(titulo, texto, botao, perigo);
        modal.classList.add("open");
        sim.focus();

        return new Promise((resolve) => {
            const encerrar = (resposta) => {
                modal.classList.remove("open");
                sim.onclick = null;
                nao.onclick = null;
                fechar.onclick = null;
                modal.onclick = null;
                document.removeEventListener("keydown", noEsc);
                resolve(resposta);
            };
            const noEsc = (e) => { if (e.key === "Escape") encerrar(false); };

            sim.onclick = () => encerrar(true);
            nao.onclick = () => encerrar(false);
            fechar.onclick = () => encerrar(false);
            modal.onclick = (e) => { if (e.target === modal) encerrar(false); };
            document.addEventListener("keydown", noEsc);
        });

        function texto_(t, msgTexto, rotulo, ehPerigo) {
            document.getElementById("confirmaTitulo").textContent = t;
            document.getElementById("confirmaTexto").textContent = msgTexto;
            sim.textContent = rotulo;
            sim.className = "dash-btn " + (ehPerigo ? "dash-btn--danger" : "dash-btn--primary");
        }
    }

    /* ------------------------------------------------------------
       Alterações não salvas
       Editar um campo e sair da página sem clicar em salvar era perda
       silenciosa. Agora o navegador avisa antes de fechar/recarregar
       ou de trocar de aba do dashboard.
       ------------------------------------------------------------ */
    const pendentes = new Set();

    function marcarPendente(chave, sim = true) {
        sim ? pendentes.add(chave) : pendentes.delete(chave);
        const aviso = document.getElementById("avisoPendente");
        if (aviso) aviso.hidden = pendentes.size === 0;
    }

    function ativarAvisoDeSaida() {
        window.addEventListener("beforeunload", (e) => {
            if (pendentes.size === 0) return;
            e.preventDefault();
            e.returnValue = "";      // o navegador mostra o aviso padrão dele
        });
    }

    /* ------------------------------------------------------------
       Helpers
       ------------------------------------------------------------ */
    function texto(id, valorTexto) {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = valorTexto;
        el.classList.remove("esqueleto", "esqueleto--largo");
    }

    /* Só escreve no campo se o usuário não estiver mexendo nele.
       forcar = true ignora isso (usado depois de salvar). */
    function valor(id, v, forcar = false) {
        const el = document.getElementById(id);
        if (!el || v === undefined || v === null) return;
        if (!forcar && (tocado.has(id) || document.activeElement === el)) return;
        el.value = v;
    }

    function msg(id, textoMsg, tipo) {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = textoMsg;
        el.className = "msg msg--" + tipo;
        el.hidden = false;
    }

    function travar(btn, travado, textoBtn) {
        if (!btn) return;
        btn.disabled = travado;
        btn.textContent = textoBtn;
    }

    /* "2026-06-25 13:57:17" ou "2026-06-25" -> "25 de junho de 2026"
       Monta a data pelos números em vez de deixar o navegador interpretar:
       new Date("2026-08-26") é lido como UTC e, em fuso negativo, mostra
       o dia anterior. */
    function formatarData(dataSql) {
        if (!dataSql) return "—";
        const p = String(dataSql).match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (!p) return "—";
        const d = new Date(Number(p[1]), Number(p[2]) - 1, Number(p[3]));
        if (isNaN(d)) return "—";
        return d.toLocaleDateString("pt-BR", { day: "numeric", month: "long", year: "numeric" });
    }
})();
