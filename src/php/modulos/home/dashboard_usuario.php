<?php
declare(strict_types=1);

ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Verificar si hay sesión activa
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

$usuario_data = null;
if (isset($pdo) && $pdo instanceof PDO &&$id_usuario_actual > 0) {
   $st = $pdo->prepare("SELECT u.nombre, u.apellido, u.correo, r.nombre AS rol FROM usuarios u LEFT JOIN roles r ON r.id_rol = u.id_rol WHERE u.id_usuario = ?");
    $st->execute([$id_usuario_actual]);
    $usuario_data =$st->fetch(PDO::FETCH_ASSOC);
}
$nombreCompleto = trim(($usuario_data['nombre'] ?? 'Usuario') . ' ' . ($usuario_data['apellido'] ?? ''));
$correoUsuario =$usuario_data['correo'] ?? '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Reglas del módulo de Consultas Veterinarias
const ESPECIES_MASCOTA = ['Perro', 'Gato', 'Ave', 'Conejo', 'Bovino', 'Equino', 'Ovino', 'Caprino', 'Porcino', 'Otro'];
const CONSULTA_HORA_INICIO = '09:00';
const CONSULTA_HORA_FIN = '18:30';
const CONSULTA_DIAS_MAXIMOS = 60;
const ZONA_HORARIA_CLINICAS = 'America/Mexico_City';

// Error de regla de negocio: viaja al cliente con un código estable para que el JS decida cómo mostrarlo.
class ErrorNegocio extends RuntimeException {
    public string $clave;
    public function __construct(string $clave, string $mensaje) {
        parent::__construct($mensaje);
        $this->clave = $clave;
    }
}

function validarCsrf(): void {
    $token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($token === '' || !hash_equals((string)($_SESSION['csrf_token'] ?? ''), $token)) {
        throw new RuntimeException('[ERROR] La sesión expiró. Recarga la página e inténtalo de nuevo.');
    }
}

function exigirPost(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        throw new RuntimeException('[ERROR] Método no permitido.');
    }
    validarCsrf();
}

