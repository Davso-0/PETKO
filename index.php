<?php
// Detección automática de dispositivo móvil → versión móvil dedicada
function kionEsMovil(): bool {
    if (isset($_COOKIE['kion_vista'])) {
        return $_COOKIE['kion_vista'] === 'movil';
    }
    if (isset($_SERVER['HTTP_SEC_CH_UA_MOBILE'])) {
        return trim((string)$_SERVER['HTTP_SEC_CH_UA_MOBILE']) === '?1';
    }
    return (bool)preg_match(
        '/Android.+Mobile|iPhone|iPod|Windows Phone|Mobile Safari|Opera Mini/i',
        (string)($_SERVER['HTTP_USER_AGENT'] ?? '')
    );
}
header('Accept-CH: Sec-CH-UA-Mobile');
header('Vary: Sec-CH-UA-Mobile, Cookie');
if (kionEsMovil()) {
    header('Location: src/php/componentes/versionmobil/entrada.php?m=home');
} else {
    header('Location: src/php/modulos/home/home.php');
}
exit;

