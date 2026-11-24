<?php
declare(strict_types=1);
namespace RaceControl\Domain;

/** Estadística descriptiva (media, mediana, moda) y detección de outliers por IQR. */
final class Estadistica
{
    public static function media(array $v): ?float
    {
        return $v ? array_sum($v) / count($v) : null;
    }

    public static function mediana(array $v): ?float
    {
        if (!$v) {
            return null;
        }
        sort($v);
        $n = count($v);
        $mitad = intdiv($n, 2);
        return $n % 2 ? (float) $v[$mitad] : ($v[$mitad - 1] + $v[$mitad]) / 2;
    }

    /** Valor más frecuente (en empate, el menor). */
    public static function moda(array $v): ?float
    {
        if (!$v) {
            return null;
        }
        $conteo = array_count_values(array_map('strval', $v));
        $max = max($conteo);
        return min(array_map('floatval', array_keys(array_filter($conteo, fn ($c) => $c === $max))));
    }

    /** Cuantil con interpolación lineal (0..1). */
    public static function cuantil(array $v, float $q): ?float
    {
        if (!$v) {
            return null;
        }
        sort($v);
        $pos = (count($v) - 1) * $q;
        $base = (int) floor($pos);
        return isset($v[$base + 1]) ? $v[$base] + ($pos - $base) * ($v[$base + 1] - $v[$base]) : (float) $v[$base];
    }

    /** Elementos fuera de [Q1-1.5·IQR, Q3+1.5·IQR], conservando sus claves. */
    public static function outliers(array $v): array
    {
        if (count($v) < 4) {
            return [];
        }
        $q1 = self::cuantil($v, 0.25);
        $q3 = self::cuantil($v, 0.75);
        $iqr = $q3 - $q1;
        return array_filter($v, fn ($x) => $x < $q1 - 1.5 * $iqr || $x > $q3 + 1.5 * $iqr);
    }
}
