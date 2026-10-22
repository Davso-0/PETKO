<?php
declare(strict_types=1);

ob_start();

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

$id_usuario_actual = 0;
if (is_array($_SESSION['usuario'] ?? null)) {
    $id_usuario_actual = (int)($_SESSION['usuario']['id_usuario'] ?? $_SESSION['usuario']['id'] ?? 0);
} else {
    $id_usuario_actual = (int)($_SESSION['usuario'] ?? 0);
}

$admin = null;
if (isset($pdo) && $pdo instanceof PDO && $id_usuario_actual > 0) {
    $stA = $pdo->prepare("SELECT u.id_usuario, u.nombre, u.apellido, u.estado, r.nombre AS rol
                           FROM usuarios u LEFT JOIN roles r ON r.id_rol = u.id_rol
                           WHERE u.id_usuario = ?");
    $stA->execute([$id_usuario_actual]);
    $admin = $stA->fetch(PDO::FETCH_ASSOC) ?: null;
}
$esAdmin = $admin && stripos((string)($admin['rol'] ?? ''), 'admin') !== false && ($admin['estado'] ?? '') !== 'INACTIVO';
$nombreAdmin = trim(($admin['nombre'] ?? 'Administrador') . ' ' . ($admin['apellido'] ?? ''));

// API AJAX
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $out = ['ok' => false, 'msg' => 'Acción desconocida'];
    try {
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            throw new RuntimeException('[ERROR DE SISTEMA] No fue posible conectar con la base de datos.');
        }
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if (!$esAdmin) {
            throw new RuntimeException('[ERROR DE SISTEMA] Tu sesión no tiene permisos de administrador.');
        }

        if ($action === 'get_metrics') {
            $ts = (int)$pdo->query("SELECT COUNT(*) FROM sucursales")->fetchColumn();
            $tu = (int)$pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
            $tp = (int)$pdo->query("SELECT COUNT(*) FROM productos")->fetchColumn();
            $inv_data = $pdo->query("SELECT COALESCE(SUM(i.existencias),0) AS piezas, COALESCE(SUM(i.existencias * p.precio),0) AS valor FROM inventarios i JOIN productos p ON p.id_producto = i.id_producto")->fetch(PDO::FETCH_ASSOC);
            $ti = (int)($inv_data['piezas'] ?? 0);
            $valor_inv = (float)($inv_data['valor'] ?? 0);
            $rv = $pdo->query("SELECT COUNT(*) AS n, COALESCE(SUM(total),0) AS t FROM ventas WHERE DATE(fecha_hora)=CURDATE()")->fetch();
            $out = ['ok' => true, 'data' => [ 'ts' => $ts, 'tu' => $tu, 'tp' => $tp, 'ti' => $ti, 'valor_inv' => $valor_inv, 'vh' => (int)($rv['n'] ?? 0), 'ih' => (float)($rv['t'] ?? 0) ]];

        } elseif ($action === 'get_sucursales') {
            $stmt = $pdo->query("SELECT id_sucursal, nombre, direccion, telefono, contacto, estado FROM sucursales ORDER BY id_sucursal");
            $out = ['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];

        } elseif ($action === 'create_sucursal') {
            $nombre = trim($_POST['nombre'] ?? ''); $dir = trim($_POST['direccion'] ?? '');
            $tel = trim($_POST['telefono'] ?? ''); $cont = trim($_POST['contacto'] ?? '');
            if ($nombre === '') throw new Exception('[ERROR DE USUARIO] El nombre de la sucursal es obligatorio.');
            $pdo->prepare("INSERT INTO sucursales (nombre,direccion,telefono,contacto,estado) VALUES(?,?,?,?,'ACTIVA')")->execute([$nombre, $dir, $tel, $cont]);
            $out = ['ok' => true, 'msg' => 'Sucursal registrada con éxito.'];

        } elseif ($action === 'update_sucursal') {
            $id = (int)($_POST['id'] ?? 0); $nombre = trim($_POST['nombre'] ?? ''); $tel = trim($_POST['telefono'] ?? '');
            if (!$id || !$nombre) throw new Exception('[ERROR DE USUARIO] Faltan datos obligatorios.');
            $est = ($_POST['estado'] ?? 'ACTIVA') === 'INACTIVA' ? 'INACTIVA' : 'ACTIVA';
            $pdo->prepare("UPDATE sucursales SET nombre=?,direccion=?,telefono=?,contacto=?,estado=? WHERE id_sucursal=?")
                ->execute([$nombre, trim($_POST['direccion'] ?? ''), $tel, trim($_POST['contacto'] ?? ''), $est, $id]);
            $out = ['ok' => true, 'msg' => 'Datos de la sucursal actualizados.'];

        } elseif ($action === 'get_gerentes') {
            $st = $pdo->query("SELECT u.id_usuario,u.nombre,u.apellido,u.correo,u.estado,r.nombre AS rol,COALESCE(s.nombre,'-- Ninguna --') AS sucursal,u.id_sucursal,u.id_rol FROM usuarios u LEFT JOIN roles r ON r.id_rol=u.id_rol LEFT JOIN sucursales s ON s.id_sucursal=u.id_sucursal WHERE r.nombre NOT LIKE '%cliente%' AND r.nombre NOT LIKE '%usuario%' ORDER BY u.id_usuario");
            $out = ['ok' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)];

        } elseif ($action === 'delete_gerente') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id === $id_usuario_actual) throw new Exception('[ERROR DE USUARIO] No puedes eliminar tu propio usuario.');
            $pdo->prepare("DELETE FROM usuarios WHERE id_usuario=?")->execute([$id]);
            $out = ['ok' => true, 'msg' => 'Registro eliminado.'];

        } elseif ($action === 'get_roles') {
            try { $data = $pdo->query("SELECT id_rol, nombre FROM roles ORDER BY id_rol")->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) { $data = []; }
            $out = ['ok' => true, 'data' => $data];

        } elseif ($action === 'get_categorias') {
            try { $data = $pdo->query("SELECT id_categoria, nombre FROM categorias ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) { $data = []; }
            $out = ['ok' => true, 'data' => $data];

        } elseif ($action === 'get_productos') {
            $q = trim($_GET['q'] ?? '');
            $sql = "SELECT p.id_producto,p.codigo,p.nombre,p.descripcion,p.imagen_url,p.id_categoria,p.precio,p.estado,COALESCE(c.nombre, 'Sin categoría') AS categoria FROM productos p LEFT JOIN categorias c ON c.id_categoria=p.id_categoria";
            $params = [];
            if ($q !== '') { $sql .= " WHERE p.codigo LIKE ? OR p.nombre LIKE ?"; $params[] = "%$q%"; $params[] = "%$q%"; }
            $sql .= " ORDER BY p.nombre ASC LIMIT 200";
            $st = $pdo->prepare($sql); $st->execute($params);
            $out = ['ok' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)];

        } elseif ($action === 'create_producto' || $action === 'update_producto') {
            $id = (int)($_POST['id'] ?? 0); $codigo = trim($_POST['codigo'] ?? ''); $nombre = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? ''); $categoria = (int)($_POST['id_categoria'] ?? 0);
            $precio = (float)str_replace(',', '.', $_POST['precio'] ?? '0');
            $imagenUrl = trim($_POST['imagen_url'] ?? '');
            $estado = ($_POST['estado'] ?? 'ACTIVO') === 'INACTIVO' ? 'INACTIVO' : 'ACTIVO';
            $sucs = $_POST['sucursales'] ?? [];
            if ($codigo === '' || $nombre === '') throw new Exception('[ERROR DE USUARIO] El código y el nombre son obligatorios.');

            $pdo->beginTransaction();
            try {
                if ($action === 'create_producto') {
                    $pdo->prepare("INSERT INTO productos(codigo,nombre,descripcion,id_categoria,precio,estado,imagen_url) VALUES(?,?,?,?,?,?,?)")
                        ->execute([$codigo, $nombre, $descripcion ?: null, $categoria > 0 ? $categoria : null, $precio, $estado, $imagenUrl ?: null]);
                    $new_id = (int)$pdo->lastInsertId();
                    if (!empty($sucs)) {
                        $st_inv = $pdo->prepare("INSERT INTO inventarios(id_producto, id_sucursal, existencias, stock_minimo) VALUES(?,?,0,5)");
                        foreach ($sucs as $sid) $st_inv->execute([$new_id, $sid]);
                    }
                    $out = ['ok' => true, 'msg' => 'Producto registrado exitosamente.'];
                } else {
                    $pdo->prepare("UPDATE productos SET codigo=?,nombre=?,descripcion=?,id_categoria=?,precio=?,estado=?,imagen_url=? WHERE id_producto=?")
                        ->execute([$codigo, $nombre, $descripcion ?: null, $categoria > 0 ? $categoria : null, $precio, $estado, $imagenUrl ?: null, $id]);
                    if (!empty($sucs)) {
                        $st_check = $pdo->prepare("SELECT COUNT(*) FROM inventarios WHERE id_producto=? AND id_sucursal=?");
                        $st_inv = $pdo->prepare("INSERT INTO inventarios(id_producto, id_sucursal, existencias, stock_minimo) VALUES(?,?,0,5)");
                        foreach ($sucs as $sid) {
                            $st_check->execute([$id, $sid]);
                            if ($st_check->fetchColumn() == 0) $st_inv->execute([$id, $sid]);
                        }
                    }
                    $out = ['ok' => true, 'msg' => 'Producto actualizado correctamente.'];
                }
                $pdo->commit();
            } catch (Exception $e) { $pdo->rollBack(); throw $e; }

        } elseif ($action === 'delete_producto') {
            $id = (int)($_POST['id'] ?? 0);
            $pdo->prepare("DELETE FROM inventarios WHERE id_producto=?")->execute([$id]);
            $pdo->prepare("DELETE FROM productos WHERE id_producto=?")->execute([$id]);
            $out = ['ok' => true, 'msg' => 'Producto eliminado permanentemente.'];

        } elseif ($action === 'get_producto_detalle') {
            $id = (int)($_GET['id'] ?? 0);
            $st = $pdo->prepare("SELECT s.id_sucursal, s.nombre AS sucursal, i.existencias FROM inventarios i JOIN sucursales s ON s.id_sucursal = i.id_sucursal WHERE i.id_producto = ?");
            $st->execute([$id]);
            $out = ['ok' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)];

        } elseif ($action === 'get_sucursales_simple') {
            $out = ['ok' => true, 'data' => $pdo->query("SELECT id_sucursal,nombre FROM sucursales WHERE estado='ACTIVA' ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC)];

        } elseif ($action === 'create_gerente' || $action === 'update_gerente') {
            $id = (int)($_POST['id'] ?? 0); $nom = trim($_POST['nombre'] ?? ''); $cor = trim($_POST['correo'] ?? '');
            $pas = trim($_POST['password'] ?? ''); $rol = (int)($_POST['id_rol'] ?? 0);
            $suc = ($_POST['id_sucursal'] ?? '') !== '' ? (int)$_POST['id_sucursal'] : null;
            if ($nom === '' || $cor === '') throw new Exception('[ERROR DE USUARIO] Nombre y correo son obligatorios.');

            $st_r = $pdo->prepare("SELECT nombre FROM roles WHERE id_rol = ?");
            $st_r->execute([$rol]);
            $is_admin = (stripos($st_r->fetchColumn() ?: '', 'admin') !== false);
            if (!$is_admin && !$suc) throw new Exception('[ERROR DE USUARIO] Los Gerentes y Cajeros DEBEN tener una sucursal.');
            if ($is_admin) $suc = null;

            $st_mail = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE correo=? AND id_usuario<>?");
            $st_mail->execute([$cor, $id]);
            if ($st_mail->fetchColumn() > 0) throw new Exception('Ya existe otro usuario con ese correo.');

            if ($action === 'create_gerente') {
                if ($pas === '') throw new Exception('La contraseña es obligatoria.');
                $pdo->prepare("INSERT INTO usuarios(nombre,apellido,correo,password_hash,id_rol,id_sucursal,estado)VALUES(?,?,?,?,?,?,'ACTIVO')")
                    ->execute([$nom, trim($_POST['apellido'] ?? ''), $cor, password_hash($pas, PASSWORD_BCRYPT), $rol, $suc]);
                $out = ['ok' => true, 'msg' => 'Personal registrado.'];
            } else {
                $est = ($_POST['estado'] ?? 'ACTIVO') === 'INACTIVO' ? 'INACTIVO' : 'ACTIVO';
                $pdo->prepare("UPDATE usuarios SET nombre=?,apellido=?,correo=?,id_rol=?,id_sucursal=?,estado=? WHERE id_usuario=?")
                    ->execute([$nom, trim($_POST['apellido'] ?? ''), $cor, $rol, $suc, $est, $id]);
                if ($pas !== '') $pdo->prepare("UPDATE usuarios SET password_hash=? WHERE id_usuario=?")->execute([password_hash($pas, PASSWORD_BCRYPT), $id]);
                $out = ['ok' => true, 'msg' => 'Personal actualizado.'];
            }

        } elseif ($action === 'get_inventarios') {
            $q = trim($_GET['q'] ?? ''); $sid = (int)($_GET['sucursal'] ?? 0);
            $sql = "SELECT i.id_inventario,p.nombre AS producto,p.codigo,s.nombre AS sucursal,i.existencias,i.stock_minimo,p.precio,CASE WHEN i.existencias<=0 THEN 'AGOTADO' WHEN i.existencias<=i.stock_minimo THEN 'BAJO' ELSE 'OK' END AS nivel FROM inventarios i JOIN productos p ON p.id_producto=i.id_producto JOIN sucursales s ON s.id_sucursal=i.id_sucursal WHERE 1=1";
            $params = [];
            if ($q !== '') { $sql .= " AND (p.nombre LIKE ? OR p.codigo LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; }
            if ($sid > 0) { $sql .= " AND i.id_sucursal=?"; $params[] = $sid; }
            $sql .= " ORDER BY i.existencias ASC LIMIT 200";
            $st = $pdo->prepare($sql); $st->execute($params);
            $out = ['ok' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)];

        } elseif ($action === 'get_ventas') {
            $sid = (int)($_GET['sucursal'] ?? 0); $desde = trim($_GET['desde'] ?? ''); $hasta = trim($_GET['hasta'] ?? '');
            $sql = "SELECT v.id_venta,v.fecha_hora,v.total,v.estado,s.nombre AS sucursal,mp.nombre AS metodo_pago,CONCAT(u.nombre,' ',COALESCE(u.apellido,'')) AS empleado FROM ventas v JOIN sucursales s ON s.id_sucursal=v.id_sucursal JOIN metodos_pago mp ON mp.id_metodo_pago=v.id_metodo_pago JOIN usuarios u ON u.id_usuario=v.id_usuario WHERE 1=1";
            $params = [];
            if ($sid > 0) { $sql .= " AND v.id_sucursal=?"; $params[] = $sid; }
            if ($desde !== '') { $sql .= " AND DATE(v.fecha_hora)>=?"; $params[] = $desde; }
            if ($hasta !== '') { $sql .= " AND DATE(v.fecha_hora)<=?"; $params[] = $hasta; }
            $sql .= " ORDER BY v.fecha_hora DESC LIMIT 200";
            $st = $pdo->prepare($sql); $st->execute($params);
            $out = ['ok' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)];
        } elseif ($action === 'get_venta_detalle') {
            $id = (int)($_GET['id'] ?? 0);
            $st = $pdo->prepare("SELECT dv.cantidad, dv.precio_unitario, dv.subtotal, COALESCE(p.nombre, 'Producto no disponible') AS producto FROM detalle_ventas dv LEFT JOIN productos p ON p.id_producto = dv.id_producto WHERE dv.id_venta = ?");
            $st->execute([$id]);
            $out = ['ok' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)];
        }
    } catch (Throwable $e) {
        $out = ['ok' => false, 'msg' => $e->getMessage()];
    }
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!$esAdmin) {
    ob_end_clean();
    echo '<!doctype html><meta charset="utf-8"><body style="font-family:sans-serif;padding:60px;text-align:center;"><h2>Acceso restringido</h2><a href="home.php">Volver al inicio</a></body>';
    exit;
}

