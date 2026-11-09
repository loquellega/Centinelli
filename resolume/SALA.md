# Sala: sincronizar varios Resolume desde Centinelli, sin TouchDesigner

Pensado para el caso del Museo de Historia Natural: **5 servidores con Resolume Arena** que tienen que arrancar sincronizados, mantenerse vivos y volver a sincronizarse si alguno se cae.

## La idea

Cada servidor ya tiene su agente Centinelli, y los agentes están dentro de la red del museo. Cualquiera de ellos puede mandar la secuencia de sincronización a los 5 Arena al mismo tiempo, igual que lo hacía TouchDesigner. La secuencia es Detener todo, luego Columna 2 y luego Columna 1, con 1 s de pausa entre pasos.

```
            dashboard (teléfono)
                 │  «sincronizar sala»
                 ▼
        hosting · Centinelli
                 │  se lo encarga a un agente en línea (el coordinador si está vivo)
   ┌─────────────┼───────────────────────────────┐   red del museo
   │  Server 1 (coordina)  Server 2 … Server 5   │
   │      agente ──OSC──► 192.168.1.11…15 :7000  │   los 5 Arena reciben el mismo
   │                                             │   mensaje en el mismo milisegundo
   └─────────────────────────────────────────────┘
```

| Momento | Quién sincroniza | Parámetro |
|---|---|---|
| Se encienden las máquinas | **sólo el coordinador**, cuando ya arrancaron todos | `sync_al_arrancar` (s) |
| Un Arena se cae o se congela y el agente lo reabre | la máquina que lo reabrió | `sync_al_reiniciar` (s) |
| Lo pides desde el dashboard | el coordinador; si está apagado, cualquier otro en línea | botón «sincronizar sala» |
| Horario fijo (opcional) | el coordinador | `sync_horarios`, p. ej. `["09:55"]` |

Sólo el coordinador sincroniza al encender. Si los 5 lo hicieran, la imagen se cortaría 5 veces seguidas.

**Toda sincronización reinicia la imagen en las 5 pantallas.** Si un Arena se cae a media visita, el público verá un corte y un reinicio. Es el precio de volver a estar sincronizados, y es lo mismo que pasaba con el script de TD.

## En el dashboard

El panel **sala** reúne los 5 servidores. Muestra:
- el estado de cada uno y la columna activa en Arena;
- un aviso **desfase** si alguno está en otra columna;
- la última sincronización;
- los botones «sincronizar sala», Columna 1, Columna 2 y Negro (los botones van a los 5).

Debajo siguen las tarjetas de cada server con sus métricas y su miniatura.

«Reiniciar PC» ya no aparece. Encender o reiniciar una computadora queda en manos del técnico de sala. Si algún día lo quieres activar, cada máquina lo permite con `"permitir_reinicio_pc": true`.

## Configuración (los 5 `config.json`)

Parte de `agente/config.sala.json`. Lo único que cambia entre las 5 máquinas es:

| | `id` | `name` | `sala.coordinador` |
|---|---|---|---|
| Server 1 | `mhn-srv1` | MHN Server 1 | `true` |
| Server 2 | `mhn-srv2` | MHN Server 2 | `false` |
| Server 3 | `mhn-srv3` | MHN Server 3 | `false` |
| Server 4 | `mhn-srv4` | MHN Server 4 | `false` |
| Server 5 | `mhn-srv5` | MHN Server 5 | `false` |

Todo lo demás queda **idéntico en los 5**: `sala.id`, la lista `servidores`, la `secuencia` y los `botones`.

- `servidores`: las IPs de los 5 servidores, incluida la de la propia máquina.
- `secuencia`: puedes cambiar columnas, agregar pasos o cambiar las pausas (`{"pausa": 1}`, en segundos).
- `sync_al_arrancar: 120`: tiempo que espera el coordinador después de encender, para que los 5 Arena ya tengan cargada su composición. Ajústalo al servidor más lento.
- `sync_al_reiniciar: 70`: lo que tarda un Arena en reabrir y cargar.
- `command_poll: 5`: cada cuántos segundos pregunta el agente si hay órdenes. Con 5, el botón tarda como máximo 5 s.

## Red y Resolume (en cada server)

1. **IP fija** en cada servidor, en la misma red (p. ej. 192.168.1.11–15). Mejor como reserva en el router.
2. Arena:
   - *Preferences ▸ OSC ▸ OSC Input* activo, puerto 7000.
   - *Preferences ▸ Webserver ▸ Enable Webserver & REST API* activo, puerto 8080. Es el latido.
   - La composición del show guardada, para que Arena la reabra al iniciar.
3. Firewall de Windows: permitir **UDP 7000 entrante** para Arena. Windows lo pregunta la primera vez; responde «Permitir» para redes privadas y marca la red del museo como privada.
4. Las salidas de internet sólo necesitan HTTPS hacia tu hosting.

## Antes de la apertura: prueba de aceptación

1. Enciende los 5. A los 2 minutos, el panel debe mostrar 5/5 en marcha, columna 1 en todos y una sincronización hecha por el coordinador.
2. Pulsa «Columna 2»: los 5 cambian. Luego «sincronizar sala»: los 5 hacen Negro, Columna 2 y Columna 1.
3. Cierra Arena en el Server 3 desde el Administrador de tareas. El agente lo reabre; a los 70 s, el Server 3 sincroniza la sala.
4. Desconecta la red del Server 1. Debe aparecer «sin conexión» y llegarte un aviso a Telegram. «Sincronizar sala» se encarga entonces a otro server.
5. Apaga y enciende todo de nuevo para confirmar el arranque en frío.

## Si se desfasan con las horas

La sincronización arranca los 5 en el mismo instante, pero si los clips son largos los relojes de cada máquina pueden ir separándose a lo largo del día. Si lo notas, agrega una resincronización en un momento sin público, por ejemplo `"sync_horarios": ["09:55", "13:30"]`.

## ¿Y el script de TouchDesigner?

Ya no hace falta para esta sala. Lo dejo en `touchdesigner/resolume_sync_v3.py` y en [SINCRONIZACION.md](SINCRONIZACION.md) por si en otro montaje TD ya es el maestro del show.
