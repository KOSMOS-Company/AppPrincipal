/* ============================================================
   KOSMOS — exercicios.js
   exercicios.php -> o panorama em cima, a lista embaixo
   ============================================================ */

const LETRAS = ["A", "B", "C", "D"];
const BACKEND = "../../../Backend/php";

/* ============================================================
   ESTANTE (exercicios.php) — abre/fecha modal, escuta eventos
   ============================================================ */
const btnNovaMateria = document.getElementById("btnNovaMateria");
const gridMaterias = document.getElementById("gridMaterias");
const listaMaterias = document.getElementById("listaMaterias");
const controles = document.getElementById("exControles");
const vazioMaterias = document.getElementById("vazioMaterias");
const filtros = document.getElementById("filtros");
const ordemMaterias = document.getElementById("ordemMaterias");

if (btnNovaMateria) {
    btnNovaMateria.addEventListener("click", () => {
        window.KosmosExercicioMateriaForm?.abrir(null);
    });
}

/* Linhas que já vêm do servidor (PHP) também precisam do lápis.
   Delegação: um clique só, para todas. A matéria recém-criada não
   está no JSON do servidor ainda, e por isso a linha que o JS monta
   cuida do próprio lápis (ver criarLinhaMateria). */
listaMaterias?.addEventListener("click", (ev) => {
    const lixeira = ev.target.closest("[data-apagar-exercicio-materia]");
    if (lixeira) {
        ev.preventDefault();
        ev.stopPropagation();
        const id = Number(lixeira.dataset.apagarExercicioMateria);
        const nome = lixeira.closest(".ex-linha")?.dataset.nome ?? "";
        window.KosmosExercicioMateriaForm?.apagarDireto(id, nome);
        return;
    }
    const btn = ev.target.closest("[data-editar-exercicio-materia]");
    if (!btn) return;
    ev.preventDefault();
    ev.stopPropagation();
    const id = Number(btn.dataset.editarExercicioMateria);
    const dados = document.getElementById("dadosExercicioMaterias");
    if (!dados) return;
    let lista = [];
    try {
        lista = JSON.parse(dados.textContent) || [];
    } catch (_) {
        return;
    }
    const materia = lista.find((m) => m.id === id);
    if (materia) {
        window.KosmosExercicioMateriaForm?.abrir(materia);
    }
});

document.addEventListener("exercicioMateria:salvo", (e) => {
    const { materia, novo } = e.detail;
    if (novo) {
        // nasce no fim dos dois: é onde a matéria nova fica depois de
        // recarregar (ela ganha a maior ordem)
        if (vazioMaterias) vazioMaterias.hidden = true;
        if (controles) controles.hidden = false;
        gridMaterias?.append(criarPontoMateria(materia));
        listaMaterias?.append(criarLinhaMateria(materia));
        /* nasce no fim e já entra no lugar: numa lista por "mais
           recentes" a matéria nova é a primeira, não a última */
        ordenarLista(ordemAtual);
        reposicionarMapa();
        atualizarContadorFiltro(materia.materia, 1);
    } else {
        // atualiza o ponto e a linha existentes
        const ponto = gridMaterias?.querySelector(`.ex-ponto[data-id="${materia.id}"]`);
        if (ponto) ponto.replaceWith(criarPontoMateria(materia));
        const linha = listaMaterias?.querySelector(`.ex-linha[data-id="${materia.id}"]`);
        if (linha) linha.replaceWith(criarLinhaMateria(materia));
        /* renomear pode bagunçar a ordem escolhida (é o próprio critério
           que mudou), então a linha volta para o lugar dela */
        ordenarLista(ordemAtual);
        reposicionarMapa();
    }
});

document.addEventListener("exercicioMateria:apagado", (e) => {
    const { id } = e.detail;
    const ponto = gridMaterias?.querySelector(`.ex-ponto[data-id="${id}"]`);
    const linha = listaMaterias?.querySelector(`.ex-linha[data-id="${id}"]`);
    if (!ponto && !linha) return;

    const materia = linha?.dataset.materia ?? "";
    ponto?.remove();
    linha?.remove();
    reposicionarMapa();
    atualizarContadorFiltro(materia, -1);
    if (listaMaterias && !listaMaterias.querySelector(".ex-linha")) {
        if (vazioMaterias) vazioMaterias.hidden = false;
        if (controles) controles.hidden = true;
    }
});

