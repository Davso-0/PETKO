<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>PETKO | Gestión para Veterinarias</title>

    <style>
        /* =====================================================
           VARIABLES Y PALETA OFICIAL PETKO
        ===================================================== */
        :root {
            --petko-blue-darker: #152844;
            --petko-blue-dark: #294980;
            --petko-blue-primary: #3B82F6;
            --petko-gold-primary: #FACC15;
            --petko-gold-dark: #B3A053;

            /* Modo Oscuro Default */
            --bg-main: #0B1A30;
            --bg-card: rgba(15, 32, 58, 0.88);
            --bg-card-border: rgba(82, 146, 255, 0.25);
            --text-main: #FFFFFF;
            --text-muted: #B0C4DE;
            --header-bg: rgba(11, 26, 48, 0.95);
            --overlay-gradient: linear-gradient(90deg, #0B1A30 0%, rgba(11, 26, 48, 0.55) 35%, rgba(11, 26, 48, 0) 70%);
            --pattern-overlay: url('../../../img/logos/15_fondo.png');
        }

        /* Modo Claro: Azul Cielo Pastel */
        body.light-mode {
            --bg-main: #DCEBFA;
            --bg-card: rgba(255, 255, 255, 0.92);
            --bg-card-border: rgba(41, 73, 128, 0.25);
            --text-main: #0F294A;
            --text-muted: #284B75;
            --header-bg: rgba(220, 235, 250, 0.95);
            --overlay-gradient: linear-gradient(90deg, #DCEBFA 0%, rgba(220, 235, 250, 0.65) 35%, rgba(220, 235, 250, 0) 70%);
            --pattern-overlay: url('../../../img/logos/13_fondo.png');
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--bg-main);
            color: var(--text-main);
            transition: background 0.35s ease, color 0.35s ease;
            overflow-x: hidden;
        }
        a { text-decoration: none; }

        /* =====================================================
           NAVBAR Y MENÚ FLOTANTE RADIAL
        ===================================================== */
        .navbar {
            width: 100%; height: 76px;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 6%;
            background: var(--header-bg);
            backdrop-filter: blur(14px);
            border-bottom: 1px solid var(--bg-card-border);
            position: sticky; top: 0; z-index: 1000;
        }

        .petko-brand-logo { height: 42px; width: auto; display: block; }
        .menu { display: flex; align-items: center; gap: 24px; }
        .menu a { color: var(--text-muted); font-size: 14px; font-weight: 700; transition: .3s; }
        .menu a:hover { color: var(--petko-gold-primary); }

        .btn-login {
            border: 1.5px solid var(--petko-gold-primary);
            color: var(--petko-gold-primary) !important;
            padding: 9px 22px; border-radius: 50px; font-weight: 700; transition: .25s;
        }
        .btn-login:hover { background: var(--petko-gold-primary); color: #0B1A30 !important; }

        /* Corrección de cierre rápido en menú flotante */
        .util-fab-container { 
            position: relative; 
            display: inline-block; 
            padding-bottom: 12px;
        }

        .util-fab-trigger {
            width: 42px; height: 42px; border-radius: 50%;
            border: 1px solid var(--petko-blue-primary);
            background: var(--petko-blue-darker); color: var(--petko-gold-primary);
            display: flex; align-items: center; justify-content: center; cursor: pointer;
        }

        .util-fab-menu {
            position: absolute; top: 46px; right: 0;
            display: flex; flex-direction: column; gap: 6px;
            background: var(--bg-card); border: 1px solid var(--bg-card-border);
            padding: 8px; border-radius: 14px; backdrop-filter: blur(12px);
            opacity: 0; pointer-events: none; transform: translateY(-8px); 
            transition: opacity 0.25s ease, transform 0.25s ease;
            z-index: 1100;
        }

        .util-fab-menu::before {
            content: '';
            position: absolute;
            top: -15px; left: 0; right: 0; height: 15px;
            background: transparent;
        }

        .util-fab-container:hover .util-fab-menu,
        .util-fab-menu:hover { 
            opacity: 1; pointer-events: auto; transform: translateY(0); 
        }

        .util-btn {
            display: flex; align-items: center; gap: 8px;
            padding: 8px 12px; border-radius: 8px; border: 0;
            background: transparent; color: var(--text-main);
            font-size: 12px; font-weight: 700; cursor: pointer; white-space: nowrap;
        }
        .util-btn:hover { background: rgba(82, 146, 255, 0.15); color: var(--petko-gold-primary); }

        /* =====================================================
           HERO 50/50 CON LÍMITE DE TEXTO
        ===================================================== */
        .hero-split-section {
            position: relative;
            min-height: calc(100vh - 76px);
            display: flex; align-items: center; justify-content: space-between;
            padding: 40px 6%; overflow: hidden;
        }

        .hero-split-section::before {
            content: ''; position: absolute; inset: 0;
            background-image: var(--pattern-overlay);
            background-size: 240px;
            opacity: 0.08; pointer-events: none; z-index: 1;
        }

        .hero-fade-carousel {
            position: absolute; top: 0; right: 0;
            width: 55%; height: 100%;
            z-index: 2; overflow: hidden;
        }

        .fade-slide {
            position: absolute; inset: 0;
            width: 100%; height: 100%;
            object-fit: cover; object-position: center;
            opacity: 0; transition: opacity 1.2s ease-in-out;
        }

        .fade-slide.active { opacity: 1; }

        .hero-overlay-mask {
            position: absolute; inset: 0; z-index: 3;
            background: var(--overlay-gradient);
            pointer-events: none;
        }

        /* Límite horizontal ajustado para que el texto NO toque la imagen */
        .hero-content { 
            position: relative; z-index: 4; 
            width: 42%; max-width: 480px; 
        }

        .hero-visual { 
            position: relative; z-index: 4; 
            width: 52%; display: flex; justify-content: flex-end; 
        }

        .tag {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 7px 16px; border-radius: 50px;
            background: rgba(82, 146, 255, 0.18);
            border: 1px solid var(--petko-blue-primary);
            color: var(--petko-gold-primary);
            font-size: 12px; font-weight: 800; margin-bottom: 20px;
        }

        .hero h1 {
            font-size: 44px; line-height: 1.15; margin-bottom: 20px;
            color: var(--text-main); font-weight: 800;
        }
        .hero h1 span { color: var(--petko-gold-primary); }
        .hero p {
            max-width: 440px; color: var(--text-muted);
            font-size: 16px; line-height: 1.6; margin-bottom: 28px; font-weight: 600;
        }

        .hero-buttons { display: flex; gap: 15px; }
        .btn-primary {
            background: linear-gradient(135deg, var(--petko-gold-primary) 0%, var(--petko-gold-dark) 100%);
            color: #0B1A30; padding: 14px 28px; border-radius: 12px; font-weight: 800; transition: .3s;
        }
        .btn-secondary {
            padding: 14px 28px; border: 1px solid var(--bg-card-border);
            background: var(--bg-card); color: var(--text-main); border-radius: 12px; font-weight: 700; transition: .3s;
        }

        /* Dashboard Mockup */
        .dashboard {
            width: 100%; max-width: 480px;
            background: var(--bg-card); padding: 24px; border-radius: 22px;
            border: 1px solid var(--bg-card-border);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35);
            backdrop-filter: blur(16px);
        }
        .dashboard-title { font-size: 17px; font-weight: bold; color: var(--petko-gold-primary); }
        .dashboard-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; }
        .dashboard-user { width: 36px; height: 36px; border-radius: 50%; background: var(--petko-blue-darker); color: var(--petko-gold-primary); display: flex; align-items: center; justify-content: center; }
        .dashboard-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 18px; }
        .stat { padding: 12px; background: rgba(82, 146, 255, 0.08); border-radius: 10px; border: 1px solid var(--bg-card-border); }
        .stat small { color: var(--text-muted); font-size: 11px; font-weight: 600; }
        .stat strong { display: block; font-size: 17px; margin-top: 4px; color: var(--text-main); }
        .dashboard-content { display: grid; grid-template-columns: 1.3fr 1fr; gap: 12px; }
        .chart, .inventory { background: rgba(0,0,0,0.12); border-radius: 12px; padding: 14px; }
        .chart h4, .inventory h4 { color: var(--petko-gold-primary); margin-bottom: 12px; font-size: 13px; }
        .bars { height: 100px; display: flex; align-items: flex-end; justify-content: space-around; }
        .bar { width: 18px; background: linear-gradient(180deg, var(--petko-gold-primary), var(--petko-gold-dark)); border-radius: 4px 4px 0 0; }
        .bar1 { height: 40%; } .bar2 { height: 65%; } .bar3 { height: 50%; } .bar4 { height: 82%; } .bar5 { height: 94%; }
        .inventory-item { display: flex; justify-content: space-between; padding: 7px 0; border-bottom: 1px solid var(--bg-card-border); font-size: 11px; }
        .available { color: #22C55E; font-weight: bold; }

        /* =====================================================
           SECCIONES GENERALES & FOOTER
        ===================================================== */
        .intro { text-align: center; padding: 50px 6% 60px; color: var(--text-muted); font-size: 17px; font-weight: 600; }
        .intro strong { color: var(--petko-gold-primary); }

        .features-section, .branches, .steps, .benefits, .vet-section, .sales-section {
            padding: 85px 6%; background: var(--bg-main); text-align: center;
        }

        .section-label { color: var(--petko-gold-primary); font-size: 12.5px; font-weight: 800; letter-spacing: 1.5px; margin-bottom: 12px; }
        .section-title { font-size: 38px; color: var(--text-main); font-weight: 800; margin-bottom: 16px; }
        .section-description { max-width: 680px; margin: 0 auto; color: var(--text-muted); font-size: 16px; line-height: 1.6; }

        .features-grid, .benefits-grid {
            display: grid; grid-template-columns: repeat(4, 1fr) !important;
            gap: 22px; margin-top: 50px; text-align: left;
        }

        .branches-grid, .steps-grid {
            display: grid; grid-template-columns: repeat(3, 1fr) !important;
            gap: 25px; margin-top: 50px; text-align: left;
        }

        .feature-card, .branch, .step {
            padding: 30px; border-radius: 20px;
            border: 1px solid var(--bg-card-border);
            background: var(--bg-card); transition: .35s ease;
        }
        .feature-card:hover, .branch:hover, .step:hover {
            transform: translateY(-6px); border-color: var(--petko-gold-primary);
        }

        .step-number { font-size: 40px; font-weight: 800; color: var(--petko-gold-primary); margin-bottom: 12px; }
        .step h3, .branch h3, .feature-card h3 { margin-bottom: 10px; color: var(--text-main); font-size: 18px; }
        .step p, .branch p, .feature-card p { color: var(--text-muted); font-size: 14px; line-height: 1.6; }

        .sales-section { display: flex; align-items: center; gap: 60px; text-align: left; }
        .sales-visual, .sales-text { flex: 1; }
        .sale-screen { background: var(--bg-card); padding: 28px; border-radius: 20px; border: 1px solid var(--bg-card-border); }
        .sale-item { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid var(--bg-card-border); font-size: 14.5px; }
        .sale-total { display: flex; justify-content: space-between; margin-top: 16px; font-size: 18px; font-weight: 800; color: var(--petko-gold-primary); }

        .check-list { list-style: none; margin-top: 20px; }
        .check-list li { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; color: var(--text-main); font-weight: 600; }
        .check-icon-badge { width: 22px; height: 22px; border-radius: 50%; background: rgba(34, 197, 94, 0.2); color: #22C55E; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }

        .cta {
            margin: 60px 6%; padding: 75px 30px; border-radius: 28px;
            background: linear-gradient(135deg, #0B1A30 0%, #294980 100%);
            color: white; text-align: center; border: 1px solid var(--petko-gold-primary);
        }
        .cta h2 { font-size: 40px; margin-bottom: 14px; font-weight: 800; }
        .cta p { color: var(--text-muted); margin-bottom: 25px; font-size: 17px; }

        footer { padding: 40px 6%; background: #071222; color: white; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--bg-card-border); }
    </style>
</head>

<body>

<!-- =====================================================
     NAVBAR PETKO
===================================================== -->
<header class="navbar">
    <a href="#inicio" class="petko-logo-link">
        <img src="../../../img/logos/7_fondo.png" alt="PETKO Veterinaria &amp; Ganadería" class="petko-brand-logo">
    </a>

    <nav class="menu">
        <a href="#inicio">Inicio</a>
        <a href="../../componentes/catalogo.php">Catálogo</a>

        <?php if (isset($_SESSION['usuario'])): ?>
            <?php
                $rolSesion = strtolower($_SESSION['usuario']['rol'] ?? '');
                $rutaDashboard = 'dashboard_usuario.php';
                if (strpos($rolSesion, 'admin') !== false) {
                    $rutaDashboard = 'dashboard.php';
                } elseif (strpos($rolSesion, 'gerente') !== false) {
                    $rutaDashboard = 'dashboard_gerente.php';
                }
            ?>
            <a href="<?= $rutaDashboard ?>" class="btn-login" style="display: inline-flex; align-items: center; gap: 8px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                <?= htmlspecialchars($_SESSION['usuario']['nombre'], ENT_QUOTES, 'UTF-8') ?>
            </a>
            <a href="cerrarSesion.php" style="color: #FF6B6B; font-weight: 600; font-size: 13px; display: inline-flex; align-items: center; gap: 4px;">
                Cerrar sesión
            </a>
        <?php else: ?>
            <a href="../../../../inicioSesion.php" class="btn-login">Iniciar sesión</a>
        <?php endif; ?>

        <!-- MENÚ FLOTANTE RADIAL DE UTILIDADES -->
        <div class="util-fab-container">
            <button class="util-fab-trigger" aria-label="Ajustes">⚙️</button>
            <div class="util-fab-menu">
                <button id="darkModeBtn" class="util-btn" type="button">
                    <span id="themeFabIcon">☀️</span> <span id="themeFabText">Cambiar Tema</span>
                </button>
                <button id="languageToggle" class="util-btn" type="button" aria-label="Cambiar idioma">
                    🌐 <span id="langFabText">EN</span>
                </button>
            </div>
        </div>
    </nav>
</header>

<!-- =====================================================
     HERO DIVIDIDO 50/50 CON MASCOTAS 1 A 1
===================================================== -->
<section class="hero-split-section" id="inicio">
    <!-- Carrusel de Mascotas -->
    <div class="hero-fade-carousel">
        <img src="../../../img/mascotas/1.png" alt="Mascota PETKO" class="fade-slide active">
        <img src="../../../img/mascotas/2.png" alt="Mascota PETKO" class="fade-slide">
        <img src="../../../img/mascotas/3.png" alt="Mascota PETKO" class="fade-slide">
        <img src="../../../img/mascotas/4.png" alt="Mascota PETKO" class="fade-slide">
        <img src="../../../img/mascotas/5.png" alt="Mascota PETKO" class="fade-slide">
        <img src="../../../img/mascotas/6.png" alt="Mascota PETKO" class="fade-slide">
        <img src="../../../img/mascotas/7.png" alt="Mascota PETKO" class="fade-slide">
        <img src="../../../img/mascotas/8.png" alt="Mascota PETKO" class="fade-slide">
        <img src="../../../img/mascotas/9.png" alt="Mascota PETKO" class="fade-slide">
        <img src="../../../img/mascotas/10.png" alt="Mascota PETKO" class="fade-slide">
        <img src="../../../img/mascotas/11.png" alt="Mascota PETKO" class="fade-slide">
    </div>

    <!-- Capa de Degradado -->
    <div class="hero-overlay-mask"></div>

    <!-- Contenido Izquierdo (Texto Delimitado) -->
    <div class="hero-content">
        <div class="tag">
            <span>SOFTWARE PARA VETERINARIAS</span>
        </div>

        <h1>
            La administración de tu veterinaria,
            <span>más fácil.</span>
        </h1>

        <p>
            PETKO es un sistema de punto de venta e inventario diseñado para ayudarte a controlar las ventas, productos, usuarios y sucursales de tu negocio veterinario desde un solo lugar.
        </p>

        <div class="hero-buttons">
            <a href="../../../../inicioSesion.php" class="btn-primary">Comenzar ahora</a>
            <a href="#funciones" class="btn-secondary">Conocer PETKO</a>
        </div>
    </div>

    <!-- Panel PETKO Mockup -->
    <div class="hero-visual">
        <div class="dashboard">
            <div class="dashboard-top">
                <div class="dashboard-title">Panel de PETKO</div>
                <div class="dashboard-user">🏠</div>
            </div>

            <div class="dashboard-stats">
                <div class="stat"><small>Ventas hoy</small><strong>$24,580</strong></div>
                <div class="stat"><small>Productos</small><strong>328</strong></div>
                <div class="stat"><small>Sucursales</small><strong>4</strong></div>
            </div>

            <div class="dashboard-content">
                <div class="chart">
                    <h4>Ventas recientes</h4>
                    <div class="bars">
                        <div class="bar bar1"></div>
                        <div class="bar bar2"></div>
                        <div class="bar bar3"></div>
                        <div class="bar bar4"></div>
                        <div class="bar bar5"></div>
                    </div>
                </div>

                <div class="inventory">
                    <h4>Inventario</h4>
                    <div class="inventory-item"><span>Alimentos</span><span class="available">125</span></div>
                    <div class="inventory-item"><span>Medicamentos</span><span class="available">48</span></div>
                    <div class="inventory-item"><span>Accesorios</span><span class="available">73</span></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- =====================================================
     INTRO
===================================================== -->
<section class="intro">
    Una solución pensada para <strong>veterinarias que quieren tener el control de su negocio.</strong>
</section>

<!-- =====================================================
     FUNCIONES PRINCIPALES
===================================================== -->
<section class="features-section" id="funciones">
    <div class="section-label">TODO EN UN SOLO LUGAR</div>
    <h2 class="section-title">Todo lo que tu veterinaria necesita</h2>
    <p class="section-description">
        PETKO reúne las herramientas necesarias para administrar las operaciones de tu negocio de manera sencilla y organizada.
    </p>

    <div class="features-grid">
        <div class="feature-card">
            <div class="feature-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
            </div>
            <h3>Punto de venta</h3>
            <p>Registra las ventas de alimentos, medicamentos, accesorios y productos veterinarios al instante.</p>
        </div>

        <div class="feature-card">
            <div class="feature-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
            </div>
            <h3>Gestión de Inventario</h3>
            <p>Mantén controladas las existencias y alertas de stock de tus productos en cada sucursal.</p>
        </div>

        <div class="feature-card">
            <div class="feature-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"></path><path d="M5 21V7l8-4v18"></path><path d="M19 21V11l-6-4"></path><path d="M9 9h1"></path><path d="M9 13h1"></path><path d="M9 17h1"></path></svg>
            </div>
            <h3>Multi-Sucursal</h3>
            <p>Administra diferentes sucursales y sedes centralizadamente desde un mismo sistema.</p>
        </div>

        <div class="feature-card">
            <div class="feature-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
            </div>
            <h3>Control de Personal</h3>
            <p>Gestiona los accesos y funciones de administradores, gerentes y cajeros.</p>
        </div>
    </div>
</section>

<!-- =====================================================
     DEMOSTRACIÓN DE VENTAS E INVENTARIO
===================================================== -->
<section class="sales-section" id="ventas">
    <div class="sales-visual">
        <div class="sale-screen">
            <h3>Punto de venta activo</h3>
            <div class="sale-item">
                <span>Alimento para perro (15kg)</span>
                <strong>$450.00</strong>
            </div>
            <div class="sale-item">
                <span>Antibiótico veterinario</span>
                <strong>$280.00</strong>
            </div>
            <div class="sale-item">
                <span>Collar antipulgas</span>
                <strong>$190.00</strong>
            </div>
            <div class="sale-total">
                <span>Total</span>
                <span>$920.00</span>
            </div>
        </div>
    </div>

    <div class="sales-text">
        <div class="section-label">PUNTO DE VENTA E INVENTARIO</div>
        <h2>Vende de forma rápida y mantén la información actualizada.</h2>
        <p>Registra cada operación al instante y consulta existencias en tiempo real sin complicaciones.</p>
        <ul class="check-list">
            <li>
                <div class="check-icon-badge"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg></div>
                <span>Registro inmediato de ventas e ingresos</span>
            </li>
            <li>
                <div class="check-icon-badge"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg></div>
                <span>Categorización de alimentos, medicamentos y accesorios</span>
            </li>
            <li>
                <div class="check-icon-badge"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg></div>
                <span>Descuento automático de existencias en inventario</span>
            </li>
            <li>
                <div class="check-icon-badge"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg></div>
                <span>Control por sucursal y reporte de operaciones</span>
            </li>
        </ul>
    </div>
</section>

<!-- =====================================================
     SUCURSALES Y ROLES
===================================================== -->
<section class="branches" id="sucursales">
    <div class="section-label">MULTI-SUCURSAL Y ROLES</div>
    <h2 class="section-title">Una sola plataforma para todas tus sucursales</h2>
    <p class="section-description">Centraliza la información y facilita la administración de cada punto de venta.</p>

    <div class="branches-grid">
        <div class="branch">
            <div class="branch-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"></path><path d="M5 21V7l8-4v18"></path><path d="M19 21V11l-6-4"></path></svg>
            </div>
            <h3>Sucursales</h3>
            <p>Administra los productos y existencias de cada sede de forma independiente.</p>
        </div>

        <div class="branch">
            <div class="branch-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            </div>
            <h3>Gerentes</h3>
            <p>Los responsables de cada sede supervisan las operaciones y stock local.</p>
        </div>

        <div class="branch">
            <div class="branch-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
            </div>
            <h3>Cajeros</h3>
            <p>Realizan los cobros diarios y atienden el punto de venta de forma ágil.</p>
        </div>
    </div>
</section>

<!-- =====================================================
     PASOS
===================================================== -->
<section class="steps" id="nosotros">
    <div class="section-label">SIMPLE Y ORGANIZADO</div>
    <h2 class="section-title">Comienza a utilizar PETKO en 3 pasos</h2>
    <p class="section-description">Organiza las operaciones de tu veterinaria sin complicaciones.</p>

    <div class="steps-grid">
        <div class="step">
            <div class="step-number">01</div>
            <h3>Ingresa</h3>
            <p>Inicia sesión en tu cuenta para acceder al sistema.</p>
        </div>

        <div class="step">
            <div class="step-number">02</div>
            <h3>Administra</h3>
            <p>Controla inventario, existencias y permisos de cada sucursal.</p>
        </div>

        <div class="step">
            <div class="step-number">03</div>
            <h3>Vende</h3>
            <p>Registra ventas y mantén actualizada la información de tu negocio.</p>
        </div>
    </div>
</section>

<!-- =====================================================
     CTA
===================================================== -->
<section class="cta">
    <h2>Tu veterinaria necesita control.</h2>
    <p>Ventas, inventario y sucursales en un solo lugar.</p>
    <a href="../../../../inicioSesion.php" class="btn-primary">Comenzar con PETKO</a>
</section>

<!-- =====================================================
     FOOTER
===================================================== -->
<footer>
    <div class="logo">PETKO</div>
    <p>© 2026 PETKO · Punto de Venta e Inventario para Veterinarias</p>
</footer>

<!-- =====================================================
     JAVASCRIPT: TEMA, TRADUCCIÓN Y CARRUSEL
===================================================== -->
<script>
    // --- 1. GESTIÓN DE TEMA (MODO OSCURO / AZUL CIELO PASTEL) ---
    const darkModeBtn = document.getElementById("darkModeBtn");
    const themeFabIcon = document.getElementById("themeFabIcon");

    function applyTheme(isLight) {
        if (isLight) {
            document.body.classList.add("light-mode");
            if (themeFabIcon) themeFabIcon.textContent = "🌙";
        } else {
            document.body.classList.remove("light-mode");
            if (themeFabIcon) themeFabIcon.textContent = "☀️";
        }
    }

    if (darkModeBtn) {
        darkModeBtn.addEventListener("click", function () {
            const isLight = !document.body.classList.contains("light-mode");
            localStorage.setItem("petko_light", isLight ? "1" : "0");
            applyTheme(isLight);
        });

        const savedLight = localStorage.getItem("petko_light");
        applyTheme(savedLight === "1");
    }

    // --- 2. DICCIONARIO Y TRADUCTOR ESPAÑOL -> INGLÉS ---
    const homeTranslations = {
        'Inicio': 'Home',
        'Catálogo': 'Catalog',
        'Cerrar sesión': 'Sign out',
        'Iniciar sesión': 'Sign in',
        'Perfil': 'Profile',
        'Cambiar Tema': 'Toggle Theme',

        'SOFTWARE PARA VETERINARIAS': 'SOFTWARE FOR VETERINARY CLINICS',
        'La administración de tu veterinaria,': 'Managing your veterinary clinic,',
        'La administración': 'Managing',
        'de tu veterinaria,': 'your veterinary clinic,',
        'más fácil.': 'made easier.',
        'más fácil': 'made easier',
        'PETKO es un sistema de punto de venta e inventario diseñado para ayudarte a controlar las ventas, productos, usuarios y sucursales de tu negocio veterinario desde un solo lugar.': 'PETKO is a point-of-sale and inventory system designed to help you manage sales, products, users, and branches of your veterinary business from one single place.',
        'Comenzar ahora': 'Get started now',
        'Conocer PETKO': 'Discover PETKO',

        'Panel de PETKO': 'PETKO Dashboard',
        'Ventas hoy': "Today's Sales",
        'Productos': 'Products',
        'Sucursales': 'Branches',
        'Ventas recientes': 'Recent Sales',
        'Inventario': 'Inventory',
        'Alimentos': 'Food',
        'Medicamentos': 'Medication',
        'Accesorios': 'Accessories',

        'Una solución pensada para': 'A solution designed for',
        'veterinarias que quieren tener el control de su negocio.': 'veterinary clinics that want control over their business.',

        'TODO EN UN SOLO LUGAR': 'EVERYTHING IN ONE PLACE',
        'Todo lo que tu veterinaria necesita': 'Everything your veterinary clinic needs',
        'PETKO reúne las herramientas necesarias para administrar las operaciones de tu negocio de manera sencilla y organizada.': 'PETKO brings together the necessary tools to manage your business operations simply and neatly.',
        'Punto de venta': 'Point of Sale',
        'Registra las ventas de alimentos, medicamentos, accesorios y productos veterinarios al instante.': 'Record sales of food, medication, accessories, and veterinary products instantly.',
        'Gestión de Inventario': 'Inventory Management',
        'Mantén controladas las existencias y alertas de stock de tus productos en cada sucursal.': 'Keep stock levels and stock alerts under control for your products in each branch.',
        'Multi-Sucursal': 'Multi-Branch',
        'Administra diferentes sucursales y sedes centralizadamente desde un mismo sistema.': 'Manage different branches and locations centrally from a single system.',
        'Control de Personal': 'Staff Control',
        'Gestiona los accesos y funciones de administradores, gerentes y cajeros.': 'Manage access rights and roles for administrators, managers, and cashiers.',

        'Punto de venta activo': 'Active Point of Sale',
        'Alimento para perro (15kg)': 'Dog food (15kg)',
        'Antibiótico veterinario': 'Veterinary antibiotic',
        'Collar antipulgas': 'Flea collar',
        'Total': 'Total',
        'PUNTO DE VENTA E INVENTARIO': 'POINT OF SALE & INVENTORY',
        'Vende de forma rápida y mantén la información actualizada.': 'Sell quickly and keep information up to date.',
        'Registra cada operación al instante y consulta existencias en tiempo real sin complicaciones.': 'Record every transaction instantly and check real-time stock without hassle.',
        'Registro inmediato de ventas e ingresos': 'Instant recording of sales and revenue',
        'Categorización de alimentos, medicamentos y accesorios': 'Categorization of food, medication, and accessories',
        'Descuento automático de existencias en inventario': 'Automatic stock reduction in inventory',
        'Control por sucursal y reporte de operaciones': 'Branch-level control and operations reporting',

        'MULTI-SUCURSAL Y ROLES': 'MULTI-BRANCH & ROLES',
        'Una sola plataforma para todas tus sucursales': 'One single platform for all your branches',
        'Centraliza la información y facilita la administración de cada punto de venta.': 'Centralize information and streamline management for every point of sale.',
        'Administra los productos y existencias de cada sede de forma independiente.': 'Manage products and stock for each location independently.',
        'Gerentes': 'Managers',
        'Los responsables de cada sede supervisan las operaciones y stock local.': 'Location managers supervise local operations and stock.',
        'Cajeros': 'Cashiers',
        'Realizan los cobros diarios y atienden el punto de venta de forma ágil.': 'Process daily checkouts and handle the cash register quickly.',

        'SIMPLE Y ORGANIZADO': 'SIMPLE & ORGANIZED',
        'Comienza a utilizar PETKO en 3 pasos': 'Start using PETKO in 3 steps',
        'Organiza las operaciones de tu veterinaria sin complicaciones.': 'Organize your veterinary operations without hassle.',
        'Ingresa': 'Log in',
        'Inicia sesión en tu cuenta para acceder al sistema.': 'Sign in to your account to access the system.',
        'Administra': 'Manage',
        'Controla inventario, existencias y permisos de cada sucursal.': 'Control inventory, stock levels, and permissions for each branch.',
        'Vende': 'Sell',
        'Registra ventas y mantén actualizada la información de tu negocio.': 'Record sales and keep your business information up to date.',

        'Tu veterinaria necesita control.': 'Your veterinary clinic needs control.',
        'Ventas, inventario y sucursales en un solo lugar.': 'Sales, inventory, and branches in one place.',
        'Comenzar con PETKO': 'Get started with PETKO',
        '© 2026 PETKO · Punto de Venta e Inventario para Veterinarias': '© 2026 PETKO · Point of Sale and Inventory for Veterinary Clinics'
    };

    const originalHomeTexts = new Map();
    let petkoInEnglish = false;

    function toggleLanguage() {
        const entries = Object.entries(homeTranslations).sort((a, b) => b[0].length - a[0].length);
        const langFabText = document.getElementById('langFabText');

        document.querySelectorAll('body *:not(script):not(style)').forEach((element) => {
            if (element.id === 'darkModeBtn' || element.id === 'languageToggle') return;

            element.childNodes.forEach((node) => {
                if (node.nodeType !== Node.TEXT_NODE) return;

                if (!originalHomeTexts.has(node)) {
                    originalHomeTexts.set(node, node.textContent);
                }

                const originalText = originalHomeTexts.get(node);
                const leading = originalText.match(/^\s*/)[0];
                const trailing = originalText.match(/\s*$/)[0];
                const normalized = originalText.trim().replace(/\s+/g, ' ');

                if (!petkoInEnglish) {
                    node.textContent = originalText;
                    return;
                }

                let translated = normalized;
                entries.forEach(([spanish, english]) => {
                    translated = translated.split(spanish).join(english);
                });
                node.textContent = `${leading}${translated}${trailing}`;
            });
        });

        document.documentElement.lang = petkoInEnglish ? 'en' : 'es';
        document.title = petkoInEnglish ? 'PETKO | Veterinary Clinic Management' : 'PETKO | Gestión para Veterinarias';

        if (langFabText) {
            langFabText.textContent = petkoInEnglish ? 'ES' : 'EN';
        }
    }

    const languageBtn = document.getElementById('languageToggle');
    if (languageBtn) {
        languageBtn.addEventListener('click', () => {
            petkoInEnglish = !petkoInEnglish;
            localStorage.setItem('petko_lang', petkoInEnglish ? 'en' : 'es');
            toggleLanguage();
        });
    }

    const savedLang = localStorage.getItem('petko_lang');
    if (savedLang === 'en') {
        petkoInEnglish = true;
        toggleLanguage();
    }

    // --- 3. CARRUSEL DIFUMINADO 1 A 1 (CROSSFADE) ---
    document.addEventListener('DOMContentLoaded', () => {
        const slides = document.querySelectorAll('.fade-slide');
        let currentSlide = 0;

        if (slides.length > 0) {
            setInterval(() => {
                slides[currentSlide].classList.remove('active');
                currentSlide = (currentSlide + 1) % slides.length;
                slides[currentSlide].classList.add('active');
            }, 3500);
        }
    });
</script>
</body>
</html>