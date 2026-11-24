<?php
declare(strict_types=1);
namespace RaceControl\Infrastructure;

/** Lee la configuración desde .env (12-factor). Las claves nunca van en el código. */
final class Env
{
    private static array $valores = [];

    public static function cargar(string $archivo): void
    {
        if (!is_file($archivo)) {
            return;
        }
        foreach (file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
            $linea = trim($linea);
            if ($linea === '' || $linea[0] === '#' || !str_contains($linea, '=')) {
                continue;
            }
            [$clave, $valor] = explode('=', $linea, 2);
            $valor = trim(preg_replace('/\s+#.*$/', '', $valor)); // quita comentarios al final de la línea
            self::$valores[trim($clave)] = trim($valor, " \t\"'");
        }
    }

    public static function get(string $clave, ?string $defecto = null): ?string
    {
        return self::$valores[$clave] ?? $_ENV[$clave] ?? (getenv($clave) !== false ? getenv($clave) : $defecto);
    }
}
