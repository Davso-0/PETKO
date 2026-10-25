<?php
declare(strict_types=1);

require_once __DIR__ . '/src/php/config/conexion_BD.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$usuarioId = $_SESSION['usuario']['id_usuario'] ?? null;
if ($usuarioId !== null && $pdo !== null) {
    $stmt = $pdo->prepare('UPDATE usuarios SET remember_token = NULL, remember_expires_at = NULL WHERE id_usuario = ?');
    $stmt->execute([$usuarioId]);
}

$_SESSION = [];

if (isset($_COOKIE['kion_remember'])) {
    setcookie('kion_remember', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE['kion_remember']);
}

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

header("Location: inicioSesion.php");
exit;

