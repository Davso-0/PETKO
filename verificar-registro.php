<?php
declare(strict_types=1);
session_start();
$_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));

function responderVerificacionRegistro(bool $ok, string $mensaje, ?string $redirect = null): never
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        ['ok' => $ok, 'message' => $mensaje, 'redirect' => $redirect],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

$csrfToken = $_SESSION['csrf_token'];
$mensajeInicial = $_SESSION['registro_notice'] ?? null;
$tipoMensajeInicial = $_SESSION['registro_notice_type'] ?? 'success';
unset($_SESSION['registro_notice']);
unset($_SESSION['registro_notice_type']);
$correoInicial = $_SESSION['registro_correo_pendiente'] ?? '';
$correoInicial = is_string($correoInicial) ? $correoInicial : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfEnviado = $_POST['csrf_token'] ?? '';
    if (!is_string($csrfEnviado) || !hash_equals($csrfToken, $csrfEnviado)) {
        responderVerificacionRegistro(false, 'La solicitud expiró o no es válida. Recarga la página e inténtalo de nuevo.');
    }

    $accion = $_POST['action'] ?? '';
    $correo = $_POST['email'] ?? '';
    $correo = is_string($correo) ? trim($correo) : '';
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        responderVerificacionRegistro(false, 'Escribe un correo electrónico válido.');
    }

    require_once __DIR__ . '/src/php/config/conexion_BD.php';
    if ($pdo === null) {
        responderVerificacionRegistro(false, $errorConexion ?? 'No fue posible conectar con la base de datos.');
    }

    try {
        require_once __DIR__ . '/src/php/servicios/EmailService.php';
        if ($accion === 'reenviar') {
            $pdo->beginTransaction();
            $consulta = $pdo->prepare(
                "SELECT u.id_usuario, u.nombre, v.enviado_en, v.ventana_envios_inicio, v.envios_en_ventana
                 , (v.enviado_en > DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 60 SECOND)) AS cooldown_activo
                 , (v.ventana_envios_inicio <= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 HOUR)) AS ventana_expirada
                 FROM usuarios u
                 INNER JOIN registro_verificaciones v ON v.id_usuario = u.id_usuario
                 WHERE u.correo = ? AND LOWER(u.estado) = 'pendiente'
                 FOR UPDATE"
            );
            $consulta->execute([$correo]);
            $pendiente = $consulta->fetch();
            if (!$pendiente) {
                $pdo->commit();
                responderVerificacionRegistro(true, 'Si existe una cuenta pendiente para ese correo, recibirás instrucciones para verificarla.');
            }

            if ((int)$pendiente['cooldown_activo'] === 1) {
                $pdo->commit();
                responderVerificacionRegistro(false, 'Espera un minuto antes de solicitar otro código.');
            }

            $envios = (int)$pendiente['envios_en_ventana'];
            $ventanaExpirada = (int)$pendiente['ventana_expirada'] === 1;
            if ($ventanaExpirada) {
                $envios = 0;
            }
            if ($envios >= 5) {
                $pdo->commit();
                responderVerificacionRegistro(false, 'Se alcanzó el límite de códigos por ahora. Inténtalo más tarde.');
            }

            $codigo = (string)random_int(10000000, 99999999);
            $actualizarCodigo = $pdo->prepare(
                'UPDATE registro_verificaciones
                 SET codigo_hash = ?, expira_en = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 10 MINUTE),
                     intentos = 0, enviado_en = CURRENT_TIMESTAMP,
                     ventana_envios_inicio = IF(?, CURRENT_TIMESTAMP, ventana_envios_inicio),
                     envios_en_ventana = ?
                 WHERE id_usuario = ?'
            );
            $actualizarCodigo->execute([
                password_hash($codigo, PASSWORD_DEFAULT),
                $ventanaExpirada ? 1 : 0,
                $envios + 1,
                $pendiente['id_usuario'],
            ]);
            $pdo->commit();

            if (!EmailService::enviarCodigoVerificacion($correo, (string)$pendiente['nombre'], $codigo)) {
                error_log('KION registro: no se pudo reenviar el código de verificación SMTP.');
                responderVerificacionRegistro(false, 'No se pudo enviar el correo. Espera un minuto e inténtalo de nuevo.');
            }

            $_SESSION['registro_correo_pendiente'] = $correo;
            responderVerificacionRegistro(true, 'Si existe una cuenta pendiente para ese correo, recibirás un nuevo código.');
        }

        if ($accion !== 'verificar') {
            responderVerificacionRegistro(false, 'La solicitud no es válida. Recarga la página e inténtalo de nuevo.');
        }

        $codigo = $_POST['code'] ?? '';
        $codigo = is_string($codigo) ? trim($codigo) : '';
        $contrasena = $_POST['password'] ?? '';
        $contrasena = is_string($contrasena) ? $contrasena : '';
        $confirmacion = $_POST['confirm_password'] ?? '';
        $confirmacion = is_string($confirmacion) ? $confirmacion : '';
        if (!preg_match('/\A[0-9]{8}\z/', $codigo)) {
            responderVerificacionRegistro(false, 'Escribe el código de 8 dígitos que recibiste.');
        }
        if (strlen($contrasena) < 8 || !preg_match('/[A-Z]/', $contrasena) || !preg_match('/[0-9]/', $contrasena)) {
            responderVerificacionRegistro(false, 'La contraseña debe tener al menos 8 caracteres, una letra mayúscula y un número.');
        }
        if (!hash_equals($contrasena, $confirmacion)) {
            responderVerificacionRegistro(false, 'Las contraseñas no coinciden.');
        }

        $pdo->beginTransaction();
        $consulta = $pdo->prepare(
            "SELECT u.id_usuario, v.codigo_hash, v.intentos
             FROM usuarios u
             INNER JOIN registro_verificaciones v ON v.id_usuario = u.id_usuario
             WHERE u.correo = ? AND LOWER(u.estado) = 'pendiente'
               AND v.expira_en > CURRENT_TIMESTAMP AND v.intentos < 5
             FOR UPDATE"
        );
        $consulta->execute([$correo]);
        $registro = $consulta->fetch();
        if (!$registro) {
            $pdo->rollBack();
            responderVerificacionRegistro(false, 'El código es inválido, expiró o alcanzó el límite de intentos. Solicita uno nuevo.');
        }
        if (!password_verify($codigo, (string)$registro['codigo_hash'])) {
            $sumarIntento = $pdo->prepare('UPDATE registro_verificaciones SET intentos = intentos + 1 WHERE id_usuario = ?');
            $sumarIntento->execute([$registro['id_usuario']]);
            $pdo->commit();
            responderVerificacionRegistro(false, 'El código no es válido. Revisa el correo e inténtalo de nuevo.');
        }

        $actualizarUsuario = $pdo->prepare(
            "UPDATE usuarios
             SET password_hash = ?, estado = 'activo'
             WHERE id_usuario = ? AND LOWER(estado) = 'pendiente' AND password_hash IS NULL"
        );
        $actualizarUsuario->execute([
            password_hash($contrasena, PASSWORD_DEFAULT),
            $registro['id_usuario'],
        ]);
        if ($actualizarUsuario->rowCount() !== 1) {
            $pdo->rollBack();
            responderVerificacionRegistro(false, 'No fue posible activar la cuenta. Solicita un nuevo código.');
        }

        $eliminarCodigo = $pdo->prepare('DELETE FROM registro_verificaciones WHERE id_usuario = ?');
        $eliminarCodigo->execute([$registro['id_usuario']]);
        $pdo->commit();
        unset($_SESSION['registro_correo_pendiente']);
        responderVerificacionRegistro(true, 'Correo verificado y cuenta activada. Ya puedes iniciar sesión.', 'inicioSesion.php');
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('KION verificación de registro: ' . $e->getMessage());
        responderVerificacionRegistro(false, 'No fue posible procesar la solicitud. Inténtalo de nuevo.');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('KION verificación de registro: ' . $e->getMessage());
        responderVerificacionRegistro(false, 'No fue posible procesar la solicitud. Inténtalo de nuevo.');
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PETKO | Verificar correo</title>
    <style>
        * { box-sizing: border-box; }
        body { min-height: 100vh; display: grid; place-items: center; margin: 0; padding: 18px; background: linear-gradient(135deg,#EEF4FF,#BAD3FF 55%,#86B3FF); color: #14284a; font: 15px system-ui,-apple-system,"Segoe UI",sans-serif; }
        main { width: min(440px,100%); padding: 28px; border: 1px solid rgba(82,146,255,.25); border-radius: 18px; background: rgba(255,255,255,.94); box-shadow: 0 24px 60px rgba(41,73,128,.2); }
        h1 { margin: 0 0 8px; color: #294980; font-size: 24px; text-align: center; }
        p { color: #4f678c; line-height: 1.5; }
        label { display: block; margin: 14px 0 6px; color: #294980; font-size: 13px; font-weight: 650; }
        input { width: 100%; padding: 11px 12px; border: 1px solid #BAD3FF; border-radius: 8px; background: #fff; color: #14284a; font: inherit; }
        input:focus { outline: 3px solid rgba(82,146,255,.22); border-color: #5292FF; }
        button { width: 100%; min-height: 42px; margin-top: 16px; border: 0; border-radius: 9px; background: linear-gradient(135deg,#5292FF,#3966B3); color: #fff; cursor: pointer; font: inherit; font-weight: 700; }
        button:disabled { cursor: wait; opacity: .65; }
        .notice { display: none; margin: 12px 0; padding: 10px 12px; border-radius: 8px; font-size: 13px; line-height: 1.45; }
        .notice.visible { display: block; }
        .notice.error { background: #FEF2F2; color: #991B1B; }
        .notice.success { background: #F0FDF4; color: #166534; }
        .divider { height: 1px; margin: 22px 0; background: #dce8f8; }
        .footer { margin-top: 18px; text-align: center; font-size: 13px; }
        a { color: #3966B3; font-weight: 650; }
    </style>
</head>
<body>
<main>
    <h1>Verifica tu correo</h1>
    <p>Ingresa el código enviado a tu correo y crea una contraseña para activar la cuenta.</p>
    <?php $claseMensajeInicial = $tipoMensajeInicial === 'error' ? 'error' : 'success'; ?>
    <div class="notice<?= is_string($mensajeInicial) && $mensajeInicial !== '' ? ' visible ' . $claseMensajeInicial : '' ?>" id="notice" role="status" aria-live="polite"><?= htmlspecialchars(is_string($mensajeInicial) ? $mensajeInicial : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
    <form id="verifyForm" method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="action" value="verificar">
        <label for="email">Correo electrónico</label>
        <input id="email" name="email" type="email" value="<?= htmlspecialchars($correoInicial, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" autocomplete="email" required>
        <label for="code">Código de verificación</label>
        <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" autocomplete="one-time-code" required>
        <label for="password">Nueva contraseña</label>
        <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required>
        <label for="confirm_password">Confirmar contraseña</label>
        <input id="confirm_password" name="confirm_password" type="password" minlength="8" autocomplete="new-password" required>
        <p>La contraseña debe tener al menos 8 caracteres, una mayúscula y un número.</p>
        <button type="submit">Verificar y activar cuenta</button>
    </form>
    <div class="divider"></div>
    <form id="resendForm" method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="action" value="reenviar">
        <label for="resendEmail">¿No recibiste el código?</label>
        <input id="resendEmail" name="email" type="email" value="<?= htmlspecialchars($correoInicial, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" autocomplete="email" required>
        <button type="submit">Solicitar otro código</button>
    </form>
    <div class="footer"><a href="registro.php">Volver al registro</a> · <a href="inicioSesion.php">Iniciar sesión</a></div>
</main>
<script>
    const notice = document.getElementById('notice');
    function showNotice(message, type) {
        notice.textContent = message;
        notice.className = `notice visible ${type}`;
    }
    async function sendAction(form) {
        const button = form.querySelector('button');
        button.disabled = true;
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form)
            });
            const result = await response.json();
            showNotice(result.message || 'No fue posible procesar la solicitud.', result.ok ? 'success' : 'error');
            if (result.redirect) {
                window.setTimeout(() => { window.location.href = result.redirect; }, 1800);
            }
        } catch (error) {
            showNotice('No fue posible conectar con el servidor. Inténtalo de nuevo.', 'error');
        } finally {
            button.disabled = false;
        }
    }
    document.getElementById('verifyForm').addEventListener('submit', (event) => {
        event.preventDefault();
        sendAction(event.currentTarget);
    });
    document.getElementById('resendForm').addEventListener('submit', (event) => {
        event.preventDefault();
        sendAction(event.currentTarget);
    });
</script>
</body>
</html>
