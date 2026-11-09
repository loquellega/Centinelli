<?php
// Centinelli · encola un comando para una instalación o para una sala (desde el dashboard)
require __DIR__ . '/lib.php';
require_login();
require_csrf();

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$type = $in['type'] ?? '';

// ── comandos de sala: se encargan a un agente de la sala que esté en línea ──
if ($type === 'sala_sync' || $type === 'sala_boton') {
    $sala = (string)($in['sala'] ?? '');
    $id = pick_sala_agent($sala);
    if (!$id) json_out(['error' => 'ningún servidor de la sala está en línea'], 409);
    $args = [];
    $label = 'Sincronizar sala';
    if ($type === 'sala_boton') {
        $i = (int)($in['i'] ?? -1);
        $st = jread(DATA . "/$id/state.json");
        $bot = $st['sala']['botones'] ?? [];
        if ($i < 0 || $i >= count($bot)) json_out(['error' => 'botón'], 400);
        $label = (string)$bot[$i];
        $args = ['i' => $i, 'label' => $label];
    }
    $cmd = queue_cmd($id, $type, $args);
    $st = jread(DATA . "/$id/state.json");
    log_event($id, ['t' => time(), 'level' => 'dash', 'msg' => "Sala: «{$label}» encargado a esta máquina"]);
    json_out(['ok' => true, 'cmd' => $cmd, 'agente' => $id, 'nombre' => $st['name'] ?? $id]);
}

// ── comandos de una instalación ──
$id = $in['id'] ?? '';
$allowed = ['restart_app', 'stop_app', 'start_app', 'screenshot', 'reboot', 'osc', 'boton', 'pantallas_guardar', 'pantallas_restaurar'];
if (!valid_id($id) || !is_file(DATA . "/$id/state.json")) json_out(['error' => 'instalación'], 404);
if (!in_array($type, $allowed, true)) json_out(['error' => 'comando'], 400);
$st = jread(DATA . "/$id/state.json");
if ($type === 'reboot' && empty($st['allow_reboot'])) json_out(['error' => 'reiniciar la PC está desactivado en esa máquina'], 403);

$args = [];
if ($type === 'osc') {
    $addr = (string)($in['address'] ?? '');
    if (!preg_match('#^/[\w/\-.]{1,120}$#u', $addr)) json_out(['error' => 'dirección OSC'], 400);
    $vals = [];
    foreach (preg_split('/\s+/', trim((string)($in['values'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) as $v) {
        $vals[] = is_numeric($v) ? (strpos($v, '.') !== false ? (float)$v : (int)$v) : $v;
    }
    $args = ['address' => $addr, 'args' => array_slice($vals, 0, 16)];
}
if ($type === 'boton') {
    $i = (int)($in['i'] ?? -1);
    if ($i < 0 || $i >= count($st['buttons'] ?? [])) json_out(['error' => 'botón'], 400);
    $args = ['i' => $i, 'label' => (string)$st['buttons'][$i]['label']];
}

$cmd = queue_cmd($id, $type, $args);
$detalle = $type === 'osc' ? ' ' . $args['address'] . ' ' . implode(' ', $args['args']) : ($type === 'boton' ? ' «' . $args['label'] . '»' : '');
log_event($id, ['t' => time(), 'level' => 'dash', 'msg' => "Comando: $type$detalle"]);
json_out(['ok' => true, 'cmd' => $cmd]);
