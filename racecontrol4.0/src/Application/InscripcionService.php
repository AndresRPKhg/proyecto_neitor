<?php
declare(strict_types=1);
namespace RaceControl\Application;

use PDO;
use PDOException;
use RaceControl\Domain\ReglasInscripcion;
use RaceControl\Infrastructure\Database;

/** Inscripciones de pilotos a carreras, aplicando ReglasInscripcion. */
final class InscripcionService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::pdo();
    }

    public function listar(int $carreraId): array
    {
        $this->carrera($carreraId);
        $st = $this->db->prepare('SELECT i.id, i.piloto_id, i.vehiculo_id, CONCAT(p.nombre," ",p.apellido) AS piloto, p.documento,
                CONCAT(v.marca," ",v.modelo) AS vehiculo, v.placa, v.tipo_vehiculo, e.nombre AS equipo, (r.id IS NOT NULL) AS tiene_resultado
            FROM inscripciones i JOIN pilotos p ON p.id=i.piloto_id JOIN vehiculos v ON v.id=i.vehiculo_id
            LEFT JOIN equipos e ON e.id=p.equipo_id LEFT JOIN resultados r ON r.inscripcion_id=i.id
            WHERE i.carrera_id=? ORDER BY p.apellido, p.nombre');
        $st->execute([$carreraId]);
        return $st->fetchAll();
    }

    public function inscribir(int $carreraId, mixed $pilotoId, mixed $vehiculoId = null): array
    {
        $carrera = $this->carrera($carreraId);
        $piloto = $this->activo('pilotos', (int) $pilotoId);
        if (!$piloto) {
            throw new ValidacionError(['piloto_id' => 'Elige un piloto válido.']);
        }

        $sql = 'SELECT id, tipo_vehiculo FROM vehiculos WHERE piloto_id=? AND deleted_at IS NULL';
        $params = [(int) $pilotoId];
        if ($carrera['tipo_vehiculo'] !== 'mixto') {
            $sql .= ' AND tipo_vehiculo=?';
            $params[] = $carrera['tipo_vehiculo'];
        }
        $st = $this->db->prepare($sql . ' ORDER BY id LIMIT 3');
        $st->execute($params);
        $vehiculos = $st->fetchAll();
        if (!$vehiculos) {
            $tipo = $carrera['tipo_vehiculo'] === 'auto' ? 'un auto'
                : ($carrera['tipo_vehiculo'] === 'moto' ? 'una moto' : 'un auto o una moto');
            throw new ValidacionError(['piloto_id' => "Asigna primero $tipo a este piloto para poder inscribirlo."]);
        }
        if ($carrera['tipo_vehiculo'] !== 'mixto' && count($vehiculos) > 1) {
            throw new ValidacionError(['piloto_id' => 'Este piloto tiene varios vehículos activos de este tipo; deja solo uno asignado.']);
        }
        if ($carrera['tipo_vehiculo'] === 'mixto' && count($vehiculos) > 2) {
            throw new ValidacionError(['piloto_id' => 'Este piloto tiene varios vehículos activos del mismo tipo; revisa sus asignaciones.']);
        }
        if ($carrera['tipo_vehiculo'] === 'mixto' && count(array_unique(array_column($vehiculos, 'tipo_vehiculo'))) !== count($vehiculos)) {
            throw new ValidacionError(['piloto_id' => 'Este piloto tiene varios vehículos activos del mismo tipo; revisa sus asignaciones.']);
        }
        if ($carrera['tipo_vehiculo'] === 'mixto' && $vehiculoId !== null && $vehiculoId !== '') {
            $vehiculoId = filter_var($vehiculoId, FILTER_VALIDATE_INT);
            $coincide = $vehiculoId !== false
                ? array_filter($vehiculos, static fn(array $v): bool => (int) $v['id'] === $vehiculoId)
                : [];
            if (!$coincide) {
                throw new ValidacionError(['vehiculo_id' => 'Elige un vehículo asignado a este piloto.']);
            }
        } elseif ($carrera['tipo_vehiculo'] === 'mixto' && count($vehiculos) > 1) {
            throw new ValidacionError(['vehiculo_id' => 'Este piloto tiene auto y moto asignados; elige cuál usará en esta carrera.']);
        } else {
            $vehiculoId = (int) $vehiculos[0]['id'];
        }
        $vehiculoId = (int) $vehiculoId;
        $vehiculo = $this->activo('vehiculos', $vehiculoId);
        if (!$vehiculo) {
            throw new ValidacionError(['piloto_id' => 'El vehículo asignado ya no está disponible.']);
        }
        $errores = ReglasInscripcion::validar($carrera, $vehiculo, $this->contar($carreraId),
            $this->existe($carreraId, 'piloto_id', (int) $pilotoId), $this->existe($carreraId, 'vehiculo_id', (int) $vehiculoId));
        if ($errores) {
            throw new ValidacionError($errores);
        }
        try {
            $this->db->prepare('INSERT INTO inscripciones (carrera_id,piloto_id,vehiculo_id,created_at) VALUES (?,?,?,?)')
                ->execute([$carreraId, (int) $pilotoId, (int) $vehiculoId, Database::ahora()]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new ValidacionError(['piloto_id' => 'Este piloto o vehículo ya está inscrito en la carrera.']);
            }
            throw $e;
        }
        return ['id' => (int) $this->db->lastInsertId()];
    }

    public function quitar(int $inscripcionId): void
    {
        $st = $this->db->prepare('SELECT i.id, c.estado FROM inscripciones i JOIN carreras c ON c.id=i.carrera_id WHERE i.id=?');
        $st->execute([$inscripcionId]);
        $fila = $st->fetch() ?: throw new NoEncontradoError();
        if ($fila['estado'] === 'finalizada') {
            throw new ErrorApp('La carrera ya finalizó: no se pueden quitar inscritos.', 409);
        }
        $this->db->prepare('DELETE FROM inscripciones WHERE id=?')->execute([$inscripcionId]);
    }

    private function carrera(int $id): array
    {
        $st = $this->db->prepare('SELECT id,campeonato_id,estado,tipo_vehiculo,cupo_maximo FROM carreras WHERE id=?');
        $st->execute([$id]);
        return $st->fetch() ?: throw new NoEncontradoError('La carrera no existe.');
    }

    private function activo(string $tabla, int $id): ?array
    {
        $tabla = $tabla === 'pilotos' ? 'pilotos' : 'vehiculos'; // lista blanca
        $st = $this->db->prepare("SELECT * FROM $tabla WHERE id=? AND deleted_at IS NULL");
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    private function contar(int $carreraId): int
    {
        $st = $this->db->prepare('SELECT COUNT(*) FROM inscripciones WHERE carrera_id=?');
        $st->execute([$carreraId]);
        return (int) $st->fetchColumn();
    }

    private function existe(int $carreraId, string $columna, int $valor): bool
    {
        $columna = $columna === 'piloto_id' ? 'piloto_id' : 'vehiculo_id'; // lista blanca
        $st = $this->db->prepare("SELECT 1 FROM inscripciones WHERE carrera_id=? AND $columna=?");
        $st->execute([$carreraId, $valor]);
        return (bool) $st->fetchColumn();
    }
}
