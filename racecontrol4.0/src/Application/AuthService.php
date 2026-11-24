<?php
declare(strict_types=1);
namespace RaceControl\Application;

use PDO;
use RaceControl\Domain\PoliticaContrasena;
use RaceControl\Infrastructure\Correo;
use RaceControl\Infrastructure\Database;
use RaceControl\Infrastructure\Env;

/** Casos de uso de autenticación: login con bloqueo, recuperación por correo y cambio de contraseña. */
final class AuthService
{
    public const MAX_INTENTOS = 5;
    public const BLOQUEO_MIN = 15;
    public const TOKEN_MIN = 30;

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::pdo();
    }

    /** @return array fila del usuario autenticado */
    public function iniciarSesion(string $usuario, string $clave, string $ip): array
    {
        $usuario = trim($usuario);
        $st = $this->db->prepare('SELECT u.*, r.nombre AS rol FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE u.usuario=? OR u.email=?');
        $st->execute([$usuario, $usuario]);
        $fila = $st->fetch();
        $clave_usuario = $fila['usuario'] ?? $usuario;

        if ($fila && $fila['bloqueado_hasta'] && strtotime($fila['bloqueado_hasta']) > time()) {
            $min = (int) ceil((strtotime($fila['bloqueado_hasta']) - time()) / 60);
            throw new ErrorApp("Cuenta bloqueada por intentos fallidos. Intenta de nuevo en $min minuto(s).", 423);
        }

        // password_verify se ejecuta siempre para no revelar si el usuario existe por el tiempo de respuesta.
        $hash = $fila['password_hash'] ?? password_hash('relleno-para-igualar-tiempos', PASSWORD_BCRYPT);
        $correcta = password_verify($clave, $hash) && $fila && (int) $fila['activo'] === 1;

        $this->registrarIntento($clave_usuario, $ip, $correcta);
        if (!$correcta) {
            if ($fila && $this->fallosRecientes($clave_usuario) >= self::MAX_INTENTOS) {
                $hasta = date('Y-m-d H:i:s', time() + self::BLOQUEO_MIN * 60);
                $this->db->prepare('UPDATE usuarios SET bloqueado_hasta=? WHERE id=?')->execute([$hasta, $fila['id']]);
                throw new ErrorApp('Demasiados intentos. La cuenta quedó bloqueada ' . self::BLOQUEO_MIN . ' minutos.', 423);
            }
            throw new ErrorApp('Usuario o contraseña incorrectos.', 401);
        }
        $this->db->prepare('UPDATE usuarios SET bloqueado_hasta=NULL WHERE id=?')->execute([$fila['id']]);
        return $fila;
    }

    /** Registro público: crea un usuario con rol lector y contraseña cifrada (Argon2id/bcrypt). */
    public function registrar(array $d): void
    {
        $nombre = trim((string) ($d['nombre'] ?? ''));
        $usuario = trim((string) ($d['usuario'] ?? ''));
        $email = mb_strtolower(trim((string) ($d['email'] ?? '')));
        $clave = (string) ($d['clave'] ?? '');
        $e = [];
        if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 120) { $e['nombre'] = 'Escribe tu nombre (3 a 120 caracteres).'; }
        if (!preg_match('/^[A-Za-z0-9_.-]{3,40}$/', $usuario)) { $e['usuario'] = 'Usuario de 3 a 40 caracteres: letras, números, punto, guion o guion bajo.'; }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 160) { $e['email'] = 'Escribe un correo válido.'; }
        $pol = PoliticaContrasena::validar($clave, $usuario);
        if ($pol) { $e['clave'] = implode(' ', $pol); }
        if ($clave !== (string) ($d['confirmar'] ?? '')) { $e['confirmar'] = 'Las contraseñas no coinciden.'; }
        if (!$e) {
            $st = $this->db->prepare('SELECT usuario, email FROM usuarios WHERE usuario=? OR email=?');
            $st->execute([$usuario, $email]);
            foreach ($st->fetchAll() as $f) {
                if (mb_strtolower($f['usuario']) === mb_strtolower($usuario)) { $e['usuario'] = 'Ese usuario ya está en uso.'; }
                if (mb_strtolower($f['email']) === $email) { $e['email'] = 'Ese correo ya está registrado.'; }
            }
        }
        if ($e) { throw new ValidacionError($e); }
        $hash = password_hash($clave, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
        $this->db->prepare("INSERT INTO usuarios (nombre,usuario,email,password_hash,rol_id) VALUES (?,?,?,?,(SELECT id FROM roles WHERE nombre='lector'))")
            ->execute([$nombre, $usuario, $email, $hash]);
    }

    /** Logout global: invalida todas las sesiones del usuario. */
    public function cerrarSesionesDe(int $usuarioId): void
    {
        $this->db->prepare('UPDATE usuarios SET sesion_version=sesion_version+1 WHERE id=?')->execute([$usuarioId]);
    }

    /** Siempre responde igual, exista o no la cuenta (no se revela qué correos están registrados). */
    public function solicitarRecuperacion(string $identificador): void
    {
        $st = $this->db->prepare('SELECT id,email,nombre FROM usuarios WHERE (usuario=? OR email=?) AND activo=1');
        $st->execute([trim($identificador), trim($identificador)]);
        $u = $st->fetch();
        if (!$u) {
            return;
        }
        $token = bin2hex(random_bytes(32));
        $this->db->prepare('INSERT INTO tokens_recuperacion (usuario_id,token_hash,expira_at,created_at) VALUES (?,?,?,?)')
            ->execute([$u['id'], hash('sha256', $token), date('Y-m-d H:i:s', time() + self::TOKEN_MIN * 60), Database::ahora()]);
        $url = rtrim((string) Env::get('APP_URL', ''), '/') . '/restablecer?token=' . $token;
        Correo::enviar($u['email'], 'Recupera tu acceso a RaceControl',
            "Hola {$u['nombre']},\n\nUsa este enlace para crear una contraseña nueva. Es de un solo uso y vence en " . self::TOKEN_MIN
            . " minutos:\n$url\n\nSi no lo pediste, ignora este mensaje.");
    }

    public function restablecer(string $token, string $nueva): void
    {
        $st = $this->db->prepare('SELECT t.id, t.usuario_id, u.usuario FROM tokens_recuperacion t JOIN usuarios u ON u.id=t.usuario_id
            WHERE t.token_hash=? AND t.usado_at IS NULL AND t.expira_at>?');
        $st->execute([hash('sha256', $token), Database::ahora()]);
        $t = $st->fetch();
        if (!$t) {
            throw new ErrorApp('El enlace no es válido o ya venció. Solicita uno nuevo.', 400);
        }
        $this->validarClave($nueva, $t['usuario'], 'clave');
        $this->db->beginTransaction();
        $this->guardarClave((int) $t['usuario_id'], $nueva);
        $this->db->prepare('UPDATE tokens_recuperacion SET usado_at=? WHERE id=?')->execute([Database::ahora(), $t['id']]);
        $this->db->prepare('UPDATE usuarios SET bloqueado_hasta=NULL WHERE id=?')->execute([$t['usuario_id']]);
        $this->db->commit();
    }

    /** Devuelve la nueva versión de sesión para que la sesión actual siga viva. */
    public function cambiarClave(array $usuario, string $actual, string $nueva): int
    {
        $st = $this->db->prepare('SELECT password_hash FROM usuarios WHERE id=?');
        $st->execute([$usuario['id']]);
        if (!password_verify($actual, (string) $st->fetchColumn())) {
            throw new ValidacionError(['actual' => 'La contraseña actual no es correcta.']);
        }
        $this->validarClave($nueva, $usuario['usuario'], 'nueva');
        $this->guardarClave((int) $usuario['id'], $nueva);
        $v = $this->db->prepare('SELECT sesion_version FROM usuarios WHERE id=?');
        $v->execute([$usuario['id']]);
        return (int) $v->fetchColumn();
    }

    private function validarClave(string $clave, string $usuario, string $campo): void
    {
        $problemas = PoliticaContrasena::validar($clave, $usuario);
        if ($problemas) {
            throw new ValidacionError([$campo => implode(' ', $problemas)]);
        }
    }

    private function guardarClave(int $id, string $clave): void
    {
        $hash = password_hash($clave, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
        $this->db->prepare('UPDATE usuarios SET password_hash=?, sesion_version=sesion_version+1 WHERE id=?')->execute([$hash, $id]);
    }

    private function registrarIntento(string $usuario, string $ip, bool $ok): void
    {
        $this->db->prepare('INSERT INTO intentos_login (usuario,ip,exitoso,created_at) VALUES (?,?,?,?)')
            ->execute([mb_substr($usuario, 0, 40), $ip, (int) $ok, Database::ahora()]);
    }

    private function fallosRecientes(string $usuario): int
    {
        $desde = date('Y-m-d H:i:s', time() - self::BLOQUEO_MIN * 60);
        // Cuenta los fallos posteriores al último inicio de sesión exitoso (por id, no por segundo) dentro de la ventana de bloqueo.
        $st = $this->db->prepare('SELECT COUNT(*) FROM intentos_login WHERE usuario=? AND exitoso=0 AND created_at>?
            AND id > (SELECT COALESCE(MAX(id),0) FROM intentos_login WHERE usuario=? AND exitoso=1)');
        $st->execute([$usuario, $desde, $usuario]);
        return (int) $st->fetchColumn();
    }
}
