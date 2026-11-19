<?php
/**
 * KION móvil · Selector de versión.
 *   entrada.php?m=home            -> elige móvil o escritorio según el dispositivo
 *   entrada.php?m=home&vista=movil|escritorio  -> fuerza la vista para esta navegación
 * La selección explícita prevalece; el modo automático solo reconoce teléfonos.
 */
declare(strict_types=1);
require_once __DIR__ . '/core/Movil.php';

$m = (string) ($_GET['m'] ?? 'home');
if (!isset(KionMovil::MODULOS[$m])) {
    $m = 'home';
}
$vista = $_GET['vista'] ?? '';
if (in_array($vista, ['movil', 'escritorio'], true)) {
    if ($vista === 'escritorio') {
        setcookie('kion_vista', 'escritorio', ['expires' => time() + 90 * 86400, 'path' => '/', 'samesite' => 'Lax']);
        $_COOKIE['kion_vista'] = 'escritorio';
    } else {
        setcookie('kion_vista', '', ['expires' => time() - 3600, 'path' => '/', 'samesite' => 'Lax']);
        unset($_COOKIE['kion_vista']);
    }
}

if ($vista === 'movil') {
    $esMovil = true;
} elseif ($vista === 'escritorio') {
    $esMovil = false;
} else {
    $esMovil = KionMovil::usarVersionMovil();
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
