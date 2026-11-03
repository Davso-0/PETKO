<?php
declare(strict_types=1);

function cookieSegura(): bool
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $forwardedProtoHttps = $forwardedProto === 'https';
    return $https || $forwardedProtoHttps || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => cookieSegura(),
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();
define('DEV_MODE', filter_var(getenv('KION_DEV_MODE') ?: false, FILTER_VALIDATE_BOOL));
$_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));

function responderInicioSesion(bool $ok, string $message, ?string $redirect = null): never
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        ['ok' => $ok, 'message' => $message, 'redirect' => $redirect],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}   

function obtenerDestinoUsuario(array $usuario): string
{
    $_SESSION['usuario'] = [
        'id_usuario' => $usuario['id_usuario'],
        'nombre' => $usuario['nombre'],
        'apellido' => $usuario['apellido'],
        'correo' => $usuario['correo'],
        'id_rol' => $usuario['id_rol'],
        'id_sucursal' => $usuario['id_sucursal'],
        'rol' => $usuario['rol'],
    ];

    $rolNombre = strtolower(trim($usuario['rol'] ?? ''));
    if (strpos($rolNombre, 'admin') !== false) {
        return 'src/php/modulos/home/dashboard.php';
    } elseif (strpos($rolNombre, 'gerente') !== false || strpos($rolNombre, 'cajero') !== false) {
        return 'src/php/modulos/home/dashboard_gerente.php'; // Ajusta esto si el gerente/cajero tienen otro panel
    }
    return 'src/php/componentes/catalogo.php';
}

function borrarCookieRecordarme(): void
{
    setcookie('kion_remember', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => cookieSegura(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

$esAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
$metodoPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$accion = $_POST['action'] ?? '';
$accion = is_string($accion) ? $accion : '';

require_once __DIR__ . '/src/php/config/conexion_BD.php';

// =========================================================================
// API AJAX: OBTENER LISTA DE USUARIOS POR ROL (MODO DEMO)
// =========================================================================
if (isset($_GET['action']) && $_GET['action'] === 'get_usuarios_por_rol' && DEV_MODE === true) {
    $rolBuscado = trim($_GET['rol'] ?? '');
    try {
        $sql = "SELECT u.id_usuario, u.nombre, u.apellido, u.correo, COALESCE(s.nombre, 'Sin sucursal') AS sucursal 
                FROM usuarios u 
                LEFT JOIN roles r ON r.id_rol = u.id_rol 
                LEFT JOIN sucursales s ON s.id_sucursal = u.id_sucursal 
                WHERE u.estado = 'ACTIVO'";

        if ($rolBuscado === 'admin') {
            $sql .= " AND r.nombre LIKE '%admin%'";
        } elseif ($rolBuscado === 'gerente') {
            $sql .= " AND r.nombre LIKE '%gerente%'";
        } elseif ($rolBuscado === 'cajero') {
            $sql .= " AND r.nombre LIKE '%cajero%'";
        } else {
            $sql .= " AND (r.nombre LIKE '%usuario%' OR r.nombre LIKE '%cliente%')";
        }
        $sql .= " ORDER BY u.nombre ASC";
        
        $stmt = $pdo->query($sql);
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'data' => $usuarios]);
        exit;
    } catch (PDOException $e) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => 'Error al consultar usuarios.']);
        exit;
    }
}

if ($pdo === null && ($metodoPost || (!isset($_SESSION['usuario']) && isset($_COOKIE['kion_remember'])))) {
    if ($metodoPost) {
        responderInicioSesion(false, $errorConexion ?? 'No fue posible conectar con la base de datos.');
    }
    borrarCookieRecordarme();
}

if (!$metodoPost && !isset($_SESSION['usuario']) && isset($_COOKIE['kion_remember']) && $pdo !== null) {
    $tokenRecordarme = $_COOKIE['kion_remember'];
    if (is_string($tokenRecordarme) && preg_match('/^[a-f0-9]{64}$/', $tokenRecordarme)) {
        try {
            $consultaRecordarme = $pdo->prepare('SELECT u.id_usuario, u.nombre, u.apellido, u.correo, u.id_rol, u.id_sucursal, u.estado, r.nombre AS rol FROM usuarios u INNER JOIN roles r ON r.id_rol = u.id_rol WHERE u.remember_token = ? AND u.remember_expires_at > CURRENT_TIMESTAMP LIMIT 1');
            $consultaRecordarme->execute([hash('sha256', $tokenRecordarme)]);
            $usuarioRecordado = $consultaRecordarme->fetch();
            if ($usuarioRecordado && $usuarioRecordado['estado'] === 'ACTIVO') {
                session_regenerate_id(true);
                $destino = obtenerDestinoUsuario($usuarioRecordado);
                header('Location: ' . $destino);
                exit;
            }
            $limpiarToken = $pdo->prepare('UPDATE usuarios SET remember_token = NULL, remember_expires_at = NULL WHERE remember_token = ?');
            $limpiarToken->execute([hash('sha256', $tokenRecordarme)]);
            borrarCookieRecordarme();
        } catch (PDOException $e) {
            error_log('KION recordar sesión: ' . $e->getMessage());
            borrarCookieRecordarme();
        }
    } else {
        borrarCookieRecordarme();
    }
}

