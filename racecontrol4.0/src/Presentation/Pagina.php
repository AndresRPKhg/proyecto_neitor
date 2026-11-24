<?php
declare(strict_types=1);
namespace RaceControl\Presentation;

use RaceControl\Application\NoEncontradoError;
use RaceControl\Application\PermisoError;
use RaceControl\Application\Recursos;
use RaceControl\Domain\Rol;
use RaceControl\Infrastructure\Actividad;
use RaceControl\Infrastructure\Csrf;
use RaceControl\Infrastructure\Database;
use RaceControl\Infrastructure\Sesion;

/** Páginas HTML (la interfaz gótica). Los datos se piden después a la API REST con JavaScript. */
final class Pagina
{
    public static function registrar(Router $r): void
    {
        $r->agregar('GET', '/login', fn (Peticion $p) => self::publica($p, 'login', 'Iniciar sesión'), ['auth' => false]);
        $r->agregar('GET', '/registro', fn (Peticion $p) => self::publica($p, 'registro', 'Crear cuenta'), ['auth' => false]);
        $r->agregar('GET', '/recuperar', fn (Peticion $p) => self::publica($p, 'recuperar', 'Recuperar acceso'), ['auth' => false]);
        $r->agregar('GET', '/restablecer', fn (Peticion $p) => self::publica($p, 'restablecer', 'Nueva contraseña'), ['auth' => false]);
        $r->agregar('GET', '/', fn (Peticion $p, array $a, array $u) => self::privada($p, $u, 'dashboard', 'Panel principal', 'dashboards'));
        $r->agregar('GET', '/reportes', fn (Peticion $p, array $a, array $u) => self::privada($p, $u, 'reportes', 'Reportes', 'reportes', 'reportes.ver'));
        $r->agregar('GET', '/configuracion', fn (Peticion $p, array $a, array $u) => self::privada($p, $u, 'configuracion', 'Configuración', 'configuracion', 'config.gestionar'));
        $r->agregar('GET', '/campeonatos/{id:\d+}', fn (Peticion $p, array $a, array $u) =>
            self::privada($p, $u, 'campeonato', 'Campeonato', 'competencias', null, ['id' => (int) $a['id']]));
        $r->agregar('GET', '/escuderias/{id:\d+}', fn (Peticion $p, array $a, array $u) =>
            self::privada($p, $u, 'escuderia', 'Escudería', 'catalogos', null, ['id' => (int) $a['id'], 'recurso' => 'equipos']));
        $r->agregar('GET', '/escuderias', fn (Peticion $p, array $a, array $u) =>
            self::privada($p, $u, 'modulo', 'Escuderías', 'catalogos', null, ['recurso' => 'equipos']));
        $r->agregar('GET', '/equipos/{id:\d+}', fn (Peticion $p, array $a, array $u) =>
            Respuesta::redirigir($p->base . '/escuderias/' . $a['id']));
        $r->agregar('GET', '/equipos', fn (Peticion $p, array $a, array $u) => Respuesta::redirigir($p->base . '/escuderias'));
        foreach (['pilotos', 'vehiculos', 'circuitos', 'campeonatos', 'usuarios'] as $recurso) {
            $def = Recursos::get($recurso);
            $r->agregar('GET', '/' . $recurso, fn (Peticion $p, array $a, array $u) =>
                self::privada($p, $u, 'modulo', $def['etiqueta'], $def['modulo'], $def['permisos']['ver'], ['recurso' => $recurso]));
        }
    }

    private static function publica(Peticion $p, string $vista, string $titulo): void
    {
        if (Sesion::usuario()) {
            Respuesta::redirigir($p->base . '/');
        }
        self::render('auth', ['vista' => $vista, 'titulo' => $titulo, 'p' => $p, 'js' => self::js($p, null, $vista, [])]);
    }

    private static function privada(Peticion $p, array $u, string $vista, string $titulo, string $modulo, ?string $permiso = null, array $extra = []): void
    {
        if ($permiso !== null && !Rol::puede($u['rol'], $permiso)) {
            throw new PermisoError('Tu rol no tiene acceso a esta sección.');
        }
        Actividad::registrar('modulo', (int) $u['id'], $extra['recurso'] ?? $modulo);
        self::render('layout', ['vista' => $vista, 'titulo' => $titulo, 'p' => $p, 'u' => $u, 'modulo' => $modulo, 'extra' => $extra,
            'js' => self::js($p, $u, $vista, $extra)]);
    }

    public static function renderizarError(Peticion $p, int $http, string $mensaje): void
    {
        $u = Sesion::usuario();
        $datos = ['vista' => 'error', 'titulo' => "Error $http", 'p' => $p, 'mensaje' => $mensaje, 'http' => $http, 'extra' => [], 'modulo' => '',
            'js' => self::js($p, $u, 'error', [])];
        $u ? self::render('layout', $datos + ['u' => $u]) : self::render('auth', $datos);
    }

    /** Datos que lee el JavaScript (JSON inerte, sin scripts en línea). */
    private static function js(Peticion $p, ?array $u, string $pagina, array $extra): string
    {
        return json_encode(['base' => $p->base, 'csrf' => Csrf::token(), 'pagina' => $pagina, 'usuario' => $u ? ApiController::perfil($u) : null,
            'inactividad' => Sesion::INACTIVIDAD] + $extra, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    }

    /** Menú lateral agrupado por categoría del producto. */
    public static function menu(array $u): array
    {
        $grupos = [
            'Dashboards' => [['/', 'panel', 'Panel principal', 'dashboards']],
            'Catálogos' => [['/pilotos', 'casco', 'Pilotos', 'catalogos'], ['/escuderias', 'equipo', 'Escuderías', 'catalogos'],
                ['/vehiculos', 'auto', 'Vehículos', 'catalogos'], ['/circuitos', 'pista', 'Circuitos', 'catalogos']],
            'Competencias' => [['/campeonatos', 'caliz', 'Campeonatos', 'competencias']],
            'Reportes' => [['/reportes', 'reporte', 'Reportes', 'reportes']],
        ];
        if (Rol::puede($u['rol'], 'usuarios.gestionar')) {
            $grupos['Gestión de usuarios'] = [['/usuarios', 'usuario', 'Usuarios y roles', 'usuarios']];
        }
        if (Rol::puede($u['rol'], 'config.gestionar')) {
            $grupos['Configuración'] = [['/configuracion', 'engranaje', 'Sistema', 'configuracion']];
        }
        return $grupos;
    }

    private static function render(string $plantilla, array $d): void
    {
        Respuesta::cabecerasSeguridad();
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        extract($d, EXTR_SKIP);
        require dirname(__DIR__, 2) . "/resources/views/$plantilla.php";
    }
}
