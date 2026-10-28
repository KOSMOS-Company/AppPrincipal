---
name: backend-foco-seguranca
description: Use para aplicar (ou revisar) o pacote de mudanças de BACKEND do KOSMOS de 2026-10-28 — ciclos de foco do Pomodoro sobrepostos/forjados deixam de ser gravados (pomodoro_util.php), ciclo repetido não muda mais os minutos, Content-Security-Policy com script-src 'self' (bootstrap.php e .htaccess), quantidade de ciclos por dia no foco_mensal.php, e testes com banco (Backend/testes/banco.php) no CI. Aciona com "aplicar as mudanças do back", "pomodoro sobreposto", "CSP script-src", "testes com banco", "sessoes no foco_mensal", "skill do backend".
---

# Backend: foco, CSP e testes com banco — KOSMOS

Pacote de mudanças de backend feito em 2026-10-28 na branch `Frontend` e
**desfeito lá** para ser aplicado do jeito certo (na branch `Backend`, ou
onde o time decidir). Esta skill tem tudo para refazer: o motivo, a ordem,
o código completo dos arquivos novos, o antes/depois dos alterados e como
conferir.

São quatro mudanças independentes. Dá para aplicar só algumas, com uma
exceção: **a CSP (seção 3) só pode entrar depois do front sem scripts
inline** — senão o site inteiro para de funcionar.

| # | Mudança | Arquivos | Depende de |
|---|---|---|---|
| 1 | Ciclo de foco sobreposto não é gravado; repetido não muda minutos | `Backend/php/pomodoro_util.php` (novo), `Backend/php/pomodoro_sessao.php` | — |
| 2 | Ciclos por dia na frequência mensal | `Backend/php/foco_mensal.php` | — (o front já lê `sessoes` se vier) |
| 3 | CSP `script-src 'self'` | `Backend/php/bootstrap.php`, `.htaccess` | **front sem `<script>` inline nem `onclick=`** |
| 4 | Testes com banco + CI | `Backend/testes/banco.php` (novo), `.github/workflows/ci.yml`, `README.md`, `docs/CONFIGURACAO-LOCAL.md` | 1 (testa `pomodoroGravar`) |

---

## 1. Pomodoro: ciclos sobrepostos e repetidos

### O erro
Em `pomodoro_sessao.php`, a regra "ciclos não podem se sobrepor no tempo"
só cortava o **XP direto**. O ciclo sobreposto era gravado mesmo assim e
contava para:
- conquistas `pomodoros` / `pomodoros_dia` (`ProgressoService.php`, que dão `xp_bonus`);
- missões da semana (`missoes.php`), frequência mensal (`foco_mensal.php`),
  gráfico da Início (`inicio_dados.php`) e o contexto do Orion (`orion_chat.php`).

E o `ON DUPLICATE KEY UPDATE minutos = VALUES(minutos)` deixava reenviar o
mesmo `fim` com `minutos: 180` e transformar um ciclo de 15 min em 3 h.
Resultado: com requisições forjadas, dava para liberar conquistas e
completar missões sem estudar.

### A correção
- A conferência de sobreposição sai do bloco do XP e vem **antes** do
  INSERT: sobreposto → **409**, nada é gravado.
- Duplicata vira `ON DUPLICATE KEY UPDATE id = id` (nada muda; `rowCount()` 0).
- Conferir + gravar numa transação com `SELECT ... FOR UPDATE` na linha do
  usuário: duas requisições paralelas não passam juntas.
- A regra mora numa função (`pomodoroGravar`) para poder ser testada sem HTTP.

O front (`pomodoro-aviso.js`) ignora a resposta desse endpoint, então o 409
não aparece para ninguém. Efeito colateral aceito: Pomodoro rodando em **dois
aparelhos ao mesmo tempo** → só o primeiro ciclo conta.

### Arquivo novo: `Backend/php/pomodoro_util.php`

