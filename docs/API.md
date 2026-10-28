# 🔌 Endpoints do backend

Todos ficam em `Backend/php/` e respondem JSON. O frontend chama por `fetch`,
com caminho relativo (`../../../Backend/php/...` a partir de uma página).

## Formato da resposta

```jsonc
// sucesso — HTTP 2xx
{ "ok": true, ...dados }

// erro — HTTP 4xx/5xx
{ "ok": false, "msg": "Texto pronto para mostrar à pessoa." }
```

O frontend decide pelo campo `ok`, e o código HTTP diz **que tipo** de erro foi.
O formato mora num lugar só, em [`api_util.php`](../Backend/php/api_util.php)
(`apiResponder`, `apiErro`, `apiExigirPost`). Os atalhos `fc*` (flashcards) e
`est*` (estudo) só repassam para ele.

| Código | Quando |
|---|---|
| 200 / 201 | deu certo (201 = conta criada) |
| 400 | pedido malformado (ação desconhecida, id inválido, token com formato errado) |
| 401 | sem login, ou login/senha errados |
| 403 | requisição vinda de outro site (`exigirMesmaOrigem`), ou sem permissão |
| 404 | não encontrado — **ou não é seu** (nunca dizemos que existe) |
| 405 | método errado (ex.: GET num endpoint de POST) |
| 409 | conflito: e-mail já cadastrado ou em uso |
| 422 | dado inválido (campo vazio, longo demais, senha curta...) |
| 429 | tentativas demais (ver limites abaixo) |
| 500 | erro nosso (o detalhe vai para `Backend/logs/php-erros.log`) |
| 502 | o n8n (IA) falhou ou respondeu num formato inesperado |

## Segurança comum a todos

- **Protegido** = chama `exigirLogin()`: sem sessão válida → 401. A mesma função
  derruba sessões antigas ("sair de todos os aparelhos", troca de senha).
- **POST de outro site** → 403 (`exigirMesmaOrigem`, comparando `Origin` com `Host`).
  Vale nos protegidos e também no login, cadastro, senha e logout.
- **Dono:** toda consulta filtra por `usuario_id`. Trocar o `?id=` na URL devolve 404.
- **Limites** ([`limite_util.php`](../Backend/php/limite_util.php)):

  | Tipo | Janela | Por e-mail/conta | Por IP |
  |---|---|---|---|
  | login (falhas) | 15 min | 5 | 20 |
  | esqueci a senha | 60 min | 3 | 10 |
  | cadastro | 60 min | 5 | 10 |
  | geração por IA | 60 min | 20 (por conta) | 60 |
  | conversa com o Orion | 60 min | 40 (por conta) | 120 |

## Autenticação e conta

| Endpoint | Método | Entrada | Observações |
|---|---|---|---|
| `login.php` | POST | `email`, `senha` | 401 genérico; o tempo de resposta é igual exista o e-mail ou não |
| `login_google.php` | POST | `access_token` ou `credential` | valida no Google e confere o `aud` (Client ID); `precisa_senha` na resposta |
| `cadastro.php` | POST | `nome`, `email`, `senha` | 201 criado, 409 e-mail em uso, 429 limite |
| `logout.php` | POST | — | só POST (antes um `<img>` de outro site deslogava a pessoa) |
| `senha_esqueci.php` | POST | `email` | resposta **sempre** genérica; o e-mail sai pelo n8n |
| `senha_resetar.php` | POST | `token`, `senha_nova` | token de 64 hex, uso único, 1 hora |
| `usuario_atual.php` | GET | — | quem está logado. **Legado:** nenhuma tela chama mais (a dashboard faz isso no servidor, pelo `pagina_dashboard.php`) |
| `config_publica.php` | GET | — | público: `google_client_id` (vem do `config.php`) |
| `conta_dados.php` | GET | — | protegido: perfil e números da conta |
| `conta_perfil.php` | POST | `nome`, `email` | protegido; 409 se o e-mail for de outra conta |
| `conta_senha.php` | POST | `senha_atual`, `senha_nova` | protegido; conta só Google cria a primeira senha |
| `conta_preferencias.php` | GET / POST | campos de `usuario_preferencias` | protegido |
| `conta_avatar.php` | POST | arquivo `foto`, ou `acao=remover` | protegido; JPG/PNG/WEBP até 2 MB, tipo conferido pelo conteúdo |
| `conta_sessoes.php` | POST | — | protegido: derruba as outras sessões |
| `conta_exportar.php` | GET | — | protegido: download de tudo em JSON (LGPD) |
| `conta_excluir.php` | POST | `senha` ou `confirmacao=EXCLUIR` | protegido: apaga a conta e tudo o que é dela |
| `onboarding_salvar.php` | POST (JSON) | `objetivo`, `meta_diaria`, `materias[]`, `pular` | protegido |
| `tutorial_visto.php` | POST | — | protegido (marca o tour do Orion como visto) |
| `tutorial_concluido.php` | POST | — | protegido: recompensa do tour (75 XP + conquista "Decolagem"), uma vez por conta |
| `recompensas_equipar.php` | POST | `tipo` (moldura/cor/emblema), `slug` | protegido; o nível é conferido no servidor |

