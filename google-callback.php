<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/php/config/conexion_BD.php';
require_once __DIR__ . '/src/php/servicios/EmailService.php';

Dotenv\Dotenv::createMutable(__DIR__)->safeLoad();
$googleClientId = trim((string)(getenv('GOOGLE_CLIENT_ID') ?: ($_ENV['GOOGLE_CLIENT_ID'] ?? $_SERVER['GOOGLE_CLIENT_ID'] ?? '')));
$credential = $_POST['credential'] ?? '';
if ($googleClientId === '') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Google Sign-In no está configurado en el servidor.']);
    exit;
}

if ($credential === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'No se recibió la identificación de Google.']);
    exit;
}

try {
    $client = new Google_Client(['client_id' => $googleClientId]);
    $payload = $client->verifyIdToken($credential);

    if (!$payload || empty($payload['sub']) || empty($payload['email']) || ($payload['email_verified'] ?? false) !== true) {
        throw new RuntimeException('La cuenta de Google no pudo ser verificada.');
    }

    $googleId = $payload['sub'];
    $correo = $payload['email'];
    $consulta = $pdo->prepare('SELECT u.id_usuario, u.nombre, u.apellido, u.correo, u.id_rol, u.id_sucursal, u.estado, r.nombre AS rol FROM usuarios u INNER JOIN roles r ON r.id_rol = u.id_rol WHERE u.google_id = ? OR u.correo = ? LIMIT 1');
    $consulta->execute([$googleId, $correo]);
    $usuario = $consulta->fetch();

    if (!$usuario) {
        $rol = $pdo->query("SELECT id_rol FROM roles WHERE nombre = 'Usuario' LIMIT 1")->fetchColumn();
        if (!$rol) {
            throw new RuntimeException('No se encontró el rol Usuario.');
        }

        $nombre = trim($payload['given_name'] ?? $payload['name'] ?? 'Usuario');
        $apellido = trim($payload['family_name'] ?? '-');
        $insertar = $pdo->prepare("INSERT INTO usuarios (nombre, apellido, correo, google_id, password_hash, id_rol, estado) VALUES (?, ?, ?, ?, NULL, ?, 'activo')");
        $insertar->execute([$nombre, $apellido, $correo, $googleId, $rol]);
        $idUsuario = (int)$pdo->lastInsertId();
        $usuario = ['id_usuario' => $idUsuario, 'nombre' => $nombre, 'apellido' => $apellido, 'correo' => $correo, 'id_rol' => $rol, 'id_sucursal' => null, 'estado' => 'activo', 'rol' => 'Usuario'];
        EmailService::enviarBienvenidaGoogle($correo, $nombre, baseUrl() . '/src/php/componentes/catalogo.php');
    } elseif (strtolower((string)$usuario['estado']) !== 'activo') {
        throw new RuntimeException('Esta cuenta se encuentra inactiva.');
    } else {
        $vincular = $pdo->prepare('UPDATE usuarios SET google_id = ? WHERE id_usuario = ? AND (google_id IS NULL OR google_id = ?)');
        $vincular->execute([$googleId, $usuario['id_usuario'], $googleId]);
    }

    function baseUrl(): string
    {
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    session_regenerate_id(true);
    $_SESSION['usuario'] = ['id_usuario' => $usuario['id_usuario'], 'nombre' => $usuario['nombre'], 'apellido' => $usuario['apellido'], 'correo' => $usuario['correo'], 'id_rol' => $usuario['id_rol'], 'id_sucursal' => $usuario['id_sucursal'], 'rol' => $usuario['rol']];
    $destino = 'src/php/modulos/home/home.php';
    echo json_encode(['ok' => true, 'redirect' => $destino]);
} catch (Throwable $e) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
