<?php
declare(strict_types=1);
namespace RaceControl\Presentation;

use RaceControl\Application\ErrorApp;
use RaceControl\Application\NoAutenticadoError;
use RaceControl\Application\NoEncontradoError;
use RaceControl\Application\PermisoError;
use RaceControl\Infrastructure\Actividad;
use RaceControl\Infrastructure\Csrf;
use RaceControl\Infrastructure\Env;
use RaceControl\Infrastructure\Sesion;

/** Enrutador mínimo con parámetros {nombre}, control de sesión, CSRF y manejo uniforme de errores. */
final class Router
{
    private array $rutas = [];

    /** @param array{auth?:bool,permiso?:string} $opc */
    public function agregar(string $metodo, string $patron, callable $manejador, array $opc = []): void
    {
        $regex = preg_replace_callback('/\{(\w+)(?::([^}]+))?\}/', fn ($m) => '(?P<' . $m[1] . '>' . ($m[2] ?? '[^/]+') . ')', $patron);
        $this->rutas[] = [$metodo, '#^' . $regex . '$#', $manejador, $opc + ['auth' => true]];
    }

    public function despachar(Peticion $p): void
    {
        $usuario = null;
        $contada = false; // la operación se cuenta una sola vez, aunque luego falle
        try {
            foreach ($this->rutas as [$metodo, $regex, $manejador, $opc]) {
                if ($metodo !== $p->metodo || !preg_match($regex, $p->ruta, $m)) {
                    continue;
                }
                $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                if ($opc['auth']) {
                    $usuario = Sesion::usuario() ?? throw new NoAutenticadoError();
                }
                if (!in_array($p->metodo, ['GET', 'HEAD'], true) && !Csrf::valido($p->cabecera('X-CSRF-Token') ?? ($p->cuerpo()['_csrf'] ?? null))) {
                    throw new ErrorApp('Token de seguridad inválido. Recarga la página e intenta de nuevo.', 419);
                }
                if (isset($opc['permiso']) && !\RaceControl\Domain\Rol::puede($usuario['rol'], $opc['permiso'])) {
                    throw new PermisoError();
                }
                $contada = $this->medir($p, $usuario, 200);
                $manejador($p, $params, $usuario);
                return;
            }
            throw new NoEncontradoError('La ruta no existe.');
        } catch (ErrorApp $e) {
            $this->medir($p, $usuario, $e->http, $contada);
            $this->responderError($p, $e->getMessage(), $e->http, $e->errores);
        } catch (\Throwable $e) {
            error_log('[RaceControl] ' . $e::class . ': ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
            $this->medir($p, $usuario, 500, $contada);
            $msg = Env::get('APP_ENV') === 'local' ? $e->getMessage() : 'Ocurrió un error inesperado. Intenta de nuevo.';
            $this->responderError($p, $msg, 500, []);
        }
    }

    /** Operaciones y errores alimentan los KPI. Solo se miden las peticiones que modifican datos. */
    private function medir(Peticion $p, ?array $usuario, int $http, bool $yaContada = false): bool
    {
        if (!$p->esApi() || in_array($p->metodo, ['GET', 'HEAD'], true) || str_contains($p->ruta, '/auth/ping') || $http === 419) {
            return $yaContada;
        }
        $id = $usuario['id'] ?? null;
        if (!$yaContada) {
            Actividad::registrar('operacion', $id, null, $p->metodo . ' ' . $p->ruta);
        }
        if ($http >= 400) {
            Actividad::registrar('error', $id, null, "HTTP $http " . $p->ruta);
        }
        return true;
    }

    private function responderError(Peticion $p, string $mensaje, int $http, array $errores): never
    {
        if ($p->esApi()) {
            Respuesta::json(['error' => $mensaje, 'errores' => (object) $errores], $http);
        }
        if ($http === 401) {
            Respuesta::redirigir($p->base . '/login');
        }
        http_response_code($http);
        Pagina::renderizarError($p, $http, $mensaje);
        exit;
    }
}