```php
<?php
// ============================================================
//  KOSMOS — Gravar um ciclo de foco (regra num lugar só)
//  Arquivo: Backend/php/pomodoro_util.php
//
//  Usado pelo pomodoro_sessao.php (o endpoint) e pelos testes com
//  banco (Backend/testes/banco.php). Fica fora do endpoint para dar
//  para testar sem HTTP nem sessão.
// ============================================================

/**
 * Grava o ciclo que terminou no instante $fim (epoch, segundos).
 *
 * Devolve 'sobrepoe' => true (e não grava nada) quando ele se cruza
 * no tempo com outro ciclo já gravado: duas sessões "terminando" em
 * horários que se cruzam não são duas sessões de estudo, são uma
 * forjada. Antes isso só tirava o XP, mas a sessão entrava e contava
 * para conquistas, missões e a frequência do mês.
 *
 * 'novo' => false quando o ciclo já existia (mesmo fim): duas abas
 * descobrem o mesmo fim, e um recarregamento redescobre. Os minutos
 * já gravados NÃO mudam — reenviar o mesmo fim com "minutos: 180"
 * transformava um ciclo de 15 min em 3 horas.
 *
 * @return array{novo: bool, sobrepoe: bool}
 */
function pomodoroGravar(PDO $pdo, int $usuarioId, int $minutos, int $fim): array {
    /* Conferir a sobreposição e gravar são um passo só: a linha do
       usuário fica travada até o commit, então duas requisições
       disparadas juntas não passam as duas pela conferência. */
    $pdo->beginTransaction();
    try {
        $pdo->prepare('SELECT id FROM usuarios WHERE id = ? FOR UPDATE')->execute([$usuarioId]);

        // Outro ciclo (fim diferente) que se cruza com este? A folga de
        // 1 minuto em cada ponta absorve relógio impreciso.
        $cruza = $pdo->prepare(
            'SELECT COUNT(*) FROM pomodoro_sessoes
              WHERE usuario_id = ?
                AND fim_em <> FROM_UNIXTIME(?)
                AND fim_em > FROM_UNIXTIME(?) - INTERVAL ? MINUTE + INTERVAL 1 MINUTE
                AND fim_em - INTERVAL minutos MINUTE < FROM_UNIXTIME(?) - INTERVAL 1 MINUTE'
        );
        $cruza->execute([$usuarioId, $fim, $fim, $minutos, $fim]);
        if ((int) $cruza->fetchColumn() > 0) {
            $pdo->rollBack();
            return ['novo' => false, 'sobrepoe' => true];
        }

        /* FROM_UNIXTIME converte no relógio do MYSQL, e o `dia` sai da
           mesma conversão. É a regra do projeto: data se calcula num
           relógio só, e o relógio é o do banco (ver "Regra de datas" no
           README). Na duplicata, `id = id` não muda nada: rowCount() 0. */
        $stmt = $pdo->prepare(
            'INSERT INTO pomodoro_sessoes (usuario_id, minutos, fim_em, dia)
                  VALUES (?, ?, FROM_UNIXTIME(?), DATE(FROM_UNIXTIME(?)))
             ON DUPLICATE KEY UPDATE id = id'
        );
        $stmt->execute([$usuarioId, $minutos, $fim, $fim]);
        $novo = $stmt->rowCount() === 1;
        $pdo->commit();

        return ['novo' => $novo, 'sobrepoe' => false];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
```

> `rowCount()` só é confiável porque a conexão **não** usa
> `PDO::MYSQL_ATTR_FOUND_ROWS` (confira em `bootstrap.php`/`conexao.php`).
> Se um dia ligarem, a duplicata passa a devolver 1 e o XP sai em dobro.

### `Backend/php/pomodoro_sessao.php` (4 trocas)

**a) Fim do comentário do cabeçalho** — troque a linha
`//  KEY UPDATE deixa a segunda tentativa passar em silêncio.` por:

```php
//  KEY UPDATE deixa a segunda tentativa passar em silêncio — e sem
//  mexer nos minutos já gravados (reenviar o mesmo fim com
//  "minutos: 180" transformava um ciclo de 15 min em 3 horas).
//
//  Ciclos não podem se sobrepor no tempo: duas sessões "terminando"
//  em horários que se cruzam não são duas sessões de estudo, são uma
//  forjada. Antes isso só tirava o XP, mas a sessão era gravada e
//  contava para conquistas, missões e a frequência do mês. Agora ela
//  nem entra.
```

