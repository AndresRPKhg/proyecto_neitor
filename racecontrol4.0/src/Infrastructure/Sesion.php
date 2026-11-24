<?php
declare(strict_types=1);
namespace RaceControl\Infrastructure;

/**
 * Sesión segura: cookie HttpOnly/Secure/SameSite, máximo 8 h con renovación deslizante,
 * cierre a los 15 min de inactividad y logout global mediante usuarios.sesion_version.
 */
final class Sesion
{
    public const MAXIMO = 8 * 3600;
    public const INACTIVIDAD = 15 * 60;

    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || Env::get('APP_ENV') === 'production';
        session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'secure' => $https, 'samesite' => 'Lax']);
        session_name('rc_sesion');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', (string) self::MAXIMO);
        session_start();
    }

    /** Devuelve el usuario autenticado o null si la sesión no es válida (caducada, inactiva o invalidada). */
    public static function usuario(): ?array
    {
        self::iniciar();
        $s = $_SESSION['auth'] ?? null;
        if (!$s) {
            return null;
        }
        $ahora = time();
        if ($ahora - $s['inicio'] > self::MAXIMO || $ahora - $s['ultima'] > self::INACTIVIDAD) {
            self::cerrar();
            return null;
        }
        $fila = self::buscar((int) $s['id']);
        if (!$fila || !(int) $fila['activo'] || (int) $fila['sesion_version'] !== (int) $s['version']) {
            self::cerrar();
            return null;
        }
        $_SESSION['auth']['ultima'] = $ahora; // renovación deslizante, sin pasar del máximo
        return $fila;
    }

    public static function abrir(array $usuario): void
    {
        self::iniciar();
        session_regenerate_id(true);
        $_SESSION['auth'] = ['id' => (int) $usuario['id'], 'version' => (int) $usuario['sesion_version'],
            'inicio' => time(), 'ultima' => time()];
        unset($_SESSION['csrf']);
    }

    /** Actualiza la versión guardada (tras cambiar contraseña) para no expulsar la sesión actual. */
    public static function sincronizarVersion(int $version): void
    {
        $_SESSION['auth']['version'] = $version;
    }

    public static function cerrar(): void
    {
        self::iniciar();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'],
                'httponly' => true, 'samesite' => 'Lax']);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /** Segundos que quedan antes de que cierre por inactividad. */
    public static function restante(): int
    {
        return max(0, self::INACTIVIDAD - (time() - (int) ($_SESSION['auth']['ultima'] ?? 0)));
    }

    private static function buscar(int $id): ?array
    {
        $st = Database::pdo()->prepare('SELECT u.id,u.nombre,u.usuario,u.email,u.activo,u.sesion_version,r.nombre AS rol
            FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE u.id=?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }
}
