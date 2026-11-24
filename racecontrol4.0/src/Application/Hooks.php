<?php
declare(strict_types=1);
namespace RaceControl\Application;

use PDO;

/** Reglas de negocio específicas de cada recurso que no caben en la definición declarativa. */
final class Hooks
{
    public static function antes(string $recurso, array $limpios, ?int $id, array &$errores, ?array $existente, PDO $db): array
    {
        if ($recurso === 'carreras' && !isset($errores['campeonato_id']) && isset($limpios['campeonato_id'])) {
            $st = $db->prepare('SELECT tipo_vehiculo FROM campeonatos WHERE id=?');
            $st->execute([$limpios['campeonato_id']]);
            $tipoCampeonato = $st->fetchColumn();
            if ($tipoCampeonato === 'mixto') {
                if (!in_array($limpios['tipo_vehiculo'] ?? null, ['auto', 'moto', 'mixto'], true)) {
                    $errores['tipo_vehiculo'] = 'Elige si esta carrera será de autos, motos o mixta.';
                }
            } elseif (($limpios['tipo_vehiculo'] ?? null) !== 'mixto') {
                $limpios['tipo_vehiculo'] = $tipoCampeonato;
            }
            if ($existente && (int) $existente['campeonato_id'] !== $limpios['campeonato_id'] && ((int) $existente['inscritos'] > 0 || $existente['estado'] === 'finalizada')) {
                $errores['campeonato_id'] = 'No puedes mover una carrera con inscritos o resultados a otro campeonato.';
            }
            if ($existente && $existente['tipo_vehiculo'] !== $limpios['tipo_vehiculo'] && (int) $existente['inscritos'] > 0) {
                $errores['tipo_vehiculo'] = 'No puedes cambiar el tipo de una carrera que ya tiene pilotos inscritos.';
            }
            if ($existente && (int) $existente['inscritos'] > 0 && (int) ($limpios['cupo_maximo'] ?? 0) < (int) $existente['inscritos']) {
                $errores['cupo_maximo'] = 'El cupo no puede ser menor que los ' . $existente['inscritos'] . ' pilotos ya inscritos.';
            }
        }
        if ($recurso === 'campeonatos' && $existente && isset($limpios['tipo_vehiculo'])
            && $limpios['tipo_vehiculo'] !== $existente['tipo_vehiculo'] && $limpios['tipo_vehiculo'] !== 'mixto'
            && (int) $existente['total_carreras'] > 0) {
            $errores['tipo_vehiculo'] = 'No puedes cambiar el tipo: el campeonato ya tiene carreras.';
        }
        if ($recurso === 'vehiculos' && !isset($errores['piloto_id']) && !isset($errores['equipo_id'])
            && ($limpios['piloto_id'] ?? null) !== null) {
            $equipoId = $limpios['equipo_id'] ?? null;
            $st = $db->prepare('SELECT equipo_id FROM pilotos WHERE id=? AND deleted_at IS NULL');
            $st->execute([$limpios['piloto_id']]);
            $equipoPiloto = $st->fetchColumn();
            if ($equipoId === null || $equipoPiloto === false || $equipoPiloto === null || (int) $equipoPiloto !== (int) $equipoId) {
                $errores['piloto_id'] = 'El piloto debe pertenecer a la misma escudería que el vehículo.';
            }
        }
        if ($recurso === 'vehiculos' && !isset($errores['piloto_id']) && !isset($errores['tipo_vehiculo'])
            && ($limpios['piloto_id'] ?? null) !== null) {
            $sql = 'SELECT 1 FROM vehiculos WHERE piloto_id=? AND tipo_vehiculo=? AND deleted_at IS NULL';
            $params = [$limpios['piloto_id'], $limpios['tipo_vehiculo']];
            if ($id !== null) {
                $sql .= ' AND id<>?';
                $params[] = $id;
            }
            $st = $db->prepare($sql . ' LIMIT 1');
            $st->execute($params);
            if ($st->fetchColumn()) {
                $errores['piloto_id'] = 'Cada piloto puede tener un solo vehículo activo de cada tipo.';
            }
        }
        if ($recurso === 'pilotos' && $id !== null && !isset($errores['equipo_id']) && array_key_exists('equipo_id', $limpios)) {
            $sql = $limpios['equipo_id'] === null
                ? 'SELECT 1 FROM vehiculos WHERE piloto_id=? LIMIT 1'
                : 'SELECT 1 FROM vehiculos WHERE piloto_id=? AND (equipo_id IS NULL OR equipo_id<>?) LIMIT 1';
            $st = $db->prepare($sql);
            $params = $limpios['equipo_id'] === null ? [$id] : [$id, $limpios['equipo_id']];
            $st->execute($params);
            if ($st->fetchColumn()) {
                $errores['equipo_id'] = 'Primero reasigna los vehículos de este piloto a su nueva escudería.';
            }
        }
        if ($recurso === 'usuarios' && $existente) {
            $dejaDeSerAdmin = $existente['rol'] === 'admin' && (
                (isset($limpios['rol_id']) && (int) $limpios['rol_id'] !== (int) $existente['rol_id'])
                || (isset($limpios['activo']) && (int) $limpios['activo'] === 0));
            if ($dejaDeSerAdmin && self::adminsActivos($db) <= 1) {
                $errores['rol_id'] = 'Debe quedar al menos un administrador activo.';
            }
        }
        return $limpios;
    }