**b) Requires e constante** — depois de `require_once __DIR__ . '/ProgressoService.php';`
acrescente `require_once __DIR__ . '/pomodoro_util.php';` e encurte o comentário
da constante:

```php
/* XP: um ciclo curto demais não é um Pomodoro (um timer de 1 minuto
   viraria fábrica de XP). */
const POMO_MIN_MINUTOS_XP = 15;
```

**c) A gravação** — ANTES (logo depois da checagem "anterior à criação da conta"):

```php
    /* FROM_UNIXTIME converte no relógio do MYSQL, ... */
    $stmt = $pdo->prepare(
        'INSERT INTO pomodoro_sessoes (usuario_id, minutos, fim_em, dia)
              VALUES (?, ?, FROM_UNIXTIME(?), DATE(FROM_UNIXTIME(?)))
         ON DUPLICATE KEY UPDATE minutos = VALUES(minutos)'
    );
    $stmt->execute([$usuario['id'], $minutos, $fim, $fim]);
```

DEPOIS:

```php
    // Sobreposição e duplicata: regras e motivos no pomodoro_util.php
    $gravado = pomodoroGravar($pdo, (int) $usuario['id'], $minutos, $fim);
    if ($gravado['sobrepoe']) {
        estErro('Este ciclo se sobrepõe a outro já registrado.', 409);
    }
    $novo = $gravado['novo'];
```

**d) O XP** — apague a linha `$novo = $stmt->rowCount() === 1;   // 2 = já existia e foi atualizada`
(o `$novo` agora vem do passo c) e troque o bloco do XP, que tinha o
`SELECT COUNT(*) ... $cruza` dentro, por:

```php
    // XP só na PRIMEIRA gravação do ciclo (a segunda aba que descobre o
    // mesmo fim não ganha de novo). A sobreposição já foi barrada acima.
    $progresso = null;
    if ($novo && $minutos >= POMO_MIN_MINUTOS_XP) {
        $progresso = progressoRegistrar($pdo, (int) $usuario['id'], [[
            'acao'     => 'pomodoro_concluido',
            'xp'       => ProgressoService::XP['pomodoro_concluido'],
            'detalhes' => ['minutos' => $minutos, 'fim' => $fim],
        ]]);
    }
```

O `catch (PDOException $e)` do fim fica como estava: o rollback de erro já
acontece dentro de `pomodoroGravar`.

---

## 2. `foco_mensal.php`: ciclos por dia

Cada dia passa a trazer `sessoes` (quantos ciclos de foco fechou). O front
(`Frontend/pages/dashboard/js/inicio.js`, dica do calendário "Mês") mostra
"N ciclos de foco" **só quando o campo vem** — sem ele, a dica mostra só o
tempo. Então este passo pode entrar a qualquer momento.

```diff
-//    dias      -> [{dia:N, minutos:N}] do dia 1 ao último, sem buracos
+//    dias      -> [{dia:N, minutos:N, sessoes:N}] do dia 1 ao último, sem buracos
+//                 (sessoes = quantos ciclos de foco a pessoa fechou no dia)
```
```diff
-    /* ---- Os minutos de cada dia do mês ---- */
+    /* ---- Os minutos e os ciclos de foco de cada dia do mês ---- */
     $stmt = $pdo->prepare(
-        'SELECT DAY(dia) AS dia, SUM(minutos) AS minutos
+        'SELECT DAY(dia) AS dia, SUM(minutos) AS minutos, COUNT(*) AS sessoes
```
```diff
-        $porDia[(int) $linha['dia']] = (int) $linha['minutos'];
+        $porDia[(int) $linha['dia']] = [(int) $linha['minutos'], (int) $linha['sessoes']];
```
```diff
-        $min = $porDia[$d] ?? 0;
-        $dias[] = ['dia' => $d, 'minutos' => $min];
+        [$min, $sessoes] = $porDia[$d] ?? [0, 0];
+        $dias[] = ['dia' => $d, 'minutos' => $min, 'sessoes' => $sessoes];
```

Cada linha de `pomodoro_sessoes` é um ciclo, por isso `COUNT(*)`. Com a
seção 1 aplicada, ciclos forjados/sobrepostos não entram nessa conta.

---

## 3. Content-Security-Policy: `script-src 'self'`

