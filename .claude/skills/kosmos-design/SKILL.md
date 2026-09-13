---
name: kosmos-design
description: O sistema de design da Kosmos — paleta, tipografia, tokens, componentes, o fundo cósmico, o mascote Orion e as regras de movimento e acessibilidade. Use SEMPRE que for criar ou alterar qualquer interface do projeto: uma página nova, uma seção, um card, um modal, um botão, um estado vazio, um formulário. Também acione ao revisar se uma tela "está na identidade", ao escolher cor, espaçamento, raio ou animação, e ao mexer no fundo de estrelas, no parallax ou no Orion.
---

# Design da Kosmos

A Kosmos é um app de estudos com cara de **espaço**: fundo escuro, roxo da marca,
estrelas de verdade atrás de tudo, e um mascote que acompanha a pessoa. Esta skill
é a fonte da verdade sobre como isso se monta.

**Antes de escrever CSS novo, procure o que já existe.** Quase tudo que uma tela
precisa — botão, campo, modal, tag de matéria, estado vazio, chip de filtro — já
está pronto em `dashboard.css` ou `inicio.css`. Componente novo é a exceção.

---

## 1. Tokens

Declarados em `:root` nos dois arquivos principais (`dashboard/css/dashboard.css`
e `inicio/css/inicio.css`) com **os mesmos valores**. Se mexer num, mexa no outro.

```css
--black:     #000000
--bg-deep:   #0d0118   /* fundo geral */
--bg-nav:    #12011f   /* barras, rodapé */
--bg-card:   #1a0230   /* caixas */
--purple-dk: #2f0a4b
--purple-md: #7203bd
--purple:    #8200db
--accent:    #a541ff   /* a cor da marca: ação, foco, destaque */
--accent-lt: #c97cff   /* hover, texto de destaque */

--text:       #ffffff
--text-dim:   #b8a8cc  /* texto corrido */
--text-muted: #7a6890  /* apoio, legendas, placeholder */

--border:     rgba(165,65,255,.2)   /* só no dashboard.css */

--radius-sm: 12px   /* campos, botões, chips */
--radius-md: 20px   /* modais, caixas médias */
--radius-lg: 32px   /* cards grandes, seções */

--transition:   .25s cubic-bezier(.4,0,.2,1)
--shadow-glow:  0 0 60px rgba(165,65,255,.25)
```

**Nunca escreva um hex solto.** Se a cor não está aqui, provavelmente a escolha
está errada — ou é um caso de gradiente (ver abaixo).

### Gradientes recorrentes

```css
/* superfície de card */
linear-gradient(150deg, #1e0636 0%, #120220 100%)

/* superfície de card FLUTUANDO no cosmos (landing page) */
linear-gradient(150deg, rgba(30,6,54,.80) 0%, rgba(18,2,32,.86) 100%)
+ backdrop-filter: blur(8px)

/* texto com a cor da marca (.h-nome) */
background: linear-gradient(135deg, var(--accent) 0%, var(--accent-lt) 100%);
-webkit-background-clip: text;
-webkit-text-fill-color: transparent;
```

### Paleta de 6 cores (avatar e cadernos)

Definida em `Backend/php/materias.php` como `CORES_AVATAR_KOSMOS` e reusada como
`CORES_CADERNO_KOSMOS`. As classes `.avatar-cor--*` já existem no `dashboard.css`:

`roxo` `azul` `verde` `laranja` `rosa` `ciano`

Para colorir algo por esta paleta, **reaproveite as classes** em vez de escrever
cores novas. Ver `.caderno-card--<cor>` em `resumos.css` como exemplo: cada cor
define só `--cad` e `--cad-esc`, e o resto do componente usa essas variáveis.

> **Armadilha de cascata (já quebrou duas vezes).** O padrão tem que vir **antes**
> dos modificadores. `.caderno-card` e `.caderno-card--verde` têm a mesma
> especificidade; se o padrão vier depois, ele vence e **toda cor escolhida é
> ignorada em silêncio**. Nada quebra — só deixa de acontecer.

---

## 2. Tipografia

Duas famílias, carregadas do Google Fonts:

