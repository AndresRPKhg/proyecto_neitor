<?php
declare(strict_types=1);
namespace RaceControl\Domain;

/** Normaliza tiempos al formato mm:ss.mmm y los convierte desde/hacia milisegundos. */
final class Tiempo
{
    /** Devuelve milisegundos, null si está vacío o false si el formato es inválido. */
    public static function aMs(?string $texto): int|false|null
    {
        $texto = trim((string) $texto);
        if ($texto === '') {
            return null;
        }
        if (!preg_match('/^(\d{1,3}):([0-5]\d)\.(\d{3})$/', $texto, $m)) {
            return false;
        }
        return ((int) $m[1] * 60 + (int) $m[2]) * 1000 + (int) $m[3];
    }

    public static function formato(?int $ms): string
    {
        if ($ms === null) {
            return '';
        }
        return sprintf('%02d:%02d.%03d', intdiv($ms, 60000), intdiv($ms % 60000, 1000), $ms % 1000);
    }
}
