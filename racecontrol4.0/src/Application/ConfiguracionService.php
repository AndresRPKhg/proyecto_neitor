<?php
declare(strict_types=1);
namespace RaceControl\Application;

use PDO;
use RaceControl\Domain\SistemaPuntos;
use RaceControl\Infrastructure\Database;

/** Configuración del sistema (por ahora: sistema de puntos por defecto para campeonatos nuevos). */
final class ConfiguracionService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::pdo();
    }

    public function obtener(): array
    {
        $st = $this->db->prepare("SELECT valor FROM configuracion WHERE clave='puntos_por_defecto'");
        $st->execute();
        return ['puntos_por_defecto' => $st->fetchColumn() ?: SistemaPuntos::DEFECTO];
    }

    public function guardar(array $datos): array
    {
        $texto = str_replace(' ', '', (string) ($datos['puntos_por_defecto'] ?? ''));
        if (SistemaPuntos::parsear($texto) === null) {
            throw new ValidacionError(['puntos_por_defecto' => 'Usa números enteros separados por coma. Ejemplo: 25,18,15.']);
        }
        $this->db->prepare("INSERT INTO configuracion (clave,valor,created_at) VALUES ('puntos_por_defecto',?,?)
            ON DUPLICATE KEY UPDATE valor=VALUES(valor)")->execute([$texto, Database::ahora()]);
        return $this->obtener();
    }
}
