<?php
declare(strict_types=1);
namespace RaceControl\Domain;

/** Puntos por posición. Se configura por campeonato (por defecto 25-18-15-12-10-8-6-4-2-1). */
final class SistemaPuntos
{
    public const DEFECTO = '25,18,15,12,10,8,6,4,2,1';

    /** @return int[]|null null si el texto no es válido */
    public static function parsear(string $texto): ?array
    {
        $puntos = [];
        foreach (array_map('trim', explode(',', $texto)) as $p) {
            if (!ctype_digit($p) || (int) $p > 1000) {
                return null;
            }
            $puntos[] = (int) $p;
        }
        return $puntos ?: null;
    }

    public static function paraPosicion(array $puntos, int $posicion): int
    {
        return $puntos[$posicion - 1] ?? 0;
    }
}