/* Filtros por matéria (chips). Eles valem só para a LISTA: o panorama
   é o retrato da prateleira inteira e não se mexe a cada clique — um
   céu que se abre e fecha a cada chip vira ruído. */
if (filtros) {
    filtros.addEventListener("click", (e) => {
        const chip = e.target.closest(".chip");
        if (!chip) return;
        filtros.querySelectorAll(".chip").forEach(c => c.classList.remove("active"));
        chip.classList.add("active");
        const filtro = chip.dataset.materia;
        listaMaterias?.querySelectorAll(".ex-linha").forEach(linha => {
            linha.hidden = !(filtro === "todos" || linha.dataset.materia === filtro);
        });
    });
}

/* Tudo que a pessoa digitou (nome, descrição, ícone) passa por aqui antes
   do innerHTML — inclusive nos atributos, por isso as aspas também. */
function esc(texto) {
    const mapa = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" };
    return String(texto ?? "").replace(/[&<>"']/g, (c) => mapa[c]);
}

/* O ponto do céu é o mesmo link da lista: um <a> para a matéria, com o
   nome em data-nome (o balão do hover lê esse atributo) e em
   aria-label — o céu não escreve o nome no chão, mas quem usa teclado
   ou leitor de tela precisa saber que aquilo é uma matéria. */
function criarPontoMateria(m) {
    const id = Number(m.id) || 0;
    const qtd = Number(m.qtd) || 0;
    const tamanho = tamanhoEstrela(qtd);
    const tpl = document.createElement("template");
    tpl.innerHTML = `
        <a class="ex-ponto${qtd === 0 ? " ex-ponto--vazia" : ""} ex-ponto--${esc(m.cor)}"
           href="exercicio_materia.php?id=${id}"
           data-id="${id}" data-qtd="${qtd}" data-nome="${esc(m.nome)}"
           data-x="50" data-y="50" data-tamanho="${tamanho}"
           aria-label="${esc(m.nome + " — " + rotuloQtdExercicios(qtd))}"
           style="--x: 50%; --y: 50%; --tamanho: ${tamanho}px"></a>
    `;
    return tpl.content.firstElementChild;
}

/* O desenho tem gêmeo em partes/exercicio-materia-linha.php — o PHP
   monta a primeira pintura da lista e o JS monta a linha da matéria
   que acabou de ser criada. Mexeu num, mexe no outro. */
function criarLinhaMateria(m) {
    const id = Number(m.id) || 0;
    const qtd = Number(m.qtd) || 0;
    const tpl = document.createElement("template");
    tpl.innerHTML = `
        <article class="ex-linha${qtd === 0 ? " ex-linha--vazia" : ""} ex-linha--${esc(m.cor)}"
                 data-id="${id}" data-materia="${esc(m.materia)}" data-qtd="${qtd}"
                 data-nome="${esc(m.nome)}" data-criado="${esc(m.criado_em)}"
                 style="--intensidade: ${intensidadeQtd(qtd)}">
            <a class="ex-linha__link" href="exercicio_materia.php?id=${id}" draggable="false"
               ${m.descricao ? `title="${esc(m.nome + " — " + m.descricao)}"` : ""}>
                <span class="ex-linha__ponto" aria-hidden="true"></span>
                <h3 class="ex-linha__nome">${esc(m.nome)}</h3>
                <span class="ex-linha__mat">${esc(m.materia)}</span>
                <span class="ex-linha__conta">${esc(rotuloQtdExercicios(qtd))}</span>
            </a>
            <button type="button" class="ex-linha__editar" data-editar-exercicio-materia="${id}"
                    title="Personalizar esta matéria"
                    aria-label="Personalizar a matéria ${esc(m.nome)}">
                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 16h3l8-8-3-3-8 8v3Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M12.5 4.5l3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            </button>
            <button type="button" class="ex-linha__editar ex-linha__apagar" data-apagar-exercicio-materia="${id}"
                    title="Excluir esta matéria"
                    aria-label="Excluir a matéria ${esc(m.nome)}">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 7h14M10 7V5h4v2M7 7l1 12h8l1-12M11 11v5M13 11v5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
        </article>
    `;
    const linha = tpl.content.firstElementChild;
    linha.querySelector("[data-editar-exercicio-materia]").addEventListener("click", (ev) => {
        ev.preventDefault();
        ev.stopPropagation();
        window.KosmosExercicioMateriaForm?.abrir(m);
    });
    return linha;
}

/* ============================================================
   A ORDEM — escolhida aqui, lembrada no navegador
   ============================================================
   Trocar a ordem não pede nada ao servidor: nome, quantidade e data
   de criação já estão em cada linha (data-nome / data-qtd /
   data-criado), então a lista inteira se reordena no navegador. A
   preferência é do navegador (kosmos_ordem_exercicios), não da conta —
   é o mesmo desenho da lateral guardada no dashboard.js, e a
   navegação privada só perde a lembrança. */
const ORDEM_CHAVE = "kosmos_ordem_exercicios";

/** Qual linha vai antes de qual, para cada escolha da pessoa. */
function comparadorOrdem(chave) {
    const nome = (linha) => linha.dataset.nome || "";
    switch (chave) {
        case "alfabetica":
            return (a, b) => nome(a).localeCompare(nome(b), "pt-BR");
        case "exercicios":
            return (a, b) =>
                (Number(b.dataset.qtd) || 0) - (Number(a.dataset.qtd) || 0) ||
                nome(a).localeCompare(nome(b), "pt-BR");
        /* mais novo primeiro: criado_em é data do MySQL
           (AAAA-MM-DD HH:MM:SS), que como texto já ordena por data. */
        case "recentes":
        default:
            return (a, b) =>
                (b.dataset.criado || "").localeCompare(a.dataset.criado || "") ||
                Number(a.dataset.id) - Number(b.dataset.id);
    }
}

function ordenarLista(chave) {
    if (!listaMaterias) return;
    const comparar = comparadorOrdem(chave);
    const linhas = [...listaMaterias.querySelectorAll(".ex-linha")].sort(comparar);
    /* Só o que está fora do lugar se move: reappendar a lista inteira a
       cada troca de ordem faria toda linha piscar de novo. */
    let anterior = null;
    for (const linha of linhas) {
        const alvo = anterior ? anterior.nextElementSibling : listaMaterias.firstElementChild;
        if (linha !== alvo) listaMaterias.insertBefore(linha, alvo);
        anterior = linha;
    }
}

function lembrarOrdem(chave) {
    try {
        localStorage.setItem(ORDEM_CHAVE, chave);
    } catch (_) {
        /* navegação privada: a ordem vale só nesta visita */
    }
}

/** O que o navegador guardou — "recentes" na primeira visita. */
function ordemSalva() {
    try {
        const salva = localStorage.getItem(ORDEM_CHAVE);
        return salva === "alfabetica" || salva === "exercicios" ? salva : "recentes";
    } catch (_) {
        return "recentes";
    }
}

/* A ordem em vigor. Fica à mão porque criar ou renomear uma matéria mexe
   justamente nos dados que a ordenação lê — quando isso acontece a lista
   se reordena sozinha, sem esperar a pessoa clicar em nada. */
let ordemAtual = ordemSalva();

/* O botão da ordem: mostra a escolha em vigor e esconde as três opções
   atrás dele. Mesmo desenho do menu da engrenagem na lateral (ver
   ativarMenuConfig, no dashboard.js) — um item escolhido é uma decisão
   tomada, então o menu sai da frente; fora do botão e Esc fecham sem
   trocar nada. */
function ativarMenuOrdem() {
    const botao = document.getElementById("ordemBotao");
    const rotulo = document.getElementById("ordemAtual");
    if (!botao || !ordemMaterias) return;

    const aberto = () => botao.getAttribute("aria-expanded") === "true";

    function abrir(sim) {
        botao.setAttribute("aria-expanded", String(sim));
        ordemMaterias.hidden = !sim;
    }

    botao.addEventListener("click", (e) => {
        e.stopPropagation();   // senão o clique fecha no mesmo instante
        abrir(!aberto());
    });

    ordemMaterias.addEventListener("click", (e) => {
        const item = e.target.closest(".ordem__item[data-ordem]");
        if (!item) return;
        /* O rótulo do botão é o texto do item. Por isso o visto da
           escolha é um ::after no CSS e não um desenho dentro do
           item — senão o botão anunciaria "✓ Mais recentes". */
        if (rotulo) rotulo.textContent = item.textContent.trim();
        ordemMaterias.querySelectorAll(".ordem__item").forEach((i) => {
            i.setAttribute("aria-checked", String(i === item));
        });
        ordemAtual = item.dataset.ordem;
        ordenarLista(ordemAtual);
        lembrarOrdem(ordemAtual);
        abrir(false);
    });

    document.addEventListener("click", (e) => {
        if (!aberto()) return;
        if (!ordemMaterias.contains(e.target) && e.target !== botao) abrir(false);
    });

    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && aberto()) {
            abrir(false);
            botao.focus();
        }
    });
}

