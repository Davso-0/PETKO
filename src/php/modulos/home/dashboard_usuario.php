<?php
declare(strict_types=1);

ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Verificar si hay sesión activa
if (!isset($_SESSION['usuario']) && !isset($_GET['action'])) {
    header('Location: ../../../../inicioSesion.php');
    exit;
}

if (file_exists(__DIR__ . '/../../config/conexion_BD.php')) {
    require_once __DIR__ . '/../../config/conexion_BD.php';
} elseif (file_exists(__DIR__ . '/../../config/conexion.php')) {
    require_once __DIR__ . '/../../config/conexion.php';
}

$id_usuario_actual = 0;
if (is_array($_SESSION['usuario'] ?? null)) {
    $id_usuario_actual = (int)($_SESSION['usuario']['id_usuario'] ?? $_SESSION['usuario']['id'] ?? 0);
} else {
    $id_usuario_actual = (int)($_SESSION['usuario'] ?? 0);
}

$usuario_data = null;
if (isset($pdo) && $pdo instanceof PDO && $id_usuario_actual > 0) {
    $st = $pdo->prepare("SELECT u.nombre, u.apellido, u.correo, r.nombre AS rol FROM usuarios u LEFT JOIN roles r ON r.id_rol = u.id_rol WHERE u.id_usuario = ?");
    $st->execute([$id_usuario_actual]);
    $usuario_data = $st->fetch(PDO::FETCH_ASSOC);
}
$nombreCompleto = trim(($usuario_data['nombre'] ?? 'Usuario') . ' ' . ($usuario_data['apellido'] ?? ''));
$correoUsuario = $usuario_data['correo'] ?? '';

function resolverImagenDashboard(?string $imagenUrl, int $idProd, string $categoria = 'PETKO'): string {
    $rutasAntiguas = [
        1 => '../../../img/perron.jpg',
        2 => '../../../img/whiskas.jpg',
        3 => '../../../img/purina.jpg',
        4 => '../../../img/malta.jpg'
    ];
    $img = trim((string)$imagenUrl);
    if ($img !== '') {
        if (preg_match('/^(https?:\/\/|data:image\/)/i', $img)) {
            return $img;
        }
        if (strpos($img, 'src/img/') === 0) {
            return '../../../img/' . substr($img, 8);
        }
        if (strpos($img, '../../img/') === 0) {
            return '../../../img/' . substr($img, 10);
        }
        return $img;
    }
    $rutaFisica = __DIR__ . '/../../../img/' . $idProd . '.jpg';
    if (file_exists($rutaFisica)) {
        return '../../../img/' . $idProd . '.jpg';
    }
    if (isset($rutasAntiguas[$idProd])) {
        return $rutasAntiguas[$idProd];
    }
    return "https://placehold.co/200x200/E8F6EE/0B2545?text=" . urlencode($categoria ?: 'PETKO');
}

