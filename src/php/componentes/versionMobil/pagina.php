<?php
/** KION móvil · Servidor genérico: pagina.php?p=inicioSesion (solo claves de config/kion_movil.php). */
declare(strict_types=1);
require __DIR__ . '/core/Movil.php';
$__km_p = preg_replace('/[^A-Za-z0-9_]/', '', (string) ($_GET['p'] ?? ''));
$__km_origen = KionMovil::iniciar('p:' . $__km_p);
chdir(dirname($__km_origen));
require $__km_origen;
