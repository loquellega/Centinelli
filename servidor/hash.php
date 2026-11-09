<?php
// Centinelli · genera el hash de la contraseña y un token para agentes.
// Úsalo una vez, copia los valores a config.php y BORRA este archivo.
if (!is_file(__DIR__ . '/config.php')) { http_response_code(500); exit('Centinelli: copia config.example.php como config.php y edítalo.'); }
$CFG = require __DIR__ . '/config.php';
if (!empty($CFG['password_hash'])) { http_response_code(403); exit('Ya hay contraseña configurada. Borra hash.php.'); }
$hash = '';
if (!empty($_POST['p'])) $hash = password_hash($_POST['p'], PASSWORD_DEFAULT);
$token = bin2hex(random_bytes(24));
$cron = bin2hex(random_bytes(12));
?><!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width">
<body style="font:15px system-ui;background:#111;color:#ddd;padding:2em;max-width:640px">
<h2>Centinelli · configuración inicial</h2>
<form method="post"><input type="password" name="p" placeholder="Contraseña del dashboard" style="padding:.5em;width:70%">
<button>Generar</button></form>
<?php if ($hash): ?><p>password_hash:</p><code style="word-break:break-all;color:#9f9"><?= htmlspecialchars($hash) ?></code><?php endif; ?>
<p>agent_token sugerido:</p><code style="color:#9cf"><?= $token ?></code>
<p>cron_key sugerida:</p><code style="color:#9cf"><?= $cron ?></code>
<p style="color:#f99">Pega los valores en config.php y borra hash.php.</p>
</body>
