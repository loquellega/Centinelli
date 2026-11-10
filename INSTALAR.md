# Centinelli · guía de instalación

Hay dos partes:
1. **El servidor**, en tu hosting. Se instala una sola vez.
2. **El agente**, en cada máquina que quieras vigilar (los servers de Resolume, una PC con TouchDesigner o Isadora).

Para el agente hay tres caminos: **por link** (un comando), **por USB** (sin internet para instalar) o **manual**. Los tres dejan lo mismo instalado.

Para usar Centinelli una vez instalado, ver [USO.md](USO.md).

---

## Parte 1 · Servidor (una vez)

1. Descarga el paquete desde https://github.com/loquellega/centinelli (botón *Code ▸ Download ZIP*).
2. Sube la carpeta `servidor/` a tu hosting y renómbrala `centinelli`. Quedaría en `https://hectorcruz.com.mx/centinelli/`.
3. En esa carpeta, copia `config.example.php` como **`config.php`**.
4. Abre `https://hectorcruz.com.mx/centinelli/hash.php`:
   - escribe la contraseña que usarás para entrar al dashboard y pulsa *Generar*;
   - copia los tres valores (`password_hash`, `agent_token`, `cron_key`) a `config.php`.
5. **Borra `hash.php`** del hosting.
6. Telegram (recomendado, para los avisos):
   - En Telegram, habla con **@BotFather** ▸ `/newbot` ▸ ponle nombre. Te da un token: pégalo en `telegram_token`.
   - Escríbele cualquier cosa a tu bot nuevo.
   - Abre `https://api.telegram.org/bot<TOKEN>/getUpdates` y copia el número de `"chat":{"id":…}` a `telegram_chat`.
7. Cron del hosting, cada 2 minutos. Sirve para avisar cuando una máquina deja de reportar:
   ```
   php /home/TU_USUARIO/public_html/centinelli/check.php
   ```
   Si tu hosting no deja usar php por línea de comandos, usa la URL `https://hectorcruz.com.mx/centinelli/check.php?key=CRON_KEY`, con el cron del panel o con cron-job.org.
8. Entra a `https://hectorcruz.com.mx/centinelli/` con tu contraseña. Abajo verás el botón **instalar**.

---

## Parte 2 · Antes de instalar el agente (en cada máquina)

| | |
|---|---|
| Windows | 10 u 11, 64 bits |
| Usuario | el que inicia sesión automáticamente en esa máquina (el agente arranca con su sesión) |
| Red | internet para reportar al dashboard; en salas Resolume, IP fija en la red local |
| Resolume (si aplica) | *Preferences ▸ OSC ▸ OSC Input* activo (7000) y *Preferences ▸ Webserver ▸ Enable Webserver & REST API* activo (8080) |
| Datos a la mano | qué máquina es (Server 1, 2…), la ruta de su archivo de show y, en el Server 1, las IPs de los demás |

---

## Camino A · Por link (recomendado si la máquina tiene internet)

1. En el dashboard, pulsa **instalar** y luego **copiar comando**. El comando ya trae la dirección del servidor y el token.
2. En la máquina: clic derecho en el botón Inicio ▸ **Terminal (Administrador)** o **Windows PowerShell (Administrador)**.
3. Pega el comando y pulsa Enter.
4. Responde las preguntas:

```
== ¿Qué máquina es esta?
   1  Server 1 de una sala Resolume (vigila a los demás y sincroniza)
   2  Otro server de la sala (vigila sus pantallas y su Arena)
   3  Instalación con TouchDesigner
   4  Instalación con Isadora
   5  Resolume suelto (sin sala)
```

   Después pregunta el nombre de la máquina, el programa y el archivo del show. Al Server 1 le pregunta además cuántos servers hay y sus IPs. Casi todo trae una respuesta sugerida entre corchetes: con Enter la aceptas.

5. Al final hace una **prueba** contra el servidor y te dice si el token y la dirección están bien. Luego pregunta si quieres arrancar Centinelli. En menos de un minuto aparece en el dashboard.

Tarda unos 3 minutos: descarga Python portátil (~11 MB), los paquetes y Centinelli.

---

## Camino B · Por USB (sin internet para instalar)

Conviene para el museo: preparas la memoria una vez y la llevas a los 5 servers.

**1. Preparar la USB** en cualquier PC con internet. En el dashboard ▸ **instalar** ▸ sección B, copia el comando. Cambia `E:` por la letra de tu memoria y pégalo en PowerShell. Puede ser PowerShell normal; no necesita administrador.

