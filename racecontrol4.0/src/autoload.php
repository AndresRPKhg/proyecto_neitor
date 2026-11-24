<?php
declare(strict_types=1);
// Autoloader PSR-4 mínimo: funciona aunque no se haya ejecutado "composer install".
spl_autoload_register(static function (string $clase): void {
    $prefijo = 'RaceControl\\';
    if (!str_starts_with($clase, $prefijo)) {
        return;
    }
    $ruta = __DIR__ . '/' . str_replace('\\', '/', substr($clase, strlen($prefijo))) . '.php';
    if (is_file($ruta)) {
        require $ruta;
    }
});