// --------------------------------------------------------------
// API (AJAX) — Acciones exclusivas del usuario
// --------------------------------------------------------------
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $out = ['ok' => false, 'msg' => 'Acción desconocida'];
    try {
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            throw new RuntimeException('[ERROR] No fue posible conectar con la base de datos.');
        }
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if ($id_usuario_actual <= 0) {
            throw new RuntimeException('[ERROR] Sesión inválida.');
        }

        if ($action === 'solicitar_cambio_password') {
            $bytes = random_bytes(32);
            $tokenPlano = bin2hex($bytes);
            $tokenHash = hash('sha256', $tokenPlano);
            $expiraEn = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $st = $pdo->prepare("UPDATE usuarios SET recovery_token = ?, recovery_expires_at = ? WHERE id_usuario = ?");
            $st->execute([$tokenHash, $expiraEn, $id_usuario_actual]);
            if ($st->rowCount() !== 1) {
                throw new RuntimeException('No fue posible generar el enlace de restablecimiento.');
            }
            $redirectUrl = '../../../../recuperar.php?token=' . $tokenPlano;
            $out = ['ok' => true, 'msg' => 'Redirigiendo a restablecimiento...', 'redirect' => $redirectUrl];

        } elseif ($action === 'get_apartados') {
            // 1. Marcar automáticamente como VENCIDO los apartados que superaron sus 48 horas
            $stExpire = $pdo->prepare("
                UPDATE apartados 
                SET estado = 'VENCIDO' 
                WHERE id_usuario = ? 
                  AND estado = 'PENDIENTE' 
                  AND TIMESTAMPDIFF(HOUR, fecha_reserva, NOW()) >= COALESCE(horas_vigencia, 48)
            ");
            $stExpire->execute([$id_usuario_actual]);

            // 2. Verificar si existen columnas cantidad y estado_pago para compatibilidad total
            $cols = array_column($pdo->query("SHOW COLUMNS FROM apartados")->fetchAll(PDO::FETCH_ASSOC), 'Field');
            $selCantidad = in_array('cantidad', $cols, true) ? 'a.cantidad' : '1 AS cantidad';
            $selEstadoPago = in_array('estado_pago', $cols, true) ? 'a.estado_pago' : "'PENDIENTE_PAGO' AS estado_pago";

            $sql = "SELECT a.id_apartado, a.folio, {$selCantidad}, 
                           p.id_producto, p.nombre AS producto, p.precio, p.imagen_url,
                           c.nombre AS categoria,
                           s.nombre AS sucursal, a.fecha_reserva, a.horas_vigencia, a.estado, {$selEstadoPago},
                           DATE_ADD(a.fecha_reserva, INTERVAL COALESCE(a.horas_vigencia, 48) HOUR) AS fecha_expiracion
                    FROM apartados a 
                    JOIN productos p ON p.id_producto = a.id_producto 
                    LEFT JOIN categorias c ON c.id_categoria = p.id_categoria
                    LEFT JOIN sucursales s ON s.id_sucursal = a.id_sucursal 
                    WHERE a.id_usuario = ? 
                    ORDER BY a.fecha_reserva DESC, a.id_apartado DESC";
            $st = $pdo->prepare($sql);
            $st->execute([$id_usuario_actual]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as &$row) {
                $row['imagen_resuelta'] = resolverImagenDashboard(
                    $row['imagen_url'] ?? null,
                    (int)($row['id_producto'] ?? 0),
                    (string)($row['categoria'] ?? 'PETKO')
                );
            }
            unset($row);

            $out = ['ok' => true, 'data' => $rows];

        } elseif ($action === 'update_password') {
            $passActual = (string)($_POST['password_actual'] ?? '');
            $passNueva = (string)($_POST['password_nueva'] ?? '');
            
            if ($passActual === '' || $passNueva === '') {
                throw new Exception('[ERROR] Debes llenar ambos campos de contraseña.');
            }
            if (strlen($passNueva) < 6) {
                throw new Exception('[ERROR] La nueva contraseña debe tener al menos 6 caracteres.');
            }

            $stHash = $pdo->prepare("SELECT password_hash FROM usuarios WHERE id_usuario = ?");
            $stHash->execute([$id_usuario_actual]);
            $hashBd = $stHash->fetchColumn();

            if (!$hashBd || !password_verify($passActual, $hashBd)) {
                throw new Exception('[ERROR] La contraseña actual es incorrecta.');
            }

            $nuevoHash = password_hash($passNueva, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE usuarios SET password_hash = ? WHERE id_usuario = ?")->execute([$nuevoHash, $id_usuario_actual]);
            $out = ['ok' => true, 'msg' => 'Tu contraseña ha sido actualizada con éxito.'];
        }
        
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'Base table or view not found') !== false && strpos($msg, 'apartados') !== false) {
            $out = ['ok' => true, 'data' => [], 'msg' => 'Aún no has apartado ningún producto.'];
        } else {
            $out = ['ok' => false, 'msg' => $msg];
        }
    }
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PETKO · Mi Cuenta</title>

    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --bg-app: oklch(96.5% 0.014 72); --bg-surface: oklch(100% 0 0); --bg-panel: oklch(99% 0.006 75);
            --ink: oklch(24% 0.028 50); --ink-soft: oklch(42% 0.030 48); --ink-faint: oklch(58% 0.022 50); --border-soft: oklch(89% 0.016 65);
            --coffee-main: oklch(32% 0.040 50); --coffee-light: oklch(91% 0.018 60);
            --coffee-gradient: linear-gradient(155deg, oklch(40% 0.045 48), oklch(26% 0.035 52));
            --success: oklch(34% 0.06 152); --success-bg: oklch(94% 0.035 152);
            --danger: oklch(50% 0.14 25); --danger-bg: oklch(95% 0.035 20);
            --warning: oklch(42% 0.09 70); --warning-bg: oklch(95% 0.045 90);
            --font-display: 'Fraunces', serif; --font-body: 'Plus Jakarta Sans', sans-serif;
            --shadow-sm: 0 4px 12px rgba(0,0,0,0.05); --shadow-md: 0 8px 24px rgba(0,0,0,0.08);
            --radius: 14px; --sidebar-w: 264px; --sidebar-w-collapsed: 84px;
            --ease-expo: cubic-bezier(0.16, 1, 0.3, 1);
        }
        body.dark-mode {
            --bg-app: oklch(20% 0.01 250); --bg-surface: oklch(25% 0.01 250); --bg-panel: oklch(23% 0.01 250);
            --ink: oklch(95% 0.01 250); --ink-soft: oklch(75% 0.01 250); --ink-faint: oklch(62% 0.01 250); --border-soft: oklch(35% 0.01 250);
            --coffee-main: oklch(80% 0.03 60); --coffee-light: oklch(30% 0.02 250);
            --coffee-gradient: linear-gradient(155deg, oklch(40% 0.045 48), oklch(20% 0.035 52));
            --success-bg: oklch(25% 0.04 152); --danger-bg: oklch(30% 0.06 20); --warning-bg: oklch(30% 0.05 85);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: var(--font-body); background: var(--bg-app); color: var(--ink); transition: background .3s ease, color .3s ease; }
        button { font: inherit; cursor: pointer; border: none; background: none; color: inherit; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        .view-section { display: none; animation: fadeIn .38s var(--ease-expo) forwards; }
        .view-section.active { display: block; }

        .app-layout { display: grid; grid-template-columns: var(--sidebar-w) 1fr; min-height: 100vh; transition: grid-template-columns .35s var(--ease-expo); }
        .app-layout.is-collapsed { grid-template-columns: var(--sidebar-w-collapsed) 1fr; }

        .sidebar { position: sticky; top: 0; height: 100vh; background: var(--bg-surface); border-right: 1px solid var(--border-soft); padding: 22px 16px; display: flex; flex-direction: column; gap: 26px; z-index: 40; transition: padding .35s var(--ease-expo), background .3s ease; }
        .brand { display: flex; align-items: center; gap: 12px; font-family: var(--font-display); font-size: 19px; font-weight: 600; padding: 0 8px; position: relative; }
        .brand i { font-size: 26px; color: var(--coffee-main); flex: none; }
        .brand-text { overflow: hidden; white-space: nowrap; transition: opacity .2s ease; }
        .brand-sub { font-size: 10.5px; letter-spacing: .6px; color: var(--ink-faint); font-weight: 700; text-transform: uppercase; margin-top: 1px; }
        .app-layout.is-collapsed .brand-text, .app-layout.is-collapsed .nav-label, .app-layout.is-collapsed .nav-section-title { opacity: 0; width: 0; pointer-events: none; }

        .sidebar-collapse-btn { position: absolute; top: 26px; right: -13px; width: 26px; height: 26px; border-radius: 50%; background: var(--bg-surface); border: 1px solid var(--border-soft); color: var(--coffee-main); display: grid; place-items: center; box-shadow: var(--shadow-sm); z-index: 41; transition: transform .3s var(--ease-expo); }
        .app-layout.is-collapsed .sidebar-collapse-btn { transform: rotate(180deg); }
        .sidebar-collapse-btn i { font-size: 14px; }

        .nav-section-title { font-size: 11px; text-transform: uppercase; letter-spacing: .8px; color: var(--ink-faint); font-weight: 700; padding: 4px 12px 6px; white-space: nowrap; }
        .nav-menu { display: flex; flex-direction: column; gap: 6px; }
        .nav-btn { display: flex; align-items: center; gap: 12px; padding: 11px 14px; border-radius: var(--radius); font-weight: 600; font-size: 14px; color: var(--ink-soft); transition: background .2s var(--ease-expo), color .2s ease, transform .2s var(--ease-expo); white-space: nowrap; }
        .nav-btn i { font-size: 19px; flex: none; }
        .nav-btn:hover { background: var(--bg-app); color: var(--ink); transform: translateX(2px); }
        .nav-btn.active { background: var(--coffee-gradient); color: #fff; box-shadow: var(--shadow-sm); }

        .sidebar-scrim { display: none; position: fixed; inset: 0; background: rgba(20,16,12,.45); z-index: 39; opacity: 0; transition: opacity .3s ease; }

        .topbar { height: 70px; display: flex; justify-content: space-between; align-items: center; padding: 0 32px; border-bottom: 1px solid var(--border-soft); background: var(--bg-panel); position: sticky; top: 0; z-index: 30; backdrop-filter: blur(10px); }
        .hamburger-btn { display: none; width: 38px; height: 38px; border-radius: 10px; border: 1px solid var(--border-soft); background: var(--bg-surface); align-items: center; justify-content: center; font-size: 18px; }
        .topbar-actions { display: flex; gap: 12px; align-items: center; }
        .icon-btn { width: 40px; height: 40px; border-radius: 50%; display: grid; place-items: center; background: var(--bg-app); border: 1px solid var(--border-soft); font-size: 19px; transition: background .2s ease, color .2s ease, transform .15s var(--ease-expo); }
        .icon-btn:hover { background: var(--coffee-light); color: var(--coffee-main); transform: translateY(-1px); }
        .user-profile { display: flex; align-items: center; gap: 10px; font-weight: 600; padding-left: 14px; border-left: 1px solid var(--border-soft); font-size: 14px; }
        .role-chip { font-size: 10px; font-weight: 800; letter-spacing: .5px; padding: 3px 8px; border-radius: 20px; background: var(--coffee-light); color: var(--coffee-main); text-transform: uppercase; }

        .content { padding: 32px; max-width: 1060px; margin: 0 auto; }
        .header-row { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; flex-wrap: wrap; gap: 14px; }
        .title { font-family: var(--font-display); font-size: 27px; font-weight: 600; }
        .subtitle { color: var(--ink-soft); font-size: 14px; margin-top: 4px; }

        .btn-primary { background: var(--coffee-gradient); color: #fff; padding: 12px 24px; border-radius: 10px; font-weight: 700; font-size: 14px; box-shadow: var(--shadow-sm); transition: transform .16s var(--ease-expo), box-shadow .2s ease; display: inline-flex; align-items: center; justify-content: center; width: 100%; max-width: 280px; margin-top: 10px;}
        .btn-primary:active { transform: scale(.96); }

        .panel { background: var(--bg-surface); border-radius: var(--radius); border: 1px solid var(--border-soft); padding: 24px; overflow-x: auto; margin-bottom: 22px; transition: background .3s ease, border-color .3s ease; }

        /* -----------------------------------------------------------------
           TARJETAS DE APARTADOS / COMPRAS ESTILO MERCADO LIBRE
           ----------------------------------------------------------------- */
        .compras-lista {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .compra-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-soft);
            border-radius: 16px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: transform .22s var(--ease-expo), box-shadow .22s var(--ease-expo), border-color .2s ease;
        }
        .compra-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: color-mix(in oklch, var(--coffee-main) 30%, var(--border-soft));
        }
        .compra-card__header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            padding: 13px 22px;
            background: var(--bg-panel);
            border-bottom: 1px solid var(--border-soft);
            font-size: 13px;
        }
        .compra-card__fecha {
            font-weight: 600;
            color: var(--ink);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .compra-card__folio-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .folio-tag {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 12px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 8px;
            background: var(--bg-app);
            border: 1px solid var(--border-soft);
            color: var(--coffee-main);
            letter-spacing: .3px;
        }
        .btn-copy-folio {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 12px;
            font-weight: 600;
            color: var(--ink-soft);
            padding: 4px 8px;
            border-radius: 6px;
            transition: background .15s ease, color .15s ease;
        }
        .btn-copy-folio:hover {
            background: var(--bg-app);
            color: var(--coffee-main);
        }
        .compra-card__body {
            display: grid;
            grid-template-columns: 88px minmax(0, 1fr) 230px;
            align-items: center;
            gap: 22px;
            padding: 20px 22px;
        }
        .compra-card__thumb {
            width: 88px;
            height: 88px;
            border-radius: 12px;
            border: 1px solid var(--border-soft);
            background: #fff;
            display: grid;
            place-items: center;
            padding: 6px;
            overflow: hidden;
        }
        .compra-card__thumb img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .compra-card__info {
            display: flex;
            flex-direction: column;
            gap: 7px;
            min-width: 0;
        }
        .compra-card__badges {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .badge.b-green { background: var(--success-bg); color: var(--success); }
        .badge.b-yellow { background: var(--warning-bg); color: var(--warning); }
        .badge.b-sky { background: rgba(59,130,246,0.12); color: #2563eb; }
        body.dark-mode .badge.b-sky { color: #60a5fa; }

        .compra-card__titulo {
            font-size: 16.5px;
            font-weight: 700;
            color: var(--ink);
            line-height: 1.3;
        }
        .compra-card__detalles {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            font-size: 13.5px;
            color: var(--ink-soft);
        }
        .compra-card__total {
            font-size: 16px;
            font-weight: 800;
            color: var(--ink);
        }
        .compra-card__vigencia {
            font-size: 12.5px;
            color: var(--ink-faint);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .compra-card__side {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            justify-content: space-between;
            gap: 12px;
            padding-left: 20px;
            border-left: 1px solid var(--border-soft);
            text-align: right;
        }
        .compra-card__sucursal-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: var(--ink-faint);
            font-weight: 700;
        }
        .compra-card__sucursal-val {
            font-size: 14px;
            font-weight: 700;
            color: var(--ink);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 2px;
        }
        .btn-card-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 9px 15px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            background: var(--coffee-light);
            color: var(--coffee-main);
            transition: background .2s ease, transform .15s var(--ease-expo);
            width: 100%;
        }
        .btn-card-action:hover {
            filter: brightness(0.96);
            transform: translateY(-1px);
        }

        .compras-empty {
            background: var(--bg-surface);
            border: 1px solid var(--border-soft);
            border-radius: 16px;
            padding: 54px 24px;
            text-align: center;
            color: var(--ink-soft);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }
        .compras-empty i {
            font-size: 44px;
            color: var(--coffee-main);
            opacity: .75;
        }

        .form-group { margin-bottom: 18px; max-width: 400px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: var(--ink-soft); margin-bottom: 6px; }
        .form-input { width: 100%; padding: 12px 16px; border: 1px solid var(--border-soft); border-radius: 8px; background: var(--bg-app); color: var(--ink); font-size: 15px; transition: border-color .2s; outline: none; }
        .form-input:focus { border-color: var(--coffee-main); }
        .form-input:disabled { opacity: 0.7; cursor: not-allowed; }

        @media (max-width: 980px) {
            .app-layout { grid-template-columns: 1fr; }
            .sidebar { position: fixed; left: 0; top: 0; width: 280px; height: 100vh; transform: translateX(-100%); transition: transform .35s var(--ease-expo); box-shadow: var(--shadow-md); }
            .app-layout.mobile-open .sidebar { transform: translateX(0); }
            .app-layout.mobile-open .sidebar-scrim { display: block; opacity: 1; }
            .sidebar-collapse-btn { display: none; }
            .hamburger-btn { display: inline-flex; }
        }
        @media (max-width: 720px) {
            .content { padding: 20px 16px; }
            .compra-card__body {
                grid-template-columns: 72px 1fr;
                gap: 14px;
                padding: 16px;
            }
            .compra-card__thumb {
                width: 72px;
                height: 72px;
            }
            .compra-card__side {
                grid-column: 1 / -1;
                flex-direction: row;
                align-items: center;
                border-left: none;
                border-top: 1px solid var(--border-soft);
                padding-left: 0;
                padding-top: 12px;
                text-align: left;
            }
            .btn-card-action {
                width: auto;
            }
        }
        /* -----------------------------------------------------------------
           MODAL DE TICKET DE APARTADO / COMPRA
           ----------------------------------------------------------------- */
        dialog.ticket-dialog {
            border: none;
            border-radius: 24px;
            padding: 0;
            width: min(430px, 92vw);
            max-height: 92vh;
            overflow-y: auto;
            background: var(--bg-surface);
            color: var(--ink);
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.25);
            margin: auto;
        }
        dialog.ticket-dialog::backdrop {
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(4px);
        }
        .tk-box {
            position: relative;
            padding: 28px 24px 24px;
        }
        .tk-close {
            position: absolute;
            top: 14px;
            right: 14px;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: var(--bg-app);
            border: 1px solid var(--border-soft);
            display: grid;
            place-items: center;
            font-size: 16px;
            color: var(--ink-soft);
        }
        .tk-close:hover { background: var(--coffee-light); color: var(--ink); }

        .tk-header {
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }
        .tk-check {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-size: 28px;
            color: #fff;
            background: #f59e0b;
            box-shadow: 0 0 0 8px rgba(245, 158, 11, 0.14);
            margin-bottom: 4px;
        }
        .tk-box[data-pagado="1"] .tk-check {
            background: #16a34a;
            box-shadow: 0 0 0 8px rgba(22, 163, 74, 0.14);
        }
        .tk-brand {
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 1.5px;
            color: var(--ink-soft);
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .tk-title {
            font-size: 23px;
            font-weight: 800;
            line-height: 1.2;
            color: var(--ink);
        }
        .tk-sub {
            font-size: 13.5px;
            color: var(--ink-soft);
            max-width: 300px;
        }
        .tk-meta {
            margin-top: 18px;
            background: var(--bg-app);
            border: 1px solid var(--border-soft);
            border-radius: 16px;
            padding: 14px 16px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px 12px;
            text-align: left;
        }
        .tk-meta dt {
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: var(--ink-faint);
            margin-bottom: 3px;
        }
        .tk-meta dd {
            font-size: 13px;
            font-weight: 700;
            color: var(--ink);
        }
        .tk-meta dd.tk-folio {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            color: #b45309;
        }
        body.dark-mode .tk-meta dd.tk-folio { color: #fbbf24; }
        .tk-status-pill {
            display: inline-block;
            padding: 4px 9px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 800;
            background: var(--warning-bg);
            color: var(--warning);
            line-height: 1.25;
        }
        .tk-box[data-pagado="1"] .tk-status-pill {
            background: var(--success-bg);
            color: var(--success);
        }

        /* Línea punteada de corte de ticket */
        .tk-divider {
            position: relative;
            margin: 20px -24px;
            border-top: 2px dashed var(--border-soft);
        }
        .tk-divider::before,
        .tk-divider::after {
            content: '';
            position: absolute;
            top: -11px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: rgba(15, 23, 42, 0.55);
        }
        .tk-divider::before { left: -10px; }
        .tk-divider::after { right: -10px; }

        .tk-items {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-soft);
            font-size: 14px;
        }
        .tk-items li {
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }
        .tk-total-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-top: 12px;
        }
        .tk-total-row span {
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .8px;
            color: var(--ink-faint);
            text-transform: uppercase;
        }
        .tk-total-row strong {
            font-size: 26px;
            font-weight: 800;
            color: var(--ink);
        }
        .tk-barcode-wrap {
            margin: 18px 0 14px;
            text-align: center;
        }
        .tk-barcode {
            height: 44px;
            width: 82%;
            margin: 0 auto 6px;
            background: repeating-linear-gradient(
                90deg,
                var(--ink) 0 2px, transparent 2px 5px,
                var(--ink) 5px 9px, transparent 9px 11px,
                var(--ink) 11px 12px, transparent 12px 16px,
                var(--ink) 16px 19px, transparent 19px 22px
            );
            opacity: .88;
        }
        .tk-barcode-text {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 11px;
            letter-spacing: 3.5px;
            color: var(--ink-faint);
        }
        .tk-mascot {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(59, 130, 246, 0.09);
            border-radius: 14px;
            padding: 10px 14px;
            margin-bottom: 16px;
            font-size: 13.5px;
            font-weight: 700;
            color: #1d4ed8;
        }
        body.dark-mode .tk-mascot { color: #93c5fd; }
        .tk-mascot img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            background: #fff;
        }
        .tk-done-btn {
            width: 100%;
            padding: 13px;
            border-radius: 999px;
            background: #2563eb;
            color: #fff;
            font-weight: 700;
            font-size: 14.5px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.28);
        }
        .tk-done-btn:hover { background: #1d4ed8; }
    </style>
    <link rel="stylesheet" href="../../../css/petko-ui.css?v=<?= (int) @filemtime(__DIR__ . '/../../../css/petko-ui.css') ?>">
</head>
<body>
<script src="../../../js/petko-prefs.js"></script>

<div class="app-layout" id="appLayout">
    <aside class="sidebar">
        <button class="sidebar-collapse-btn" id="btnCollapse"><i class="ph ph-caret-left"></i></button>
        <a class="brand" href="home.php" aria-label="PETKO · Inicio"><img src="../../../img/petko/logo_amarillo.png" alt="PETKO Veterinaria y Ganadería" width="520" height="161"></a>
        <nav class="nav-menu">
            <div class="nav-section-title">Mi Actividad</div>
            <button class="nav-btn active" data-target="view-apartados"><i class="ph ph-shopping-bag"></i><span class="nav-label">Mis Apartados</span></button>
            <div class="nav-section-title">Ajustes</div>
            <button class="nav-btn" data-target="view-config"><i class="ph ph-gear"></i><span class="nav-label">Configuración</span></button>
            <div class="nav-section-title">Navegación</div>
            <a class="nav-btn" href="../../componentes/catalogo.php"><i class="ph ph-storefront"></i><span class="nav-label">Ir al Catálogo</span></a>
            <a class="nav-btn" href="home.php"><i class="ph ph-house"></i><span class="nav-label">Volver a Home</span></a>
            <a class="nav-btn" href="cerrarSesion.php" style="color:var(--danger)"><i class="ph ph-sign-out"></i><span class="nav-label">Cerrar sesión</span></a>
        </nav>
    </aside>
    <div class="sidebar-scrim" id="sidebarScrim"></div>

    <main>
        <header class="topbar">
            <div style="display:flex; align-items:center; gap:14px;">
                <button class="hamburger-btn" id="btnMobileMenu"><i class="ph ph-list"></i></button>
                <b id="topbarTitle" data-i18n="topbar_apartados" style="font-size: 16px;">Mis Apartados</b>
            </div>
            <div class="topbar-actions">
                <button class="icon-btn" id="btnTheme" data-petko-theme type="button">🌙</button>
                <button class="icon-btn" id="btnLang" data-petko-lang type="button">EN</button>
                <div class="user-profile">
                    <i class="ph-fill ph-user-circle" style="font-size:24px; color:var(--coffee-main)"></i>
                    <span><?= htmlspecialchars($nombreCompleto) ?></span>
                    <span class="role-chip"><?= htmlspecialchars($usuario_data['rol'] ?? 'Cliente') ?></span>
                </div>
            </div>
        </header>

        <div class="content">

            <!-- VISTA: APARTADOS (ESTILO MERCADO LIBRE) -->
            <section id="view-apartados" class="view-section active">
                <div class="header-row">
                    <div>
                        <h1 class="title" data-i18n="apartados_title">Mis Productos Apartados</h1>
                        <p class="subtitle" data-i18n="apartados_subtitle">Consulta los productos reservados por 48 horas, su estado de pago y sucursal de recolección.</p>
                    </div>
                </div>

                <div id="apartadosList" class="compras-lista">
                    <div class="compras-empty">
                        <i class="ph ph-spinner"></i>
                        <p data-i18n="loading">Cargando información...</p>
                    </div>
                </div>
            </section>

            <!-- VISTA: CONFIGURACIÓN -->
            <section id="view-config" class="view-section">
                <div class="header-row">
                    <div>
                        <h1 class="title" data-i18n="config_title">Configuración de Cuenta</h1>
                        <p class="subtitle" data-i18n="config_subtitle">Gestiona tu información personal y seguridad.</p>
                    </div>
                </div>
                <div class="panel" style="display: flex; gap: 40px; flex-wrap: wrap;">
                    
                    <div style="flex: 1; min-width: 300px;">
                        <h3 style="margin-bottom: 20px; font-size: 16px;" data-i18n="contact_title">Datos de Contacto</h3>
                        <div class="form-group">
                            <label data-i18n="name_label">Nombre Completo</label>
                            <input type="text" class="form-input" value="<?= htmlspecialchars($nombreCompleto) ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label data-i18n="email_label">Correo Electrónico</label>
                            <input type="text" class="form-input" value="<?= htmlspecialchars($correoUsuario) ?>" disabled>
                        </div>
                        <p style="font-size: 12px; color: var(--ink-faint);" data-i18n="contact_help">* Para cambiar tu correo o nombre, contacta a una sucursal.</p>
                    </div>

                    <div style="flex:  1; min-width: 300px;">
                        <h3 style="margin-bottom: 20px; font-size: 16px;" data-i18n="password_title">Cambiar Contraseña</h3>
                        <div class="form-group">
                            <label data-i18n="pass_actual_label">Contraseña de la cuenta</label>
                            <input type="password" class="form-input" value="••••••••••••" disabled>
                        </div>
                        <div style="margin-top: 20px; padding: 16px; background: var(--bg-app); border: 1px solid var(--border-soft); border-radius: 10px;">
                            <p style="font-size: 13.5px; color: var(--ink-soft); margin-bottom: 10px;" data-i18n="pass_help_text">¿Deseas cambiar o actualizar tu contraseña?</p>
                            <button type="button" id="btnSolicitarCambioPass" class="btn-primary" style="max-width: 100%; width: auto; margin: 0;">
                                <i class="ph ph-key" style="margin-right: 6px;"></i> <span data-i18n="btn_solicitar_pass">Cambiar Contraseña</span>
                            </button>
                        </div>
                    </div>

                </div>
            </section>

        </div>
    </main>
</div>
<!-- MODAL TICKET DE APARTADO / COMPRA -->
<dialog id="modal-ticket-user" class="ticket-dialog" aria-labelledby="tk-title">
    <div class="tk-box" id="tk-box" data-pagado="0">
        <button type="button" class="tk-close" id="tk-close" aria-label="Cerrar">
            <i class="ph-bold ph-x"></i>
        </button>

        <header class="tk-header">
            <div class="tk-check"><i class="ph-bold ph-check" id="tk-icon"></i></div>
            <div class="tk-brand"><i class="ph-fill ph-paw-print"></i> PETKO</div>
            <h2 class="tk-title" id="tk-title"></h2>
            <p class="tk-sub" id="tk-sub"></p>
        </header>

        <dl class="tk-meta">
            <div>
                <dt>FOLIO</dt>
                <dd class="tk-folio" id="tk-folio"></dd>
            </div>
            <div>
                <dt>FECHA</dt>
                <dd id="tk-fecha"></dd>
            </div>
            <div>
                <dt>MÉTODO</dt>
                <dd id="tk-metodo"></dd>
            </div>
            <div>
                <dt>ESTADO</dt>
                <dd><span class="tk-status-pill" id="tk-estado"></span></dd>
            </div>
        </dl>

        <div class="tk-divider" aria-hidden="true"></div>

        <ul class="tk-items" id="tk-items"></ul>

        <div class="tk-total-row">
            <span>TOTAL</span>
            <strong id="tk-total">$0.00</strong>
        </div>

        <div class="tk-barcode-wrap" aria-hidden="true">
            <div class="tk-barcode"></div>
            <div class="tk-barcode-text" id="tk-barcode-text"></div>
        </div>

        <div class="tk-mascot">
            <img src="../../../img/perrito_feliz.png" alt="" onerror="this.style.display='none'">
            <p id="tk-thanks">¡Guau! Gracias por tu pedido 🐾</p>
        </div>

        <button type="button" class="tk-done-btn" id="tk-done">
            <i class="ph-fill ph-paw-print"></i>
            <span id="tk-btn-lbl">Cerrar ticket</span>
        </button>
    </div>
</dialog>
<script>
const endpoint = 'dashboard_usuario.php';
const $ = s => document.querySelector(s);
const escapeHtml = v => String(v ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
const money = v => '$' + Number(v || 0).toFixed(2);
const notify = (msg, icon='success') => Swal.fire({ toast:true, position:'top-end', icon, title: msg, showConfirmButton:false, timer:2600 });

const I18N = {
    es: {
        brand_sub: 'Panel de Cliente', nav_section_activity: 'Mi Actividad', nav_section_settings: 'Ajustes',
        nav_section_navigation: 'Navegación', nav_apartados: 'Mis Apartados', nav_config: 'Configuración',
        nav_catalog: 'Ir al Catálogo', nav_logout: 'Cerrar sesión', topbar_apartados: 'Mis Apartados',
        topbar_config: 'Configuración', theme_title: 'Modo Oscuro', role_chip: 'Cliente',
        apartados_title: 'Mis Productos Apartados', apartados_subtitle: 'Consulta los productos reservados por 48 horas, su estado de pago y sucursal de recolección.',
        loading: 'Cargando información...',
        config_title: 'Configuración de Cuenta', config_subtitle: 'Gestiona tu información personal y seguridad.',
        contact_title: 'Datos de Contacto', name_label: 'Nombre Completo', email_label: 'Correo Electrónico',
        contact_help: '* Para cambiar tu correo o nombre, contacta a una sucursal.',
        password_title: 'Cambiar Contraseña', pass_actual_label: 'Contraseña de la cuenta',
        pass_help_text: '¿Deseas cambiar o actualizar tu contraseña?', btn_solicitar_pass: 'Cambiar Contraseña',
        empty_apartados: 'Aún no tienes productos apartados o comprados.',
        missing_apartados: 'Aún no has apartado ningún producto.', error_response: 'Error al procesar la respuesta del servidor.',
        error_request: 'No fue posible completar la operación.', redirecting_password: 'Redirigiendo a restablecimiento...',
        entregado: 'ENTREGADO', vencido: 'VENCIDO', cancelado: 'CANCELADO', pendiente: 'APARTADO (48H)',
        pagado: 'YA PAGADO', pendiente_pago: 'EN ESPERA DE PAGO',
        vence_lbl: 'Vence el', pickup_lbl: 'Recoger en sucursal', unit_singular: 'unidad', unit_plural: 'unidades',
        view_ticket: 'Ver ticket', copy_folio: 'Copiar', folio_copied: 'Folio copiado'
    },
    en: {
        brand_sub: 'Customer Dashboard', nav_section_activity: 'My Activity', nav_section_settings: 'Settings',
        nav_section_navigation: 'Navigation', nav_apartados: 'My Reservations', nav_config: 'Settings',
        nav_catalog: 'Go to Catalog', nav_logout: 'Log out', topbar_apartados: 'My Reservations',
        topbar_config: 'Settings', theme_title: 'Dark Mode', role_chip: 'Customer',
        apartados_title: 'My Reserved Products', apartados_subtitle: 'Review products reserved for 48 hours, payment status, and pickup branch.',
        loading: 'Loading information...',
        config_title: 'Account Settings', config_subtitle: 'Manage your personal information and security.',
        contact_title: 'Contact Information', name_label: 'Full Name', email_label: 'Email Address',
        contact_help: '* To change your email or name, contact a branch.',
        password_title: 'Change Password', pass_actual_label: 'Account password',
        pass_help_text: 'Would you like to change or update your password?', btn_solicitar_pass: 'Change Password',
        empty_apartados: 'You do not have any reserved or purchased products yet.',
        missing_apartados: 'You have not reserved any products yet.', error_response: 'Error processing the server response.',
        error_request: 'Unable to complete the operation.', redirecting_password: 'Redirecting to password reset...',
        entregado: 'DELIVERED', vencido: 'EXPIRED', cancelado: 'CANCELED', pendiente: 'RESERVED (48H)',
        pagado: 'PAID', pendiente_pago: 'PENDING PAYMENT',
        vence_lbl: 'Expires on', pickup_lbl: 'Pickup branch', unit_singular: 'unit', unit_plural: 'units',
        view_ticket: 'View ticket', copy_folio: 'Copy', folio_copied: 'Folio copied'
    }
};
let currentLang = PetkoPrefs.lang();
function t(key) {
    return (I18N[currentLang] && I18N[currentLang][key]) || I18N.es[key] || key;
}
function applyLanguage() {
    document.documentElement.lang = currentLang;
    document.querySelectorAll('[data-i18n]').forEach(el => { el.textContent = t(el.dataset.i18n); });
    document.querySelectorAll('[data-i18n-title]').forEach(el => { el.title = t(el.dataset.i18nTitle); });
}

async function request(actionStr, options = {}) {
    const r = await fetch(`${endpoint}?action=${actionStr}`, options);
    const text = await r.text();
    let data;
    try { data = JSON.parse(text); } catch(e) { throw new Error(t('error_response')); }
    if (!data.ok) throw new Error(data.msg || t('error_request'));
    return data;
}

PetkoPrefs.onLang(function (l) {
    currentLang = l;
    applyLanguage();
    const activeView = document.querySelector('.view-section.active');
    if (activeView) switchView(activeView.id);
});

const appLayout = $('#appLayout');
$('#btnCollapse').addEventListener('click', () => appLayout.classList.toggle('is-collapsed'));
$('#btnMobileMenu').addEventListener('click', () => appLayout.classList.add('mobile-open'));
$('#sidebarScrim').addEventListener('click', () => appLayout.classList.remove('mobile-open'));

const navButtons = document.querySelectorAll('.nav-btn[data-target]');
const views = document.querySelectorAll('.view-section');

function switchView(targetId) {
    const targetView = document.getElementById(targetId);
    if (!targetView) return;
    views.forEach(v => v.classList.remove('active'));
    navButtons.forEach(b => b.classList.remove('active'));
    targetView.classList.add('active');
    const btn = document.querySelector(`.nav-btn[data-target="${targetId}"]`);
    if (btn) btn.classList.add('active');
    appLayout.classList.remove('mobile-open');
    $('#topbarTitle').textContent = t(targetId === 'view-config' ? 'topbar_config' : 'topbar_apartados');

    if (targetId === 'view-apartados') loadApartados();
}
navButtons.forEach(btn => btn.addEventListener('click', e => switchView(e.currentTarget.dataset.target)));

applyLanguage();

function copiarFolio(folio) {
    if (!folio) return;
    navigator.clipboard?.writeText(folio).then(() => {
        notify(t('folio_copied'));
    });
}
// -------- APARTADOS (TARJETAS VISUALES ESTILO MERCADO LIBRE) --------
let apartadosCache = [];

async function loadApartados() {
    const container = $('#apartadosList');
    try {
        const { data, msg } = await request('get_apartados');
        if (msg) {
            container.innerHTML = `<div class="compras-empty">
                <i class="ph ph-shopping-bag"></i>
                <p>${escapeHtml(t('missing_apartados'))}</p>
                <a href="../../componentes/catalogo.php" class="btn-card-action" style="width:auto; margin-top:6px;">${escapeHtml(t('nav_catalog'))}</a>
            </div>`;
            return;
        }   

        if (!data.length) {
            container.innerHTML = `<div class="compras-empty">
                <i class="ph ph-shopping-bag"></i>
                <p>${escapeHtml(t('empty_apartados'))}</p>
                <a href="../../componentes/catalogo.php" class="btn-card-action" style="width:auto; margin-top:6px;">${escapeHtml(t('nav_catalog'))}</a>
            </div>`;
            return;
        }

        apartadosCache = data;
        const locale = currentLang === 'es' ? 'es-MX' : 'en-US';
        container.innerHTML = data.map((x, idx) => {
            let badgeClass = 'b-sky';
            let estadoIcon = 'ph-hourglass-medium';
            if (x.estado === 'ENTREGADO') { badgeClass = 'b-green'; estadoIcon = 'ph-package'; }
            if (x.estado === 'VENCIDO' || x.estado === 'CANCELADO') { badgeClass = 'b-yellow'; estadoIcon = 'ph-warning-circle'; }

            const estadoKey = x.estado === 'ENTREGADO' ? 'entregado'
                : x.estado === 'VENCIDO' ? 'vencido'
                : x.estado === 'CANCELADO' ? 'cancelado'
                : 'pendiente';
            const estadoTexto = t(estadoKey);

            const yaPagado = x.estado_pago === 'PAGADO';
            const pagoBadge = yaPagado
                ? `<span class="badge b-green"><i class="ph-bold ph-check-circle"></i> ${escapeHtml(t('pagado'))}</span>`
                : `<span class="badge b-yellow"><i class="ph-bold ph-clock"></i> ${escapeHtml(t('pendiente_pago'))}</span>`;

            const cant = Number(x.cantidad || 1);
            const precioUnit = Number(x.precio || 0);
            const totalFila = precioUnit * cant;
            const unidadTxt = cant === 1 ? t('unit_singular') : t('unit_plural');

            const fechaReserva = x.fecha_reserva
                ? new Date(x.fecha_reserva.replace(' ', 'T')).toLocaleDateString(locale, { day: 'numeric', month: 'long', year: 'numeric' })
                : '-';
            const horaReserva = x.fecha_reserva
                ? new Date(x.fecha_reserva.replace(' ', 'T')).toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' })
                : '';
            const expira = x.fecha_expiracion
                ? new Date(x.fecha_expiracion.replace(' ', 'T')).toLocaleString(locale, { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
                : '';

            const folioStr = x.folio || ('#' + x.id_apartado);
            const imgSrc = x.imagen_resuelta || `https://placehold.co/200x200/E8F6EE/0B2545?text=PETKO`;

            return `<article class="compra-card">
                <header class="compra-card__header">
                    <div class="compra-card__fecha">
                        <i class="ph ph-calendar-blank" style="color:var(--coffee-main); font-size:16px;"></i>
                        <span>${escapeHtml(fechaReserva)}</span>
                        ${horaReserva ? `<span style="color:var(--ink-faint); font-weight:500;">· ${escapeHtml(horaReserva)}</span>` : ''}
                    </div>
                    <div class="compra-card__folio-wrap">
                        <span class="folio-tag">${escapeHtml(folioStr)}</span>
                        <button type="button" class="btn-copy-folio" onclick="copiarFolio('${escapeHtml(folioStr)}')" title="${escapeHtml(t('copy_folio'))}">
                            <i class="ph ph-copy"></i>
                        </button>
                    </div>
                </header>

                <div class="compra-card__body">
                    <div class="compra-card__thumb">
                        <img src="${escapeHtml(imgSrc)}" alt="${escapeHtml(x.producto)}" loading="lazy"
                             onerror="this.onerror=null;this.src='https://placehold.co/200x200/E8F6EE/0B2545?text=PETKO';">
                    </div>

                    <div class="compra-card__info">
                        <div class="compra-card__badges">
                            ${pagoBadge}
                            <span class="badge ${badgeClass}"><i class="ph-bold ${estadoIcon}"></i> ${escapeHtml(estadoTexto)}</span>
                        </div>
                        <h3 class="compra-card__titulo">${escapeHtml(x.producto)}</h3>
                        <div class="compra-card__detalles">
                            <span class="compra-card__total">${escapeHtml(money(totalFila))}</span>
                            <span>·</span>
                            <span>${cant} ${escapeHtml(unidadTxt)} ${cant > 1 ? `(${escapeHtml(money(precioUnit))} c/u)` : ''}</span>
                        </div>
                        ${expira ? `<div class="compra-card__vigencia">
                            <i class="ph ph-timer"></i>
                            <span>${escapeHtml(t('vence_lbl'))} <b>${escapeHtml(expira)}</b></span>
                        </div>` : ''}
                    </div>

                    <div class="compra-card__side">
                        <div>
                            <div class="compra-card__sucursal-label">${escapeHtml(t('pickup_lbl'))}</div>
                            <div class="compra-card__sucursal-val">
                                <i class="ph-fill ph-storefront" style="color:var(--coffee-main);"></i>
                                <span>${escapeHtml(x.sucursal || 'Sucursal Principal')}</span>
                            </div>
                        </div>
                        <button type="button" class="btn-card-action" onclick="abrirTicket(${idx})">
                            <i class="ph ph-receipt"></i>
                            <span>${escapeHtml(t('view_ticket'))}</span>
                        </button>
                    </div>
                </div>
            </article>`;
        }).join('');
    } catch (e) {
        container.innerHTML = `<div class="compras-empty" style="color:var(--danger);">
            <i class="ph ph-warning-circle" style="color:var(--danger);"></i>
            <p>${escapeHtml(e.message)}</p>
        </div>`;
    }
}

const modalTicketUser = $('#modal-ticket-user');
$('#tk-close')?.addEventListener('click', () => modalTicketUser.close());
$('#tk-done')?.addEventListener('click', () => modalTicketUser.close());
modalTicketUser?.addEventListener('click', e => {
    if (e.target === modalTicketUser) modalTicketUser.close();
});

function abrirTicket(idx) {
    const item = apartadosCache[idx];
    if (!item || !modalTicketUser) return;

    // Agrupar todos los productos que pertenecen al mismo folio
    const grupo = item.folio
        ? apartadosCache.filter(x => x.folio === item.folio)
        : [item];

    const esPagado = item.estado_pago === 'PAGADO';
    const estaVencido = item.estado === 'VENCIDO' || item.estado === 'CANCELADO';
    const folioStr = item.folio || ('PTK-' + item.id_apartado);

    $('#tk-box').dataset.pagado = esPagado ? '1' : '0';

    if (currentLang === 'en') {
        $('#tk-title').textContent = esPagado ? 'Purchase & 48h reservation confirmed!' : '48h reservation confirmed!';
        $('#tk-sub').textContent = esPagado
            ? `Paid order reserved for 48 hours at ${item.sucursal || 'branch'}.`
            : `Product(s) reserved for 48 hours to pay at ${item.sucursal || 'branch'}.`;
        $('#tk-metodo').textContent = esPagado ? 'Online payment' : 'Pay at branch (Cash)';
        $('#tk-estado').textContent = estaVencido ? t(item.estado.toLowerCase()) : (esPagado ? 'PAID · RESERVED (48H)' : 'PENDING PAYMENT (48H)');
        $('#tk-thanks').textContent = 'Woof! Thank you for your order 🐾';
        $('#tk-btn-lbl').textContent = 'Close ticket';
    } else {
        $('#tk-title').textContent = esPagado ? '¡Compra y apartado confirmados!' : '¡Apartado confirmado por 48 h!';
        $('#tk-sub').textContent = esPagado
            ? `Pedido pagado y apartado por 48 horas en sucursal ${item.sucursal || ''}.`
            : `Producto(s) apartados por 48 horas para pago en sucursal ${item.sucursal || ''}.`;
        $('#tk-metodo').textContent = esPagado ? 'Pago en línea' : 'Pago en sucursal (Efectivo)';
        $('#tk-estado').textContent = estaVencido ? t(item.estado.toLowerCase()) : (esPagado ? 'PAGADO · APARTADO (48H)' : 'PENDIENTE DE PAGO (48H)');
        $('#tk-thanks').textContent = '¡Guau! Gracias por tu pedido 🐾';
        $('#tk-btn-lbl').textContent = 'Cerrar ticket';
    }

    const fechaFmt = item.fecha_reserva
        ? new Date(item.fecha_reserva.replace(' ', 'T')).toLocaleString(currentLang === 'es' ? 'es-MX' : 'en-US', {
            day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false
        })
        : '-';

    $('#tk-folio').textContent = folioStr;
    $('#tk-fecha').textContent = fechaFmt;

    let totalTicket = 0;
    $('#tk-items').innerHTML = grupo.map(g => {
        const cant = Number(g.cantidad || 1);
        const sub = Number(g.precio || 0) * cant;
        totalTicket += sub;
        return `<li>
            <span>${cant}x ${escapeHtml(g.producto)}</span>
            <strong>${escapeHtml(money(sub))}</strong>
        </li>`;
    }).join('');

    $('#tk-total').textContent = money(totalTicket);
    $('#tk-barcode-text').textContent = folioStr.split('').join(' ');

    modalTicketUser.showModal();
}

$('#btnSolicitarCambioPass').addEventListener('click', async () => {
    try {
        const data = await request('solicitar_cambio_password');
        notify(t('redirecting_password'));
        window.location.href = data.redirect;
    } catch (err) {
        notify(err.message, 'error');
    }
});

// Inicializar
document.addEventListener('DOMContentLoaded', () => {
    loadApartados();
});
</script>
</body>
</html>