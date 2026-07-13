@echo off
setlocal
set "SERVICE_NAME=FlowDeadlineWorker"
sc query "%SERVICE_NAME%" >nul 2>&1
if %errorlevel% equ 0 (
    sc stop "%SERVICE_NAME%"
    if errorlevel 1 exit /b
    for /l %%I in (1,1,30) do (
        sc query "%SERVICE_NAME%" ^| find "STOPPED" >nul
        if not errorlevel 1 exit /b 0
        timeout /t 1 /nobreak >nul
    )
    echo Servico nao parou em 30 segundos.
    exit /b 1
)

set "PID_FILE=%~dp0deadline_worker.pid"
if not exist "%PID_FILE%" (
    echo Worker nao esta em execucao ou o PID file nao existe.
    exit /b 1
)
set /p WORKER_PID=<"%PID_FILE%"
>"%~dp0deadline_worker.stop" echo stop
for /l %%I in (1,1,30) do (
    tasklist /FI "PID eq %WORKER_PID%" 2>nul ^| find "%WORKER_PID%" >nul
    if errorlevel 1 exit /b 0
    timeout /t 1 /nobreak >nul
)
echo Encerramento gracioso excedeu 30 segundos. Solicitando termino do processo.
taskkill /PID %WORKER_PID% /T
exit /b