### Por quê
A CSP só tinha `frame-ancestors`, `base-uri`, `object-src` e `form-action`.
Sem `script-src`, se algum texto de usuário escapasse sem tratamento num
`innerHTML`, ele viraria código. Com `script-src 'self'`, só rodam arquivos
`.js` do próprio site (e o login do Google). É a segunda barreira — a
primeira continua sendo o `escaparHtml()` em todo template.

### ⚠️ Pré-requisito: o front sem script inline
`script-src 'self'` **bloqueia** `<script>` com código dentro do HTML e
atributos `onclick=`, `onsubmit=` etc. Antes de aplicar, rode na raiz:

```bash
# blocos <script> que executam código (os type="application/json" podem ficar)
grep -rn -E "<script(\s[^>]*)?>" Frontend Backend --include=*.php --include=*.html \
  | grep -v "src=" | grep -v 'type="application/json"'
# atributos de evento inline (no HTML ou montados em template no JS)
grep -rn -E "\son[a-z]+=\s*[\"']" Frontend --include=*.php --include=*.html --include=*.js
```

Os dois têm que voltar **vazios** (fora comentários). Na branch `Frontend`
isso já foi feito em 2026-10-28: os scripts inline viraram
`shared/visual-lite.js`, `shared/mostrar-senha.js`, `login/js/*.js`,
`cadastro/js/*.js`, `dashboard/js/visual-inicial.js`, `intro-inicial.js`,
`conta-inicial.js`; os dados do PHP para o JS foram para
`<script type="application/json">` (`#dadosConta`, `#dadosProgressoInicial`);
e `onclick`/`onsubmit` viraram `addEventListener`.

### `Backend/php/bootstrap.php`

```diff
-    header("Content-Security-Policy: frame-ancestors 'self'; base-uri 'self'; object-src 'none'; form-action 'self'");
+    // script-src 'self': nenhum script inline roda (mantenha igual ao .htaccess)
+    header("Content-Security-Policy: script-src 'self' https://accounts.google.com/gsi/; frame-ancestors 'self'; base-uri 'self'; object-src 'none'; form-action 'self'");
```

### `.htaccess` (raiz) — tem que ficar IGUAL ao bootstrap

```diff
-    Header set Content-Security-Policy "frame-ancestors 'self'; base-uri 'self'; object-src 'none'; form-action 'self'"
+    # script-src: só scripts dos nossos arquivos (e o login do Google).
+    # Nenhum <script> inline nem onclick="..." roda — se um dia algum
+    # texto do usuário escapar sem tratamento, ele não vira código.
+    # Mantenha igual ao Backend/php/bootstrap.php.
+    Header set Content-Security-Policy "script-src 'self' https://accounts.google.com/gsi/; frame-ancestors 'self'; base-uri 'self'; object-src 'none'; form-action 'self'"
```

`https://accounts.google.com/gsi/` cobre o `gsi/client` do botão "Entrar com
o Google". `style-src`, `connect-src` e `frame-src` ficam de fora de
propósito (o app usa `style=` e `<style>` gerados por JS).

### Nota para o README (seção "Rode os testes")

```markdown
   **Scripts:** a Content-Security-Policy (`.htaccess` e `bootstrap.php`) só aceita scripts de
   arquivos `.js` do próprio site. `<script>` com código dentro do HTML e atributos como
   `onclick="..."` **não rodam** — use um arquivo `.js` e `addEventListener`. Dados do PHP
   para o JS vão num `<script type="application/json">` (ele não é executado).
```

---

## 4. Testes com banco

O `Backend/testes/rodar.php` só cobre lógica pura. O `banco.php` cria um
banco **descartável** `kosmos_teste` a partir do `Backend/kosmos.sql`, roda
19 testes (ciclos de foco, XP do Pomodoro, limite de tentativas) e apaga o
banco no fim. O `kosmos` nunca é tocado.

### Arquivo novo: `Backend/testes/banco.php`

