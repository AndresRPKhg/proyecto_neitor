<?php
declare(strict_types=1);
namespace RaceControl\Presentation;

use RaceControl\Application\AuthService;
use RaceControl\Application\CatalogoService;
use RaceControl\Application\ConfiguracionService;
use RaceControl\Application\ErrorApp;
use RaceControl\Application\InscripcionService;
use RaceControl\Application\MetricasService;
use RaceControl\Application\PermisoError;
use RaceControl\Application\Recursos;
use RaceControl\Application\ReporteService;
use RaceControl\Application\NoEncontradoError;
use RaceControl\Application\ResultadoService;
use RaceControl\Application\ValidacionError;
use RaceControl\Domain\Rol;
use RaceControl\Infrastructure\Csrf;
use RaceControl\Infrastructure\Sesion;

/** Controladores REST (JSON). Los permisos se validan aquí, en el backend, nunca solo en la interfaz. */
final class ApiController
{
    public static function registrar(Router $r): void
    {
        $recurso = implode('|', array_keys(Recursos::todos()));
        $id = '\d+';

        // --- Mapa de pista (imagen por circuito) ---
        $r->agregar('GET', '/api/circuitos/{id:\d+}/mapa', [self::class, 'verMapa']);
        $r->agregar('POST', '/api/circuitos/{id:\d+}/mapa', [self::class, 'subirMapa'], ['permiso' => 'catalogo.editar']);
        $r->agregar('DELETE', '/api/circuitos/{id:\d+}/mapa', [self::class, 'quitarMapa'], ['permiso' => 'catalogo.editar']);

        // --- Autenticación ---
        $r->agregar('POST', '/api/auth/login', [self::class, 'login'], ['auth' => false]);
        $r->agregar('POST', '/api/auth/registro', [self::class, 'registro'], ['auth' => false]);
        $r->agregar('POST', '/api/auth/recuperar', [self::class, 'recuperar'], ['auth' => false]);
        $r->agregar('POST', '/api/auth/restablecer', [self::class, 'restablecer'], ['auth' => false]);
        $r->agregar('POST', '/api/auth/logout', [self::class, 'logout']);
        $r->agregar('GET', '/api/auth/yo', [self::class, 'yo']);
        $r->agregar('POST', '/api/auth/ping', [self::class, 'ping']);
        $r->agregar('POST', '/api/auth/cambiar-clave', [self::class, 'cambiarClave']);

        // --- Configuración, dashboard, reportes ---
        $r->agregar('GET', '/api/configuracion', [self::class, 'configuracion']);
        $r->agregar('PUT', '/api/configuracion', [self::class, 'guardarConfiguracion'], ['permiso' => 'config.gestionar']);
        $r->agregar('GET', '/api/dashboard', [self::class, 'dashboard'], ['permiso' => 'reportes.ver']);
        $r->agregar('GET', '/api/reportes', [self::class, 'tiposReporte'], ['permiso' => 'reportes.ver']);
        $r->agregar('GET', '/api/reportes/{tipo:[a-z]+}', [self::class, 'reporte'], ['permiso' => 'reportes.ver']);

        // --- Competencias ---
        $r->agregar('GET', "/api/campeonatos/{id:$id}/posiciones", [self::class, 'posiciones']);
        $r->agregar('GET', "/api/carreras/{id:$id}/inscripciones", [self::class, 'inscritos']);
        $r->agregar('POST', "/api/carreras/{id:$id}/inscripciones", [self::class, 'inscribir'], ['permiso' => 'inscripciones.gestionar']);
        $r->agregar('DELETE', "/api/inscripciones/{id:$id}", [self::class, 'quitarInscripcion'], ['permiso' => 'inscripciones.gestionar']);
        $r->agregar('GET', "/api/carreras/{id:$id}/resultados", [self::class, 'resultados']);
        $r->agregar('POST', "/api/carreras/{id:$id}/resultados", [self::class, 'guardarResultados'], ['permiso' => 'resultados.gestionar']);
        $r->agregar('POST', "/api/carreras/{id:$id}/resultados/importar", [self::class, 'importarResultados'], ['permiso' => 'resultados.gestionar']);

        // --- CRUD genérico ---
        $r->agregar('GET', "/api/{recurso:$recurso}/meta", [self::class, 'meta']);
        $r->agregar('GET', "/api/{recurso:$recurso}/opciones", [self::class, 'opciones']);
        $r->agregar('GET', "/api/{recurso:$recurso}", [self::class, 'listar']);
        $r->agregar('GET', "/api/{recurso:$recurso}/{id:$id}", [self::class, 'obtener']);
        $r->agregar('POST', "/api/{recurso:$recurso}", [self::class, 'crear']);
        $r->agregar('PUT', "/api/{recurso:$recurso}/{id:$id}", [self::class, 'actualizar']);
        $r->agregar('DELETE', "/api/{recurso:$recurso}/{id:$id}", [self::class, 'eliminar']);
    }

