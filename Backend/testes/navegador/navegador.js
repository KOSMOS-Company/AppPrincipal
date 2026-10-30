/* ============================================================
   KOSMOS — Teste de navegador (PC e celular)
   Arquivo: Backend/testes/navegador/navegador.js

   O rodar.php e o banco.php testam a lógica; este abre o site num
   navegador de verdade e confere o que só aparece na tela:
     - nenhuma página dá erro de JavaScript nem resposta 500;
     - nenhuma página fica mais larga que a tela (PC 1366 px, celular 375 px);
     - texto com HTML (<b class=xssprobe>) nunca vira HTML de verdade;
     - os fluxos de conta: cadastro, confirmação de e-mail (quando ligada),
       "Lembrar de mim", logout, Pomodoro sem Iniciar, dicas do Orion e 404.
   Cria uma conta de teste (teste.navegador.<hora>@exemplo.com) e a apaga
   no fim, pelo próprio "Excluir conta".

   Uso (com o site no ar e o banco com as migrações em dia):
       cd Backend/testes/navegador
       npm install                 (uma vez; baixa o Playwright)
       npx playwright install chromium   (uma vez; ou use o Edge, abaixo)
       node navegador.js
   Variáveis (todas opcionais):
       KOSMOS_URL        raiz do app        (padrão http://localhost/AppPrincipal)
       KOSMOS_PHP        php da linha de comando (padrão "php"; no XAMPP:
                         C:\xampp\php\php.exe)
       KOSMOS_NAVEGADOR  "msedge" ou "chrome" para usar o navegador instalado
   Sai com código 1 se algum teste falhar. Roda no CI (.github/workflows/ci.yml).
   ============================================================ */

const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const path = require('path');

const RAIZ   = (process.env.KOSMOS_URL || 'http://localhost/AppPrincipal').replace(/\/$/, '');
const PAGES  = RAIZ + '/Frontend/pages/';
const PHP    = process.env.KOSMOS_PHP || 'php';
const CANAL  = process.env.KOSMOS_NAVEGADOR || undefined;
const EMAIL  = `teste.navegador.${Date.now()}@exemplo.com`;
const SENHA  = 'SenhaDeTeste#2026';

let total = 0, falhas = 0;
function confere(nome, ok, detalhe) {
    total++;
    if (ok) { console.log('  ok    ' + nome); return; }
    falhas++;
    console.log('  FALHOU ' + nome + (detalhe ? '\n         ' + detalhe : ''));
}

function semear(acao) {
    const saida = execFileSync(PHP, [path.join(__dirname, 'semear.php'), acao, EMAIL], { encoding: 'utf8' });
    return JSON.parse(saida);
}

/** POST pelo próprio navegador (mesma origem, com os cookies da página). */
function api(page, arquivo, campos, comoJson) {
    return page.evaluate(async ([url, c, j]) => {
        const opcoes = { method: 'POST', credentials: 'same-origin' };
        if (j) {
            opcoes.headers = { 'Content-Type': 'application/json' };
            opcoes.body = JSON.stringify(c);
        } else {
            opcoes.body = new FormData();
            for (const k in c) opcoes.body.append(k, c[k]);
        }
        const r = await fetch(url, opcoes);
        let json = null;
        try { json = await r.json(); } catch (e) { /* não era JSON */ }
        return { status: r.status, json };
    }, [RAIZ + '/Backend/php/' + arquivo, campos || {}, !!comoJson]);
}

/* ------------------------------------------------------------
   Uma página: abre e confere erros, largura e HTML injetado
   ------------------------------------------------------------ */
