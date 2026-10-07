---
name: versao-lite-foco
description: Use quando{for pedido reduzir brilho, glow, box-shadow, drop-shadow, blur, backdrop-filter, animação pulsante ou excesso visual no KOSMOS — a aba Início da dashboard, a sidebar, a home/inicio, login, cadastro ou qualquer tela de estudo. Define o estilo "Lite / Foco" como a versão enxuta do site: mesma identidade, sem ruído. Aciona com "lite", "foco", "tirar o brilho", "deixa mais clean", "muito neon", "estilo Lite".
---

# Estilo Lite / Foco — KOSMOS

Este é o estilo de referência do site na versão **Lite / Foco**.

> **Lite** = menos brilho, menos ornamento, menos movimento.
> **Foco** = o que está em uso é o que acende; o resto se apaga.

**O estilo é, essencialmente, um único ato: remover brilhos excessivos e o resto
do ornamento da mesma família.** Não é um tema novo, não é um skinset, não
troca a paleta. É a regra de como a identidade cósmica se comporta quando a
pessoa passa **horas** lendo, escrevendo e respondendo prova dentro da tela.

A landing page é o oposto: a visita dura um minuto, o céu é o assunto, e cada
elemento pode brilhar à vontade. O Lite / Foco é a **mesma marca com menos
ruído** — porque lá o conteúdo é o protagonista.

---

## 1. Princípios

| # | Princípio | Na prática |
|---|---|---|
| 1 | **O brilho é sinal** | Se algo brilha, é porque está em uso. Brilho em elemento parado é ruído. |
| 2 | **A cor é estado, não decoração** | O roxo (`--accent`) marca ativo e interativo. Nunca "para ficar bonito". |
| 3 | **Brilho não usa cor de marca** | Halo em branco, nunca no roxo do `--accent`. Cor de marca é estado; brilho é presença. |
| 4 | **Superfície neutra** | Branco translúcido, sombra neutra. O fundo é o céu; o card não compete. |
| 5 | **O estado vazio é honesto** | "—" e "0 de 3" são respostas válidas. Número inventado para preencher layout é mentira. |

---

## 2. O que remover (o trabalho principal)

Sempre que for mexer numa tela, revise estes cinco itens:

1. **Glow difuso** — a segunda e a terceira camadas de `box-shadow` com raio
   grande e cor de marca (`0 0 24px rgba(165,65,255,.35)`,
   `0 0 60px rgba(165,65,255,.25)`, `0 0 40px rgba(165,65,255,.10)`).
   Isso é o "adesivo neon" do estilo antigo. **Some com ele.**
2. **Halo em roxo** — `filter: drop-shadow(...)` com `rgba(165,65,255,…)`.
   No lugar, halo **branco** e fraco, só no item em uso.
3. **Sombra colorida no hover** — `box-shadow: 0 8px 24px rgba(165,65,255,.35)`.
   Hover levanta o card com sombra neutra e profunda, não com cor.
4. **Animação que pulsa/brilha sem estado** — `animation` de brilho, `glow`,
   `pulse`, breathing. Movimento só comunica mudança de estado.
5. **Desfoque de fundo em excesso** — `backdrop-filter` com blur grande
   (`blur(20px) saturate(140%)`) e sobreposição da superfície com o céu
   aparecendo forte demais. Menos céu, mais legibilidade.

### Substituições canônicas

Use os tokens de `css/dashboard.css` (bloco `:root`) em vez de sombra escrita
à mão:

| Token | Valor | Uso |
|---|---|---|
| `--shadow-neutral` | `0 4px 12px rgba(0,0,0,.4)` | apoio, chip, item em repouso |
| `--shadow-card-hover` | `0 20px 50px rgba(0,0,0,.45)` | hover / elevação |
| `--desfoque-painel` | `blur(8px)` | vidro das superfícies grandes e poucas |
| `--superficie` | gradiente translúcido | superfície de card |
| `--cosmos-opacidade` | `.85` | quanto o céu aparece (1 = mais) |
| `--veu-forca` | `.36` | quanto a folha escurece |
| `--ruido-forca` | `.04` | grão — aqui ébem menos que na landing |

`--shadow-glow` (`0 0 60px rgba(165,65,255,.25)`) é token **do estilo antigo**:
não introduzir novos usos dele numa tela Lite / Foco.

> **Sobre o `backdrop-filter`:** ele obriga o navegador a reler o que já foi
> pintado, por elemento, a cada quadro. Nas superfícies grandes e poucas por
> tela (painel, abas, carta do flashcard) o custo é baixo e o vidro compensa.
> Nos cartões que se repetem — caderno, resumo, deck — há dezenas na tela, e
> ali vira travamento na rolagem. **Nas listas, use translucidez sem blur.**

---

## 3. Onde o estilo já está aplicado

Fonte única — não existe cópia em outro lugar:

| Arquivo | Papel |
|---|---|
| `Frontend/pages/dashboard/index.php` | Aba Início: ordem de leitura, blocos, estados |
| `Frontend/pages/dashboard/partes/sidebar.php` | Navegação (desktop + sheet mobile) |
| `Frontend/pages/dashboard/css/dashboard.css` | Tokens (`:root`) e regras de `.botoesL` |
| `Frontend/pages/dashboard/partes/fundo.php` | `data-calmo` no canvas: reduz o **custo** do céu |
| `Frontend/pages/shared/cosmos.css` | As regras do céu (`--cosmos-opacidade`, `--veu-forca`, `--ruido-forca`) |

A sidebar é incluída com `include` em todas as páginas da dashboard, então
mexer nela muda o site inteiro de uma vez.

> ⚠️ `dashboard.css` e `dashboard.js` são **compartilhados** por todas as abas.
> Reverter ou reescrever qualquer regra global de lá afeta busca, resumos,
> flashcards, provas, conta… Para o Lite / Foco, prefira mexer no PHP da home,
> na sidebar e nas regras de `.botoesL`.

---

## 4. O estado ativo do ícone (o "brilho")

Este é o contrato central do estilo. O ícone ativo **troca de forma**: o traço
some e entra a versão **preenchida** do mesmo desenho, com um halo.

Cada item carrega **dois** SVG, lado a lado:

```html
<svg class="nav-icon nav-icon--outline" …>  <!-- traço, estado inativo -->
<svg class="nav-icon nav-icon--filled"  …>  <!-- preenchido, estado ativo -->
```

A troca é CSS — nunca JS:

```css
.nav-icon--outline { display: block; }
.nav-icon--filled  { display: none; }

.botoesL a.active .nav-icon--outline { display: none; }
.botoesL a.active .nav-icon--filled  { display: block; }
.botoesL a.active .nav-icon {
    opacity: 1;
    filter: drop-shadow(0 0 6px rgba(255,255,255,.3));   /* branco, não roxo */
}
```

**No mobile é diferente, de propósito.** O item ativo da barra inferior vira um
chip preenchido e o ícone recebe um **disco branco** com o desenho em roxo:

```css
.botoesL a.active           { background: var(--accent); }
.botoesL a.active .nav-icon { background: #fff; color: var(--purple); filter: none; }
```

`filter: none` é obrigatório ali: o halo do desktop borraria o disco branco. No
mobile quem carrega o peso visual é o chip, não o ícone.

---

## 5. Contrato técnico (o que não quebrar)

1. **Item de navegação novo = dois SVG.** Sem o `--filled`, a aba selecionada
   fica sem ícone. Copie o par inteiro, não só a linha do `--outline`.
2. **`--filled` usa `fill="currentColor"`.** É o que deixa o mesmo SVG ser roxo
   no mobile (dentro do disco branco) e branco no desktop. Não fixe `fill` no
   atributo.
3. **O par precisa ser irmão direto.** A troca é por seletor de classe no
   mesmo `<a>`.
4. **A ordem Início → Orion → Buscar → Estudar → Foco é intencional.** No
   mobile ela é reescrita por `order`; mexer nos `href` quebra a barra de baixo
   sem aviso.
5. **Ícone que muda também existe no *sheet*.** Os `.sheet-card` usam o mesmo
   par `--outline` / `--filled`.
6. **Toda linha nova de ícone precisa de `viewBox="0 0 24 24"`**, desenhada em
   24×24. O `.nav-icon` é 20×20 no desktop e 22×22 no mobile.

---

## 6. Ordem de leitura da home (contrato estrutural)

```
1. Cabeçalho + selo de sequência      → onde estou
2. Revisar hoje                       → só aparece se HÁ o que revisar (hidden)
3. Esta semana                        → o painel principal
     ├ gráfico dos últimos 7 dias
     ├ meta do dia
     └ resumos · flashcards · exercícios  (no rodapé do painel)
4. Atalhos                            → 5 cards, uma linha só
5. Primeiros passos  OU  Missões da semana   → mutuamente exclusivos
```

- O passo 2 é **condicional por JS**, não aviso permanente de "0 cartões" —
  ruído fixo treina a pessoa a ignorar o aviso de verdade.
- O passo 5 não acumula: assim que os três primeiros passos fecham, a lista é
  substituída pelas missões da semana (`$MISSOES` no PHP). Uma tela, uma
  instrução.
- O `layout` do passo 5 é decidido no servidor, não no CSS — evita a tela piscar
  entre os dois estados.

---

## 7. Checklist antes de dar qualquer coisa por pronta

- [ ] O brilho/animação que sobrou está **em uso**? Se está parado, tem que sair.
- [ ] Alguma sombra nova foi escrita à mão em vez de usar `--shadow-neutral` /
      `--shadow-card-hover`?
- [ ] Algum halo ficou **roxo** em vez de branco?
- [ ] Alguma lista grande ganhou `backdrop-filter`? Não deve.
- [ ] A cor nova está **marcando estado**, ou só decorando?
- [ ] A tela ainda se lê **de cima para baixo** numa olhada só?
- [ ] O estado vazio está **honesto** (esconde ou mostra "—")?
- [ ] O item de navegação novo tem **os dois SVG**?
- [ ] A mudança ficou dentro de `index.php` / `sidebar.php` / `.botoesL`, sem
      mexer nas regras globais de `dashboard.css`?

---

## 8. Fora do escopo

Cada aba tem sua própria folha e pode manter o que precisar
(`resumos.css`, `flashcards.css`, `provas.css`, `pomodoro.css`, `conta.css`,
`orion-aba.css`, …). O Lite / Foco governa **a home e a navegação** — a moldura
pela qual a pessoa entra em tudo o mais. Alterações de conteúdo dentro de uma
aba específica não precisam seguir estas regras.

A landing page (`pages/inicio/`), login e cadastro são **vitrine**: lá o céu é o
assunto e o brilho é permitido. Não apliques a regra de remoção de brilhos
nelas — o corte de intensidade é o que distingue a Lite / Foco da vitrine.

---

## 9. Referência rápida

| O quê | Onde |
|---|---|
| Tokens (cores, raios, sombras, superfície) | `css/dashboard.css`, bloco `:root` |
| Regra do ícone ativo (desktop) | `css/dashboard.css`, `.botoesL a.active .nav-icon` |
| Regra do chip ativo (mobile) | `css/dashboard.css`, media query do mobile |
| Troca outline → filled | `css/dashboard.css`, `.nav-icon--outline` / `--filled` |
| Estrutura da home | `index.php` |
| Pares de ícone da navegação | `partes/sidebar.php` |
| Ordem da barra inferior no mobile | `css/dashboard.css`, regras `order` |
| Céu: opacidade, véu, grão | `../shared/cosmos.css` |
| Custo do shader | `partes/fundo.php`, `data-calmo` |
