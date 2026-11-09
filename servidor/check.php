<?php
// Centinelli · revisa instalaciones que dejaron de reportar y manda alertas.
// Cron cada 1–5 min:   php /ruta/a/centinelli/check.php
// o por URL:           https://tu-sitio/centinelli/check.php?key=CRON_KEY
require __DIR__ . '/lib.php';

if (PHP_SAPI !== 'cli') {
    if ($CFG['cron_key'] === 'CAMBIA-ESTA-CLAVE' || !hash_equals($CFG['cron_key'], (string)($_GET['key'] ?? ''))) {
        http_response_code(403); exit('clave');
    }
}
$res = [];
foreach (list_ids() as $id) {
    $st = jread(DATA . "/$id/state.json");
    $res[$id] = array_keys(check_alerts($id, $st));
}
header('Content-Type: application/json');
echo json_encode($res, JSON_UNESCAPED_UNICODE), "\n";
