<?php
declare(strict_types=1);
// Registra un latido (heartbeat) para medir el tiempo activo. Programar cada 5 minutos.
require __DIR__ . '/_arranque.php';

(new \RaceControl\Application\MetricasService())->latido();
