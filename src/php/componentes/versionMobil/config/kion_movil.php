<?php
/**
 * KION · Configuración de la versión móvil.
 * Todo es opcional: por defecto la ruta de cada archivo original se detecta sola.
 */
$projectRoot = str_replace('\\', '/', dirname(__DIR__, 5));

return [
    // Rutas explícitas de cada módulo principal del proyecto KION
    'rutas' => [
        'home'              => $projectRoot . '/src/php/modulos/home/home.php',
        'catalogo'          => $projectRoot . '/src/php/componentes/catalogo.php',
        'dashboard'         => $projectRoot . '/src/php/modulos/home/dashboard.php',
        'dashboard_gerente' => $projectRoot . '/src/php/modulos/home/dashboard_gerente.php',
        'dashboard_usuario' => $projectRoot . '/src/php/modulos/home/dashboard_usuario.php',
    ],

    // Otras páginas del proyecto que también se sirven con el diseño móvil
    // mediante  pagina.php?p=<clave>   (clave => archivo original).
    'genericas' => [
        'inicioSesion' => $projectRoot . '/inicioSesion.php',
        'cerrarSesion' => $projectRoot . '/src/php/modulos/home/cerrarSesion.php',
        'registro'     => $projectRoot . '/registro.php',
        'recuperar'    => $projectRoot . '/recuperar.php',
    ],

    'niveles_busqueda' => 5,         // cuántas carpetas hacia arriba se busca el proyecto
    'color_tema'       => '#0a1f3d', // color de la barra del navegador móvil
];
