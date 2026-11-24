<?php
declare(strict_types=1);
namespace RaceControl\Application;

use PDO;
use RaceControl\Domain\SistemaPuntos;
use RaceControl\Domain\Tiempo;
use RaceControl\Infrastructure\Database;

/** Registro de resultados, cálculo de posiciones y puntos, y recálculo de la tabla del campeonato. */
final class ResultadoService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::pdo();
    }

    /** Inscritos de la carrera con su resultado (si existe). */
    public function obtener(int $carreraId): array
    {
        $this->carrera($carreraId);
        $st = $this->db->prepare('SELECT i.id AS inscripcion_id, CONCAT(p.nombre," ",p.apellido) AS piloto, p.documento,
                CONCAT(v.marca," ",v.modelo) AS vehiculo, v.tipo_vehiculo, r.estado, r.posicion, r.tiempo_ms, r.mejor_vuelta_ms, r.puntos
            FROM inscripciones i JOIN pilotos p ON p.id=i.piloto_id JOIN vehiculos v ON v.id=i.vehiculo_id
            LEFT JOIN resultados r ON r.inscripcion_id=i.id WHERE i.carrera_id=?
            ORDER BY (r.posicion IS NULL), r.posicion, p.apellido');
        $st->execute([$carreraId]);
        return array_map(function ($f) {
            $f['tiempo'] = Tiempo::formato($f['tiempo_ms'] !== null ? (int) $f['tiempo_ms'] : null);
            $f['mejor_vuelta'] = Tiempo::formato($f['mejor_vuelta_ms'] !== null ? (int) $f['mejor_vuelta_ms'] : null);
            $f['estado'] = $f['estado'] ?? 'clasificado';
            return $f;
        }, $st->fetchAll());
    }

    /**
     * Guarda (o corrige) resultados. Cada fila: inscripcion_id, estado, tiempo, mejor_vuelta (mm:ss.mmm).
     * @return array resultados recalculados de la carrera
     */
    public function guardar(int $carreraId, array $filas): array
    {
        $carrera = $this->carrera($carreraId);
        $validas = $this->idsInscripcion($carreraId);
        $errores = [];
        $limpias = [];
        foreach ($filas as $i => $f) {
            $id = (int) ($f['inscripcion_id'] ?? 0);
            $clave = "fila_$id";
            if (!isset($validas[$id])) {
                $errores["fila_$i"] = 'La inscripción no pertenece a esta carrera.';
                continue;
            }
            $estado = (string) ($f['estado'] ?? 'clasificado');
            $tiempo = Tiempo::aMs($f['tiempo'] ?? '');
            $vuelta = Tiempo::aMs($f['mejor_vuelta'] ?? '');
            if (!in_array($estado, ['clasificado', 'dnf', 'dsq'], true)) {
                $errores[$clave] = 'Estado no válido.';
            } elseif ($tiempo === false || $vuelta === false) {
                $errores[$clave] = 'Usa el formato mm:ss.mmm (por ejemplo 45:12.345).';
            } elseif ($estado === 'clasificado' && $tiempo === null) {
                $errores[$clave] = 'Un piloto clasificado necesita tiempo total.';
            } else {
                $limpias[] = [$id, $estado, $estado === 'clasificado' ? $tiempo : null, $vuelta];
            }
        }
        if ($errores) {
            throw new ValidacionError($errores, 'Hay filas con errores. Corrígelas y vuelve a guardar.');
        }
        $this->db->beginTransaction();
        try {
            $up = $this->db->prepare('INSERT INTO resultados (inscripcion_id,estado,tiempo_ms,mejor_vuelta_ms,created_at) VALUES (?,?,?,?,?)
                ON DUPLICATE KEY UPDATE estado=VALUES(estado), tiempo_ms=VALUES(tiempo_ms), mejor_vuelta_ms=VALUES(mejor_vuelta_ms)');
            foreach ($limpias as [$id, $estado, $tiempo, $vuelta]) {
                $up->execute([$id, $estado, $tiempo, $vuelta, Database::ahora()]);
            }
            $this->recalcularCarrera($carreraId, (int) $carrera['campeonato_id']);
            $this->db->prepare("UPDATE carreras SET estado='finalizada' WHERE id=?")->execute([$carreraId]);
            $this->recalcularPosiciones((int) $carrera['campeonato_id']);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        return $this->obtener($carreraId);
    }

    /**
     * Importa un CSV con columnas: documento,tiempo,mejor_vuelta,estado (estado opcional).
     * @return array resultados recalculados de la carrera
     */
    public function importarCsv(int $carreraId, string $csv): array
    {
        $this->carrera($carreraId);
        $porDocumento = [];
        foreach ($this->idsInscripcion($carreraId) as $id => $documento) {
            $porDocumento[$documento] = $id;
        }
        $lineas = preg_split('/\r\n|\n|\r/', trim(preg_replace('/^\xEF\xBB\xBF/', '', $csv)));
        $cab = array_map(fn ($c) => mb_strtolower(trim($c)), str_getcsv(array_shift($lineas) ?? '', ',', '"', ''));
        if (!in_array('documento', $cab, true) || !in_array('tiempo', $cab, true)) {
            throw new ErrorApp('El CSV debe tener al menos las columnas "documento" y "tiempo".', 422);
        }
        $filas = [];
        $errores = [];
        foreach ($lineas as $n => $linea) {
            if (trim($linea) === '') {
                continue;
            }
            $v = array_map('trim', str_getcsv($linea, ',', '"', ''));
            $fila = array_combine($cab, array_pad($v, count($cab), ''));
            $doc = $fila['documento'];
            if (!isset($porDocumento[$doc])) {
                $errores['linea_' . ($n + 2)] = "Línea " . ($n + 2) . ": el documento '$doc' no está inscrito en esta carrera.";
                continue;
            }
            $filas[] = ['inscripcion_id' => $porDocumento[$doc], 'tiempo' => $fila['tiempo'],
                'mejor_vuelta' => $fila['mejor_vuelta'] ?? '', 'estado' => ($fila['estado'] ?? '') ?: 'clasificado'];
        }
        if ($errores) {
            throw new ValidacionError($errores, 'El CSV tiene líneas con problemas; no se importó nada.');
        }
        return $this->guardar($carreraId, $filas);
    }

    /** Asigna posición y puntos a todos los resultados de la carrera según el tiempo. */
    private function recalcularCarrera(int $carreraId, int $campeonatoId): void
    {
        $puntos = $this->puntosDe($campeonatoId);
        $st = $this->db->prepare('SELECT r.id, r.estado FROM resultados r JOIN inscripciones i ON i.id=r.inscripcion_id
            WHERE i.carrera_id=? ORDER BY (r.estado<>"clasificado"), r.tiempo_ms, r.id');
        $st->execute([$carreraId]);
        $upd = $this->db->prepare('UPDATE resultados SET posicion=?, puntos=? WHERE id=?');
        $pos = 0;
        foreach ($st->fetchAll() as $r) {
            if ($r['estado'] === 'clasificado') {
                $pos++;
                $upd->execute([$pos, SistemaPuntos::paraPosicion($puntos, $pos), $r['id']]);
            } else {
                $upd->execute([null, 0, $r['id']]);
            }
        }
    }

    /** Recalcula toda la tabla de un campeonato (tras cambiar el sistema de puntos). */
    public function recalcularCampeonato(int $campeonatoId): void
    {
        $st = $this->db->prepare("SELECT id FROM carreras WHERE campeonato_id=? AND estado='finalizada'");
        $st->execute([$campeonatoId]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $carreraId) {
            $this->recalcularCarrera((int) $carreraId, $campeonatoId);
        }
        $this->recalcularPosiciones($campeonatoId);
    }

    /** Tabla agregada posiciones_campeonato: se recalcula cada vez que se guardan resultados. */
    public function recalcularPosiciones(int $campeonatoId): void
    {
        $propia = !$this->db->inTransaction();
        $propia && $this->db->beginTransaction();
        $this->db->prepare('DELETE FROM posiciones_campeonato WHERE campeonato_id=?')->execute([$campeonatoId]);
        $this->db->prepare("INSERT INTO posiciones_campeonato (campeonato_id,piloto_id,puntos,victorias,podios,carreras,created_at)
            SELECT c.id, i.piloto_id, COALESCE(SUM(r.puntos),0),
                   COALESCE(SUM(r.estado='clasificado' AND r.posicion=1),0), COALESCE(SUM(r.estado='clasificado' AND r.posicion<=3),0), COUNT(*), ?
            FROM resultados r JOIN inscripciones i ON i.id=r.inscripcion_id JOIN carreras c ON c.id=i.carrera_id
            WHERE c.campeonato_id=? GROUP BY c.id, i.piloto_id")->execute([Database::ahora(), $campeonatoId]);
        $propia && $this->db->commit();
    }

    /** Tabla de posiciones con empates resueltos por victorias y podios. */
    public function posiciones(int $campeonatoId): array
    {
        $st = $this->db->prepare('SELECT pc.piloto_id, CONCAT(p.nombre," ",p.apellido) AS piloto, e.nombre AS equipo,
                pc.puntos, pc.victorias, pc.podios, pc.carreras
            FROM posiciones_campeonato pc JOIN pilotos p ON p.id=pc.piloto_id LEFT JOIN equipos e ON e.id=p.equipo_id
            WHERE pc.campeonato_id=? ORDER BY pc.puntos DESC, pc.victorias DESC, pc.podios DESC, p.apellido');
        $st->execute([$campeonatoId]);
        $filas = $st->fetchAll();
        foreach ($filas as $i => &$f) {
            $f['posicion'] = $i + 1;
        }
        return $filas;
    }

    private function puntosDe(int $campeonatoId): array
    {
        $st = $this->db->prepare('SELECT puntos FROM campeonatos WHERE id=?');
        $st->execute([$campeonatoId]);
        return SistemaPuntos::parsear((string) $st->fetchColumn()) ?? SistemaPuntos::parsear(SistemaPuntos::DEFECTO);
    }

    /** @return array<int,string> inscripcion_id => documento del piloto */
    private function idsInscripcion(int $carreraId): array
    {
        $st = $this->db->prepare('SELECT i.id, p.documento FROM inscripciones i JOIN pilotos p ON p.id=i.piloto_id WHERE i.carrera_id=?');
        $st->execute([$carreraId]);
        return array_column($st->fetchAll(), 'documento', 'id');
    }

    private function carrera(int $id): array
    {
        $st = $this->db->prepare('SELECT id,campeonato_id,estado FROM carreras WHERE id=?');
        $st->execute([$id]);
        return $st->fetch() ?: throw new NoEncontradoError('La carrera no existe.');
    }
}
