#!/usr/bin/env python3
"""
Centinelli — agente de monitoreo para instalaciones con TouchDesigner / Isadora.

Corre en la máquina de la instalación. Hace cuatro cosas:
  1. Vigila que el proceso de la app (TouchDesigner.exe, Isadora.exe…) esté vivo
     y lo relanza si se cae.
  2. Escucha un latido OSC que manda la propia app. Si el proceso sigue abierto
     pero el latido deja de llegar, la pieza está congelada → la reinicia.
  3. Cada N segundos manda al servidor: estado, métricas (CPU, RAM, GPU,
     temperatura, disco), valores que reporte la app y una miniatura de pantalla.
  4. Recibe comandos en la respuesta del servidor (reiniciar app, reiniciar PC,
     mandar un OSC a la app, captura inmediata). No abre puertos hacia internet:
     sólo conexiones salientes HTTPS.

Dependencias:  pip install psutil mss pillow
(mss y pillow sólo para la miniatura; sin ellas el agente funciona sin captura.)

Uso:  python centinelli_agente.py config.json
"""

import base64
import io
import json
import os
import platform
import shutil
import socket
import struct
import subprocess
import sys
import threading
import time
import urllib.error
import urllib.request
from collections import deque

try:
    import psutil
except ImportError:
    print("Falta psutil:  pip install psutil")
    sys.exit(1)

try:
    import mss
    from PIL import Image
    HAS_SCREEN = True
except ImportError:
    HAS_SCREEN = False

VERSION = "0.2"


# ───────────────────────────── OSC mínimo (sin dependencias) ─────────────────────────────

def _osc_pad(b):
    return b + b"\x00" * (4 - len(b) % 4)


def _osc_str(s):
    return _osc_pad(s.encode("utf-8"))


def osc_build(address, args=()):
    tags = ","
    payload = b""
    for a in args:
        if isinstance(a, bool):
            tags += "i"
            payload += struct.pack(">i", int(a))
        elif isinstance(a, int):
            tags += "i"
            payload += struct.pack(">i", a)
        elif isinstance(a, float):
            tags += "f"
            payload += struct.pack(">f", a)
        else:
            tags += "s"
            payload += _osc_str(str(a))
    return _osc_str(address) + _osc_str(tags) + payload


def _read_str(data, i):
    end = data.index(b"\x00", i)
    s = data[i:end].decode("utf-8", "replace")
    i = end + 1
    i += (4 - i % 4) % 4
    return s, i


def osc_parse(data):
    """Devuelve lista de (address, [args]). Soporta bundles."""
    out = []
    if data.startswith(b"#bundle"):
        i = 16
        while i + 4 <= len(data):
            size = struct.unpack(">i", data[i:i + 4])[0]
            i += 4
            out.extend(osc_parse(data[i:i + size]))
            i += size
        return out
    try:
        addr, i = _read_str(data, 0)
        args = []
        if i < len(data):
            tags, i = _read_str(data, i)
            for t in tags[1:]:
                if t == "i":
                    args.append(struct.unpack(">i", data[i:i + 4])[0]); i += 4
                elif t == "f":
                    args.append(round(struct.unpack(">f", data[i:i + 4])[0], 4)); i += 4
                elif t == "d":
                    args.append(struct.unpack(">d", data[i:i + 8])[0]); i += 8
                elif t == "h":
                    args.append(struct.unpack(">q", data[i:i + 8])[0]); i += 8
                elif t == "s":
                    s, i = _read_str(data, i); args.append(s)
                elif t == "T":
                    args.append(True)
                elif t == "F":
                    args.append(False)
        out.append((addr, args))
    except Exception:
        pass
    return out


# ───────────────────────────── Agente ─────────────────────────────

