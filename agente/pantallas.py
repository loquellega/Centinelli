"""
Centinelli · vigilancia de salidas de video (Windows).

Problema que resuelve: con varias salidas a proyectores, cuando un proyector entra en
standby, se reinicia o el HDMI pierde contacto, Windows reacomoda los displays
(\\\\.\\DISPLAY3 pasa a ser otra salida, cambian posiciones) y las salidas de
Resolume dejan de estar en orden.

Qué hace:
  - leer()       → lista de salidas activas: puerto físico, nombre, posición, resolución, Hz
  - comparar()   → diferencias contra una disposición de referencia
  - restaurar()  → vuelve a poner cada salida (identificada por su PUERTO, no por su número
                   de display) en la posición, resolución y frecuencia de la referencia

Identidad de cada salida: el "device interface name" del monitor, que incluye el UID del
puerto de la tarjeta (…#UID4352#…). Así distingue proyectores idénticos o emuladores de
EDID iguales, y no cambia aunque Windows renumere los displays.

Sin dependencias: ctypes sobre user32.dll. En macOS/Linux leer() devuelve None.
"""

import json
import os
import re
import sys

ES_WINDOWS = sys.platform == "win32"

if ES_WINDOWS:
    import ctypes
    from ctypes import wintypes

    class DISPLAY_DEVICEW(ctypes.Structure):
        _fields_ = [("cb", wintypes.DWORD), ("DeviceName", wintypes.WCHAR * 32),
                    ("DeviceString", wintypes.WCHAR * 128), ("StateFlags", wintypes.DWORD),
                    ("DeviceID", wintypes.WCHAR * 128), ("DeviceKey", wintypes.WCHAR * 128)]

    class POINTL(ctypes.Structure):
        _fields_ = [("x", wintypes.LONG), ("y", wintypes.LONG)]

    class DEVMODEW(ctypes.Structure):
        # variante "display" de DEVMODEW
        _fields_ = [("dmDeviceName", wintypes.WCHAR * 32), ("dmSpecVersion", wintypes.WORD),
                    ("dmDriverVersion", wintypes.WORD), ("dmSize", wintypes.WORD),
                    ("dmDriverExtra", wintypes.WORD), ("dmFields", wintypes.DWORD),
                    ("dmPosition", POINTL), ("dmDisplayOrientation", wintypes.DWORD),
                    ("dmDisplayFixedOutput", wintypes.DWORD), ("dmColor", ctypes.c_short),
                    ("dmDuplex", ctypes.c_short), ("dmYResolution", ctypes.c_short),
                    ("dmTTOption", ctypes.c_short), ("dmCollate", ctypes.c_short),
                    ("dmFormName", wintypes.WCHAR * 32), ("dmLogPixels", wintypes.WORD),
                    ("dmBitsPerPel", wintypes.DWORD), ("dmPelsWidth", wintypes.DWORD),
                    ("dmPelsHeight", wintypes.DWORD), ("dmDisplayFlags", wintypes.DWORD),
                    ("dmDisplayFrequency", wintypes.DWORD), ("dmICMMethod", wintypes.DWORD),
                    ("dmICMIntent", wintypes.DWORD), ("dmMediaType", wintypes.DWORD),
                    ("dmDitherType", wintypes.DWORD), ("dmReserved1", wintypes.DWORD),
                    ("dmReserved2", wintypes.DWORD), ("dmPanningWidth", wintypes.DWORD),
                    ("dmPanningHeight", wintypes.DWORD)]

    user32 = ctypes.WinDLL("user32", use_last_error=True)
    user32.EnumDisplayDevicesW.argtypes = [wintypes.LPCWSTR, wintypes.DWORD,
                                           ctypes.POINTER(DISPLAY_DEVICEW), wintypes.DWORD]
    user32.EnumDisplaySettingsW.argtypes = [wintypes.LPCWSTR, wintypes.DWORD, ctypes.POINTER(DEVMODEW)]
    user32.ChangeDisplaySettingsExW.argtypes = [wintypes.LPCWSTR, ctypes.POINTER(DEVMODEW),
                                                wintypes.HWND, wintypes.DWORD, wintypes.LPVOID]

