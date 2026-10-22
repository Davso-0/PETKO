<?php
// MODO SIN BASE DE DATOS
session_start();

if (!isset($_SESSION['ventas'])) {
    $_SESSION['ventas'] = [];
}

// BACKEND API - MANEJO DE PETICIONES AJAX / FETCH
$action = $_GET['action'] ?? '';

// API 1: OBTENER HISTORIAL DE COBROS Y DATOS DE CAJA
if ($action === 'get_cobros') {
    header('Content-Type: application/json');
    try {
        $cobros = $_SESSION['ventas'];
        echo json_encode(['ok' => true, 'data' => $cobros]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// API 2: BUSCAR PRODUCTO POR CÓDIGO DE BARRAS O ID
elseif ($action === 'buscar_producto') {
    header('Content-Type: application/json');
    $codigo = trim($_GET['codigo'] ?? '');
    try {
        $productos = [
            ['id_producto' => 1, 'codigo_barras' => '750100000001', 'nombre' => 'Producto Demo 1', 'precio' => 25.50, 'stock' => 50],
            ['id_producto' => 2, 'codigo_barras' => '750100000002', 'nombre' => 'Producto Demo 2', 'precio' => 100.00, 'stock' => 20],
            ['id_producto' => 3, 'codigo_barras' => '750100000003', 'nombre' => 'Servicio Técnico Base', 'precio' => 150.00, 'stock' => 100]
        ];

        $producto = null;
        foreach ($productos as $p) {
            if ($p['codigo_barras'] === $codigo || (string)$p['id_producto'] === $codigo) {
                $producto = $p;
                break;
            }
        }

        if ($producto) {
            echo json_encode(['ok' => true, 'data' => $producto]);
        } else {
            echo json_encode(['ok' => false, 'msg' => 'Producto no encontrado']);
        }
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// API 3: REGISTRAR LA VENTA Y DETALLES EN LA BD
elseif ($action === 'registrar_venta_completa') {
    header('Content-Type: application/json');
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (!$data || empty($data['items'])) {
        echo json_encode(['ok' => false, 'msg' => 'El carrito está vacío']);
        exit;
    }

    try {
        $cliente = $data['cliente_nombre'] ?? 'Cliente General';
        $metodo = $data['metodo_pago'] ?? 'EFECTIVO';
        $total = $data['total'] ?? 0;
        $idVenta = count($_SESSION['ventas']) + 1;

        $nuevaVenta = [
            'id_venta' => $idVenta,
            'cliente_nombre' => $cliente,
            'monto' => $total,
            'metodo_pago' => $metodo,
            'fecha_pago' => date('Y-m-d H:i:s')
        ];

        array_unshift($_SESSION['ventas'], $nuevaVenta);

        echo json_encode(['ok' => true, 'msg' => 'Venta registrada con éxito', 'id_venta' => $idVenta]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'msg' => 'Error al guardar la venta: ' . $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KION - Módulo de Cobranza</title>
    <!-- ESTILOS CSS -->
    <link rel="stylesheet" href="../../css/cobranza.css">
    <link rel="stylesheet" href="../../css/style.css">
</head>
<body>
    <div class="layout-wrapper">
        <!-- BARRA LATERAL (SIDEBAR) -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <img src="../../img/logo_branco.png" alt="KION Logo" class="sidebar-logo-img">
                <button class="btn-toggle" id="toggleSidebarBtn">☰</button>
            </div>
            <nav class="sidebar-nav">
                <a href="#" class="nav-item active" data-target="seccion-pos">
                    <img src="../../img/icono_perro.png" alt="POS" class="nav-icon">
                    <span class="nav-text">Punto de Venta</span>
                </a>
                <a href="#" class="nav-item" data-target="seccion-historial">
                    <img src="../../img/icono_gato.png" alt="Historial" class="nav-icon">
                    <span class="nav-text">Historial de Pagos</span>
                </a>
                <a href="#" class="nav-item" data-target="seccion-caja">
                    <img src="../../img/icono_toro.png" alt="Caja" class="nav-icon">
                    <span class="nav-text">Corte de Caja</span>
                </a>
            </nav>
            <div class="sidebar-footer">
                <a href="../modulos/home/home.php" class="nav-item exit-item">
                    <span class="exit-icon">➔</span>
                    <span class="nav-text">Volver al Menú</span>
                </a>
            </div>
        </aside>

        <!-- CONTENIDO PRINCIPAL -->
        <main class="main-content">
            <header class="cobranza-header">
                <div class="brand">
                    <img src="../../img/logo_st_naranja.png" alt="ST Logo">
                    <h2 id="titulo-seccion">Punto de Venta</h2>
                </div>
            </header>

            <!-- SECCIÓN 1: PUNTO DE VENTA (POS) -->
            <section id="seccion-pos" class="modulo-seccion active">
                <div class="pos-layout">
                    <div class="pos-main card-panel">
                        <div class="scanner-box">
                            <label for="inputCodigoBarras"><b>Escanear Producto o Código</b></label>
                            <div class="scanner-input-group">
                                <input type="text" id="inputCodigoBarras" class="form-input" placeholder="Ingresa o escanea el código de barras..." autofocus>
                                <button type="button" id="btnAgregarPrueba" class="btn-secondary">+ Producto Demo</button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table-cobranza table-pos">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th style="text-align: center;">Cantidad</th>
                                        <th style="text-align: right;">Precio Unitario</th>
                                        <th style="text-align: right;">Subtotal</th>
                                        <th style="text-align: center;">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbCarrito">
                                    <tr>
                                        <td colspan="5" class="empty-cart-msg">Escanea un producto para comenzar...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="pos-total-bar">
                            <span>Total a Cobrar:</span>
                            <strong id="txtTotalVenta">$0.00</strong>
                        </div>
                    </div>

                    <div class="pos-sidebar card-panel">
                        <h3>Detalle del Cobro</h3>
                        <form id="formFinalizarVenta">
                            <div class="form-group">
                                <label for="clienteNombre">Nombre del Cliente</label>
                                <input type="text" id="clienteNombre" class="form-input" placeholder="Cliente General">
                            </div>

                            <div class="form-group">
                                <label for="metodoPago">Método de Pago</label>
                                <select id="metodoPago" class="form-select">
                                    <option value="EFECTIVO">Efectivo</option>
                                    <option value="TARJETA">Tarjeta de Débito/Crédito</option>
                                </select>
                            </div>

                            <div class="summary-box">
                                <div class="summary-row">
                                    <span>Artículos:</span>
                                    <strong id="txtCantArticulos">0</strong>
                                </div>
                                <div class="summary-row total-row">
                                    <span>Total:</span>
                                    <strong id="txtTotalPanel">$0.00</strong>
                                </div>
                            </div>

                            <button type="submit" class="btn-primary btn-pay">Completar Cobro</button>
                            <button type="button" id="btnVaciarCarrito" class="btn-danger">Cancelar / Vaciar</button>
                        </form>
                    </div>
                </div>
            </section>

            <!-- SECCIÓN 2: HISTORIAL DE PAGOS -->
            <section id="seccion-historial" class="modulo-seccion">
                <div class="card-panel">
                    <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h3>Historial de Ventas</h3>
                        <input type="text" id="filtroHistorial" class="form-input" placeholder="Buscar cliente o folio..." style="width: 250px;">
                    </div>
                    <div class="table-responsive">
                        <table class="table-cobranza">
                            <thead>
                                <tr>
                                    <th>Folio</th>
                                    <th>Cliente</th>
                                    <th>Monto Total</th>
                                    <th>Método de Pago</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody id="tbCobros">
                                <tr>
                                    <td colspan="5" style="text-align:center;">Cargando registros...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- SECCIÓN 3: CORTE DE CAJA -->
            <section id="seccion-caja" class="modulo-seccion">
                <div class="card-panel">
                    <h3>Corte de Caja Diario</h3>
                    <div class="caja-summary">
                        <div class="caja-row">
                            <span>Ventas en Efectivo:</span>
                            <strong id="totalEfectivoCaja">$0.00</strong>
                        </div>
                        <div class="caja-row">
                            <span>Ventas con Tarjeta:</span>
                            <strong id="totalTarjetaCaja">$0.00</strong>
                        </div>
                        <div class="caja-row total">
                            <span>Total Recaudado:</span>
                            <strong id="totalGeneralCaja">$0.00</strong>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- JAVASCRIPT DEL MÓDULO -->
    <script src="../../js/cobranza.js"></script>
</body>
</html>