- **Syne** (400–800) — títulos, números grandes, nomes de card. Sempre que o texto
  é "voz da marca".
- **DM Sans** (300–500 + itálico 300) — texto corrido, botões, campos, legendas.

```css
h1,h2,h3,h4 { font-family: 'Syne', sans-serif; line-height: 1.15; }
body        { font-family: 'DM Sans', sans-serif; line-height: 1.65; }
```

Títulos de seção usam `clamp()` para escalar sem media query:

```css
.section-title { font-size: clamp(1.75rem, 3.5vw, 2.8rem); font-weight: 700; }
```

### O par tag + título

O cabeçalho padrão de seção são dois elementos, sempre nesta ordem:

```html
<span class="section-tag">Biblioteca</span>          <!-- kicker, MAIÚSCULAS -->
<h2 class="section-title">Seus <em>Cadernos</em></h2> <!-- <em> pinta com a marca -->
```

No dashboard o destaque do `<h1>` usa `<span class="h-nome">` em vez de `<em>`.

---

## 3. Layout e responsividade

```css
.container { max-width: 1200px; margin: 0 auto; padding: 0 24px; }
```

**Breakpoints em uso** (siga estes; não invente outros):

| Largura | O que muda |
|---|---|
| `min-width: 1025px` | scrollytelling e seções pinadas só existem daqui para cima |
| `max-width: 900px` | palcos de duas colunas viram uma |
| `max-width: 768px` | **o principal**: menu vira barra inferior, grades viram 1 coluna |
| `max-width: 560px` / `480px` | ajustes finos de campo e cartão |

No dashboard, abaixo de 768px a navegação lateral vira **barra fixa inferior**
(`.botoesL`, `position: fixed; bottom: 0; z-index: 100`). Qualquer coisa flutuante
no canto inferior precisa subir acima dela — ver `.pomo-selo` em
`pomodoro-aviso.css` (`bottom: calc(84px + env(safe-area-inset-bottom))`).

---

## 4. Componentes que já existem

Antes de criar, confira esta lista.

| Classe | Para quê |
|---|---|
| `.dash-btn` + `--primary` `--outline` `--ghost` `--danger` | botões do dashboard |
| `.btn` + `--primary` `--outline` `--ghost` `--lg` | botões da landing page |
| `.campo` | par label + input/select/textarea; `.campo__dica` para a ajuda |
| `.modal` + `.modal__box` `.modal__head` `.modal__form` `.modal__actions` | qualquer diálogo |
| `.chips` + `.chip` (`.active`) | filtros por matéria |
| `.materia-tag` | etiqueta de matéria (MAIÚSCULAS, com pontinho) |
| `.vazio` | estado vazio: ícone + `<h3>` + `<p>` |
| `.msg` + `--sucesso` `--erro` | retorno de formulário |
| `.anim-in` | entrada escalonada (use `animation-delay` inline por índice) |
| `.esqueleto` | placeholder de carregamento |
| `.section-tag` / `.section-title` / `.h-nome` | cabeçalho de seção |
| `.reveal` + `--up` `--left` `--right` `--scale` `--d1..d4` | entrada na rolagem (LP) |

### Estado vazio

Todo grid precisa de um. E o texto muda conforme o motivo — "não existe nada"
é diferente de "nada **neste filtro**":

```js
vazio.querySelector("h3").textContent = nenhum
    ? "Nenhum caderno por aqui"
    : "Nenhum caderno nesta matéria";
```

### Parte reaproveitada (PHP)

HTML que aparece em mais de uma página vira um arquivo em
`Frontend/pages/dashboard/partes/`. Existem: `sidebar`, `modal-confirma`,
`modal-resumo`, `modal-caderno`, `caderno-card`, `resumo-card`.

Toda parte começa recusando acesso direto:

```php
if (!isset($USUARIO, $PREF)) { http_response_code(403); exit('Esta página não é acessada direto.'); }
```

Quando o mesmo cartão é desenhado por PHP (primeira pintura) **e** por JS
(repintura), os dois ficam marcados com um aviso cruzado no comentário. Mexeu num,
mexa no outro.

---

## 5. O fundo cósmico (landing page)

