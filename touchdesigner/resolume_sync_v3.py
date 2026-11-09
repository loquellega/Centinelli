# Setup v3: sincronización de Resolume por OSC a varias IPs + localhost,
#           integrada con Centinelli
#
# USO:
# 1. En el COMP del Server 1 (el que emite), crea un Text DAT y pega este script.
#    Si ya corriste la v1 en ese mismo COMP, puedes correr esta encima: conserva
#    tus parámetros y agrega los nuevos.
# 2. Click derecho sobre el Text DAT > Run Script.
# 3. Borra el Text DAT. Si venías de la v1, también puedes borrar 'oscout1'
#    (la v2 ya no lo usa: manda el OSC directo por UDP).
#
# QUÉ HACE:
# Cada comando (Detener todo, Columna 2, Columna 1) se manda a TODAS las IPs
# de la lista y además a 127.0.0.1 si "Enviar a localhost" está activo.
#
# NUEVO EN v3 (Centinelli):
# - Si "Reportar a Centinelli" está activo, cada envío y cada sincronización
#   completa quedan en la bitácora del dashboard, con cuántos destinos recibieron
#   el mensaje y cuáles fallaron. La tarjeta muestra la hora de la última sync.
# - El COMP 'centinelli' de este mismo proyecto puede disparar la sincronización
#   desde el dashboard o desde otra máquina: /centinelli/cmd/sync,
#   /centinelli/cmd/detener y /centinelli/cmd/columna <n> (ver SINCRONIZACION.md).
#   No hace falta configurar nada: el COMP busca este 'exec_sync' por su nombre.
# - Puedes correr esta v3 encima de la v2: conserva tus parámetros.

comp = parent()

# ---------------------------------------------------------------- parámetros
page = None
for pg in comp.customPages:
    if pg.name == 'Resolume':
        page = pg
if page is None:
    page = comp.appendCustomPage('Resolume')


def falta(nombre):
    return len(comp.pars(nombre)) == 0


if falta('Ip'):
    p = page.appendStr('Ip', label='IPs destino (separadas por coma)')
    p[0].default = ''
    p[0].val = ''
else:
    comp.par.Ip.label = 'IPs destino (separadas por coma)'

if falta('Localhost'):
    p = page.appendToggle('Localhost', label='Enviar también a localhost')
    p[0].default = True
    p[0].val = True

if falta('Port'):
    p = page.appendInt('Port', label='Puerto OSC')
    p[0].default = 7000
    p[0].val = 7000

if falta('Puertolocal'):
    p = page.appendInt('Puertolocal', label='Puerto OSC localhost')
    p[0].default = 7000
    p[0].val = 7000

if falta('Autoinicio'):
    p = page.appendToggle('Autoinicio', label='Auto al iniciar')
    p[0].default = True
    p[0].val = True

if falta('Espera'):
    p = page.appendFloat('Espera', label='Espera inicial (s)')
    p[0].default = 60
    p[0].val = 60
    p[0].min = 0
    p[0].normMax = 300

if falta('Pausa'):
    p = page.appendFloat('Pausa', label='Pausa entre pasos (s)')
    p[0].default = 1
    p[0].val = 1
    p[0].min = 0
    p[0].normMax = 5

if falta('Centinelli'):
    p = page.appendToggle('Centinelli', label='Reportar a Centinelli')
    p[0].default = True
    p[0].val = True

if falta('Puertocentinelli'):
    p = page.appendInt('Puertocentinelli', label='Puerto del agente Centinelli')
    p[0].default = 9300
    p[0].val = 9300

if falta('Sincronizar'):
    page.appendPulse('Sincronizar', label='Sincronizar (Stop + Col 2 + Col 1)')
if falta('Detenertodo'):
    page.appendPulse('Detenertodo', label='Detener todo')
if falta('Columna1'):
    page.appendPulse('Columna1', label='Columna 1')
if falta('Columna2'):
    page.appendPulse('Columna2', label='Columna 2')

