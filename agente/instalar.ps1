<#
  Centinelli · instalador para Windows
  Colección Alchemy of Meaning — Héctor Cruz Juárez — https://hectorcruz.com.mx

  Uso
    Desde internet (lo muestra la página "instalar" del dashboard):
      iwr https://raw.githubusercontent.com/loquellega/centinelli/main/agente/instalar.ps1 -UseBasicParsing -OutFile $env:TEMP\ci.ps1
      powershell -ExecutionPolicy Bypass -File $env:TEMP\ci.ps1 -Servidor 'https://…/api.php' -Token '…'

    Desde una USB: doble clic en INSTALAR.bat (no necesita internet para instalar).

    Preparar una USB (en una PC con internet):
      powershell -ExecutionPolicy Bypass -File instalar.ps1 -PrepararUSB E:\centinelli

  Qué hace
    1. Python portátil en C:\centinelli\python (no se instala en Windows, no toca nada más)
    2. Paquetes psutil, mss y pillow dentro de esa carpeta
    3. Copia el agente a C:\centinelli
    4. Te pregunta qué máquina es y escribe config.json
    5. (opcional) Abre en el firewall los puertos de Resolume para la red privada
    6. Registra el arranque automático al iniciar sesión y lo arranca
#>
param(
    [string]$Servidor = "",
    [string]$Token = "",
    [string]$Destino = "C:\centinelli",
    [string]$PrepararUSB = ""
)

# "Continue": en PowerShell 5, con "Stop", cualquier aviso que python o pip escriban en stderr
# se vuelve un error fatal. Los pasos que importan llevan -ErrorAction Stop o revisan $LASTEXITCODE.
$ErrorActionPreference = "Continue"
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
$ProgressPreference = "SilentlyContinue"   # descargas mucho más rápidas en PowerShell 5

$PyVer   = "3.12.10"
$PyZip   = "https://www.python.org/ftp/python/$PyVer/python-$PyVer-embed-amd64.zip"
$GetPip  = "https://bootstrap.pypa.io/get-pip.py"
$RepoZip = "https://github.com/loquellega/centinelli/archive/refs/heads/main.zip"
$Aqui    = Split-Path -Parent $MyInvocation.MyCommand.Path

function Titulo($t) { Write-Host ""; Write-Host "== $t" -ForegroundColor Green }
function Ok($t)     { Write-Host "   OK  $t" -ForegroundColor Gray }
function Pregunta($t, $def) {
    $s = if ($def) { " [$def]" } else { "" }
    $r = Read-Host "   $t$s"
    if ([string]::IsNullOrWhiteSpace($r)) { return $def } else { return $r.Trim() }
}
function SiNo($t, $def = "s") {
    $r = Pregunta "$t (s/n)" $def
    return ($r -match '^[sSyY]')
}
function EscribirJson($ruta, $obj) {
    $json = $obj | ConvertTo-Json -Depth 20
    [IO.File]::WriteAllText($ruta, $json, (New-Object Text.UTF8Encoding $false))   # UTF-8 sin BOM
}

Write-Host ""
Write-Host "  CENTINELLI · instalador" -ForegroundColor White
Write-Host "  monitoreo remoto de instalaciones · Alchemy of Meaning" -ForegroundColor DarkGray

