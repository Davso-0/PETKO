<?php
declare(strict_types=1);
$mcNombre = $nombreCompleto ?? $nombreAdmin ?? $nombreGerente ?? ($_SESSION['usuario']['nombre'] ?? 'Usuario');
$mcCorreo = $correoUsuario ?? ($usuario_data['correo'] ?? $_SESSION['usuario']['correo'] ?? '');
$mcCsrf   = $_SESSION['csrf_token'] ?? '';
?>
<style>
    .mc-compras-lista { display: flex; flex-direction: column; gap: 16px; }
    .mc-compra-card {
        background: var(--bg-surface); border: 1.5px solid var(--border-soft);
        border-radius: 18px; box-shadow: var(--shadow-sm); overflow: hidden;
        transition: transform .22s ease, box-shadow .22s ease, border-color .2s ease;
    }
    .mc-compra-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
        border-color: var(--accent, var(--coffee-main));
    }
    .mc-card-header {
        display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;
        gap: 10px; padding: 13px 22px; background: var(--bg-panel);
        border-bottom: 1px solid var(--border-soft); font-size: 13px;
    }
    .mc-card-fecha {
        font-weight: 600; color: var(--ink); display: flex; align-items: center; gap: 8px;
    }
    .mc-folio-tag {
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; font-weight: 700;
        padding: 4px 10px; border-radius: 8px; background: var(--bg-app);
        border: 1px solid var(--border-soft); color: var(--accent, var(--coffee-main));
        letter-spacing: .3px;
    }
    .mc-btn-copy {
        display: inline-flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 600;
        color: var(--ink-soft); padding: 5px 9px; border-radius: 8px;
        border: 1px solid var(--border-soft); background: var(--bg-surface);
        transition: .15s ease;
    }
    .mc-btn-copy:hover { color: var(--accent, var(--coffee-main)); border-color: var(--accent, var(--coffee-main)); }
    .mc-card-body {
        display: grid; grid-template-columns: 88px minmax(0, 1fr) 230px;
        align-items: center; gap: 22px; padding: 20px 22px;
    }
    .mc-card-thumb {
        width: 88px; height: 88px; border-radius: 12px; border: 1px solid var(--border-soft);
        background: #fff; display: grid; place-items: center; padding: 6px; overflow: hidden;
    }
    .mc-card-thumb img { width: 100%; height: 100%; object-fit: contain; }
    .mc-card-info { display: flex; flex-direction: column; gap: 7px; min-width: 0; }
    .mc-card-titulo { font-size: 16.5px; font-weight: 700; color: var(--ink); line-height: 1.3; }
    .mc-card-detalles { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; font-size: 13.5px; color: var(--ink-soft); }
    .mc-card-total { font-size: 16px; font-weight: 800; color: var(--ink); }
    .mc-card-vigencia { font-size: 12.5px; color: var(--ink-faint); display: flex; align-items: center; gap: 6px; }
    .mc-card-side {
        display: flex; flex-direction: column; align-items: flex-end; justify-content: space-between;
        gap: 12px; padding-left: 20px; border-left: 1px solid var(--border-soft); text-align: right;
    }
    .mc-btn-ticket {
        display: inline-flex; align-items: center; justify-content: center; gap: 6px;
        padding: 9px 15px; border-radius: 12px; font-size: 13px; font-weight: 700;
        background: var(--coffee-light, rgba(26, 111, 216, 0.12));
        color: var(--coffee-main, var(--accent));
        border: 1px solid transparent;
        transition: .2s ease; width: 100%;
    }
    .mc-btn-ticket:hover {
        border-color: var(--coffee-main, var(--accent));
        transform: translateY(-1px);
    }
    .mc-empty {
        background: var(--bg-surface); border: 1.5px solid var(--border-soft);
        border-radius: 18px; padding: 54px 24px; text-align: center;
        color: var(--ink-soft); display: flex; flex-direction: column; align-items: center; gap: 12px;
    }
    .mc-form-group { margin-bottom: 16px; max-width: 460px; }
    .mc-form-group label { display: block; font-size: 13.5px; font-weight: 700; color: var(--ink-soft); margin-bottom: 8px; }
    .mc-form-input {
        width: 100%; padding: 12px 16px; border: 1.5px solid var(--border-soft);
        border-radius: 14px; background: var(--bg-app); color: var(--ink); font-size: 14.5px; outline: none;
    }
    .mc-form-input:disabled { opacity: 0.85; cursor: not-allowed; }

    .mc-pass-box {
        background: var(--bg-app);
        border: 1.5px solid var(--border-soft);
        border-radius: 16px;
        padding: 20px 22px;
        max-width: 460px;
        margin-top: 14px;
    }
    .mc-pass-box p {
        font-size: 14.5px;
        color: var(--ink-soft);
        margin-bottom: 16px;
        font-weight: 500;
    }
    .mc-btn-cambiar-pass {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 13px 26px;
        border-radius: 999px;
        font-weight: 700;
        font-size: 14.5px;
        color: #fff;
        background: linear-gradient(135deg, #60b5ff 0%, #2f80ed 100%);
        box-shadow: 0 6px 18px rgba(47, 128, 237, 0.28);
        transition: transform .16s ease, box-shadow .2s ease, opacity .2s ease;
    }
    .mc-btn-cambiar-pass:hover {
        transform: translateY(-1px);
        box-shadow: 0 8px 22px rgba(47, 128, 237, 0.36);
    }
    .mc-btn-cambiar-pass:active { transform: scale(.97); }
    .mc-btn-cambiar-pass:disabled { opacity: 0.7; cursor: wait; }

    /* MODAL TICKET DE APARTADO */
    dialog.mc-ticket-dialog {
        border: none; border-radius: 24px; padding: 0; width: min(430px, 92vw);
        max-height: 92vh; overflow-y: auto;
        background: var(--bg-surface); color: var(--ink);
        box-shadow: 0 24px 60px rgba(0, 0, 0, 0.28); margin: auto;
    }
    dialog.mc-ticket-dialog::backdrop {
        background: rgba(15, 23, 42, 0.55); backdrop-filter: blur(4px);
    }
    .tk-box { position: relative; padding: 28px 24px 24px; }
    .tk-close {
        position: absolute; top: 14px; right: 14px; width: 34px; height: 34px;
        border-radius: 50%; background: var(--bg-app); border: 1px solid var(--border-soft);
        display: grid; place-items: center; font-size: 16px; color: var(--ink-soft);
    }
    .tk-close:hover { background: var(--bg-panel); color: var(--ink); }
    .tk-header {
        text-align: center; display: flex; flex-direction: column; align-items: center; gap: 8px;
    }
    .tk-check {
        width: 64px; height: 64px; border-radius: 50%; display: grid; place-items: center;
        font-size: 28px; color: #fff; background: #f59e0b;
        box-shadow: 0 0 0 8px rgba(245, 158, 11, 0.14); margin-bottom: 4px;
    }
    .tk-box[data-pagado="1"] .tk-check {
        background: #16a34a; box-shadow: 0 0 0 8px rgba(22, 163, 74, 0.14);
    }
    .tk-brand {
        font-size: 12px; font-weight: 800; letter-spacing: 1.5px; color: var(--ink-soft);
        display: inline-flex; align-items: center; gap: 5px;
    }
    .tk-title { font-size: 22px; font-weight: 800; line-height: 1.2; color: var(--ink); }
    .tk-sub { font-size: 13.5px; color: var(--ink-soft); max-width: 310px; }
    .tk-meta {
        margin-top: 18px; background: var(--bg-app); border: 1px solid var(--border-soft);
        border-radius: 16px; padding: 14px 16px; display: grid; grid-template-columns: 1fr 1fr;
        gap: 14px 12px; text-align: left;
    }
    .tk-meta dt {
        font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px;
        color: var(--ink-faint); margin-bottom: 3px;
    }
    .tk-meta dd { font-size: 13px; font-weight: 700; color: var(--ink); }
    .tk-meta dd.tk-folio { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; color: #b45309; }
    body.dark-mode .tk-meta dd.tk-folio { color: #fbbf24; }
    .tk-status-pill {
        display: inline-block; padding: 4px 9px; border-radius: 8px; font-size: 11px;
        font-weight: 800; background: var(--warning-bg); color: var(--warning); line-height: 1.25;
    }
    .tk-box[data-pagado="1"] .tk-status-pill { background: var(--success-bg); color: var(--success); }
    .tk-divider {
        position: relative; margin: 20px -24px; border-top: 2px dashed var(--border-soft);
    }
    .tk-divider::before, .tk-divider::after {
        content: ''; position: absolute; top: -11px; width: 20px; height: 20px;
        border-radius: 50%; background: rgba(15, 23, 42, 0.55);
    }
    .tk-divider::before { left: -10px; }
    .tk-divider::after { right: -10px; }
    .tk-items {
        list-style: none; display: flex; flex-direction: column; gap: 8px;
        padding-bottom: 12px; border-bottom: 1px solid var(--border-soft); font-size: 14px;
    }
    .tk-items li { display: flex; justify-content: space-between; gap: 12px; }
    .tk-total-row {
        display: flex; justify-content: space-between; align-items: baseline; margin-top: 12px;
    }
    .tk-total-row span {
        font-size: 12px; font-weight: 800; letter-spacing: .8px; color: var(--ink-faint); text-transform: uppercase;
    }
    .tk-total-row strong { font-size: 26px; font-weight: 800; color: var(--ink); }
    .tk-barcode-wrap { margin: 18px 0 14px; text-align: center; }
    .tk-barcode {
        height: 44px; width: 82%; margin: 0 auto 6px;
        background: repeating-linear-gradient(
            90deg,
            var(--ink) 0 2px, transparent 2px 5px,
            var(--ink) 5px 9px, transparent 9px 11px,
            var(--ink) 11px 12px, transparent 12px 16px,
            var(--ink) 16px 19px, transparent 19px 22px
        );
        opacity: .88;
    }
    .tk-barcode-text {
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: 11px; letter-spacing: 3.5px; color: var(--ink-faint);
    }
    .tk-mascot {
        display: flex; align-items: center; gap: 12px; background: rgba(59, 130, 246, 0.09);
        border-radius: 14px; padding: 10px 14px; margin-bottom: 16px;
        font-size: 13.5px; font-weight: 700; color: #1d4ed8;
    }
    body.dark-mode .tk-mascot { color: #93c5fd; }
    .tk-mascot img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; background: #fff; }
    .tk-done-btn {
        width: 100%; padding: 13px; border-radius: 999px; background: #2563eb; color: #fff;
        font-weight: 700; font-size: 14.5px; display: inline-flex; align-items: center;
        justify-content: center; gap: 8px; box-shadow: 0 6px 16px rgba(37, 99, 235, 0.28);
    }
    .tk-done-btn:hover { background: #1d4ed8; }

    @media (max-width: 720px) {
        .mc-card-body { grid-template-columns: 72px 1fr; gap: 14px; padding: 16px; }
        .mc-card-thumb { width: 72px; height: 72px; }
        .mc-card-side {
            grid-column: 1 / -1; flex-direction: row; align-items: center; justify-content: space-between;
            border-left: none; border-top: 1px solid var(--border-soft); padding-left: 0; padding-top: 12px; text-align: left;
        }
        .mc-btn-ticket { width: auto; }
    }
</style>

<!-- VISTA: MIS APARTADOS -->
<section id="view-mi-cuenta" class="view-section">
    <div class="header-row">
        <div>
            <h1 class="title"><i class="ph ph-shopping-bag"></i> <span data-mc-i18n="ap_title">Mis Productos Apartados</span></h1>
            <p class="subtitle" data-mc-i18n="ap_sub">Consulta los productos reservados por 48 horas, su estado de pago y sucursal de recolección.</p>
        </div>
    </div>
    <div id="mcApartadosList" class="mc-compras-lista">
        <div id="listaApartados"></div>
    </div>
</section>

<!-- VISTA: CONFIGURACIÓN DE CUENTA -->
<section id="view-mi-cuenta-config" class="view-section">
    <div class="header-row">
        <div>
            <h1 class="title"><i class="ph ph-gear"></i> <span data-mc-i18n="cfg_title">Configuración de Cuenta</span></h1>
            <p class="subtitle" data-mc-i18n="cfg_sub">Gestiona tu información personal y contraseña.</p>
        </div>
    </div>
    <div class="panel" style="display:flex; gap:36px; flex-wrap:wrap;">
        <div style="flex:1; min-width:280px;">
            <h3 style="margin-bottom:18px; font-size:17px; font-weight:800;" data-mc-i18n="cfg_contact_title">Datos de Contacto</h3>
            <div class="mc-form-group">
                <label data-mc-i18n="cfg_fullname">Nombre Completo</label>
                <input type="text" class="mc-form-input" value="<?= htmlspecialchars((string)$mcNombre) ?>" disabled>
            </div>
            <div class="mc-form-group">
                <label data-mc-i18n="cfg_email">Correo Electrónico</label>
                <input type="text" class="mc-form-input" id="mcCorreoInput" value="<?= htmlspecialchars((string)$mcCorreo) ?>" disabled>
            </div>
            <p style="font-size:12px; color:var(--ink-faint);" data-mc-i18n="cfg_note">* Para cambiar tu correo o nombre, contacta a una sucursal.</p>
        </div>

        <div style="flex:1; min-width:280px;">
            <h3 style="margin-bottom:18px; font-size:17px; font-weight:800;" data-mc-i18n="cfg_pass_title">Cambiar Contraseña</h3>
            <div class="mc-form-group" style="margin-bottom:0;">
                <label data-mc-i18n="cfg_pass_label">Contraseña de la cuenta</label>
                <input type="password" class="mc-form-input" value="••••••••••••" disabled>
            </div>
            <div class="mc-pass-box">
                <p data-mc-i18n="cfg_pass_q">¿Deseas cambiar o actualizar tu contraseña?</p>
                <button type="button" id="mcBtnSolicitarCambioPass" class="mc-btn-cambiar-pass">
                    <i class="ph-bold ph-key"></i>
                    <span data-mc-i18n="cfg_pass_btn">Cambiar Contraseña</span>
                </button>
            </div>
        </div>
    </div>
</section>

<!-- MODAL TICKET DE APARTADO -->
<dialog id="mcModalTicket" class="mc-ticket-dialog" aria-labelledby="mc-tk-title">
    <div class="tk-box" id="mc-tk-box" data-pagado="0">
        <button type="button" class="tk-close" id="mcTkClose" aria-label="Cerrar">
            <i class="ph-bold ph-x"></i>
        </button>

        <header class="tk-header">
            <div class="tk-check"><i class="ph-bold ph-check"></i></div>
            <div class="tk-brand"><i class="ph-fill ph-paw-print"></i> PETKO</div>
            <h2 class="tk-title" id="mc-tk-title"></h2>
            <p class="tk-sub" id="mc-tk-sub"></p>
        </header>

        <dl class="tk-meta">
            <div>
                <dt data-mc-i18n="tk_folio">FOLIO</dt>
                <dd class="tk-folio" id="mc-tk-folio"></dd>
            </div>
            <div>
                <dt data-mc-i18n="tk_fecha">FECHA</dt>
                <dd id="mc-tk-fecha"></dd>
            </div>
            <div>
                <dt data-mc-i18n="tk_metodo">MÉTODO</dt>
                <dd id="mc-tk-metodo"></dd>
            </div>
            <div>
                <dt data-mc-i18n="tk_estado">ESTADO</dt>
                <dd><span class="tk-status-pill" id="mc-tk-estado"></span></dd>
            </div>
        </dl>

        <div class="tk-divider" aria-hidden="true"></div>

        <ul class="tk-items" id="mc-tk-items"></ul>

        <div class="tk-total-row">
            <span data-mc-i18n="tk_total">TOTAL</span>
            <strong id="mc-tk-total">$0.00</strong>
        </div>

        <div class="tk-barcode-wrap" aria-hidden="true">
            <div class="tk-barcode"></div>
            <div class="tk-barcode-text" id="mc-tk-barcode-text"></div>
        </div>

        <div class="tk-mascot">
            <img src="../../../img/perrito_feliz.png" alt="" onerror="this.style.display='none'">
            <p id="mc-tk-thanks" data-mc-i18n="tk_thanks">¡Guau! Gracias por tu pedido 🐾</p>
        </div>

        <button type="button" class="tk-done-btn" id="mcTkDone">
            <i class="ph-fill ph-paw-print"></i>
            <span id="mc-tk-btn-lbl" data-mc-i18n="tk_close">Cerrar ticket</span>
        </button>
    </div>
</dialog>

<script>
(function () {
    const mcEndpoint = window.location.pathname.split('/').pop() || 'dashboard_usuario.php';
    const mcCsrf = <?= json_encode($mcCsrf, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const esc = v => String(v ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
    const fmtMoney = v => '$' + Number(v || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    let mcCache = [];

    const MC_I18N = {
        es: {
            ap_title: 'Mis Productos Apartados',
            ap_sub: 'Consulta los productos reservados por 48 horas, su estado de pago y sucursal de recolección.',
            cfg_title: 'Configuración de Cuenta',
            cfg_sub: 'Gestiona tu información personal y contraseña.',
            cfg_contact_title: 'Datos de Contacto',
            cfg_fullname: 'Nombre Completo',
            cfg_email: 'Correo Electrónico',
            cfg_note: '* Para cambiar tu correo o nombre, contacta a una sucursal.',
            cfg_pass_title: 'Cambiar Contraseña',
            cfg_pass_label: 'Contraseña de la cuenta',
            cfg_pass_q: '¿Deseas cambiar o actualizar tu contraseña?',
            cfg_pass_btn: 'Cambiar Contraseña',
            generating: 'Generando enlace...',
            empty_msg: 'Aún no tienes productos apartados o comprados.',
            go_catalog: 'Ir al Catálogo',
            folio_copied: 'Folio copiado',
            paid: 'YA PAGADO',
            awaiting_pay: 'EN ESPERA DE PAGO',
            reserved_48: 'APARTADO (48H)',
            delivered: 'ENTREGADO',
            expired: 'VENCIDO',
            canceled: 'CANCELADO',
            unit: 'unidad',
            units: 'unidades',
            each: 'c/u',
            expires_on: 'Vence el',
            pickup_branch: 'Recoger en sucursal',
            main_branch: 'Sucursal Principal',
            view_ticket: 'Ver ticket',
            tk_folio: 'FOLIO',
            tk_fecha: 'FECHA',
            tk_metodo: 'MÉTODO',
            tk_estado: 'ESTADO',
            tk_total: 'TOTAL',
            tk_thanks: '¡Guau! Gracias por tu pedido 🐾',
            tk_close: 'Cerrar ticket',
            tk_title_paid: '¡Compra y apartado confirmados!',
            tk_title_res: '¡Apartado confirmado por 48 h!',
            tk_sub_paid: 'Pedido pagado y apartado por 48 horas en sucursal',
            tk_sub_res: 'Producto(s) apartados por 48 horas para pago en sucursal',
            tk_method_online: 'Pago en línea',
            tk_method_cash: 'Pago en sucursal (Efectivo)',
            tk_st_paid: 'PAGADO · APARTADO (48H)',
            tk_st_pend: 'PENDIENTE DE PAGO (48H)'
        },
        en: {
            ap_title: 'My Reserved Products',
            ap_sub: 'Check products reserved for 48 hours, their payment status, and pickup branch.',
            cfg_title: 'Account Settings',
            cfg_sub: 'Manage your personal information and password.',
            cfg_contact_title: 'Contact Information',
            cfg_fullname: 'Full Name',
            cfg_email: 'Email Address',
            cfg_note: '* To change your email or name, please contact a branch.',
            cfg_pass_title: 'Change Password',
            cfg_pass_label: 'Account password',
            cfg_pass_q: 'Would you like to change or update your password?',
            cfg_pass_btn: 'Change Password',
            generating: 'Generating link...',
            empty_msg: 'You do not have any reserved or purchased products yet.',
            go_catalog: 'Go to Catalog',
            folio_copied: 'ID copied',
            paid: 'PAID',
            awaiting_pay: 'AWAITING PAYMENT',
            reserved_48: 'RESERVED (48H)',
            delivered: 'DELIVERED',
            expired: 'EXPIRED',
            canceled: 'CANCELED',
            unit: 'unit',
            units: 'units',
            each: 'each',
            expires_on: 'Expires on',
            pickup_branch: 'Pick up at branch',
            main_branch: 'Main Branch',
            view_ticket: 'View ticket',
            tk_folio: 'FOLIO',
            tk_fecha: 'DATE',
            tk_metodo: 'METHOD',
            tk_estado: 'STATUS',
            tk_total: 'TOTAL',
            tk_thanks: 'Woof! Thank you for your order 🐾',
            tk_close: 'Close ticket',
            tk_title_paid: 'Purchase and reservation confirmed!',
            tk_title_res: 'Reservation confirmed for 48 hrs!',
            tk_sub_paid: 'Order paid and reserved for 48 hours at branch',
            tk_sub_res: 'Product(s) reserved for 48 hours for payment at branch',
            tk_method_online: 'Online payment',
            tk_method_cash: 'In-branch payment (Cash)',
            tk_st_paid: 'PAID · RESERVED (48H)',
            tk_st_pend: 'PENDING PAYMENT (48H)'
        }
    };

    function getMcLang() {
        return (window.PetkoPrefs && typeof PetkoPrefs.lang === 'function')
            ? PetkoPrefs.lang()
            : (localStorage.getItem('petko_lang') || 'es');
    }
    function mct(key) {
        const l = getMcLang();
        return (MC_I18N[l] && MC_I18N[l][key]) || MC_I18N.es[key] || key;
    }
    function applyMcLang() {
        document.querySelectorAll('[data-mc-i18n]').forEach(el => {
            el.textContent = mct(el.dataset.mcI18n);
        });
    }

    function renderApartados() {
        const list = document.getElementById('mcApartadosList');
        if (!list) return;
        const locale = getMcLang() === 'en' ? 'en-US' : 'es-MX';

        if (!mcCache.length) {
            list.innerHTML = `<div class="mc-empty">
                <i class="ph ph-shopping-bag" style="font-size:44px; opacity:.75;"></i>
                <p>${esc(mct('empty_msg'))}</p>
                <a href="../../componentes/catalogo.php" class="mc-btn-ticket" style="width:auto; margin-top:6px;">${esc(mct('go_catalog'))}</a>
            </div>`;
            return;
        }

        list.innerHTML = mcCache.map((x, idx) => {
            const pagado = x.estado_pago === 'PAGADO';
            let badgeEstado = 'b-yellow';
            let estadoIcon = 'ph-hourglass-medium';
            let estadoTexto = mct('reserved_48');
            if (x.estado === 'ENTREGADO') { badgeEstado = 'b-green'; estadoIcon = 'ph-package'; estadoTexto = mct('delivered'); }
            else if (x.estado === 'VENCIDO') { badgeEstado = 'b-red'; estadoIcon = 'ph-warning-circle'; estadoTexto = mct('expired'); }
            else if (x.estado === 'CANCELADO') { badgeEstado = 'b-red'; estadoIcon = 'ph-warning-circle'; estadoTexto = mct('canceled'); }

            const cant = Number(x.cantidad || 1);
            const precioUnit = Number(x.precio || 0);
            const total = precioUnit * cant;
            const folio = x.folio || ('#' + x.id_apartado);
            const fechaFmt = x.fecha_reserva
                ? new Date(x.fecha_reserva.replace(' ', 'T')).toLocaleDateString(locale, { day: 'numeric', month: 'long', year: 'numeric' })
                : '-';
            const expiraFmt = x.fecha_expiracion
                ? new Date(x.fecha_expiracion.replace(' ', 'T')).toLocaleString(locale, { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
                : '';

            return `<article class="mc-compra-card">
                <header class="mc-card-header">
                    <div class="mc-card-fecha">
                        <i class="ph ph-calendar-blank" style="font-size:16px;"></i>
                        <span>${esc(fechaFmt)}</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span class="mc-folio-tag">${esc(folio)}</span>
                        <button type="button" class="mc-btn-copy" onclick="navigator.clipboard?.writeText('${esc(folio)}'); Swal.fire({toast:true,position:'top-end',icon:'success',title:'${esc(mct('folio_copied'))}',showConfirmButton:false,timer:1800});">
                            <i class="ph ph-copy"></i>
                        </button>
                    </div>
                </header>
                <div class="mc-card-body">
                    <div class="mc-card-thumb"><img src="${esc(x.imagen_resuelta)}" alt="${esc(x.producto)}"></div>
                    <div class="mc-card-info">
                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                            <span class="badge ${pagado ? 'b-green' : 'b-yellow'}"><i class="ph-bold ${pagado ? 'ph-check-circle' : 'ph-clock'}"></i> ${pagado ? mct('paid') : mct('awaiting_pay')}</span>
                            <span class="badge ${badgeEstado}"><i class="ph-bold ${estadoIcon}"></i> ${estadoTexto}</span>
                        </div>
                        <h3 class="mc-card-titulo">${esc(x.producto)}</h3>
                        <div class="mc-card-detalles">
                            <span class="mc-card-total">${fmtMoney(total)}</span>
                            <span>·</span>
                            <span>${cant} ${cant === 1 ? mct('unit') : mct('units')} ${cant > 1 ? `(${fmtMoney(precioUnit)} ${mct('each')})` : ''}</span>
                        </div>
                        ${expiraFmt ? `<div class="mc-card-vigencia"><i class="ph ph-timer"></i> <span>${esc(mct('expires_on'))} <b>${esc(expiraFmt)}</b></span></div>` : ''}
                    </div>
                    <div class="mc-card-side">
                        <div>
                            <div style="font-size:11px; text-transform:uppercase; letter-spacing:.5px; color:var(--ink-faint); font-weight:700;">${esc(mct('pickup_branch'))}</div>
                            <div style="font-size:14px; font-weight:700; margin-top:2px;"><i class="ph-fill ph-storefront"></i> ${esc(x.sucursal || mct('main_branch'))}</div>
                        </div>
                        <button type="button" class="mc-btn-ticket" data-mc-ticket="${idx}">
                            <i class="ph ph-receipt"></i> <span>${esc(mct('view_ticket'))}</span>
                        </button>
                    </div>
                </div>
            </article>`;
        }).join('');
    }

    async function mcFetch(action, opts = {}) {
        const method = (opts.method || 'GET').toUpperCase();
        const headers = new Headers(opts.headers || {});
        if (method !== 'GET' && mcCsrf) {
            headers.set('X-CSRF-Token', mcCsrf);
        }
        const r = await fetch(`${mcEndpoint}?action=${action}`, { ...opts, headers });
        const data = await r.json();
        if (!data.ok) throw new Error(data.msg || 'Error en la operación.');
        return data;
    }

    async function cargarApartadosMiCuenta() {
        const list = document.getElementById('mcApartadosList');
        if (!list) return;
        try {
            const { data } = await mcFetch('get_apartados');
            mcCache = data || [];
            renderApartados();
        } catch (err) {
            list.innerHTML = `<div class="mc-empty" style="color:var(--danger);"><p>${esc(err.message)}</p></div>`;
        }
    }

    async function cargarPerfilSiVacio() {
        const correoIn = document.getElementById('mcCorreoInput');
        if (correoIn && !correoIn.value) {
            try {
                const { data } = await mcFetch('get_mi_perfil');
                if (data?.correo) correoIn.value = data.correo;
            } catch (e) {}
        }
    }

    // Modal Ticket
    const dlg = document.getElementById('mcModalTicket');
    document.getElementById('mcTkClose')?.addEventListener('click', () => dlg.close());
    document.getElementById('mcTkDone')?.addEventListener('click', () => dlg.close());
    dlg?.addEventListener('click', e => { if (e.target === dlg) dlg.close(); });

    document.addEventListener('click', e => {
        const btn = e.target.closest('[data-mc-ticket]');
        if (!btn || !dlg) return;
        const item = mcCache[Number(btn.dataset.mcTicket)];
        if (!item) return;

        const locale = getMcLang() === 'en' ? 'en-US' : 'es-MX';
        const grupo = item.folio ? mcCache.filter(x => x.folio === item.folio) : [item];
        const esPagado = item.estado_pago === 'PAGADO';
        const estaVencido = item.estado === 'VENCIDO' || item.estado === 'CANCELADO';
        const folioStr = item.folio || ('PTK-' + item.id_apartado);

        document.getElementById('mc-tk-box').dataset.pagado = esPagado ? '1' : '0';
        document.getElementById('mc-tk-title').textContent = esPagado ? mct('tk_title_paid') : mct('tk_title_res');
        document.getElementById('mc-tk-sub').textContent = (esPagado ? mct('tk_sub_paid') : mct('tk_sub_res')) + ` ${item.sucursal || ''}.`;
        document.getElementById('mc-tk-metodo').textContent = esPagado ? mct('tk_method_online') : mct('tk_method_cash');
        document.getElementById('mc-tk-estado').textContent = estaVencido
            ? (item.estado === 'VENCIDO' ? mct('expired') : mct('canceled'))
            : (esPagado ? mct('tk_st_paid') : mct('tk_st_pend'));

        const fechaFmt = item.fecha_reserva
            ? new Date(item.fecha_reserva.replace(' ', 'T')).toLocaleString(locale, {
                day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false
            })
            : '-';

        document.getElementById('mc-tk-folio').textContent = folioStr;
        document.getElementById('mc-tk-fecha').textContent = fechaFmt;

        let tot = 0;
        document.getElementById('mc-tk-items').innerHTML = grupo.map(g => {
            const cant = Number(g.cantidad || 1);
            const sub = Number(g.precio || 0) * cant;
            tot += sub;
            return `<li><span>${cant}x ${esc(g.producto)}</span><strong>${fmtMoney(sub)}</strong></li>`;
        }).join('');

        document.getElementById('mc-tk-total').textContent = fmtMoney(tot);
        document.getElementById('mc-tk-barcode-text').textContent = folioStr.split('').join(' ');
        dlg.showModal();
    });

    // Botón Cambiar Contraseña
    document.getElementById('mcBtnSolicitarCambioPass')?.addEventListener('click', async function () {
        const btn = this;
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `<i class="ph ph-spinner"></i> <span>${esc(mct('generating'))}</span>`;
        try {
            const d = await mcFetch('solicitar_cambio_password', { method: 'POST' });
            if (d.redirect) {
                window.location.href = d.redirect;
            }
        } catch (err) {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            Swal.fire({ icon: 'error', title: 'Error', text: err.message });
        }
    });

    document.addEventListener('click', e => {
        const nav = e.target.closest('[data-target="view-mi-cuenta"], [data-goto="view-mi-cuenta"]');
        if (nav) cargarApartadosMiCuenta();
        const navCfg = e.target.closest('[data-target="view-mi-cuenta-config"], [data-goto="view-mi-cuenta-config"]');
        if (navCfg) cargarPerfilSiVacio();
    });

    if (window.PetkoPrefs && typeof PetkoPrefs.onLang === 'function') {
        PetkoPrefs.onLang(() => {
            applyMcLang();
            renderApartados();
        });
    }

    applyMcLang();
    window.addEventListener('DOMContentLoaded', () => {
        applyMcLang();
        if (document.getElementById('view-mi-cuenta')?.classList.contains('active')) {
            cargarApartadosMiCuenta();
        }
    });
})();
</script>