async function conferirPagina(page, rotulo, url, statusEsperado = 200) {
    const erros = [];
    const aoErro = (e) => erros.push('JS: ' + e.message);
    const aoConsole = (m) => {
        // recurso 404/401 esperado já aparece no status; o resto é erro de verdade
        if (m.type() === 'error' && !/Failed to load resource/.test(m.text())) erros.push('console: ' + m.text());
    };
    const aoResposta = (r) => { if (r.status() >= 500) erros.push(r.status() + ' ' + r.url()); };
    page.on('pageerror', aoErro);
    page.on('console', aoConsole);
    page.on('response', aoResposta);

    // "load" e não "networkidle": o céu animado e os avisos do Pomodoro
    // mantêm a rede ocupada, e numa máquina lenta a espera estourava à toa
    const resp = await page.goto(url, { waitUntil: 'load', timeout: 60000 }).catch((e) => { erros.push(e.message.split('\n')[0]); return null; });
    await page.waitForTimeout(1500);   // o JS da página desenha as listas
    const info = await page.evaluate(() => ({
        largura: document.documentElement.scrollWidth,
        tela: document.documentElement.clientWidth,
        injetado: document.querySelectorAll('.xssprobe').length,
    })).catch(() => ({ largura: 0, tela: 0, injetado: 0 }));

    page.off('pageerror', aoErro);
    page.off('console', aoConsole);
    page.off('response', aoResposta);

    const status = resp ? resp.status() : 0;
    confere(`${rotulo}: abre (HTTP ${statusEsperado})`, status === statusEsperado, 'veio ' + status);
    confere(`${rotulo}: sem erro de JavaScript/servidor`, erros.length === 0, erros.slice(0, 3).join(' | '));
    confere(`${rotulo}: cabe na tela`, info.largura <= info.tela, `${info.largura}px numa tela de ${info.tela}px`);
    confere(`${rotulo}: não vira HTML o que o usuário escreveu`, info.injetado === 0, info.injetado + ' elementos injetados');
}

