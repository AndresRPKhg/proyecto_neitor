<?php
declare(strict_types=1);
namespace RaceControl\Application;

/** Error de aplicación: la capa de presentación lo traduce a un código HTTP. */
class ErrorApp extends \RuntimeException
{
    public function __construct(string $mensaje, public readonly int $http = 400, public readonly array $errores = [])
    {
        parent::__construct($mensaje);
    }
}
