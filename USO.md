# Centinelli · cómo usarlo

Guía de uso diario, para ti y para el técnico de sala. Para instalarlo, ver `INSTALAR-SERVER1.md` (sala MHN) o `PRUEBA-LOCAL.md` (prueba en una computadora).

Versión 0.6 · Colección Alchemy of Meaning (Alquimia del Significado)

---

## 1. Entrar

Abre en el navegador, en el teléfono o la computadora:

```
https://hectorcruz.com.mx/centinelli/
```

Escribe la contraseña. La sesión dura 30 días en ese navegador. En el teléfono conviene agregarlo a la pantalla de inicio (Compartir ▸ Agregar a inicio).

La página se actualiza sola cada 8 segundos. No hace falta recargarla.

---

## 2. Qué ves

### Barra inferior (dock)

`2 instalaciones · todo en marcha · 13:42:05`

- **todo en marcha:** no hay ninguna falla.
- **1 con fallas** (en rojo): una tarjeta tiene problemas. La pestaña del navegador también lo muestra: `(1) Centinelli`.
- **mural:** deja sólo los visores y las lecturas, sin botones. Sirve para una pantalla de monitoreo fija. Tecla `h`.
- **alto:** contraste alto, blanco sobre negro, para pantallas con mucha luz. Tecla `c`.
- **salir:** cierra la sesión.

### Panel de sala

Es la ventana ancha de arriba: **sala · MHN · Sala inmersiva**.

| Elemento | Qué significa |
|---|---|
| `5/5 responden` | cuántos Resolume contestan |
| Punto **verde** | todo bien |
| Punto **amarillo** | esperando el arranque, o hay desfase de columnas |
| Punto **rojo** | algún server no responde |
| Fichas `Server 1 … Server 5` | estado de cada uno, su columna activa y su IP |
| `columna 1 en todos` | todos están en la misma columna |
| `▲ desfase: Server 3 en 2` | ese server está en otra columna que el resto |
| `última sync 09:12:40` | hora de la última sincronización |
| Bitácora | lo último que pasó en la sala |

### Tarjeta de cada server

- **Visor:** una miniatura de la pantalla principal, cada 5 min. Las esquinas muestran el latido, la temperatura, el nombre de la máquina y la antigüedad de la captura.
- **Estado** (arriba a la derecha):

| Estado | Qué pasa |
|---|---|
| en marcha (verde) | Arena abierto y respondiendo |
| iniciando | arrancando o reabriéndose; es normal en los primeros minutos |
| sin latido | Arena está abierto pero no responde; Centinelli lo va a reiniciar |
| app caída | Arena se cerró; Centinelli lo está reabriendo |
| detenida | alguien pulsó «detener»; no se reabre solo |
| sin conexión | el server no reporta: apagado, sin red o sin internet |

- **Medidores:** CPU, GPU, RAM, VRAM, disco y temperatura. Se ponen ámbar cuando suben demasiado.
- **Valores de Resolume:** composición, BPM, clips conectados y columna.
- **Salidas de video:** un mapa de las salidas (ver sección 5).
- **Bitácora · comandos:** al tocarla se despliega el historial de esa máquina.

---

## 3. Uso diario de la sala

### Al abrir la sala (técnico)

1. Prender los proyectores.
2. Prender los servers en orden: 1, 2, 3, 4, 5.
3. Esperar. Arena tarda unos 8 minutos en arrancar en cada server.
4. **No hace falta hacer nada más.** Cuando los 5 terminan de arrancar, Centinelli espera un minuto y sincroniza la sala: Negro → Columna 2 → Columna 1.

En el teléfono verás:
- los servers pasando de «arrancando» a «responde», uno por uno;
- luego, en la bitácora: `sincronización completa … (arranque: los 5 servidores listos)`;
- el panel en verde, con `columna 1 en todos`.

Si un server no arranca, a los 15 minutos Centinelli sincroniza a los que estén y avisa cuál falta. Cuando ese server por fin arranca, la sala se resincroniza sola.

### Durante el día

No hay que hacer nada. Centinelli:
- reabre Arena si se cierra o se congela;
- resincroniza la sala cuando un server vuelve;
- regresa las salidas de video a su lugar si Windows las reacomoda;
- avisa por Telegram de cualquier cosa que no pueda resolver solo.

### Al cerrar la sala

Apagar como siempre. Vas a recibir avisos de «sin conexión»; son normales. Si te molestan, puedes silenciar el chat de Telegram en las horas de cierre.

---

## 4. Botones

### En el panel de sala (actúan sobre los 5 servers)

| Botón | Qué hace | Cuándo usarlo |
|---|---|---|
| **sincronizar sala** | Negro → Columna 2 → Columna 1 en los 5 | Si ves desfase o las pantallas se ven fuera de tiempo. **Las 5 pantallas vuelven a empezar** |
| **Columna 1 / Columna 2** | Dispara esa columna en los 5 | Pruebas o cambio de contenido |
| **Negro** | Apaga todos los clips en los 5 | Mantenimiento, emergencias |

