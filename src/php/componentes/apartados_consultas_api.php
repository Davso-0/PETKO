<?php
declare(strict_types=1);

function petkoSincronizarVencimientos(PDO $pdo): void {
    try {
        // 1. Marcar como vencidos los que superaron fecha_limite (o 48h desde fecha_apartado)
        $pdo->exec("
            UPDATE apartados
               SET estado = 'VENCIDO',
                   fecha_vencimiento = COALESCE(fecha_vencimiento, NOW())
             WHERE estado IN ('PENDIENTE_RECOGER', 'PENDIENTE')
               AND COALESCE(fecha_limite, DATE_ADD(fecha_apartado, INTERVAL 48 HOUR)) < NOW()
               AND archivado = 0
        ");

        // 2. Archivar apartados vencidos después de 1 día completo (24h)
        $pdo->exec("
            UPDATE apartados
               SET archivado = 1
             WHERE estado = 'VENCIDO'
               AND fecha_vencimiento IS NOT NULL
               AND fecha_vencimiento <= DATE_SUB(NOW(), INTERVAL 1 DAY)
               AND archivado = 0
        ");

        // 3. Marcar consultas vencidas (respeta CANCELADA y ATENDIDA)
        $pdo->exec("
            UPDATE consultas
               SET estado = 'VENCIDA',
                   fecha_vencimiento = COALESCE(fecha_vencimiento, NOW())
             WHERE estado IN ('PENDIENTE', 'CONFIRMADA', 'PROGRAMADA')
               AND fecha_hora < NOW()
               AND archivado = 0
        ");

        // 4. Archivar consultas vencidas/no asistió después de 1 día completo
        $pdo->exec("
            UPDATE consultas
               SET archivado = 1
             WHERE estado IN ('VENCIDA', 'NO_ASISTIO')
               AND COALESCE(fecha_vencimiento, fecha_hora) <= DATE_SUB(NOW(), INTERVAL 1 DAY)
               AND archivado = 0
        ");
    } catch (Throwable $e) {
    }
}

function petkoApartadosConsultasApi(PDO $pdo, int $idUsuario, string $rolNombre, ?int $idSucursalUsuario = null): array {
    $action = (string)($_REQUEST['action'] ?? '');
    $accionesSoportadas = [
        'ac_get_resumen',
        'ac_get_apartados',
        'ac_get_apartados_archivados',
        'ac_entregar_apartado',
        'ac_cancelar_apartado',
        'ac_archivar_apartado',
        'ac_get_consultas',
        'ac_get_consultas_archivadas',
        'ac_actualizar_consulta',
        'ac_archivar_consulta',
        'ac_buscar_folio_pdv',
        'liquidar_entregar_apartado',
        'buscar_folio'
    ];

    if (!in_array($action, $accionesSoportadas, true)) {
        return ['handled' => false, 'response' => []];
    }

    if ($idUsuario <= 0) {
        return ['handled' => true, 'response' => ['ok' => false, 'msg' => 'Sesión no válida o expirada.']];
    }

    $rolLower  = mb_strtolower($rolNombre);
    $esAdmin   = str_contains($rolLower, 'admin');
    $esGerente = str_contains($rolLower, 'gerente');
    $esCajero  = str_contains($rolLower, 'cajero');

    if (!$esAdmin && !$esGerente && !$esCajero) {
        return ['handled' => true, 'response' => ['ok' => false, 'msg' => 'No tienes permisos para acceder a este módulo.']];
    }

    if (!$esAdmin) {
        $stS = $pdo->prepare("SELECT id_sucursal FROM usuarios WHERE id_usuario = ?");
        $stS->execute([$idUsuario]);
        $sucDb = (int)$stS->fetchColumn();
        if ($sucDb > 0) {
            $idSucursalUsuario = $sucDb;
        }
        if (!$idSucursalUsuario || $idSucursalUsuario <= 0) {
            return ['handled' => true, 'response' => ['ok' => false, 'msg' => 'Tu usuario no tiene una sucursal asignada.']];
        }
    }

    petkoSincronizarVencimientos($pdo);

    try {
        // =====================================================================
        // 1. INDICADORES RESUMIDOS (KPIs) Y ALERTAS
        // =====================================================================
        if ($action === 'ac_get_resumen') {
            $whereSucA = $esAdmin ? '' : ' AND a.id_sucursal = :suc ';
            $whereSucC = $esAdmin ? '' : ' AND c.id_sucursal = :suc ';
            $params = $esAdmin ? [] : [':suc' => $idSucursalUsuario];

            $sqlA = "SELECT
                        SUM(CASE WHEN a.estado IN ('PENDIENTE_RECOGER','PENDIENTE') THEN 1 ELSE 0 END) AS activos,
                        SUM(CASE WHEN a.estado IN ('PENDIENTE_RECOGER','PENDIENTE') AND a.estado_pago <> 'PAGADO' THEN 1 ELSE 0 END) AS pendientes_pago,
                        SUM(CASE WHEN a.estado IN ('PENDIENTE_RECOGER','PENDIENTE') AND a.estado_pago = 'PAGADO' THEN 1 ELSE 0 END) AS listos_entrega,
                        SUM(CASE WHEN a.estado = 'VENCIDO' THEN 1 ELSE 0 END) AS vencidos,
                        SUM(CASE WHEN a.estado IN ('PENDIENTE_RECOGER','PENDIENTE') AND COALESCE(a.fecha_limite, DATE_ADD(a.fecha_apartado, INTERVAL 48 HOUR)) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) AS por_vencer
                     FROM apartados a
                     WHERE a.archivado = 0 {$whereSucA}";
            $stA = $pdo->prepare($sqlA);
            $stA->execute($params);
            $kpiA = $stA->fetch(PDO::FETCH_ASSOC) ?: [];

            $sqlC = "SELECT
                        SUM(CASE WHEN c.estado IN ('PENDIENTE','CONFIRMADA','PROGRAMADA') AND c.fecha_hora >= NOW() THEN 1 ELSE 0 END) AS proximas,
                        SUM(CASE WHEN c.estado = 'PENDIENTE' THEN 1 ELSE 0 END) AS pendientes_confirmacion,
                        SUM(CASE WHEN c.estado = 'CANCELADA' THEN 1 ELSE 0 END) AS canceladas,
                        SUM(CASE WHEN c.estado IN ('VENCIDA','NO_ASISTIO') THEN 1 ELSE 0 END) AS vencidas,
                        SUM(CASE WHEN c.estado IN ('PENDIENTE','CONFIRMADA','PROGRAMADA') AND c.fecha_hora BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 2 HOUR) THEN 1 ELSE 0 END) AS inminentes
                     FROM consultas c
                     WHERE c.archivado = 0 {$whereSucC}";
            $stC = $pdo->prepare($sqlC);
            $stC->execute($params);
            $kpiC = $stC->fetch(PDO::FETCH_ASSOC) ?: [];

            return ['handled' => true, 'response' => [
                'ok' => true,
                'data' => [
                    'apartados' => array_map('intval', $kpiA),
                    'consultas' => array_map('intval', $kpiC),
                    'rol' => $esAdmin ? 'ADMIN' : ($esGerente ? 'GERENTE' : 'CAJERO')
                ]
            ]];
        }

        // =====================================================================
        // 2. LISTAR APARTADOS (ACTIVOS Y ARCHIVADOS) - SIN ANTICIPOS
        // =====================================================================
        if ($action === 'ac_get_apartados' || $action === 'ac_get_apartados_archivados') {
            $soloArchivados = ($action === 'ac_get_apartados_archivados');
            $q        = trim((string)($_GET['q'] ?? ''));
            $sucursal = (int)($_GET['sucursal'] ?? 0);
            $estado   = trim((string)($_GET['estado'] ?? ''));
            $estPago  = trim((string)($_GET['estado_pago'] ?? ''));
            $fecha    = trim((string)($_GET['fecha'] ?? ''));
            $proxVenc = ($_GET['proximos_vencer'] ?? '') === '1';

            $sql = "SELECT a.id_apartado,
                           COALESCE(NULLIF(a.folio, ''), CONCAT('APT-', LPAD(a.id_apartado, 5, '0'))) AS folio,
                           a.id_usuario,
                           CONCAT(u.nombre, ' ', COALESCE(u.apellido, '')) AS cliente_nombre,
                           COALESCE(a.telefono_contacto, '') AS telefono,
                           a.id_producto,
                           COALESCE(p.nombre, 'Producto apartado') AS producto_nombre,
                           COALESCE(p.codigo, 'S/C') AS producto_codigo,
                           p.imagen_url,
                           a.cantidad,
                           COALESCE(NULLIF(a.total_apartado,0), (COALESCE(p.precio,0) * a.cantidad)) AS total_apartado,
                           COALESCE(NULLIF(a.total_apartado,0), (COALESCE(p.precio,0) * a.cantidad)) AS total,
                           a.estado_pago,
                           a.estado,
                           a.fecha_apartado,
                           COALESCE(a.fecha_limite, DATE_ADD(a.fecha_apartado, INTERVAL 48 HOUR)) AS fecha_limite,
                           a.motivo_cancelacion,
                           a.archivado,
                           a.id_sucursal,
                           COALESCE(s.nombre, 'Sucursal Principal') AS sucursal_nombre
                    FROM apartados a
                    LEFT JOIN usuarios u ON u.id_usuario = a.id_usuario
                    LEFT JOIN productos p ON p.id_producto = a.id_producto
                    LEFT JOIN sucursales s ON s.id_sucursal = a.id_sucursal
                    WHERE a.archivado = " . ($soloArchivados ? '1' : '0');
            $params = [];

            if (!$esAdmin) {
                $sql .= " AND a.id_sucursal = ?";
                $params[] = $idSucursalUsuario;
            } elseif ($sucursal > 0) {
                $sql .= " AND a.id_sucursal = ?";
                $params[] = $sucursal;
            }

            if (!$soloArchivados) {
                if ($q !== '') {
                    $sql .= " AND (a.folio LIKE ? OR u.nombre LIKE ? OR u.apellido LIKE ? OR p.nombre LIKE ? OR p.codigo LIKE ? OR a.telefono_contacto LIKE ?)";
                    $like = "%$q%";
                    array_push($params, $like, $like, $like, $like, $like, $like);
                }
                if ($estado !== '') {
                    if ($estado === 'PENDIENTE_RECOGER') {
                        $sql .= " AND a.estado IN ('PENDIENTE_RECOGER', 'PENDIENTE')";
                    } else {
                        $sql .= " AND a.estado = ?";
                        $params[] = $estado;
                    }
                }
                if ($estPago !== '') {
                    $sql .= " AND a.estado_pago = ?";
                    $params[] = $estPago;
                }
                if ($fecha !== '') {
                    $sql .= " AND (DATE(a.fecha_apartado) = ? OR DATE(COALESCE(a.fecha_limite, DATE_ADD(a.fecha_apartado, INTERVAL 48 HOUR))) = ?)";
                    $params[] = $fecha;
                    $params[] = $fecha;
                }
                if ($proxVenc) {
                    $sql .= " AND a.estado IN ('PENDIENTE_RECOGER','PENDIENTE') AND COALESCE(a.fecha_limite, DATE_ADD(a.fecha_apartado, INTERVAL 48 HOUR)) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)";
                }
            }

            $sql .= $soloArchivados
                ? " ORDER BY a.id_apartado DESC LIMIT 150"
                : " ORDER BY FIELD(a.estado, 'PENDIENTE_RECOGER', 'PENDIENTE', 'VENCIDO', 'ENTREGADO', 'CANCELADO'), fecha_limite ASC, a.id_apartado DESC LIMIT 300";

            $st = $pdo->prepare($sql);
            $st->execute($params);

            return ['handled' => true, 'response' => ['ok' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)]];
        }

        // =====================================================================
        // 3. COBRAR 100% Y ENTREGAR APARTADO (DESDE PDV CAJERO / GERENTE)
        // =====================================================================
        if ($action === 'ac_entregar_apartado' || $action === 'liquidar_entregar_apartado') {
            $idApartado    = (int)($_POST['id_apartado'] ?? 0);
            $idMetodo      = max(1, (int)($_POST['id_metodo_pago'] ?? 1));
            $montoRecibido = isset($_POST['monto_recibido']) && $_POST['monto_recibido'] !== ''
                ? (float)$_POST['monto_recibido']
                : null;

            if ($idApartado <= 0) {
                throw new RuntimeException('ID de apartado inválido.');
            }

            $pdo->beginTransaction();

            $st = $pdo->prepare("
                SELECT a.*,
                       COALESCE(p.nombre, 'Producto apartado') AS producto_nombre,
                       COALESCE(p.codigo, '') AS producto_codigo,
                       COALESCE(p.precio, 0) AS precio_producto,
                       COALESCE(s.nombre, 'Sucursal Principal') AS sucursal_nombre,
                       CONCAT(u.nombre, ' ', COALESCE(u.apellido, '')) AS cliente_nombre
                  FROM apartados a
                  LEFT JOIN productos p ON p.id_producto = a.id_producto
                  LEFT JOIN sucursales s ON s.id_sucursal = a.id_sucursal
                  LEFT JOIN usuarios u ON u.id_usuario = a.id_usuario
                 WHERE a.id_apartado = ?
                 FOR UPDATE
            ");
            $st->execute([$idApartado]);
            $ap = $st->fetch(PDO::FETCH_ASSOC);

            if (!$ap) {
                throw new RuntimeException('El apartado no existe.');
            }
            if (!$esAdmin && (int)$ap['id_sucursal'] !== (int)$idSucursalUsuario) {
                throw new RuntimeException('Este apartado pertenece a otra sucursal.');
            }
            if ($ap['estado'] === 'ENTREGADO') {
                throw new RuntimeException('Este apartado ya fue entregado anteriormente.');
            }
            if (in_array($ap['estado'], ['CANCELADO', 'VENCIDO'], true)) {
                throw new RuntimeException('No se puede entregar un apartado con estado ' . $ap['estado'] . '.');
            }

            $cantidad   = max(1, (int)($ap['cantidad'] ?? 1));
            $total      = (float)($ap['total_apartado'] ?? 0);
            if ($total <= 0) {
                $total = round((float)$ap['precio_producto'] * $cantidad, 2);
            }
            $precioUnit = round($total / $cantidad, 2);

            // Buscar turno de caja ABIERTO en esa sucursal (priorizando el del usuario actual)
            // Buscar turno de caja ABIERTO en esa sucursal (priorizando el enviado por el POS)
            $idTurnoPost = (int)($_POST['id_turno'] ?? 0);
            $idTurno = null;
            if ($idTurnoPost > 0) {
                $stTurno = $pdo->prepare("
                    SELECT id_turno
                      FROM turnos_caja
                     WHERE id_turno = ? AND id_sucursal = ? AND estado = 'ABIERTO'
                     LIMIT 1
                ");
                $stTurno->execute([$idTurnoPost, (int)$ap['id_sucursal']]);
                $tVal = $stTurno->fetchColumn();
                if ($tVal) {
                    $idTurno = (int)$tVal;
                }
            }
            if ($idTurno === null) {
                $stTurno = $pdo->prepare("
                    SELECT id_turno
                      FROM turnos_caja
                     WHERE id_sucursal = ? AND estado = 'ABIERTO'
                     ORDER BY id_turno DESC
                     LIMIT 1
                ");
                $stTurno->execute([(int)$ap['id_sucursal']]);
                $tVal = $stTurno->fetchColumn();
                if ($tVal) {
                    $idTurno = (int)$tVal;
                }
            }
            // Nombre del método de pago
            $metodoNombre = ($idMetodo === 1) ? 'Efectivo' : (($idMetodo === 2) ? 'Tarjeta' : 'Transferencia');
            try {
                $stMp = $pdo->prepare("SELECT nombre FROM metodos_pago WHERE id_metodo_pago = ? LIMIT 1");
                $stMp->execute([$idMetodo]);
                $mpDb = $stMp->fetchColumn();
                if ($mpDb) {
                    $metodoNombre = (string)$mpDb;
                }
            } catch (Throwable $e) {
            }

            // Nombre del cajero / usuario que entrega
            $cajeroNombre = '';
            try {
                $stCaj = $pdo->prepare("SELECT CONCAT(nombre, ' ', COALESCE(apellido, '')) FROM usuarios WHERE id_usuario = ? LIMIT 1");
                $stCaj->execute([$idUsuario]);
                $cajeroNombre = trim((string)$stCaj->fetchColumn());
            } catch (Throwable $e) {
            }

            // Registrar la venta completa en caja (si aún no estaba pagado en línea)
            $yaPagadoOnline = ($ap['estado_pago'] === 'PAGADO');
            $idVenta = 0;

            if (!$yaPagadoOnline && $total > 0) {
                if ($idTurno !== null) {
                    $stV = $pdo->prepare("
                        INSERT INTO ventas (fecha_hora, total, estado, id_sucursal, id_metodo_pago, id_usuario, id_turno)
                        VALUES (NOW(), ?, 'COMPLETADA', ?, ?, ?, ?)
                    ");
                    $stV->execute([$total, (int)$ap['id_sucursal'], $idMetodo, $idUsuario, $idTurno]);
                } else {
                    $stV = $pdo->prepare("
                        INSERT INTO ventas (fecha_hora, total, estado, id_sucursal, id_metodo_pago, id_usuario)
                        VALUES (NOW(), ?, 'COMPLETADA', ?, ?, ?)
                    ");
                    $stV->execute([$total, (int)$ap['id_sucursal'], $idMetodo, $idUsuario]);
                }

                $idVenta = (int)$pdo->lastInsertId();

                if ($idVenta > 0 && (int)$ap['id_producto'] > 0) {
                    try {
                        $stDet = $pdo->prepare("
                            INSERT INTO detalle_ventas (id_venta, id_producto, cantidad, precio_unitario, subtotal)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stDet->execute([$idVenta, (int)$ap['id_producto'], $cantidad, $precioUnit, $total]);
                    } catch (Throwable $e) {
                    }
                }
            } else {
                $metodoNombre = 'Pagado en línea';
                $montoRecibido = null;
            }

            // Actualizar estado del apartado a ENTREGADO y PAGADO
            $pdo->prepare("
                UPDATE apartados
                   SET total_apartado = ?,
                       monto_pagado = ?,
                       saldo_pendiente = 0.00,
                       estado_pago = 'PAGADO',
                       estado = 'ENTREGADO',
                       fecha_entrega = NOW(),
                       entregado_por = ?
                 WHERE id_apartado = ?
            ")->execute([$total, $total, $idUsuario, $idApartado]);

            $pdo->commit();

            $folio = $ap['folio'] ?: ('APT-' . str_pad((string)$idApartado, 5, '0', STR_PAD_LEFT));
            $cambioEntregado = ($montoRecibido !== null && $montoRecibido >= $total)
                ? round($montoRecibido - $total, 2)
                : null;

            $ticketPayload = [
                'folio'            => $folio,
                'id_venta'         => $idVenta,
                'id_turno'         => $idTurno,
                'fecha_hora'       => date('d/m/Y H:i'),
                'metodo_pago'      => $metodoNombre,
                'sucursal'         => $ap['sucursal_nombre'],
                'cliente'          => trim((string)$ap['cliente_nombre']),
                'cajero'           => $cajeroNombre !== '' ? $cajeroNombre : 'Personal en caja',
                'total'            => $total,
                'monto_recibido'   => $montoRecibido,
                'cambio_entregado' => $cambioEntregado,
                'items'            => [[
                    'nombre'          => $ap['producto_nombre'],
                    'cantidad'        => $cantidad,
                    'precio_unitario' => $precioUnit,
                    'subtotal'        => $total
                ]]
            ];

            return ['handled' => true, 'response' => [
                'ok'         => true,
                'msg'        => 'Apartado cobrado y entregado correctamente.',
                'mensaje'    => 'Apartado cobrado y entregado correctamente.',
                'id_turno'   => $idTurno,
                'ticket'     => $ticketPayload,
                'ticket_url' => "../../componentes/comprobante_ticket.php?tipo=apartado&folio=" . urlencode($folio)
            ]];
        }

        // =====================================================================
        // 4. CANCELAR / ELIMINAR Y ARCHIVAR APARTADO
        // =====================================================================
        if ($action === 'ac_cancelar_apartado') {
            if ($esCajero && !$esAdmin && !$esGerente) {
                throw new RuntimeException('El cajero no tiene permisos administrativos para cancelar apartados.');
            }
            $idApartado = (int)($_POST['id_apartado'] ?? 0);
            $motivo     = trim((string)($_POST['motivo'] ?? 'Cancelado por el establecimiento'));

            $pdo->beginTransaction();
            $st = $pdo->prepare("SELECT * FROM apartados WHERE id_apartado = ? FOR UPDATE");
            $st->execute([$idApartado]);
            $ap = $st->fetch(PDO::FETCH_ASSOC);

            if (!$ap) throw new RuntimeException('Apartado no encontrado.');
            if (!$esAdmin && (int)$ap['id_sucursal'] !== (int)$idSucursalUsuario) {
                throw new RuntimeException('No puedes cancelar apartados de otra sucursal.');
            }
            if ($ap['estado'] === 'ENTREGADO') {
                throw new RuntimeException('No se puede cancelar un apartado ya entregado.');
            }
            if ($ap['estado'] === 'CANCELADO') {
                throw new RuntimeException('Este apartado ya estaba cancelado.');
            }

            if ((int)$ap['id_producto'] > 0 && (int)$ap['id_sucursal'] > 0 && (int)$ap['cantidad'] > 0) {
                $pdo->prepare("UPDATE inventarios SET existencias = existencias + ? WHERE id_producto = ? AND id_sucursal = ?")
                    ->execute([(int)$ap['cantidad'], (int)$ap['id_producto'], (int)$ap['id_sucursal']]);
            }

            $pdo->prepare("
                UPDATE apartados
                   SET estado = 'CANCELADO',
                       motivo_cancelacion = ?,
                       cancelado_por = ?
                 WHERE id_apartado = ?
            ")->execute([$motivo, $idUsuario, $idApartado]);

            $pdo->commit();
            return ['handled' => true, 'response' => ['ok' => true, 'msg' => 'Apartado cancelado y existencias devueltas al inventario.']];
        }

        if ($action === 'ac_archivar_apartado') {
            if (!$esAdmin && !$esGerente) {
                throw new RuntimeException('Solo el administrador o gerente puede archivar registros.');
            }
            $idApartado = (int)($_POST['id_apartado'] ?? 0);
            $sql = "UPDATE apartados SET archivado = 1 WHERE id_apartado = ?" . ($esAdmin ? "" : " AND id_sucursal = ?");
            $st = $pdo->prepare($sql);
            $st->execute($esAdmin ? [$idApartado] : [$idApartado, $idSucursalUsuario]);
            return ['handled' => true, 'response' => ['ok' => true, 'msg' => 'Pedido movido a la sección de archivados.']];
        }

        // =====================================================================
        // 5. BÚSQUEDA RÁPIDA EN PDV (CAJERO) - PAGO COMPLETO EN SUCURSAL
        // =====================================================================
        if ($action === 'ac_buscar_folio_pdv' || $action === 'buscar_folio') {
            $q = trim((string)($_GET['folio'] ?? $_GET['q'] ?? ''));
            if ($q === '') throw new RuntimeException('Ingresa un folio, nombre o código.');

            $sql = "SELECT a.id_apartado,
                           a.id_apartado AS id,
                           COALESCE(NULLIF(a.folio, ''), CONCAT('APT-', LPAD(a.id_apartado, 5, '0'))) AS folio,
                           a.id_usuario,
                           CONCAT(u.nombre, ' ', COALESCE(u.apellido, '')) AS cliente_nombre,
                           COALESCE(a.telefono_contacto, '') AS telefono,
                           a.id_producto,
                           COALESCE(p.nombre, 'Producto') AS producto_nombre,
                           COALESCE(p.codigo, '') AS producto_codigo,
                           a.cantidad,
                           CONCAT(a.cantidad, 'x ', COALESCE(p.nombre, 'Producto')) AS productos_resumen,
                           COALESCE(NULLIF(a.total_apartado, 0), (COALESCE(p.precio, 0) * a.cantidad)) AS total_apartado,
                           COALESCE(NULLIF(a.total_apartado, 0), (COALESCE(p.precio, 0) * a.cantidad)) AS total,
                           a.estado,
                           a.estado_pago,
                           a.fecha_apartado,
                           COALESCE(a.fecha_limite, DATE_ADD(a.fecha_apartado, INTERVAL 48 HOUR)) AS fecha_limite,
                           a.id_sucursal,
                           COALESCE(s.nombre, 'Sucursal') AS sucursal_nombre
                    FROM apartados a
                    LEFT JOIN usuarios u ON u.id_usuario = a.id_usuario
                    LEFT JOIN productos p ON p.id_producto = a.id_producto
                    LEFT JOIN sucursales s ON s.id_sucursal = a.id_sucursal
                    WHERE a.archivado = 0
                      AND (a.folio LIKE ? OR u.nombre LIKE ? OR u.apellido LIKE ? OR a.telefono_contacto LIKE ? OR p.codigo LIKE ?)";
            $params = ["%$q%", "%$q%", "%$q%", "%$q%", "%$q%"];

            if (!$esAdmin) {
                $sql .= " AND a.id_sucursal = ?";
                $params[] = $idSucursalUsuario;
            }
            $sql .= " ORDER BY a.id_apartado DESC LIMIT 15";
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);

            if (!$rows) {
                return ['handled' => true, 'response' => [
                    'ok' => false,
                    'msg' => 'No se encontraron apartados en esta sucursal con ese criterio.',
                    'mensaje' => 'No se encontraron apartados en esta sucursal.'
                ]];
            }

            return ['handled' => true, 'response' => [
                'ok' => true,
                'tipo' => 'APARTADO',
                'data' => $rows[0],
                'lista' => $rows
            ]];
        }

        // =====================================================================
        // 6. CONSULTAS VETERINARIAS (ACTIVAS Y ARCHIVADAS)
        // =====================================================================
        if ($action === 'ac_get_consultas' || $action === 'ac_get_consultas_archivadas') {
            $soloArchivadas = ($action === 'ac_get_consultas_archivadas');
            $q        = trim((string)($_GET['q'] ?? ''));
            $sucursal = (int)($_GET['sucursal'] ?? 0);
            $estado   = trim((string)($_GET['estado'] ?? ''));
            $fecha    = trim((string)($_GET['fecha'] ?? ''));
            $proximas = ($_GET['proximas'] ?? '') === '1';

            $sql = "SELECT c.id_consulta,
                           COALESCE(NULLIF(c.folio, ''), CONCAT('VET-', LPAD(c.id_consulta, 5, '0'))) AS folio,
                           c.fecha_hora,
                           c.motivo,
                           COALESCE(c.servicio, 'Consulta General') AS servicio,
                           COALESCE(c.veterinario_asignado, 'Por asignar') AS veterinario_asignado,
                           c.estado,
                           c.fecha_creacion,
                           c.motivo_cancelacion,
                           m.id_mascota,
                           m.nombre AS mascota_nombre,
                           m.especie AS tipo_animal,
                           u.id_usuario,
                           CONCAT(u.nombre, ' ', COALESCE(u.apellido, '')) AS cliente_nombre,
                           c.id_sucursal,
                           s.nombre AS sucursal_nombre
                    FROM consultas c
                    INNER JOIN mascotas m ON m.id_mascota = c.id_mascota
                    INNER JOIN usuarios u ON u.id_usuario = m.id_usuario
                    INNER JOIN sucursales s ON s.id_sucursal = c.id_sucursal
                    WHERE c.archivado = " . ($soloArchivadas ? '1' : '0');
            $params = [];

            if (!$esAdmin) {
                $sql .= " AND c.id_sucursal = ?";
                $params[] = $idSucursalUsuario;
            } elseif ($sucursal > 0) {
                $sql .= " AND c.id_sucursal = ?";
                $params[] = $sucursal;
            }

            if (!$soloArchivadas) {
                if ($q !== '') {
                    $sql .= " AND (c.folio LIKE ? OR m.nombre LIKE ? OR u.nombre LIKE ? OR u.apellido LIKE ? OR c.motivo LIKE ?)";
                    $like = "%$q%";
                    array_push($params, $like, $like, $like, $like, $like);
                }
                if ($estado !== '') {
                    $sql .= " AND c.estado = ?";
                    $params[] = $estado;
                }
                if ($fecha !== '') {
                    $sql .= " AND DATE(c.fecha_hora) = ?";
                    $params[] = $fecha;
                }
                if ($proximas) {
                    $sql .= " AND c.estado IN ('PENDIENTE','CONFIRMADA','PROGRAMADA') AND c.fecha_hora >= NOW()";
                }
            }

            $sql .= $soloArchivadas
                ? " ORDER BY c.id_consulta DESC LIMIT 150"
                : " ORDER BY FIELD(c.estado, 'PENDIENTE', 'CONFIRMADA', 'PROGRAMADA', 'VENCIDA', 'NO_ASISTIO', 'ATENDIDA', 'CANCELADA'), c.fecha_hora ASC LIMIT 300";

            $st = $pdo->prepare($sql);
            $st->execute($params);

            return ['handled' => true, 'response' => ['ok' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)]];
        }

        if ($action === 'ac_actualizar_consulta') {
            if ($esCajero && !$esAdmin && !$esGerente) {
                throw new RuntimeException('El cajero solo tiene permisos de consulta en citas veterinarias.');
            }
            $idConsulta  = (int)($_POST['id_consulta'] ?? 0);
            $nuevoEstado = strtoupper(trim((string)($_POST['estado'] ?? '')));
            $veterinario = trim((string)($_POST['veterinario_asignado'] ?? ''));
            $servicio    = trim((string)($_POST['servicio'] ?? ''));
            $fechaHora   = trim((string)($_POST['fecha_hora'] ?? ''));

            $estadosValidos = ['PENDIENTE', 'CONFIRMADA', 'PROGRAMADA', 'ATENDIDA', 'CANCELADA', 'NO_ASISTIO', 'VENCIDA'];
            if (!in_array($nuevoEstado, $estadosValidos, true)) {
                throw new RuntimeException('Estado de consulta inválido.');
            }

            $st = $pdo->prepare("SELECT id_sucursal FROM consultas WHERE id_consulta = ?");
            $st->execute([$idConsulta]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new RuntimeException('Consulta no encontrada.');
            if (!$esAdmin && (int)$row['id_sucursal'] !== (int)$idSucursalUsuario) {
                throw new RuntimeException('No puedes modificar consultas de otra sucursal.');
            }

            $campos = ["estado = ?", "modificado_por = ?"];
            $vals   = [$nuevoEstado, $idUsuario];

            if ($veterinario !== '') {
                $campos[] = "veterinario_asignado = ?";
                $vals[]   = $veterinario;
            }
            if ($servicio !== '') {
                $campos[] = "servicio = ?";
                $vals[]   = $servicio;
            }
            if ($fechaHora !== '') {
                $campos[] = "fecha_hora = ?";
                $vals[]   = str_replace('T', ' ', $fechaHora);
            }
            if ($nuevoEstado === 'CANCELADA') {
                $campos[] = "motivo_cancelacion = ?";
                $vals[]   = trim((string)($_POST['motivo_cancelacion'] ?? 'Cancelada por el personal'));
            }

            $vals[] = $idConsulta;
            $pdo->prepare("UPDATE consultas SET " . implode(', ', $campos) . " WHERE id_consulta = ?")->execute($vals);

            return ['handled' => true, 'response' => ['ok' => true, 'msg' => 'Consulta actualizada y sincronizada con el usuario.']];
        }

        if ($action === 'ac_archivar_consulta') {
            if (!$esAdmin && !$esGerente) {
                throw new RuntimeException('Solo el administrador o gerente puede archivar consultas.');
            }
            $idConsulta = (int)($_POST['id_consulta'] ?? 0);
            $sql = "UPDATE consultas SET archivado = 1 WHERE id_consulta = ?" . ($esAdmin ? "" : " AND id_sucursal = ?");
            $st = $pdo->prepare($sql);
            $st->execute($esAdmin ? [$idConsulta] : [$idConsulta, $idSucursalUsuario]);
            return ['handled' => true, 'response' => ['ok' => true, 'msg' => 'Consulta movida a la sección de archivados.']];
        }

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['handled' => true, 'response' => ['ok' => false, 'msg' => $e->getMessage(), 'mensaje' => $e->getMessage()]];
    }

    return ['handled' => false, 'response' => []];
}

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'apartados_consultas_api.php') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    require_once __DIR__ . '/../config/auth_guard.php';
    if (file_exists(__DIR__ . '/../config/conexion_BD.php')) {
        require_once __DIR__ . '/../config/conexion_BD.php';
    } elseif (file_exists(__DIR__ . '/../config/conexion.php')) {
        require_once __DIR__ . '/../config/conexion.php';
    }
    $uid = (int)($_SESSION['usuario']['id_usuario'] ?? $_SESSION['usuario']['id'] ?? $_SESSION['usuario'] ?? 0);
    $rol = (string)($_SESSION['usuario']['rol'] ?? '');
    if ($rol === '' && isset($pdo) && $uid > 0) {
        $stR = $pdo->prepare("SELECT r.nombre FROM usuarios u JOIN roles r ON r.id_rol = u.id_rol WHERE u.id_usuario = ?");
        $stR->execute([$uid]);
        $rol = (string)$stR->fetchColumn();
    }
    $res = petkoApartadosConsultasApi($pdo, $uid, $rol);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($res['response'] ?: ['ok' => false, 'msg' => 'Acción inválida'], JSON_UNESCAPED_UNICODE);
    exit;
}