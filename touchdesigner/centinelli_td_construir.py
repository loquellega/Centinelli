# Centinelli · constructor del componente para TouchDesigner
#
# Cómo usarlo:
#   1. Abre tu proyecto .toe
#   2. Abre el Textport (Alt+T) y escribe:
#        exec(open(r'C:/ruta/a/centinelli_td_construir.py', encoding='utf-8').read())
#      (o pega este archivo completo en un Text DAT y dale "Run Script")
#   3. Se crea /project1/centinelli. Guarda el proyecto. Si quieres reusarlo,
#      clic derecho sobre el COMP → Save Component .tox
#
# Lo que hace el COMP:
#   · Manda cada segundo  /centinelli/heartbeat <fps>  al agente (127.0.0.1:9300)
#   · Escucha comandos del dashboard en el puerto 9301 (OSC In DAT)
#   · Desde cualquier script puedes reportar valores al dashboard:
#        mod('/project1/centinelli/centinelli').Reportar('escena', 'leon')
#        mod('/project1/centinelli/centinelli').Evento('Partida terminada')

PADRE = op('/project1') or op('/')
AGENTE_IP, AGENTE_PUERTO, PUERTO_COMANDOS = '127.0.0.1', 9300, 9301

old = PADRE.op('centinelli')
if old:
    old.destroy()

c = PADRE.create(baseCOMP, 'centinelli')
c.nodeX, c.nodeY = -600, 400
c.color = (0.2, 0.55, 0.35)
c.comment = 'Centinelli: latido + comandos remotos'

# ── salida OSC hacia el agente ──
salida = c.create(oscoutDAT, 'salida')
salida.par.netaddress = AGENTE_IP
salida.par.port = AGENTE_PUERTO
salida.nodeX, salida.nodeY = 0, 0

# ── módulo con funciones ──
modulo = c.create(textDAT, 'centinelli')
modulo.nodeX, modulo.nodeY = 0, -150
modulo.text = r'''# Funciones de Centinelli. Úsalas desde cualquier script:
#   mod('/project1/centinelli/centinelli').Reportar('escena', 'leon')
#   mod('/project1/centinelli/centinelli').Evento('Algo pasó')
import time

def _out():
    return op('salida')

def Reportar(clave, valor):
    """Valor que aparece en la tarjeta del dashboard (escena, jugadores, partida…)."""
    if not isinstance(valor, (int, float, str)):
        valor = str(valor)
    _out().sendOSC('/centinelli/' + str(clave), [valor])

def Evento(texto):
    """Línea en la bitácora del dashboard."""
    _out().sendOSC('/centinelli/event', [str(texto)])

def Latido(fps):
    _out().sendOSC('/centinelli/heartbeat', [float(fps)])
'''

# ── latido cada segundo con fps medidos ──
latido = c.create(executeDAT, 'latido')
latido.nodeX, latido.nodeY = 200, 0
latido.text = r'''import time
V = mod('centinelli')

def onStart():
    V.Evento('TouchDesigner iniciado: ' + project.name)
    return

def onFrameStart(frame):
    s = parent().fetch('_vg', None, storeDefault=False)
    now = time.perf_counter()
    if s is None:
        parent().store('_vg', {'t': now, 'n': 0})
        return
    s['n'] += 1
    dt = now - s['t']
    if dt >= 1.0:
        V.Latido(s['n'] / dt)
        s['t'], s['n'] = now, 0
    return
'''
latido.par.framestart = True
latido.par.start = True
latido.par.active = True

# ── comandos que llegan del dashboard ──
cb = c.create(textDAT, 'comandos')
cb.nodeX, cb.nodeY = 200, -150
cb.text = r'''# Comandos que llegan desde el dashboard (campo OSC de la tarjeta).
# Comandos genéricos ya incluidos:
#   /centinelli/cmd/par    /project1/miCOMP  Nombrepar  valor   → cambia un parámetro
#   /centinelli/cmd/pulse  /project1/miCOMP  Nombrepar          → pulsa un parámetro (Reset, Start…)
#   /centinelli/cmd/ping                                        → responde en la bitácora
# Agrega los tuyos en ACCIONES (p.ej. cambiar de escena, mandar cue a PIXERA).
V = mod('centinelli')

def escena(args):
    # EJEMPLO: ajusta a tu proyecto
    # op('/project1/sceneChanger').par.Scene = args[0]
    V.Evento('Escena pedida: ' + str(args[0] if args else ''))

def _par(args):
    o = op(args[0]); p = getattr(o.par, args[1])
    p.val = args[2]
    V.Evento('par %s.%s = %s' % (args[0], args[1], args[2]))

def _pulse(args):
    o = op(args[0]); getattr(o.par, args[1]).pulse()
    V.Evento('pulse %s.%s' % (args[0], args[1]))

ACCIONES = {
    '/centinelli/cmd/escena': escena,
    '/centinelli/cmd/par': _par,
    '/centinelli/cmd/pulse': _pulse,
    '/centinelli/cmd/ping': lambda a: V.Evento('pong · fps %.1f' % project.cookRate),
}

def onReceiveOSC(dat, rowIndex, message, byteData, timeStamp, address, args, peer):
    f = ACCIONES.get(address)
    if f is None:
        V.Evento('Comando OSC desconocido: ' + address)
        return
    try:
        f(list(args))
    except Exception as e:
        V.Evento('Error en %s: %s' % (address, e))
    return
'''

entrada = c.create(oscinDAT, 'entrada')
entrada.nodeX, entrada.nodeY = 400, -150
entrada.par.port = PUERTO_COMANDOS
entrada.par.callbacks = cb

print('Centinelli: componente creado en', c.path, '· latido →', AGENTE_IP, AGENTE_PUERTO, '· comandos en', PUERTO_COMANDOS)
