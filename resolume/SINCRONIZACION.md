# Sincronización de varios Resolume con Centinelli

Escenario típico: varias máquinas con Resolume Arena (fachada, pantallas, proyectores) y una máquina con TouchDesigner que las sincroniza por OSC. Envía Detener todo, luego Columna 2 y luego Columna 1 a todas las IPs, con el script `touchdesigner/resolume_sync_v3.py`.

El problema que resuelve esta integración: **si Centinelli reinicia Arena en una máquina porque se cayó o se congeló, esa máquina queda desfasada del resto.** Ahora la propia máquina pide al TouchDesigner que vuelva a sincronizar a todos.

```
 Resolume B se congela
   └─ su agente Centinelli reabre Arena
        └─ espera 70 s (a que cargue la composición)
             └─ manda /centinelli/cmd/sync  ──►  TD maestro (puerto 9301)
                                                   └─ resolume_sync_v3: Stop + Col 2 + Col 1
                                                        └─ a TODAS las IPs (A, B, C…)
                                                        └─ reporta a Centinelli: «Sincronización completa 21:14:03»
```

## Piezas

| Máquina | Qué lleva |
|---|---|
| TD maestro | agente Centinelli (`config.ejemplo.json`), COMP `centinelli` (`centinelli_td_construir.py`), script `resolume_sync_v3.py` |
| Cada Resolume | agente Centinelli con `config.resolume.json`, que trae `botones` y `al_reiniciar` |

## 1. TouchDesigner maestro

1. Corre `centinelli_td_construir.py` (crea `/project1/centinelli`, que escucha comandos en 9301).
2. En el COMP de sincronización, corre `resolume_sync_v3.py`. Si ya tenías la v2, córrelo encima: tus IPs y tiempos se conservan.
3. En la página **Resolume** aparecen dos parámetros nuevos:
   - **Reportar a Centinelli**: cada envío queda en la bitácora del dashboard, con cuántos destinos lo recibieron y cuáles fallaron. La tarjeta del TD muestra `sync 21:14:03`.
   - **Puerto del agente Centinelli**: 9300 por defecto.

El COMP `centinelli` encuentra solo el `exec_sync`, así que no hay rutas que configurar. Entiende tres comandos:

| OSC a TD:9301 | Hace |
|---|---|
| `/centinelli/cmd/sync` | sincronización completa (Stop + Col 2 + Col 1) |
| `/centinelli/cmd/detener` | `/composition/disconnectall` a todos |
| `/centinelli/cmd/columna 3` | dispara la columna 3 en todos |

## 2. Cada máquina con Resolume

En su `config.json` (ver `agente/config.resolume.json`):

```json
"botones": [
  {"label": "Sincronizar todo", "address": "/centinelli/cmd/sync", "host": "192.168.1.10", "port": 9301, "confirmar": true},
  {"label": "Columna 1", "address": "/composition/columns/1/connect", "args": [1]},
  {"label": "Negro", "address": "/composition/disconnectall", "args": [1], "confirmar": true}
],
"al_reiniciar": {
  "espera": 70,
  "mensajes": [{"address": "/centinelli/cmd/sync", "host": "192.168.1.10", "port": 9301}]
}
```

- `botones` aparecen como botones grandes en la tarjeta del dashboard. Sin `host` ni `port`, el mensaje va a la app de esa máquina (Arena, en el puerto `osc.app_port`). Con `host` y `port`, va a otra máquina de la red local, como el TD maestro. `confirmar` pide confirmación antes de mandar.
- `al_reiniciar` se dispara cada vez que el agente abre la app: al arrancar, por un reinicio automático o con «reiniciar app» desde el dashboard. `espera` debe cubrir lo que tarda Arena en cargar la composición. Con el `Espera inicial` de 60 s del script, 70 s es un buen punto de partida.

Por seguridad, el dashboard sólo puede pedir «el botón número N». Las IPs y direcciones viven en el `config.json` de cada máquina y no se pueden cambiar desde internet.

## Notas

- `192.168.1.10` es un ejemplo: pon la IP fija de la máquina TD. Conviene reservarla en el router.
- Si el TD maestro es el que se reinicia, su `Auto al iniciar` ya vuelve a sincronizar a todos. No hace falta `al_reiniciar` en esa máquina.
- Para que el firewall de Windows deje pasar el OSC entrante, abre el puerto UDP 9301 en la máquina TD y el 7000 en las de Resolume.
