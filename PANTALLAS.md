# Vigilancia de salidas de video (pantallas)

## El problema

Con varias salidas a proyectores (NVIDIA GeForce, como las 4090), cuando un proyector entra en standby, se reinicia o el HDMI pierde contacto un instante, Windows **reacomoda los displays**: cambia su orden, su posición o su frecuencia. Resolume sigue mandando cada salida a «Display 3», pero ahora Display 3 es otro proyector. Las imágenes quedan fuera de secuencia.

Las tarjetas profesionales (RTX/Quadro) pueden fijar el EDID desde el driver. Las GeForce no, y por eso la solución de hardware son los **emuladores de EDID**, como los que ya pusiste en el Server 1.

## Lo que hace Centinelli

| Situación | Qué hace |
|---|---|
| Están todas las salidas pero **en otro orden, posición, resolución o Hz** | Avisa y, si `restaurar` está activo, **las vuelve a poner como en la referencia**. Después reabre Arena para que tome las pantallas, y la sala se resincroniza sola (el Server 1 ve volver al Arena) |
| **Falta una salida** (Windows ya no la ve) | Avisa por Telegram con el nombre del proyector. No lo puede arreglar: es hardware. Hay que revisar el proyector, el cable o el emulador de EDID |
| Restaura más de 6 veces en una hora | Deja de intentar y avisa: algo físico está fallando |

Cada salida se reconoce por el **puerto físico de la tarjeta**, no por su número de display, que es justo lo que Windows cambia. Así distingue proyectores idénticos y emuladores de EDID iguales.

En el dashboard, cada server muestra un **mapa de sus salidas**:
- **contorno punteado:** dónde debe ir cada salida (la referencia);
- **rectángulo verde:** la salida está bien;
- **rectángulo rojo:** está fuera de lugar. El número indica qué salida de la referencia es, así se ve de inmediato si «la 3 está donde va la 4».

Desde ahí puedes **restaurar** a mano o **guardar la disposición actual como referencia**.

## Instalación

Necesita el agente en cada server que quieras vigilar.

- **Server 1:** ya viene en `config.server1.json` (bloque `pantallas`).
- **Servers 2 a 5:** una instalación ligera con `config.servers2-5.json`. Los pasos son los de `INSTALAR-SERVER1.md` (Python, carpeta `C:\centinelli`, tarea de inicio), cambiando sólo:
  - `id` y `name` (`mhn-server2` / «MHN Server 2», etc.);
  - `token`;
  - la ruta de Arena y del archivo en `app.launch`.

Estos agentes **no sincronizan**: sólo vigilan sus pantallas y su Arena. Aparecen en el dashboard debajo del panel de la sala.

## La referencia

La **disposición de referencia** es la foto de cómo deben estar las salidas. Se guarda en `C:\centinelli\pantallas_referencia.json`.

- La primera vez, el agente la guarda solo, al terminar `espera_arranque` (10 min). **Revisa en el dashboard que esté bien**: todas las salidas en su sitio y a la frecuencia correcta.
- Si no lo está, o si cambias la instalación, acomoda las pantallas en Windows y pulsa **«guardar como referencia»** en la tarjeta de ese server.
- Mientras corre `espera_arranque`, no restaura ni avisa, porque al encender es normal que los proyectores tarden.

## Ajustes (`pantallas` en `config.json`)

| Campo | Qué hace |
|---|---|
| `vigilar` | activa la vigilancia (`true`) |
| `cada` | cada cuántos segundos revisa (5; cuesta menos de 1 ms) |
| `restaurar` | `true` = restaura sola; `false` = sólo avisa y tú decides con el botón |
| `estable` | segundos que debe durar el desorden antes de restaurar (15). Evita actuar a media reconexión |
| `tras_restaurar` | `"reiniciar_app"` (recomendado) o `"nada"` |
| `max_restauraciones_hora` | tope antes de rendirse y avisar (6) |

## Recomendación

Centinelli **no sustituye a los emuladores de EDID**: los complementa. El emulador evita que Windows pierda la salida. Centinelli te avisa cuando aun así pasa algo, lo corrige si sólo cambió el orden, y deja registro con hora en la bitácora. Ese registro te dice si el Server 5 falla cuando un proyector entra en standby, a ciertas horas, o siempre en el mismo puerto.

## Limitaciones

- Sólo Windows. Usa las funciones de pantallas de Windows (`EnumDisplayDevices`, `ChangeDisplaySettingsEx`), sin instalar nada extra.
- **No se probó todavía en una PC Windows con proyectores reales.** La lógica se probó con salidas simuladas: reordenamiento, cambio de Hz, salida desaparecida y restauración. Pruébalo primero en tu máquina con `"restaurar": false`. Desordena las pantallas a mano en Configuración de Windows y pulsa «restaurar pantallas» para ver que las regrese.