```php
<?php
// ============================================================
//  KOSMOS — Testes que precisam do banco
//  Arquivo: Backend/testes/banco.php
//
//  O rodar.php cobre só a lógica pura. Este cobre o que mora no
//  MySQL: gravação dos ciclos de foco (sobreposição, duplicata),
//  o XP que eles dão e o limite de tentativas de login.
//
//  Cria um banco DESCARTÁVEL (kosmos_teste) a partir do
//  Backend/kosmos.sql, roda os testes e apaga o banco no fim. O
//  banco de verdade (kosmos) nunca é tocado.
//
//  Uso (da raiz do AppPrincipal, com o MySQL do XAMPP ligado):
//      C:\xampp\php\php.exe Backend/testes/banco.php
//  Conexão: localhost / root / sem senha, ou as variáveis
//  TESTE_DB_HOST, TESTE_DB_USER e TESTE_DB_PASS (usadas no CI).
//  Sai com código 1 se algum teste falhar.
// ============================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../php/bootstrap.php';
require_once __DIR__ . '/../php/ProgressoService.php';
require_once __DIR__ . '/../php/pomodoro_util.php';
require_once __DIR__ . '/../php/limite_util.php';

const BANCO_TESTE = 'kosmos_teste';

$falhas = 0;
$total  = 0;

function confere(string $nome, $obtido, $esperado): void {
    global $falhas, $total;
    $total++;
    if ($obtido === $esperado) {
        echo "  ok    $nome\n";
        return;
    }
    $falhas++;
    echo "  FALHA $nome\n"
       . '        esperado: ' . var_export($esperado, true) . "\n"
       . '        obtido:   ' . var_export($obtido, true) . "\n";
}

// ---------------------------------------------------------------
// Monta o banco descartável
// ---------------------------------------------------------------
$host = getenv('TESTE_DB_HOST') ?: 'localhost';
$user = getenv('TESTE_DB_USER') ?: 'root';
$pass = getenv('TESTE_DB_PASS') ?: '';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "Sem conexão com o MySQL ($host): " . $e->getMessage() . "\n");
    exit(1);
}

$sql = file_get_contents(__DIR__ . '/../kosmos.sql');
$sql = str_replace(
    ['CREATE DATABASE IF NOT EXISTS `kosmos`', 'USE `kosmos`'],
    ['CREATE DATABASE `' . BANCO_TESTE . '`',  'USE `' . BANCO_TESTE . '`'],
    $sql
);
if (!str_contains($sql, 'USE `' . BANCO_TESTE . '`')) {
    fwrite(STDERR, "O kosmos.sql mudou de formato: não achei o 'USE `kosmos`' para trocar.\n");
    exit(1);
}

$pdo->exec('DROP DATABASE IF EXISTS `' . BANCO_TESTE . '`');
$importa = $pdo->query($sql);
do { /* percorre cada comando: um erro em qualquer um vira exceção aqui */ } while ($importa->nextRowset());
$importa->closeCursor();
$pdo->exec('USE `' . BANCO_TESTE . '`');
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

register_shutdown_function(function () use ($pdo) {
    $pdo->exec('DROP DATABASE IF EXISTS `' . BANCO_TESTE . '`');
});

$criaUsuario = function (int $id, string $email) use ($pdo): void {
    $pdo->prepare('INSERT INTO usuarios (id, nome, email, criado_em) VALUES (?, ?, ?, NOW() - INTERVAL 30 DAY)')
        ->execute([$id, 'Teste ' . $id, $email]);
};
$um = function (string $sql, array $args = []) use ($pdo) {
    $s = $pdo->prepare($sql);
    $s->execute($args);
    return $s->fetchColumn();
};

// ---------------------------------------------------------------
echo "Ciclos de foco (pomodoro_util.php)\n";

$criaUsuario(1, 'ana@teste.dev');
$fim = time() - 3600;   // um ciclo de 25 min que terminou há uma hora

$r = pomodoroGravar($pdo, 1, 25, $fim);
confere('primeiro ciclo é gravado como novo', $r, ['novo' => true, 'sobrepoe' => false]);

$r = pomodoroGravar($pdo, 1, 25, $fim);
confere('o mesmo fim de novo (outra aba) não é novo', $r, ['novo' => false, 'sobrepoe' => false]);

pomodoroGravar($pdo, 1, 180, $fim);
confere('reenviar o mesmo fim com 180 min não muda os minutos',
    (int) $um('SELECT minutos FROM pomodoro_sessoes WHERE usuario_id = 1'), 25);

$r = pomodoroGravar($pdo, 1, 25, $fim + 10 * 60);
confere('ciclo que se cruza com outro é recusado', $r, ['novo' => false, 'sobrepoe' => true]);
confere('o ciclo recusado não entra na tabela',
    (int) $um('SELECT COUNT(*) FROM pomodoro_sessoes WHERE usuario_id = 1'), 1);

$r = pomodoroGravar($pdo, 1, 180, $fim - 60);
confere('ciclo longo que engole o anterior também é recusado', $r['sobrepoe'], true);

$r = pomodoroGravar($pdo, 1, 25, $fim + 25 * 60);
confere('ciclo logo em seguida (sem cruzar) é aceito', $r, ['novo' => true, 'sobrepoe' => false]);

$r = pomodoroGravar($pdo, 1, 25, $fim + 25 * 60 + 30 + 25 * 60);
confere('a folga de 1 minuto aceita relógio meio adiantado', $r['novo'], true);

$criaUsuario(2, 'bia@teste.dev');
$r = pomodoroGravar($pdo, 2, 25, $fim + 10 * 60);
confere('o ciclo de outra conta no mesmo horário não conta como sobreposto', $r['novo'], true);

confere('conquistas contam só os ciclos aceitos',
    (int) $um('SELECT COUNT(*) FROM pomodoro_sessoes WHERE usuario_id = 1 AND minutos >= 15'), 3);

// ---------------------------------------------------------------
echo "XP do Pomodoro (ProgressoService.php)\n";

$criaUsuario(3, 'caio@teste.dev');
$xpPomodoro = ProgressoService::XP['pomodoro_concluido'];
$progresso  = progressoRegistrar($pdo, 3, [[
    'acao' => 'pomodoro_concluido', 'xp' => $xpPomodoro, 'detalhes' => ['minutos' => 25],
]]);
confere('registrar um Pomodoro devolve o progresso', is_array($progresso), true);
confere('o XP entra no histórico',
    (int) $um("SELECT COALESCE(SUM(quantidade_xp), 0) FROM historico_xp WHERE usuario_id = 3 AND origem_acao = 'pomodoro_concluido'"),
    $xpPomodoro);
confere('o XP soma no progresso da conta',
    (int) $um('SELECT xp_atual FROM usuario_progresso WHERE usuario_id = 3') >= $xpPomodoro, true);

// ---------------------------------------------------------------
echo "Limite de tentativas (limite_util.php)\n";

$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
$alvo = 'alvo@teste.dev';
confere('sem tentativas, não há limite', limiteExcedido($pdo, 'login', $alvo), false);

for ($i = 0; $i < LIMITES['login']['email']; $i++) {
    registrarTentativa($pdo, 'login', $alvo);
}
confere('5 senhas erradas no mesmo e-mail travam o login', limiteExcedido($pdo, 'login', $alvo), true);
confere('o limite de login não trava a recuperação de senha', limiteExcedido($pdo, 'reset', $alvo), false);

limparTentativas($pdo, 'login', $alvo);
confere('login certo libera aquele e-mail', limiteExcedido($pdo, 'login', $alvo), false);

for ($i = 0; $i < LIMITES['login']['ip']; $i++) {
    registrarTentativa($pdo, 'login', "conta$i@teste.dev");
}
confere('muitas contas tentadas do mesmo IP travam até um e-mail novo',
    limiteExcedido($pdo, 'login', 'novo@teste.dev'), true);

$_SERVER['REMOTE_ADDR'] = '198.51.100.9';
confere('outro IP, com e-mail novo, segue livre', limiteExcedido($pdo, 'login', 'novo@teste.dev'), false);

// ---------------------------------------------------------------
echo "\n" . ($total - $falhas) . " de $total testes passaram.\n";
exit($falhas > 0 ? 1 : 0);
```

