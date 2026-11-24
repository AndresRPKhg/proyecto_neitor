#!/usr/bin/env python3
"""Prueba de humo de extremo a extremo contra un servidor en marcha (python3 tests/humo.py http://127.0.0.1:8099)."""
import json, re, sys, urllib.request, urllib.error, http.cookiejar

BASE = sys.argv[1] if len(sys.argv) > 1 else 'http://127.0.0.1:8099'
fallos = []

class Cliente:
    def __init__(self):
        self.jar = http.cookiejar.CookieJar()
        self.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))
        self.csrf = ''
    def pedir(self, metodo, ruta, cuerpo=None, csrf=True):
        datos = json.dumps(cuerpo).encode() if cuerpo is not None else None
        req = urllib.request.Request(BASE + ruta, data=datos, method=metodo)
        req.add_header('Accept', 'application/json')
        if datos is not None: req.add_header('Content-Type', 'application/json')
        if csrf and self.csrf: req.add_header('X-CSRF-Token', self.csrf)
        try:
            r = self.op.open(req)
            return r.status, r.read().decode()
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode()
    def json(self, metodo, ruta, cuerpo=None, csrf=True):
        c, t = self.pedir(metodo, ruta, cuerpo, csrf)
        try: return c, json.loads(t)
        except Exception: return c, t
    def entrar(self, usuario, clave):
        _, html = self.pedir('GET', '/login')
        self.csrf = json.loads(re.search(r'id="rc-datos">(.*?)</script>', html, re.S).group(1).replace('\\u0022', '"').replace('\\u0026', '&').replace('\\u003C', '<').replace('\\u003E', '>').replace('\\u0027', "'"))['csrf']
        c, r = self.json('POST', '/api/auth/login', {'usuario': usuario, 'clave': clave})
        if c == 200: self.csrf = r['csrf']
        return c, r

def ok(cond, msg):
    print(('  OK  ' if cond else ' FALLA ') + msg)
    if not cond: fallos.append(msg)

a = Cliente()
print('Autenticación')
c, r = a.json('POST', '/api/auth/login', {'usuario': 'admin', 'clave': 'x'}, csrf=False); ok(c == 401 or c == 419, f'login sin sesión/CSRF rechazado ({c})')
c, r = a.entrar('admin', 'incorrecta'); ok(c == 401, f'contraseña incorrecta -> 401 ({c})')
c, r = a.entrar('admin', 'Catedral#Noche2026'); ok(c == 200, 'login correcto')
c, r = a.json('GET', '/api/auth/yo'); ok(c == 200 and r['rol'] == 'admin', 'perfil admin')
c, _ = a.json('POST', '/api/pilotos', {'nombre': 'x'}, csrf=False); ok(c == 419, f'POST sin CSRF -> 419 ({c})')

