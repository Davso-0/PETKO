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
    <style>
        :root {
            /* Paleta Oficial PETKO */
            --petko-yellow: #E5C051;
            --petko-yellow-hover: #D4A72C;
            --petko-blue: #1B6CA8;
            --petko-navy: #0B2545;
            
            --bg-card: rgba(11, 22, 38, 0.78);
            --field-bg: rgba(255, 255, 255, 0.07);
            --field-border: rgba(255, 255, 255, 0.18);
            --text-main: #FFFFFF;
            --text-muted: #A3B8CC;
            --error-red: #FF6B6B;
            --error-bg: rgba(255, 107, 107, 0.15);
            --success-green: #22C55E;
            --success-bg: rgba(34, 197, 94, 0.15);
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
            background: #071A32;
            color: var(--text-main);
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            animation: pageEnter .4s ease backwards;
        }
        body.page-exit { animation: pageExit .28s ease both; }
        #videoFondo, .velo { position: fixed; inset: 0; width: 100%; height: 100%; }
        #videoFondo { z-index: -2; object-fit: cover; }
        .velo { z-index: -1; background: rgba(7, 26, 50, 0.65); }
        
        /* Botón Flotante Regresar */
        .btn-back-home {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 100;
            display: flex;
            align-items: center;
            gap: 10px;
            height: 46px;
            width: 46px;
            padding: 0 13px;
            border: 1px solid rgba(229, 192, 81, 0.35);
            border-radius: 50px;
            background: rgba(11, 22, 38, 0.85);
            color: var(--petko-yellow);
            text-decoration: none;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            overflow: hidden;
            transition: width 0.35s cubic-bezier(0.4, 0, 0.2, 1), background 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease;
            white-space: nowrap;
        }
        .btn-back-home svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
            fill: none;
            stroke: currentColor;
            stroke-width: 2.2;
            stroke-linecap: round;
            stroke-linejoin: round;
            transition: transform 0.25s ease;
        }
        .btn-back-home span {
            font-size: 13px;
            font-weight: 700;
            opacity: 0;
            transform: translateX(-8px);
            transition: opacity 0.25s ease 0.05s, transform 0.25s ease 0.05s;
            color: #FFFFFF;
        }
        .btn-back-home:hover {
            width: 155px;
            border-color: var(--petko-yellow);
            background: rgba(11, 22, 38, 0.95);
            box-shadow: 0 10px 25px rgba(229, 192, 81, 0.25);
        }
        .btn-back-home:hover svg { transform: translateX(-2px); }
        .btn-back-home:hover span { opacity: 1; transform: translateX(0); }

        /* Tarjeta Principal y Efectos */
        .auth-card {
            width: min(420px, 100%);
            max-height: calc(100svh - 16px);
            padding: 24px 28px;
            border: 1px solid rgba(229, 192, 81, 0.35);
            border-radius: 24px;
            background: var(--bg-card);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.75), 0 0 24px rgba(27, 108, 168, 0.25);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.35s cubic-bezier(0.4, 0, 0.2, 1), border-color 0.35s ease;
        }
        .auth-card:hover {
            border-color: rgba(229, 192, 81, 0.55);
            box-shadow: 0 30px 60px -12px rgba(0, 0, 0, 0.85), 0 0 32px rgba(229, 192, 81, 0.18);
        }
        
        .brand-header { margin-bottom: 12px; text-align: center; }
        .brand-header img {
            max-width: 190px;
            width: 100%;
            height: auto;
            display: block;
            margin: 0 auto;
            filter: drop-shadow(0 6px 14px rgba(0, 0, 0, 0.5));
            transition: transform 0.3s ease;
        }
        .brand-header img:hover { transform: scale(1.03); }
        
        .form-title { margin: 0 0 11px; color: var(--petko-yellow); font-size: 18px; font-weight: 700; text-align: center; }
        .name-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .form-group { min-width: 0; margin-bottom: 8px; }
        label { display: block; margin-bottom: 4px; color: var(--text-muted); font-size: 11px; transition: color 0.2s ease; }
        .form-group:focus-within label { color: var(--petko-yellow); }

        /* Inputs Dinámicos */
        input[type="email"], input[type="password"], input[type="text"] {
            width: 100%;
            min-width: 0;
            padding: 9px 12px;
            border: 1px solid var(--field-border);
            border-radius: 9px;
            outline: none;
            background: var(--field-bg);
            color: var(--text-main);
            font: inherit;
            font-size: 13px;
            transition: transform 0.22s cubic-bezier(0.4, 0, 0.2, 1), border-color 0.22s ease, background-color 0.22s ease, box-shadow 0.22s ease;
        }
        input[type="email"]:hover, input[type="password"]:hover, input[type="text"]:hover {
            border-color: rgba(229, 192, 81, 0.5);
            background: rgba(255, 255, 255, 0.10);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        }
        input:focus {
            border-color: var(--petko-yellow);
            background: rgba(255, 255, 255, 0.12);
            transform: translateY(-2px) scale(1.01);
            box-shadow: 0 0 0 3px rgba(229, 192, 81, 0.35), 0 6px 16px rgba(0, 0, 0, 0.3);
        }
        input:-webkit-autofill, input:-webkit-autofill:hover, input:-webkit-autofill:focus {
            -webkit-text-fill-color: var(--text-main);
            box-shadow: 0 0 0 1000px #0B2545 inset;
            transition: background-color 9999s ease-out;
        }
        
        .input-wrapper { position: relative; }
        .input-wrapper input { padding-right: 44px; }
        .toggle-password { position: absolute; top: 50%; right: 6px; display: grid; width: 32px; height: 32px; padding: 0; transform: translateY(-50%); place-items: center; border: 0; border-radius: 6px; background: transparent; color: var(--text-muted); cursor: pointer; transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), color 0.2s ease; }
        .toggle-password:hover { color: var(--petko-yellow); transform: translateY(-50%) scale(1.15) rotate(-5deg); }
        .toggle-password svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-linecap: round; stroke-linejoin: round; stroke-width: 1.7; }
        
        .password-help { margin: 1px 0 8px; }
        .password-help p { margin: 0 0 5px; color: var(--text-muted); font-size: 10px; }
        .password-rules { display: flex; flex-wrap: wrap; gap: 6px; margin: 0; padding: 0; list-style: none; }
        .password-rules li { padding: 4px 7px; border: 1px solid rgba(255, 255, 255, .15); border-radius: 999px; background: rgba(255, 255, 255, .04); color: var(--text-muted); font-size: 9px; line-height: 1.2; transition: all .18s; cursor: default; }
        .password-rules li::before { margin-right: 4px; content: '○'; color: #777; }
        .password-rules li.valid { border-color: rgba(34, 197, 94, .4); background: rgba(34, 197, 94, .12); color: var(--success-green); }
        .password-rules li.valid::before { content: '✓'; color: var(--success-green); }
        .password-rules li:hover { transform: translateY(-1px); border-color: rgba(229, 192, 81, 0.4); color: #FFFFFF; }
        
        .field-error { display: none; margin: -4px 0 7px; color: var(--error-red); font-size: 10px; }
        .field-error.visible { display: block; }
        
        .check-row { display: flex; align-items: flex-start; gap: 8px; margin: 2px 0 10px; color: var(--text-muted); font-size: 10px; line-height: 1.35; cursor: pointer; }
        .check-row input { width: 14px; height: 14px; flex: 0 0 auto; margin: 0; accent-color: var(--petko-yellow); cursor: pointer; }
        .check-row label { margin: 0; color: inherit; font-size: inherit; cursor: pointer; transition: color 0.2s ease; }
        .check-row:hover label { color: var(--petko-yellow); }
        
        .btn-submit { width: 100%; min-height: 40px; border: 0; border-radius: 9px; background: linear-gradient(135deg, var(--petko-yellow) 0%, var(--petko-yellow-hover) 100%); color: #071A32; cursor: pointer; font: inherit; font-size: 14px; font-weight: 800; box-shadow: 0 6px 18px rgba(229, 192, 81, 0.25); transition: all .22s ease; }
        .btn-submit:hover { box-shadow: 0 8px 22px rgba(229, 192, 81, 0.4); transform: translateY(-2px); }
        .btn-submit:disabled { cursor: wait; opacity: .7; transform: none; }
        
        .form-footer { margin-top: 11px; color: var(--text-muted); font-size: 11px; text-align: center; }
        .form-footer a { color: var(--petko-yellow); font-weight: 700; text-decoration: none; transition: color 0.2s ease; }
        .form-footer a:hover { color: #FFFFFF; text-decoration: underline; }
        
        .notice { display: none; margin: 0 0 9px; padding: 8px 10px; border: 1px solid transparent; border-radius: 8px; font-size: 11px; line-height: 1.35; }
        .notice.visible { display: block; animation: fadeIn .2s ease both; }
        .notice.error { border-color: rgba(255, 107, 107, .35); background: var(--error-bg); color: var(--error-red); }
        .notice.success { border-color: rgba(34, 197, 94, .35); background: var(--success-bg); color: var(--success-green); }
        
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
        @media (max-width: 360px) { .auth-card { padding-right: 17px; padding-left: 17px; } .name-row { gap: 7px; } }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; } }
    </style>
</head>
<body>
    <!-- Botón Flotante Regresar al Inicio -->
    <a href="src/php/modulos/home/home.php" class="btn-back-home" title="Volver al inicio" aria-label="Volver al inicio">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <line x1="19" y1="12" x2="5" y2="12"></line>
            <polyline points="12 19 5 12 12 5"></polyline>
        </svg>
        <span>Volver al inicio</span>
    </a>

    <video id="videoFondo" autoplay muted loop playsinline>
        <source src="src/videoEJEMPLO/22.mp4" type="video/mp4">
    </video>
    <div class="velo"></div>

    <main class="auth-card">
        <header class="brand-header">
            <img src="src/img/logos/4_fondo.png" alt="PETKO Veterinaria &amp; Ganadería">
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
                <input id="email" name="email" type="email" placeholder="usuario@petko.com" autocomplete="email" required>
            </div>
            <div class="form-group">
                <label for="password">Contraseña</label>
                <div class="input-wrapper">
                    <input id="password" name="password" type="password" autocomplete="new-password" required>
                    <button class="toggle-password" type="button" aria-label="Mostrar contraseña" aria-pressed="false">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>
            <div class="password-help">
                <p>La contraseña debe cumplir:</p>
                <ul class="password-rules" aria-label="Requisitos de la contraseña">
                    <li data-rule="length">Mínimo 8</li>
                    <li data-rule="uppercase">1 mayúscula</li>
                    <li data-rule="number">1 número</li>
                </ul>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirmar contraseña</label>
                <div class="input-wrapper">
                    <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required>
                    <button class="toggle-password" type="button" aria-label="Mostrar contraseña" aria-pressed="false">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>
            <small class="field-error" id="confirmPasswordError" aria-live="polite">Las contraseñas no coinciden.</small>
            <div class="check-row">
                <input id="terms" name="terms" type="checkbox" value="1" required>
                <label for="terms">Acepto los Términos y Condiciones y la Política de Privacidad.</label>
            </div>
            <button class="btn-submit" type="submit">Crear cuenta</button>
        </form>
        <div class="form-footer">¿Ya tienes cuenta? <a href="inicioSesion.php">Inicia sesión</a></div>
    </main>

    <script>
        const registerForm = document.getElementById('registerForm');
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const confirmPasswordError = document.getElementById('confirmPasswordError');
        const registerNotice = document.getElementById('registerNotice');
        const registerButton = registerForm.querySelector('.btn-submit');
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