$ts = 0; $tu = 0; $tp = 0; $vh = 0; $ih = 0.0; $ti = 0; $valor_inv = 0; $v7 = []; $mp = [];
try {
    $ts = (int)$pdo->query("SELECT COUNT(*) FROM sucursales")->fetchColumn();
    $tu = (int)$pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
    $tp = (int)$pdo->query("SELECT COUNT(*) FROM productos")->fetchColumn();
    $inv_data = $pdo->query("SELECT COALESCE(SUM(i.existencias),0) AS piezas, COALESCE(SUM(i.existencias * p.precio),0) AS valor FROM inventarios i JOIN productos p ON p.id_producto = i.id_producto")->fetch(PDO::FETCH_ASSOC);
    $ti = (int)($inv_data['piezas'] ?? 0); $valor_inv = (float)($inv_data['valor'] ?? 0);
    $rv = $pdo->query("SELECT COUNT(*) AS n,COALESCE(SUM(total),0) AS t FROM ventas WHERE DATE(fecha_hora)=CURDATE()")->fetch();
    $vh = (int)($rv['n'] ?? 0); $ih = (float)($rv['t'] ?? 0);
    for ($i = 6; $i >= 0; $i--) {
        $fd = date('Y-m-d', strtotime("-{$i} days")); $fl = date('d/m', strtotime("-{$i} days"));
        $r = $pdo->prepare("SELECT COUNT(*) AS n,COALESCE(SUM(total),0) AS t FROM ventas WHERE DATE(fecha_hora)=?"); $r->execute([$fd]); $rr = $r->fetch();
        $v7['labels'][] = $fl; $v7['ingresos'][] = (float)($rr['t'] ?? 0);
    }
    $mp = $pdo->query("SELECT mp.nombre AS metodo,COUNT(v.id_venta) AS total_ventas FROM metodos_pago mp LEFT JOIN ventas v ON v.id_metodo_pago=mp.id_metodo_pago GROUP BY mp.id_metodo_pago,mp.nombre")->fetchAll();
} catch (PDOException $e) {}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KION · Panel de Administración</title>

    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <style>
        :root {
            --bg-app: oklch(96.5% 0.014 72); --bg-surface: oklch(100% 0 0); --bg-panel: oklch(99% 0.006 75);
            --ink: oklch(24% 0.028 50); --ink-soft: oklch(42% 0.030 48); --ink-faint: oklch(58% 0.022 50); --border-soft: oklch(89% 0.016 65);
            --coffee-main: oklch(32% 0.040 50); --coffee-light: oklch(91% 0.018 60);
            --coffee-gradient: linear-gradient(155deg, oklch(40% 0.045 48), oklch(26% 0.035 52));
            --success: oklch(34% 0.06 152); --success-bg: oklch(94% 0.035 152);
            --danger: oklch(50% 0.14 25); --danger-bg: oklch(95% 0.035 20);
            --warning: oklch(42% 0.09 70); --warning-bg: oklch(95% 0.045 90);
            --sky: oklch(38% 0.07 240); --sky-bg: oklch(95% 0.022 235);
            --font-display: 'Fraunces', serif; --font-body: 'Plus Jakarta Sans', sans-serif;
            --shadow-sm: 0 4px 12px rgba(0,0,0,0.05); --shadow-md: 0 8px 24px rgba(0,0,0,0.08); --shadow-lg: 0 16px 40px rgba(0,0,0,0.12);
            --radius: 14px; --sidebar-w: 264px; --sidebar-w-collapsed: 84px;
            --ease-expo: cubic-bezier(0.16, 1, 0.3, 1);
        }
        body.dark-mode {
            --bg-app: oklch(20% 0.01 250); --bg-surface: oklch(25% 0.01 250); --bg-panel: oklch(23% 0.01 250);
            --ink: oklch(95% 0.01 250); --ink-soft: oklch(75% 0.01 250); --ink-faint: oklch(62% 0.01 250); --border-soft: oklch(35% 0.01 250);
            --coffee-main: oklch(80% 0.03 60); --coffee-light: oklch(30% 0.02 250);
            --coffee-gradient: linear-gradient(155deg, oklch(40% 0.045 48), oklch(20% 0.035 52));
            --success-bg: oklch(25% 0.04 152); --danger-bg: oklch(30% 0.06 20); --warning-bg: oklch(30% 0.05 85); --sky-bg: oklch(26% 0.03 235);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body { font-family: var(--font-body); background: var(--bg-app); color: var(--ink); transition: background .3s ease, color .3s ease; }
        button { font: inherit; cursor: pointer; border: none; background: none; color: inherit; }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration: .001ms !important; transition-duration: .001ms !important; } }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes wave { 0%,60%,100% { transform: rotate(0deg); } 10% { transform: rotate(16deg); } 20% { transform: rotate(-8deg); } 30% { transform: rotate(16deg); } 40% { transform: rotate(-4deg); } }
        .view-section { display: none; animation: fadeIn .38s var(--ease-expo) forwards; }
        .view-section.active { display: block; }
        .wave { display: inline-block; transform-origin: 70% 70%; animation: wave 2.2s ease-in-out .3s 1; }

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

        .nav-section-title { font-size: 11px; text-transform: uppercase; letter-spacing: .8px; color: var(--ink-faint); font-weight: 700; padding: 4px 12px 6px; white-space: nowrap; }
        .nav-menu { display: flex; flex-direction: column; gap: 6px; }
        .nav-btn { display: flex; align-items: center; gap: 12px; padding: 11px 14px; border-radius: var(--radius); font-weight: 600; font-size: 14px; color: var(--ink-soft); transition: background .2s var(--ease-expo), color .2s ease, transform .2s var(--ease-expo); white-space: nowrap; }
        .nav-btn i { font-size: 19px; flex: none; }
        .nav-btn:hover { background: var(--bg-app); color: var(--ink); transform: translateX(2px); }
        .nav-btn.active { background: var(--coffee-gradient); color: #fff; box-shadow: var(--shadow-sm); }

        .sidebar-scrim { display: none; position: fixed; inset: 0; background: rgba(20,16,12,.45); z-index: 39; opacity: 0; transition: opacity .3s ease; }

        .topbar { height: 70px; display: flex; justify-content: space-between; align-items: center; padding: 0 32px; border-bottom: 1px solid var(--border-soft); background: var(--bg-panel); position: sticky; top: 0; z-index: 30; backdrop-filter: blur(10px); }
        .topbar-title-block .eyebrow { font-size: 11.5px; color: var(--ink-faint); font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
        .topbar-title-block .title-main { font-family: var(--font-display); font-size: 20px; font-weight: 600; }
        .hamburger-btn { display: none; width: 38px; height: 38px; border-radius: 10px; border: 1px solid var(--border-soft); background: var(--bg-surface); align-items: center; justify-content: center; font-size: 18px; }
        .topbar-actions { display: flex; gap: 12px; align-items: center; }
        .icon-btn { width: 40px; height: 40px; border-radius: 50%; display: grid; place-items: center; background: var(--bg-app); border: 1px solid var(--border-soft); font-size: 19px; transition: background .2s ease, color .2s ease, transform .15s var(--ease-expo); }
        .icon-btn:hover { background: var(--coffee-light); color: var(--coffee-main); transform: translateY(-1px); }
        .user-profile { display: flex; align-items: center; gap: 10px; font-weight: 600; padding-left: 14px; border-left: 1px solid var(--border-soft); font-size: 14px; }
        .admin-chip { font-size: 10px; font-weight: 800; letter-spacing: .5px; padding: 3px 8px; border-radius: 20px; background: var(--coffee-light); color: var(--coffee-main); text-transform: uppercase; }

        .content { padding: 32px; max-width: 1440px; margin: 0 auto; }
        .header-row { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; flex-wrap: wrap; gap: 14px; }
        .title { font-family: var(--font-display); font-size: 27px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .subtitle { color: var(--ink-soft); font-size: 14px; margin-top: 4px; }

        .btn-primary { background: var(--coffee-gradient); color: #fff; padding: 10px 20px; border-radius: 10px; font-weight: 700; font-size: 13.5px; box-shadow: var(--shadow-sm); display: inline-flex; align-items: center; gap: 8px; transition: transform .16s var(--ease-expo), box-shadow .2s ease; }
        .btn-primary:hover { box-shadow: var(--shadow-md); transform: translateY(-1px); }
        .btn-ghost { background: var(--bg-surface); border: 1px solid var(--border-soft); color: var(--coffee-main); padding: 10px 18px; border-radius: 10px; font-weight: 700; font-size: 13px; display: inline-flex; align-items: center; gap: 8px; transition: background .2s ease; }
        .btn-ghost:hover { background: var(--coffee-light); }

        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 18px; margin-bottom: 24px; }
        .kpi-card { background: var(--bg-surface); padding: 22px; border-radius: var(--radius); border: 1px solid var(--border-soft); box-shadow: var(--shadow-sm); display: flex; flex-direction: column; gap: 12px; transition: transform .25s var(--ease-expo), box-shadow .25s var(--ease-expo), border-color .25s ease; }
        .kpi-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); border-color: var(--coffee-light); }
        .kpi-top { display: flex; align-items: flex-start; justify-content: space-between; }
        .kpi-card i.kpi-icon { font-size: 22px; color: var(--coffee-main); padding: 11px; background: var(--coffee-light); border-radius: 11px; }
        .kpi-tag { font-size: 10.5px; font-weight: 800; padding: 4px 9px; border-radius: 20px; background: var(--success-bg); color: var(--success); text-transform: uppercase; }
        .kpi-val { font-family: var(--font-display); font-size: 30px; font-weight: 600; }
        .kpi-label { color: var(--ink-soft); font-size: 13px; font-weight: 600; }

        .panel { background: var(--bg-surface); border-radius: var(--radius); border: 1px solid var(--border-soft); padding: 24px; overflow-x: auto; margin-bottom: 22px; transition: background .3s ease, border-color .3s ease; }
        .panel-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
        .panel-title { font-family: var(--font-display); font-size: 17px; font-weight: 600; }
        .panel-sub { font-size: 12px; color: var(--ink-faint); margin-top: 2px; }
        .grid-2col { display: grid; grid-template-columns: 1.3fr 1fr; gap: 20px; }
        .chart-box { position: relative; height: 250px; }
        .donut-wrap { position: relative; height: 220px; display: flex; align-items: center; justify-content: center; }
        .donut-center { position: absolute; text-align: center; pointer-events: none; }
        .donut-center .num { font-family: var(--font-display); font-size: 22px; font-weight: 700; }
        .donut-center .lbl { font-size: 10px; color: var(--ink-faint); font-weight: 700; text-transform: uppercase; }

        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { font-size: 11.5px; text-transform: uppercase; letter-spacing: .4px; color: var(--ink-faint); padding-bottom: 14px; border-bottom: 1px solid var(--border-soft); font-weight: 700; }
        td { padding: 14px 0; border-bottom: 1px solid var(--border-soft); font-weight: 500; vertical-align: middle; }
        tbody tr:hover { background: var(--bg-app); }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11.5px; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; }
        .badge.b-red { background: var(--danger-bg); color: var(--danger); }
        .badge.b-green { background: var(--success-bg); color: var(--success); }
        .badge.b-yellow { background: var(--warning-bg); color: var(--warning); }
        .chip-btn { border: 1px solid var(--border-soft); background: var(--bg-app); color: var(--coffee-main); font-size: 12px; font-weight: 700; padding: 6px 12px; border-radius: 8px; transition: background .18s ease; }
        .chip-btn:hover { background: var(--coffee-main); color: #fff; }
        .icon-x { width: 30px; height: 30px; border-radius: 8px; border: 1px solid var(--border-soft); display: inline-grid; place-items: center; color: var(--ink-soft); transition: background .18s ease; }
        .icon-x:hover { background: var(--danger-bg); color: var(--danger); }
        .row-actions { display: flex; gap: 6px; align-items: center; }

        .form-inline { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
        .search-input, .select-input { padding: 10px 16px; border: 1px solid var(--border-soft); border-radius: 10px; background: var(--bg-app); color: var(--ink); min-width: 200px; font-family: inherit; font-size: 14px; }
        
        /* Modal product preview */
        .swal-preview-img { max-width: 150px; max-height: 150px; border-radius: 8px; border: 1px solid var(--border-soft); object-fit: cover; }

        .stock-list { display: flex; flex-direction: column; gap: 2px; }
        .stock-row { display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 14px; padding: 12px 4px; border-bottom: 1px solid var(--border-soft); }
        .stock-thumb { width: 38px; height: 38px; border-radius: 10px; background: var(--coffee-light); display: grid; place-items: center; color: var(--coffee-main); font-size: 17px; flex: none; }
        .stock-name { font-size: 13.5px; font-weight: 700; }
        .stock-meta { font-size: 11.5px; color: var(--ink-faint); margin-top: 2px; }
        .stock-qty { text-align: right; font-weight: 700; font-size: 13px; }
        .stock-qty small { display: block; font-weight: 500; color: var(--ink-faint); font-size: 10.5px; }

        .info-strip { display: flex; justify-content: space-between; align-items: flex-end; padding-bottom: 15px; margin-bottom: 15px; border-bottom: 1px solid var(--border-soft); }

        .dark-mode .swal2-popup { background: var(--bg-surface); color: var(--ink); }
        .dark-mode .swal2-input, .dark-mode .swal2-select, .dark-mode .swal2-textarea { background: var(--bg-app); color: var(--ink); border-color: var(--border-soft); }

        @media (max-width: 1180px) { .grid-2col { grid-template-columns: 1fr; } }
        @media (max-width: 980px) {
            .app-layout { grid-template-columns: 1fr; }
            .sidebar { position: fixed; left: 0; top: 0; width: 280px; height: 100vh; transform: translateX(-100%); transition: transform .35s var(--ease-expo); box-shadow: var(--shadow-lg); }
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
        <button class="sidebar-collapse-btn" id="btnCollapse" title="Colapsar menú"><i class="ph ph-caret-left"></i></button>
        <div class="brand">
            <i class="ph-fill ph-paw-print"></i>
            <div class="brand-text"><div>KION</div><div class="brand-sub" data-i18n="brand_sub">Administración General</div></div>
        </div>
        <nav class="nav-menu">
            <div class="nav-section-title" data-i18n="nav_section_panel">Panel</div>
            <button class="nav-btn active" data-target="view-general"><i class="ph ph-squares-four"></i><span class="nav-label" data-i18n="nav_general">Resumen General</span></button>
            <div class="nav-section-title" data-i18n="nav_section_gestion">Gestión</div>
            <button class="nav-btn" data-target="view-sucursales"><i class="ph ph-storefront"></i><span class="nav-label" data-i18n="nav_sucursales">Sucursales</span></button>
            <button class="nav-btn" data-target="view-personal"><i class="ph ph-users-three"></i><span class="nav-label" data-i18n="nav_personal">Personal</span></button>
            <button class="nav-btn" data-target="view-inventario"><i class="ph ph-package"></i><span class="nav-label" data-i18n="nav_inventario">Inventario</span></button>
            <button class="nav-btn" data-target="view-productos"><i class="ph ph-tag"></i><span class="nav-label" data-i18n="nav_productos">Productos</span></button>
            <button class="nav-btn" data-target="view-ventas"><i class="ph ph-chart-line-up"></i><span class="nav-label" data-i18n="nav_ventas">Ventas</span></button>
            <div class="nav-section-title" data-i18n="nav_section_nav">Navegación</div>
            <a class="nav-btn" href="home.php"><i class="ph ph-house"></i><span class="nav-label" data-i18n="nav_home">Volver a Home</span></a>
            <a class="nav-btn" href="cerrarSesion.php" style="color:var(--danger)"><i class="ph ph-sign-out"></i><span class="nav-label" data-i18n="nav_logout">Cerrar sesión</span></a>
        </nav>
    </aside>
    <div class="sidebar-scrim" id="sidebarScrim"></div>

    <main>
        <header class="topbar">
            <div style="display:flex; align-items:center; gap:14px;">
                <button class="hamburger-btn" id="btnMobileMenu"><i class="ph ph-list"></i></button>
                <div class="topbar-title-block">
                    <div class="eyebrow" data-i18n="eyebrow_panel">Panel principal</div>
                    <div class="title-main" id="topbarTitle" data-i18n="nav_general">Resumen General</div>
                </div>
            </div>
            <div class="topbar-actions">
                <button class="icon-btn" id="btnLang" title="Switch language / Cambiar idioma"><i class="ph ph-translate"></i></button>
                <button class="icon-btn" id="btnTheme" title="Modo Oscuro"><i class="ph ph-moon"></i></button>
                <div class="user-profile">
                    <i class="ph-fill ph-user-circle" style="font-size:24px; color:var(--coffee-main)"></i>
                    <span><?= htmlspecialchars($nombreAdmin) ?></span>
                    <span class="admin-chip" data-i18n="role_chip">Admin</span>
                </div>
            </div>
        </header>

        <div class="content">

            <!-- RESUMEN GENERAL -->
            <section id="view-general" class="view-section active">
                <div class="header-row">
                    <div><h1 class="title"><span data-i18n="greeting">Hola, administración</span> <span class="wave">👋</span></h1><p class="subtitle" data-i18n="greeting_sub">Este es el pulso operativo de KION al día de hoy.</p></div>
                    <button class="btn-ghost" id="refreshDashboard"><i class="ph ph-arrows-clockwise"></i> <span data-i18n="btn_actualizar">Actualizar</span></button>
                </div>
                <div class="kpi-grid">
                    <div class="kpi-card"><div class="kpi-top"><i class="ph-fill ph-storefront kpi-icon"></i><span class="kpi-tag" data-i18n="kpi_tag_red">Red</span></div><div class="kpi-val" id="metricSucursales"><?= number_format($ts) ?></div><div class="kpi-label" data-i18n="th_sucursales">Sucursales</div></div>
                    <div class="kpi-card"><div class="kpi-top"><i class="ph-fill ph-users-three kpi-icon"></i><span class="kpi-tag" data-i18n="kpi_tag_equipo">Equipo</span></div><div class="kpi-val" id="metricUsuarios"><?= number_format($tu) ?></div><div class="kpi-label" data-i18n="nav_personal">Personal</div></div>
                    <div class="kpi-card"><div class="kpi-top"><i class="ph-fill ph-package kpi-icon"></i><span class="kpi-tag" data-i18n="kpi_tag_catalogo">Catálogo</span></div><div class="kpi-val" id="metricProductos"><?= number_format($tp) ?></div><div class="kpi-label" data-i18n="nav_productos">Productos</div></div>
                    <div class="kpi-card"><div class="kpi-top"><i class="ph-fill ph-money kpi-icon"></i><span class="kpi-tag" data-i18n="kpi_tag_hoy">Hoy</span></div><div class="kpi-val" id="metricIngresos">$<?= number_format($ih, 2) ?></div><div class="kpi-label" id="metricVentasCount"><?= number_format($vh) ?> <span data-i18n="ventas_realizadas">ventas realizadas</span></div></div>
                </div>
                <div class="grid-2col">
                    <div class="panel"><div class="panel-head"><div><h2 class="panel-title" data-i18n="chart_ventas">Ventas (7 días)</h2><p class="panel-sub" data-i18n="chart_ventas_sub">Ingresos consolidados de toda la red</p></div></div><div class="chart-box"><canvas id="salesChart"></canvas></div></div>
                    <div class="panel"><div class="panel-head"><div><h2 class="panel-title" data-i18n="chart_pagos">Métodos de pago</h2><p class="panel-sub" data-i18n="chart_pagos_sub">Distribución histórica</p></div></div><div class="donut-wrap"><canvas id="paymentChart"></canvas><div class="donut-center"><div class="num" id="donutVentasCount"><?= number_format($vh) ?></div><div class="lbl" data-i18n="ventas_lbl">ventas</div></div></div></div>
                </div>
                <div class="grid-2col">
                    <div class="panel"><div class="panel-head"><div><h2 class="panel-title" data-i18n="atencion_title">Atención pendiente</h2><p class="panel-sub" data-i18n="atencion_sub">Productos bajos o agotados</p></div><button class="chip-btn" data-goto="view-inventario" data-i18n="ir_inventario">Ir al inventario</button></div><div id="lowStockList" class="stock-list" data-i18n="cargando">Cargando...</div></div>
                    <div class="panel">
                        <div class="panel-head"><div><h2 class="panel-title" data-i18n="salud_inv_title">Salud del Inventario</h2><p class="panel-sub" data-i18n="salud_inv_sub">Métricas globales de mercancía</p></div></div>
                        <div class="info-strip"><div><div class="kpi-label" data-i18n="total_unidades">Total de Unidades Físicas</div><div class="kpi-val" style="font-size:22px; margin-top:4px;" id="metricInventarioTotal"><?= number_format($ti) ?></div></div><i class="ph-fill ph-cube kpi-icon"></i></div>
                        <div class="info-strip"><div><div class="kpi-label" data-i18n="valor_estimado">Valor Estimado (Precio Venta)</div><div class="kpi-val" style="font-size:22px; margin-top:4px; color:var(--success);" id="metricValorInventario">$<?= number_format($valor_inv, 2) ?></div></div><i class="ph-fill ph-chart-pie-slice kpi-icon"></i></div>
                    </div>
                </div>
            </section>

            <!-- SUCURSALES -->
            <section id="view-sucursales" class="view-section">
                <div class="header-row">
                    <div><h1 class="title"><i class="ph ph-storefront"></i> <span data-i18n="nav_sucursales">Sucursales</span></h1><p class="subtitle" data-i18n="suc_sub">Crea, consulta, modifica y elimina puntos de venta.</p></div>
                    <button class="btn-primary" id="addSucursal"><i class="ph ph-plus"></i> <span data-i18n="btn_add_sucursal">Agregar sucursal</span></button>
                </div>
                <div class="panel"><table><thead><tr><th data-i18n="th_sucursal">Sucursal</th><th data-i18n="th_direccion">Dirección</th><th data-i18n="th_telefono">Teléfono</th><th data-i18n="th_contacto">Contacto</th><th data-i18n="th_estado">Estado</th><th></th></tr></thead><tbody id="sucursalesTable"></tbody></table></div>
            </section>

            <!-- PERSONAL -->
            <section id="view-personal" class="view-section">
                <div class="header-row">
                    <div><h1 class="title"><i class="ph ph-users-three"></i> <span data-i18n="nav_personal">Personal</span></h1><p class="subtitle" data-i18n="per_sub">Registra gerentes/cajeros y asígnalos a una sucursal.</p></div>
                    <button class="btn-primary" id="addPersonal"><i class="ph ph-plus"></i> <span data-i18n="btn_add_personal">Agregar personal</span></button>
                </div>
                <div class="panel"><table><thead><tr><th data-i18n="nav_personal">Personal</th><th data-i18n="th_rol">Rol</th><th data-i18n="th_sucursal">Sucursal</th><th data-i18n="th_estado">Estado</th><th></th></tr></thead><tbody id="personalTable"></tbody></table></div>
            </section>

            <!-- INVENTARIO -->
            <section id="view-inventario" class="view-section">
                <div class="header-row"><div><h1 class="title"><i class="ph ph-package"></i> <span data-i18n="nav_inventario">Inventario</span></h1><p class="subtitle" data-i18n="inv_sub">Consulta las existencias de todas las sucursales.</p></div></div>
                <div class="panel">
                    <div class="form-inline" style="margin-bottom:16px;">
                        <input class="search-input" id="inventorySearch" type="search" data-i18n-placeholder="placeholder_buscar_producto" placeholder="Producto o código...">
                        <select class="select-input" id="inventoryBranch"><option value="" data-i18n="todas_sucursales">Todas las sucursales</option></select>
                    </div>
                    <table><thead><tr><th data-i18n="th_producto">Producto</th><th data-i18n="th_sucursal">Sucursal</th><th data-i18n="th_existencia">Existencia</th><th data-i18n="th_precio">Precio</th><th data-i18n="th_nivel">Nivel</th></tr></thead><tbody id="inventarioTable"></tbody></table>
                </div>
            </section>

            <!-- PRODUCTOS -->
            <section id="view-productos" class="view-section">
                <div class="header-row">
                    <div><h1 class="title"><i class="ph ph-tag"></i> <span data-i18n="prod_title">Catálogo Global</span></h1><p class="subtitle" data-i18n="prod_sub">Registra los productos base de todo el sistema.</p></div>
                    <button class="btn-primary" id="addProducto"><i class="ph ph-plus"></i> <span data-i18n="btn_add_producto">Agregar producto</span></button>
                </div>
                <div class="panel">
                    <div class="form-inline" style="margin-bottom:16px;"><input class="search-input" id="productsSearch" type="search" data-i18n-placeholder="placeholder_buscar_producto" placeholder="Buscar producto o código..."></div>
                    <table><thead><tr><th data-i18n="th_codigo">Código</th><th data-i18n="th_producto">Producto</th><th data-i18n="th_categoria">Categoría</th><th data-i18n="th_precio">Precio</th><th data-i18n="th_estado">Estado</th><th></th></tr></thead><tbody id="productosTable"></tbody></table>
                </div>
            </section>

            <!-- VENTAS -->
            <section id="view-ventas" class="view-section">
                <div class="header-row"><div><h1 class="title"><i class="ph ph-chart-line-up"></i> <span data-i18n="ven_title">Historial de Ventas</span></h1><p class="subtitle" data-i18n="ven_sub">Supervisa las ventas de toda la red.</p></div></div>
                <div class="panel">
                    <div class="form-inline" style="margin-bottom:16px;">
                        <select class="select-input" id="salesBranch"><option value="" data-i18n="todas_sucursales">Todas las sucursales</option></select>
                        <input class="search-input" id="salesFrom" type="date"><input class="search-input" id="salesTo" type="date">
                    </div>
                    <table><thead><tr><th data-i18n="th_folio">Folio</th><th data-i18n="th_fecha">Fecha</th><th data-i18n="th_sucursal">Sucursal</th><th data-i18n="nav_personal">Personal</th><th data-i18n="th_pago">Pago</th><th data-i18n="th_total">Total</th><th data-i18n="th_estado">Estado</th><th></th></tr></thead><tbody id="ventasTable"></tbody></table>
                </div>
            </section>

        </div>
    </main>
</div>

<script>
const endpoint = 'dashboard.php';
const $ = s => document.querySelector(s);
const escapeHtml = v => String(v ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
const money = v => '$' + Number(v || 0).toFixed(2);
const notify = (msg, icon='success') => Swal.fire({ toast:true, position:'top-end', icon, title: msg, showConfirmButton:false, timer:2600 });

async function request(actionStr, options = {}) {
    const r = await fetch(`${endpoint}?action=${actionStr}`, options);
    const text = await r.text();
    let data;
    try { data = JSON.parse(text); } catch(e) { throw new Error('[ERROR DE SISTEMA] Falló la lectura de la respuesta del servidor.'); }
    if (!data.ok) throw new Error(data.msg || 'No fue posible completar la operación.');
    return data;
}
const statusBadge = s => `<span class="badge ${String(s).toUpperCase().includes('INACT') ? 'b-red' : 'b-green'}">${escapeHtml(s)}</span>`;
const empty = (cols, text=null) => `<tr><td colspan="${cols}" style="text-align:center;color:var(--ink-faint);padding:30px">${text || t('sin_registros')}</td></tr>`;

// -------- TEMA --------
const body = document.body, btnTheme = $('#btnTheme');
btnTheme.addEventListener('click', () => {
    body.classList.toggle('dark-mode');
    const icon = btnTheme.querySelector('i');
    icon.classList.toggle('ph-moon'); icon.classList.toggle('ph-sun');
    localStorage.setItem('kion-admin-theme', body.classList.contains('dark-mode') ? 'dark' : 'light');
});
if (localStorage.getItem('kion-admin-theme') === 'dark') { body.classList.add('dark-mode'); btnTheme.querySelector('i').classList.replace('ph-moon','ph-sun'); }

// -------- IDIOMA (ES / EN) --------
const I18N = {
    es: {
        brand_sub: 'Administración General', role_chip: 'Admin', eyebrow_panel: 'Panel principal',
        nav_section_panel: 'Panel', nav_section_gestion: 'Gestión', nav_section_nav: 'Navegación',
        nav_general: 'Resumen General', nav_sucursales: 'Sucursales', nav_personal: 'Personal', nav_inventario: 'Inventario',
        nav_productos: 'Productos', nav_ventas: 'Ventas', nav_home: 'Volver a Home', nav_logout: 'Cerrar sesión',
        greeting: 'Hola, administración', greeting_sub: 'Este es el pulso operativo de KION al día de hoy.', btn_actualizar: 'Actualizar',
        kpi_tag_red: 'Red', kpi_tag_equipo: 'Equipo', kpi_tag_catalogo: 'Catálogo', kpi_tag_hoy: 'Hoy', ventas_realizadas: 'ventas realizadas', ventas_lbl: 'ventas',
        th_sucursales: 'Sucursales', chart_ventas: 'Ventas (7 días)', chart_ventas_sub: 'Ingresos consolidados de toda la red',
        chart_pagos: 'Métodos de pago', chart_pagos_sub: 'Distribución histórica',
        atencion_title: 'Atención pendiente', atencion_sub: 'Productos bajos o agotados', ir_inventario: 'Ir al inventario', cargando: 'Cargando...',
        salud_inv_title: 'Salud del Inventario', salud_inv_sub: 'Métricas globales de mercancía',
        total_unidades: 'Total de Unidades Físicas', valor_estimado: 'Valor Estimado (Precio Venta)',
        suc_sub: 'Crea, consulta, modifica y elimina puntos de venta.', btn_add_sucursal: 'Agregar sucursal',
        th_sucursal: 'Sucursal', th_direccion: 'Dirección', th_telefono: 'Teléfono', th_contacto: 'Contacto', th_estado: 'Estado',
        per_sub: 'Registra gerentes/cajeros y asígnalos a una sucursal.', btn_add_personal: 'Agregar personal', th_rol: 'Rol',
        inv_sub: 'Consulta las existencias de todas las sucursales.', placeholder_buscar_producto: 'Buscar producto o código...',
        todas_sucursales: 'Todas las sucursales', th_producto: 'Producto', th_existencia: 'Existencia', th_precio: 'Precio', th_nivel: 'Nivel',
        prod_title: 'Catálogo Global', prod_sub: 'Registra los productos base de todo el sistema.', btn_add_producto: 'Agregar producto',
        th_codigo: 'Código', th_categoria: 'Categoría',
        ven_title: 'Historial de Ventas', ven_sub: 'Supervisa las ventas de toda la red.',
        th_folio: 'Folio', th_fecha: 'Fecha', th_pago: 'Pago', th_total: 'Total',
        editar: 'Editar', ver_stock: 'Ver stock', ver: 'Ver', guardar: 'Guardar', cancelar: 'Cancelar', si_eliminar: 'Sí, eliminar',
        sin_registros: 'No hay registros.', panel_actualizado: 'Panel actualizado.', inv_saludable: 'Todo el inventario está en niveles saludables.',
        sin_datos: 'Sin datos.', no_asignado: 'No está asignado a ninguna sucursal.',
        modal_prod_new: 'Nuevo Producto', modal_prod_edit: 'Editar Producto', img_url: 'URL de Imagen', desc_lbl: 'Descripción', asign_suc: 'Asignar a sucursales:'
    },
    en: {
        brand_sub: 'General Administration', role_chip: 'Admin', eyebrow_panel: 'Main panel',
        nav_section_panel: 'Overview', nav_section_gestion: 'Management', nav_section_nav: 'Navigation',
        nav_general: 'Dashboard', nav_sucursales: 'Branches', nav_personal: 'Staff', nav_inventario: 'Inventory',
        nav_productos: 'Products', nav_ventas: 'Sales', nav_home: 'Back to Home', nav_logout: 'Log out',
        greeting: 'Hello, admin', greeting_sub: "Here's KION's operating pulse for today.", btn_actualizar: 'Refresh',
        kpi_tag_red: 'Network', kpi_tag_equipo: 'Team', kpi_tag_catalogo: 'Catalog', kpi_tag_hoy: 'Today', ventas_realizadas: 'sales made', ventas_lbl: 'sales',
        th_sucursales: 'Branches', chart_ventas: 'Sales (7 days)', chart_ventas_sub: 'Consolidated revenue across the network',
        chart_pagos: 'Payment methods', chart_pagos_sub: 'Historical distribution',
        atencion_title: 'Needs attention', atencion_sub: 'Low or out-of-stock products', ir_inventario: 'Go to inventory', cargando: 'Loading...',
        salud_inv_title: 'Inventory Health', salud_inv_sub: 'Global merchandise metrics',
        total_unidades: 'Total Physical Units', valor_estimado: 'Estimated Value (Sale Price)',
        suc_sub: 'Create, view, edit and delete store branches.', btn_add_sucursal: 'Add branch',
        th_sucursal: 'Branch', th_direccion: 'Address', th_telefono: 'Phone', th_contacto: 'Contact', th_estado: 'Status',
        per_sub: 'Register managers/cashiers and assign them to a branch.', btn_add_personal: 'Add staff', th_rol: 'Role',
        inv_sub: 'Check stock levels across all branches.', placeholder_buscar_producto: 'Search product or code...',
        todas_sucursales: 'All branches', th_producto: 'Product', th_existencia: 'Stock', th_precio: 'Price', th_nivel: 'Level',
        prod_title: 'Global Catalog', prod_sub: 'Register the base products for the whole system.', btn_add_producto: 'Add product',
        th_codigo: 'Code', th_categoria: 'Category',
        ven_title: 'Sales History', ven_sub: 'Monitor sales across the whole network.',
        th_folio: 'ID', th_fecha: 'Date', th_pago: 'Payment', th_total: 'Total',
        editar: 'Edit', ver_stock: 'View stock', ver: 'View', guardar: 'Save', cancelar: 'Cancel', si_eliminar: 'Yes, delete',
        sin_registros: 'No records.', panel_actualizado: 'Dashboard refreshed.', inv_saludable: 'All inventory is at healthy levels.',
        sin_datos: 'No data.', no_asignado: 'Not assigned to any branch.',
        modal_prod_new: 'New Product', modal_prod_edit: 'Edit Product', img_url: 'Image URL', desc_lbl: 'Description', asign_suc: 'Assign to branches:'
    }
};
let currentLang = localStorage.getItem('kion-admin-lang') || 'es';
function t(key) { return (I18N[currentLang] && I18N[currentLang][key]) || I18N.es[key] || key; }
function applyLanguage() {
    document.documentElement.lang = currentLang;
    document.querySelectorAll('[data-i18n]').forEach(el => { el.textContent = t(el.dataset.i18n); });
    document.querySelectorAll('[data-i18n-placeholder]').forEach(el => { el.placeholder = t(el.dataset.i18nPlaceholder); });
}
$('#btnLang').addEventListener('click', () => {
    currentLang = currentLang === 'es' ? 'en' : 'es';
    localStorage.setItem('kion-admin-lang', currentLang);
    applyLanguage();
    const activeView = document.querySelector('.view-section.active');
    if (activeView) switchView(activeView.id);
});
applyLanguage();

// -------- SIDEBAR --------
const appLayout = $('#appLayout');
$('#btnCollapse').addEventListener('click', () => appLayout.classList.toggle('is-collapsed'));
$('#btnMobileMenu').addEventListener('click', () => appLayout.classList.add('mobile-open'));
$('#sidebarScrim').addEventListener('click', () => appLayout.classList.remove('mobile-open'));

const navButtons = document.querySelectorAll('.nav-btn[data-target]');
const views = document.querySelectorAll('.view-section');
const titleKeys = { 'view-general':'nav_general', 'view-sucursales':'nav_sucursales', 'view-personal':'nav_personal', 'view-inventario':'nav_inventario', 'view-productos':'prod_title', 'view-ventas':'ven_title' };
function switchView(targetId) {
    views.forEach(v => v.classList.remove('active'));
    navButtons.forEach(b => b.classList.remove('active'));
    document.getElementById(targetId).classList.add('active');
    const btn = document.querySelector(`.nav-btn[data-target="${targetId}"]`);
    if (btn) btn.classList.add('active');
    $('#topbarTitle').textContent = t(titleKeys[targetId] || 'nav_general');
    appLayout.classList.remove('mobile-open');
    if (targetId === 'view-general') loadDashboard();
    if (targetId === 'view-sucursales') loadSucursales();
    if (targetId === 'view-personal') loadPersonal();
    if (targetId === 'view-inventario') loadInventario();
    if (targetId === 'view-productos') loadProductos();
    if (targetId === 'view-ventas') loadVentas();
}
navButtons.forEach(btn => btn.addEventListener('click', e => switchView(e.currentTarget.dataset.target)));
document.addEventListener('click', e => { const g = e.target.closest('[data-goto]'); if (g) switchView(g.dataset.goto); });

// -------- DASHBOARD --------
let salesChartInstance = null, paymentChartInstance = null;
async function loadDashboard() {
    try {
        const { data } = await request('get_metrics');
        $('#metricSucursales').textContent = Number(data.ts).toLocaleString();
        $('#metricUsuarios').textContent = Number(data.tu).toLocaleString();
        $('#metricProductos').textContent = Number(data.tp).toLocaleString();
        $('#metricIngresos').textContent = money(data.ih);
        $('#metricVentasCount').textContent = Number(data.vh).toLocaleString() + ' ' + t('ventas_lbl');
        $('#donutVentasCount').textContent = Number(data.vh).toLocaleString();
        $('#metricInventarioTotal').textContent = Number(data.ti).toLocaleString();
        $('#metricValorInventario').textContent = money(data.valor_inv);
    } catch (e) {}
    loadLowStock();
}
async function loadLowStock() {
    try {
        const { data } = await request('get_inventarios');
        const low = data.filter(x => x.nivel !== 'OK').slice(0, 5);
        $('#lowStockList').innerHTML = low.length ? low.map(x => `<div class="stock-row"><span class="stock-thumb"><i class="ph ph-package"></i></span><div><div class="stock-name">${escapeHtml(x.producto)}</div><div class="stock-meta">${escapeHtml(x.sucursal)}</div></div><div class="stock-qty">${escapeHtml(x.existencias)}<small>mín. ${escapeHtml(x.stock_minimo)}</small></div></div>`).join('') : `<p class="subtitle" style="font-size:13px; padding:10px 0;">${t('inv_saludable')}</p>`;
    } catch (e) {}
}
$('#refreshDashboard').addEventListener('click', () => { loadDashboard(); notify(t('panel_actualizado')); });

// -------- SUCURSALES --------
async function loadSucursales() {
    try {
        const { data } = await request('get_sucursales');
        $('#sucursalesTable').innerHTML = data.length ? data.map(x => `<tr>
            <td><b>${escapeHtml(x.nombre)}</b></td><td>${escapeHtml(x.direccion||'-')}</td><td>${escapeHtml(x.telefono||'-')}</td><td>${escapeHtml(x.contacto||'-')}</td>
            <td>${statusBadge(x.estado)}</td>
            <td class="row-actions"><button class="chip-btn" data-edit-suc='${JSON.stringify(x).replace(/'/g,'&#39;')}'>${t('editar')}</button><button class="icon-x" data-del-suc="${x.id_sucursal}"><i class="ph ph-trash"></i></button></td>
        </tr>`).join('') : empty(6);
    } catch (e) { notify(e.message, 'error'); }
}
async function sucursalModal(record = null) {
    const editing = !!record;
    const L = currentLang === 'en' ? { titleNew:'New Branch', titleEdit:'Edit Branch', nombre:'Name *', dir:'Address', tel:'Phone', cont:'Contact', estado:'Status', required:'Name is required.' } : { titleNew:'Nueva Sucursal', titleEdit:'Editar Sucursal', nombre:'Nombre *', dir:'Dirección', tel:'Teléfono', cont:'Contacto', estado:'Estado', required:'El nombre es obligatorio.' };
    const { value: form } = await Swal.fire({
        title: editing ? L.titleEdit : L.titleNew,
        html: `<div style="text-align:left;">
            <label><b>${L.nombre}</b></label><input id="swal-nombre" class="swal2-input" style="width:100%;margin:6px 0 12px;" value="${editing?escapeHtml(record.nombre):''}">
            <label><b>${L.dir}</b></label><input id="swal-dir" class="swal2-input" style="width:100%;margin:6px 0 12px;" value="${editing?escapeHtml(record.direccion||''):''}">
            <label><b>${L.tel}</b></label><input id="swal-tel" class="swal2-input" style="width:100%;margin:6px 0 12px;" value="${editing?escapeHtml(record.telefono||''):''}">
            <label><b>${L.cont}</b></label><input id="swal-cont" class="swal2-input" style="width:100%;margin:6px 0 12px;" value="${editing?escapeHtml(record.contacto||''):''}">
            ${editing ? `<label><b>${L.estado}</b></label><select id="swal-estado" class="swal2-input" style="width:100%;margin:6px 0;"><option ${record.estado==='ACTIVA'?'selected':''}>ACTIVA</option><option${record.estado==='INACTIVA'?'selected':''}>INACTIVA</option></select>` : ''}
        </div>`,
        confirmButtonText: t('guardar'), confirmButtonColor: 'var(--coffee-main)', showCancelButton: true, cancelButtonText: t('cancelar'),
        preConfirm: () => {
            const nombre = document.getElementById('swal-nombre').value.trim();
            if (!nombre) { Swal.showValidationMessage(L.required); return false; }
            const out = { nombre, direccion: document.getElementById('swal-dir').value, telefono: document.getElementById('swal-tel').value, contacto: document.getElementById('swal-cont').value };
            if (editing) { out.id = record.id_sucursal; out.estado = document.getElementById('swal-estado').value; }
            return out;
        }
    });
    if (!form) return;
    try {
        const fd = new FormData(); Object.entries(form).forEach(([k,v]) => fd.append(k,v));
        const d = await request(editing ? 'update_sucursal' : 'create_sucursal', { method:'POST', body: fd });
        notify(d.msg); loadSucursales(); loadDashboard(); loadBranchSelects();
    } catch (e) { notify(e.message, 'error'); }
}
$('#addSucursal').addEventListener('click', () => sucursalModal());
document.addEventListener('click', async e => {
    const editBtn = e.target.closest('[data-edit-suc]');
    if (editBtn) sucursalModal(JSON.parse(editBtn.dataset.editSuc));
    const delBtn = e.target.closest('[data-del-suc]');
    if (delBtn) {
        const Ld = currentLang === 'en' ? { title:'Delete branch?', text:'This action cannot be undone.' } : { title:'¿Eliminar sucursal?', text:'Esta acción no se puede deshacer.' };
        const conf = await Swal.fire({ title:Ld.title, text:Ld.text, icon:'warning', showCancelButton:true, confirmButtonColor:'var(--danger)', confirmButtonText:t('si_eliminar'), cancelButtonText:t('cancelar') });
        if (!conf.isConfirmed) return;
        try {
            const fd = new FormData(); fd.append('id', delBtn.dataset.delSuc);
            const d = await request('delete_sucursal', { method:'POST', body:fd });
            notify(d.msg); loadSucursales(); loadDashboard(); loadBranchSelects();
        } catch (err) { notify(err.message, 'error'); }
    }
});

// -------- PERSONAL --------
async function loadPersonal() {
    try {
        const { data } = await request('get_gerentes');
        $('#personalTable').innerHTML = data.length ? data.map(x => `<tr>
            <td><b>${escapeHtml(x.nombre)} ${escapeHtml(x.apellido||'')}</b><br><small style="color:var(--ink-faint)">${escapeHtml(x.correo)}</small></td>
            <td>${escapeHtml(x.rol||'-')}</td><td>${escapeHtml(x.sucursal)}</td><td>${statusBadge(x.estado)}</td>
            <td class="row-actions"><button class="chip-btn" data-edit-per='${JSON.stringify(x).replace(/'/g,'&#39;')}'>${t('editar')}</button><button class="icon-x" data-del-per="${x.id_usuario}"><i class="ph ph-trash"></i></button></td>
        </tr>`).join('') : empty(5);
    } catch (e) { notify(e.message, 'error'); }
}
async function personalModal(record = null) {
    const editing = !!record;
    let roles = [], sucursales = [];
    try { [roles, sucursales] = await Promise.all([request('get_roles'), request('get_sucursales_simple')]); } catch(e) { notify(e.message,'error'); return; }
    const roleOptions = roles.data.map(r => `<option value="${r.id_rol}" ${editing && +record.id_rol===+r.id_rol?'selected':''}>${escapeHtml(r.nombre)}</option>`).join('');
    const Lp = currentLang === 'en' ? { titleNew:'New Staff', titleEdit:'Edit Staff', noBranch:'-- No branch (Admin only) --', nombre:'Name *', apellido:'Last name', correo:'Email *', rol:'Role *', suc:'Branch', pass:'Password', passEdit:'(leave empty to keep it)', estado:'Status', required:'Name and email are required.' } : { titleNew:'Nuevo Personal', titleEdit:'Editar Personal', noBranch:'-- Sin sucursal (solo Admin) --', nombre:'Nombre *', apellido:'Apellido', correo:'Correo *', rol:'Rol *', suc:'Sucursal', pass:'Contraseña', passEdit:'(dejar vacío para no cambiar)', estado:'Estado', required:'Nombre y correo son obligatorios.' };
    const branchOptions = `<option value="">${Lp.noBranch}</option>` + sucursales.data.map(s => `<option value="${s.id_sucursal}" ${editing && +record.id_sucursal===+s.id_sucursal?'selected':''}>${escapeHtml(s.nombre)}</option>`).join('');

    const { value: form } = await Swal.fire({
        title: editing ? Lp.titleEdit : Lp.titleNew,
        html: `<div style="text-align:left;">
            <label><b>${Lp.nombre}</b></label><input id="swal-nom" class="swal2-input" style="width:100%;margin:6px 0 12px;" value="${editing?escapeHtml(record.nombre):''}">
            <label><b>${Lp.apellido}</b></label><input id="swal-ape" class="swal2-input" style="width:100%;margin:6px 0 12px;" value="${editing?escapeHtml(record.apellido||''):''}">
            <label><b>${Lp.correo}</b></label><input id="swal-correo" type="email" class="swal2-input" style="width:100%;margin:6px 0 12px;" value="${editing?escapeHtml(record.correo):''}">
            <label><b>${Lp.rol}</b></label><select id="swal-rol" class="swal2-input" style="width:100%;margin:6px 0 12px;">${roleOptions}</select>
            <label><b>${Lp.suc}</b></label><select id="swal-suc" class="swal2-input" style="width:100%;margin:6px 0 12px;">${branchOptions}</select>
            <label><b>${Lp.pass} ${editing?Lp.passEdit:'*'}</b></label><input id="swal-pass" type="password" class="swal2-input" style="width:100%;margin:6px 0 12px;">
            ${editing ? `<label><b>${Lp.estado}</b></label><select id="swal-estado" class="swal2-input" style="width:100%;margin:6px 0;"><option ${record.estado==='ACTIVO'?'selected':''}>ACTIVO</option><option${record.estado==='INACTIVO'?'selected':''}>INACTIVO</option></select>` : ''}
        </div>`,
        confirmButtonText: t('guardar'), confirmButtonColor: 'var(--coffee-main)', showCancelButton: true, cancelButtonText: t('cancelar'),
        preConfirm: () => {
            const nombre = document.getElementById('swal-nom').value.trim();
            const correo = document.getElementById('swal-correo').value.trim();
            if (!nombre || !correo) { Swal.showValidationMessage(Lp.required); return false; }
            const out = { nombre, apellido: document.getElementById('swal-ape').value, correo, id_rol: document.getElementById('swal-rol').value, id_sucursal: document.getElementById('swal-suc').value, password: document.getElementById('swal-pass').value };
            if (editing) { out.id = record.id_usuario; out.estado = document.getElementById('swal-estado').value; }
            return out;
        }
    });
    if (!form) return;
    try {
        const fd = new FormData(); Object.entries(form).forEach(([k,v]) => fd.append(k,v));
        const d = await request(editing ? 'update_gerente' : 'create_gerente', { method:'POST', body: fd });
        notify(d.msg); loadPersonal(); loadDashboard();
    } catch (e) { notify(e.message, 'error'); }
}
$('#addPersonal').addEventListener('click', () => personalModal());
document.addEventListener('click', async e => {
    const editBtn = e.target.closest('[data-edit-per]');
    if (editBtn) personalModal(JSON.parse(editBtn.dataset.editPer));
    const delBtn = e.target.closest('[data-del-per]');
    if (delBtn) {
        const Ld2 = currentLang === 'en' ? { title:'Delete record?', text:'This user will be permanently deleted.' } : { title:'¿Eliminar registro?', text:'Se eliminará permanentemente este usuario.' };
        const conf = await Swal.fire({ title:Ld2.title, text:Ld2.text, icon:'warning', showCancelButton:true, confirmButtonColor:'var(--danger)', confirmButtonText:t('si_eliminar'), cancelButtonText:t('cancelar') });
        if (!conf.isConfirmed) return;
        try {
            const fd = new FormData(); fd.append('id', delBtn.dataset.delPer);
            const d = await request('delete_gerente', { method:'POST', body:fd });
            notify(d.msg); loadPersonal(); loadDashboard();
        } catch (err) { notify(err.message, 'error'); }
    }
});

// -------- INVENTARIO --------
async function loadBranchSelects() {
    try {
        const { data } = await request('get_sucursales_simple');
        const options = data.map(x => `<option value="${x.id_sucursal}">${escapeHtml(x.nombre)}</option>`).join('');
        ['#inventoryBranch','#salesBranch'].forEach(s => $(s).innerHTML = `<option value="">${t('todas_sucursales')}</option>` + options);
    } catch (e) {}
}
async function loadInventario() {
    try {
        const q = $('#inventorySearch').value, suc = $('#inventoryBranch').value;
        const { data } = await request(`get_inventarios&q=${encodeURIComponent(q)}&sucursal=${encodeURIComponent(suc)}`);
        $('#inventarioTable').innerHTML = data.length ? data.map(x => {
            const cls = x.nivel === 'OK' ? 'b-green' : (x.nivel === 'BAJO' ? 'b-yellow' : 'b-red');
            return `<tr><td><b>${escapeHtml(x.producto)}</b><br><small style="color:var(--ink-faint)">${escapeHtml(x.codigo)}</small></td><td>${escapeHtml(x.sucursal)}</td><td><b>${escapeHtml(x.existencias)}</b> <small>/ mín. ${escapeHtml(x.stock_minimo)}</small></td><td>${money(x.precio)}</td><td><span class="badge ${cls}">${escapeHtml(x.nivel)}</span></td></tr>`;
        }).join('') : empty(5);
    } catch (e) { notify(e.message, 'error'); }
}
['#inventorySearch','#inventoryBranch'].forEach(s => $(s).addEventListener('input', loadInventario));

// -------- PRODUCTOS --------
async function loadProductos() {
    try {
        const q = $('#productsSearch').value;
        const { data } = await request(`get_productos&q=${encodeURIComponent(q)}`);
        $('#productosTable').innerHTML = data.length ? data.map(x => `<tr>
            <td>
                <div style="display:flex; align-items:center; gap:10px;">
                    <img src="${x.imagen_url ? escapeHtml(x.imagen_url) : 'https://placehold.co/100x100?text=Sin+Imagen'}" style="width:36px; height:36px; border-radius:8px; object-fit:cover; border:1px solid var(--border-soft);">
                    <b>${escapeHtml(x.codigo)}</b>
                </div>
            </td>
            <td><b>${escapeHtml(x.nombre)}</b></td><td>${escapeHtml(x.categoria)}</td><td>${money(x.precio)}</td>
            <td>${statusBadge(x.estado)}</td>
            <td class="row-actions"><button class="chip-btn" data-view-prod="${x.id_producto}">${t('ver_stock')}</button><button class="chip-btn" data-edit-prod='${JSON.stringify(x).replace(/'/g,'&#39;')}'>${t('editar')}</button><button class="icon-x" data-del-prod="${x.id_producto}"><i class="ph ph-trash"></i></button></td>
        </tr>`).join('') : empty(6);
    } catch (e) { notify(e.message, 'error'); }
}
$('#productsSearch').addEventListener('input', loadProductos);

async function productoModal(record = null) {
    const editing = !!record;
    let categorias = [], sucursales = [], asignadas = [];
    try {
        const promises = [request('get_categorias'), request('get_sucursales_simple')];
        if (editing) promises.push(request(`get_producto_detalle&id=${record.id_producto}`));
        const results = await Promise.all(promises);
        categorias = results[0].data; sucursales = results[1].data;
        if (editing) asignadas = results[2].data.map(x => x.id_sucursal);
    } catch (e) { notify(e.message, 'error'); return; }

    const catOptions = categorias.map(c => `<option value="${c.id_categoria}" ${editing && +record.id_categoria===+c.id_categoria?'selected':''}>${escapeHtml(c.nombre)}</option>`).join('');
    const branchChecks = sucursales.map(s => `<label style="font-size:13px; display:flex; align-items:center; gap:6px;"><input type="checkbox" class="swal-suc-chk" value="${s.id_sucursal}" ${asignadas.includes(s.id_sucursal)?'checked':''}> ${escapeHtml(s.nombre)}</label>`).join('');

    const { value: form } = await Swal.fire({
        title: editing ? t('modal_prod_edit') : t('modal_prod_new'),
        html: `<div style="text-align:left;">
            <div style="text-align:center; margin-bottom:12px;">
                <img id="swal-preview" class="swal-preview-img" src="${editing && record.imagen_url ? escapeHtml(record.imagen_url) : 'https://placehold.co/150x150?text=Sin+Imagen'}">
            </div>
            <label><b>${t('img_url')}</b></label><input id="swal-img" class="swal2-input" style="width:100%;margin:6px 0 12px;" value="${editing?escapeHtml(record.imagen_url||''):''}" placeholder="https://...">
            <div style="display:flex; gap:10px;">
                <div style="flex:1"><label><b>${t('th_codigo')} *</b></label><input id="swal-codigo" class="swal2-input" style="width:100%;margin:6px 0 12px;" value="${editing?escapeHtml(record.codigo):''}"></div>
                <div style="flex:2"><label><b>${t('th_producto')} *</b></label><input id="swal-nombre" class="swal2-input" style="width:100%;margin:6px 0 12px;" value="${editing?escapeHtml(record.nombre):''}"></div>
            </div>
            <label><b>${t('desc_lbl')}</b></label>
            <textarea id="swal-desc" class="swal2-textarea" style="width:100%;margin:6px 0 12px; font-family:inherit; min-height:80px;">${editing?escapeHtml(record.descripcion||''):''}</textarea>
            
            <div style="display:flex; gap:10px;">
                <div style="flex:1"><label><b>${t('th_categoria')}</b></label><select id="swal-cat" class="swal2-input" style="width:100%;margin:6px 0 12px;">${catOptions}</select></div>
                <div style="flex:1"><label><b>${t('th_precio')} *</b></label><input id="swal-precio" type="number" step="0.01" class="swal2-input" style="width:100%;margin:6px 0 12px;" value="${editing?record.precio:''}"></div>
            </div>

            <div style="padding:14px; background:var(--bg-app); border:1px solid var(--border-soft); border-radius:10px; margin:10px 0;">
                <label><b>${t('asign_suc')}</b></label>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:8px;">${branchChecks || '<span style="font-size:12px;color:var(--ink-faint)">No hay sucursales activas.</span>'}</div>
            </div>
            ${editing ? `<label><b>Estado</b></label><select id="swal-estado" class="swal2-input" style="width:100%;margin:6px 0;"><option ${record.estado==='ACTIVO'?'selected':''}>ACTIVO</option><option${record.estado==='INACTIVO'?'selected':''}>INACTIVO</option></select>` : ''}
        </div>`,
        confirmButtonText: t('guardar'), confirmButtonColor: 'var(--coffee-main)', showCancelButton: true, cancelButtonText: t('cancelar'), width: 550,
        didOpen: () => {
            document.getElementById('swal-img').addEventListener('input', e => {
                document.getElementById('swal-preview').src = e.target.value.trim() || 'https://placehold.co/150x150?text=Sin+Imagen';
            });
        },
        preConfirm: () => {
            const codigo = document.getElementById('swal-codigo').value.trim();
            const nombre = document.getElementById('swal-nombre').value.trim();
            if (!codigo || !nombre) { Swal.showValidationMessage('Código y nombre son obligatorios.'); return false; }
            const sucs = Array.from(document.querySelectorAll('.swal-suc-chk:checked')).map(c => c.value);
            const out = { 
                codigo, 
                nombre, 
                imagen_url: document.getElementById('swal-img').value.trim(),
                descripcion: document.getElementById('swal-desc').value.trim(), 
                id_categoria: document.getElementById('swal-cat').value, 
                precio: document.getElementById('swal-precio').value, 
                sucursales: sucs 
            };
            if (editing) { out.id = record.id_producto; out.estado = document.getElementById('swal-estado').value; }
            return out;
        }
    });
    if (!form) return;
    try {
        const fd = new FormData();
        Object.entries(form).forEach(([k,v]) => { if (k === 'sucursales') { v.forEach(s => fd.append('sucursales[]', s)); } else { fd.append(k, v); } });
        const d = await request(editing ? 'update_producto' : 'create_producto', { method:'POST', body: fd });
        notify(d.msg); loadProductos(); loadInventario(); loadDashboard();
    } catch (e) { notify(e.message, 'error'); }
}
$('#addProducto').addEventListener('click', () => productoModal());
document.addEventListener('click', async e => {
    const editBtn = e.target.closest('[data-edit-prod]');
    if (editBtn) productoModal(JSON.parse(editBtn.dataset.editProd));
    const delBtn = e.target.closest('[data-del-prod]');
    if (delBtn) {
        const conf = await Swal.fire({ title:'¿Eliminar producto?', text:'Se eliminará el producto y su inventario asociado.', icon:'warning', showCancelButton:true, confirmButtonColor:'var(--danger)', confirmButtonText:t('si_eliminar'), cancelButtonText:t('cancelar') });
        if (!conf.isConfirmed) return;
        try {
            const fd = new FormData(); fd.append('id', delBtn.dataset.delProd);
            const d = await request('delete_producto', { method:'POST', body:fd });
            notify(d.msg); loadProductos(); loadInventario(); loadDashboard();
        } catch (err) { notify(err.message, 'error'); }
    }
    const viewBtn = e.target.closest('[data-view-prod]');
    if (viewBtn) {
        try {
            const { data } = await request(`get_producto_detalle&id=${viewBtn.dataset.viewProd}`);
            Swal.fire({ title: 'Stock por sucursal', html: data.length ? `<table style="width:100%;text-align:left;"><tr><th>Sucursal</th><th style="text-align:right">Existencias</th></tr>${data.map(x=>`<tr><td>${escapeHtml(x.sucursal)}</td><td style="text-align:right"><b>${x.existencias}</b></td></tr>`).join('')}</table>` : `<p>${t('no_asignado')}</p>` });
        } catch (err) { notify(err.message, 'error'); }
    }
});

// -------- VENTAS --------
async function loadVentas() {
    try {
        const p = new URLSearchParams({ sucursal: $('#salesBranch').value, desde: $('#salesFrom').value, hasta: $('#salesTo').value });
        const { data } = await request(`get_ventas&${p}`);
        $('#ventasTable').innerHTML = data.length ? data.map(x => `<tr>
            <td>#${x.id_venta}</td><td>${new Date(x.fecha_hora).toLocaleString('es-MX')}</td><td>${escapeHtml(x.sucursal)}</td><td>${escapeHtml(x.empleado)}</td><td>${escapeHtml(x.metodo_pago)}</td>
            <td><b>${money(x.total)}</b></td><td>${statusBadge(x.estado)}</td>
            <td><button class="chip-btn" data-ver-venta="${x.id_venta}">${t('ver')}</button></td>
        </tr>`).join('') : empty(8);
    } catch (e) { notify(e.message, 'error'); }
}
['#salesBranch','#salesFrom','#salesTo'].forEach(s => $(s).addEventListener('change', loadVentas));
document.addEventListener('click', async e => {
    const verBtn = e.target.closest('[data-ver-venta]');
    if (!verBtn) return;
    try {
        const { data } = await request(`get_venta_detalle&id=${verBtn.dataset.verVenta}`);
        Swal.fire({ title: `Detalle Venta #${verBtn.dataset.verVenta}`, html: data.length ? `<table style="width:100%;text-align:left;"><tr><th>Producto</th><th>Cant</th><th style="text-align:right">Subtotal</th></tr>${data.map(x=>`<tr><td>${escapeHtml(x.producto)}</td><td>${x.cantidad}</td><td style="text-align:right">${money(x.subtotal)}</td></tr>`).join('')}</table>` : t('sin_datos') });
    } catch (e) { notify(e.message, 'error'); }
});

// -------- INICIO --------
const initial = <?= json_encode(['labels' => $v7['labels'] ?? [], 'ingresos' => $v7['ingresos'] ?? [], 'payments' =>$mp], JSON_UNESCAPED_UNICODE) ?>;
window.addEventListener('DOMContentLoaded', () => {
    const palette = ['#936545', '#547c5c', '#648ba6', '#b3832e', '#a66d7e'];
    if (window.Chart) {
        salesChartInstance = new Chart($('#salesChart'), {
            type: 'line',
            data: { labels: initial.labels || [], datasets: [{ label: 'Ingresos', data: initial.ingresos || [], borderColor: '#6c4630', backgroundColor: 'rgba(147,101,69,.14)', fill: true, tension: .38, pointRadius: 3 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true }, x: { grid: { display: false } } } }
        });
        paymentChartInstance = new Chart($('#paymentChart'), {
            type: 'doughnut',
            data: { labels: (initial.payments || []).map(x => x.metodo), datasets: [{ data: (initial.payments || []).map(x => x.total_ventas), backgroundColor: palette, borderWidth: 0 }] },
            options: { responsive: true, maintainAspectRatio: false, cutout: '74%', plugins: { legend: { position: 'bottom' } } }
        });
    }
    loadBranchSelects();
    loadDashboard();
});
</script>
</body>
</html>