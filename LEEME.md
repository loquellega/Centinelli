# Centinelli · guía de instalación

Versión 0.6. **¿Primera vez? Instalación guiada en [INSTALAR.md](INSTALAR.md) y prueba en [PRUEBA-LOCAL.md](PRUEBA-LOCAL.md).** Esta guía describe la instalación manual y los detalles técnicos. Sirve para saber desde el teléfono si cada instalación está viva, verla en una miniatura, recibir un aviso por Telegram si algo falla y reiniciarla a distancia.

```
 Máquina de la instalación                          Tu hosting PHP
┌─────────────────────────────────┐   HTTPS     ┌──────────────────────────┐
│ TouchDesigner/Isadora/Resolume  │  (saliente) │ api.php   ← reportes     │
│   └─ latido OSC/HTTP ► agente ──┼────────────►│ data/     (JSON, sin BD) │
│        ◄── comandos OSC ──┘     │◄────────────┤ index.php  dashboard     │
│ agente: vigila proceso, métricas│ comandos en │ check.php  cron/alertas  │──► Telegram
│        captura, reinicios       │ la respuesta└──────────────────────────┘
└─────────────────────────────────┘
```

La máquina sólo hace conexiones **salientes**, así que funciona detrás del firewall del museo o del teatro sin abrir puertos.

## Qué detecta

| Situación | Cómo la detecta | Qué hace |
|---|---|---|
| La app se cerró o crasheó | el proceso no existe | la relanza |
| La app se congeló (ventana abierta pero sin moverse) | deja de llegar el latido (OSC, o HTTP en Resolume) | la mata y la relanza |
| Se reinicia una y otra vez | más de 6 reinicios en una hora | pausa los reinicios y te avisa |
| Se cayó internet, se apagó la máquina o se fue la luz | el servidor deja de recibir reportes | aviso «sin conexión» |
| GPU caliente / disco lleno | métricas | aviso |

Un problema se avisa sólo si dura más de 45 s, para no mandarte un mensaje por cada reinicio corto. Cuando se resuelve, llega un 🟢.

## Carpetas

- `servidor/`: se sube a tu hosting, por ejemplo `hectorcruz.com.mx/centinelli/`
- `agente/`: va en cada máquina de instalación
- `touchdesigner/`: script que construye el COMP `centinelli` dentro de tu .toe
- `isadora/`: guía para armar el latido y los comandos en Isadora
- `resolume/`: guía para Resolume Arena/Avenue (latido por su REST API, comandos por OSC) `SALA.md` para sincronizar varios Resolume directamente desde Centinelli (sin TouchDesigner) y `SINCRONIZACION.md` para la variante con TouchDesigner como maestro

## 1. Servidor (una vez)

1. Sube la carpeta `servidor/` a tu hosting. Debe quedar `data/` con su `.htaccess`, porque bloquea el acceso directo a los datos.
2. Copia `config.example.php` como `config.php`. Abre `https://…/centinelli/hash.php`, escribe una contraseña y copia `password_hash`, `agent_token` y `cron_key` a `config.php`. **Borra `hash.php`.**
3. Telegram (recomendado):
   - Habla con @BotFather, crea un bot y pega el token en `telegram_token`.
   - Escríbele cualquier cosa a tu bot y abre `https://api.telegram.org/bot<TOKEN>/getUpdates`. Copia el `chat.id` a `telegram_chat`.
4. Cron cada 2 minutos en el panel del hosting, para detectar máquinas que dejaron de reportar:
   `php /home/usuario/public_html/centinelli/check.php`
   Si el hosting no deja usar php por línea de comandos, usa la URL `https://…/centinelli/check.php?key=CRON_KEY` con el cron del panel o con un servicio como cron-job.org.

## 2. Agente en la máquina (Windows)

1. Instala Python 3 (marca «Add to PATH») y luego:
   `pip install psutil mss pillow`
2. Copia `agente/` a `C:/centinelli/`. Copia como `config.json` la plantilla que toque (`config.ejemplo.json` para TouchDesigner, `config.isadora.json` o `config.resolume.json`) y ajusta:
   - `id`: corto y sin espacios, como `ofdcm-lobby`; `name`: el nombre que verás en la tarjeta
   - `server` y `token` (el `agent_token` de `config.php`)
   - `app.process`: `TouchDesigner.exe`, `Isadora.exe` o `Arena.exe` (mira el nombre exacto en el Administrador de tareas)
   - `app.launch`: ruta del ejecutable y del archivo `.toe` o `.izz`
3. Prueba a mano: `python centinelli_agente.py config.json`. La tarjeta debe aparecer en el dashboard.
4. Ejecuta `instalar_inicio_windows.bat` como administrador. El agente arranca al iniciar sesión y lanza la app por su cuenta (`start_on_boot`). Quita TD o Isadora de otros arranques automáticos para que no se abran dos veces.

> Va como tarea al iniciar sesión, no como servicio de Windows. Un servicio no puede abrir programas con interfaz ni capturar la pantalla.

## 3. TouchDesigner

En el Textport (Alt+T):
```python
exec(open(r'C:/centinelli/touchdesigner/centinelli_td_construir.py', encoding='utf-8').read())
```
Crea `/project1/centinelli`, que manda el latido con los fps cada segundo y escucha comandos en el puerto 9301. Guarda el .toe. También puedes exportar el COMP como .tox para reusarlo.