print('Catálogos (CRUD genérico)')
c, r = a.json('GET', '/api/pilotos?por=10'); ok(c == 200 and r['total'] == 4, f"lista pilotos total={r.get('total') if c==200 else r}")
c, r = a.json('GET', '/api/pilotos?q=lucia'); ok(c == 200, 'búsqueda')
c, r = a.json('GET', '/api/pilotos?orden=documento&dir=desc&por=25'); ok(c == 200 and r['datos'][0]['documento'] == '1004', 'orden por columna')
c, r = a.json('POST', '/api/pilotos', {}); ok(c == 422 and 'nombre' in r['errores'], 'validación inline en servidor')
c, r = a.json('POST', '/api/pilotos', {'nombre': 'ana', 'apellido': 'ruiz', 'documento': '1001', 'fecha_nacimiento': '1999-01-01', 'nacionalidad': 'colombiana'}); ok(c == 422 and 'documento' in r['errores'], 'documento duplicado')
c, r = a.json('POST', '/api/pilotos', {'nombre': 'ana', 'apellido': 'ruiz', 'documento': '2001', 'fecha_nacimiento': '2999-01-01', 'nacionalidad': 'colombiana'}); ok(c == 422 and 'fecha_nacimiento' in r['errores'], 'fecha futura rechazada')
c, r = a.json('POST', '/api/pilotos', {'nombre': 'ana', 'apellido': 'ruiz', 'documento': '2001', 'fecha_nacimiento': '1999-01-01', 'nacionalidad': 'colombiana', 'equipo_id': 1}); ok(c == 201 and r['nombre'] == 'Ana', 'crear piloto (normaliza nombre)')
pid = r['id']
c, r = a.json('PUT', f'/api/pilotos/{pid}', {'nombre': 'Ana', 'apellido': 'Ruiz', 'documento': '2001', 'fecha_nacimiento': '1999-01-01', 'nacionalidad': 'Colombiana'}); ok(c == 200, 'editar piloto')
c, r = a.json('DELETE', f'/api/pilotos/{pid}'); ok(c == 200, 'borrado lógico')
c, r = a.json('GET', f'/api/pilotos/{pid}'); ok(c == 404, 'piloto borrado no aparece')
c, r = a.json('POST', '/api/vehiculos', {'tipo_vehiculo': 'moto', 'marca': 'Yamaha', 'modelo': 'R1', 'placa': 'mt-001', 'anio': 2024}); ok(c == 201 and r['placa'] == 'MT-001', 'crear moto (placa en mayúsculas)')
moto = r['id']
c, r = a.json('GET', '/api/pilotos/opciones?equipo_id=3'); ok(c == 200 and {p['id'] for p in r} == {1, 4}, 'pilotos filtrados por escudería')
c, r = a.json('GET', '/api/pilotos/opciones?equipo_id=no-valido'); ok(c == 422, 'rechaza filtro de escudería inválido')
c, r = a.json('PUT', f'/api/vehiculos/{moto}', {'tipo_vehiculo': 'moto', 'marca': 'Yamaha', 'modelo': 'R1', 'placa': 'MT-001', 'anio': 2024, 'equipo_id': 1, 'piloto_id': 3}); ok(c == 422 and 'piloto_id' in r['errores'], 'rechaza piloto de otra escudería')
c, r = a.json('PUT', f'/api/vehiculos/{moto}', {'tipo_vehiculo': 'moto', 'marca': 'Yamaha', 'modelo': 'R1', 'placa': 'MT-001', 'anio': 2024, 'equipo_id': 1, 'piloto_id': 2}); ok(c == 200 and r['piloto'] == 'Marco Valenti', 'asigna piloto de la misma escudería')
c, r = a.json('POST', '/api/vehiculos', {'tipo_vehiculo': 'moto', 'marca': 'Honda', 'modelo': 'CBR', 'placa': 'MT-002', 'anio': 2024, 'equipo_id': 1, 'piloto_id': 2}); ok(c == 422 and 'piloto_id' in r['errores'], 'impide dos vehículos activos del mismo tipo por piloto')
c, r = a.json('GET', '/api/pilotos/opciones?tipo_vehiculo=moto'); ok(c == 200 and {p['id'] for p in r} == {2}, 'lista solo pilotos con una moto asignada')
c, r = a.json('PUT', '/api/pilotos/2', {'nombre': 'Marco', 'apellido': 'Valenti', 'documento': '1002', 'fecha_nacimiento': '1992-07-02', 'nacionalidad': 'Italiana', 'equipo_id': 3}); ok(c == 422 and 'equipo_id' in r['errores'], 'impide cambiar escudería si el piloto tiene vehículos asignados')
c, r = a.json('DELETE', '/api/equipos/1'); ok(c == 409, 'impide eliminar escudería con pilotos o vehículos activos')
c, r = a.json('POST', '/api/campeonatos', {'nombre': 'Copa Test', 'temporada': 2026, 'tipo_vehiculo': 'auto', 'puntos': '10,5,x'}); ok(c == 422 and 'puntos' in r['errores'], 'sistema de puntos inválido')
c, r = a.json('GET', '/api/vehiculos/opciones?tipo_vehiculo=auto'); ok(c == 200 and len(r) == 4, 'opciones filtradas por tipo')

