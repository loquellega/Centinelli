<?php
// Centinelli · página "instalar": comando listo para pegar en cada máquina (requiere sesión)
require __DIR__ . '/lib.php';
if (!logged_in()) { header('Location: ./'); exit; }

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$base  = ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$api   = $base . '/api.php';
$token = $CFG['agent_token'];
$raw   = 'https://raw.githubusercontent.com/loquellega/centinelli/main/agente/instalar.ps1';
$zip   = 'https://github.com/loquellega/centinelli/archive/refs/heads/main.zip';
$listo = $token && $token !== 'CAMBIA-ESTE-TOKEN';

$cmd = "iwr $raw -UseBasicParsing -OutFile \$env:TEMP\\ci.ps1; powershell -ExecutionPolicy Bypass -File \$env:TEMP\\ci.ps1 -Servidor '$api' -Token '$token'";
$usb = "iwr $raw -UseBasicParsing -OutFile \$env:TEMP\\ci.ps1; powershell -ExecutionPolicy Bypass -File \$env:TEMP\\ci.ps1 -PrepararUSB E:\\centinelli";
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<title>Centinelli · instalar</title>
<style>
:root{--a-bg:#0a0a0a;--a-panel:#101010;--a-bar:#1c1c1c;--a-line:#2e2e2e;--a-line-ctl:#3a3a3a;--a-hover:#1f1f1f;
  --a-ink:#d2d2d2;--a-ink-strong:#fff;--a-ink-dim:#8f8f8f;--a-accent:#76c411;--a-accent-ink:#a5e04a;--a-accent-bg:#1b2512;
  --a-radius:3px;--a-pad:10px;--a-bar-h:24px;--a-ctl-h:32px;--a-t:120ms;--a-font:Inter,Arial,Helvetica,sans-serif;--a-size:12px;--a-size-sm:11px;--c-warn:#e8b23a}
*{box-sizing:border-box}
body{margin:0;background:var(--a-bg);color:var(--a-ink);font:400 13px/1.6 var(--a-font);-webkit-font-smoothing:antialiased}
main{max-width:860px;margin:0 auto;padding:16px max(16px,env(safe-area-inset-right)) 40px max(16px,env(safe-area-inset-left));display:flex;flex-direction:column;gap:12px}
.a-win{border:1px solid var(--a-line);border-radius:var(--a-radius);background:var(--a-panel);overflow:hidden}
.a-tb{display:flex;justify-content:space-between;align-items:center;height:var(--a-bar-h);padding:0 var(--a-pad);background:var(--a-bar);border-bottom:1px solid var(--a-line);font-size:var(--a-size-sm);font-weight:500}
.a-wb{padding:12px 14px;display:flex;flex-direction:column;gap:10px}
h1{font-size:15px;font-weight:600;color:var(--a-ink-strong);margin:4px 0 0;letter-spacing:.02em}
p{margin:0}
.dim{color:var(--a-ink-dim);font-size:var(--a-size-sm)}
ol{margin:0;padding-left:20px;display:flex;flex-direction:column;gap:6px}
code,pre{font-family:ui-monospace,Consolas,Menlo,monospace;font-size:11.5px}
code{color:var(--a-ink-strong);background:var(--a-bg);border:1px solid var(--a-line);border-radius:2px;padding:0 4px}
pre{margin:0;padding:10px;background:var(--a-bg);border:1px solid var(--a-line-ctl);border-radius:var(--a-radius);color:var(--a-ink-strong);white-space:pre-wrap;word-break:break-all;user-select:all}
.fila{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.a-btn{min-height:var(--a-ctl-h);padding:0 12px;background:transparent;color:var(--a-ink);border:1px solid var(--a-line-ctl);border-radius:var(--a-radius);font:inherit;font-size:12px;cursor:pointer;text-decoration:none;display:inline-grid;place-items:center;transition:background var(--a-t),border-color var(--a-t)}
.a-btn:hover{background:var(--a-hover);border-color:#555}
.a-btn.primary{background:var(--a-accent);border-color:var(--a-accent);color:#0a0a0a;font-weight:600}
:focus-visible{outline:2px solid var(--a-accent);outline-offset:2px}
.aviso{color:var(--c-warn)}
table{border-collapse:collapse;width:100%;font-size:12px}
td{border-top:1px solid var(--a-line);padding:6px 4px;vertical-align:top}
td:first-child{color:var(--a-ink-dim);white-space:nowrap;padding-right:14px}
.cred{color:var(--a-ink-dim);font-size:10px;text-align:center;margin-top:8px}
.cred a{color:inherit}
</style>
</head>
<body>
<main>
  <div class="fila" style="justify-content:space-between">
    <h1>centinelli · instalar en una máquina</h1>
    <a class="a-btn" href="./">← dashboard</a>
  </div>

  <?php if (!$listo): ?>
  <p class="aviso">Primero configura <code>agent_token</code> en <code>config.php</code> (usa <code>hash.php</code> para generarlo).</p>
  <?php endif; ?>

  <section class="a-win">
    <div class="a-tb"><span>A · desde internet (recomendado)</span><span>~3 min</span></div>
    <div class="a-wb">
      <ol>
        <li>En la máquina: clic derecho en el botón Inicio ▸ <b>Terminal (Administrador)</b> o <b>Windows PowerShell (Administrador)</b>.</li>
        <li>Pega este comando y pulsa Enter. Ya lleva la dirección de este servidor y el token.</li>
      </ol>
      <pre id="cmd"><?= htmlspecialchars($cmd) ?></pre>
      <div class="fila"><button class="a-btn primary" onclick="copiar('cmd', this)">copiar comando</button>
        <span class="dim">no lo compartas fuera de tu equipo: contiene el token</span></div>
      <ol start="3">
        <li>Responde las preguntas: qué máquina es (Server 1, otro server de la sala, TouchDesigner, Isadora…), su nombre, el programa y, en el Server 1, las IPs de los demás.</li>
        <li>Al terminar aparece en este dashboard en menos de un minuto.</li>
      </ol>
    </div>
  </section>

  <section class="a-win">
    <div class="a-tb"><span>B · desde una USB (sin internet para instalar)</span><span>museos, redes cerradas</span></div>
    <div class="a-wb">
      <p><b>1. Preparar la USB</b>, una sola vez, en cualquier PC con internet. Cambia <code>E:</code> por la letra de tu memoria:</p>
      <pre id="usb"><?= htmlspecialchars($usb) ?></pre>
      <div class="fila"><button class="a-btn" onclick="copiar('usb', this)">copiar comando</button>
        <span class="dim">deja en la USB Python portátil, los paquetes y Centinelli (~40 MB)</span></div>
      <p><b>2. En cada máquina:</b> abre la carpeta <code>centinelli</code> de la USB y haz doble clic en <code>INSTALAR.bat</code>. Te pedirá el token; cópialo de aquí:</p>
      <pre id="tok"><?= htmlspecialchars($token) ?></pre>
      <div class="fila"><button class="a-btn" onclick="copiar('tok', this)">copiar token</button>
        <span class="dim">servidor: <code><?= htmlspecialchars($api) ?></code></span></div>
    </div>
  </section>

  <section class="a-win">
    <div class="a-tb"><span>C · descarga manual</span></div>
    <div class="a-wb">
      <p>Descarga el paquete, descomprime y haz doble clic en <code>agente/INSTALAR.bat</code>. Descarga Python durante la instalación.</p>
      <div class="fila"><a class="a-btn" href="<?= htmlspecialchars($zip) ?>">descargar centinelli.zip</a>
        <a class="a-btn" href="https://github.com/loquellega/centinelli">ver en GitHub</a></div>
    </div>
  </section>

  <section class="a-win">
    <div class="a-tb"><span>qué deja instalado</span></div>
    <div class="a-wb">
      <table>
        <tr><td>Carpeta</td><td><code>C:\centinelli</code> (agente, <code>config.json</code>, registro <code>centinelli.log</code>)</td></tr>
        <tr><td>Python</td><td>portátil, dentro de <code>C:\centinelli\python</code>. No se instala en Windows ni toca otros programas</td></tr>
        <tr><td>Arranque</td><td>tarea «Centinelli agente» al iniciar sesión, sin ventana, con prioridad baja; se reinicia sola si se cierra</td></tr>
        <tr><td>Firewall</td><td>sólo si es Resolume y lo aceptas: UDP 7000 y TCP 8080 en red privada</td></tr>
        <tr><td>Desinstalar</td><td>Programador de tareas ▸ borrar «Centinelli agente» y borrar <code>C:\centinelli</code></td></tr>
      </table>
    </div>
  </section>

  <p class="cred">Diseño, investigación y desarrollo: Héctor Cruz Juárez — <a href="https://hectorcruz.com.mx">hectorcruz.com.mx</a> · Colección Alchemy of Meaning.</p>
</main>
<script>
function copiar(id, b){
  const t = document.getElementById(id).textContent;
  const fin = () => { const o = b.textContent; b.textContent = 'copiado'; setTimeout(() => b.textContent = o, 1500); };
  if (navigator.clipboard) navigator.clipboard.writeText(t).then(fin, () => selec(id));
  else selec(id);
}
function selec(id){ const r = document.createRange(); r.selectNodeContents(document.getElementById(id)); const s = getSelection(); s.removeAllRanges(); s.addRange(r); }
</script>
</body>
</html>