    public static function despuesGuardar(string $recurso, int $id, array $limpios, ?array $existente, PDO $db): void
    {
        // Cambios sensibles en un usuario invalidan todas sus sesiones (logout global).
        if ($recurso === 'usuarios' && $existente !== null) {
            $sensible = isset($limpios['password_hash']) || (int) ($limpios['activo'] ?? 1) === 0
                || (int) ($limpios['rol_id'] ?? $existente['rol_id']) !== (int) $existente['rol_id'];
            if ($sensible) {
                $db->prepare('UPDATE usuarios SET sesion_version=sesion_version+1 WHERE id=?')->execute([$id]);
            }
        }
        // Si cambia el sistema de puntos de un campeonato, se recalculan sus resultados.
        if ($recurso === 'campeonatos' && $existente !== null && isset($limpios['puntos']) && $limpios['puntos'] !== $existente['puntos']) {
            (new ResultadoService($db))->recalcularCampeonato($id);
        }
    }

    public static function antesBorrar(string $recurso, array $existente, array $ctx, PDO $db): void
    {
        if ($recurso === 'usuarios') {
            if ((int) $existente['id'] === (int) ($ctx['usuario']['id'] ?? 0)) {
                throw new ErrorApp('No puedes eliminar tu propia cuenta.', 409);
            }
            if ($existente['rol'] === 'admin' && self::adminsActivos($db) <= 1) {
                throw new ErrorApp('Debe quedar al menos un administrador activo.', 409);
            }
        }
        if ($recurso === 'campeonatos' && (int) $existente['total_carreras'] > 0) {
            throw new ErrorApp('Primero elimina las carreras del campeonato.', 409);
        }
        if ($recurso === 'equipos') {
            $st = $db->prepare('SELECT
                (SELECT COUNT(*) FROM pilotos WHERE equipo_id=? AND deleted_at IS NULL) +
                (SELECT COUNT(*) FROM vehiculos WHERE equipo_id=? AND deleted_at IS NULL)');
            $st->execute([$existente['id'], $existente['id']]);
            if ((int) $st->fetchColumn() > 0) {
                throw new ErrorApp('Primero reasigna los pilotos y vehículos activos a otra escudería.', 409);
            }
        }
        if ($recurso === 'pilotos') {
            $st = $db->prepare('SELECT 1 FROM vehiculos WHERE piloto_id=? AND deleted_at IS NULL LIMIT 1');
            $st->execute([$existente['id']]);
            if ($st->fetchColumn()) {
                throw new ErrorApp('Antes de eliminarlo, desasigna al piloto de sus vehículos.', 409);
            }
        }
    }

    public static function despuesBorrar(string $recurso, array $existente, PDO $db): void
    {
        if ($recurso === 'carreras') {
            (new ResultadoService($db))->recalcularPosiciones((int) $existente['campeonato_id']);
        }
    }

    private static function adminsActivos(PDO $db): int
    {
        return (int) $db->query("SELECT COUNT(*) FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE r.nombre='admin' AND u.activo=1")->fetchColumn();
    }
}
