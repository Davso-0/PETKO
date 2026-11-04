<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/src/php/config/conexion_BD.php';

$token = $_POST['token'] ?? $_GET['token'] ?? '';
$token = is_string($token) ? trim($token) : '';
$error = null;
$exito = false;
$usuario = null;

if (!preg_match('/\A[a-f0-9]{64}\z/', $token) || $pdo === null) {
    $error = $pdo === null ? ($errorConexion ?? 'No fue posible conectar con el sistema.') : 'El enlace de activación no es válido.';
} else {
    try {
        $consulta = $pdo->prepare(
            "SELECT u.id_usuario, u.nombre, u.correo, r.nombre AS rol
             FROM usuarios u INNER JOIN roles r ON r.id_rol = u.id_rol
             WHERE u.reset_token = ? AND u.estado = 'pendiente' AND u.token_expira > CURRENT_TIMESTAMP LIMIT 1"
        );
        $consulta->execute([$token]);
        $usuario = $consulta->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$usuario) {
            $error = 'El enlace de activación ha expirado o no es válido. Solicita un nuevo correo al administrador.';
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $usuario) {
            $password = $_POST['password'] ?? '';
            $confirmacion = $_POST['password_confirm'] ?? '';
            $acepta = $_POST['acepto_terminos'] ?? '';
            if (!is_string($password) || !is_string($confirmacion) || $password === '') {
                $error = 'Ingresa una contraseña.';
            } elseif (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
                $error = 'La contraseña debe tener al menos 8 caracteres, una mayúscula y un número.';
            } elseif ($password !== $confirmacion) {
                $error = 'Las contraseñas no coinciden.';
            } elseif ($acepta !== '1') {
                $error = 'Debes aceptar los Términos y Condiciones.';
            } else {
                $actualizar = $pdo->prepare(
                    "UPDATE usuarios SET password_hash = ?, estado = 'activo', reset_token = NULL, token_expira = NULL WHERE id_usuario = ? AND estado = 'pendiente'"
                );
                $actualizar->execute([password_hash($password, PASSWORD_DEFAULT), $usuario['id_usuario']]);
                if ($actualizar->rowCount() !== 1) {
                    $error = 'La cuenta ya fue activada o el enlace dejó de ser válido.';
                } else {
                    $exito = true;
                }
            }
        }
    } catch (PDOException $exception) {
        error_log('PETKO activar-cuenta: ' . $exception->getMessage());
        $error = 'No fue posible procesar la activación. Inténtalo nuevamente.';
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PETKO | Activar cuenta</title>
    <style>
        :root { --navy:#0B2545; --deep:#071A32; --green:#22C55E; --bg:#F3F6F8; --text:#142033; --muted:#5A6B7D; }
        * { box-sizing:border-box; } body { margin:0; min-height:100vh; display:grid; place-items:center; padding:24px; background:var(--bg); color:var(--text); font:16px Arial,sans-serif; }
        .card { width:min(480px,100%); background:#fff; border-radius:10px; box-shadow:0 10px 30px rgba(7,26,50,.12); overflow:hidden; }
        header { padding:24px; background:var(--navy); color:#fff; font-size:28px; font-weight:bold; } header span { color:var(--green); }
        main { padding:28px; } h1 { margin:0 0 8px; color:var(--navy); font-size:24px; } p { color:var(--muted); line-height:1.5; }
        label { display:block; margin:16px 0 6px; font-weight:bold; } input[type=password] { width:100%; padding:12px; border:1px solid #CBD5E1; border-radius:6px; font:inherit; }
        .terms { display:flex; gap:8px; align-items:flex-start; margin:18px 0; color:var(--muted); line-height:1.4; } a { color:#087F3E; } button { width:100%; padding:13px; border:0; border-radius:6px; background:var(--green); color:#fff; font-weight:bold; cursor:pointer; }
        .error { padding:12px; border-radius:6px; background:#FEF2F2; color:#991B1B; } .success { padding:12px; border-radius:6px; background:#F0FDF4; color:#166534; }
    </style>
</head>
<body><article class="card"><header>PET<span>KO</span></header><main>
<?php if ($exito): ?>
    <h1>Cuenta activada</h1><p class="success">Tu contraseña se estableció correctamente. Ya puedes iniciar sesión en PETKO.</p><p><a href="inicioSesion.php">Ir a iniciar sesión</a></p>
<?php elseif ($error !== null): ?>
    <h1>No se pudo activar la cuenta</h1><p class="error"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
<?php else: ?>
    <h1>Activa tu cuenta</h1><p>Hola <?= htmlspecialchars((string)$usuario['nombre'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>. Define una contraseña para tu cuenta de <?= htmlspecialchars((string)$usuario['rol'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>.</p>
    <form method="post">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <label for="password">Nueva contraseña</label><input id="password" name="password" type="password" minlength="8" required autocomplete="new-password">
        <label for="password_confirm">Confirmar contraseña</label><input id="password_confirm" name="password_confirm" type="password" minlength="8" required autocomplete="new-password">
        <label class="terms"><input type="checkbox" name="acepto_terminos" value="1" required> <span>Acepto los <a href="terminos.php?origen=activacion&amp;token=<?= rawurlencode($token) ?>" target="_blank" rel="noopener">Términos y Condiciones</a>.</span></label>
        <button type="submit">Activar mi cuenta</button>
    </form>
<?php endif; ?>
</main></article></body></html>
