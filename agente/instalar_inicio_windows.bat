@echo off
rem Registra el agente para que arranque al iniciar sesión (necesario para ver la pantalla
rem y lanzar TouchDesigner/Isadora con interfaz; un "servicio" de Windows no puede hacerlo).
rem Ejecutar como administrador.
schtasks /Create /SC ONLOGON /TN "Centinelli agente" /TR "\"%~dp0centinelli.bat\"" /RL HIGHEST /F
echo.
echo Listo. El agente arrancara en el proximo inicio de sesion.
echo Para probarlo ahora:  schtasks /Run /TN "Centinelli agente"
pause