ATTACHED = 0x1
PRIMARY = 0x4
MIRROR = 0x8
ENUM_CURRENT = 0xFFFFFFFF          # ENUM_CURRENT_SETTINGS (-1)
EDD_GET_DEVICE_INTERFACE_NAME = 0x1
DM_POSITION = 0x20
DM_PELSWIDTH = 0x80000
DM_PELSHEIGHT = 0x100000
DM_DISPLAYFREQUENCY = 0x400000
CDS_UPDATEREGISTRY = 0x1
CDS_NORESET = 0x10000000
DISP_OK = 0


def _clave_puerto(interface_name, monitor_id):
    """Clave estable por puerto físico: 'EPS1234#UID4352' a partir del interface name."""
    s = interface_name or monitor_id or ""
    m = re.search(r"DISPLAY#([^#]+)#[^#]*?(UID\d+)", s, re.I)
    if m:
        return f"{m.group(1)}#{m.group(2)}".upper()
    return s.upper() or None


# Simulación para pruebas sin Windows: CENTINELLI_PANTALLAS_SIM=/ruta/salidas.json
SIM = os.environ.get("CENTINELLI_PANTALLAS_SIM")


def leer():
    """Salidas activas (conectadas al escritorio). None si no es Windows."""
    if SIM:
        try:
            with open(SIM, encoding="utf-8") as f:
                return json.load(f)
        except Exception:
            return []
    if not ES_WINDOWS:
        return None
    out = []
    i = 0
    while True:
        ad = DISPLAY_DEVICEW()
        ad.cb = ctypes.sizeof(ad)
        if not user32.EnumDisplayDevicesW(None, i, ctypes.byref(ad), 0):
            break
        i += 1
        if ad.StateFlags & MIRROR:
            continue
        # monitor conectado a este adaptador (nombre + interface name con el UID del puerto)
        mon = DISPLAY_DEVICEW()
        mon.cb = ctypes.sizeof(mon)
        tiene_mon = user32.EnumDisplayDevicesW(ad.DeviceName, 0, ctypes.byref(mon),
                                               EDD_GET_DEVICE_INTERFACE_NAME)
        if not (ad.StateFlags & ATTACHED):
            if tiene_mon and mon.DeviceID:
                # conectado físicamente pero fuera del escritorio
                out.append({"id": _clave_puerto(mon.DeviceID, ""), "display": ad.DeviceName,
                            "nombre": mon.DeviceString or "monitor", "activa": False})
            continue
        dm = DEVMODEW()
        dm.dmSize = ctypes.sizeof(dm)
        if not user32.EnumDisplaySettingsW(ad.DeviceName, ENUM_CURRENT, ctypes.byref(dm)):
            continue
        out.append({
            "id": _clave_puerto(mon.DeviceID if tiene_mon else "", ad.DeviceID) or ad.DeviceName,
            "display": ad.DeviceName,                 # \\.\DISPLAY3 (puede cambiar)
            "nombre": (mon.DeviceString if tiene_mon else "") or ad.DeviceString,
            "x": dm.dmPosition.x, "y": dm.dmPosition.y,
            "w": dm.dmPelsWidth, "h": dm.dmPelsHeight, "hz": dm.dmDisplayFrequency,
            "principal": bool(ad.StateFlags & PRIMARY), "activa": True,
        })
    return out


