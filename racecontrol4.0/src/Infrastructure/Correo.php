<?php
declare(strict_types=1);
namespace RaceControl\Infrastructure;

/** Envío de correo. Con MAIL_DRIVER=log (XAMPP) el mensaje se escribe en storage/logs/correos.log. */
final class Correo
{
    public static function enviar(string $para, string $asunto, string $mensaje): void
    {
        $de = Env::get('MAIL_FROM', 'no-reply@racecontrol.local');
        if (Env::get('MAIL_DRIVER', 'log') === 'mail') {
            @mail($para, '=?UTF-8?B?' . base64_encode($asunto) . '?=', $mensaje,
                "From: $de\r\nContent-Type: text/plain; charset=UTF-8");
            return;
        }
        $linea = sprintf("[%s] Para: %s | Asunto: %s\n%s\n---\n", date('c'), $para, $asunto, $mensaje);
        @file_put_contents(dirname(__DIR__, 2) . '/storage/logs/correos.log', $linea, FILE_APPEND);
    }
}
