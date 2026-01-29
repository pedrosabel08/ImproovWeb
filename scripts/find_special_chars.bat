@echo off
setlocal enableextensions enabledelayedexpansion

set "TARGET_DIR=C:\Marcio\LD1_RES\mapas"
set "OUT_FILE=%~dp0special_chars_report.txt"

if not exist "%TARGET_DIR%" (
  echo Pasta nao encontrada: "%TARGET_DIR%"
  exit /b 1
)

echo Verificando arquivos em: "%TARGET_DIR%"
echo Gerando relatorio em: "%OUT_FILE%"

echo === Arquivos com caracteres especiais ===> "%OUT_FILE%"

for /r "%TARGET_DIR%" %%F in (*.*) do (
  set "NAME=%%~nxF"
  set "SPECIAL=0"
  rem Se tiver apenas letras, numeros, espaco, ponto, underline, hifen, parenteses, colchetes, chaves
  rem entao NAO tem caractere especial
  echo(!NAME!| findstr /r /c:"^[A-Za-z0-9 ._()\[\]{}-]*$" >nul
  if errorlevel 1 set "SPECIAL=1"

  if !SPECIAL! EQU 1 (
    echo %%F>> "%OUT_FILE%"
  )
)

echo Concluido.
exit /b 0
