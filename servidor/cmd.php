<?php
// Centinelli · encola un comando para una instalación (desde el dashboard)
require __DIR__ . '/lib.php';
require_login();
require_csrf();

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$id = $in['id'] ?? '';
$type = $in['type'] ?? '';
$allowed = ['restart_app', 'stop_app', 'start_app', 'screenshot', 'reboot', 'osc', 'boton'];
if (!valid_id($id) || !is_file(DATA . "/$id/state.json")) json_out(['error' => 'instalación'], 404);
if (!in_array($type, $allowed, true)) json_out(['error' => 'comando'], 400);

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
    $st = jread(DATA . "/$id/state.json");
    if ($i < 0 || $i >= count($st['buttons'] ?? [])) json_out(['error' => 'botón'], 400);
    $args = ['i' => $i, 'label' => (string)$st['buttons'][$i]['label']];
}

$cmd = ['id' => bin2hex(random_bytes(6)), 'type' => $type, 'args' => $args,
        'status' => 'pendiente', 'created' => time()];
jupdate(DATA . "/$id/cmds.json", function ($c) use ($cmd) { $c[] = $cmd; return array_slice($c, -30); });
$detalle = $type === 'osc' ? ' ' . $args['address'] . ' ' . implode(' ', $args['args']) : ($type === 'boton' ? ' «' . $args['label'] . '»' : '');
log_event($id, ['t' => time(), 'level' => 'dash', 'msg' => "Comando: $type$detalle"]);
json_out(['ok' => true, 'cmd' => $cmd]);