if ($metodoPost) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!is_string($csrfToken) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        responderInicioSesion(false, 'La solicitud expiró o no es válida. Recarga la página e inténtalo de nuevo.');
    }

    if ($pdo === null) {
        responderInicioSesion(false, $errorConexion ?? 'No fue posible conectar con la base de datos.');
    }

    // =========================================================================
    // PROCESAMIENTO DE ACCESO RÁPIDO (MODO DEMO)
    // =========================================================================
    if (isset($_POST['demo_login']) && DEV_MODE === true) {
        $idUsuarioDemo = (int)($_POST['id_usuario_demo'] ?? 0);
        
        try {
            $consulta = $pdo->prepare('SELECT u.id_usuario, u.nombre, u.apellido, u.correo, u.password_hash, u.id_rol, u.id_sucursal, u.estado, r.nombre AS rol FROM usuarios u LEFT JOIN roles r ON r.id_rol = u.id_rol WHERE u.id_usuario = ? LIMIT 1');
            $consulta->execute([$idUsuarioDemo]);
            $usuario = $consulta->fetch();

            if (!$usuario) {
                responderInicioSesion(false, 'El usuario seleccionado no existe en la base de datos.');
            }
            if ($usuario['estado'] !== 'ACTIVO') {
                responderInicioSesion(false, 'Esta cuenta se encuentra inactiva.');
            }

            session_regenerate_id(true);
            $destino = obtenerDestinoUsuario($usuario);
            responderInicioSesion(true, 'Acceso de prueba correcto.', $destino);

        } catch (PDOException $e) {
            error_log('KION demo login: ' . $e->getMessage());
            responderInicioSesion(false, 'Error de BD al procesar rol de prueba.');
        }
    }

    if ($accion === 'recuperar') {
        $correoRecuperacion = $_POST['correo_recuperacion'] ?? '';
        $correoRecuperacion = is_string($correoRecuperacion) ? trim($correoRecuperacion) : '';
        if (!filter_var($correoRecuperacion, FILTER_VALIDATE_EMAIL)) {
            responderInicioSesion(false, 'Escribe un correo electrónico válido.');
        }

        $hostSolicitud = $_SERVER['HTTP_HOST'] ?? '';
        $esEntornoLocal = (defined('DEV_MODE') && DEV_MODE === true)
            || in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
            || (is_string($hostSolicitud) && str_contains($hostSolicitud, 'localhost'));

        try {
            $consultaRecuperacion = $pdo->prepare("SELECT id_usuario, nombre FROM usuarios WHERE correo = ? AND estado = 'ACTIVO' LIMIT 1");
            $consultaRecuperacion->execute([$correoRecuperacion]);
            $usuarioRec = $consultaRecuperacion->fetch();
            if ($usuarioRec) {
                $tokenRecuperacion = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $tokenRecuperacion);
                $guardarRecuperacion = $pdo->prepare('UPDATE usuarios SET recovery_token = ?, recovery_expires_at = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 1 HOUR) WHERE id_usuario = ?');
                $guardarRecuperacion->execute([
                    $tokenHash,
                    $usuarioRec['id_usuario'],
                ]);

                $host = $_SERVER['SERVER_NAME'] ?? '';
                if (!is_string($host) || !preg_match('/\A[a-zA-Z0-9.-]+\z/', $host)) {
                    throw new RuntimeException('KION recuperación: nombre de servidor inválido.');
                }
                $protocolo = cookieSegura() ? 'https' : 'http';
                $puertoServidor = $_SERVER['HTTP_X_FORWARDED_PORT'] ?? $_SERVER['SERVER_PORT'] ?? '';
                $puerto = is_string($puertoServidor) && ctype_digit($puertoServidor) ? (int)$puertoServidor : 0;
                $puertoUrl = $puerto > 0 && $puerto <= 65535
                    && (($protocolo === 'https' && $puerto !== 443) || ($protocolo === 'http' && $puerto !== 80))
                    ? ':' . $puerto
                    : '';
                $rutaDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
                $enlaceRecuperacion = $protocolo . '://' . $host . $puertoUrl . $rutaDir
                    . '/recuperar.php?token=' . rawurlencode($tokenRecuperacion);

                if ($esEntornoLocal) {
                    error_log('KION ENLACE DE PRUEBA: ' . $enlaceRecuperacion);
                    responderInicioSesion(
                        true,
                        'Entorno local detectado: Redirigiendo a la pantalla para restablecer contraseña...',
                        $enlaceRecuperacion
                    );
                }

                $nombreHtml = htmlspecialchars((string)$usuarioRec['nombre'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $enlaceHtml = htmlspecialchars($enlaceRecuperacion, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $asunto = 'Restablece tu contraseña - PETKO / KION';
                $cabeceras = [
                    'MIME-Version: 1.0',
                    'Content-type: text/html; charset=UTF-8',
                    'From: PETKO Vet & Agro <no-reply@kion.com>',
                    'Reply-To: soporte@kion.com',
                    'X-Mailer: PHP/' . PHP_VERSION,
                ];
                $mensajeHtml = '<!doctype html><html lang="es"><body style="font-family:Arial,sans-serif;background-color:#f8fafc;padding:20px;color:#0f172a;">'
                    . '<div style="max-width:480px;margin:0 auto;background:#ffffff;padding:30px;border-radius:16px;border:1px solid #cbd5e1;box-shadow:0 4px 12px rgba(0,0,0,0.05);">'
                    . '<h2 style="color:#16a34a;margin-top:0;">PETKO Vet &amp; Agro</h2>'
                    . '<p>Hola <strong>' . $nombreHtml . '</strong>,</p>'
                    . '<p>Recibimos una solicitud para restablecer la contraseña de tu cuenta.</p>'
                    . '<p>Haz clic en el siguiente botón para ingresar tu nueva contraseña. Este enlace caduca en <strong>1 hora</strong>:</p>'
                    . '<div style="text-align:center;margin:28px 0;"><a href="' . $enlaceHtml . '" style="background:#16a34a;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:bold;display:inline-block;">Restablecer contraseña</a></div>'
                    . '<p style="font-size:12px;color:#64748b;">Si no realizaste esta solicitud, puedes ignorar este mensaje de manera segura.</p>'
                    . '</div></body></html>';

                $mailEnviado = @mail($correoRecuperacion, $asunto, $mensajeHtml, implode("\r\n", $cabeceras));
                if (!$mailEnviado) {
                    error_log('KION recuperación: el servicio mail() no está configurado o no pudo enviar el correo.');
                }
            }
            responderInicioSesion(true, 'Si el correo está registrado, recibirás un enlace de recuperación en tu bandeja de entrada.');
        } catch (PDOException $e) {
            error_log('KION recuperación de contraseña: ' . $e->getMessage());
            responderInicioSesion(false, 'No fue posible procesar la solicitud. Inténtalo de nuevo.');
        } catch (RuntimeException $e) {
            error_log($e->getMessage());
            responderInicioSesion(false, 'No fue posible procesar la solicitud. Inténtalo de nuevo.');
        }
    }

    $ahora = time();
    if (isset($_SESSION['bloqueo_hasta']) && $_SESSION['bloqueo_hasta'] > $ahora) {
        responderInicioSesion(false, 'Demasiados intentos fallidos. Inténtalo en 15 minutos.');
    }
    if (isset($_SESSION['bloqueo_hasta']) && $_SESSION['bloqueo_hasta'] <= $ahora) {
        unset($_SESSION['bloqueo_hasta'], $_SESSION['intentos_login']);
    }

    $correo = $_POST['correo'] ?? '';
    $correo = is_string($correo) ? trim($correo) : '';
    $contrasena = $_POST['contrasena'] ?? '';
    $contrasena = is_string($contrasena) ? $contrasena : '';
    
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        responderInicioSesion(false, 'Escribe un correo electrónico válido.');
    }
    if ($contrasena === '') {
        responderInicioSesion(false, 'Escribe tu contraseña.');
    }

    try {
        $consulta = $pdo->prepare('SELECT u.id_usuario, u.nombre, u.apellido, u.correo, u.password_hash, u.id_rol, u.id_sucursal, u.estado, r.nombre AS rol FROM usuarios u INNER JOIN roles r ON r.id_rol = u.id_rol WHERE u.correo = ? LIMIT 1');
        $consulta->execute([$correo]);
        $usuario = $consulta->fetch();

        if (!$usuario || !password_verify($contrasena, $usuario['password_hash'])) {
            $_SESSION['intentos_login'] = ($_SESSION['intentos_login'] ?? 0) + 1;
            if ($_SESSION['intentos_login'] > 5) {
                $_SESSION['bloqueo_hasta'] = $ahora + (15 * 60);
                responderInicioSesion(false, 'Demasiados intentos fallidos. Inténtalo en 15 minutos.');
            }
            responderInicioSesion(false, 'La contraseña o correo electrónico no son correctos.');
        }
        if ($usuario['estado'] !== 'ACTIVO') {
            responderInicioSesion(false, 'Esta cuenta se encuentra inactiva.');
        }

        unset($_SESSION['intentos_login'], $_SESSION['bloqueo_hasta']);
        $recordarme = isset($_POST['recordarme']) && $_POST['recordarme'] === '1';
        if ($recordarme) {
            $tokenRecordarme = bin2hex(random_bytes(32));
            $expiracionRecordarme = time() + (86400 * 30);
            $guardarRecordarme = $pdo->prepare('UPDATE usuarios SET remember_token = ?, remember_expires_at = ? WHERE id_usuario = ?');
            $guardarRecordarme->execute([
                hash('sha256', $tokenRecordarme),
                date('Y-m-d H:i:s', $expiracionRecordarme),
                $usuario['id_usuario'],
            ]);
            setcookie('kion_remember', $tokenRecordarme, [
                'expires' => $expiracionRecordarme,
                'path' => '/',
                'secure' => cookieSegura(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            $revocarRecordarme = $pdo->prepare('UPDATE usuarios SET remember_token = NULL, remember_expires_at = NULL WHERE id_usuario = ?');
            $revocarRecordarme->execute([$usuario['id_usuario']]);
            if (isset($_COOKIE['kion_remember'])) {
                borrarCookieRecordarme();
            }
        }

        session_regenerate_id(true);
        $destino = obtenerDestinoUsuario($usuario);
        responderInicioSesion(true, 'Inicio de sesión correcto.', $destino);
    } catch (PDOException $e) {
        error_log('KION inicio de sesión: ' . $e->getMessage());
        responderInicioSesion(false, 'No fue posible completar el inicio de sesión. Inténtalo de nuevo.');
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PETKO | Iniciar sesión</title>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600;700&amp;family=Plus+Jakarta+Sans:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/bold/style.css">
    <style>
        :root {
            --bg-gradient: linear-gradient(135deg, #f8fafc 0%, #f0fdf4 50%, #e0f2fe 100%);
            --card-bg: rgba(255, 255, 255, 0.85);
            --card-border: rgba(255, 255, 255, 0.9);
            --green-primary: #16a34a;
            --green-hover: #15803d;
            --green-glow: rgba(22, 163, 74, 0.25);
            --field-bg: rgba(248, 250, 252, 0.8);
            --field-border: #cbd5e1;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --error-red: #dc2626;
            --error-bg: #fef2f2;
            --success-green: #16a34a;
            --success-bg: #f0fdf4;
            --font-main: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            --font-brand: 'Fredoka', sans-serif;
            --bg-main: #f8fafc;
            --gold: var(--green-primary);
            --gold-light: var(--green-hover);
            --bg-card: var(--card-bg);
        }
        * { box-sizing: border-box; }
        html, body { width: 100%; height: 100%; margin: 0; overflow: hidden; }
        body {
            display: grid;
            height: 100vh;
            height: 100svh;
            place-items: center;
            overflow: hidden;
            padding: 12px 16px;
            background: #000;
            color: var(--text-main);
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            animation: pageEnter .4s ease backwards;
        }
        body.page-exit { animation: pageExit .28s ease both; }
        #videoFondo, .velo { position: fixed; inset: 0; width: 100%; height: 100%; }
        #videoFondo { z-index: -2; object-fit: cover; }
        .velo { z-index: -1; background: rgba(0, 0, 0, .65); }
        .auth-card {
            width: min(420px, 100%);
            max-height: calc(100svh - 24px);
            padding: 24px 28px;
            border: 1px solid rgba(201, 164, 68, .25);
            border-radius: 16px;
            background: var(--bg-card);
            box-shadow: 0 20px 50px rgba(0, 0, 0, .6);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }
        .brand-header { margin-bottom: 17px; text-align: center; }
        .brand-logo { margin: 0 0 2px; color: #fff; font-size: 26px; font-weight: 800; letter-spacing: 3px; }
        .brand-subtitle { color: var(--text-muted); font-size: 10px; letter-spacing: 1.5px; text-transform: uppercase; }
        .form-title { margin: 0 0 14px; color: var(--gold-light); font-size: 18px; text-align: center; }
        .form-group { margin-bottom: 10px; }
        label { display: block; margin-bottom: 5px; color: var(--text-muted); font-size: 12px; }
        input[type="email"], input[type="password"], input[type="text"] {
            width: 100%;
            padding: 10px 13px;
            border: 1px solid var(--field-border);
            border-radius: 8px;
            outline: none;
            background: var(--field-bg);
            color: var(--text-main);
            font: inherit;
            font-size: 14px;
        }
        input:focus { border-color: var(--gold-light); box-shadow: 0 0 0 2px rgba(201, 164, 68, .22); }
        input:-webkit-autofill, input:-webkit-autofill:hover, input:-webkit-autofill:focus {
            -webkit-text-fill-color: var(--text-main);
            box-shadow: 0 0 0 1000px #191815 inset;
            transition: background-color 9999s ease-out;
        }
        .input-wrapper { position: relative; }
        .input-wrapper input { padding-right: 46px; }
        .toggle-password { position: absolute; top: 50%; right: 7px; display: grid; width: 34px; height: 34px; padding: 0; transform: translateY(-50%); place-items: center; border: 0; border-radius: 6px; background: transparent; color: var(--text-muted); cursor: pointer; }
        .toggle-password:hover { color: var(--gold-light); }
        .toggle-password svg { width: 19px; height: 19px; fill: none; stroke: currentColor; stroke-linecap: round; stroke-linejoin: round; stroke-width: 1.7; }
        .forgot-link { display: block; margin: 0 0 10px; color: var(--gold-light); font-size: 12px; text-align: right; text-decoration: none; }
        .forgot-link:hover, .form-footer a:hover { color: #f2d77f; text-decoration: underline; }
        .check-row { display: flex; align-items: center; gap: 8px; margin: 0 0 12px; color: var(--text-muted); font-size: 12px; }
        .check-row input { width: 15px; height: 15px; margin: 0; accent-color: var(--gold); }
        .check-row label { margin: 0; color: inherit; font-size: inherit; }
        .btn-submit, .google-login { display: flex; width: 100%; min-height: 40px; align-items: center; justify-content: center; border-radius: 8px; }
        .btn-submit { border: 0; background: linear-gradient(135deg, #e6c260 0%, #c9a444 100%); color: #17130a; cursor: pointer; font: inherit; font-size: 14px; font-weight: 750; transition: box-shadow .2s, transform .2s; }
        .btn-submit:hover { box-shadow: 0 4px 15px rgba(201, 164, 68, .35); transform: translateY(-1px); }
        .btn-submit:disabled { cursor: wait; opacity: .7; transform: none; }
        .login-divider { display: flex; align-items: center; gap: 10px; margin: 12px 0 8px; color: var(--text-muted); font-size: 10px; }
        .login-divider::before, .login-divider::after { height: 1px; flex: 1; background: var(--field-border); content: ''; }
        .google-login { min-height: 36px; }
        .form-footer { margin-top: 12px; color: var(--text-muted); font-size: 12px; text-align: center; }
        .form-footer a { color: var(--gold-light); font-weight: 700; text-decoration: none; }
        .notice { display: none; margin: 0 0 10px; padding: 9px 11px; border: 1px solid transparent; border-radius: 8px; font-size: 12px; line-height: 1.4; }
        .notice.visible { display: block; animation: fadeIn .2s ease both; }
        .notice.error { border-color: rgba(239, 170, 165, .35); background: var(--error-bg); color: var(--error-red); }
        .notice.success { border-color: rgba(139, 209, 154, .35); background: var(--success-bg); color: var(--success-green); }
        .dialog-backdrop { position: fixed; inset: 0; z-index: 5; display: none; place-items: center; padding: 16px; background: rgba(0, 0, 0, .66); backdrop-filter: blur(7px); }
        .dialog-backdrop.visible { display: grid; animation: fadeIn .18s ease both; }
        .recovery-dialog { width: min(380px, 100%); padding: 24px; border: 1px solid rgba(201, 164, 68, .3); border-radius: 14px; background: rgba(15, 15, 15, .96); box-shadow: 0 20px 55px rgba(0, 0, 0, .65); }
        .dialog-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 8px; }
        .dialog-head h2 { margin: 0; color: var(--gold-light); font-size: 17px; }
        .dialog-close { width: 32px; height: 32px; border: 0; border-radius: 7px; background: transparent; color: var(--text-muted); cursor: pointer; font-size: 22px; }
        .dialog-copy { margin: 0 0 15px; color: var(--text-muted); font-size: 12px; line-height: 1.5; }
        .dialog-actions { display: flex; justify-content: flex-end; gap: 9px; margin-top: 14px; }
        .btn-secondary { min-height: 38px; padding: 0 13px; border: 1px solid var(--field-border); border-radius: 8px; background: transparent; color: var(--text-muted); cursor: pointer; font: inherit; font-size: 12px; }
        .btn-secondary:hover { border-color: var(--gold); color: var(--text-main); }
        .dialog-submit { min-height: 38px; padding: 0 15px; border: 0; border-radius: 8px; background: linear-gradient(135deg, #e6c260, #c9a444); color: #17130a; cursor: pointer; font: inherit; font-size: 12px; font-weight: 700; }
        
        <?php if (DEV_MODE === true): ?>
        .role-demo-link { display: inline-block; margin-top: 8px; color: var(--text-muted) !important; font-size: 10px; font-weight: 400 !important; cursor: pointer; }
        .role-overlay { position: fixed; inset: 0; z-index: 6; display: none; place-items: center; padding: 16px; background: rgba(0, 0, 0, .66); backdrop-filter: blur(6px); }
        .role-overlay.visible { display: grid; }
        .role-picker { position: relative; width: min(330px, 100%); padding: 22px; border: 1px solid rgba(201, 164, 68, .3); border-radius: 12px; background: #171717; }
        .role-picker h2 { margin: 0 0 7px; color: var(--gold-light); font-size: 17px; text-align: center; }
        .role-picker p { margin: 0 0 14px; color: var(--text-muted); font-size: 11px; text-align: center; }
        .role-options { display: grid; gap: 7px; }
        .role-option { display: block; width: 100%; padding: 9px; border: 1px solid var(--field-border); border-radius: 7px; color: var(--text-main); font-size: 12px; text-align: center; text-decoration: none; background: transparent; cursor: pointer; transition: all 0.2s ease; }
        .role-option:hover { border-color: var(--gold); color: var(--gold-light); background: rgba(201, 164, 68, .1); }
        .role-picker-close { position: absolute; top: 6px; right: 8px; border: 0; background: transparent; color: var(--text-muted); cursor: pointer; font-size: 22px; }
        <?php endif; ?>
        
        @keyframes pageEnter { from { opacity: 0; transform: translateY(7px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes pageExit { to { opacity: 0; transform: translateY(-5px); } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
        @media (max-height: 650px) {
            .auth-card { padding: 17px 24px; }
            .brand-header { margin-bottom: 10px; }
            .form-title { margin-bottom: 9px; }
            .form-group { margin-bottom: 7px; }
            .login-divider { margin: 8px 0 5px; }
            .form-footer { margin-top: 7px; }
        }
        @media (max-width: 400px) { .auth-card { padding-right: 21px; padding-left: 21px; } }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; } }
        html, body { background: var(--bg-main); }
        body { position: relative; isolation: isolate; color: var(--text-main); font-family: var(--font-main); }
        input, button { font-family: var(--font-main); }
        #videoFondo, .velo { display: none; }
        .bg-viewport { position: fixed; z-index: -1; inset: 0; overflow: hidden; background: var(--bg-gradient); pointer-events: none; }
        .bg-viewport::before, .bg-viewport::after { position: absolute; width: min(58vw, 680px); aspect-ratio: 1; border-radius: 50%; content: ''; filter: blur(60px); opacity: .85; animation: floatGlow 16s ease-in-out infinite alternate; }
        .bg-viewport::before { top: -18%; left: -10%; background: radial-gradient(circle, #dcfce7 0%, rgba(220, 252, 231, .58) 35%, transparent 70%); }
        .bg-viewport::after { right: -10%; bottom: -22%; background: radial-gradient(circle, #e0f2fe 0%, rgba(224, 242, 254, .7) 35%, transparent 70%); animation-delay: -8s; }
        .bg-orb { position: absolute; top: 38%; left: 56%; width: min(34vw, 390px); aspect-ratio: 1; border-radius: 50%; background: radial-gradient(circle, rgba(187, 247, 208, .68), rgba(220, 252, 231, .18) 52%, transparent 72%); filter: blur(60px); animation: floatGlow 20s ease-in-out -4s infinite alternate; }
        .floating-paw { position: absolute; bottom: -50px; width: 34px; height: 34px; fill: #16a34a; opacity: .12; animation: floatUp 18s linear infinite; }
        .floating-paw:nth-of-type(2) { left: 12%; animation-delay: -3s; }
        .floating-paw:nth-of-type(3) { left: 33%; width: 24px; height: 24px; animation-delay: -12s; }
        .floating-paw:nth-of-type(4) { left: 69%; width: 29px; height: 29px; animation-delay: -7s; }
        .floating-paw:nth-of-type(5) { left: 88%; width: 22px; height: 22px; animation-delay: -15s; }
        .floating-paw:nth-of-type(6) { left: 49%; width: 20px; height: 20px; animation-delay: -10s; }
        @keyframes floatGlow { from { transform: translate3d(-3%, -2%, 0) scale(.95); } to { transform: translate3d(5%, 4%, 0) scale(1.08); } }
        @keyframes floatUp { 0% { transform: translate3d(0, 0, 0) rotate(-12deg); opacity: 0; } 12% { opacity: .12; } 82% { opacity: .12; } 100% { transform: translate3d(30px, -115vh, 0) rotate(24deg); opacity: 0; } }
        .auth-card { position: relative; z-index: 1; width: min(420px, 100%); max-height: calc(100svh - 24px); overflow-y: auto; border: 1px solid var(--card-border); border-radius: 24px; background: var(--card-bg); box-shadow: 0 20px 50px rgba(15, 23, 42, .08), 0 2px 6px rgba(15, 23, 42, .04); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); }
        .brand-logo { color: #0f172a; }
        .brand-badge { background: rgba(22, 163, 74, .1); border-color: rgba(22, 163, 74, .28); color: var(--green-hover); }
        .form-title, .dialog-head h2, .role-picker h2 { color: #15803d; }
        label, .dialog-copy, .form-footer, .login-divider { color: var(--text-muted); }
        .check-row input { accent-color: var(--green-primary); }
        .btn-submit, .dialog-submit { border-radius: 999px; background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); color: #fff; font-weight: 700; }
        .btn-submit:hover { box-shadow: 0 8px 25px rgba(22, 163, 74, .35); transform: translateY(-2px); }
        .dialog-backdrop { background: rgba(226, 232, 240, .68); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); }
        .recovery-dialog, .role-picker { border: 1px solid rgba(22, 163, 74, .2); border-radius: 18px; background: rgba(255, 255, 255, .96); box-shadow: 0 20px 50px rgba(15, 23, 42, .12), 0 2px 6px rgba(15, 23, 42, .05); }
        .dialog-close, .role-picker-close { color: var(--text-muted); }
        .notice.error { border-color: rgba(220, 38, 38, .2); background: var(--error-bg); color: var(--error-red); }
        .notice.success { border-color: rgba(22, 163, 74, .2); background: var(--success-bg); color: var(--success-green); }
        @media (prefers-reduced-motion: reduce) { .bg-viewport::before, .bg-viewport::after, .bg-orb, .floating-paw { animation: none !important; } }
        .brand-header { display: flex; flex-direction: column; align-items: center; }
        .brand-badge { display: inline-flex; align-items: center; gap: 6px; margin-bottom: 8px; padding: 5px 11px; border: 1px solid rgba(34, 197, 94, .48); border-radius: 999px; background: rgba(34, 197, 94, .12); color: var(--green-primary); font-size: 10px; font-weight: 700; letter-spacing: .02em; }
        .brand-badge i { font-size: 14px; }
        .brand-logo { font-family: var(--font-brand); font-size: 28px; font-weight: 700; letter-spacing: .04em; line-height: 1; }
        .brand-logo span { color: var(--green-primary); }
        .brand-subtitle { color: var(--text-muted); font-family: var(--font-main); }
        .form-title, .dialog-head h2, .role-picker h2 { color: var(--success-green); font-family: var(--font-main); }
        label, .dialog-copy, .form-footer, .login-divider { color: var(--text-muted); }
        input[type="email"], input[type="password"], input[type="text"] { border-radius: 10px; background: rgba(255, 255, 255, .05); font-family: var(--font-main); }
        input:focus { border-color: var(--green-primary); box-shadow: 0 0 12px var(--green-glow); }
        input:-webkit-autofill, input:-webkit-autofill:hover, input:-webkit-autofill:focus { -webkit-text-fill-color: var(--text-main); box-shadow: 0 0 0 1000px #0b2238 inset; }
        .toggle-password { color: var(--text-muted); }
        .toggle-password:hover { color: var(--green-primary); }
        .toggle-password i { font-size: 19px; }
        .toggle-password .ph-eye-slash { display: none; }
        .toggle-password[aria-pressed="true"] .ph-eye { display: none; }
        .toggle-password[aria-pressed="true"] .ph-eye-slash { display: inline; }
        .forgot-link, .form-footer a { color: var(--green-primary); }
        .forgot-link:hover, .form-footer a:hover { color: var(--success-green); }
        .check-row input { accent-color: var(--green-primary); }
        .btn-submit, .dialog-submit { border-radius: 12px; background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); color: #052e16; font-weight: 700; }
        .btn-submit:hover { box-shadow: 0 6px 20px var(--green-glow); transform: translateY(-2px); }
        .dialog-backdrop, .role-overlay { background: rgba(2, 12, 24, .72); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); }
        .recovery-dialog, .role-picker { border: 1px solid rgba(34, 197, 94, .3); border-radius: 18px; background: rgba(11, 34, 56, .96); box-shadow: 0 25px 60px rgba(0, 0, 0, .5), 0 0 30px rgba(34, 197, 94, .08); }
        .dialog-close, .role-picker-close { border-radius: 9px; color: var(--text-muted); }
        .dialog-close:hover, .role-picker-close:hover { color: var(--green-primary); }
        .btn-secondary { border-radius: 10px; }
        .btn-secondary:hover { border-color: var(--green-primary); }
        .role-picker h2 { color: var(--success-green); }
        .role-option { border-radius: 10px; font-family: var(--font-main); }
        .role-option:hover { border-color: var(--green-primary); color: var(--success-green); background: rgba(34, 197, 94, .1); }
        .role-demo-link { color: var(--text-muted) !important; }
        .notice.error { border-color: rgba(248, 113, 113, .35); background: var(--error-bg); color: var(--error-red); }
        .notice.success { border-color: rgba(74, 222, 128, .35); background: var(--success-bg); color: var(--success-green); }
    
/* ===== PETKO · TEMA AZUL DE MARCA (solo estilos) ===== */
:root{--green-primary:#3966B3;--green-hover:#294980;--green-glow:rgba(82,146,255,.35);--bg-gradient:linear-gradient(135deg,#EEF4FF 0%,#BAD3FF 55%,#86B3FF 100%);--bg-main:#EEF4FF;--field-border:#BAD3FF;--text-main:#14284a;--text-muted:#4f678c}
.bg-viewport::before{background:radial-gradient(circle,#BAD3FF 0%,rgba(186,211,255,.6) 35%,transparent 70%)}
.bg-viewport::after{background:radial-gradient(circle,#86B3FF 0%,rgba(134,179,255,.5) 35%,transparent 70%)}
.bg-orb{background:radial-gradient(circle,rgba(82,146,255,.45),rgba(186,211,255,.15) 55%,transparent 72%)}
.floating-paw{fill:#3966B3}
body::before{content:'';position:fixed;inset:0;z-index:0;pointer-events:none;background:url(src/img/petko/patron.png) center/560px;opacity:.06}
.auth-card{border:1px solid rgba(82,146,255,.28);box-shadow:0 24px 60px rgba(41,73,128,.22);transition:transform .35s cubic-bezier(.22,1,.36,1),box-shadow .35s}
.auth-card:hover{transform:translateY(-4px);box-shadow:0 30px 70px rgba(41,73,128,.28)}
.brand-badge{background:rgba(82,146,255,.12);border-color:rgba(82,146,255,.4);color:#294980}
.brand-logo{color:#294980}.brand-logo span{color:#5292FF}
.form-title,.dialog-head h2,.role-picker h2{color:#294980}
input:focus{border-color:#5292FF;box-shadow:0 0 0 4px rgba(82,146,255,.22)}
input:-webkit-autofill,input:-webkit-autofill:hover,input:-webkit-autofill:focus{box-shadow:0 0 0 1000px #EEF4FF inset}
.forgot-link,.form-footer a,.toggle-password:hover,.dialog-close:hover,.role-picker-close:hover{color:#3966B3}
.forgot-link:hover,.form-footer a:hover{color:#294980}
.check-row input{accent-color:#3966B3}
.btn-submit,.dialog-submit{background:linear-gradient(135deg,#5292FF 0%,#3966B3 100%);color:#fff}
.btn-submit:hover{box-shadow:0 8px 24px rgba(82,146,255,.4)}
.btn-secondary:hover,.role-option:hover{border-color:#5292FF;color:#294980;background:rgba(82,146,255,.1)}
.recovery-dialog,.role-picker{border:1px solid rgba(82,146,255,.3);background:rgba(255,255,255,.97);box-shadow:0 25px 60px rgba(41,73,128,.25)}
.dialog-backdrop,.role-overlay{background:rgba(12,26,53,.6)}
@media(max-width:480px){.auth-card{border-radius:20px}input[type="email"],input[type="password"],input[type="text"]{font-size:16px}}


/* ===== PETKO · BARRA SUPERIOR (volver + modo + idioma) ===== */
.topbar{position:fixed;top:0;left:0;right:0;z-index:20;display:flex;align-items:center;justify-content:space-between;gap:10px;padding:14px 18px;padding-top:max(14px,env(safe-area-inset-top));pointer-events:none}
.topbar>*{pointer-events:auto}
.topbar-right{display:flex;align-items:center;gap:8px}
.back-btn,.dark-mode-btn,.language-btn{height:42px;border:1px solid rgba(57,102,179,.35);background:rgba(255,255,255,.72);color:#294980;font:inherit;font-weight:700;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);transition:background .25s,color .25s,border-color .25s,transform .25s,box-shadow .25s}
.back-btn{gap:8px;padding:0 18px 0 14px;border-radius:21px;font-size:13px}
.back-btn svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:2.4;stroke-linecap:round;stroke-linejoin:round;transition:transform .25s}
.back-btn:hover svg{transform:translateX(-3px)}
.dark-mode-btn{width:42px;border-radius:50%;font-size:18px;line-height:1;padding:0}
.language-btn{min-width:42px;padding:0 12px;border-radius:21px;font-size:13px}
.back-btn:hover,.dark-mode-btn:hover,.language-btn:hover{background:#5292FF;border-color:#5292FF;color:#fff;box-shadow:0 8px 22px rgba(82,146,255,.35);transform:translateY(-1px)}
.back-btn:focus-visible,.dark-mode-btn:focus-visible,.language-btn:focus-visible{outline:3px solid rgba(82,146,255,.55);outline-offset:2px}

/* ===== PETKO · LOGO IMAGEN ===== */
.brand-logo{margin:0 0 4px;line-height:0}
.brand-logo img{display:block;height:64px;width:auto;max-width:100%;margin:0 auto;filter:drop-shadow(0 6px 14px rgba(41,73,128,.25));transition:transform .3s}
.auth-card:hover .brand-logo img{transform:scale(1.04) rotate(-1.5deg)}
.brand-header{margin-bottom:14px}

/* ===== PETKO · MODO OSCURO (body.dark-mode, igual que el home) ===== */
body{transition:background .3s,color .3s}
.auth-card,.recovery-dialog,.role-picker,input,.btn-secondary,.role-option{transition:background .3s,border-color .3s,color .3s,box-shadow .3s,transform .35s cubic-bezier(.22,1,.36,1)}
body.dark-mode{--bg-gradient:linear-gradient(135deg,#030710 0%,#071225 55%,#0b1d3a 100%);--bg-main:#071225;--card-bg:rgba(11,29,58,.88);--card-border:rgba(92,200,255,.18);--field-bg:rgba(255,255,255,.06);--field-border:#1f3f73;--text-main:#eaf3ff;--text-muted:#a3b8d6;--error-red:#ff9a8d;--error-bg:rgba(255,120,100,.12);--success-green:#7ee0a8;--success-bg:rgba(60,200,130,.12);--green-primary:#5cc8ff;--green-hover:#8fd6ff;--green-glow:rgba(92,200,255,.35);color-scheme:dark}
body.dark-mode,html:has(body.dark-mode){background:#071225}
body.dark-mode .bg-viewport::before{background:radial-gradient(circle,rgba(47,128,237,.55) 0%,rgba(23,71,154,.35) 35%,transparent 70%)}
body.dark-mode .bg-viewport::after{background:radial-gradient(circle,rgba(92,200,255,.28) 0%,rgba(23,71,154,.25) 35%,transparent 70%)}
body.dark-mode .bg-orb{background:radial-gradient(circle,rgba(47,128,237,.35),rgba(23,71,154,.12) 55%,transparent 72%)}
body.dark-mode .floating-paw{fill:#5cc8ff}
body.dark-mode::before{opacity:.05;filter:invert(1)}
body.dark-mode .auth-card{border-color:rgba(92,200,255,.2);box-shadow:0 24px 60px rgba(0,0,0,.6)}
body.dark-mode .auth-card:hover{box-shadow:0 30px 70px rgba(0,0,0,.7)}
body.dark-mode .brand-logo img{filter:drop-shadow(0 6px 16px rgba(0,0,0,.55))}
body.dark-mode .brand-badge{background:rgba(92,200,255,.12);border-color:rgba(92,200,255,.4);color:#8fd6ff}
body.dark-mode .form-title,body.dark-mode .dialog-head h2,body.dark-mode .role-picker h2{color:#eaf3ff}
body.dark-mode label,body.dark-mode .dialog-copy,body.dark-mode .form-footer,body.dark-mode .login-divider,body.dark-mode .brand-subtitle{color:#a3b8d6}
body.dark-mode input[type="email"],body.dark-mode input[type="password"],body.dark-mode input[type="text"]{background:rgba(255,255,255,.06);border-color:#1f3f73;color:#eaf3ff}
body.dark-mode input::placeholder{color:#6f87ab}
body.dark-mode input:focus{border-color:#5cc8ff;box-shadow:0 0 0 4px rgba(92,200,255,.2)}
body.dark-mode input:-webkit-autofill,body.dark-mode input:-webkit-autofill:hover,body.dark-mode input:-webkit-autofill:focus{-webkit-text-fill-color:#eaf3ff;box-shadow:0 0 0 1000px #0d2145 inset}
body.dark-mode .forgot-link,body.dark-mode .form-footer a,body.dark-mode .toggle-password:hover,body.dark-mode .dialog-close:hover,body.dark-mode .role-picker-close:hover{color:#5cc8ff}
body.dark-mode .forgot-link:hover,body.dark-mode .form-footer a:hover{color:#8fd6ff}
body.dark-mode .check-row input{accent-color:#5cc8ff}
body.dark-mode .btn-submit,body.dark-mode .dialog-submit{background:linear-gradient(135deg,#5cc8ff 0%,#2f80ed 100%);color:#04142e}
body.dark-mode .btn-submit:hover{box-shadow:0 8px 24px rgba(92,200,255,.4)}
body.dark-mode .btn-secondary{border-color:#1f3f73;color:#a3b8d6}
body.dark-mode .btn-secondary:hover,body.dark-mode .role-option:hover{border-color:#5cc8ff;color:#eaf3ff;background:rgba(92,200,255,.12)}
body.dark-mode .role-option{border-color:#1f3f73;color:#eaf3ff}
body.dark-mode .recovery-dialog,body.dark-mode .role-picker{border-color:rgba(92,200,255,.3);background:rgba(11,29,58,.98);box-shadow:0 25px 60px rgba(0,0,0,.65)}
body.dark-mode .dialog-backdrop,body.dark-mode .role-overlay{background:rgba(2,6,14,.75)}
body.dark-mode .notice.error{border-color:rgba(255,154,141,.35)}
body.dark-mode .notice.success{border-color:rgba(126,224,168,.35)}
body.dark-mode .back-btn,body.dark-mode .dark-mode-btn,body.dark-mode .language-btn{background:rgba(2,6,14,.6);border-color:rgba(200,225,255,.3);color:#f2f8ff}
body.dark-mode .back-btn:hover,body.dark-mode .dark-mode-btn:hover,body.dark-mode .language-btn:hover{background:#5cc8ff;border-color:#5cc8ff;color:#04142e;box-shadow:0 8px 22px rgba(92,200,255,.35)}
body.dark-mode #btnVolverRoles{color:#5cc8ff!important}

/* ===== PETKO · TELÉFONO ===== */
body{padding-top:calc(68px + env(safe-area-inset-top))}
.auth-card{max-height:calc(100svh - 84px - env(safe-area-inset-top))}
@media(max-width:480px){
  .topbar{padding:10px 12px;padding-top:max(10px,env(safe-area-inset-top))}
  .back-btn{height:38px;padding:0 14px 0 11px;font-size:12px}
  .dark-mode-btn{width:38px;height:38px;font-size:16px}
  .language-btn{height:38px;min-width:38px;font-size:12px}
  body{padding-left:12px;padding-right:12px;padding-top:calc(60px + env(safe-area-inset-top))}
  .auth-card{max-height:calc(100svh - 72px - env(safe-area-inset-top))}
  .brand-logo img{height:54px}
}
@media(max-width:340px){.back-btn .back-text{display:none}.back-btn{padding:0 12px}}
@media(max-height:650px){.brand-logo img{height:46px}.brand-badge{margin-bottom:5px}}
</style>
</head>
<body>
    <script>
        try {
            var d = localStorage.getItem('petko_dark');
            if (d === null && window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches) d = '1';
            if (d === '1') document.body.classList.add('dark-mode');
        } catch (err) {}
    </script>

    <div class="topbar">
        <a class="back-btn" id="backHome" href="src/php/modulos/home/home.php" aria-label="Volver al inicio">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5"/><path d="m11 18-6-6 6-6"/></svg>
            <span class="back-text">Inicio</span>
        </a>
        <div class="topbar-right">
            <button id="darkModeBtn" class="dark-mode-btn" type="button" title="Cambiar a modo oscuro" aria-label="Cambiar a modo oscuro" aria-pressed="false" data-no-i18n>🌙</button>
            <button id="languageToggle" class="language-btn" type="button" title="Switch to English" aria-label="Switch to English" data-no-i18n>EN</button>
        </div>
    </div>
    <div class="bg-viewport" aria-hidden="true">
        <span class="bg-orb"></span>
        <svg class="floating-paw" viewBox="0 0 64 64" aria-hidden="true"><ellipse cx="32" cy="42" rx="15" ry="12"/><ellipse cx="13" cy="27" rx="6" ry="8" transform="rotate(-25 13 27)"/><ellipse cx="25" cy="17" rx="6" ry="8" transform="rotate(-8 25 17)"/><ellipse cx="39" cy="17" rx="6" ry="8" transform="rotate(8 39 17)"/><ellipse cx="51" cy="27" rx="6" ry="8" transform="rotate(25 51 27)"/></svg>
        <svg class="floating-paw" viewBox="0 0 64 64" aria-hidden="true"><ellipse cx="32" cy="42" rx="15" ry="12"/><ellipse cx="13" cy="27" rx="6" ry="8" transform="rotate(-25 13 27)"/><ellipse cx="25" cy="17" rx="6" ry="8" transform="rotate(-8 25 17)"/><ellipse cx="39" cy="17" rx="6" ry="8" transform="rotate(8 39 17)"/><ellipse cx="51" cy="27" rx="6" ry="8" transform="rotate(25 51 27)"/></svg>
        <svg class="floating-paw" viewBox="0 0 64 64" aria-hidden="true"><ellipse cx="32" cy="42" rx="15" ry="12"/><ellipse cx="13" cy="27" rx="6" ry="8" transform="rotate(-25 13 27)"/><ellipse cx="25" cy="17" rx="6" ry="8" transform="rotate(-8 25 17)"/><ellipse cx="39" cy="17" rx="6" ry="8" transform="rotate(8 39 17)"/><ellipse cx="51" cy="27" rx="6" ry="8" transform="rotate(25 51 27)"/></svg>
        <svg class="floating-paw" viewBox="0 0 64 64" aria-hidden="true"><ellipse cx="32" cy="42" rx="15" ry="12"/><ellipse cx="13" cy="27" rx="6" ry="8" transform="rotate(-25 13 27)"/><ellipse cx="25" cy="17" rx="6" ry="8" transform="rotate(-8 25 17)"/><ellipse cx="39" cy="17" rx="6" ry="8" transform="rotate(8 39 17)"/><ellipse cx="51" cy="27" rx="6" ry="8" transform="rotate(25 51 27)"/></svg>
        <svg class="floating-paw" viewBox="0 0 64 64" aria-hidden="true"><ellipse cx="32" cy="42" rx="15" ry="12"/><ellipse cx="13" cy="27" rx="6" ry="8" transform="rotate(-25 13 27)"/><ellipse cx="25" cy="17" rx="6" ry="8" transform="rotate(-8 25 17)"/><ellipse cx="39" cy="17" rx="6" ry="8" transform="rotate(8 39 17)"/><ellipse cx="51" cy="27" rx="6" ry="8" transform="rotate(25 51 27)"/></svg>
    </div>

    <main class="auth-card">
        <header class="brand-header">
            <span class="brand-badge"><i class="ph-fill ph-paw-print" aria-hidden="true"></i> Veterinaria &amp; Farmacia</span>
            <h1 class="brand-logo"><img src="src/img/petko/logo_amarillo.png" alt="PETKO" width="208" height="64"></h1>
            <div class="brand-subtitle">Vet &amp; Agropecuario</div>
        </header>
        <h2 class="form-title">Iniciar sesión</h2>
        <div class="notice" id="loginNotice" data-no-i18n role="status" aria-live="polite"></div>
        <form id="loginForm" method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-group">
                <label for="correo">Correo electrónico</label>
                <input id="correo" name="correo" type="email" placeholder="usuario@kion.com" autocomplete="email" required>
            </div>
            <div class="form-group">
                <label for="contrasena">Contraseña</label>
                <div class="input-wrapper">
                    <input id="contrasena" name="contrasena" type="password" placeholder="Tu contraseña" autocomplete="current-password" required>
                    <button class="toggle-password" type="button" aria-label="Mostrar contraseña" aria-pressed="false">
                        <i class="ph ph-eye" aria-hidden="true"></i><i class="ph ph-eye-slash" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <a class="forgot-link" href="#recuperar-contrasena" id="forgotPassword">¿Olvidaste tu contraseña?</a>
            <div class="check-row">
                <input id="recordarme" name="recordarme" type="checkbox" value="1">
                <label for="recordarme">Recordarme</label>
            </div>
            <button class="btn-submit" type="submit">Iniciar sesión</button>
        </form>
        <div class="login-divider"><span>o continúa con</span></div>
        <div class="google-login" id="googleLoginButton" aria-label="Continuar con Google"></div>
        <div class="form-footer">
            ¿No tienes cuenta? <a href="registro.php">Regístrate</a>
            <?php if (DEV_MODE === true): ?>
                <br><a class="role-demo-link" href="#seleccionar-rol" id="openRolePicker">Seleccionar rol (demostración)</a>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal Recuperación -->
    <div class="dialog-backdrop" id="recoveryBackdrop">
        <section class="recovery-dialog" role="dialog" aria-modal="true" aria-labelledby="recoveryTitle">
            <div class="dialog-head">
                <h2 id="recoveryTitle">Recuperar contraseña</h2>
                <button class="dialog-close" id="closeRecovery" type="button" aria-label="Cerrar">&times;</button>
            </div>
            <p class="dialog-copy">Escribe el correo asociado a tu cuenta y te indicaremos cómo continuar.</p>
            <div class="notice" id="recoveryNotice" data-no-i18n role="status" aria-live="polite"></div>
            <form id="recoveryForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="recuperar">
                <div class="form-group">
                    <label for="correoRecuperacion">Correo electrónico</label>
                    <input id="correoRecuperacion" name="correo_recuperacion" type="email" placeholder="usuario@kion.com" autocomplete="email" required>
                </div>
                <div class="dialog-actions">
                    <button class="btn-secondary" id="cancelRecovery" type="button">Cancelar</button>
                    <button class="dialog-submit" type="submit">Enviar instrucciones</button>
                </div>
            </form>
        </section>
    </div>

    <!-- Modal Roles (2 Pasos) -->
    <?php if (DEV_MODE === true): ?>
        <div class="role-overlay" id="roleOverlay">
            <section class="role-picker" role="dialog" aria-modal="true" aria-labelledby="rolePickerTitle">
                <button class="role-picker-close" id="closeRolePicker" type="button" aria-label="Cerrar">&times;</button>
                
                <!-- Cabecera de 2 pasos -->
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                    <button id="btnVolverRoles" type="button" style="display: none; background: none; border: none; color: var(--gold-light); cursor: pointer; font-size: 12px; font-weight: bold; padding:0;">← Volver</button>
                    <h2 id="rolePickerTitle" data-no-i18n style="margin: 0; flex: 1; text-align: center;">Seleccionar rol</h2>
                </div>
                <p id="rolePickerSub" data-no-i18n style="margin: 0 0 14px; color: var(--text-muted); font-size: 11px; text-align: center;">Acceso rápido para la exposición de avances.</p>

                <!-- Paso 1: Elegir el tipo de rol -->
                <nav class="role-options" id="stepRoles" aria-label="Roles de demostración">
                    <button type="button" class="role-option btn-rol-categoria" data-rol="admin" data-label="Administrador General">Administrador General</button>
                    <button type="button" class="role-option btn-rol-categoria" data-rol="gerente" data-label="Gerente">Gerente</button>
                    <button type="button" class="role-option btn-rol-categoria" data-rol="cajero" data-label="Cajero">Cajero</button>
                    <button type="button" class="role-option btn-rol-categoria" data-rol="usuario" data-label="Usuario / Cliente">Usuario / Cliente</button>
                </nav>

                <!-- Paso 2: Elegir el usuario (Llenado con AJAX) -->
                <div class="role-options" id="stepUsuarios" data-no-i18n style="display: none; max-height: 220px; overflow-y: auto; padding-right: 5px;">
                </div>
            </section>
        </div>
    <?php endif; ?>

    <script>
        const loginForm = document.getElementById('loginForm');
        const loginNotice = document.getElementById('loginNotice');
        const recoveryForm = document.getElementById('recoveryForm');
        const recoveryNotice = document.getElementById('recoveryNotice');
        const recoveryBackdrop = document.getElementById('recoveryBackdrop');
        const googleClientId = '467947233896-kuvnpl5cdegqkduq4m3e1ste6280feaf.apps.googleusercontent.com';

        // =====================================================================
        // PETKO · MODO OSCURO + IDIOMA (comparte petko_dark / petko_lang con el home)
        // =====================================================================
        const petkoGet = (k) => { try { return localStorage.getItem(k); } catch (e) { return null; } };
        const petkoSet = (k, v) => { try { localStorage.setItem(k, v); } catch (e) {} };

        const loginI18n = {
            'Inicio': 'Home',
            'Volver al inicio': 'Back to home',
            'Veterinaria & Farmacia': 'Veterinary & Pharmacy',
            'Vet & Agropecuario': 'Vet & Livestock',
            'Iniciar sesión': 'Sign in',
            'Correo electrónico': 'Email',
            'Contraseña': 'Password',
            'Tu contraseña': 'Your password',
            '¿Olvidaste tu contraseña?': 'Forgot your password?',
            'Recordarme': 'Remember me',
            'o continúa con': 'or continue with',
            'Continuar con Google': 'Continue with Google',
            '¿No tienes cuenta?': "Don't have an account?",
            'Regístrate': 'Sign up',
            'Seleccionar rol (demostración)': 'Select role (demo)',
            'Recuperar contraseña': 'Reset password',
            'Escribe el correo asociado a tu cuenta y te indicaremos cómo continuar.': "Enter the email linked to your account and we'll tell you how to continue.",
            'Cancelar': 'Cancel',
            'Enviar instrucciones': 'Send instructions',
            'Cerrar': 'Close',
            'Mostrar contraseña': 'Show password',
            'Ocultar contraseña': 'Hide password',
            'Cambiar a modo claro': 'Switch to light mode',
            'Cambiar a modo oscuro': 'Switch to dark mode',
            'Cambiar a español': 'Switch to Spanish',
            'Switch to English': 'Switch to English',
            'PETKO | Iniciar sesión': 'PETKO | Sign in',
            // Selector de rol (demo)
            '← Volver': '← Back',
            'Seleccionar rol': 'Select role',
            'Acceso rápido para la exposición de avances.': 'Quick access for the progress presentation.',
            'Administrador General': 'General Administrator',
            'Gerente': 'Manager',
            'Cajero': 'Cashier',
            'Usuario / Cliente': 'User / Customer',
            'Usuarios': 'Users',
            'Selecciona la cuenta con la que deseas ingresar.': 'Select the account you want to sign in with.',
            'Cargando usuarios...': 'Loading users...',
            'No se encontraron usuarios activos en este rol.': 'No active users found for this role.',
            'Error de conexión.': 'Connection error.',
            // Mensajes
            'Escribe un correo electrónico válido.': 'Enter a valid email address.',
            'Escribe tu contraseña.': 'Enter your password.',
            'No fue posible completar la solicitud.': 'We could not complete the request.',
            'Solicitud completada.': 'Request completed.',
            'No fue posible conectar con el servidor. Inténtalo de nuevo.': 'Could not connect to the server. Please try again.',
            'No fue posible iniciar sesión con Google.': 'Could not sign in with Google.',
            'No fue posible conectar con Google.': 'Could not connect to Google.',
            'Inicio de sesión correcto.': 'Signed in successfully.',
            'Acceso de prueba correcto.': 'Demo access granted.',
            'Error de conexión con el servidor.': 'Server connection error.',
            'Demasiados intentos fallidos. Inténtalo en 15 minutos.': 'Too many failed attempts. Try again in 15 minutes.',
            'El usuario seleccionado no existe en la base de datos.': 'The selected user does not exist in the database.',
            'Error de BD al procesar rol de prueba.': 'Database error while processing the demo role.',
            'Esta cuenta se encuentra inactiva.': 'This account is inactive.',
            'La contraseña o correo electrónico no son correctos.': 'The email or password is incorrect.',
            'La solicitud expiró o no es válida. Recarga la página e inténtalo de nuevo.': 'The request expired or is not valid. Reload the page and try again.',
            'No fue posible completar el inicio de sesión. Inténtalo de nuevo.': 'Could not complete sign in. Please try again.',
            'No fue posible procesar la solicitud. Inténtalo de nuevo.': 'Could not process the request. Please try again.',
            'No fue posible conectar con la base de datos.': 'Could not connect to the database.',
            'Si el correo está registrado, recibirás un enlace de recuperación en tu bandeja de entrada.': 'If the email is registered, you will receive a recovery link in your inbox.'
        };
        let loginEN = petkoGet('petko_lang') === 'en';
        const t = (es) => (loginEN && Object.prototype.hasOwnProperty.call(loginI18n, es)) ? loginI18n[es] : es;
        const origTexts = new Map();

        function applyLoginLanguage() {
            // Textos
            document.querySelectorAll('body *:not(script):not(style)').forEach((el) => {
                if (el.closest('[data-no-i18n]')) return;
                el.childNodes.forEach((node) => {
                    if (node.nodeType !== Node.TEXT_NODE) return;
                    if (!origTexts.has(node)) origTexts.set(node, node.textContent);
                    const original = origTexts.get(node);
                    const key = original.trim().replace(/\s+/g, ' ');
                    if (!key) return;
                    const lead = original.match(/^\s*/)[0], trail = original.match(/\s*$/)[0];
                    node.textContent = lead + t(key) + trail;
                });
            });
            // Atributos
            [['placeholder', 'esPh'], ['aria-label', 'esAria'], ['title', 'esTitle']].forEach(([attr, key]) => {
                document.querySelectorAll('[' + attr + ']').forEach((el) => {
                    if (el.hasAttribute('data-no-i18n')) return;
                    if (el.dataset[key] === undefined) el.dataset[key] = el.getAttribute(attr);
                    el.setAttribute(attr, t(el.dataset[key]));
                });
            });
            // Avisos ya mostrados
            document.querySelectorAll('.notice[data-es]').forEach((n) => { n.textContent = t(n.dataset.es); });
            // Selector de rol
            if (typeof renderRoleHeader === 'function') renderRoleHeader();
            // Barra superior
            document.documentElement.lang = loginEN ? 'en' : 'es';
            document.title = t('PETKO | Iniciar sesión');
            const langBtn = document.getElementById('languageToggle');
            const lt = loginEN ? 'Cambiar a español' : 'Switch to English';
            langBtn.textContent = loginEN ? 'ES' : 'EN';
            langBtn.title = lt; langBtn.setAttribute('aria-label', lt);
            updateDarkLabels();
        }

        function updateDarkLabels() {
            const btn = document.getElementById('darkModeBtn');
            const dark = document.body.classList.contains('dark-mode');
            const label = t(dark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro');
            btn.textContent = dark ? '☀️' : '🌙';
            btn.title = label; btn.setAttribute('aria-label', label);
            btn.setAttribute('aria-pressed', String(dark));
        }

        document.getElementById('darkModeBtn').addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            petkoSet('petko_dark', document.body.classList.contains('dark-mode') ? '1' : '0');
            updateDarkLabels();
            renderGoogleButton();
        });
        document.getElementById('languageToggle').addEventListener('click', () => {
            loginEN = !loginEN;
            petkoSet('petko_lang', loginEN ? 'en' : 'es');
            applyLoginLanguage();
            renderGoogleButton();
        });



        function showNotice(element, message, type) {
            element.dataset.es = message;
            element.textContent = t(message);
            element.className = `notice visible ${type}`;
        }

        async function sendForm(form, notice, successDelay = 1500) {
            notice.className = 'notice';
            const button = form.querySelector('button[type="submit"]');
            if (button) button.disabled = true;
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(form)
                });
                const result = await response.json();
                if (!result.ok) {
                    showNotice(notice, result.message || 'No fue posible completar la solicitud.', 'error');
                    return;
                }
                showNotice(notice, result.message || 'Solicitud completada.', 'success');
                if (result.redirect) window.setTimeout(() => { window.location.href = result.redirect; }, successDelay);
            } catch (error) {
                showNotice(notice, 'No fue posible conectar con el servidor. Inténtalo de nuevo.', 'error');
            } finally {
                if (button) button.disabled = false;
            }
        }

        document.querySelectorAll('.toggle-password').forEach((button) => {
            button.addEventListener('click', () => {
                const input = button.parentElement.querySelector('input');
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                button.dataset.esAria = show ? 'Ocultar contraseña' : 'Mostrar contraseña';
                button.setAttribute('aria-label', t(button.dataset.esAria));
                button.setAttribute('aria-pressed', String(show));
            });
        });

        loginForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const email = loginForm.elements.correo.value.trim();
            const password = loginForm.elements.contrasena.value;
            if (!email || !loginForm.elements.correo.validity.valid) {
                showNotice(loginNotice, 'Escribe un correo electrónico válido.', 'error');
                return;
            }
            if (!password) {
                showNotice(loginNotice, 'Escribe tu contraseña.', 'error');
                return;
            }
            sendForm(loginForm, loginNotice);
        });

        document.getElementById('forgotPassword').addEventListener('click', (event) => {
            event.preventDefault();
            recoveryNotice.className = 'notice';
            recoveryForm.reset();
            recoveryBackdrop.classList.add('visible');
            document.getElementById('correoRecuperacion').focus();
        });
        function closeRecoveryDialog() { recoveryBackdrop.classList.remove('visible'); }
        document.getElementById('closeRecovery').addEventListener('click', closeRecoveryDialog);
        document.getElementById('cancelRecovery').addEventListener('click', closeRecoveryDialog);
        recoveryBackdrop.addEventListener('click', (event) => {
            if (event.target === recoveryBackdrop) closeRecoveryDialog();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeRecoveryDialog();
        });
        recoveryForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const emailInput = recoveryForm.elements.correo_recuperacion;
            if (!emailInput.value.trim() || !emailInput.validity.valid) {
                showNotice(recoveryNotice, 'Escribe un correo electrónico válido.', 'error');
                return;
            }
            sendForm(recoveryForm, recoveryNotice, 0);
        });

        function handleGoogleCredential(response) {
            const body = new URLSearchParams({ credential: response.credential });
            fetch('google-callback.php', { method: 'POST', body })
                .then((response) => response.json())
                .then((result) => {
                    if (!result.ok) {
                        showNotice(loginNotice, result.message || 'No fue posible iniciar sesión con Google.', 'error');
                        return;
                    }
                    showNotice(loginNotice, 'Inicio de sesión correcto.', 'success');
                    window.setTimeout(() => { window.location.href = result.redirect; }, 1500);
                })
                .catch(() => showNotice(loginNotice, 'No fue posible conectar con Google.', 'error'));
        }
        let googleReady = false;
        function renderGoogleButton() {
            if (!googleReady) return;
            const dark = document.body.classList.contains('dark-mode');
            const box = document.getElementById('googleLoginButton');
            box.innerHTML = '';
            google.accounts.id.renderButton(box, {
                theme: dark ? 'filled_black' : 'outline', size: 'medium', width: Math.min(290, box.clientWidth || 290), text: 'continue_with', shape: 'rectangular', locale: loginEN ? 'en' : 'es'
            });
        }
        window.addEventListener('load', () => {
            if (!window.google?.accounts?.id) return;
            google.accounts.id.initialize({ client_id: googleClientId, callback: handleGoogleCredential });
            googleReady = true;
            renderGoogleButton();
        });

        // =====================================================================
        // LÓGICA DE ROLES: 2 PASOS (JS)
        // =====================================================================
        <?php if (DEV_MODE === true): ?>
        const roleOverlay = document.getElementById('roleOverlay');
        const stepRoles = document.getElementById('stepRoles');
        const stepUsuarios = document.getElementById('stepUsuarios');
        const btnVolverRoles = document.getElementById('btnVolverRoles');
        const rolePickerTitle = document.getElementById('rolePickerTitle');
        const rolePickerSub = document.getElementById('rolePickerSub');

        document.getElementById('openRolePicker').addEventListener('click', (event) => {
            event.preventDefault();
            mostrarPasoRoles();
            roleOverlay.classList.add('visible');
        });
        
        document.getElementById('closeRolePicker').addEventListener('click', () => roleOverlay.classList.remove('visible'));
        
        roleOverlay.addEventListener('click', (event) => {
            if (event.target === roleOverlay) roleOverlay.classList.remove('visible');
        });

        let roleStepState = 'roles', roleCatLabel = '';
        function renderRoleHeader() {
            if (!rolePickerTitle) return;
            if (roleStepState === 'roles') {
                rolePickerTitle.textContent = t('Seleccionar rol');
                rolePickerSub.textContent = t('Acceso rápido para la exposición de avances.');
            } else {
                rolePickerTitle.textContent = t('Usuarios') + ': ' + t(roleCatLabel);
                rolePickerSub.textContent = t('Selecciona la cuenta con la que deseas ingresar.');
            }
        }
        function mostrarPasoRoles() {
            stepRoles.style.display = 'grid';
            stepUsuarios.style.display = 'none';
            btnVolverRoles.style.display = 'none';
            roleStepState = 'roles';
            renderRoleHeader();
        }

        btnVolverRoles.addEventListener('click', mostrarPasoRoles);

        document.querySelectorAll('.btn-rol-categoria').forEach((btn) => {
            btn.addEventListener('click', (evento) => {
                evento.preventDefault();
                const rol = btn.dataset.rol;

                stepUsuarios.innerHTML = '<p style="text-align:center; color:var(--text-muted); font-size:12px; padding:10px;">' + t('Cargando usuarios...') + '</p>';
                stepRoles.style.display = 'none';
                stepUsuarios.style.display = 'grid';
                btnVolverRoles.style.display = 'inline-block';

                roleStepState = 'users';
                roleCatLabel = btn.dataset.label;
                renderRoleHeader();

                fetch(`?action=get_usuarios_por_rol&rol=${rol}`)
                    .then(res => res.json())
                    .then(res => {
                        if (!res.ok || !res.data || res.data.length === 0) {
                            stepUsuarios.innerHTML = '<p style="text-align:center; color:var(--text-muted); font-size:12px; padding:10px;">' + t('No se encontraron usuarios activos en este rol.') + '</p>';
                            return;
                        }

                        stepUsuarios.innerHTML = res.data.map(u => `
                            <button type="button" class="role-option btn-seleccionar-usuario" 
                                    data-id="${u.id_usuario}" 
                                    data-correo="${u.correo}">
                                <div style="font-weight: bold; margin-bottom:3px;">${u.nombre} ${u.apellido}</div>
                                <div style="font-size: 10px; opacity: 0.7;">${u.correo} <br> ${u.sucursal}</div>
                            </button>
                        `).join('');
                    })
                    .catch(() => {
                        stepUsuarios.innerHTML = '<p style="text-align:center; color:var(--error-red); font-size:12px; padding:10px;">' + t('Error de conexión.') + '</p>';
                    });
            });
        });

        stepUsuarios.addEventListener('click', (e) => {
            const btnUser = e.target.closest('.btn-seleccionar-usuario');
            if (!btnUser) return;

            const idUsuario = btnUser.dataset.id;
            const correo = btnUser.dataset.correo;

            // Llenado visual
            document.getElementById('correo').value = correo;
            document.getElementById('contrasena').value = '••••••••';
            roleOverlay.classList.remove('visible');

            // Preparar y enviar petición
            const formData = new FormData();
            formData.append('id_usuario_demo', idUsuario);
            formData.append('demo_login', '1');
            formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(res => res.json())
            .then(resultado => {
                if (resultado.ok) {
                    showNotice(loginNotice, resultado.message, 'success');
                    setTimeout(() => window.location.href = resultado.redirect, 500);
                } else {
                    showNotice(loginNotice, resultado.message, 'error');
                }
            })
            .catch(() => showNotice(loginNotice, 'Error de conexión con el servidor.', 'error'));
        });
        <?php endif; ?>

        applyLoginLanguage();

        document.querySelectorAll('a[href="registro.php"]').forEach((link) => {
            link.addEventListener('click', (event) => {
                event.preventDefault();
                document.body.classList.add('page-exit');
                window.setTimeout(() => { window.location.href = link.href; }, 280);
            });
        });
    </script>
    <script src="https://accounts.google.com/gsi/client?hl=es" async defer></script>
</body>
</html>