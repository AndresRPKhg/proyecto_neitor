<?php
declare(strict_types=1);
namespace RaceControl\Domain;

/** Matriz de permisos por rol (un solo nivel, sin herencia). Siempre se valida en el backend. */
final class Rol
{
    public const ADMIN = 'admin';
    public const EDITOR = 'editor';
    public const LECTOR = 'lector';

    private const PERMISOS = [
        'usuarios.gestionar'      => [self::ADMIN],
        'config.gestionar'        => [self::ADMIN],
        'catalogo.crear'          => [self::ADMIN, self::EDITOR],
        'catalogo.editar'         => [self::ADMIN, self::EDITOR],
        'catalogo.borrar'         => [self::ADMIN],
        'competencia.crear'       => [self::ADMIN, self::EDITOR],
        'competencia.editar'      => [self::ADMIN, self::EDITOR],
        'competencia.borrar'      => [self::ADMIN],
        'inscripciones.gestionar' => [self::ADMIN, self::EDITOR],
        'resultados.gestionar'    => [self::ADMIN, self::EDITOR],
        'reportes.ver'            => [self::ADMIN, self::EDITOR, self::LECTOR],
        'reportes.exportar'       => [self::ADMIN, self::EDITOR, self::LECTOR],
    ];

    public static function puede(string $rol, string $permiso): bool
    {
        return in_array($rol, self::PERMISOS[$permiso] ?? [], true);
    }

    /** Lista de permisos del rol (se envía al frontend solo para mostrar/ocultar botones). */
    public static function permisosDe(string $rol): array
    {
        return array_keys(array_filter(self::PERMISOS, fn ($roles) => in_array($rol, $roles, true)));
    }
}
