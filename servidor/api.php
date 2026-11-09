<?php
// Centinelli · endpoint que reciben los agentes (POST JSON con cabecera X-Centinelli-Token)
require __DIR__ . '/lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'POST'], 405);
$token = $_SERVER['HTTP_X_CENTINELLI_TOKEN'] ?? '';
if ($CFG['agent_token'] === 'CAMBIA-ESTE-TOKEN' || !hash_equals($CFG['agent_token'], $token)) {
    json_out(['error' => 'token'], 403);
}

$raw = file_get_contents('php://input', false, null, 0, 4 * 1024 * 1024);
$in = json_decode($raw, true);
if (!is_array($in) || !valid_id($in['id'] ?? null)) json_out(['error' => 'datos'], 400);

$id  = $in['id'];
$dir = inst_dir($id);
$now = time();

// Miniatura
if (!empty($in['thumb'])) {
    $jpg = base64_decode($in['thumb'], true);
    if ($jpg !== false && substr($jpg, 0, 3) === "\xFF\xD8\xFF") {
        file_put_contents("$dir/thumb.jpg", $jpg, LOCK_EX);
        $in['thumb_t'] = $now;
    }
}
unset($in['thumb']);

// Eventos del agente → bitácora
$agent_errors = [];
if (!empty($in['events']) && is_array($in['events'])) {
    foreach ($in['events'] as $e) {
        if (is_array($e) && ($e['level'] ?? '') === 'error') $agent_errors[] = substr((string)($e['msg'] ?? ''), 0, 300);
    }
    jupdate("$dir/events.json", function ($ev) use ($in) {
        foreach (array_slice($in['events'], -200) as $e) {
            if (is_array($e)) $ev[] = ['t' => (int)($e['t'] ?? time()),
                'level' => substr((string)($e['level'] ?? 'info'), 0, 10),
                'msg' => substr((string)($e['msg'] ?? ''), 0, 500)];
        }
        return array_slice($ev, -300);
    });
}
unset($in['events']);

// Comandos: confirma los atendidos y entrega los pendientes
$acks = is_array($in['acks'] ?? null) ? $in['acks'] : [];
unset($in['acks']);
$pending = [];
jupdate("$dir/cmds.json", function ($cmds) use ($acks, &$pending, $now) {
    foreach ($cmds as &$c) {
        foreach ($acks as $a) {
            if (($a['id'] ?? null) === $c['id']) { $c['status'] = 'hecho'; $c['result'] = (string)($a['result'] ?? ''); $c['done'] = $now; }
        }
        if ($c['status'] === 'pendiente') {
            $pending[] = ['id' => $c['id'], 'type' => $c['type'], 'args' => $c['args']];
            $c['status'] = 'enviado'; $c['sent'] = $now;
        }
    }
    return array_slice($cmds, -30);
});

// Estado
$prev = jread("$dir/state.json");
$in['last_seen'] = $now;
$in['ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
if (empty($in['thumb_t']) && !empty($prev['thumb_t'])) $in['thumb_t'] = $prev['thumb_t'];
jwrite("$dir/state.json", $in);

check_alerts($id, $in, array_slice($agent_errors, 0, 5));

json_out(['ok' => true, 'commands' => $pending, 'server_time' => $now]);
