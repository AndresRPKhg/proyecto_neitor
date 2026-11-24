<?php
declare(strict_types=1);
/**
 * Crea (o actualiza) el usuario administrador sin guardar contraseñas en el código.
 * Uso: php bin/crear_admin.php usuario correo@dominio.com "Nombre Completo"
 * La contraseña se pide por consola (o con la variable RC_ADMIN_PASS en entornos automatizados).
 */
require __DIR__ . '/_arranque.php';

use RaceControl\Domain\PoliticaContrasena;
use RaceControl\Infrastructure\Database;

[$_, $usuario, $correo, $nombre] = array_pad($argv, 4, null);
if (!$usuario || !$correo || !$nombre) {
    fwrite(STDERR, "Uso: php bin/crear_admin.php usuario correo \"Nombre Completo\"\n");
    exit(1);
}
$clave = getenv('RC_ADMIN_PASS') ?: '';
if ($clave === '') {
    fwrite(STDOUT, 'Contraseña (mín. 12 caracteres, mayúscula, minúscula, número y símbolo): ');
    $clave = trim((string) fgets(STDIN));
}
$problemas = PoliticaContrasena::validar($clave, $usuario);
if ($problemas) {
    fwrite(STDERR, "Contraseña no válida:\n - " . implode("\n - ", $problemas) . "\n");
    exit(1);
}
$db = Database::pdo();
$hash = password_hash($clave, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
$db->prepare("INSERT IGNORE INTO roles (id,nombre) VALUES (1,'admin'),(2,'editor'),(3,'lector')")->execute();
$db->prepare("INSERT INTO usuarios (nombre,usuario,email,password_hash,rol_id,created_at) VALUES (?,?,?,?,1,?)
    ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), email=VALUES(email), password_hash=VALUES(password_hash), rol_id=1, activo=1,
    bloqueado_hasta=NULL, sesion_version=sesion_version+1")->execute([$nombre, $usuario, $correo, $hash, Database::ahora()]);
echo "Administrador '$usuario' listo.\n";