if (ordemMaterias) {
    ativarMenuOrdem();

    /* A preferência entra pelo MESMO caminho do dedo: marcar a opção e
       deixar a lista se reordenar é a mesma coisa que a pessoa ter
       clicado. Uma regra só, e a lista já chega na ordem antes de ser
       vista. */
    ordemMaterias.querySelector(`.ordem__item[data-ordem="${ordemAtual}"]`)?.click();
}

/* ============================================================
   O PANORAMA — as matérias como pontos de uma constelação
   ============================================================
   Tudo aqui tem gêmeo em exercicios.php (posicaoEstrela,
   tamanhoEstrela, intensidadeQtd, alturaMapa): o PHP monta a
   primeira pintura para o painel chegar pronto do servidor, e o JS
   redesenha quando a prateleira muda (criou, apagou) ou quando a tela
   muda de largura. Mexeu num, mexe no outro.

   O painel é retrato: mostra o TODO de uma vez, não reage ao filtro
   nem à ordem e não escreve o nome de ninguém no céu. Cada ponto é
   um link para a matéria (o mesmo destino da lista) e diz o nome no
   hover. O que ele guarda é o TODO — os pontos que sobrassem depois
   de um filtro seriam outro céu, e a faixa existiria só para repetir
   o que a lista já diz. */

