<?php
declare(strict_types=1);
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<style>
    #petkoReceiptModal {
        border: none;
        border-radius: 20px;
        padding: 0;
        width: min(440px, 95vw);
        max-height: 92vh;
        background: #ffffff;
        color: #0a1f3d;
        box-shadow: 0 25px 60px rgba(0,0,0,0.35);
        margin: auto;
        overflow-y: auto;
    }
    #petkoReceiptModal::backdrop {
        background: rgba(6, 14, 29, 0.65);
        backdrop-filter: blur(4px);
    }
    .receipt-toolbar {
        display: flex; justify-content: space-between; align-items: center;
        padding: 16px 18px; background: #0a1f3d; color: #fff;
    }
    .receipt-toolbar .title { font-size: 14px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
    .receipt-body { padding: 18px; background: #fff; }
    .receipt-kv { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .receipt-box { padding: 10px 12px; background: #f4f8fd; border: 1px solid #d5e2f2; border-radius: 12px; }
    .receipt-box small { display: block; color: #567296; font-size: 10px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; }
    .receipt-box strong { display: block; margin-top: 6px; font-size: 14px; }
    .receipt-items { margin-top: 18px; }
    .receipt-items table { width: 100%; border-collapse: collapse; }
    .receipt-items th, .receipt-items td { padding: 8px 0; border-bottom: 1px solid #edf3fb; text-align: left; }
    .receipt-items th:last-child, .receipt-items td:last-child { text-align: right; }
    .receipt-total { margin-top: 18px; display: flex; justify-content: space-between; align-items: center; padding-top: 16px; border-top: 2px solid #dfeaf7; font-size: 18px; font-weight: 800; }
    .receipt-actions { display: flex; gap: 10px; padding: 14px 18px 18px; }
    .receipt-actions button { flex: 1; padding: 11px 12px; border-radius: 10px; font-weight: 700; border: none; cursor: pointer; }
    .btn-print { background: #0a1f3d; color: #fff; }
    .btn-pdf { background: #dff6ea; color: #0d734c; }
    @media print {
        body * { visibility: hidden !important; }
        #petkoReceiptModal, #petkoReceiptModal * { visibility: visible !important; }
        #petkoReceiptModal { position: fixed; inset: 0; width: 100%; max-width: 100%; border: none; background: white; box-shadow: none; }
        .receipt-toolbar, .receipt-actions { display: none !important; }
        #petkoPrintableReceipt { width: 80mm; max-width: 100%; }
    }
</style>

<dialog id="petkoReceiptModal">
    <div id="petkoPrintableReceipt">
        <div class="receipt-toolbar">
            <div class="title">PETKO</div>
            <button type="button" class="btn-close" data-close-receipt data-html2canvas-ignore="true" style="background:transparent; border:none; color:#fff; font-size:22px; cursor:pointer;">×</button>
        </div>
        <div class="receipt-body" id="petkoReceiptContent"></div>
        <div class="receipt-actions" data-html2canvas-ignore="true">
            <button type="button" class="btn-print" id="petkoPrintReceipt">Imprimir</button>
            <button type="button" class="btn-pdf" id="petkoDownloadPdf">Descargar PDF</button>
        </div>
    </div>
</dialog>

<script>
(function () {
    const modal = document.getElementById('petkoReceiptModal');
    const content = document.getElementById('petkoReceiptContent');
    const printBtn = document.getElementById('petkoPrintReceipt');
    const pdfBtn = document.getElementById('petkoDownloadPdf');
    const closeBtn = document.querySelector('[data-close-receipt]');

    const R_I18N = {
        es: {
            venta: 'Venta', apartado: 'Apartado', comprobante: 'Comprobante',
            folio: 'Folio', fecha: 'Fecha', metodo: 'Método', sucursal: 'Sucursal',
            producto: 'Producto', cant: 'Cant.', subtotal: 'Subtotal',
            sin_art: 'Sin artículos', total: 'Total', recibido: 'Recibido', cambio: 'Cambio',
            cajero: 'Cajero:', imprimir: 'Imprimir', pdf: 'Descargar PDF'
        },
        en: {
            venta: 'Sale', apartado: 'Layaway', comprobante: 'Receipt',
            folio: 'Folio', fecha: 'Date', metodo: 'Method', sucursal: 'Branch',
            producto: 'Product', cant: 'Qty.', subtotal: 'Subtotal',
            sin_art: 'No items', total: 'Total', recibido: 'Received', cambio: 'Change',
            cajero: 'Cashier:', imprimir: 'Print', pdf: 'Download PDF'
        }
    };

    const getRLang = () => (window.PetkoPrefs && typeof PetkoPrefs.lang === 'function')
        ? PetkoPrefs.lang()
        : (localStorage.getItem('petko_lang') || 'es');
    const rt = k => (R_I18N[getRLang()] && R_I18N[getRLang()][k]) || R_I18N.es[k] || k;

    function money(v) {
        return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(v || 0));
    }

    function safeText(v) {
        return String(v ?? '').replace(/[<>&"']/g, ch => ({ '<':'&lt;', '>':'&gt;', '&':'&amp;', '"':'&quot;', "'":'&#39;' }[ch]));
    }

    function renderReceipt(tipo, data) {
        printBtn.textContent = rt('imprimir');
        pdfBtn.textContent = rt('pdf');

        const items = Array.isArray(data?.items) ? data.items : (Array.isArray(data?.data?.items) ? data.data.items : []);
        const total = Number(data?.total ?? data?.data?.total ?? 0);
        const tipoLabel = tipo === 'venta' ? rt('venta') : (tipo === 'apartado' ? rt('apartado') : rt('comprobante'));
        const folio = data?.folio ?? data?.data?.folio ?? 'N/A';
        const fecha = data?.fecha_hora ?? data?.fecha ?? new Date().toLocaleString(getRLang() === 'en' ? 'en-US' : 'es-MX');
        const metodo = data?.metodo_pago ?? data?.data?.metodo_pago ?? '—';
        const sucursal = data?.sucursal?.nombre ?? data?.sucursal ?? data?.data?.sucursal ?? '—';
        const cajero = data?.cajero ?? data?.data?.cajero ?? '—';
        const recibido = data?.monto_recibido ?? data?.data?.monto_recibido ?? null;
        const cambio = data?.cambio_entregado ?? data?.data?.cambio_entregado ?? null;

        modal.dataset.folio = String(folio).replace(/[^a-zA-Z0-9-_]/g, '') || 'PETKO';

        const rows = items.length ? items.map(item => {
            const qty = Number(item.cantidad ?? item.qty ?? 1);
            const unit = Number(item.precio_unitario ?? item.precio ?? 0);
            const subtotal = Number(item.subtotal ?? (unit * qty));
            return `<tr><td>${safeText(item.nombre || item.producto || rt('producto'))}</td><td>${qty}</td><td>${money(subtotal)}</td></tr>`;
        }).join('') : `<tr><td colspan="3">${safeText(rt('sin_art'))}</td></tr>`;

        content.innerHTML = `
            <div style="font-family:Arial, sans-serif; color:#0a1f3d;">
                <div style="text-align:center; margin-bottom:18px;">
                    <div style="font-size:26px; font-weight:800; letter-spacing:0.08em;">PETKO</div>
                    <div style="font-size:11px; color:#567296; letter-spacing:0.12em; text-transform:uppercase; margin-top:4px;">${safeText(tipoLabel)}</div>
                </div>
                <div class="receipt-kv">
                    <div class="receipt-box"><small>${safeText(rt('folio'))}</small><strong>${safeText(folio)}</strong></div>
                    <div class="receipt-box"><small>${safeText(rt('fecha'))}</small><strong>${safeText(fecha)}</strong></div>
                    <div class="receipt-box"><small>${safeText(rt('metodo'))}</small><strong>${safeText(metodo)}</strong></div>
                    <div class="receipt-box"><small>${safeText(rt('sucursal'))}</small><strong>${safeText(sucursal)}</strong></div>
                </div>
                <div class="receipt-items">
                    <table>
                        <thead><tr><th>${safeText(rt('producto'))}</th><th>${safeText(rt('cant'))}</th><th>${safeText(rt('subtotal'))}</th></tr></thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
                <div class="receipt-total"><span>${safeText(rt('total'))}</span><span>${money(total)}</span></div>
                ${recibido !== null ? `<div class="receipt-total" style="font-size:15px; margin-top:10px;"><span>${safeText(rt('recibido'))}</span><span>${money(recibido)}</span></div>` : ''}
                ${cambio !== null ? `<div class="receipt-total" style="font-size:15px; margin-top:10px;"><span>${safeText(rt('cambio'))}</span><span>${money(cambio)}</span></div>` : ''}
                ${cajero ? `<div style="margin-top:14px; padding-top:12px; border-top:1px solid #edf3fb; font-size:12px; color:#567296;">${safeText(rt('cajero'))} ${safeText(cajero)}</div>` : ''}
            </div>
        `;
    }

    function openReceipt(tipo, data) {
        renderReceipt(tipo, data);
        if (typeof modal.showModal === 'function') {
            modal.showModal();
        } else {
            modal.setAttribute('open', 'open');
        }
    }

    printBtn.addEventListener('click', () => window.print());
    pdfBtn.addEventListener('click', () => {
        const element = document.getElementById('petkoPrintableReceipt');
        const folioClean = modal.dataset.folio || 'PETKO';
        const fileName = `Ticket_${folioClean}.pdf`;
        html2pdf().set({
            margin: 6,
            filename: fileName,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
        }).from(element).save();
    });
    if (closeBtn) closeBtn.addEventListener('click', () => modal.close());
    modal.addEventListener('click', (event) => {
        if (event.target === modal) modal.close();
    });

    window.mostrarComprobantePETKO = openReceipt;
})();
</script>