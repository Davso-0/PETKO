<?php
declare(strict_types=1);

ob_start();
require_once __DIR__ . '/../../config/auth_guard.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['usuario']) && !isset($_GET['action'])) {
    header('Location: ../../../../inicioSesion.php');
    exit;
}

if (file_exists(__DIR__ . '/../../config/conexion_BD.php')) {
    require_once __DIR__ . '/../../config/conexion_BD.php';
} elseif (file_exists(__DIR__ . '/../../config/conexion.php')) {
    require_once __DIR__ . '/../../config/conexion.php';
}

require_once __DIR__ . '/../../componentes/mi_cuenta_api.php';

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
$correoUsuario  = $usuario_data['correo'] ?? '';

if (isset($_GET['action'])) {
    verificarCsrf();
    $out = ['ok' => false, 'msg' => 'Acción desconocida'];
    if (isset($pdo) && $pdo instanceof PDO) {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $miCuenta = petkoMiCuentaApi($pdo, $id_usuario_actual, (string)($usuario_data['rol'] ?? ''));
        if ($miCuenta['handled'] ?? false) {
            $out = $miCuenta['response'];
        }
    } else {
        $out = ['ok' => false, 'msg' => '[ERROR] No fue posible conectar con la base de datos.'];
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
            --accent: var(--coffee-main);
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
            --accent: var(--coffee-main);
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

        .sidebar { position: sticky; top: 0; height: 100vh; background: var(--bg-surface); border-right: 1px solid var(--border-soft); padding: 22px 16px; display: flex; flex-direction: column; gap: 26px; z-index: 40; transition: padding .35s var(--ease-expo), background .3s ease; overflow: visible; }
        .brand { display: flex; align-items: center; gap: 12px; padding: 0 8px; position: relative; }
        .app-layout.is-collapsed .nav-label, .app-layout.is-collapsed .nav-section-title { opacity: 0; width: 0; overflow: hidden; pointer-events: none; }

        .sidebar-collapse-btn { position: absolute; top: 26px; right: -13px; width: 26px; height: 26px; border-radius: 50%; background: var(--bg-surface); border: 1px solid var(--border-soft); color: var(--coffee-main); display: grid; place-items: center; box-shadow: var(--shadow-sm); z-index: 41; transition: transform .3s var(--ease-expo); }
        .app-layout.is-collapsed .sidebar-collapse-btn { transform: rotate(180deg); }

        .nav-section-title { font-size: 11px; text-transform: uppercase; letter-spacing: .8px; color: var(--ink-faint); font-weight: 700; padding: 4px 12px 6px; white-space: nowrap; }
        .nav-menu { display: flex; flex-direction: column; gap: 6px; }
        .nav-btn { position: relative; display: flex; align-items: center; gap: 12px; padding: 11px 14px; border-radius: var(--radius); font-weight: 600; font-size: 14px; color: var(--ink-soft); transition: background .2s var(--ease-expo), color .2s ease, transform .2s var(--ease-expo); white-space: nowrap; text-decoration: none; }
        .nav-btn i { font-size: 19px; flex: none; }
        .nav-btn:hover { background: var(--bg-app); color: var(--ink); transform: translateX(2px); }
        .nav-btn.active { background: var(--coffee-gradient); color: #fff; box-shadow: var(--shadow-sm); }

        /* Tooltip flotante cuando el sidebar está colapsado */
        .app-layout.is-collapsed .nav-btn::after {
            content: attr(data-tooltip);
            position: absolute;
            left: calc(100% + 14px);
            top: 50%;
            transform: translateY(-50%) translateX(-6px);
            background: var(--ink);
            color: var(--bg-surface);
            font-size: 12.5px;
            font-weight: 700;
            padding: 7px 13px;
            border-radius: 10px;
            white-space: nowrap;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.22);
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity .18s ease, transform .18s ease;
            z-index: 999;
        }
        .app-layout.is-collapsed .nav-btn:hover::after {
            opacity: 1;
            visibility: visible;
            transform: translateY(-50%) translateX(0);
        }

        .sidebar-scrim { display: none; position: fixed; inset: 0; background: rgba(20,16,12,.45); z-index: 39; opacity: 0; transition: opacity .3s ease; }

        .topbar { height: 70px; display: flex; justify-content: space-between; align-items: center; padding: 0 32px; border-bottom: 1px solid var(--border-soft); background: var(--bg-panel); position: sticky; top: 0; z-index: 30; backdrop-filter: blur(10px); }
        .hamburger-btn { display: none; width: 38px; height: 38px; border-radius: 10px; border: 1px solid var(--border-soft); background: var(--bg-surface); align-items: center; justify-content: center; font-size: 18px; }
        .topbar-actions { display: flex; gap: 12px; align-items: center; }
        .icon-btn { width: 40px; height: 40px; border-radius: 50%; display: grid; place-items: center; background: var(--bg-app); border: 1px solid var(--border-soft); font-size: 19px; transition: .2s ease; }
        .icon-btn:hover { background: var(--coffee-light); color: var(--coffee-main); transform: translateY(-1px); }
        .user-profile { display: flex; align-items: center; gap: 10px; font-weight: 600; padding-left: 14px; border-left: 1px solid var(--border-soft); font-size: 14px; }
        .role-chip { font-size: 10px; font-weight: 800; letter-spacing: .5px; padding: 3px 8px; border-radius: 20px; background: var(--coffee-light); color: var(--coffee-main); text-transform: uppercase; }

        .content { padding: 32px; max-width: 1060px; margin: 0 auto; }
        .header-row { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; flex-wrap: wrap; gap: 14px; }
        .title { font-family: var(--font-display); font-size: 27px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .subtitle { color: var(--ink-soft); font-size: 14px; margin-top: 4px; }

        .btn-primary { background: var(--coffee-gradient); color: #fff; padding: 12px 24px; border-radius: 999px; font-weight: 700; font-size: 14px; box-shadow: var(--shadow-sm); transition: transform .16s var(--ease-expo); display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-primary:active { transform: scale(.96); }
        .panel { background: var(--bg-surface); border-radius: var(--radius); border: 1px solid var(--border-soft); padding: 24px; margin-bottom: 22px; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11.5px; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; }
        .badge.b-green { background: var(--success-bg); color: var(--success); }
        .badge.b-yellow { background: var(--warning-bg); color: var(--warning); }
        .badge.b-red { background: var(--danger-bg); color: var(--danger); }

        @media (max-width: 980px) {
            .app-layout { grid-template-columns: 1fr; }
            .sidebar { position: fixed; left: 0; top: 0; width: 280px; height: 100vh; transform: translateX(-100%); transition: transform .35s var(--ease-expo); box-shadow: var(--shadow-md); }
            .app-layout.mobile-open .sidebar { transform: translateX(0); }
            .app-layout.mobile-open .sidebar-scrim { display: block; opacity: 1; }
            .sidebar-collapse-btn { display: none; }
            .hamburger-btn { display: inline-flex; }
        }
    </style>
    <link rel="stylesheet" href="../../../css/petko-ui.css?v=<?= (int) @filemtime(__DIR__ . '/../../../css/petko-ui.css') ?>">
</head>
<body>
<script src="../../../js/petko-prefs.js"></script>

<div class="app-layout" id="appLayout">
    <aside class="sidebar">
        <button class="sidebar-collapse-btn" id="btnCollapse" data-i18n-title="collapse_menu" title="Colapsar menú"><i class="ph ph-caret-left"></i></button>
        <a class="brand" href="home.php" aria-label="PETKO · Inicio"><img src="../../../img/petko/logo_amarillo.png" alt="PETKO Veterinaria y Ganadería" width="520" height="161"></a>
        <nav class="nav-menu">
            <div class="nav-section-title" data-i18n="sec_activity">Mi Actividad</div>
            <button class="nav-btn active" data-target="view-mi-cuenta" data-tooltip="Mis Apartados"><i class="ph ph-shopping-bag"></i><span class="nav-label" data-i18n="nav_apartados">Mis Apartados</span></button>
            <div class="nav-section-title" data-i18n="sec_settings">Ajustes</div>
            <button class="nav-btn" data-target="view-mi-cuenta-config" data-tooltip="Configuración"><i class="ph ph-gear"></i><span class="nav-label" data-i18n="nav_config">Configuración</span></button>
            <div class="nav-section-title" data-i18n="sec_nav">Navegación</div>
            <a class="nav-btn" href="../../componentes/catalogo.php" data-tooltip="Ir al Catálogo"><i class="ph ph-storefront"></i><span class="nav-label" data-i18n="nav_catalog">Ir al Catálogo</span></a>
            <a class="nav-btn" href="home.php" data-tooltip="Volver a Home"><i class="ph ph-house"></i><span class="nav-label" data-i18n="nav_home">Volver a Home</span></a>
            <a class="nav-btn" href="cerrarSesion.php" data-tooltip="Cerrar sesión" style="color:var(--danger)"><i class="ph ph-sign-out"></i><span class="nav-label" data-i18n="nav_logout">Cerrar sesión</span></a>
        </nav>
    </aside>
    <div class="sidebar-scrim" id="sidebarScrim"></div>

    <main>
        <header class="topbar">
            <div style="display:flex; align-items:center; gap:14px;">
                <button class="hamburger-btn" id="btnMobileMenu"><i class="ph ph-list"></i></button>
                <b id="topbarTitle" style="font-size: 16px;" data-i18n="nav_apartados">Mis Apartados</b>
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
            <?php require_once __DIR__ . '/../../componentes/mi_cuenta_vistas.php'; ?>
        </div>
    </main>
</div>

<script>
const $ = s => document.querySelector(s);
const appLayout = $('#appLayout');
$('#btnCollapse').addEventListener('click', () => appLayout.classList.toggle('is-collapsed'));
$('#btnMobileMenu').addEventListener('click', () => appLayout.classList.add('mobile-open'));
$('#sidebarScrim').addEventListener('click', () => appLayout.classList.remove('mobile-open'));

const I18N_USR = {
    es: {
        collapse_menu: 'Colapsar menú',
        sec_activity: 'Mi Actividad',
        nav_apartados: 'Mis Apartados',
        sec_settings: 'Ajustes',
        nav_config: 'Configuración',
        sec_nav: 'Navegación',
        nav_catalog: 'Ir al Catálogo',
        nav_home: 'Volver a Home',
        nav_logout: 'Cerrar sesión'
    },
    en: {
        collapse_menu: 'Collapse menu',
        sec_activity: 'My Activity',
        nav_apartados: 'My Reservations',
        sec_settings: 'Settings',
        nav_config: 'Settings',
        sec_nav: 'Navigation',
        nav_catalog: 'Go to Catalog',
        nav_home: 'Back to Home',
        nav_logout: 'Log out'
    }
};

let currentLang = (window.PetkoPrefs ? PetkoPrefs.lang() : null) || localStorage.getItem('petko_lang') || 'es';
function tu(key) { return (I18N_USR[currentLang] && I18N_USR[currentLang][key]) || I18N_USR.es[key] || key; }

function syncUserTooltipsAndLang() {
    document.documentElement.lang = currentLang;
    document.querySelectorAll('[data-i18n]').forEach(el => { el.textContent = tu(el.dataset.i18n); });
    document.querySelectorAll('[data-i18n-title]').forEach(el => { el.title = tu(el.dataset.i18nTitle); });
    document.querySelectorAll('.nav-btn').forEach(btn => {
        const lbl = btn.querySelector('.nav-label');
        if (lbl) btn.setAttribute('data-tooltip', lbl.textContent.trim());
    });
    const activeView = document.querySelector('.view-section.active');
    if (activeView) {
        $('#topbarTitle').textContent = activeView.id === 'view-mi-cuenta-config' ? tu('nav_config') : tu('nav_apartados');
    }
}

if (window.PetkoPrefs && typeof PetkoPrefs.onLang === 'function') {
    PetkoPrefs.onLang(l => {
        currentLang = l;
        syncUserTooltipsAndLang();
    });
}

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
    $('#topbarTitle').textContent = targetId === 'view-mi-cuenta-config' ? tu('nav_config') : tu('nav_apartados');
}

navButtons.forEach(btn => btn.addEventListener('click', e => switchView(e.currentTarget.dataset.target)));

syncUserTooltipsAndLang();

window.addEventListener('load', () => {
    const btnApartados = document.querySelector('.nav-btn[data-target="view-mi-cuenta"]');
    if (btnApartados) {
        btnApartados.click();
    } else {
        switchView('view-mi-cuenta');
    }
});
</script>
</body>
</html>