print('Competencias')
c, mixto = a.json('POST', '/api/campeonatos', {'nombre': 'Copa Mixta Test', 'temporada': 2027, 'tipo_vehiculo': 'mixto', 'puntos': '25,18,15'}); ok(c == 201 and mixto['tipo_vehiculo'] == 'mixto', 'crear campeonato mixto')
def crear_carrera_mixta(tipo, nombre):
    return a.json('POST', '/api/carreras', {'campeonato_id': mixto['id'], 'tipo_vehiculo': tipo, 'circuito_id': 1,
        'nombre': nombre, 'fecha': '2027-01-15', 'cupo_maximo': 20, 'vueltas': 12})
c, carrera_moto = crear_carrera_mixta('moto', 'Gran Premio de Motos'); ok(c == 201 and carrera_moto['tipo_vehiculo'] == 'moto', 'crear carrera de motos en campeonato mixto')
c, carrera_auto = crear_carrera_mixta('auto', 'Gran Premio de Autos'); ok(c == 201 and carrera_auto['tipo_vehiculo'] == 'auto', 'crear carrera de autos en campeonato mixto')
c, carrera_mixta = crear_carrera_mixta('mixto', 'Gran Premio Mixto'); ok(c == 201 and carrera_mixta['tipo_vehiculo'] == 'mixto', 'crear carrera mixta')
c, carreras_mixtas = a.json('GET', f"/api/carreras?campeonato_id={mixto['id']}&por=25"); ok(c == 200 and {r['tipo_vehiculo'] for r in carreras_mixtas['datos']} == {'auto', 'moto', 'mixto'}, 'el campeonato contiene carreras de ambos tipos y mixtas')
c, campeonato_auto = a.json('POST', '/api/campeonatos', {'nombre': 'Copa Auto Mixta Test', 'temporada': 2027, 'tipo_vehiculo': 'auto', 'puntos': '25,18,15'}); ok(c == 201, 'crear campeonato de autos para probar carrera mixta')
c, carrera_mixta_auto = a.json('POST', '/api/carreras', {'campeonato_id': campeonato_auto['id'], 'tipo_vehiculo': 'mixto', 'circuito_id': 1,
    'nombre': 'Gran Premio Mixto en Campeonato Auto', 'fecha': '2027-01-16', 'cupo_maximo': 20, 'vueltas': 12}); ok(c == 201 and carrera_mixta_auto['tipo_vehiculo'] == 'mixto', 'permitir carrera mixta en campeonato de autos')
c, r = a.json('POST', f"/api/carreras/{carrera_moto['id']}/inscripciones", {'piloto_id': 2}); ok(c == 201, 'inscribir automáticamente la moto asignada al piloto')
c, r = a.json('GET', f"/api/carreras/{carrera_moto['id']}/inscripciones"); ok(c == 200 and r[0]['vehiculo'] == 'Yamaha R1', 'usar la moto asignada en la carrera de motos')
c, r = a.json('POST', f"/api/carreras/{carrera_moto['id']}/inscripciones", {'piloto_id': 4}); ok(c == 422 and 'piloto_id' in r['errores'], 'rechazar piloto sin moto asignada')
c, r = a.json('POST', f"/api/carreras/{carrera_mixta['id']}/inscripciones", {'piloto_id': 2}); ok(c == 422 and 'vehiculo_id' in r['errores'], 'pedir vehículo al piloto que tiene auto y moto en carrera mixta')
c, r = a.json('POST', f"/api/carreras/{carrera_mixta['id']}/inscripciones", {'piloto_id': 1}); ok(c == 201, 'asignar automáticamente el auto del piloto con un solo vehículo en carrera mixta')
c, r = a.json('POST', f"/api/carreras/{carrera_mixta['id']}/inscripciones", {'piloto_id': 2, 'vehiculo_id': moto}); ok(c == 201, 'elegir la moto del piloto en carrera mixta')
c, ins_mixta = a.json('GET', f"/api/carreras/{carrera_mixta['id']}/inscripciones"); ok(c == 200 and {i['tipo_vehiculo'] for i in ins_mixta} == {'auto', 'moto'}, 'carrera mixta admite autos y motos')
c, r = a.json('GET', '/api/carreras/1/inscripciones'); ok(c == 200 and r == [], 'sin inscritos')
for p in [1, 2, 3, 4]:
    c, r = a.json('POST', '/api/carreras/1/inscripciones', {'piloto_id': p}); ok(c == 201, f'inscribir piloto {p} con su vehículo asignado')
