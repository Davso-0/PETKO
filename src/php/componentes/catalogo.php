<?php
declare(strict_types=1);
require_once __DIR__ . '/versionMobil/core/Movil.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =====================================================
   DETECCIÓN AUTOMÁTICA DE VERSIÓN MÓVIL
   Solo en peticiones GET directas (no AJAX)
===================================================== */
if (empty($GLOBALS['KION_MOBILE_RENDER']) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && ($_GET['action'] ?? '') === '') {
    if (KionMovil::usarVersionMovil()) {
        header('Accept-CH: Sec-CH-UA-Mobile');
        header('Vary: Sec-CH-UA-Mobile, Cookie');
        header('Location: versionMobil/catalogo.php');
        exit;
    }
}

// Token CSRF para proteger peticiones POST de apartados y compras
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// inicioSesion.php guarda al usuario en $_SESSION['usuario'] con su id_usuario.
$idUsuarioSesion = 0;
if (is_array($_SESSION['usuario'] ?? null)) {
    $idUsuarioSesion = (int)($_SESSION['usuario']['id_usuario'] ?? $_SESSION['usuario']['id'] ?? 0);
} else {
    $idUsuarioSesion = (int)($_SESSION['usuario'] ?? 0);
}
$usuarioAutenticado = $idUsuarioSesion > 0;
$rutaLogin = '../../../inicioSesion.php';

// Conexión centralizada y segura
require_once __DIR__ . '/../config/conexion_BD.php';
$errorConsulta = $errorConexion ?? null;

