<?php
declare(strict_types=1);
// Arranque común de los scripts de consola.
require dirname(__DIR__) . '/src/autoload.php';
\RaceControl\Infrastructure\Env::cargar(dirname(__DIR__) . '/.env');
date_default_timezone_set(\RaceControl\Infrastructure\Env::get('APP_TIMEZONE', 'America/Bogota'));
