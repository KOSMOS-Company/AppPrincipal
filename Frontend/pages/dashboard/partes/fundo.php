<?php
if (!isset($USUARIO, $PREF)) { http_response_code(403); exit('Esta página não é acessada direto.'); }

/* ============================================================
   O FUNDO DO DASHBOARD

   Antes daqui cada página repetia um bloco `.bg` com um degradê
   parado, duas orbes desfocadas e um grid. Bonito, mas era OUTRO
   universo: a landing page tem um céu de verdade — estrelas em
   quatro profundidades, Via Láctea, nebulosa em ruído fractal —
   e quem entrava no app trocava isso por um papel de parede.

   Agora é o MESMO céu, dos mesmos dois arquivos
   (Frontend/pages/shared/cosmos-gl.js e cosmos.js), só que em
   modo calmo: `data-calmo` faz o shader render em menos resolução
   e em metade dos quadros, e o CSS do dashboard baixa a opacidade
   do canvas e fecha mais o véu. Aqui a pessoa fica horas
   estudando — o céu tem que estar atrás do trabalho, não na
   frente dele.

   As três camadas, na ordem em que o navegador as pinta:
     canvas.cosmos  →  o céu
     .veu           →  a folha que segura a legibilidade
     .contGeral     →  o app

   Fica numa parte só porque são OITO páginas. Mudar o fundo em
   uma e esquecer as outras sete é exatamente o tipo de coisa que
   este arquivo existe para impedir.
   ============================================================ */
?>
    <canvas class="cosmos" id="cosmos" data-calmo aria-hidden="true"></canvas>
    <div class="veu" aria-hidden="true"></div>
