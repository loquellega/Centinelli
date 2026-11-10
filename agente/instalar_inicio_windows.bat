@echo off
rem Sólo registra el arranque automático (si ya configuraste todo a mano).
rem Lo normal es usar INSTALAR.bat, que además instala Python y escribe config.json.
rem Ejecutar como administrador.
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$b='%~dp0'.TrimEnd('\');" ^
  "$py = if (Test-Path \"$b\python\pythonw.exe\") { \"$b\python\pythonw.exe\" } else { (Get-Command pythonw).Source };" ^
  "$a = New-ScheduledTaskAction -Execute $py -Argument ('\"' + $b + '\centinelli_agente.py\" \"' + $b + '\config.json\"') -WorkingDirectory $b;" ^
  "$t = New-ScheduledTaskTrigger -AtLogOn -User $env:USERNAME;" ^
  "$p = New-ScheduledTaskPrincipal -UserId $env:USERNAME -LogonType Interactive -RunLevel Highest;" ^
  "$s = New-ScheduledTaskSettingsSet -ExecutionTimeLimit ([TimeSpan]::Zero) -RestartCount 999 -RestartInterval (New-TimeSpan -Minutes 1) -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable;" ^
  "Register-ScheduledTask -TaskName 'Centinelli agente' -Action $a -Trigger $t -Principal $p -Settings $s -Force | Out-Null;" ^
  "Write-Host 'Listo: Centinelli arrancara solo, sin ventana, al iniciar sesion.'"
pause