# ───────────────────────────── administrador ─────────────────────────────
if (-not $PrepararUSB) {
    $admin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole(
        [Security.Principal.WindowsBuiltInRole]::Administrator)
    if (-not $admin) {
        Write-Host "   Hace falta permiso de administrador; se abrirá otra ventana." -ForegroundColor Yellow
        $args2 = @("-NoProfile", "-ExecutionPolicy", "Bypass", "-File", "`"$($MyInvocation.MyCommand.Path)`"",
                   "-Destino", "`"$Destino`"")
        if ($Servidor) { $args2 += @("-Servidor", "`"$Servidor`"") }
        if ($Token)    { $args2 += @("-Token", "`"$Token`"") }
        Start-Process powershell -Verb RunAs -ArgumentList $args2
        exit
    }
}

trap {
    Write-Host ""
    Write-Host "   X  $($_.Exception.Message)" -ForegroundColor Red
    Write-Host "      Si es una descarga, revisa la conexión a internet o usa la instalación por USB." -ForegroundColor DarkGray
    Read-Host "   Enter para cerrar"
    exit 1
}

$base = if ($PrepararUSB) { $PrepararUSB } else { $Destino }
New-Item -ItemType Directory -Force -Path $base | Out-Null

# ───────────────────────────── Python portátil ─────────────────────────────
Titulo "Python portátil"
$py = Join-Path $base "python\python.exe"
$pyUSB = Join-Path $Aqui "python\python.exe"
if (Test-Path $py) {
    Ok "ya está en $base\python"
} elseif ((Test-Path $pyUSB) -and ($Aqui -ne $base)) {
    Copy-Item -Recurse -Force (Join-Path $Aqui "python") $base
    Ok "copiado desde la USB"
} else {
    $tmp = Join-Path $env:TEMP "centinelli-py.zip"
    Write-Host "   descargando Python $PyVer…"
    Invoke-WebRequest $PyZip -OutFile $tmp -UseBasicParsing -ErrorAction Stop
    Expand-Archive $tmp -DestinationPath (Join-Path $base "python") -Force -ErrorAction Stop
    Remove-Item $tmp
    # habilitar site-packages en el Python embebido
    $pth = Get-ChildItem (Join-Path $base "python") -Filter "python*._pth" | Select-Object -First 1
    (Get-Content $pth.FullName) -replace '^#\s*import site', 'import site' | Set-Content $pth.FullName -Encoding ASCII
    Write-Host "   instalando pip…"
    $gp = Join-Path $env:TEMP "get-pip.py"
    Invoke-WebRequest $GetPip -OutFile $gp -UseBasicParsing -ErrorAction Stop -ErrorAction Stop
    & $py $gp --no-warn-script-location -q 2>&1 | Out-Null
    if ($LASTEXITCODE -ne 0) { Write-Host "   X  no se pudo instalar pip" -ForegroundColor Red; Read-Host "   Enter para cerrar"; exit 1 }
    Remove-Item $gp
    Ok "Python $PyVer en $base\python"
}
$tiene = & $py -c "import psutil, mss, PIL; print('ok')" 2>$null
if ($tiene -ne "ok") {
    Write-Host "   instalando psutil, mss y pillow…"
    & $py -m pip install --no-warn-script-location -q psutil mss pillow 2>&1 | Out-Null
    $tiene = & $py -c "import psutil, mss, PIL; print('ok')" 2>$null
    if ($tiene -ne "ok") { Write-Host "   X  no se pudieron instalar los paquetes (¿internet?)" -ForegroundColor Red; Read-Host "   Enter para cerrar"; exit 1 }
}
Ok "paquetes listos"

# ───────────────────────────── archivos del agente ─────────────────────────────
Titulo "Agente Centinelli"
if (Test-Path (Join-Path $Aqui "centinelli_agente.py")) {
    if ($Aqui -ne $base) {
        Get-ChildItem $Aqui -File | Where-Object { $_.Name -notin @("config.json", "pantallas_referencia.json", "centinelli.log") } |
            Copy-Item -Destination $base -Force
    }
    Ok "copiado desde $Aqui"
} else {
    $tmp = Join-Path $env:TEMP "centinelli-repo.zip"
    $dir = Join-Path $env:TEMP "centinelli-repo"
    Write-Host "   descargando de GitHub…"
    Invoke-WebRequest $RepoZip -OutFile $tmp -UseBasicParsing -ErrorAction Stop
    if (Test-Path $dir) { Remove-Item -Recurse -Force $dir }
    Expand-Archive $tmp -DestinationPath $dir -Force -ErrorAction Stop
    $src = Get-ChildItem $dir -Directory | Select-Object -First 1
    Get-ChildItem (Join-Path $src.FullName "agente") -File | Copy-Item -Destination $base -Force
    Remove-Item $tmp; Remove-Item -Recurse -Force $dir
    Ok "última versión desde GitHub"
}

if ($PrepararUSB) {
    Titulo "USB lista"
    Write-Host "   Carpeta: $PrepararUSB"
    Write-Host "   En cada server: abre esa carpeta y haz doble clic en INSTALAR.bat"
    Write-Host "   (no necesita internet para instalar; sí para reportar al dashboard)"
    exit
}

# ───────────────────────────── configuración ─────────────────────────────
$cfgPath = Join-Path $base "config.json"
$configurar = $true
if (Test-Path $cfgPath) {
    Titulo "Configuración"
    $configurar = -not (SiNo "Ya hay un config.json en esta máquina. ¿Conservarlo?" "s")
}

$rol = "0"
if ($configurar) {
    Titulo "¿Qué máquina es esta?"
    Write-Host "   1  Server 1 de una sala Resolume (vigila a los demás y sincroniza)"
    Write-Host "   2  Otro server de la sala (vigila sus pantallas y su Arena)"
    Write-Host "   3  Instalación con TouchDesigner"
    Write-Host "   4  Instalación con Isadora"
    Write-Host "   5  Resolume suelto (sin sala)"
    $rol = Pregunta "Número" "1"
    $plantilla = @{ "1" = "config.server1.json"; "2" = "config.servers2-5.json"; "3" = "config.ejemplo.json";
                    "4" = "config.isadora.json"; "5" = "config.resolume.json" }[$rol]
    if (-not $plantilla) { throw "Opción no válida: $rol" }
    $c = Get-Content (Join-Path $base $plantilla) -Raw -Encoding UTF8 | ConvertFrom-Json

    Titulo "Conexión con el dashboard"
    if (-not $Servidor) { $Servidor = Pregunta "Dirección del servidor (api.php)" "https://hectorcruz.com.mx/centinelli/api.php" }
    if (-not $Token)    { $Token = Pregunta "Token de los agentes (está en la página 'instalar' del dashboard)" "" }
    $c.server = $Servidor
    $c.token = $Token

    Titulo "Nombre de esta máquina"
    $sug = if ($rol -eq "1") { "mhn-server1" } elseif ($rol -eq "2") { "mhn-server2" } else { ($env:COMPUTERNAME).ToLower() }
    $c.id = (Pregunta "Identificador corto, sin espacios" $sug) -replace '[^A-Za-z0-9_-]', '-'
    $c.name = Pregunta "Nombre que verás en el dashboard" (($c.id -replace '-', ' ').ToUpper())

    Titulo "Aplicación"
    $exeSug = @{ "3" = "C:\Program Files\Derivative\TouchDesigner\bin\TouchDesigner.exe";
                 "4" = "C:\Program Files\TroikaTronix\Isadora\Isadora.exe" }[$rol]
    if (-not $exeSug) {
        $exeSug = @("C:\Program Files\Resolume Arena\Arena.exe", "C:\Program Files\Resolume Avenue\Avenue.exe") |
            Where-Object { Test-Path $_ } | Select-Object -First 1
        if (-not $exeSug) { $exeSug = "C:\Program Files\Resolume Arena\Arena.exe" }
    }
    $exe = Pregunta "Programa" $exeSug
    $arch = Pregunta "Archivo del show (.avc / .toe / .izz), Enter para ninguno" ""
    $c.app.process = Split-Path $exe -Leaf
    $launch = @($exe -replace '\\', '/')
    if ($arch) { $launch += ($arch -replace '\\', '/') }
    $c.app.launch = $launch
    if ($c.app.PSObject.Properties.Name -contains "cwd") { $c.app.PSObject.Properties.Remove("cwd") }
    if ($rol -in @("1", "2")) {
        Write-Host "   Al encender, Windows abre la aplicación por su cuenta; Centinelli espera antes de vigilarla."
        $c.app.espera_arranque = [int](Pregunta "Minutos que tarda en arrancar todo" "10") * 60
    } else {
        $c.app.start_on_boot = SiNo "¿Que Centinelli abra la aplicación al encender? (si Windows ya la abre, responde n)" "s"
    }

    if ($rol -eq "1") {
        Titulo "Sala"
        $c.sala.nombre = Pregunta "Nombre de la sala" $c.sala.nombre
        $c.sala.id = ($c.sala.nombre.ToLower() -replace '[^a-z0-9]+', '-').Trim('-')
        $n = [int](Pregunta "¿Cuántos servers tiene la sala, contando éste?" "5")
        $srv = @(@{ ip = "127.0.0.1"; nombre = "Server 1" })
        for ($i = 2; $i -le $n; $i++) {
            $ip = Pregunta "IP del Server $i" "192.168.1.1$i"
            $srv += @{ ip = $ip; nombre = "Server $i" }
        }
        $c.sala.servidores = $srv
        $m = Pregunta "Minutos de espera máxima si un server no arranca" "15"
        $c.sala.espera_maxima = [int]$m * 60
    }
    if ($rol -eq "2") {
        $sala = Pregunta "Nombre de la sala (igual que en el Server 1)" "MHN · Sala inmersiva"
        $c.sala.id = ($sala.ToLower() -replace '[^a-z0-9]+', '-').Trim('-')
    }
    EscribirJson $cfgPath $c
    Ok "config.json guardado"
}

# ───────────────────────────── firewall (Resolume) ─────────────────────────────
$esResolume = $rol -in @("1", "2", "5") -or ((Get-Content $cfgPath -Raw) -match 'Arena|Avenue')
if ($esResolume) {
    Titulo "Firewall"
    if (SiNo "¿Permitir en la red privada OSC (UDP 7000) y el webserver de Resolume (TCP 8080)?" "s") {
        netsh advfirewall firewall delete rule name="Centinelli - Resolume OSC" | Out-Null
        netsh advfirewall firewall delete rule name="Centinelli - Resolume REST" | Out-Null
        netsh advfirewall firewall add rule name="Centinelli - Resolume OSC" dir=in action=allow protocol=UDP localport=7000 profile=private | Out-Null
        netsh advfirewall firewall add rule name="Centinelli - Resolume REST" dir=in action=allow protocol=TCP localport=8080 profile=private | Out-Null
        Ok "reglas creadas (sólo red privada)"
        $perfil = (Get-NetConnectionProfile | Select-Object -First 1).NetworkCategory
        if ($perfil -ne "Private") {
            Write-Host "   !  La red de esta máquina está como '$perfil'. Cámbiala a Privada en Configuración ▸ Red." -ForegroundColor Yellow
        }
    }
}

# ───────────────────────────── arranque automático ─────────────────────────────
Titulo "Arranque automático"
# Tarea al iniciar sesión, sin ventana (pythonw), sin límite de tiempo (por defecto Windows
# las detiene a los 3 días) y que se reinicia sola si el agente llega a cerrarse.
$pyw = Join-Path $base "python\pythonw.exe"
$accion = New-ScheduledTaskAction -Execute $pyw -Argument "`"$base\centinelli_agente.py`" `"$cfgPath`"" -WorkingDirectory $base
$disparo = New-ScheduledTaskTrigger -AtLogOn -User $env:USERNAME
$quien = New-ScheduledTaskPrincipal -UserId $env:USERNAME -LogonType Interactive -RunLevel Highest
$ajustes = New-ScheduledTaskSettingsSet -ExecutionTimeLimit ([TimeSpan]::Zero) -RestartCount 999 `
    -RestartInterval (New-TimeSpan -Minutes 1) -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable
Register-ScheduledTask -TaskName "Centinelli agente" -Action $accion -Trigger $disparo -Principal $quien `
    -Settings $ajustes -Force | Out-Null
Ok "se abrirá solo (y sin ventana) al iniciar sesión de $env:USERNAME"

# prueba rápida: ¿llega al servidor?
Titulo "Prueba"
$prueba = & (Join-Path $base "python\python.exe") -c @"
import json, urllib.request
c = json.load(open(r'$cfgPath', encoding='utf-8-sig'))
r = urllib.request.Request(c['server'], data=b'{}', headers={'X-Centinelli-Token': c['token'], 'Content-Type': 'application/json'})
try:
    urllib.request.urlopen(r, timeout=10)
except urllib.error.HTTPError as e:
    print({400: 'ok', 403: 'token', 405: 'url'}.get(e.code, 'http %s' % e.code))
except Exception as e:
    print('red: %s' % e)
else:
    print('ok')
"@
switch -Wildcard ($prueba) {
    "ok"    { Ok "el servidor responde y acepta el token" }
    "token" { Write-Host "   X  el servidor rechazó el token: revisa 'token' en $cfgPath" -ForegroundColor Red }
    "url"   { Write-Host "   X  la dirección no parece ser api.php: revisa 'server' en $cfgPath" -ForegroundColor Red }
    default { Write-Host "   X  no se pudo llegar al servidor ($prueba). ¿Hay internet?" -ForegroundColor Red }
}

if (SiNo "¿Arrancar Centinelli ahora?" "s") {
    Get-Process pythonw -ErrorAction SilentlyContinue | Where-Object { $_.Path -like "$base*" } | Stop-Process -Force
    Start-ScheduledTask -TaskName "Centinelli agente"
    Ok "en marcha; en menos de un minuto aparece en el dashboard"
}

Titulo "Listo"
Write-Host "   Carpeta:      $base"
Write-Host "   Configuración: $cfgPath"
Write-Host "   Registro:      $base\centinelli.log"
$srvFinal = (Get-Content $cfgPath -Raw -Encoding UTF8 | ConvertFrom-Json).server
Write-Host "   Dashboard:     $($srvFinal -replace 'api\.php$', '')"
Write-Host ""
Read-Host "   Enter para cerrar"
