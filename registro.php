<?php
declare(strict_types=1);

session_start();
$_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));

function responderRegistro(bool $ok, string $message, ?string $redirect = null): never
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        ['ok' => $ok, 'message' => $message, 'redirect' => $redirect],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

$esAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!is_string($csrfToken) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        responderRegistro(false, 'La solicitud expiró o no es válida. Recarga la página e inténtalo de nuevo.');
    }

    $nombre = $_POST['nombre'] ?? '';
    $nombre = is_string($nombre) ? trim($nombre) : '';
    $apellido = $_POST['apellido'] ?? '';
    $apellido = is_string($apellido) ? trim($apellido) : '';
    $correo = $_POST['email'] ?? '';
    $correo = is_string($correo) ? trim($correo) : '';
    $contrasena = $_POST['password'] ?? '';
    $contrasena = is_string($contrasena) ? $contrasena : '';
    $confirmacion = $_POST['confirm_password'] ?? '';
    $confirmacion = is_string($confirmacion) ? $confirmacion : '';
    $aceptoTerminos = isset($_POST['terms']) && is_string($_POST['terms']) && $_POST['terms'] === '1';

    if ($nombre === '' || $apellido === '' || $correo === '' || $contrasena === '' || $confirmacion === '' || !$aceptoTerminos) {
        responderRegistro(false, 'Completa todos los campos y acepta los Términos y Condiciones y la Política de Privacidad.');
    }
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        responderRegistro(false, 'Escribe un correo electrónico válido.');
    }
    if (strlen($contrasena) < 8 || !preg_match('/[A-Z]/', $contrasena) || !preg_match('/[0-9]/', $contrasena)) {
        responderRegistro(false, 'La contraseña debe tener al menos 8 caracteres, una letra mayúscula y un número.');
    }
    if ($contrasena !== $confirmacion) {
        responderRegistro(false, 'Las contraseñas no coinciden.');
    }

    require_once __DIR__ . '/src/php/config/conexion_BD.php';
    if ($pdo === null) {
        responderRegistro(false, $errorConexion ?? 'No fue posible conectar con la base de datos.');
    }

    try {
        $consultaCorreo = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE correo = ?');
        $consultaCorreo->execute([$correo]);
        if ((int)$consultaCorreo->fetchColumn() > 0) {
            responderRegistro(false, 'Este correo electrónico ya se encuentra registrado.');
        }

        $consultaRol = $pdo->query("SELECT id_rol FROM roles WHERE nombre = 'Usuario' LIMIT 1");
        $idRol = $consultaRol->fetchColumn();
        if (!$idRol) {
            error_log('KION registro: no existe el rol Usuario en la base de datos.');
            responderRegistro(false, 'No fue posible completar el registro en este momento.');
        }

        $insertar = $pdo->prepare("INSERT INTO usuarios (nombre, apellido, correo, password_hash, id_rol, estado) VALUES (?, ?, ?, ?, ?, 'ACTIVO')");
        $insertar->execute([
            $nombre,
            $apellido,
            $correo,
            password_hash($contrasena, PASSWORD_DEFAULT),
            $idRol,
        ]);

        responderRegistro(true, '¡Cuenta creada exitosamente! Redirigiendo al inicio de sesión...', 'inicioSesion.php');
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            responderRegistro(false, 'Este correo electrónico ya se encuentra registrado.');
        }
        error_log('KION registro: ' . $e->getMessage());
        responderRegistro(false, 'No fue posible completar el registro. Inténtalo de nuevo.');
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PETKO | Crear cuenta</title>
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
            padding: 8px 14px;
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
            max-height: calc(100svh - 16px);
            padding: 20px 24px;
            border: 1px solid rgba(201, 164, 68, .25);
            border-radius: 16px;
            background: var(--bg-card);
            box-shadow: 0 20px 50px rgba(0, 0, 0, .6);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }
        .brand-header { margin-bottom: 10px; text-align: center; }
        .brand-logo { margin: 0; color: #fff; font-size: 26px; font-weight: 800; letter-spacing: 3px; line-height: 1.1; }
        .brand-subtitle { color: var(--text-muted); font-size: 10px; letter-spacing: 1.5px; text-transform: uppercase; }
        .form-title { margin: 0 0 11px; color: var(--gold-light); font-size: 18px; text-align: center; }
        .name-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .form-group { min-width: 0; margin-bottom: 8px; }
        label { display: block; margin-bottom: 4px; color: var(--text-muted); font-size: 11px; }
        input[type="email"], input[type="password"], input[type="text"] {
            width: 100%;
            min-width: 0;
            padding: 9px 12px;
            border: 1px solid var(--field-border);
            border-radius: 8px;
            outline: none;
            background: var(--field-bg);
            color: var(--text-main);
            font: inherit;
            font-size: 13px;
        }
        input:focus { border-color: var(--gold-light); box-shadow: 0 0 0 2px rgba(201, 164, 68, .22); }
        input:-webkit-autofill, input:-webkit-autofill:hover, input:-webkit-autofill:focus {
            -webkit-text-fill-color: var(--text-main);
            box-shadow: 0 0 0 1000px #191815 inset;
            transition: background-color 9999s ease-out;
        }
        .input-wrapper { position: relative; }
        .input-wrapper input { padding-right: 44px; }
        .toggle-password { position: absolute; top: 50%; right: 6px; display: grid; width: 32px; height: 32px; padding: 0; transform: translateY(-50%); place-items: center; border: 0; border-radius: 6px; background: transparent; color: var(--text-muted); cursor: pointer; }
        .toggle-password:hover { color: var(--gold-light); }
        .toggle-password svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-linecap: round; stroke-linejoin: round; stroke-width: 1.7; }
        .password-help { margin: 1px 0 8px; }
        .password-help p { margin: 0 0 5px; color: var(--text-muted); font-size: 10px; }
        .password-rules { display: flex; flex-wrap: wrap; gap: 6px; margin: 0; padding: 0; list-style: none; }
        .password-rules li { padding: 4px 7px; border: 1px solid rgba(255, 255, 255, .11); border-radius: 999px; background: rgba(255, 255, 255, .035); color: #aaa; font-size: 9px; line-height: 1.2; transition: color .18s, border-color .18s, background-color .18s; }
        .password-rules li::before { margin-right: 4px; content: '○'; color: #777; }
        .password-rules li.valid { border-color: rgba(139, 209, 154, .32); background: rgba(139, 209, 154, .08); color: var(--success-green); }
        .password-rules li.valid::before { content: '✓'; color: var(--success-green); }
        .field-error { display: none; margin: -4px 0 7px; color: var(--error-red); font-size: 10px; }
        .field-error.visible { display: block; }
        .check-row { display: flex; align-items: flex-start; gap: 8px; margin: 2px 0 10px; color: var(--text-muted); font-size: 10px; line-height: 1.35; }
        .check-row input { width: 14px; height: 14px; flex: 0 0 auto; margin: 0; accent-color: var(--gold); }
        .check-row label { display: inline; margin: 0; color: inherit; font-size: inherit; }
        .check-row > div { min-width: 0; }
        .check-row a { color: var(--gold-light); text-decoration: underline; text-underline-offset: 2px; }
        .check-row a:hover { color: #f2d77f; }
        .btn-submit { width: 100%; min-height: 39px; border: 0; border-radius: 8px; background: linear-gradient(135deg, #e6c260 0%, #c9a444 100%); color: #17130a; cursor: pointer; font: inherit; font-size: 13px; font-weight: 750; transition: box-shadow .2s, transform .2s; }
        .btn-submit:hover { box-shadow: 0 4px 15px rgba(201, 164, 68, .35); transform: translateY(-1px); }
        .btn-submit:disabled { cursor: wait; opacity: .7; transform: none; }
        .form-footer { margin-top: 11px; color: var(--text-muted); font-size: 11px; text-align: center; }
        .form-footer a { color: var(--gold-light); font-weight: 700; text-decoration: none; }
        .form-footer a:hover { color: #f2d77f; text-decoration: underline; }
        .notice { display: none; margin: 0 0 9px; padding: 8px 10px; border: 1px solid transparent; border-radius: 8px; font-size: 11px; line-height: 1.35; }
        .notice.visible { display: block; animation: fadeIn .2s ease both; }
        .notice.error { border-color: rgba(239, 170, 165, .35); background: var(--error-bg); color: var(--error-red); }
        .notice.success { border-color: rgba(139, 209, 154, .35); background: var(--success-bg); color: var(--success-green); }
        .terms-modal { position: fixed; z-index: 10; inset: 0; display: grid; padding: 20px; place-items: center; background: rgba(0, 0, 0, .62); opacity: 0; visibility: hidden; transition: opacity .2s ease, visibility .2s ease; backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); }
        .terms-modal.visible { opacity: 1; visibility: visible; }
        .terms-dialog { width: min(820px, 100%); height: min(780px, calc(100svh - 40px)); overflow: hidden; transform: translateY(8px); border: 1px solid rgba(201, 164, 68, .3); border-radius: 18px; background: #0c0c0c; box-shadow: 0 25px 60px rgba(0, 0, 0, .7); transition: transform .2s ease; }
        .terms-modal.visible .terms-dialog { transform: translateY(0); }
        .terms-frame { display: block; width: 100%; height: 100%; border: 0; }
        @keyframes pageEnter { from { opacity: 0; transform: translateY(7px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes pageExit { to { opacity: 0; transform: translateY(-5px); } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
        @media (max-height: 650px) {
            .auth-card { padding-top: 14px; padding-bottom: 14px; }
            .brand-header { margin-bottom: 6px; }
            .form-title { margin-bottom: 7px; }
            .form-group { margin-bottom: 5px; }
            .password-help { margin-bottom: 5px; }
            .form-footer { margin-top: 7px; }
        }
        @media (max-width: 540px) { .terms-modal { padding: 10px; } .terms-dialog { height: calc(100svh - 20px); } }
        @media (max-width: 360px) { .auth-card { padding-right: 17px; padding-left: 17px; } .name-row { gap: 7px; } }
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
        .auth-card { position: relative; z-index: 1; width: min(420px, 100%); max-height: calc(100svh - 16px); overflow-y: auto; border: 1px solid var(--card-border); border-radius: 24px; background: var(--card-bg); box-shadow: 0 20px 50px rgba(15, 23, 42, .08), 0 2px 6px rgba(15, 23, 42, .04); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); }
        .brand-logo { color: #0f172a; }
        .brand-badge { background: rgba(22, 163, 74, .1); border-color: rgba(22, 163, 74, .28); color: var(--green-hover); }
        .form-title { color: #15803d; }
        label, .password-help p, .form-footer { color: var(--text-muted); }
        input[type="email"], input[type="password"], input[type="text"] { border-radius: 10px; background: var(--field-bg); color: #0f172a; }
        input:focus { border-color: var(--green-primary); box-shadow: 0 0 0 3px var(--green-glow); background: #fff; }
        input:-webkit-autofill, input:-webkit-autofill:hover, input:-webkit-autofill:focus { -webkit-text-fill-color: #0f172a; box-shadow: 0 0 0 1000px #fff inset; }
        .toggle-password { color: var(--text-muted); }
        .toggle-password:hover { color: var(--green-primary); }
        .toggle-password .ph-eye-slash { display: none; }
        .toggle-password[aria-pressed="true"] .ph-eye { display: none; }
        .toggle-password[aria-pressed="true"] .ph-eye-slash { display: inline; }
        .password-rules li { border-color: #e2e8f0; background: #f1f5f9; color: #64748b; }
        .password-rules li::before { content: none; }
        .password-rules li .rule-icon { margin-right: 4px; font-size: 12px; vertical-align: -1px; }
        .password-rules li .rule-icon-valid { display: none; }
        .password-rules li.valid { border-color: #bbf7d0; background: #dcfce7; color: #15803d; }
        .password-rules li.valid::before { content: none; }
        .password-rules li.valid .rule-icon-empty { display: none; }
        .password-rules li.valid .rule-icon-valid { display: inline; }
        .check-row input { accent-color: var(--green-primary); }
        .check-row a, .form-footer a { color: var(--green-primary); }
        .check-row a:hover, .form-footer a:hover { color: var(--green-hover); }
        .btn-submit { border-radius: 999px; background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); color: #fff; font-weight: 700; }
        .btn-submit:hover { box-shadow: 0 8px 25px rgba(22, 163, 74, .35); transform: translateY(-2px); }
        .notice.error { border-color: rgba(220, 38, 38, .2); background: var(--error-bg); color: var(--error-red); }
        .notice.success { border-color: rgba(22, 163, 74, .2); background: var(--success-bg); color: var(--success-green); }
        @media (prefers-reduced-motion: reduce) { .bg-viewport::before, .bg-viewport::after, .bg-orb, .floating-paw { animation: none !important; } }
        .brand-header { display: flex; flex-direction: column; align-items: center; }
        .brand-badge { display: inline-flex; align-items: center; gap: 6px; margin-bottom: 8px; padding: 5px 11px; border: 1px solid rgba(34, 197, 94, .48); border-radius: 999px; background: rgba(34, 197, 94, .12); color: var(--green-primary); font-size: 10px; font-weight: 700; letter-spacing: .02em; }
        .brand-badge i { font-size: 14px; }
        .brand-logo { font-family: var(--font-brand); font-size: 28px; font-weight: 700; letter-spacing: 2px; }
        .brand-subtitle { color: var(--text-muted); font-family: var(--font-main); }
        .form-title { color: var(--success-green); font-family: var(--font-main); }
        label, .password-help p, .form-footer { color: var(--text-muted); }
        input[type="email"], input[type="password"], input[type="text"] { border-radius: 10px; background: rgba(255, 255, 255, .05); font-family: var(--font-main); }
        input:focus { border-color: var(--green-primary); box-shadow: 0 0 12px var(--green-glow); }
        input:-webkit-autofill, input:-webkit-autofill:hover, input:-webkit-autofill:focus { -webkit-text-fill-color: var(--text-main); box-shadow: 0 0 0 1000px #0b2238 inset; }
        .toggle-password { color: var(--text-muted); }
        .toggle-password:hover { color: var(--green-primary); }
        .toggle-password i { font-size: 18px; }
        .toggle-password .ph-eye-slash { display: none; }
        .toggle-password[aria-pressed="true"] .ph-eye { display: none; }
        .toggle-password[aria-pressed="true"] .ph-eye-slash { display: inline; }
        .password-rules li { border-color: rgba(148, 163, 184, .18); background: rgba(148, 163, 184, .06); color: #94a3b8; }
        .password-rules li::before { content: none; }
        .password-rules li .rule-icon { margin-right: 4px; font-size: 12px; vertical-align: -1px; }
        .password-rules li .rule-icon-valid { display: none; }
        .password-rules li.valid { border-color: rgba(34, 197, 94, .32); background: rgba(34, 197, 94, .15); color: #4ade80; }
        .password-rules li.valid::before { content: none; }
        .password-rules li.valid .rule-icon-empty { display: none; }
        .password-rules li.valid .rule-icon-valid { display: inline; }
        .check-row input { accent-color: var(--green-primary); }
        .check-row a { color: var(--green-primary); }
        .check-row a:hover { color: var(--success-green); }
        .form-footer a { color: var(--green-primary); }
        .form-footer a:hover { color: var(--success-green); }
        .btn-submit { border-radius: 12px; background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); color: #052e16; font-weight: 700; }
        .btn-submit:hover { box-shadow: 0 6px 20px var(--green-glow); transform: translateY(-2px); }
        .notice.error { border-color: rgba(248, 113, 113, .35); background: var(--error-bg); color: var(--error-red); }
        .notice.success { border-color: rgba(74, 222, 128, .35); background: var(--success-bg); color: var(--success-green); }
        .terms-modal { background: rgba(2, 12, 24, .72); }
        .terms-dialog { border-color: rgba(34, 197, 94, .3); border-radius: 18px; background: rgba(11, 34, 56, .96); box-shadow: 0 25px 60px rgba(0, 0, 0, .5), 0 0 30px rgba(34, 197, 94, .08); }
    </style>
</head>
<body>
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
            <h1 class="brand-logo">PETKO</h1>
            <div class="brand-subtitle">Vet &amp; Agropecuario</div>
        </header>
        <h2 class="form-title">Crear una cuenta</h2>
        <div class="notice" id="registerNotice" role="status" aria-live="polite"></div>
        <form id="registerForm" method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="name-row">
                <div class="form-group">
                    <label for="nombre">Nombres</label>
                    <input id="nombre" name="nombre" type="text" placeholder="Ej. Ana" autocomplete="given-name" required>
                </div>
                <div class="form-group">
                    <label for="apellido">Apellidos</label>
                    <input id="apellido" name="apellido" type="text" placeholder="Ej. Martínez" autocomplete="family-name" required>
                </div>
            </div>
            <div class="form-group">
                <label for="email">Correo electrónico</label>
                <input id="email" name="email" type="email" placeholder="usuario@kion.com" autocomplete="email" required>
            </div>
            <div class="form-group">
                <label for="password">Contraseña</label>
                <div class="input-wrapper">
                    <input id="password" name="password" type="password" autocomplete="new-password" required>
                    <button class="toggle-password" type="button" aria-label="Mostrar contraseña" aria-pressed="false">
                        <i class="ph ph-eye" aria-hidden="true"></i><i class="ph ph-eye-slash" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <div class="password-help">
                <p>La contraseña debe cumplir:</p>
                <ul class="password-rules" aria-label="Requisitos de la contraseña">
                    <li data-rule="length"><i class="ph ph-circle rule-icon rule-icon-empty" aria-hidden="true"></i><i class="ph-bold ph-check rule-icon rule-icon-valid" aria-hidden="true"></i>Mínimo 8</li>
                    <li data-rule="uppercase"><i class="ph ph-circle rule-icon rule-icon-empty" aria-hidden="true"></i><i class="ph-bold ph-check rule-icon rule-icon-valid" aria-hidden="true"></i>1 mayúscula</li>
                    <li data-rule="number"><i class="ph ph-circle rule-icon rule-icon-empty" aria-hidden="true"></i><i class="ph-bold ph-check rule-icon rule-icon-valid" aria-hidden="true"></i>1 número</li>
                </ul>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirmar contraseña</label>
                <div class="input-wrapper">
                    <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required>
                    <button class="toggle-password" type="button" aria-label="Mostrar contraseña" aria-pressed="false">
                        <i class="ph ph-eye" aria-hidden="true"></i><i class="ph ph-eye-slash" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <small class="field-error" id="confirmPasswordError" aria-live="polite">Las contraseñas no coinciden.</small>
            <div class="check-row">
                <input id="terms" name="terms" type="checkbox" value="1" required>
                <label for="terms">Acepto los <a href="terminos.php" id="openTermsLink" target="_blank" rel="noopener">Términos y Condiciones y la Política de Privacidad</a>.</label>
            </div>
            <button class="btn-submit" type="submit">Crear cuenta</button>
        </form>
        <div class="form-footer">¿Ya tienes cuenta? <a href="inicioSesion.php">Inicia sesión</a></div>
    </main>

    <div class="terms-modal" id="termsModal" aria-hidden="true">
    <section class="terms-dialog" role="dialog" aria-modal="true" aria-label="Términos, condiciones y política de privacidad">
        <iframe class="terms-frame" id="termsFrame" title="Términos, condiciones y política de privacidad" src="about:blank"></iframe>
    </section>
    </div>

    <script>
        const registerForm = document.getElementById('registerForm');
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const confirmPasswordError = document.getElementById('confirmPasswordError');
        const registerNotice = document.getElementById('registerNotice');
        const registerButton = registerForm.querySelector('.btn-submit');
        const termsCheckbox = document.getElementById('terms');
        const termsModal = document.getElementById('termsModal');
        const termsFrame = document.getElementById('termsFrame');
        const openTermsLink = document.getElementById('openTermsLink');
        let previousFocus = null;
        const passwordRules = {
            length: (value) => value.length >= 8,
            uppercase: (value) => /[A-Z]/.test(value),
            number: (value) => /[0-9]/.test(value)
        };

        function showNotice(message, type) {
            registerNotice.textContent = message;
            registerNotice.className = `notice visible ${type}`;
        }
        function updatePasswordRules() {
            Object.entries(passwordRules).forEach(([rule, check]) => {
                document.querySelector(`[data-rule="${rule}"]`).classList.toggle('valid', check(passwordInput.value));
            });
        }
        function updatePasswordMatch() {
            const mismatch = confirmPasswordInput.value.length > 0 && passwordInput.value !== confirmPasswordInput.value;
            confirmPasswordError.classList.toggle('visible', mismatch);
            confirmPasswordInput.setAttribute('aria-invalid', String(mismatch));
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
        passwordInput.addEventListener('input', () => {
            updatePasswordRules();
            updatePasswordMatch();
        });
        confirmPasswordInput.addEventListener('input', updatePasswordMatch);

        function closeTermsModal(accepted = false) {
            if (!termsModal.classList.contains('visible')) return;
            termsModal.classList.remove('visible');
            termsModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            if (accepted) termsCheckbox.checked = true;
            if (previousFocus instanceof HTMLElement) previousFocus.focus();
        }
        openTermsLink.addEventListener('click', (event) => {
            event.preventDefault();
            previousFocus = document.activeElement;
            termsFrame.src = 'terminos.php?embed=1';
            termsModal.classList.add('visible');
            termsModal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        });
        termsModal.addEventListener('click', (event) => {
            if (event.target === termsModal) closeTermsModal();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeTermsModal();
        });
        window.addEventListener('message', (event) => {
            if (event.origin !== window.location.origin
                || event.source !== termsFrame.contentWindow
                || !event.data
                || !['kion-terms-accepted', 'kion-terms-dismissed'].includes(event.data.type)) {
                return;
            }
            closeTermsModal(event.data.type === 'kion-terms-accepted');
        });

        registerForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            registerNotice.className = 'notice';
            updatePasswordRules();
            updatePasswordMatch();
            const nombre = registerForm.elements.nombre.value.trim();
            const apellido = registerForm.elements.apellido.value.trim();
            const emailInput = registerForm.elements.email;
            if (!nombre || !apellido) {
                showNotice('Escribe tus nombres y apellidos.', 'error');
                return;
            }
            if (!emailInput.value.trim() || !emailInput.validity.valid) {
                showNotice('Escribe un correo electrónico válido.', 'error');
                emailInput.focus();
                return;
            }
            if (!Object.values(passwordRules).every((check) => check(passwordInput.value))) {
                showNotice('La contraseña necesita al menos 8 caracteres, una mayúscula y un número.', 'error');
                passwordInput.focus();
                return;
            }
            if (!confirmPasswordInput.value || passwordInput.value !== confirmPasswordInput.value) {
                confirmPasswordError.classList.add('visible');
                showNotice('Confirma la contraseña correctamente.', 'error');
                confirmPasswordInput.focus();
                return;
            }
            if (!registerForm.elements.terms.checked) {
                showNotice('Debes aceptar los Términos y Condiciones y la Política de Privacidad.', 'error');
                return;
            }

            registerButton.disabled = true;
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(registerForm)
                });
                const result = await response.json();
                if (!result.ok) {
                    showNotice(result.message || 'No fue posible crear la cuenta.', 'error');
                    return;
                }
                showNotice(result.message || 'Cuenta creada correctamente.', 'success');
                window.setTimeout(() => { window.location.href = result.redirect; }, 1500);
            } catch (error) {
                showNotice('No fue posible conectar con el servidor. Inténtalo de nuevo.', 'error');
            } finally {
                registerButton.disabled = false;
            }
        });

        document.querySelectorAll('a[href="inicioSesion.php"]').forEach((link) => {
            link.addEventListener('click', (event) => {
                event.preventDefault();
                document.body.classList.add('page-exit');
                window.setTimeout(() => { window.location.href = link.href; }, 280);
            });
        });
    </script>
</body>
</html>