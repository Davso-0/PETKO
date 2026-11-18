<?php
declare(strict_types=1);

// =========================================================================
// KION / PETKO - GUARDIA CENTRAL DE AUTENTICACIÓN Y SEGURIDAD (OWASP TOP 10)
// =========================================================================

// 1. Configuración y endurecimiento estricto de sesión
if (session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// 2. Cabeceras HTTP defensivas y Anti-bfcache (Bloqueo del botón "Atrás" tras logout)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

// 3. Detección básica de secuestro de sesión (User-Agent Fingerprint)
$currentUaHash = hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
if (isset($_SESSION['user_agent_hash'])) {
    if (!hash_equals((string)$_SESSION['user_agent_hash'], $currentUaHash)) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        session_start();
        $_SESSION['user_agent_hash'] = $currentUaHash;
    }
} else {
    $_SESSION['user_agent_hash'] = $currentUaHash;
}

// 4. Expiración automática de sesión por inactividad (30 minutos = 1800 segundos)
$TIEMPO_EXPIRACION = 1800;
if (isset($_SESSION['ultimo_acceso']) && (time() - (int)$_SESSION['ultimo_acceso'] > $TIEMPO_EXPIRACION)) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    session_start();
    $_SESSION['user_agent_hash'] = $currentUaHash;
}
$_SESSION['ultimo_acceso'] = time();

// 5. Regeneración periódica de ID de sesión (cada 5 minutos = 300 segundos)
if (!isset($_SESSION['created_at'])) {
    $_SESSION['created_at'] = time();
} elseif (time() - (int)$_SESSION['created_at'] > 300) {
    session_regenerate_id(true);
    $_SESSION['created_at'] = time();
}

// 6. Token CSRF unificado y disponible en toda la sesión
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Valida el token CSRF en peticiones con efectos secundarios (POST, PUT, DELETE, PATCH).
 */
function verificarCsrf(): void {
    $metodo = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (in_array($metodo, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $_POST['csrf_token']
            ?? $_GET['csrf_token']
            ?? '';

        if ($token === '' && str_contains((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
            if (is_array($data) && !empty($data['csrf_token'])) {
                $token = (string)$data['csrf_token'];
            }
        }

        if (!is_string($token) || $token === '' || !hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)$token)) {
            responderJsonError('[SEGURIDAD] Token de seguridad inválido o sesión expirada. Recarga la página.', 403);
        }
    }
}

/**
 * Emite una respuesta de error JSON estándar con código de estado HTTP adecuado.
 */
function responderJsonError(string $mensaje, int $code = 403): never {
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok'      => false,
        'msg'     => $mensaje,
        'message' => $mensaje
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Control de Acceso Estricto Basado en Roles (RBAC).
 * Separa de manera estricta el rol de la asignación de sucursal.
 *
 * @param PDO $pdo Conexión a BD
 * @param array $rolesPermitidos Roles autorizados para esta vista/recurso
 * @param string $loginPath Ruta de redirección en caso de no estar autenticado
 * @param bool $requiereSucursalActiva Si se requiere validar que el gerente tenga sucursal activa
 * @return array Datos revalidados del usuario desde la base de datos
 */
function exigirAcceso(
    PDO $pdo,
    array $rolesPermitidos,
    string $loginPath = '../../../../inicioSesion.php',
    bool $requiereSucursalActiva = false
): array {
    $isAjax = isset($_GET['action'])
        || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

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

    // Revalidar en cada carga contra la base de datos (revocación inmediata si cambia rol o estado)
    $st = $pdo->prepare(
        "SELECT u.id_usuario, u.nombre, u.apellido, u.correo, u.estado, u.id_sucursal, r.nombre AS rol
           FROM usuarios u
      LEFT JOIN roles r ON r.id_rol = u.id_rol
          WHERE u.id_usuario = ?
          LIMIT 1"
    );
    $st->execute([$idUsuario]);
    $user = $st->fetch(PDO::FETCH_ASSOC);

    if (!$user || strtoupper(trim((string)($user['estado'] ?? ''))) !== 'ACTIVO') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        if ($isAjax) {
            responderJsonError('[ACCESO DENEGADO] Cuenta inactiva o inexistente.', 403);
        }
        header("Location: $loginPath?error=inactivo");
        exit;
    }

    $rolActual = strtolower(trim((string)($user['rol'] ?? '')));
    $esAdmin = str_contains($rolActual, 'admin');
    $esGerente = str_contains($rolActual, 'gerente');
    $esCajero = str_contains($rolActual, 'cajero');
    $esUsuario = str_contains($rolActual, 'usuario') || str_contains($rolActual, 'cliente') || (!$esAdmin && !$esGerente && !$esCajero);

    // Validar coincidencia con los roles autorizados
    $autorizado = false;
    foreach ($rolesPermitidos as $r) {
        $rNorm = strtolower(trim((string)$r));
        if ($rNorm === 'admin' && $esAdmin) {
            $autorizado = true;
            break;
        }
        if ($rNorm === 'gerente' && ($esGerente || $esCajero)) {
            $autorizado = true;
            break;
        }
        if (($rNorm === 'usuario' || $rNorm === 'cliente') && $esUsuario) {
            $autorizado = true;
            break;
        }
    }

    // Manejo de acceso no autorizado con redirección según especificación estricta
    if (!$autorizado) {
        if ($isAjax) {
            responderJsonError('[ACCESO DENEGADO] No tienes permisos para ejecutar esta acción.', 403);
        }

        // Reglas estrictas de redirección entre dashboards:
        // 1. Si un admin o gerente intenta entrar al dashboard_usuario, enviarlo a dashboard_gerente.php (o dashboard.php)
        if ($esAdmin) {
            // El admin va a su panel central o a gerencia según corresponda
            header('Location: dashboard_gerente.php');
            exit;
        }
        if ($esGerente || $esCajero) {
            header('Location: dashboard_gerente.php');
            exit;
        }
        // 2. Si un usuario intenta entrar a dashboard_gerente o dashboard (admin), enviarlo a dashboard_usuario.php
        header('Location: dashboard_usuario.php');
        exit;
    }

    // Validación de sucursal activa para el Gerente
    if ($requiereSucursalActiva && $esGerente && !$esAdmin) {
        $idSucursal = (int)($user['id_sucursal'] ?? 0);
        $sucursalValida = false;
        if ($idSucursal > 0) {
            $stSuc = $pdo->prepare("SELECT COUNT(*) FROM sucursales WHERE id_sucursal = ? AND estado = 'ACTIVA'");
            $stSuc->execute([$idSucursal]);
            $sucursalValida = (int)$stSuc->fetchColumn() > 0;
        }

        if (!$sucursalValida) {
            if ($isAjax) {
                responderJsonError('[ACCESO DENEGADO] Tu cuenta de gerente no tiene una sucursal activa asignada.', 403);
            }
            // En vista HTML, permitiremos continuar para que dashboard_gerente muestre su pantalla informativa de "Sin Sucursal"
        }
    }

    // Sincronizar datos frescos en la sesión
    $_SESSION['usuario'] = [
        'id_usuario'  => (int)$user['id_usuario'],
        'nombre'      => (string)($user['nombre'] ?? ''),
        'apellido'    => (string)($user['apellido'] ?? ''),
        'correo'      => (string)($user['correo'] ?? ''),
        'rol'         => (string)($user['rol'] ?? ''),
        'id_sucursal' => !empty($user['id_sucursal']) ? (int)$user['id_sucursal'] : null,
    ];

    return $user;
}