Desde tus scripts:
```python
mod('/project1/centinelli/centinelli').Reportar('escena', 'leon')     # aparece en la tarjeta
mod('/project1/centinelli/centinelli').Evento('Partida completa')      # línea en la bitácora
```
Agrega tus propios comandos en el DAT `comandos` (`ACCIONES`); por ejemplo, mandar un cue a PIXERA o cambiar de escena. Desde el dashboard escribe la dirección y los valores en el campo OSC de la tarjeta.

## 4. Isadora

Ver `isadora/ISADORA.md`. Es una escena de fondo con Pulse Generator → Counter → OSC Transmit a `127.0.0.1:9300 /centinelli/heartbeat`.

## 5. Resolume Arena / Avenue

Ver `resolume/RESOLUME.md`. No necesita patch: el agente usa el REST API de Arena como latido y le manda comandos por OSC (puerto 7000). La tarjeta muestra la composición, el BPM y los clips conectados.

## 6. Sala: varios Resolume sincronizados

Varias máquinas con Arena se agrupan en una **sala** (bloque `sala` del `config.json`). Los agentes se mandan la secuencia de sincronización entre ellos: al encender (sólo el coordinador), cuando uno reabre Arena, a ciertas horas o con el botón del dashboard. No hace falta TouchDesigner. Dos variantes: **un solo agente en el primer server** que vigila a los demás por su REST API y sincroniza cuando todos arrancaron (`INSTALAR-SERVER1.md`, `agente/config.server1.json`), o un agente en cada server (`resolume/SALA.md`, `agente/config.sala.json`).

## 7. Salidas de video

Con el bloque `pantallas`, el agente vigila que Windows no reacomode los displays: orden, posición, resolución y Hz de cada salida, identificada por su puerto físico. Si sólo cambió el orden, las restaura y reabre la app. Si falta una salida, avisa. Guía completa en `PANTALLAS.md`.

## 8. Botones de cue y acciones al reiniciar

En el `config.json` de cada máquina puedes definir:

- `botones`: botones con nombre en la tarjeta del dashboard («Sincronizar todo», «Columna 1», «Negro»…). Cada uno manda un OSC a la app o a otra IP de la red local.
- `al_reiniciar`: mensajes OSC que el agente manda N segundos después de abrir la app. Sirve, por ejemplo, para pedir una resincronización.

Ejemplos en `agente/config.resolume.json` y explicación completa en `resolume/SINCRONIZACION.md`.

## Rendimiento

- El agente corre con prioridad baja.
- Entre reportes completos (cada `interval`, 30–60 s) manda reportes ligeros sólo con estado, valores y órdenes; `nvidia-smi` se consulta sólo en los completos.
- Busca el proceso de la app por su PID, sin recorrer todos los procesos.
- Si vigila la sala, consulta el REST API de su propio Arena una sola vez para todo.
- El servidor sólo escribe en disco lo que cambió.
- El dashboard no redibuja si nada cambió y deja de consultar cuando la pestaña está oculta.

Mediciones en `INSTALAR-SERVER1.md`.

## Lista para dejar una máquina sola

- BIOS (opcional): **Restore on AC power loss → Power On**, para que encienda sola al volver la luz. Si prefieres que la encienda el técnico de sala, déjalo apagado.
- Windows: inicio de sesión automático (`netplwiz`), sin suspensión, pantalla nunca se apaga
- Windows Update: horas activas que cubran el horario de la instalación, o actualizaciones pausadas durante la temporada
- Desactiva notificaciones y el «Asistente de concentración»
- No-break (UPS)
- Acceso de emergencia: **Tailscale** para entrar por red privada sin abrir puertos, más **AnyDesk** o **Parsec** para ver el escritorio
- Opcional: un contacto inteligente (Shelly o TP-Link) para apagar y encender la máquina si se congela todo el sistema

## Seguridad

- El dashboard pide contraseña y la sesión usa cookie httpOnly. Los comandos llevan token CSRF.
- Los agentes se identifican con `agent_token`. Si una máquina se pierde, cambia el token en `config.php` y en las demás máquinas.
- «Reiniciar PC» está **desactivado por defecto**: encender o reiniciar la computadora queda en manos del técnico de sala. Se activa por máquina con `"permitir_reinicio_pc": true`, y aun así pide confirmación.
- El OSC In de TouchDesigner escucha en todas las interfaces de la red local. Si la red del lugar no es de confianza, quita del DAT `comandos` las acciones `par` y `pulse`, que son las más poderosas.

## Límites conocidos de esta versión

- Probado de punta a punta (servidor PHP 8.3 + agente + apps simuladas, incluido un Resolume falso con su REST API): caída, congelamiento, tope de reinicios, comandos, OSC, alertas y desconexión. **El script de TouchDesigner, el patch de Isadora y la config de Resolume no se probaron dentro de esas apps.** La sala se probó con 5 Resolume simulados (arranque, reinicio de uno, coordinador caído, botones de sala y desfase). Hay que revisarlos en tu máquina.
- En Windows, `psutil` no da la temperatura del CPU. La del GPU Nvidia sí sale, vía `nvidia-smi`.
- En Mac, «Reiniciar PC» necesita permisos de `sudo` sin contraseña para `shutdown`.
- La miniatura se toma cada 60 s, o al pulsar «captura».
- Dashboard: tecla `h` = modo mural (sólo visores y lecturas), `c` = contraste alto, `r` = recargar.

## Ideas para la v0.4

- Integrarlo como módulo de hq
- Temperatura del CPU en Windows con LibreHardwareMonitor
- Horario: encender y apagar la app según la hora de apertura del museo
- Historial de fps y temperatura en una gráfica de 24 h
