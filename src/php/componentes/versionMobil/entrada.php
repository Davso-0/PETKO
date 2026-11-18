<?php
/**
 * KION móvil · Selector de versión.
 *   entrada.php?m=home            -> elige móvil o escritorio según el dispositivo
 *   entrada.php?m=home&vista=movil|escritorio  -> fuerza (y recuerda) la vista elegida
 * Criterio: preferencia guardada > Client Hints (Sec-CH-UA-Mobile) > agente de usuario (respaldo).
 */
declare(strict_types=1);
require_once __DIR__ . '/core/Movil.php';

$m = (string) ($_GET['m'] ?? 'home');
if (!isset(KionMovil::MODULOS[$m])) {
    $m = 'home';
}
$vista = $_GET['vista'] ?? '';
if (in_array($vista, ['movil', 'escritorio'], true)) {
    setcookie('kion_vista', $vista, ['expires' => time() + 90 * 86400, 'path' => '/', 'samesite' => 'Lax']);
    $_COOKIE['kion_vista'] = $vista;
}

$pref = (string) ($_COOKIE['kion_vista'] ?? '');
if ($pref === 'movil' || $pref === 'escritorio') {
    $esMovil = $pref === 'movil';
} elseif (isset($_SERVER['HTTP_SEC_CH_UA_MOBILE'])) {
    $esMovil = trim((string) $_SERVER['HTTP_SEC_CH_UA_MOBILE']) === '?1';
} else {
    $esMovil = (bool) preg_match('/Android.+Mobile|iPhone|iPod|Windows Phone|Mobile Safari|Opera Mini/i', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
}

$resto = $_GET;
unset($resto['m'], $resto['vista']);
$qs = $resto ? '?' . http_build_query($resto) : '';

$script = rawurldecode((string) ($_SERVER['SCRIPT_NAME'] ?? '/'));
$movilUrl = rtrim(str_replace('\\', '/', dirname($script)), '/');

if ($esMovil) {
    $destino = KionMovil::codificar($movilUrl) . '/' . $m . '.php' . $qs;
} else {
    $orig = KionMovil::localizar(KionMovil::MODULOS[$m]);
    if ($orig === null) {
        http_response_code(500);
        exit('No se encontró el archivo original.');
    }
    $dir = KionMovil::normalizar($movilUrl . '/' . KionMovil::relativo(KionMovil::dirMovil(), dirname($orig)));
    $destino = KionMovil::codificar($dir) . '/' . rawurlencode(basename($orig)) . $qs;
}
header('Accept-CH: Sec-CH-UA-Mobile');
header('Vary: Sec-CH-UA-Mobile, Cookie');
header('Cache-Control: no-store');
header('Location: ' . $destino, true, 302);
exit;
