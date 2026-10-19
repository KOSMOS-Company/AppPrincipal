/* ============================================================
   KOSMOS — visual.js  (a escolha entre o visual completo e o Lite)

   A preferência é do APARELHO, não da conta: quem estuda num
   notebook potente e num celular antigo quer coisas diferentes em
   cada um. Por isso fica no localStorage (`kosmos_visual`), como a
   barra recolhida.

   Quem LÊ a preferência é o partes/fundo.php, antes da primeira
   pintura. Este arquivo só cuida da troca: a classe no <html>, o
   que fica salvo, e o aviso `kosmos:visual` para quem anima algo
   (o céu, em shared/cosmos-gl.js e cosmos.js) parar ou voltar.

   Controles (qualquer um, em qualquer página):
     [data-visual-opcao="completo|lite"]  role="radio"   — Conta
     [data-visual-alternar]               role="menuitemcheckbox" — engrenagem
   ============================================================ */

(function () {
    const CHAVE = "kosmos_visual";
    const raiz = document.documentElement;

    const atual = () => (raiz.classList.contains("kosmos-lite") ? "lite" : "completo");

    function sincronizar() {
        const modo = atual();
        document.querySelectorAll("[data-visual-opcao]").forEach((el) => {
            const sim = el.dataset.visualOpcao === modo;
            el.setAttribute("aria-checked", String(sim));
            el.tabIndex = sim ? 0 : -1;   // grupo de rádio: Tab entra no marcado
        });
        document.querySelectorAll("[data-visual-alternar]").forEach((el) => {
            el.setAttribute("aria-checked", String(modo === "lite"));
        });
    }

    function definir(modo, salvar = true) {
        if (modo !== "lite") modo = "completo";
        if (modo === atual()) return;

        raiz.classList.toggle("kosmos-lite", modo === "lite");
        if (salvar) {
            try {
                if (modo === "lite") localStorage.setItem(CHAVE, "lite");
                else localStorage.removeItem(CHAVE);
            } catch (e) { /* navegação privada: vale só até fechar a aba */ }
        }

        document.dispatchEvent(new CustomEvent("kosmos:visual", { detail: modo }));
        sincronizar();
    }

    window.KosmosVisual = { atual, definir };

    document.addEventListener("click", (e) => {
        const opcao = e.target.closest("[data-visual-opcao]");
        if (opcao) { definir(opcao.dataset.visualOpcao); return; }
        if (e.target.closest("[data-visual-alternar]")) {
            definir(atual() === "lite" ? "completo" : "lite");
        }
    });

    // Setas no grupo de rádio, como num <input type="radio">
    document.addEventListener("keydown", (e) => {
        const opcao = e.target.closest("[data-visual-opcao]");
        if (!opcao || !["ArrowLeft", "ArrowRight", "ArrowUp", "ArrowDown"].includes(e.key)) return;
        e.preventDefault();
        const grupo = [...opcao.parentElement.querySelectorAll("[data-visual-opcao]")];
        const passo = e.key === "ArrowLeft" || e.key === "ArrowUp" ? -1 : 1;
        const proxima = grupo[(grupo.indexOf(opcao) + passo + grupo.length) % grupo.length];
        definir(proxima.dataset.visualOpcao);
        proxima.focus();
    });

    // Outra aba mudou a escolha: esta acompanha
    window.addEventListener("storage", (e) => {
        if (e.key === CHAVE) definir(e.newValue, false);
        if (e.key === CHAVE_FOCO) definirFoco(e.newValue === "1", false);
    });

    /* ------------------------------------------------------------
       MODO FOCO (complemento do Lite, desligado por padrão)
       O Lite é sobre o aparelho; o Foco é sobre a ATENÇÃO: some o
       Orion flutuante e os avisos de "+XP" / "conquista desbloqueada"
       (o XP continua contando — dá para ver na barra e em Conquistas).
       Também por aparelho, lido pelo partes/fundo.php antes da pintura.
       Controle: [data-foco-alternar] na Conta › Estudo, que só aparece com
       o Lite escolhido; o efeito também só vale com o Lite (css/lite.css).
       ------------------------------------------------------------ */
    const CHAVE_FOCO = "kosmos_foco";
    const CHAVE_FOCO_POMO = "kosmos_foco_pomodoro";
    const lerChave = (k) => { try { return localStorage.getItem(k) === "1"; } catch (e) { return false; } };
    const gravarChave = (k, ligar) => {
        try {
            if (ligar) localStorage.setItem(k, "1");
            else localStorage.removeItem(k);
        } catch (e) { /* navegação privada */ }
    };

    /* A escolha da pessoa (o interruptor) é uma coisa; a classe no
       <html> é outra: com "ligar durante o Pomodoro", o foco também
       vale enquanto um ciclo de FOCO está correndo, sem mexer na
       escolha salva. */
    let focoEscolhido = lerChave(CHAVE_FOCO) || raiz.classList.contains("kosmos-foco");
    const focoAtivo = () => focoEscolhido;

    function pomodoroEmFoco() {
        if (!lerChave(CHAVE_FOCO_POMO)) return false;
        try {
            const chave = window.kosmosChaveConta ? window.kosmosChaveConta("kosmos:pomodoro") : "kosmos:pomodoro";
            const s = JSON.parse(localStorage.getItem(chave) || "null");
            return !!s && s.modo === "foco" && !!s.rodando && (!s.fimEm || s.fimEm > Date.now());
        } catch (e) {
            return false;
        }
    }

    /* O foco escolhido no interruptor segue sendo do Lite. O do Pomodoro
       tem classe própria (kosmos-foco-pomodoro) porque vale nos dois
       visuais: o css/lite.css trata as duas classes como "foco". */
    function aplicarFoco() {
        raiz.classList.toggle("kosmos-foco", focoEscolhido);
        raiz.classList.toggle("kosmos-foco-pomodoro", pomodoroEmFoco());
    }

    function sincronizarFoco() {
        document.querySelectorAll("[data-foco-alternar]").forEach((el) => {
            el.setAttribute("aria-checked", String(focoEscolhido));
        });
        document.querySelectorAll("[data-foco-pomodoro-alternar]").forEach((el) => {
            el.setAttribute("aria-checked", String(lerChave(CHAVE_FOCO_POMO)));
        });
    }
    function definirFoco(ligar, salvar = true) {
        focoEscolhido = !!ligar;
        if (salvar) gravarChave(CHAVE_FOCO, focoEscolhido);
        aplicarFoco();
        sincronizarFoco();
    }
    document.addEventListener("click", (e) => {
        if (e.target.closest("[data-foco-alternar]")) definirFoco(!focoAtivo());
        if (e.target.closest("[data-foco-pomodoro-alternar]")) {
            gravarChave(CHAVE_FOCO_POMO, !lerChave(CHAVE_FOCO_POMO));
            aplicarFoco();
            sincronizarFoco();
        }
    });
    window.KosmosVisual.foco = { ativo: focoAtivo, definir: definirFoco };

    // O Pomodoro anda sozinho (e muda em outra aba): confere de tempos em tempos
    aplicarFoco();
    sincronizarFoco();
    setInterval(aplicarFoco, 2000);
    window.addEventListener("storage", (e) => {
        if (e.key === CHAVE_FOCO_POMO || (e.key && e.key.indexOf("kosmos:pomodoro") !== -1)) {
            aplicarFoco();
            sincronizarFoco();
        }
    });

    /* ------------------------------------------------------------
       SUGERIR O LITE EM APARELHO MAIS FRACO — uma vez só
       Muita gente não sabe que o Lite existe. Se o aparelho dá sinais
       de ser modesto (pouca memória, poucos núcleos), se o navegador
       está em economia de dados, ou se o sistema pede menos
       transparência, um aviso discreto oferece a troca. Respondeu
       (sim ou não) ou fechou: não volta mais neste aparelho.
       ------------------------------------------------------------ */
    const CHAVE_SUGERIU = "kosmos_visual_sugerido";

    function aparelhoModesto() {
        const memoria = navigator.deviceMemory;                 // GB (Chrome/Edge)
        const nucleos = navigator.hardwareConcurrency;
        const conexao = navigator.connection;
        return (memoria && memoria <= 4)
            || (nucleos && nucleos <= 2)
            || (conexao && conexao.saveData)
            || window.matchMedia("(prefers-reduced-transparency: reduce)").matches;
    }

    function sugerirLite() {
        if (atual() === "lite") return;
        try { if (localStorage.getItem(CHAVE_SUGERIU)) return; } catch (e) { return; }
        if (!aparelhoModesto()) return;

        const marcar = () => { try { localStorage.setItem(CHAVE_SUGERIU, "1"); } catch (e) { /* ignora */ } };

        const aviso = document.createElement("div");
        aviso.className = "visual-sugestao";
        aviso.setAttribute("role", "dialog");
        aviso.setAttribute("aria-label", "Sugestão de visual leve");
        aviso.innerHTML =
            '<p class="visual-sugestao__txt"><strong>Seu aparelho parece mais modesto.</strong> ' +
            'Quer usar o visual leve? Menos brilho e movimento, mais fluidez e bateria.</p>' +
            '<div class="visual-sugestao__acoes">' +
                '<button type="button" class="dash-btn dash-btn--outline" data-sug="nao">Agora não</button>' +
                '<button type="button" class="dash-btn dash-btn--primary" data-sug="sim">Usar visual leve</button>' +
            '</div>';
        document.body.appendChild(aviso);
        requestAnimationFrame(() => aviso.classList.add("visual-sugestao--dentro"));

        const fechar = () => {
            aviso.classList.remove("visual-sugestao--dentro");
            setTimeout(() => aviso.remove(), 300);
        };
        aviso.addEventListener("click", (e) => {
            const b = e.target.closest("[data-sug]");
            if (!b) return;
            marcar();
            if (b.dataset.sug === "sim") definir("lite");
            fechar();
        });
    }

    sincronizar();
    sincronizarFoco();
    // dá um respiro depois de a página abrir: o aviso não disputa a primeira olhada
    setTimeout(sugerirLite, 2500);
})();
