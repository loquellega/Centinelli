<?php
// Centinelli · sirve la miniatura (sólo con sesión iniciada)
require __DIR__ . '/lib.php';
if (!logged_in()) { http_response_code(401); exit; }
$id = $_GET['id'] ?? '';
$f = valid_id($id) ? DATA . "/$id/thumb.jpg" : '';
if (!$f || !is_file($f)) { http_response_code(404); exit; }
header('Content-Type: image/jpeg');
header('Cache-Control: private, max-age=10');
readfile($f);