Três camadas empilhadas, nesta ordem:

```
z 0   <canvas id="cosmos">   estrelas, Via Láctea, nebulosa, cadentes
z 0   <div class="veu">      folha translúcida — legibilidade
z 1   as seções
```

- **`js/cosmos-gl.js`** — WebGL cru (sem biblioteca). Um shader de fragmento pinta
  tudo: 4 camadas de estrelas com parallax próprio, a faixa da Via Láctea, névoa
  em ruído fractal, 2 estrelas cadentes e uma luz que segue o ponteiro.
- **`js/cosmos.js`** — a **reserva** em Canvas 2D. Só assume se não houver WebGL ou
  se o shader não compilar. Carregado depois, começa perguntando quem é o dono.
- **`.veu`** — `position: fixed`, `rgba(5,0,12,.52)`, opacidade **constante**.

> **Por que o véu é um só.** Antes cada seção tinha o próprio degradê e metade não
> tinha nenhum — em **toda emenda** aparecia uma faixa horizontal dura. Seção não
> deve ter fundo próprio: quem escurece é o véu.

### O que faz parecer estrela, e não neve

Esta distinção custou duas correções. O que separa os dois **não é a quantidade**:

- **Faixa dinâmica enorme.** Milhares de pontos quase invisíveis, algumas dezenas
  médias, pouquíssimas fortes. Tamanho por `pow(t, 5)`, brilho por `pow(h, 3)`.
- **Núcleo de queda abrupta.** `pow(max(0., 1. - d/raio), 4.)`. Um `smoothstep`
  do raio até zero desenha uma bolinha macia — isso é literalmente um floco.
- **Bloom só nas raras.** É ele que dá vida; se todas tiverem, vira neve.
- **Cores diferentes.** A maioria branco-azulada, algumas quentes, algumas roxas.
- **A Via Láctea.** É a faixa diagonal, com poeira e trilhas escuras, que faz o
  céu parecer *o cosmos* em vez de pontos ao acaso.

### Desempenho

O shader roda por pixel, então: `fbm` em 4 oitavas, `n1` reaproveitado, render
**abaixo** da resolução da tela (1.25× no desktop, 0.6× no celular), metade dos
quadros no toque, e o laço **para** quando a aba perde o foco.

---

## 6. Profundidade (landing page)

`js/profundidade.js` é declarativo: o HTML diz o plano, o JS faz a conta e escreve
variáveis CSS. **Não escreva uma regra de parallax por elemento.**

```html
<div data-plano="fundo">      <!-- anda  -18px -->
<div data-plano="meio">       <!-- anda  -55px -->
<div data-plano="frente">     <!-- anda -110px -->
<div data-parallax="-190">    <!-- à mão -->
<div data-scrub="#algumPin">  <!-- recebe --scrub 0..1 do progresso do pin -->
<div data-relevo>             <!-- inclina + brilho seguindo o ponteiro -->
```

> **Um elemento, um dono do `transform`.** Se uma regra `html.scrolly .classe`
> também definir `transform`, ela vence por especificidade e o parallax morre
> **em silêncio**. Onde os dois efeitos valem, **componha num `transform` só**:
>
> ```css
> transform: perspective(900px)
>            rotateX(calc(var(--inc-x,0) * 5deg))
>            scale(calc(1 + var(--sp,0) * .07));
> ```

### Seções pinadas (scrollytelling)

Só acima de 1025px e só sem `prefers-reduced-motion` — a classe `html.scrolly`
é o portão. O padrão é `__pin` (altura em `vh`) + `__vista` (`position: sticky`).
Alturas em uso: Recursos 300vh, Experimente 320vh, A visita 520vh, Sobre e
História 170vh.

Campo de formulário **nunca** vai dentro de um pin: digitar numa seção que trava
a rolagem é péssimo.

---

## 7. Orion, o mascote

SVG montado por código em `Frontend/pages/shared/mascote.js` (CSS em `mascote.css`).
Zero imagens. Funciona em qualquer página.

```html
<span data-mascote data-mascote-auto="0"></span>
```

