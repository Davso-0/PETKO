<?php
declare(strict_types=1);

/* =====================================================
   SEGURIDAD: SESIÓN
===================================================== */
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');   // rechaza IDs de sesión inventados
    ini_set('session.use_only_cookies', '1');  // nunca aceptar el ID por la URL
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,   // solo por HTTPS cuando el sitio lo use
        'httponly' => true,     // JavaScript no puede leer la cookie
        'samesite' => 'Lax',    // protege contra peticiones desde otros sitios
    ]);
    session_start();
}

/* =====================================================
   CERRAR SESIÓN (integrado en este archivo: POST + token CSRF)
===================================================== */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['accion'] ?? '') === 'cerrar_sesion') {
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || empty($_SESSION['csrf']) || !hash_equals((string)$_SESSION['csrf'], $token)) {
        http_response_code(403);
        exit('Solicitud no válida. Regresa al inicio e inténtalo de nuevo.');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => 'Lax',
        ]);
    }
    session_destroy();
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Location: ' . basename((string)($_SERVER['SCRIPT_NAME'] ?? 'home.php')));
    exit;
}

// Cierre de sesión por inactividad (30 minutos)
const PETKO_IDLE_SECONDS = 1800;
if (isset($_SESSION['usuario'])) {
    if (isset($_SESSION['ultima_actividad']) && (time() - (int)$_SESSION['ultima_actividad']) > PETKO_IDLE_SECONDS) {
        unset($_SESSION['usuario'], $_SESSION['csrf'], $_SESSION['ultima_actividad']);
        session_regenerate_id(true);
    } else {
        $_SESSION['ultima_actividad'] = time();
    }
}

