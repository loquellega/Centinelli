# Prueba en tu computadora (una sola máquina con Resolume Arena)

Es el ensayo de lo que luego harás en el museo, pero con una sola computadora que es a la vez «sala» y «coordinador». Tiempo estimado: 30 minutos.

## 1. Servidor (una vez)

1. Sube la carpeta `servidor/` a tu hosting como `hectorcruz.com.mx/centinelli/`.
2. Copia `config.example.php` como `config.php`.
3. Abre `https://hectorcruz.com.mx/centinelli/hash.php`, escribe una contraseña y pega en `config.php` los tres valores que te da: `password_hash`, `agent_token` y `cron_key`. **Borra `hash.php`.**
4. Opcional pero recomendado: Telegram (pasos en `LEEME.md`).
5. Entra a `https://hectorcruz.com.mx/centinelli/`. Verás «Ninguna instalación ha reportado todavía».

## 2. Resolume

1. *Preferences ▸ Webserver*: activa **Enable Webserver & REST API** (puerto 8080).
2. *Preferences ▸ OSC*: activa **OSC Input** (puerto 7000).
3. Abre una composición con clips en las columnas 1 y 2 y guárdala.

## 3. Agente

1. Instala Python 3. En Windows, marca «Add python.exe to PATH» en el instalador. Luego, en una terminal:
   ```
   pip install psutil mss pillow
   ```
2. Copia la carpeta `agente/` a `C:\centinelli\` (en Mac, a tu carpeta de usuario).
3. Copia `config.sala.json` como `config.json` y cambia:
   - `id`: `prueba-hector`; `name`: `Prueba · mi PC`
   - `server`: `https://hectorcruz.com.mx/centinelli/api.php`
   - `token`: el `agent_token` de `config.php`
   - `app.process`: `Arena.exe` (en Mac: `Arena`)
   - `app.launch`: la ruta de tu Arena (en Mac: `["open", "-a", "Resolume Arena"]`, y entonces `app.process` = `Arena`)
   - `sala.id`: `prueba`; `sala.nombre`: `Prueba local`
   - `sala.servidores`: `["127.0.0.1"]`
   - `sala.sync_al_arrancar`: `20` (en la prueba no hace falta esperar 2 minutos)
4. Con Arena cerrado, ejecuta:
   ```
   cd C:\centinelli
   python centinelli_agente.py config.json
   ```
   El agente abre Arena. A los 20 s verás en la terminal «Sala: sincronización completa», y Arena habrá hecho Negro, Columna 2 y Columna 1.

## 4. Qué probar

| Prueba | Qué debe pasar |
|---|---|
| Abrir el dashboard en el teléfono | Panel «sala · Prueba local» 1/1, columna 1, tarjeta con miniatura de tu pantalla |
| Botón «Columna 2» | Arena cambia en ≤5 s; el panel muestra col 2 |
| Botón «sincronizar sala» | Negro → Col 2 → Col 1; «última sync» se actualiza |
| Cerrar Arena desde el Administrador de tareas | El agente lo reabre (≈10 s) y a los 70 s sincroniza; queda en la bitácora |
| «detener» desde el dashboard | Arena se cierra y no se reabre; «iniciar» la vuelve a abrir |
| Cerrar el agente (Ctrl+C) | A los 2 min, «sin conexión» en el dashboard y aviso de Telegram si lo configuraste. Para este aviso, el cron de `check.php` tiene que estar activo |

## 5. Para que arranque solo

Cuando todo funcione, ejecuta `instalar_inicio_windows.bat` como administrador. El agente arrancará al iniciar sesión y abrirá Arena por su cuenta. Quita Arena de los programas de inicio de Windows para que no se abra dos veces.

## Después: el museo

Mismos pasos en los 5 servidores, con la configuración de `resolume/SALA.md`: las 5 IPs en `servidores`, `coordinador: true` sólo en el Server 1 y `sync_al_arrancar: 120`.
