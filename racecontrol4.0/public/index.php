<?php
declare(strict_types=1);
// Controlador frontal de RaceControl: todas las peticiones pasan por aquí.

// Con el servidor integrado de PHP, los archivos estáticos se sirven directamente.
if (PHP_SAPI === 'cli-server') {
    $archivo = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($archivo)) {
        return false;
    }
}

$raiz = dirname(__DIR__);
require $raiz . '/src/autoload.php';

use RaceControl\Infrastructure\Env;
use RaceControl\Presentation\{ApiController, Pagina, Peticion, Router};

Env::cargar($raiz . '/.env');
date_default_timezone_set(Env::get('APP_TIMEZONE', 'America/Bogota'));
ini_set('display_errors', '0'); // los errores van al log, nunca a la pantalla
ini_set('log_errors', '1');
ini_set('error_log', $raiz . '/storage/logs/app.log');

$router = new Router();
ApiController::registrar($router);
Pagina::registrar($router);
$router->despachar(new Peticion());
