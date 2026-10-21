<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PETKO | Gestión para Veterinarias</title>
<style>

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
.grid{display:grid;gap:20px;margin-top:56px;text-align:left}.g4{grid-template-columns:repeat(4,1fr)}.g3{grid-template-columns:repeat(3,1fr)}
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
.cta{margin:80px 7%;padding:85px 30px;border-radius:28px;text-align:center;color:#f2f8ff;background:linear-gradient(125deg,#08142c,#1d4e9e)}
.cta h2{font-size:clamp(32px,4vw,46px);margin-bottom:18px}.cta p{color:#c4d8f2;font-size:17px;margin-bottom:30px}
footer{padding:55px 7%;background:#060e1f;color:#a9bfdc;display:flex;justify-content:space-between;align-items:center;font-size:14px}

@media(max-width:1000px){.g4{grid-template-columns:repeat(2,1fr)}.g3{grid-template-columns:1fr}.split{flex-direction:column;gap:40px}.split>div{width:100%}}
@media(max-width:860px){.navbar{padding:0 4%}.menu{gap:10px}.menu a:not(.btn-login){display:none}.logo{font-size:26px}}
@media(max-width:600px){.g4{grid-template-columns:1fr}.sec{padding:80px 6%}footer{flex-direction:column;gap:15px;text-align:center}}

/* HERO */
.ps-wrap { position: relative; height: 560vh; background: #0a1428; }
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
.ps-sticky:after {
    content: 'PETKO'; position: absolute; right: -2.5%; bottom: -10%;
    font-size: clamp(150px, 26vw, 390px); line-height: .8; letter-spacing: -.09em; font-weight: 900;
    color: rgba(200,225,255,.03); pointer-events: none;
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
}

/* ===== MODO CLARO (por defecto): todo en tonos claros ===== */
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
body:not(.dark-mode) .ps-sticky:after{color:rgba(30,90,200,.05)}
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

/* ===== MODO OSCURO: tarjetas y paneles también oscuros ===== */
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
}
@media(max-width:860px) and (max-height:720px){.ps-world-box{transform:scale(.5)}}

</style>
</head>
<body>

<header class="navbar">
    <div class="logo">PE<span>T</span>KO</div>
    <nav class="menu">
        <a href="#inicio">Inicio</a>
        <a href="#funciones">Funciones</a>
        <a href="#ventas">Ventas</a>
        <a href="#inventario">Inventario</a>
        <a href="#sucursales">Sucursales</a>
        <a href="#nosotros">Nosotros</a>
        <a href="../../../../inicioSesion.php" class="btn-login">Iniciar sesión</a>
        <button id="darkModeBtn" class="dark-mode-btn" title="Cambiar modo">🌙</button>
        <button id="languageToggle" class="language-btn" type="button" aria-label="Cambiar idioma">EN</button>
    </nav>
</header>

<section class="ps-wrap" id="inicio" aria-label="PETKO: gestión para veterinarias">
    <div class="ps-sticky">

        <div class="ps-rail">
            <button data-go="0" aria-label="Inicio"></button><button data-go="1" aria-label="Ventas"></button>
            <button data-go="2" aria-label="Inventario"></button><button data-go="3" aria-label="Sucursales"></button>
            <button data-go="4" aria-label="Todo junto"></button>
        </div>

        <div class="ps-text">
            <div class="ps-step on">
                <div class="ps-tag">PETKO · CONTROL INTELIGENTE PARA VETERINARIAS</div>
                <h1>Tu veterinaria, <em>en otro nivel.</em></h1>
                <p>Ventas, inventario y sucursales bajo control. La plataforma inteligente que hace que tu negocio veterinario se mueva con la misma energía que tú.</p>
                <div class="ps-btns">
                    <a href="#" class="ps-b1">Comenzar ahora</a>
                    <a href="#funciones" class="ps-b2">Explorar PETKO</a>
                </div>
                <div class="ps-chips">
                    <button data-go="1">🛒 Punto de venta</button><button data-go="2">📦 Inventario</button>
                    <button data-go="3">🏪 Sucursales</button><button data-go="3">👥 Usuarios</button>
                </div>
                <div class="ps-hint">Prueba las tarjetas: quita productos de la venta o repón el inventario.</div>
            </div>

            <div class="ps-step">
                <h2>Cobra en segundos.</h2>
                <p>El punto de venta registra alimentos, medicamentos y accesorios, y descuenta el inventario al instante.</p>
                <ul class="ps-list"><li>Registro de ventas</li><li>Productos veterinarios</li><li>Control por sucursal</li><li>Actualización del inventario</li></ul>
            </div>

            <div class="ps-step">
                <h2>Sabe qué te queda.</h2>
                <p>Existencias por producto y por sucursal. Lo que está por agotarse aparece marcado como bajo.</p>
                <ul class="ps-list"><li>Consulta de existencias</li><li>Organización por productos</li><li>Control por sucursal</li><li>Actualización después de una venta</li></ul>
            </div>

            <div class="ps-step">
                <h2>Cada sucursal, un solo panel.</h2>
                <p>Gerentes y cajeros con sus propios accesos, y toda la información centralizada.</p>
                <ul class="ps-list"><li>Administración de sucursales</li><li>Gerentes y cajeros</li><li>Usuarios con diferentes funciones</li></ul>
            </div>

            <div class="ps-step">
                <h2>Todo junto <em>en PETKO.</em></h2>
                <p>Ventas, inventario y sucursales en un solo lugar.</p>
                <div class="ps-btns">
                    <a href="#" class="ps-b1">Comenzar ahora</a>
                    <a href="#funciones" class="ps-b2">Ver funciones</a>
                </div>
            </div>
        </div>

        <div class="ps-scene">
            <div class="ps-world-box">
                <div class="ps-world" id="ps-world">

                    <div class="ps-card ps-c0">
                        <h3 data-go="1" title="Ver detalle">Nueva venta</h3>
                        <button class="ps-row" data-v="450"><span>Alimento para perro</span><strong>$450</strong></button>
                        <button class="ps-row" data-v="280"><span>Medicamento</span><strong>$280</strong></button>
                        <button class="ps-row" data-v="190"><span>Accesorio</span><strong>$190</strong></button>
                        <button class="ps-row" data-v="120"><span>Shampoo</span><strong>$120</strong></button>
                        <div class="ps-tip">Toca un producto para quitarlo o agregarlo.</div>
                        <div class="ps-total"><span>Total</span><span id="ps-total" data-i18n>$1,040</span></div>
                        <button class="ps-act" id="ps-pay" data-i18n>Cobrar</button>
                    </div>

                    <div class="ps-card ps-c1">
                        <h3 data-go="2" title="Ver detalle">Inventario</h3>
                        <div class="ps-row"><span>Alimentos</span><span class="ps-ok">125</span></div>
                        <div class="ps-row"><span>Medicamentos</span><span class="ps-ok">48</span></div>
                        <div class="ps-row"><span>Agropecuario</span><span class="ps-low" id="ps-qty" data-i18n>8 · Bajo</span></div>
                        <button class="ps-act" id="ps-restock" data-i18n>Reponer +20</button>
                    </div>

                    <div class="ps-card ps-c2">
                        <h3 data-go="3" title="Ver detalle">Sucursales</h3>
                        <div class="ps-tabs" data-i18n><button class="on" data-t="0">Hoy</button><button data-t="1">Semana</button></div>
                        <div class="ps-branch"><span>Centro</span><strong id="ps-v0" data-i18n>$12,400</strong></div>
                        <div class="ps-bar"><b id="ps-b0" style="width:86%"></b></div>
                        <div class="ps-branch"><span>Norte</span><strong id="ps-v1" data-i18n>$8,200</strong></div>
                        <div class="ps-bar"><b id="ps-b1" style="width:58%"></b></div>
                    </div>

                </div>
            </div>
        </div>

    </div>
</section>

<section class="intro">Una solución pensada para <strong>veterinarias que quieren tener el control de su negocio.</strong></section>

<section class="sec c" id="funciones">
    <div class="label">TODO EN UN SOLO LUGAR</div>
    <h2 class="title">Todo lo que tu veterinaria necesita</h2>
    <p class="desc">PETKO reúne las herramientas necesarias para administrar las operaciones de tu negocio de manera sencilla y organizada.</p>
    <div class="grid g4">
        <div class="card"><div class="ico">🛒</div><h3>Punto de venta</h3><p>Registra las ventas de alimentos, medicamentos, accesorios y productos veterinarios de manera rápida.</p></div>
        <div class="card"><div class="ico">📦</div><h3>Inventario</h3><p>Mantén controladas las existencias de los productos de tu veterinaria.</p></div>
        <div class="card"><div class="ico">🏪</div><h3>Sucursales</h3><p>Administra diferentes sucursales desde un mismo sistema.</p></div>
        <div class="card"><div class="ico">👥</div><h3>Usuarios</h3><p>Gestiona los accesos y funciones de gerentes y cajeros.</p></div>
    </div>
</section>

<section class="sec alt" id="nosotros">
    <div class="split">
        <div>
            <div class="label">PENSADO PARA VETERINARIAS</div>
            <h2>Tu veterinaria necesita más que una caja.</h2>
            <p>Administrar una veterinaria implica mucho más que realizar ventas. También necesitas controlar productos, existencias, sucursales y usuarios.</p>
            <ul class="check"><li>Control de productos veterinarios</li><li>Registro de ventas</li><li>Inventario actualizado</li><li>Administración de sucursales</li><li>Usuarios con diferentes funciones</li></ul>
        </div>
        <div class="panel p1"><div class="mini">
            <h3>🐶 Productos veterinarios</h3>
            <div class="r"><span>Alimentos</span><strong>125</strong></div>
            <div class="r"><span>Medicamentos</span><strong>48</strong></div>
            <div class="r"><span>Accesorios</span><strong>73</strong></div>
            <div class="r"><span>Productos agropecuarios</span><strong>91</strong></div>
        </div></div>
    </div>
</section>

<section class="sec" id="ventas">
    <div class="split">
        <div class="panel p2"><div class="mini">
            <h3>Nueva venta</h3>
            <div class="r"><span>Alimento para perro</span><strong>$450</strong></div>
            <div class="r"><span>Medicamento</span><strong>$280</strong></div>
            <div class="r"><span>Accesorio</span><strong>$190</strong></div>
            <div class="tot"><span>Total</span><span>$920</span></div>
        </div></div>
        <div>
            <div class="label">PUNTO DE VENTA</div>
            <h2>Vende de forma rápida y organizada.</h2>
            <p>Registra cada operación desde el punto de venta y mantén la información de tu negocio organizada.</p>
            <ul class="check"><li>Registro de ventas</li><li>Productos veterinarios</li><li>Control por sucursal</li><li>Actualización del inventario</li></ul>
        </div>
    </div>
</section>

<section class="sec alt" id="inventario">
    <div class="split">
        <div>
            <div class="label">INVENTARIO</div>
            <h2>Mantén tus productos bajo control.</h2>
            <p>Con PETKO puedes consultar las existencias de los productos de tu veterinaria y mantener organizada la información de cada sucursal.</p>
            <ul class="check"><li>Consulta de existencias</li><li>Organización por productos</li><li>Control por sucursal</li><li>Actualización después de una venta</li></ul>
        </div>
        <div class="tbl">
            <h3>Inventario de productos</h3>
            <div class="tr th"><span>Producto</span><span>Existencia</span><span>Estado</span></div>
            <div class="tr"><span>Alimento</span><span>125</span><span class="good">Disponible</span></div>
            <div class="tr"><span>Medicamentos</span><span>48</span><span class="good">Disponible</span></div>
            <div class="tr"><span>Accesorios</span><span>73</span><span class="good">Disponible</span></div>
            <div class="tr"><span>Producto agropecuario</span><span>8</span><span class="bad">Bajo</span></div>
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
        <div class="card"><div class="num">01</div><h3>Registra</h3><p>Agrega tus productos, sucursales y usuarios al sistema.</p></div>
        <div class="card"><div class="num">02</div><h3>Administra</h3><p>Controla inventario, usuarios y operaciones de cada sucursal.</p></div>
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
    <h2>Tu veterinaria necesita control.</h2>
    <p>Ventas, inventario y sucursales en un solo lugar.</p>
    <a href="#" class="ps-b1">Comenzar con PETKO</a>
</section>

<footer>
    <div class="logo">PE<span>T</span>KO</div>
    <p>© 2026 PETKO · Punto de Venta e Inventario para Veterinarias</p>
</footer>

<script>
(function () {
    const wrap = document.getElementById('inicio');
    const sticky = wrap.querySelector('.ps-sticky');
    const world = document.getElementById('ps-world');
    const cards = [...wrap.querySelectorAll('.ps-card')];
    const steps = [...wrap.querySelectorAll('.ps-step')];
    const rail = [...wrap.querySelectorAll('.ps-rail button')];
    const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
    const end = [[-165, 0], [165, -112], [165, 112]];
    const T = [0, .24, .42, .6, 1];
    const clamp = (v, a, b) => Math.min(b, Math.max(a, v));
    const ease = t => t < .5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
    let last = -1, ticking = false, tx = 0, ty = 0, cx = 0, cy = 0, raf = 0;

    function render() {
        ticking = false;
        let p = 1;
        if (!reduce) {
            const total = wrap.offsetHeight - sticky.offsetHeight;
            p = clamp((82 - wrap.getBoundingClientRect().top) / total, 0, 1);
        }
        const e = 1 - ease(clamp((p - .68) / .3, 0, 1));
        const rz = (-40 + 18 * Math.min(p / .68, 1)) * e;
        world.style.transform = `rotateX(${58 * e - cy * 6}deg) rotateY(${cx * 8}deg) rotateZ(${rz}deg)`;
        cards.forEach((c, i) => {
            c.style.transform = `translate3d(${end[i][0] * (1 - e)}px, ${end[i][1] * (1 - e)}px, ${(i - 1) * 200 * e}px)`;
        });
        const idx = p < .15 ? 0 : p < .33 ? 1 : p < .51 ? 2 : p < .68 ? 3 : 4;
        if (idx !== last) {
            last = idx;
            steps.forEach((s, i) => s.classList.toggle('on', i === idx));
            rail.forEach((d, i) => d.classList.toggle('on', i === idx));
            cards.forEach((c, i) => {
                const f = idx >= 1 && idx <= 3;
                c.classList.toggle('on', f && i === idx - 1);
                c.style.opacity = f && i !== idx - 1 ? .5 : 1;
            });
        }
    }
    const onScroll = () => { if (!ticking) { ticking = true; requestAnimationFrame(render); } };
    addEventListener('scroll', onScroll, { passive: true });
    addEventListener('resize', onScroll);

    // Mouse: luz + inclinación 3D suave
    function follow() {
        cx += (tx - cx) * .08; cy += (ty - cy) * .08; render();
        raf = (Math.abs(tx - cx) > .002 || Math.abs(ty - cy) > .002) ? requestAnimationFrame(follow) : 0;
    }
    if (!reduce) {
        sticky.addEventListener('pointermove', ev => {
            if (ev.pointerType === 'touch') return;
            const b = sticky.getBoundingClientRect(), x = (ev.clientX - b.left) / b.width, y = (ev.clientY - b.top) / b.height;
            tx = x * 2 - 1; ty = y * 2 - 1;
            sticky.style.setProperty('--mx', x * 100 + '%'); sticky.style.setProperty('--my', y * 100 + '%');
            if (!raf) raf = requestAnimationFrame(follow);
        });
        sticky.addEventListener('pointerleave', () => { tx = ty = 0; if (!raf) raf = requestAnimationFrame(follow); });
    }

    // Clic en riel, chips y títulos de tarjeta: saltar al paso
    wrap.querySelectorAll('[data-go]').forEach(el => el.addEventListener('click', () => {
        const total = wrap.offsetHeight - sticky.offsetHeight;
        const top = wrap.getBoundingClientRect().top + scrollY - 82;
        scrollTo({ top: top + T[+el.dataset.go] * total, behavior: reduce ? 'auto' : 'smooth' });
    }));

    // Tarjeta de venta
    const rows = [...wrap.querySelectorAll('button.ps-row')], tot = document.getElementById('ps-total'), pay = document.getElementById('ps-pay');
    let shown = 1040;
    const sum = () => rows.reduce((s, r) => s + (r.classList.contains('off') ? 0 : +r.dataset.v), 0);
    function tween(to) {
        const from = shown, t0 = performance.now();
        (function f(t) {
            const k = Math.min(1, (t - t0) / 300);
            shown = Math.round(from + (to - from) * k);
            tot.textContent = '$' + shown.toLocaleString('en-US');
            if (k < 1) requestAnimationFrame(f);
        })(t0);
    }
    rows.forEach(r => r.addEventListener('click', () => { r.classList.toggle('off'); tween(sum()); }));
    pay.addEventListener('click', () => {
        if (!sum()) return;
        pay.textContent = 'Venta registrada ✓'; pay.classList.add('done');
        setTimeout(() => { pay.textContent = 'Cobrar'; pay.classList.remove('done'); }, 1600);
    });

    // Tarjeta de inventario
    const qty = document.getElementById('ps-qty'), rb = document.getElementById('ps-restock');
    let restocked = false;
    rb.addEventListener('click', () => {
        restocked = !restocked;
        qty.textContent = restocked ? '28 · Disponible' : '8 · Bajo';
        qty.className = restocked ? 'ps-ok' : 'ps-low';
        rb.textContent = restocked ? 'Repuesto ✓ (deshacer)' : 'Reponer +20';
        rb.classList.toggle('done', restocked);
    });

    // Tarjeta de sucursales: Hoy / Semana
    const data = [[['$12,400', 86], ['$8,200', 58]], [['$71,900', 92], ['$49,300', 63]]];
    wrap.querySelectorAll('.ps-tabs button').forEach(b => b.addEventListener('click', () => {
        wrap.querySelectorAll('.ps-tabs button').forEach(x => x.classList.toggle('on', x === b));
        data[+b.dataset.t].forEach((d, i) => {
            document.getElementById('ps-v' + i).textContent = d[0];
            document.getElementById('ps-b' + i).style.width = d[1] + '%';
        });
    }));

    render();

    // Textos para el botón EN/ES (se suman a tu diccionario)
    addEventListener('DOMContentLoaded', () => {
        if (typeof homeTranslations === 'undefined') return;
        Object.assign(homeTranslations, {
            'Tu veterinaria,': 'Your veterinary clinic,', 'Cobra en segundos.': 'Check out in seconds.',
            'El punto de venta registra alimentos, medicamentos y accesorios, y descuenta el inventario al instante.': 'The point of sale records food, medicine and accessories, and updates your inventory instantly.',
            'Sabe qué te queda.': 'Know what you have left.',
            'Existencias por producto y por sucursal. Lo que está por agotarse aparece marcado como bajo.': 'Stock by product and by branch. Items running out are flagged as low.',
            'Cada sucursal, un solo panel.': 'Every branch, one dashboard.',
            'Gerentes y cajeros con sus propios accesos, y toda la información centralizada.': 'Managers and cashiers with their own access, and all information centralized.',
            'Todo junto': 'All together', 'en PETKO.': 'in PETKO.',
            'Ventas, inventario y sucursales en un solo lugar.': 'Sales, inventory and branches in one place.',
            'Ver funciones': 'See features', 'Gerentes y cajeros': 'Managers and cashiers', 'Agropecuario': 'Agricultural',
            'Prueba las tarjetas: quita productos de la venta o repón el inventario.': 'Try the cards: remove products from the sale or restock inventory.',
            'Toca un producto para quitarlo o agregarlo.': 'Tap a product to remove or add it.',
            'Alimento para perro': 'Dog food', 'Accesorio': 'Accessory', 'Centro': 'Downtown', 'Norte': 'North',
            '🛒 Punto de venta': '🛒 Point of sale', '📦 Inventario': '📦 Inventory', '🏪 Sucursales': '🏪 Branches', '👥 Usuarios': '👥 Users'
        });
    });
})();
</script>

<script>
    // ===== MODO OSCURO =====
    const darkModeBtn = document.getElementById("darkModeBtn");
    darkModeBtn.addEventListener("click", function () {
        document.body.classList.toggle("dark-mode");
        darkModeBtn.innerHTML = document.body.classList.contains("dark-mode") ? "☀️" : "🌙";
    });

    // ===== IDIOMA ES / EN =====
    const homeTranslations = {
        'Inicio': 'Home', 'Funciones': 'Features', 'Ventas': 'Sales', 'Inventario': 'Inventory',
        'Sucursales': 'Branches', 'Nosotros': 'About us', 'Iniciar sesión': 'Sign in',
        'PETKO · CONTROL INTELIGENTE PARA VETERINARIAS': '🐾 SOFTWARE FOR VETERINARY CLINICS',
        'en otro nivel.': 'on another level.',
        'Ventas, inventario y sucursales bajo control. La plataforma inteligente que hace que tu negocio veterinario se mueva con la misma energía que tú.': 'Sales, inventory and branches under control. The smart platform that keeps your veterinary business moving with the same energy you have.',
        'Comenzar ahora': 'Get started', 'Explorar PETKO': 'Discover PETKO', 'Comenzar con PETKO': 'Get started with PETKO',
        'Una solución pensada para': 'A solution designed for',
        'veterinarias que quieren tener el control de su negocio.': 'veterinary clinics that want control of their business.',
        'TODO EN UN SOLO LUGAR': 'EVERYTHING IN ONE PLACE',
        'Todo lo que tu veterinaria necesita': 'Everything your veterinary clinic needs',
        'PETKO reúne las herramientas necesarias para administrar las operaciones de tu negocio de manera sencilla y organizada.': 'PETKO brings together the tools you need to manage your business simply and efficiently.',
        'Punto de venta': 'Point of sale', 'Usuarios': 'Users',
        'Registra las ventas de alimentos, medicamentos, accesorios y productos veterinarios de manera rápida.': 'Record food, medicine, accessories, and veterinary product sales quickly.',
        'Mantén controladas las existencias de los productos de tu veterinaria.': 'Keep track of your veterinary clinic inventory.',
        'Administra diferentes sucursales desde un mismo sistema.': 'Manage different branches from one system.',
        'Gestiona los accesos y funciones de gerentes y cajeros.': 'Manage manager and cashier access and permissions.',
        'PENSADO PARA VETERINARIAS': 'DESIGNED FOR VETERINARY CLINICS',
        'Tu veterinaria necesita más que una caja.': 'Your veterinary clinic needs more than a register.',
        'Administrar una veterinaria implica mucho más que realizar ventas. También necesitas controlar productos, existencias, sucursales y usuarios.': 'Managing a veterinary clinic involves more than making sales. You also need to control products, inventory, branches, and users.',
        'Control de productos veterinarios': 'Veterinary product control', 'Registro de ventas': 'Sales recording',
        'Inventario actualizado': 'Updated inventory', 'Administración de sucursales': 'Branch management',
        'Usuarios con diferentes funciones': 'Users with different permissions',
        'Productos veterinarios': 'Veterinary products', 'Control por sucursal': 'Branch-based control',
        'Actualización del inventario': 'Inventory updates', 'Consulta de existencias': 'Check inventory',
        'Organización por productos': 'Organized by product', 'Actualización después de una venta': 'Updated after every sale',
        'Alimentos': 'Food', 'Medicamentos': 'Medicine', 'Accesorios': 'Accessories', 'Productos agropecuarios': 'Agricultural products',
        'PUNTO DE VENTA': 'POINT OF SALE', 'Nueva venta': 'New sale', 'Total': 'Total',
        'Vende de forma rápida y organizada.': 'Sell quickly and stay organized.',
        'Registra cada operación desde el punto de venta y mantén la información de tu negocio organizada.': 'Record every transaction at the point of sale and keep your business information organized.',
        'INVENTARIO': 'INVENTORY', 'Mantén tus productos bajo control.': 'Keep your products under control.',
        'Con PETKO puedes consultar las existencias de los productos de tu veterinaria y mantener organizada la información de cada sucursal.': 'With PETKO, you can check product stock and keep each branch organized.',
        'Inventario de productos': 'Product inventory', 'Producto': 'Product', 'Existencia': 'Stock', 'Estado': 'Status',
        'Disponible': 'Available', 'Bajo': 'Low', 'Alimento': 'Food', 'Producto agropecuario': 'Agricultural product',
        'MULTI-SUCURSAL': 'MULTI-BRANCH', 'Una sola plataforma para todas tus sucursales': 'One platform for all your branches',
        'Centraliza la información y facilita la administración de cada punto de venta de tu negocio veterinario.': 'Centralize information and simplify the management of every veterinary branch.',
        'Sucursal': 'Branch', 'Gerentes': 'Managers', 'Cajeros': 'Cashiers',
        'Administra los productos y operaciones correspondientes a cada sucursal.': 'Manage the products and operations for each branch.',
        'Los responsables de cada sede pueden gestionar las operaciones de su sucursal.': 'Branch managers can manage their location operations.',
        'Realizan las ventas y consultan la información necesaria para trabajar.': 'They make sales and access the information they need to work.',
        'SIMPLE Y ORGANIZADO': 'SIMPLE AND ORGANIZED', 'Comienza a utilizar PETKO': 'Start using PETKO',
        'Organiza las operaciones de tu veterinaria desde un solo sistema.': 'Organize your veterinary clinic operations from one system.',
        'Registra': 'Register', 'Administra': 'Manage', 'Vende': 'Sell',
        'Agrega tus productos, sucursales y usuarios al sistema.': 'Add your products, branches, and users to the system.',
        'Controla inventario, usuarios y operaciones de cada sucursal.': 'Control inventory, users, and every branch operation.',
        'Registra las ventas y mantén actualizado tu inventario.': 'Record sales and keep your inventory updated.',
        '¿POR QUÉ PETKO?': 'WHY PETKO?', 'Menos complicaciones. Más control.': 'Less hassle. More control.',
        '🐾 Especializado': '🐾 Specialized', '⚡ Sencillo': '⚡ Simple', '📦 Organizado': '📦 Organized', '🏪 Centralizado': '🏪 Centralized',
        'Diseñado pensando en las necesidades de negocios veterinarios.': 'Designed for the needs of veterinary businesses.',
        'Una interfaz pensada para facilitar las operaciones diarias.': 'An interface designed to simplify daily operations.',
        'Mantén tus productos e información organizada.': 'Keep your products and information organized.',
        'Gestiona las diferentes sucursales desde un mismo sistema.': 'Manage different branches from one system.',
        'Tu veterinaria necesita control.': 'Your veterinary clinic needs control.',
        '© 2026 PETKO · Punto de Venta e Inventario para Veterinarias': '© 2026 PETKO · Point of Sale and Inventory for Veterinary Clinics',
        'Hoy': 'Today', 'Semana': 'Week', '🐶 Productos veterinarios': '🐶 Veterinary products'
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
        document.getElementById('languageToggle').textContent = homeInEnglish ? 'ES' : 'EN';
    }

    document.getElementById('languageToggle').addEventListener('click', () => {
        homeInEnglish = !homeInEnglish;
        toggleHomeLanguage();
    });
</script>

</body>
</html>