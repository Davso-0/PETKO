<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Este script solo puede ejecutarse desde la consola.');
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/php/servicios/EmailService.php';

$destinatario = $argv[1] ?? getenv('KION_TEST_EMAIL');
if (!is_string($destinatario) || !filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Uso: php probar-correo.php destinatario@example.com\n");
    exit(2);
}

try {
    if (!EmailService::enviarPrueba($destinatario)) {
        fwrite(STDERR, "No se pudo enviar el correo. Revisa el error_log de PHP.\n");
        exit(1);
    }

    fwrite(STDOUT, "Correo de prueba enviado correctamente a {$destinatario}.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, "Error SMTP: {$exception->getMessage()}\n");
    exit(1);
}
