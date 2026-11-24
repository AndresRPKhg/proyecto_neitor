<?php
declare(strict_types=1);
namespace RaceControl\Infrastructure;

use PDO;

/** Conexión PDO única. Todas las consultas del sistema usan sentencias preparadas. */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                Env::get('DB_HOST', '127.0.0.1'), Env::get('DB_PORT', '3306'), Env::get('DB_NAME', 'racecontrol'));
            self::$pdo = new PDO($dsn, Env::get('DB_USER', 'root'), Env::get('DB_PASS', ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }
        return self::$pdo;
    }

    /** Permite inyectar otra conexión (pruebas). */
    public static function usar(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function ahora(): string
    {
        return date('Y-m-d H:i:s');
    }
}
