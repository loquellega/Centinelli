@echo off
rem Corre el agente a mano, con ventana, para ver qué hace (Ctrl+C para salir).
rem El arranque automático no usa este archivo: lo registra INSTALAR.bat (sin ventana).
cd /d "%~dp0"
if exist "%~dp0python\python.exe" (
  "%~dp0python\python.exe" centinelli_agente.py config.json
) else (
  python centinelli_agente.py config.json
)
pause
