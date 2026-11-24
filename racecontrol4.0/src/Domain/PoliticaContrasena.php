<?php
declare(strict_types=1);
namespace RaceControl\Domain;

/** Política fuerte: mínimo 12 caracteres, mayúscula, minúscula, número y símbolo; sin contraseñas comunes. */
final class PoliticaContrasena
{
    private const COMUNES = ['password', 'contrasena', 'contraseña', '123456789012', 'qwertyuiop', 'administrador',
        'racecontrol', 'abcdefghijkl', 'iloveyou', 'welcome', 'letmein', 'admin1234'];

    /** @return string[] lista de problemas (vacía si la contraseña es válida) */
    public static function validar(string $clave, string $usuario = ''): array
    {
        $errores = [];
        if (mb_strlen($clave) < 12) {
            $errores[] = 'Debe tener al menos 12 caracteres.';
        }
        if (!preg_match('/\p{Lu}/u', $clave)) {
            $errores[] = 'Debe incluir una letra mayúscula.';
        }
        if (!preg_match('/\p{Ll}/u', $clave)) {
            $errores[] = 'Debe incluir una letra minúscula.';
        }
        if (!preg_match('/\d/', $clave)) {
            $errores[] = 'Debe incluir un número.';
        }
        if (!preg_match('/[^\p{L}\d]/u', $clave)) {
            $errores[] = 'Debe incluir un símbolo.';
        }
        $minuscula = mb_strtolower($clave);
        foreach (self::COMUNES as $comun) {
            if (str_contains($minuscula, $comun)) {
                $errores[] = 'Es una contraseña demasiado común.';
                break;
            }
        }
        if ($usuario !== '' && str_contains($minuscula, mb_strtolower($usuario))) {
            $errores[] = 'No puede contener tu nombre de usuario.';
        }
        return $errores;
    }
}
