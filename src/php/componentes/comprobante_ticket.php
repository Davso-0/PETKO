<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth_guard.php';

if (file_exists(__DIR__ . '/../config/conexion_BD.php')) {
    require_once __DIR__ . '/../config/conexion_BD.php';
} elseif (file_exists(__DIR__ . '/../config/conexion.php')) {
    require_once __DIR__ . '/../config/conexion.php';
}

// Helper para formatear fechas
function formatearFechaTicket(?string $fecha): string {
    if (!$fecha) return 'N/A';
    $ts = strtotime($fecha);
    return $ts ? date('d/m/Y H:i', $ts) : $fecha;
}

// Validar sesión compatible con la estructura de PETKO
$idUsuarioSesion = $_SESSION['id_usuario']
    ?? (is_array($_SESSION['usuario'] ?? null)
        ? ($_SESSION['usuario']['id_usuario'] ?? $_SESSION['usuario']['id'] ?? null)
        : ($_SESSION['usuario'] ?? null));

if (!$idUsuarioSesion) {
    header('Location: ../inicioSesion.php');
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('Error: No se pudo establecer la conexión PDO ($pdo) con la base de datos.');
}

$tipo  = $_GET['tipo'] ?? 'apartado'; // apartado | consulta
$folio = trim($_GET['folio'] ?? '');

if ($folio === '') {
    die('Folio no especificado.');
}

// Sincronizar vencimientos antes de imprimir
$pdo->query("UPDATE apartados SET estado = 'VENCIDO' WHERE estado IN ('PENDIENTE', 'PENDIENTE_RECOGER') AND DATE_ADD(fecha_reserva, INTERVAL horas_vigencia HOUR) <= NOW()");

