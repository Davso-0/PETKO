<?php
declare(strict_types=1);
$acEsAdmin = isset($esAdmin) && $esAdmin === true;
?>
<style>
    .ac-tabs { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 22px; }
    .ac-tab-btn {
        padding: 12px 22px; border-radius: 999px; font-weight: 800; font-size: 14px;
        border: 1.5px solid var(--border-soft); background: var(--bg-surface); color: var(--ink-soft);
        display: inline-flex; align-items: center; gap: 8px; transition: .25s ease;
    }
    .ac-tab-btn.active {
        background: linear-gradient(135deg, #66c7ff, #1a6fd8); color: #04142e;
        border-color: transparent; box-shadow: 0 8px 20px rgba(26,111,216,.30);
    }
    .ac-alert-bar { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; }
    .ac-alert-pill {
        padding: 8px 16px; border-radius: 999px; font-size: 12.5px; font-weight: 700;
        display: inline-flex; align-items: center; gap: 6px;
        background: var(--warning-bg); color: var(--warning); border: 1px solid currentColor;
    }
    .ac-alert-pill.danger { background: var(--danger-bg); color: var(--danger); }

    .ac-table { width: 100%; border-collapse: separate; border-spacing: 0; }
    .ac-table th {
        padding: 13px 14px; font-size: 11.5px; font-weight: 800; text-transform: uppercase;
        letter-spacing: .06em; color: var(--accent); border-bottom: 2px solid var(--border-soft);
        white-space: nowrap;
    }
    .ac-table td {
        padding: 14px; border-bottom: 1px solid var(--border-soft);
        vertical-align: middle; font-size: 13.5px;
    }
    .ac-table tbody tr:hover { background: rgba(26, 111, 216, 0.04); }

    .ac-badge {
        padding: 5px 11px; border-radius: 999px; font-size: 11px; font-weight: 800;
        text-transform: uppercase; letter-spacing: .04em; display: inline-flex; align-items: center; gap: 5px;
        white-space: nowrap;
    }
    .ac-b-green  { background: var(--success-bg); color: var(--success); }
    .ac-b-yellow { background: var(--warning-bg); color: var(--warning); }
    .ac-b-red    { background: var(--danger-bg); color: var(--danger); }
    .ac-b-blue   { background: rgba(26, 111, 216, 0.14); color: var(--accent); }
    .ac-b-gray   { background: rgba(100, 116, 139, 0.16); color: var(--ink-soft); }

    .ac-folio-pill {
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-weight: 800; font-size: 13px; color: var(--ink);
        background: var(--bg-panel); border: 1px solid var(--border-soft);
        padding: 6px 11px; border-radius: 10px; display: inline-block; white-space: nowrap;
    }

    .ac-prod-cell { display: flex; align-items: center; gap: 12px; max-width: 270px; }
    .ac-prod-thumb {
        width: 46px; height: 46px; border-radius: 12px; object-fit: cover;
        border: 1px solid var(--border-soft); flex-shrink: 0;
    }
    .ac-prod-title {
        font-weight: 700; color: var(--ink); line-height: 1.3;
        display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
    }

    .ac-total-box { display: flex; flex-direction: column; align-items: flex-start; gap: 5px; }
    .ac-total-amount { font-size: 16px; font-weight: 800; color: var(--ink); }

    /* Botones de acción amplios y claros */
    .ac-btn-delete {
        display: inline-flex; align-items: center; justify-content: center; gap: 7px;
        padding: 10px 18px; border-radius: 12px; font-weight: 800; font-size: 13px;
        background: var(--danger-bg); color: var(--danger);
        border: 1.5px solid rgba(196, 43, 28, 0.28);
        transition: all .2s ease; white-space: nowrap;
    }
    .ac-btn-delete:hover {
        background: var(--danger); color: #fff; border-color: var(--danger);
        transform: translateY(-2px); box-shadow: 0 6px 16px rgba(196, 43, 28, 0.28);
    }
    .ac-btn-archive {
        display: inline-flex; align-items: center; justify-content: center; gap: 7px;
        padding: 10px 16px; border-radius: 12px; font-weight: 700; font-size: 13px;
        background: var(--bg-panel); color: var(--ink-soft);
        border: 1.5px solid var(--border-soft);
        transition: all .2s ease; white-space: nowrap;
    }
    .ac-btn-archive:hover {
        background: var(--ink-soft); color: #fff; transform: translateY(-1px);
    }

    /* Sección inferior colapsable de pedidos archivados */
    .ac-archive-panel {
        margin-top: 22px; background: var(--bg-panel);
        border: 1.5px dashed var(--border-soft); border-radius: var(--radius);
        padding: 18px 22px;
    }
    .ac-archive-header {
        display: flex; justify-content: space-between; align-items: center;
        gap: 12px; flex-wrap: wrap;
    }
    .ac-archive-title {
        display: flex; align-items: center; gap: 10px;
        font-size: 15px; font-weight: 800; color: var(--ink-soft);
    }
    .ac-archive-count {
        background: rgba(100, 116, 139, 0.18); color: var(--ink-soft);
        padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 800;
    }
    .ac-archive-body { margin-top: 16px; overflow-x: auto; }
    .ac-table-archived tbody tr {
        opacity: 0.68; filter: grayscale(0.85);
        background: rgba(148, 163, 184, 0.06);
    }
    .ac-table-archived tbody tr:hover { opacity: 0.9; filter: grayscale(0.4); }

    .ac-subpanel { display: none; }
    .ac-subpanel.active { display: block; }
</style>

<section id="view-apartados-consultas" class="view-section">
    <div class="header-row">
        <div>
            <h1 class="title"><i class="ph-fill ph-clipboard-text"></i> <span data-ac-i18n="ac_title">Gestión de Apartados y Consultas</span></h1>
            <p class="subtitle" data-ac-i18n="<?= $acEsAdmin ? 'ac_sub_admin' : 'ac_sub_branch' ?>">
                <?= $acEsAdmin
                    ? 'Control centralizado de apartados de productos y citas veterinarias en todas las sucursales.'
                    : 'Administra los apartados de productos y consultas veterinarias programadas en tu sucursal.' ?>
            </p>
        </div>
        <button class="chip-btn" type="button" onclick="acRecargarTodo()">
            <i class="ph ph-arrows-clockwise"></i> <span data-ac-i18n="ac_sync">Sincronizar ahora</span>
        </button>
    </div>

    <div class="ac-alert-bar" id="acAlertBar"></div>

    <!-- KPIs -->
    <div class="kpi-grid" style="margin-bottom: 24px;">
        <div class="kpi-card">
            <div class="kpi-top"><i class="ph-fill ph-shopping-bag kpi-icon"></i><span class="kpi-tag" data-ac-i18n="kpi_ap_tag">Apartados</span></div>
            <div class="kpi-val" id="acKpiApActivos">0</div>
            <div class="kpi-label"><span data-ac-i18n="kpi_ap_activos">Activos</span> (<span id="acKpiApListos">0</span> <span data-ac-i18n="kpi_ap_listos">listos para entrega</span>)</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-top"><i class="ph-fill ph-currency-dollar kpi-icon" style="color:var(--warning); background:var(--warning-bg);"></i><span class="kpi-tag" style="background:var(--warning-bg); color:var(--warning);" data-ac-i18n="kpi_saldos_tag">Saldos</span></div>
            <div class="kpi-val" id="acKpiApPendPago">0</div>
            <div class="kpi-label" data-ac-i18n="kpi_pend_pago">Pendientes de pago</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-top"><i class="ph-fill ph-stethoscope kpi-icon"></i><span class="kpi-tag" data-ac-i18n="kpi_con_tag">Consultas</span></div>
            <div class="kpi-val" id="acKpiConProximas">0</div>
            <div class="kpi-label"><span data-ac-i18n="kpi_con_prox">Próximas</span> (<span id="acKpiConPend">0</span> <span data-ac-i18n="kpi_con_pend">por confirmar</span>)</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-top"><i class="ph-fill ph-clock-countdown kpi-icon" style="color:var(--danger); background:var(--danger-bg);"></i><span class="kpi-tag" style="background:var(--danger-bg); color:var(--danger);" data-ac-i18n="kpi_venc_tag">Vencidos</span></div>
            <div class="kpi-val" id="acKpiVencidosTotal">0</div>
            <div class="kpi-label"><span id="acKpiApVenc">0</span> <span data-ac-i18n="kpi_venc_ap">apartados</span> · <span id="acKpiConVenc">0</span> <span data-ac-i18n="kpi_venc_con">consultas</span></div>
        </div>
    </div>

    <!-- TABS -->
    <div class="ac-tabs">
        <button type="button" class="ac-tab-btn active" data-ac-tab="tab-apartados">
            <i class="ph-bold ph-shopping-bag"></i> <span data-ac-i18n="tab_apartados">Apartados de Productos</span>
        </button>
        <button type="button" class="ac-tab-btn" data-ac-tab="tab-consultas">
            <i class="ph-bold ph-stethoscope"></i> <span data-ac-i18n="tab_consultas">Consultas Veterinarias</span>
        </button>
    </div>

    <!-- ===================================================================
         SUBMÓDULO 1: APARTADOS DE PRODUCTOS
         =================================================================== -->
    <div id="tab-apartados" class="ac-subpanel active">
        <div class="panel">
            <div class="form-inline" style="margin-bottom: 18px;">
                <input type="text" class="search-input" id="acApSearch" data-ac-i18n-placeholder="ph_search_ap" placeholder="Buscar por folio, cliente, teléfono o producto...">
                <?php if ($acEsAdmin): ?>
                    <select class="select-input" id="acApSucursal"><option value="" data-ac-i18n="opt_all_branches">Todas las sucursales</option></select>
                <?php endif; ?>
                <select class="select-input" id="acApEstado">
                    <option value="" data-ac-i18n="opt_all_states">Todos los estados</option>
                    <option value="PENDIENTE_RECOGER" data-ac-i18n="st_pend_pickup">Pendiente de recoger</option>
                    <option value="ENTREGADO" data-ac-i18n="st_delivered">Entregado</option>
                    <option value="VENCIDO" data-ac-i18n="st_expired">Vencido</option>
                    <option value="CANCELADO" data-ac-i18n="st_canceled">Cancelado</option>
                </select>
                <select class="select-input" id="acApEstadoPago">
                    <option value="" data-ac-i18n="opt_any_pay">Cualquier pago</option>
                    <option value="PENDIENTE_PAGO" data-ac-i18n="st_pend_pay">Pendiente de pago</option>
                    <option value="PAGADO" data-ac-i18n="st_paid">Pagado</option>
                </select>
                <input type="date" class="search-input" id="acApFecha">
                <label style="display:inline-flex; align-items:center; gap:6px; font-size:13px; font-weight:700; cursor:pointer;">
                    <input type="checkbox" id="acApProxVencer"> <span data-ac-i18n="chk_expiring">Próximos a vencer (24h)</span>
                </label>
            </div>

            <div style="overflow-x:auto;">
                <table class="ac-table">
                    <thead>
                        <tr>
                            <th data-ac-i18n="th_folio">Folio</th>
                            <th data-ac-i18n="th_client">Cliente</th>
                            <th data-ac-i18n="th_product">Producto</th>
                            <th data-ac-i18n="th_total">Total</th>
                            <th data-ac-i18n="th_status">Estado</th>
                            <th data-ac-i18n="th_dates_branch">Fechas / Sucursal</th>
                            <th style="text-align:center;" data-ac-i18n="th_actions">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="acApartadosBody">
                        <tr><td colspan="7" style="text-align:center; padding:32px; color:var(--ink-faint);">Cargando apartados...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TABLA INFERIOR COLAPSABLE: APARTADOS ARCHIVADOS -->
        <div class="ac-archive-panel">
            <div class="ac-archive-header">
                <div class="ac-archive-title">
                    <i class="ph-fill ph-archive" style="font-size:20px;"></i>
                    <span data-ac-i18n="arch_ap_title">Pedidos Archivados</span>
                    <span class="ac-archive-count" id="acApArchCount">0</span>
                </div>
                <button type="button" class="chip-btn" id="btnToggleApArchivados" onclick="acToggleArchivados('ap')">
                    <i class="ph ph-eye-slash"></i> <span data-ac-i18n="btn_hide_arch">Ocultar archivados</span>
                </button>
            </div>
            <div class="ac-archive-body" id="acApArchBodyWrap">
                <table class="ac-table ac-table-archived">
                    <thead>
                        <tr>
                            <th data-ac-i18n="th_folio">Folio</th>
                            <th data-ac-i18n="th_client">Cliente</th>
                            <th data-ac-i18n="th_product">Producto</th>
                            <th data-ac-i18n="th_total">Total</th>
                            <th data-ac-i18n="th_final_status">Estado Final</th>
                            <th data-ac-i18n="th_dates_branch">Fechas / Sucursal</th>
                        </tr>
                    </thead>
                    <tbody id="acApartadosArchBody">
                        <tr><td colspan="6" style="text-align:center; padding:20px; color:var(--ink-faint);">Sin pedidos archivados.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ===================================================================
         SUBMÓDULO 2: CONSULTAS VETERINARIAS
         =================================================================== -->
    <div id="tab-consultas" class="ac-subpanel">
        <div class="panel">
            <div class="form-inline" style="margin-bottom: 18px;">
                <input type="text" class="search-input" id="acConSearch" data-ac-i18n-placeholder="ph_search_con" placeholder="Buscar por folio, mascota, propietario o motivo...">
                <?php if ($acEsAdmin): ?>
                    <select class="select-input" id="acConSucursal"><option value="" data-ac-i18n="opt_all_branches">Todas las sucursales</option></select>
                <?php endif; ?>
                <select class="select-input" id="acConEstado">
                    <option value="" data-ac-i18n="opt_all_states">Todos los estados</option>
                    <option value="PENDIENTE" data-ac-i18n="st_pend_confirm">Pendiente de confirmación</option>
                    <option value="CONFIRMADA" data-ac-i18n="st_confirmed">Confirmada</option>
                    <option value="PROGRAMADA" data-ac-i18n="st_scheduled">Programada</option>
                    <option value="ATENDIDA" data-ac-i18n="st_attended">Atendida</option>
                    <option value="CANCELADA" data-ac-i18n="st_canceled">Cancelada</option>
                    <option value="NO_ASISTIO" data-ac-i18n="st_noshow">No asistió</option>
                    <option value="VENCIDA" data-ac-i18n="st_expired">Vencida</option>
                </select>
                <input type="date" class="search-input" id="acConFecha">
                <label style="display:inline-flex; align-items:center; gap:6px; font-size:13px; font-weight:700; cursor:pointer;">
                    <input type="checkbox" id="acConProximas"> <span data-ac-i18n="chk_upcoming">Solo próximas</span>
                </label>
            </div>

            <div style="overflow-x:auto;">
                <table class="ac-table">
                    <thead>
                        <tr>
                            <th data-ac-i18n="th_folio">Folio</th>
                            <th data-ac-i18n="th_owner">Propietario</th>
                            <th data-ac-i18n="th_pet">Mascota / Especie</th>
                            <th data-ac-i18n="th_reason">Motivo / Servicio</th>
                            <th data-ac-i18n="th_datetime">Fecha y Hora</th>
                            <th data-ac-i18n="th_branch_vet">Sucursal / Vet.</th>
                            <th data-ac-i18n="th_status">Estado</th>
                            <th style="text-align:center;" data-ac-i18n="th_actions">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="acConsultasBody">
                        <tr><td colspan="8" style="text-align:center; padding:32px; color:var(--ink-faint);">Cargando consultas...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TABLA INFERIOR COLAPSABLE: CONSULTAS ARCHIVADAS -->
        <div class="ac-archive-panel">
            <div class="ac-archive-header">
                <div class="ac-archive-title">
                    <i class="ph-fill ph-archive" style="font-size:20px;"></i>
                    <span data-ac-i18n="arch_con_title">Consultas Archivadas</span>
                    <span class="ac-archive-count" id="acConArchCount">0</span>
                </div>
                <button type="button" class="chip-btn" id="btnToggleConArchivados" onclick="acToggleArchivados('con')">
                    <i class="ph ph-eye-slash"></i> <span data-ac-i18n="btn_hide_arch">Ocultar archivados</span>
                </button>
            </div>
            <div class="ac-archive-body" id="acConArchBodyWrap">
                <table class="ac-table ac-table-archived">
                    <thead>
                        <tr>
                            <th data-ac-i18n="th_folio">Folio</th>
                            <th data-ac-i18n="th_owner">Propietario</th>
                            <th data-ac-i18n="th_pet">Mascota / Especie</th>
                            <th data-ac-i18n="th_reason">Motivo / Servicio</th>
                            <th data-ac-i18n="th_datetime">Fecha y Hora</th>
                            <th data-ac-i18n="th_branch">Sucursal</th>
                            <th data-ac-i18n="th_final_status">Estado Final</th>
                        </tr>
                    </thead>
                    <tbody id="acConsultasArchBody">
                        <tr><td colspan="7" style="text-align:center; padding:20px; color:var(--ink-faint);">Sin consultas archivadas.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<script>
(function() {
    const AC_I18N = {
        es: {
            ac_title: 'Gestión de Apartados y Consultas',
            ac_sub_admin: 'Control centralizado de apartados de productos y citas veterinarias en todas las sucursales.',
            ac_sub_branch: 'Administra los apartados de productos y consultas veterinarias programadas en tu sucursal.',
            ac_sync: 'Sincronizar ahora',
            kpi_ap_tag: 'Apartados', kpi_ap_activos: 'Activos', kpi_ap_listos: 'listos para entrega',
            kpi_saldos_tag: 'Saldos', kpi_pend_pago: 'Pendientes de pago',
            kpi_con_tag: 'Consultas', kpi_con_prox: 'Próximas', kpi_con_pend: 'por confirmar',
            kpi_venc_tag: 'Vencidos', kpi_venc_ap: 'apartados', kpi_venc_con: 'consultas',
            tab_apartados: 'Apartados de Productos', tab_consultas: 'Consultas Veterinarias',
            ph_search_ap: 'Buscar por folio, cliente, teléfono o producto...',
            ph_search_con: 'Buscar por folio, mascota, propietario o motivo...',
            opt_all_branches: 'Todas las sucursales', opt_all_states: 'Todos los estados', opt_any_pay: 'Cualquier pago',
            st_pend_pickup: 'Pendiente de recoger', st_delivered: 'Entregado', st_expired: 'Vencido', st_canceled: 'Cancelado',
            st_pend_pay: 'Pendiente de pago', st_paid: 'Pagado',
            st_pend_confirm: 'Pendiente de confirmación', st_confirmed: 'Confirmada', st_scheduled: 'Programada',
            st_attended: 'Atendida', st_noshow: 'No asistió',
            chk_expiring: 'Próximos a vencer (24h)', chk_upcoming: 'Solo próximas',
            th_folio: 'Folio', th_client: 'Cliente', th_product: 'Producto', th_total: 'Total',
            th_status: 'Estado', th_final_status: 'Estado Final', th_dates_branch: 'Fechas / Sucursal', th_actions: 'Acciones',
            th_owner: 'Propietario', th_pet: 'Mascota / Especie', th_reason: 'Motivo / Servicio',
            th_datetime: 'Fecha y Hora', th_branch_vet: 'Sucursal / Vet.', th_branch: 'Sucursal',
            arch_ap_title: 'Pedidos Archivados', arch_con_title: 'Consultas Archivadas',
            btn_hide_arch: 'Ocultar archivados', btn_show_arch: 'Mostrar archivados',
            lbl_cod: 'Cód:', lbl_cant: 'Cant:', lbl_apartado: 'Apartado:', lbl_limite: 'Límite (48h):',
            btn_eliminar: 'Eliminar', btn_archivar: 'Archivar', btn_gestionar: 'Gestionar',
            empty_ap: 'No se encontraron apartados con los filtros seleccionados.',
            empty_ap_arch: 'Sin pedidos archivados.',
            empty_con: 'No se encontraron consultas con los filtros seleccionados.',
            empty_con_arch: 'Sin consultas archivadas.',
            alert_expiring: 'apartado(s) próximos a vencer en menos de 24h',
            alert_expired: 'apartado(s) vencido(s)',
            alert_con_pend: 'consulta(s) pendiente(s) de confirmar',
            alert_con_imm: 'consulta(s) en las próximas 2 horas',
            sw_del_title: 'Eliminar / Cancelar Apartado',
            sw_del_text: 'Se cancelará el apartado, se devolverán las piezas al inventario y el cliente verá la cancelación en su cuenta.',
            sw_del_ph: 'Motivo de cancelación (obligatorio)',
            sw_del_ok: 'Sí, eliminar apartado', sw_del_back: 'Volver',
            sw_del_val: 'Debes indicar el motivo de cancelación.',
            sw_con_title: 'Consulta', sw_con_st: 'Estado de la consulta', sw_con_dt: 'Fecha y Hora',
            sw_con_vet: 'Veterinario asignado', sw_con_vet_ph: 'Nombre del médico veterinario',
            sw_con_mot: 'Motivo de cancelación (si aplica)', sw_con_mot_ph: 'Solo si el estado es Cancelada',
            sw_save: 'Guardar cambios', sw_close: 'Cerrar'
        },
        en: {
            ac_title: 'Layaways & Veterinary Appointments',
            ac_sub_admin: 'Centralized control of product layaways and veterinary appointments across all branches.',
            ac_sub_branch: 'Manage product layaways and scheduled veterinary appointments at your branch.',
            ac_sync: 'Sync now',
            kpi_ap_tag: 'Layaways', kpi_ap_activos: 'Active', kpi_ap_listos: 'ready for pickup',
            kpi_saldos_tag: 'Balances', kpi_pend_pago: 'Pending payment',
            kpi_con_tag: 'Appointments', kpi_con_prox: 'Upcoming', kpi_con_pend: 'to confirm',
            kpi_venc_tag: 'Expired', kpi_venc_ap: 'layaways', kpi_venc_con: 'appointments',
            tab_apartados: 'Product Layaways', tab_consultas: 'Veterinary Appointments',
            ph_search_ap: 'Search by folio, customer, phone or product...',
            ph_search_con: 'Search by folio, pet, owner or reason...',
            opt_all_branches: 'All branches', opt_all_states: 'All statuses', opt_any_pay: 'Any payment',
            st_pend_pickup: 'Pending pickup', st_delivered: 'Delivered', st_expired: 'Expired', st_canceled: 'Canceled',
            st_pend_pay: 'Pending payment', st_paid: 'Paid',
            st_pend_confirm: 'Pending confirmation', st_confirmed: 'Confirmed', st_scheduled: 'Scheduled',
            st_attended: 'Attended', st_noshow: 'No-show',
            chk_expiring: 'Expiring soon (24h)', chk_upcoming: 'Upcoming only',
            th_folio: 'Folio', th_client: 'Customer', th_product: 'Product', th_total: 'Total',
            th_status: 'Status', th_final_status: 'Final Status', th_dates_branch: 'Dates / Branch', th_actions: 'Actions',
            th_owner: 'Owner', th_pet: 'Pet / Species', th_reason: 'Reason / Service',
            th_datetime: 'Date & Time', th_branch_vet: 'Branch / Vet.', th_branch: 'Branch',
            arch_ap_title: 'Archived Layaways', arch_con_title: 'Archived Appointments',
            btn_hide_arch: 'Hide archived', btn_show_arch: 'Show archived',
            lbl_cod: 'Code:', lbl_cant: 'Qty:', lbl_apartado: 'Reserved:', lbl_limite: 'Deadline (48h):',
            btn_eliminar: 'Delete', btn_archivar: 'Archive', btn_gestionar: 'Manage',
            empty_ap: 'No layaways found with the selected filters.',
            empty_ap_arch: 'No archived layaways.',
            empty_con: 'No appointments found with the selected filters.',
            empty_con_arch: 'No archived appointments.',
            alert_expiring: 'layaway(s) expiring in less than 24h',
            alert_expired: 'expired layaway(s)',
            alert_con_pend: 'appointment(s) pending confirmation',
            alert_con_imm: 'appointment(s) within the next 2 hours',
            sw_del_title: 'Delete / Cancel Layaway',
            sw_del_text: 'The layaway will be canceled, items returned to inventory, and the customer will see the cancellation in their account.',
            sw_del_ph: 'Cancellation reason (required)',
            sw_del_ok: 'Yes, delete layaway', sw_del_back: 'Back',
            sw_del_val: 'You must enter a cancellation reason.',
            sw_con_title: 'Appointment', sw_con_st: 'Appointment status', sw_con_dt: 'Date & Time',
            sw_con_vet: 'Assigned veterinarian', sw_con_vet_ph: 'Veterinarian name',
            sw_con_mot: 'Cancellation reason (if applicable)', sw_con_mot_ph: 'Only if status is Canceled',
            sw_save: 'Save changes', sw_close: 'Close'
        }
    };

    const getAcLang = () => (window.PetkoPrefs && typeof PetkoPrefs.lang === 'function')
        ? PetkoPrefs.lang()
        : (localStorage.getItem('petko_lang') || 'es');
    const act = k => (AC_I18N[getAcLang()] && AC_I18N[getAcLang()][k]) || AC_I18N.es[k] || k;
    const acLocale = () => getAcLang() === 'en' ? 'en-US' : 'es-MX';

    function applyAcLang() {
        document.querySelectorAll('[data-ac-i18n]').forEach(el => { el.textContent = act(el.dataset.acI18n); });
        document.querySelectorAll('[data-ac-i18n-placeholder]').forEach(el => { el.placeholder = act(el.dataset.acI18nPlaceholder); });
    }

    const acMoney = v => '$' + Number(v || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const acEsc = v => String(v ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));

    const getBadgeAp = (est) => ({
        'PENDIENTE_RECOGER': ['ac-b-blue', act('st_pend_pickup')],
        'PENDIENTE':         ['ac-b-blue', act('st_pend_pickup')],
        'ENTREGADO':         ['ac-b-green', act('st_delivered')],
        'CANCELADO':         ['ac-b-gray', act('st_canceled')],
        'VENCIDO':           ['ac-b-red', act('st_expired')]
    }[est] || ['ac-b-blue', est]);

    const getBadgeCon = (est) => ({
        'PENDIENTE':  ['ac-b-yellow', act('st_pend_confirm')],
        'CONFIRMADA': ['ac-b-blue', act('st_confirmed')],
        'PROGRAMADA': ['ac-b-blue', act('st_scheduled')],
        'ATENDIDA':   ['ac-b-green', act('st_attended')],
        'CANCELADA':  ['ac-b-gray', act('st_canceled')],
        'NO_ASISTIO': ['ac-b-red', act('st_noshow')],
        'VENCIDA':    ['ac-b-red', act('st_expired')]
    }[est] || ['ac-b-yellow', est]);

    document.querySelectorAll('.ac-tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.ac-tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.ac-subpanel').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById(btn.dataset.acTab).classList.add('active');
        });
    });

    window.acToggleArchivados = function(tipo) {
        const wrap = document.getElementById(tipo === 'ap' ? 'acApArchBodyWrap' : 'acConArchBodyWrap');
        const btn  = document.getElementById(tipo === 'ap' ? 'btnToggleApArchivados' : 'btnToggleConArchivados');
        const oculto = wrap.style.display === 'none';
        wrap.style.display = oculto ? 'block' : 'none';
        btn.innerHTML = oculto
            ? `<i class="ph ph-eye-slash"></i> <span>${acEsc(act('btn_hide_arch'))}</span>`
            : `<i class="ph ph-eye"></i> <span>${acEsc(act('btn_show_arch'))}</span>`;
    };

    function calcularFechaLimite48h(ap) {
        const raw = ap.fecha_limite || ap.fecha_apartado;
        if (!raw) return '48h';
        const d = new Date(String(raw).replace(' ', 'T'));
        if (isNaN(d)) return '48h';
        if (!ap.fecha_limite) d.setHours(d.getHours() + 48);
        return d.toLocaleString(acLocale(), { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    function renderFilaApartado(ap, esArchivado = false) {
        const [clsA, lblA] = getBadgeAp(ap.estado);
        const pagado = ap.estado_pago === 'PAGADO';
        const img = ap.imagen_url ? acEsc(ap.imagen_url) : 'https://placehold.co/80x80?text=PETKO';
        const activo = (ap.estado === 'PENDIENTE_RECOGER' || ap.estado === 'PENDIENTE');
        const fApartado = ap.fecha_apartado ? new Date(String(ap.fecha_apartado).replace(' ', 'T')).toLocaleDateString(acLocale()) : '-';
        const fLimite = calcularFechaLimite48h(ap);

        return `<tr>
            <td><span class="ac-folio-pill">${acEsc(ap.folio)}</span></td>
            <td>
                <b>${acEsc(ap.cliente_nombre)}</b>
                ${ap.telefono ? `<br><small style="color:var(--ink-soft);"><i class="ph ph-phone"></i> ${acEsc(ap.telefono)}</small>` : ''}
            </td>
            <td>
                <div class="ac-prod-cell">
                    <img src="${img}" class="ac-prod-thumb" alt="">
                    <div>
                        <div class="ac-prod-title" title="${acEsc(ap.producto_nombre)}">${acEsc(ap.producto_nombre)}</div>
                        <small style="color:var(--ink-faint); font-weight:600;">${acEsc(act('lbl_cod'))} ${acEsc(ap.producto_codigo)} · ${acEsc(act('lbl_cant'))} <b>${ap.cantidad}</b></small>
                    </div>
                </div>
            </td>
            <td>
                <div class="ac-total-box">
                    <span class="ac-total-amount">${acMoney(ap.total_apartado)}</span>
                    <span class="ac-badge ${pagado ? 'ac-b-green' : 'ac-b-red'}">
                        <i class="ph-bold ${pagado ? 'ph-check-circle' : 'ph-clock'}"></i>
                        ${acEsc(pagado ? act('st_paid') : act('st_pend_pay'))}
                    </span>
                </div>
            </td>
            <td><span class="ac-badge ${clsA}">${acEsc(lblA)}</span></td>
            <td>
                <small>${acEsc(act('lbl_apartado'))} <b>${fApartado}</b></small><br>
                <small style="color:var(--warning); font-weight:700;">${acEsc(act('lbl_limite'))} ${fLimite}</small><br>
                <small style="color:var(--accent); font-weight:800;">${acEsc(ap.sucursal_nombre)}</small>
            </td>
            ${esArchivado ? '' : `
            <td style="text-align:center;">
                ${activo
                    ? `<button type="button" class="ac-btn-delete" onclick="acCancelarApartado(${ap.id_apartado}, '${acEsc(ap.folio)}')">
                           <i class="ph-bold ph-trash"></i> ${acEsc(act('btn_eliminar'))}
                       </button>`
                    : `<button type="button" class="ac-btn-archive" onclick="acArchivarApartado(${ap.id_apartado})">
                           <i class="ph-bold ph-archive"></i> ${acEsc(act('btn_archivar'))}
                       </button>`
                }
            </td>`}
        </tr>`;
    }

    async function cargarSucursalesSelect() {
        const s1 = document.getElementById('acApSucursal');
        const s2 = document.getElementById('acConSucursal');
        if (!s1 && !s2) return;
        try {
            const { data } = await request('get_sucursales_simple');
            const opts = `<option value="">${acEsc(act('opt_all_branches'))}</option>` +
                data.map(s => `<option value="${s.id_sucursal}">${acEsc(s.nombre)}</option>`).join('');
            if (s1) s1.innerHTML = opts;
            if (s2) s2.innerHTML = opts;
        } catch (e) {}
    }

    async function cargarResumen() {
        try {
            const { data } = await request('ac_get_resumen');
            const a = data.apartados || {};
            const c = data.consultas || {};

            document.getElementById('acKpiApActivos').textContent = a.activos || 0;
            document.getElementById('acKpiApListos').textContent = a.listos_entrega || 0;
            document.getElementById('acKpiApPendPago').textContent = a.pendientes_pago || 0;
            document.getElementById('acKpiConProximas').textContent = c.proximas || 0;
            document.getElementById('acKpiConPend').textContent = c.pendientes_confirmacion || 0;
            document.getElementById('acKpiApVenc').textContent = a.vencidos || 0;
            document.getElementById('acKpiConVenc').textContent = c.vencidas || 0;
            document.getElementById('acKpiVencidosTotal').textContent = (a.vencidos || 0) + (c.vencidas || 0);

            const alertas = [];
            if ((a.por_vencer || 0) > 0) alertas.push(`<span class="ac-alert-pill"><i class="ph-bold ph-warning"></i> ${a.por_vencer} ${acEsc(act('alert_expiring'))}</span>`);
            if ((a.vencidos || 0) > 0) alertas.push(`<span class="ac-alert-pill danger"><i class="ph-bold ph-clock-countdown"></i> ${a.vencidos} ${acEsc(act('alert_expired'))}</span>`);
            if ((c.pendientes_confirmacion || 0) > 0) alertas.push(`<span class="ac-alert-pill"><i class="ph-bold ph-bell-ringing"></i> ${c.pendientes_confirmacion} ${acEsc(act('alert_con_pend'))}</span>`);
            if ((c.inminentes || 0) > 0) alertas.push(`<span class="ac-alert-pill"><i class="ph-bold ph-stethoscope"></i> ${c.inminentes} ${acEsc(act('alert_con_imm'))}</span>`);
            document.getElementById('acAlertBar').innerHTML = alertas.join('');
        } catch (e) {}
    }

    async function cargarApartados() {
        const tbody = document.getElementById('acApartadosBody');
        const params = new URLSearchParams({
            q: document.getElementById('acApSearch')?.value || '',
            sucursal: document.getElementById('acApSucursal')?.value || '',
            estado: document.getElementById('acApEstado')?.value || '',
            estado_pago: document.getElementById('acApEstadoPago')?.value || '',
            fecha: document.getElementById('acApFecha')?.value || '',
            proximos_vencer: document.getElementById('acApProxVencer')?.checked ? '1' : '0'
        });

        try {
            const { data } = await request(`ac_get_apartados&${params}`);
            tbody.innerHTML = data.length
                ? data.map(ap => renderFilaApartado(ap, false)).join('')
                : `<tr><td colspan="7" style="text-align:center; padding:32px; color:var(--ink-faint);">${acEsc(act('empty_ap'))}</td></tr>`;
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; color:var(--danger); padding:24px;">${acEsc(e.message)}</td></tr>`;
        }

        try {
            const { data: arch } = await request('ac_get_apartados_archivados');
            document.getElementById('acApArchCount').textContent = arch.length;
            document.getElementById('acApartadosArchBody').innerHTML = arch.length
                ? arch.map(ap => renderFilaApartado(ap, true)).join('')
                : `<tr><td colspan="6" style="text-align:center; padding:20px; color:var(--ink-faint);">${acEsc(act('empty_ap_arch'))}</td></tr>`;
        } catch (e) {}
    }

    async function cargarConsultas() {
        const tbody = document.getElementById('acConsultasBody');
        const params = new URLSearchParams({
            q: document.getElementById('acConSearch')?.value || '',
            sucursal: document.getElementById('acConSucursal')?.value || '',
            estado: document.getElementById('acConEstado')?.value || '',
            fecha: document.getElementById('acConFecha')?.value || '',
            proximas: document.getElementById('acConProximas')?.checked ? '1' : '0'
        });

        try {
            const { data } = await request(`ac_get_consultas&${params}`);
            tbody.innerHTML = data.length ? data.map(c => {
                const [clsC, lblC] = getBadgeCon(c.estado);
                const esFinal = ['ATENDIDA', 'CANCELADA', 'VENCIDA', 'NO_ASISTIO'].includes(c.estado);
                return `<tr>
                    <td><span class="ac-folio-pill">${acEsc(c.folio)}</span></td>
                    <td><b>${acEsc(c.cliente_nombre)}</b></td>
                    <td><b>${acEsc(c.mascota_nombre)}</b><br><small style="color:var(--accent); font-weight:700;">${acEsc(c.tipo_animal)}</small></td>
                    <td>
                        <small style="font-weight:700; color:var(--ink);">${acEsc(c.servicio)}</small><br>
                        <span style="font-size:12.5px; color:var(--ink-soft);">${acEsc(c.motivo)}</span>
                    </td>
                    <td><b>${new Date(String(c.fecha_hora).replace(' ', 'T')).toLocaleString(acLocale(), {dateStyle:'medium', timeStyle:'short'})}</b></td>
                    <td><b>${acEsc(c.sucursal_nombre)}</b><br><small style="color:var(--ink-faint);">Vet: ${acEsc(c.veterinario_asignado)}</small></td>
                    <td><span class="ac-badge ${clsC}">${acEsc(lblC)}</span></td>
                    <td style="text-align:center;">
                        <div style="display:inline-flex; gap:8px; align-items:center;">
                            <button class="chip-btn" onclick='acGestionarConsulta(${JSON.stringify(c).replace(/'/g, "&#39;")})'>${acEsc(act('btn_gestionar'))}</button>
                            ${esFinal ? `<button class="ac-btn-archive" onclick="acArchivarConsulta(${c.id_consulta})"><i class="ph-bold ph-archive"></i> ${acEsc(act('btn_archivar'))}</button>` : ''}
                        </div>
                    </td>
                </tr>`;
            }).join('') : `<tr><td colspan="8" style="text-align:center; padding:32px; color:var(--ink-faint);">${acEsc(act('empty_con'))}</td></tr>`;
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; color:var(--danger); padding:24px;">${acEsc(e.message)}</td></tr>`;
        }

        try {
            const { data: archC } = await request('ac_get_consultas_archivadas');
            document.getElementById('acConArchCount').textContent = archC.length;
            document.getElementById('acConsultasArchBody').innerHTML = archC.length ? archC.map(c => {
                const [clsC, lblC] = getBadgeCon(c.estado);
                return `<tr>
                    <td><span class="ac-folio-pill">${acEsc(c.folio)}</span></td>
                    <td><b>${acEsc(c.cliente_nombre)}</b></td>
                    <td><b>${acEsc(c.mascota_nombre)}</b> (${acEsc(c.tipo_animal)})</td>
                    <td>${acEsc(c.motivo)}</td>
                    <td>${new Date(String(c.fecha_hora).replace(' ', 'T')).toLocaleString(acLocale(), {dateStyle:'medium', timeStyle:'short'})}</td>
                    <td>${acEsc(c.sucursal_nombre)}</td>
                    <td><span class="ac-badge ${clsC}">${acEsc(lblC)}</span></td>
                </tr>`;
            }).join('') : `<tr><td colspan="7" style="text-align:center; padding:20px; color:var(--ink-faint);">${acEsc(act('empty_con_arch'))}</td></tr>`;
        } catch (e) {}
    }

    window.acCancelarApartado = async function(idApartado, folio) {
        const { value: motivo } = await Swal.fire({
            title: `${act('sw_del_title')} · ${folio}`,
            text: act('sw_del_text'),
            input: 'text',
            inputPlaceholder: act('sw_del_ph'),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: 'var(--danger)',
            confirmButtonText: act('sw_del_ok'),
            cancelButtonText: act('sw_del_back'),
            inputValidator: v => !v.trim() && act('sw_del_val')
        });
        if (!motivo) return;
        try {
            const fd = new FormData();  
            fd.append('id_apartado', idApartado);
            fd.append('motivo', motivo);
            const res = await request('ac_cancelar_apartado', { method: 'POST', body: fd });
            notify(res.msg);
            acRecargarTodo();
        } catch (e) { notify(e.message, 'error'); }
    };

    window.acArchivarApartado = async function(idApartado) {
        try {
            const fd = new FormData();
            fd.append('id_apartado', idApartado);
            const res = await request('ac_archivar_apartado', { method: 'POST', body: fd });
            notify(res.msg);
            acRecargarTodo();
        } catch (e) { notify(e.message, 'error'); }
    };

    window.acGestionarConsulta = async function(c) {
        const dtVal = String(c.fecha_hora || '').replace(' ', 'T').slice(0, 16);
        const { value: form } = await Swal.fire({
            title: `${act('sw_con_title')} ${c.folio} · ${c.mascota_nombre}`,
            html: `<div style="text-align:left; font-size:13.5px;">
                <label style="font-weight:700; display:block; margin-bottom:4px;">${acEsc(act('sw_con_st'))}</label>
                <select id="swal-con-est" class="swal2-select" style="width:100%; margin:0 0 12px;">
                    <option value="PENDIENTE" ${c.estado==='PENDIENTE'?'selected':''}>${acEsc(act('st_pend_confirm'))}</option>
                    <option value="CONFIRMADA" ${c.estado==='CONFIRMADA'?'selected':''}>${acEsc(act('st_confirmed'))}</option>
                    <option value="PROGRAMADA" ${c.estado==='PROGRAMADA'?'selected':''}>${acEsc(act('st_scheduled'))}</option>
                    <option value="ATENDIDA" ${c.estado==='ATENDIDA'?'selected':''}>${acEsc(act('st_attended'))}</option>
                    <option value="NO_ASISTIO" ${c.estado==='NO_ASISTIO'?'selected':''}>${acEsc(act('st_noshow'))}</option>
                    <option value="CANCELADA" ${c.estado==='CANCELADA'?'selected':''}>${acEsc(act('st_canceled'))}</option>
                </select>
                <label style="font-weight:700; display:block; margin-bottom:4px;">${acEsc(act('sw_con_dt'))}</label>
                <input id="swal-con-fh" type="datetime-local" class="swal2-input" style="width:100%; margin:0 0 12px;" value="${dtVal}">
                <label style="font-weight:700; display:block; margin-bottom:4px;">${acEsc(act('sw_con_vet'))}</label>
                <input id="swal-con-vet" class="swal2-input" style="width:100%; margin:0 0 12px;" value="${acEsc(c.veterinario_asignado === 'Por asignar' ? '' : c.veterinario_asignado)}" placeholder="${acEsc(act('sw_con_vet_ph'))}">
                <label style="font-weight:700; display:block; margin-bottom:4px;">${acEsc(act('sw_con_mot'))}</label>
                <input id="swal-con-mot" class="swal2-input" style="width:100%; margin:0;" value="${acEsc(c.motivo_cancelacion || '')}" placeholder="${acEsc(act('sw_con_mot_ph'))}">
            </div>`,
            showCancelButton: true,
            confirmButtonText: act('sw_save'),
            cancelButtonText: act('sw_close'),
            preConfirm: () => ({
                estado: document.getElementById('swal-con-est').value,
                fecha_hora: document.getElementById('swal-con-fh').value,
                veterinario_asignado: document.getElementById('swal-con-vet').value.trim(),
                motivo_cancelacion: document.getElementById('swal-con-mot').value.trim()
            })
        });
        if (!form) return;
        try {
            const fd = new FormData();
            fd.append('id_consulta', c.id_consulta);
            Object.entries(form).forEach(([k, v]) => fd.append(k, v));
            const res = await request('ac_actualizar_consulta', { method: 'POST', body: fd });
            notify(res.msg);
            acRecargarTodo();
        } catch (e) { notify(e.message, 'error'); }
    };

    window.acArchivarConsulta = async function(idConsulta) {
        try {
            const fd = new FormData();
            fd.append('id_consulta', idConsulta);
            const res = await request('ac_archivar_consulta', { method: 'POST', body: fd });
            notify(res.msg);
            acRecargarTodo();
        } catch (e) { notify(e.message, 'error'); }
    };

    window.acRecargarTodo = function() {
        applyAcLang();
        cargarResumen();
        cargarApartados();
        cargarConsultas();
    };

    window.initModuloApartadosConsultas = function() {
        applyAcLang();
        cargarSucursalesSelect();
        acRecargarTodo();
    };

    if (window.PetkoPrefs && typeof PetkoPrefs.onLang === 'function') {
        PetkoPrefs.onLang(() => {
            applyAcLang();
            cargarResumen();
            cargarApartados();
            cargarConsultas();
        });
    }

    applyAcLang();
    ['acApSearch', 'acApSucursal', 'acApEstado', 'acApEstadoPago', 'acApFecha', 'acApProxVencer'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener(id === 'acApSearch' ? 'input' : 'change', cargarApartados);
    });
    ['acConSearch', 'acConSucursal', 'acConEstado', 'acConFecha', 'acConProximas'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener(id === 'acConSearch' ? 'input' : 'change', cargarConsultas);
    });
})();
</script>       