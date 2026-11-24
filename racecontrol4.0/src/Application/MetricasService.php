<?php
declare(strict_types=1);
namespace RaceControl\Application;

use PDO;
use RaceControl\Infrastructure\Database;

/** KPI, datos de gráficos y consolidación diaria de métricas. */
final class MetricasService
{
    public const LATIDO_MIN = 5; // un latido representa 5 minutos de sistema activo

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::pdo();
    }

    /** Registra un latido como máximo cada 4 minutos (también lo puede llamar bin/latido.php desde el programador de tareas). */
    public function latido(): void
    {
        $desde = date('Y-m-d H:i:s', time() - 240);
        $st = $this->db->prepare("SELECT COUNT(*) FROM registro_actividad WHERE tipo='latido' AND created_at>?");
        $st->execute([$desde]);
        if ((int) $st->fetchColumn() === 0) {
            $this->db->prepare("INSERT INTO registro_actividad (tipo,detalle,created_at) VALUES ('latido','heartbeat',?)")->execute([Database::ahora()]);
        }
    }

    public function kpis(int $dias = 30): array
    {
        $desde = date('Y-m-d H:i:s', time() - $dias * 86400);
        $cuenta = function (string $tipo) use ($desde): int {
            $st = $this->db->prepare('SELECT COUNT(*) FROM registro_actividad WHERE tipo=? AND created_at>=?');
            $st->execute([$tipo, $desde]);
            return (int) $st->fetchColumn();
        };
        $operaciones = $cuenta('operacion');
        $errores = $cuenta('error');
        $uso = $this->db->prepare("SELECT modulo, COUNT(*) AS usos FROM registro_actividad WHERE tipo='modulo' AND created_at>=? GROUP BY modulo ORDER BY usos DESC");
        $uso->execute([$desde]);

        return [
            'pilotos_registrados' => (int) $this->db->query('SELECT COUNT(*) FROM pilotos WHERE deleted_at IS NULL')->fetchColumn(),
            'campeonatos' => (int) $this->db->query('SELECT COUNT(*) FROM campeonatos')->fetchColumn(),
            'carreras_programadas' => (int) $this->db->query("SELECT COUNT(*) FROM carreras WHERE estado='programada'")->fetchColumn(),
            'errores_pct' => $operaciones > 0 ? round($errores / $operaciones * 100, 1) : 0.0,
            'tiempo_activo_horas' => round($cuenta('latido') * self::LATIDO_MIN / 60, 1),
            'uso_por_modulo' => $uso->fetchAll(),
            'dias' => $dias,
        ];
    }

    /** Datos para las gráficas: barras, líneas y pastel. */
    public function graficos(?int $campeonatoId = null, ?int $pilotoId = null): array
    {
        $p = [];
        $f = '';
        if ($campeonatoId) {
            $f = 'WHERE pc.campeonato_id=?';
            $p[] = $campeonatoId;
        }
        $st = $this->db->prepare("SELECT COALESCE(e.nombre,'Sin escudería') AS etiqueta, SUM(pc.puntos) AS valor FROM posiciones_campeonato pc
            JOIN pilotos pi ON pi.id=pc.piloto_id LEFT JOIN equipos e ON e.id=pi.equipo_id $f GROUP BY e.id, e.nombre ORDER BY valor DESC");
        $st->execute($p);
        $puntosEquipo = $st->fetchAll();

        $pilotosEquipo = $this->db->query("SELECT COALESCE(e.nombre,'Sin escudería') AS etiqueta, COUNT(*) AS valor FROM pilotos p
            LEFT JOIN equipos e ON e.id=p.equipo_id WHERE p.deleted_at IS NULL GROUP BY e.id, e.nombre ORDER BY valor DESC")->fetchAll();
        $vehiculosTipo = $this->db->query("SELECT IF(tipo_vehiculo='auto','Autos','Motos') AS etiqueta, COUNT(*) AS valor FROM vehiculos
            WHERE deleted_at IS NULL GROUP BY tipo_vehiculo")->fetchAll();

        $tendencia = [];
        if ($pilotoId) {
            $q = 'SELECT ca.nombre AS carrera, ca.fecha, r.puntos, r.tiempo_ms, r.posicion FROM resultados r
                JOIN inscripciones i ON i.id=r.inscripcion_id JOIN carreras ca ON ca.id=i.carrera_id WHERE i.piloto_id=?';
            $pp = [$pilotoId];
            if ($campeonatoId) {
                $q .= ' AND ca.campeonato_id=?';
                $pp[] = $campeonatoId;
            }
            $st = $this->db->prepare($q . ' ORDER BY ca.fecha');
            $st->execute($pp);
            $tendencia = $st->fetchAll();
        }
        return ['puntos_por_equipo' => $puntosEquipo, 'pilotos_por_equipo' => $pilotosEquipo,
            'vehiculos_por_tipo' => $vehiculosTipo, 'tendencia' => $tendencia];
    }

    /** Consolida un día en metricas_diarias (se ejecuta con bin/consolidar.php una vez al día). */
    public function consolidar(string $fecha): void
    {
        $ini = "$fecha 00:00:00";
        $fin = "$fecha 23:59:59";
        $cuenta = function (string $tipo) use ($ini, $fin): int {
            $st = $this->db->prepare('SELECT COUNT(*) FROM registro_actividad WHERE tipo=? AND created_at BETWEEN ? AND ?');
            $st->execute([$tipo, $ini, $fin]);
            return (int) $st->fetchColumn();
        };
        $pilotos = (int) $this->db->query('SELECT COUNT(*) FROM pilotos WHERE deleted_at IS NULL')->fetchColumn();
        $this->db->prepare('INSERT INTO metricas_diarias (fecha,usos,operaciones,errores,latidos,pilotos_activos,created_at) VALUES (?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE usos=VALUES(usos), operaciones=VALUES(operaciones), errores=VALUES(errores),
            latidos=VALUES(latidos), pilotos_activos=VALUES(pilotos_activos)')
            ->execute([$fecha, $cuenta('modulo'), $cuenta('operacion'), $cuenta('error'), $cuenta('latido'), $pilotos, Database::ahora()]);
    }
}