$registro = null;
if ($tipo === 'consulta') {
    $stmt = $pdo->prepare("
        SELECT c.*, m.nombre AS mascota_nombre, m.especie,
               CONCAT(u.nombre, ' ', u.apellido) AS cliente_nombre, u.correo,
               s.nombre AS sucursal_nombre, s.direccion AS sucursal_dir, s.telefono AS sucursal_tel
        FROM consultas c
        INNER JOIN mascotas m ON m.id_mascota = c.id_mascota
        INNER JOIN usuarios u ON u.id_usuario = m.id_usuario
        INNER JOIN sucursales s ON s.id_sucursal = c.id_sucursal
        WHERE c.folio = ? LIMIT 1
    ");
    $stmt->execute([$folio]);
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare("
        SELECT a.*,
               DATE_ADD(a.fecha_reserva, INTERVAL a.horas_vigencia HOUR) AS fecha_limite,
               p.nombre AS producto_nombre, p.codigo AS producto_codigo,
               CONCAT(u.nombre, ' ', u.apellido) AS cliente_nombre, u.correo,
               s.nombre AS sucursal_nombre, s.direccion AS sucursal_dir, s.telefono AS sucursal_tel
        FROM apartados a
        INNER JOIN productos p ON p.id_producto = a.id_producto
        INNER JOIN usuarios u ON u.id_usuario = a.id_usuario
        INNER JOIN sucursales s ON s.id_sucursal = a.id_sucursal
        WHERE a.folio = ? LIMIT 1
    ");
    $stmt->execute([$folio]);
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$registro) {
    die('Comprobante no encontrado.');
}
?>  
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Comprobante PETKO - <?= htmlspecialchars($folio) ?></title>
<style>
  body { font-family: 'Courier New', Courier, monospace; background: #f3f4f6; margin: 0; padding: 20px; color: #111827; }
  .ticket { max-width: 360px; margin: 0 auto; background: #fff; padding: 22px; border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,.08); }
  .center { text-align: center; }
  .brand { font-size: 22px; font-weight: 800; letter-spacing: 2px; margin: 0; }
  .sub { font-size: 12px; color: #4b5563; margin: 3px 0; }
  .divider { border-top: 1px dashed #9ca3af; margin: 12px 0; }
  .row { display: flex; justify-content: space-between; font-size: 13px; margin: 6px 0; gap: 10px; }
  .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: bold; }
  .PENDIENTE { background: #fef3c7; color: #92400e; }
  .ENTREGADO, .ATENDIDA, .PAGADO { background: #d1fae5; color: #065f46; }
  .VENCIDO, .CANCELADO { background: #fee2e2; color: #991b1b; }
  .folio-box { background: #f9fafb; border: 1px solid #e5e7eb; padding: 10px; text-align: center; border-radius: 8px; margin: 10px 0; }
  .folio-code { font-size: 16px; font-weight: bold; letter-spacing: 1px; }
  .qr-wrap { text-align: center; margin: 14px 0 6px; }
  .actions { text-align: center; margin-top: 18px; display: flex; gap: 8px; justify-content: center; }
  .btn { padding: 10px 16px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 13px; }
  .btn-print { background: #0f766e; color: #fff; }
  .btn-back { background: #e5e7eb; color: #111827; text-decoration: none; }
  @media print {
    body { background: #fff; padding: 0; }
    .ticket { box-shadow: none; max-width: 100%; border-radius: 0; }
    .actions { display: none; }
  }
</style>
</head>
<body>

<div class="ticket">
  <div class="center">
    <h1 class="brand">PETKO</h1>
    <p class="sub">Veterinaria & Agropecuaria</p>
    <p class="sub">Sucursal <?= htmlspecialchars($registro['sucursal_nombre']) ?></p>
    <p class="sub"><?= htmlspecialchars($registro['sucursal_dir']) ?> · Tel: <?= htmlspecialchars($registro['sucursal_tel'] ?? '') ?></p>
  </div>

  <div class="divider"></div>

  <div class="folio-box">
    <div class="sub">FOLIO DE <?= $tipo === 'consulta' ? 'CONSULTA MÉDICA' : 'APARTADO' ?></div>
    <div class="folio-code"><?= htmlspecialchars($folio) ?></div>
    <div style="margin-top:6px;">
      <span class="badge <?= htmlspecialchars($registro['estado']) ?>"><?= htmlspecialchars($registro['estado']) ?></span>
      <span class="badge <?= htmlspecialchars($registro['estado_pago']) ?>"><?= str_replace('_', ' ', htmlspecialchars($registro['estado_pago'])) ?></span>
    </div>
  </div>

  <div class="row"><span>Cliente:</span><strong><?= htmlspecialchars($registro['cliente_nombre']) ?></strong></div>

  <?php if ($tipo === 'apartado'): ?>
    <div class="row"><span>Fecha Reserva:</span><span><?= date('d/m/Y H:i', strtotime($registro['fecha_reserva'])) ?></span></div>
    <div class="row"><span>Válido hasta:</span><strong><?= date('d/m/Y H:i', strtotime($registro['fecha_limite'])) ?></strong></div>
    <div class="divider"></div>
    <div class="row"><span>Producto:</span><strong><?= htmlspecialchars($registro['producto_nombre']) ?></strong></div>
    <div class="row"><span>Código:</span><span><?= htmlspecialchars($registro['producto_codigo']) ?></span></div>
    <div class="row"><span>Cantidad:</span><span><?= (int)$registro['cantidad'] ?> pza(s)</span></div>
    <div class="row"><span>Precio Unit.:</span><span>$<?= number_format((float)$registro['precio_unitario'], 2) ?></span></div>
    <div class="divider"></div>
    <div class="row"><span>Total Apartado:</span><strong>$<?= number_format((float)$registro['total_apartado'], 2) ?></strong></div>
    <div class="row"><span>Abonado / Pagado:</span><span>$<?= number_format((float)$registro['monto_pagado'], 2) ?></span></div>
    <div class="row" style="font-size:15px;"><span>SALDO EN CAJA:</span><strong>$<?= number_format((float)$registro['saldo_pendiente'], 2) ?></strong></div>
  <?php else: ?>
    <div class="row"><span>Cita:</span><strong><?= date('d/m/Y H:i', strtotime($registro['fecha_hora'])) ?></strong></div>
    <div class="row"><span>Mascota:</span><strong><?= htmlspecialchars($registro['mascota_nombre']) ?> (<?= htmlspecialchars($registro['especie']) ?>)</strong></div>
    <div class="row"><span>Motivo:</span><span><?= htmlspecialchars($registro['motivo']) ?></span></div>
    <div class="divider"></div>
    <div class="row" style="font-size:15px;"><span>TOTAL CONSULTA:</span><strong>$<?= number_format((float)$registro['costo_consulta'], 2) ?></strong></div>
  <?php endif; ?>

  <div class="divider"></div>

  <div class="qr-wrap">
    <img src="https://api.qrserver.com/v1/create-qr-code/?size=130x130&data=<?= urlencode($folio) ?>" alt="QR Folio">
    <p class="sub" style="margin-top:6px;">Presenta este folio o QR en caja para validar y recoger.</p>
  </div>
</div>

<div class="actions">
  <button class="btn btn-print" onclick="window.print()">Imprimir / Guardar PDF</button>
  <a class="btn btn-back" href="javascript:history.back()">Volver</a>
</div>

</body>
</html>