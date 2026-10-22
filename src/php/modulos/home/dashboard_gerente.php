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

// --------------------------------------------------------------
// Resolver el usuario/gerente en sesión y su sucursal asignada
// --------------------------------------------------------------
$id_usuario_actual = 0;
if (is_array($_SESSION['usuario'] ?? null)) {
    $id_usuario_actual = (int)($_SESSION['usuario']['id_usuario'] ?? $_SESSION['usuario']['id'] ?? 0);
} else {
    $id_usuario_actual = (int)($_SESSION['usuario'] ?? 0);
}

$gerente = null;
if (isset($pdo) && $pdo instanceof PDO && $id_usuario_actual > 0) {
    $stG = $pdo->prepare("SELECT u.id_usuario, u.nombre, u.apellido, u.id_sucursal, r.nombre AS rol
                           FROM usuarios u LEFT JOIN roles r ON r.id_rol = u.id_rol
                           WHERE u.id_usuario = ?");
    $stG->execute([$id_usuario_actual]);
    $gerente = $stG->fetch(PDO::FETCH_ASSOC) ?: null;
}
$id_sucursal = (int)($gerente['id_sucursal'] ?? 0);
$nombreGerente = trim(($gerente['nombre'] ?? 'Gerente') . ' ' . ($gerente['apellido'] ?? ''));
$esGerente = stripos((string)($gerente['rol'] ?? ''), 'gerente') !== false;

// --------------------------------------------------------------
// API (AJAX) — todas las acciones quedan restringidas a $id_sucursal
// --------------------------------------------------------------
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $out = ['ok' => false, 'msg' => 'Acción desconocida'];
    try {
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            throw new RuntimeException('[ERROR DE SISTEMA] No fue posible conectar con la base de datos.');
        }
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // 1. Validar explícitamente el rol desde la sesión
        $rolSesion = $_SESSION['usuario']['rol'] ?? '';
        $esGerente = stripos((string)$rolSesion, 'gerente') !== false;

        // 2. Bloquear si no es gerente o si no tiene sucursal
        if (!$esGerente || $id_sucursal <= 0) {
            throw new RuntimeException('[ACCESO DENEGADO] No tienes permisos de gerente o no tienes una sucursal asignada. Contacta al administrador.');
        }

        if ($action === 'get_metrics') {
            $rv = $pdo->prepare("SELECT COUNT(*) n, COALESCE(SUM(total),0) t FROM ventas WHERE id_sucursal=? AND DATE(fecha_hora)=CURDATE()");
            $rv->execute([$id_sucursal]); $rv = $rv->fetch();
            $stB = $pdo->prepare("SELECT COUNT(*) FROM inventarios WHERE id_sucursal=? AND existencias<=stock_minimo");
            $stB->execute([$id_sucursal]); $bajo = (int)$stB->fetchColumn();
            $stP = $pdo->prepare("SELECT COUNT(*) FROM inventarios WHERE id_sucursal=?");
            $stP->execute([$id_sucursal]); $totalProd = (int)$stP->fetchColumn();
            $stC = $pdo->prepare("SELECT COUNT(*) FROM cajas WHERE id_sucursal=? AND estado='ACTIVA'");
            $stC->execute([$id_sucursal]); $cajasActivas = (int)$stC->fetchColumn();

            $labels = []; $ingresos = [];
            for ($i = 6; $i >= 0; $i--) {
                $fd = date('Y-m-d', strtotime("-{$i} days"));
                $fl = date('d/m', strtotime("-{$i} days"));
                $r = $pdo->prepare("SELECT COALESCE(SUM(total),0) t FROM ventas WHERE id_sucursal=? AND DATE(fecha_hora)=?");
                $r->execute([$id_sucursal, $fd]);
                $labels[] = $fl; $ingresos[] = (float)$r->fetchColumn();
            }

            $out = ['ok' => true, 'data' => [
                'ih' => (float)($rv['t'] ?? 0), 'vh' => (int)($rv['n'] ?? 0),
                'bajo' => $bajo, 'totalProd' => $totalProd, 'cajasActivas' => $cajasActivas,
                'labels' => $labels, 'ingresos' => $ingresos
            ]];

        } elseif ($action === 'get_sucursal') {
            $st = $pdo->prepare("SELECT id_sucursal, nombre, direccion, telefono, contacto, estado FROM sucursales WHERE id_sucursal=?");
            $st->execute([$id_sucursal]);
            $out = ['ok' => true, 'data' => $st->fetch(PDO::FETCH_ASSOC) ?: null];

        } elseif ($action === 'get_categorias') {
            try { $data = $pdo->query("SELECT id_categoria, nombre FROM categorias ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) { $data = []; }
            $out = ['ok' => true, 'data' => $data];

        } elseif ($action === 'get_metodos_pago') {
            try { $data = $pdo->query("SELECT id_metodo_pago, nombre FROM metodos_pago ORDER BY id_metodo_pago")->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) { $data = []; }
            $out = ['ok' => true, 'data' => $data];

        } elseif ($action === 'get_productos') {
            $q = trim($_GET['q'] ?? '');
            $sql = "SELECT p.id_producto, p.codigo, p.nombre, p.descripcion, p.id_categoria, p.precio, p.estado,
                           COALESCE(c.nombre,'Sin categoría') AS categoria, i.existencias, i.stock_minimo
                    FROM productos p
                    JOIN inventarios i ON i.id_producto=p.id_producto AND i.id_sucursal=?
                    LEFT JOIN categorias c ON c.id_categoria=p.id_categoria
                    WHERE 1=1";
            $params = [$id_sucursal];
            if ($q !== '') { $sql .= " AND (p.codigo LIKE ? OR p.nombre LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; }
            $sql .= " ORDER BY p.nombre ASC LIMIT 200";
            $st = $pdo->prepare($sql); $st->execute($params);
            $out = ['ok' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)];

        } elseif ($action === 'create_producto') {
            $codigo = trim($_POST['codigo'] ?? ''); $nombre = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? ''); $categoria = (int)($_POST['id_categoria'] ?? 0);
            $precio = (float)str_replace(',', '.', $_POST['precio'] ?? '0');
            $existInicial = max(0, (int)($_POST['existencias'] ?? 0));
            $stockMin = max(0, (int)($_POST['stock_minimo'] ?? 5));
            if ($codigo === '' || $nombre === '') throw new Exception('[ERROR DE USUARIO] El código y el nombre son obligatorios.');

            $pdo->beginTransaction();
            try {
                $pdo->prepare("INSERT INTO productos(codigo,nombre,descripcion,id_categoria,precio,estado) VALUES(?,?,?,?,?,'ACTIVO')")
                    ->execute([$codigo, $nombre, $descripcion ?: null, $categoria > 0 ? $categoria : null, $precio]);
                $newId = (int)$pdo->lastInsertId();
                $pdo->prepare("INSERT INTO inventarios(id_producto,id_sucursal,existencias,stock_minimo) VALUES(?,?,?,?)")
                    ->execute([$newId, $id_sucursal, $existInicial, $stockMin]);
                $pdo->commit();
                $out = ['ok' => true, 'msg' => 'Producto registrado y asignado a tu sucursal.'];
            } catch (Exception $e) { $pdo->rollBack(); throw $e; }

        } elseif ($action === 'update_producto') {
            $id = (int)($_POST['id'] ?? 0);
            $codigo = trim($_POST['codigo'] ?? ''); $nombre = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? ''); $categoria = (int)($_POST['id_categoria'] ?? 0);
            $precio = (float)str_replace(',', '.', $_POST['precio'] ?? '0');
            $estado = ($_POST['estado'] ?? 'ACTIVO') === 'INACTIVO' ? 'INACTIVO' : 'ACTIVO';
            $stockMin = max(0, (int)($_POST['stock_minimo'] ?? 5));
            if (!$id || $codigo === '' || $nombre === '') throw new Exception('[ERROR DE USUARIO] Faltan datos obligatorios.');

            $chk = $pdo->prepare("SELECT COUNT(*) FROM inventarios WHERE id_producto=? AND id_sucursal=?");
            $chk->execute([$id, $id_sucursal]);
            if (!$chk->fetchColumn()) throw new Exception('[ERROR DE USUARIO] Este producto no pertenece a tu sucursal.');

            $pdo->prepare("UPDATE productos SET codigo=?,nombre=?,descripcion=?,id_categoria=?,precio=?,estado=? WHERE id_producto=?")
                ->execute([$codigo, $nombre, $descripcion ?: null, $categoria > 0 ? $categoria : null, $precio, $estado, $id]);
            $pdo->prepare("UPDATE inventarios SET stock_minimo=? WHERE id_producto=? AND id_sucursal=?")
                ->execute([$stockMin, $id, $id_sucursal]);
            $out = ['ok' => true, 'msg' => 'Producto actualizado correctamente.'];

        } elseif ($action === 'get_inventario') {
            $q = trim($_GET['q'] ?? ''); $soloBajo = ($_GET['solo_bajo'] ?? '') === '1';
            $sql = "SELECT i.id_inventario, p.id_producto, p.nombre AS producto, p.codigo, i.existencias, i.stock_minimo, p.precio,
                           CASE WHEN i.existencias<=0 THEN 'AGOTADO' WHEN i.existencias<=i.stock_minimo THEN 'BAJO' ELSE 'OK' END AS nivel
                    FROM inventarios i JOIN productos p ON p.id_producto=i.id_producto
                    WHERE i.id_sucursal=?";
            $params = [$id_sucursal];
            if ($q !== '') { $sql .= " AND (p.nombre LIKE ? OR p.codigo LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; }
            if ($soloBajo) { $sql .= " AND i.existencias<=i.stock_minimo"; }
            $sql .= " ORDER BY i.existencias ASC LIMIT 200";
            $st = $pdo->prepare($sql); $st->execute($params);
            $out = ['ok' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)];

        } elseif ($action === 'get_productos_simple') {
            $st = $pdo->prepare("SELECT p.id_producto, p.nombre, p.codigo FROM productos p JOIN inventarios i ON i.id_producto=p.id_producto AND i.id_sucursal=? WHERE p.estado='ACTIVO' ORDER BY p.nombre");
            $st->execute([$id_sucursal]);
            $out = ['ok' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)];

        } elseif ($action === 'ajustar_stock') {
            $id_p = (int)($_POST['id_producto'] ?? 0);
            $cant = (int)($_POST['cantidad'] ?? 0);
            $tipo = $_POST['tipo'] ?? '';
            if (!$id_p) throw new Exception('[ERROR DE USUARIO] Selecciona un producto válido.');
            if ($cant < 0) throw new Exception('[ERROR DE USUARIO] No puedes ingresar cantidades negativas.');

            $st = $pdo->prepare("SELECT existencias FROM inventarios WHERE id_producto=? AND id_sucursal=?");
            $st->execute([$id_p, $id_sucursal]);
            $row = $st->fetch();
            if (!$row) throw new Exception("[ERROR DE USUARIO] Ese producto no está asignado a tu sucursal.");

            $nueva = (int)$row['existencias'];
            if ($tipo === 'entrada') $nueva += $cant;
            elseif ($tipo === 'salida') $nueva = max(0, $nueva - $cant);
            elseif ($tipo === 'reemplazo') $nueva = $cant;

            $pdo->prepare("UPDATE inventarios SET existencias=? WHERE id_producto=? AND id_sucursal=?")->execute([$nueva, $id_p, $id_sucursal]);
            $out = ['ok' => true, 'msg' => "Stock actualizado. Nueva existencia: $nueva unidades."];

        } elseif ($action === 'get_cajas') {
            $st = $pdo->prepare("SELECT c.id_caja, c.nombre, c.estado, c.fecha_registro,
                                        (SELECT COUNT(*) FROM cajeros cj WHERE cj.id_caja=c.id_caja) AS total_cajeros
                                 FROM cajas c WHERE c.id_sucursal=? ORDER BY c.id_caja");
            $st->execute([$id_sucursal]);
            $out = ['ok' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)];

        } elseif ($action === 'get_cajas_activas') {
            $st = $pdo->prepare("SELECT id_caja, nombre FROM cajas WHERE id_sucursal=? AND estado='ACTIVA' ORDER BY nombre");
            $st->execute([$id_sucursal]);
            $out = ['ok' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)];

        } elseif ($action === 'create_caja') {
            $nombre = trim($_POST['nombre'] ?? '');
            if ($nombre === '') throw new Exception('[ERROR DE USUARIO] El nombre de la caja es obligatorio.');
            $pdo->prepare("INSERT INTO cajas(id_sucursal,nombre,estado) VALUES(?,?,'ACTIVA')")->execute([$id_sucursal, $nombre]);
            $out = ['ok' => true, 'msg' => 'Caja registrada correctamente.'];

        } elseif ($action === 'delete_caja') {
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception('[ERROR DE SISTEMA] ID de caja inválido.');
            $chk = $pdo->prepare("SELECT COUNT(*) FROM cajas WHERE id_caja=? AND id_sucursal=?");
            $chk->execute([$id, $id_sucursal]);
            if (!$chk->fetchColumn()) throw new Exception('[ERROR DE USUARIO] Esa caja no pertenece a tu sucursal.');
            $pdo->beginTransaction();
            try {
                $pdo->prepare("DELETE FROM cajeros WHERE id_caja=?")->execute([$id]);
                $pdo->prepare("DELETE FROM cajas WHERE id_caja=?")->execute([$id]);
                $pdo->commit();
            } catch (Exception $e) { $pdo->rollBack(); throw $e; }
            $out = ['ok' => true, 'msg' => 'Caja y sus cajeros asociados fueron eliminados.'];

        } elseif ($action === 'get_cajeros_caja') {
            $id_caja = (int)($_GET['id_caja'] ?? 0);
            $soloActivos = ($_GET['solo_activos'] ?? '1') !== '0';
            $chk = $pdo->prepare("SELECT COUNT(*) FROM cajas WHERE id_caja=? AND id_sucursal=?");
            $chk->execute([$id_caja, $id_sucursal]);
            if (!$chk->fetchColumn()) throw new Exception('[ERROR DE USUARIO] Esa caja no pertenece a tu sucursal.');
            $sql = "SELECT id_cajero, nombre, estado, fecha_registro FROM cajeros WHERE id_caja=? AND id_sucursal=?";
            $params = [$id_caja, $id_sucursal];
            if ($soloActivos) { $sql .= " AND estado='ACTIVO'"; }
            $sql .= " ORDER BY nombre";
            $st = $pdo->prepare($sql); $st->execute($params);
            $out = ['ok' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)];

        } elseif ($action === 'create_cajero') {
            $id_caja = (int)($_POST['id_caja'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $pass = trim($_POST['password'] ?? '');
            if (!$id_caja) throw new Exception('[ERROR DE USUARIO] Selecciona una caja válida.');
            if ($nombre === '' || $pass === '') throw new Exception('[ERROR DE USUARIO] El nombre y la contraseña del cajero son obligatorios.');
            if (strlen($pass) < 4) throw new Exception('[ERROR DE USUARIO] La contraseña del cajero debe tener al menos 4 caracteres.');
            $chk = $pdo->prepare("SELECT COUNT(*) FROM cajas WHERE id_caja=? AND id_sucursal=?");
            $chk->execute([$id_caja, $id_sucursal]);
            if (!$chk->fetchColumn()) throw new Exception('[ERROR DE USUARIO] Esa caja no pertenece a tu sucursal.');
            $pdo->prepare("INSERT INTO cajeros(id_caja,id_sucursal,nombre,password_hash,estado) VALUES(?,?,?,?,'ACTIVO')")
                ->execute([$id_caja, $id_sucursal, $nombre, password_hash($pass, PASSWORD_BCRYPT)]);
            $out = ['ok' => true, 'msg' => 'Cajero registrado y vinculado a la caja.'];

        } elseif ($action === 'delete_cajero') {
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception('[ERROR DE SISTEMA] ID de cajero inválido.');
            $chk = $pdo->prepare("SELECT COUNT(*) FROM cajeros WHERE id_cajero=? AND id_sucursal=?");
            $chk->execute([$id, $id_sucursal]);
            if (!$chk->fetchColumn()) throw new Exception('[ERROR DE USUARIO] Ese cajero no pertenece a tu sucursal.');
            $pdo->prepare("DELETE FROM cajeros WHERE id_cajero=?")->execute([$id]);
            $out = ['ok' => true, 'msg' => 'Cajero eliminado.'];

        } elseif ($action === 'login_cajero') {
            $id_caja = (int)($_POST['id_caja'] ?? 0);
            $id_cajero = (int)($_POST['id_cajero'] ?? 0);
            $pass = (string)($_POST['password'] ?? '');
            $dineroInicial = (float)str_replace(',', '.', $_POST['dinero_inicial'] ?? '0');
            if (!$id_caja || !$id_cajero || $pass === '') throw new Exception('[ERROR DE USUARIO] Completa la caja, el cajero y la contraseña.');
            if ($dineroInicial < 0) throw new Exception('[ERROR DE USUARIO] El fondo de caja inicial no puede ser negativo.');

            $stCaja = $pdo->prepare("SELECT nombre FROM cajas WHERE id_caja=? AND id_sucursal=? AND estado='ACTIVA'");
            $stCaja->execute([$id_caja, $id_sucursal]);
            $nombreCaja = $stCaja->fetchColumn();
            if (!$nombreCaja) throw new Exception('[ERROR DE USUARIO] Esa caja no está disponible en tu sucursal.');

            $stCaj = $pdo->prepare("SELECT nombre, password_hash FROM cajeros WHERE id_cajero=? AND id_caja=? AND id_sucursal=? AND estado='ACTIVO'");
            $stCaj->execute([$id_cajero, $id_caja, $id_sucursal]);
            $cajero = $stCaj->fetch(PDO::FETCH_ASSOC);
            if (!$cajero) throw new Exception('[ERROR DE USUARIO] Ese cajero no tiene acceso a la caja seleccionada.');
            if (!password_verify($pass, $cajero['password_hash'])) throw new Exception('[ERROR DE USUARIO] Contraseña de cajero incorrecta.');

            // ¿Ya hay un turno abierto en esta caja? Si es del mismo cajero, se reanuda; si no, se bloquea.
            $stAbierto = $pdo->prepare("SELECT id_turno, id_cajero, dinero_inicial, fecha_inicio, horas_extra FROM turnos_caja WHERE id_caja=? AND estado='ABIERTO' LIMIT 1");
            $stAbierto->execute([$id_caja]);
            $abierto = $stAbierto->fetch(PDO::FETCH_ASSOC);
            if ($abierto) {
                if ((int)$abierto['id_cajero'] !== $id_cajero) {
                    throw new Exception('[ERROR DE USUARIO] Esta caja ya tiene un turno abierto con otro cajero. Ciérralo antes de iniciar uno nuevo.');
                }
                $out = ['ok' => true, 'msg' => 'Turno reanudado.', 'data' => [
                    'nombre_cajero' => $cajero['nombre'], 'nombre_caja' => $nombreCaja, 'id_caja' => $id_caja, 'id_cajero' => $id_cajero,
                    'id_turno' => (int)$abierto['id_turno'], 'dinero_inicial' => (float)$abierto['dinero_inicial'],
                    'fecha_inicio' => $abierto['fecha_inicio'], 'horas_extra' => (bool)$abierto['horas_extra'], 'reanudado' => true
                ]];
            } else {
                $pdo->prepare("INSERT INTO turnos_caja(id_caja,id_cajero,id_sucursal,fecha_inicio,dinero_inicial,estado) VALUES(?,?,?,NOW(),?,'ABIERTO')")
                    ->execute([$id_caja, $id_cajero, $id_sucursal, $dineroInicial]);
                $idTurno = (int)$pdo->lastInsertId();
                $fInicio = $pdo->query("SELECT fecha_inicio FROM turnos_caja WHERE id_turno=" . $idTurno)->fetchColumn();
                $out = ['ok' => true, 'msg' => 'Acceso concedido.', 'data' => [
                    'nombre_cajero' => $cajero['nombre'], 'nombre_caja' => $nombreCaja, 'id_caja' => $id_caja, 'id_cajero' => $id_cajero,
                    'id_turno' => $idTurno, 'dinero_inicial' => $dineroInicial, 'fecha_inicio' => $fInicio, 'horas_extra' => false, 'reanudado' => false
                ]];
            }

        } elseif ($action === 'verificar_password_gerente') {
            $pass = (string)($_POST['password'] ?? '');
            if ($pass === '') throw new Exception('[ERROR DE USUARIO] Ingresa tu contraseña.');
            $st = $pdo->prepare("SELECT password_hash FROM usuarios WHERE id_usuario=?");
            $st->execute([$id_usuario_actual]);
            $hash = $st->fetchColumn();
            if (!$hash || !password_verify($pass, $hash)) throw new Exception('[ERROR DE USUARIO] Contraseña incorrecta. No se autorizó la salida.');
            $out = ['ok' => true, 'msg' => 'Salida autorizada.'];

        } elseif ($action === 'get_turno_estado') {
            $id_turno = (int)($_GET['id_turno'] ?? 0);
            $st = $pdo->prepare("SELECT * FROM turnos_caja WHERE id_turno=? AND id_sucursal=?");
            $st->execute([$id_turno, $id_sucursal]);
            $turno = $st->fetch(PDO::FETCH_ASSOC);
            if (!$turno) throw new Exception('[ERROR DE USUARIO] Ese turno no pertenece a tu sucursal.');

            $stV = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM ventas WHERE id_turno=?");
            $stV->execute([$id_turno]);
            $totalVendido = (float)$stV->fetchColumn();

            $stVE = $pdo->prepare("SELECT COALESCE(SUM(v.total),0) FROM ventas v JOIN metodos_pago mp ON mp.id_metodo_pago=v.id_metodo_pago WHERE v.id_turno=? AND mp.nombre LIKE '%efectivo%'");
            $stVE->execute([$id_turno]);
            $totalEfectivo = (float)$stVE->fetchColumn();

            $stR = $pdo->prepare("SELECT COALESCE(SUM(monto),0) FROM retiros_caja WHERE id_turno=?");
            $stR->execute([$id_turno]);
            $totalRetiros = (float)$stR->fetchColumn();

            $dineroEsperado = (float)$turno['dinero_inicial'] + $totalEfectivo - $totalRetiros;

            $out = ['ok' => true, 'data' => [
                'id_turno' => (int)$turno['id_turno'], 'dinero_inicial' => (float)$turno['dinero_inicial'],
                'total_vendido' => $totalVendido, 'total_efectivo' => $totalEfectivo, 'total_retiros' => $totalRetiros,
                'dinero_esperado' => $dineroEsperado, 'horas_extra' => (bool)$turno['horas_extra'], 'estado' => $turno['estado'],
                'fecha_inicio' => $turno['fecha_inicio']
            ]];

        } elseif ($action === 'registrar_retiro') {
            $id_turno = (int)($_POST['id_turno'] ?? 0);
            $monto = (float)str_replace(',', '.', $_POST['monto'] ?? '0');
            $motivo = trim($_POST['motivo'] ?? '');
            if ($monto <= 0) throw new Exception('[ERROR DE USUARIO] El monto del retiro debe ser mayor a cero.');
            $chk = $pdo->prepare("SELECT COUNT(*) FROM turnos_caja WHERE id_turno=? AND id_sucursal=? AND estado='ABIERTO'");
            $chk->execute([$id_turno, $id_sucursal]);
            if (!$chk->fetchColumn()) throw new Exception('[ERROR DE USUARIO] Ese turno no está abierto en tu sucursal.');
            $pdo->prepare("INSERT INTO retiros_caja(id_turno,monto,motivo,fecha) VALUES(?,?,?,NOW())")->execute([$id_turno, $monto, $motivo ?: null]);
            $out = ['ok' => true, 'msg' => 'Retiro registrado. Las ventas no se ven afectadas.'];

        } elseif ($action === 'autorizar_horas_extra') {
            $id_turno = (int)($_POST['id_turno'] ?? 0);
            $pass = (string)($_POST['password'] ?? '');
            if ($pass === '') throw new Exception('[ERROR DE USUARIO] Ingresa la contraseña del gerente.');
            $st = $pdo->prepare("SELECT password_hash FROM usuarios WHERE id_usuario=?");
            $st->execute([$id_usuario_actual]);
            $hash = $st->fetchColumn();
            if (!$hash || !password_verify($pass, $hash)) throw new Exception('[ERROR DE USUARIO] Contraseña incorrecta. No se autorizaron las horas extra.');
            $chk = $pdo->prepare("SELECT COUNT(*) FROM turnos_caja WHERE id_turno=? AND id_sucursal=? AND estado='ABIERTO'");
            $chk->execute([$id_turno, $id_sucursal]);
            if (!$chk->fetchColumn()) throw new Exception('[ERROR DE USUARIO] Ese turno no está abierto en tu sucursal.');
            $pdo->prepare("UPDATE turnos_caja SET horas_extra=1 WHERE id_turno=?")->execute([$id_turno]);
            $out = ['ok' => true, 'msg' => 'Horas extra autorizadas.'];

        } elseif ($action === 'cerrar_turno') {
            $id_turno = (int)($_POST['id_turno'] ?? 0);
            $dineroContado = (float)str_replace(',', '.', $_POST['dinero_contado'] ?? '');
            $pass = (string)($_POST['password'] ?? '');
            if ($_POST['dinero_contado'] === '' || $_POST['dinero_contado'] === null) throw new Exception('[ERROR DE USUARIO] Ingresa el dinero contado.');
            if ($pass === '') throw new Exception('[ERROR DE USUARIO] Ingresa la contraseña del gerente.');

            $st = $pdo->prepare("SELECT password_hash FROM usuarios WHERE id_usuario=?");
            $st->execute([$id_usuario_actual]);
            $hash = $st->fetchColumn();
            if (!$hash || !password_verify($pass, $hash)) throw new Exception('[ERROR DE USUARIO] Contraseña incorrecta. No se autorizó el cierre.');

            $stT = $pdo->prepare("SELECT * FROM turnos_caja WHERE id_turno=? AND id_sucursal=? AND estado='ABIERTO'");
            $stT->execute([$id_turno, $id_sucursal]);
            $turno = $stT->fetch(PDO::FETCH_ASSOC);
            if (!$turno) throw new Exception('[ERROR DE USUARIO] Ese turno no está abierto en tu sucursal.');

            $stV = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM ventas WHERE id_turno=?");
            $stV->execute([$id_turno]);
            $totalVendido = (float)$stV->fetchColumn();

            $stVE = $pdo->prepare("SELECT COALESCE(SUM(v.total),0) FROM ventas v JOIN metodos_pago mp ON mp.id_metodo_pago=v.id_metodo_pago WHERE v.id_turno=? AND mp.nombre LIKE '%efectivo%'");
            $stVE->execute([$id_turno]);
            $totalEfectivo = (float)$stVE->fetchColumn();

            $stR = $pdo->prepare("SELECT COALESCE(SUM(monto),0) FROM retiros_caja WHERE id_turno=?");
            $stR->execute([$id_turno]);
            $totalRetiros = (float)$stR->fetchColumn();

            $dineroEsperado = (float)$turno['dinero_inicial'] + $totalEfectivo - $totalRetiros;
            $diferencia = round($dineroContado - $dineroEsperado, 2);
            $estadoCorte = abs($diferencia) < 0.01 ? 'EXACTO' : ($diferencia > 0 ? 'SOBRANTE' : 'FALTANTE');

            $pdo->prepare("UPDATE turnos_caja SET fecha_fin=NOW(), total_vendido=?, total_retiros=?, dinero_esperado=?, dinero_contado=?, diferencia=?, estado_corte=?, estado='CERRADO' WHERE id_turno=?")
                ->execute([$totalVendido, $totalRetiros, $dineroEsperado, $dineroContado, $diferencia, $estadoCorte, $id_turno]);

            $out = ['ok' => true, 'msg' => 'Corte de caja guardado.', 'data' => [
                'dinero_inicial' => (float)$turno['dinero_inicial'], 'total_vendido' => $totalVendido, 'total_retiros' => $totalRetiros,
                'dinero_esperado' => $dineroEsperado, 'dinero_contado' => $dineroContado, 'diferencia' => $diferencia, 'estado_corte' => $estadoCorte
            ]];

        } elseif ($action === 'get_cortes_caja') {
            $st = $pdo->prepare("SELECT t.id_turno, t.fecha_inicio, t.fecha_fin, t.dinero_inicial, t.total_vendido, t.total_retiros,
                                        t.dinero_esperado, t.dinero_contado, t.diferencia, t.estado_corte, t.horas_extra, t.estado,
                                        c.nombre AS caja_nombre, cj.nombre AS cajero_nombre, s.nombre AS sucursal_nombre
                                 FROM turnos_caja t
                                 JOIN cajas c ON c.id_caja = t.id_caja
                                 JOIN cajeros cj ON cj.id_cajero = t.id_cajero
                                 JOIN sucursales s ON s.id_sucursal = t.id_sucursal
                                 WHERE t.id_sucursal=?
                                 ORDER BY t.fecha_inicio DESC LIMIT 200");
            $st->execute([$id_sucursal]);
            $out = ['ok' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)];

        } elseif ($action === 'get_ventas') {
            $desde = trim($_GET['desde'] ?? ''); $hasta = trim($_GET['hasta'] ?? '');
            $sql = "SELECT v.id_venta, v.fecha_hora, v.total, v.estado, mp.nombre AS metodo_pago,
                           CONCAT(u.nombre,' ',COALESCE(u.apellido,'')) AS empleado
                    FROM ventas v
                    JOIN metodos_pago mp ON mp.id_metodo_pago=v.id_metodo_pago
                    JOIN usuarios u ON u.id_usuario=v.id_usuario
                    WHERE v.id_sucursal=?";
            $params = [$id_sucursal];
            if ($desde !== '') { $sql .= " AND DATE(v.fecha_hora)>=?"; $params[] = $desde; }
            if ($hasta !== '') { $sql .= " AND DATE(v.fecha_hora)<=?"; $params[] = $hasta; }
            $sql .= " ORDER BY v.fecha_hora DESC LIMIT 200";
            $st = $pdo->prepare($sql); $st->execute($params);
            $out = ['ok' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)];

        } elseif ($action === 'get_venta_detalle') {
            $id = (int)($_GET['id'] ?? 0);
            $chk = $pdo->prepare("SELECT COUNT(*) FROM ventas WHERE id_venta=? AND id_sucursal=?");
            $chk->execute([$id, $id_sucursal]);
            if (!$chk->fetchColumn()) throw new Exception('[ERROR DE USUARIO] Esa venta no pertenece a tu sucursal.');
            $st = $pdo->prepare("SELECT dv.cantidad, dv.precio_unitario, dv.subtotal, COALESCE(p.nombre,'Producto no disponible') AS producto
                                  FROM detalle_ventas dv LEFT JOIN productos p ON p.id_producto=dv.id_producto WHERE dv.id_venta=?");
            $st->execute([$id]);
            $out = ['ok' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)];

        } elseif ($action === 'buscar_producto_pos') {
            $codigo = trim($_GET['codigo'] ?? '');
            if ($codigo === '') throw new Exception('[ERROR DE USUARIO] Ingresa un código.');
            $st = $pdo->prepare("SELECT p.id_producto, p.codigo, p.nombre, p.precio, i.existencias
                                  FROM productos p JOIN inventarios i ON i.id_producto=p.id_producto AND i.id_sucursal=?
                                  WHERE p.codigo=? AND p.estado='ACTIVO' LIMIT 1");
            $st->execute([$id_sucursal, $codigo]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception('[ERROR DE USUARIO] Producto no encontrado en tu sucursal.');
            if ($row['existencias'] <= 0) throw new Exception('[ERROR DE USUARIO] "' . $row['nombre'] . '" está agotado en tu sucursal.');
            $out = ['ok' => true, 'data' => $row];

        } elseif ($action === 'buscar_productos_pos') {
            $q = trim($_GET['q'] ?? '');
            if ($q === '') { $out = ['ok' => true, 'data' => []]; }
            else {
                $like = "%$q%";
                $st = $pdo->prepare("SELECT p.id_producto, p.codigo, p.nombre, p.precio, i.existencias
                                      FROM productos p JOIN inventarios i ON i.id_producto=p.id_producto AND i.id_sucursal=?
                                      WHERE p.estado='ACTIVO' AND (p.nombre LIKE ? OR p.codigo LIKE ?)
                                      ORDER BY (p.codigo = ?) DESC, p.nombre ASC LIMIT 8");
                $st->execute([$id_sucursal, $like, $like, $q]);
                $out = ['ok' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)];
            }

        } elseif ($action === 'procesar_venta') {
            $carrito = json_decode($_POST['carrito'] ?? '[]', true);
            $idMetodo = (int)($_POST['id_metodo_pago'] ?? 0);
            $idTurno = (int)($_POST['id_turno'] ?? 0);
            if (!is_array($carrito) || count($carrito) === 0) throw new Exception('[ERROR DE USUARIO] El carrito está vacío.');
            if (!$idMetodo) throw new Exception('[ERROR DE USUARIO] Selecciona un método de pago.');
            if ($idTurno) {
                $chkT = $pdo->prepare("SELECT COUNT(*) FROM turnos_caja WHERE id_turno=? AND id_sucursal=? AND estado='ABIERTO'");
                $chkT->execute([$idTurno, $id_sucursal]);
                if (!$chkT->fetchColumn()) throw new Exception('[ERROR DE USUARIO] Tu turno de caja no está abierto. Vuelve a iniciar sesión de caja.');
            }

            $pdo->beginTransaction();
            try {
                $total = 0; $lineas = [];
                $stStock = $pdo->prepare("SELECT i.existencias, p.precio, p.nombre FROM inventarios i JOIN productos p ON p.id_producto=i.id_producto WHERE i.id_producto=? AND i.id_sucursal=?");
                foreach ($carrito as $item) {
                    $idP = (int)($item['id_producto'] ?? 0); $cant = (int)($item['cantidad'] ?? 0);
                    if ($cant <= 0 || !$idP) continue;
                    $stStock->execute([$idP, $id_sucursal]);
                    $row = $stStock->fetch();
                    if (!$row) throw new Exception('[ERROR DE USUARIO] Un producto del carrito ya no está disponible.');
                    if ((int)$row['existencias'] < $cant) throw new Exception('[ERROR DE USUARIO] Stock insuficiente para "' . $row['nombre'] . '". Disponible: ' . $row['existencias']);
                    $subtotal = (float)$row['precio'] * $cant;
                    $total += $subtotal;
                    $lineas[] = ['id_producto' => $idP, 'cantidad' => $cant, 'precio_unitario' => $row['precio'], 'subtotal' => $subtotal];
                }
                if (empty($lineas)) throw new Exception('[ERROR DE USUARIO] No hay productos válidos en el carrito.');

                $pdo->prepare("INSERT INTO ventas(fecha_hora,total,estado,id_sucursal,id_metodo_pago,id_usuario,id_turno) VALUES(NOW(),?,'COMPLETADA',?,?,?,?)")
                    ->execute([$total, $id_sucursal, $idMetodo, $id_usuario_actual, $idTurno ?: null]);
                $idVenta = (int)$pdo->lastInsertId();

                $stDet = $pdo->prepare("INSERT INTO detalle_ventas(id_venta,id_producto,cantidad,precio_unitario,subtotal) VALUES(?,?,?,?,?)");
                $stUpd = $pdo->prepare("UPDATE inventarios SET existencias=existencias-? WHERE id_producto=? AND id_sucursal=?");
                foreach ($lineas as $l) {
                    $stDet->execute([$idVenta, $l['id_producto'], $l['cantidad'], $l['precio_unitario'], $l['subtotal']]);
                    $stUpd->execute([$l['cantidad'], $l['id_producto'], $id_sucursal]);
                }
                $pdo->commit();

                $dineroEsperado = null;
                if ($idTurno) {
                    $stT = $pdo->prepare("SELECT dinero_inicial FROM turnos_caja WHERE id_turno=?");
                    $stT->execute([$idTurno]);
                    $dInicial = (float)$stT->fetchColumn();
                    $stVE = $pdo->prepare("SELECT COALESCE(SUM(v.total),0) FROM ventas v JOIN metodos_pago mp ON mp.id_metodo_pago=v.id_metodo_pago WHERE v.id_turno=? AND mp.nombre LIKE '%efectivo%'");
                    $stVE->execute([$idTurno]);
                    $totalEfectivo = (float)$stVE->fetchColumn();
                    $stR = $pdo->prepare("SELECT COALESCE(SUM(monto),0) FROM retiros_caja WHERE id_turno=?");
                    $stR->execute([$idTurno]);
                    $totalRetiros = (float)$stR->fetchColumn();
                    $dineroEsperado = $dInicial + $totalEfectivo - $totalRetiros;
                }

                $out = ['ok' => true, 'msg' => 'Venta registrada correctamente.', 'data' => ['id_venta' => $idVenta, 'total' => $total, 'dinero_esperado' => $dineroEsperado]];
            } catch (Exception $e) { $pdo->rollBack(); throw $e; }
        }
    } catch (Throwable $e) {
        $out = ['ok' => false, 'msg' => $e->getMessage()];
    }
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

// --------------------------------------------------------------
// Render de la página
// --------------------------------------------------------------
if ($id_sucursal <= 0) {
    ob_end_clean();
    echo '<!doctype html><meta charset="utf-8"><body style="font-family:sans-serif;padding:60px;text-align:center;color:#6c4630;">
    <h2>Tu usuario no tiene una sucursal asignada</h2><p>Contacta al administrador para que te asigne una sucursal.</p></body>';
    exit;
}
$stSuc = $pdo->prepare("SELECT nombre FROM sucursales WHERE id_sucursal=?");
$stSuc->execute([$id_sucursal]);
$nombreSucursal = $stSuc->fetchColumn() ?: 'Sucursal';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KION · Panel de Gerente</title>

    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <style>
        :root {
            --bg-app: oklch(96.5% 0.014 72); --bg-surface: oklch(100% 0 0); --bg-panel: oklch(99% 0.006 75);
            --ink: oklch(24% 0.028 50); --ink-soft: oklch(42% 0.030 48); --border-soft: oklch(89% 0.016 65);
            --coffee-main: oklch(32% 0.040 50); --coffee-light: oklch(91% 0.018 60);
            --coffee-gradient: linear-gradient(155deg, oklch(40% 0.045 48), oklch(26% 0.035 52));
            --success: oklch(34% 0.06 152); --success-bg: oklch(94% 0.035 152);
            --danger: oklch(50% 0.14 25); --danger-bg: oklch(95% 0.035 20);
            --warning: oklch(42% 0.09 70); --warning-bg: oklch(95% 0.045 90);
            --font-display: 'Fraunces', serif; --font-body: 'Plus Jakarta Sans', sans-serif;
            --shadow-sm: 0 4px 12px rgba(0,0,0,0.05); --shadow-md: 0 8px 24px rgba(0,0,0,0.08);
            --radius: 14px; --sidebar-w: 260px; --sidebar-w-collapsed: 84px;
            --ease-expo: cubic-bezier(0.16, 1, 0.3, 1);
        }
        body.dark-mode {
            --bg-app: oklch(20% 0.01 250); --bg-surface: oklch(25% 0.01 250); --bg-panel: oklch(23% 0.01 250);
            --ink: oklch(95% 0.01 250); --ink-soft: oklch(75% 0.01 250); --border-soft: oklch(35% 0.01 250);
            --coffee-main: oklch(80% 0.03 60); --coffee-light: oklch(30% 0.02 250);
            --coffee-gradient: linear-gradient(155deg, oklch(40% 0.045 48), oklch(20% 0.035 52));
            --success-bg: oklch(25% 0.04 152); --danger-bg: oklch(30% 0.06 20); --warning-bg: oklch(30% 0.05 85);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: var(--font-body); background: var(--bg-app); color: var(--ink); transition: background 0.3s, color 0.3s; }
        button { font: inherit; cursor: pointer; border: none; background: none; color: inherit; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .view-section { display: none; animation: fadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        .view-section.active { display: block; }

        .app-layout { display: grid; grid-template-columns: var(--sidebar-w) 1fr; min-height: 100vh; transition: grid-template-columns .35s var(--ease-expo, cubic-bezier(0.16,1,0.3,1)); }
        .app-layout.is-collapsed { grid-template-columns: var(--sidebar-w-collapsed, 84px) 1fr; }
        .sidebar { position: sticky; top: 0; height: 100vh; background: var(--bg-surface); border-right: 1px solid var(--border-soft); padding: 22px 16px; display: flex; flex-direction: column; gap: 26px; z-index: 40; transition: padding .35s ease, background .3s ease; }
        .brand { display: flex; align-items: center; gap: 12px; font-family: var(--font-display); font-size: 19px; font-weight: 600; padding: 0 8px; position: relative; }
        .brand i { font-size: 26px; color: var(--coffee-main); flex: none; }
        .brand-text { overflow: hidden; white-space: nowrap; transition: opacity .2s ease; }
        .brand-sub { font-size: 10.5px; letter-spacing: .6px; color: var(--ink-soft); font-weight: 700; text-transform: uppercase; margin-top: 1px; opacity: .7; }
        .app-layout.is-collapsed .brand-text, .app-layout.is-collapsed .nav-label, .app-layout.is-collapsed .nav-section-title { opacity: 0; width: 0; pointer-events: none; }
        .sidebar-collapse-btn { position: absolute; top: 22px; right: -13px; width: 26px; height: 26px; border-radius: 50%; background: var(--bg-surface); border: 1px solid var(--border-soft); color: var(--coffee-main); display: grid; place-items: center; box-shadow: var(--shadow-sm); z-index: 41; transition: transform .3s var(--ease-expo, cubic-bezier(0.16,1,0.3,1)); }
        .app-layout.is-collapsed .sidebar-collapse-btn { transform: rotate(180deg); }
        .sidebar-collapse-btn i { font-size: 13px; }
        .nav-section-title { font-size: 10.5px; text-transform: uppercase; letter-spacing: .8px; color: var(--ink-soft); opacity: .6; font-weight: 700; padding: 4px 12px 4px; white-space: nowrap; }
        .nav-menu { display: flex; flex-direction: column; gap: 6px; overflow-y: auto; }
        .nav-btn { display: flex; align-items: center; gap: 12px; padding: 11px 14px; border-radius: var(--radius); font-weight: 600; font-size: 14px; color: var(--ink-soft); transition: all 0.2s; white-space: nowrap; }
        .nav-btn i { font-size: 19px; flex: none; }
        .nav-btn:hover { background: var(--bg-app); color: var(--ink); transform: translateX(2px); }
        .nav-btn.active { background: var(--coffee-gradient); color: #fff; box-shadow: var(--shadow-sm); }
        .sidebar-scrim { display: none; position: fixed; inset: 0; background: rgba(20,16,12,.45); z-index: 39; opacity: 0; transition: opacity .3s ease; }
        .hamburger-btn { display: none; width: 38px; height: 38px; border-radius: 10px; border: 1px solid var(--border-soft); background: var(--bg-surface); align-items: center; justify-content: center; font-size: 18px; }

        .topbar { height: 70px; display: flex; justify-content: space-between; align-items: center; padding: 0 32px; border-bottom: 1px solid var(--border-soft); background: var(--bg-panel); position: sticky; top: 0; z-index: 30; }
        .topbar-actions { display: flex; gap: 16px; align-items: center; }
        .icon-btn { width: 40px; height: 40px; border-radius: 50%; display: grid; place-items: center; background: var(--bg-app); border: 1px solid var(--border-soft); font-size: 20px; transition: 0.2s; }
        .icon-btn:hover { background: var(--coffee-light); color: var(--coffee-main); transform: translateY(-1px); }
        .icon-btn:active { transform: scale(.92); }
        .user-profile { display: flex; align-items: center; gap: 10px; font-weight: 600; padding-left: 16px; border-left: 1px solid var(--border-soft); }
        .role-chip { font-size: 10px; font-weight: 800; letter-spacing: .5px; padding: 3px 8px; border-radius: 20px; background: var(--coffee-light); color: var(--coffee-main); text-transform: uppercase; }

        .content { padding: 32px; max-width: 1400px; margin: 0 auto; }
        .header-row { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
        .title { font-family: var(--font-display); font-size: 28px; font-weight: 600; }
        .subtitle { color: var(--ink-soft); font-size: 14px; margin-top: 4px; }

        .btn-primary { background: var(--coffee-gradient); color: #fff; padding: 10px 20px; border-radius: 8px; font-weight: 600; box-shadow: var(--shadow-sm); transition: transform 0.2s; display:inline-flex; align-items:center; gap:8px; }
        .btn-primary:active { transform: scale(0.96); }
        .btn-danger { background: var(--danger); color: #fff; padding: 10px 20px; border-radius: 8px; font-weight: 600; }

        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 24px; }
        .kpi-card { background: var(--bg-surface); padding: 24px; border-radius: var(--radius); border: 1px solid var(--border-soft); box-shadow: var(--shadow-sm); display: flex; flex-direction: column; gap: 12px; }
        .kpi-card i { font-size: 28px; color: var(--coffee-main); padding: 12px; background: var(--coffee-light); border-radius: 10px; width: fit-content; }
        .kpi-val { font-family: var(--font-display); font-size: 32px; font-weight: 600; }

        .panel { background: var(--bg-surface); border-radius: var(--radius); border: 1px solid var(--border-soft); padding: 24px; overflow-x: auto; margin-bottom:24px; }
        .grid-2col { display:grid; grid-template-columns: 1.4fr 1fr; gap:24px; }
        .chart-box { position:relative; height:260px; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { font-size: 12px; text-transform: uppercase; color: var(--ink-soft); padding-bottom: 16px; border-bottom: 1px solid var(--border-soft); }
        td { padding: 16px 0; border-bottom: 1px solid var(--border-soft); font-weight: 500; }
        tr:last-child td { border: none; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        .badge.b-red { background: var(--danger-bg); color: var(--danger); }
        .badge.b-green { background: var(--success-bg); color: var(--success); }
        .badge.b-yellow { background: var(--warning-bg); color: var(--warning); }
        .chip-btn { border:1px solid var(--border-soft); background:var(--bg-app); color:var(--coffee-main); font-size:12px; font-weight:700; padding:6px 12px; border-radius:8px; }
        .chip-btn:hover { background:var(--coffee-main); color:#fff; }
        .icon-x { width:30px; height:30px; border-radius:8px; border:1px solid var(--border-soft); display:inline-grid; place-items:center; color:var(--ink-soft); }
        .icon-x:hover { background:var(--danger-bg); color:var(--danger); }
        .form-inline { display:flex; gap:12px; flex-wrap:wrap; align-items:center; }
        .search-input { padding:10px 16px; border:1px solid var(--border-soft); border-radius:8px; background:var(--bg-app); color:var(--ink); min-width:220px; }
        .info-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:20px; }
        .info-item span { display:block; color:var(--ink-soft); font-size:12px; text-transform:uppercase; margin-bottom:6px; }
        .info-item b { font-size:18px; }

        .pos-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
        .pos-scanner { background: var(--bg-surface); padding: 24px; border-radius: var(--radius); border: 1px solid var(--border-soft); margin-bottom: 20px; display: flex; gap: 16px; }
        .pos-scanner input { flex: 1; padding: 14px 20px; font-size: 16px; border: 2px solid var(--border-soft); border-radius: 8px; background: var(--bg-app); color: var(--ink); outline: none; transition: border 0.3s; }
        .pos-scanner input:focus { border-color: var(--coffee-main); }
        .pos-cart { background: var(--bg-surface); border-radius: var(--radius); border: 1px solid var(--border-soft); min-height: 400px; display: flex; flex-direction: column; }
        .pos-summary { background: var(--coffee-main); color: #fff; border-radius: var(--radius); padding: 32px 24px; display: flex; flex-direction: column; gap: 20px; }
        .summary-row { display: flex; justify-content: space-between; font-size: 16px; font-weight: 500; opacity: 0.9; }
        .summary-total { font-family: var(--font-display); font-size: 42px; font-weight: 700; margin: 10px 0 20px; text-align: right; }
        .pay-btn { background: #fff; color: var(--coffee-main); width: 100%; padding: 16px; border-radius: 8px; font-size: 18px; font-weight: 700; box-shadow: 0 4px 15px rgba(0,0,0,0.2); transition: transform 0.2s; }
        .pay-btn:active { transform: scale(0.97); }
        .pay-select { width:100%; padding:12px; border-radius:8px; border:none; font-weight:600; color:var(--coffee-main); }
        .pos-search-dropdown { position:absolute; top:calc(100% + 6px); left:0; right:0; background:var(--bg-surface); border:1px solid var(--border-soft); border-radius:10px; box-shadow:var(--shadow-md); max-height:280px; overflow-y:auto; z-index:50; display:none; }
        .pos-search-dropdown.show { display:block; }
        .pos-search-item { display:flex; justify-content:space-between; align-items:center; gap:10px; padding:10px 14px; cursor:pointer; border-bottom:1px solid var(--border-soft); }
        .pos-search-item:last-child { border-bottom:none; }
        .pos-search-item:hover { background:var(--bg-app); }
        .pos-search-item.disabled { opacity:.5; cursor:not-allowed; }
        .pos-search-item .psi-name { font-weight:700; font-size:13.5px; }
        .pos-search-item .psi-meta { font-size:11.5px; color:var(--ink-soft); }
        .app-layout.pos-mode .sidebar, .app-layout.pos-mode .sidebar-collapse-btn, .app-layout.pos-mode .hamburger-btn { display:none !important; }
        .app-layout.pos-mode { grid-template-columns: 1fr !important; }

        .dark-mode .swal2-popup { background: var(--bg-surface); color: var(--ink); }
        .dark-mode .swal2-input, .dark-mode .swal2-select, .dark-mode .swal2-textarea { background: var(--bg-app); color: var(--ink); border-color: var(--border-soft); }

        .cajero-row { display:flex; justify-content:space-between; align-items:center; padding:10px 4px; border-bottom:1px solid var(--border-soft); font-size:13.5px; }
        .cajero-row:last-child { border-bottom:none; }

        @media (max-width: 980px){
            .grid-2col{ grid-template-columns:1fr; } .pos-layout{ grid-template-columns:1fr; }
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
        <button class="sidebar-collapse-btn" id="btnCollapse" title="Colapsar menú"><i class="ph ph-caret-left"></i></button>
        <div class="brand">
            <i class="ph-fill ph-paw-print"></i>
            <div class="brand-text"><div>KION</div><div class="brand-sub" data-i18n="brand_sub">Gerencia de Sucursal</div></div>
        </div>
        <nav class="nav-menu">
            <div class="nav-section-title" data-i18n="nav_section_panel">Panel</div>
            <button class="nav-btn active" data-target="view-dash"><i class="ph ph-squares-four"></i><span class="nav-label" data-i18n="nav_dash">Panel Principal</span></button>
            <div class="nav-section-title" data-i18n="nav_section_gestion">Gestión</div>
            <button class="nav-btn" data-target="view-sucursal"><i class="ph ph-storefront"></i><span class="nav-label" data-i18n="nav_sucursal">Mi Sucursal</span></button>
            <button class="nav-btn" data-target="view-inventario"><i class="ph ph-package"></i><span class="nav-label" data-i18n="nav_inventario">Inventario</span></button>
            <button class="nav-btn" data-target="view-productos"><i class="ph ph-tag"></i><span class="nav-label" data-i18n="nav_productos">Productos</span></button>
            <button class="nav-btn" data-target="view-cajas"><i class="ph ph-desktop"></i><span class="nav-label" data-i18n="nav_cajas">Cajas</span></button>
            <button class="nav-btn" data-target="view-ventas"><i class="ph ph-chart-line-up"></i><span class="nav-label" data-i18n="nav_ventas">Ventas</span></button>
            <button class="nav-btn" data-target="view-cortes"><i class="ph ph-calculator"></i><span class="nav-label" data-i18n="nav_cortes">Cortes de Caja</span></button>
            <div class="nav-section-title" data-i18n="nav_section_caja">Caja</div>
            <button class="nav-btn" id="btnIrPOS"><i class="ph ph-monitor"></i><span class="nav-label" data-i18n="nav_pos">Punto de Venta</span></button>
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
                <b id="branchName">📍 <span data-i18n="branch_prefix">Sucursal</span>: <?= htmlspecialchars($nombreSucursal) ?></b>
            </div>
            <div class="topbar-actions">
                <button class="icon-btn" id="btnLang" title="Switch language / Cambiar idioma"><i class="ph ph-translate"></i></button>
                <button class="icon-btn" id="btnTheme" title="Modo Oscuro"><i class="ph ph-moon"></i></button>
                <div class="user-profile">
                    <i class="ph-fill ph-user-circle" style="font-size:24px; color:var(--coffee-main)"></i>
                    <span><?= htmlspecialchars($nombreGerente) ?></span>
                    <span class="role-chip" data-i18n="role_chip">Gerente</span>
                </div>
            </div>
        </header>

        <div class="content">

            <!-- PANEL PRINCIPAL -->
            <section id="view-dash" class="view-section active">
                <div class="header-row"><div><h1 class="title" data-i18n="dash_title">Resumen de Operaciones</h1><p class="subtitle" data-i18n="dash_sub">Métricas en tiempo real de tu sucursal.</p></div></div>
                <div class="kpi-grid">
                    <div class="kpi-card"><i class="ph-fill ph-money"></i><span style="color:var(--ink-soft); font-weight:600;" data-i18n="kpi_ingresos">Ingresos Hoy</span><div class="kpi-val" id="kpiIngresos">$0.00</div></div>
                    <div class="kpi-card"><i class="ph-fill ph-warning-circle" style="color:var(--danger)"></i><span style="color:var(--ink-soft); font-weight:600;" data-i18n="kpi_bajo">Stock Bajo</span><div class="kpi-val" id="kpiBajo" style="color:var(--danger)">0</div></div>
                    <div class="kpi-card"><i class="ph-fill ph-desktop"></i><span style="color:var(--ink-soft); font-weight:600;" data-i18n="kpi_cajas">Cajas Activas</span><div class="kpi-val" id="kpiCajas">0</div></div>
                    <div class="kpi-card"><i class="ph-fill ph-package"></i><span style="color:var(--ink-soft); font-weight:600;" data-i18n="kpi_productos">Productos Asignados</span><div class="kpi-val" id="kpiProductos">0</div></div>
                </div>
                <div class="grid-2col">
                    <div class="panel"><h3 style="margin-bottom:16px;" data-i18n="chart_ventas">Ventas (7 días)</h3><div class="chart-box"><canvas id="salesChart"></canvas></div></div>
                    <div class="panel"><h3 data-i18n="alertas_title">Alertas de Inventario Bajo</h3><table style="margin-top:16px;" id="tblAlertas"><tr><th data-i18n="th_producto">Producto</th><th data-i18n="th_existencia">Existencia</th><th data-i18n="th_estado">Estado</th></tr></table></div>
                </div>
            </section>

            <!-- MI SUCURSAL -->
            <section id="view-sucursal" class="view-section">
                <div class="header-row"><div><h1 class="title" data-i18n="misuc_title">Mi Sucursal</h1><p class="subtitle" data-i18n="misuc_sub">Información general de tu punto de venta.</p></div></div>
                <div class="panel"><div class="info-grid" id="infoSucursal" data-i18n="cargando">Cargando...</div></div>
            </section>

            <!-- INVENTARIO -->
            <section id="view-inventario" class="view-section">
                <div class="header-row">
                    <div><h1 class="title" data-i18n="inv_title">Inventario</h1><p class="subtitle" data-i18n="inv_sub">Consulta y actualiza existencias de tu sucursal.</p></div>
                    <button class="btn-primary" id="btnAjustarStock"><i class="ph ph-plus"></i> <span data-i18n="btn_actualizar_existencias">Actualizar Existencias</span></button>
                </div>
                <div class="panel">
                    <div class="form-inline" style="margin-bottom:16px;">
                        <input class="search-input" id="invSearch" data-i18n-placeholder="placeholder_buscar_producto" placeholder="Buscar producto o código...">
                        <label style="display:flex; align-items:center; gap:6px; font-weight:600; font-size:14px;"><input type="checkbox" id="invSoloBajo"> <span data-i18n="solo_bajo_stock">Solo bajo stock</span></label>
                    </div>
                    <table><thead><tr><th data-i18n="th_producto">Producto</th><th data-i18n="th_codigo">Código</th><th data-i18n="th_existencia">Existencia</th><th data-i18n="th_precio">Precio</th><th data-i18n="th_nivel">Nivel</th></tr></thead><tbody id="tblInventario"></tbody></table>
                </div>
            </section>

            <!-- PRODUCTOS -->
            <section id="view-productos" class="view-section">
                <div class="header-row">
                    <div><h1 class="title" data-i18n="prod_title">Productos</h1><p class="subtitle" data-i18n="prod_sub">Registra, consulta y modifica productos de tu sucursal.</p></div>
                    <button class="btn-primary" id="btnNuevoProducto"><i class="ph ph-plus"></i> <span data-i18n="btn_nuevo_producto">Nuevo Producto</span></button>
                </div>
                <div class="panel">
                    <div class="form-inline" style="margin-bottom:16px;"><input class="search-input" id="prodSearch" data-i18n-placeholder="placeholder_buscar_producto" placeholder="Buscar producto o código..."></div>
                    <table><thead><tr><th data-i18n="th_codigo">Código</th><th data-i18n="th_producto">Producto</th><th data-i18n="th_categoria">Categoría</th><th data-i18n="th_precio">Precio</th><th data-i18n="th_stock">Stock</th><th data-i18n="th_estado">Estado</th><th></th></tr></thead><tbody id="tblProductos"></tbody></table>
                </div>
            </section>

            <!-- CAJAS -->
            <section id="view-cajas" class="view-section">
                <div class="header-row">
                    <div><h1 class="title" data-i18n="caj_title">Cajas</h1><p class="subtitle" data-i18n="caj_sub">Registra cajas y cajeros de tu sucursal.</p></div>
                    <button class="btn-primary" id="btnNuevaCaja"><i class="ph ph-plus"></i> <span data-i18n="btn_nueva_caja">Nueva Caja</span></button>
                </div>
                <div class="panel"><table><thead><tr><th data-i18n="th_nombre">Nombre</th><th data-i18n="th_estado">Estado</th><th data-i18n="th_cajeros">Cajeros</th><th data-i18n="th_registrada">Registrada</th><th></th></tr></thead><tbody id="tblCajas"></tbody></table></div>
            </section>

            <!-- VENTAS -->
            <section id="view-ventas" class="view-section">
                <div class="header-row"><div><h1 class="title" data-i18n="ven_title">Historial de Ventas</h1><p class="subtitle" data-i18n="ven_sub">Supervisa las ventas realizadas en tu sucursal.</p></div></div>
                <div class="panel">
                    <div class="form-inline" style="margin-bottom:16px;">
                        <input class="search-input" type="date" id="ventasDesde"><input class="search-input" type="date" id="ventasHasta">
                    </div>
                    <table><thead><tr><th data-i18n="th_folio">Folio</th><th data-i18n="th_fecha">Fecha</th><th data-i18n="th_personal">Personal</th><th data-i18n="th_metodo">Método</th><th data-i18n="th_total">Total</th><th data-i18n="th_estado">Estado</th><th></th></tr></thead><tbody id="tblVentas"></tbody></table>
                </div>
            </section>

            <!-- CORTES DE CAJA -->
            <section id="view-cortes" class="view-section">
                <div class="header-row"><div><h1 class="title" data-i18n="cortes_title">Cortes de Caja</h1><p class="subtitle" data-i18n="cortes_sub">Consulta los cortes registrados por cada turno de caja.</p></div></div>
                <div class="panel" style="overflow-x:auto;">
                    <table style="min-width:1100px;"><thead><tr>
                        <th data-i18n="th_cajero">Cajero</th><th data-i18n="th_caja">Caja</th><th data-i18n="th_inicio">Inicio</th><th data-i18n="th_fin">Fin</th>
                        <th data-i18n="th_inicial">Inicial</th><th data-i18n="th_vendido">Vendido</th><th data-i18n="th_esperado">Esperado</th><th data-i18n="th_contado">Contado</th>
                        <th data-i18n="th_diferencia">Diferencia</th><th data-i18n="th_extra">H. Extra</th><th data-i18n="th_estado">Estado</th>
                    </tr></thead><tbody id="tblCortes"></tbody></table>
                </div>
            </section>

            <!-- PUNTO DE VENTA -->
            <section id="view-pos" class="view-section">
                <div class="header-row" style="margin-bottom: 12px;">
                    <div><h1 class="title"><span data-i18n="pos_caja_label">Caja</span> <span id="lblCajaActual">-</span></h1><p class="subtitle"><span data-i18n="pos_cajero_label">Cajero:</span> <b id="lblCajeroActual">—</b> · <span id="lblTurnoTiempo" style="color:var(--ink-soft);"></span></p></div>
                    <button class="btn-danger" id="btnCerrarCaja"><i class="ph ph-sign-out"></i> <span data-i18n="btn_cerrar_turno">Cerrar Turno</span></button>
                </div>
                <div class="pos-layout">
                    <div>
                        <div class="pos-scanner" style="position:relative;">
                            <i class="ph ph-barcode" style="font-size:32px; color:var(--coffee-main); align-self:center;"></i>
                            <div style="flex:1; position:relative;">
                                <input type="text" id="posBarcode" style="width:100%;" data-i18n-placeholder="pos_scan_placeholder" placeholder="Escanea, o escribe nombre/código..." autocomplete="off">
                                <div id="posSearchResults" class="pos-search-dropdown"></div>
                            </div>
                        </div>
                        <div class="pos-cart panel" style="padding:0;">
                            <table style="width:100%; margin:0;">
                                <thead><tr><th style="padding:16px 24px;" data-i18n="th_producto">Producto</th><th style="text-align:center;" data-i18n="th_cant">Cant.</th><th style="text-align:right;" data-i18n="th_precio">Precio</th><th style="text-align:right; padding-right:24px;" data-i18n="th_subtotal">Subtotal</th></tr></thead>
                                <tbody id="cartBody"><tr><td colspan="4" style="text-align:center; padding: 40px; color:var(--ink-soft);" data-i18n="carrito_vacio">El carrito está vacío. Escanea un producto.</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="pos-summary">
                        <h2 data-i18n="resumen_venta">Resumen de Venta</h2>
                        <select class="pay-select" id="posMetodo"><option value="" data-i18n="cargando_metodos">Cargando métodos de pago...</option></select>
                        <div class="summary-row" style="font-size:12.5px; opacity:.8;"><span data-i18n="fondo_inicial_label">Fondo inicial:</span><span id="posFondoInicial">$0.00</span></div>
                        <div class="summary-row" style="font-size:12.5px; opacity:.8;"><span data-i18n="efectivo_esperado_label">Efectivo esperado:</span><span id="posEfectivoEsperado">$0.00</span></div>
                        <button class="chip-btn" id="btnRetiroManual" style="align-self:flex-start; background:rgba(255,255,255,.15); border-color:rgba(255,255,255,.3); color:#fff;" type="button"><span data-i18n="btn_registrar_retiro">+ Registrar retiro</span></button>
                        <div style="flex:1;"></div>
                        <div class="summary-row"><span data-i18n="subtotal_label">Subtotal:</span><span id="posSubtotal">$0.00</span></div>
                        <div class="summary-row"><span data-i18n="iva_label">IVA (16%):</span><span id="posIva">$0.00</span></div>
                        <hr style="border:1px solid rgba(255,255,255,0.2)">
                        <div style="font-size:18px; font-weight:600; opacity:0.9;" data-i18n="total_pagar">Total a Pagar</div>
                        <div class="summary-total" id="posTotal">$0.00</div>
                        <button class="pay-btn" id="btnCobrar"><i class="ph-fill ph-credit-card"></i> <span data-i18n="btn_procesar_cobro">PROCESAR COBRO</span></button>
                    </div>
                </div>
            </section>

        </div>
    </main>
</div>

<script>
const endpoint = 'dashboard_gerente.php';
const $ = s => document.querySelector(s);
const escapeHtml = v => String(v ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
const money = v => '$' + Number(v || 0).toFixed(2);

const notify = (msg, icon='success') => Swal.fire({ toast:true, position:'top-end', icon, title: msg, showConfirmButton:false, timer:2500 });

async function request(actionStr, options = {}) {
    const r = await fetch(`${endpoint}?action=${actionStr}`, options);
    const text = await r.text();
    let data;
    try { data = JSON.parse(text); } catch(e) { throw new Error('[ERROR DE SISTEMA] Respuesta inválida del servidor.'); }
    if (!data.ok) throw new Error(data.msg || 'No fue posible completar la operación.');
    return data;
}

// -------- TEMA --------
const body = document.body, btnTheme = $('#btnTheme');
btnTheme.addEventListener('click', () => {
    body.classList.toggle('dark-mode');
    const icon = btnTheme.querySelector('i');
    icon.classList.toggle('ph-moon'); icon.classList.toggle('ph-sun');
    localStorage.setItem('kion-gerente-theme', body.classList.contains('dark-mode') ? 'dark' : 'light');
});
if (localStorage.getItem('kion-gerente-theme') === 'dark') { body.classList.add('dark-mode'); btnTheme.querySelector('i').classList.replace('ph-moon','ph-sun'); }

// -------- SIDEBAR: colapsar / móvil --------
const appLayout = $('#appLayout');
$('#btnCollapse').addEventListener('click', () => appLayout.classList.toggle('is-collapsed'));
$('#btnMobileMenu').addEventListener('click', () => appLayout.classList.add('mobile-open'));
$('#sidebarScrim').addEventListener('click', () => appLayout.classList.remove('mobile-open'));

// -------- IDIOMA (ES / EN) --------
const I18N = {
    es: {
        brand_sub: 'Gerencia de Sucursal', role_chip: 'Gerente', branch_prefix: 'Sucursal',
        nav_section_panel: 'Panel', nav_section_gestion: 'Gestión', nav_section_caja: 'Caja', nav_section_nav: 'Navegación',
        nav_dash: 'Panel Principal', nav_sucursal: 'Mi Sucursal', nav_inventario: 'Inventario', nav_productos: 'Productos',
        nav_cajas: 'Cajas', nav_ventas: 'Ventas', nav_pos: 'Punto de Venta', nav_home: 'Volver a Home', nav_logout: 'Cerrar sesión',
        topbar_dash: 'Panel Principal', topbar_sucursal: 'Mi Sucursal', topbar_inventario: 'Inventario', topbar_productos: 'Productos',
        topbar_cajas: 'Cajas', topbar_ventas: 'Ventas', topbar_pos: 'Punto de Venta',
        dash_title: 'Resumen de Operaciones', dash_sub: 'Métricas en tiempo real de tu sucursal.',
        kpi_ingresos: 'Ingresos Hoy', kpi_bajo: 'Stock Bajo', kpi_cajas: 'Cajas Activas', kpi_productos: 'Productos Asignados',
        chart_ventas: 'Ventas (7 días)', alertas_title: 'Alertas de Inventario Bajo', sin_alertas: 'Sin alertas.',
        misuc_title: 'Mi Sucursal', misuc_sub: 'Información general de tu punto de venta.', cargando: 'Cargando...', no_encontrada: 'No encontrada.',
        lbl_nombre: 'Nombre', lbl_direccion: 'Dirección', lbl_telefono: 'Teléfono', lbl_contacto: 'Contacto', lbl_estado: 'Estado',
        inv_title: 'Inventario', inv_sub: 'Consulta y actualiza existencias de tu sucursal.', btn_actualizar_existencias: 'Actualizar Existencias',
        placeholder_buscar_producto: 'Buscar producto o código...', solo_bajo_stock: 'Solo bajo stock', sin_resultados: 'Sin resultados.',
        prod_title: 'Productos', prod_sub: 'Registra, consulta y modifica productos de tu sucursal.', btn_nuevo_producto: 'Nuevo Producto',
        sin_productos: 'Sin productos registrados.',
        caj_title: 'Cajas', caj_sub: 'Registra cajas y cajeros de tu sucursal.', btn_nueva_caja: 'Nueva Caja', sin_cajas: 'Sin cajas registradas.',
        ven_title: 'Historial de Ventas', ven_sub: 'Supervisa las ventas realizadas en tu sucursal.', sin_ventas: 'Sin ventas en el rango.',
        pos_caja_label: 'Caja', pos_cajero_label: 'Cajero:', btn_cerrar_turno: 'Cerrar Turno',
        resumen_venta: 'Resumen de Venta', cargando_metodos: 'Cargando métodos de pago...',
        subtotal_label: 'Subtotal:', iva_label: 'IVA (16%):', total_pagar: 'Total a Pagar', btn_procesar_cobro: 'PROCESAR COBRO',
        carrito_vacio: 'El carrito está vacío. Escanea un producto.',
        th_producto: 'Producto', th_codigo: 'Código', th_existencia: 'Existencia', th_precio: 'Precio', th_nivel: 'Nivel',
        th_categoria: 'Categoría', th_stock: 'Stock', th_estado: 'Estado', th_nombre: 'Nombre', th_cajeros: 'Cajeros', th_registrada: 'Registrada',
        th_folio: 'Folio', th_fecha: 'Fecha', th_personal: 'Personal', th_metodo: 'Método', th_total: 'Total', th_cant: 'Cant.', th_subtotal: 'Subtotal',
        editar: 'Editar', eliminar: 'Eliminar', ver: 'Ver', ver_stock: 'Ver stock', guardar: 'Guardar', cancelar: 'Cancelar', registrar: 'Registrar',
        cajeros_btn: 'Cajeros', mas_cajero_btn: '+ Cajero',
        nav_cortes: 'Cortes de Caja', cortes_title: 'Cortes de Caja', cortes_sub: 'Consulta los cortes registrados por cada turno de caja.',
        th_caja: 'Caja', th_inicio: 'Inicio', th_fin: 'Fin', th_inicial: 'Inicial', th_vendido: 'Vendido', th_esperado: 'Esperado',
        th_contado: 'Contado', th_diferencia: 'Diferencia', th_extra: 'H. Extra', sin_cortes: 'Aún no hay cortes registrados.',
        si: 'Sí', no: 'No', abierto: 'Abierto',
        pos_scan_placeholder: 'Escanea, o escribe nombre/código...', fondo_inicial_label: 'Fondo inicial:', efectivo_esperado_label: 'Efectivo esperado:',
        btn_registrar_retiro: '+ Registrar retiro', agotado_label: 'Agotado'
    },
    en: {
        brand_sub: 'Branch Management', role_chip: 'Manager', branch_prefix: 'Branch',
        nav_section_panel: 'Overview', nav_section_gestion: 'Management', nav_section_caja: 'Register', nav_section_nav: 'Navigation',
        nav_dash: 'Dashboard', nav_sucursal: 'My Branch', nav_inventario: 'Inventory', nav_productos: 'Products',
        nav_cajas: 'Registers', nav_ventas: 'Sales', nav_pos: 'Point of Sale', nav_home: 'Back to Home', nav_logout: 'Log out',
        topbar_dash: 'Dashboard', topbar_sucursal: 'My Branch', topbar_inventario: 'Inventory', topbar_productos: 'Products',
        topbar_cajas: 'Registers', topbar_ventas: 'Sales', topbar_pos: 'Point of Sale',
        dash_title: 'Operations Overview', dash_sub: 'Real-time metrics for your branch.',
        kpi_ingresos: 'Revenue Today', kpi_bajo: 'Low Stock', kpi_cajas: 'Active Registers', kpi_productos: 'Assigned Products',
        chart_ventas: 'Sales (7 days)', alertas_title: 'Low Inventory Alerts', sin_alertas: 'No alerts.',
        misuc_title: 'My Branch', misuc_sub: 'General information about your store.', cargando: 'Loading...', no_encontrada: 'Not found.',
        lbl_nombre: 'Name', lbl_direccion: 'Address', lbl_telefono: 'Phone', lbl_contacto: 'Contact', lbl_estado: 'Status',
        inv_title: 'Inventory', inv_sub: 'Check and update your branch stock.', btn_actualizar_existencias: 'Update Stock',
        placeholder_buscar_producto: 'Search product or code...', solo_bajo_stock: 'Low stock only', sin_resultados: 'No results.',
        prod_title: 'Products', prod_sub: 'Register, check and edit your branch products.', btn_nuevo_producto: 'New Product',
        sin_productos: 'No products registered.',
        caj_title: 'Registers', caj_sub: 'Register cash registers and cashiers for your branch.', btn_nueva_caja: 'New Register', sin_cajas: 'No registers yet.',
        ven_title: 'Sales History', ven_sub: 'Monitor the sales made at your branch.', sin_ventas: 'No sales in range.',
        pos_caja_label: 'Register', pos_cajero_label: 'Cashier:', btn_cerrar_turno: 'Close Shift',
        resumen_venta: 'Order Summary', cargando_metodos: 'Loading payment methods...',
        subtotal_label: 'Subtotal:', iva_label: 'Tax (16%):', total_pagar: 'Total Due', btn_procesar_cobro: 'PROCESS PAYMENT',
        carrito_vacio: 'The cart is empty. Scan a product.',
        th_producto: 'Product', th_codigo: 'Code', th_existencia: 'Stock', th_precio: 'Price', th_nivel: 'Level',
        th_categoria: 'Category', th_stock: 'Stock', th_estado: 'Status', th_nombre: 'Name', th_cajeros: 'Cashiers', th_registrada: 'Registered',
        th_folio: 'ID', th_fecha: 'Date', th_personal: 'Staff', th_metodo: 'Method', th_total: 'Total', th_cant: 'Qty', th_subtotal: 'Subtotal',
        editar: 'Edit', eliminar: 'Delete', ver: 'View', ver_stock: 'View stock', guardar: 'Save', cancelar: 'Cancel', registrar: 'Register',
        cajeros_btn: 'Cashiers', mas_cajero_btn: '+ Cashier',
        nav_cortes: 'Cash Closings', cortes_title: 'Cash Closings', cortes_sub: 'Check the closings recorded for each cash shift.',
        th_caja: 'Register', th_inicio: 'Start', th_fin: 'End', th_inicial: 'Initial', th_vendido: 'Sold', th_esperado: 'Expected',
        th_contado: 'Counted', th_diferencia: 'Difference', th_extra: 'Overtime', sin_cortes: 'No closings recorded yet.',
        si: 'Yes', no: 'No', abierto: 'Open',
        pos_scan_placeholder: 'Scan, or type name/code...', fondo_inicial_label: 'Initial fund:', efectivo_esperado_label: 'Expected cash:',
        btn_registrar_retiro: '+ Register withdrawal', agotado_label: 'Out of stock'
    }
};
let currentLang = localStorage.getItem('kion-gerente-lang') || 'es';
function t(key) { return (I18N[currentLang] && I18N[currentLang][key]) || I18N.es[key] || key; }
function applyLanguage() {
    document.documentElement.lang = currentLang;
    document.querySelectorAll('[data-i18n]').forEach(el => { el.textContent = t(el.dataset.i18n); });
    document.querySelectorAll('[data-i18n-placeholder]').forEach(el => { el.placeholder = t(el.dataset.i18nPlaceholder); });
}
const btnLang = $('#btnLang');
btnLang.addEventListener('click', () => {
    currentLang = currentLang === 'es' ? 'en' : 'es';
    localStorage.setItem('kion-gerente-lang', currentLang);
    applyLanguage();
    // Recargar vista activa para traducir contenido generado dinámicamente
    const activeView = document.querySelector('.view-section.active');
    if (activeView) switchView(activeView.id);
});
applyLanguage();

// -------- NAVEGACIÓN --------
const navButtons = document.querySelectorAll('.nav-btn[data-target]');
const views = document.querySelectorAll('.view-section');
function switchView(targetId) {
    views.forEach(v => v.classList.remove('active'));
    navButtons.forEach(b => b.classList.remove('active'));
    document.getElementById(targetId).classList.add('active');
    const btn = document.querySelector(`.nav-btn[data-target="${targetId}"]`);
    if (btn) btn.classList.add('active');
    appLayout.classList.remove('mobile-open');
    if (targetId === 'view-dash') loadDashboard();
    if (targetId === 'view-sucursal') loadSucursal();
    if (targetId === 'view-inventario') loadInventario();
    if (targetId === 'view-productos') loadProductos();
    if (targetId === 'view-cajas') loadCajas();
    if (targetId === 'view-ventas') loadVentas();
    if (targetId === 'view-cortes') loadCortes();
}
navButtons.forEach(btn => btn.addEventListener('click', e => switchView(e.currentTarget.dataset.target)));

// -------- DASHBOARD --------
let salesChartInstance = null;
async function loadDashboard() {
    try {
        const { data } = await request('get_metrics');
        $('#kpiIngresos').innerText = money(data.ih);
        $('#kpiBajo').innerText = data.bajo;
        $('#kpiCajas').innerText = data.cajasActivas;
        $('#kpiProductos').innerText = data.totalProd;

        if (salesChartInstance) salesChartInstance.destroy();
        salesChartInstance = new Chart($('#salesChart'), {
            type: 'line',
            data: { labels: data.labels, datasets: [{ label: 'Ingresos', data: data.ingresos, borderColor:'#6c4630', backgroundColor:'rgba(147,101,69,.14)', fill:true, tension:.38, pointRadius:3 }] },
            options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}} }
        });

        const { data: inv } = await request('get_inventario&solo_bajo=1');
        const rows = inv.slice(0, 6).map(x => {
            const cls = x.nivel === 'AGOTADO' ? 'b-red' : 'b-yellow';
            return `<tr><td>${escapeHtml(x.producto)}</td><td><b>${x.existencias}</b> / ${x.stock_minimo}</td><td><span class="badge ${cls}">${escapeHtml(x.nivel)}</span></td></tr>`;
        }).join('');
        $('#tblAlertas').innerHTML = `<tr><th>${t('th_producto')}</th><th>${t('th_existencia')}</th><th>${t('th_estado')}</th></tr>` + (rows || `<tr><td colspan="3" style="text-align:center;color:var(--ink-soft);padding:20px">${t('sin_alertas')}</td></tr>`);
    } catch (e) { notify(e.message, 'error'); }
}

// -------- MI SUCURSAL --------
async function loadSucursal() {
    try {
        const { data } = await request('get_sucursal');
        if (!data) { $('#infoSucursal').innerHTML = t('no_encontrada'); return; }
        $('#infoSucursal').innerHTML = `
            <div class="info-item"><span>${t('lbl_nombre')}</span><b>${escapeHtml(data.nombre)}</b></div>
            <div class="info-item"><span>${t('lbl_direccion')}</span><b>${escapeHtml(data.direccion || '-')}</b></div>
            <div class="info-item"><span>${t('lbl_telefono')}</span><b>${escapeHtml(data.telefono || '-')}</b></div>
            <div class="info-item"><span>${t('lbl_contacto')}</span><b>${escapeHtml(data.contacto || '-')}</b></div>
            <div class="info-item"><span>${t('lbl_estado')}</span><span class="badge ${data.estado==='ACTIVA'?'b-green':'b-red'}">${escapeHtml(data.estado)}</span></div>`;
    } catch (e) { notify(e.message, 'error'); }
}

// -------- INVENTARIO --------
async function loadInventario() {
    try {
        const q = $('#invSearch').value, solo = $('#invSoloBajo').checked ? '1' : '0';
        const { data } = await request(`get_inventario&q=${encodeURIComponent(q)}&solo_bajo=${solo}`);
        $('#tblInventario').innerHTML = data.length ? data.map(x => {
            const cls = x.nivel === 'OK' ? 'b-green' : (x.nivel === 'BAJO' ? 'b-yellow' : 'b-red');
            return `<tr><td><b>${escapeHtml(x.producto)}</b></td><td>${escapeHtml(x.codigo)}</td><td><b>${x.existencias}</b> / mín. ${x.stock_minimo}</td><td>${money(x.precio)}</td><td><span class="badge ${cls}">${escapeHtml(x.nivel)}</span></td></tr>`;
        }).join('') : `<tr><td colspan="5" style="text-align:center;color:var(--ink-soft);padding:30px">${t('sin_resultados')}</td></tr>`;
    } catch (e) { notify(e.message, 'error'); }
}
$('#invSearch').addEventListener('input', loadInventario);
$('#invSoloBajo').addEventListener('change', loadInventario);

$('#btnAjustarStock').addEventListener('click', async () => {
    let productos = [];
    try { productos = (await request('get_productos_simple')).data; } catch(e) { notify(e.message,'error'); return; }
    const options = productos.map(p => `<option value="${p.id_producto}">${escapeHtml(p.nombre)} (${escapeHtml(p.codigo)})</option>`).join('');
    const movLabels = currentLang === 'en'
        ? { in:'Inbound (add)', out:'Outbound (subtract)', fix:'Fixed adjustment (replace)', prod:'Product:', tipo:'Movement type:', cant:'Quantity:' }
        : { in:'Entrada (sumar)', out:'Salida (restar)', fix:'Ajuste fijo (reemplazar)', prod:'Producto:', tipo:'Tipo de movimiento:', cant:'Cantidad:' };
    const { value: form } = await Swal.fire({
        title: t('btn_actualizar_existencias'),
        html: `<div style="text-align:left;">
            <label><b>${movLabels.prod}</b></label>
            <select id="swal-prod" class="swal2-input" style="width:100%;margin:8px 0 16px;">${options}</select>
            <label><b>${movLabels.tipo}</b></label>
            <select id="swal-tipo" class="swal2-input" style="width:100%;margin:8px 0 16px;">
                <option value="entrada">${movLabels.in}</option>
                <option value="salida">${movLabels.out}</option>
                <option value="reemplazo">${movLabels.fix}</option>
            </select>
            <label><b>${movLabels.cant}</b></label>
            <input id="swal-cant" type="number" min="0" class="swal2-input" style="width:100%;margin-top:8px;" value="1">
        </div>`,
        confirmButtonText: t('guardar'), confirmButtonColor: 'var(--coffee-main)', showCancelButton: true, cancelButtonText: t('cancelar'),
        preConfirm: () => ({ id_producto: document.getElementById('swal-prod').value, tipo: document.getElementById('swal-tipo').value, cantidad: document.getElementById('swal-cant').value })
    });
    if (!form) return;
    try {
        const fd = new FormData(); Object.entries(form).forEach(([k,v]) => fd.append(k,v));
        const d = await request('ajustar_stock', { method:'POST', body: fd });
        notify(d.msg); loadInventario(); loadDashboard();
    } catch (e) { notify(e.message, 'error'); }
});

// -------- PRODUCTOS --------
async function loadProductos() {
    try {
        const q = $('#prodSearch').value;
        const { data } = await request(`get_productos&q=${encodeURIComponent(q)}`);
        $('#tblProductos').innerHTML = data.length ? data.map(x => `<tr>
            <td><b>${escapeHtml(x.codigo)}</b></td><td>${escapeHtml(x.nombre)}</td><td>${escapeHtml(x.categoria)}</td>
            <td>${money(x.precio)}</td><td>${x.existencias}</td>
            <td><span class="badge ${x.estado==='ACTIVO'?'b-green':'b-red'}">${escapeHtml(x.estado)}</span></td>
            <td><button class="chip-btn" data-edit-prod='${JSON.stringify(x).replace(/'/g,'&#39;')}'>${t('editar')}</button></td>
        </tr>`).join('') : `<tr><td colspan="7" style="text-align:center;color:var(--ink-soft);padding:30px">${t('sin_productos')}</td></tr>`;
    } catch (e) { notify(e.message, 'error'); }
}
$('#prodSearch').addEventListener('input', loadProductos);

async function productoModal(record = null) {
    let categorias = [];
    try { categorias = (await request('get_categorias')).data; } catch(e) {}
    const catOptions = categorias.map(c => `<option value="${c.id_categoria}" ${record && +record.id_categoria===+c.id_categoria?'selected':''}>${escapeHtml(c.nombre)}</option>`).join('');
    const editing = !!record;
    const L = currentLang === 'en'
        ? { titleNew:'New Product', titleEdit:'Edit Product', codigo:'Code *', nombre:'Name *', desc:'Description', cat:'Category', precio:'Base price *', minStock:'Minimum stock', initStock:'Initial stock', estado:'Status', required:'Code and name are required.' }
        : { titleNew:'Nuevo Producto', titleEdit:'Editar Producto', codigo:'Código *', nombre:'Nombre *', desc:'Descripción', cat:'Categoría', precio:'Precio base *', minStock:'Stock mínimo', initStock:'Existencia inicial', estado:'Estado', required:'Código y nombre son obligatorios.' };

    const { value: form } = await Swal.fire({
        title: editing ? L.titleEdit : L.titleNew,
        html: `<div style="text-align:left;">
            <label><b>${L.codigo}</b></label><input id="swal-codigo" class="swal2-input" style="width:100%;margin:6px 0 12px;" value="${editing?escapeHtml(record.codigo):''}">
            <label><b>${L.nombre}</b></label><input id="swal-nombre" class="swal2-input" style="width:100%;margin:6px 0 12px;" value="${editing?escapeHtml(record.nombre):''}">
            <label><b>${L.desc}</b></label><input id="swal-desc" class="swal2-input" style="width:100%;margin:6px 0 12px;" value="${editing?escapeHtml(record.descripcion||''):''}">
            <label><b>${L.cat}</b></label><select id="swal-cat" class="swal2-input" style="width:100%;margin:6px 0 12px;">${catOptions}</select>
            <label><b>${L.precio}</b></label><input id="swal-precio" type="number" step="0.01" class="swal2-input" style="width:100%;margin:6px 0 12px;" value="${editing?record.precio:''}">
            ${editing ? `<label><b>${L.minStock}</b></label><input id="swal-min" type="number" min="0" class="swal2-input" style="width:100%;margin:6px 0 12px;" value="${record.stock_minimo}">`
                      : `<label><b>${L.initStock}</b></label><input id="swal-exist" type="number" min="0" class="swal2-input" style="width:100%;margin:6px 0 12px;" value="0">
                         <label><b>${L.minStock}</b></label><input id="swal-min" type="number" min="0" class="swal2-input" style="width:100%;margin:6px 0 12px;" value="5">`}
            ${editing ? `<label><b>${L.estado}</b></label><select id="swal-estado" class="swal2-input" style="width:100%;margin:6px 0;"><option ${record.estado==='ACTIVO'?'selected':''}>ACTIVO</option><option ${record.estado==='INACTIVO'?'selected':''}>INACTIVO</option></select>` : ''}
        </div>`,
        confirmButtonText: t('guardar'), confirmButtonColor: 'var(--coffee-main)', showCancelButton: true, cancelButtonText: t('cancelar'),
        preConfirm: () => {
            const codigo = document.getElementById('swal-codigo').value.trim();
            const nombre = document.getElementById('swal-nombre').value.trim();
            if (!codigo || !nombre) { Swal.showValidationMessage(L.required); return false; }
            const out = {
                codigo, nombre, descripcion: document.getElementById('swal-desc').value,
                id_categoria: document.getElementById('swal-cat').value, precio: document.getElementById('swal-precio').value,
                stock_minimo: document.getElementById('swal-min').value
            };
            if (editing) { out.id = record.id_producto; out.estado = document.getElementById('swal-estado').value; }
            else { out.existencias = document.getElementById('swal-exist').value; }
            return out;
        }
    });
    if (!form) return;
    try {
        const fd = new FormData(); Object.entries(form).forEach(([k,v]) => fd.append(k,v));
        const d = await request(editing ? 'update_producto' : 'create_producto', { method:'POST', body: fd });
        notify(d.msg); loadProductos(); loadInventario(); loadDashboard();
    } catch (e) { notify(e.message, 'error'); }
}
$('#btnNuevoProducto').addEventListener('click', () => productoModal());
document.addEventListener('click', e => {
    const editBtn = e.target.closest('[data-edit-prod]');
    if (editBtn) productoModal(JSON.parse(editBtn.dataset.editProd));
});

// -------- CAJAS --------
async function loadCajas() {
    try {
        const { data } = await request('get_cajas');
        $('#tblCajas').innerHTML = data.length ? data.map(x => `<tr>
            <td><b>${escapeHtml(x.nombre)}</b></td>
            <td><span class="badge ${x.estado==='ACTIVA'?'b-green':'b-red'}">${escapeHtml(x.estado)}</span></td>
            <td><button class="chip-btn" data-ver-cajeros="${x.id_caja}" data-caja-nombre="${escapeHtml(x.nombre)}">${t('cajeros_btn')} (${x.total_cajeros})</button></td>
            <td>${new Date(x.fecha_registro).toLocaleDateString(currentLang === 'en' ? 'en-US' : 'es-MX')}</td>
            <td class="row-actions">
                <button class="chip-btn" data-add-cajero="${x.id_caja}" data-caja-nombre="${escapeHtml(x.nombre)}">${t('mas_cajero_btn')}</button>
                <button class="icon-x" data-del-caja="${x.id_caja}">×</button>
            </td>
        </tr>`).join('') : `<tr><td colspan="5" style="text-align:center;color:var(--ink-soft);padding:30px">${t('sin_cajas')}</td></tr>`;
    } catch (e) { notify(e.message, 'error'); }
}
$('#btnNuevaCaja').addEventListener('click', async () => {
    const L = currentLang === 'en' ? { title:'New Register', placeholder:'e.g. Main Register 01', required:'Name is required.' } : { title:'Nueva Caja', placeholder:'Ej. Caja Principal 01', required:'El nombre es obligatorio.' };
    const { value: nombre } = await Swal.fire({
        title: L.title, input: 'text', inputPlaceholder: L.placeholder,
        confirmButtonText: t('registrar'), confirmButtonColor: 'var(--coffee-main)', showCancelButton: true, cancelButtonText: t('cancelar'),
        inputValidator: v => !v && L.required
    });
    if (!nombre) return;
    try {
        const fd = new FormData(); fd.append('nombre', nombre);
        const d = await request('create_caja', { method:'POST', body: fd });
        notify(d.msg); loadCajas(); loadDashboard();
    } catch (e) { notify(e.message, 'error'); }
});

// -------- CAJEROS (registro y gestión por caja) --------
async function cajeroModal(idCaja, nombreCaja) {
    const L = currentLang === 'en'
        ? { title:`New Cashier — ${nombreCaja}`, nombre:'Cashier name *', pass:'Password *', required:'Name and password are required.', minLen:'Password must be at least 4 characters.' }
        : { title:`Nuevo Cajero — ${nombreCaja}`, nombre:'Nombre del cajero *', pass:'Contraseña *', required:'Nombre y contraseña son obligatorios.', minLen:'La contraseña debe tener al menos 4 caracteres.' };
    const { value: form } = await Swal.fire({
        title: L.title,
        html: `<div style="text-align:left;">
            <label><b>${L.nombre}</b></label><input id="swal-cnom" class="swal2-input" style="width:100%;margin:6px 0 12px;">
            <label><b>${L.pass}</b></label><input id="swal-cpass" type="password" class="swal2-input" style="width:100%;margin:6px 0;">
        </div>`,
        confirmButtonText: t('registrar'), confirmButtonColor: 'var(--coffee-main)', showCancelButton: true, cancelButtonText: t('cancelar'),
        preConfirm: () => {
            const nombre = document.getElementById('swal-cnom').value.trim();
            const pass = document.getElementById('swal-cpass').value;
            if (!nombre || !pass) { Swal.showValidationMessage(L.required); return false; }
            if (pass.length < 4) { Swal.showValidationMessage(L.minLen); return false; }
            return { nombre, password: pass };
        }
    });
    if (!form) return;
    try {
        const fd = new FormData(); fd.append('id_caja', idCaja); fd.append('nombre', form.nombre); fd.append('password', form.password);
        const d = await request('create_cajero', { method:'POST', body: fd });
        notify(d.msg); loadCajas();
    } catch (e) { notify(e.message, 'error'); }
}
async function verCajerosModal(idCaja, nombreCaja) {
    const L = currentLang === 'en' ? { title:`Cashiers — ${nombreCaja}`, none:'No cashiers registered yet.', add:'+ New cashier' } : { title:`Cajeros — ${nombreCaja}`, none:'Aún no hay cajeros registrados.', add:'+ Nuevo cajero' };
    try {
        const { data } = await request(`get_cajeros_caja&id_caja=${idCaja}&solo_activos=0`);
        const listHtml = data.length ? data.map(c => `<div class="cajero-row"><span>${escapeHtml(c.nombre)} <span class="badge ${c.estado==='ACTIVO'?'b-green':'b-red'}" style="margin-left:6px;">${escapeHtml(c.estado)}</span></span><button class="icon-x" data-del-cajero="${c.id_cajero}" data-caja-id="${idCaja}" data-caja-nombre="${escapeHtml(nombreCaja)}">×</button></div>`).join('') : `<p style="font-size:13px;color:var(--ink-soft);">${L.none}</p>`;
        const result = await Swal.fire({
            title: L.title,
            html: `<div style="text-align:left; max-height:300px; overflow-y:auto;">${listHtml}</div>`,
            confirmButtonText: L.add, confirmButtonColor: 'var(--coffee-main)', showCancelButton: true, cancelButtonText: t('cancelar')
        });
        if (result.isConfirmed) cajeroModal(idCaja, nombreCaja);
    } catch (e) { notify(e.message, 'error'); }
}
document.addEventListener('click', async e => {
    const delBtn = e.target.closest('[data-del-caja]');
    if (delBtn) {
        const L = currentLang === 'en' ? { title:'Delete register?', text:'This will also remove any cashiers linked to it.', confirm:'Yes, delete' } : { title:'¿Eliminar caja?', text:'También se eliminarán los cajeros vinculados a ella.', confirm:'Sí, eliminar' };
        const conf = await Swal.fire({ title:L.title, text:L.text, icon:'warning', showCancelButton:true, confirmButtonText:L.confirm, confirmButtonColor:'var(--danger)', cancelButtonText:t('cancelar') });
        if (!conf.isConfirmed) return;
        try {
            const fd = new FormData(); fd.append('id', delBtn.dataset.delCaja);
            const d = await request('delete_caja', { method:'POST', body: fd });
            notify(d.msg); loadCajas(); loadDashboard();
        } catch (err) { notify(err.message, 'error'); }
        return;
    }
    const addCajeroBtn = e.target.closest('[data-add-cajero]');
    if (addCajeroBtn) { cajeroModal(addCajeroBtn.dataset.addCajero, addCajeroBtn.dataset.cajaNombre); return; }
    const verCajerosBtn = e.target.closest('[data-ver-cajeros]');
    if (verCajerosBtn) { verCajerosModal(verCajerosBtn.dataset.verCajeros, verCajerosBtn.dataset.cajaNombre); return; }
    const delCajeroBtn = e.target.closest('[data-del-cajero]');
    if (delCajeroBtn) {
        const L = currentLang === 'en' ? { title:'Remove cashier?', confirm:'Yes, remove' } : { title:'¿Eliminar cajero?', confirm:'Sí, eliminar' };
        const conf = await Swal.fire({ title:L.title, icon:'warning', showCancelButton:true, confirmButtonText:L.confirm, confirmButtonColor:'var(--danger)', cancelButtonText:t('cancelar') });
        if (!conf.isConfirmed) return;
        try {
            const fd = new FormData(); fd.append('id', delCajeroBtn.dataset.delCajero);
            const d = await request('delete_cajero', { method:'POST', body: fd });
            notify(d.msg); loadCajas();
            verCajerosModal(delCajeroBtn.dataset.cajaId, delCajeroBtn.dataset.cajaNombre);
        } catch (err) { notify(err.message, 'error'); }
    }
});

// -------- VENTAS --------
async function loadVentas() {
    try {
        const p = new URLSearchParams({ desde: $('#ventasDesde').value, hasta: $('#ventasHasta').value });
        const { data } = await request(`get_ventas&${p}`);
        $('#tblVentas').innerHTML = data.length ? data.map(x => `<tr>
            <td>#${x.id_venta}</td><td>${new Date(x.fecha_hora).toLocaleString('es-MX')}</td><td>${escapeHtml(x.empleado)}</td>
            <td>${escapeHtml(x.metodo_pago)}</td><td><b>${money(x.total)}</b></td>
            <td><span class="badge b-green">${escapeHtml(x.estado)}</span></td>
            <td><button class="chip-btn" data-ver-venta="${x.id_venta}">${t('ver')}</button></td>
        </tr>`).join('') : `<tr><td colspan="7" style="text-align:center;color:var(--ink-soft);padding:30px">${t('sin_ventas')}</td></tr>`;
    } catch (e) { notify(e.message, 'error'); }
}
['#ventasDesde','#ventasHasta'].forEach(s => $(s).addEventListener('change', loadVentas));
document.addEventListener('click', async e => {
    const verBtn = e.target.closest('[data-ver-venta]');
    if (!verBtn) return;
    try {
        const { data } = await request(`get_venta_detalle&id=${verBtn.dataset.verVenta}`);
        const Ld = currentLang === 'en' ? { title:'Sale Detail', noData:'No data.' } : { title:'Detalle Venta', noData:'Sin datos.' };
        Swal.fire({
            title: `${Ld.title} #${verBtn.dataset.verVenta}`,
            html: data.length ? `<table style="width:100%;text-align:left;"><tr><th>${t('th_producto')}</th><th>${t('th_cant')}</th><th style="text-align:right">${t('th_subtotal')}</th></tr>${data.map(x=>`<tr><td>${escapeHtml(x.producto)}</td><td>${x.cantidad}</td><td style="text-align:right">${money(x.subtotal)}</td></tr>`).join('')}</table>` : Ld.noData
        });
    } catch (e) { notify(e.message, 'error'); }
});

// -------- CORTES DE CAJA --------
async function loadCortes() {
    try {
        const { data } = await request('get_cortes_caja');
        $('#tblCortes').innerHTML = data.length ? data.map(x => {
            const abierto = x.estado === 'ABIERTO';
            const diffCls = abierto ? '' : (x.estado_corte === 'EXACTO' ? 'b-green' : (x.estado_corte === 'SOBRANTE' ? 'b-yellow' : 'b-red'));
            return `<tr>
                <td><b>${escapeHtml(x.cajero_nombre)}</b></td><td>${escapeHtml(x.caja_nombre)}</td>
                <td>${new Date(x.fecha_inicio).toLocaleString(currentLang === 'en' ? 'en-US' : 'es-MX')}</td>
                <td>${x.fecha_fin ? new Date(x.fecha_fin).toLocaleString(currentLang === 'en' ? 'en-US' : 'es-MX') : `<span class="badge b-yellow">${t('abierto')}</span>`}</td>
                <td>${money(x.dinero_inicial)}</td><td>${x.total_vendido !== null ? money(x.total_vendido) : '-'}</td>
                <td>${x.dinero_esperado !== null ? money(x.dinero_esperado) : '-'}</td><td>${x.dinero_contado !== null ? money(x.dinero_contado) : '-'}</td>
                <td>${x.diferencia !== null ? `<span class="badge ${diffCls}">${money(x.diferencia)}</span>` : '-'}</td>
                <td>${x.horas_extra == 1 ? t('si') : t('no')}</td>
                <td><span class="badge ${abierto ? 'b-yellow' : 'b-green'}">${escapeHtml(x.estado)}</span></td>
            </tr>`;
        }).join('') : `<tr><td colspan="11" style="text-align:center;color:var(--ink-soft);padding:30px">${t('sin_cortes')}</td></tr>`;
    } catch (e) { notify(e.message, 'error'); }
}
const btnIrPOS = $('#btnIrPOS'), btnCerrarCaja = $('#btnCerrarCaja');
let cart = [], shiftOpen = false;
let currentCajaId = null, currentCajeroId = null, currentTurnoId = null;
let dineroInicial = 0, turnoInicioTs = null, horasExtraAutorizadas = false, turno8hPrompted = false;
let retiroPromptOpen = false, turnoTimerHandle = null;
const OCHO_HORAS_MS = 8 * 60 * 60 * 1000;

function guardarTurnoLocal() {
    localStorage.setItem('kion-gerente-turno', JSON.stringify({
        id_turno: currentTurnoId, id_caja: currentCajaId, id_cajero: currentCajeroId,
        dinero_inicial: dineroInicial, turno_inicio_ts: turnoInicioTs, horas_extra: horasExtraAutorizadas,
        nombre_caja: $('#lblCajaActual').innerText, nombre_cajero: $('#lblCajeroActual').innerText
    }));
}
function limpiarTurnoLocal() { localStorage.removeItem('kion-gerente-turno'); }

function activarModoCaja(datosLogin) {
    currentCajaId = datosLogin.id_caja; currentCajeroId = datosLogin.id_cajero; currentTurnoId = datosLogin.id_turno;
    dineroInicial = parseFloat(datosLogin.dinero_inicial) || 0;
    turnoInicioTs = new Date(String(datosLogin.fecha_inicio).replace(' ', 'T')).getTime() || Date.now();
    horasExtraAutorizadas = !!datosLogin.horas_extra;
    turno8hPrompted = false;
    $('#lblCajaActual').innerText = datosLogin.nombre_caja;
    $('#lblCajeroActual').innerText = datosLogin.nombre_cajero;
    $('#posFondoInicial').innerText = money(dineroInicial);
    shiftOpen = true;
    appLayout.classList.add('pos-mode');
    guardarTurnoLocal();
    iniciarTemporizadorTurno();
    refrescarEsperado();
}

function iniciarTemporizadorTurno() {
    if (turnoTimerHandle) clearInterval(turnoTimerHandle);
    actualizarLblTiempo();
    turnoTimerHandle = setInterval(() => {
        actualizarLblTiempo();
        const transcurrido = Date.now() - turnoInicioTs;
        if (transcurrido >= OCHO_HORAS_MS && !turno8hPrompted && !horasExtraAutorizadas) {
            turno8hPrompted = true;
            manejarTurno8h();
        }
    }, 30000);
}
function detenerTemporizadorTurno() { if (turnoTimerHandle) { clearInterval(turnoTimerHandle); turnoTimerHandle = null; } }
function actualizarLblTiempo() {
    if (!turnoInicioTs) return;
    const mins = Math.floor((Date.now() - turnoInicioTs) / 60000);
    const h = Math.floor(mins / 60), m = mins % 60;
    $('#lblTurnoTiempo').textContent = (currentLang === 'en' ? `${h}h ${m}m in shift` : `${h}h ${m}m en turno`) + (horasExtraAutorizadas ? ' · ' + (currentLang === 'en' ? 'overtime' : 'horas extra') : '');
}

async function manejarTurno8h() {
    const L = currentLang === 'en'
        ? { title:'8-hour shift reached', text:'Will the cashier work overtime?', yes:'Yes, overtime', no:'No, close shift' }
        : { title:'Turno de 8 horas cumplido', text:'¿El cajero hará horas extra?', yes:'Sí, horas extra', no:'No, cerrar turno' };
    const r = await Swal.fire({ title:L.title, text:L.text, icon:'warning', showDenyButton:true, showCancelButton:false, confirmButtonText:L.yes, denyButtonText:L.no, allowOutsideClick:false, allowEscapeKey:false, confirmButtonColor:'var(--success)', denyButtonColor:'var(--danger)' });
    if (r.isConfirmed) {
        const Lp = currentLang === 'en' ? { title:'Authorize overtime', placeholder:'Manager password', confirmBtn:'Authorize', required:'Enter the manager password.' } : { title:'Autorizar horas extra', placeholder:'Contraseña del gerente', confirmBtn:'Autorizar', required:'Ingresa la contraseña del gerente.' };
        const pr = await Swal.fire({
            title: Lp.title, input:'password', inputPlaceholder: Lp.placeholder, showCancelButton:true, confirmButtonText: Lp.confirmBtn, cancelButtonText:t('cancelar'), confirmButtonColor:'var(--coffee-main)',
            inputValidator: v => !v && Lp.required,
            preConfirm: async (pass) => {
                try { const fd = new FormData(); fd.append('id_turno', currentTurnoId); fd.append('password', pass); await request('autorizar_horas_extra', { method:'POST', body: fd }); return true; }
                catch (err) { Swal.showValidationMessage(err.message); return false; }
            }
        });
        if (pr.isConfirmed) { horasExtraAutorizadas = true; guardarTurnoLocal(); actualizarLblTiempo(); notify(currentLang === 'en' ? 'Overtime authorized.' : 'Horas extra autorizadas.'); }
        else { await abrirFlujoCierre(true); }
    } else if (r.isDenied) {
        await abrirFlujoCierre(true);
    }
}

async function refrescarEsperado() {
    if (!currentTurnoId) return;
    try {
        const { data } = await request(`get_turno_estado&id_turno=${currentTurnoId}`);
        $('#posEfectivoEsperado').innerText = money(data.dinero_esperado);
        if (dineroInicial > 0 && data.dinero_esperado > dineroInicial * 2) promptRetiro();
    } catch (e) {}
}

async function promptRetiro() {
    if (retiroPromptOpen) return; retiroPromptOpen = true;
    const L = currentLang === 'en'
        ? { title:'Cash withdrawal suggested', text:'Cash in the register has exceeded twice the initial fund. Register a withdrawal amount (leave empty to skip for now).', confirmBtn:'Register withdrawal', later:'Not now', motivo:'Threshold withdrawal' }
        : { title:'Se sugiere un retiro de efectivo', text:'El efectivo en caja superó el doble del fondo inicial. Registra el monto a retirar (deja vacío para omitir por ahora).', confirmBtn:'Registrar retiro', later:'Ahora no', motivo:'Retiro por umbral' };
    const { value: monto } = await Swal.fire({
        title: L.title, html: `<p style="font-size:13px;text-align:left;">${L.text}</p>`, input:'number', inputAttributes:{ min:0, step:'0.01' },
        confirmButtonText: L.confirmBtn, confirmButtonColor: 'var(--coffee-main)', showCancelButton:true, cancelButtonText: L.later
    });
    retiroPromptOpen = false;
    if (!monto || parseFloat(monto) <= 0) return;
    try {
        const fd = new FormData(); fd.append('id_turno', currentTurnoId); fd.append('monto', monto); fd.append('motivo', L.motivo);
        const d = await request('registrar_retiro', { method:'POST', body: fd });
        notify(d.msg); refrescarEsperado();
    } catch (e) { notify(e.message, 'error'); }
}
$('#btnRetiroManual').addEventListener('click', async () => {
    const L = currentLang === 'en' ? { title:'Register cash withdrawal', placeholder:'Amount', confirmBtn:'Register', required:'Enter a valid amount.' } : { title:'Registrar retiro de efectivo', placeholder:'Monto', confirmBtn:'Registrar', required:'Ingresa un monto válido.' };
    const { value: monto } = await Swal.fire({
        title: L.title, input:'number', inputAttributes:{ min:0, step:'0.01' }, inputPlaceholder: L.placeholder,
        confirmButtonText: L.confirmBtn, confirmButtonColor: 'var(--coffee-main)', showCancelButton:true, cancelButtonText: t('cancelar'),
        inputValidator: v => (!v || parseFloat(v) <= 0) && L.required
    });
    if (!monto) return;
    try {
        const fd = new FormData(); fd.append('id_turno', currentTurnoId); fd.append('monto', monto); fd.append('motivo', currentLang === 'en' ? 'Manual withdrawal' : 'Retiro manual');
        const d = await request('registrar_retiro', { method:'POST', body: fd });
        notify(d.msg); refrescarEsperado();
    } catch (e) { notify(e.message, 'error'); }
});

async function abrirFlujoCierre(obligatorio = false) {
    let estado;
    try { estado = (await request(`get_turno_estado&id_turno=${currentTurnoId}`)).data; } catch (e) { notify(e.message, 'error'); return; }
    const L = currentLang === 'en'
        ? { title:'Cash register closing', inicial:'Initial cash', vendido:'Total sold', retiros:'Withdrawals', esperado:'Expected cash', contado:'Cash physically counted *', pass:'Manager password *', confirmar:'Save closing', fillAll:'Enter the counted amount and the manager password.', guardado:'Closing saved', exacto:'Exact register', sobrante:'Surplus', faltante:'Shortage', diferencia:'Difference' }
        : { title:'Corte de Caja', inicial:'Dinero inicial', vendido:'Total vendido', retiros:'Retiros', esperado:'Dinero esperado', contado:'Dinero contado físicamente *', pass:'Contraseña de gerente *', confirmar:'Guardar corte', fillAll:'Ingresa el dinero contado y la contraseña del gerente.', guardado:'Corte guardado', exacto:'Caja exacta', sobrante:'Sobrante', faltante:'Faltante', diferencia:'Diferencia' };

    const result = await Swal.fire({
        title: L.title,
        html: `<div style="text-align:left; font-size:13.5px;">
            <div class="cajero-row"><span>${L.inicial}</span><b>${money(estado.dinero_inicial)}</b></div>
            <div class="cajero-row"><span>${L.vendido}</span><b>${money(estado.total_vendido)}</b></div>
            <div class="cajero-row"><span>${L.retiros}</span><b>${money(estado.total_retiros)}</b></div>
            <div class="cajero-row"><span>${L.esperado}</span><b>${money(estado.dinero_esperado)}</b></div>
            <label style="display:block;margin-top:14px;"><b>${L.contado}</b></label>
            <input id="swal-contado" type="number" step="0.01" min="0" class="swal2-input" style="width:100%;margin:6px 0 12px;">
            <label><b>${L.pass}</b></label>
            <input id="swal-pass-cierre" type="password" class="swal2-input" style="width:100%;margin:6px 0;">
        </div>`,
        confirmButtonText: L.confirmar, confirmButtonColor: 'var(--danger)',
        showCancelButton: !obligatorio, cancelButtonText: t('cancelar'), allowOutsideClick: !obligatorio, allowEscapeKey: !obligatorio,
        preConfirm: async () => {
            const contado = document.getElementById('swal-contado').value;
            const pass = document.getElementById('swal-pass-cierre').value;
            if (contado === '' || !pass) { Swal.showValidationMessage(L.fillAll); return false; }
            try {
                const fd = new FormData(); fd.append('id_turno', currentTurnoId); fd.append('dinero_contado', contado); fd.append('password', pass);
                const d = await request('cerrar_turno', { method:'POST', body: fd });
                return d.data;
            } catch (err) { Swal.showValidationMessage(err.message); return false; }
        }
    });
    if (!result.isConfirmed || !result.value) { if (obligatorio) return abrirFlujoCierre(true); return; }

    const r = result.value;
    const diffLabel = r.estado_corte === 'EXACTO' ? L.exacto : (r.estado_corte === 'SOBRANTE' ? L.sobrante : L.faltante);
    const diffIcon = r.estado_corte === 'FALTANTE' ? 'warning' : 'success';
    await Swal.fire({
        title: L.guardado, icon: diffIcon,
        html: `<div style="text-align:left; font-size:13.5px;"><div class="cajero-row"><span>${L.diferencia}</span><b>${money(r.diferencia)} — ${diffLabel}</b></div></div>`
    });

    detenerTemporizadorTurno();
    shiftOpen = false; cart = []; currentCajaId = null; currentCajeroId = null; currentTurnoId = null;
    dineroInicial = 0; turnoInicioTs = null; horasExtraAutorizadas = false; turno8hPrompted = false;
    updateCartUI();
    $('#lblCajaActual').innerText = '-'; $('#lblCajeroActual').innerText = '—'; $('#lblTurnoTiempo').textContent = '';
    appLayout.classList.remove('pos-mode');
    limpiarTurnoLocal();
    switchView('view-dash'); loadDashboard();
}

btnIrPOS.addEventListener('click', async () => {
    if (shiftOpen) { switchView('view-pos'); return; }
    let cajas = [];
    try { cajas = (await request('get_cajas_activas')).data; } catch(e) {}
    const Lo = currentLang === 'en'
        ? { noCajas:'Register at least one active register before opening a shift.', title:'Open Register', selCaja:'Select register:', selCajero:'Select cashier:', selCajeroPlaceholder:'-- Choose a register first --', noCajeros:'This register has no cashiers yet.', pass:'Cashier password:', fondo:'Initial cash fund:', confirmBtn:'Open Shift', fillAll:'Complete the register, cashier and password.', opened:'Shift opened', resumed:'Shift resumed' }
        : { noCajas:'Registra al menos una caja activa antes de abrir un turno.', title:'Apertura de Caja', selCaja:'Selecciona caja:', selCajero:'Selecciona cajero:', selCajeroPlaceholder:'-- Primero elige una caja --', noCajeros:'Esta caja no tiene cajeros registrados.', pass:'Contraseña del cajero:', fondo:'Fondo de caja inicial:', confirmBtn:'Abrir Turno', fillAll:'Completa la caja, el cajero y la contraseña.', opened:'Turno abierto', resumed:'Turno reanudado' };

    if (!cajas.length) { notify(Lo.noCajas, 'error'); switchView('view-cajas'); return; }
    const options = '<option value="">-- --</option>' + cajas.map(c => `<option value="${c.id_caja}">${escapeHtml(c.nombre)}</option>`).join('');

    const result = await Swal.fire({
        title: Lo.title,
        html: `<div style="text-align:left; font-size:14px;">
            <label><b>${Lo.selCaja}</b></label>
            <select id="swal-caja" class="swal2-input" style="width:100%; margin: 8px 0 16px;">${options}</select>
            <label><b>${Lo.selCajero}</b></label>
            <select id="swal-cajero" class="swal2-input" style="width:100%; margin: 8px 0 16px;" disabled><option value="">${Lo.selCajeroPlaceholder}</option></select>
            <label><b>${Lo.pass}</b></label>
            <input id="swal-pass-cajero" type="password" class="swal2-input" style="width:100%; margin: 8px 0 16px;" disabled>
            <label><b>${Lo.fondo}</b></label>
            <input id="swal-fondo-inicial" type="number" step="0.01" min="0" class="swal2-input" style="width:100%; margin: 8px 0;" value="0" disabled>
        </div>`,
        confirmButtonText: Lo.confirmBtn, confirmButtonColor: 'var(--success)', showCancelButton: true, cancelButtonText: t('cancelar'),
        didOpen: () => {
            const cajaSel = document.getElementById('swal-caja');
            const cajeroSel = document.getElementById('swal-cajero');
            const passInput = document.getElementById('swal-pass-cajero');
            const fondoInput = document.getElementById('swal-fondo-inicial');
            cajaSel.addEventListener('change', async () => {
                cajeroSel.disabled = true; passInput.disabled = true; fondoInput.disabled = true; passInput.value = '';
                if (!cajaSel.value) { cajeroSel.innerHTML = `<option value="">${Lo.selCajeroPlaceholder}</option>`; return; }
                cajeroSel.innerHTML = `<option value="">${t('cargando')}</option>`;
                try {
                    const { data } = await request(`get_cajeros_caja&id_caja=${cajaSel.value}&solo_activos=1`);
                    if (!data.length) { cajeroSel.innerHTML = `<option value="">${Lo.noCajeros}</option>`; return; }
                    cajeroSel.innerHTML = '<option value="">-- --</option>' + data.map(c => `<option value="${c.id_cajero}">${escapeHtml(c.nombre)}</option>`).join('');
                    cajeroSel.disabled = false; passInput.disabled = false; fondoInput.disabled = false;
                } catch (e) { cajeroSel.innerHTML = `<option value="">${Lo.noCajeros}</option>`; }
            });
        },
        preConfirm: async () => {
            const idCaja = document.getElementById('swal-caja').value;
            const idCajero = document.getElementById('swal-cajero').value;
            const pass = document.getElementById('swal-pass-cajero').value;
            const fondo = document.getElementById('swal-fondo-inicial').value;
            if (!idCaja || !idCajero || !pass) { Swal.showValidationMessage(Lo.fillAll); return false; }
            try {
                const fd = new FormData(); fd.append('id_caja', idCaja); fd.append('id_cajero', idCajero); fd.append('password', pass); fd.append('dinero_inicial', fondo || '0');
                const d = await request('login_cajero', { method:'POST', body: fd });
                return d.data;
            } catch (err) { Swal.showValidationMessage(err.message); return false; }
        }
    });
    if (!result.isConfirmed || !result.value) return;

    activarModoCaja(result.value);
    try {
        const { data: metodos } = await request('get_metodos_pago');
        $('#posMetodo').innerHTML = metodos.length ? metodos.map(m => `<option value="${m.id_metodo_pago}">${escapeHtml(m.nombre)}</option>`).join('') : '<option value="">-</option>';
    } catch (e) {}

    Swal.fire({ icon:'success', title: result.value.reanudado ? Lo.resumed : Lo.opened, timer:1200, showConfirmButton:false });
    switchView('view-pos');
    $('#posBarcode').focus();
});

btnCerrarCaja.addEventListener('click', () => abrirFlujoCierre(false));

// -------- BÚSQUEDA / ESCANEO DE PRODUCTOS --------
const posBarcode = $('#posBarcode');
const posSearchResults = $('#posSearchResults');
let posSearchTimer = null;

function addProductToCart(data) {
    const existing = cart.find(i => i.id_producto === data.id_producto);
    if (existing) { existing.qty++; } else { cart.push({ id_producto: data.id_producto, nombre: data.nombre, precio: parseFloat(data.precio), qty: 1 }); }
    updateCartUI();
}
function hideSearchResults() { posSearchResults.classList.remove('show'); posSearchResults.innerHTML = ''; }

// Escaneo por código de barras (Enter) — se conserva el comportamiento original
posBarcode.addEventListener('keypress', async e => {
    if (e.key !== 'Enter' || posBarcode.value.trim() === '') return;
    const codigo = posBarcode.value.trim(); posBarcode.value = ''; hideSearchResults();
    try {
        const { data } = await request(`buscar_producto_pos&codigo=${encodeURIComponent(codigo)}`);
        addProductToCart(data);
    } catch (e) { notify(e.message, 'error'); }
});

// Búsqueda dinámica por nombre o código, sin recargar la página
posBarcode.addEventListener('input', () => {
    const q = posBarcode.value.trim();
    clearTimeout(posSearchTimer);
    if (q.length === 0) { hideSearchResults(); return; }
    posSearchTimer = setTimeout(async () => {
        try {
            const { data } = await request(`buscar_productos_pos&q=${encodeURIComponent(q)}`);
            if (!data.length) { hideSearchResults(); return; }
            posSearchResults.innerHTML = data.map(p => {
                const agotado = p.existencias <= 0;
                return `<div class="pos-search-item ${agotado ? 'disabled' : ''}" ${agotado ? '' : `data-add-product='${JSON.stringify(p).replace(/'/g, '&#39;')}'`}>
                    <div><div class="psi-name">${escapeHtml(p.nombre)}</div><div class="psi-meta">${escapeHtml(p.codigo)} · ${money(p.precio)}</div></div>
                    <span class="badge ${agotado ? 'b-red' : 'b-green'}">${agotado ? t('agotado_label') : p.existencias}</span>
                </div>`;
            }).join('');
            posSearchResults.classList.add('show');
        } catch (e) { hideSearchResults(); }
    }, 200);
});
posSearchResults.addEventListener('click', e => {
    const item = e.target.closest('[data-add-product]');
    if (!item) return;
    addProductToCart(JSON.parse(item.dataset.addProduct));
    posBarcode.value = ''; hideSearchResults(); posBarcode.focus();
});
document.addEventListener('click', e => {
    if (!e.target.closest('.pos-scanner')) hideSearchResults();
});

function updateCartUI() {
    const tbody = $('#cartBody');
    if (cart.length === 0) {
        tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; padding:40px; color:var(--ink-soft);">${t('carrito_vacio')}</td></tr>`;
        $('#posSubtotal').innerText = money(0); $('#posIva').innerText = money(0); $('#posTotal').innerText = money(0);
        return;
    }
    let subtotal = 0;
    tbody.innerHTML = cart.map(item => {
        const lineTotal = item.precio * item.qty; subtotal += lineTotal;
        return `<tr><td style="padding:16px 24px;"><b>${escapeHtml(item.nombre)}</b></td>
            <td style="text-align:center;"><span style="background:var(--coffee-light); color:var(--coffee-main); padding:4px 12px; border-radius:20px; font-weight:700;">${item.qty}</span></td>
            <td style="text-align:right; color:var(--ink-soft);">${money(item.precio)}</td>
            <td style="text-align:right; padding-right:24px; font-weight:700;">${money(lineTotal)}</td></tr>`;
    }).join('');
    const iva = subtotal * 0.16, total = subtotal + iva;
    $('#posSubtotal').innerText = money(subtotal); $('#posIva').innerText = money(iva); $('#posTotal').innerText = money(total);
}

$('#btnCobrar').addEventListener('click', async () => {
    const Lp = currentLang === 'en'
        ? { noItems:'There are no products in the sale.', noMethod:'Select a payment method.', title:'Process Payment', totalLabel:'Total to charge:', confirmBtn:'Confirm Payment', success:'Payment Successful!' }
        : { noItems:'No hay productos en la venta.', noMethod:'Selecciona un método de pago.', title:'Procesar Cobro', totalLabel:'Total a cobrar:', confirmBtn:'Confirmar Pago', success:'¡Pago Exitoso!' };
    if (cart.length === 0) { notify(Lp.noItems, 'error'); return; }
    const idMetodo = $('#posMetodo').value;
    if (!idMetodo) { notify(Lp.noMethod, 'error'); return; }

    const conf = await Swal.fire({
        title: Lp.title, text: `${Lp.totalLabel} ${$('#posTotal').innerText}`, icon: 'info',
        showCancelButton: true, confirmButtonColor: 'var(--coffee-main)', confirmButtonText: Lp.confirmBtn, cancelButtonText: t('cancelar')
    });
    if (!conf.isConfirmed) return;

    try {
        const fd = new FormData();
        fd.append('carrito', JSON.stringify(cart.map(i => ({ id_producto:i.id_producto, cantidad:i.qty }))));
        fd.append('id_metodo_pago', idMetodo);
        if (currentTurnoId) fd.append('id_turno', currentTurnoId);
        const d = await request('procesar_venta', { method:'POST', body: fd });
        if (d.data && d.data.dinero_esperado !== null && d.data.dinero_esperado !== undefined) {
            $('#posEfectivoEsperado').innerText = money(d.data.dinero_esperado);
            if (dineroInicial > 0 && d.data.dinero_esperado > dineroInicial * 2) promptRetiro();
        }
        Swal.fire({ title:Lp.success, text:d.msg, icon:'success', timer:1800, showConfirmButton:false }).then(() => {
            cart = []; updateCartUI(); posBarcode.focus(); loadDashboard();
        });
    } catch (e) { notify(e.message, 'error'); }
});

// Reanudar un turno abierto si la página se recarga mientras el cajero trabaja
(async function resumirTurnoSiExiste() {
    const raw = localStorage.getItem('kion-gerente-turno');
    if (!raw) return;
    let saved;
    try { saved = JSON.parse(raw); } catch (e) { limpiarTurnoLocal(); return; }
    if (!saved || !saved.id_turno) return;
    try {
        const { data } = await request(`get_turno_estado&id_turno=${saved.id_turno}`);
        if (data.estado !== 'ABIERTO') { limpiarTurnoLocal(); return; }
        activarModoCaja({
            id_caja: saved.id_caja, id_cajero: saved.id_cajero, id_turno: saved.id_turno,
            dinero_inicial: saved.dinero_inicial, fecha_inicio: data.fecha_inicio, horas_extra: data.horas_extra,
            nombre_caja: saved.nombre_caja, nombre_cajero: saved.nombre_cajero
        });
        try {
            const { data: metodos } = await request('get_metodos_pago');
            $('#posMetodo').innerHTML = metodos.length ? metodos.map(m => `<option value="${m.id_metodo_pago}">${escapeHtml(m.nombre)}</option>`).join('') : '<option value="">-</option>';
        } catch (e) {}
        switchView('view-pos');
    } catch (e) { limpiarTurnoLocal(); }
})();

// -------- INICIO --------
loadDashboard();
</script>

</body>
</html>