    // ---------- Autenticación ----------

    public static function login(Peticion $p): void
    {
        $d = $p->cuerpo();
        $errores = [];
        if (trim((string) ($d['usuario'] ?? '')) === '') {
            $errores['usuario'] = 'Escribe tu usuario o correo.';
        }
        if ((string) ($d['clave'] ?? '') === '') {
            $errores['clave'] = 'Escribe tu contraseña.';
        }
        if ($errores) {
            throw new ValidacionError($errores);
        }
        $fila = (new AuthService())->iniciarSesion((string) $d['usuario'], (string) $d['clave'], $p->ip());
        Sesion::abrir($fila);
        Respuesta::json(['ok' => true, 'csrf' => Csrf::token(), 'destino' => $p->base . '/']);
    }

    public static function registro(Peticion $p): void
    {
        (new AuthService())->registrar($p->cuerpo());
        Respuesta::json(['ok' => true, 'mensaje' => 'Cuenta creada. Ya puedes iniciar sesión.', 'destino' => $p->base . '/login'], 201);
    }

    public static function recuperar(Peticion $p): void
    {
        $id = trim((string) ($p->cuerpo()['usuario'] ?? ''));
        if ($id === '') {
            throw new ValidacionError(['usuario' => 'Escribe tu usuario o correo.']);
        }
        (new AuthService())->solicitarRecuperacion($id);
        Respuesta::json(['ok' => true, 'mensaje' => 'Si la cuenta existe, enviamos un enlace de recuperación al correo registrado. Vence en 30 minutos.']);
    }

    public static function restablecer(Peticion $p): void
    {
        $d = $p->cuerpo();
        if ((string) ($d['clave'] ?? '') !== (string) ($d['confirmar'] ?? '')) {
            throw new ValidacionError(['confirmar' => 'Las contraseñas no coinciden.']);
        }
        (new AuthService())->restablecer((string) ($d['token'] ?? ''), (string) ($d['clave'] ?? ''));
        Respuesta::json(['ok' => true, 'mensaje' => 'Contraseña actualizada. Ya puedes iniciar sesión.', 'destino' => $p->base . '/login']);
    }

    public static function logout(Peticion $p, array $_, array $usuario): void
    {
        (new AuthService())->cerrarSesionesDe((int) $usuario['id']); // logout global
        Sesion::cerrar();
        Respuesta::json(['ok' => true, 'destino' => $p->base . '/login']);
    }

    public static function yo(Peticion $p, array $_, array $u): void
    {
        Respuesta::json(self::perfil($u) + ['segundos_restantes' => Sesion::restante()]);
    }

    public static function ping(Peticion $p, array $_, array $u): void
    {
        (new MetricasService())->latido();
        Respuesta::json(['ok' => true, 'segundos_restantes' => Sesion::INACTIVIDAD]);
    }

    public static function cambiarClave(Peticion $p, array $_, array $u): void
    {
        $d = $p->cuerpo();
        if ((string) ($d['nueva'] ?? '') !== (string) ($d['confirmar'] ?? '')) {
            throw new ValidacionError(['confirmar' => 'Las contraseñas no coinciden.']);
        }
        $version = (new AuthService())->cambiarClave($u, (string) ($d['actual'] ?? ''), (string) ($d['nueva'] ?? ''));
        Sesion::sincronizarVersion($version); // las demás sesiones del usuario quedan invalidadas
        Respuesta::json(['ok' => true, 'mensaje' => 'Contraseña actualizada. Se cerraron tus otras sesiones.']);
    }

    public static function perfil(array $u): array
    {
        return ['id' => (int) $u['id'], 'nombre' => $u['nombre'], 'usuario' => $u['usuario'], 'email' => $u['email'],
            'rol' => $u['rol'], 'permisos' => Rol::permisosDe($u['rol'])];
    }