// --------------------------------------------------------------
// API (AJAX) — Acciones exclusivas del usuario
// --------------------------------------------------------------
if (isset($_GET['action'])) {$action = $_GET['action'];$out = ['ok' => false, 'msg' => 'Acción desconocida'];
    try {
        if (!isset($pdo) || !($pdo instanceof PDO)) {             throw new RuntimeException('[ERROR] No fue posible conectar con la base de datos.');         }$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if ($id_usuario_actual <= 0) {
            throw new RuntimeException('[ERROR] Sesión inválida.');
        }

        if ($action === 'solicitar_cambio_password') {
            $bytes = random_bytes(32);
            $tokenPlano = bin2hex($bytes);
            $tokenHash = hash('sha256', $tokenPlano);
            $expiraEn = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $st = $pdo->prepare("UPDATE usuarios SET recovery_token = ?, recovery_expires_at = ? WHERE id_usuario = ?");
            $st->execute([$tokenHash, $expiraEn, $id_usuario_actual]);
            if ($st->rowCount() !== 1) {
                throw new RuntimeException('No fue posible generar el enlace de restablecimiento.');
            }
            $redirectUrl = '../../../../recuperar.php?token=' . $tokenPlano;
            $out = ['ok' => true, 'msg' => 'Redirigiendo a restablecimiento...', 'redirect' => $redirectUrl];

        } elseif ($action === 'get_apartados') {
            // Asumimos una tabla 'apartados' que relaciona al usuario, el producto y la sucursal.
            $sql = "SELECT a.id_apartado, p.nombre AS producto, p.precio, s.nombre AS sucursal, a.fecha_reserva, a.estado 
                    FROM apartados a 
                    JOIN productos p ON p.id_producto = a.id_producto 
                    JOIN sucursales s ON s.id_sucursal = a.id_sucursal 
                    WHERE a.id_usuario = ? 
                    ORDER BY a.fecha_reserva DESC";
            $st = $pdo->prepare($sql);
            $st->execute([$id_usuario_actual]);
            $out = ['ok' => true, 'data' =>$st->fetchAll(PDO::FETCH_ASSOC)];

        } elseif ($action === 'update_password') {
            $passActual = (string)($_POST['password_actual'] ?? '');
            $passNueva = (string)($_POST['password_nueva'] ?? '');
            
            if ($passActual === '' || $passNueva === '') {
                throw new Exception('[ERROR] Debes llenar ambos campos de contraseña.');
            }
            if (strlen($passNueva) < 6) {
                throw new Exception('[ERROR] La nueva contraseña debe tener al menos 6 caracteres.');
            }

            $stHash =$pdo->prepare("SELECT password_hash FROM usuarios WHERE id_usuario = ?");
            $stHash->execute([$id_usuario_actual]);
            $hashBd =$stHash->fetchColumn();

            if (!$hashBd || !password_verify($passActual,$hashBd)) {
                throw new Exception('[ERROR] La contraseña actual es incorrecta.');
            }

            $nuevoHash = password_hash($passNueva, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE usuarios SET password_hash = ? WHERE id_usuario = ?")->execute([$nuevoHash, $id_usuario_actual]);$out = ['ok' => true, 'msg' => 'Tu contraseña ha sido actualizada con éxito.'];

        // ---------------- CONSULTAS VETERINARIAS ----------------
        } elseif ($action === 'get_mascotas') {
            $st = $pdo->prepare("SELECT m.id_mascota, m.nombre, m.especie,
                                        (SELECT COUNT(*) FROM consultas c
                                          WHERE c.id_mascota = m.id_mascota AND c.estado = 'PENDIENTE') AS citas_pendientes
                                 FROM mascotas m
                                 WHERE m.id_usuario = ? AND m.estado = 'ACTIVA'
                                 ORDER BY m.nombre ASC");
            $st->execute([$id_usuario_actual]);
            $out = ['ok' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)];

        } elseif ($action === 'create_mascota') {
            exigirPost();
            $nombre = trim((string)($_POST['nombre'] ?? ''));
            $especie = (string)($_POST['especie'] ?? '');

            if ($nombre === '' || mb_strlen($nombre) > 50) {
                throw new RuntimeException('[ERROR] El nombre de la mascota es obligatorio (máximo 50 caracteres).');
            }
            if (!in_array($especie, ESPECIES_MASCOTA, true)) {
                throw new RuntimeException('[ERROR] Selecciona una especie válida.');
            }

            $st = $pdo->prepare("SELECT COUNT(*) FROM mascotas WHERE id_usuario = ? AND nombre = ? AND especie = ? AND estado = 'ACTIVA'");
            $st->execute([$id_usuario_actual, $nombre, $especie]);
            if ((int)$st->fetchColumn() > 0) {
                throw new RuntimeException('[ERROR] Ya registraste una mascota con ese nombre y especie.');
            }

            $st = $pdo->prepare("INSERT INTO mascotas (id_usuario, nombre, especie) VALUES (?, ?, ?)");
            $st->execute([$id_usuario_actual, $nombre, $especie]);
            $out = [
                'ok' => true,
                'msg' => 'Mascota registrada correctamente.',
                'data' => ['id_mascota' => (int)$pdo->lastInsertId(), 'nombre' => $nombre, 'especie' => $especie, 'citas_pendientes' => 0],
            ];

        } elseif ($action === 'eliminar_mascota') {
            exigirPost();
            $idMascota = (int)($_POST['id_mascota'] ?? 0);
            if ($idMascota <= 0) {
                throw new RuntimeException('[ERROR] Mascota no válida.');
            }

            $pdo->beginTransaction();
            try {
                $st = $pdo->prepare("SELECT nombre FROM mascotas
                                     WHERE id_mascota = ? AND id_usuario = ? AND estado = 'ACTIVA'
                                     FOR UPDATE");
                $st->execute([$idMascota, $id_usuario_actual]);
                $nombreMascota = $st->fetchColumn();
                if ($nombreMascota === false) {
                    throw new RuntimeException('[ERROR] La mascota no existe o no pertenece a tu cuenta.');
                }

                $st = $pdo->prepare("SELECT COUNT(*) FROM consultas WHERE id_mascota = ?");
                $st->execute([$idMascota]);
                $tieneHistorial = (int)$st->fetchColumn() > 0;
                $canceladas = 0;

                if (!$tieneHistorial) {
                    try {
                        $pdo->prepare("DELETE FROM mascotas WHERE id_mascota = ? AND id_usuario = ?")
                            ->execute([$idMascota, $id_usuario_actual]);
                    } catch (PDOException $e) {
                        // 1451: una consulta se registró entre el conteo y el DELETE; se archiva en su lugar.
                        if ((int)($e->errorInfo[1] ?? 0) !== 1451) {
                            throw $e;
                        }
                        $tieneHistorial = true;
                    }
                }

                if ($tieneHistorial) {
                    $st = $pdo->prepare("UPDATE consultas SET estado = 'CANCELADA' WHERE id_mascota = ? AND estado = 'PENDIENTE'");
                    $st->execute([$idMascota]);
                    $canceladas = $st->rowCount();
                    $pdo->prepare("UPDATE mascotas SET estado = 'INACTIVA' WHERE id_mascota = ? AND id_usuario = ?")
                        ->execute([$idMascota, $id_usuario_actual]);
                }
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }

            $out = [
                'ok' => true,
                'msg' => 'Mascota eliminada correctamente.',
                'data' => [
                    'id_mascota' => $idMascota,
                    'nombre' => $nombreMascota,
                    'modo' => $tieneHistorial ? 'archivada' : 'eliminada',
                    'citas_canceladas' => $canceladas,
                ],
            ];

        } elseif ($action === 'get_sucursales') {
            $st = $pdo->query("SELECT id_sucursal, nombre, direccion FROM sucursales WHERE estado = 'ACTIVA' ORDER BY nombre ASC");
            $out = ['ok' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)];

        } elseif ($action === 'get_consultas') {
            $st = $pdo->prepare("SELECT c.id_consulta, c.fecha_hora, c.motivo, c.estado,
                                        m.nombre AS mascota, m.especie, s.nombre AS sucursal
                                 FROM consultas c
                                 INNER JOIN mascotas m ON m.id_mascota = c.id_mascota
                                 INNER JOIN sucursales s ON s.id_sucursal = c.id_sucursal
                                 WHERE m.id_usuario = ?
                                 ORDER BY c.fecha_hora DESC, c.id_consulta DESC");
            $st->execute([$id_usuario_actual]);
            $out = ['ok' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)];

        } elseif ($action === 'obtener_horarios_ocupados') {
            $idSucursal = (int)($_GET['id_sucursal'] ?? 0);
            $fecha = (string)($_GET['fecha'] ?? '');
            if ($idSucursal <= 0) {
                throw new RuntimeException('[ERROR] Selecciona una sucursal.');
            }
            $zona = new DateTimeZone(ZONA_HORARIA_CLINICAS);
            $dia = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha, $zona);
            if ($dia === false || $dia->format('Y-m-d') !== $fecha) {
                throw new RuntimeException('[ERROR] La fecha no es válida.');
            }

            // Rango [día, día+1) en lugar de DATE(fecha_hora) para no anular un índice sobre la columna.
            $st = $pdo->prepare("SELECT DISTINCT DATE_FORMAT(fecha_hora, '%H:%i') AS hora
                                 FROM consultas
                                 WHERE id_sucursal = ?
                                   AND fecha_hora >= ? AND fecha_hora < ?
                                   AND estado <> 'CANCELADA'
                                 ORDER BY hora ASC");
            $st->execute([$idSucursal, $dia->format('Y-m-d 00:00:00'), $dia->modify('+1 day')->format('Y-m-d 00:00:00')]);
            $out = [
                'ok' => true,
                'data' => [
                    'fecha' => $fecha,
                    'id_sucursal' => $idSucursal,
                    'ocupados' => $st->fetchAll(PDO::FETCH_COLUMN),
                ],
            ];

        } elseif ($action === 'create_consulta') {
            exigirPost();
            $idMascota = (int)($_POST['id_mascota'] ?? 0);
            $idSucursal = (int)($_POST['id_sucursal'] ?? 0);
            $fecha = (string)($_POST['fecha'] ?? '');
            $hora = (string)($_POST['hora'] ?? '');
            $motivo = trim((string)($_POST['motivo'] ?? ''));

            if ($idMascota <= 0 || $idSucursal <= 0) {
                throw new RuntimeException('[ERROR] Selecciona la mascota y la sucursal.');
            }
            $longitudMotivo = mb_strlen($motivo);
            if ($longitudMotivo < 10 || $longitudMotivo > 500) {
                throw new RuntimeException('[ERROR] Describe el motivo de la consulta (entre 10 y 500 caracteres).');
            }
            if (!preg_match('/^([01]\d|2[0-3]):(00|30)$/', $hora) || $hora < CONSULTA_HORA_INICIO || $hora > CONSULTA_HORA_FIN) {
                throw new RuntimeException('[ERROR] Elige un horario entre las ' . CONSULTA_HORA_INICIO . ' y las ' . CONSULTA_HORA_FIN . ' h.');
            }

            $zona = new DateTimeZone(ZONA_HORARIA_CLINICAS);
            $fechaHora = DateTimeImmutable::createFromFormat('!Y-m-d H:i', "{$fecha} {$hora}", $zona);
            if ($fechaHora === false || $fechaHora->format('Y-m-d H:i') !== "{$fecha} {$hora}") {
                throw new RuntimeException('[ERROR] La fecha de la cita no es válida.');
            }
            $ahora = new DateTimeImmutable('now', $zona);
            if ($fechaHora <= $ahora) {
                throw new RuntimeException('[ERROR] La cita debe agendarse en una fecha y hora futuras.');
            }
            if ($fechaHora > $ahora->modify('+' . CONSULTA_DIAS_MAXIMOS . ' days')) {
                throw new RuntimeException('[ERROR] Solo puedes agendar con un máximo de ' . CONSULTA_DIAS_MAXIMOS . ' días de anticipación.');
            }
            $fechaHoraSql = $fechaHora->format('Y-m-d H:i:s');

            // La mascota debe pertenecer al usuario de la sesión, nunca al que diga el cliente.
            $st = $pdo->prepare("SELECT nombre FROM mascotas WHERE id_mascota = ? AND id_usuario = ? AND estado = 'ACTIVA'");
            $st->execute([$idMascota, $id_usuario_actual]);
            $nombreMascota = $st->fetchColumn();
            if ($nombreMascota === false) {
                throw new RuntimeException('[ERROR] La mascota seleccionada no existe o no pertenece a tu cuenta.');
            }

            $st = $pdo->prepare("SELECT nombre FROM sucursales WHERE id_sucursal = ? AND estado = 'ACTIVA'");
            $st->execute([$idSucursal]);
            $nombreSucursal = $st->fetchColumn();
            if ($nombreSucursal === false) {
                throw new RuntimeException('[ERROR] La sucursal seleccionada no está disponible.');
            }

            $pdo->beginTransaction();
            try {
                // Una mascota solo puede tener una cita PENDIENTE por día (en cualquier sucursal).
                $st = $pdo->prepare("SELECT c.fecha_hora, s.nombre AS sucursal
                                     FROM consultas c
                                     INNER JOIN sucursales s ON s.id_sucursal = c.id_sucursal
                                     WHERE c.id_mascota = ? AND c.estado = 'PENDIENTE'
                                       AND c.fecha_hora >= ? AND c.fecha_hora < ?
                                     LIMIT 1
                                     FOR UPDATE");
                $inicioDia = $fechaHora->setTime(0, 0);
                $st->execute([$idMascota, $inicioDia->format('Y-m-d H:i:s'), $inicioDia->modify('+1 day')->format('Y-m-d H:i:s')]);
                $citaDelDia = $st->fetch(PDO::FETCH_ASSOC);
                if ($citaDelDia) {
                    throw new ErrorNegocio('CITA_DUPLICADA', sprintf(
                        '%s ya tiene una cita pendiente ese día a las %s h en la sucursal %s. Solo se permite una cita pendiente por día para cada mascota.',
                        $nombreMascota,
                        substr($citaDelDia['fecha_hora'], 11, 5),
                        $citaDelDia['sucursal']
                    ));
                }

                // Un consultorio por sucursal: un horario solo admite una cita no cancelada,
                // y una mascota no puede tener dos citas a la misma hora.
                $st = $pdo->prepare("SELECT id_mascota, id_sucursal FROM consultas
                                     WHERE estado <> 'CANCELADA' AND fecha_hora = ?
                                       AND (id_sucursal = ? OR id_mascota = ?)
                                     FOR UPDATE");
                $st->execute([$fechaHoraSql, $idSucursal, $idMascota]);
                $choque = $st->fetch(PDO::FETCH_ASSOC);
                if ($choque) {
                    throw (int)$choque['id_mascota'] === $idMascota
                        ? new ErrorNegocio('MASCOTA_OCUPADA', 'Tu mascota ya tiene una cita a esa hora.')
                        : new ErrorNegocio('HORARIO_OCUPADO', 'Ese horario ya está ocupado en la sucursal. Elige otro.');
                }

                $st = $pdo->prepare("INSERT INTO consultas (id_mascota, id_sucursal, fecha_hora, motivo, estado) VALUES (?, ?, ?, ?, 'PENDIENTE')");
                $st->execute([$idMascota, $idSucursal, $fechaHoraSql, $motivo]);
                $idConsulta = (int)$pdo->lastInsertId();
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }

            $out = [
                'ok' => true,
                'msg' => 'Cita agendada correctamente.',
                'data' => [
                    'id_consulta' => $idConsulta,
                    'folio' => 'VET-' . str_pad((string)$idConsulta, 5, '0', STR_PAD_LEFT),
                    'mascota' => $nombreMascota,
                    'sucursal' => $nombreSucursal,
                    'fecha_hora' => $fechaHoraSql,
                ],
            ];
        }
        
    } catch (Throwable $e) {
        $msg =$e->getMessage();
        // Manejo amigable si la tabla 'apartados' aún no existe
        if (strpos($msg, 'Base table or view not found') !== false && strpos($msg, 'apartados') !== false) {$out = ['ok' => true, 'data' => [], 'msg' => 'Aún no has apartado ningún producto.'];
        } elseif (strpos($msg, 'Unknown column') !== false && strpos($msg, 'estado') !== false) {
            $out = ['ok' => false, 'msg' => '[ERROR] Falta aplicar docs/base_datos/migracion_consultas_veterinarias.sql en la base de datos.'];
        } else {
            $out = ['ok' => false, 'msg' =>$msg];
            if ($e instanceof ErrorNegocio) {
                $out['code'] = $e->clave;
            }
        }
    }
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KION · Mi Cuenta</title>
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --bg-app: oklch(96.5% 0.014 72); --bg-surface: oklch(100% 0 0); --bg-panel: oklch(99% 0.006 75);
            --ink: oklch(24% 0.028 50); --ink-soft: oklch(42% 0.030 48); --ink-faint: oklch(58% 0.022 50); --border-soft: oklch(89% 0.016 65);
            --coffee-main: oklch(32% 0.040 50); --coffee-light: oklch(91% 0.018 60);
            --coffee-gradient: linear-gradient(155deg, oklch(40% 0.045 48), oklch(26% 0.035 52));
            --success: oklch(34% 0.06 152); --success-bg: oklch(94% 0.035 152);
            --danger: oklch(50% 0.14 25); --danger-bg: oklch(95% 0.035 20);
            --warning: oklch(42% 0.09 70); --warning-bg: oklch(95% 0.045 90);
            --font-display: 'Fraunces', serif; --font-body: 'Plus Jakarta Sans', sans-serif;
            --shadow-sm: 0 4px 12px rgba(0,0,0,0.05); --shadow-md: 0 8px 24px rgba(0,0,0,0.08);
            --radius: 14px; --sidebar-w: 264px; --sidebar-w-collapsed: 84px;
            --ease-expo: cubic-bezier(0.16, 1, 0.3, 1);
        }
        body.dark-mode {
            --bg-app: oklch(20% 0.01 250); --bg-surface: oklch(25% 0.01 250); --bg-panel: oklch(23% 0.01 250);
            --ink: oklch(95% 0.01 250); --ink-soft: oklch(75% 0.01 250); --ink-faint: oklch(62% 0.01 250); --border-soft: oklch(35% 0.01 250);
            --coffee-main: oklch(80% 0.03 60); --coffee-light: oklch(30% 0.02 250);
            --coffee-gradient: linear-gradient(155deg, oklch(40% 0.045 48), oklch(20% 0.035 52));
            --success-bg: oklch(25% 0.04 152); --danger-bg: oklch(30% 0.06 20); --warning-bg: oklch(30% 0.05 85);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: var(--font-body); background: var(--bg-app); color: var(--ink); transition: background .3s ease, color .3s ease; }
        button { font: inherit; cursor: pointer; border: none; background: none; color: inherit; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        .view-section { display: none; animation: fadeIn .38s var(--ease-expo) forwards; }
        .view-section.active { display: block; }

        .app-layout { display: grid; grid-template-columns: var(--sidebar-w) 1fr; min-height: 100vh; transition: grid-template-columns .35s var(--ease-expo); }
        .app-layout.is-collapsed { grid-template-columns: var(--sidebar-w-collapsed) 1fr; }
        .app-layout > main { min-width: 0; }

        .sidebar { position: sticky; top: 0; height: 100vh; background: var(--bg-surface); border-right: 1px solid var(--border-soft); padding: 22px 16px; display: flex; flex-direction: column; gap: 26px; z-index: 40; transition: padding .35s var(--ease-expo), background .3s ease; }
        .brand { display: flex; align-items: center; gap: 12px; font-family: var(--font-display); font-size: 19px; font-weight: 600; padding: 0 8px; position: relative; }
        .brand i { font-size: 26px; color: var(--coffee-main); flex: none; }
        .brand-text { overflow: hidden; white-space: nowrap; transition: opacity .2s ease; }
        .brand-sub { font-size: 10.5px; letter-spacing: .6px; color: var(--ink-faint); font-weight: 700; text-transform: uppercase; margin-top: 1px; }
        .app-layout.is-collapsed .brand-text, .app-layout.is-collapsed .nav-label, .app-layout.is-collapsed .nav-section-title { opacity: 0; width: 0; pointer-events: none; }

        .sidebar-collapse-btn { position: absolute; top: 26px; right: -13px; width: 26px; height: 26px; border-radius: 50%; background: var(--bg-surface); border: 1px solid var(--border-soft); color: var(--coffee-main); display: grid; place-items: center; box-shadow: var(--shadow-sm); z-index: 41; transition: transform .3s var(--ease-expo); }
        .app-layout.is-collapsed .sidebar-collapse-btn { transform: rotate(180deg); }
        .sidebar-collapse-btn i { font-size: 14px; }

        .nav-section-title { font-size: 11px; text-transform: uppercase; letter-spacing: .8px; color: var(--ink-faint); font-weight: 700; padding: 4px 12px 6px; white-space: nowrap; }
        .nav-menu { display: flex; flex-direction: column; gap: 6px; }
        .nav-btn { display: flex; align-items: center; gap: 12px; padding: 11px 14px; border-radius: var(--radius); font-weight: 600; font-size: 14px; color: var(--ink-soft); transition: background .2s var(--ease-expo), color .2s ease, transform .2s var(--ease-expo); white-space: nowrap; }
        .nav-btn i { font-size: 19px; flex: none; }
        .nav-btn:hover { background: var(--bg-app); color: var(--ink); transform: translateX(2px); }
        .nav-btn.active { background: var(--coffee-gradient); color: #fff; box-shadow: var(--shadow-sm); }

        .sidebar-scrim { display: none; position: fixed; inset: 0; background: rgba(20,16,12,.45); z-index: 39; opacity: 0; transition: opacity .3s ease; }

        .topbar { height: 70px; display: flex; justify-content: space-between; align-items: center; padding: 0 32px; border-bottom: 1px solid var(--border-soft); background: var(--bg-panel); position: sticky; top: 0; z-index: 30; backdrop-filter: blur(10px); }
        .hamburger-btn { display: none; width: 38px; height: 38px; border-radius: 10px; border: 1px solid var(--border-soft); background: var(--bg-surface); align-items: center; justify-content: center; font-size: 18px; }
        .topbar-actions { display: flex; gap: 12px; align-items: center; }
        .icon-btn { width: 40px; height: 40px; border-radius: 50%; display: grid; place-items: center; background: var(--bg-app); border: 1px solid var(--border-soft); font-size: 19px; transition: background .2s ease, color .2s ease, transform .15s var(--ease-expo); }
        .icon-btn:hover { background: var(--coffee-light); color: var(--coffee-main); transform: translateY(-1px); }
        .user-profile { display: flex; align-items: center; gap: 10px; font-weight: 600; padding-left: 14px; border-left: 1px solid var(--border-soft); font-size: 14px; }
        .role-chip { font-size: 10px; font-weight: 800; letter-spacing: .5px; padding: 3px 8px; border-radius: 20px; background: var(--coffee-light); color: var(--coffee-main); text-transform: uppercase; }

        .content { padding: 32px; max-width: 1100px; margin: 0 auto; }
        .header-row { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; flex-wrap: wrap; gap: 14px; }
        .title { font-family: var(--font-display); font-size: 27px; font-weight: 600; }
        .subtitle { color: var(--ink-soft); font-size: 14px; margin-top: 4px; }

        .btn-primary { background: var(--coffee-gradient); color: #fff; padding: 12px 24px; border-radius: 10px; font-weight: 700; font-size: 14px; box-shadow: var(--shadow-sm); transition: transform .16s var(--ease-expo), box-shadow .2s ease; display: inline-flex; align-items: center; justify-content: center; width: 100%; max-width: 280px; margin-top: 10px;}
        .btn-primary:active { transform: scale(.96); }

        .panel { background: var(--bg-surface); border-radius: var(--radius); border: 1px solid var(--border-soft); padding: 24px; overflow-x: auto; margin-bottom: 22px; transition: background .3s ease, border-color .3s ease; }
        
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { font-size: 11.5px; text-transform: uppercase; letter-spacing: .4px; color: var(--ink-faint); padding-bottom: 14px; border-bottom: 1px solid var(--border-soft); font-weight: 700; }
        td { padding: 14px 0; border-bottom: 1px solid var(--border-soft); font-weight: 500; vertical-align: middle; }
        tbody tr:hover { background: var(--bg-app); }
        tr:last-child td { border: none; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11.5px; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; }
        .badge.b-green { background: var(--success-bg); color: var(--success); }
        .badge.b-yellow { background: var(--warning-bg); color: var(--warning); }
        .badge.b-sky { background: rgba(59,130,246,0.1); color: #3b82f6; }

        .form-group { margin-bottom: 18px; max-width: 400px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: var(--ink-soft); margin-bottom: 6px; }
        .form-input { width: 100%; padding: 12px 16px; border: 1px solid var(--border-soft); border-radius: 8px; background: var(--bg-app); color: var(--ink); font-size: 15px; transition: border-color .2s; outline: none; }
        .form-input:focus { border-color: var(--coffee-main); }
        .form-input:disabled { opacity: 0.7; cursor: not-allowed; }
        textarea.form-input { resize: vertical; min-height: 96px; line-height: 1.5; font-family: inherit; }
        select.form-input { cursor: pointer; }

        /* -------- CONSULTAS VETERINARIAS -------- */
        .vet-grid { display: grid; grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr); gap: 22px; align-items: start; }
        .vet-grid .panel { margin-bottom: 0; }
        .panel-head { display: flex; align-items: center; gap: 12px; margin-bottom: 18px; }
        .panel-head-icon { width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; background: var(--coffee-light); color: var(--coffee-main); font-size: 21px; flex: none; }
        .panel-head h3 { font-size: 16px; }
        .panel-head p { font-size: 12.5px; color: var(--ink-faint); margin-top: 2px; }
        .vet-form .form-group { max-width: none; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .form-hint { display: flex; justify-content: space-between; font-size: 12px; color: var(--ink-faint); margin-top: 6px; }
        .form-hint.is-limit { color: var(--danger); }
        .vet-form .btn-primary { max-width: none; }
        .btn-primary:disabled { opacity: .6; cursor: progress; }
        .btn-primary i { margin-right: 6px; font-size: 17px; }

        .pet-list { display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px; }
        .pet-card { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 1px solid var(--border-soft); border-radius: 12px; background: var(--bg-app); animation: fadeIn .38s var(--ease-expo); }
        .pet-avatar { width: 38px; height: 38px; border-radius: 50%; display: grid; place-items: center; background: var(--coffee-gradient); color: #fff; font-size: 20px; flex: none; }
        .pet-name { font-weight: 700; font-size: 14.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .pet-species { font-size: 12px; color: var(--ink-faint); font-weight: 600; }
        .pet-info { flex: 1; min-width: 0; }
        .pet-pending { display: inline-block; margin-top: 4px; font-size: 11px; font-weight: 700; color: var(--coffee-main); }
        .pet-delete { width: 34px; height: 34px; flex: none; border-radius: 50%; display: grid; place-items: center; color: var(--ink-faint); font-size: 17px; border: 1px solid transparent; transition: background .2s ease, color .2s ease, border-color .2s ease; }
        .pet-delete:hover, .pet-delete:focus-visible { background: var(--danger-bg); color: var(--danger); border-color: var(--danger); }
        .pet-delete:disabled { opacity: .5; cursor: wait; }
        .form-input.is-loading { background-image: linear-gradient(90deg, transparent, var(--coffee-light), transparent); background-size: 200% 100%; animation: slotsLoading 1s linear infinite; }
        @keyframes slotsLoading { from { background-position: 200% 0; } to { background-position: -200% 0; } }
        .vet-empty { padding: 18px; border: 1px dashed var(--border-soft); border-radius: 12px; text-align: center; color: var(--ink-faint); font-size: 13.5px; line-height: 1.5; }
        .vet-empty i { display: block; font-size: 26px; margin-bottom: 6px; color: var(--coffee-main); }
        .vet-divider { border: none; border-top: 1px solid var(--border-soft); margin: 4px 0 18px; }
        .vet-table { min-width: 680px; }
        .vet-table th, .vet-table td { white-space: nowrap; padding-right: 16px; }
        .citas-scroll { max-height: 350px; overflow: auto; overscroll-behavior: contain; scrollbar-width: thin; scrollbar-color: var(--ink-faint) transparent; border-radius: 8px; }
        .citas-scroll:focus-visible { outline: 2px solid var(--coffee-main); outline-offset: 4px; }
        .citas-scroll thead th { position: sticky; top: 0; z-index: 1; background: var(--bg-surface); padding-top: 4px; border-bottom: 0; box-shadow: inset 0 -1px 0 var(--border-soft); transition: background .3s ease; }
        .vet-table tbody tr { transition: opacity .2s ease; }
        .vet-table tr.is-cancelada { opacity: 0.6; }
        .collapse-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 999px; border: 1px solid var(--border-soft); background: var(--bg-surface); color: var(--ink-soft); font-size: 13px; font-weight: 700; transition: background .2s ease, color .2s ease, border-color .2s ease; }
        .collapse-btn:hover { background: var(--coffee-light); color: var(--coffee-main); border-color: var(--coffee-main); }
        .collapse-btn:focus-visible { outline: 2px solid var(--coffee-main); outline-offset: 2px; }
        .collapse-btn i { font-size: 15px; transition: transform .3s var(--ease-expo); }
        .collapse-btn[aria-expanded="false"] i { transform: rotate(180deg); }
        .collapsible { display: grid; grid-template-rows: 1fr; opacity: 1; transition: grid-template-rows .38s var(--ease-expo), opacity .28s ease; }
        .collapsible > .collapsible-inner { min-height: 0; overflow: hidden; visibility: visible; transition: visibility 0s; }
        .collapsible.is-collapsed { grid-template-rows: 0fr; opacity: 0; }
        .collapsible.is-collapsed > .collapsible-inner { visibility: hidden; transition: visibility 0s .38s; }
        @media (prefers-reduced-motion: reduce) {
            .collapsible, .collapsible > .collapsible-inner, .collapse-btn i { transition: none; }
        }
        .motivo-cell { max-width: 280px; color: var(--ink-soft); font-size: 13.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .badge.b-red { background: var(--danger-bg); color: var(--danger); }
        .swal2-popup .vet-resumen { text-align: left; display: grid; gap: 8px; font-size: 14.5px; margin-top: 6px; }
        .swal2-popup .vet-resumen span { color: var(--ink-faint); font-weight: 600; display: inline-block; min-width: 92px; }

        @media (max-width: 980px) {
            .app-layout { grid-template-columns: 1fr; }
            .sidebar { position: fixed; left: 0; top: 0; width: 280px; height: 100vh; transform: translateX(-100%); transition: transform .35s var(--ease-expo); box-shadow: var(--shadow-md); }
            .app-layout.mobile-open .sidebar { transform: translateX(0); }
            .app-layout.mobile-open .sidebar-scrim { display: block; opacity: 1; }
            .sidebar-collapse-btn { display: none; }
            .hamburger-btn { display: inline-flex; }
            .vet-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 560px) {
            .topbar { padding: 0 16px; }
            .user-profile > span:not(.role-chip) { display: none; }
            .content { padding: 22px 16px; }
            .form-row { grid-template-columns: 1fr; gap: 0; }
        }
    </style>
</head>
<body>

<div class="app-layout" id="appLayout">
    <aside class="sidebar">
        <button class="sidebar-collapse-btn" id="btnCollapse"><i class="ph ph-caret-left"></i></button>
        <div class="brand">
            <i class="ph-fill ph-paw-print"></i>
            <div class="brand-text"><div>KION</div><div class="brand-sub" data-i18n="brand_sub">Panel de Cliente</div></div>
        </div>
        <nav class="nav-menu">
            <div class="nav-section-title">Mi Actividad</div>
            <button class="nav-btn active" data-target="view-apartados"><i class="ph ph-shopping-bag"></i><span class="nav-label">Mis Apartados</span></button>
            <button class="nav-btn" data-target="view-consultas"><i class="ph ph-stethoscope"></i><span class="nav-label" data-i18n="nav_consultas">Consultas Veterinarias</span></button>
            <div class="nav-section-title">Ajustes</div>
            <button class="nav-btn" data-target="view-config"><i class="ph ph-gear"></i><span class="nav-label">Configuración</span></button>
            <div class="nav-section-title">Navegación</div>
            <a class="nav-btn" href="../../componentes/catalogo.php"><i class="ph ph-storefront"></i><span class="nav-label">Ir al Catálogo</span></a>
            <a class="nav-btn" href="home.php"><i class="ph ph-house"></i><span class="nav-label">Volver a Home</span></a>
            <a class="nav-btn" href="cerrarSesion.php" style="color:var(--danger)"><i class="ph ph-sign-out"></i><span class="nav-label">Cerrar sesión</span></a>
        </nav>
    </aside>
    <div class="sidebar-scrim" id="sidebarScrim"></div>

    <main>
        <header class="topbar">
            <div style="display:flex; align-items:center; gap:14px;">
                <button class="hamburger-btn" id="btnMobileMenu"><i class="ph ph-list"></i></button>
                <b id="topbarTitle" data-i18n="topbar_apartados" style="font-size: 16px;">Mis Apartados</b>
            </div>
            <div class="topbar-actions">
                <button class="icon-btn" id="btnTheme" title="Modo Oscuro" data-i18n-title="theme_title"><i class="ph ph-moon"></i></button>
                <button class="icon-btn" id="btnLang" title="Switch language / Cambiar idioma"><i class="ph ph-translate"></i></button>
                <div class="user-profile">
                    <i class="ph-fill ph-user-circle" style="font-size:24px; color:var(--coffee-main)"></i>
                    <span><?= htmlspecialchars($nombreCompleto) ?></span>
                    <span class="role-chip"><?= htmlspecialchars($usuario_data['rol'] ?? 'Cliente') ?></span>
                </div>
            </div>
        </header>

        <div class="content">

            <!-- VISTA: APARTADOS -->
            <section id="view-apartados" class="view-section active">
                <div class="header-row">
                    <div>
                        <h1 class="title" data-i18n="apartados_title">Mis Productos Apartados</h1>
                        <p class="subtitle" data-i18n="apartados_subtitle">Consulta los productos que has reservado y su sucursal de recolección.</p>
                    </div>
                </div>
                <div class="panel">
                    <table>
                        <thead>
                            <tr>
                                <th data-i18n="th_producto">Producto</th>
                                <th data-i18n="th_sucursal">Sucursal de Recolección</th>
                                <th data-i18n="th_precio">Precio</th>
                                <th data-i18n="th_fecha">Fecha de Reserva</th>
                                <th data-i18n="th_estado">Estado</th>
                            </tr>
                        </thead>
                        <tbody id="apartadosTable">
                            <tr><td colspan="5" style="text-align:center; padding: 40px; color:var(--ink-faint);" data-i18n="loading">Cargando información...</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- VISTA: CONSULTAS VETERINARIAS -->
            <section id="view-consultas" class="view-section">
                <div class="header-row">
                    <div>
                        <h1 class="title" data-i18n="consultas_title">Consultas Veterinarias</h1>
                        <p class="subtitle" data-i18n="consultas_subtitle">Registra a tus mascotas y agenda su cita en la sucursal que prefieras.</p>
                    </div>
                </div>

                <div class="vet-grid">
                    <!-- Mascotas -->
                    <div class="panel">
                        <div class="panel-head">
                            <span class="panel-head-icon"><i class="ph ph-paw-print"></i></span>
                            <div>
                                <h3 data-i18n="pets_title">Mis Mascotas</h3>
                                <p data-i18n="pets_subtitle">Los pacientes vinculados a tu cuenta.</p>
                            </div>
                        </div>

                        <div class="pet-list" id="petList" aria-live="polite">
                            <div class="vet-empty" data-i18n="loading">Cargando información...</div>
                        </div>

                        <hr class="vet-divider">

                        <form class="vet-form" id="formMascota" novalidate>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="mascotaNombre" data-i18n="pet_name_label">Nombre</label>
                                    <input type="text" class="form-input" id="mascotaNombre" name="nombre" maxlength="50" required
                                           placeholder="Ej. Firulais" data-i18n-placeholder="pet_name_ph" autocomplete="off">
                                </div>
                                <div class="form-group">
                                    <label for="mascotaEspecie" data-i18n="pet_species_label">Especie</label>
                                    <select class="form-input" id="mascotaEspecie" name="especie" required>
                                        <option value="" data-i18n="select_placeholder">Selecciona...</option>
                                        <option value="Perro" data-i18n="sp_perro">Perro</option>
                                        <option value="Gato" data-i18n="sp_gato">Gato</option>
                                        <option value="Ave" data-i18n="sp_ave">Ave</option>
                                        <option value="Conejo" data-i18n="sp_conejo">Conejo</option>
                                        <option value="Bovino" data-i18n="sp_bovino">Bovino</option>
                                        <option value="Equino" data-i18n="sp_equino">Equino</option>
                                        <option value="Ovino" data-i18n="sp_ovino">Ovino</option>
                                        <option value="Caprino" data-i18n="sp_caprino">Caprino</option>
                                        <option value="Porcino" data-i18n="sp_porcino">Porcino</option>
                                        <option value="Otro" data-i18n="sp_otro">Otro</option>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn-primary" id="btnGuardarMascota">
                                <i class="ph ph-plus-circle"></i><span data-i18n="btn_add_pet">Registrar mascota</span>
                            </button>
                        </form>
                    </div>

                    <!-- Agendar cita -->
                    <div class="panel">
                        <div class="panel-head">
                            <span class="panel-head-icon"><i class="ph ph-calendar-plus"></i></span>
                            <div>
                                <h3 data-i18n="appt_title">Agendar Cita</h3>
                                <p data-i18n="appt_subtitle">Horario de consulta: 09:00 a 18:30 h, en bloques de 30 minutos.</p>
                            </div>
                        </div>

                        <form class="vet-form" id="formConsulta" novalidate>
                            <div class="form-group">
                                <label for="consultaMascota" data-i18n="appt_pet_label">Mascota</label>
                                <select class="form-input" id="consultaMascota" name="id_mascota" required>
                                    <option value="" data-i18n="appt_pet_empty">Primero registra una mascota</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="consultaSucursal" data-i18n="appt_branch_label">Sucursal</label>
                                <select class="form-input" id="consultaSucursal" name="id_sucursal" required>
                                    <option value="" data-i18n="loading">Cargando información...</option>
                                </select>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="consultaFecha" data-i18n="appt_date_label">Fecha</label>
                                    <input type="date" class="form-input" id="consultaFecha" name="fecha" required>
                                </div>
                                <div class="form-group">
                                    <label for="consultaHora" data-i18n="appt_time_label">Hora</label>
                                    <select class="form-input" id="consultaHora" name="hora" required></select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="consultaMotivo" data-i18n="appt_reason_label">Motivo de la consulta</label>
                                <textarea class="form-input" id="consultaMotivo" name="motivo" maxlength="500" required
                                          placeholder="Describe síntomas, vacunas pendientes o el servicio que necesitas."
                                          data-i18n-placeholder="appt_reason_ph"></textarea>
                                <div class="form-hint" id="motivoHint"><span data-i18n="appt_reason_hint">Mínimo 10 caracteres.</span><span id="motivoContador">0/500</span></div>
                            </div>
                            <button type="submit" class="btn-primary" id="btnAgendar">
                                <i class="ph ph-calendar-check"></i><span data-i18n="btn_schedule">Agendar cita</span>
                            </button>
                        </form>
                    </div>
                </div>

                <div class="header-row" style="margin-top: 32px; margin-bottom: 16px;">
                    <div>
                        <h2 class="title" id="citasTitulo" style="font-size: 21px;" data-i18n="appts_list_title">Mis Citas</h2>
                        <p class="subtitle" data-i18n="appts_list_subtitle">Historial y próximas consultas de tus mascotas.</p>
                    </div>
                    <button type="button" class="collapse-btn" id="btnToggleCitas" aria-expanded="true" aria-controls="citasColapsable">
                        <i class="ph ph-caret-up"></i><span data-i18n="appts_hide">Ocultar historial</span>
                    </button>
                </div>
                <div class="collapsible" id="citasColapsable">
                    <div class="collapsible-inner">
                        <div class="panel">
                            <div class="citas-scroll" role="region" aria-labelledby="citasTitulo" tabindex="0">
                                <table class="vet-table">
                                    <thead>
                                        <tr>
                                            <th data-i18n="th_mascota">Mascota</th>
                                            <th data-i18n="th_sucursal_cita">Sucursal</th>
                                            <th data-i18n="th_fecha_hora">Fecha y hora</th>
                                            <th data-i18n="th_motivo">Motivo</th>
                                            <th data-i18n="th_estado">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody id="consultasTable">
                                        <tr><td colspan="5" style="text-align:center; padding: 40px; color:var(--ink-faint);" data-i18n="loading">Cargando información...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- VISTA: CONFIGURACIÓN -->
            <section id="view-config" class="view-section">
                <div class="header-row">
                    <div>
                        <h1 class="title" data-i18n="config_title">Configuración de Cuenta</h1>
                        <p class="subtitle" data-i18n="config_subtitle">Gestiona tu información personal y seguridad.</p>
                    </div>
                </div>
                <div class="panel" style="display: flex; gap: 40px; flex-wrap: wrap;">
                    
                    <div style="flex: 1; min-width: 300px;">
                        <h3 style="margin-bottom: 20px; font-size: 16px;" data-i18n="contact_title">Datos de Contacto</h3>
                        <div class="form-group">
                            <label data-i18n="name_label">Nombre Completo</label>
                            <input type="text" class="form-input" value="<?= htmlspecialchars($nombreCompleto) ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label data-i18n="email_label">Correo Electrónico</label>
                            <input type="text" class="form-input" value="<?= htmlspecialchars($correoUsuario) ?>" disabled>
                        </div>
                        <p style="font-size: 12px; color: var(--ink-faint);" data-i18n="contact_help">* Para cambiar tu correo o nombre, contacta a una sucursal.</p>
                    </div>

                    <div style="flex:  1; min-width: 300px;">
                        <h3 style="margin-bottom: 20px; font-size: 16px;" data-i18n="password_title">Cambiar Contraseña</h3>
                        <div class="form-group">
                            <label data-i18n="pass_actual_label">Contraseña de la cuenta</label>
                            <input type="password" class="form-input" value="••••••••••••" disabled>
                        </div>
                        <div style="margin-top: 20px; padding: 16px; background: var(--bg-app); border: 1px solid var(--border-soft); border-radius: 10px;">
                            <p style="font-size: 13.5px; color: var(--ink-soft); margin-bottom: 10px;" data-i18n="pass_help_text">¿Deseas cambiar o actualizar tu contraseña?</p>
                            <button type="button" id="btnSolicitarCambioPass" class="btn-primary" style="max-width: 100%; width: auto; margin: 0;">
                                <i class="ph ph-key" style="margin-right: 6px;"></i> <span data-i18n="btn_solicitar_pass">Cambiar Contraseña</span>
                            </button>
                        </div>
                    </div>

                </div>
            </section>

        </div>
    </main>
</div>

<script>
const endpoint = 'dashboard_usuario.php';
const $ = s => document.querySelector(s);
const escapeHtml = v => String(v ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
const money = v => '$' + Number(v || 0).toFixed(2);
const notify = (msg, icon='success') => Swal.fire({ toast:true, position:'top-end', icon, title: msg, showConfirmButton:false, timer:2600 });

const I18N = {
    es: {
        brand_sub: 'Panel de Cliente', nav_section_activity: 'Mi Actividad', nav_section_settings: 'Ajustes',
        nav_section_navigation: 'Navegación', nav_apartados: 'Mis Apartados', nav_config: 'Configuración',
        nav_catalog: 'Ir al Catálogo', nav_logout: 'Cerrar sesión', topbar_apartados: 'Mis Apartados',
        topbar_config: 'Configuración', theme_title: 'Modo Oscuro', role_chip: 'Cliente',
        apartados_title: 'Mis Productos Apartados', apartados_subtitle: 'Consulta los productos que has reservado y su sucursal de recolección.',
        th_producto: 'Producto', th_sucursal: 'Sucursal de Recolección', th_precio: 'Precio',
        th_fecha: 'Fecha de Reserva', th_estado: 'Estado', loading: 'Cargando información...',
        config_title: 'Configuración de Cuenta', config_subtitle: 'Gestiona tu información personal y seguridad.',
        contact_title: 'Datos de Contacto', name_label: 'Nombre Completo', email_label: 'Correo Electrónico',
        contact_help: '* Para cambiar tu correo o nombre, contacta a una sucursal.',
        password_title: 'Cambiar Contraseña', pass_actual_label: 'Contraseña de la cuenta',
        pass_help_text: '¿Deseas cambiar o actualizar tu contraseña?', btn_solicitar_pass: 'Cambiar Contraseña',
        empty_apartados: 'No tienes ningún producto apartado en este momento.',
        missing_apartados: 'Aún no has apartado ningún producto.', error_response: 'Error al procesar la respuesta del servidor.',
        error_request: 'No fue posible completar la operación.', redirecting_password: 'Redirigiendo a restablecimiento...',
        entregado: 'ENTREGADO', vencido: 'VENCIDO', cancelado: 'CANCELADO', pendiente: 'PENDIENTE',
        nav_consultas: 'Consultas Veterinarias', topbar_consultas: 'Consultas Veterinarias',
        consultas_title: 'Consultas Veterinarias', consultas_subtitle: 'Registra a tus mascotas y agenda su cita en la sucursal que prefieras.',
        pets_title: 'Mis Mascotas', pets_subtitle: 'Los pacientes vinculados a tu cuenta.',
        pets_empty: 'Aún no registras mascotas. Agrega la primera para poder agendar una cita.',
        pet_name_label: 'Nombre', pet_name_ph: 'Ej. Firulais', pet_species_label: 'Especie', select_placeholder: 'Selecciona...',
        sp_perro: 'Perro', sp_gato: 'Gato', sp_ave: 'Ave', sp_conejo: 'Conejo', sp_bovino: 'Bovino', sp_equino: 'Equino',
        sp_ovino: 'Ovino', sp_caprino: 'Caprino', sp_porcino: 'Porcino', sp_otro: 'Otro',
        btn_add_pet: 'Registrar mascota', appt_title: 'Agendar Cita',
        appt_subtitle: 'Horario de consulta: 09:00 a 18:30 h, en bloques de 30 minutos.',
        appt_pet_label: 'Mascota', appt_pet_empty: 'Primero registra una mascota', appt_branch_label: 'Sucursal',
        appt_branch_empty: 'No hay sucursales disponibles', appt_date_label: 'Fecha', appt_time_label: 'Hora',
        appt_time_empty: 'Sin horarios disponibles hoy', appt_time_full: 'Sin horarios libres en esta fecha',
        slot_ocupado: 'Ocupado',
        appt_slots_error: 'No se pudo consultar la disponibilidad. Se validará al agendar.',
        slot_movido: 'Las {hora} ya están ocupadas; te propusimos las {nueva}.',
        slot_sin_libres: 'Las {hora} ya están ocupadas y no quedan horarios libres en esta fecha.',
        pet_delete_aria: 'Eliminar a {nombre}', pet_pending_badge: '{n} cita(s) pendiente(s)',
        del_pet_title: '¿Eliminar a {nombre}?', del_pet_text: 'Dejará de aparecer en tu lista y no podrás agendarle nuevas citas.',
        del_pet_pending: 'Tiene {n} cita(s) pendiente(s) que se cancelarán y liberarán su horario.',
        del_pet_history: 'Si ya tiene consultas registradas, su historial se conservará.',
        del_pet_confirm: 'Sí, eliminar', del_pet_cancel: 'Cancelar',
        ok_del_title: 'Mascota eliminada', ok_del_text: '{nombre} se eliminó de tu cuenta.',
        ok_del_archived: '{nombre} se retiró de tu lista. Su historial de consultas se conservó.',
        ok_del_cancelled: 'Se cancelaron {n} cita(s) pendiente(s).',
        err_dup_title: 'Ya tiene una cita ese día', err_slot_title: 'Horario no disponible', err_btn: 'Entendido',
        appt_reason_label: 'Motivo de la consulta',
        appt_reason_ph: 'Describe síntomas, vacunas pendientes o el servicio que necesitas.',
        appt_reason_hint: 'Mínimo 10 caracteres.', btn_schedule: 'Agendar cita', saving: 'Guardando...',
        appts_list_title: 'Mis Citas', appts_list_subtitle: 'Historial y próximas consultas de tus mascotas.',
        appts_hide: 'Ocultar historial', appts_show: 'Mostrar historial',
        th_mascota: 'Mascota', th_sucursal_cita: 'Sucursal', th_fecha_hora: 'Fecha y hora', th_motivo: 'Motivo',
        appts_empty: 'Todavía no tienes citas agendadas.',
        st_pendiente: 'PENDIENTE', st_atendida: 'ATENDIDA', st_cancelada: 'CANCELADA',
        val_pet_name: 'Escribe el nombre de tu mascota.', val_pet_species: 'Selecciona la especie.',
        val_appt_pet: 'Selecciona la mascota.', val_appt_branch: 'Selecciona la sucursal.',
        val_appt_date: 'Elige una fecha y una hora disponibles.', val_appt_reason: 'Describe el motivo (mínimo 10 caracteres).',
        ok_pet_title: '¡Mascota registrada!', ok_pet_text: '{nombre} ya forma parte de tu manada. Ahora puedes agendarle una cita.',
        ok_appt_title: '¡Cita agendada!', ok_appt_btn: 'Entendido', lbl_folio: 'Folio', lbl_pet: 'Mascota',
        lbl_branch: 'Sucursal', lbl_when: 'Fecha', ok_appt_note: 'Te esperamos 10 minutos antes. Lleva la cartilla de vacunación.'
    },
    en: {
        brand_sub: 'Customer Dashboard', nav_section_activity: 'My Activity', nav_section_settings: 'Settings',
        nav_section_navigation: 'Navigation', nav_apartados: 'My Reservations', nav_config: 'Settings',
        nav_catalog: 'Go to Catalog', nav_logout: 'Log out', topbar_apartados: 'My Reservations',
        topbar_config: 'Settings', theme_title: 'Dark Mode', role_chip: 'Customer',
        apartados_title: 'My Reserved Products', apartados_subtitle: 'Review the products you have reserved and their pickup branch.',
        th_producto: 'Product', th_sucursal: 'Pickup Branch', th_precio: 'Price',
        th_fecha: 'Reservation Date', th_estado: 'Status', loading: 'Loading information...',
        config_title: 'Account Settings', config_subtitle: 'Manage your personal information and security.',
        contact_title: 'Contact Information', name_label: 'Full Name', email_label: 'Email Address',
        contact_help: '* To change your email or name, contact a branch.',
        password_title: 'Change Password', pass_actual_label: 'Account password',
        pass_help_text: 'Would you like to change or update your password?', btn_solicitar_pass: 'Change Password',
        empty_apartados: 'You do not have any reserved products at this time.',
        missing_apartados: 'You have not reserved any products yet.', error_response: 'Error processing the server response.',
        error_request: 'Unable to complete the operation.', redirecting_password: 'Redirecting to password reset...',
        entregado: 'DELIVERED', vencido: 'EXPIRED', cancelado: 'CANCELED', pendiente: 'PENDING',
        nav_consultas: 'Vet Appointments', topbar_consultas: 'Vet Appointments',
        consultas_title: 'Vet Appointments', consultas_subtitle: 'Register your pets and book an appointment at your preferred branch.',
        pets_title: 'My Pets', pets_subtitle: 'Patients linked to your account.',
        pets_empty: 'You have not registered any pets yet. Add your first one to book an appointment.',
        pet_name_label: 'Name', pet_name_ph: 'e.g. Buddy', pet_species_label: 'Species', select_placeholder: 'Select...',
        sp_perro: 'Dog', sp_gato: 'Cat', sp_ave: 'Bird', sp_conejo: 'Rabbit', sp_bovino: 'Cattle', sp_equino: 'Horse',
        sp_ovino: 'Sheep', sp_caprino: 'Goat', sp_porcino: 'Pig', sp_otro: 'Other',
        btn_add_pet: 'Register pet', appt_title: 'Book Appointment',
        appt_subtitle: 'Consultation hours: 09:00 to 18:30, in 30-minute slots.',
        appt_pet_label: 'Pet', appt_pet_empty: 'Register a pet first', appt_branch_label: 'Branch',
        appt_branch_empty: 'No branches available', appt_date_label: 'Date', appt_time_label: 'Time',
        appt_time_empty: 'No slots left today', appt_time_full: 'No free slots on this date',
        slot_ocupado: 'Booked',
        appt_slots_error: 'Availability could not be checked. It will be validated on booking.',
        slot_movido: '{hora} is already booked; we suggested {nueva}.',
        slot_sin_libres: '{hora} is already booked and there are no free slots left on this date.',
        pet_delete_aria: 'Remove {nombre}', pet_pending_badge: '{n} pending appointment(s)',
        del_pet_title: 'Remove {nombre}?', del_pet_text: 'It will no longer appear in your list and you will not be able to book new appointments for it.',
        del_pet_pending: 'It has {n} pending appointment(s) that will be canceled, freeing their slots.',
        del_pet_history: 'If it already has recorded visits, its history will be kept.',
        del_pet_confirm: 'Yes, remove', del_pet_cancel: 'Cancel',
        ok_del_title: 'Pet removed', ok_del_text: '{nombre} was removed from your account.',
        ok_del_archived: '{nombre} was removed from your list. Its visit history was kept.',
        ok_del_cancelled: '{n} pending appointment(s) were canceled.',
        err_dup_title: 'Already booked that day', err_slot_title: 'Slot not available', err_btn: 'Got it',
        appt_reason_label: 'Reason for the visit',
        appt_reason_ph: 'Describe symptoms, pending vaccines or the service you need.',
        appt_reason_hint: 'At least 10 characters.', btn_schedule: 'Book appointment', saving: 'Saving...',
        appts_list_title: 'My Appointments', appts_list_subtitle: 'History and upcoming visits for your pets.',
        appts_hide: 'Hide history', appts_show: 'Show history',
        th_mascota: 'Pet', th_sucursal_cita: 'Branch', th_fecha_hora: 'Date & time', th_motivo: 'Reason',
        appts_empty: 'You have no appointments yet.',
        st_pendiente: 'PENDING', st_atendida: 'ATTENDED', st_cancelada: 'CANCELED',
        val_pet_name: 'Enter your pet\'s name.', val_pet_species: 'Select the species.',
        val_appt_pet: 'Select the pet.', val_appt_branch: 'Select the branch.',
        val_appt_date: 'Choose an available date and time.', val_appt_reason: 'Describe the reason (at least 10 characters).',
        ok_pet_title: 'Pet registered!', ok_pet_text: '{nombre} is now part of your pack. You can book an appointment now.',
        ok_appt_title: 'Appointment booked!', ok_appt_btn: 'Got it', lbl_folio: 'Folio', lbl_pet: 'Pet',
        lbl_branch: 'Branch', lbl_when: 'Date', ok_appt_note: 'Please arrive 10 minutes early and bring the vaccination record.'
    }
};
let currentLang = localStorage.getItem('kion-user-lang') === 'en' ? 'en' : 'es';
function t(key) {
    return (I18N[currentLang] && I18N[currentLang][key]) || I18N.es[key] || key;
}
function applyLanguage() {
    document.documentElement.lang = currentLang;
    document.querySelectorAll('[data-i18n]').forEach(el => { el.textContent = t(el.dataset.i18n); });
    document.querySelectorAll('[data-i18n-title]').forEach(el => { el.title = t(el.dataset.i18nTitle); });
    document.querySelectorAll('[data-i18n-placeholder]').forEach(el => { el.placeholder = t(el.dataset.i18nPlaceholder); });
}

const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

async function request(actionStr, options = {}) {
    options.headers = { 'X-CSRF-Token': csrfToken, ...(options.headers || {}) };
    const r = await fetch(`${endpoint}?action=${actionStr}`, options);
    const text = await r.text();
    let data;
    try { data = JSON.parse(text); } catch(e) { throw new Error(t('error_response')); }
    if (!data.ok) throw Object.assign(new Error(data.msg || t('error_request')), { code: data.code || null });
    return data;
}

// -------- TEMA --------
const body = document.body, btnTheme = $('#btnTheme');
if (localStorage.getItem('kion-user-theme') === 'dark') {
    body.classList.add('dark-mode');
    btnTheme.querySelector('i').classList.replace('ph-moon', 'ph-sun');
}
btnTheme.addEventListener('click', () => {
    body.classList.toggle('dark-mode');
    const icon = btnTheme.querySelector('i');
    icon.classList.toggle('ph-moon'); icon.classList.toggle('ph-sun');
    localStorage.setItem('kion-user-theme', body.classList.contains('dark-mode') ? 'dark' : 'light');
});

$('#btnLang').addEventListener('click', () => {
    currentLang = currentLang === 'es' ? 'en' : 'es';
    localStorage.setItem('kion-user-lang', currentLang);
    applyLanguage();
    const activeView = document.querySelector('.view-section.active');
    if (activeView) switchView(activeView.id);
});

// -------- SIDEBAR --------
const appLayout = $('#appLayout');
$('#btnCollapse').addEventListener('click', () => appLayout.classList.toggle('is-collapsed'));
$('#btnMobileMenu').addEventListener('click', () => appLayout.classList.add('mobile-open'));
$('#sidebarScrim').addEventListener('click', () => appLayout.classList.remove('mobile-open'));

const navButtons = document.querySelectorAll('.nav-btn[data-target]');
const views = document.querySelectorAll('.view-section');

function switchView(targetId) {
    const targetView = document.getElementById(targetId);
    if (!targetView) return;
    views.forEach(v => v.classList.remove('active'));
    navButtons.forEach(b => b.classList.remove('active'));
    targetView.classList.add('active');
    const btn = document.querySelector(`.nav-btn[data-target="${targetId}"]`);
    if (btn) btn.classList.add('active');
    appLayout.classList.remove('mobile-open');
    const titulos = { 'view-config': 'topbar_config', 'view-consultas': 'topbar_consultas' };
    $('#topbarTitle').textContent = t(titulos[targetId] || 'topbar_apartados');

    if (targetId === 'view-apartados') loadApartados();
    if (targetId === 'view-consultas') loadConsultasModule();
}
navButtons.forEach(btn => btn.addEventListener('click', e => switchView(e.currentTarget.dataset.target)));

applyLanguage();

// -------- MIS CITAS: COLAPSAR / DESPLEGAR --------
const btnToggleCitas = $('#btnToggleCitas');
const citasColapsable = $('#citasColapsable');

function aplicarCitasColapsadas(colapsada) {
    citasColapsable.classList.toggle('is-collapsed', colapsada);
    btnToggleCitas.setAttribute('aria-expanded', String(!colapsada));
    const etiqueta = btnToggleCitas.querySelector('span');
    etiqueta.dataset.i18n = colapsada ? 'appts_show' : 'appts_hide';
    etiqueta.textContent = t(etiqueta.dataset.i18n);
}

aplicarCitasColapsadas(localStorage.getItem('kion-user-citas-colapsadas') === '1');
btnToggleCitas.addEventListener('click', () => {
    const colapsada = !citasColapsable.classList.contains('is-collapsed');
    aplicarCitasColapsadas(colapsada);
    localStorage.setItem('kion-user-citas-colapsadas', colapsada ? '1' : '0');
});

// -------- APARTADOS --------
async function loadApartados() {
    const table = $('#apartadosTable');
    try {
        const { data, msg } = await request('get_apartados');
        if (msg) {
            table.innerHTML = `<tr><td colspan="5" style="text-align:center; padding: 30px; color:var(--ink-faint);">${escapeHtml(t('missing_apartados'))}</td></tr>`;
            return;
        }

        table.innerHTML = data.length ? data.map(x => {
            let badgeClass = 'b-sky';
            if(x.estado === 'ENTREGADO') badgeClass = 'b-green';
            if(x.estado === 'VENCIDO' || x.estado === 'CANCELADO') badgeClass = 'b-yellow';

            const estadoKey = x.estado === 'ENTREGADO' ? 'entregado'
                : x.estado === 'VENCIDO' ? 'vencido'
                : x.estado === 'CANCELADO' ? 'cancelado'
                : null;
            const estado = estadoKey ? t(estadoKey) : (x.estado || t('pendiente'));
            const fecha = x.fecha_reserva ? new Date(x.fecha_reserva).toLocaleDateString(currentLang === 'es' ? 'es-MX' : 'en-US') : '-';
            return `<tr>
                <td><b>${escapeHtml(x.producto)}</b></td>
                <td><i class="ph ph-storefront" style="vertical-align: middle; margin-right: 4px;"></i> ${escapeHtml(x.sucursal)}</td>
                <td><b>${escapeHtml(money(x.precio))}</b></td>
                <td>${escapeHtml(fecha)}</td>
                <td><span class="badge ${badgeClass}">${escapeHtml(estado)}</span></td>
            </tr>`;
        }).join('') : `<tr><td colspan="5" style="text-align:center; padding: 40px; color:var(--ink-faint);">${escapeHtml(t('empty_apartados'))}</td></tr>`;
    } catch (e) {
        table.innerHTML = `<tr><td colspan="5" style="text-align:center; padding: 30px; color:var(--danger);">${escapeHtml(e.message)}</td></tr>`;
    }
}

$('#btnSolicitarCambioPass').addEventListener('click', async () => {
    try {
        const data = await request('solicitar_cambio_password');
        notify(t('redirecting_password'));
        window.location.href = data.redirect;
    } catch (err) {
        notify(err.message, 'error');
    }
});

// -------- CONSULTAS VETERINARIAS --------
const ZONA_CLINICAS = 'America/Mexico_City';
const HORA_INICIO = '09:00', HORA_FIN = '18:30', DIAS_MAXIMOS = 60, MOTIVO_MIN = 10, MOTIVO_MAX = 500;
const ICONOS_ESPECIE = { Perro: 'ph-dog', Gato: 'ph-cat', Ave: 'ph-bird', Conejo: 'ph-rabbit', Bovino: 'ph-cow', Equino: 'ph-horse' };
const ESTADOS_CONSULTA = { PENDIENTE: ['b-sky', 'st_pendiente'], ATENDIDA: ['b-green', 'st_atendida'], CANCELADA: ['b-red', 'st_cancelada'] };

const formMascota = $('#formMascota');
const formConsulta = $('#formConsulta');
const selMascota = $('#consultaMascota');
const selSucursal = $('#consultaSucursal');
const inputFecha = $('#consultaFecha');
const selHora = $('#consultaHora');
const txtMotivo = $('#consultaMotivo');
let mascotas = [];

const especieTexto = especie => t('sp_' + String(especie || 'otro').toLowerCase());
const iconoEspecie = especie => ICONOS_ESPECIE[especie] || 'ph-paw-print';
const localeActual = () => (currentLang === 'es' ? 'es-MX' : 'en-US');

// Fecha y hora "de reloj" en la zona de las clínicas, sin depender de la del navegador.
function ahoraEnClinica() {
    const partes = Object.fromEntries(new Intl.DateTimeFormat('en-CA', {
        timeZone: ZONA_CLINICAS, year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', hourCycle: 'h23'
    }).formatToParts(new Date()).map(p => [p.type, p.value]));
    return { fecha: `${partes.year}-${partes.month}-${partes.day}`, hora: `${partes.hour}:${partes.minute}` };
}

function sumarDias(fechaIso, dias) {
    const d = new Date(`${fechaIso}T12:00:00`);
    d.setDate(d.getDate() + dias);
    return d.toISOString().slice(0, 10);
}

function formatearFechaHora(valor) {
    const d = new Date(String(valor).replace(' ', 'T'));
    return isNaN(d) ? valor : d.toLocaleString(localeActual(), { dateStyle: 'medium', timeStyle: 'short' });
}

function swalTema() {
    const cs = getComputedStyle(document.body);
    return {
        background: cs.getPropertyValue('--bg-surface').trim(),
        color: cs.getPropertyValue('--ink').trim(),
        confirmButtonColor: 'oklch(36% 0.045 50)'
    };
}

function setCargando(boton, cargando) {
    const etiqueta = boton.querySelector('span');
    if (cargando) {
        boton.dataset.textoOriginal = etiqueta.dataset.i18n;
        etiqueta.textContent = t('saving');
    } else if (boton.dataset.textoOriginal) {
        etiqueta.textContent = t(boton.dataset.textoOriginal);
    }
    boton.disabled = cargando;
}

// Marca las horas ocupadas sobre las opciones existentes (sin reconstruir la lista si no cambió),
// así la hora elegida se respeta. Solo se reemplaza si quedó ocupada o ya pasó;
// en ese caso devuelve { hora, nueva } para poder avisar al usuario.
function pintarHorarios(ocupados = new Set()) {
    const { fecha: hoy, hora: horaActual } = ahoraEnClinica();
    const elegida = selHora.value;
    const slots = [];
    for (let m = 9 * 60; m <= 18 * 60 + 30; m += 30) {
        const hhmm = `${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`;
        if (inputFecha.value === hoy && hhmm <= horaActual) continue;
        slots.push(hhmm);
    }
    const libres = slots.filter(h => !ocupados.has(h));

    const valoresActuales = [...selHora.options].map(o => o.value).filter(Boolean);
    if (valoresActuales.join() !== slots.join()) {
        selHora.innerHTML = slots.map(h => `<option value="${h}">${h}</option>`).join('');
    }

    const textoAviso = !slots.length ? t('appt_time_empty') : !libres.length ? t('appt_time_full') : '';
    let aviso = selHora.querySelector('option[value=""]');
    if (textoAviso) {
        if (!aviso) { aviso = new Option('', ''); selHora.prepend(aviso); }
        aviso.textContent = textoAviso;
    } else if (aviso) {
        aviso.remove();
    }

    for (const opcion of selHora.options) {
        if (!opcion.value) continue;
        const ocupada = ocupados.has(opcion.value);
        opcion.disabled = ocupada;
        opcion.textContent = ocupada ? `${opcion.value} · ${t('slot_ocupado')}` : opcion.value;
    }

    if (libres.includes(elegida)) {
        selHora.value = elegida;
        return null;
    }
    const nueva = libres.find(h => h > elegida) || libres[libres.length - 1] || '';
    selHora.value = nueva;
    return elegida && ocupados.has(elegida) ? { hora: elegida, nueva } : null;
}

let peticionHorarios = 0;

async function actualizarHorariosOcupados({ avisar = true } = {}) {
    const idSucursal = selSucursal.value;
    const fecha = inputFecha.value;
    const folio = ++peticionHorarios;
    if (!idSucursal || !fecha) {
        selHora.classList.remove('is-loading');
        pintarHorarios();
        return;
    }

    selHora.classList.add('is-loading');
    selHora.setAttribute('aria-busy', 'true');
    let ocupados = new Set();
    let fallo = false;
    try {
        const params = new URLSearchParams({ id_sucursal: idSucursal, fecha });
        const { data } = await request(`obtener_horarios_ocupados&${params}`);
        ocupados = new Set(data.ocupados);
    } catch (e) {
        fallo = true;
    }
    if (folio !== peticionHorarios) return;

    selHora.classList.remove('is-loading');
    selHora.removeAttribute('aria-busy');
    const reemplazo = pintarHorarios(ocupados);
    if (fallo) {
        notify(t('appt_slots_error'), 'warning');
    } else if (reemplazo && avisar) {
        const plantilla = reemplazo.nueva ? t('slot_movido') : t('slot_sin_libres');
        notify(plantilla.replace('{hora}', reemplazo.hora).replace('{nueva}', reemplazo.nueva), 'warning');
    }
}

function configurarFechas() {
    const { fecha: hoy, hora } = ahoraEnClinica();
    // Pasada la última hora de consulta, el primer día disponible es mañana.
    const primerDia = hora >= HORA_FIN ? sumarDias(hoy, 1) : hoy;
    inputFecha.min = primerDia;
    inputFecha.max = sumarDias(hoy, DIAS_MAXIMOS);
    if (!inputFecha.value || inputFecha.value < primerDia || inputFecha.value > inputFecha.max) {
        inputFecha.value = primerDia;
    }
    actualizarHorariosOcupados();
}

function renderMascotas() {
    const lista = $('#petList');
    lista.innerHTML = mascotas.length ? mascotas.map(m => {
        const pendientes = Number(m.citas_pendientes) || 0;
        const etiquetaBorrar = escapeHtml(t('pet_delete_aria').replace('{nombre}', m.nombre));
        return `
        <div class="pet-card">
            <span class="pet-avatar"><i class="ph-fill ${iconoEspecie(m.especie)}"></i></span>
            <div class="pet-info">
                <div class="pet-name">${escapeHtml(m.nombre)}</div>
                <div class="pet-species">${escapeHtml(especieTexto(m.especie))}</div>
                ${pendientes ? `<span class="pet-pending"><i class="ph ph-calendar-check"></i> ${escapeHtml(t('pet_pending_badge').replace('{n}', pendientes))}</span>` : ''}
            </div>
            <button type="button" class="pet-delete" data-id="${escapeHtml(m.id_mascota)}" aria-label="${etiquetaBorrar}" title="${etiquetaBorrar}">
                <i class="ph ph-trash"></i>
            </button>
        </div>`;
    }).join('')
        : `<div class="vet-empty"><i class="ph ph-paw-print"></i>${escapeHtml(t('pets_empty'))}</div>`;

    const seleccionPrevia = selMascota.value;
    selMascota.innerHTML = mascotas.length
        ? `<option value="">${escapeHtml(t('select_placeholder'))}</option>` + mascotas.map(m =>
            `<option value="${m.id_mascota}">${escapeHtml(m.nombre)} · ${escapeHtml(especieTexto(m.especie))}</option>`).join('')
        : `<option value="">${escapeHtml(t('appt_pet_empty'))}</option>`;
    if (mascotas.some(m => String(m.id_mascota) === seleccionPrevia)) selMascota.value = seleccionPrevia;
    else if (mascotas.length === 1) selMascota.value = mascotas[0].id_mascota;
}

async function loadMascotas() {
    const { data } = await request('get_mascotas');
    mascotas = data;
    renderMascotas();
}

async function loadSucursales() {
    const { data } = await request('get_sucursales');
    const seleccionPrevia = selSucursal.value;
    selSucursal.innerHTML = data.length
        ? `<option value="">${escapeHtml(t('select_placeholder'))}</option>` + data.map(s =>
            `<option value="${s.id_sucursal}">${escapeHtml(s.nombre)} — ${escapeHtml(s.direccion)}</option>`).join('')
        : `<option value="">${escapeHtml(t('appt_branch_empty'))}</option>`;
    if (data.some(s => String(s.id_sucursal) === seleccionPrevia)) selSucursal.value = seleccionPrevia;
}

async function loadConsultas() {
    const tabla = $('#consultasTable');
    try {
        const { data } = await request('get_consultas');
        tabla.innerHTML = data.length ? data.map(c => {
            const [clase, clave] = ESTADOS_CONSULTA[c.estado] || ESTADOS_CONSULTA.PENDIENTE;
            return `<tr class="${c.estado === 'CANCELADA' ? 'is-cancelada' : ''}">
                <td><b><i class="ph-fill ${iconoEspecie(c.especie)}" style="color:var(--coffee-main); margin-right:6px; vertical-align:middle;"></i>${escapeHtml(c.mascota)}</b></td>
                <td><i class="ph ph-storefront" style="vertical-align: middle; margin-right: 4px;"></i> ${escapeHtml(c.sucursal)}</td>
                <td>${escapeHtml(formatearFechaHora(c.fecha_hora))}</td>
                <td><div class="motivo-cell" title="${escapeHtml(c.motivo)}">${escapeHtml(c.motivo)}</div></td>
                <td><span class="badge ${clase}">${escapeHtml(t(clave))}</span></td>
            </tr>`;
        }).join('') : `<tr><td colspan="5" style="text-align:center; padding: 40px; color:var(--ink-faint);">${escapeHtml(t('appts_empty'))}</td></tr>`;
    } catch (e) {
        tabla.innerHTML = `<tr><td colspan="5" style="text-align:center; padding: 30px; color:var(--danger);">${escapeHtml(e.message)}</td></tr>`;
    }
}

async function loadConsultasModule() {
    configurarFechas();
    try {
        await Promise.all([loadMascotas(), loadSucursales()]);
    } catch (e) {
        notify(e.message, 'error');
    }
    loadConsultas();
}

function actualizarContadorMotivo() {
    const largo = txtMotivo.value.trim().length;
    $('#motivoContador').textContent = `${txtMotivo.value.length}/${MOTIVO_MAX}`;
    $('#motivoHint').classList.toggle('is-limit', largo > 0 && largo < MOTIVO_MIN);
}

inputFecha.addEventListener('change', () => actualizarHorariosOcupados());
selSucursal.addEventListener('change', () => actualizarHorariosOcupados());
txtMotivo.addEventListener('input', actualizarContadorMotivo);

$('#petList').addEventListener('click', async e => {
    const boton = e.target.closest('.pet-delete');
    if (!boton) return;
    const mascota = mascotas.find(m => String(m.id_mascota) === boton.dataset.id);
    if (!mascota) return;
    const pendientes = Number(mascota.citas_pendientes) || 0;

    const { isConfirmed } = await Swal.fire({
        ...swalTema(),
        icon: 'warning',
        titleText: t('del_pet_title').replace('{nombre}', mascota.nombre),
        html: `<p>${escapeHtml(t('del_pet_text'))}</p>`
            + (pendientes ? `<p style="margin-top:10px;"><b>${escapeHtml(t('del_pet_pending').replace('{n}', pendientes))}</b></p>` : '')
            + `<p style="margin-top:10px; font-size:13px; opacity:.75;">${escapeHtml(t('del_pet_history'))}</p>`,
        showCancelButton: true,
        confirmButtonText: t('del_pet_confirm'),
        cancelButtonText: t('del_pet_cancel'),
        confirmButtonColor: getComputedStyle(document.body).getPropertyValue('--danger').trim(),
        reverseButtons: true,
        focusCancel: true
    });
    if (!isConfirmed) return;

    boton.disabled = true;
    try {
        const fd = new FormData();
        fd.append('id_mascota', mascota.id_mascota);
        const { data } = await request('eliminar_mascota', { method: 'POST', body: fd });
        mascotas = mascotas.filter(m => String(m.id_mascota) !== String(data.id_mascota));
        renderMascotas();
        if (data.citas_canceladas > 0) {
            loadConsultas();
            actualizarHorariosOcupados({ avisar: false });
        }
        const texto = t(data.modo === 'archivada' ? 'ok_del_archived' : 'ok_del_text').replace('{nombre}', data.nombre)
            + (data.citas_canceladas > 0 ? ' ' + t('ok_del_cancelled').replace('{n}', data.citas_canceladas) : '');
        Swal.fire({
            ...swalTema(),
            icon: 'success',
            title: t('ok_del_title'),
            text: texto,
            timer: 3600,
            timerProgressBar: true,
            showConfirmButton: false
        });
    } catch (err) {
        boton.disabled = false;
        notify(err.message, 'error');
    }
});

formMascota.addEventListener('submit', async e => {
    e.preventDefault();
    const nombre = $('#mascotaNombre').value.trim();
    const especie = $('#mascotaEspecie').value;
    if (!nombre) return notify(t('val_pet_name'), 'warning');
    if (!especie) return notify(t('val_pet_species'), 'warning');

    const boton = $('#btnGuardarMascota');
    setCargando(boton, true);
    try {
        const { data } = await request('create_mascota', { method: 'POST', body: new FormData(formMascota) });
        mascotas.push(data);
        mascotas.sort((a, b) => a.nombre.localeCompare(b.nombre, localeActual()));
        renderMascotas();
        selMascota.value = data.id_mascota;
        formMascota.reset();
        Swal.fire({
            ...swalTema(),
            icon: 'success',
            title: t('ok_pet_title'),
            text: t('ok_pet_text').replace('{nombre}', data.nombre),
            timer: 3200,
            timerProgressBar: true,
            showConfirmButton: false
        });
    } catch (err) {
        notify(err.message, 'error');
    } finally {
        setCargando(boton, false);
    }
});

formConsulta.addEventListener('submit', async e => {
    e.preventDefault();
    if (!selMascota.value) return notify(t('val_appt_pet'), 'warning');
    if (!selSucursal.value) return notify(t('val_appt_branch'), 'warning');
    if (!inputFecha.value || !selHora.value) return notify(t('val_appt_date'), 'warning');
    if (txtMotivo.value.trim().length < MOTIVO_MIN) return notify(t('val_appt_reason'), 'warning');

    const boton = $('#btnAgendar');
    setCargando(boton, true);
    try {
        const { data } = await request('create_consulta', { method: 'POST', body: new FormData(formConsulta) });
        formConsulta.reset();
        configurarFechas();
        loadMascotas().catch(() => renderMascotas());
        actualizarContadorMotivo();
        loadConsultas();
        Swal.fire({
            ...swalTema(),
            icon: 'success',
            title: t('ok_appt_title'),
            html: `<div class="vet-resumen">
                <div><span>${escapeHtml(t('lbl_folio'))}</span><b>${escapeHtml(data.folio)}</b></div>
                <div><span>${escapeHtml(t('lbl_pet'))}</span>${escapeHtml(data.mascota)}</div>
                <div><span>${escapeHtml(t('lbl_branch'))}</span>${escapeHtml(data.sucursal)}</div>
                <div><span>${escapeHtml(t('lbl_when'))}</span>${escapeHtml(formatearFechaHora(data.fecha_hora))}</div>
                <p style="margin-top:8px; font-size:13px; opacity:.75;">${escapeHtml(t('ok_appt_note'))}</p>
            </div>`,
            confirmButtonText: t('ok_appt_btn')
        });
    } catch (err) {
        const titulosConflicto = { CITA_DUPLICADA: 'err_dup_title', HORARIO_OCUPADO: 'err_slot_title', MASCOTA_OCUPADA: 'err_slot_title' };
        if (titulosConflicto[err.code]) {
            Swal.fire({
                ...swalTema(),
                icon: 'warning',
                title: t(titulosConflicto[err.code]),
                text: err.message,
                confirmButtonText: t('err_btn')
            });
            if (err.code !== 'CITA_DUPLICADA') actualizarHorariosOcupados({ avisar: false });
        } else {
            notify(err.message, 'error');
        }
    } finally {
        setCargando(boton, false);
    }
});

// Inicializar
document.addEventListener('DOMContentLoaded', () => {
    loadApartados();
});
</script>
</body>
</html>