## Estudo

Os endpoints com várias operações recebem o campo `acao`.

| Endpoint | Método | Ações / entrada |
|---|---|---|
| `inicio_dados.php` | GET | semana, hoje, meta, métricas, primeiros passos e missões |
| `foco_mensal.php` | GET | `?mes=AAAA-MM` — calendário de foco do mês |
| `pomodoro_sessao.php` | POST (JSON) | `fim` (epoch em segundos), `minutos` — idempotente |
| `busca.php` | GET | `?q=termo` — resumos, cadernos, baralhos, cartões e exercícios |
| `resumos_listar.php` | GET | `?caderno=ID` (opcional; `0` = soltos) |
| `resumos_salvar.php` | POST | `titulo`, `corpo`, `caderno`, `materia`, `id` (editar) |
| `resumos_excluir.php` | POST | `id` |
| `resumos_mover.php` | POST | `resumo`, `caderno` (vazio = sem caderno) |
| `resumos_imagem.php` | POST | `enviar` (`resumo` + `imagens[]`), `excluir` (`id`) |
| `resumos_caderno.php` | POST | `criar`, `editar`, `excluir`, `ordenar` |
| `resumos_capa.php` | POST | `enviar` (`caderno` + `capa`), `remover` |
| `flashcards_listar.php` | GET | baralhos com totais |
| `flashcards_cartoes.php` | GET | `?deck=ID` |
| `flashcards_deck.php` | POST | `criar`, `editar`, `excluir` |
| `flashcards_cartao.php` | POST | `criar`, `editar`, `excluir` |
| `flashcards_revisao.php` | POST | `deck`, `respostas` (JSON) — uma transação |
| `flashcards_gerar.php` | POST (JSON) | `sugerir` (`resumo`), `salvar` — por regra, sem IA |
| `revisar_fila.php` | GET | cartões vencidos de todos os baralhos |
| `provas.php` | GET / POST | `criar`, `editar`, `excluir`, `resultado`, `topico_criar`, `topico_alternar`, `topico_excluir` |
| `exercicios_materia.php` | POST | `criar`, `editar`, `excluir`, `ordenar` |
| `exercicios_listar.php` | GET | `?materia_id=ID` ou `?id=ID` |
| `exercicios_salvar.php` | POST | `criar`, `editar`, `excluir` |
| `exercicios_responder.php` | POST | `exercicio`, `questao`, `escolhida` — o gabarito é do servidor |
| `orion_chat.php` | POST (JSON) | `mensagem` (≤ 1000), `historico` (últimas 8), `pagina`, `questao` (do "Por que eu errei?") — devolve `resposta` e `humor` do mascote; chama o n8n (Groq), espera até 45 s; sem `N8N_ORION_URL` responde 503 e as telas usam as respostas prontas |
| `gerar_exercicios.php` | POST | `materia` (≤ 200), `conteudo` (≤ 1000), `plano` (JSON, até 15 questões) — chama o n8n, espera até 90 s |

## Arquivos que não são endpoints

Bibliotecas incluídas pelos endpoints (não chame direto): `bootstrap.php`,
`api_util.php`, `sessao.php`, `pagina_dashboard.php`, `*_comum.php`, `*_util.php`,
`ProgressoService.php`, `missoes.php`, `orion_util.php` (e o `orion_manual.md`, o que o Orion sabe do site), `materias.php`, `datas.php`,
`conquistas_icones.php`. Scripts de terminal ficam em `Backend/tarefas/` e
`Backend/sql/migrar.php`, e o `.htaccess` bloqueia os dois no navegador.
