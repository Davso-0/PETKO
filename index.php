<?php
// Detección automática de dispositivo móvil → versión móvil dedicada
require_once __DIR__ . '/src/php/componentes/versionMobil/core/Movil.php';

function kionEsMovil(): bool {
    return KionMovil::usarVersionMovil();
}
header('Accept-CH: Sec-CH-UA-Mobile');
header('Vary: Sec-CH-UA-Mobile, Cookie');
if (kionEsMovil()) {
    header('Location: src/php/componentes/versionMobil/entrada.php?m=home');
} else {
    header('Location: src/php/modulos/home/home.php');
}
exit;

