<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// inicioSesion.php guarda al usuario en $_SESSION['usuario'] con su id_usuario.
$usuarioAutenticado = !empty($_SESSION['usuario']['id_usuario']);
$rutaLogin = '../../../inicioSesion.php';

// Conexión PDO básica para XAMPP local
$dbHost = 'localhost';
$dbName = 'kion';
$dbUser = 'root';
$dbPass = '';

$pdo = null;
$errorConsulta = null;

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    $errorConsulta = 'Fallo al conectar con la base de datos.';
}

$categoriaSeleccionada = isset($_GET['categoria']) ? (string) $_GET['categoria'] : null;
$productos = [];
$productosCatalogo = [];
$detallesProducto = [];
$categoriasDisponibles = [];

if ($pdo !== null) {
    try {
        $catStmt = $pdo->query("SELECT nombre FROM categorias ORDER BY nombre ASC");
        while ($row = $catStmt->fetch(PDO::FETCH_ASSOC)) {
            $categoriasDisponibles[] = $row['nombre'];
        }

        if (empty($categoriasDisponibles)) {
            $categoriasDisponibles = ['Medicamentos', 'Alimentos', 'Antiparasitarios', 'Higiene', 'Accesorios'];
        }

        // Consulta exacta solicitada, sin modificaciones.
        $sql = "SELECT p.id_producto, p.nombre, p.precio, c.nombre AS categoria, COALESCE(i.existencias, 10) AS stock FROM productos p LEFT JOIN categorias c ON p.id_categoria = c.id_categoria LEFT JOIN inventarios i ON p.id_producto = i.id_producto WHERE p.estado = 'ACTIVO'";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $totalCatalogo = count($productos);
        // Se pintan todos los productos para que JS filtre por categoría
        // sin recargar; $productos queda como la selección inicial.
        $productosCatalogo = $productos;

        if ($categoriaSeleccionada !== null && !in_array($categoriaSeleccionada, $categoriasDisponibles, true)) {
            $categoriaSeleccionada = null;
        }

        // El filtro por categoría se aplica aquí, en PHP, para no tocar
        // la consulta SQL pedida.
        if ($categoriaSeleccionada !== null) {
            $productos = array_values(array_filter($productos, function ($p) use ($categoriaSeleccionada) {
                return ($p['categoria'] ?? null) === $categoriaSeleccionada;
            }));
        }
    } catch (PDOException $e) {
        $errorConsulta = 'No pudimos cargar los productos.';
    }

    // Consulta aparte y opcional para la vista ampliada: si falla, el
    // catálogo sigue funcionando sin descripciones.
    try {
        $detStmt = $pdo->query("SELECT id_producto, codigo, descripcion FROM productos");
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PETKO · Catálogo</title>
    <script>
        (function () {
            try {
                var escala = parseFloat(localStorage.getItem('kion-font-scale'));
                if (escala >= 0.9 && escala <= 1.3) {
                    document.documentElement.style.setProperty('--font-scale', escala);
                    if (escala >= 1.2) document.documentElement.classList.add('texto-grande');
                }
            } catch (e) {}
        })();
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;1,9..144,500;1,9..144,600&family=Fredoka:wght@500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/bold/style.css">
    <link rel="stylesheet" href="../../css/catalogo.css?v=<?= (int) @filemtime(__DIR__ . '/../../css/catalogo.css') ?>">
</head>
<body class="catalogo-app">

<header class="topbar">
    <div class="topbar__marca">
        <a href="../modulos/home/home.php" class="topbar__logo" aria-label="PETKO · Inicio">
            <span class="topbar__logo-icono" aria-hidden="true"><i class="ph-fill ph-paw-print"></i></span>
            <span class="topbar__logo-texto">PET<span>KO</span></span>
        </a>
        <span class="topbar__subtitulo" data-i18n="topbar_sub">Veterinaria y Farmacia · cuidado con patitas.</span>
        <span class="topbar__badge"><i class="ph-fill ph-heartbeat"></i> Clínica</span>
    </div>

    <div class="topbar__acciones">
        <div class="a11y" id="a11y">
            <button type="button" class="btn-icon a11y__toggle" id="a11y-toggle"
                    aria-expanded="false" aria-controls="a11y-grupo"
                    aria-label="Tamaño de texto" title="Tamaño de texto" data-i18n-aria="a11y_title">
                <i class="ph ph-text-aa"></i>
            </button>
            <div class="a11y__grupo" id="a11y-grupo" role="group" aria-label="Tamaño de texto" data-i18n-aria="a11y_title">
                <button type="button" class="a11y__btn a11y__btn--menos" data-a11y="menos"
                        aria-label="Reducir texto" title="Reducir texto" data-i18n-aria="a11y_less">A<span aria-hidden="true">−</span></button>
                <button type="button" class="a11y__btn a11y__btn--base" data-a11y="base"
                        aria-label="Tamaño de texto por defecto" title="Tamaño de texto por defecto" data-i18n-aria="a11y_default">A</button>
                <button type="button" class="a11y__btn a11y__btn--mas" data-a11y="mas"
                        aria-label="Aumentar texto" title="Aumentar texto" data-i18n-aria="a11y_more">A<span aria-hidden="true">+</span></button>
                <span class="a11y__nivel" id="a11y-nivel" aria-live="polite">100%</span>
            </div>
        </div>
        <button class="btn-icon" id="btnTheme" title="Modo Oscuro / Claro"><i class="ph ph-moon"></i></button>
        <button class="btn-icon" id="btnLang" title="English / Español"><i class="ph ph-translate"></i></button>
        
        <?php 
        // Determinar la ruta del dashboard dinámicamente con las rutas relativas correctas
        $rutaDashboard = $rutaLogin; // Por defecto si no hay sesión
        $textoPanel = 'Iniciar Sesión';
        $iconoPanel = 'ph-sign-in';
        $claveI18nPanel = 'nav_login';
        
        if (isset($_SESSION['usuario'])) {
            $textoPanel = 'Panel';
            $iconoPanel = 'ph-squares-four';
            $claveI18nPanel = 'nav_dashboard';
            $rolSesion = strtolower($_SESSION['usuario']['rol'] ?? '');
            
            if (strpos($rolSesion, 'admin') !== false) {
                $rutaDashboard = '../modulos/home/dashboard.php';
            } elseif (strpos($rolSesion, 'gerente') !== false) {
                $rutaDashboard = '../modulos/home/dashboard_gerente.php';
            } else {
                // Cliente regular o usuario sin dashboard administrativo
                $rutaDashboard = '../modulos/home/dashboard_usuario.php'; 
            }
        }
        ?>
        
        <a href="<?= htmlspecialchars($rutaDashboard, ENT_QUOTES, 'UTF-8') ?>" class="btn-nav">
            <i class="ph <?= $iconoPanel ?>"></i> <span data-i18n="<?= $claveI18nPanel ?>"><?= $textoPanel ?></span>
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
                <p>No hay productos registrados en esta categoría.</p>
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
                    
                    // La consulta exacta ya no trae imagen_url ni descripcion,
                    // así que resolvemos la imagen con el mismo esquema de
                    // respaldo de siempre y usamos un texto genérico.
                    $imgSrc = $producto['imagen_url'] ?? '';
                    if (!$imgSrc) {
                        $rutaAutomatica = "../../img/{$idProd}.jpg";
                        if (isset($rutasImagenesAntiguas[$idProd])) {
                            $imgSrc = $rutasImagenesAntiguas[$idProd];
                        } elseif (file_exists($rutaAutomatica)) {
                            $imgSrc = $rutaAutomatica;
                        } else {
                            $imgSrc = "https://placehold.co/400x400/E8F6EE/0B2545?text=" . urlencode($producto['categoria'] ?? 'PETKO');
                        }
                    }
                    $detalle     = $detallesProducto[$idProd] ?? [];
                    $codigo      = htmlspecialchars((string) ($detalle['codigo'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $descripcion = htmlspecialchars((string) ($detalle['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8');
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
                            <button type="button" class="btn-apartar js-apartar"<?= $agotado ? ' disabled' : '' ?>>
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
            <p class="checkout__sub" id="checkout-sub" data-i18n="checkout_sub">Elige la opción que mejor te acomode; en ambas generamos tu ticket al instante.</p>
        </header>

        <div class="checkout__resumen">
            <div class="checkout__miniaturas" id="checkout-miniaturas" aria-hidden="true"></div>
            <span class="checkout__articulos" id="checkout-articulos"></span>
            <span class="checkout__total">
                <span data-i18n="checkout_total">Total</span>
                <strong id="checkout-total">$0.00</strong>
            </span>
        </div>

        <div class="checkout__opciones">
            <button type="button" class="checkout__opcion checkout__opcion--pagar" data-checkout="pagar">
                <span class="checkout__opcion-icono" aria-hidden="true"><i class="ph ph-credit-card"></i></span>
                <span class="checkout__opcion-texto">
                    <span class="checkout__opcion-titulo" data-i18n="pay_now_title">Pagar ahora</span>
                    <span class="checkout__opcion-metodo" data-i18n="pay_now_method">Tarjeta / Transferencia</span>
                    <span class="checkout__opcion-desc" data-i18n="pay_now_desc">Pago seguro en línea y tu ticket de compra al momento.</span>
                </span>
                <i class="ph ph-caret-right checkout__opcion-flecha" aria-hidden="true"></i>
            </button>

            <button type="button" class="checkout__opcion checkout__opcion--sucursal" data-checkout="sucursal">
                <span class="checkout__opcion-icono" aria-hidden="true"><i class="ph ph-storefront"></i></span>
                <span class="checkout__opcion-texto">
                    <span class="checkout__opcion-titulo" data-i18n="pay_store_title">Apartar y pagar en sucursal</span>
                    <span class="checkout__opcion-metodo" data-i18n="pay_store_method">Efectivo</span>
                    <span class="checkout__opcion-desc" data-i18n="pay_store_desc">Te guardamos tus productos 48 h; pagas en efectivo al recoger.</span>
                </span>
                <i class="ph ph-caret-right checkout__opcion-flecha" aria-hidden="true"></i>
            </button>
        </div>

        <p class="modal-producto__politica checkout__nota">
            <i class="ph ph-shield-check" aria-hidden="true"></i>
            <span data-i18n="checkout_secure">Tus datos están protegidos. Recibirás un folio único para cualquier aclaración.</span>
        </p>
    </div>
</dialog>

<!-- TICKET DE COMPRA (simulado) -->
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
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="../../js/catalogo.js?v=<?= (int) @filemtime(__DIR__ . '/../../js/catalogo.js') ?>"></script>
</body>
</html>