class Centinelli:
    def __init__(self, cfg):
        self.cfg = cfg
        app = cfg.get("app", {})
        osc = cfg.get("osc", {})
        self.proc_name = app.get("process", "").lower()
        self.launch_cmd = app.get("launch")            # lista: [exe, archivo]
        self.launch_cwd = app.get("cwd")
        self.autorestart = app.get("autorestart", True)
        self.grace = app.get("restart_grace", 60)      # s tras lanzar antes de exigir latido
        self.max_restarts_hour = app.get("max_restarts_per_hour", 6)

        self.osc_port = osc.get("listen_port", 9300)
        self.hb_timeout = osc.get("heartbeat_timeout", 15)
        self.hb_required = osc.get("heartbeat_required", True)
        self.hb_is_fps = osc.get("heartbeat_is_fps", True)   # TD manda fps; Isadora un contador
        # Latido por HTTP: para apps que no pueden mandar OSC periódico pero tienen API
        # (Resolume Arena/Avenue: Preferences ▸ Webserver ▸ Enable Webserver & REST API)
        self.http_hb = cfg.get("http_heartbeat") or {}
        self.app_host = osc.get("app_host", "127.0.0.1")
        self.app_port = osc.get("app_port", 9301)

        self.last_beat = 0.0
        self.beat_count = 0
        self.app_values = {}          # lo que la app reporte con /centinelli/<clave> valor
        self.events = deque(maxlen=200)
        self.acks = []
        self.last_launch = 0.0
        self.child = None
        self.restart_times = deque(maxlen=50)
        self.paused = False           # "detener app" desde el dashboard pausa el watchdog
        self.want_screenshot = False
        self.last_shot = 0.0
        self.lock = threading.Lock()
        self.sock_out = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)

        psutil.cpu_percent(None)      # inicializa la medición

    # ── registro ──
    def event(self, level, msg):
        e = {"t": int(time.time()), "level": level, "msg": msg}
        with self.lock:
            self.events.append(e)
        print(time.strftime("%H:%M:%S"), level.upper(), msg, flush=True)

    # ── OSC entrante (latido y valores de la app) ──
    def osc_loop(self):
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        s.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
        s.bind(("0.0.0.0", self.osc_port))
        self.event("info", f"Escuchando latido OSC en puerto {self.osc_port}")
        while True:
            try:
                data, _ = s.recvfrom(65535)
            except OSError:
                continue
            for addr, args in osc_parse(data):
                if not addr.startswith("/centinelli"):
                    continue
                key = addr[len("/centinelli/"):] or "heartbeat"
                with self.lock:
                    if key == "heartbeat":
                        self.last_beat = time.time()
                        self.beat_count += 1
                        if args and self.hb_is_fps:
                            self.app_values["fps"] = args[0]
                    elif key == "event":
                        self.events.append({"t": int(time.time()), "level": "app",
                                            "msg": " ".join(str(a) for a in args)})
                    else:
                        self.app_values[key] = args[0] if len(args) == 1 else args

    def http_loop(self):
        url = self.http_hb.get("url")
        every = self.http_hb.get("every", 5)
        timeout = self.http_hb.get("timeout", 4)
        self.event("info", f"Latido por HTTP: {url}")
        while True:
            try:
                with urllib.request.urlopen(url, timeout=timeout) as r:
                    data = r.read()
                with self.lock:
                    self.last_beat = time.time()
                    self.beat_count += 1
                if self.http_hb.get("resolume"):
                    self._resolume_values(data)
            except Exception:
                pass   # sin respuesta: el latido envejece y el watchdog decide
            time.sleep(every)

    def _resolume_values(self, raw):
        """Saca de GET /api/v1/composition lo útil para la tarjeta (todo opcional)."""
        try:
            c = json.loads(raw.decode("utf-8", "replace"))
        except Exception:
            return
        def val(d, *path):
            for k in path:
                if not isinstance(d, dict):
                    return None
                d = d.get(k)
            return d.get("value") if isinstance(d, dict) else d
        vals = {}
        name = val(c, "name")
        if name:
            vals["composición"] = name
        bpm = val(c, "tempocontroller", "tempo")
        if isinstance(bpm, (int, float)):
            vals["bpm"] = round(bpm, 1)
        layers = c.get("layers") or []
        vivos = []
        for i, layer in enumerate(layers, 1):
            for clip in layer.get("clips") or []:
                if val(clip, "connected") in ("Connected", "Connected & previewing"):
                    vivos.append(f"L{i}:{val(clip, 'name') or '?'}")
        if layers:
            vals["clips"] = " · ".join(vivos) if vivos else "ninguno"
        with self.lock:
            self.app_values.update(vals)

    def osc_send(self, address, args=()):
        self.sock_out.sendto(osc_build(address, args), (self.app_host, self.app_port))

    # ── proceso ──
    def find_procs(self):
        if self.child is not None:
            self.child.poll()
        if not self.proc_name:
            return []
        found = []
        for p in psutil.process_iter(["name", "pid"]):
            try:
                if (p.info["name"] or "").lower() == self.proc_name \
                        and p.status() != psutil.STATUS_ZOMBIE:
                    found.append(p)
            except (psutil.NoSuchProcess, psutil.AccessDenied):
                pass
        return found

    def start_app(self, reason=""):
        if not self.launch_cmd:
            self.event("warn", "No hay comando 'launch' en la config; no puedo iniciar la app")
            return
        try:
            kw = {"cwd": self.launch_cwd} if self.launch_cwd else {}
            if os.name == "nt":
                kw["creationflags"] = 0x00000008  # DETACHED_PROCESS
            if self.child is not None:
                self.child.poll()          # recoge el proceso anterior (evita zombis en Mac/Linux)
            self.child = subprocess.Popen(self.launch_cmd, **kw)
            self.last_launch = time.time()
            self.last_beat = 0.0
            self.event("info", f"App iniciada {reason}".strip())
        except Exception as e:
            self.event("error", f"No se pudo iniciar la app: {e}")

    def stop_app(self):
        procs = self.find_procs()
        for p in procs:
            try:
                p.terminate()
            except psutil.Error:
                pass
        gone, alive = psutil.wait_procs(procs, timeout=8)
        for p in alive:
            try:
                p.kill()
            except psutil.Error:
                pass
        self.event("info", f"App detenida ({len(procs)} proceso/s)")

    def restart_app(self, reason):
        now = time.time()
        recent = [t for t in self.restart_times if now - t < 3600]
        if len(recent) >= self.max_restarts_hour:
            self.event("error", f"Demasiados reinicios en una hora ({len(recent)}); "
                                "pauso el reinicio automático. Revisa la máquina.")
            self.paused = True
            return
        self.restart_times.append(now)
        self.event("warn", f"Reiniciando app: {reason}")
        self.stop_app()
        time.sleep(2)
        self.start_app("(reinicio)")

    def app_state(self):
        procs = self.find_procs()
        now = time.time()
        beat_age = now - self.last_beat if self.last_beat else None
        if not self.proc_name:
            running = None
        else:
            running = bool(procs)
        if running is False:
            state = "caida"
        elif self.last_beat == 0 and now - self.last_launch < self.grace:
            state = "iniciando"
        elif beat_age is None or beat_age > self.hb_timeout:
            state = "sin_latido" if self.hb_required else "ok"
        else:
            state = "ok"
        if self.paused:
            state = "pausada" if running is False else state
        return state, procs, beat_age

    def watchdog(self):
        state, procs, beat_age = self.app_state()
        if self.paused or not self.autorestart:
            return state
        now = time.time()
        if state == "caida":
            self.restart_app("el proceso no está corriendo")
        elif state == "sin_latido" and procs and now - self.last_launch > self.grace:
            # Si nunca llegó latido desde que arrancó el agente, no matamos la app sin más
            if self.beat_count == 0 and self.last_launch == 0:
                return state
            if beat_age is None:
                why = f"no llegó ningún latido {int(now - self.last_launch)} s después de abrir"
            else:
                why = f"sin latido desde hace {int(beat_age)} s (¿congelada?)"
            self.restart_app(why)
        return state

    # ── métricas ──
    def metrics(self, procs):
        m = {
            "cpu": psutil.cpu_percent(None),
            "ram": psutil.virtual_memory().percent,
            "uptime_h": round((time.time() - psutil.boot_time()) / 3600, 1),
        }
        try:
            root = os.path.abspath(os.sep)
            m["disk"] = round(psutil.disk_usage(root).percent, 1)
        except Exception:
            pass
        if procs:
            try:
                p = procs[0]
                m["app_ram_mb"] = round(p.memory_info().rss / 1048576)
                m["app_uptime_min"] = round((time.time() - p.create_time()) / 60)
            except psutil.Error:
                pass
        # GPU Nvidia
        if shutil.which("nvidia-smi"):
            try:
                out = subprocess.run(
                    ["nvidia-smi", "--query-gpu=utilization.gpu,temperature.gpu,memory.used,memory.total",
                     "--format=csv,noheader,nounits"],
                    capture_output=True, text=True, timeout=5).stdout.strip().splitlines()[0]
                u, t, mu, mt = [float(x) for x in out.split(",")]
                m.update(gpu=u, gpu_temp=t, vram=round(mu / mt * 100, 1))
            except Exception:
                pass
        # Temperatura CPU (Linux; en Windows psutil no la da)
        try:
            temps = psutil.sensors_temperatures()
            vals = [x.current for arr in temps.values() for x in arr if x.current]
            if vals:
                m["cpu_temp"] = round(max(vals), 1)
        except Exception:
            pass
        return m

    def screenshot(self):
        sc = self.cfg.get("screenshot", {})
        if not HAS_SCREEN or not sc.get("enabled", True):
            return None
        try:
            with mss.mss() as s:
                mon = s.monitors[sc.get("monitor", 0)]  # 0 = todos los monitores
                raw = s.grab(mon)
                img = Image.frombytes("RGB", raw.size, raw.bgra, "raw", "BGRX")
            w = sc.get("width", 480)
            img.thumbnail((w, w * 4))
            buf = io.BytesIO()
            img.save(buf, "JPEG", quality=sc.get("quality", 60))
            return base64.b64encode(buf.getvalue()).decode()
        except Exception as e:
            self.event("warn", f"Captura falló: {e}")
            return None

    # ── comandos desde el dashboard ──
    def run_command(self, c):
        t, args, cid = c.get("type"), c.get("args") or {}, c.get("id")
        result = "ok"
        try:
            if t == "restart_app":
                self.paused = False
                self.restart_times.clear()
                self.restart_app("pedido desde el dashboard")
            elif t == "stop_app":
                self.paused = True
                self.stop_app()
            elif t == "start_app":
                self.paused = False
                self.start_app("desde el dashboard")
            elif t == "screenshot":
                self.want_screenshot = True
            elif t == "osc":
                addr = args.get("address", "")
                vals = args.get("args", [])
                if not addr.startswith("/"):
                    raise ValueError("dirección OSC inválida")
                self.osc_send(addr, vals)
                self.event("info", f"OSC enviado a la app: {addr} {vals}")
            elif t == "reboot":
                self.event("warn", "Reiniciando la computadora (pedido desde el dashboard)")
                self.report()  # avisa antes de irse
                if os.name == "nt":
                    subprocess.Popen(["shutdown", "/r", "/t", "10", "/f"])
                elif platform.system() == "Darwin":
                    subprocess.Popen(["sudo", "shutdown", "-r", "+1"])
                else:
                    subprocess.Popen(["shutdown", "-r", "+1"])
            else:
                result = "desconocido"
        except Exception as e:
            result = f"error: {e}"
            self.event("error", f"Comando {t} falló: {e}")
        self.acks.append({"id": cid, "result": result})

    # ── reporte al servidor ──
    def report(self, state=None):
        st, procs, beat_age = self.app_state()
        state = state or st
        now = time.time()
        sc = self.cfg.get("screenshot", {})
        shot = None
        if self.want_screenshot or now - self.last_shot > sc.get("every", 60):
            shot = self.screenshot()
            self.last_shot = now
            self.want_screenshot = False
        with self.lock:
            events = list(self.events)
            values = dict(self.app_values)
        body = {
            "id": self.cfg["id"],
            "name": self.cfg.get("name", self.cfg["id"]),
            "agent": VERSION,
            "host": socket.gethostname(),
            "os": f"{platform.system()} {platform.release()}",
            "state": state,
            "paused": self.paused,
            "beat_age": round(beat_age, 1) if beat_age is not None else None,
            "metrics": self.metrics(procs),
            "values": values,
            "events": events,
            "acks": self.acks,
            "interval": self.cfg.get("interval", 30),
        }
        if shot:
            body["thumb"] = shot
        req = urllib.request.Request(
            self.cfg["server"], data=json.dumps(body).encode(),
            headers={"Content-Type": "application/json",
                     "X-Centinelli-Token": self.cfg["token"],
                     "User-Agent": f"centinelli-agente/{VERSION}"})
        try:
            with urllib.request.urlopen(req, timeout=20) as r:
                resp = json.loads(r.read().decode() or "{}")
            # enviado: vaciamos eventos y acks ya entregados
            with self.lock:
                for _ in events:
                    if self.events:
                        self.events.popleft()
            self.acks = [a for a in self.acks if a not in body["acks"]]
            for c in resp.get("commands", []):
                self.run_command(c)
            return True
        except urllib.error.HTTPError as e:
            print("Servidor respondió", e.code, e.read()[:200], flush=True)
        except Exception as e:
            print("Sin conexión con el servidor:", e, flush=True)
        return False

    def run(self):
        threading.Thread(target=self.osc_loop, daemon=True).start()
        if self.http_hb.get("url"):
            threading.Thread(target=self.http_loop, daemon=True).start()
        self.event("info", f"Centinelli {VERSION} iniciado en {socket.gethostname()}")
        if self.cfg.get("app", {}).get("start_on_boot") and not self.find_procs():
            self.start_app("al arrancar el agente")
        interval = self.cfg.get("interval", 30)
        poll = self.cfg.get("command_poll", 10)  # cada cuánto pregunta si hay comandos
        last_report = 0
        last_state = None
        while True:
            state = self.watchdog()
            now = time.time()
            # reporta en el intervalo, o antes si cambió el estado (para alertar rápido)
            if now - last_report >= interval or state != last_state or self.acks or self.want_screenshot:
                self.report(state)
                last_report = now
                last_state = state
            elif now - last_report >= poll:
                self.report(state)
                last_report = now
            time.sleep(2)


def main():
    path = sys.argv[1] if len(sys.argv) > 1 else os.path.join(os.path.dirname(__file__), "config.json")
    with open(path, encoding="utf-8") as f:
        cfg = json.load(f)
    for k in ("server", "token", "id"):
        if not cfg.get(k):
            print(f"Falta '{k}' en {path}")
            sys.exit(1)
    Centinelli(cfg).run()


if __name__ == "__main__":
    main()
