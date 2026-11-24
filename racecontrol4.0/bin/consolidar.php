<?php
declare(strict_types=1);
// Consolida las métricas de un día (por defecto, ayer). Programar una vez al día (cron o Programador de tareas de Windows).
require __DIR__ . '/_arranque.php';

$fecha = $argv[1] ?? date('Y-m-d', strtotime('-1 day'));
(new \RaceControl\Application\MetricasService())->consolidar($fecha);
echo "Métricas consolidadas para $fecha.\n";