const NS_SVG = "http://www.w3.org/2000/svg";

/** Onde o ponto i fica, em % do painel (espiral de Vogel, ângulo áureo). */
function posicaoEstrela(i, n, rx, ry) {
    if (n <= 1) return { x: 50, y: 50 };
    const ang = (i * 137.508) * Math.PI / 180;
    const raio = Math.sqrt(i / (n - 1));
    return {
        x: Math.round((50 + raio * Math.cos(ang) * rx) * 100) / 100,
        y: Math.round((50 + raio * Math.sin(ang) * ry) * 100) / 100,
    };
}

/** O tamanho do ponto é a quantidade de exercícios guardados nela. */
function tamanhoEstrela(qtd) {
    if (qtd <= 0) return 13;
    if (qtd <= 4) return 15;
    if (qtd <= 11) return 17;
    if (qtd <= 29) return 19;
    return 22;
}

/** O quanto o círculo da lista acende: a mesma faixa de quantidade,
 *  na intensidade da cor em vez do tamanho. */
function intensidadeQtd(qtd) {
    if (qtd <= 0) return ".34";
    if (qtd <= 4) return ".52";
    if (qtd <= 11) return ".72";
    if (qtd <= 29) return ".88";
    return "1";
}

/** O quanto a faixa cresce: um pouco, e só. */
function alturaMapa(materias) {
    return Math.max(150, Math.min(200, 140 + materias * 4)) + "px";
}

function pontosMapa() {
    if (!gridMaterias) return [];
    return [...gridMaterias.querySelectorAll(".ex-ponto")];
}

/* O quanto a espiral pode abrir: precisa sobrar espaço livre nas
   bordas para o maior ponto, senão ele é cortado pela faixa. O passo
   é medido no desenho de verdade (data-tamanho), não chutado, porque
   o ponto muda com a coluna e com a quantidade de matérias. */
