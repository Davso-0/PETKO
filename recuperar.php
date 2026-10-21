<?php
declare(strict_types=1);

function cookieSeguraRecuperacion(): bool
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    return $https || $forwardedProto === 'https' || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => cookieSeguraRecuperacion(),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
$_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));

function responderRecuperacion(bool $ok, string $message, ?string $redirect = null): never
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        ['ok' => $ok, 'message' => $message, 'redirect' => $redirect],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

require_once __DIR__ . '/src/php/config/conexion_BD.php';
$errorPagina = null;
$usuarioRecuperacion = null;
$token = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['token'] ?? '')
    : ($_GET['token'] ?? '');
$token = is_string($token) ? $token : '';
$tokenValido = preg_match('/\A[a-f0-9]{64}\z/', $token) === 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!is_string($csrfToken) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        responderRecuperacion(false, 'La solicitud expiró o no es válida. Recarga la página e inténtalo de nuevo.');
    }
    if ($pdo === null) {
        responderRecuperacion(false, $errorConexion ?? 'No fue posible conectar con la base de datos.');
    }
    if (!$tokenValido) {
        responderRecuperacion(false, 'El enlace de recuperación es inválido o ha expirado.');
    }

    $contrasena = $_POST['password'] ?? '';
    $confirmacion = $_POST['confirm_password'] ?? '';
    $contrasena = is_string($contrasena) ? $contrasena : '';
    $confirmacion = is_string($confirmacion) ? $confirmacion : '';
    if ($contrasena !== $confirmacion) {
        responderRecuperacion(false, 'Las contraseñas no coinciden.');
    }
    if (!preg_match('/^(?=.*[A-Z])(?=.*\d).{8,}$/', $contrasena)) {
        responderRecuperacion(false, 'La contraseña debe tener al menos 8 caracteres, una letra mayúscula y un número.');
    }

    try {
        $tokenHash = hash('sha256', $token);
        $consultaToken = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE recovery_token = ? AND recovery_expires_at > CURRENT_TIMESTAMP AND estado = 'ACTIVO' LIMIT 1");
        $consultaToken->execute([$tokenHash]);
        $idUsuario = $consultaToken->fetchColumn();
        if (!$idUsuario) {
            responderRecuperacion(false, 'El enlace de recuperación es inválido o ha expirado.');
        }

        $hashNuevo = password_hash($contrasena, PASSWORD_DEFAULT);
        $actualizar = $pdo->prepare("UPDATE usuarios SET password_hash = ?, recovery_token = NULL, recovery_expires_at = NULL WHERE id_usuario = ? AND recovery_token = ? AND recovery_expires_at > CURRENT_TIMESTAMP AND estado = 'ACTIVO'");
        $actualizar->execute([$hashNuevo, $idUsuario, $tokenHash]);
        if ($actualizar->rowCount() !== 1) {
            responderRecuperacion(false, 'El enlace de recuperación es inválido o ha expirado.');
        }

        responderRecuperacion(true, 'Tu contraseña se actualizó correctamente.', 'inicioSesion.php?reset=success');
    } catch (PDOException $e) {
        error_log('KION restablecimiento de contraseña: ' . $e->getMessage());
        responderRecuperacion(false, 'No fue posible actualizar la contraseña. Inténtalo de nuevo.');
    }
}

