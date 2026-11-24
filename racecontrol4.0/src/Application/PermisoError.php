<?php
declare(strict_types=1);
namespace RaceControl\Application;

final class PermisoError extends ErrorApp
{
    public function __construct(string $mensaje = 'No tienes permiso para realizar esta acción.')
    {
        parent::__construct($mensaje, 403);
    }
}
