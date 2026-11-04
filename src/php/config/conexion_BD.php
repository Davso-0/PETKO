<?php

declare(strict_types=1);

// Configuración centralizada de conexión a la Base de Datos KION
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

if (class_exists('Dotenv\\Dotenv')) {
    Dotenv\Dotenv::createMutable(dirname(__DIR__, 3))->safeLoad();
}

$configuracion = static function (string $nombre, string $predeterminado = ''): string {
    $valor = getenv($nombre);
    if ($valor === false || trim($valor) === '') {
        $valor = $_ENV[$nombre] ?? $_SERVER[$nombre] ?? $predeterminado;
    }
    return trim((string)$valor);
};

$dbHost = $configuracion('KION_DB_HOST', 'localhost');
$dbName = $configuracion('KION_DB_NAME', 'kion');
$dbUser = $configuracion('KION_DB_USER', 'root');
$dbPass = $configuracion('KION_DB_PASSWORD');
$dbPort = $configuracion('KION_DB_PORT', '3306');

$pdo = null;
$errorConexion = null;

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    $errorConexion = 'No pudimos conectar con la base de datos.';
    error_log('KION conexion.php - Error de conexión: ' . $e->getMessage());
}
