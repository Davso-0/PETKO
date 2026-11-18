<?php
/** KION móvil · dashboard_gerente.php: ejecuta el original sin modificarlo e inyecta la capa móvil. */
declare(strict_types=1);
require_once __DIR__ . '/core/Movil.php';
$__km_origen = KionMovil::iniciar('dashboard_gerente');
chdir(dirname($__km_origen));
require $__km_origen;
