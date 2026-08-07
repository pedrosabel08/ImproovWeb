@echo off
set PY=C:\Users\usuario\AppData\Local\Python\bin\python.exe
cd /d C:\xampp\htdocs\ImproovWeb\Render
"%PY%" script.py >> run_schedule.log 2>&1
exit /b %ERRORLEVEL%