function espalhamento(pontos) {
    const w = gridMaterias.clientWidth;
    const h = gridMaterias.clientHeight;
    if (!w || !h) return { rx: 40, ry: 34 };

    let maior = 22;
    for (const p of pontos) {
        maior = Math.max(maior, Number(p.dataset.tamanho) || 22);
    }
    const folga = maior / 2 + 5;
    return {
        rx: Math.min(40, Math.max(10, (w / 2 - folga) / w * 100)),
        ry: Math.min(34, Math.max(8, (h / 2 - folga) / h * 100)),
    };
}

/* No painel os pontos encolhem em coluna estreita e quando a
   prateleira enche: muita matéria num espaço curto é um borrão, não
   um céu. O piso é generoso de propósito — encolher até o ponto deixar
   de parecer clicável é justamente o defeito que o tamanho maior veio
   resolver, e aí não adianta nada. */
function escalaPonto(quantas) {
    const w = gridMaterias ? gridMaterias.clientWidth : 1200;
    const coluna = Math.min(1, w / 900);
    const densidade = quantas > 18 ? Math.sqrt(18 / quantas) : 1;
    return Math.max(.72, coluna * densidade);
}

function reposicionarMapa() {
    if (!gridMaterias) return;
    const pontos = pontosMapa();
    gridMaterias.classList.toggle("ex-mapa--vazio", pontos.length === 0);
    if (pontos.length === 0) {
        desenharLinhas();
        return;
    }

    gridMaterias.style.setProperty("--altura-mapa", alturaMapa(pontos.length));

    /* Primeiro o tamanho (ele muda o quanto o ponto central pode
       andar para a borda), depois a posição de cada um. */
    const escala = escalaPonto(pontos.length);
    for (const p of pontos) {
        const tamanho = Math.round(tamanhoEstrela(Number(p.dataset.qtd) || 0) * escala);
        p.style.setProperty("--tamanho", tamanho + "px");
        p.dataset.tamanho = tamanho;
    }

    const { rx, ry } = espalhamento(pontos);
    pontos.forEach((p, i) => {
        const pos = posicaoEstrela(i, pontos.length, rx, ry);
        p.style.setProperty("--x", pos.x + "%");
        p.style.setProperty("--y", pos.y + "%");
        p.dataset.x = pos.x;
        p.dataset.y = pos.y;
    });

    desenharLinhas();
}

/* As linhas finas que ligam um ponto ao seguinte. */
function desenharLinhas() {
    const svg = document.getElementById("exMapaLinhas");
    if (!svg) return;
    while (svg.firstChild) svg.removeChild(svg.firstChild);
    const pontos = pontosMapa();
    for (let k = 1; k < pontos.length; k++) {
        const linha = document.createElementNS(NS_SVG, "line");
        linha.setAttribute("x1", pontos[k - 1].dataset.x);
        linha.setAttribute("y1", pontos[k - 1].dataset.y);
        linha.setAttribute("x2", pontos[k].dataset.x);
        linha.setAttribute("y2", pontos[k].dataset.y);
        linha.setAttribute("vector-effect", "non-scaling-stroke");
        svg.appendChild(linha);
    }
}

if (gridMaterias) {
    reposicionarMapa();
    let esperaResize;
    window.addEventListener("resize", () => {
        clearTimeout(esperaResize);
        esperaResize = setTimeout(reposicionarMapa, 150);
    });
}

/* Só mexe na contagem de chips que já têm o contador: os chips desta
   página não trazem número, e criar o span aqui grudava "Biologia1"
   no rótulo (sem CSS para .rs-aba__n nesta tela). */
function atualizarContadorFiltro(materia, delta) {
    if (!materia) return;
    const chip = filtros?.querySelector(`[data-materia="${CSS.escape(materia)}"]`);
    const span = chip?.querySelector(".rs-aba__n");
    if (span) {
        const atual = parseInt(span.textContent || "0", 10);
        const novo = Math.max(0, atual + delta);
        span.textContent = novo;
        span.hidden = novo === 0;
    }
}
