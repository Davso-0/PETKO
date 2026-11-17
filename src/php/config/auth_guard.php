<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    session_start();
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

$TIEMPO_EXPIRACION = 1800;
if (isset($_SESSION['ultimo_acceso']) && (time() - (int)$_SESSION['ultimo_acceso'] > $TIEMPO_EXPIRACION)) {
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION['ultimo_acceso'] = time();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function verificarCsrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', (string)$token)) {
            responderJsonError('[SEGURIDAD] Token de seguridad inválido o sesión expirada. Recarga la página.', 403);
        }
    }
}

function responderJsonError(string $mensaje, int $code = 403): void {
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'msg' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

function exigirAcceso(PDO $pdo, array $rolesPermitidos, string $loginPath = '../../../../inicioSesion.php'): array {
    $isAjax = isset($_GET['action']) || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

    $idUsuario = 0;
    if (is_array($_SESSION['usuario'] ?? null)) {
        $idUsuario = (int)($_SESSION['usuario']['id_usuario'] ?? $_SESSION['usuario']['id'] ?? 0);
    } else {
        $idUsuario = (int)($_SESSION['usuario'] ?? 0);
    }

    if ($idUsuario <= 0) {
        if ($isAjax) {
            responderJsonError('[SESIÓN EXPIRADA] Debes iniciar sesión nuevamente.', 401);
        }
        header("Location: $loginPath");
        exit;
    }

    $st = $pdo->prepare("SELECT u.id_usuario, u.nombre, u.apellido, u.correo, u.estado, u.id_sucursal, r.nombre AS rol FROM usuarios u LEFT JOIN roles r ON r.id_rol = u.id_rol WHERE u.id_usuario = ? LIMIT 1");
    $st->execute([$idUsuario]);
    $user = $st->fetch(PDO::FETCH_ASSOC);

    if (!$user || strtoupper((string)($user['estado'] ?? '')) === 'INACTIVO') {
        session_unset();
        session_destroy();
        if ($isAjax) {
            responderJsonError('[ACCESO DENEGADO] Cuenta inactiva o inexistente.', 403);
        }
        header("Location: $loginPath");
        exit;
    }

    $rolActual = strtolower(trim((string)($user['rol'] ?? '')));
    $autorizado = false;
    foreach ($rolesPermitidos as $r) {
        if (strpos($rolActual, strtolower((string)$r)) !== false) {
            $autorizado = true;
            break;
        }
    }

    if (!$autorizado) {
        if ($isAjax) {
            responderJsonError('[ACCESO DENEGADO] No tienes permisos para ejecutar esta acción.', 403);
        }
        if (strpos($rolActual, 'admin') !== false) {
            header('Location: dashboard.php');
        } elseif (strpos($rolActual, 'gerente') !== false || strpos($rolActual, 'cajero') !== false) {
            header('Location: dashboard_gerente.php');
        } else {
            header('Location: dashboard_usuario.php');
        }
        exit;
    }

    $_SESSION['usuario'] = [
        'id_usuario' => (int)($user['id_usuario'] ?? 0),
        'nombre' => $user['nombre'] ?? '',
        'apellido' => $user['apellido'] ?? '',
        'correo' => $user['correo'] ?? '',
        'rol' => $user['rol'] ?? '',
        'id_sucursal' => !empty($user['id_sucursal']) ? (int)$user['id_sucursal'] : null,
    ];

    return $user;
}
