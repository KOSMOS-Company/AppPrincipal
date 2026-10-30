# ============================================================
#  KOSMOS — Agenda as tarefas do Kosmos no Agendador do Windows
#  Arquivo: Backend/tarefas/agendar-windows.ps1
#
#  Cria (ou recria) três tarefas no Agendador de Tarefas:
#    Kosmos - Lembrete diario   todo dia, 19h       notificacoes.php lembrete --enviar
#    Kosmos - Resumo semanal    domingo, 18h        notificacoes.php resumo --enviar
#    Kosmos - Limpeza           todo dia, 3h        limpeza.php --apagar
#
#  ATENÇÃO: só UM computador da equipe deve rodar isto (ou o servidor,
#  quando o site for publicado). Os e-mails saem para as contas do
#  banco DESTE computador; com dois PCs agendados, quem tiver conta nos
#  dois recebe tudo em dobro. Ver docs/CONFIGURACAO-LOCAL.md.
#
#  Precisa, no Backend/php/config.php: N8N_NOTIF_URL e APP_URL.
#  As tarefas rodam só com este usuário logado no Windows e o XAMPP
#  (MySQL) ligado; se o PC estiver desligado no horário, a tarefa roda
#  assim que ele ligar.
#
#  Uso (PowerShell, na raiz do AppPrincipal):
#      powershell -ExecutionPolicy Bypass -File Backend\tarefas\agendar-windows.ps1
#      powershell -ExecutionPolicy Bypass -File Backend\tarefas\agendar-windows.ps1 -Remover
#  Opcional: -Php "D:\xampp\php\php.exe" se o XAMPP não estiver em C:\xampp.
# ============================================================

param(
    [switch]$Remover,
    [string]$Php = 'C:\xampp\php\php.exe'
)

$ErrorActionPreference = 'Stop'
$raiz = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path

$tarefas = @(
    @{ Nome = 'Kosmos - Lembrete diario'; Args = 'Backend\tarefas\notificacoes.php lembrete --enviar'
       Gatilho = { New-ScheduledTaskTrigger -Daily -At '19:00' } },
    @{ Nome = 'Kosmos - Resumo semanal'; Args = 'Backend\tarefas\notificacoes.php resumo --enviar'
       Gatilho = { New-ScheduledTaskTrigger -Weekly -DaysOfWeek Sunday -At '18:00' } },
    @{ Nome = 'Kosmos - Limpeza'; Args = 'Backend\tarefas\limpeza.php --apagar'
       Gatilho = { New-ScheduledTaskTrigger -Daily -At '03:00' } }
)

if ($Remover) {
    foreach ($t in $tarefas) {
        if (Get-ScheduledTask -TaskName $t.Nome -ErrorAction SilentlyContinue) {
            Unregister-ScheduledTask -TaskName $t.Nome -Confirm:$false
            Write-Host "Removida: $($t.Nome)"
        }
    }
    exit 0
}

if (-not (Test-Path $Php)) {
    Write-Error "Não achei o PHP em $Php. Use -Php com o caminho do php.exe."
}

# Confere antes de agendar: a simulação lista quem receberia, sem enviar
& $Php (Join-Path $raiz 'Backend\tarefas\notificacoes.php') lembrete | Out-Null
if ($LASTEXITCODE -ne 0) {
    Write-Error 'O notificacoes.php falhou na simulação. Confira o config.php e se o MySQL está ligado.'
}

# Se o horário passar com o PC desligado, roda quando ele ligar
$opcoes = New-ScheduledTaskSettingsSet -StartWhenAvailable -ExecutionTimeLimit (New-TimeSpan -Minutes 30)

foreach ($t in $tarefas) {
    $acao = New-ScheduledTaskAction -Execute $Php -Argument $t.Args -WorkingDirectory $raiz
    Register-ScheduledTask -TaskName $t.Nome -Action $acao -Trigger (& $t.Gatilho) `
        -Settings $opcoes -Description 'Kosmos (Backend/tarefas/agendar-windows.ps1)' -Force | Out-Null
    Write-Host "Agendada: $($t.Nome)"
}
Write-Host ''
Write-Host 'Pronto. Para conferir: Agendador de Tarefas > Biblioteca. Para desfazer: -Remover.'