    // ---------- Mapa de pista ----------

    private const MAPA_TIPOS = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

    private static function dirMapas(): string
    {
        $d = dirname(__DIR__, 2) . '/storage/mapas';
        if (!is_dir($d)) {
            mkdir($d, 0775, true);
        }
        return $d;
    }

    private static function circuito(int $id): array
    {
        $st = \RaceControl\Infrastructure\Database::pdo()->prepare('SELECT id, mapa FROM circuitos WHERE id=?');
        $st->execute([$id]);
        return $st->fetch() ?: throw new NoEncontradoError('El circuito no existe.');
    }

    public static function verMapa(Peticion $p, array $params): void
    {
        $c = self::circuito((int) $params['id']);
        $ruta = $c['mapa'] ? self::dirMapas() . '/' . basename((string) $c['mapa']) : '';
        if (!$ruta || !is_file($ruta)) {
            throw new NoEncontradoError('Este circuito aún no tiene mapa.');
        }
        $tipo = array_search(pathinfo($ruta, PATHINFO_EXTENSION), self::MAPA_TIPOS, true) ?: 'application/octet-stream';
        header('Content-Type: ' . $tipo);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        exit;
    }

    public static function subirMapa(Peticion $p, array $params): void
    {
        $id = (int) $params['id'];
        $c = self::circuito($id);
        $a = $_FILES['mapa'] ?? null;
        if (!$a || ($a['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new ValidacionError(['mapa' => 'Selecciona una imagen (máximo 3 MB).']);
        }
        if ($a['size'] > 3 * 1024 * 1024) {
            throw new ValidacionError(['mapa' => 'La imagen supera los 3 MB.']);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($a['tmp_name']);
        if (!isset(self::MAPA_TIPOS[$mime]) || @getimagesize($a['tmp_name']) === false) {
            throw new ValidacionError(['mapa' => 'Formato no permitido. Usa PNG, JPG o WEBP.']);
        }
        $nombre = "circuito_$id." . self::MAPA_TIPOS[$mime];
        $dir = self::dirMapas();
        if ($c['mapa'] && is_file("$dir/" . basename((string) $c['mapa']))) {
            unlink("$dir/" . basename((string) $c['mapa']));
        }
        if (!move_uploaded_file($a['tmp_name'], "$dir/$nombre")) {
            throw new \RaceControl\Application\ErrorApp('No se pudo guardar el mapa.', 500);
        }
        \RaceControl\Infrastructure\Database::pdo()->prepare('UPDATE circuitos SET mapa=? WHERE id=?')->execute([$nombre, $id]);
        Respuesta::json(['ok' => true, 'mapa' => $nombre]);
    }

    public static function quitarMapa(Peticion $p, array $params): void
    {
        $id = (int) $params['id'];
        $c = self::circuito($id);
        if ($c['mapa']) {
            @unlink(self::dirMapas() . '/' . basename((string) $c['mapa']));
        }
        \RaceControl\Infrastructure\Database::pdo()->prepare('UPDATE circuitos SET mapa=NULL WHERE id=?')->execute([$id]);
        Respuesta::json(['ok' => true]);
    }

    // ---------- Configuración, dashboard y reportes ----------

    public static function configuracion(Peticion $p): void
    {
        Respuesta::json((new ConfiguracionService())->obtener());
    }

    public static function guardarConfiguracion(Peticion $p): void
    {
        Respuesta::json((new ConfiguracionService())->guardar($p->cuerpo()));
    }

    public static function dashboard(Peticion $p): void
    {
        $m = new MetricasService();
        $m->consolidar(date('Y-m-d'));
        $camp = (int) ($p->query['campeonato_id'] ?? 0) ?: null;
        $piloto = (int) ($p->query['piloto_id'] ?? 0) ?: null;
        $rep = new ReporteService();
        Respuesta::json([
            'kpis' => $m->kpis(),
            'graficos' => $m->graficos($camp, $piloto),
            'estadisticas' => $rep->estadisticas(),
            'outliers' => $rep->outliers(),
            'calidad' => $rep->calidad(),
        ]);
    }

    public static function tiposReporte(Peticion $p): void
    {
        Respuesta::json(ReporteService::TIPOS);
    }

    public static function reporte(Peticion $p, array $params, array $u): void
    {
        $datos = (new ReporteService())->generar($params['tipo'], $p->query);
        if (($p->query['formato'] ?? '') === 'csv') {
            if (!Rol::puede($u['rol'], 'reportes.exportar')) {
                throw new PermisoError();
            }
            Respuesta::csv('racecontrol_' . $params['tipo'] . '_' . date('Ymd') . '.csv', ReporteService::aCsv($datos));
        }
        Respuesta::json($datos);
    }

    // ---------- Competencias ----------

    public static function posiciones(Peticion $p, array $params): void
    {
        Respuesta::json((new ResultadoService())->posiciones((int) $params['id']));
    }

    public static function inscritos(Peticion $p, array $params): void
    {
        Respuesta::json((new InscripcionService())->listar((int) $params['id']));
    }

    public static function inscribir(Peticion $p, array $params): void
    {
        $d = $p->cuerpo();
        Respuesta::json((new InscripcionService())->inscribir((int) $params['id'], $d['piloto_id'] ?? null, $d['vehiculo_id'] ?? null), 201);
    }

    public static function quitarInscripcion(Peticion $p, array $params): void
    {
        (new InscripcionService())->quitar((int) $params['id']);
        Respuesta::json(['ok' => true]);
    }

    public static function resultados(Peticion $p, array $params): void
    {
        Respuesta::json((new ResultadoService())->obtener((int) $params['id']));
    }

    public static function guardarResultados(Peticion $p, array $params): void
    {
        $filas = $p->cuerpo()['resultados'] ?? [];
        if (!is_array($filas) || !$filas) {
            throw new ErrorApp('No hay resultados para guardar.', 422);
        }
        Respuesta::json((new ResultadoService())->guardar((int) $params['id'], $filas));
    }

    public static function importarResultados(Peticion $p, array $params): void
    {
        $csv = (string) ($p->cuerpo()['csv'] ?? '');
        if (strlen($csv) > 200000 || trim($csv) === '') {
            throw new ErrorApp('El CSV está vacío o es demasiado grande.', 422);
        }
        Respuesta::json((new ResultadoService())->importarCsv((int) $params['id'], $csv));
    }

    // ---------- CRUD genérico ----------

    private static function autorizar(string $recurso, string $accion, array $u): array
    {
        $def = Recursos::get($recurso);
        $permiso = $def['permisos'][$accion] ?? null;
        if ($permiso !== null && !Rol::puede($u['rol'], $permiso)) {
            throw new PermisoError();
        }
        return $def;
    }

    public static function meta(Peticion $p, array $params, array $u): void
    {
        self::autorizar($params['recurso'], 'ver', $u);
        Respuesta::json(Recursos::meta($params['recurso']));
    }

    public static function opciones(Peticion $p, array $params, array $u): void
    {
        Respuesta::json((new CatalogoService())->opciones($params['recurso'], $p->query));
    }

    public static function listar(Peticion $p, array $params, array $u): void
    {
        $def = self::autorizar($params['recurso'], 'ver', $u);
        if (!empty($def['solo_opciones'])) {
            throw new PermisoError();
        }
        Respuesta::json((new CatalogoService())->listar($params['recurso'], $p->query));
    }

    public static function obtener(Peticion $p, array $params, array $u): void
    {
        self::autorizar($params['recurso'], 'ver', $u);
        Respuesta::json((new CatalogoService())->obtener($params['recurso'], (int) $params['id']));
    }

    public static function crear(Peticion $p, array $params, array $u): void
    {
        self::autorizar($params['recurso'], 'crear', $u);
        Respuesta::json((new CatalogoService())->crear($params['recurso'], $p->cuerpo(), ['usuario' => $u]), 201);
    }

    public static function actualizar(Peticion $p, array $params, array $u): void
    {
        self::autorizar($params['recurso'], 'editar', $u);
        Respuesta::json((new CatalogoService())->actualizar($params['recurso'], (int) $params['id'], $p->cuerpo(), ['usuario' => $u]));
    }

    public static function eliminar(Peticion $p, array $params, array $u): void
    {
        self::autorizar($params['recurso'], 'borrar', $u);
        (new CatalogoService())->eliminar($params['recurso'], (int) $params['id'], ['usuario' => $u]);
        Respuesta::json(['ok' => true]);
    }
}
