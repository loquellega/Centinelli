<?php
// Centinelli · funciones comunes
if (!is_file(__DIR__ . '/config.php')) { http_response_code(500); exit('Centinelli: copia config.example.php como config.php y edítalo.'); }
$CFG = require __DIR__ . '/config.php';
date_default_timezone_set($CFG['timezone'] ?? 'UTC');
define('DATA', __DIR__ . '/data');
if (!is_dir(DATA)) { @mkdir(DATA, 0750, true); }

function valid_id($id) { return is_string($id) && preg_match('/^[A-Za-z0-9_-]{1,40}$/', $id); }
function inst_dir($id) { $d = DATA . '/' . $id; if (!is_dir($d)) @mkdir($d, 0750, true); return $d; }

function jread($file, $default = []) {
    if (!is_file($file)) return $default;
    $j = json_decode((string)@file_get_contents($file), true);
    return is_array($j) ? $j : $default;
}
function jwrite($file, $data) {
    $tmp = $file . '.tmp' . getmypid();
    file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    rename($tmp, $file);
}
// Lectura-modificación-escritura con candado (para la cola de comandos)
function jupdate($file, callable $fn, $default = []) {
    $fh = fopen($file . '.lock', 'c');
    flock($fh, LOCK_EX);
    $data = $fn(jread($file, $default));
    jwrite($file, $data);
    flock($fh, LOCK_UN); fclose($fh);
    return $data;
}

function list_ids() {
    $out = [];
    foreach (glob(DATA . '/*', GLOB_ONLYDIR) ?: [] as $d) {
        $id = basename($d);
        if (valid_id($id) && is_file("$d/state.json")) $out[] = $id;
    }
    sort($out);
    return $out;
}

function json_out($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── sesión del dashboard ───
function start_session() {
    if (session_status() === PHP_SESSION_NONE) {
        session_name('centinelli');
        session_set_cookie_params(['lifetime' => 60 * 60 * 24 * 30, 'httponly' => true,
            'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
        session_start();
    }
}
function logged_in() { start_session(); return !empty($_SESSION['ok']); }
function require_login() { if (!logged_in()) json_out(['error' => 'login'], 401); }
function csrf() { start_session(); if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function require_csrf() {
    $h = $_SERVER['HTTP_X_CSRF'] ?? '';
    if (!hash_equals(csrf(), $h)) json_out(['error' => 'csrf'], 403);
}

// ─── evaluación de problemas y alertas ───
function problems($st, $now = null) {
    global $CFG;
    $now = $now ?? time();
    $p = [];
    $interval = (int)($st['interval'] ?? 30);
    $limit = max($CFG['offline_after'], $interval * 3);
    if ($now - ($st['last_seen'] ?? 0) > $limit) { $p['offline'] = 'Sin conexión con la máquina'; return $p; }
    $s = $st['state'] ?? '';
    if ($s === 'caida')      $p['caida'] = 'La app no está corriendo';
    if ($s === 'sin_latido') $p['sin_latido'] = 'La app no manda latido (¿congelada?)';
    $m = $st['metrics'] ?? [];
    if (($m['gpu_temp'] ?? 0) > $CFG['gpu_temp_max']) $p['gpu_temp'] = 'GPU caliente: ' . $m['gpu_temp'] . ' °C';
    if (($m['cpu_temp'] ?? 0) > $CFG['cpu_temp_max']) $p['cpu_temp'] = 'CPU caliente: ' . $m['cpu_temp'] . ' °C';
    if (($m['disk'] ?? 0) > $CFG['disk_max'])         $p['disk'] = 'Disco casi lleno: ' . $m['disk'] . ' %';
    return $p;
}

// Un problema sólo se avisa si dura más de 'alert_delay' s (evita avisos por un reinicio de 10 s).
// $agent_errors: eventos de nivel "error" que mandó el agente (p.ej. demasiados reinicios) → aviso inmediato.
function check_alerts($id, $st, $agent_errors = []) {
    global $CFG;
    $file = inst_dir($id) . '/alerts.json';
    $a = jread($file, ['active' => [], 'pending' => []]);
    $a += ['active' => [], 'pending' => []];
    $probs = problems($st);
    $t = time();
    $delay = (int)($CFG['alert_delay'] ?? 45);
    $name = $st['name'] ?? $id;
    $msgs = [];
    foreach ($probs as $k => $txt) {
        if (isset($a['active'][$k])) continue;
        $since = $a['pending'][$k] ?? $t;
        if ($k === 'offline' || $t - $since >= $delay) {
            $a['active'][$k] = $txt; unset($a['pending'][$k]);
            $msgs[] = "🔴 $name — $txt";
        } else {
            $a['pending'][$k] = $since;
        }
    }
    foreach (array_keys($a['pending']) as $k) if (!isset($probs[$k])) unset($a['pending'][$k]);
    foreach ($a['active'] as $k => $txt) {
        if (!isset($probs[$k])) { unset($a['active'][$k]); $msgs[] = "🟢 $name — resuelto: $txt"; }
    }
    foreach ($agent_errors as $e) $msgs[] = "⚠️ $name — $e";
    jwrite($file, $a);
    if ($msgs) {
        notify(implode("\n", $msgs));
        log_event($id, ['t' => $t, 'level' => 'alerta', 'msg' => implode(' · ', $msgs)]);
    }
    return $a['active'];
}

function notify($text) {
    global $CFG;
    if ($CFG['telegram_token'] && $CFG['telegram_chat']) {
        $url = 'https://api.telegram.org/bot' . $CFG['telegram_token'] . '/sendMessage';
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 8,
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query(['chat_id' => $CFG['telegram_chat'], 'text' => "Centinelli\n" . $text])]]);
        @file_get_contents($url, false, $ctx);
    }
    if ($CFG['alert_email']) {
        @mail($CFG['alert_email'], '=?UTF-8?B?' . base64_encode('Centinelli: alerta') . '?=', $text,
              "Content-Type: text/plain; charset=UTF-8");
    }
}

function log_event($id, $e) {
    jupdate(inst_dir($id) . '/events.json', function ($ev) use ($e) {
        $ev[] = $e;
        return array_slice($ev, -300);
    });
}