// Token CSRF para el botón de cerrar sesión
if (isset($_SESSION['usuario']) && empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

/* =====================================================
   SEGURIDAD: CABECERAS HTTP
===================================================== */
$nonce = base64_encode(random_bytes(16));

header_remove('X-Powered-By');
// Solo se ejecutan scripts y estilos marcados con el nonce de esta carga.
// (style-src-attr permite los estilos en línea de las fotos; Google Fonts es el único sitio externo permitido)
header("Content-Security-Policy: default-src 'self'; script-src 'nonce-$nonce'; style-src 'nonce-$nonce' https://fonts.googleapis.com; style-src-attr 'unsafe-inline'; img-src 'self' data:; font-src 'self' https://fonts.gstatic.com; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
header('Cross-Origin-Opener-Policy: same-origin');
if ($https) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

/* =====================================================
   DATOS DE LA VISTA
===================================================== */
function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$usuario  = $_SESSION['usuario'] ?? null;
$logueado = is_array($usuario) && !empty($usuario['nombre']);
$nombre   = $logueado ? (string)$usuario['nombre'] : '';
$csrf     = (string)($_SESSION['csrf'] ?? '');

// Ruta del panel según el rol (cliente por defecto)
$rutaDashboard = 'dashboard_usuario.php';
if ($logueado) {
    $rolSesion = mb_strtolower((string)($usuario['rol'] ?? ''), 'UTF-8');
    if (strpos($rolSesion, 'admin') === 0) {
        $rutaDashboard = 'dashboard.php';
    } elseif (strpos($rolSesion, 'gerente') !== false) {
        $rutaDashboard = 'dashboard_gerente.php';
    }
}
// Los botones "Comenzar" llevan al panel si ya inició sesión, o al login si no
$ctaHref = $logueado ? $rutaDashboard : '../../../../inicioSesion.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PETKO | Gestión para Veterinarias</title>
<style nonce="<?= e($nonce) ?>">

:root{--bg:#f3f8ff;--bg2:#fff;--card:#fff;--ink:#0d2548;--muted:#5b7396;--line:#d9e6f7;--acc:#2f80ed;--acc2:#5cc8ff}
body.dark-mode{--bg:#071225;--bg2:#040b18;--card:#0b1d3a;--ink:#eaf3ff;--muted:#a3b8d6;--line:#16305a}
*{margin:0;padding:0;box-sizing:border-box}html{scroll-behavior:smooth}
body{font-family:'Inter','Segoe UI',Arial,sans-serif;background:var(--bg2);color:var(--ink);overflow-x:hidden;transition:background .3s,color .3s}
a{text-decoration:none}

/* NAVBAR */
.navbar{width:100%;height:82px;display:flex;align-items:center;justify-content:space-between;padding:0 6.5%;background:rgba(8,18,38,.96);border-bottom:1px solid rgba(124,196,255,.16);backdrop-filter:blur(18px);position:sticky;top:0;z-index:1000}
.logo{font-size:30px;font-weight:800;letter-spacing:3px;color:#f2f8ff}.logo span{color:#5cc8ff}
.menu{display:flex;align-items:center;gap:clamp(12px,1.7vw,27px)}
.menu a{color:#d6e6fb;font-size:13px;transition:color .25s}.menu a:hover{color:#5cc8ff}
.menu .btn-login{background:#5cc8ff;color:#04142e;border-radius:999px;padding:12px 19px;font-weight:800;transition:.25s}
.menu .btn-login:hover{background:#9adcff;color:#04142e;transform:translateY(-2px)}
.dark-mode-btn,.language-btn{border:1px solid rgba(200,225,255,.3);background:transparent;color:#f2f8ff;height:42px;cursor:pointer;font-weight:700;transition:.3s}
.dark-mode-btn{width:42px;border-radius:50%;font-size:18px;display:flex;align-items:center;justify-content:center}
.language-btn{min-width:42px;padding:0 10px;border-radius:21px;font-size:12px}
.dark-mode-btn:hover,.language-btn:hover{background:#5cc8ff;color:#04142e;border-color:#5cc8ff}
body.dark-mode .navbar{background:rgba(2,6,14,.97)}
body.dark-mode .ps-wrap{background:#030710}
body.dark-mode .ps-sticky{background:linear-gradient(120deg,#030710,#071430 60%,#02050c)}

/* SECCIONES */
.sec{padding:110px 7%;scroll-margin-top:82px;background:var(--bg2)}.alt{background:var(--bg)}.c{text-align:center}
.label{color:var(--acc);font-size:13px;font-weight:800;letter-spacing:2px;margin-bottom:14px}
.title{font-size:clamp(32px,4vw,54px);line-height:1.08;letter-spacing:-.045em;margin-bottom:18px}
.desc{max-width:680px;margin:0 auto;color:var(--muted);line-height:1.8;font-size:17px}
.intro{text-align:center;padding:40px 7% 70px;color:var(--muted);font-size:15px;background:var(--bg2)}.intro strong{color:var(--acc)}
.grid{display:grid;gap:20px;margin-top:56px;text-align:left}.g4{grid-template-columns:repeat(4,1fr)}.g3{grid-template-columns:repeat(3,1fr)}.g6{grid-template-columns:repeat(6,1fr)}
.card{background:var(--card);border:1px solid var(--line);border-radius:22px;padding:28px;transition:.3s}
.card:hover{transform:translateY(-7px);border-color:var(--acc2);box-shadow:0 22px 48px rgba(30,90,200,.14)}
.card h3{margin-bottom:10px}.card p{color:var(--muted);font-size:14px;line-height:1.6}
.ico{width:54px;height:54px;border-radius:16px;background:rgba(92,200,255,.18);display:flex;align-items:center;justify-content:center;font-size:25px;margin-bottom:20px}
.split{display:flex;align-items:center;gap:80px}.split>div{flex:1}
.split h2{font-size:clamp(30px,3.6vw,46px);line-height:1.12;letter-spacing:-.04em;margin-bottom:20px}
.split p{color:var(--muted);font-size:17px;line-height:1.8;margin-bottom:24px}
.check{list-style:none}.check li{margin:14px 0;font-size:15px}.check li:before{content:'✓  ';color:var(--acc);font-weight:800}
.panel{min-height:360px;border-radius:26px;display:flex;align-items:center;justify-content:center;padding:30px}
.p1{background:linear-gradient(135deg,#0f2f66,#2f80ed)}.p2{background:#0a1a38}
.mini{width:84%;background:#f4f9ff;color:#14305a;border-radius:18px;padding:25px;box-shadow:0 18px 40px rgba(0,10,40,.3)}
.mini h3{margin-bottom:16px}
.r{display:flex;justify-content:space-between;padding:12px 0;border-bottom:1px solid #dbe8f7;font-size:14px}
.tot{display:flex;justify-content:space-between;margin-top:16px;font-size:18px;font-weight:800;color:#0f3a78}
.tbl{background:var(--card);border:1px solid var(--line);border-radius:26px;padding:30px;box-shadow:0 15px 40px rgba(30,90,200,.1)}
.tbl h3{margin-bottom:22px}
.tr{display:grid;grid-template-columns:2fr 1fr 1fr;gap:10px;padding:15px 5px;border-bottom:1px solid var(--line);font-size:14px}
.th{font-weight:800;color:var(--acc)}.good{color:#1f9a73;font-weight:700}.bad{color:#d8584a;font-weight:700}
.num{font-size:47px;font-weight:800;color:var(--acc2);margin-bottom:12px}
.benefit{padding:25px}.benefit strong{display:block;font-size:21px;margin-bottom:10px}.benefit p{color:var(--muted);font-size:14px;line-height:1.6}
.cta{margin:80px 7%;padding:85px 30px;border-radius:28px;text-align:center;color:#f2f8ff;background:linear-gradient(125deg,#08142c,#1d4e9e);position:relative;overflow:hidden}
.cta h2{font-size:clamp(32px,4vw,46px);margin-bottom:18px}.cta p{color:#c4d8f2;font-size:17px;margin-bottom:30px}
footer{padding:55px 7%;background:#060e1f;color:#a9bfdc;display:flex;justify-content:space-between;align-items:center;font-size:14px}

/* NUEVO: mascotas, huellas y animales flotantes */
.pet{text-align:center;padding:26px 12px}
.pet b{display:block;font-size:54px;line-height:1;margin-bottom:12px;transition:transform .3s}
.pet:hover b{transform:scale(1.18) rotate(-6deg)}
.pet h3{font-size:16px;margin-bottom:4px}.pet p{font-size:13px}
.pf{position:relative;width:96px;height:96px;margin:0 auto 14px!important;border-radius:50%;overflow:hidden;border:3px solid #fff;box-shadow:0 8px 20px rgba(30,80,160,.25);background:#dcecff;display:flex;align-items:center;justify-content:center;font-size:46px}
.paws{letter-spacing:14px;font-size:22px;opacity:.6;margin-top:18px}
.cta .fl{position:absolute;font-size:46px;opacity:.35;animation:fl 7s ease-in-out infinite}
.ps-pets{position:absolute;inset:0;pointer-events:none;z-index:0}
.ps-pets span{position:absolute;font-size:var(--s,44px);opacity:.55;animation:fl 7s ease-in-out infinite;animation-delay:var(--d,0s)}
@keyframes fl{50%{transform:translateY(-16px) rotate(6deg)}}

@media(max-width:1000px){.g4{grid-template-columns:repeat(2,1fr)}.g3{grid-template-columns:1fr}.g6{grid-template-columns:repeat(3,1fr)}.split{flex-direction:column;gap:40px}.split>div{width:100%}}
@media(max-width:860px){.navbar{padding:0 4%}.menu{gap:10px}.menu a:not(.btn-login){display:none}.logo{font-size:26px}}
@media(max-width:600px){.g4{grid-template-columns:1fr}.g6{grid-template-columns:repeat(2,1fr)}.sec{padding:80px 6%}footer{flex-direction:column;gap:15px;text-align:center}}

/* HERO */
.ps-wrap { position: relative; height: 480vh; background: #0a1428; }
.ps-sticky {
    --mx: 72%; --my: 50%;
    position: sticky; top: 82px; height: calc(100vh - 82px); min-height: 580px; overflow: hidden;
    display: grid; grid-template-columns: 1fr 1.1fr; align-items: center; gap: 2%;
    padding: 0 6.5% 0 7.5%; color: #eaf3ff;
    background: linear-gradient(120deg, #0a1428, #0f2142 60%, #081022);
}
.ps-sticky:before {
    content: ''; position: absolute; inset: 0; pointer-events: none;
    background: radial-gradient(circle 420px at var(--mx) var(--my), rgba(60,150,255,.30), transparent 70%);
}
.ps-text { position: relative; height: 480px; z-index: 2; }
.ps-step {
    position: absolute; inset: 0; display: flex; flex-direction: column; justify-content: center;
    opacity: 0; transform: translateY(22px); pointer-events: none;
    transition: opacity .45s ease, transform .45s ease;
}
.ps-step.on { opacity: 1; transform: none; pointer-events: auto; }
.ps-tag {
    align-self: flex-start; padding: 10px 16px; margin-bottom: 26px; border-radius: 999px;
    background: rgba(92,200,255,.10); color: #8fd6ff; border: 1px solid rgba(92,200,255,.35);
    font-size: 10px; font-weight: 700; letter-spacing: 1.6px;
}
.ps-step h1, .ps-step h2 {
    font-weight: 850; letter-spacing: -.06em; line-height: 1; color: #f2f8ff; margin-bottom: 22px;
    font-size: clamp(34px, 4.4vw, 62px);
}
.ps-step h1 { font-size: clamp(46px, 5.2vw, 80px); }
.ps-step em {
    display: block; color: #5cc8ff; font-family: Georgia, 'Times New Roman', serif;
    font-style: italic; font-weight: 500; letter-spacing: -.05em;
}
.ps-step p { color: #a9bfdc; font-size: 17px; line-height: 1.75; max-width: 480px; }
.ps-btns { display: flex; gap: 13px; flex-wrap: wrap; margin-top: 28px; }
.ps-b1, .ps-b2 { display: inline-block; padding: 15px 25px; border-radius: 999px; font-weight: 800; font-size: 15px; transition: transform .25s, box-shadow .25s, background .25s; }
.ps-b1 { background: linear-gradient(135deg, #7fd6ff, #2f80ed); color: #04142e; box-shadow: 0 10px 28px rgba(47,128,237,.35); }
.ps-b1:hover { transform: translateY(-4px); box-shadow: 0 16px 36px rgba(47,128,237,.5); }
.ps-b2 { border: 1px solid rgba(200,225,255,.35); color: #eaf3ff; }
.ps-b2:hover { background: rgba(200,225,255,.1); }
.ps-chips { display: flex; flex-wrap: wrap; gap: 9px; margin-top: 28px; }
.ps-chips button {
    padding: 9px 14px; border-radius: 999px; font: inherit; font-size: 13px; color: #d6e6fb; cursor: pointer;
    border: 1px solid rgba(200,225,255,.2); background: rgba(200,225,255,.06); transition: .25s;
}
.ps-chips button:hover { background: #5cc8ff; color: #04142e; border-color: #5cc8ff; transform: translateY(-2px); }
.ps-hint { margin-top: 16px; font-size: 12.5px; color: #7f9bc0; }
.ps-list { list-style: none; margin-top: 22px; padding: 0; }
.ps-list li { padding: 8px 0; font-size: 15px; color: #d6e6fb; border-bottom: 1px solid rgba(200,225,255,.12); max-width: 420px; }
.ps-list li:before { content: '✓  '; color: #5cc8ff; font-weight: 700; }
.ps-rail { position: absolute; left: 1.6%; top: 50%; transform: translateY(-50%); display: flex; flex-direction: column; gap: 4px; z-index: 3; }
.ps-rail button { background: none; border: 0; padding: 5px 10px; cursor: pointer; }
.ps-rail button:before { content: ''; display: block; width: 4px; height: 22px; border-radius: 4px; background: rgba(200,225,255,.22); transition: background .3s, height .3s; }
.ps-rail button:hover:before { background: rgba(92,200,255,.6); }
.ps-rail button.on:before { background: #5cc8ff; height: 44px; }
.ps-scene { position: relative; height: 100%; perspective: 1400px; z-index: 1; }
.ps-world-box { display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; }
.ps-world { position: relative; width: 0; height: 0; transform-style: preserve-3d; will-change: transform; }
.ps-card {
    position: absolute; left: -140px; width: 280px; padding: 20px; display: flex; flex-direction: column;
    background: #f4f9ff; color: #14305a; border-radius: 20px;
    box-shadow: 0 30px 60px rgba(0,10,40,.5);
    transition: opacity .35s ease, outline-color .35s ease, box-shadow .3s ease;
    outline: 2px solid transparent; outline-offset: 3px; will-change: transform;
}
.ps-card:hover { box-shadow: 0 30px 70px rgba(0,10,40,.55), 0 0 0 1px rgba(92,200,255,.6), 0 0 36px rgba(60,150,255,.35); }
.ps-card.on { outline-color: #5cc8ff; }
.ps-card h3 { font-size: 14px; margin-bottom: 10px; color: #0f3a78; cursor: pointer; }
.ps-card h3:hover { color: #2f80ed; }
.ps-card button { font: inherit; color: inherit; }
.ps-row { display: flex; justify-content: space-between; width: 100%; padding: 8px 0; border: 0; border-bottom: 1px solid #dbe8f7; background: none; font-size: 12.5px; text-align: left; }
button.ps-row { cursor: pointer; transition: opacity .2s; }
button.ps-row:hover span:first-child { color: #2f80ed; }
button.ps-row.off { opacity: .35; }
button.ps-row.off span:first-child { text-decoration: line-through; }
.ps-total { display: flex; justify-content: space-between; margin-top: 12px; font-size: 17px; font-weight: 800; color: #0f3a78; }
.ps-tip { font-size: 10.5px; color: #6b86ad; margin-top: 6px; }
.ps-act {
    margin-top: auto; padding: 10px; border: 0; border-radius: 10px; cursor: pointer; font-weight: 800; font-size: 13px;
    background: linear-gradient(135deg, #5cc8ff, #2f80ed); color: #04142e; transition: transform .2s, filter .2s;
}
.ps-act:hover { transform: translateY(-2px); filter: brightness(1.08); }
.ps-act.done { background: #2fa37a; color: #fff; }
.ps-ok { color: #1f9a73; font-weight: 700; }
.ps-low { color: #d8584a; font-weight: 700; }
.ps-tabs { display: flex; gap: 6px; margin-bottom: 10px; }
.ps-tabs button { flex: 1; padding: 6px; border: 1px solid #cfe0f5; border-radius: 8px; background: none; font-size: 11.5px; font-weight: 700; cursor: pointer; color: #4a6c9b; }
.ps-tabs button.on { background: #2f80ed; border-color: #2f80ed; color: #fff; }
.ps-branch { font-size: 12.5px; display: flex; justify-content: space-between; }
.ps-bar { height: 8px; border-radius: 4px; background: #dbe8f7; margin: 6px 0 12px; overflow: hidden; }
.ps-bar b { display: block; height: 100%; background: linear-gradient(90deg, #5cc8ff, #2f80ed); border-radius: 4px; transition: width .5s ease; }
.ps-c0 { top: -195px; height: 390px; }
.ps-c1, .ps-c2 { top: -95px; height: 190px; }

@media (max-width: 860px) {
    .ps-sticky { grid-template-columns: 1fr; grid-template-rows: auto 1fr; padding: 18px 6% 0; gap: 0; }
    .ps-text { height: 340px; }
    .ps-step h1 { font-size: clamp(38px, 11vw, 52px); }
    .ps-step h2 { font-size: clamp(30px, 8.5vw, 40px); }
    .ps-step p { font-size: 14.5px; line-height: 1.6; }
    .ps-tag { margin-bottom: 14px; }
    .ps-btns { margin-top: 18px; }
    .ps-chips, .ps-hint { display: none; }
    .ps-list li { padding: 5px 0; font-size: 13.5px; }
    .ps-rail { display: none; }
    .ps-world-box { transform: scale(.58); }
}
@media (prefers-reduced-motion: reduce) {
    .ps-wrap { height: auto; }
    .ps-sticky { position: relative; top: 0; height: auto; min-height: 600px; }
    .ps-step { transition: none; }
    .ps-pets span, .cta .fl { animation: none; }
}

/* ===== MODO CLARO (por defecto) ===== */
body:not(.dark-mode) .navbar{background:rgba(255,255,255,.92);border-bottom:1px solid #d9e6f7}
body:not(.dark-mode) .logo{color:#0d2548}body:not(.dark-mode) .logo span{color:#2f80ed}
body:not(.dark-mode) .menu a{color:#36557f}body:not(.dark-mode) .menu a:hover{color:#2f80ed}
body:not(.dark-mode) .menu .btn-login{background:#2f80ed;color:#fff}
body:not(.dark-mode) .menu .btn-login:hover{background:#1f6bd6;color:#fff}
body:not(.dark-mode) .dark-mode-btn,body:not(.dark-mode) .language-btn{border-color:rgba(30,90,200,.3);color:#0d2548}
body:not(.dark-mode) .dark-mode-btn:hover,body:not(.dark-mode) .language-btn:hover{background:#2f80ed;border-color:#2f80ed;color:#fff}

body:not(.dark-mode) .ps-wrap{background:#eaf3ff}
body:not(.dark-mode) .ps-sticky{background:linear-gradient(120deg,#f6faff,#e4f0ff 60%,#d6e8ff);color:#0d2548}
body:not(.dark-mode) .ps-sticky:before{background:radial-gradient(circle 420px at var(--mx) var(--my),rgba(60,150,255,.22),transparent 70%)}
body:not(.dark-mode) .ps-tag{background:rgba(47,128,237,.10);color:#1f5fc4;border-color:rgba(47,128,237,.35)}
body:not(.dark-mode) .ps-step h1,body:not(.dark-mode) .ps-step h2{color:#0d2548}
body:not(.dark-mode) .ps-step em{color:#2f80ed}
body:not(.dark-mode) .ps-step p{color:#4a6a94}
body:not(.dark-mode) .ps-b1{background:linear-gradient(135deg,#4aa3ff,#2f6fe0);color:#fff;box-shadow:0 10px 28px rgba(47,111,224,.35)}
body:not(.dark-mode) .ps-b2{border-color:rgba(30,90,200,.4);color:#1f5fc4}
body:not(.dark-mode) .ps-b2:hover{background:rgba(47,128,237,.10)}
body:not(.dark-mode) .ps-chips button{color:#254a7d;border-color:rgba(30,90,200,.25);background:rgba(255,255,255,.75)}
body:not(.dark-mode) .ps-chips button:hover{background:#2f80ed;border-color:#2f80ed;color:#fff}
body:not(.dark-mode) .ps-hint{color:#6b86ad}
body:not(.dark-mode) .ps-list li{color:#254a7d;border-bottom-color:rgba(30,90,200,.15)}
body:not(.dark-mode) .ps-list li:before{color:#2f80ed}
body:not(.dark-mode) .ps-rail button:before{background:rgba(30,90,200,.22)}
body:not(.dark-mode) .ps-rail button:hover:before{background:rgba(47,128,237,.6)}
body:not(.dark-mode) .ps-rail button.on:before{background:#2f80ed}
body:not(.dark-mode) .ps-card{background:#fff;border:1px solid #d9e6f7;box-shadow:0 24px 55px rgba(30,90,200,.20)}
body:not(.dark-mode) .ps-card.on{outline-color:#2f80ed}
body:not(.dark-mode) .p1{background:linear-gradient(135deg,#bcdcff,#7ab8ff)}
body:not(.dark-mode) .p2{background:#d9e9ff}
body:not(.dark-mode) .mini{box-shadow:0 18px 40px rgba(30,90,200,.18)}
body:not(.dark-mode) .cta{background:linear-gradient(125deg,#2f6fe0,#4aa3ff)}
body:not(.dark-mode) .cta p{color:#eaf3ff}
body:not(.dark-mode) footer{background:#e4effd;color:#36557f}

/* ===== MODO OSCURO ===== */
body.dark-mode .ps-card{background:#0d2145;color:#eaf3ff;box-shadow:0 30px 60px rgba(0,0,0,.55)}
body.dark-mode .ps-card h3,body.dark-mode .ps-total{color:#8fd6ff}
body.dark-mode .ps-row{border-bottom-color:#1c3a68}
body.dark-mode .ps-tip{color:#7f9bc0}
body.dark-mode .ps-tabs button{border-color:#1c3a68;color:#a3b8d6}
body.dark-mode .ps-tabs button.on{background:#2f80ed;border-color:#2f80ed;color:#fff}
body.dark-mode .ps-bar{background:#1c3a68}
body.dark-mode .p1{background:linear-gradient(135deg,#071a3d,#17479a)}
body.dark-mode .p2{background:#030a18}
body.dark-mode .mini{background:#0d2145;color:#eaf3ff}
body.dark-mode .r{border-bottom-color:#1c3a68}
body.dark-mode .tot{color:#8fd6ff}
body.dark-mode .cta{background:linear-gradient(125deg,#050d1f,#14408a)}
body.dark-mode footer{background:#02060e;color:#a9bfdc}

/* ===== LETRAS MÁS GRANDES Y VISIBLES ===== */
:root{--muted:#44628a}
body.dark-mode{--muted:#c2d3ea}
.menu a{font-size:15px;font-weight:500}
.menu .btn-login{font-size:15px}
.language-btn{font-size:14px}
.intro{font-size:18px}
.label{font-size:15px}
.desc,.split p{font-size:19px}
.card p,.benefit p{font-size:16px}
.pet p{font-size:14px}
.check li{font-size:17px}
.r,.tr{font-size:16px}
footer{font-size:16px}

.ps-text{height:560px}
.ps-tag{font-size:12.5px}
.ps-step p{font-size:18px;color:#c5d6ee}
.ps-b1,.ps-b2{font-size:16px}
.ps-chips button{font-size:15px;padding:10px 16px}
.ps-hint{font-size:14.5px;color:#a9c0de}
.ps-list li{font-size:17px}
.ps-card{left:-150px;width:300px}
.ps-card h3{font-size:16px}
.ps-row{font-size:14.5px}
.ps-tip{font-size:12.5px;color:#4d6b95}
.ps-total{font-size:19px}
.ps-act{font-size:15px}
.ps-tabs button{font-size:13.5px}
.ps-branch{font-size:14.5px}
.ps-c0{top:-220px;height:440px}
.ps-c1,.ps-c2{top:-107px;height:215px}
body:not(.dark-mode) .ps-step p{color:#34567f}
body:not(.dark-mode) .ps-hint{color:#4d6b95}
body.dark-mode .ps-tip{color:#a9c0de}

@media(max-width:860px){
  .ps-text{height:400px}
  .ps-step p{font-size:16px;line-height:1.5}
  .ps-list li{font-size:15.5px}
  .ps-tag{font-size:11.5px}
  .ps-world-box{transform:scale(.64)}
  .desc,.split p{font-size:17px}
  .ps-pets span:nth-child(n+5){display:none}
}
@media(max-width:860px) and (max-height:720px){.ps-world-box{transform:scale(.5)}}

/* ===== CAPA VINTAGE (modo claro): papel crema, serif, polaroids y sellos ===== */
:root{--serif:'Playfair Display','Cormorant Garamond',Georgia,'Times New Roman',serif}
.title,.split h2,.ps-step h2,.ps-step h1,.cta h2,.card h3,.benefit strong{font-family:var(--serif)}
.label{font-family:var(--serif);font-style:italic;letter-spacing:1.5px;font-weight:600}
body:not(.dark-mode){--bg2:#f8fbff;--bg:#eaf3ff;--card:#fff;--line:#c9dcf3}
body:not(.dark-mode) .sec,body:not(.dark-mode) .intro{background-image:radial-gradient(rgba(47,128,237,.07) 1px,transparent 1.2px);background-size:7px 7px}
body:not(.dark-mode) .card,body:not(.dark-mode) .tbl{border:1.5px solid #a9c8ee;box-shadow:inset 0 0 0 4px var(--card),inset 0 0 0 5px #d3e4f8}
body:not(.dark-mode) .ico{background:#dcecff}
body:not(.dark-mode) .ps-wrap{background:#eaf3ff}
body:not(.dark-mode) .ps-sticky{background:linear-gradient(120deg,#f6faff,#e4f0ff 60%,#d6e8ff)}
body:not(.dark-mode) .ps-card{background:#fff;border:1.5px solid #a9c8ee}
body:not(.dark-mode) footer{background:#e4effd}

/* Sello circular */
.sello{position:absolute;right:5%;top:-34px;width:112px;height:112px;border-radius:50%;border:2px dashed #2f80ed;color:#2f80ed;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;font-family:var(--serif);font-style:italic;font-size:13px;line-height:1.25;transform:rotate(12deg);background:var(--bg2);z-index:2}
.sello b{font-size:26px;font-style:normal}
/* Galería de polaroids */
.galeria{position:relative}
.pols{display:flex;flex-wrap:wrap;justify-content:center;gap:34px 30px;margin-top:60px}
.pol{width:230px;background:#fff;padding:12px 12px 16px;box-shadow:0 14px 30px rgba(30,80,160,.25);transform:rotate(var(--r,0deg));transition:transform .35s,box-shadow .35s;position:relative}
.pol:hover{transform:rotate(0) scale(1.06);box-shadow:0 22px 44px rgba(30,80,160,.35);z-index:3}
.pol:before{content:'';position:absolute;top:-12px;left:50%;width:78px;height:24px;margin-left:-39px;background:rgba(120,190,255,.55);transform:rotate(-3deg)}
.ph{position:relative;height:210px;overflow:hidden;background:linear-gradient(135deg,#dcecff,#a9c8ee);display:flex;align-items:center;justify-content:center;font-size:84px}
.im{position:absolute;inset:0;display:block;background-size:cover;background-position:center 30%;filter:saturate(.95) contrast(1.02)}
.pol figcaption{font-family:var(--serif);font-style:italic;font-size:16px;color:#1f4a85;margin-top:12px;text-align:center}
body.dark-mode .pol{background:#10244a}body.dark-mode .pol figcaption{color:#cfe0f7}body.dark-mode .ph{background:linear-gradient(135deg,#17315e,#0c1c3a)}
body.dark-mode .sello{background:var(--bg2);border-color:#5cc8ff;color:#5cc8ff}
/* Estante de la botica */
.botica{display:flex;flex-wrap:wrap;justify-content:center;gap:14px;margin-top:46px}
.botica span{font-family:var(--serif);font-style:italic;font-size:17px;padding:12px 20px;border:1.5px solid #a9c8ee;border-radius:8px;background:#fff;color:#1f4a85;box-shadow:3px 3px 0 #cfe1f8}
.botica span b{font-style:normal;margin-right:6px}
body.dark-mode .botica span{background:#0d2145;color:#eaf3ff;border-color:#1c3a68;box-shadow:3px 3px 0 #16305a}
/* Polaroids flotando en el hero */
.ps-pols{position:absolute;inset:0;pointer-events:none;z-index:0}
.ps-pols .pol{position:absolute;width:120px;padding:6px 6px 8px;opacity:.9;animation:fl 8s ease-in-out infinite}
.ps-pols .pol:before{width:44px;height:14px;margin-left:-22px;top:-8px}
.ps-pols .ph{height:96px;font-size:44px}
@media(max-width:860px){.ps-pols{display:none}.sello{display:none}.pol{width:190px}}
@media(prefers-reduced-motion:reduce){.ps-pols .pol{animation:none}}

/* ===== FONDO DEL HERO: aurora azul, huellas, anillo y huellitas caminando ===== */
.ps-sticky:after{content:'';position:absolute;right:-6%;top:50%;width:min(62vw,820px);aspect-ratio:1;margin-top:calc(min(62vw,820px)/-2);border-radius:50%;border:2px dashed rgba(47,128,237,.30);box-shadow:inset 0 0 0 28px rgba(47,128,237,.04),inset 0 0 0 29px rgba(47,128,237,.16),inset 0 0 0 96px rgba(92,200,255,.06),0 0 120px rgba(92,200,255,.25);animation:spin 90s linear infinite;pointer-events:none}
@keyframes spin{to{transform:rotate(360deg)}}
.ps-bg{position:absolute;inset:0;overflow:hidden;pointer-events:none;z-index:0;background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='170' height='170'%3E%3Cg fill='%232f80ed' fill-opacity='.10'%3E%3Cg transform='translate(36 40) rotate(-20) scale(.9)'%3E%3Cellipse cx='0' cy='8' rx='11' ry='9'/%3E%3Cellipse cx='-12' cy='-6' rx='4.5' ry='6'/%3E%3Cellipse cx='-4' cy='-13' rx='4.5' ry='6'/%3E%3Cellipse cx='5' cy='-13' rx='4.5' ry='6'/%3E%3Cellipse cx='13' cy='-6' rx='4.5' ry='6'/%3E%3C/g%3E%3Cg transform='translate(124 126) rotate(24) scale(.7)'%3E%3Cellipse cx='0' cy='8' rx='11' ry='9'/%3E%3Cellipse cx='-12' cy='-6' rx='4.5' ry='6'/%3E%3Cellipse cx='-4' cy='-13' rx='4.5' ry='6'/%3E%3Cellipse cx='5' cy='-13' rx='4.5' ry='6'/%3E%3Cellipse cx='13' cy='-6' rx='4.5' ry='6'/%3E%3C/g%3E%3Cg transform='translate(128 36) rotate(40)'%3E%3Crect x='-16' y='-3' width='32' height='6' rx='3'/%3E%3Ccircle cx='-17' cy='-4' r='4.5'/%3E%3Ccircle cx='-17' cy='4' r='4.5'/%3E%3Ccircle cx='17' cy='-4' r='4.5'/%3E%3Ccircle cx='17' cy='4' r='4.5'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") 0 0/170px 170px;animation:tile 60s linear infinite}
@keyframes tile{to{background-position:170px 170px}}
.ps-bg i.b{position:absolute;border-radius:50%;filter:blur(14px);animation:drift 16s ease-in-out infinite alternate}
.ps-bg .b1{width:560px;height:560px;left:-9%;top:-22%;background:radial-gradient(circle,rgba(92,200,255,.55),transparent 66%)}
.ps-bg .b2{width:640px;height:640px;right:-8%;bottom:-30%;background:radial-gradient(circle,rgba(47,128,237,.40),transparent 66%);animation-delay:-6s}
.ps-bg .b3{width:420px;height:420px;left:34%;top:30%;background:radial-gradient(circle,rgba(255,255,255,.75),transparent 66%);animation-delay:-11s}
@keyframes drift{to{transform:translate(50px,36px) scale(1.12)}}
.ps-trail{position:absolute;left:0;right:0;bottom:0;height:120px}
.ps-trail b{position:absolute;width:34px;height:34px;background:#2f80ed;opacity:0;-webkit-mask:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 40 40'%3E%3Cg transform='translate(20 23)'%3E%3Cellipse cx='0' cy='6' rx='9' ry='7.5'/%3E%3Cellipse cx='-10' cy='-5' rx='3.6' ry='5'/%3E%3Cellipse cx='-3.6' cy='-11' rx='3.6' ry='5'/%3E%3Cellipse cx='3.6' cy='-11' rx='3.6' ry='5'/%3E%3Cellipse cx='10' cy='-5' rx='3.6' ry='5'/%3E%3C/g%3E%3C/svg%3E") center/contain no-repeat;mask:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 40 40'%3E%3Cg transform='translate(20 23)'%3E%3Cellipse cx='0' cy='6' rx='9' ry='7.5'/%3E%3Cellipse cx='-10' cy='-5' rx='3.6' ry='5'/%3E%3Cellipse cx='-3.6' cy='-11' rx='3.6' ry='5'/%3E%3Cellipse cx='3.6' cy='-11' rx='3.6' ry='5'/%3E%3Cellipse cx='10' cy='-5' rx='3.6' ry='5'/%3E%3C/g%3E%3C/svg%3E") center/contain no-repeat;transform:rotate(80deg);animation:step 9s ease-in-out infinite}
@keyframes step{0%,6%{opacity:0;scale:.5}16%,62%{opacity:.5;scale:1}80%,100%{opacity:0;scale:1}}
body:not(.dark-mode) .ps-sticky{background:linear-gradient(135deg,#f6fbff 0%,#dcecff 48%,#bcd9ff 100%)}
body:not(.dark-mode) .ps-sticky:before{background:radial-gradient(circle 420px at var(--mx) var(--my),rgba(255,255,255,.65),transparent 70%)}
body.dark-mode .ps-sticky:after{border-color:rgba(92,200,255,.35);box-shadow:inset 0 0 0 28px rgba(92,200,255,.04),inset 0 0 0 29px rgba(92,200,255,.18),0 0 140px rgba(47,128,237,.35)}
body.dark-mode .ps-bg .b1{background:radial-gradient(circle,rgba(47,128,237,.40),transparent 66%)}body.dark-mode .ps-bg .b2{background:radial-gradient(circle,rgba(92,200,255,.22),transparent 66%)}
body.dark-mode .ps-bg .b3{background:radial-gradient(circle,rgba(92,200,255,.18),transparent 66%)}
body.dark-mode .ps-trail b{background:#5cc8ff}
@media(max-width:860px){.ps-sticky:after{right:-45%;width:130vw;margin-top:-65vw}.ps-bg .b1,.ps-bg .b2{width:380px;height:380px}}
@media(prefers-reduced-motion:reduce){.ps-sticky:after,.ps-bg,.ps-bg i.b,.ps-trail b{animation:none}.ps-trail b{opacity:.4}}
/* ===== HERO A PANTALLA COMPLETA + MENÚ DISCRETO ===== */
.navbar{position:fixed;left:0;right:0;top:0;height:64px;transition:transform .35s ease,background .3s}
.navbar.hide{transform:translateY(-100%)}
.navbar .logo{font-size:24px}
.menu a{font-size:14px}.menu .btn-login{padding:9px 16px;font-size:14px}
.dark-mode-btn,.language-btn{height:36px}.dark-mode-btn{width:36px;font-size:16px}
body:not(.dark-mode) .navbar{background:rgba(255,255,255,.45);border-bottom-color:transparent}
body.dark-mode .navbar{background:rgba(2,6,14,.4);border-bottom-color:transparent}
body:not(.dark-mode) .navbar.solid{background:rgba(255,255,255,.93);border-bottom:1px solid #c9dcf3}
body.dark-mode .navbar.solid{background:rgba(2,6,14,.96)}
.sec{scroll-margin-top:64px}
.ps-sticky{top:0;height:100vh;min-height:640px;padding-top:64px}
.ps-step h1{font-size:clamp(52px,6vw,98px)}
.ps-step h2{font-size:clamp(38px,4.8vw,70px)}
.ps-step p{font-size:clamp(18px,1.35vw,22px);max-width:540px}
@media(min-width:861px){.ps-world-box{transform:scale(1.14)}}
@media(max-width:860px){.ps-sticky{padding-top:84px}}
@media(prefers-reduced-motion:reduce){.ps-sticky{height:auto;min-height:100vh}}
/* Collage de fotos del hero (con parallax del mouse) */
.ps-pols .pol{width:var(--w,160px);padding:8px 8px 10px;opacity:.97;translate:calc(var(--px,0) * var(--k,12) * -1px) calc(var(--py,0) * var(--k,12) * -1px);transition:translate .5s ease-out}
.ps-pols .ph{height:calc(var(--w,160px) * .98)}
/* ===== ESCENARIO DE FOTOS DEL HERO (una escena por paso) ===== */
.ps-set{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;opacity:0;transform:scale(.94) translateY(28px);transition:opacity .6s ease,transform .7s cubic-bezier(.2,.8,.2,1);pointer-events:none}
.ps-set.on{opacity:1;transform:none}
.ps-set .pol{position:absolute;width:var(--w);padding:10px 10px 12px;translate:calc(var(--px,0) * var(--k,14) * -1px) calc(var(--py,0) * var(--k,14) * -1px);transition:translate .5s ease-out}
.ps-set .ph{height:calc(var(--w) * 1.2)}
.ps-set .pol.main{--w:min(26vw,40vh);position:relative;z-index:2;padding:14px 14px 16px;box-shadow:0 30px 70px rgba(30,80,160,.35)}
.ps-set .pol.acc{z-index:1}
.ps-set.on .pol{animation:bob 9s ease-in-out infinite}
@keyframes bob{50%{transform:translateY(-12px) rotate(calc(var(--r) + 2deg))}}
@media(max-width:860px){.ps-set .pol.acc{display:none}.ps-set .pol.main{--w:min(56vw,30vh)}}
@media(prefers-reduced-motion:reduce){.ps-set.on .pol{animation:none}}
.ptabs{display:flex;flex-wrap:wrap;justify-content:center;gap:12px;margin-top:44px}
.ptabs button{display:flex;align-items:center;gap:10px;padding:8px 22px 8px 8px;border-radius:999px;border:1.5px solid #a9c8ee;background:#fff;color:var(--ink);font-family:var(--serif);font-size:17px;cursor:pointer;transition:.25s}
.ptabs button .pf{width:46px;height:46px;margin:0!important;border-width:2px;box-shadow:none}
.ptabs button:hover{border-color:#2f80ed;transform:translateY(-2px)}
.ptabs button.on{background:#2f80ed;border-color:#2f80ed;color:#fff}
body.dark-mode .ptabs button{background:#0d2145;border-color:#1c3a68;color:#eaf3ff}
body.dark-mode .ptabs button.on{background:#2f80ed;border-color:#2f80ed}
.ppanel{display:none;margin-top:50px;gap:60px;align-items:center;justify-content:center;flex-wrap:wrap;text-align:left}
.ppanel.on{display:flex;animation:pin .4s ease}
@keyframes pin{from{opacity:0;transform:translateY(12px)}}
.pgal{display:flex;gap:26px;justify-content:center;padding-top:14px;flex-wrap:wrap}
.pgal .pol{width:220px}.pgal .ph{height:280px}
.ptxt{max-width:420px}.ptxt h3{font-family:var(--serif);font-size:34px;margin-bottom:10px}
.ptxt p{color:var(--muted);font-size:19px;line-height:1.7;margin-bottom:16px}
@media(prefers-reduced-motion:reduce){.ppanel.on{animation:none}}
.im-salchicha{background-image:url(../../../img/home/salchicha.webp)}
.im-gatito{background-image:url(../../../img/home/gatito.webp)}
.im-gato-adulto{background-image:url(../../../img/home/gato-adulto.webp)}
.im-conejo-gris{background-image:url(../../../img/home/conejo-gris.webp)}
.im-conejo-blanco{background-image:url(../../../img/home/conejo-blanco.webp)}
.im-perico{background-image:url(../../../img/home/perico.webp)}
.im-pajaros{background-image:url(../../../img/home/pajaros.webp)}
.im-perro-clasico{background-image:url(../../../img/home/perro-clasico.webp)}
.im-rata{background-image:url(../../../img/home/rata.webp)}
.im-caballos{background-image:url(../../../img/home/caballos.webp)}
.im-revision-ganado{background-image:url(../../../img/home/revision-ganado.webp)}
.im-laboratorio{background-image:url(../../../img/home/laboratorio.webp)}
.im-loros{background-image:url(../../../img/home/loros.webp)}
.im-gatitos{background-image:url(../../../img/home/gatitos.webp)}
.im-perro-taza{background-image:url(../../../img/home/perro-taza.webp)}
.im-conejo-pasto{background-image:url(../../../img/home/conejo-pasto.webp)}
.im-cuyos{background-image:url(../../../img/home/cuyos.webp)}
.im-patito{background-image:url(../../../img/home/patito.webp)}
.im-caballos-arbol{background-image:url(../../../img/home/caballos-arbol.webp)}
.im-vaca-monte{background-image:url(../../../img/home/vaca-monte.webp)}
.im-patos{background-image:url(../../../img/home/patos.webp)}
.im-cerditos{background-image:url(../../../img/home/cerditos.webp)}
.im-caballos-sol{background-image:url(../../../img/home/caballos-sol.webp)}
.im-vaca-cerdo{background-image:url(../../../img/home/vaca-cerdo.webp)}
.logout-form{display:inline}
.logout-link{background:none;border:0;cursor:pointer;font:inherit;font-size:14px;font-weight:700;color:#c0392b;transition:.25s}
.logout-link:hover{text-decoration:underline}
body.dark-mode .logout-link{color:#ff9a8d}
.logo{line-height:0}.logo img{height:46px;width:auto;display:block;transition:transform .3s}.logo:hover img{transform:scale(1.06) rotate(-2deg)}.navbar .logo img{height:50px}@media(max-width:860px){.navbar .logo img{height:40px}}

</style>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,800;1,500&display=swap" rel="stylesheet">
</head>
<body>
<script nonce="<?= e($nonce) ?>">
    try {
        var d = localStorage.getItem("petko_dark");
        if (d === null && window.matchMedia && matchMedia("(prefers-color-scheme: dark)").matches) d = "1";
        if (d === "1") document.body.classList.add("dark-mode");
    } catch (err) {}
</script>

<header class="navbar">
    <a class="logo" href="#inicio" aria-label="PETKO"><img src="../../../img/petko/logo_amarillo.png" alt="PETKO Veterinaria y Ganadería"></a>
    <nav class="menu">
        <a href="#inicio">Inicio</a>
        <a href="#funciones">Funciones</a>
        <a href="#mascotas">Mascotas</a>
        <a href="#ventas">Ventas</a>
        <a href="#inventario">Inventario</a>
        <a href="#sucursales">Sucursales</a>
        <a href="#nosotros">Nosotros</a>
        <?php if ($logueado): ?>
            <a href="<?= e($rutaDashboard) ?>" class="btn-login"><span data-i18n><?= e($nombre) ?></span></a>
            <!-- Cerrar sesión: formulario POST con token CSRF (lo procesa este mismo archivo) -->
            <form method="post" action="<?= e(basename((string)($_SERVER['SCRIPT_NAME'] ?? 'home.php'))) ?>" class="logout-form">
                <input type="hidden" name="accion" value="cerrar_sesion">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <button type="submit" class="logout-link">Cerrar sesión</button>
            </form>
        <?php else: ?>
            <a href="../../../../inicioSesion.php" class="btn-login">Iniciar sesión</a>
        <?php endif; ?>
        <button id="darkModeBtn" class="dark-mode-btn" title="Cambiar modo">🌙</button>
        <button id="languageToggle" class="language-btn" type="button" aria-label="Cambiar idioma">EN</button>
    </nav>
</header>

<section class="ps-wrap" id="inicio" aria-label="PETKO: gestión para veterinarias">
    <div class="ps-sticky">

        <!-- Fondo decorativo -->
        <div class="ps-bg" aria-hidden="true"><i class="b b1"></i><i class="b b2"></i><i class="b b3"></i><div class="ps-trail"><b style="left:4.0%;bottom:36px;animation-delay:0.0s"></b><b style="left:11.4%;bottom:14px;animation-delay:0.8s"></b><b style="left:18.8%;bottom:36px;animation-delay:1.6s"></b><b style="left:26.2%;bottom:14px;animation-delay:2.4s"></b><b style="left:33.6%;bottom:36px;animation-delay:3.2s"></b><b style="left:41.0%;bottom:14px;animation-delay:4.0s"></b><b style="left:48.4%;bottom:36px;animation-delay:4.8s"></b><b style="left:55.8%;bottom:14px;animation-delay:5.6s"></b><b style="left:63.2%;bottom:36px;animation-delay:6.4s"></b><b style="left:70.6%;bottom:14px;animation-delay:7.2s"></b><b style="left:78.0%;bottom:36px;animation-delay:8.0s"></b><b style="left:85.4%;bottom:14px;animation-delay:8.8s"></b><b style="left:92.8%;bottom:36px;animation-delay:9.6s"></b></div></div>

        <div class="ps-rail">
            <button data-go="0" aria-label="Inicio"></button><button data-go="1" aria-label="Consultas y ventas"></button>
            <button data-go="2" aria-label="Inventario"></button><button data-go="3" aria-label="Citas"></button>
            <button data-go="4" aria-label="Todo junto"></button>
        </div>

        <div class="ps-text">
            <div class="ps-step on">
                <div class="ps-tag">PETKO · CONTROL INTELIGENTE PARA VETERINARIAS</div>
                <h1>Tu veterinaria, <em>en otro nivel.</em></h1>
                <p>Consultas, vacunas, ventas, inventario y sucursales bajo control. Perros, gatos, aves y conejos: cada mascota con su ficha y tu negocio siempre en orden.</p>
                <div class="ps-btns">
                    <a href="<?= e($ctaHref) ?>" class="ps-b1">Comenzar ahora</a>
                    <a href="#funciones" class="ps-b2">Explorar PETKO</a>
                </div>
                <div class="ps-chips">
                    <button data-go="1">🛒 Punto de venta</button><button data-go="2">📦 Inventario</button>
                    <button data-go="3">📅 Citas</button><button data-go="3">🐾 Mascotas</button>
                </div>
            </div>

            <div class="ps-step">
                <h2>Cobra la consulta y la vacuna.</h2>
                <p>Cobra servicios, medicamentos y alimento en una sola cuenta por mascota, y descuenta el inventario al instante.</p>
                <ul class="ps-list"><li>Registro de ventas</li><li>Servicios y productos veterinarios</li><li>Cuenta por paciente</li><li>Actualización del inventario</li></ul>
            </div>

            <div class="ps-step">
                <h2>Que nunca falte una vacuna.</h2>
                <p>Existencias de alimento, medicamentos y vacunas por sucursal. Lo que está por agotarse aparece marcado como bajo.</p>
                <ul class="ps-list"><li>Consulta de existencias</li><li>Control de vacunas y medicamentos</li><li>Control por sucursal</li><li>Actualización después de una venta</li></ul>
            </div>

            <div class="ps-step">
                <h2>Cada mascota, su agenda.</h2>
                <p>Citas, recordatorios y fichas clínicas para tus pacientes, con gerentes y cajeros trabajando en cada sucursal.</p>
                <ul class="ps-list"><li>Citas y recordatorios</li><li>Ficha clínica por mascota</li><li>Gerentes y cajeros</li></ul>
            </div>

            <div class="ps-step">
                <h2>Todo junto <em>en PETKO.</em></h2>
                <p>Pacientes, ventas, inventario y sucursales en un solo lugar.</p>
                <div class="ps-btns">
                    <a href="<?= e($ctaHref) ?>" class="ps-b1">Comenzar ahora</a>
                    <a href="#funciones" class="ps-b2">Ver funciones</a>
                </div>
            </div>
        </div>

        <div class="ps-scene">
            <div class="ps-set on">
                <figure class="pol acc" style="--r:-9deg;--w:min(13vw,26vh);--k:26;left:2%;top:7%;animation-delay:.6s"><div class="ph"><i class="im im-gatitos" style="background-position:50% 45%"></i></div></figure>
                <figure class="pol main" style="--r:-3deg;--k:10"><div class="ph"><i class="im im-loros" style="background-position:50% 45%"></i></div></figure>
                <figure class="pol acc" style="--r:8deg;--w:min(12vw,24vh);--k:18;right:1%;bottom:6%;animation-delay:1.4s"><div class="ph"><i class="im im-perro-taza" style="background-position:50% 35%"></i></div></figure>
            </div>
            <div class="ps-set">
                <figure class="pol acc" style="--r:-9deg;--w:min(13vw,26vh);--k:26;left:2%;top:7%;animation-delay:.6s"><div class="ph"><i class="im im-cuyos" style="background-position:50% 40%"></i></div></figure>
                <figure class="pol main" style="--r:-3deg;--k:10"><div class="ph"><i class="im im-conejo-pasto" style="background-position:45% 42%"></i></div></figure>
                <figure class="pol acc" style="--r:8deg;--w:min(12vw,24vh);--k:18;right:1%;bottom:6%;animation-delay:1.4s"><div class="ph"><i class="im im-patito" style="background-position:50% 50%"></i></div></figure>
            </div>
            <div class="ps-set">
                <figure class="pol acc" style="--r:-8deg;--w:min(14vw,28vh);--k:22;left:2%;top:7%;animation-delay:.8s"><div class="ph"><i class="im im-vaca-monte" style="background-position:50% 80%"></i></div></figure>
                <figure class="pol main" style="--r:-3deg;--k:10"><div class="ph"><i class="im im-caballos-arbol" style="background-position:50% 50%"></i></div></figure>
            </div>
            <div class="ps-set">
                <figure class="pol acc" style="--r:7deg;--w:min(14vw,28vh);--k:22;right:1%;bottom:6%;animation-delay:1s"><div class="ph"><i class="im im-cerditos" style="background-position:50% 85%"></i></div></figure>
                <figure class="pol main" style="--r:-3deg;--k:10"><div class="ph"><i class="im im-patos" style="background-position:50% 50%"></i></div></figure>
            </div>
            <div class="ps-set">
                <figure class="pol acc" style="--r:-8deg;--w:min(14vw,28vh);--k:22;left:2%;top:7%;animation-delay:.8s"><div class="ph"><i class="im im-vaca-cerdo" style="background-position:40% 50%"></i></div></figure>
                <figure class="pol main" style="--r:-3deg;--k:10"><div class="ph"><i class="im im-caballos-sol" style="background-position:50% 55%"></i></div></figure>
            </div>
        </div>

    </div>
</section>

<section class="intro">Una solución pensada para <strong>veterinarias que quieren tener el control de su negocio.</strong>
    <div class="paws" aria-hidden="true">🐾 🐾 🐾 🐾 🐾</div>
</section>

<section class="sec c" id="funciones">
    <div class="label">TODO EN UN SOLO LUGAR</div>
    <h2 class="title">Todo lo que tu veterinaria necesita</h2>
    <p class="desc">PETKO reúne las herramientas necesarias para atender a tus pacientes y administrar tu negocio de manera sencilla y organizada.</p>
    <div class="grid g4">
        <div class="card"><div class="ico">🩺</div><h3>Consultas y ficha clínica</h3><p>Guarda el historial de cada mascota: consultas, diagnósticos, peso y tratamientos.</p></div>
        <div class="card"><div class="ico">💉</div><h3>Vacunas y desparasitación</h3><p>Lleva el control de cada aplicación y sabe cuándo toca la siguiente.</p></div>
        <div class="card"><div class="ico">📅</div><h3>Citas y recordatorios</h3><p>Organiza la agenda del día y recuerda a los dueños la visita de su mascota.</p></div>
        <div class="card"><div class="ico">🛁</div><h3>Estética y baño</h3><p>Registra los servicios de baño, corte y estética junto con el resto de la cuenta.</p></div>
        <div class="card"><div class="ico">🛒</div><h3>Punto de venta</h3><p>Registra las ventas de alimentos, medicamentos, accesorios y servicios de manera rápida.</p></div>
        <div class="card"><div class="ico">📦</div><h3>Inventario</h3><p>Mantén controladas las existencias de los productos de tu veterinaria.</p></div>
        <div class="card"><div class="ico">🏪</div><h3>Sucursales</h3><p>Administra diferentes sucursales desde un mismo sistema.</p></div>
        <div class="card"><div class="ico">👥</div><h3>Usuarios</h3><p>Gestiona los accesos y funciones de gerentes y cajeros.</p></div>
    </div>
</section>

<section class="sec alt c" id="mascotas">
    <div class="label">PARA CADA PACIENTE</div>
    <h2 class="title">Un expediente para cada mascota</h2>
    <p class="desc">Desde un cachorro hasta un loro: registra a todos los animales que llegan a tu veterinaria.</p>
    <div class="ptabs" role="tablist"><button type="button" role="tab" class="on" data-p="perros"><span class="pf"><i class="im im-salchicha"></i></span>Perros</button><button type="button" role="tab" class="" data-p="gatos"><span class="pf"><i class="im im-gato-adulto"></i></span>Gatos</button><button type="button" role="tab" class="" data-p="conejos"><span class="pf"><i class="im im-conejo-gris"></i></span>Conejos</button><button type="button" role="tab" class="" data-p="aves"><span class="pf"><i class="im im-perico"></i></span>Aves</button><button type="button" role="tab" class="" data-p="roedores"><span class="pf"><i class="im im-rata"></i></span>Roedores</button><button type="button" role="tab" class="" data-p="agro"><span class="pf"><i class="im im-caballos"></i></span>Agropecuario</button></div>
    <div class="ppanel on" id="pp-perros" role="tabpanel"><div class="pgal"><figure class="pol" style="--r:-4deg"><div class="ph"><i class="im im-salchicha"></i></div></figure><figure class="pol" style="--r:3deg"><div class="ph"><i class="im im-perro-clasico"></i></div></figure></div><div class="ptxt"><h3>Perros</h3><p>Vacunas, desparasitación y control de peso</p><ul class="check"><li>Vacuna antirrábica y múltiple</li><li>Control de peso y alimentación</li><li>Desparasitación al día</li></ul></div></div>
    <div class="ppanel" id="pp-gatos" role="tabpanel"><div class="pgal"><figure class="pol" style="--r:-4deg"><div class="ph"><i class="im im-gatito"></i></div></figure><figure class="pol" style="--r:3deg"><div class="ph"><i class="im im-gato-adulto"></i></div></figure></div><div class="ptxt"><h3>Gatos</h3><p>Esterilización, vacunas y revisiones</p><ul class="check"><li>Esterilización</li><li>Vacuna triple felina</li><li>Chequeo anual</li></ul></div></div>
    <div class="ppanel" id="pp-conejos" role="tabpanel"><div class="pgal"><figure class="pol" style="--r:-4deg"><div class="ph"><i class="im im-conejo-gris"></i></div></figure><figure class="pol" style="--r:3deg"><div class="ph"><i class="im im-conejo-blanco"></i></div></figure></div><div class="ptxt"><h3>Conejos</h3><p>Chequeos y alimentación</p><ul class="check"><li>Revisión de dientes y uñas</li><li>Guía de alimentación</li><li>Control de peso</li></ul></div></div>
    <div class="ppanel" id="pp-aves" role="tabpanel"><div class="pgal"><figure class="pol" style="--r:-4deg"><div class="ph"><i class="im im-perico"></i></div></figure><figure class="pol" style="--r:3deg"><div class="ph"><i class="im im-pajaros"></i></div></figure></div><div class="ptxt"><h3>Aves</h3><p>Revisión y cuidados especiales</p><ul class="check"><li>Revisión de plumaje y pico</li><li>Alimentación y suplementos</li><li>Control de parásitos</li></ul></div></div>
    <div class="ppanel" id="pp-roedores" role="tabpanel"><div class="pgal"><figure class="pol" style="--r:-4deg"><div class="ph"><i class="im im-rata"></i></div></figure></div><div class="ptxt"><h3>Roedores</h3><p>Hámsters, cuyos y más</p><ul class="check"><li>Chequeo general</li><li>Alimentación y vitaminas</li><li>Cuidado de dientes</li></ul></div></div>
    <div class="ppanel" id="pp-agro" role="tabpanel"><div class="pgal"><figure class="pol" style="--r:-4deg"><div class="ph"><i class="im im-caballos"></i></div></figure><figure class="pol" style="--r:3deg"><div class="ph"><i class="im im-revision-ganado"></i></div></figure><figure class="pol" style="--r:-2deg"><div class="ph"><i class="im im-laboratorio"></i></div></figure></div><div class="ptxt"><h3>Agropecuario</h3><p>Ganado, caballos y animales de granja</p><ul class="check"><li>Revisión con ultrasonido</li><li>Análisis de laboratorio</li><li>Visitas a campo y control del hato</li></ul></div></div>
</section>

<section class="sec c galeria" id="galeria">
    <div class="sello"><b>🐾</b>cuidado<br>animal</div>
    <div class="label">NUESTRO ÁLBUM</div>
    <h2 class="title">Cada mascota tiene su historia</h2>
    <p class="desc">Fotos, recuerdos y cuidados: en PETKO cada paciente es parte de la familia.</p>
    <div class="pols">
        <figure class="pol" style="--r:-4deg"><div class="ph"><i class="im im-salchicha" role="img" aria-label="Perro salchicha"></i>🐶</div><figcaption>Dulce compañero</figcaption></figure>
        <figure class="pol" style="--r:3deg"><div class="ph"><i class="im im-gatito" role="img" aria-label="Gatito"></i>🐱</div><figcaption>Gatito curioso</figcaption></figure>
        <figure class="pol" style="--r:-2deg"><div class="ph"><i class="im im-conejo-gris" role="img" aria-label="Conejo"></i>🐰</div><figcaption>Orejitas caídas</figcaption></figure>
        <figure class="pol" style="--r:5deg"><div class="ph"><i class="im im-perico" role="img" aria-label="Perico"></i>🦜</div><figcaption>Perico coqueto</figcaption></figure>
        <figure class="pol" style="--r:-5deg"><div class="ph"><i class="im im-perro-clasico" role="img" aria-label="Perro"></i>🐶</div><figcaption>Mirada tierna</figcaption></figure>
        <figure class="pol" style="--r:2deg"><div class="ph"><i class="im im-gato-adulto" role="img" aria-label="Gato"></i>🐱</div><figcaption>Elegante y atento</figcaption></figure>
        <figure class="pol" style="--r:-3deg"><div class="ph"><i class="im im-conejo-blanco" role="img" aria-label="Conejo blanco"></i>🐰</div><figcaption>Entre flores</figcaption></figure>
        <figure class="pol" style="--r:4deg"><div class="ph"><i class="im im-pajaros" role="img" aria-label="Aves"></i>🐦</div><figcaption>Dúo de aves</figcaption></figure>
        <figure class="pol" style="--r:-2deg"><div class="ph"><i class="im im-rata" role="img" aria-label="Rata"></i>🐭</div><figcaption>Pequeño explorador</figcaption></figure>
        <figure class="pol" style="--r:3deg"><div class="ph"><i class="im im-caballos" role="img" aria-label="Caballos y ganado"></i>🐴</div><figcaption>Campo y ganado</figcaption></figure>
        <figure class="pol" style="--r:-4deg"><div class="ph"><i class="im im-revision-ganado" role="img" aria-label="Revisión de ganado"></i>🩺</div><figcaption>Revisión con ultrasonido</figcaption></figure>
        <figure class="pol" style="--r:2deg"><div class="ph"><i class="im im-laboratorio" role="img" aria-label="Muestras de laboratorio"></i>🧪</div><figcaption>Análisis de laboratorio</figcaption></figure>
    </div>
    <div class="botica">
        <span><b>🩺</b>Estetoscopio</span><span><b>💉</b>Vacunas</span><span><b>💊</b>Tónicos y vitaminas</span>
        <span><b>🦴</b>Huesitos</span><span><b>🧴</b>Shampoo medicado</span><span><b>🌡️</b>Termómetro</span><span><b>🧸</b>Juguetes</span>
    </div>
</section>

<section class="sec" id="nosotros">
    <div class="split">
        <div>
            <div class="label">PENSADO PARA VETERINARIAS</div>
            <h2>Tu veterinaria necesita más que una caja.</h2>
            <p>Administrar una veterinaria implica mucho más que realizar ventas. También necesitas conocer a cada paciente, controlar productos, existencias, sucursales y usuarios.</p>
            <ul class="check"><li>Ficha clínica de cada mascota</li><li>Control de vacunas y desparasitación</li><li>Registro de ventas</li><li>Inventario actualizado</li><li>Administración de sucursales</li></ul>
        </div>
        <div class="panel p1"><div class="mini">
            <h3>🐶 Luna · Labrador · 3 años</h3>
            <div class="r"><span>Antirrábica</span><strong class="good">Al día</strong></div>
            <div class="r"><span>Parvovirus</span><strong class="good">Al día</strong></div>
            <div class="r"><span>Desparasitación</span><strong class="bad">Vence en 5 días</strong></div>
            <div class="r"><span>Próxima cita</span><strong>24 nov · 10:30</strong></div>
        </div></div>
    </div>
</section>

<section class="sec alt" id="ventas">
    <div class="split">
        <div class="panel p2"><div class="mini">
            <h3>🐱 Cuenta de Michi</h3>
            <div class="r"><span>Consulta general</span><strong>$350</strong></div>
            <div class="r"><span>Medicamento</span><strong>$280</strong></div>
            <div class="r"><span>Arenero y accesorio</span><strong>$190</strong></div>
            <div class="tot"><span>Total</span><span>$820</span></div>
        </div></div>
        <div>
            <div class="label">PUNTO DE VENTA</div>
            <h2>Cobra servicios y productos en una sola cuenta.</h2>
            <p>Registra cada operación desde el punto de venta, ligada a la mascota que atendiste, y mantén la información de tu negocio organizada.</p>
            <ul class="check"><li>Registro de ventas</li><li>Servicios y productos veterinarios</li><li>Control por sucursal</li><li>Actualización del inventario</li></ul>
        </div>
    </div>
</section>

<section class="sec" id="inventario">
    <div class="split">
        <div>
            <div class="label">INVENTARIO</div>
            <h2>Mantén tus productos bajo control.</h2>
            <p>Con PETKO puedes consultar las existencias de alimento, medicamentos y vacunas, y mantener organizada la información de cada sucursal.</p>
            <ul class="check"><li>Consulta de existencias</li><li>Organización por productos</li><li>Control por sucursal</li><li>Actualización después de una venta</li></ul>
        </div>
        <div class="tbl">
            <h3>Inventario de productos</h3>
            <div class="tr th"><span>Producto</span><span>Existencia</span><span>Estado</span></div>
            <div class="tr"><span>🍖 Alimento</span><span>125</span><span class="good">Disponible</span></div>
            <div class="tr"><span>💊 Medicamentos</span><span>48</span><span class="good">Disponible</span></div>
            <div class="tr"><span>🦴 Accesorios</span><span>73</span><span class="good">Disponible</span></div>
            <div class="tr"><span>💉 Vacunas</span><span>8</span><span class="bad">Bajo</span></div>
        </div>
    </div>
</section>

<section class="sec alt" id="citas">
    <div class="split">
        <div class="tbl">
            <h3>📅 Citas de hoy</h3>
            <div class="tr th"><span>Paciente</span><span>Hora</span><span>Servicio</span></div>
            <div class="tr"><span>🐶 Luna</span><span>09:00</span><span class="good">Vacuna</span></div>
            <div class="tr"><span>🐱 Michi</span><span>10:30</span><span class="good">Consulta</span></div>
            <div class="tr"><span>🐰 Toby</span><span>12:00</span><span class="good">Revisión</span></div>
            <div class="tr"><span>🦜 Kiwi</span><span>16:00</span><span class="good">Control</span></div>
        </div>
        <div>
            <div class="label">CITAS Y RECORDATORIOS</div>
            <h2>Cada mascota llega a su cita.</h2>
            <p>Organiza la agenda de consultas, vacunas y estética, y no dejes pasar ninguna fecha importante de tus pacientes.</p>
            <ul class="check"><li>Agenda diaria y semanal</li><li>Recordatorios de vacunas</li><li>Historial por mascota</li></ul>
        </div>
    </div>
</section>

<section class="sec c" id="sucursales">
    <div class="label">MULTI-SUCURSAL</div>
    <h2 class="title">Una sola plataforma para todas tus sucursales</h2>
    <p class="desc">Centraliza la información y facilita la administración de cada punto de venta de tu negocio veterinario.</p>
    <div class="grid g3">
        <div class="card"><div class="ico">🏪</div><h3>Sucursal</h3><p>Administra los productos y operaciones correspondientes a cada sucursal.</p></div>
        <div class="card"><div class="ico">👨‍💼</div><h3>Gerentes</h3><p>Los responsables de cada sede pueden gestionar las operaciones de su sucursal.</p></div>
        <div class="card"><div class="ico">👩‍💻</div><h3>Cajeros</h3><p>Realizan las ventas y consultan la información necesaria para trabajar.</p></div>
    </div>
</section>

<section class="sec alt c">
    <div class="label">SIMPLE Y ORGANIZADO</div>
    <h2 class="title">Comienza a utilizar PETKO</h2>
    <p class="desc">Organiza las operaciones de tu veterinaria desde un solo sistema.</p>
    <div class="grid g3">
        <div class="card"><div class="num">01</div><h3>Registra</h3><p>Agrega tus productos, sucursales, usuarios y a tus pacientes al sistema.</p></div>
        <div class="card"><div class="num">02</div><h3>Administra</h3><p>Controla inventario, citas y operaciones de cada sucursal.</p></div>
        <div class="card"><div class="num">03</div><h3>Vende</h3><p>Registra las ventas y mantén actualizado tu inventario.</p></div>
    </div>
</section>

<section class="sec c">
    <div class="label">¿POR QUÉ PETKO?</div>
    <h2 class="title">Menos complicaciones. Más control.</h2>
    <div class="grid g4">
        <div class="benefit"><strong>🐾 Especializado</strong><p>Diseñado pensando en las necesidades de negocios veterinarios.</p></div>
        <div class="benefit"><strong>⚡ Sencillo</strong><p>Una interfaz pensada para facilitar las operaciones diarias.</p></div>
        <div class="benefit"><strong>📦 Organizado</strong><p>Mantén tus productos e información organizada.</p></div>
        <div class="benefit"><strong>🏪 Centralizado</strong><p>Gestiona las diferentes sucursales desde un mismo sistema.</p></div>
    </div>
</section>

<section class="cta">
    <span class="fl" style="left:6%;top:14%" aria-hidden="true">🐶</span>
    <span class="fl" style="right:7%;top:18%;animation-delay:1.5s" aria-hidden="true">🐱</span>
    <span class="fl" style="left:12%;bottom:10%;animation-delay:3s" aria-hidden="true">🐰</span>
    <span class="fl" style="right:12%;bottom:12%;animation-delay:2s" aria-hidden="true">🦜</span>
    <h2>Tu veterinaria necesita control.</h2>
    <p>Pacientes, ventas, inventario y sucursales en un solo lugar.</p>
    <a href="<?= e($ctaHref) ?>" class="ps-b1">Comenzar con PETKO</a>
</section>

<footer>
    <a class="logo" href="#inicio" aria-label="PETKO"><img src="../../../img/petko/logo_amarillo.png" alt="PETKO Veterinaria y Ganadería"></a>
    <p>© 2026 PETKO · Punto de Venta e Inventario para Veterinarias</p>
</footer>

<script nonce="<?= e($nonce) ?>">
(function () {
    const wrap = document.getElementById('inicio');
    const sticky = wrap.querySelector('.ps-sticky');
    const steps = [...wrap.querySelectorAll('.ps-step')];
    const sets = [...wrap.querySelectorAll('.ps-set')];
    const rail = [...wrap.querySelectorAll('.ps-rail button')];
    const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
    const T = [0, .25, .45, .65, 1];
    const clamp = (v, a, b) => Math.min(b, Math.max(a, v));
    let last = -1, ticking = false;

    function render() {
        ticking = false;
        let p = 0;
        if (!reduce) {
            const total = wrap.offsetHeight - sticky.offsetHeight;
            p = clamp(-wrap.getBoundingClientRect().top / total, 0, 1);
        }
        const idx = p < .2 ? 0 : p < .4 ? 1 : p < .6 ? 2 : p < .8 ? 3 : 4;
        if (idx !== last) {
            last = idx;
            steps.forEach((s, i) => s.classList.toggle('on', i === idx));
            sets.forEach((s, i) => s.classList.toggle('on', i === idx));
            rail.forEach((d, i) => d.classList.toggle('on', i === idx));
        }
    }
    const onScroll = () => { if (!ticking) { ticking = true; requestAnimationFrame(render); } };
    addEventListener('scroll', onScroll, { passive: true });
    addEventListener('resize', onScroll);

    // Mouse: luz y movimiento suave de las fotos
    if (!reduce) {
        sticky.addEventListener('pointermove', ev => {
            if (ev.pointerType === 'touch') return;
            const b = sticky.getBoundingClientRect(), x = (ev.clientX - b.left) / b.width, y = (ev.clientY - b.top) / b.height;
            sticky.style.setProperty('--mx', x * 100 + '%'); sticky.style.setProperty('--my', y * 100 + '%');
            sticky.style.setProperty('--px', (x * 2 - 1).toFixed(3)); sticky.style.setProperty('--py', (y * 2 - 1).toFixed(3));
        });
        sticky.addEventListener('pointerleave', () => { sticky.style.setProperty('--px', 0); sticky.style.setProperty('--py', 0); });
    }

    // Clic en riel y chips: saltar al paso
    wrap.querySelectorAll('[data-go]').forEach(el => el.addEventListener('click', () => {
        const total = wrap.offsetHeight - sticky.offsetHeight;
        const top = wrap.getBoundingClientRect().top + scrollY;
        scrollTo({ top: top + T[+el.dataset.go] * total, behavior: reduce ? 'auto' : 'smooth' });
    }));

    render();
})();
</script>

<script nonce="<?= e($nonce) ?>">
    // ===== MENÚ: se oculta al bajar y vuelve al subir o acercar el mouse =====
    const nav = document.querySelector('.navbar');
    let lastY = scrollY;
    addEventListener('scroll', () => {
        const y = scrollY;
        nav.classList.toggle('solid', y > 40);
        if (y > lastY + 4 && y > 120) nav.classList.add('hide');
        else if (y < lastY - 4 || y <= 120) nav.classList.remove('hide');
        lastY = y;
    }, { passive: true });
    addEventListener('pointermove', e => { if (e.clientY < 70) nav.classList.remove('hide'); });

    // ===== PESTAÑAS DE MASCOTAS =====
    document.querySelectorAll('.ptabs button').forEach(b => b.addEventListener('click', () => {
        document.querySelectorAll('.ptabs button').forEach(x => x.classList.toggle('on', x === b));
        document.querySelectorAll('.ppanel').forEach(p => p.classList.toggle('on', p.id === 'pp-' + b.dataset.p));
    }));

    // ===== ALMACENAMIENTO (no falla si el navegador lo bloquea) =====
    function petkoGet(k) { try { return localStorage.getItem(k); } catch (err) { return null; } }
    function petkoSet(k, v) { try { localStorage.setItem(k, v); } catch (err) {} }

    // ===== MODO OSCURO (persistente) =====
    const darkModeBtn = document.getElementById("darkModeBtn");
    function updateDarkLabels() {
        const dark = document.body.classList.contains("dark-mode");
        darkModeBtn.textContent = dark ? "☀️" : "🌙";
        const t = (typeof homeInEnglish !== "undefined" && homeInEnglish)
            ? (dark ? "Switch to light mode" : "Switch to dark mode")
            : (dark ? "Cambiar a modo claro" : "Cambiar a modo oscuro");
        darkModeBtn.title = t;
        darkModeBtn.setAttribute("aria-label", t);
        darkModeBtn.setAttribute("aria-pressed", String(dark));
    }
    darkModeBtn.addEventListener("click", function () {
        document.body.classList.toggle("dark-mode");
        petkoSet("petko_dark", document.body.classList.contains("dark-mode") ? "1" : "0");
        updateDarkLabels();
    });

    // ===== IDIOMA ES / EN =====
    const homeTranslations = {
        'Inicio': 'Home', 'Funciones': 'Features', 'Mascotas': 'Pets', 'Ventas': 'Sales', 'Inventario': 'Inventory',
        'Sucursales': 'Branches', 'Nosotros': 'About us', 'Iniciar sesión': 'Sign in', 'Cerrar sesión': 'Sign out',
        'PETKO · CONTROL INTELIGENTE PARA VETERINARIAS': '🐾 SOFTWARE FOR VETERINARY CLINICS',
        'Tu veterinaria,': 'Your veterinary clinic,', 'en otro nivel.': 'on another level.',
        'Consultas, vacunas, ventas, inventario y sucursales bajo control. Perros, gatos, aves y conejos: cada mascota con su ficha y tu negocio siempre en orden.': 'Visits, vaccines, sales, inventory and branches under control. Dogs, cats, birds and rabbits: every pet with its own record and your business always in order.',
        'Comenzar ahora': 'Get started', 'Explorar PETKO': 'Discover PETKO', 'Comenzar con PETKO': 'Get started with PETKO',
        '🛒 Punto de venta': '🛒 Point of sale', '📦 Inventario': '📦 Inventory', '📅 Citas': '📅 Appointments', '🐾 Mascotas': '🐾 Pets',
        'Prueba las tarjetas: quita servicios de la cuenta o repón las vacunas.': 'Try the cards: remove services from the bill or restock the vaccines.',
        'Cobra la consulta y la vacuna.': 'Charge the visit and the vaccine.',
        'Cobra servicios, medicamentos y alimento en una sola cuenta por mascota, y descuenta el inventario al instante.': 'Charge services, medicine and food in a single bill per pet, and update your inventory instantly.',
        'Servicios y productos veterinarios': 'Veterinary services and products', 'Cuenta por paciente': 'Bill per patient',
        'Que nunca falte una vacuna.': 'Never run out of vaccines.',
        'Existencias de alimento, medicamentos y vacunas por sucursal. Lo que está por agotarse aparece marcado como bajo.': 'Stock of food, medicine and vaccines by branch. Items running out are flagged as low.',
        'Control de vacunas y medicamentos': 'Vaccine and medicine control',
        'Cada mascota, su agenda.': 'Every pet, its own schedule.',
        'Citas, recordatorios y fichas clínicas para tus pacientes, con gerentes y cajeros trabajando en cada sucursal.': 'Appointments, reminders and medical records for your patients, with managers and cashiers working at every branch.',
        'Citas y recordatorios': 'Appointments and reminders', 'Ficha clínica por mascota': 'Medical record per pet', 'Gerentes y cajeros': 'Managers and cashiers',
        'Todo junto': 'All together', 'en PETKO.': 'in PETKO.',
        'Pacientes, ventas, inventario y sucursales en un solo lugar.': 'Patients, sales, inventory and branches in one place.',
        'Ver funciones': 'See features',
        '🐶 Cuenta de Luna': "🐶 Luna's bill", 'Consulta general': 'General checkup', 'Vacuna antirrábica': 'Rabies vaccine',
        'Desparasitante': 'Dewormer', 'Alimento premium': 'Premium food',
        'Toca un concepto para quitarlo o agregarlo.': 'Tap an item to remove or add it.',
        'Vacunas': 'Vaccines', '📅 Citas': '📅 Appointments', 'Consultas': 'Visits', 'Vacunaciones': 'Vaccinations',
        'Hoy': 'Today', 'Semana': 'Week',
        'Una solución pensada para': 'A solution designed for',
        'veterinarias que quieren tener el control de su negocio.': 'veterinary clinics that want control of their business.',
        'TODO EN UN SOLO LUGAR': 'EVERYTHING IN ONE PLACE',
        'Todo lo que tu veterinaria necesita': 'Everything your veterinary clinic needs',
        'PETKO reúne las herramientas necesarias para atender a tus pacientes y administrar tu negocio de manera sencilla y organizada.': 'PETKO brings together the tools you need to care for your patients and manage your business simply and efficiently.',
        'Consultas y ficha clínica': 'Visits and medical records',
        'Guarda el historial de cada mascota: consultas, diagnósticos, peso y tratamientos.': 'Keep every pet\'s history: visits, diagnoses, weight and treatments.',
        'Vacunas y desparasitación': 'Vaccines and deworming',
        'Lleva el control de cada aplicación y sabe cuándo toca la siguiente.': 'Track every dose and know when the next one is due.',
        'Organiza la agenda del día y recuerda a los dueños la visita de su mascota.': 'Organize the daily schedule and remind owners about their pet\'s visit.',
        'Estética y baño': 'Grooming and bath',
        'Registra los servicios de baño, corte y estética junto con el resto de la cuenta.': 'Record bath, haircut and grooming services along with the rest of the bill.',
        'Punto de venta': 'Point of sale', 'Usuarios': 'Users',
        'Registra las ventas de alimentos, medicamentos, accesorios y servicios de manera rápida.': 'Record food, medicine, accessory and service sales quickly.',
        'Mantén controladas las existencias de los productos de tu veterinaria.': 'Keep track of your veterinary clinic inventory.',
        'Administra diferentes sucursales desde un mismo sistema.': 'Manage different branches from one system.',
        'Gestiona los accesos y funciones de gerentes y cajeros.': 'Manage manager and cashier access and permissions.',
        'PARA CADA PACIENTE': 'FOR EVERY PATIENT', 'Un expediente para cada mascota': 'A record for every pet',
        'Desde un cachorro hasta un loro: registra a todos los animales que llegan a tu veterinaria.': 'From a puppy to a parrot: register every animal that comes to your clinic.',
        'Perros': 'Dogs', 'Gatos': 'Cats', 'Conejos': 'Rabbits', 'Aves': 'Birds', 'Roedores': 'Rodents', 'Agropecuario': 'Agricultural',
        'Vacunas, desparasitación y control de peso': 'Vaccines, deworming and weight control',
        'Esterilización, vacunas y revisiones': 'Spaying/neutering, vaccines and checkups',
        'Chequeos y alimentación': 'Checkups and nutrition', 'Revisión y cuidados especiales': 'Checkups and special care',
        'Hámsters, cuyos y más': 'Hamsters, guinea pigs and more', 'Ganado, caballos y animales de granja': 'Cattle, horses and farm animals',
        'PENSADO PARA VETERINARIAS': 'DESIGNED FOR VETERINARY CLINICS',
        'Tu veterinaria necesita más que una caja.': 'Your veterinary clinic needs more than a register.',
        'Administrar una veterinaria implica mucho más que realizar ventas. También necesitas conocer a cada paciente, controlar productos, existencias, sucursales y usuarios.': 'Managing a veterinary clinic involves more than making sales. You also need to know every patient and control products, inventory, branches, and users.',
        'Ficha clínica de cada mascota': 'Medical record for every pet',
        'Control de vacunas y desparasitación': 'Vaccine and deworming control',
        'Registro de ventas': 'Sales recording', 'Inventario actualizado': 'Updated inventory',
        'Administración de sucursales': 'Branch management',
        'Luna · Labrador · 3 años': 'Luna · Labrador · 3 years old',
        'Antirrábica': 'Rabies', 'Parvovirus': 'Parvovirus', 'Desparasitación': 'Deworming',
        'Al día': 'Up to date', 'Vence en 5 días': 'Due in 5 days', 'Próxima cita': 'Next appointment', '24 nov · 10:30': 'Nov 24 · 10:30 AM',
        'PUNTO DE VENTA': 'POINT OF SALE', '🐱 Cuenta de Michi': "🐱 Michi's bill",
        'Medicamento': 'Medicine', 'Arenero y accesorio': 'Litter box and accessory', 'Total': 'Total',
        'Cobra servicios y productos en una sola cuenta.': 'Charge services and products in a single bill.',
        'Registra cada operación desde el punto de venta, ligada a la mascota que atendiste, y mantén la información de tu negocio organizada.': 'Record every transaction at the point of sale, linked to the pet you treated, and keep your business information organized.',
        'Control por sucursal': 'Branch-based control', 'Actualización del inventario': 'Inventory updates',
        'INVENTARIO': 'INVENTORY', 'Mantén tus productos bajo control.': 'Keep your products under control.',
        'Con PETKO puedes consultar las existencias de alimento, medicamentos y vacunas, y mantener organizada la información de cada sucursal.': 'With PETKO, you can check stock of food, medicine and vaccines, and keep each branch organized.',
        'Consulta de existencias': 'Check inventory', 'Organización por productos': 'Organized by product',
        'Actualización después de una venta': 'Updated after every sale',
        'Inventario de productos': 'Product inventory', 'Producto': 'Product', 'Existencia': 'Stock', 'Estado': 'Status',
        'Disponible': 'Available', 'Bajo': 'Low', 'Alimento': 'Food', 'Medicamentos': 'Medicine', 'Accesorios': 'Accessories',
        '📅 Citas de hoy': "📅 Today's appointments", 'Paciente': 'Patient', 'Hora': 'Time', 'Servicio': 'Service',
        'Vacuna': 'Vaccine', 'Consulta': 'Visit', 'Revisión': 'Checkup', 'Control': 'Follow-up',
        'CITAS Y RECORDATORIOS': 'APPOINTMENTS AND REMINDERS', 'Cada mascota llega a su cita.': 'Every pet makes it to its appointment.',
        'Organiza la agenda de consultas, vacunas y estética, y no dejes pasar ninguna fecha importante de tus pacientes.': 'Organize the schedule for visits, vaccines and grooming, and never miss an important date for your patients.',
        'Agenda diaria y semanal': 'Daily and weekly schedule', 'Recordatorios de vacunas': 'Vaccine reminders', 'Historial por mascota': 'History per pet',
        'MULTI-SUCURSAL': 'MULTI-BRANCH', 'Una sola plataforma para todas tus sucursales': 'One platform for all your branches',
        'Centraliza la información y facilita la administración de cada punto de venta de tu negocio veterinario.': 'Centralize information and simplify the management of every veterinary branch.',
        'Sucursal': 'Branch', 'Gerentes': 'Managers', 'Cajeros': 'Cashiers',
        'Administra los productos y operaciones correspondientes a cada sucursal.': 'Manage the products and operations for each branch.',
        'Los responsables de cada sede pueden gestionar las operaciones de su sucursal.': 'Branch managers can manage their location operations.',
        'Realizan las ventas y consultan la información necesaria para trabajar.': 'They make sales and access the information they need to work.',
        'SIMPLE Y ORGANIZADO': 'SIMPLE AND ORGANIZED', 'Comienza a utilizar PETKO': 'Start using PETKO',
        'Organiza las operaciones de tu veterinaria desde un solo sistema.': 'Organize your veterinary clinic operations from one system.',
        'Registra': 'Register', 'Administra': 'Manage', 'Vende': 'Sell',
        'Agrega tus productos, sucursales, usuarios y a tus pacientes al sistema.': 'Add your products, branches, users and patients to the system.',
        'Controla inventario, citas y operaciones de cada sucursal.': 'Control inventory, appointments and every branch operation.',
        'Registra las ventas y mantén actualizado tu inventario.': 'Record sales and keep your inventory updated.',
        '¿POR QUÉ PETKO?': 'WHY PETKO?', 'Menos complicaciones. Más control.': 'Less hassle. More control.',
        '🐾 Especializado': '🐾 Specialized', '⚡ Sencillo': '⚡ Simple', '📦 Organizado': '📦 Organized', '🏪 Centralizado': '🏪 Centralized',
        'Diseñado pensando en las necesidades de negocios veterinarios.': 'Designed for the needs of veterinary businesses.',
        'Una interfaz pensada para facilitar las operaciones diarias.': 'An interface designed to simplify daily operations.',
        'Mantén tus productos e información organizada.': 'Keep your products and information organized.',
        'Gestiona las diferentes sucursales desde un mismo sistema.': 'Manage different branches from one system.',
        'Tu veterinaria necesita control.': 'Your veterinary clinic needs control.',
        '© 2026 PETKO · Punto de Venta e Inventario para Veterinarias': '© 2026 PETKO · Point of Sale and Inventory for Veterinary Clinics',
        'NUESTRO ÁLBUM': 'OUR ALBUM', 'Cada mascota tiene su historia': 'Every pet has a story',
        'Fotos, recuerdos y cuidados: en PETKO cada paciente es parte de la familia.': 'Photos, memories and care: at PETKO every patient is part of the family.',
        'Luna, día de vacuna': 'Luna, vaccine day', 'Michi, chequeo anual': 'Michi, yearly checkup', 'Toby, revisión': 'Toby, checkup',
        'Kiwi, control': 'Kiwi, follow-up', 'Consulta con cariño': 'Visits with love', 'Vacunas al día': 'Vaccines up to date',
        'Estetoscopio': 'Stethoscope', 'Tónicos y vitaminas': 'Tonics and vitamins', 'Huesitos': 'Chew bones',
        'Shampoo medicado': 'Medicated shampoo', 'Termómetro': 'Thermometer', 'Juguetes': 'Toys',
        'Dulce compañero': 'Sweet companion', 'Gatito curioso': 'Curious kitten', 'Orejitas caídas': 'Floppy ears', 'Perico coqueto': 'Charming cockatiel',
        'Mirada tierna': 'Tender gaze', 'Elegante y atento': 'Elegant and alert', 'Entre flores': 'Among the flowers', 'Dúo de aves': 'Pair of birds',
        'Pequeño explorador': 'Little explorer', 'Campo y ganado': 'Countryside and cattle', 'Revisión con ultrasonido': 'Ultrasound checkup', 'Análisis de laboratorio': 'Lab testing',
        'Vacuna antirrábica y múltiple': 'Rabies and multi-disease vaccine', 'Control de peso y alimentación': 'Weight and nutrition control',
        'Desparasitación al día': 'Deworming up to date', 'Esterilización': 'Spaying/neutering', 'Vacuna triple felina': 'Feline triple vaccine', 'Chequeo anual': 'Yearly checkup',
        'Revisión de dientes y uñas': 'Teeth and nail checkup', 'Guía de alimentación': 'Feeding guide', 'Control de peso': 'Weight control',
        'Revisión de plumaje y pico': 'Feather and beak checkup', 'Alimentación y suplementos': 'Food and supplements', 'Control de parásitos': 'Parasite control',
        'Chequeo general': 'General checkup', 'Alimentación y vitaminas': 'Food and vitamins', 'Cuidado de dientes': 'Dental care',
        'Revisión con ultrasonido': 'Ultrasound checkup', 'Análisis de laboratorio': 'Lab testing', 'Visitas a campo y control del hato': 'Farm visits and herd control',
        'cuidado': 'pet', 'animal': 'care'
    };

    const originalHomeTexts = new Map();
    let homeInEnglish = false;

    function toggleHomeLanguage() {
        const entries = Object.entries(homeTranslations).sort((a, b) => b[0].length - a[0].length);
        document.querySelectorAll('body *:not(script):not(style)').forEach((element) => {
            element.childNodes.forEach((node) => {
                if (node.nodeType !== Node.TEXT_NODE || node.parentElement?.hasAttribute('data-i18n')) return;
                if (!originalHomeTexts.has(node)) originalHomeTexts.set(node, node.textContent);
                const originalText = originalHomeTexts.get(node);
                const leading = originalText.match(/^\s*/)[0];
                const trailing = originalText.match(/\s*$/)[0];
                if (!homeInEnglish) { node.textContent = originalText; return; }
                let translated = originalText.trim().replace(/\s+/g, ' ');
                entries.forEach(([es, en]) => { translated = translated.split(es).join(en); });
                node.textContent = leading + translated + trailing;
            });
        });
        document.documentElement.lang = homeInEnglish ? 'en' : 'es';
        const langBtn = document.getElementById('languageToggle');
        const lt = homeInEnglish ? 'Cambiar a español' : 'Switch to English';
        langBtn.textContent = homeInEnglish ? 'ES' : 'EN';
        langBtn.title = lt;
        langBtn.setAttribute('aria-label', lt);
        document.title = homeInEnglish ? 'PETKO | Veterinary Clinic Management' : 'PETKO | Gestión para Veterinarias';
        updateDarkLabels();
    }

    document.getElementById('languageToggle').addEventListener('click', () => {
        homeInEnglish = !homeInEnglish;
        petkoSet('petko_lang', homeInEnglish ? 'en' : 'es');
        toggleHomeLanguage();
    });

    // Aplicar el idioma y el modo guardados
    homeInEnglish = petkoGet('petko_lang') === 'en';
    toggleHomeLanguage();
</script>

</body>
</html>
