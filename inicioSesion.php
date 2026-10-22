<?php
declare(strict_types=1);

session_start();
define('DEV_MODE', true);
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

function cookieSegura(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
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
            responderInicioSesion(false, 'Escribe un correo electrónico válido.', null);
        }

        try {
            $consultaRecuperacion = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE correo = ? AND estado = \'ACTIVO\' LIMIT 1');
            $consultaRecuperacion->execute([$correoRecuperacion]);
            $idUsuarioRecuperacion = $consultaRecuperacion->fetchColumn();
            if ($idUsuarioRecuperacion) {
                $tokenRecuperacion = bin2hex(random_bytes(32));
                $guardarRecuperacion = $pdo->prepare('UPDATE usuarios SET recovery_token = ?, recovery_expires_at = ? WHERE id_usuario = ?');
                $guardarRecuperacion->execute([
                    hash('sha256', $tokenRecuperacion),
                    date('Y-m-d H:i:s', time() + 3600),
                    $idUsuarioRecuperacion,
                ]);
            }
            responderInicioSesion(true, 'Se han enviado las instrucciones de recuperación a tu correo electrónico.');
        } catch (PDOException $e) {
            error_log('KION recuperación de contraseña: ' . $e->getMessage());
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
    <title>KION | Iniciar sesión</title>
    <style>
        :root {
            --gold: #c9a444;
            --gold-light: #e6c260;
            --bg-card: rgba(12, 12, 12, 0.65);
            --field-bg: rgba(255, 255, 255, 0.05);
            --field-border: rgba(255, 255, 255, 0.15);
            --text-main: #fff;
            --text-muted: #a0a0a0;
            --error-red: #efaaa5;
            --error-bg: rgba(239, 170, 165, 0.12);
            --success-green: #8bd19a;
            --success-bg: rgba(139, 209, 154, 0.12);
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
    </style>
</head>
<body>
    <video id="videoFondo" autoplay muted loop playsinline>
        <source src="src/videoEJEMPLO/20.mp4" type="video/mp4">
    </video>
    <div class="velo"></div>

    <main class="auth-card">
        <header class="brand-header">
            <h1 class="brand-logo">KION</h1>
            <div class="brand-subtitle">Vet &amp; Agropecuario</div>
        </header>
        <h2 class="form-title">Iniciar sesión</h2>
        <div class="notice" id="loginNotice" role="status" aria-live="polite"></div>
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
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
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
            <div class="notice" id="recoveryNotice" role="status" aria-live="polite"></div>
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
                    <h2 id="rolePickerTitle" style="margin: 0; flex: 1; text-align: center;">Seleccionar rol</h2>
                </div>
                <p id="rolePickerSub" style="margin: 0 0 14px; color: var(--text-muted); font-size: 11px; text-align: center;">Acceso rápido para la exposición de avances.</p>

                <!-- Paso 1: Elegir el tipo de rol -->
                <nav class="role-options" id="stepRoles" aria-label="Roles de demostración">
                    <button type="button" class="role-option btn-rol-categoria" data-rol="admin">Administrador General</button>
                    <button type="button" class="role-option btn-rol-categoria" data-rol="gerente">Gerente</button>
                    <button type="button" class="role-option btn-rol-categoria" data-rol="cajero">Cajero</button>
                    <button type="button" class="role-option btn-rol-categoria" data-rol="usuario">Usuario / Cliente</button>
                </nav>

                <!-- Paso 2: Elegir el usuario (Llenado con AJAX) -->
                <div class="role-options" id="stepUsuarios" style="display: none; max-height: 220px; overflow-y: auto; padding-right: 5px;">
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

        function showNotice(element, message, type) {
            element.textContent = message;
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
                button.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
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
        window.addEventListener('load', () => {
            if (!window.google?.accounts?.id) return;
            google.accounts.id.initialize({ client_id: googleClientId, callback: handleGoogleCredential });
            google.accounts.id.renderButton(document.getElementById('googleLoginButton'), {
                theme: 'filled_black', size: 'medium', width: 290, text: 'continue_with', shape: 'rectangular', locale: 'es'
            });
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

        function mostrarPasoRoles() {
            stepRoles.style.display = 'grid';
            stepUsuarios.style.display = 'none';
            btnVolverRoles.style.display = 'none';
            rolePickerTitle.textContent = 'Seleccionar rol';
            rolePickerSub.textContent = 'Acceso rápido para la exposición de avances.';
        }

        btnVolverRoles.addEventListener('click', mostrarPasoRoles);

        document.querySelectorAll('.btn-rol-categoria').forEach((btn) => {
            btn.addEventListener('click', (evento) => {
                evento.preventDefault();
                const rol = btn.dataset.rol;

                stepUsuarios.innerHTML = '<p style="text-align:center; color:var(--text-muted); font-size:12px; padding:10px;">Cargando usuarios...</p>';
                stepRoles.style.display = 'none';
                stepUsuarios.style.display = 'grid';
                btnVolverRoles.style.display = 'inline-block';

                rolePickerTitle.textContent = `Usuarios: ${btn.textContent}`;
                rolePickerSub.textContent = 'Selecciona la cuenta con la que deseas ingresar.';

                fetch(`?action=get_usuarios_por_rol&rol=${rol}`)
                    .then(res => res.json())
                    .then(res => {
                        if (!res.ok || !res.data || res.data.length === 0) {
                            stepUsuarios.innerHTML = '<p style="text-align:center; color:var(--text-muted); font-size:12px; padding:10px;">No se encontraron usuarios activos en este rol.</p>';
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
                        stepUsuarios.innerHTML = '<p style="text-align:center; color:var(--error-red); font-size:12px; padding:10px;">Error de conexión.</p>';
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