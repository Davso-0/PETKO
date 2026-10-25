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
if (isset($pdo) && $pdo instanceof PDO &&$id_usuario_actual > 0) {
   $st = $pdo->prepare("SELECT u.nombre, u.apellido, u.correo, r.nombre AS rol FROM usuarios u LEFT JOIN roles r ON r.id_rol = u.id_rol WHERE u.id_usuario = ?");
    $st->execute([$id_usuario_actual]);
    $usuario_data =$st->fetch(PDO::FETCH_ASSOC);
}
$nombreCompleto = trim(($usuario_data['nombre'] ?? 'Usuario') . ' ' . ($usuario_data['apellido'] ?? ''));
$correoUsuario =$usuario_data['correo'] ?? '';

// --------------------------------------------------------------
// API (AJAX) — Acciones exclusivas del usuario
// --------------------------------------------------------------
if (isset($_GET['action'])) {$action = $_GET['action'];$out = ['ok' => false, 'msg' => 'Acción desconocida'];
    try {
        if (!isset($pdo) || !($pdo instanceof PDO)) {             throw new RuntimeException('[ERROR] No fue posible conectar con la base de datos.');         }$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
            // Asumimos una tabla 'apartados' que relaciona al usuario, el producto y la sucursal.
            $sql = "SELECT a.id_apartado, p.nombre AS producto, p.precio, s.nombre AS sucursal, a.fecha_reserva, a.estado 
                    FROM apartados a 
                    JOIN productos p ON p.id_producto = a.id_producto 
                    JOIN sucursales s ON s.id_sucursal = a.id_sucursal 
                    WHERE a.id_usuario = ? 
                    ORDER BY a.fecha_reserva DESC";
            $st = $pdo->prepare($sql);
            $st->execute([$id_usuario_actual]);
            $out = ['ok' => true, 'data' =>$st->fetchAll(PDO::FETCH_ASSOC)];

        } elseif ($action === 'update_password') {
            $passActual = (string)($_POST['password_actual'] ?? '');
            $passNueva = (string)($_POST['password_nueva'] ?? '');
            
            if ($passActual === '' || $passNueva === '') {
                throw new Exception('[ERROR] Debes llenar ambos campos de contraseña.');
            }
            if (strlen($passNueva) < 6) {
                throw new Exception('[ERROR] La nueva contraseña debe tener al menos 6 caracteres.');
            }

            $stHash =$pdo->prepare("SELECT password_hash FROM usuarios WHERE id_usuario = ?");
            $stHash->execute([$id_usuario_actual]);
            $hashBd =$stHash->fetchColumn();

            if (!$hashBd || !password_verify($passActual,$hashBd)) {
                throw new Exception('[ERROR] La contraseña actual es incorrecta.');
            }

            $nuevoHash = password_hash($passNueva, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE usuarios SET password_hash = ? WHERE id_usuario = ?")->execute([$nuevoHash, $id_usuario_actual]);$out = ['ok' => true, 'msg' => 'Tu contraseña ha sido actualizada con éxito.'];
        }
        
    } catch (Throwable $e) {
        $msg =$e->getMessage();
        // Manejo amigable si la tabla 'apartados' aún no existe
        if (strpos($msg, 'Base table or view not found') !== false && strpos($msg, 'apartados') !== false) {$out = ['ok' => true, 'data' => [], 'msg' => 'Aún no has apartado ningún producto.'];
        } else {
            $out = ['ok' => false, 'msg' =>$msg];
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
    <title>KION · Mi Cuenta</title>

    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
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

        .content { padding: 32px; max-width: 1100px; margin: 0 auto; }
        .header-row { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; flex-wrap: wrap; gap: 14px; }
        .title { font-family: var(--font-display); font-size: 27px; font-weight: 600; }
        .subtitle { color: var(--ink-soft); font-size: 14px; margin-top: 4px; }

        .btn-primary { background: var(--coffee-gradient); color: #fff; padding: 12px 24px; border-radius: 10px; font-weight: 700; font-size: 14px; box-shadow: var(--shadow-sm); transition: transform .16s var(--ease-expo), box-shadow .2s ease; display: inline-flex; align-items: center; justify-content: center; width: 100%; max-width: 280px; margin-top: 10px;}
        .btn-primary:active { transform: scale(.96); }

        .panel { background: var(--bg-surface); border-radius: var(--radius); border: 1px solid var(--border-soft); padding: 24px; overflow-x: auto; margin-bottom: 22px; transition: background .3s ease, border-color .3s ease; }
        
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { font-size: 11.5px; text-transform: uppercase; letter-spacing: .4px; color: var(--ink-faint); padding-bottom: 14px; border-bottom: 1px solid var(--border-soft); font-weight: 700; }
        td { padding: 14px 0; border-bottom: 1px solid var(--border-soft); font-weight: 500; vertical-align: middle; }
        tbody tr:hover { background: var(--bg-app); }
        tr:last-child td { border: none; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11.5px; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; }
        .badge.b-green { background: var(--success-bg); color: var(--success); }
        .badge.b-yellow { background: var(--warning-bg); color: var(--warning); }
        .badge.b-sky { background: rgba(59,130,246,0.1); color: #3b82f6; }

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
    </style>
</head>
<body>

<div class="app-layout" id="appLayout">
    <aside class="sidebar">
        <button class="sidebar-collapse-btn" id="btnCollapse"><i class="ph ph-caret-left"></i></button>
        <div class="brand">
            <i class="ph-fill ph-paw-print"></i>
            <div class="brand-text"><div>KION</div><div class="brand-sub" data-i18n="brand_sub">Panel de Cliente</div></div>
        </div>
        <nav class="nav-menu">
            <div class="nav-section-title" data-i18n="nav_section_activity">Mi Actividad</div>
            <button class="nav-btn active" data-target="view-apartados"><i class="ph ph-shopping-bag"></i><span class="nav-label" data-i18n="nav_apartados">Mis Apartados</span></button>
            <div class="nav-section-title" data-i18n="nav_section_settings">Ajustes</div>
            <button class="nav-btn" data-target="view-config"><i class="ph ph-gear"></i><span class="nav-label" data-i18n="nav_config">Configuración</span></button>
            <div class="nav-section-title" data-i18n="nav_section_navigation">Navegación</div>
            <a class="nav-btn" href="../../componentes/catalogo.php"><i class="ph ph-storefront"></i><span class="nav-label" data-i18n="nav_catalog">Ir al Catálogo</span></a>
            <a class="nav-btn" href="../../../../cerrarSesion.php" style="color:var(--danger)"><i class="ph ph-sign-out"></i><span class="nav-label" data-i18n="nav_logout">Cerrar sesión</span></a>
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
                <button class="icon-btn" id="btnTheme" title="Modo Oscuro" data-i18n-title="theme_title"><i class="ph ph-moon"></i></button>
                <button class="icon-btn" id="btnLang" title="Switch language / Cambiar idioma"><i class="ph ph-translate"></i></button>
                <div class="user-profile">
                    <i class="ph-fill ph-user-circle" style="font-size:24px; color:var(--coffee-main)"></i>
                    <span><?= htmlspecialchars($nombreCompleto) ?></span>
                    <span class="role-chip" data-i18n="role_chip">Cliente</span>
                </div>
            </div>
        </header>

        <div class="content">

            <!-- VISTA: APARTADOS -->
            <section id="view-apartados" class="view-section active">
                <div class="header-row">
                    <div>
                        <h1 class="title" data-i18n="apartados_title">Mis Productos Apartados</h1>
                        <p class="subtitle" data-i18n="apartados_subtitle">Consulta los productos que has reservado y su sucursal de recolección.</p>
                    </div>
                </div>
                <div class="panel">
                    <table>
                        <thead>
                            <tr>
                                <th data-i18n="th_producto">Producto</th>
                                <th data-i18n="th_sucursal">Sucursal de Recolección</th>
                                <th data-i18n="th_precio">Precio</th>
                                <th data-i18n="th_fecha">Fecha de Reserva</th>
                                <th data-i18n="th_estado">Estado</th>
                            </tr>
                        </thead>
                        <tbody id="apartadosTable">
                            <tr><td colspan="5" style="text-align:center; padding: 40px; color:var(--ink-faint);" data-i18n="loading">Cargando información...</td></tr>
                        </tbody>
                    </table>
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
        apartados_title: 'Mis Productos Apartados', apartados_subtitle: 'Consulta los productos que has reservado y su sucursal de recolección.',
        th_producto: 'Producto', th_sucursal: 'Sucursal de Recolección', th_precio: 'Precio',
        th_fecha: 'Fecha de Reserva', th_estado: 'Estado', loading: 'Cargando información...',
        config_title: 'Configuración de Cuenta', config_subtitle: 'Gestiona tu información personal y seguridad.',
        contact_title: 'Datos de Contacto', name_label: 'Nombre Completo', email_label: 'Correo Electrónico',
        contact_help: '* Para cambiar tu correo o nombre, contacta a una sucursal.',
        password_title: 'Cambiar Contraseña', pass_actual_label: 'Contraseña de la cuenta',
        pass_help_text: '¿Deseas cambiar o actualizar tu contraseña?', btn_solicitar_pass: 'Cambiar Contraseña',
        empty_apartados: 'No tienes ningún producto apartado en este momento.',
        missing_apartados: 'Aún no has apartado ningún producto.', error_response: 'Error al procesar la respuesta del servidor.',
        error_request: 'No fue posible completar la operación.', redirecting_password: 'Redirigiendo a restablecimiento...',
        entregado: 'ENTREGADO', vencido: 'VENCIDO', cancelado: 'CANCELADO', pendiente: 'PENDIENTE'
    },
    en: {
        brand_sub: 'Customer Dashboard', nav_section_activity: 'My Activity', nav_section_settings: 'Settings',
        nav_section_navigation: 'Navigation', nav_apartados: 'My Reservations', nav_config: 'Settings',
        nav_catalog: 'Go to Catalog', nav_logout: 'Log out', topbar_apartados: 'My Reservations',
        topbar_config: 'Settings', theme_title: 'Dark Mode', role_chip: 'Customer',
        apartados_title: 'My Reserved Products', apartados_subtitle: 'Review the products you have reserved and their pickup branch.',
        th_producto: 'Product', th_sucursal: 'Pickup Branch', th_precio: 'Price',
        th_fecha: 'Reservation Date', th_estado: 'Status', loading: 'Loading information...',
        config_title: 'Account Settings', config_subtitle: 'Manage your personal information and security.',
        contact_title: 'Contact Information', name_label: 'Full Name', email_label: 'Email Address',
        contact_help: '* To change your email or name, contact a branch.',
        password_title: 'Change Password', pass_actual_label: 'Account password',
        pass_help_text: 'Would you like to change or update your password?', btn_solicitar_pass: 'Change Password',
        empty_apartados: 'You do not have any reserved products at this time.',
        missing_apartados: 'You have not reserved any products yet.', error_response: 'Error processing the server response.',
        error_request: 'Unable to complete the operation.', redirecting_password: 'Redirecting to password reset...',
        entregado: 'DELIVERED', vencido: 'EXPIRED', cancelado: 'CANCELED', pendiente: 'PENDING'
    }
};
let currentLang = localStorage.getItem('kion-user-lang') === 'en' ? 'en' : 'es';
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

// -------- TEMA --------
const body = document.body, btnTheme = $('#btnTheme');
if (localStorage.getItem('kion-user-theme') === 'dark') {
    body.classList.add('dark-mode');
    btnTheme.querySelector('i').classList.replace('ph-moon', 'ph-sun');
}
btnTheme.addEventListener('click', () => {
    body.classList.toggle('dark-mode');
    const icon = btnTheme.querySelector('i');
    icon.classList.toggle('ph-moon'); icon.classList.toggle('ph-sun');
    localStorage.setItem('kion-user-theme', body.classList.contains('dark-mode') ? 'dark' : 'light');
});

$('#btnLang').addEventListener('click', () => {
    currentLang = currentLang === 'es' ? 'en' : 'es';
    localStorage.setItem('kion-user-lang', currentLang);
    applyLanguage();
    const activeView = document.querySelector('.view-section.active');
    if (activeView) switchView(activeView.id);
});

// -------- SIDEBAR --------
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

// -------- APARTADOS --------
async function loadApartados() {
    const table = $('#apartadosTable');
    try {
        const { data, msg } = await request('get_apartados');
        if (msg) {
            table.innerHTML = `<tr><td colspan="5" style="text-align:center; padding: 30px; color:var(--ink-faint);">${escapeHtml(t('missing_apartados'))}</td></tr>`;
            return;
        }

        table.innerHTML = data.length ? data.map(x => {
            let badgeClass = 'b-sky';
            if(x.estado === 'ENTREGADO') badgeClass = 'b-green';
            if(x.estado === 'VENCIDO' || x.estado === 'CANCELADO') badgeClass = 'b-yellow';

            const estadoKey = x.estado === 'ENTREGADO' ? 'entregado'
                : x.estado === 'VENCIDO' ? 'vencido'
                : x.estado === 'CANCELADO' ? 'cancelado'
                : null;
            const estado = estadoKey ? t(estadoKey) : (x.estado || t('pendiente'));
            const fecha = x.fecha_reserva ? new Date(x.fecha_reserva).toLocaleDateString(currentLang === 'es' ? 'es-MX' : 'en-US') : '-';
            return `<tr>
                <td><b>${escapeHtml(x.producto)}</b></td>
                <td><i class="ph ph-storefront" style="vertical-align: middle; margin-right: 4px;"></i> ${escapeHtml(x.sucursal)}</td>
                <td><b>${escapeHtml(money(x.precio))}</b></td>
                <td>${escapeHtml(fecha)}</td>
                <td><span class="badge ${badgeClass}">${escapeHtml(estado)}</span></td>
            </tr>`;
        }).join('') : `<tr><td colspan="5" style="text-align:center; padding: 40px; color:var(--ink-faint);">${escapeHtml(t('empty_apartados'))}</td></tr>`;
    } catch (e) {
        table.innerHTML = `<tr><td colspan="5" style="text-align:center; padding: 30px; color:var(--danger);">${escapeHtml(e.message)}</td></tr>`;
    }
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