@echo off
setlocal
set "SERVICE_NAME=FlowDeadlineWorker"
sc query "%SERVICE_NAME%" >nul 2>&1
if %errorlevel% equ 0 (
    sc start "%SERVICE_NAME%"
    exit /b
)

set "PYTHON=C:\Users\usuario\AppData\Local\Programs\Python\Python313\python.exe"
if not exist "%PYTHON%" (
    echo Python nao encontrado em %PYTHON%.
    exit /b 1
)
cd /d "%~dp0"
"%PYTHON%" deadline_worker.py
exit /b
