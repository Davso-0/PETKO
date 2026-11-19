<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/php/config/conexion_BD.php';
require_once __DIR__ . '/src/php/servicios/EmailService.php';

Dotenv\Dotenv::createMutable(__DIR__)->safeLoad();

function responderGoogle(bool $ok, string $message = '', ?string $redirect = null, int $status = 200): never
{
    http_response_code($status);
    $respuesta = ['ok' => $ok];
    if ($message !== '') {
        $respuesta['message'] = $message;
    }
    if ($redirect !== null) {
        $respuesta['redirect'] = $redirect;
    }
    echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderGoogle(false, 'Método no permitido.', null, 405);
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!is_string($csrfToken)
    || !isset($_SESSION['csrf_token'])
    || !hash_equals((string)$_SESSION['csrf_token'], $csrfToken)) {
    responderGoogle(false, 'La solicitud expiró o no es válida.', null, 403);
}

$credential = $_POST['credential'] ?? '';
if (!is_string($credential) || trim($credential) === '') {
    responderGoogle(false, 'No se recibió la identificación de Google.', null, 400);
}

$clientId = trim((string)(getenv('GOOGLE_CLIENT_ID')
    ?: ($_ENV['GOOGLE_CLIENT_ID'] ?? $_SERVER['GOOGLE_CLIENT_ID'] ?? '')));
if ($clientId === '') {
    responderGoogle(false, 'Google Sign-In no está configurado en el servidor.', null, 500);
}
if ($pdo === null) {
    responderGoogle(false, $errorConexion ?? 'No fue posible conectar con la base de datos.', null, 503);
}

try {
    $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode(trim($credential));
    $curl = curl_init($url);
    if ($curl === false) {
        throw new RuntimeException('No fue posible iniciar la validación con Google.');
    }
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $tokenResponse = curl_exec($curl);
    $curlError = curl_error($curl);
    $httpStatus = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($tokenResponse === false || $curlError !== '' || $httpStatus !== 200) {
        throw new RuntimeException('La identificación de Google no es válida.');
    }
    $payload = json_decode($tokenResponse, true);
    if (!is_array($payload)) {
        throw new RuntimeException('Respuesta inválida del servicio de Google.');
    }

    $email = strtolower(trim((string)($payload['email'] ?? '')));
    $googleId = trim((string)($payload['sub'] ?? ''));
    $emailVerified = filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $expiresAt = filter_var($payload['exp'] ?? null, FILTER_VALIDATE_INT);
    $issuer = (string)($payload['iss'] ?? '');
    if ($googleId === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || !$emailVerified
        || !hash_equals($clientId, (string)($payload['aud'] ?? ''))
        || !in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true)
        || $expiresAt === false || $expiresAt <= time()) {
        throw new RuntimeException('La cuenta de Google no pudo ser verificada.');
    }

    // Se consultan ambas claves por separado para detectar conflictos entre
    // un Google ID y un correo que pertenezcan a cuentas distintas.
    $consultaGoogle = $pdo->prepare(
        'SELECT u.id_usuario, u.nombre, u.apellido, u.correo, u.google_id, u.id_rol, u.id_sucursal,
                u.estado, r.nombre AS rol
         FROM usuarios u
         INNER JOIN roles r ON r.id_rol = u.id_rol
         WHERE u.google_id = ?
         LIMIT 1'
    );
    $consultaGoogle->execute([$googleId]);
    $usuarioGoogle = $consultaGoogle->fetch();

    $consultaCorreo = $pdo->prepare(
        'SELECT u.id_usuario, u.nombre, u.apellido, u.correo, u.google_id, u.id_rol, u.id_sucursal,
                u.estado, r.nombre AS rol
         FROM usuarios u
         INNER JOIN roles r ON r.id_rol = u.id_rol
         WHERE LOWER(u.correo) = ?
         LIMIT 1'
    );
    $consultaCorreo->execute([$email]);
    $usuarioCorreo = $consultaCorreo->fetch();

    if ($usuarioGoogle && $usuarioCorreo
        && (int)$usuarioGoogle['id_usuario'] !== (int)$usuarioCorreo['id_usuario']) {
        throw new RuntimeException('La cuenta de Google y el correo están vinculados a usuarios distintos.');
    }
    $usuario = $usuarioGoogle ?: $usuarioCorreo;

    if (!$usuario) {
        $idRol = $pdo->query("SELECT id_rol FROM roles WHERE nombre = 'Usuario' LIMIT 1")->fetchColumn();
        if (!$idRol) {
            throw new RuntimeException('No fue posible asignar un rol a la cuenta.');
        }
        $nombre = trim((string)($payload['given_name'] ?? $payload['name'] ?? 'Usuario'));
        $apellido = trim((string)($payload['family_name'] ?? '-'));
        $insertar = $pdo->prepare(
            "INSERT INTO usuarios (nombre, apellido, correo, google_id, password_hash, id_rol, estado)
             VALUES (?, ?, ?, ?, NULL, ?, 'activo')"
        );
        $insertar->execute([$nombre, $apellido, $email, $googleId, $idRol]);
        $usuario = [
            'id_usuario' => (int)$pdo->lastInsertId(),
            'nombre' => $nombre,
            'apellido' => $apellido,
            'correo' => $email,
            'id_rol' => (int)$idRol,
            'id_sucursal' => null,
            'estado' => 'activo',
            'rol' => 'Usuario',
        ];
        EmailService::enviarBienvenidaGoogle(
            $email,
            $nombre,
            'https://petko.site.je'
        );
    } elseif (strtolower((string)$usuario['estado']) !== 'activo') {
        throw new RuntimeException('Esta cuenta se encuentra inactiva.');
    } else {
        $googleIdRegistrado = trim((string)($usuario['google_id'] ?? ''));
        if ($googleIdRegistrado !== '' && !hash_equals($googleIdRegistrado, $googleId)) {
            throw new RuntimeException('Este correo ya está vinculado a otra cuenta de Google.');
        }
        if ($googleIdRegistrado === '') {
            $vincular = $pdo->prepare(
                'UPDATE usuarios SET google_id = ? WHERE id_usuario = ? AND google_id IS NULL'
            );
            $vincular->execute([$googleId, $usuario['id_usuario']]);
        }
    }

    // Regenerar el identificador evita fijación de sesión después de autenticar.
    session_regenerate_id(true);
    $_SESSION['usuario'] = [
        'id_usuario' => $usuario['id_usuario'],
        'nombre' => $usuario['nombre'],
        'apellido' => $usuario['apellido'],
        'correo' => $usuario['correo'],
        'id_rol' => $usuario['id_rol'],
        'id_sucursal' => $usuario['id_sucursal'],
        'rol' => $usuario['rol'],
    ];
    responderGoogle(true, 'Inicio de sesión correcto.', 'index.php');
} catch (PDOException $exception) {
    error_log('KION login Google - Base de datos: ' . $exception->getMessage());
    responderGoogle(false, 'No fue posible completar el inicio de sesión.', null, 500);
} catch (RuntimeException $exception) {
    error_log('KION login Google: ' . $exception->getMessage());
    responderGoogle(false, $exception->getMessage(), null, 401);
} catch (Throwable $exception) {
    error_log('KION login Google - Error inesperado: ' . $exception->getMessage());
    responderGoogle(false, 'No fue posible completar el inicio de sesión.', null, 500);
}