def comparar(actual, ref):
    """Diferencias entre la disposición actual y la de referencia.
    Devuelve (lista de textos, se_puede_restaurar)."""
    if actual is None or not ref:
        return [], False
    act = {d["id"]: d for d in actual if d.get("activa")}
    inact = {d["id"]: d for d in actual if not d.get("activa")}
    difs = []
    faltan = []
    for n, r in enumerate(ref, 1):
        etiqueta = f"Salida {n} ({r.get('nombre') or r['id']})"
        a = act.get(r["id"])
        if a is None:
            if r["id"] in inact:
                difs.append(f"{etiqueta}: conectada pero fuera del escritorio")
            else:
                difs.append(f"{etiqueta}: no aparece (¿proyector apagado o cable?)")
                faltan.append(r["id"])
            continue
        if (a["x"], a["y"]) != (r["x"], r["y"]):
            difs.append(f"{etiqueta}: en {a['x']},{a['y']} y debería estar en {r['x']},{r['y']}")
        if (a["w"], a["h"]) != (r["w"], r["h"]):
            difs.append(f"{etiqueta}: {a['w']}×{a['h']} y debería ser {r['w']}×{r['h']}")
        if r.get("hz") and a["hz"] != r["hz"]:
            difs.append(f"{etiqueta}: {a['hz']} Hz y debería ser {r['hz']} Hz")
    ids_ref = {r["id"] for r in ref}
    for a in act.values():
        if a["id"] not in ids_ref:
            difs.append(f"Salida nueva no esperada: {a.get('nombre') or a['id']} en {a['x']},{a['y']}")
    # restaurable si todas las salidas de la referencia están presentes (activas o conectadas)
    return difs, (bool(difs) and not faltan)


def restaurar(ref, actual=None):
    """Pone cada salida de la referencia en su posición/resolución/Hz. Devuelve (ok, detalle)."""
    if SIM:
        act = {d["id"]: d for d in leer()}
        faltan = [r for r in ref if r["id"] not in act]
        for r in ref:
            if r["id"] in act:
                act[r["id"]].update({k: r[k] for k in ("x", "y", "w", "h", "hz")}, activa=True)
        with open(SIM, "w", encoding="utf-8") as f:
            json.dump(list(act.values()), f)
        return not faltan, "simulación"
    if not ES_WINDOWS:
        return False, "sólo en Windows"
    actual = actual if actual is not None else leer()
    por_id = {d["id"]: d for d in actual or []}
    hechos, errores = [], []
    for r in ref:
        a = por_id.get(r["id"])
        if a is None:
            errores.append(f"{r.get('nombre') or r['id']}: no está conectada")
            continue
        dm = DEVMODEW()
        dm.dmSize = ctypes.sizeof(dm)
        user32.EnumDisplaySettingsW(a["display"], ENUM_CURRENT, ctypes.byref(dm))
        dm.dmPosition.x, dm.dmPosition.y = int(r["x"]), int(r["y"])
        dm.dmPelsWidth, dm.dmPelsHeight = int(r["w"]), int(r["h"])
        dm.dmFields = DM_POSITION | DM_PELSWIDTH | DM_PELSHEIGHT
        if r.get("hz"):
            dm.dmDisplayFrequency = int(r["hz"])
            dm.dmFields |= DM_DISPLAYFREQUENCY
        res = user32.ChangeDisplaySettingsExW(a["display"], ctypes.byref(dm), None,
                                              CDS_UPDATEREGISTRY | CDS_NORESET, None)
        (hechos if res == DISP_OK else errores).append(
            f"{r.get('nombre') or r['id']}" + ("" if res == DISP_OK else f": código {res}"))
    # aplica todos los cambios juntos
    res = user32.ChangeDisplaySettingsExW(None, None, None, 0, None)
    if res != DISP_OK:
        errores.append(f"aplicar cambios: código {res}")
    return not errores, ("restauradas: " + ", ".join(hechos)) + (("; errores: " + "; ".join(errores)) if errores else "")


def cargar_ref(ruta):
    try:
        with open(ruta, encoding="utf-8") as f:
            d = json.load(f)
        return d.get("salidas") if isinstance(d, dict) else d
    except Exception:
        return None


def guardar_ref(ruta, salidas):
    activas = sorted([d for d in salidas if d.get("activa")], key=lambda d: (d["y"], d["x"]))
    limpio = [{k: d[k] for k in ("id", "nombre", "x", "y", "w", "h", "hz", "principal")} for d in activas]
    with open(ruta, "w", encoding="utf-8") as f:
        json.dump({"salidas": limpio}, f, ensure_ascii=False, indent=2)
    return limpio
