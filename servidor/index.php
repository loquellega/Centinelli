<?php
// Centinelli · dashboard
// Diseño, investigación y desarrollo: Héctor Cruz Juárez — https://hectorcruz.com.mx
// Colección Alchemy of Meaning.
require __DIR__ . '/lib.php';
start_session();

$err = '';
if (isset($_GET['salir'])) { $_SESSION = []; session_destroy(); header('Location: ./'); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['p'])) {
    if ($CFG['password_hash'] && password_verify($_POST['p'], $CFG['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['ok'] = 1;
        header('Location: ./'); exit;
    }
    sleep(2);
    $err = $CFG['password_hash'] ? 'Contraseña incorrecta' : 'Falta configurar la contraseña (hash.php)';
}
$ok = logged_in();
const VERSION = '0.5';
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0a0a0a">
<meta name="robots" content="noindex">
<title>Centinelli</title>
<style>
/* ── Alchemy of Meaning · UI v1 · tokens ── */
:root{
  --a-bg:#0a0a0a; --a-panel:#101010; --a-bar:#1c1c1c; --a-line:#2e2e2e; --a-line-ctl:#3a3a3a; --a-hover:#1f1f1f;
  --a-ink:#d2d2d2; --a-ink-strong:#fff; --a-ink-dim:#8f8f8f;
  --a-accent:#76c411; --a-accent-ink:#a5e04a; --a-accent-bg:#1b2512;
  --a-radius:3px; --a-radius-sm:2px; --a-gap:8px; --a-pad:10px; --a-bar-h:24px; --a-ctl-h:32px; --a-t:120ms;
  --a-font:Inter,Arial,Helvetica,sans-serif; --a-size:12px; --a-size-sm:11px;
  /* propio de Centinelli: sólo para fallas (nunca decoración) */
  --c-fail:#ff6a57; --c-fail-bg:#2a1311; --c-warn:#e8b23a;
}
:root[data-contrast="alto"]{
  --a-bg:#000;--a-panel:#000;--a-bar:#fff;--a-line:#fff;--a-line-ctl:#fff;--a-hover:#262626;--a-ink:#fff;--a-ink-dim:#a8a8a8;
}
:root[data-contrast="alto"] .a-tb{color:#000}
:root[data-contrast="alto"] .a-tb .st{color:#000}

/* ── componentes ── */
*{box-sizing:border-box}
html,body{margin:0;min-height:100%}
.a-ui{font:400 var(--a-size)/1.5 var(--a-font);color:var(--a-ink);background:var(--a-bg);
  font-variant-numeric:tabular-nums;-webkit-font-smoothing:antialiased}
.a-win{border:1px solid var(--a-line);border-radius:var(--a-radius);background:var(--a-panel);overflow:hidden;
  transition:border-color var(--a-t)}
.a-tb{display:flex;justify-content:space-between;align-items:center;gap:var(--a-gap);height:var(--a-bar-h);padding:0 var(--a-pad);
  background:var(--a-bar);border-bottom:1px solid var(--a-line);color:var(--a-ink);
  font-size:var(--a-size-sm);font-weight:500;letter-spacing:.02em;user-select:none}
.a-wb{padding:var(--a-pad);display:flex;flex-direction:column;gap:var(--a-pad)}
.a-row{display:flex;justify-content:space-between;align-items:center;gap:var(--a-gap)}
.a-lbl{color:var(--a-ink-dim);font-size:var(--a-size-sm)}
.a-val{color:var(--a-ink-strong)}
.a-btn{min-height:var(--a-ctl-h);padding:0 12px;background:transparent;color:var(--a-ink);
  border:1px solid var(--a-line-ctl);border-radius:var(--a-radius);font:inherit;cursor:pointer;white-space:nowrap;
  transition:background var(--a-t),border-color var(--a-t),color var(--a-t)}
.a-btn:hover{background:var(--a-hover);border-color:#555}
.a-btn[aria-pressed="true"]{background:var(--a-accent-bg);border-color:var(--a-accent);color:var(--a-accent-ink)}
.a-btn.primary{background:var(--a-accent);border-color:var(--a-accent);color:#0a0a0a;font-weight:600}
.a-btn.fail{color:var(--c-fail);border-color:#5a2a25}
.a-btn.fail:hover{background:var(--c-fail-bg)}
:focus-visible{outline:2px solid var(--a-accent);outline-offset:2px}
.a-meter{display:block;width:100%;height:3px;border-radius:2px;background:var(--a-line);position:relative;overflow:hidden}
.a-meter>i{position:absolute;inset:0 auto 0 0;width:calc(var(--v,0)*100%);background:var(--a-accent);transition:width .6s}
.a-meter.hot>i{background:var(--c-warn)}
.a-scroll{scrollbar-width:thin;scrollbar-color:var(--a-line-ctl) transparent}
.a-scroll::-webkit-scrollbar{width:6px}.a-scroll::-webkit-scrollbar-thumb{background:var(--a-line-ctl);border-radius:3px}
.a-input{min-height:var(--a-ctl-h);background:var(--a-bg);color:var(--a-ink-strong);border:1px solid var(--a-line-ctl);
  border-radius:var(--a-radius);padding:0 9px;font:inherit;min-width:0;transition:border-color var(--a-t)}
.a-input:focus{border-color:#666;outline:none}

/* ── maqueta ── */
main{padding:var(--a-pad) max(var(--a-pad),env(safe-area-inset-right)) calc(56px + env(safe-area-inset-bottom)) max(var(--a-pad),env(safe-area-inset-left));
  display:grid;gap:var(--a-pad);grid-template-columns:repeat(auto-fill,minmax(340px,1fr));align-items:start}
@media (max-width:420px){main{grid-template-columns:1fr}.dock .marca{display:none}.dock .sum{white-space:normal;line-height:1.35}}

/* barra de título de cada instalación */
.tb-name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--a-ink-strong)}
.st{display:flex;align-items:center;gap:6px;color:var(--a-ink-dim);flex:none}
.dot{width:7px;height:7px;border-radius:50%;background:var(--a-ink-dim)}
.dot.vivo{background:var(--a-accent)}
.dot.aviso{background:var(--c-warn)}
.dot.falla{background:var(--c-fail);animation:lat 1.1s steps(2) infinite}
@keyframes lat{50%{opacity:.25}}
.a-win.falla{border-color:#6b2c25}
.a-win.falla .a-tb{border-bottom-color:#6b2c25}

/* visor: miniatura + marco de esquinas con cifras + retícula */
.visor{position:relative;aspect-ratio:16/9;background:#000 center/contain no-repeat;border-bottom:1px solid var(--a-line)}
.visor .ret{position:absolute;inset:0;pointer-events:none;mix-blend-mode:difference}
.visor .ret::before,.visor .ret::after{content:"";position:absolute;background:#fff;opacity:.55}
.visor .ret::before{left:50%;top:calc(50% - 7px);width:1px;height:15px}
.visor .ret::after{top:50%;left:calc(50% - 7px);height:1px;width:15px}
.visor .esq{position:absolute;inset:6px;pointer-events:none}
.visor .esq span{position:absolute;width:10px;height:10px;border-color:#fff;border-style:solid;border-width:0;opacity:.7;mix-blend-mode:difference}
.visor .esq .tl{left:0;top:0;border-left-width:1px;border-top-width:1px}
.visor .esq .tr{right:0;top:0;border-right-width:1px;border-top-width:1px}
.visor .esq .bl{left:0;bottom:0;border-left-width:1px;border-bottom-width:1px}
.visor .esq .br{right:0;bottom:0;border-right-width:1px;border-bottom-width:1px}
.visor .cif{position:absolute;font-size:10px;line-height:1;color:#fff;mix-blend-mode:difference;letter-spacing:.03em}
.visor .c-tl{left:20px;top:8px}.visor .c-tr{right:20px;top:8px;text-align:right}
.visor .c-bl{left:20px;bottom:8px}.visor .c-br{right:20px;bottom:8px;text-align:right}
.visor .vacio{position:absolute;inset:auto 0 30%;display:grid;place-items:center;color:var(--a-ink-dim);font-size:var(--a-size-sm)}

.fallas{display:flex;flex-direction:column;gap:2px;color:var(--c-fail)}
.fallas div::before{content:"▲ ";font-size:9px;vertical-align:1px}
.lect{display:grid;grid-template-columns:repeat(4,1fr);gap:10px 12px}
.lect .m{display:flex;flex-direction:column;gap:3px}
.lect .m .a-row{align-items:baseline}
.lect .a-val{white-space:nowrap}
.vals{display:flex;flex-wrap:wrap;gap:3px 14px}
.meta{color:var(--a-ink-dim);font-size:var(--a-size-sm)}
.btns{display:flex;flex-wrap:wrap;gap:6px}
.cues{display:grid;grid-template-columns:repeat(auto-fill,minmax(96px,1fr));gap:6px;padding-bottom:var(--a-pad);border-bottom:1px solid var(--a-line)}
.cue{color:var(--a-ink-strong);border-color:#555}
.osc{display:flex;gap:6px}
.osc .a-input:first-child{flex:1.3}.osc .a-input:nth-child(2){flex:1}
.bit summary{cursor:pointer;color:var(--a-ink-dim);font-size:var(--a-size-sm);list-style:none;display:flex;justify-content:space-between}
.bit summary::-webkit-details-marker{display:none}
.bit summary::after{content:"+"}.bit[open] summary::after{content:"−"}
.log{max-height:200px;overflow:auto;margin-top:6px;font-size:var(--a-size-sm);line-height:1.6;border-top:1px solid var(--a-line);padding-top:6px}
.log time{color:var(--a-ink-dim);margin-right:8px}
.log .l-warn{color:var(--c-warn)}.log .l-error,.log .l-alerta{color:var(--c-fail)}
.log .l-dash{color:var(--a-ink-strong)}.log .l-info,.log .l-app{color:var(--a-ink)}

/* sala */
.sala{grid-column:1/-1}
.chips{display:flex;flex-wrap:wrap;gap:6px}
.chip{display:inline-flex;align-items:center;gap:7px;padding:5px 9px;border:1px solid var(--a-line-ctl);border-radius:var(--a-radius-sm);background:var(--a-bg)}
.sala-info{flex-wrap:wrap}
.desf{color:var(--c-warn)}
.sala-log{border-top:1px solid var(--a-line);max-height:none}
.sala-log .a-lbl{margin-right:6px}
.sep{grid-column:1/-1;color:var(--a-ink-dim);font-size:var(--a-size-sm);padding:6px 2px 0;border-top:1px solid var(--a-line)}

/* modo mural (h): sólo visor y estado */
body.mural .ctl{display:none}

/* dock inferior */
.dock{position:fixed;left:0;right:0;bottom:0;display:flex;align-items:center;gap:var(--a-gap);
  min-height:40px;padding:6px max(var(--a-pad),env(safe-area-inset-right)) calc(6px + env(safe-area-inset-bottom)) max(var(--a-pad),env(safe-area-inset-left));
  background:var(--a-panel);border-top:1px solid var(--a-line);z-index:5}
.dock .marca{font-weight:600;color:var(--a-ink-strong);letter-spacing:.04em}
.dock .sum{flex:1;color:var(--a-ink-dim);font-size:var(--a-size-sm);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.dock .sum b{font-weight:500;color:var(--a-ink-strong)}
.dock .sum b.f{color:var(--c-fail)}
.dock .a-btn{min-height:26px;padding:0 9px;font-size:var(--a-size-sm)}
.vacio-g{grid-column:1/-1;color:var(--a-ink-dim);text-align:center;padding:16vh 20px}

#toast{position:fixed;left:50%;bottom:calc(52px + env(safe-area-inset-bottom));transform:translateX(-50%);background:var(--a-bar);
  border:1px solid var(--a-line-ctl);border-radius:var(--a-radius);padding:6px 12px;font-size:var(--a-size-sm);opacity:0;transition:opacity var(--a-t);pointer-events:none;z-index:6}
#toast.on{opacity:1}

/* acceso */
.acceso{min-height:100vh;display:grid;place-items:center;padding:20px}
.acceso .a-win{width:min(320px,100%)}
.acceso .a-input{width:100%}
.acceso .err{color:var(--c-fail);font-size:var(--a-size-sm)}
.cred{color:var(--a-ink-dim);font-size:10px;line-height:1.5;text-align:center;margin-top:14px}
.cred a{color:inherit}
</style>
</head>
<body class="a-ui">
<?php if (!$ok): ?>
<div class="acceso">
  <div>
    <form class="a-win" method="post">
      <div class="a-tb"><span>centinelli</span><span>v<?= VERSION ?></span></div>
      <div class="a-wb">
        <span class="a-lbl">Contraseña</span>
        <input class="a-input" type="password" name="p" autofocus autocomplete="current-password" aria-label="Contraseña">
        <button class="a-btn primary">Entrar</button>
        <?php if ($err): ?><span class="err"><?= htmlspecialchars($err) ?></span><?php endif; ?>
      </div>
    </form>
    <p class="cred">Diseño, investigación y desarrollo: Héctor Cruz Juárez — <a href="https://hectorcruz.com.mx">hectorcruz.com.mx</a><br>Colección Alchemy of Meaning.</p>
  </div>
</div>
<?php else: ?>
<main id="grid"><div class="vacio-g">cargando…</div></main>
<nav class="dock" aria-label="Centinelli">
  <span class="marca">centinelli</span>
  <span class="sum" id="sum" aria-live="polite">…</span>
  <button class="a-btn" id="b-mural" aria-pressed="false" title="Modo mural: sólo visores (tecla h)">mural</button>
  <button class="a-btn" id="b-alto" aria-pressed="false" title="Contraste alto (tecla c)">alto</button>
  <a class="a-btn" href="?salir" style="display:inline-grid;place-items:center;text-decoration:none">salir</a>
</nav>
<div id="toast" role="status"></div>
<script>
const CSRF = <?= json_encode(csrf()) ?>;
// clase del punto, rótulo
const ESTADOS = {
  ok:['vivo','en marcha'], iniciando:['aviso','iniciando'], sin_latido:['falla','sin latido'],
  caida:['falla','app caída'], pausada:['','detenida'], offline:['falla','sin conexión']
};
let serverNow = 0, data = [], salas = [];
const $ = s => document.querySelector(s);
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const hace = t => { if(!t) return '—'; const s = Math.max(0, serverNow - t);
  if (s < 60) return s + ' s'; if (s < 3600) return Math.floor(s/60) + ' min';
  if (s < 86400) return Math.floor(s/3600) + ' h'; return Math.floor(s/86400) + ' d'; };
const hora = t => new Date(t*1000).toLocaleString('es-MX',{day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit',second:'2-digit'});
const n = (v, u='') => v === undefined || v === null ? '—' : (Math.round(v*10)/10) + u;

function toast(msg){ const t=$('#toast'); t.textContent=msg; t.classList.add('on'); clearTimeout(t._h); t._h=setTimeout(()=>t.classList.remove('on'),2400); }

async function load(){
  try{
    const r = await fetch('state.php', {cache:'no-store'});
    if (r.status === 401) { location.reload(); return; }
    const j = await r.json(); serverNow = j.now; data = j.items; salas = j.salas || []; render();
  }catch(e){ $('#sum').textContent = 'sin conexión con el servidor'; }
}

function medidor(lbl, v, max=100, umbral=null, u='%'){
  const val = v == null ? null : Math.max(0, Math.min(1, v/max));
  const hot = umbral != null && v != null && v >= umbral;
  return `<div class="m"><div class="a-row"><span class="a-lbl">${lbl}</span><span class="a-val">${n(v, u)}</span></div>
    <span class="a-meter${hot?' hot':''}" role="meter" aria-label="${lbl}" aria-valuemin="0" aria-valuemax="${max}" aria-valuenow="${v??0}"><i style="--v:${val??0}"></i></span></div>`;
}

function tarjeta(it){
  const p = it.problems || {};
  const key = p.offline ? 'offline' : (it.paused && it.state==='pausada' ? 'pausada' : it.state);
  const [cls, label] = ESTADOS[key] || ['', key || '—'];
  const m = it.metrics || {}, v = Object.assign({}, it.values || {});
  const fps = v.fps; delete v.fps;
  const falla = Object.keys(p).length > 0;
  const abierto = document.querySelector(`details[data-id="${it.id}"]`)?.open ? 'open' : '';
  const oscA = document.querySelector(`#oa-${it.id}`)?.value ?? '/centinelli/cmd/escena';
  const oscV = document.querySelector(`#ov-${it.id}`)?.value ?? '';
  const temp = m.gpu_temp ?? m.cpu_temp;
  const id = esc(it.id);
  return `<section class="a-win${falla?' falla':''}" aria-label="${esc(it.name||it.id)}">
    <div class="a-tb"><span class="tb-name">${esc(it.name||it.id)}</span><span class="st"><span class="dot ${cls}"></span>${label}</span></div>
    <div class="visor" style="${it.has_thumb?`background-image:url('thumb.php?id=${encodeURIComponent(it.id)}&t=${it.thumb_t||0}')`:''}">
      ${it.has_thumb?'':'<div class="vacio">sin captura</div>'}
      <div class="ret"></div>
      <div class="esq"><span class="tl"></span><span class="tr"></span><span class="bl"></span><span class="br"></span></div>
      <span class="cif c-tl">${fps!=null? n(fps)+' fps' : (it.beat_age!=null && !p.offline ? 'latido '+n(it.beat_age,' s') : '—')}</span>
      <span class="cif c-tr">${temp!=null? n(temp,' °C') : ''}</span>
      <span class="cif c-bl">${esc(it.host||'')}</span>
      <span class="cif c-br">${it.has_thumb? 'captura ' + hace(it.thumb_t) : ''}</span>
    </div>
    <div class="a-wb">
      ${falla ? `<div class="fallas">${Object.values(p).map(x=>`<div>${esc(x)}</div>`).join('')}</div>`:''}
      <div class="lect">
        ${medidor('cpu', m.cpu, 100, 90)}${medidor('gpu', m.gpu, 100, 95)}${medidor('ram', m.ram, 100, 90)}${medidor('vram', m.vram, 100, 90)}
        ${medidor('disco', m.disk, 100, 90)}${medidor('temp', temp, 100, 85, '°')}
        <div class="m"><div class="a-row"><span class="a-lbl">app</span><span class="a-val">${m.app_uptime_min!=null ? (m.app_uptime_min<120? m.app_uptime_min+' min' : Math.round(m.app_uptime_min/60)+' h') : '—'}</span></div></div>
        <div class="m"><div class="a-row"><span class="a-lbl">reporte</span><span class="a-val">${hace(it.last_seen)}</span></div></div>
      </div>
      ${Object.keys(v).length ? `<div class="vals">${Object.entries(v).map(([k,x])=>`<span><span class="a-lbl">${esc(k)}</span> <span class="a-val">${esc(Array.isArray(x)?x.join(' '):x)}</span></span>`).join('')}</div>`:''}
      <div class="meta">${esc(it.os||'')}${it.agent? ' · agente '+esc(it.agent):''}</div>
      ${(it.buttons||[]).length ? `<div class="cues ctl">${it.buttons.map((b,i)=>`<button class="a-btn cue" onclick="boton('${id}',${i},${b.confirm?1:0},this)">${esc(b.label)}</button>`).join('')}</div>`:''}
      <div class="btns ctl">
        <button class="a-btn" onclick="cmd('${id}','restart_app')">reiniciar app</button>
        ${it.paused ? `<button class="a-btn primary" onclick="cmd('${id}','start_app')">iniciar</button>` : `<button class="a-btn" onclick="cmd('${id}','stop_app','¿Detener la app? El reinicio automático queda en pausa.')">detener</button>`}
        <button class="a-btn" onclick="cmd('${id}','screenshot')">captura</button>
        ${it.allow_reboot ? `<button class="a-btn fail" onclick="cmd('${id}','reboot','¿Reiniciar la COMPUTADORA de «${esc(it.name||it.id)}»?')">reiniciar pc</button>` : ''}
      </div>
      <div class="osc ctl">
        <input class="a-input" id="oa-${id}" value="${esc(oscA)}" placeholder="/direccion/osc" spellcheck="false" aria-label="Dirección OSC">
        <input class="a-input" id="ov-${id}" value="${esc(oscV)}" placeholder="valores" spellcheck="false" aria-label="Valores OSC">
        <button class="a-btn" onclick="osc('${id}')">osc</button>
      </div>
      <details class="bit ctl" data-id="${id}" ${abierto}><summary>bitácora · comandos</summary>
        <div class="log a-scroll">
          ${(it.cmds||[]).map(c=>`<div class="l-dash"><time>${hora(c.created)}</time>${esc(c.type==='boton' ? '«'+(c.args&&c.args.label||'')+'»' : c.type)} → ${esc(c.status)}${c.result? ' ('+esc(c.result)+')':''}</div>`).join('')}
          ${(it.events||[]).map(e=>`<div class="l-${esc(e.level)}"><time>${hora(e.t)}</time>${esc(e.msg)}</div>`).join('')}
        </div>
      </details>
    </div>
  </section>`;
}

function panelRemotos(s, coord){
  // Sala vigilada desde una sola máquina (Server 1): los demás no tienen agente
  const off = coord && (coord.problems||{}).offline;
  const rs = s.remotos;
  const ok = rs.filter(r => r.estado === 'ok' && !off);
  const cols = {};
  ok.forEach(r => { (cols[r.col] ??= []).push(r.nombre); });
  const ck = Object.keys(cols);
  let colTxt = '';
  if (ck.length === 1) colTxt = `<span class="a-lbl">columna</span> <span class="a-val">${esc(ck[0])}</span> <span class="a-lbl">en todos</span>`;
  else if (ck.length > 1) {
    const mayor = ck.sort((a,b) => cols[b].length - cols[a].length)[0];
    colTxt = `<span class="desf">▲ desfase: ${ck.filter(c => c !== mayor).map(c => `${cols[c].map(esc).join(', ')} en ${esc(c)}`).join(' · ')} (el resto en columna ${esc(mayor)})</span>`;
  }
  const arrancando = rs.some(r => r.estado === 'arrancando') && !off;
  const caidos = rs.filter(r => r.estado === 'caido').length;
  const sev = off || caidos ? 'falla' : (ck.length > 1 || arrancando ? 'aviso' : 'vivo');
  const lbl = off ? 'sin conexión con el server que vigila' : `${ok.length}/${rs.length} responden${arrancando ? ' · esperando arranque' : ''}${ck.length > 1 ? ' · desfase' : ''}`;
  return {sev, lbl, colTxt, chips: rs.map(r => {
    const [cl, txt] = off ? ['', 'sin datos'] : r.estado === 'ok' ? ['vivo', 'responde'] : r.estado === 'arrancando' ? ['aviso', 'arrancando'] : ['falla', 'no responde'];
    return `<span class="chip"><span class="dot ${cl}"></span><span class="a-val">${esc(r.nombre)}</span>
      <span class="a-lbl">${txt}${r.estado==='ok' && !off ? ' · col '+esc(r.col) : ''} · ${esc(r.ip)}</span></span>`; }).join('')};
}

function panelSala(s){
  const miembros = s.miembros.map(id => data.find(d => d.id === id)).filter(Boolean);
  const vivos = miembros.filter(m => !Object.keys(m.problems||{}).length && m.state === 'ok');
  const enLinea = miembros.filter(m => !(m.problems||{}).offline);
  const todos = vivos.length === miembros.length;
  // columnas activas (Resolume) de los que están en línea
  const cols = {};
  enLinea.forEach(m => { const c = (m.values||{}).columna; if (c !== undefined) (cols[c] ??= []).push(m.name || m.id); });
  const ck = Object.keys(cols);
  let colTxt = '';
  if (ck.length === 1) colTxt = `<span class="a-lbl">columna</span> <span class="a-val">${esc(ck[0])}</span> <span class="a-lbl">en todos</span>`;
  else if (ck.length > 1) {
    const mayor = ck.sort((a,b) => cols[b].length - cols[a].length)[0];
    const fuera = ck.filter(c => c !== mayor).map(c => `${cols[c].map(esc).join(', ')} en ${esc(c)}`).join(' · ');
    colTxt = `<span class="desf">▲ desfase: ${fuera} (el resto en columna ${esc(mayor)})</span>`;
  }
  const desfase = ck.length > 1;
  const syncs = miembros.map(m => (m.values||{}).sync).filter(Boolean).sort();
  const ev = miembros.flatMap(m => (m.events||[]).filter(e => /^Sala:/.test(e.msg)).map(e => ({...e, quien: m.name || m.id})))
    .sort((a,b) => b.t - a.t).slice(0, 6);
  const sid = esc(s.id);
  const R = s.remotos ? panelRemotos(s, data.find(d => d.id === s.coordinador)) : null;
  const sev = R ? R.sev : (!todos ? 'falla' : (desfase ? 'aviso' : 'vivo'));
  return `<section class="a-win sala${sev==='falla'?' falla':''}" aria-label="Sala ${esc(s.nombre)}">
    <div class="a-tb"><span class="tb-name">sala · ${esc(s.nombre)}</span>
      <span class="st"><span class="dot ${sev}"></span>${R ? R.lbl : `${vivos.length}/${miembros.length} en marcha${desfase?' · desfase':''}`}</span></div>
    <div class="a-wb">
      <div class="chips">${R ? R.chips : miembros.map(m => {
        const p = m.problems||{}; const k = p.offline ? 'offline' : m.state; const [cl, lb] = ESTADOS[k] || ['', k];
        const c = (m.values||{}).columna;
        return `<span class="chip"><span class="dot ${cl}"></span><span class="a-val">${esc(m.name||m.id)}</span>
          <span class="a-lbl">${lb}${c!==undefined && !p.offline ? ' · col '+esc(c) : ''}${m.id===s.coordinador?' · coordina':''}</span></span>`; }).join('')}</div>
      <div class="a-row sala-info"><span>${R ? R.colTxt : colTxt}</span>
        <span><span class="a-lbl">última sync</span> <span class="a-val">${syncs.length ? esc(syncs[syncs.length-1]) : '—'}</span>
        ${(s.horarios||[]).length ? ` · <span class="a-lbl">programada</span> <span class="a-val">${s.horarios.map(esc).join(' · ')}</span>` : ''}</span></div>
      <div class="btns">
        <button class="a-btn primary" onclick="salaCmd('${sid}','sala_sync',null,'¿Sincronizar los ${R ? s.remotos.length : miembros.length} servidores de «${esc(s.nombre)}»? Todas las pantallas vuelven a empezar.')">sincronizar sala</button>
        ${(s.botones||[]).map((b,i) => `<button class="a-btn cue" onclick="salaCmd('${sid}','sala_boton',${i},'¿Mandar «${esc(b)}» a toda la sala?')">${esc(b)}</button>`).join('')}
      </div>
      ${ev.length ? `<div class="log sala-log">${ev.map(e => `<div class="l-${esc(e.level)}"><time>${hora(e.t)}</time><span class="a-lbl">${esc(e.quien)}</span> ${esc(e.msg.replace(/^Sala: /,''))}</div>`).join('')}</div>` : ''}
    </div>
  </section>`;
}

function render(){
  if (document.activeElement && document.activeElement.tagName === 'INPUT') return;
  const g = $('#grid');
  if (!data.length) { g.innerHTML = '<div class="vacio-g">Ninguna instalación ha reportado todavía.<br>Arranca el agente en una máquina.</div>'; }
  else {
    const usados = new Set();
    let html = '';
    salas.forEach(s => { html += panelSala(s); s.miembros.forEach(id => { const it = data.find(d => d.id === id); if (it) { html += tarjeta(it); usados.add(id); } }); });
    const sueltos = data.filter(d => !usados.has(d.id));
    if (sueltos.length && salas.length) html += '<div class="sep">otras instalaciones</div>';
    html += sueltos.map(tarjeta).join('');
    g.innerHTML = html;
  }
  const mal = data.filter(i => Object.keys(i.problems||{}).length).length;
  $('#sum').innerHTML = `<b>${data.length}</b> instalaci${data.length===1?'ón':'ones'} · ${mal ? `<b class="f">${mal} con fallas</b>` : 'todo en marcha'} · ${new Date().toLocaleTimeString('es-MX')}`;
  document.title = mal ? `(${mal}) Centinelli` : 'Centinelli';
}

async function send(body){
  const r = await fetch('cmd.php', {method:'POST', headers:{'Content-Type':'application/json','X-CSRF':CSRF}, body:JSON.stringify(body)});
  const j = await r.json().catch(()=>({}));
  if (j.ok) { toast('Enviado. La máquina lo recibe en su próximo reporte (≤10 s).'); setTimeout(load, 1500); }
  else toast('Error: ' + (j.error || r.status));
}
async function salaCmd(sala, type, i, aviso){
  if (aviso && !confirm(aviso)) return;
  const r = await fetch('cmd.php', {method:'POST', headers:{'Content-Type':'application/json','X-CSRF':CSRF}, body:JSON.stringify({sala, type, i})});
  const j = await r.json().catch(()=>({}));
  if (j.ok) { toast('Encargado a «' + j.nombre + '». Se ejecuta en su próximo reporte (≤10 s).'); setTimeout(load, 1500); }
  else toast('Error: ' + (j.error || r.status));
}
function cmd(id, type, aviso){ if (aviso && !confirm(aviso)) return; send({id, type}); }
function boton(id, i, conf, el){ if (conf && !confirm('¿Mandar «' + el.textContent + '»?')) return; send({id, type:'boton', i}); }
function osc(id){ send({id, type:'osc', address:$('#oa-'+id).value.trim(), values:$('#ov-'+id).value}); }

// preferencias de vista (por navegador)
const pref = (k, v) => { try { if (v === undefined) return localStorage.getItem('centinelli.'+k); localStorage.setItem('centinelli.'+k, v); } catch(e){} };
function setMural(on){ document.body.classList.toggle('mural', on); $('#b-mural').setAttribute('aria-pressed', on); pref('mural', on?1:0); }
function setAlto(on){ on ? document.documentElement.dataset.contrast='alto' : delete document.documentElement.dataset.contrast; $('#b-alto').setAttribute('aria-pressed', on); pref('alto', on?1:0); }
$('#b-mural').onclick = () => setMural(!document.body.classList.contains('mural'));
$('#b-alto').onclick = () => setAlto(!document.documentElement.dataset.contrast);
setMural(pref('mural') === '1'); setAlto(pref('alto') === '1');
document.addEventListener('keydown', e => {
  if (e.target.tagName === 'INPUT' || e.metaKey || e.ctrlKey || e.altKey) return;
  if (e.key === 'h') setMural(!document.body.classList.contains('mural'));
  if (e.key === 'c') setAlto(!document.documentElement.dataset.contrast);
  if (e.key === 'r') load();
});

load(); setInterval(load, 8000);
document.addEventListener('visibilitychange', () => { if (!document.hidden) load(); });
</script>
<?php endif; ?>
</body>
</html>
