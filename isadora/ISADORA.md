# Centinelli en Isadora

Isadora no necesita plugin. Basta un latido OSC y, si quieres control remoto, un par de OSC Listener.

## 1. Latido (obligatorio)

El agente espera `/centinelli/heartbeat` en `127.0.0.1:9300`. Si deja de llegar más de 15 s, toma la pieza por congelada y reinicia Isadora.

Arma una **escena de fondo** que siempre esté activa, para que el latido no se apague cuando cambias de escena:

```
[Pulse Generator  freq 1 Hz]
        │ trigger
[Counter  min 0 · max 100000 · mode wrap]
        │ output
[OSC Transmit  ip addr 127.0.0.1 · port 9300 · address /centinelli/heartbeat]
```

- El **Counter** hace que el valor cambie en cada pulso. Así el OSC Transmit envía aunque sólo transmita cuando cambia el valor.
- Activa esa escena desde la primera escena del show con **Activate Scene**, o desde **Scene ▸ Activate** al abrir el archivo. Así queda viva en paralelo.
- En `config.isadora.json` viene `"heartbeat_is_fps": false`, así que el número del contador no se muestra como fps. Si quieres ver fps reales, mándalos aparte a `/centinelli/fps`.

> Los nombres exactos de las entradas del OSC Transmit pueden variar un poco entre Isadora 3 y 4. Lo importante es la IP, el puerto 9300 y la dirección `/centinelli/heartbeat`.

## 2. Reportar valores al dashboard (opcional)

Cualquier OSC a `/centinelli/<clave>` aparece en la tarjeta. Por ejemplo, `/centinelli/escena` con el nombre o número de escena. Para escribir una línea en la bitácora usa `/centinelli/event` con un texto.

Ejemplo: en cada escena, un **Enter Scene Trigger** que dispara un OSC Transmit a `/centinelli/escena` con el número de la escena.

## 3. Recibir comandos del dashboard (opcional)

En `config.json` del agente, `osc.app_port` debe ser el puerto en el que Isadora recibe OSC (por defecto **1234**; revísalo en *Isadora ▸ Preferences ▸ Communications*).

1. *Communications ▸ Stream Setup*: activa OSC, manda desde el dashboard un comando (por ejemplo `/centinelli/cmd/escena 3`) y deja que Isadora lo detecte con **Auto-Detect Input**. Asígnale un canal.
2. En la escena de fondo: **OSC Listener** (ese canal) → **Jump++** o **Jump** para cambiar a la escena indicada.

Direcciones sugeridas:

| Dirección | Valores | Uso |
|---|---|---|
| `/centinelli/cmd/escena` | número | saltar a una escena |
| `/centinelli/cmd/reset` | 1 | volver al estado inicial / demo |
| `/centinelli/cmd/volumen` | 0–100 | ajustar audio |

## 4. config.json para Isadora

Usa `agente/config.isadora.json` como base: `process` = `Isadora.exe` (en Mac, `Isadora`), `launch` con la ruta a tu `.izz` y `restart_grace` de 90 s, porque Isadora tarda más en abrir archivos pesados.
