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

// Helper para procesamiento seguro de imágenes de producto (subida física o URL)
function procesarSubidaImagen(?array $file, string $urlTexto = ''): ?string {
    if ($file && isset($file['error']) && $file['error'] === UPLOAD_ERR_OK && $file['size'] > 0) {
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (isset($allowed[$mime])) {
            $ext = $allowed[$mime];
            $dirDestino = __DIR__ . '/../../../img/productos/';
            if (!is_dir($dirDestino)) {
                @mkdir($dirDestino, 0777, true);
            }
            $nombreArchivo = 'prod_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $destino = $dirDestino . $nombreArchivo;
            if (move_uploaded_file($file['tmp_name'], $destino)) {
                return '../../img/productos/' . $nombreArchivo;
            }
        }
    }
    $urlTexto = trim($urlTexto);
    return $urlTexto !== '' ? $urlTexto : null;
}

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

        } elseif ($action === 'delete_sucursal') {
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception('[ERROR DE USUARIO] ID de sucursal inválido.');
            $stV = $pdo->prepare("SELECT COUNT(*) FROM ventas WHERE id_sucursal=?");
            $stV->execute([$id]);
            if ($stV->fetchColumn() > 0) {
                $pdo->prepare("UPDATE sucursales SET estado='INACTIVA' WHERE id_sucursal=?")->execute([$id]);
                $out = ['ok' => true, 'msg' => 'La sucursal tiene ventas históricas asociadas. Se marcó como INACTIVA para proteger los reportes.'];
            } else {
                $pdo->beginTransaction();
                try {
                    $pdo->prepare("DELETE FROM inventarios WHERE id_sucursal=?")->execute([$id]);
                    $pdo->prepare("DELETE FROM cajeros WHERE id_sucursal=?")->execute([$id]);
                    $pdo->prepare("DELETE FROM cajas WHERE id_sucursal=?")->execute([$id]);
                    $pdo->prepare("UPDATE usuarios SET id_sucursal=NULL WHERE id_sucursal=?")->execute([$id]);
                    $pdo->prepare("DELETE FROM sucursales WHERE id_sucursal=?")->execute([$id]);
                    $pdo->commit();
                    $out = ['ok' => true, 'msg' => 'Sucursal eliminada permanentemente.'];
                } catch (Exception $e) { $pdo->rollBack(); throw $e; }
            }

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

        } elseif ($action === 'get_gestion_productos') {
            $q = trim($_GET['q'] ?? '');
            $sid = (int)($_GET['sucursal'] ?? 0);
            $stockFilter = $_GET['stock'] ?? '';
            $cat = (int)($_GET['categoria'] ?? 0);

            $params = [];
            $sql = "SELECT p.id_producto, p.codigo, p.nombre, p.descripcion, p.imagen_url, p.precio, p.estado, 
                           COALESCE(c.nombre, 'Sin categoría') AS categoria, p.id_categoria 
                    FROM productos p 
                    LEFT JOIN categorias c ON c.id_categoria=p.id_categoria 
                    WHERE 1=1";

            if ($q !== '') { $sql .= " AND (p.codigo LIKE ? OR p.nombre LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; }
            if ($cat > 0) { $sql .= " AND p.id_categoria=?"; $params[] = $cat; }
            $sql .= " ORDER BY p.nombre ASC LIMIT 300";
            
            $st = $pdo->prepare($sql); $st->execute($params);
            $productos = $st->fetchAll(PDO::FETCH_ASSOC);

            // Obtener inventarios para calcular totales
            $result = [];
            foreach ($productos as $p) {
                $stInv = $pdo->prepare("SELECT i.id_sucursal, s.nombre AS sucursal, i.existencias, i.stock_minimo 
                                        FROM inventarios i 
                                        JOIN sucursales s ON s.id_sucursal = i.id_sucursal 
                                        WHERE i.id_producto = ?" . ($sid > 0 ? " AND i.id_sucursal = $sid" : ""));
                $stInv->execute([$p['id_producto']]);
                $invs = $stInv->fetchAll(PDO::FETCH_ASSOC);
                
                $totalExistencias = array_sum(array_column($invs, 'existencias'));
                $minStock = $invs ? min(array_column($invs, 'stock_minimo')) : 5;

                // Aplicar filtros de stock
                if ($stockFilter === 'agotado' && $totalExistencias > 0) continue;
                if ($stockFilter === 'bajo' && $totalExistencias > $minStock) continue;

                $p['total_existencias'] = $totalExistencias;
                $p['stock_minimo'] = $minStock;
                $p['detalle_stock'] = $invs;
                $result[] = $p;
            }
            $out = ['ok' => true, 'data' => $result];

        } elseif ($action === 'create_producto' || $action === 'update_producto') {
            $id = (int)($_POST['id'] ?? 0); $codigo = trim($_POST['codigo'] ?? ''); $nombre = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? ''); $categoria = (int)($_POST['id_categoria'] ?? 0);
            $precio = (float)str_replace(',', '.', $_POST['precio'] ?? '0');
            $estado = ($_POST['estado'] ?? 'ACTIVO') === 'INACTIVO' ? 'INACTIVO' : 'ACTIVO';
            $sucs = $_POST['sucursales'] ?? [];
            if ($codigo === '' || $nombre === '') throw new Exception('[ERROR DE USUARIO] El código y el nombre son obligatorios.');

            $imagenUrl = procesarSubidaImagen($_FILES['imagen_archivo'] ?? null, $_POST['imagen_url'] ?? '');

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
                    if ($imagenUrl === null && !isset($_POST['imagen_url'])) {
                        $stAct = $pdo->prepare("SELECT imagen_url FROM productos WHERE id_producto=?");
                        $stAct->execute([$id]);
                        $imagenUrl = $stAct->fetchColumn() ?: null;
                    }
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
            
            // VALIDACIÓN ESTRICTA: Solo eliminar si el stock es exactamente 0 en TODAS las sucursales
            $stStock = $pdo->prepare("SELECT COALESCE(SUM(existencias), 0) FROM inventarios WHERE id_producto=?");
            $stStock->execute([$id]);
            $totalStock = (int)$stStock->fetchColumn();
            
            if ($totalStock > 0) {
                throw new Exception("[ACCIÓN DENEGADA] No puedes eliminar este producto. Aún existen $totalStock unidades repartidas en tus sucursales. Debes ajustar el stock a 0 antes de eliminar.");
            }

            $pdo->beginTransaction();
            try {
                // 1. Desvincular de las ventas para que el historial (montos y totales) se mantenga intacto
                $pdo->prepare("UPDATE detalle_ventas SET id_producto = NULL WHERE id_producto=?")->execute([$id]);
                
                // 2. Eliminar registros de inventario
                $pdo->prepare("DELETE FROM inventarios WHERE id_producto=?")->execute([$id]);
                
                // 3. Eliminar el producto permanentemente de la base de datos
                $pdo->prepare("DELETE FROM productos WHERE id_producto=?")->execute([$id]);
                
                $pdo->commit();
                $out = ['ok' => true, 'msg' => 'Producto eliminado permanentemente.'];
            } catch (Exception $e) { 
                $pdo->rollBack(); 
                throw $e; 
            }

        } elseif ($action === 'ajustar_stock') {
            $id_p = (int)($_POST['id_producto'] ?? 0);
            $id_s = (int)($_POST['id_sucursal'] ?? 0);
            $cant = (int)($_POST['cantidad'] ?? 0);
            $tipo = $_POST['tipo'] ?? '';
            
            if (!$id_p || !$id_s) throw new Exception('[ERROR] Debes seleccionar un producto y una sucursal.');
            if ($cant < 0) throw new Exception('[ERROR] No puedes ingresar cantidades negativas.');

            $st = $pdo->prepare("SELECT existencias FROM inventarios WHERE id_producto=? AND id_sucursal=?");
            $st->execute([$id_p, $id_s]);
            $row = $st->fetch();
            
            if (!$row) throw new Exception("[ERROR] Este producto no está asignado a la sucursal seleccionada. Edita el producto para asignarlo primero.");

            $nueva = (int)$row['existencias'];
            if ($tipo === 'entrada') $nueva += $cant;
            elseif ($tipo === 'salida') $nueva = max(0, $nueva - $cant);
            elseif ($tipo === 'reemplazo') $nueva = $cant;

            $pdo->prepare("UPDATE inventarios SET existencias=? WHERE id_producto=? AND id_sucursal=?")->execute([$nueva, $id_p, $id_s]);
            $out = ['ok' => true, 'msg' => "Stock actualizado correctamente. Nueva existencia: $nueva unidades."];

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

    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <style>
        /*[cite: 1, 2] */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap');

:root {
    /*[cite: 2] */
    --bg-app: #f3f8ff; 
    --bg-surface: #ffffff; 
    --bg-panel: #f4f9ff;
    
    --ink: #0d2548; 
    --ink-soft: #5b7396; 
    --ink-faint: #a9bfdc;
    --border-soft: #d9e6f7;
    
    --accent: #2f80ed; 
    --accent-light: #5cc8ff; 
    --accent-glow: rgba(47, 128, 237, 0.35);
    
    --success: #1f9a73; 
    --success-bg: #dcecff;
    --danger: #d8584a; 
    --danger-bg: #ffe4e6;
    --warning: #F59E0B; 
    --warning-bg: #FFFBEB;

    --font-display: 'Playfair Display', Georgia, serif;
    --font-body: 'Inter', 'Segoe UI', sans-serif;

    --shadow-sm: 0 8px 20px rgba(30, 80, 160, 0.1);
    --shadow-md: 0 15px 40px rgba(30, 90, 200, 0.1);
    --shadow-lux: 0 22px 48px rgba(30, 90, 200, 0.14);
    
    --radius: 22px; 
    --radius-pill: 999px;
    --sidebar-w: 264px; 
    --sidebar-w-collapsed: 84px;
    --ease-lux: cubic-bezier(0.22, 1, 0.36, 1);
}

body.dark-mode {
    /*[cite: 2] */
    --bg-app: #071225; 
    --bg-surface: #040b18; 
    --bg-panel: #0b1d3a;
    
    --ink: #eaf3ff; 
    --ink-soft: #a3b8d6; 
    --ink-faint: #7f9bc0;
    --border-soft: #16305a;
    
    --accent: #5cc8ff; 
    --accent-light: #8fd6ff; 
    --accent-glow: rgba(92, 200, 255, 0.35);
    
    --shadow-sm: 0 8px 20px rgba(0, 0, 0, 0.4);
    --shadow-md: 0 15px 40px rgba(0, 0, 0, 0.5);
    --shadow-lux: 0 30px 60px rgba(0, 0, 0, 0.55);
    
    --success-bg: rgba(31, 154, 115, 0.15);
    --danger-bg: rgba(216, 88, 74, 0.15);
}

* { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }

body { 
    /*[cite: 1, 2] */
    font-family: var(--font-body); 
    background: var(--bg-app); 
    color: var(--ink); 
    transition: background .3s ease, color .3s ease; 
    background-image: radial-gradient(rgba(47, 128, 237, 0.07) 1px, transparent 1.2px);
    background-size: 7px 7px;
    background-attachment: fixed;
}
body.dark-mode {
    background-image: radial-gradient(rgba(92, 200, 255, 0.04) 1px, transparent 1.2px);
}

button { font: inherit; cursor: pointer; border: none; background: none; color: inherit; }

@keyframes fadeIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
@keyframes wave { 0%,60%,100% { transform: rotate(0deg); } 10% { transform: rotate(16deg); } 20% { transform: rotate(-8deg); } 30% { transform: rotate(16deg); } 40% { transform: rotate(-4deg); } }
@keyframes pulseGlow { 0% { box-shadow: 0 0 0 0 var(--accent-glow); } 70% { box-shadow: 0 0 0 10px transparent; } 100% { box-shadow: 0 0 0 0 transparent; } }

.view-section { display: none; animation: fadeIn .45s var(--ease-lux) forwards; }
.view-section.active { display: block; }
.wave { display: inline-block; transform-origin: 70% 70%; animation: wave 2.2s ease-in-out .3s infinite; } /*[cite: 1] */

.app-layout { display: grid; grid-template-columns: var(--sidebar-w) 1fr; min-height: 100vh; transition: grid-template-columns .35s var(--ease-lux); }
.app-layout.is-collapsed { grid-template-columns: var(--sidebar-w-collapsed) 1fr; }

/*[cite: 1, 2] */
.sidebar { 
    position: sticky; top: 0; height: 100vh; 
    background: rgba(255, 255, 255, 0.92); 
    border-right: 1px solid var(--border-soft); 
    padding: 22px 16px; display: flex; flex-direction: column; gap: 26px; z-index: 40; 
    backdrop-filter: blur(18px);
    transition: padding .35s var(--ease-lux), background .3s ease; 
}
body.dark-mode .sidebar { background: rgba(2, 6, 14, 0.96); }

.brand { display: flex; align-items: center; gap: 12px; font-family: var(--font-display); font-size: 24px; font-weight: 800; color: var(--ink); padding: 0 8px; position: relative; letter-spacing: 1px; }
.brand i { font-size: 28px; color: var(--accent); flex: none; }
.brand-text { overflow: hidden; white-space: nowrap; transition: opacity .2s ease; }
.brand-sub { font-size: 11px; letter-spacing: .6px; color: var(--ink-faint); font-weight: 700; text-transform: uppercase; margin-top: 1px; font-family: var(--font-body); }
.app-layout.is-collapsed .brand-text, .app-layout.is-collapsed .nav-label, .app-layout.is-collapsed .nav-section-title { opacity: 0; width: 0; pointer-events: none; }

.sidebar-collapse-btn { position: absolute; top: 26px; right: -13px; width: 26px; height: 26px; border-radius: 50%; background: var(--bg-surface); border: 1px solid var(--border-soft); color: var(--accent); display: grid; place-items: center; box-shadow: var(--shadow-sm); z-index: 41; transition: transform .3s var(--ease-lux); }
.app-layout.is-collapsed .sidebar-collapse-btn { transform: rotate(180deg); }

.nav-section-title { font-size: 11px; text-transform: uppercase; letter-spacing: .8px; color: var(--ink-faint); font-weight: 700; padding: 4px 12px 6px; white-space: nowrap; }
.nav-menu { display: flex; flex-direction: column; gap: 6px; }

/*[cite: 1, 2] */
.nav-btn { display: flex; align-items: center; gap: 12px; padding: 11px 14px; border-radius: var(--radius-pill); font-weight: 600; font-size: 14px; color: var(--ink-soft); transition: background .25s var(--ease-lux), color .25s ease, transform .25s var(--ease-lux); white-space: nowrap; }
.nav-btn i { font-size: 20px; flex: none; }
.nav-btn:hover { color: var(--accent); transform: translateX(4px); }
.nav-btn.active { position: relative; background: linear-gradient(135deg, #7fd6ff, #2f80ed); color: #fff; font-weight: 700; box-shadow: 0 10px 28px rgba(47,128,237,.35); }
body.dark-mode .nav-btn.active { background: linear-gradient(135deg, #5cc8ff, #2f80ed); color: #04142e; }
.nav-btn.active i { color: inherit; }

.sidebar-scrim { display: none; position: fixed; inset: 0; background: rgba(7, 26, 50, 0.48); backdrop-filter: blur(3px); z-index: 39; opacity: 0; transition: opacity .3s ease; }

/*[cite: 1, 2] */
.topbar { height: 76px; display: flex; justify-content: space-between; align-items: center; padding: 0 32px; border-bottom: 1px solid rgba(124, 196, 255, 0.16); background: rgba(8, 18, 38, 0.96); backdrop-filter: blur(18px); position: sticky; top: 0; z-index: 30; box-shadow: 0 10px 28px rgba(7, 26, 50, 0.28); color: #f2f8ff; }
body.dark-mode .topbar { background: rgba(2, 6, 14, 0.97); border-bottom-color: rgba(200, 225, 255, 0.1); }
.topbar-title-block .eyebrow { font-size: 11.5px; color: rgba(255,255,255,0.72); font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
.topbar-title-block .title-main { font-family: var(--font-display); font-size: 22px; font-weight: 700; letter-spacing: 0.04em; }
.hamburger-btn { display: none; width: 38px; height: 38px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.18); background: rgba(255,255,255,0.08); align-items: center; justify-content: center; font-size: 18px; color: white; }
.topbar-actions { display: flex; gap: 12px; align-items: center; }
.icon-btn { width: 42px; height: 42px; border-radius: 50%; display: grid; place-items: center; background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.18); font-size: 20px; transition: transform .2s ease, background .2s ease, border-color .2s ease; }
.icon-btn:hover { background: var(--accent); border-color: var(--accent-light); color: #fff; transform: translateY(-2px); }
.user-profile { display: flex; align-items: center; gap: 10px; font-weight: 600; padding-left: 14px; border-left: 1px solid rgba(255,255,255,0.18); font-size: 14px; }
.admin-chip { display: inline-flex; align-items: center; background: rgba(92, 200, 255, 0.16); color: var(--accent-light); border: 1px solid rgba(92, 200, 255, 0.35); font-size: 11px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; padding: 5px 10px; border-radius: var(--radius-pill); }

.content { padding: 36px; max-width: 1440px; margin: 0 auto; }
.header-row { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 32px; flex-wrap: wrap; gap: 16px; }

/*[cite: 1, 2] */
.title { font-family: var(--font-display); font-size: clamp(28px, 3vw, 46px); font-weight: 700; display: flex; align-items: center; gap: 10px; line-height: 1.1; letter-spacing: -0.02em; color: var(--ink); }
.subtitle { color: var(--ink-soft); font-size: 17px; margin-top: 8px; line-height: 1.6; }

/*[cite: 1, 2] */
.btn-primary { background: linear-gradient(135deg, #7fd6ff, #2f80ed); color: #04142e; padding: 14px 24px; border-radius: var(--radius-pill); font-weight: 800; font-size: 15px; box-shadow: 0 10px 28px rgba(47,128,237,.35); display: inline-flex; align-items: center; gap: 8px; transition: transform .3s var(--ease-lux), box-shadow .3s ease; }
.btn-primary:hover { box-shadow: 0 16px 36px rgba(47,128,237,.5); transform: translateY(-4px); }
body.dark-mode .btn-primary { background: linear-gradient(135deg, #5cc8ff, #2f80ed); }

.btn-ghost { background: var(--bg-surface); border: 1px solid rgba(30,90,200,.4); color: var(--accent); padding: 12px 20px; border-radius: var(--radius-pill); font-weight: 800; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; transition: .25s; }
.btn-ghost:hover { background: rgba(47,128,237,.10); transform: translateY(-2px); }
body.dark-mode .btn-ghost { border-color: rgba(200, 225, 255, 0.35); color: #eaf3ff; }
body.dark-mode .btn-ghost:hover { background: rgba(200, 225, 255, 0.1); }

/*[cite: 1, 2] */
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 24px; margin-bottom: 32px; }
.kpi-card { background: var(--bg-surface); padding: 28px; border-radius: var(--radius); border: 1.5px solid var(--border-soft); box-shadow: inset 0 0 0 4px var(--bg-surface), inset 0 0 0 5px var(--bg-panel), var(--shadow-sm); display: flex; flex-direction: column; gap: 14px; transition: .3s; }
body.dark-mode .kpi-card { box-shadow: var(--shadow-sm); }
.kpi-card:hover { transform: translateY(-7px); border-color: var(--accent-light); box-shadow: var(--shadow-lux); }
.kpi-top { display: flex; align-items: flex-start; justify-content: space-between; }
.kpi-card i.kpi-icon { font-size: 26px; color: var(--accent); padding: 14px; background: rgba(92, 200, 255, 0.18); border-radius: 16px; transition: transform 0.3s ease; }
.kpi-card:hover i.kpi-icon { transform: scale(1.1) rotate(-6deg); animation: pulseGlow 1.5s infinite; }
.kpi-tag { font-size: 11px; font-weight: 800; padding: 6px 12px; border-radius: var(--radius-pill); background: var(--success-bg); color: var(--success); text-transform: uppercase; letter-spacing: 0.06em; }
.kpi-val { font-family: var(--font-display); font-size: 38px; font-weight: 700; color: var(--ink); letter-spacing: -0.01em; }
.kpi-label { color: var(--ink-soft); font-size: 15px; font-weight: 600; }

/*[cite: 1, 2] */
.panel { background: var(--bg-surface); border-radius: var(--radius); border: 1.5px solid var(--border-soft); padding: 30px; overflow-x: auto; margin-bottom: 24px; box-shadow: var(--shadow-md); transition: .3s; }
.panel:hover { border-color: var(--accent-light); }
.panel-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 24px; flex-wrap: wrap; }
.panel-title { font-family: var(--font-display); font-size: 24px; font-weight: 700; color: var(--ink); }
.panel-sub { font-size: 14px; color: var(--ink-faint); margin-top: 6px; }

.grid-2col { display: grid; grid-template-columns: 1.4fr 1fr; gap: 24px; }
.chart-box { position: relative; height: 260px; }
.donut-wrap { position: relative; height: 240px; display: flex; align-items: center; justify-content: center; }
.donut-center { position: absolute; text-align: center; pointer-events: none; }
.donut-center .num { font-family: var(--font-display); font-size: 32px; font-weight: 700; color: var(--ink); }
.donut-center .lbl { font-size: 12px; color: var(--ink-faint); font-weight: 700; text-transform: uppercase; }

/*[cite: 1, 2] */
table { width: 100%; border-collapse: collapse; text-align: left; }
th { font-size: 12.5px; text-transform: uppercase; letter-spacing: .08em; color: var(--accent); padding-bottom: 16px; border-bottom: 1px solid var(--border-soft); font-weight: 800; }
td { padding: 18px 0; border-bottom: 1px solid var(--border-soft); font-weight: 500; vertical-align: middle; font-size: 15px; color: var(--ink); }
tbody tr { transition: background 0.25s ease, transform 0.2s ease; }
tbody tr:hover { background: var(--bg-panel); transform: scale(1.01); border-radius: 12px; }

.badge { padding: 6px 14px; border-radius: var(--radius-pill); font-size: 12px; font-weight: 800; display: inline-flex; align-items: center; gap: 6px; text-transform: uppercase; letter-spacing: 0.04em; }
.badge.b-red { background: var(--danger-bg); color: var(--danger); }
.badge.b-green { background: var(--success-bg); color: var(--success); }
.badge.b-yellow { background: var(--warning-bg); color: var(--warning); }

.chip-btn { border: 1.5px solid var(--border-soft); background: var(--bg-surface); color: var(--ink-soft); font-size: 13px; font-weight: 700; padding: 10px 18px; border-radius: var(--radius-pill); transition: .25s ease; }
.chip-btn:hover { background: var(--accent); border-color: var(--accent); color: #fff; transform: translateY(-2px); box-shadow: var(--shadow-sm); }

.icon-x { width: 36px; height: 36px; border-radius: 12px; border: 1px solid transparent; display: inline-grid; place-items: center; color: var(--danger); font-size: 20px; transition: .2s ease; }
.icon-x:hover { background: var(--danger-bg); transform: scale(1.1); }
.row-actions { display: flex; gap: 10px; align-items: center; }

/*[cite: 1, 2] */
.form-inline { display: flex; gap: 16px; flex-wrap: wrap; align-items: center; }
.search-input, .select-input { padding: 14px 20px; border: 1.5px solid var(--border-soft); border-radius: var(--radius-pill); background: var(--bg-surface); color: var(--ink); min-width: 240px; font-family: var(--font-body); font-size: 15px; font-weight: 500; transition: .3s ease; }
.search-input:focus, .select-input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 4px var(--accent-glow); }
body.dark-mode .search-input:focus, body.dark-mode .select-input:focus { box-shadow: 0 0 0 4px rgba(92, 200, 255, 0.15); }

.swal-preview-img { max-width: 150px; max-height: 150px; border-radius: 16px; border: 1.5px solid var(--border-soft); object-fit: cover; }

.stock-list { display: flex; flex-direction: column; gap: 8px; }
.stock-row { display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 16px; padding: 16px 12px; border-bottom: 1px dashed var(--border-soft); transition: .2s ease; }
.stock-row:hover { background: var(--bg-panel); border-radius: 12px; transform: translateX(4px); }
.stock-row:last-child { border-bottom: none; }
.stock-thumb { width: 48px; height: 48px; border-radius: 16px; background: rgba(92, 200, 255, 0.18); display: grid; place-items: center; color: var(--accent); font-size: 22px; flex: none; }
.stock-name { font-size: 15px; font-weight: 700; color: var(--ink); }
.stock-meta { font-size: 13px; color: var(--ink-faint); margin-top: 4px; }
.stock-qty { text-align: right; font-weight: 800; font-size: 16px; color: var(--ink); }
.stock-qty small { display: block; font-weight: 600; color: var(--ink-faint); font-size: 12px; }

.info-strip { display: flex; justify-content: space-between; align-items: flex-end; padding-bottom: 20px; margin-bottom: 20px; border-bottom: 1px dashed var(--border-soft); }

/*[cite: 1, 2] */
.swal2-popup { background: var(--bg-surface) !important; color: var(--ink) !important; border-radius: 28px !important; box-shadow: var(--shadow-lux) !important; font-family: var(--font-body) !important; border: 1.5px solid var(--border-soft) !important; }
.swal2-title { font-family: var(--font-display) !important; color: var(--ink) !important; }
.swal2-input, .swal2-select, .swal2-textarea, .swal2-file { background: var(--bg-surface) !important; color: var(--ink) !important; border: 1.5px solid var(--border-soft) !important; border-radius: 16px !important; font-family: var(--font-body) !important; }
.swal2-input:focus, .swal2-select:focus, .swal2-textarea:focus { border-color: var(--accent) !important; box-shadow: 0 0 0 4px var(--accent-glow) !important; }

/*[cite: 1, 2] */
.prod-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 32px 28px; }
.prod-card { background: var(--bg-surface); border: 1.5px solid var(--border-soft); border-radius: 26px; overflow: hidden; display: flex; flex-direction: column; padding: 12px; box-shadow: var(--shadow-sm); transition: .3s var(--ease-lux); position: relative; }
.prod-card:hover { box-shadow: var(--shadow-lux); transform: translateY(-7px); border-color: var(--accent-light); }

.prod-card-img-wrap { width: 100%; aspect-ratio: 1 / 1; border-radius: 18px; background: linear-gradient(135deg, #dcecff, #a9c8ee); overflow: hidden; position: relative; }
body.dark-mode .prod-card-img-wrap { background: linear-gradient(135deg, #17315e, #0c1c3a); }
.prod-card-img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; transition: transform 0.6s var(--ease-lux); }
.prod-card:hover .prod-card-img { transform: scale(1.08); }

.prod-card-body { padding: 20px 14px 14px; flex: 1; display: flex; flex-direction: column; }
.prod-meta { font-size: 11px; color: var(--accent); display: flex; justify-content: space-between; margin-bottom: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; }
.prod-title { font-family: var(--font-display); font-size: 18px; font-weight: 700; line-height: 1.3; color: var(--ink); margin-bottom: 18px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 46px; }

.prod-price-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; margin-top: auto; }
.prod-price { font-size: 26px; font-family: var(--font-display); font-weight: 700; color: var(--accent); letter-spacing: -0.01em; }

.tarjeta__stock { font-size: 12px; font-weight: 800; background: var(--success-bg); padding: 6px 14px; border-radius: var(--radius-pill); color: var(--success); }
.tarjeta__stock.stock-none { color: var(--danger); background: var(--danger-bg); }
.tarjeta__stock.stock-low { color: var(--warning); background: var(--warning-bg); }

.prod-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; border-top: 1px dashed var(--border-soft); padding-top: 16px; }
.btn-card { padding: 12px; border-radius: var(--radius-pill); font-size: 13.5px; font-weight: 800; text-align: center; border: 1.5px solid var(--border-soft); background: transparent; color: var(--ink-soft); transition: .3s var(--ease-lux); display: flex; align-items: center; justify-content: center; gap: 8px;}
.btn-card:hover { border-color: var(--accent); color: var(--accent); transform: translateY(-2px); box-shadow: var(--shadow-sm); }
.btn-card-del:hover { background: var(--danger-bg); color: var(--danger); border-color: transparent; }
.btn-card-full { grid-column: span 2; background: linear-gradient(135deg, #7fd6ff, #2f80ed); color: #04142e; border-color: transparent; box-shadow: 0 8px 20px rgba(47,128,237,.35); }
.btn-card-full:hover { background: linear-gradient(135deg, #5cc8ff, #1f6bd6); color: #fff; box-shadow: 0 12px 30px rgba(47,128,237,.5); }
body.dark-mode .btn-card-full { background: linear-gradient(135deg, #5cc8ff, #2f80ed); }

@media (max-width: 1180px) { .grid-2col { grid-template-columns: 1fr; } }
@media (max-width: 980px) {
    .app-layout { grid-template-columns: 1fr; }
    .sidebar { position: fixed; left: 0; top: 0; width: 280px; height: 100vh; transform: translateX(-100%); transition: transform .4s var(--ease-lux); box-shadow: 0 10px 40px rgba(7, 26, 50, 0.45); border-right: none; }
    .app-layout.mobile-open .sidebar { transform: translateX(0); }
    .app-layout.mobile-open .sidebar-scrim { display: block; opacity: 1; pointer-events: auto; }
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
            <button class="nav-btn" data-target="view-gestion-productos"><i class="ph ph-package"></i><span class="nav-label" data-i18n="nav_gestion_productos">Gestión de Productos</span></button>
            <button class="nav-btn" data-target="view-ventas"><i class="ph ph-chart-line-up"></i><span class="nav-label" data-i18n="nav_ventas">Ventas</span></button>
            <div class="nav-section-title" data-i18n="nav_section_nav">Navegación</div>
            <a class="nav-btn" href="../../componentes/catalogo.php"><i class="ph ph-storefront"></i><span class="nav-label" data-i18n="nav_catalogo">Ir al Catálogo</span></a>
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
                    <i class="ph-fill ph-user-circle" style="font-size:24px; color:var(--green)"></i>
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
                    <div class="kpi-card"><div class="kpi-top"><i class="ph-fill ph-package kpi-icon"></i><span class="kpi-tag" data-i18n="kpi_tag_catalogo">Catálogo</span></div><div class="kpi-val" id="metricProductos"><?= number_format($tp) ?></div><div class="kpi-label" data-i18n="nav_productos_old">Productos</div></div>
                    <div class="kpi-card"><div class="kpi-top"><i class="ph-fill ph-money kpi-icon"></i><span class="kpi-tag" data-i18n="kpi_tag_hoy">Hoy</span></div><div class="kpi-val" id="metricIngresos">$<?= number_format($ih, 2) ?></div><div class="kpi-label" id="metricVentasCount"><?= number_format($vh) ?> <span data-i18n="ventas_realizadas">ventas realizadas</span></div></div>
                </div>
                <div class="grid-2col">
                    <div class="panel"><div class="panel-head"><div><h2 class="panel-title" data-i18n="chart_ventas">Ventas (7 días)</h2><p class="panel-sub" data-i18n="chart_ventas_sub">Ingresos consolidados de toda la red</p></div></div><div class="chart-box"><canvas id="salesChart"></canvas></div></div>
                    <div class="panel"><div class="panel-head"><div><h2 class="panel-title" data-i18n="chart_pagos">Métodos de pago</h2><p class="panel-sub" data-i18n="chart_pagos_sub">Distribución histórica</p></div></div><div class="donut-wrap"><canvas id="paymentChart"></canvas><div class="donut-center"><div class="num" id="donutVentasCount"><?= number_format($vh) ?></div><div class="lbl" data-i18n="ventas_lbl">ventas</div></div></div></div>
                </div>
                <div class="grid-2col">
                    <div class="panel"><div class="panel-head"><div><h2 class="panel-title" data-i18n="atencion_title">Atención pendiente</h2><p class="panel-sub" data-i18n="atencion_sub">Productos bajos o agotados</p></div><button class="chip-btn" data-goto="view-gestion-productos" data-i18n="ir_inventario">Ir al inventario</button></div><div id="lowStockList" class="stock-list" data-i18n="cargando">Cargando...</div></div>
                    <div class="panel">
                        <div class="panel-head"><div><h2 class="panel-title" data-i18n="salud_inv_title">Salud del Inventario</h2><p class="panel-sub" data-i18n="salud_inv_sub">Métricas globales de mercancía</p></div></div>
                        <div class="info-strip"><div><div class="kpi-label" data-i18n="total_unidades">Total de Unidades Físicas</div><div class="kpi-val" style="font-size:26px; margin-top:6px;" id="metricInventarioTotal"><?= number_format($ti) ?></div></div><i class="ph-fill ph-cube kpi-icon" style="font-size:24px; color:var(--green-deep); background:var(--mint); padding:10px; border-radius:14px;"></i></div>
                        <div class="info-strip" style="border:none; padding-bottom:0;"><div><div class="kpi-label" data-i18n="valor_estimado">Valor Estimado (Precio Venta)</div><div class="kpi-val" style="font-size:26px; margin-top:6px; color:var(--success);" id="metricValorInventario">$<?= number_format($valor_inv, 2) ?></div></div><i class="ph-fill ph-chart-pie-slice kpi-icon" style="font-size:24px; color:var(--green-deep); background:var(--mint); padding:10px; border-radius:14px;"></i></div>
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

           <!-- GESTIÓN DE PRODUCTOS UNIFICADA -->
            <section id="view-gestion-productos" class="view-section">
                <div class="header-row">
                    <div>
                        <h1 class="title"><i class="ph ph-package"></i> <span data-i18n="nav_gestion_productos">Gestión de Productos</span></h1>
                        <p class="subtitle" data-i18n="gestion_prod_sub">Catálogo técnico e inventario unificado. Controla existencias y catálogo en un solo lugar.</p>
                    </div>
                    <button class="btn-primary" id="addProductoUnified"><i class="ph ph-plus"></i> <span data-i18n="btn_add_producto">Nuevo Producto</span></button>
                </div>
                
                <div class="panel" style="background: transparent; border: none; padding: 0; box-shadow: none;">
                    <div class="form-inline" style="margin-bottom:24px; background: var(--bg-surface); padding: 20px; border-radius: var(--radius); border: 1px solid rgba(11, 37, 69, 0.06); box-shadow: var(--shadow-sm);">
                        <input class="search-input" id="gpSearch" data-i18n-placeholder="placeholder_buscar_producto" placeholder="Buscar por nombre o código..." style="flex:1; min-width: 240px;">
                        <select class="select-input" id="gpBranch"><option value="" data-i18n="todas_sucursales">Todas las sucursales</option></select>
                        <select class="select-input" id="gpCategory"><option value="" data-i18n="todas_categorias">Todas las categorías</option></select>
                        <select class="select-input" id="gpStock">
                            <option value="" data-i18n="cualquier_stock">Cualquier nivel de stock</option>
                            <option value="bajo" data-i18n="stock_bajo">Stock Bajo</option>
                            <option value="agotado" data-i18n="stock_agotado">Agotados</option>
                        </select>
                    </div>
                    
                    <div class="prod-grid" id="gpGrid"></div>
                </div>
            </section>
            <!-- VENTAS -->
            <section id="view-ventas" class="view-section">
                <div class="header-row"><div><h1 class="title"><i class="ph ph-chart-line-up"></i> <span data-i18n="ven_title">Historial de Ventas</span></h1><p class="subtitle" data-i18n="ven_sub">Supervisa las ventas de toda la red.</p></div></div>
                <div class="panel">
                    <div class="form-inline" style="margin-bottom:20px;">
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
const money = v => '$' + Number(v || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
const notify = (msg, icon='success') => Swal.fire({ toast:true, position:'top-end', icon, title: msg, showConfirmButton:false, timer:2600, background: 'var(--bg-surface)', color: 'var(--ink)' });

async function request(actionStr, options = {}) {
    const r = await fetch(`${endpoint}?action=${actionStr}`, options);
    const text = await r.text();
    let data;
    try { data = JSON.parse(text); } catch(e) { throw new Error('[ERROR DE SISTEMA] Falló la lectura de la respuesta del servidor.'); }
    if (!data.ok) throw new Error(data.msg || 'No fue posible completar la operación.');
    return data;
}
const statusBadge = s => `<span class="badge ${String(s).toUpperCase().includes('INACT') ? 'b-red' : 'b-green'}">${escapeHtml(s)}</span>`;
const empty = (cols, text=null) => `<tr><td colspan="${cols}" style="text-align:center;color:var(--ink-faint);padding:40px">${text || t('sin_registros')}</td></tr>`;

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
        nav_general: 'Resumen General', nav_sucursales: 'Sucursales', nav_personal: 'Personal', 
        nav_gestion_productos: 'Gestión de Productos', nav_productos_old: 'Productos', nav_ventas: 'Ventas', nav_catalogo: 'Ir al Catálogo', nav_home: 'Volver a Home', nav_logout: 'Cerrar sesión',
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
        placeholder_buscar_producto: 'Buscar producto o código...',
        todas_sucursales: 'Todas las sucursales', todas_categorias: 'Todas las categorías', cualquier_stock: 'Cualquier nivel de stock',
        stock_bajo: 'Stock Bajo', stock_agotado: 'Agotados',
        gestion_prod_sub: 'Catálogo técnico e inventario unificado. Controla existencias y catálogo en un solo lugar.', btn_add_producto: 'Nuevo Producto',
        th_codigo: 'Código', th_categoria: 'Categoría',
        ven_title: 'Historial de Ventas', ven_sub: 'Supervisa las ventas de toda la red.',
        th_folio: 'Folio', th_fecha: 'Fecha', th_pago: 'Pago', th_total: 'Total',
        editar: 'Editar', ver_stock: 'Ver stock', ver: 'Ver', guardar: 'Guardar', cancelar: 'Cancelar', si_eliminar: 'Sí, eliminar',
        sin_registros: 'No hay registros.', panel_actualizado: 'Panel actualizado.', inv_saludable: 'Todo el inventario está en niveles saludables.',
        sin_datos: 'Sin datos.', no_asignado: 'No está asignado a ninguna sucursal.',
        modal_prod_new: 'Nuevo Producto', modal_prod_edit: 'Editar Producto', img_url: 'Imagen del Producto (subir archivo o ingresar URL)', desc_lbl: 'Descripción', asign_suc: 'Asignar a sucursales:',
        funcion_csv_desarrollo: 'Función de exportación CSV en desarrollo.',
        ajustar_stock_title: 'Ajustar Stock', sucursal_destino: 'Sucursal Destino', tipo_movimiento: 'Tipo de movimiento', cantidad_piezas: 'Cantidad de piezas',
        mov_entrada: 'Entrada de mercancía (Sumar)', mov_salida: 'Salida / Merma (Restar)', mov_reemplazo: 'Ajuste Fijo (Reemplazar total)', aplicar_ajuste: 'Aplicar Ajuste'
    },
    en: {
        brand_sub: 'General Administration', role_chip: 'Admin', eyebrow_panel: 'Main panel',
        nav_section_panel: 'Overview', nav_section_gestion: 'Management', nav_section_nav: 'Navigation',
        nav_general: 'Dashboard', nav_sucursales: 'Branches', nav_personal: 'Staff', 
        nav_gestion_productos: 'Product Management', nav_productos_old: 'Products', nav_ventas: 'Sales', nav_catalogo: 'Go to Catalog', nav_home: 'Back to Home', nav_logout: 'Log out',
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
        placeholder_buscar_producto: 'Search product or code...',
        todas_sucursales: 'All branches', todas_categorias: 'All categories', cualquier_stock: 'Any stock level',
        stock_bajo: 'Low Stock', stock_agotado: 'Out of stock',
        gestion_prod_sub: 'Technical catalog and unified inventory. Control stock and catalog in one place.', btn_add_producto: 'New Product',
        th_codigo: 'Code', th_categoria: 'Category',
        ven_title: 'Sales History', ven_sub: 'Monitor sales across the whole network.',
        th_folio: 'ID', th_fecha: 'Date', th_pago: 'Payment', th_total: 'Total',
        editar: 'Edit', ver_stock: 'View stock', ver: 'View', guardar: 'Save', cancelar: 'Cancel', si_eliminar: 'Yes, delete',
        sin_registros: 'No records.', panel_actualizado: 'Dashboard refreshed.', inv_saludable: 'All inventory is at healthy levels.',
        sin_datos: 'No data.', no_asignado: 'Not assigned to any branch.',
        modal_prod_new: 'New Product', modal_prod_edit: 'Edit Product', img_url: 'Image URL', desc_lbl: 'Description', asign_suc: 'Assign to branches:',
        funcion_csv_desarrollo: 'CSV export feature in development.',
        ajustar_stock_title: 'Adjust Stock', sucursal_destino: 'Target Branch', tipo_movimiento: 'Movement type', cantidad_piezas: 'Quantity',
        mov_entrada: 'Inbound (Add)', mov_salida: 'Outbound (Subtract)', mov_reemplazo: 'Fixed Adjustment (Replace)', aplicar_ajuste: 'Apply Adjustment'
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
const titleKeys = { 'view-general':'nav_general', 'view-sucursales':'nav_sucursales', 'view-personal':'nav_personal', 'view-gestion-productos':'nav_gestion_productos', 'view-ventas':'ven_title' };
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
    if (targetId === 'view-gestion-productos') loadGestionProductos();
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
        $('#lowStockList').innerHTML = low.length ? low.map(x => `<div class="stock-row"><span class="stock-thumb"><i class="ph ph-package"></i></span><div><div class="stock-name">${escapeHtml(x.producto)}</div><div class="stock-meta">${escapeHtml(x.sucursal)}</div></div><div class="stock-qty">${escapeHtml(x.existencias)}<small>mín. ${escapeHtml(x.stock_minimo)}</small></div></div>`).join('') : `<p class="subtitle" style="font-size:14px; padding:10px 0;">${t('inv_saludable')}</p>`;
    } catch (e) {}
}
$('#refreshDashboard').addEventListener('click', () => { loadDashboard(); notify(t('panel_actualizado')); });

// -------- SUCURSALES --------
async function loadSucursales() {
    try {
        const { data } = await request('get_sucursales');
        $('#sucursalesTable').innerHTML = data.length ? data.map(x => `<tr>
            <td><b style="color:var(--navy);font-weight:700;">${escapeHtml(x.nombre)}</b></td><td>${escapeHtml(x.direccion||'-')}</td><td>${escapeHtml(x.telefono||'-')}</td><td>${escapeHtml(x.contacto||'-')}</td>
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
            <label class="swal2-label" style="font-weight:600; font-size:13px; color:var(--navy); display:block; margin-bottom:6px;">${L.nombre}</label><input id="swal-nombre" class="swal2-input" style="width:100%;margin:0 0 16px;" value="${editing?escapeHtml(record.nombre):''}">
            <label class="swal2-label" style="font-weight:600; font-size:13px; color:var(--navy); display:block; margin-bottom:6px;">${L.dir}</label><input id="swal-dir" class="swal2-input" style="width:100%;margin:0 0 16px;" value="${editing?escapeHtml(record.direccion||''):''}">
            <label class="swal2-label" style="font-weight:600; font-size:13px; color:var(--navy); display:block; margin-bottom:6px;">${L.tel}</label><input id="swal-tel" class="swal2-input" style="width:100%;margin:0 0 16px;" value="${editing?escapeHtml(record.telefono||''):''}">
            <label class="swal2-label" style="font-weight:600; font-size:13px; color:var(--navy); display:block; margin-bottom:6px;">${L.cont}</label><input id="swal-cont" class="swal2-input" style="width:100%;margin:0 0 16px;" value="${editing?escapeHtml(record.contacto||''):''}">
            ${editing ? `<label class="swal2-label" style="font-weight:600; font-size:13px; color:var(--navy); display:block; margin-bottom:6px;">${L.estado}</label><select id="swal-estado" class="swal2-select" style="width:100%;margin:0;"><option ${record.estado==='ACTIVA'?'selected':''}>ACTIVA</option><option${record.estado==='INACTIVA'?'selected':''}>INACTIVA</option></select>` : ''}
        </div>`,
        confirmButtonText: t('guardar'), confirmButtonColor: 'var(--green)', showCancelButton: true, cancelButtonText: t('cancelar'),
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
            <td><b style="color:var(--navy);font-weight:700;">${escapeHtml(x.nombre)} ${escapeHtml(x.apellido||'')}</b><br><small style="color:var(--ink-faint);font-weight:600;">${escapeHtml(x.correo)}</small></td>
            <td><span style="font-weight:700;color:var(--ink);">${escapeHtml(x.rol||'-')}</span></td><td>${escapeHtml(x.sucursal)}</td><td>${statusBadge(x.estado)}</td>
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
            <label class="swal2-label" style="font-weight:600; font-size:13px; color:var(--navy); display:block; margin-bottom:6px;">${Lp.nombre}</label><input id="swal-nom" class="swal2-input" style="width:100%;margin:0 0 16px;" value="${editing?escapeHtml(record.nombre):''}">
            <label class="swal2-label" style="font-weight:600; font-size:13px; color:var(--navy); display:block; margin-bottom:6px;">${Lp.apellido}</label><input id="swal-ape" class="swal2-input" style="width:100%;margin:0 0 16px;" value="${editing?escapeHtml(record.apellido||''):''}">
            <label class="swal2-label" style="font-weight:600; font-size:13px; color:var(--navy); display:block; margin-bottom:6px;">${Lp.correo}</label><input id="swal-correo" type="email" class="swal2-input" style="width:100%;margin:0 0 16px;" value="${editing?escapeHtml(record.correo):''}">
            <label class="swal2-label" style="font-weight:600; font-size:13px; color:var(--navy); display:block; margin-bottom:6px;">${Lp.rol}</label><select id="swal-rol" class="swal2-select" style="width:100%;margin:0 0 16px;">${roleOptions}</select>
            <label class="swal2-label" style="font-weight:600; font-size:13px; color:var(--navy); display:block; margin-bottom:6px;">${Lp.suc}</label><select id="swal-suc" class="swal2-select" style="width:100%;margin:0 0 16px;">${branchOptions}</select>
            <label class="swal2-label" style="font-weight:600; font-size:13px; color:var(--navy); display:block; margin-bottom:6px;">${Lp.pass} <span style="color:var(--ink-faint); font-weight:normal;">${editing?Lp.passEdit:'*'}</span></label><input id="swal-pass" type="password" class="swal2-input" style="width:100%;margin:0 0 16px;">
            ${editing ? `<label class="swal2-label" style="font-weight:600; font-size:13px; color:var(--navy); display:block; margin-bottom:6px;">${Lp.estado}</label><select id="swal-estado" class="swal2-select" style="width:100%;margin:0;"><option ${record.estado==='ACTIVO'?'selected':''}>ACTIVO</option><option${record.estado==='INACTIVO'?'selected':''}>INACTIVO</option></select>` : ''}
        </div>`,
        confirmButtonText: t('guardar'), confirmButtonColor: 'var(--green)', showCancelButton: true, cancelButtonText: t('cancelar'),
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

// -------- GESTIÓN UNIFICADA DE PRODUCTOS --------
async function loadFiltrosGP() {
    try {
        const [cats, sucs] = await Promise.all([request('get_categorias'), request('get_sucursales_simple')]);
        const catLabel = t('todas_categorias');
        const sucLabel = t('todas_sucursales');
        $('#gpCategory').innerHTML = `<option value="">${catLabel}</option>` + cats.data.map(c => `<option value="${c.id_categoria}">${escapeHtml(c.nombre)}</option>`).join('');
        $('#gpBranch').innerHTML = `<option value="">${sucLabel}</option>` + sucs.data.map(s => `<option value="${s.id_sucursal}">${escapeHtml(s.nombre)}</option>`).join('');
    } catch(e) {}
}

async function loadGestionProductos() {
    try {
        const q = $('#gpSearch').value;
        const suc = $('#gpBranch').value;
        const cat = $('#gpCategory').value;
        const stock = $('#gpStock').value;

        const { data } = await request(`get_gestion_productos&q=${encodeURIComponent(q)}&sucursal=${suc}&categoria=${cat}&stock=${stock}`);
        
        $('#gpGrid').innerHTML = data.length ? data.map(x => {
            const statusCls = x.estado === 'ACTIVO' ? 'color: var(--success);' : 'color: var(--danger);';
            const img = x.imagen_url ? escapeHtml(x.imagen_url) : 'https://placehold.co/300x300?text=Sin+Imagen';
            
            let stockClass = 'stock-ok';
            let stockLabel = `Stock: ${x.total_existencias}`;
            
            if (x.total_existencias <= 0) { 
                stockClass = 'stock-none'; 
                stockLabel = 'AGOTADO';
            } else if (x.total_existencias <= x.stock_minimo) { 
                stockClass = 'stock-low'; 
                stockLabel = `Bajo: ${x.total_existencias}`;
            }

            return `
            <div class="prod-card">
                <div class="prod-card-img-wrap">
                    <img src="${img}" class="prod-card-img" alt="${escapeHtml(x.nombre)}">
                </div>
                <div class="prod-card-body">
                    <div class="prod-meta"><span>${escapeHtml(x.categoria)}</span> <span style="${statusCls}">${escapeHtml(x.estado)}</span></div>
                    <h3 class="prod-title" title="${escapeHtml(x.nombre)}">${escapeHtml(x.nombre)}</h3>
                    
                    <div class="prod-price-row">
                        <div class="prod-price">${money(x.precio)}</div>
                        <div class="tarjeta__stock ${stockClass}">${stockLabel}</div>
                    </div>

                    <div class="prod-actions">
                        <button class="btn-card" data-edit-prod='${JSON.stringify(x).replace(/'/g,'&#39;')}'><i class="ph ph-pencil-simple"></i> ${t('editar')}</button>
                        <button class="btn-card btn-card-del" data-del-prod="${x.id_producto}"><i class="ph ph-trash"></i> Borrar</button>
                        <button class="btn-card btn-card-full" data-ajustar-stock="${x.id_producto}" data-nombre="${escapeHtml(x.nombre)}"><i class="ph ph-arrows-left-right"></i> Ajustar Stock</button>
                    </div>
                </div>
            </div>`;
        }).join('') : `<div style="grid-column: 1 / -1; text-align:center; padding: 40px; color:var(--ink-faint); font-weight:600; font-size:16px;">${t('sin_registros')}</div>`;
    } catch (e) { notify(e.message, 'error'); }
}

['#gpSearch', '#gpBranch', '#gpCategory', '#gpStock'].forEach(s => $(s).addEventListener('change', loadGestionProductos));
$('#gpSearch').addEventListener('input', (e) => { clearTimeout(window.searchT); window.searchT = setTimeout(loadGestionProductos, 300); });

async function ajustarStockModalAdmin(id_producto, nombre_producto) {
    const { data: sucs } = await request('get_sucursales_simple');
    if(!sucs.length) { notify('No hay sucursales activas.', 'error'); return; }
    const options = sucs.map(s => `<option value="${s.id_sucursal}">${escapeHtml(s.nombre)}</option>`).join('');

    const { value: form } = await Swal.fire({
        title: t('ajustar_stock_title'),
        html: `
            <div style="text-align:left; font-size:14px;">
                <p style="margin-bottom:18px; color:var(--clinic); font-weight:700; font-size:15px; border-bottom:1px solid var(--border-soft); padding-bottom:10px;">${nombre_producto}</p>
                <label class="swal2-label" style="font-weight:600; font-size:13px; color:var(--navy); display:block; margin-bottom:6px;">${t('sucursal_destino')}</label>
                <select id="swal-s" class="swal2-select" style="width:100%;margin:0 0 16px;">${options}</select>
                
                <label class="swal2-label" style="font-weight:600; font-size:13px; color:var(--navy); display:block; margin-bottom:6px;">${t('tipo_movimiento')}</label>
                <select id="swal-t" class="swal2-select" style="width:100%;margin:0 0 16px;">
                    <option value="entrada">${t('mov_entrada')}</option>
                    <option value="salida">${t('mov_salida')}</option>
                    <option value="reemplazo">${t('mov_reemplazo')}</option>
                </select>
                
                <label class="swal2-label" style="font-weight:600; font-size:13px; color:var(--navy); display:block; margin-bottom:6px;">${t('cantidad_piezas')}</label>
                <input id="swal-c" type="number" min="0" value="1" class="swal2-input" style="width:100%;margin:0;">
            </div>
        `,
        confirmButtonText: t('aplicar_ajuste'), confirmButtonColor: 'var(--green)', showCancelButton: true, cancelButtonText: t('cancelar'),
        preConfirm: () => ({
            id_producto,
            id_sucursal: document.getElementById('swal-s').value,
            tipo: document.getElementById('swal-t').value,
            cantidad: document.getElementById('swal-c').value
        })
    });
    
    if (form) {
        try {
            const fd = new FormData(); Object.entries(form).forEach(([k,v]) => fd.append(k,v));
            const d = await request('ajustar_stock', { method:'POST', body: fd });
            notify(d.msg); loadGestionProductos(); loadDashboard();
        } catch (e) { notify(e.message, 'error'); }
    }
}

// CREAR/EDITAR PRODUCTO
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
    const branchChecks = sucursales.map(s => `<label style="font-size:13.5px; display:flex; align-items:center; gap:8px; font-weight:600;"><input type="checkbox" class="swal-suc-chk" value="${s.id_sucursal}" ${asignadas.includes(s.id_sucursal)?'checked':''} style="width:16px;height:16px;accent-color:var(--green);"> ${escapeHtml(s.nombre)}</label>`).join('');

    const { value: form } = await Swal.fire({
        title: editing ? t('modal_prod_edit') : t('modal_prod_new'),
        html: `<div style="text-align:left;">
            <div style="text-align:center; margin-bottom:18px;">
                <img id="swal-preview" class="swal-preview-img" src="${editing && record.imagen_url ? escapeHtml(record.imagen_url) : 'https://placehold.co/150x150?text=Sin+Imagen'}" style="max-width:140px; max-height:140px; object-fit:cover; border-radius:18px; border:1.5px solid rgba(11, 37, 69, 0.22); box-shadow:var(--shadow-sm);">
            </div>
            
            <label class="swal2-label" style="font-weight:700; font-size:13px; color:var(--navy); display:block; margin-bottom:6px;">${t('img_url')}</label>
            <input type="file" id="swal-file" accept="image/jpeg,image/png,image/webp,image/gif" class="swal2-file" style="width:100%; margin:0 0 10px; font-size:13px; border-radius:12px;">
            <input id="swal-img" class="swal2-input" style="width:100%;margin:0 0 16px; font-size:13px;" value="${editing?escapeHtml(record.imagen_url||''):''}" placeholder="O pega un enlace de imagen (https://...)">
            
            <div style="display:flex; gap:12px; margin-bottom:16px;">
                <div style="flex:1"><label class="swal2-label" style="font-weight:700; font-size:13px; color:var(--navy); display:block; margin-bottom:6px;">${t('th_codigo')} *</label><input id="swal-codigo" class="swal2-input" style="width:100%;margin:0;" value="${editing?escapeHtml(record.codigo):''}"></div>
                <div style="flex:2"><label class="swal2-label" style="font-weight:700; font-size:13px; color:var(--navy); display:block; margin-bottom:6px;">${t('th_producto')} *</label><input id="swal-nombre" class="swal2-input" style="width:100%;margin:0;" value="${editing?escapeHtml(record.nombre):''}"></div>
            </div>
            
            <label class="swal2-label" style="font-weight:700; font-size:13px; color:var(--navy); display:block; margin-bottom:6px;">${t('desc_lbl')}</label>
            <textarea id="swal-desc" class="swal2-textarea" style="width:100%;margin:0 0 16px; min-height:80px;">${editing?escapeHtml(record.descripcion||''):''}</textarea>
            
            <div style="display:flex; gap:12px; margin-bottom:16px;">
                <div style="flex:1"><label class="swal2-label" style="font-weight:700; font-size:13px; color:var(--navy); display:block; margin-bottom:6px;">${t('th_categoria')}</label><select id="swal-cat" class="swal2-select" style="width:100%;margin:0;">${catOptions}</select></div>
                <div style="flex:1"><label class="swal2-label" style="font-weight:700; font-size:13px; color:var(--navy); display:block; margin-bottom:6px;">${t('th_precio')} *</label><input id="swal-precio" type="number" step="0.01" class="swal2-input" style="width:100%;margin:0;" value="${editing?record.precio:''}"></div>
            </div>

            <div style="padding:16px; background:var(--bg-panel); border:1px solid rgba(11, 37, 69, 0.12); border-radius:20px; margin-bottom:16px;">
                <label class="swal2-label" style="font-weight:700; font-size:14px; color:var(--navy); display:block; margin-bottom:10px;">${t('asign_suc')}</label>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">${branchChecks || '<span style="font-size:13px;color:var(--ink-faint);font-weight:600;">No hay sucursales activas.</span>'}</div>
            </div>
            ${editing ? `<label class="swal2-label" style="font-weight:700; font-size:13px; color:var(--navy); display:block; margin-bottom:6px;">Estado</label><select id="swal-estado" class="swal2-select" style="width:100%;margin:0;"><option ${record.estado==='ACTIVO'?'selected':''}>ACTIVO</option><option${record.estado==='INACTIVO'?'selected':''}>INACTIVO</option></select>` : ''}
        </div>`,
        confirmButtonText: t('guardar'), confirmButtonColor: 'var(--green)', showCancelButton: true, cancelButtonText: t('cancelar'), width: 580,
        didOpen: () => {
            const preview = document.getElementById('swal-preview');
            const fileIn = document.getElementById('swal-file');
            const urlIn = document.getElementById('swal-img');

            fileIn.addEventListener('change', e => {
                if (e.target.files && e.target.files[0]) {
                    preview.src = URL.createObjectURL(e.target.files[0]);
                }
            });
            urlIn.addEventListener('input', e => {
                const val = e.target.value.trim();
                if (val) {
                    preview.src = val;
                } else if (fileIn.files && fileIn.files[0]) {
                    preview.src = URL.createObjectURL(fileIn.files[0]);
                } else {
                    preview.src = 'https://placehold.co/150x150?text=Sin+Imagen';
                }
            });
        },
        preConfirm: () => {
            const codigo = document.getElementById('swal-codigo').value.trim();
            const nombre = document.getElementById('swal-nombre').value.trim();
            if (!codigo || !nombre) { Swal.showValidationMessage('Código y nombre son obligatorios.'); return false; }
            const sucs = Array.from(document.querySelectorAll('.swal-suc-chk:checked')).map(c => c.value);
            const fileEl = document.getElementById('swal-file');
            const out = { 
                codigo, 
                nombre, 
                imagen_url: document.getElementById('swal-img').value.trim(),
                imagen_archivo: (fileEl.files && fileEl.files[0]) ? fileEl.files[0] : null,
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
        Object.entries(form).forEach(([k,v]) => {
            if (k === 'sucursales') {
                v.forEach(s => fd.append('sucursales[]', s));
            } else if (k === 'imagen_archivo') {
                if (v) fd.append('imagen_archivo', v);
            } else if (v !== null && v !== undefined) {
                fd.append(k, v);
            }
        });
        const d = await request(editing ? 'update_producto' : 'create_producto', { method:'POST', body: fd });
        notify(d.msg); 
        if (typeof loadGestionProductos === 'function') loadGestionProductos();
        loadDashboard();
    } catch (e) { notify(e.message, 'error'); }
}

$('#addProductoUnified').addEventListener('click', () => productoModal());
document.addEventListener('click', async e => {
    const editBtn = e.target.closest('[data-edit-prod]');
    if (editBtn) productoModal(JSON.parse(editBtn.dataset.editProd));
    const delBtn = e.target.closest('[data-del-prod]');
    if (delBtn) {
        const conf = await Swal.fire({ title:'¿Eliminar producto?', text:'Solo procederá si el stock global es 0.', icon:'warning', showCancelButton:true, confirmButtonColor:'var(--danger)', confirmButtonText:t('si_eliminar'), cancelButtonText:t('cancelar') });
        if (!conf.isConfirmed) return;
        try {
            const fd = new FormData(); fd.append('id', delBtn.dataset.delProd);
            const d = await request('delete_producto', { method:'POST', body:fd });
            notify(d.msg); 
            if (typeof loadGestionProductos === 'function') loadGestionProductos(); 
            loadDashboard();
        } catch (err) { notify(err.message, 'error'); }
    }
    const btnAjustar = e.target.closest('[data-ajustar-stock]');
    if (btnAjustar) ajustarStockModalAdmin(btnAjustar.dataset.ajustarStock, btnAjustar.dataset.nombre);
});


async function loadBranchSelects() {
    try {
        const { data } = await request('get_sucursales_simple');
        const options = data.map(x => `<option value="${x.id_sucursal}">${escapeHtml(x.nombre)}</option>`).join('');
        $('#salesBranch').innerHTML = `<option value="">${t('todas_sucursales')}</option>` + options;
    } catch (e) {}
}

// -------- VENTAS --------
async function loadVentas() {
    try {
        const p = new URLSearchParams({ sucursal: $('#salesBranch').value, desde: $('#salesFrom').value, hasta: $('#salesTo').value });
        const { data } = await request(`get_ventas&${p}`);
        $('#ventasTable').innerHTML = data.length ? data.map(x => `<tr>
            <td><b style="color:var(--navy);font-weight:700;">#${x.id_venta}</b></td><td>${new Date(x.fecha_hora).toLocaleString('es-MX')}</td><td>${escapeHtml(x.sucursal)}</td><td>${escapeHtml(x.empleado)}</td><td><span style="font-weight:600;color:var(--ink-soft);">${escapeHtml(x.metodo_pago)}</span></td>
            <td><b style="font-size:16px;color:var(--clinic);font-family:var(--font-lux);">${money(x.total)}</b></td><td>${statusBadge(x.estado)}</td>
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
        Swal.fire({ title: `Detalle Venta #${verBtn.dataset.verVenta}`, html: data.length ? `<table style="width:100%;text-align:left;font-size:14px;"><tr style="border-bottom:2px solid var(--border-soft);"><th style="padding-bottom:10px;">Producto</th><th style="padding-bottom:10px;">Cant</th><th style="text-align:right;padding-bottom:10px;">Subtotal</th></tr>${data.map(x=>`<tr><td style="padding:12px 0;">${escapeHtml(x.producto)}</td><td style="padding:12px 0;font-weight:700;">${x.cantidad}</td><td style="text-align:right;padding:12px 0;font-weight:700;color:var(--navy);">${money(x.subtotal)}</td></tr>`).join('')}</table>` : t('sin_datos') });
    } catch (e) { notify(e.message, 'error'); }
});

// -------- INICIO --------
const initial = <?= json_encode(['labels' => $v7['labels'] ?? [], 'ingresos' => $v7['ingresos'] ?? [], 'payments' =>$mp], JSON_UNESCAPED_UNICODE) ?>;
window.addEventListener('DOMContentLoaded', () => {
    const palette = ['#1B6CA8', '#22C55E', '#0B2545', '#E8F6EE', '#16A34A']; // Colores catálogos
    if (window.Chart) {
        Chart.defaults.font.family = '"Plus Jakarta Sans", sans-serif';
        salesChartInstance = new Chart($('#salesChart'), {
            type: 'line',
            data: { labels: initial.labels || [], datasets: [{ label: 'Ingresos', data: initial.ingresos || [], borderColor: '#1B6CA8', backgroundColor: 'rgba(27, 108, 168, 0.12)', fill: true, tension: .4, pointRadius: 4, pointBackgroundColor: '#1B6CA8', pointBorderColor: '#fff', pointBorderWidth: 2 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: 'rgba(11, 37, 69, 0.05)' } }, x: { grid: { display: false } } } }
        });
        paymentChartInstance = new Chart($('#paymentChart'), {
            type: 'doughnut',
            data: { labels: (initial.payments || []).map(x => x.metodo), datasets: [{ data: (initial.payments || []).map(x => x.total_ventas), backgroundColor: palette, borderWidth: 0 }] },
            options: { responsive: true, maintainAspectRatio: false, cutout: '76%', plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20 } } } }
        });
    }
    loadBranchSelects();
    loadDashboard();
    loadFiltrosGP();
});
</script>
</body>
</html>