c, r = a.json('POST', '/api/carreras/1/inscripciones', {'piloto_id': 1}); ok(c == 422, 'inscripción duplicada rechazada')
c, ins = a.json('GET', '/api/carreras/1/inscripciones'); ok(len(ins) == 4, '4 inscritos')
orden = {i['piloto_id']: i['id'] for i in ins}
ok(next((i['vehiculo'] == 'Ferrari 296 GT3' for i in ins if i['piloto_id'] == 2), False), 'el piloto usa automáticamente su auto asignado en la carrera de autos')
res = [{'inscripcion_id': orden[1], 'estado': 'clasificado', 'tiempo': '45:12.345', 'mejor_vuelta': '01:32.100'},
       {'inscripcion_id': orden[2], 'estado': 'clasificado', 'tiempo': '44:59.000', 'mejor_vuelta': '01:31.900'},
       {'inscripcion_id': orden[3], 'estado': 'clasificado', 'tiempo': '45:30.500', 'mejor_vuelta': '01:33.000'},
       {'inscripcion_id': orden[4], 'estado': 'dnf', 'tiempo': '', 'mejor_vuelta': ''}]
bad = json.loads(json.dumps(res)); bad[0]['tiempo'] = '45-12'
c, r = a.json('POST', '/api/carreras/1/resultados', {'resultados': bad}); ok(c == 422, 'tiempo con formato inválido')
c, r = a.json('POST', '/api/carreras/1/resultados', {'resultados': res}); ok(c == 200, 'guardar resultados')
pos = {x['piloto']: (x['posicion'], x['puntos']) for x in r} if c == 200 else {}
ok(pos.get('Marco Valenti') == (1, '25.00'), f'Marco 1º con 25 pts: {pos.get("Marco Valenti")}')
ok(pos.get('Lucía Navarro') == (2, '18.00'), 'Lucía 2ª con 18 pts')
ok(pos.get('Sofía Duarte') == (None, '0.00'), 'DNF sin posición ni puntos')
c, t = a.json('GET', '/api/campeonatos/1/posiciones'); ok(c == 200 and t[0]['piloto'] == 'Marco Valenti' and float(t[0]['puntos']) == 25, 'tabla de posiciones coincide')
c, r = a.json('POST', '/api/carreras/1/inscripciones', {'piloto_id': 1}); ok(c == 422, 'carrera finalizada no admite inscripciones')
csv = 'documento,tiempo,mejor_vuelta,estado\n1001,44:00.000,01:30.000,clasificado\n'
c, r = a.json('POST', '/api/carreras/1/resultados/importar', {'csv': csv}); ok(c == 200, 'importar CSV')
c, t = a.json('GET', '/api/campeonatos/1/posiciones'); ok(t[0]['piloto'] == 'Lucía Navarro', f"recálculo tras CSV: líder {t[0]['piloto']}")
c, r = a.json('PUT', '/api/campeonatos/1', {'nombre': 'Copa Catedral', 'temporada': 2026, 'tipo_vehiculo': 'auto', 'puntos': '10,5,3'}); ok(c == 200, 'cambiar puntos del campeonato')
c, t = a.json('GET', '/api/campeonatos/1/posiciones'); ok(float(t[0]['puntos']) == 10, 'recálculo con nuevo sistema de puntos')

print('Dashboard y reportes')
c, d = a.json('GET', '/api/dashboard?piloto_id=1&campeonato_id=1'); ok(c == 200 and d['kpis']['pilotos_registrados'] == 4 and d['graficos']['tendencia'], 'dashboard')
ok(d['kpis']['errores_pct'] >= 0, f"KPI errores {d['kpis']['errores_pct']} %")
c, t = a.pedir('GET', '/api/reportes/posiciones?campeonato_id=1&formato=csv'); ok(c == 200 and 'Lucía Navarro' in t and t.startswith('\ufeff'), 'exportar CSV')
for tipo in ['pilotos', 'estadisticas', 'outliers', 'calidad']:
    c, _ = a.json('GET', f'/api/reportes/{tipo}'); ok(c == 200, f'reporte {tipo}')
