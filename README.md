# Centinelli

**Monitoreo y control remoto de instalaciones multimedia hechas con TouchDesigner, Isadora o Resolume Arena.**

Centinelli vigila la computadora de una instalación mientras tú no estás. Sabe si la pieza sigue viva, la reinicia si se cae o se congela y te avisa por Telegram. Desde el teléfono puedes verla en miniatura y mandarle comandos.

Versión 0.2 · prototipo · Colección Alchemy of Meaning (Alquimia del Significado)

![Dashboard](capturas/01-dashboard.png)

## Qué hace

- **Vigila el proceso** (TouchDesigner, Isadora, Arena) y lo relanza si se cierra.
- **Detecta congelamientos.** La app manda un latido OSC; en Resolume, el agente consulta su REST API. Si el latido deja de llegar con la ventana abierta, el agente mata la app y la vuelve a abrir. Si se reinicia demasiadas veces en una hora, se detiene y te avisa.
- **Reporta** fps, CPU, GPU, RAM, VRAM, temperatura, disco y los valores que mande la pieza (escena, jugadores; en Resolume: composición, BPM y clips conectados). Además manda una miniatura de la pantalla cada minuto.
- **Recibe comandos** desde el dashboard: reiniciar la app o la PC, pedir una captura o mandar cualquier mensaje OSC a la pieza (cambiar de escena, disparar una columna de Arena).
- **Avisa** por Telegram o correo si una máquina pierde conexión, si la app falla más de 45 s o si el GPU se calienta. También avisa cuando se resuelve.
- **No abre puertos.** La máquina sólo hace conexiones de salida por HTTPS, así que funciona detrás del firewall de museos y teatros.

| Teléfono | Modo mural (`h`) | Acceso |
|---|---|---|
| ![móvil](capturas/02-movil.png) | ![mural](capturas/03-mural.png) | ![acceso](capturas/04-acceso.png) |

## Cómo está hecho

```
agente/          Python (psutil): corre en cada máquina, vigila y reporta
servidor/        PHP sin base de datos: API, dashboard, alertas (sube a cualquier hosting compartido)
touchdesigner/   script que construye el COMP «centinelli» dentro del .toe
isadora/         guía: escena de fondo con latido OSC y comandos
resolume/        guía y config: latido por REST API, comandos por OSC
```

## Uso rápido

1. Sube `servidor/` a tu hosting, copia `config.example.php` como `config.php` y genera contraseña y token con `hash.php`.
2. En la máquina: `pip install psutil mss pillow`, copia la plantilla de `agente/` que corresponda como `config.json` y ejecuta `python centinelli_agente.py config.json`.
3. En la pieza: el COMP de TouchDesigner, la escena de latido de Isadora o el webserver de Arena.

La guía completa de instalación está en **[LEEME.md](LEEME.md)**. Incluye la lista para dejar una máquina funcionando sola: BIOS, Windows, no-break y acceso de emergencia.

## Estado

Prototipo. El ciclo completo (agente, servidor, alertas y comandos) está probado con apps simuladas. La integración dentro de TouchDesigner, Isadora y Resolume falta probarla en producción.

## Créditos

Diseño, investigación y desarrollo: **Héctor Cruz Juárez** — https://hectorcruz.com.mx
Colección **Alchemy of Meaning (Alquimia del Significado)**. Interfaz con el estilo alchemy-ui v1.
Licencia GPL-3.0.

---

## English

**Remote monitoring and control for media installations built with TouchDesigner, Isadora or Resolume Arena.**

Centinelli watches an installation computer while you are away. It knows whether the piece is still running, restarts it when it crashes or freezes, and alerts you on Telegram. From your phone you can see a thumbnail and send it commands.

Version 0.2 · prototype · Alchemy of Meaning collection

### What it does

- **Watches the process** (TouchDesigner, Isadora, Arena) and relaunches it if it closes.
- **Detects freezes.** The app sends an OSC heartbeat; for Resolume, the agent polls its REST API. If the heartbeat stops while the window is still open, the agent kills and relaunches the app. After too many restarts in an hour it stops and alerts you.
- **Reports** fps, CPU, GPU, RAM, VRAM, temperature, disk and any values the piece sends (scene, players; for Resolume: composition, BPM and connected clips). It also sends a screen thumbnail every minute.
- **Takes commands** from the dashboard: restart the app or the PC, take a screenshot, or send any OSC message to the piece (change scene, trigger an Arena column).
- **Alerts** by Telegram or email when a machine goes offline, the app fails for more than 45 s, or the GPU runs hot. It also tells you when the problem clears.
- **Opens no ports.** The machine only makes outbound HTTPS requests, so it works behind museum and theatre firewalls.

### Layout

`agente/` Python agent (psutil) · `servidor/` database-free PHP server (API, dashboard, alerts) · `touchdesigner/` script that builds the «centinelli» COMP · `isadora/` heartbeat scene guide · `resolume/` REST-API heartbeat + OSC commands.

### Quick start

1. Upload `servidor/` to any PHP host, copy `config.example.php` to `config.php`, and generate a password and token with `hash.php`.
2. On the machine, run `pip install psutil mss pillow`, copy the matching template from `agente/` to `config.json`, then run `python centinelli_agente.py config.json`.
3. In the piece, add the TouchDesigner COMP, the Isadora heartbeat scene, or enable Arena's webserver.

The full setup guide (in Spanish) is in [LEEME.md](LEEME.md).

### Credits

Design, research and development: **Héctor Cruz Juárez** — https://hectorcruz.com.mx
**Alchemy of Meaning (Alquimia del Significado)** collection. Licensed under GPL-3.0.
