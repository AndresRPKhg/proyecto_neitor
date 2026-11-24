<?php
declare(strict_types=1);
namespace RaceControl\Application;

final class ValidacionError extends ErrorApp
{
    /** @param array<string,string> $errores mensajes por campo (validación inline) */
    public function __construct(array $errores, string $mensaje = 'Revisa los campos marcados.')
    {
        parent::__construct($mensaje, 422, $errores);
    }
}
