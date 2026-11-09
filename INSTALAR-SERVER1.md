# Instalar Centinelli en el Server 1 (MHN · sala inmersiva)

**Centinelli va sólo en el Server 1.** Los servers 2 a 5 no llevan nada instalado: siguen recibiendo OSC en el puerto 7000, como con el objeto de TouchDesigner. La única novedad en ellos es activar el webserver de Resolume, para que el Server 1 pueda ver si están vivos.

## Cómo queda funcionando

1. El técnico prende los proyectores y luego los servers en orden: 1, 2, 3, 4 y 5. Igual que ahora.
2. Windows abre Arena en cada server con su archivo, como ahora. Centinelli **no** abre otro Arena durante el arranque.
3. El Server 1 pregunta cada 15 s a los 5 Arena si ya respondieron. Cuando **los 5 están listos y pasa 1 minuto más**, manda a los 5 la secuencia **Negro → Columna 2 → Columna 1**. Ahí quedan sincronizados.
   - Si alguno no arranca, a los **15 minutos** sincroniza de todos modos con los que estén y te avisa cuál falta.
   - Si prefieres un tiempo fijo (por ejemplo, 8 minutos después de encender el Server 1), está la opción de abajo.
4. Durante el día, el Server 1 vigila los 5:
   - Si uno deja de responder, te llega un aviso por Telegram y el panel lo marca en rojo.
   - Cuando vuelve (porque el técnico lo reinició), la sala **se resincroniza sola** 90 s después.
   - Si el Arena del Server 1 se cierra o se congela, Centinelli lo reabre y resincroniza a los 5.
5. Desde el teléfono ves el panel de la sala: quién responde, en qué columna está cada uno, si hay desfase y la última sincronización. Tienes los botones «sincronizar sala», Columna 1, Columna 2 y Negro.

Encender, apagar y reiniciar las computadoras sigue siendo trabajo del técnico. Centinelli no lo hace.

## Lo que necesitas antes

- Las IPs fijas de los 5 servers (en los ejemplos: 192.168.1.11 a .15).
- Acceso de administrador al Server 1.
- La ruta del archivo de Resolume del Server 1. Clic derecho en el acceso directo de inicio de Windows ▸ Propiedades ▸ Destino.
- El servidor de Centinelli ya instalado en tu hosting y el `agent_token` de su `config.php` (pasos en `PRUEBA-LOCAL.md`, sección 1).

## Paso 1 · Resolume en los 5 servers

En **cada** server (1 a 5):

1. *Arena ▸ Preferences ▸ OSC*: **OSC Input** activo, puerto **7000**. Probablemente ya lo está.
2. *Arena ▸ Preferences ▸ Webserver*: **Enable Webserver & REST API** activo, puerto **8080**.
3. Firewall de Windows: cuando Arena pregunte, «Permitir» en redes privadas. Si no pregunta, abre *Firewall de Windows ▸ Permitir una aplicación* y marca Resolume Arena en **Privada**. Comprueba que la red del museo esté como «Privada».
4. Guarda la composición: *File ▸ Save*.

Para comprobarlo desde el Server 1, abre en el navegador `http://192.168.1.12:8080`. Si sale la página de Resolume, ese server está listo para que lo vigilen. Repite con .13, .14 y .15.

## Paso 2 · Python en el Server 1

1. Descarga Python 3 de python.org (Windows installer, 64-bit).
2. En el instalador, marca **«Add python.exe to PATH»** y luego «Install Now».
3. Abre **Símbolo del sistema** y escribe:
   ```
   pip install psutil mss pillow
   ```

## Paso 3 · Copiar Centinelli

