<?php
/** KION móvil · catalogo.php: ejecuta el original sin modificarlo e inyecta la capa móvil. */
declare(strict_types=1);
require_once __DIR__ . '/core/Movil.php';
$__km_origen = KionMovil::iniciar('catalogo');
chdir(dirname($__km_origen));
require $__km_origen;
