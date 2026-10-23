# Manual do Kosmos (o que o Orion sabe sobre o site)
<!--
  Lido pelo Backend/php/orion_chat.php e enviado à IA em toda mensagem.
  MANTENHA CURTO: cada palavra gasta cota do Groq (tokens por minuto).
  Mudou uma aba, um botão ou uma regra? Atualize aqui também.
  O .htaccess do Backend bloqueia .md no navegador.
-->

Kosmos: app gratuito de organização de estudos para ensino médio, ENEM e vestibular. Menu lateral (no celular, barra de baixo + botão "Mais"): Início, Orion, Buscar, Biblioteca, Flashcards, Exercícios, Pomodoro, Provas. No rodapé do menu: barra de XP/nível (abre Conquistas), avatar (abre Conta) e engrenagem (atalhos de configuração).

## Abas
- **Início**: resumo do dia. Gráfico "Esta semana" com minutos de foco por dia (clicar abre o calendário do mês), meta diária, atalhos, "Revisar hoje" (aparece quando há cartões vencidos), "Primeiros passos" (escrever 1 resumo, criar 1 flashcard, fazer 1 Pomodoro) e depois "Missões da semana" (4 por semana: estudar em X dias, somar minutos de foco, escrever resumos, revisar flashcards, criar cartões, responder questões).
- **Biblioteca**: cadernos por matéria (cor, ícone, capa) com resumos dentro. Resumo tem título, texto e fotos (ex.: foto do caderno de papel). Arraste um resumo para outro caderno; resumos sem caderno ficam em "Sem caderno". No resumo aberto: Modo revisão (o texto marcado com ==destaque== vira lacuna para completar), exportar PDF e "Gerar flashcards" (também no menu ⋮ de cada resumo).
- **Flashcards**: baralhos (deck) com cartões frente/verso. "Estudar" mostra a frente; a pessoa responde "Já sei" ou "Ainda não". Repetição espaçada: acertou, o cartão volta em mais dias (1, 3, depois multiplica até 180); errou, volta amanhã. Para revisar os cartões vencidos de TODOS os baralhos numa fila só, use o botão "Revisar hoje", que fica SÓ no Início (não existe dentro da aba Flashcards) e só aparece quando há cartões vencidos; dentro da aba Flashcards, o "Estudar" de cada baralho estuda só aquele baralho.
- **Exercícios**: matérias (aparecem como estrelas num céu ou em lista). Dentro de uma matéria, "Novo exercício" gera questões com IA: escolhe o conteúdo, as dificuldades (Fácil/Médio/Difícil) e quantas (até 15). Depois é só praticar: 5 alternativas, dica opcional e correção no fim, onde há o botão "Por que eu errei?" que chama o Orion.
- **Pomodoro**: timer de foco (padrão 25 min) e pausas (5 min, longa de 15 a cada 4 ciclos). Os tempos mudam em Conta › Estudo. Continua contando com a aba fechada; durante o foco o "modo foco" esconde distrações.
- **Provas**: marca a prova (título, matéria, data), lista os assuntos que caem (marca os já estudados), mostra o material da matéria (cadernos e baralhos) e a contagem de dias. Depois da prova, registre a nota.
- **Buscar**: procura de uma vez em resumos, cadernos, baralhos, cartões e exercícios.
- **Orion**: este chat.
- **Conquistas** (clicar na barra de XP): nível (1 a 50, com títulos), trilha de recompensas (molduras, emblemas e cores do avatar que liberam com o nível) e "Como ganhar XP".
- **Conta**: Perfil (nome, e-mail, foto e cor do avatar), Segurança (trocar senha, sair de todos os aparelhos), Estudo (tempos do Pomodoro, meta diária, matérias favoritas, visual Lite), Notificações (lembrete diário e resumo semanal por e-mail), Privacidade (baixar meus dados, excluir conta) e Sobre.

## XP (ganha estudando, com teto por dia)
Pomodoro concluído 50 (mín. 15 min) · cartão acertado 5 · sessão de flashcards 30 · questão certa 20, errada 5 · resumo novo 40 · estudo com o Orion (gerar questões) 10 · sequência de dias: 25 por dia + bônus por semana completa.

## Outras coisas
- Sequência: dias seguidos com acesso; pular um dia zera.
- Visual Lite (Conta › Estudo ou engrenagem): menos efeitos, para celular fraco ou foco.
- Esqueceu a senha: na tela de login, "Esqueceu a senha?" manda um link por e-mail (vale 1 hora).
- Entrar com Google: funciona; a primeira vez pede para criar uma senha.
- Tudo é gratuito. Os dados ficam na conta, em qualquer aparelho.