c, _ = a.json('GET', '/api/reportes/resultados?carrera_id=1'); ok(c == 200, 'reporte resultados')

print('Usuarios, roles y permisos')
c, r = a.json('POST', '/api/usuarios', {'nombre': 'edu editor', 'usuario': 'editor1', 'email': 'e@x.co', 'rol_id': 2, 'contrasena': 'corta', 'activo': 1}); ok(c == 422 and 'contrasena' in r['errores'], 'contraseña débil rechazada')
c, r = a.json('POST', '/api/usuarios', {'nombre': 'edu editor', 'usuario': 'editor1', 'email': 'e@x.co', 'rol_id': 2, 'contrasena': 'Editor#Pista2026', 'activo': 1}); ok(c == 201 and 'password_hash' not in r, 'crear editor (sin exponer hash)')
c, r = a.json('POST', '/api/usuarios', {'nombre': 'Lena Lector', 'usuario': 'lector1', 'email': 'l@x.co', 'rol_id': 3, 'contrasena': 'Lector#Pista2026', 'activo': 1}); ok(c == 201, 'crear lector')
c, r = a.json('DELETE', '/api/usuarios/1'); ok(c == 409, 'no puede borrarse a sí mismo')
e = Cliente(); c, _ = e.entrar('editor1', 'Editor#Pista2026'); ok(c == 200, 'login editor')
c, _ = e.json('GET', '/api/usuarios'); ok(c == 403, 'editor no ve usuarios')
c, _ = e.json('DELETE', '/api/pilotos/1'); ok(c == 403, 'editor no borra catálogos')
c, _ = e.json('POST', '/api/equipos', {'nombre': 'Equipo Nuevo', 'pais': 'Chile'}); ok(c == 201, 'editor crea catálogos')
c, _ = e.json('POST', '/api/carreras/1/resultados', {'resultados': res}); ok(c == 200, 'editor registra resultados')
c, _ = e.json('PUT', '/api/configuracion', {'puntos_por_defecto': '5,3,1'}); ok(c == 403, 'editor no cambia configuración')
l = Cliente(); c, _ = l.entrar('lector1', 'Lector#Pista2026'); ok(c == 200, 'login lector')
c, _ = l.json('GET', '/api/campeonatos'); ok(c == 200, 'lector consulta')
c, _ = l.json('POST', '/api/equipos', {'nombre': 'No', 'pais': 'No'}); ok(c == 403, 'lector no crea')
c, _ = l.json('POST', '/api/carreras/1/inscripciones', {'piloto_id': 1}); ok(c == 403, 'lector no inscribe')
c, t = l.pedir('GET', '/api/reportes/pilotos?formato=csv'); ok(c == 200, 'lector exporta')
c, t = l.pedir('GET', '/usuarios'); ok(c == 403, 'página de usuarios prohibida para lector')
for ruta in ['/', '/pilotos', '/escuderias', '/escuderias/1', '/equipos', '/campeonatos', '/campeonatos/1', '/reportes']:
    c, t = a.pedir('GET', ruta); ok(c == 200 and 'RaceControl' in t, f'página {ruta}')
c, t = a.pedir('GET', '/configuracion'); ok(c == 200, 'página configuración (admin)')
c, t = a.pedir('GET', '/no-existe'); ok(c == 404, 'ruta inexistente -> 404')

print('Bloqueo por intentos fallidos y logout global')
x = Cliente()
for i in range(5): c, r = x.entrar('lector1', 'mala-clave')
ok(c == 423, f'5.º intento bloquea la cuenta ({c})')
c, r = x.entrar('lector1', 'Lector#Pista2026'); ok(c == 423, 'cuenta bloqueada aunque la clave sea correcta')
c, _ = l.json('GET', '/api/auth/yo'); ok(c in (200, 401), 'sesión previa del lector')
c, r = a.json('POST', '/api/auth/logout', {}); ok(c == 200, 'logout')
c, _ = a.json('GET', '/api/auth/yo'); ok(c == 401, 'sesión invalidada tras logout')

print()
print('RESULTADO:', 'TODO BIEN' if not fallos else f'{len(fallos)} fallo(s): ' + '; '.join(fallos))
sys.exit(1 if fallos else 0)
