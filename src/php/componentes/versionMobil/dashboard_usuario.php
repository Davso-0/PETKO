<?php
/** KION móvil · dashboard_usuario.php: ejecuta el original sin modificarlo e inyecta la capa móvil. */
declare(strict_types=1);
require __DIR__ . '/core/Movil.php';
$__km_origen = KionMovil::iniciar('dashboard_usuario');
chdir(dirname($__km_origen));
require $__km_origen;