(async () => {
    const navegador = await chromium.launch({ channel: CANAL, headless: true });
    const PC      = { viewport: { width: 1366, height: 768 } };
    const CELULAR = { viewport: { width: 375, height: 740 }, isMobile: true, hasTouch: true };

    // ---------------------------------------------------------- páginas públicas
    for (const [nome, opcoes] of [['PC', PC], ['celular', CELULAR]]) {
        console.log(`Páginas públicas (${nome})`);
        const ctx = await navegador.newContext(opcoes);
        const page = await ctx.newPage();
        for (const p of ['inicio/index.html', 'login/index.html', 'cadastro/index.html', 'cadastro/termos.html',
                         'cadastro/politica.html', 'login/redefinir.html', 'login/confirmar.html']) {
            await conferirPagina(page, p, PAGES + p);
        }
        await conferirPagina(page, 'página que não existe', PAGES + 'nao-existe-' + Date.now() + '.html', 404);
        await ctx.close();
    }

    // ---------------------------------------------------------- conta de teste
    console.log('Conta: cadastro e confirmação');
    const ctx = await navegador.newContext(PC);
    const page = await ctx.newPage();
    await page.goto(PAGES + 'login/index.html');

    const cad = await api(page, 'cadastro.php', { nome: 'Teste Navegador', email: EMAIL, senha: SENHA });
    confere('cadastro cria a conta', cad.status === 201, JSON.stringify(cad));
    const ids = semear('semear');

    if (ids.confirmacao) {
        confere('cadastro avisa que mandou o link', cad.json && cad.json.confirmar === true);
        const antes = await api(page, 'login.php', { email: EMAIL, senha: SENHA });
        confere('login antes de confirmar é barrado', antes.status === 403 && antes.json.nao_confirmado === true, JSON.stringify(antes));
        const invalido = await api(page, 'confirmar_email.php', { token: 'f'.repeat(64) });
        confere('link de confirmação inventado é recusado', invalido.status === 400);
        const { token } = semear('token');
        const conf = await api(page, 'confirmar_email.php', { token });
        confere('link de confirmação confirma e entra', conf.status === 200 && conf.json.ok, JSON.stringify(conf));
        const deNovo = await api(page, 'confirmar_email.php', { token });
        confere('o mesmo link não vale duas vezes', deNovo.status === 400);
    } else {
        console.log('  (confirmação de e-mail desligada neste config.php: N8N_CONFIRMA_URL vazio — pulado)');
    }

    // ---------------------------------------------------------- login e "Lembrar de mim"
    console.log('Login e "Lembrar de mim"');
    const errado = await api(page, 'login.php', { email: EMAIL, senha: 'senha-errada-123' });
    confere('senha errada dá 401', errado.status === 401);
    const login = await api(page, 'login.php', { email: EMAIL, senha: SENHA, lembrar: '1' });
    confere('login com "Lembrar de mim"', login.status === 200, JSON.stringify(login));
    const cookies = await ctx.cookies();
    confere('o cookie do "Lembrar de mim" foi criado', cookies.some((c) => c.name === 'kosmos_lembrar' && c.httpOnly));

    await ctx.clearCookies({ name: 'PHPSESSID' });   // fechou o navegador: a sessão foi embora
    await page.goto(RAIZ + '/');                      // abre o site pelo endereço de sempre
    await page.waitForURL(/\/dashboard\/index\.php/, { timeout: 20000 }).catch(() => {});
    confere('reabrir o site com "Lembrar de mim" vai direto ao dashboard', page.url().includes('/dashboard/index.php'), page.url());
    await page.goto(PAGES + 'login/index.html');
    await page.waitForURL(/\/dashboard\/index\.php/, { timeout: 20000 }).catch(() => {});
    confere('logado, o "Entrar" também leva ao dashboard', page.url().includes('/dashboard/index.php'), page.url());

    await api(page, 'logout.php');
    confere('sair apaga o "Lembrar de mim"', !(await ctx.cookies()).some((c) => c.name === 'kosmos_lembrar'));
    await page.goto(PAGES + 'dashboard/index.php');
    confere('depois de sair, o dashboard manda para o login', page.url().includes('/login/'), page.url());

    // ---------------------------------------------------------- dashboard, PC e celular
    const paginas = [
        'index.php', 'resumos.php', `caderno.php?id=${ids.caderno}`, `resumo.php?id=${ids.resumo}`,
        `resumo.php?id=${ids.resumo_longo}`, 'flashcards.php', `flashcards.php?deck=${ids.deck}`, 'revisar.php',
        'exercicios.php', `exercicio_materia.php?id=${ids.materia}`, 'provas.php', 'pomodoro.php',
        'conquistas.php', 'conta.php', 'busca.php?q=xssprobe', 'orion.php',
    ];
    for (const [nome, opcoes] of [['PC', PC], ['celular', CELULAR]]) {
        console.log(`Dashboard (${nome})`);
        const c = await navegador.newContext(opcoes);
        const p = await c.newPage();
        await p.goto(PAGES + 'login/index.html');
        await api(p, 'login.php', { email: EMAIL, senha: SENHA });
        for (const pg of paginas) await conferirPagina(p, pg, PAGES + 'dashboard/' + pg);
        await conferirPagina(p, 'resumo de outra conta', PAGES + 'dashboard/resumo.php?id=999999999', 404);
        await c.close();
    }

    // ---------------------------------------------------------- Pomodoro e dicas
    console.log('Pomodoro e dicas do Orion');
    await api(page, 'login.php', { email: EMAIL, senha: SENHA });
    const aparelho = 'a'.repeat(32);
    const fim = Math.floor(Date.now() / 1000);
    const semIniciar = await api(page, 'pomodoro_sessao.php', { fim, minutos: 25, aparelho }, true);
    confere('ciclo sem Iniciar não é gravado', semIniciar.status === 409, JSON.stringify(semIniciar));
    const iniciar = await api(page, 'pomodoro_inicio.php', { aparelho });
    confere('Iniciar é aceito', iniciar.status === 200);
    const cedo = await api(page, 'pomodoro_sessao.php', { fim, minutos: 25, aparelho }, true);
    confere('ciclo de 25 min logo depois do Iniciar não é gravado', cedo.status === 409);
    const outro = await api(page, 'pomodoro_sessao.php', { fim, minutos: 1, aparelho: 'b'.repeat(32) }, true);
    confere('o Iniciar de um aparelho não vale para outro', outro.status === 409);

    const dica = await api(page, 'tutorial_dica.php', { dicas: 'provas' });
    confere('dica vista é guardada na conta', dica.status === 200);
    await page.goto(PAGES + 'dashboard/provas.php');
    const vistas = await page.evaluate(() => JSON.parse(document.getElementById('dadosConta').textContent).dicasVistas);
    confere('a página recebe as dicas já vistas', Array.isArray(vistas) && vistas.includes('provas'), JSON.stringify(vistas));

    // ---------------------------------------------------------- limpeza
    const fora = await api(page, 'conta_excluir.php', { senha: SENHA });
    confere('a conta de teste é apagada', fora.status === 200, JSON.stringify(fora));

    await navegador.close();
    console.log(`\n${total - falhas} de ${total} testes passaram.`);
    process.exit(falhas > 0 ? 1 : 0);
})().catch((e) => {
    console.error(e);
    process.exit(1);
});
