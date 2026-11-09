<?php
// ─── Centinelli · configuración del servidor ───
// Copia este archivo como config.php (no se sube a GitHub) y edítalo.
return [
    // Contraseña del dashboard: abre hash.php una vez, pega aquí el resultado y borra hash.php.
    'password_hash' => '',

    // Token que comparten los agentes (ponlo también en el config.json de cada máquina).
    // Genera uno largo, p.ej. con hash.php o cualquier generador de contraseñas.
    'agent_token'   => 'CAMBIA-ESTE-TOKEN',

    // Alertas por Telegram (opcional). Crea un bot con @BotFather y obtén tu chat_id
    // escribiéndole al bot y abriendo https://api.telegram.org/bot<TOKEN>/getUpdates
    'telegram_token' => '',
    'telegram_chat'  => '',

    // Alertas por correo (opcional, usa mail() del hosting). Deja vacío para no usar.
    'alert_email'    => '',

    // Clave para llamar check.php desde un cron por URL (si el hosting no permite php-cli).
    'cron_key'       => 'CAMBIA-ESTA-CLAVE',

    // Umbrales
    'alert_delay'    => 45,    // s que debe durar un problema antes de avisar
    'offline_after'  => 120,   // s sin reporte → "sin conexión" (mínimo; también 3× el intervalo)
    'gpu_temp_max'   => 85,
    'cpu_temp_max'   => 90,
    'disk_max'       => 95,

    'timezone'       => 'America/Mexico_City',
];