1. Descomprime `centinelli-v0.5.zip` y copia la carpeta `agente` a `C:\centinelli\`.
2. En `C:\centinelli\`, copia `config.server1.json` y renombra la copia a `config.json`.
3. Abre `config.json` con el Bloc de notas y cambia:

| Campo | Qué poner |
|---|---|
| `token` | el `agent_token` de tu `config.php` |
| `app.launch` | la ruta de Arena y la de tu archivo, como en el acceso directo. Usa `/` en lugar de `\`, p. ej. `"D:/MHN/sala.avc"` |
| `sala.servidores` | deja el Server 1 como `127.0.0.1` y pon las IPs reales de los servers 2 a 5 |
| `sala.secuencia` | si tu secuencia usa otras columnas, cámbialas aquí |

Guarda el archivo.

## Paso 4 · Probar a mano

Con los 5 servers encendidos y Arena abierto en todos:

```
cd C:\centinelli
python centinelli_agente.py config.json
```

En la ventana deben salir estas líneas:

- `Sala: vigilando 5 Resolume por su REST API`
- `Sala: Server 1 (127.0.0.1) listo`, …, `Server 5 (…) listo`
- y un minuto después: `Sala: sincronización completa a las …`

En ese momento los 5 Arena hacen Negro, Columna 2 y Columna 1. Abre el dashboard en el teléfono y prueba los botones. Cierra la ventana con **Ctrl+C**.

> Si un server sale como «no responde», revisa el paso 1 (webserver y firewall) y prueba `http://IP:8080` desde el Server 1.

## Paso 5 · Que arranque solo

> El Server 1 también vigila sus **salidas de video** (bloque `pantallas`). Si quieres vigilar las de los servers 2 a 5, ver [PANTALLAS.md](PANTALLAS.md).


1. Clic derecho en `instalar_inicio_windows.bat` ▸ **Ejecutar como administrador**.
2. Deja el acceso directo de Arena en el inicio de Windows, como está. Centinelli no lo abre al encender: sólo lo reabre si se cae después del arranque.
3. Reinicia el Server 1 y repite el encendido normal de la sala para comprobarlo.

Lo que hace Centinelli queda registrado en `C:\centinelli\centinelli.log`.

## Rendimiento

Medido con 5 Resolume simulados, cada uno con una composición de ~1 MB (6 capas × 40 clips):

| | Server 1 (vigila la sala) | Server con sólo pantallas |
|---|---|---|
| CPU del agente | ~0.75 % de un núcleo | ~0.2 % de un núcleo |
| Memoria | ~50 MB | ~37 MB |
| Consultas al REST API de cada Arena | 4 por minuto | 4 por minuto |
| Llamadas a `nvidia-smi` | 2 por minuto | 1 por minuto |

El agente corre con **prioridad baja**: Windows siempre le da preferencia a Resolume. Las capturas de pantalla se toman cada 5 min y sólo del monitor principal (`screenshot.monitor: 1`). Si notas cualquier tirón en las salidas, desactívalas con `"enabled": false`.

## Ajustes

| Quiero… | En `config.json` |
|---|---|
| Un tiempo fijo en vez de «cuando estén todos» | `"esperar_a_todos": false` y `"sync_al_arrancar": 480` (segundos desde que arranca el Server 1) |
| Esperar más tras el último server | `"margen": 120` |
| Otro tope si un server no arranca | `"espera_maxima": 1200` |
| Que no resincronice sola cuando vuelve un server | `"sync_al_volver": 0` |
| Resincronizar todos los días antes de abrir | `"sync_horarios": ["09:55"]` |
| Mandar por broadcast en lugar de IP por IP | en `servidores`, una sola entrada con la IP de broadcast (p. ej. `192.168.1.255`), y `"vigilar": false` |

Recomiendo **IPs explícitas en lugar de broadcast**, por tres razones:
- algunos switches y perfiles de firewall bloquean el broadcast;
- Windows no siempre entrega el broadcast a su propio Arena;
- con IPs, Centinelli puede vigilar a cada server por su nombre.

El envío a las 5 IPs sale en el mismo milisegundo, igual que el broadcast.

## Por qué «cuando estén todos» y no 8 minutos fijos

Los 8 minutos cuentan desde que arranca el Server 1. Si ese día el técnico tarda en prender el Server 5, o un Arena carga lento, la sincronización llega antes de que todos estén listos y ese server queda fuera. Con «cuando estén todos», Centinelli ve llegar uno por uno y sincroniza cuando el último ya cargó su composición. Si un server llega tarde después de la sincronización, la sala se resincroniza sola al detectarlo.
