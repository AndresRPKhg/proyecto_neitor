<?php
declare(strict_types=1);
namespace RaceControl\Infrastructure;

/** Token CSRF por sesión; se exige en toda petición que modifica datos. */
final class Csrf
{
    public static function token(): string
    {
        Sesion::iniciar();
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function valido(?string $recibido): bool
    {
        Sesion::iniciar();
        return $recibido !== null && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $recibido);
    }
}
