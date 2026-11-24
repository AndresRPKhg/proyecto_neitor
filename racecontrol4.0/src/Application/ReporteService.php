<?php
declare(strict_types=1);
namespace RaceControl\Application;

use PDO;
use RaceControl\Domain\Estadistica;
use RaceControl\Domain\Tiempo;
use RaceControl\Infrastructure\Database;

/** Reportes tabulares (JSON o CSV): posiciones, resultados, estadísticas, outliers y calidad de datos. */
final class ReporteService
{
    public const TIPOS = [
        'posiciones' => 'Tabla de posiciones de un campeonato',
        'resultados' => 'Resultados de una carrera',
        'pilotos' => 'Listado de pilotos',
        'estadisticas' => 'Estadística descriptiva (media, mediana, moda)',
        'outliers' => 'Tiempos fuera de rango (IQR) para revisión',
        'calidad' => 'Calidad de datos: nulos y faltantes',
    ];

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::pdo();
    }

    /** @return array{titulo:string,columnas:array<string,string>,filas:array} */
    public function generar(string $tipo, array $q): array
    {
        return match ($tipo) {
            'posiciones' => $this->posiciones((int) ($q['campeonato_id'] ?? 0)),
            'resultados' => $this->resultados((int) ($q['carrera_id'] ?? 0)),
            'pilotos' => $this->pilotos(),
            'estadisticas' => $this->estadisticas(),
            'outliers' => $this->outliers(),
            'calidad' => $this->calidad(),
            default => throw new NoEncontradoError('Reporte desconocido.'),
        };
    }

    private function posiciones(int $campeonatoId): array
    {
        if (!$campeonatoId) {
            throw new ValidacionError(['campeonato_id' => 'Elige un campeonato.']);
        }
        $filas = (new ResultadoService($this->db))->posiciones($campeonatoId);
        return ['titulo' => 'Tabla de posiciones', 'columnas' => ['posicion' => 'Pos.', 'piloto' => 'Piloto', 'equipo' => 'Escudería',
            'puntos' => 'Puntos', 'victorias' => 'Victorias', 'podios' => 'Podios', 'carreras' => 'Carreras'], 'filas' => $filas];
    }

    private function resultados(int $carreraId): array
    {
        if (!$carreraId) {
            throw new ValidacionError(['carrera_id' => 'Elige una carrera.']);
        }
        $filas = (new ResultadoService($this->db))->obtener($carreraId);
        return ['titulo' => 'Resultados de la carrera', 'columnas' => ['posicion' => 'Pos.', 'piloto' => 'Piloto', 'vehiculo' => 'Vehículo',
            'estado' => 'Estado', 'tiempo' => 'Tiempo', 'mejor_vuelta' => 'Mejor vuelta', 'puntos' => 'Puntos'], 'filas' => $filas];
    }

    private function pilotos(): array
    {
        $filas = $this->db->query('SELECT CONCAT(p.nombre," ",p.apellido) AS piloto, p.documento, p.nacionalidad, p.fecha_nacimiento, e.nombre AS equipo
            FROM pilotos p LEFT JOIN equipos e ON e.id=p.equipo_id WHERE p.deleted_at IS NULL ORDER BY p.apellido')->fetchAll();
        return ['titulo' => 'Pilotos registrados', 'columnas' => ['piloto' => 'Piloto', 'documento' => 'Documento', 'nacionalidad' => 'Nacionalidad',
            'fecha_nacimiento' => 'Nacimiento', 'equipo' => 'Escudería'], 'filas' => $filas];
    }

    public function estadisticas(): array
    {
        $vueltas = array_map('intval', $this->db->query('SELECT mejor_vuelta_ms FROM resultados WHERE mejor_vuelta_ms IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN));
        $puntos = array_map('floatval', $this->db->query('SELECT puntos FROM posiciones_campeonato')->fetchAll(PDO::FETCH_COLUMN));
        $pos = array_map('intval', $this->db->query('SELECT posicion FROM resultados WHERE posicion IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN));
        $ms = fn (?float $v) => $v === null ? '' : Tiempo::formato((int) round($v));
        $num = fn (?float $v) => $v === null ? '' : (string) round($v, 2);
        $filas = [
            ['metrica' => 'Mejor vuelta (mm:ss.mmm)', 'muestras' => count($vueltas), 'media' => $ms(Estadistica::media($vueltas)),
                'mediana' => $ms(Estadistica::mediana($vueltas)), 'moda' => $ms(Estadistica::moda($vueltas))],
            ['metrica' => 'Puntos por piloto', 'muestras' => count($puntos), 'media' => $num(Estadistica::media($puntos)),
                'mediana' => $num(Estadistica::mediana($puntos)), 'moda' => $num(Estadistica::moda($puntos))],
            ['metrica' => 'Posición de llegada', 'muestras' => count($pos), 'media' => $num(Estadistica::media($pos)),
                'mediana' => $num(Estadistica::mediana($pos)), 'moda' => $num(Estadistica::moda($pos))],
        ];
        return ['titulo' => 'Estadística descriptiva', 'columnas' => ['metrica' => 'Métrica', 'muestras' => 'Muestras', 'media' => 'Media',
            'mediana' => 'Mediana', 'moda' => 'Moda'], 'filas' => $filas];
    }

    /** Mejores vueltas fuera de [Q1-1.5·IQR, Q3+1.5·IQR] dentro de cada carrera. */
    public function outliers(): array
    {
        $st = $this->db->query('SELECT ca.id AS carrera_id, ca.nombre AS carrera, CONCAT(p.nombre," ",p.apellido) AS piloto, r.mejor_vuelta_ms
            FROM resultados r JOIN inscripciones i ON i.id=r.inscripcion_id JOIN carreras ca ON ca.id=i.carrera_id JOIN pilotos p ON p.id=i.piloto_id
            WHERE r.mejor_vuelta_ms IS NOT NULL ORDER BY ca.id');
        $porCarrera = [];
        foreach ($st->fetchAll() as $f) {
            $porCarrera[$f['carrera_id']][] = $f;
        }
        $filas = [];
        foreach ($porCarrera as $grupo) {
            $valores = array_map(fn ($g) => (int) $g['mejor_vuelta_ms'], $grupo);
            foreach (array_keys(Estadistica::outliers($valores)) as $i) {
                $filas[] = ['carrera' => $grupo[$i]['carrera'], 'piloto' => $grupo[$i]['piloto'],
                    'mejor_vuelta' => Tiempo::formato((int) $grupo[$i]['mejor_vuelta_ms']), 'motivo' => 'Fuera del rango IQR: revisar el dato'];
            }
        }
        return ['titulo' => 'Tiempos fuera de rango', 'columnas' => ['carrera' => 'Carrera', 'piloto' => 'Piloto',
            'mejor_vuelta' => 'Mejor vuelta', 'motivo' => 'Motivo'], 'filas' => $filas];
    }

    public function calidad(): array
    {
        $consultas = [
            'Resultados clasificados sin tiempo' => "SELECT COUNT(*) FROM resultados WHERE estado='clasificado' AND tiempo_ms IS NULL",
            'Inscritos sin resultado en carreras finalizadas' => "SELECT COUNT(*) FROM inscripciones i JOIN carreras c ON c.id=i.carrera_id
                LEFT JOIN resultados r ON r.inscripcion_id=i.id WHERE c.estado='finalizada' AND r.id IS NULL",
            'Resultados sin mejor vuelta' => 'SELECT COUNT(*) FROM resultados WHERE mejor_vuelta_ms IS NULL',
            'Pilotos sin escudería' => 'SELECT COUNT(*) FROM pilotos WHERE deleted_at IS NULL AND equipo_id IS NULL',
            'Vehículos sin escudería' => 'SELECT COUNT(*) FROM vehiculos WHERE deleted_at IS NULL AND equipo_id IS NULL',
            'Carreras sin inscritos' => 'SELECT COUNT(*) FROM carreras c WHERE NOT EXISTS (SELECT 1 FROM inscripciones i WHERE i.carrera_id=c.id)',
        ];
        $filas = [];
        foreach ($consultas as $nombre => $sql) {
            $n = (int) $this->db->query($sql)->fetchColumn();
            $filas[] = ['control' => $nombre, 'cantidad' => $n, 'estado' => $n === 0 ? 'Sin observaciones' : 'Revisar'];
        }
        return ['titulo' => 'Calidad de datos', 'columnas' => ['control' => 'Control', 'cantidad' => 'Cantidad', 'estado' => 'Estado'], 'filas' => $filas];
    }

    /** CSV listo para Excel (BOM UTF-8) y protegido contra inyección de fórmulas. */
    public static function aCsv(array $reporte): string
    {
        $h = fopen('php://temp', 'r+');
        fwrite($h, "\xEF\xBB\xBF");
        fputcsv($h, array_values($reporte['columnas']), ',', '"', '');
        foreach ($reporte['filas'] as $fila) {
            $linea = [];
            foreach (array_keys($reporte['columnas']) as $col) {
                $v = (string) ($fila[$col] ?? '');
                $linea[] = preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
            }
            fputcsv($h, $linea, ',', '"', '');
        }
        rewind($h);
        return (string) stream_get_contents($h);
    }
}