Detalhes que quebram fácil:
- A importação troca `CREATE DATABASE IF NOT EXISTS \`kosmos\`` e `USE \`kosmos\``
  por `kosmos_teste`. Se o `kosmos.sql` mudar esse cabeçalho, o teste **para
  com erro** em vez de mexer no banco real — ajuste o `str_replace`.
- `kosmos.sql` usa sintaxe do MariaDB (`ADD COLUMN IF NOT EXISTS`): no CI
  tem que ser MariaDB, não MySQL 8.
- A coluna do XP no histórico é `quantidade_xp` (não `xp_ganho`).

### `.github/workflows/ci.yml`

Cabeçalho:

```yaml
#  Roda a cada push e pull request. Não precisa de n8n:
#   1. sintaxe de todo arquivo PHP (php -l);
#   2. os testes de lógica pura (Backend/testes/rodar.php);
#   3. os testes com banco (Backend/testes/banco.php), num MariaDB
#      descartável — a mesma família do MySQL do XAMPP;
#   4. sintaxe de todo arquivo JavaScript (node --check);
#   5. nenhum segredo commitado por engano (config.php / conexao.php).
```

No job `verificar`, entre `runs-on` e `steps`:

```yaml
    services:
      mariadb:
        image: mariadb:10.6
        env:
          MARIADB_ROOT_PASSWORD: teste
        ports:
          - 3306:3306
        options: >-
          --health-cmd="healthcheck.sh --connect --innodb_initialized"
          --health-interval=5s
          --health-timeout=5s
          --health-retries=20
```