// -------------------------------------------------------------------------
// ENDPOINT AJAX SEGURO (POST): Guardar apartado / compra en BD por 48 horas
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'registrar_apartado') {
    ob_start();
    header('Content-Type: application/json; charset=utf-8');

    // 1. Verificar sesión autenticada
    if (!$usuarioAutenticado) {
        if (ob_get_length()) ob_clean();
        http_response_code(401);
        echo json_encode([
            'ok'   => false,
            'auth' => false,
            'msg'  => 'Debes iniciar sesión para apartar o comprar productos.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2. Verificar token CSRF
    $headerCsrf = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($headerCsrf === '' || !hash_equals($_SESSION['csrf_token'], $headerCsrf)) {
        if (ob_get_length()) ob_clean();
        http_response_code(403);
        echo json_encode([
            'ok'  => false,
            'msg' => 'Token de seguridad inválido o sesión expirada. Recarga la página.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        if (ob_get_length()) ob_clean();
        http_response_code(500);
        echo json_encode(['ok' => false, 'msg' => 'Sin conexión a la base de datos.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($payload)) {
        if (ob_get_length()) ob_clean();
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => 'Formato de solicitud inválido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $rawItems = is_array($payload['items'] ?? null) ? $payload['items'] : [];
    $modo = ($payload['modo'] ?? 'sucursal') === 'pagar' ? 'pagar' : 'sucursal';
    $estadoPago = $modo === 'pagar' ? 'PAGADO' : 'PENDIENTE_PAGO';
    $idSucursal = (int)($payload['id_sucursal'] ?? 0);

    // Agrupar y sanear cantidades por id_producto (evita duplicados y cantidades negativas)
    $itemsAgrupados = [];
    foreach ($rawItems as $item) {
        $idProd = (int)($item['id_producto'] ?? $item['id'] ?? 0);
        $cant = (int)($item['cantidad'] ?? $item['qty'] ?? 1);
        if ($idProd > 0 && $cant > 0) {
            $itemsAgrupados[$idProd] = min(50, ($itemsAgrupados[$idProd] ?? 0) + $cant);
        }
    }

    if (empty($itemsAgrupados)) {
        if (ob_get_length()) ob_clean();
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => 'No hay productos válidos en el carrito para procesar.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Generar folio único desde el servidor (nunca confiar en el folio del cliente)
    $folio = 'PTK-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

    try {
        $pdo->beginTransaction();

        // 3. Validar que la sucursal exista realmente
        if ($idSucursal > 0) {
            $stSuc = $pdo->prepare("SELECT id_sucursal FROM sucursales WHERE id_sucursal = ? LIMIT 1");
            $stSuc->execute([$idSucursal]);
            if (!$stSuc->fetchColumn()) {
                $idSucursal = 0;
            }
        }
        if ($idSucursal <= 0) {
            $idSucursal = (int)$pdo->query("SELECT id_sucursal FROM sucursales ORDER BY id_sucursal ASC LIMIT 1")->fetchColumn();
        }
        if ($idSucursal <= 0) {
            throw new RuntimeException('No hay sucursales activas registradas en el sistema.');
        }

        // Detectar columnas disponibles en apartados antes de preparar el INSERT
        $colsApartados = array_column($pdo->query("SHOW COLUMNS FROM apartados")->fetchAll(PDO::FETCH_ASSOC), 'Field');
        $tieneCantidad = in_array('cantidad', $colsApartados, true);
        $tieneEstadoPago = in_array('estado_pago', $colsApartados, true);

        $stmtCheckProd = $pdo->prepare("
            SELECT id_producto, nombre, precio, UPPER(TRIM(COALESCE(estado, 'ACTIVO'))) AS estado_prod
            FROM productos
            WHERE id_producto = ?
            LIMIT 1
        ");

        $stmtCheckInv = $pdo->prepare("
            SELECT id_inventario, existencias
            FROM inventarios
            WHERE id_producto = ?
            FOR UPDATE
        ");

        $stmtUpdateStock = $pdo->prepare("
            UPDATE inventarios 
            SET existencias = GREATEST(0, existencias - ?) 
            WHERE id_inventario = ?
        ");

        if ($tieneCantidad && $tieneEstadoPago) {
            $stmtInsert = $pdo->prepare("
                INSERT INTO apartados (id_usuario, id_producto, id_sucursal, cantidad, fecha_reserva, estado, estado_pago, folio, horas_vigencia)
                VALUES (?, ?, ?, ?, NOW(), 'PENDIENTE', ?, ?, 48)
            ");
        } else {
            $stmtInsert = $pdo->prepare("
                INSERT INTO apartados (id_usuario, id_producto, id_sucursal, fecha_reserva, estado, folio, horas_vigencia)
                VALUES (?, ?, ?, NOW(), 'PENDIENTE', ?, 48)
            ");
        }

        $stocksActualizados = [];
        $resumenTicket = [];
        $totalReal = 0.0;

        foreach ($itemsAgrupados as $idProd => $cant) {
            $stmtCheckProd->execute([$idProd]);
            $prodInfo = $stmtCheckProd->fetch(PDO::FETCH_ASSOC);

            if (!$prodInfo || $prodInfo['estado_prod'] !== 'ACTIVO') {
                $pdo->rollBack();
                if (ob_get_length()) ob_clean();
                http_response_code(409);
                echo json_encode([
                    'ok'          => false,
                    'invalid_ids' => [$idProd],
                    'msg'         => "El producto (ID {$idProd}) ya no está disponible en el catálogo y fue retirado de tu carrito."
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Consultar filas de inventario del producto
            $stmtCheckInv->execute([$idProd]);
            $filasInv = $stmtCheckInv->fetchAll(PDO::FETCH_ASSOC);

            $tieneInv = count($filasInv) > 0;
            $stockActual = $tieneInv
                ? array_sum(array_map(static fn($r) => (int)$r['existencias'], $filasInv))
                : 10;

            if ($stockActual < $cant) {
                throw new RuntimeException("Stock insuficiente para \"{$prodInfo['nombre']}\" (disponibles: {$stockActual}).");
            }

            if ($tieneCantidad && $tieneEstadoPago) {
                $stmtInsert->execute([$idUsuarioSesion, $idProd, $idSucursal, $cant, $estadoPago, $folio]);
            } else {
                for ($i = 0; $i < $cant; $i++) {
                    $stmtInsert->execute([$idUsuarioSesion, $idProd, $idSucursal, $folio]);
                }
            }

            // Descontar existencias de las filas de inventario disponibles
            if ($tieneInv) {
                $restante = $cant;
                foreach ($filasInv as $filaInv) {
                    if ($restante <= 0) break;
                    $dispFila = (int)$filaInv['existencias'];
                    if ($dispFila <= 0) continue;
                    $aDescontar = min($dispFila, $restante);
                    $stmtUpdateStock->execute([$aDescontar, (int)$filaInv['id_inventario']]);
                    $restante -= $aDescontar;
                }
            }

            $precioUnit = (float)$prodInfo['precio'];
            $totalReal += $precioUnit * $cant;
            $stocksActualizados[$idProd] = max(0, $stockActual - $cant);
            $resumenTicket[] = [
                'id_producto' => $idProd,
                'nombre'      => $prodInfo['nombre'],
                'cantidad'    => $cant,
                'precio'      => $precioUnit
            ];
        }

        $pdo->commit();
        if (ob_get_length()) ob_clean();

        echo json_encode([
            'ok'          => true,
            'folio'       => $folio,
            'fecha'       => date('d/m/Y H:i'),
            'modo'        => $modo,
            'estado_pago' => $estadoPago,
            'vigencia'    => 48,
            'total'       => $totalReal,
            'items'       => $resumenTicket,
            'stocks'      => $stocksActualizados,
            'msg'         => $modo === 'pagar'
                ? 'Pago verificado y producto(s) apartados por 48 horas.'
                : 'Producto(s) apartados por 48 horas para pago en sucursal.'
        ], JSON_UNESCAPED_UNICODE);
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (ob_get_length()) ob_clean();
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$categoriaSeleccionada = isset($_GET['categoria']) ? (string) $_GET['categoria'] : null;
$productos = [];
$productosCatalogo = [];
$detallesProducto = [];
$categoriasDisponibles = [];
$sucursalesDisponibles = [];

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $sucStmt = $pdo->query("SELECT id_sucursal, nombre FROM sucursales ORDER BY id_sucursal ASC");
        $sucursalesDisponibles = $sucStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $sucursalesDisponibles = [['id_sucursal' => 1, 'nombre' => 'Sucursal Principal']];
    }

    try {
        $catStmt = $pdo->query("SELECT nombre FROM categorias ORDER BY nombre ASC");
        while ($row = $catStmt->fetch(PDO::FETCH_ASSOC)) {
            $categoriasDisponibles[] = $row['nombre'];
        }
        if (empty($categoriasDisponibles)) {
            $categoriasDisponibles = ['Medicamentos', 'Alimentos', 'Antiparasitarios', 'Higiene', 'Accesorios'];
        }

        // Consulta de productos agrupada por id_producto para evitar duplicados por sucursal en inventarios
        $sql = "SELECT p.id_producto, p.codigo, p.nombre, p.descripcion, p.imagen_url, p.precio,
                       MAX(c.nombre) AS categoria,
                       COALESCE(SUM(i.existencias), 10) AS stock
                FROM productos p
                LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
                LEFT JOIN inventarios i ON p.id_producto = i.id_producto
                WHERE UPPER(TRIM(COALESCE(p.estado, 'ACTIVO'))) = 'ACTIVO'
                GROUP BY p.id_producto, p.codigo, p.nombre, p.descripcion, p.imagen_url, p.precio
                ORDER BY p.id_producto ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $totalCatalogo = count($productos);
        $productosCatalogo = $productos;

        if ($categoriaSeleccionada !== null && !in_array($categoriaSeleccionada, $categoriasDisponibles, true)) {
            $categoriaSeleccionada = null;
        }

        if ($categoriaSeleccionada !== null) {
            $productos = array_values(array_filter($productos, function ($p) use ($categoriaSeleccionada) {
                return ($p['categoria'] ?? null) === $categoriaSeleccionada;
            }));
        }
    } catch (PDOException $e) {
        $errorConsulta = 'No pudimos cargar los productos en este momento.';
    }

    try {
        $detStmt = $pdo->query("SELECT id_producto, codigo, descripcion, imagen_url FROM productos");
        foreach ($detStmt as $fila) {
            $detallesProducto[(int) $fila['id_producto']] = $fila;
        }
    } catch (PDOException $e) {
        $detallesProducto = [];
    }
}

function formatearPrecio(float $precio): string {
    return '$' . number_format($precio, 2) . ' MXN';
}

$rutasImagenesAntiguas = [
    1 => '../../img/perron.jpg',
    2 => '../../img/whiskas.jpg',
    3 => '../../img/purina.jpg',
    4 => '../../img/malta.jpg'
];

function resolverImagenProducto(?string $imagenUrl, int $idProd, string $categoria = 'PETKO'): string {
    global $rutasImagenesAntiguas;
    $img = trim((string)$imagenUrl);
    if ($img !== '') {
        if (preg_match('/^(https?:\/\/|data:image\/)/i', $img)) {
            return $img;
        }
        if (strpos($img, 'src/img/') === 0) {
            $img = '../../img/' . substr($img, 8);
        }
        return $img;
    }
    $rutaFisica = __DIR__ . '/../../img/' . $idProd . '.jpg';
    if (file_exists($rutaFisica)) {
        return '../../img/' . $idProd . '.jpg';
    }
    if (isset($rutasImagenesAntiguas[$idProd])) {
        return $rutasImagenesAntiguas[$idProd];
    }
    return "https://placehold.co/400x400/E8F6EE/0B2545?text=" . urlencode($categoria);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PETKO · Catálogo</title>
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/bold/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="../../css/catalogo.css?v=<?= (int) @filemtime(__DIR__ . '/../../css/catalogo.css') ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="catalogo-app">
<script src="../../js/petko-prefs.js"></script>

<header class="topbar">
    <div class="topbar__marca">
        <a href="../modulos/home/home.php" class="topbar__logo" aria-label="PETKO · Inicio">
            <img src="../../img/petko/logo_amarillo.png" alt="PETKO Veterinaria y Ganadería" width="520" height="161">
        </a>
        <span class="topbar__subtitulo" data-i18n="topbar_sub">Veterinaria y Farmacia · cuidado con patitas.</span>
        <span class="topbar__badge"><i class="ph-fill ph-heartbeat"></i> Clínica</span>
    </div>

    <div class="topbar__acciones">
        <button class="btn-icon" id="btnTheme" data-petko-theme type="button">🌙</button>
        <button class="btn-icon" id="btnLang" data-petko-lang type="button">EN</button>
        
        <?php 
        $rutaDashboard = $rutaLogin;
        $textoPanel = 'Iniciar Sesión';
        $iconoPanel = 'ph-sign-in';
        $claveI18nPanel = 'nav_login';
        $esClienteCompras = false;
        
        if (isset($_SESSION['usuario'])) {
            $rolSesion = strtolower($_SESSION['usuario']['rol'] ?? '');
            
            if (strpos($rolSesion, 'admin') !== false) {
                $textoPanel = 'Panel';
                $iconoPanel = 'ph-squares-four';
                $claveI18nPanel = 'nav_dashboard';
                $rutaDashboard = '../modulos/home/dashboard.php';
            } elseif (strpos($rolSesion, 'gerente') !== false) {
                $textoPanel = 'Panel';
                $iconoPanel = 'ph-squares-four';
                $claveI18nPanel = 'nav_dashboard';
                $rutaDashboard = '../modulos/home/dashboard_gerente.php';
            } else {
                // Para clientes / usuarios: mostrar "Mis compras" directo a su panel
                $textoPanel = 'Mis compras';
                $iconoPanel = 'ph-shopping-bag';
                $claveI18nPanel = 'nav_my_purchases';
                $rutaDashboard = '../modulos/home/dashboard_usuario.php';
                $esClienteCompras = true;
            }
        }
        ?>
        
        <a href="<?= htmlspecialchars($rutaDashboard, ENT_QUOTES, 'UTF-8') ?>" class="btn-nav" id="btn-nav-panel">
            <i class="ph <?= $iconoPanel ?>"></i>
            <?php if ($esClienteCompras): ?>
                <span data-custom-i18n="nav_my_purchases"><?= $textoPanel ?></span>
            <?php else: ?>
                <span data-i18n="<?= $claveI18nPanel ?>"><?= $textoPanel ?></span>
            <?php endif; ?>
        </a>
        
        <button class="btn-icon topbar__carrito" id="open-cart" aria-label="Carrito">
            <i class="ph ph-shopping-cart"></i>
            <span id="cart-count" class="topbar__carrito-conteo is-empty" aria-live="polite">0</span>
        </button>
    </div>
</header>

<div class="hero-wrap">
    <section class="hero" aria-labelledby="hero-titulo">
        <div class="hero__contenido">
            <span class="hero__eyebrow">
                <i class="ph-fill ph-first-aid-kit" aria-hidden="true"></i>
                <span data-i18n="hero_eyebrow">Veterinaria &amp; Farmacia</span>
            </span>
            <h1 class="hero__titulo" id="hero-titulo" data-i18n="hero_title">Farmacia para tu <em>manada</em></h1>
            <p class="hero__subtitulo" data-i18n="hero_sub">Medicamentos, nutrición y accesorios elegidos con criterio clínico para el bienestar de perros, gatos y cada integrante de tu familia.</p>
            <div class="hero__acciones">
                <a href="#catalogo" class="hero__cta">
                    <span data-i18n="hero_cta">Explorar catálogo</span>
                    <i class="ph-bold ph-arrow-down" aria-hidden="true"></i>
                </a>
            </div>
            <dl class="hero__stats">
                <div class="hero__stat">
                    <dt><?= (int) ($totalCatalogo ?? count($productos)) ?></dt>
                    <dd data-i18n="hero_stat_products">productos</dd>
                </div>
                <div class="hero__stat">
                    <dt><?= count($categoriasDisponibles) ?></dt>
                    <dd data-i18n="hero_stat_categories">categorías</dd>
                </div>
                <div class="hero__stat">
                    <dt><i class="ph-fill ph-heartbeat" aria-hidden="true"></i></dt>
                    <dd data-i18n="hero_stat_vet">asesoría veterinaria</dd>
                </div>
            </dl>
        </div>
        <div class="hero__visual" aria-hidden="true">
            <div class="hero__orbe">
                <span class="hero__orbe-anillo"></span>
                <i class="ph-fill ph-paw-print"></i>
            </div>
            <span class="hero__chip hero__chip--1"><i class="ph-fill ph-pill"></i> <span data-i18n="hero_chip_1">Farmacia</span></span>
            <span class="hero__chip hero__chip--2"><i class="ph-fill ph-bowl-food"></i> <span data-i18n="hero_chip_2">Nutrición</span></span>
            <span class="hero__chip hero__chip--3"><i class="ph-fill ph-heart"></i> <span data-i18n="hero_chip_3">Bienestar</span></span>
        </div>
    </section>
</div>

<div class="layout">
    <aside class="filtros">
        <div class="filtros__busqueda">
            <i class="ph ph-magnifying-glass" aria-hidden="true"></i>
            <input type="search" id="busqueda-catalogo" name="busqueda" autocomplete="off"
                   placeholder="Buscar producto..."
                   data-i18n-placeholder="search_ph"
                   aria-label="Buscar producto">
        </div>
        <div class="filtros__hero">
            <img src="../../img/perrito_clinica.png" alt="Perrito veterinario con estetoscopio" class="filtros__mascota">
            <div class="perrito-globo" id="perrito-globo" data-tono="reposo" role="status" aria-live="polite">
                <p class="perrito-globo__texto filtros__saludo" id="perrito-globo-texto">¡Guau! Elige una categoría</p>
                <i class="ph-fill ph-paw-print perrito-globo__huella" aria-hidden="true"></i>
            </div>
        </div>
        <h2 class="filtros__titulo"><i class="ph-fill ph-paw-print"></i> <span data-i18n="filter_title">Categorías</span></h2>
        <nav class="filtros__lista">
            <a href="catalogo.php" data-categoria="" class="filtros__enlace <?= $categoriaSeleccionada === null ? 'filtros__enlace--activo' : '' ?>"<?= $categoriaSeleccionada === null ? ' aria-current="page"' : '' ?>>
                <i class="ph-fill ph-bone"></i>
                <span data-i18n="filter_all">Todos los productos</span>
                <?php if ($categoriaSeleccionada === null): ?><i class="ph-bold ph-check filtros__check" aria-hidden="true"></i><?php endif; ?>
            </a>
            <?php foreach ($categoriasDisponibles as $cat):
                $iconoCat = 'ph-paw-print';
                $catLower = mb_strtolower((string) $cat);
                if (str_contains($catLower, 'medic')) {
                    $iconoCat = 'ph-pill';
                } elseif (str_contains($catLower, 'aliment')) {
                    $iconoCat = 'ph-bowl-food';
                } elseif (str_contains($catLower, 'antiparas') || str_contains($catLower, 'pulga')) {
                    $iconoCat = 'ph-bug';
                } elseif (str_contains($catLower, 'higien') || str_contains($catLower, 'baño')) {
                    $iconoCat = 'ph-drop';
                } elseif (str_contains($catLower, 'acces')) {
                    $iconoCat = 'ph-tennis-ball';
                }
            ?>
                <a href="?categoria=<?= urlencode($cat) ?>" data-categoria="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>" class="filtros__enlace <?= $categoriaSeleccionada === $cat ? 'filtros__enlace--activo' : '' ?>"<?= $categoriaSeleccionada === $cat ? ' aria-current="page"' : '' ?>>
                    <i class="ph-fill <?= $iconoCat ?>"></i>
                    <span><?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if ($categoriaSeleccionada === $cat): ?><i class="ph-bold ph-check filtros__check" aria-hidden="true"></i><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <main class="catalogo" id="catalogo">
        <div class="catalogo__encabezado">
            <div>
                <p class="catalogo__kicker" data-i18n="cat_kicker">Colección PETKO</p>
                <h2 class="catalogo__titulo" id="catalogo-titulo"><?= $categoriaSeleccionada !== null ? htmlspecialchars($categoriaSeleccionada, ENT_QUOTES, 'UTF-8') : '<span data-i18n="cat_title">Catálogo General</span>' ?></h2>
            </div>
            <p class="catalogo__conteo"><span id="conteo-visible"><?= count($productos) ?></span> <span data-i18n="cat_items">artículos</span></p>
        </div>

        <?php if ($errorConsulta !== null): ?>
            <div class="estado-vacio">
                <i class="ph ph-warning-circle" style="font-size: 40px; margin-bottom:10px; color:var(--clinic);"></i>
                <p><?= htmlspecialchars($errorConsulta, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        <?php elseif (count($productosCatalogo) === 0): ?>
            <div class="estado-vacio">
                <i class="ph ph-package" style="font-size: 40px; margin-bottom:10px; color:var(--ink-soft);"></i>
                <p data-i18n="empty_state">No hay productos con stock disponible en esta categoría.</p>
            </div>
        <?php else: ?>
            <div class="estado-vacio" id="categoria-vacia"<?= count($productos) === 0 ? '' : ' hidden' ?>>
                <i class="ph ph-package" style="font-size: 40px; margin-bottom:10px; color:var(--ink-soft);"></i>
                <p data-i18n="empty_state">No hay productos con stock disponible en esta categoría.</p>
            </div>
            <div class="grid-productos">
                <?php
                $indiceVisible = 0;
                foreach ($productosCatalogo as $producto):
                    $categoriaProd = (string) ($producto['categoria'] ?? '');
                    $visibleInicial = $categoriaSeleccionada === null || $categoriaProd === $categoriaSeleccionada;
                    $stock   = (int) $producto['stock'];
                    $agotado = $stock <= 0;
                    $nombre  = htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8');
                    $idProd  = (int) $producto['id_producto'];
                    $precio  = (float) $producto['precio'];
                    
                    $imgSrc = resolverImagenProducto($producto['imagen_url'] ?? null, $idProd, $producto['categoria'] ?? 'PETKO');
                    $detalle     = $detallesProducto[$idProd] ?? [];
                    $codigo      = htmlspecialchars((string) ($producto['codigo'] ?? ($detalle['codigo'] ?? '')), ENT_QUOTES, 'UTF-8');
                    $descripcion = htmlspecialchars((string) ($producto['descripcion'] ?? ($detalle['descripcion'] ?? '')), ENT_QUOTES, 'UTF-8');
                    $imgAttr     = htmlspecialchars($imgSrc, ENT_QUOTES, 'UTF-8');
                ?>
                <article class="tarjeta" data-id="<?= $idProd ?>" data-nombre="<?= $nombre ?>" data-categoria="<?= htmlspecialchars($categoriaProd, ENT_QUOTES, 'UTF-8') ?>"
                         data-precio="<?= $precio ?>" data-stock="<?= $stock ?>" data-img="<?= $imgAttr ?>"
                         data-codigo="<?= $codigo ?>" data-descripcion="<?= $descripcion ?>"
                         style="--i: <?= $visibleInicial ? min($indiceVisible++, 12) : 0 ?>"<?= $visibleInicial ? '' : ' hidden' ?>>
                    <div class="tarjeta__imagen js-ver-detalle" role="button" tabindex="0" aria-label="Ver detalle de <?= $nombre ?>">
                        <img src="<?= $imgAttr ?>" alt="<?= $nombre ?>" loading="lazy">
                        <span class="tarjeta__ver-hint" aria-hidden="true">
                            <i class="ph-bold ph-arrows-out-simple"></i>
                            <span data-i18n="btn_view">Vista ampliada</span>
                        </span>
                    </div>
                    <div class="tarjeta__cuerpo">
                        <span class="tarjeta__categoria"><?= htmlspecialchars($producto['categoria'] ?? 'General', ENT_QUOTES, 'UTF-8') ?></span>
                        <h3 class="tarjeta__titulo"><button type="button" class="tarjeta__titulo-btn js-ver-detalle"><?= $nombre ?></button></h3>
                        <div class="tarjeta__meta">
                            <span class="tarjeta__precio">$<?= number_format($precio, 2) ?></span>
                            <?php if ($agotado): ?>
                                <span class="tarjeta__stock stock-none">
                                    <span data-i18n="stock_out">Agotado</span>
                                </span>
                            <?php elseif ($stock < 10): ?>
                                <span class="tarjeta__stock stock-low">
                                    🔥 <span data-i18n="stock_low_a">¡Últimas</span> <?= $stock ?> <span data-i18n="stock_low_b">piezas!</span>
                                </span>
                            <?php else: ?>
                                <span class="tarjeta__stock stock-ok">
                                    <span data-i18n="stock_lbl">Disponibles:</span> <?= $stock ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="tarjeta__acciones">
                            <button type="button" class="btn-agregar js-add-cart"
                                    data-id="<?= $idProd ?>" data-nombre="<?= $nombre ?>" data-precio="<?= $precio ?>"
                                    data-img="<?= $imgAttr ?>" data-stock="<?= $stock ?>"<?= $agotado ? ' disabled' : '' ?>>
                                <i class="ph ph-shopping-cart-plus" aria-hidden="true"></i>
                                <span data-i18n="<?= $agotado ? 'btn_out' : 'btn_add' ?>"><?= $agotado ? 'Sin stock' : 'Agregar al carrito' ?></span>
                            </button>
                            <button type="button" class="btn-apartar js-apartar" data-id="<?= $idProd ?>"<?= $agotado ? ' disabled' : '' ?>>
                                <i class="ph ph-bookmark-simple" aria-hidden="true"></i>
                                <span data-i18n="btn_reserve">Apartar producto</span>
                            </button>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <div class="busqueda-vacia" id="busqueda-vacia" role="status" hidden>
                <div class="busqueda-vacia__ilustracion">
                    <img src="../../img/perrito_clinica.png" alt="" aria-hidden="true">
                    <span class="busqueda-vacia__lupa"><i class="ph-bold ph-magnifying-glass"></i></span>
                </div>
                <h2 class="busqueda-vacia__titulo" data-i18n="search_empty_title">¡Guau! No encontramos productos con ese nombre</h2>
                <p class="busqueda-vacia__texto" data-i18n="search_empty_sub">Revisa la ortografía o prueba con otra palabra, como “croquetas” o “shampoo”.</p>
                <button type="button" class="busqueda-vacia__btn" id="limpiar-busqueda">
                    <i class="ph-bold ph-arrow-counter-clockwise"></i>
                    <span data-i18n="search_clear">Ver todos los productos</span>
                </button>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- CARRITO LATERAL -->
<div id="cart-overlay" class="cart-overlay"></div>
<aside id="cart-panel" class="cart-panel">
    <div class="cart-panel__header">
        <h2 data-i18n="cart_title">Tu Carrito</h2>
        <button id="close-cart" class="cart-panel__close"><i class="ph ph-x"></i></button>
    </div>
    <div class="cart-panel__scroll">
        <div id="cart-items" class="cart-panel__items">
            <!-- Los items se renderizan aquí -->
        </div>
        <section class="apartados" id="apartados" aria-labelledby="apartados-titulo" hidden>
            <h3 class="apartados__titulo" id="apartados-titulo">
                <i class="ph-fill ph-bookmark-simple" aria-hidden="true"></i>
                <span data-i18n="reserve_title">Apartados</span>
                <span class="apartados__conteo" id="apartados-conteo">0</span>
            </h3>
            <p class="apartados__politica" data-i18n="reserve_policy_short">Te los guardamos 48 h en sucursal · pagas al recoger.</p>
            <div class="apartados__lista" id="apartados-lista"></div>
        </section>
    </div>
    <div class="cart-panel__footer">
        <div class="cart-panel__total">
            <span data-i18n="cart_total">Total:</span>
            <span id="cart-total-price">$0.00</span>
        </div>
        <button type="button" class="cart-panel__checkout" id="btn-checkout" aria-haspopup="dialog"><i class="ph ph-credit-card"></i> <span data-i18n="cart_checkout">Proceder al pago</span></button>
    </div>
</aside>

<!-- VISTA AMPLIADA DEL PRODUCTO -->
<dialog class="modal-producto" id="modal-producto" aria-labelledby="modal-titulo">
    <div class="modal-producto__caja">
        <button type="button" class="modal-producto__cerrar" id="modal-cerrar" aria-label="Cerrar">
            <i class="ph-bold ph-x"></i>
        </button>

        <div class="modal-producto__media" id="modal-media">
            <img id="modal-img" src="data:," alt="">
            <span class="modal-producto__zoom-hint" aria-hidden="true">
                <i class="ph ph-magnifying-glass-plus"></i>
                <span data-i18n="modal_zoom">Pasa el cursor para ampliar</span>
            </span>
        </div>

        <div class="modal-producto__info">
            <div class="modal-producto__meta">
                <span class="modal-producto__categoria" id="modal-categoria"></span>
                <span class="modal-producto__codigo" id="modal-codigo"></span>
            </div>
            <h2 class="modal-producto__titulo" id="modal-titulo"></h2>
            <p class="modal-producto__descripcion" id="modal-descripcion"></p>

            <div class="modal-producto__nota">
                <i class="ph-fill ph-stethoscope" aria-hidden="true"></i>
                <p><strong data-i18n="modal_note_title">Recomendación PETKO</strong> <span id="modal-nota"></span></p>
            </div>

            <div class="modal-producto__precio-fila">
                <span class="modal-producto__precio" id="modal-precio"></span>
                <span class="tarjeta__stock" id="modal-stock"></span>
            </div>

            <div class="modal-producto__acciones">
                <button type="button" class="btn-agregar js-add-cart" id="modal-agregar"></button>
                <button type="button" class="btn-apartar" id="modal-apartar"></button>
            </div>

            <p class="modal-producto__politica">
                <i class="ph ph-info" aria-hidden="true"></i>
                <span data-i18n="reserve_policy">Política PETKO: apartamos 1 pieza por producto durante 48 horas en sucursal, sin anticipo. Pagas al recoger.</span>
            </p>

            <ul class="modal-producto__beneficios">
                <li><i class="ph-fill ph-first-aid-kit" aria-hidden="true"></i> <span data-i18n="benefit_1">Asesoría veterinaria</span></li>
                <li><i class="ph-fill ph-storefront" aria-hidden="true"></i> <span data-i18n="benefit_2">Recoge en sucursal</span></li>
                <li><i class="ph-fill ph-seal-check" aria-hidden="true"></i> <span data-i18n="benefit_3">Producto original</span></li>
            </ul>
        </div>
    </div>
</dialog>

<!-- CHECKOUT: elegir cómo finalizar el pedido -->
<dialog class="modal-producto modal-checkout" id="modal-checkout" aria-labelledby="checkout-titulo" aria-describedby="checkout-sub">
    <div class="modal-producto__caja checkout__caja">
        <button type="button" class="modal-producto__cerrar" id="checkout-cerrar" aria-label="Cerrar" data-i18n-aria="checkout_close">
            <i class="ph-bold ph-x"></i>
        </button>

        <header class="checkout__cabecera">
            <span class="checkout__sello" aria-hidden="true"><i class="ph-fill ph-paw-print"></i></span>
            <span class="modal-producto__categoria" data-i18n="checkout_eyebrow">Finalizar pedido</span>
            <h2 class="checkout__titulo" id="checkout-titulo" data-i18n="checkout_title">¿Cómo deseas finalizar tu pedido?</h2>
            <p class="checkout__sub" id="checkout-sub" data-i18n="checkout_sub">Elige la opción que mejor te acomode; en ambas generamos tu ticket verificado y apartamos por 48 h.</p>
        </header>

        <div class="checkout__resumen">
            <div class="checkout__miniaturas" id="checkout-miniaturas" aria-hidden="true"></div>
            <span class="checkout__articulos" id="checkout-articulos"></span>
            <span class="checkout__total">
                <span data-i18n="checkout_total">Total</span>
                <strong id="checkout-total">$0.00</strong>
            </span>
        </div>

        <?php if (!empty($sucursalesDisponibles)): ?>
        <div style="margin: 12px 0 4px;">
            <label for="checkout-sucursal" style="display:block; font-size:12.5px; font-weight:700; margin-bottom:6px; opacity:.85;">
                <i class="ph-fill ph-storefront"></i> Sucursal de recolección (vigencia 48 h):
            </label>
            <select id="checkout-sucursal" style="width:100%; padding:10px 12px; border-radius:10px; border:1px solid rgba(0,0,0,.15); font:inherit; background:var(--surface, #fff); color:inherit;">
                <?php foreach ($sucursalesDisponibles as $suc): ?>
                    <option value="<?= (int)$suc['id_sucursal'] ?>"><?= htmlspecialchars($suc['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="checkout__opciones">
            <button type="button" class="checkout__opcion checkout__opcion--pagar" data-checkout="pagar">
                <span class="checkout__opcion-icono" aria-hidden="true"><i class="ph ph-credit-card"></i></span>
                <span class="checkout__opcion-texto">
                    <span class="checkout__opcion-titulo" data-i18n="pay_now_title">Pagar ahora</span>
                    <span class="checkout__opcion-metodo" data-i18n="pay_now_method">Tarjeta / Transferencia · Ya pagado</span>
                    <span class="checkout__opcion-desc" data-i18n="pay_now_desc">Pago verificado, apartado asegurado por 48 h y ticket real al momento.</span>
                </span>
                <i class="ph ph-caret-right checkout__opcion-flecha" aria-hidden="true"></i>
            </button>

            <button type="button" class="checkout__opcion checkout__opcion--sucursal" data-checkout="sucursal">
                <span class="checkout__opcion-icono" aria-hidden="true"><i class="ph ph-storefront"></i></span>
                <span class="checkout__opcion-texto">
                    <span class="checkout__opcion-titulo" data-i18n="pay_store_title">Apartar y pagar en sucursal</span>
                    <span class="checkout__opcion-metodo" data-i18n="pay_store_method">Efectivo · En espera de pago</span>
                    <span class="checkout__opcion-desc" data-i18n="pay_store_desc">Te guardamos tus productos 48 h; pagas en efectivo al recoger.</span>
                </span>
                <i class="ph ph-caret-right checkout__opcion-flecha" aria-hidden="true"></i>
            </button>
        </div>

        <p class="modal-producto__politica checkout__nota">
            <i class="ph ph-shield-check" aria-hidden="true"></i>
            <span data-i18n="checkout_secure">Tus datos están protegidos. Recibirás un folio único verificado en base de datos.</span>
        </p>
    </div>
</dialog>

<!-- TICKET DE COMPRA (solo visible tras verificación en BD) -->
<dialog class="modal-producto modal-ticket" id="modal-ticket" aria-labelledby="ticket-titulo" aria-describedby="ticket-sub">
    <div class="modal-producto__caja ticket" id="ticket-caja" data-modo="pagar">
        <button type="button" class="modal-producto__cerrar" id="ticket-cerrar" aria-label="Cerrar" data-i18n-aria="checkout_close">
            <i class="ph-bold ph-x"></i>
        </button>

        <div class="ticket__parte ticket__parte--arriba">
            <div class="ticket__confeti" aria-hidden="true">
                <i class="ph-fill ph-paw-print" style="--x: -150px; --y: -70px; --r: -40deg; --d: 0s;"></i>
                <i class="ph-fill ph-paw-print" style="--x: -95px; --y: -120px; --r: 25deg; --d: 0.06s;"></i>
                <i class="ph-fill ph-paw-print" style="--x: -40px; --y: -140px; --r: -15deg; --d: 0.12s;"></i>
                <i class="ph-fill ph-paw-print" style="--x: 40px; --y: -135px; --r: 30deg; --d: 0.04s;"></i>
                <i class="ph-fill ph-paw-print" style="--x: 100px; --y: -110px; --r: -25deg; --d: 0.1s;"></i>
                <i class="ph-fill ph-paw-print" style="--x: 150px; --y: -60px; --r: 45deg; --d: 0.08s;"></i>
                <i class="ph-fill ph-sparkle" style="--x: -125px; --y: -15px; --r: 60deg; --d: 0.14s;"></i>
                <i class="ph-fill ph-sparkle" style="--x: 125px; --y: -20px; --r: -60deg; --d: 0.16s;"></i>
            </div>

            <header class="ticket__cabecera">
                <span class="ticket__estado-icono" aria-hidden="true"><i class="ph-bold ph-check" id="ticket-icono"></i></span>
                <span class="ticket__marca"><i class="ph-fill ph-paw-print" aria-hidden="true"></i> PETKO</span>
                <h2 class="ticket__titulo" id="ticket-titulo"></h2>
                <p class="ticket__sub" id="ticket-sub"></p>
            </header>

            <dl class="ticket__datos">
                <div>
                    <dt data-i18n="ticket_folio">Folio</dt>
                    <dd class="ticket__folio" id="ticket-folio"></dd>
                </div>
                <div>
                    <dt data-i18n="ticket_date">Fecha</dt>
                    <dd id="ticket-fecha"></dd>
                </div>
                <div>
                    <dt data-i18n="ticket_method">Método</dt>
                    <dd id="ticket-metodo"></dd>
                </div>
                <div>
                    <dt data-i18n="ticket_status">Estado</dt>
                    <dd><span class="ticket__badge" id="ticket-estado"></span></dd>
                </div>
            </dl>
        </div>

        <div class="ticket__parte ticket__parte--abajo">
            <ul class="ticket__items" id="ticket-items"></ul>

            <div class="ticket__total">
                <span data-i18n="checkout_total">Total</span>
                <strong id="ticket-total">$0.00</strong>
            </div>

            <div class="ticket__codigo" aria-hidden="true">
                <span class="ticket__barras"></span>
                <span id="ticket-codigo"></span>
            </div>

            <div class="ticket__mascota">
                <img id="ticket-perrito" src="../../img/perrito_feliz.png" alt="">
                <p data-i18n="dog_thanks">¡Guau! Gracias por tu pedido 🐾</p>
            </div>

            <button type="button" class="btn-agregar ticket__seguir" id="ticket-seguir">
                <i class="ph ph-paw-print" aria-hidden="true"></i>
                <span data-i18n="ticket_continue">Seguir comprando</span>
            </button>
        </div>
    </div>
</dialog>

<div class="gato-zona gato-zona--izq" aria-hidden="true">
    <img src="../../img/gato_izq.png?v=<?= (int) @filemtime(__DIR__ . '/../../img/gato_izq.png') ?>" alt="" class="gato-chismoso">
</div>
<div class="gato-zona gato-zona--der" aria-hidden="true">
    <img src="../../img/gato_der.png?v=<?= (int) @filemtime(__DIR__ . '/../../img/gato_der.png') ?>" alt="" class="gato-chismoso">
</div>

<div class="perrito-flotante" id="perrito-flotante" data-tono="feliz" aria-hidden="true">
    <img src="../../img/perrito_clinica.png" alt="" class="perrito-flotante__avatar">
    <div class="perrito-flotante__burbuja">
        <p id="perrito-flotante-texto"></p>
        <i class="ph-fill ph-paw-print perrito-globo__huella"></i>
    </div>
</div>

<div id="toast-container" class="toast-container" aria-live="polite" aria-atomic="true"></div>

<script>
    window.PETKO_SESION = <?= json_encode([
        'autenticado' => $usuarioAutenticado,
        'loginUrl'    => $rutaLogin,
        'csrf'        => $csrfToken,
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?>;

    // Sincronizar localStorage con el stock real de MySQL ANTES de que catalogo.js cargue el carrito
    (function sincronizarCarritoAntesDeCatalogo() {
        const activos = new Map();
        document.querySelectorAll('.tarjeta[data-id]').forEach(card => {
            const id = Number(card.dataset.id);
            if (id > 0) activos.set(id, Number(card.dataset.stock || 0));
        });

        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (!key || !/cart|carrito|petko/i.test(key) || /apartado|reserve/i.test(key)) continue;
            try {
                const parsed = JSON.parse(localStorage.getItem(key));
                if (Array.isArray(parsed)) {
                    let modificado = false;
                    const limpio = parsed.reduce((acc, item) => {
                        const id = Number(item?.id_producto ?? item?.id ?? 0);
                        if (!id) {
                            acc.push(item);
                            return acc;
                        }
                        if (activos.size > 0 && !activos.has(id)) {
                            modificado = true;
                            return acc;
                        }
                        if (activos.has(id)) {
                            const stockReal = activos.get(id);
                            if (stockReal <= 0) {
                                modificado = true;
                                return acc;
                            }
                            const cant = Number(item.cantidad ?? item.qty ?? item.quantity ?? 1);
                            if (Number(item.stock) !== stockReal || cant > stockReal) {
                                modificado = true;
                                const ajustada = Math.min(cant, stockReal);
                                item.stock = stockReal;
                                if ('cantidad' in item) item.cantidad = ajustada;
                                if ('qty' in item) item.qty = ajustada;
                                if ('quantity' in item) item.quantity = ajustada;
                            }
                        }
                        acc.push(item);
                        return acc;
                    }, []);
                    if (modificado || limpio.length !== parsed.length) {
                        localStorage.setItem(key, JSON.stringify(limpio));
                    }
                }
            } catch (_) {}
        }
    })();
</script>
<script src="../../js/catalogo.js?v=<?= (int) @filemtime(__DIR__ . '/../../js/catalogo.js') ?>"></script>

<!-- Puente seguro de verificación en BD: impide tickets falsos y limpia IDs obsoletos -->
<script>
(function () {
    let transaccionEnCurso = false;
    let ticketVerificado = false;
    let ultimoCarritoSnapshot = [];

    // Mapa de productos activos cargados actualmente desde MySQL en el catálogo
    function obtenerProductosActivosCatalogo() {
        const mapa = new Map();
        document.querySelectorAll('.tarjeta[data-id]').forEach(card => {
            const id = Number(card.dataset.id);
            if (id > 0) {
                mapa.set(id, {
                    id_producto: id,
                    nombre: (card.dataset.nombre || '').trim().toLowerCase(),
                    stock: Number(card.dataset.stock || 0)
                });
            }
        });
        return mapa;
    }

    // Purgar IDs obsoletos y ajustar cantidades/stock en localStorage contra el stock real de MySQL
    function limpiarIdsObsoletosLocalStorage(idsInvalidos = []) {
        const activos = obtenerProductosActivosCatalogo();
        const setInvalidos = new Set(idsInvalidos.map(Number));

        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (!key || !/cart|carrito|petko/i.test(key) || /apartado|reserve/i.test(key)) continue;
            try {
                const raw = localStorage.getItem(key);
                const parsed = JSON.parse(raw);
                if (Array.isArray(parsed)) {
                    let huboCambios = false;
                    const filtrado = parsed.reduce((acc, item) => {
                        const id = Number(item?.id_producto ?? item?.id ?? 0);
                        if (!id) {
                            acc.push(item);
                            return acc;
                        }
                        if (setInvalidos.has(id) || (activos.size > 0 && !activos.has(id))) {
                            huboCambios = true;
                            return acc;
                        }
                        if (activos.has(id)) {
                            const stockReal = activos.get(id).stock;
                            if (stockReal <= 0) {
                                huboCambios = true;
                                return acc;
                            }
                            const cantActual = Number(item.cantidad ?? item.qty ?? item.quantity ?? 1);
                            if (Number(item.stock) !== stockReal || cantActual > stockReal) {
                                huboCambios = true;
                                const nuevaCant = Math.min(cantActual, stockReal);
                                item.stock = stockReal;
                                if ('cantidad' in item) item.cantidad = nuevaCant;
                                if ('qty' in item) item.qty = nuevaCant;
                                if ('quantity' in item) item.quantity = nuevaCant;
                            }
                        }
                        acc.push(item);
                        return acc;
                    }, []);

                    if (huboCambios || filtrado.length !== parsed.length) {
                        localStorage.setItem(key, JSON.stringify(filtrado));
                    }
                }
            } catch (_) {}
        }
    }

    // Obtener los items REALES del carrito sin arrastrar basura vieja de localStorage
    function obtenerItemsCarritoReales() {
        const activos = obtenerProductosActivosCatalogo();
        const itemsDOM = [];

        // 1. Prioridad absoluta: leer los artículos visibles en #cart-items
        document.querySelectorAll('#cart-items > *').forEach(row => {
            let id = Number(row.dataset?.id || row.querySelector('[data-id]')?.dataset?.id || 0);

            if (!id) {
                const textoNodo = (row.textContent || '').toLowerCase();
                for (const [idAct, info] of activos.entries()) {
                    if (info.nombre && textoNodo.includes(info.nombre)) {
                        id = idAct;
                        break;
                    }
                }
            }

            if (id > 0 && (activos.size === 0 || activos.has(id))) {
                const qtyEl = row.querySelector('input[type="number"], [data-qty], .cart-item__qty, .qty, .cantidad');
                let qty = 1;
                if (qtyEl) {
                    qty = Number(qtyEl.value || qtyEl.dataset?.qty || qtyEl.textContent.replace(/\D+/g, '')) || 1;
                } else {
                    const matchCant = (row.textContent || '').match(/(?:x|×)\s*(\d+)/i);
                    if (matchCant) qty = Number(matchCant[1]) || 1;
                }
                itemsDOM.push({ id_producto: id, cantidad: Math.max(1, qty) });
            }
        });

        if (itemsDOM.length > 0) {
            return itemsDOM;
        }

        // 2. Respaldo: leer localStorage filtrando estrictamente contra productos activos
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (!key || !/cart|carrito/i.test(key) || /apartado|reserve/i.test(key)) continue;
            try {
                const parsed = JSON.parse(localStorage.getItem(key));
                if (Array.isArray(parsed) && parsed.length > 0) {
                    const validos = parsed
                        .map(x => ({
                            id_producto: Number(x.id_producto ?? x.id ?? 0),
                            cantidad: Math.max(1, Number(x.cantidad ?? x.qty ?? x.quantity ?? 1))
                        }))
                        .filter(x => x.id_producto > 0 && (activos.size === 0 || activos.has(x.id_producto)));
                    if (validos.length > 0) return validos;
                }
            } catch (_) {}
        }

        return [];
    }

    // Guardia de seguridad en #modal-ticket: cierra inmediatamente cualquier apertura falsa
    const modalTicket = document.getElementById('modal-ticket');
    if (modalTicket) {
        const obsTicket = new MutationObserver(() => {
            if (modalTicket.open && !ticketVerificado) {
                modalTicket.close();
            }
        });
        obsTicket.observe(modalTicket, { attributes: true, attributeFilter: ['open'] });
    }

    function renderizarTicketVerificado(data) {
        if (!modalTicket) return;
        const caja = document.getElementById('ticket-caja');
        if (caja) caja.dataset.modo = data.modo;

        const titulo = document.getElementById('ticket-titulo');
        const sub = document.getElementById('ticket-sub');
        const folio = document.getElementById('ticket-folio');
        const fecha = document.getElementById('ticket-fecha');
        const metodo = document.getElementById('ticket-metodo');
        const estado = document.getElementById('ticket-estado');
        const itemsUl = document.getElementById('ticket-items');
        const totalEl = document.getElementById('ticket-total');
        const codigoEl = document.getElementById('ticket-codigo');

        const isEn = (window.PetkoPrefs && PetkoPrefs.lang() === 'en');
        const esPago = data.modo === 'pagar';
        if (titulo) titulo.textContent = isEn
            ? (esPago ? 'Purchase & reservation confirmed!' : 'Reservation confirmed for 48 hrs!')
            : (esPago ? '¡Compra y apartado confirmados!' : '¡Apartado confirmado por 48 h!');
        if (sub) sub.textContent = isEn
            ? (esPago ? 'Payment verified and product(s) reserved for 48 hours.' : 'Product(s) reserved for 48 hours for in-branch payment.')
            : data.msg;
        if (folio) folio.textContent = data.folio;
        if (fecha) fecha.textContent = data.fecha;
        if (metodo) metodo.textContent = isEn
            ? (esPago ? 'Online payment' : 'In-branch payment (Cash)')
            : (esPago ? 'Pago en línea' : 'Pago en sucursal (Efectivo)');
        if (estado) estado.textContent = isEn
            ? (esPago ? 'PAID · RESERVED 48H' : 'PENDING PAYMENT (48H)')
            : (esPago ? 'PAGADO · APARTADO 48H' : 'PENDIENTE DE PAGO (48H)');
        if (totalEl) totalEl.textContent = '$' + Number(data.total || 0).toFixed(2);
        if (codigoEl) codigoEl.textContent = data.folio;

        if (itemsUl && Array.isArray(data.items)) {
            itemsUl.innerHTML = data.items.map(it => {
                const subTotal = (Number(it.precio) * Number(it.cantidad)).toFixed(2);
                return `<li><span>${it.cantidad}x ${it.nombre}</span><strong>$${subTotal}</strong></li>`;
            }).join('');
        }

        ticketVerificado = true;
        modalTicket.showModal();
        setTimeout(() => { ticketVerificado = false; }, 600);
    }

    function vaciarCarritoLocal() {
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key && /cart|carrito/i.test(key) && !/apartado|reserve/i.test(key)) {
                localStorage.setItem(key, '[]');
            }
        }
        const cartItemsEl = document.getElementById('cart-items');
        if (cartItemsEl) cartItemsEl.innerHTML = '';
        const countEl = document.getElementById('cart-count');
        if (countEl) {
            countEl.textContent = '0';
            countEl.classList.add('is-empty');
        }
        const totalEl = document.getElementById('cart-total-price');
        if (totalEl) totalEl.textContent = '$0.00';
    }

    async function procesarEnServidor(items, modo) {
        if (!window.PETKO_SESION || !window.PETKO_SESION.autenticado) {
            await Swal.fire({
                icon: 'info',
                title: 'Inicia sesión',
                text: 'Debes iniciar sesión para apartar o comprar productos.',
                confirmButtonText: 'Ir a iniciar sesión'
            });
            window.location.href = window.PETKO_SESION?.loginUrl || '../../../inicioSesion.php';
            return;
        }

        if (transaccionEnCurso) return;
        transaccionEnCurso = true;

        const modalCheckoutEl = document.getElementById('modal-checkout');

        try {
            // Si eligió "Pagar ahora", mostrar confirmación encima del modal de checkout
            if (modo === 'pagar') {
                const isEn = (window.PetkoPrefs && PetkoPrefs.lang() === 'en');
                const conf = await Swal.fire({
                    target: (modalCheckoutEl && modalCheckoutEl.open) ? modalCheckoutEl : 'body',
                    icon: 'question',
                    title: isEn ? 'Confirm online payment' : 'Confirmar pago en línea',
                    text: isEn
                        ? 'Your payment will be verified and your products will be reserved for 48 hours as PAID.'
                        : 'Se verificará el cargo y tus productos quedarán apartados por 48 horas como YA PAGADOS.',
                    showCancelButton: true,
                    confirmButtonText: isEn ? 'Confirm payment' : 'Confirmar pago',
                    cancelButtonText: isEn ? 'Cancel' : 'Cancelar'
                });
                if (!conf.isConfirmed) return;
            }

            const selectSuc = document.getElementById('checkout-sucursal');
            const idSucursal = selectSuc ? Number(selectSuc.value) : 1;

            const resp = await fetch('catalogo.php?action=registrar_apartado', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.PETKO_SESION.csrf || ''
                },
                body: JSON.stringify({
                    items: items,
                    modo: modo,
                    id_sucursal: idSucursal
                })
            });

            const data = await resp.json();

            if (!resp.ok || !data.ok) {
                if (Array.isArray(data.invalid_ids) && data.invalid_ids.length > 0) {
                    limpiarIdsObsoletosLocalStorage(data.invalid_ids);
                }
                throw new Error(data.msg || 'No se pudo completar la operación en la base de datos.');
            }

            // Actualizar stock visual en las tarjetas del catálogo y en localStorage
            if (data.stocks) {
                Object.entries(data.stocks).forEach(([idProd, nuevoStock]) => {
                    const card = document.querySelector(`.tarjeta[data-id="${idProd}"]`);
                    if (card) {
                        card.dataset.stock = String(nuevoStock);
                        const btnAdd = card.querySelector('.js-add-cart');
                        if (btnAdd) btnAdd.dataset.stock = String(nuevoStock);

                        const stockSpan = card.querySelector('.tarjeta__stock');
                        if (stockSpan) {
                            if (Number(nuevoStock) <= 0) {
                                stockSpan.className = 'tarjeta__stock stock-none';
                                stockSpan.textContent = 'Agotado';
                                card.querySelectorAll('button').forEach(b => {
                                    if (!b.classList.contains('js-ver-detalle')) b.disabled = true;
                                });
                            } else {
                                stockSpan.textContent = `Disponibles: ${nuevoStock}`;
                            }
                        }
                    }
                });
                limpiarIdsObsoletosLocalStorage();
            }

            // Cerrar modal de checkout y carrito lateral
            if (modalCheckoutEl && modalCheckoutEl.open) modalCheckoutEl.close();
            document.getElementById('cart-panel')?.classList.remove('is-open', 'open');
            document.getElementById('cart-overlay')?.classList.remove('is-open', 'open');

            if (modo === 'pagar' || items.length > 1 || ultimoCarritoSnapshot.length > 0) {
                vaciarCarritoLocal();
                ultimoCarritoSnapshot = [];
            }

            renderizarTicketVerificado(data);

        } catch (err) {
            if (modalCheckoutEl && modalCheckoutEl.open) modalCheckoutEl.close();
            Swal.fire({
                icon: 'error',
                title: 'Operación no completada',
                text: err.message || 'Error al verificar el producto en la base de datos.'
            });
        } finally {
            transaccionEnCurso = false;
        }
    }

    // Limpiar basura vieja en localStorage apenas carga la página
    document.addEventListener('DOMContentLoaded', () => {
        limpiarIdsObsoletosLocalStorage();
    });

    // Capturar snapshot limpio del carrito al abrir checkout
    document.addEventListener('click', function (e) {
        if (e.target.closest('#btn-checkout')) {
            ultimoCarritoSnapshot = obtenerItemsCarritoReales();
        }
    }, true);

    // Interceptar EN FASE DE CAPTURA antes que catalogo.js para impedir excesos de stock y tickets falsos
    document.addEventListener('click', function (e) {
        // 0A. Evitar agregar al carrito por encima del stock real de MySQL
        const btnAddCart = e.target.closest('.js-add-cart');
        if (btnAddCart) {
            const idProd = Number(btnAddCart.dataset.id || btnAddCart.closest('.tarjeta')?.dataset.id || 0);
            const card = document.querySelector(`.tarjeta[data-id="${idProd}"]`);
            const stockMax = card ? Number(card.dataset.stock || 0) : Number(btnAddCart.dataset.stock || 0);
            const enCarrito = obtenerItemsCarritoReales().find(it => it.id_producto === idProd)?.cantidad || 0;

            if (stockMax <= 0 || enCarrito + 1 > stockMax) {
                e.preventDefault();
                e.stopImmediatePropagation();
                Swal.fire({
                    icon: 'warning',
                    title: 'Stock máximo alcanzado',
                    text: stockMax <= 0
                        ? 'Este producto ya está agotado.'
                        : `Solo quedan ${stockMax} unidad(es) disponibles de este producto.`
                });
                return;
            }
        }

        // 0B. Evitar subir cantidad (+) dentro del carrito por encima del stock real de MySQL
        const btnPlusCart = e.target.closest('#cart-items button, #cart-items [data-action="plus"], #cart-items .qty-plus');
        if (btnPlusCart && btnPlusCart.closest('#cart-items')) {
            const textoBtn = (btnPlusCart.textContent || btnPlusCart.dataset.action || '').trim();
            if (textoBtn === '+' || /plus|inc|add/i.test(textoBtn)) {
                const row = btnPlusCart.closest('#cart-items > *');
                const activos = obtenerProductosActivosCatalogo();
                let idProd = Number(row?.dataset?.id || row?.querySelector('[data-id]')?.dataset?.id || 0);
                if (!idProd && row) {
                    const txt = (row.textContent || '').toLowerCase();
                    for (const [idAct, info] of activos.entries()) {
                        if (info.nombre && txt.includes(info.nombre)) {
                            idProd = idAct;
                            break;
                        }
                    }
                }
                if (idProd > 0 && activos.has(idProd)) {
                    const stockMax = activos.get(idProd).stock;
                    const actual = obtenerItemsCarritoReales().find(it => it.id_producto === idProd)?.cantidad || 1;
                    if (actual >= stockMax) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        Swal.fire({
                            icon: 'warning',
                            title: 'Stock máximo alcanzado',
                            text: `Solo hay ${stockMax} disponible(s) en inventario.`
                        });
                        return;
                    }
                }
            }
        }

        // 1. Botón "Apartar producto" (tarjeta o modal de producto)
        const btnApartar = e.target.closest('.js-apartar, #modal-apartar');
        if (btnApartar) {
            e.preventDefault();
            e.stopImmediatePropagation();
            const card = btnApartar.closest('.tarjeta');
            const idProd = Number(
                btnApartar.dataset.id ||
                card?.dataset.id ||
                document.getElementById('modal-agregar')?.dataset.id ||
                0
            );
            if (idProd > 0) {
                const modalProd = document.getElementById('modal-producto');
                if (modalProd && modalProd.open) modalProd.close();
                procesarEnServidor([{ id_producto: idProd, cantidad: 1 }], 'sucursal');
            }
            return;
        }

        // 2. Opciones del modal Checkout ("Pagar ahora" o "Apartar y pagar en sucursal")
        const btnCheckoutOpcion = e.target.closest('[data-checkout]');
        if (btnCheckoutOpcion) {
            e.preventDefault();
            e.stopImmediatePropagation();
            const modo = btnCheckoutOpcion.dataset.checkout === 'pagar' ? 'pagar' : 'sucursal';
            const items = ultimoCarritoSnapshot.length > 0 ? ultimoCarritoSnapshot : obtenerItemsCarritoReales();
            if (items.length === 0) {
                Swal.fire({ icon: 'warning', title: 'Carrito vacío', text: 'Agrega al menos un producto disponible antes de continuar.' });
                return;
            }
            procesarEnServidor(items, modo);
        }
    }, true);

    function actualizarTextoMisCompras(lang) {
        const span = document.querySelector('[data-custom-i18n="nav_my_purchases"]');
        if (span) {
            span.textContent = (lang === 'en') ? 'My purchases' : 'Mis compras';
        }
    }

    if (window.PetkoPrefs) {
        actualizarTextoMisCompras(PetkoPrefs.lang());
        PetkoPrefs.onLang(actualizarTextoMisCompras);
    }
})();
</script>
</body>
</html>