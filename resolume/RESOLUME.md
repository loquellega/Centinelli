# Centinelli con Resolume Arena / Avenue

Resolume no necesita patch ni plugin. Centinelli usa dos cosas que Arena ya trae:

- **Webserver y REST API** (Resolume 7.8 o posterior): sirve como latido. El agente pide `GET /api/v1/composition` cada 5 s. Si Arena responde, está viva. Si deja de responder más de 20 s con el proceso abierto, la toma por congelada y la reinicia. De esa misma respuesta saca, para la tarjeta, el nombre de la composición, el BPM y los clips conectados en cada capa.
- **Entrada OSC**: recibe los comandos del dashboard, como disparar columnas o clips.

## 1. En Arena

1. *Preferences ▸ Webserver*: activa **Enable Webserver & REST API**. El puerto por defecto es 8080.
2. *Preferences ▸ OSC*: activa **OSC Input**. El puerto por defecto es 7000.
3. Haz que Arena abra sola la composición del show al iniciar. Lo más seguro es guardar la composición antes de dejar la máquina, porque Arena reabre la última que tuvo abierta.

## 2. En el agente

Usa `agente/config.resolume.json` como base:

- `app.process`: `Arena.exe` (`Avenue.exe` si es Avenue; en Mac, `Arena`). Confírmalo en el Administrador de tareas.
- `http_heartbeat.url`: cambia el puerto si moviste el del webserver.
- `osc.app_port`: el puerto de entrada OSC de Arena.

## 3. Comandos útiles desde el campo OSC de la tarjeta

| Dirección | Valor | Qué hace |
|---|---|---|
| `/composition/columns/3/connect` | `1` | dispara la columna 3 |
| `/composition/layers/1/clips/2/connect` | `1` | dispara el clip 2 de la capa 1 |
| `/composition/layers/1/clear` | `1` | limpia la capa 1 |
| `/composition/master` | `0.0`–`1.0` | master de la composición |
| `/composition/disconnectall` | `1` | apaga todos los clips (negro) |

Las direcciones exactas de cualquier parámetro se ven en Arena con *Shortcuts ▸ Edit OSC*, haciendo clic sobre el control.

## Botones y sincronización de varios Resolume

Los comandos que uses seguido pueden ir como botones con nombre en la tarjeta (`botones` en el `config.json`). Si sincronizas varios Resolume desde TouchDesigner, ve **[SINCRONIZACION.md](SINCRONIZACION.md)**: cuando Centinelli reabre Arena en una máquina, esa máquina pide sola la resincronización de todas.

## Límites

- La miniatura es una captura de la pantalla de la máquina. Si la salida va en pantalla completa a otro monitor, `screenshot.monitor: 0` captura todos los monitores juntos.
- Si el webserver está apagado, el agente toma a Arena por congelada y la reinicia en ciclo, hasta llegar al tope de reinicios por hora. Activa siempre el webserver antes de usar esta config.