Deja en `E:\centinelli` Python portátil, los paquetes y Centinelli, unos 40 MB.

**2. En cada máquina:**
1. Conecta la USB y abre la carpeta `centinelli`.
2. Doble clic en **`INSTALAR.bat`**. Windows pedirá permiso de administrador.
3. Responde las mismas preguntas del camino A. El **token** está en el dashboard ▸ instalar ▸ «copiar token». Puedes guardarlo en un `.txt` dentro de la misma USB.

La instalación no descarga nada: copia todo desde la USB a `C:\centinelli`. Internet sólo hace falta después, para que el agente reporte.

> Si actualizas Centinelli, vuelve a preparar la USB para llevar la versión nueva.

---

## Camino C · Manual

1. Descarga el ZIP desde GitHub (o desde el dashboard ▸ instalar ▸ «descargar centinelli.zip») y descomprímelo.
2. Doble clic en `agente/INSTALAR.bat`. Es el mismo instalador: descarga Python durante la instalación.

Para hacerlo todo a mano sin el instalador, con tu propio Python y `pip install psutil mss pillow`, ver `LEEME.md`.

---

## Qué deja instalado

| | |
|---|---|
| `C:\centinelli\` | el agente, `config.json` (tu configuración) y `centinelli.log` (registro) |
| `C:\centinelli\python\` | Python 3.12 portátil con psutil, mss y pillow. **No se instala en Windows** ni toca otros programas o Pythons |
| Tarea «Centinelli agente» | arranca al iniciar sesión, **sin ventana** y con prioridad baja. Si se cierra, se reinicia al minuto. Nunca se detiene por tiempo |
| Firewall (sólo Resolume, si aceptas) | entrada UDP 7000 (OSC) y TCP 8080 (REST API) **sólo en red privada** |

---

## Verificar

- En el dashboard aparece la tarjeta con el nombre que le diste, en verde.
- En el Server 1: el panel de sala muestra a todos los servers con «responde».
- Registro: `C:\centinelli\centinelli.log` (ábrelo con el Bloc de notas).
- Para verlo trabajar en vivo: cierra la tarea en el Administrador de tareas (`pythonw.exe`) y haz doble clic en `C:\centinelli\centinelli.bat`, que abre una ventana con lo que va haciendo. Al terminar, reinicia la sesión para que vuelva la tarea.

## Si algo falla

| Mensaje del instalador | Qué hacer |
|---|---|
| `X  el servidor rechazó el token` | Copia de nuevo el token desde el dashboard ▸ instalar y corrígelo en `C:\centinelli\config.json` (`"token"`) |
| `X  la dirección no parece ser api.php` | Corrige `"server"` en `config.json`: debe terminar en `/centinelli/api.php` |
| `X  no se pudo llegar al servidor` | Sin internet o un proxy del lugar lo bloquea. Centinelli queda instalado y reportará cuando haya conexión |
| `!  La red está como 'Public'` | Configuración ▸ Red e Internet ▸ la red ▸ *Perfil de red: Privada* |
| PowerShell dice que no se pueden ejecutar scripts | Usa el comando completo del dashboard (ya incluye `-ExecutionPolicy Bypass`) o `INSTALAR.bat` |

## Cambiar la configuración después

Edita `C:\centinelli\config.json` con el Bloc de notas y reinicia la sesión, o en el Programador de tareas ▸ «Centinelli agente» ▸ Finalizar y luego Ejecutar. También puedes volver a correr el instalador y responder **n** a «¿Conservarlo?».

## Actualizar

Vuelve a correr el comando del camino A o el `INSTALAR.bat` de una USB actualizada, y responde **s** a «Ya hay un config.json… ¿Conservarlo?». Se conservan tu configuración, la referencia de pantallas y el registro.

## Desinstalar

1. Programador de tareas ▸ «Centinelli agente» ▸ Eliminar.
2. Borra `C:\centinelli`.
3. (Opcional) Firewall de Windows ▸ reglas de entrada ▸ borra «Centinelli - Resolume OSC» y «Centinelli - Resolume REST».

---

> **Estado:** el instalador está escrito para Windows 10/11 con PowerShell 5, pero **todavía no se ha probado en una PC Windows real**. Lo ideal es probarlo primero en tu computadora (ver [PRUEBA-LOCAL.md](PRUEBA-LOCAL.md)) antes de llevarlo al museo.
