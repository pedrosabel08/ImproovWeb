param(
    [string]$ServiceName = "FlowDeadlineWorker",
    [string]$PythonPath = "C:\Users\usuario\AppData\Local\Programs\Python\Python313\python.exe",
    [Parameter(Mandatory = $true)]
    [string]$NssmPath
)

$ErrorActionPreference = "Stop"
$scriptDirectory = Split-Path -Parent $MyInvocation.MyCommand.Path
$workerPath = Join-Path $scriptDirectory "deadline_worker.py"
$logDirectory = Join-Path $scriptDirectory "logs"

if (-not (Test-Path -LiteralPath $NssmPath)) {
    throw "NSSM nao encontrado: $NssmPath"
}
if (-not (Test-Path -LiteralPath $PythonPath)) {
    throw "Python nao encontrado: $PythonPath"
}
if (-not (Test-Path -LiteralPath $workerPath)) {
    throw "Worker nao encontrado: $workerPath"
}
New-Item -ItemType Directory -Path $logDirectory -Force | Out-Null

& $NssmPath install $ServiceName $PythonPath $workerPath
if ($LASTEXITCODE -ne 0) { throw "Falha ao instalar o servico $ServiceName." }
& $NssmPath set $ServiceName AppDirectory $scriptDirectory
& $NssmPath set $ServiceName DisplayName "Flow x Deadline Continuous Worker"
& $NssmPath set $ServiceName Description "Fila, sincronizacao e descoberta Flow x Deadline"
& $NssmPath set $ServiceName Start SERVICE_AUTO_START
& $NssmPath set $ServiceName AppExit Default Restart
& $NssmPath set $ServiceName AppRestartDelay 5000
& $NssmPath set $ServiceName AppStdout (Join-Path $logDirectory "service-stdout.log")
& $NssmPath set $ServiceName AppStderr (Join-Path $logDirectory "service-stderr.log")
& $NssmPath set $ServiceName AppRotateFiles 1
& $NssmPath set $ServiceName AppRotateOnline 1
& $NssmPath set $ServiceName AppRotateBytes 10485760

Write-Host "Servico $ServiceName instalado. Valide em dry-run antes de executar: sc start $ServiceName"
