<?php
// Funciones de ayuda para las vistas.
if (!function_exists('e')) {
    /** Escapa texto para HTML (protección contra XSS en la salida). */
    function e(mixed $v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    /** Dibuja un icono del sprite SVG. */
    function icono(string $nombre, string $clase = ''): string
    {
        return '<svg class="rc-icono ' . e($clase) . '" aria-hidden="true" focusable="false"><use href="#i-' . e($nombre) . '"></use></svg>';
    }
}