if ($pdo === null) {
    $errorPagina = $errorConexion ?? 'No fue posible conectar con la base de datos.';
} elseif (!$tokenValido) {
    $errorPagina = 'El enlace de recuperación es inválido o ha expirado.';
} else {
    try {
        $consultaToken = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE recovery_token = ? AND recovery_expires_at > CURRENT_TIMESTAMP AND estado = 'ACTIVO' LIMIT 1");
        $consultaToken->execute([hash('sha256', $token)]);
        $usuarioRecuperacion = $consultaToken->fetch();
        if (!$usuarioRecuperacion) {
            $errorPagina = 'El enlace de recuperación es inválido o ha expirado.';
        }
    } catch (PDOException $e) {
        error_log('KION validación de enlace de recuperación: ' . $e->getMessage());
        $errorPagina = 'No fue posible validar el enlace. Inténtalo de nuevo más tarde.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PETKO | Restablecer contraseña</title>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600;700&amp;family=Plus+Jakarta+Sans:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css">
    <style>
        :root {
            --bg-gradient: linear-gradient(135deg, #f8fafc 0%, #f0fdf4 50%, #e0f2fe 100%);
            --card-bg: rgba(255, 255, 255, .85);
            --green-primary: #16a34a;
            --green-hover: #15803d;
            --field-bg: rgba(248, 250, 252, .8);
            --field-border: #cbd5e1;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --error-red: #dc2626;
            --error-bg: #fef2f2;
            --success-green: #16a34a;
            --success-bg: #f0fdf4;
            --font-main: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            --font-brand: 'Fredoka', sans-serif;
        }
        * { box-sizing: border-box; }
        html, body { width: 100%; min-height: 100%; margin: 0; }
        body {
            display: grid;
            min-height: 100vh;
            min-height: 100svh;
            place-items: center;
            padding: 16px;
            background: var(--bg-gradient);
            color: var(--text-main);
            font-family: var(--font-main);
        }
        .auth-card {
            width: min(420px, 100%);
            padding: 26px 28px;
            border: 1px solid rgba(255, 255, 255, .9);
            border-radius: 16px;
            background: var(--card-bg);
            box-shadow: 0 20px 50px rgba(15, 23, 42, .12);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }
        .brand-header { margin-bottom: 18px; text-align: center; }
        .brand-badge { color: var(--green-primary); font-size: 11px; font-weight: 700; }
        .brand-logo { margin: 3px 0 0; color: #0f172a; font-family: var(--font-brand); font-size: 30px; font-weight: 700; letter-spacing: 3px; line-height: 1.1; }
        .brand-logo span { color: var(--green-primary); }
        .brand-subtitle { color: var(--text-muted); font-size: 10px; letter-spacing: 1.5px; text-transform: uppercase; }
        .form-title { margin: 0 0 8px; color: var(--green-hover); font-size: 19px; text-align: center; }
        .form-copy { margin: 0 0 17px; color: var(--text-muted); font-size: 12px; line-height: 1.55; text-align: center; }
        .form-group { margin-bottom: 12px; }
        label { display: block; margin-bottom: 5px; color: var(--text-muted); font-size: 12px; }
        .input-wrapper { position: relative; }
        input[type="password"], input[type="text"] {
            width: 100%;
            padding: 10px 44px 10px 13px;
            border: 1px solid var(--field-border);
            border-radius: 8px;
            outline: none;
            background: var(--field-bg);
            color: var(--text-main);
            font: inherit;
            font-size: 14px;
        }
        input:focus { border-color: var(--green-hover); box-shadow: 0 0 0 2px rgba(22, 163, 74, .18); }
        .toggle-password { position: absolute; top: 50%; right: 6px; display: grid; width: 34px; height: 34px; padding: 0; transform: translateY(-50%); place-items: center; border: 0; border-radius: 6px; background: transparent; color: var(--text-muted); cursor: pointer; }
        .toggle-password:hover { color: var(--green-primary); }
        .toggle-password .ph-eye-slash { display: none; }
        .toggle-password[aria-pressed="true"] .ph-eye { display: none; }
        .toggle-password[aria-pressed="true"] .ph-eye-slash { display: inline; }
        .password-help { margin: -4px 0 13px; color: var(--text-muted); font-size: 11px; }
        .password-help p { margin: 0 0 4px; }
        .password-help ul { margin: 0; padding-left: 18px; }
        .notice { display: none; margin: 0 0 12px; padding: 10px 12px; border: 1px solid transparent; border-radius: 8px; font-size: 12px; line-height: 1.45; }
        .notice.visible { display: block; }
        .notice.error { border-color: rgba(248, 113, 113, .35); background: var(--error-bg); color: var(--error-red); }
        .notice.success { border-color: rgba(74, 222, 128, .35); background: var(--success-bg); color: var(--success-green); }
        .btn-submit, .btn-link { display: flex; width: 100%; min-height: 42px; align-items: center; justify-content: center; border-radius: 9px; font: inherit; font-size: 13px; font-weight: 700; text-decoration: none; }
        .btn-submit { border: 0; background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); color: #052e16; cursor: pointer; }
        .btn-submit:hover { box-shadow: 0 6px 20px rgba(22, 163, 74, .25); transform: translateY(-1px); }
        .btn-submit:disabled { cursor: wait; opacity: .65; transform: none; }
        .btn-link { border: 1px solid var(--field-border); color: var(--green-hover); }
        .btn-link:hover { background: rgba(22, 163, 74, .06); }
        .form-footer { margin-top: 15px; color: var(--text-muted); font-size: 12px; text-align: center; }
        .form-footer a { color: var(--green-primary); font-weight: 700; text-decoration: none; }
        @media (max-width: 400px) { .auth-card { padding: 23px 20px; } }
    </style>
</head>
<body>
    <main class="auth-card">
        <header class="brand-header">
            <span class="brand-badge"><i class="ph-fill ph-paw-print" aria-hidden="true"></i> Veterinaria &amp; Farmacia</span>
            <h1 class="brand-logo">PET<span>KO</span></h1>
            <div class="brand-subtitle">Vet &amp; Agropecuario</div>
        </header>
        <?php if ($errorPagina !== null): ?>
            <h2 class="form-title">No se pudo restablecer la contraseña</h2>
            <div class="notice visible error" role="alert"><?= htmlspecialchars($errorPagina, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <a class="btn-link" href="inicioSesion.php">Volver al inicio de sesión</a>
        <?php else: ?>
            <h2 class="form-title">Crear nueva contraseña</h2>
            <p class="form-copy">Elige una contraseña segura para volver a acceder a tu cuenta.</p>
            <div class="notice" id="resetNotice" role="status" aria-live="polite"></div>
            <form id="resetForm" method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-group">
                    <label for="password">Nueva contraseña</label>
                    <div class="input-wrapper">
                        <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required>
                        <button class="toggle-password" type="button" aria-label="Mostrar contraseña" aria-pressed="false">
                            <i class="ph ph-eye" aria-hidden="true"></i><i class="ph ph-eye-slash" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirmar contraseña</label>
                    <div class="input-wrapper">
                        <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" minlength="8" required>
                        <button class="toggle-password" type="button" aria-label="Mostrar contraseña" aria-pressed="false">
                            <i class="ph ph-eye" aria-hidden="true"></i><i class="ph ph-eye-slash" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <div class="password-help">
                    <p>La contraseña debe tener al menos 8 caracteres, una letra mayúscula y un número.</p>
                </div>
                <button class="btn-submit" type="submit">Guardar nueva contraseña</button>
            </form>
        <?php endif; ?>
        <div class="form-footer"><a href="inicioSesion.php">Volver al inicio de sesión</a></div>
    </main>
    <?php if ($errorPagina === null): ?>
        <script>
            const resetForm = document.getElementById('resetForm');
            const resetNotice = document.getElementById('resetNotice');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');

            function showNotice(message, type) {
                resetNotice.textContent = message;
                resetNotice.className = `notice visible ${type}`;
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

            resetForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                resetNotice.className = 'notice';
                if (passwordInput.value !== confirmPasswordInput.value) {
                    showNotice('Las contraseñas no coinciden.', 'error');
                    confirmPasswordInput.focus();
                    return;
                }
                if (!/^(?=.*[A-Z])(?=.*\d).{8,}$/.test(passwordInput.value)) {
                    showNotice('La contraseña debe tener al menos 8 caracteres, una letra mayúscula y un número.', 'error');
                    passwordInput.focus();
                    return;
                }

                const button = resetForm.querySelector('button[type="submit"]');
                button.disabled = true;
                try {
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: new FormData(resetForm)
                    });
                    const result = await response.json();
                    if (!response.ok || !result.ok) {
                        showNotice(result.message || 'No fue posible actualizar la contraseña.', 'error');
                        return;
                    }
                    showNotice(result.message, 'success');
                    window.setTimeout(() => { window.location.href = result.redirect; }, 1200);
                } catch (error) {
                    showNotice('No fue posible conectar con el servidor. Inténtalo de nuevo.', 'error');
                } finally {
                    button.disabled = false;
                }
            });
        </script>
    <?php endif; ?>
</body>
</html>