Depois do passo `Testes (Backend/testes/rodar.php)`:

```yaml
      - name: Testes com banco (Backend/testes/banco.php)
        env:
          TESTE_DB_HOST: 127.0.0.1
          TESTE_DB_USER: root
          TESTE_DB_PASS: teste
        run: php Backend/testes/banco.php
```

### Documentação
- `README.md`, árvore de pastas, embaixo de `testes/rodar.php`:
  `│   ├── testes/banco.php        # testes com banco (cria e apaga um kosmos_teste)`
- `README.md`, passo "6. **Rode os testes**": tirar "(não precisam do banco)",
  acrescentar `C:\xampp\php\php.exe Backend/testes/banco.php` e o parágrafo:
  "O primeiro não precisa do banco. O segundo precisa do MySQL ligado: ele cria
  um banco descartável (`kosmos_teste`) a partir do `kosmos.sql`, testa o que
  mora no banco (ciclos de foco, XP, limite de tentativas) e apaga o banco no
  fim — o `kosmos` nunca é tocado."
- `docs/CONFIGURACAO-LOCAL.md`, seção 5: `54 de 54` vira `75 de 75`, mais
  "Com o MySQL ligado, rode também `C:\xampp\php\php.exe Backend/testes/banco.php`
  (cria e apaga um banco `kosmos_teste`)."

---

## 5. Como conferir depois de aplicar

```bash
# sintaxe
for f in $(git ls-files "*.php"); do C:/xampp/php/php.exe -l "$f" | grep -v "No syntax errors"; done
# testes
C:\xampp\php\php.exe Backend/testes/rodar.php     # 75 de 75
C:\xampp\php\php.exe Backend/testes/banco.php     # 19 de 19
# CSP no ar
curl -sI http://localhost/AppPrincipal/Frontend/pages/login/index.html | grep -i content-security
```

No navegador, com a CSP aplicada: abra login, cadastro, Início, Exercícios
(botões Praticar/Excluir), Conquistas e Conta com o DevTools aberto. **Nenhuma**
mensagem "Refused to execute inline script" / "violates the following Content
Security Policy" pode aparecer. Se aparecer, há script inline sobrando: mova
para um `.js` em vez de afrouxar a CSP com `'unsafe-inline'`.

Pomodoro de ponta a ponta (logado, `Origin: http://localhost`):
1. POST `{fim, minutos: 25}` → `novo: true`, ganha XP;
2. o mesmo `fim` com `minutos: 180` → `novo: false`, minutos continuam 25;
3. outro `fim` 1 min antes, 25 min → **409** "Este ciclo se sobrepõe…", nada gravado.

Atenção: um `fim` anterior a `usuarios.criado_em` já dá 409 "anterior à
criação da conta" (regra antiga) — em conta recém-criada use um fim de
segundos atrás.

## 6. Como desfazer

Cada seção é independente, menos a 4, que precisa da 1. Para tirar a CSP,
volte as **duas** linhas (bootstrap e .htaccess) juntas — elas têm que ser
iguais. Tirar a CSP não quebra nada do front.
