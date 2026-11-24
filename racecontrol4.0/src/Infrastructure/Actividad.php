<?php
declare(strict_types=1);
namespace RaceControl\Infrastructure;

/** Registro de actividad: alimenta las métricas (uso por módulo, errores, tiempo activo). */
final class Actividad
{
    public static function registrar(string $tipo, ?int $usuarioId = null, ?string $modulo = null, ?string $detalle = null): void
    {
        try {
            $st = Database::pdo()->prepare('INSERT INTO registro_actividad (usuario_id,tipo,modulo,detalle,created_at) VALUES (?,?,?,?,?)');
            $st->execute([$usuarioId, $tipo, $modulo, $detalle !== null ? mb_substr($detalle, 0, 255) : null, Database::ahora()]);
        } catch (\Throwable $e) {
            error_log('No se pudo registrar actividad: ' . $e->getMessage());
        }
    }
}
