<?php
// Centinelli · estado de todas las instalaciones para el dashboard
require __DIR__ . '/lib.php';
require_login();

$now = time();
$out = [];
foreach (list_ids() as $id) {
    $d  = DATA . "/$id";
    $st = jread("$d/state.json");
    $st['id'] = $id;
    $st['problems'] = problems($st, $now);
    $st['events'] = array_reverse(array_slice(jread("$d/events.json"), -40));
    $st['cmds'] = array_reverse(array_slice(jread("$d/cmds.json"), -8));
    $st['has_thumb'] = is_file("$d/thumb.jpg");
    $out[] = $st;
}
json_out(['now' => $now, 'items' => $out, 'salas' => array_values(salas($out))]);
