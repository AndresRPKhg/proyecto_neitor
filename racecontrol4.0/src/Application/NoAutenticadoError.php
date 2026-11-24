<?php
declare(strict_types=1);
namespace RaceControl\Application;

final class NoAutenticadoError extends ErrorApp
{
    public function __construct(string $mensaje = 'Tu sesión terminó. Inicia sesión de nuevo.')
    {
        parent::__construct($mensaje, 401);
    }
}
