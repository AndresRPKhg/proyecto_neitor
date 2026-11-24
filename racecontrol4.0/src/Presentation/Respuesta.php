<?php
declare(strict_types=1);
namespace RaceControl\Presentation;

/** Respuestas HTTP: JSON, CSV, HTML y redirecciones. */
final class Respuesta
{
    public static function json(mixed $datos, int $http = 200): never
    {
        http_response_code($http);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        exit;
    }

    public static function csv(string $nombre, string $contenido): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-z0-9_.-]/i', '_', $nombre) . '"');
        header('Cache-Control: no-store');
        echo $contenido;
        exit;
    }

    public static function redirigir(string $url): never
    {
        header('Location: ' . $url, true, 302);
        exit;
    }

    public static function cabecerasSeguridad(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; font-src 'self' data:; frame-ancestors 'none'");
    }
}
