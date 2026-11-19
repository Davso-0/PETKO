<?php
declare(strict_types=1);

function petkoResolverImagenDashboard(?string $imagenUrl, int $idProd, string $categoria = 'PETKO'): string {
    $rutasAntiguas = [
        1 => '../../../img/perron.jpg',
        2 => '../../../img/whiskas.jpg',
        3 => '../../../img/purina.jpg',
        4 => '../../../img/malta.jpg'
    ];
    $img = trim((string)$imagenUrl);
    if ($img !== '') {
        if (preg_match('/^(https?:\/\/|data:image\/)/i', $img)) {
            return $img;
        }
        if (strpos($img, 'src/img/') === 0) {
            return '../../../img/' . substr($img, 8);
        }
        if (strpos($img, '../../img/') === 0) {
            return '../../../img/' . substr($img, 10);
        }
        return $img;
    }
    $rutaFisica = __DIR__ . '/../../img/' . $idProd . '.jpg';
    if (file_exists($rutaFisica)) {
        return '../../../img/' . $idProd . '.jpg';
    }
    if (isset($rutasAntiguas[$idProd])) {
        return $rutasAntiguas[$idProd];
    }
    return 'https://placehold.co/200x200/E8F6EE/0B2545?text=' . urlencode($categoria ?: 'PETKO');
}

function petkoMiCuentaApi(PDO $pdo, int $idUsuarioActual, string $rolActual = ''): array {
    if (!isset($_GET['action'])) {
        return ['handled' => false];
    }

    $action = $_GET['action'];
    if (!in_array($action, ['solicitar_cambio_password', 'get_apartados', 'get_mi_perfil'], true)) {
        return ['handled' => false];
    }

    try {
        if ($idUsuarioActual <= 0) {
            throw new RuntimeException('[ERROR] Sesión inválida.');
        }

        if ($action === 'get_mi_perfil') {
            $st = $pdo->prepare("SELECT u.nombre, u.apellido, u.correo, r.nombre AS rol 
                                 FROM usuarios u LEFT JOIN roles r ON r.id_rol = u.id_rol 
                                 WHERE u.id_usuario = ?");
            $st->execute([$idUsuarioActual]);
            $u = $st->fetch(PDO::FETCH_ASSOC);
            if (!$u) throw new RuntimeException('Usuario no encontrado.');
            return ['handled' => true, 'response' => ['ok' => true, 'data' => $u]];
        }

        if ($action === 'solicitar_cambio_password') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new RuntimeException('Método no permitido.');
            }

            // 1. Generar token plano (64 hex chars) y su hash SHA-256
            $tokenPlano = bin2hex(random_bytes(32));
            $tokenHash  = hash('sha256', $tokenPlano);

            // 2. Guardar token y vigencia en la base de datos
            $stUpd = $pdo->prepare("
                UPDATE usuarios 
                SET recovery_token = ?, 
                    recovery_expires_at = DATE_ADD(NOW(), INTERVAL 15 MINUTE) 
                WHERE id_usuario = ? AND estado = 'ACTIVO'
            ");
            $stUpd->execute([$tokenHash, $idUsuarioActual]);

            if ($stUpd->rowCount() !== 1) {
                throw new RuntimeException('No fue posible generar el enlace de recuperación para esta cuenta.');
            }

            $redirectUrl = '../../../../recuperar.php?token=' . $tokenPlano;
            return [
                'handled' => true,
                'response' => [
                    'ok' => true,
                    'msg' => 'Redirigiendo a restablecimiento...',
                    'redirect' => $redirectUrl
                ]
            ];
        }

        if ($action === 'get_apartados') {
            $stExpire = $pdo->prepare("
                UPDATE apartados 
                SET estado = 'VENCIDO' 
                WHERE id_usuario = ? 
                  AND estado = 'PENDIENTE' 
                  AND TIMESTAMPDIFF(HOUR, fecha_reserva, NOW()) >= COALESCE(horas_vigencia, 48)
            ");
            $stExpire->execute([$idUsuarioActual]);

            $cols = array_column($pdo->query('SHOW COLUMNS FROM apartados')->fetchAll(PDO::FETCH_ASSOC), 'Field');
            $selCantidad = in_array('cantidad', $cols, true) ? 'a.cantidad' : '1 AS cantidad';
            $selEstadoPago = in_array('estado_pago', $cols, true) ? 'a.estado_pago' : "'PENDIENTE_PAGO' AS estado_pago";

            $sql = "SELECT a.id_apartado, a.folio, {$selCantidad}, 
                           p.id_producto, p.nombre AS producto, p.precio, p.imagen_url, 
                           c.nombre AS categoria, s.nombre AS sucursal, 
                           a.fecha_reserva, a.horas_vigencia, a.estado, {$selEstadoPago}, 
                           DATE_ADD(a.fecha_reserva, INTERVAL COALESCE(a.horas_vigencia, 48) HOUR) AS fecha_expiracion 
                    FROM apartados a 
                    JOIN productos p ON p.id_producto = a.id_producto 
                    LEFT JOIN categorias c ON c.id_categoria = p.id_categoria 
                    LEFT JOIN sucursales s ON s.id_sucursal = a.id_sucursal 
                    WHERE a.id_usuario = ? 
                    ORDER BY a.fecha_reserva DESC, a.id_apartado DESC";
            $st = $pdo->prepare($sql);
            $st->execute([$idUsuarioActual]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as &$row) {
                $row['imagen_resuelta'] = petkoResolverImagenDashboard(
                    $row['imagen_url'] ?? null,
                    (int)($row['id_producto'] ?? 0),
                    (string)($row['categoria'] ?? 'PETKO')
                );
            }
            unset($row);

            return ['handled' => true, 'response' => ['ok' => true, 'data' => $rows]];
        }
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'Base table or view not found') !== false && strpos($msg, 'apartados') !== false) {
            return ['handled' => true, 'response' => ['ok' => true, 'data' => [], 'msg' => 'Aún no has apartado ningún producto.']];
        }
        return ['handled' => true, 'response' => ['ok' => false, 'msg' => $msg]];
    }

    return ['handled' => false];
}