# ---------------------------------------------------------------- Execute DAT
CODIGO_EXEC = r'''
import socket
import struct
import re


def _cadena(s):
    # Cadena OSC: terminada en null y rellenada a múltiplos de 4 bytes
    b = s.encode('utf-8') + b'\x00'
    return b + b'\x00' * (-len(b) % 4)


def _mensaje(direccion, valor=1):
    return _cadena(direccion) + _cadena(',i') + struct.pack('>i', int(valor))


def _mensaje_txt(direccion, texto):
    return _cadena(direccion) + _cadena(',s') + _cadena(str(texto))


def centinelli(clave, texto):
    # Manda un dato al agente Centinelli de esta máquina (si está activo).
    # Nunca debe afectar la sincronización: cualquier error se ignora.
    p = parent().par
    try:
        if not p.Centinelli.eval():
            return
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        try:
            try:
                s.ioctl(socket.SIO_UDP_CONNRESET, False)
            except Exception:
                pass
            s.sendto(_mensaje_txt('/centinelli/' + clave, texto),
                     ('127.0.0.1', int(p.Puertocentinelli.eval())))
        finally:
            s.close()
    except Exception:
        pass


def destinos():
    # Devuelve una lista de (ip, puerto)
    p = parent().par
    puerto = int(p.Port.eval())
    ips = [x for x in re.split(r'[,\s;]+', str(p.Ip.eval())) if x]
    lista = []
    if p.Localhost.eval():
        # Evita mandar doble si la IP de esta misma PC está en la lista
        try:
            propias = set(socket.gethostbyname_ex(socket.gethostname())[2])
        except Exception:
            propias = set()
        ips = [x for x in ips if x not in propias and x not in ('127.0.0.1', 'localhost')]
    lista = [(x, puerto) for x in ips]
    if p.Localhost.eval():
        # Localhost va al final y nunca debe tumbar el envío a las demás IPs
        try:
            pl = int(p.Puertolocal.eval())
        except Exception:
            pl = puerto
        lista.append(('127.0.0.1', pl))
    return lista


def osc(direccion, valor=1):
    datos = _mensaje(direccion, valor)
    try:
        lista = destinos()
    except Exception as e:
        print('OSC error armando destinos:', e)
        centinelli('event', 'Sync: error armando destinos: {}'.format(e))
        return
    fallos = []
    for ip, puerto in lista:
        # Un socket por destino: en Windows, un puerto UDP cerrado en localhost
        # hace fallar el siguiente envío del mismo socket
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        try:
            try:
                s.ioctl(socket.SIO_UDP_CONNRESET, False)
            except Exception:
                pass
            s.sendto(datos, (ip, puerto))
            print('OSC ->', ip, puerto, direccion, valor)
        except Exception as e:
            print('OSC error', ip, puerto, e)
            fallos.append(ip)
        finally:
            s.close()
    ok = len(lista) - len(fallos)
    txt = 'Sync: {} → {}/{} destinos'.format(direccion, ok, len(lista))
    if fallos:
        txt += ' (fallaron: {})'.format(', '.join(fallos))
    centinelli('event', txt)
    return ok, fallos


def detener():
    osc('/composition/disconnectall')


def columna(n):
    osc('/composition/columns/{}/connect'.format(int(n)))


def _fin_sync():
    columna(1)
    import time
    hora = time.strftime('%H:%M:%S')
    centinelli('sync', hora)
    centinelli('event', 'Sincronización completa (Stop + Col 2 + Col 1) a las ' + hora)


def sincronizar(origen='local'):
    pausa = int(parent().par.Pausa.eval() * 1000)
    centinelli('event', 'Sincronización iniciada ({})'.format(origen))
    detener()
    run("op('{}').module.columna(2)".format(me.path), delayMilliSeconds=pausa)
    run("op('{}').module._fin_sync()".format(me.path), delayMilliSeconds=pausa * 2)


def onStart():
    if parent().par.Autoinicio.eval():
        espera = int(parent().par.Espera.eval() * 1000)
        run("op('{}').module.sincronizar('autoinicio')".format(me.path), delayMilliSeconds=espera)
    return
'''

ex = comp.op('exec_sync') or comp.create(executeDAT, 'exec_sync')
ex.text = CODIGO_EXEC
ex.par.start = True

# ---------------------------------------------------------------- Parameter Execute DAT
CODIGO_PAR = r'''
def onPulse(par):
    m = op('exec_sync').module
    if par.name == 'Sincronizar':
        m.sincronizar('botón en TouchDesigner')
    elif par.name == 'Detenertodo':
        m.detener()
    elif par.name == 'Columna1':
        m.columna(1)
    elif par.name == 'Columna2':
        m.columna(2)
    return


def onValueChange(par, prev):
    return


def onValuesChanged(changes):
    return


def onExpressionChange(par, val, prev):
    return


def onExportChange(par, val, prev):
    return


def onEnableChange(par, val, prev):
    return


def onModeChange(par, val, prev):
    return
'''

pe = comp.op('parexec_sync') or comp.create(parameterexecuteDAT, 'parexec_sync')
pe.text = CODIGO_PAR
pe.par.op = '..'
pe.par.pars = '*'
pe.par.custom = True
pe.par.builtin = False
pe.par.onpulse = True
pe.par.valuechange = False

print('Resolume sync v3 (Centinelli) listo en', comp.path)