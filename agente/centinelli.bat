@echo off
rem Corre el agente y lo relanza si se llega a cerrar.
cd /d "%~dp0"
:loop
python centinelli_agente.py config.json >> centinelli.log 2>&1
timeout /t 5 /nobreak >nul
goto loop