Todos piden confirmación. La orden llega en 5 a 10 segundos (el server la recoge en su siguiente consulta).

### En la tarjeta de un server (sólo esa máquina)

| Botón | Qué hace |
|---|---|
| **reiniciar app** | Cierra y vuelve a abrir Arena. Si es el Server 1, la sala se resincroniza después |
| **detener** | Cierra Arena y **pausa** el reinicio automático. Úsalo para dar mantenimiento sin que Centinelli lo reabra |
| **iniciar** | Vuelve a abrir Arena y reactiva el reinicio automático |
| **captura** | Pide una miniatura nueva de la pantalla ahora mismo |
| Campo **osc** | Manda un mensaje OSC a Arena (p. ej. `/composition/columns/3/connect` y valor `1`) |

**Reiniciar la computadora no está disponible** desde el dashboard. Encender, apagar o reiniciar un server lo hace el técnico.

---

## 5. Salidas de video

Cada server con el agente muestra un mapa de sus salidas:

- **Contorno punteado:** dónde debe estar cada salida (la referencia).
- **Rectángulo verde:** la salida está en su lugar.
- **Rectángulo rojo:** está mal. El número dice qué salida es. Si ves `1 2 4 3`, la 3 y la 4 quedaron intercambiadas.
- Debajo aparece qué está mal: posición, resolución o Hz.

| Botón | Qué hace |
|---|---|
| **restaurar pantallas** | Regresa las salidas a la referencia y reabre Arena. Normalmente lo hace solo a los 15 s |
| **guardar como referencia** | Toma la disposición **actual** como la correcta. **Sólo cuando todo se vea bien** en los proyectores |

Si dice **«no aparece (¿proyector apagado o cable?)»**, Windows dejó de ver esa salida. No se arregla desde aquí. El técnico debe revisar:
1. que el proyector esté encendido y no en standby;
2. el HDMI y el emulador de EDID de esa salida;
3. si nada de eso funciona, reiniciar ese server.

---

## 6. Avisos de Telegram

| Aviso | Qué hacer |
|---|---|
| 🔴 *Server 3 (192.168.1.13) no responde* | Revisar si el server está prendido y Arena abierto. Cuando vuelve, la sala se resincroniza sola |
| 🔴 *Sin conexión con la máquina* | El Server 1 no reporta: puede estar apagado o sin internet. Fuera de horario es normal |
| 🔴 *La app no está corriendo / no manda latido* | Centinelli ya está reiniciando Arena. Si se repite, revisar la máquina |
| ⚠️ *Demasiados reinicios en una hora* | Centinelli dejó de reiniciar Arena. Algo falla de fondo: revisar la máquina en persona |
| 🔴 *Salidas de video: … no aparece* | Ver sección 5 |
| ⚠️ *Pantallas: ya restauré 6 veces en una hora* | Un proyector, cable o emulador está fallando una y otra vez. Revisarlo físicamente |
| 🔴 *GPU caliente / disco casi lleno* | Revisar ventilación o liberar espacio |
| 🟢 *… resuelto* | El problema se resolvió |

Un problema sólo se avisa si dura más de 45 segundos, así que los reinicios cortos no generan mensajes.

---

## 7. Situaciones frecuentes

**Las pantallas se ven fuera de tiempo, pero el panel dice `columna 1 en todos`.**
Pulsa **sincronizar sala**. Si pasa todos los días, agrega una resincronización antes de abrir (`sync_horarios`, ver `INSTALAR-SERVER1.md`).

**Un proyector muestra la imagen de otro.**
Mira el mapa de salidas de ese server. Si está en rojo, **restaurar pantallas**. Si está en verde pero se ve mal, la referencia quedó mal guardada: acomoda las pantallas en Windows y pulsa **guardar como referencia**.

**Hay que darle mantenimiento a un server sin que Centinelli reabra Arena.**
Pulsa **detener** en su tarjeta. Al terminar, pulsa **iniciar**.

**El panel dice «sin conexión con el server que vigila».**
El Server 1 no llega al servidor web. La sala puede estar funcionando bien; sólo no la estás viendo. Revisa el internet del museo o el Server 1.

**Un server aparece «arrancando» durante mucho tiempo.**
Su Arena no responde por el REST API. Revisa que esté abierto y que su webserver esté activo (*Preferences ▸ Webserver*).

---

## 8. Dónde se guarda cada cosa

| Qué | Dónde |
|---|---|
| Configuración de cada server | `C:\centinelli\config.json` |
| Registro de todo lo que hace el agente | `C:\centinelli\centinelli.log` |
| Disposición de referencia de las pantallas | `C:\centinelli\pantallas_referencia.json` |
| Contraseña, token y Telegram | `config.php` en el hosting |
| Historial de cada instalación | carpeta `data/` en el hosting (no borrarla al actualizar) |

Para actualizar Centinelli, reemplaza los archivos del agente y del servidor. **Conserva `config.json`, `config.php`, `pantallas_referencia.json` y la carpeta `data/`.**
