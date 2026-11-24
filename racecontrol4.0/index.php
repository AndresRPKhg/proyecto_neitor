<?php
// Entrada de la raíz del proyecto: lleva a la aplicación real en /public (así basta con abrir http://localhost/racecontrol/).
$ruta = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
header('Location: ' . $ruta . '/public/', true, 302);
exit;
