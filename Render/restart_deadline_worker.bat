@echo off
setlocal
call "%~dp0stop_deadline_worker.bat"
timeout /t 3 /nobreak >nul
call "%~dp0start_deadline_worker.bat"
exit /b %errorlevel%
