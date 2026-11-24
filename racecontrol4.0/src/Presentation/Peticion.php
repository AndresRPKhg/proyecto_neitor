<?php
declare(strict_types=1);
namespace RaceControl\Presentation;

/** Datos de la petición HTTP actual. */
final class Peticion
{
    public readonly string $metodo;
    public readonly string $ruta;
    public readonly string $base;
    public readonly array $query;
    private ?array $cuerpo = null;

    public function __construct()
    {
        $this->metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        $camino = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        if ($this->base !== '' && str_starts_with($camino, $this->base)) {
            $camino = substr($camino, strlen($this->base));
        }
        $this->ruta = '/' . trim($camino, '/');
        $this->query = $_GET;
    }

    public function esApi(): bool
    {
        return str_starts_with($this->ruta, '/api/');
    }

    /** Cuerpo JSON (o formulario) como arreglo. */
    public function cuerpo(): array
    {
        if ($this->cuerpo === null) {
            $crudo = (string) file_get_contents('php://input');
            $json = json_decode($crudo, true);
            $this->cuerpo = is_array($json) ? $json : $_POST;
        }
        return $this->cuerpo;
    }

    public function cuerpoCrudo(): string
    {
        return (string) file_get_contents('php://input');
    }

    public function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function cabecera(string $nombre): ?string
    {
        $clave = 'HTTP_' . strtoupper(str_replace('-', '_', $nombre));
        return $_SERVER[$clave] ?? null;
    }
}
