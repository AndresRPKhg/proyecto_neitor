<?php
declare(strict_types=1);
namespace RaceControl\Application;

final class NoEncontradoError extends ErrorApp
{
    public function __construct(string $mensaje = 'No se encontró el registro solicitado.')
    {
        parent::__construct($mensaje, 404);
    }
}
