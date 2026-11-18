<?php
/**
 * KION · Configuración de la versión móvil.
 * Todo es opcional: por defecto la ruta de cada archivo original se detecta sola.
 */
return [
    // Rutas explícitas de cada módulo principal del proyecto KION
    'rutas' => [
        'home'              => 'C:/xampp/htdocs/KION/src/php/modulos/home/home.php',
        'catalogo'          => 'C:/xampp/htdocs/KION/src/php/componentes/catalogo.php',
        'dashboard'         => 'C:/xampp/htdocs/KION/src/php/modulos/home/dashboard.php',
        'dashboard_gerente' => 'C:/xampp/htdocs/KION/src/php/modulos/home/dashboard_gerente.php',
        'dashboard_usuario' => 'C:/xampp/htdocs/KION/src/php/modulos/home/dashboard_usuario.php',
    ],

    // Otras páginas del proyecto que también se sirven con el diseño móvil
    // mediante  pagina.php?p=<clave>   (clave => archivo original).
    'genericas' => [
        'inicioSesion' => 'C:/xampp/htdocs/KION/inicioSesion.php',
        'cerrarSesion' => 'C:/xampp/htdocs/KION/src/php/modulos/home/cerrarSesion.php',
        'registro'     => 'C:/xampp/htdocs/KION/registro.php',
        'recuperar'    => 'C:/xampp/htdocs/KION/recuperar.php',
    ],

    'niveles_busqueda' => 5,         // cuántas carpetas hacia arriba se busca el proyecto
    'color_tema'       => '#0a1f3d', // color de la barra del navegador móvil
];