```js
el.mascote.humor('normal' | 'feliz' | 'confuso')
el.mascote.piscar(ms)
el.mascote.fecharOlhos(true|false)
el.mascote.olharPara(x, y)
window.KosmosMascote.montar(el)
```

- `--mascote-tam` controla o tamanho.
- `data-mascote-auto="0"` desliga a ligação automática aos campos da página.
- Sem isso, ele **tapa os olhos** sozinho quando alguém digita numa `input[type=password]`
  e **sorri** com `input[type=email]` válido.

> **Acessibilidade.** O `montar()` marca o elemento como `aria-hidden="true"` — ele
> é decoração. Então **nunca** ponha `role="button"`/`tabindex` nele: seria um foco
> invisível para leitor de tela. Envolva num `<button>` de verdade, com o desenho
> dentro.

Na landing page ele também **acompanha** a pessoa (`js/orion-guia.js`), comentando
cada seção. As regras que o impedem de virar incômodo: não aparece no hero, some
onde já existe um Orion grande, dá para dispensar (a escolha vale a visita, em
`sessionStorage`), o balão fecha sozinho ao rolar, e no celular ele só fala se for
tocado.

---

## 8. Movimento e acessibilidade

**Regras que não se negociam.**

1. **`prefers-reduced-motion` sempre.** Está respeitado em 26 arquivos. Animação
   nova sem isso é bug. O padrão é o JS nem ligar o efeito **e** o CSS ter uma
   rede de segurança (`animation: none`, `transform: none`).

2. **`[hidden]` é `!important`** (`dashboard.css:16`). Use o atributo `hidden` para
   esconder; não brigue com `display`.

3. **Nada só no hover.** Todo hover tem par no toque e no teclado. Cartões mostram
   os botões em `:hover` **e** em `:focus-within`, e sempre visíveis em
   `@media (hover: none)`.

4. **Arrastar nunca é o único caminho.** O HTML5 drag-and-drop não existe no toque,
   nem no teclado, nem para leitor de tela. Onde houver arrastar, tem que haver um
   menu que faça o mesmo — ver o `⋮` dos cartões de resumo.

5. **`:focus-visible` em tudo que recebe foco**, com `outline: 2px solid var(--accent)`
   e `outline-offset`.

6. **Grupo de escolha é `radiogroup` de verdade**: `aria-checked`, setas navegando,
   só o escolhido no Tab.

7. **Não abra pedido de permissão sozinho** (giroscópio, notificação). Peça a partir
   de um clique da pessoa.

---

## 9. Convenções de código

- **Português em tudo**: classes, ids, variáveis, funções, comentários. Nomes como
  `carregarDecks`, `mostrarView`, `aviso`, `esc`, `plural`.
- **Zero bibliotecas no frontend.** O único `<script src>` externo é o do Google
  Identity. Canvas 2D, WebGL e animações são escritos à mão, de propósito.
- **Comentário explica o *porquê*, não o *quê*.** O padrão do projeto é registrar a
  decisão e a armadilha evitada — muitos bugs deste código voltariam sem isso.
- **CSS por feature**: `resumos.css`, `flashcards.css`, `pomodoro.css`… mais
  `dashboard.css` com o que é compartilhado.
- **Camada de sobreposição vai no fim do arquivo.** Regras que precisam vencer
  outras (a profundidade dos cards, por exemplo) dependem da ordem, porque a
  especificidade é igual.
- **Escape antes de `innerHTML`.** Todo texto vindo do banco passa por `esc()` /
  `escapar()` no JS e `hesc()` no PHP.

---

## 10. Antes de entregar uma tela

- [ ] Usei tokens, não hex solto?
- [ ] O componente já existia?
- [ ] Tem estado vazio, e ele distingue "nada" de "nada neste filtro"?
- [ ] Funciona em 768px e no toque?
- [ ] Respeita `prefers-reduced-motion`?
- [ ] O que é clicável tem `:focus-visible` e rótulo acessível?
- [ ] Nenhum elemento focável dentro de `aria-hidden`?
- [ ] Se há arrastar, existe caminho equivalente sem arrastar?
- [ ] Texto do usuário escapado antes de virar HTML?
- [ ] Se o cartão é desenhado por PHP **e** por JS, os dois estão iguais?
