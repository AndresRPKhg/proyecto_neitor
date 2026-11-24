<?php
declare(strict_types=1);
namespace RaceControl\Domain;

/** Reglas de negocio de las inscripciones (puras, sin base de datos, fáciles de probar). */
final class ReglasInscripcion
{
    /**
     * @param array $carrera  ['estado','tipo_vehiculo','cupo_maximo']
     * @param array $vehiculo ['tipo_vehiculo']
     * @return array<string,string> errores por campo
     */
    public static function validar(array $carrera, array $vehiculo, int $inscritos, bool $pilotoYaInscrito, bool $vehiculoYaInscrito): array
    {
        $e = [];
        if ($carrera['estado'] === 'finalizada') {
            $e['carrera_id'] = 'La carrera ya finalizó; no admite inscripciones.';
        } elseif ($inscritos >= (int) $carrera['cupo_maximo']) {
            $e['carrera_id'] = 'La carrera no tiene cupos disponibles.';
        }
        if ($pilotoYaInscrito) {
            $e['piloto_id'] = 'Este piloto ya está inscrito en la carrera.';
        }
        if ($carrera['tipo_vehiculo'] !== 'mixto' && $vehiculo['tipo_vehiculo'] !== $carrera['tipo_vehiculo']) {
            $e['vehiculo_id'] = 'El vehículo debe ser de tipo ' . $carrera['tipo_vehiculo'] . ', igual que la carrera.';
        } elseif ($vehiculoYaInscrito) {
            $e['vehiculo_id'] = 'Este vehículo ya está inscrito en la carrera.';
        }
        return $e;
    }
}
