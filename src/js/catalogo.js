document.addEventListener('DOMContentLoaded', () => {

    // ==========================================
    // MODO OSCURO / CLARO
    // ==========================================
    const body = document.body;
    const btnTheme = document.getElementById('btnTheme');
    const themeIcon = btnTheme.querySelector('i');

    if (localStorage.getItem('kion-admin-theme') === 'dark' || localStorage.getItem('kion-catalog-theme') === 'dark') {
        body.classList.add('dark-mode');
        themeIcon.classList.replace('ph-moon', 'ph-sun');
    }

    btnTheme.addEventListener('click', () => {
        body.classList.toggle('dark-mode');
        const isDark = body.classList.contains('dark-mode');

        if (isDark) {
            themeIcon.classList.replace('ph-moon', 'ph-sun');
        } else {
            themeIcon.classList.replace('ph-sun', 'ph-moon');
        }

        localStorage.setItem('kion-catalog-theme', isDark ? 'dark' : 'light');
        localStorage.setItem('kion-admin-theme', isDark ? 'dark' : 'light');
    });

    // ==========================================
    // IDIOMA (Bilingüe)
    // ==========================================
    const I18N = {
        es: {
            topbar_sub: 'Veterinaria y Farmacia · cuidado con patitas.',
            filter_hello: '¡Guau! Elige una categoría',
            hero_eyebrow: 'Veterinaria &amp; Farmacia',
            hero_title: 'Farmacia para tu <em>manada</em>',
            hero_sub: 'Medicamentos, nutrición y accesorios elegidos con criterio clínico para el bienestar de perros, gatos y cada integrante de tu familia.',
            hero_cta: 'Explorar catálogo',
            hero_stat_products: 'productos',
            hero_stat_categories: 'categorías',
            hero_stat_vet: 'asesoría veterinaria',
            hero_chip_1: 'Farmacia',
            hero_chip_2: 'Nutrición',
            hero_chip_3: 'Bienestar',
            cat_kicker: 'Colección PETKO',
            nav_dashboard: 'Panel',
            nav_login: 'Iniciar Sesión',
            filter_title: 'Categorías',
            filter_all: 'Todos los productos',
            cat_title: 'Catálogo General',
            cat_items: 'artículos',
            empty_state: 'No hay productos con stock disponible en esta categoría.',
            search_ph: 'Buscar producto...',
            search_empty_title: '¡Guau! No encontramos productos con ese nombre',
            search_empty_sub: 'Revisa la ortografía o prueba con otra palabra, como “croquetas” o “shampoo”.',
            search_clear: 'Ver todos los productos',
            stock_lbl: 'Disponibles:',
            stock_low_a: '¡Últimas',
            stock_low_b: 'piezas!',
            stock_out: 'Agotado',
            btn_add: 'Agregar al carrito',
            btn_out: 'Sin stock',
            cart_title: 'Tu Carrito',
            cart_total: 'Total:',
            cart_checkout: 'Proceder al pago',
            cart_empty: 'Tu carrito está vacío.',
            alert_added: '¡Agregado a la manada! 🐾',
            alert_max: 'No puedes agregar más del stock disponible',
            alert_login: 'Inicia sesión para comprar',
            btn_view: 'Vista ampliada',
            modal_zoom: 'Pasa el cursor para ampliar',
            modal_code: 'Código',
            modal_note_title: 'Recomendación PETKO',
            modal_desc_fallback: 'Producto seleccionado por nuestro equipo veterinario para el cuidado integral de tu mascota.',
            note_medic: 'Adminístralo solo con indicación de tu médico veterinario y respeta la dosis según el peso de tu mascota.',
            note_food: 'Haz la transición de alimento de forma gradual durante 7 días y mantén siempre agua fresca disponible.',
            note_parasite: 'Elige la presentación según el peso de tu mascota y repite la aplicación en el calendario indicado.',
            note_hygiene: 'Ideal para la rutina semanal; evita el contacto con ojos y mucosas.',
            note_access: 'Revisa la talla y el tamaño adecuados para tu mascota antes de usarlo.',
            note_default: 'Nuestro equipo veterinario puede orientarte sobre su uso en sucursal.',
            benefit_1: 'Asesoría veterinaria',
            benefit_2: 'Recoge en sucursal',
            benefit_3: 'Producto original',
            btn_reserve: 'Apartar producto',
            btn_reserved: 'Ver mis apartados',
            reserve_policy: 'Política PETKO: apartamos 1 pieza por producto durante 48 horas en sucursal, sin anticipo. Pagas al recoger.',
            reserve_policy_short: 'Te los guardamos 48 h en sucursal · pagas al recoger.',
            reserve_title: 'Apartados',
            reserve_ok: '¡Producto apartado! Folio {folio} · te lo guardamos 48 h 🐾',
            reserve_dup: 'Este producto ya está apartado',
            reserve_out: 'Sin stock disponible para apartar',
            reserve_folio: 'Folio',
            reserve_expires: 'Vence en {h} h',
            reserve_to_cart: 'Al carrito',
            reserve_cancel: 'Cancelar apartado',
            reserve_cancelled: 'Apartado cancelado',
            dog_welcome: '¡Guau! Bienvenido a PETKO 🐾 ¿Qué necesita tu manada hoy?',
            dog_search_one: '¡Lo encontré! Hay 1 producto para “{q}” 🔎',
            dog_search_many: '¡Olfateé {n} productos para “{q}”! 🐶',
            dog_search_empty: 'Mmm… busqué “{q}” por todos lados y no encontré nada.',
            dog_category: '¡Buena elección! Esto es lo mejor de {cat}.',
            dog_category_all: '¡Aquí está todo el catálogo para tu manada!',
            dog_cart_1: '¡Excelente elección para tu manada!',
            dog_cart_2: '¡Eso le va a encantar! 🦴',
            dog_cart_3: '¡Guau! Tu carrito se ve muy saludable 🩺',
            dog_cart_max: '¡Ups! Ya no quedan más piezas de ese producto.',
            dog_reserve: '¡Guardado por 48 horas! Folio {folio} 📌',
            dog_reserve_dup: '¡Tranquilo, ese ya lo tienes apartado!',
            dog_reserve_out: 'Ese producto está agotado, no puedo apartarlo.',
            dog_reserve_cancel: 'Listo, liberé ese apartado.',
            dog_text: '¡Así se lee mejor! Texto al {p}% 👓',
            a11y_title: 'Tamaño de texto',
            a11y_less: 'Reducir texto',
            a11y_default: 'Tamaño de texto por defecto',
            a11y_more: 'Aumentar texto',
            checkout_close: 'Cerrar',
            checkout_eyebrow: 'Finalizar pedido',
            checkout_title: '¿Cómo deseas finalizar tu pedido?',
            checkout_sub: 'Elige la opción que mejor te acomode; en ambas generamos tu ticket al instante.',
            checkout_items_1: '1 artículo',
            checkout_items_n: '{n} artículos',
            checkout_total: 'Total',
            checkout_secure: 'Tus datos están protegidos. Recibirás un folio único para cualquier aclaración.',
            checkout_processing: 'Procesando…',
            pay_now_title: 'Pagar ahora',
            pay_now_method: 'Tarjeta / Transferencia',
            pay_now_desc: 'Pago seguro en línea y tu ticket de compra al momento.',
            pay_store_title: 'Apartar y pagar en sucursal',
            pay_store_method: 'Efectivo',
            pay_store_desc: 'Te guardamos tus productos 48 h; pagas en efectivo al recoger.',
            ticket_paid_title: '¡Pago Exitoso!',
            ticket_paid_sub: 'Tu pago fue aprobado y tu ticket de compra está listo.',
            ticket_store_title: '¡Apartado Exitoso!',
            ticket_store_sub: 'Muestra este ticket en sucursal; guardamos tus productos 48 h.',
            ticket_folio: 'Folio',
            ticket_date: 'Fecha',
            ticket_method: 'Método',
            ticket_status: 'Estado',
            ticket_status_paid: 'Pagado',
            ticket_status_store: 'Paga en sucursal al recoger',
            ticket_continue: 'Seguir comprando',
            dog_thanks: '¡Guau! Gracias por tu pedido 🐾'
        },
        en: {
            topbar_sub: 'Veterinary Pharmacy · care with paws.',
            filter_hello: 'Woof! Pick a category',
            hero_eyebrow: 'Veterinary &amp; Pharmacy',
            hero_title: 'A pharmacy for your <em>pack</em>',
            hero_sub: 'Medicine, nutrition and accessories chosen with clinical care for the wellbeing of dogs, cats and every member of your family.',
            hero_cta: 'Explore catalog',
            hero_stat_products: 'products',
            hero_stat_categories: 'categories',
            hero_stat_vet: 'veterinary advice',
            hero_chip_1: 'Pharmacy',
            hero_chip_2: 'Nutrition',
            hero_chip_3: 'Wellbeing',
            cat_kicker: 'PETKO Collection',
            nav_dashboard: 'Dashboard',
            nav_login: 'Log In',
            filter_title: 'Categories',
            filter_all: 'All products',
            cat_title: 'General Catalog',
            cat_items: 'items',
            empty_state: 'No products available in stock for this category.',
            search_ph: 'Search product...',
            search_empty_title: 'Woof! We couldn’t find products with that name',
            search_empty_sub: 'Check the spelling or try another word, like “food” or “shampoo”.',
            search_clear: 'Show all products',
            stock_lbl: 'In Stock:',
            stock_low_a: 'Only',
            stock_low_b: 'left!',
            stock_out: 'Out of Stock',
            btn_add: 'Add to Cart',
            btn_out: 'No Stock',
            cart_title: 'Your Cart',
            cart_total: 'Total:',
            cart_checkout: 'Proceed to Checkout',
            cart_empty: 'Your cart is empty.',
            alert_added: 'Added to the pack! 🐾',
            alert_max: 'You cannot exceed the available stock',
            alert_login: 'Log in to shop',
            btn_view: 'Quick view',
            modal_zoom: 'Hover to zoom',
            modal_code: 'SKU',
            modal_note_title: 'PETKO tip',
            modal_desc_fallback: 'Selected by our veterinary team for the complete care of your pet.',
            note_medic: 'Only give it under your veterinarian’s guidance and follow the dose for your pet’s weight.',
            note_food: 'Switch food gradually over 7 days and always keep fresh water available.',
            note_parasite: 'Pick the presentation for your pet’s weight and repeat it on the recommended schedule.',
            note_hygiene: 'Great for the weekly routine; avoid contact with eyes and mucous membranes.',
            note_access: 'Check the right size for your pet before using it.',
            note_default: 'Our veterinary team can advise you on its use in store.',
            benefit_1: 'Veterinary advice',
            benefit_2: 'In-store pickup',
            benefit_3: 'Genuine product',
            btn_reserve: 'Reserve item',
            btn_reserved: 'View my reservations',
            reserve_policy: 'PETKO policy: we hold 1 unit per product for 48 hours in store, no deposit. Pay at pickup.',
            reserve_policy_short: 'Held for 48 h in store · pay at pickup.',
            reserve_title: 'Reserved',
            reserve_ok: 'Item reserved! Code {folio} · held for 48 h 🐾',
            reserve_dup: 'This item is already reserved',
            reserve_out: 'No stock available to reserve',
            reserve_folio: 'Code',
            reserve_expires: 'Expires in {h} h',
            reserve_to_cart: 'To cart',
            reserve_cancel: 'Cancel reservation',
            reserve_cancelled: 'Reservation cancelled',
            dog_welcome: 'Woof! Welcome to PETKO 🐾 What does your pack need today?',
            dog_search_one: 'Found it! 1 product for “{q}” 🔎',
            dog_search_many: 'I sniffed out {n} products for “{q}”! 🐶',
            dog_search_empty: 'Hmm… I searched everywhere for “{q}” and found nothing.',
            dog_category: 'Great pick! Here’s the best of {cat}.',
            dog_category_all: 'Here’s the whole catalog for your pack!',
            dog_cart_1: 'Excellent choice for your pack!',
            dog_cart_2: 'They’re going to love it! 🦴',
            dog_cart_3: 'Woof! Your cart looks very healthy 🩺',
            dog_cart_max: 'Oops! There are no more units of that product.',
            dog_reserve: 'Held for 48 hours! Code {folio} 📌',
            dog_reserve_dup: 'Relax, you already reserved that one!',
            dog_reserve_out: 'That product is out of stock, I can’t reserve it.',
            dog_reserve_cancel: 'Done, I released that reservation.',
            dog_text: 'Easier to read! Text at {p}% 👓',
            a11y_title: 'Text size',
            a11y_less: 'Decrease text size',
            a11y_default: 'Default text size',
            a11y_more: 'Increase text size',
            checkout_close: 'Close',
            checkout_eyebrow: 'Complete order',
            checkout_title: 'How would you like to complete your order?',
            checkout_sub: 'Pick the option that suits you best; either way we generate your ticket instantly.',
            checkout_items_1: '1 item',
            checkout_items_n: '{n} items',
            checkout_total: 'Total',
            checkout_secure: 'Your data is protected. You will get a unique folio for any inquiry.',
            checkout_processing: 'Processing…',
            pay_now_title: 'Pay now',
            pay_now_method: 'Card / Bank transfer',
            pay_now_desc: 'Secure online payment and your receipt right away.',
            pay_store_title: 'Reserve and pay in store',
            pay_store_method: 'Cash',
            pay_store_desc: 'We hold your products for 48 h; pay in cash at pickup.',
            ticket_paid_title: 'Payment Successful!',
            ticket_paid_sub: 'Your payment was approved and your receipt is ready.',
            ticket_store_title: 'Reservation Successful!',
            ticket_store_sub: 'Show this ticket in store; we hold your products for 48 h.',
            ticket_folio: 'Folio',
            ticket_date: 'Date',
            ticket_method: 'Method',
            ticket_status: 'Status',
            ticket_status_paid: 'Paid',
            ticket_status_store: 'Pay in store at pickup',
            ticket_continue: 'Keep shopping',
            dog_thanks: 'Woof! Thanks for your order 🐾'
        }
    };

    let currentLang = localStorage.getItem('kion-catalog-lang') || 'es';

    function t(key) { return I18N[currentLang][key] || key; }

    function applyLanguage() {
        document.documentElement.lang = currentLang;
        document.querySelectorAll('[data-i18n]').forEach(el => {
            el.innerHTML = t(el.dataset.i18n);
        });
        document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
            el.placeholder = t(el.dataset.i18nPlaceholder);
            el.setAttribute('aria-label', t(el.dataset.i18nPlaceholder));
        });
        document.querySelectorAll('[data-i18n-aria]').forEach(el => {
            el.setAttribute('aria-label', t(el.dataset.i18nAria));
            if (el.hasAttribute('title')) el.title = t(el.dataset.i18nAria);
        });
        renderCart();
        renderApartados();
        if (productoModal) pintarModal(productoModal);
        const checkoutAbierto = document.getElementById('modal-checkout');
        if (checkoutAbierto && checkoutAbierto.open) pintarCheckout();
        const ticketAbierto = document.getElementById('modal-ticket');
        if (ticketAbierto && ticketAbierto.open) pintarTicket();
        perritoCambiarIdioma();
    }

    document.getElementById('btnLang').addEventListener('click', () => {
        currentLang = currentLang === 'es' ? 'en' : 'es';
        localStorage.setItem('kion-catalog-lang', currentLang);
        applyLanguage();
    });

    // ==========================================
    // NOTIFICACIONES TOAST
    // ==========================================
    const toastContainer = document.getElementById('toast-container');

    function showToast(mensaje, tipo = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast toast--${tipo}`;
        toast.setAttribute('role', 'status');

        const icono = document.createElement('i');
        const iconosToast = { success: 'ph-paw-print', reserva: 'ph-bookmark-simple', warning: 'ph-warning-circle' };
        icono.className = `ph-fill ${iconosToast[tipo] || iconosToast.warning}`;
        const texto = document.createElement('span');
        texto.textContent = mensaje;
        toast.append(icono, texto);

        toastContainer.appendChild(toast);
        void toast.offsetWidth;
        toast.classList.add('toast--visible');
        
        setTimeout(() => {
            toast.classList.remove('toast--visible');
            toast.classList.add('toast--saliendo');
            toast.addEventListener('transitionend', () => toast.remove(), { once: true });
            setTimeout(() => toast.remove(), 600);
        }, 3000);
    }

    // ==========================================
    // PERRITO DOCTOR · globitos de diálogo
    // ==========================================
    const perritoGlobo = document.getElementById('perrito-globo');
    const perritoTexto = document.getElementById('perrito-globo-texto');
    const perritoImg = document.querySelector('.filtros__mascota');
    const perritoFlotante = document.getElementById('perrito-flotante');
    const perritoFlotanteTexto = document.getElementById('perrito-flotante-texto');
    const DOG_CART = ['dog_cart_1', 'dog_cart_2', 'dog_cart_3'];
    let perritoMensaje = null;
    let perritoPendiente = null;
    let perritoTemporizador = null;
    let perritoCambio = null;
    let mascotaVisible = true;

    // Expresiones del perrito: la foto cambia junto con el globo y con el
    // tema (bata blanca en claro, bata negra en oscuro).
    const perritoAvatar = document.querySelector('.perrito-flotante__avatar');
    const perritoVacio = document.querySelector('.busqueda-vacia__ilustracion img');
    const carpetaPerrito = perritoImg ? perritoImg.getAttribute('src').replace(/[^/]*$/, '') : '';
    const FOTOS_PERRITO = {
        claro: {
            base: `${carpetaPerrito}perrito_clinica.png`,
            feliz: `${carpetaPerrito}perrito_feliz.png`,
            triste: `${carpetaPerrito}perrito_triste.png`,
            hablando: `${carpetaPerrito}perrito_hablando.png`
        },
        oscuro: {
            base: `${carpetaPerrito}perrito_clinica_negro.png`,
            feliz: `${carpetaPerrito}perrito_feliz_oscuro.png`,
            triste: `${carpetaPerrito}perrito_triste_oscuro.png`,
            hablando: `${carpetaPerrito}perrito_hablando_oscuro.png`
        }
    };

    function temaOscuroActivo() {
        return body.classList.contains('dark-mode');
    }

    function fotoPerrito(cara, oscuro = temaOscuroActivo()) {
        return FOTOS_PERRITO[oscuro ? 'oscuro' : 'claro'][cara];
    }
    const CARA_POR_MENSAJE = {
        dog_cart_1: 'feliz',
        dog_cart_2: 'feliz',
        dog_cart_3: 'feliz',
        dog_reserve: 'feliz',
        dog_thanks: 'feliz',
        dog_search_empty: 'triste',
        dog_cart_max: 'triste',
        dog_reserve_out: 'triste'
    };
    const fotosPrecargadas = {};
    let caraActual = 'base';
    let srcSolicitado = perritoImg ? perritoImg.getAttribute('src') : '';
    let solicitudCara = 0;

    function precargarFoto(src) {
        if (!fotosPrecargadas[src]) {
            const img = new Image();
            img.src = src;
            fotosPrecargadas[src] = typeof img.decode === 'function'
                ? img.decode().catch(() => {})
                : new Promise(resolve => { img.onload = img.onerror = resolve; });
        }
        return fotosPrecargadas[src];
    }

    function precargarTema(oscuro, caras = ['base', 'hablando', 'feliz', 'triste']) {
        caras.forEach(cara => precargarFoto(fotoPerrito(cara, oscuro)));
    }

    function fundirFotoPerrito(nuevoSrc) {
        document.querySelectorAll('.perrito-fantasma').forEach(f => f.remove());
        if (prefiereMenosMovimiento || !perritoImg.offsetWidth) {
            perritoImg.src = nuevoSrc;
            return;
        }
        const fantasma = perritoImg.cloneNode(false);
        fantasma.className = 'filtros__mascota perrito-fantasma';
        fantasma.alt = '';
        fantasma.setAttribute('aria-hidden', 'true');
        Object.assign(fantasma.style, {
            top: `${perritoImg.offsetTop}px`,
            left: `${perritoImg.offsetLeft}px`,
            width: `${perritoImg.offsetWidth}px`,
            height: `${perritoImg.offsetHeight}px`
        });
        perritoImg.after(fantasma);
        perritoImg.src = nuevoSrc;
        void fantasma.offsetWidth;
        fantasma.classList.add('is-desvaneciendo');
        fantasma.addEventListener('transitionend', () => fantasma.remove(), { once: true });
        setTimeout(() => fantasma.remove(), 600);
    }

    // La foto se resuelve con el tema vigente al momento de la llamada, así
    // que también sirve para re-pintar la cara actual tras cambiar de tema.
    function cambiarCaraPerrito(cara) {
        if (!perritoImg || !FOTOS_PERRITO.claro[cara]) return;
        const src = fotoPerrito(cara);
        caraActual = cara;
        if (src === srcSolicitado) return;
        srcSolicitado = src;
        const solicitud = ++solicitudCara;
        if (perritoAvatar) perritoAvatar.src = src;
        // Se espera a que la foto esté decodificada para no mostrar un hueco;
        // si mientras tanto llegó otra expresión, esta se descarta.
        precargarFoto(src).then(() => {
            if (solicitud === solicitudCara) fundirFotoPerrito(src);
        });
    }

    function sincronizarPerritoConTema() {
        if (perritoVacio) perritoVacio.src = fotoPerrito('triste');
        cambiarCaraPerrito(caraActual);
    }

    if (perritoImg) {
        // Primer pintado sin fundido, ya con la bata del tema guardado.
        srcSolicitado = fotoPerrito('base');
        perritoImg.src = srcSolicitado;
        if (perritoAvatar) perritoAvatar.src = srcSolicitado;
        if (perritoVacio) perritoVacio.src = fotoPerrito('triste');

        precargarTema(temaOscuroActivo(), ['hablando']);
        precargarTema(!temaOscuroActivo(), ['base']);
        window.addEventListener('load', () => {
            setTimeout(() => precargarTema(temaOscuroActivo(), ['feliz', 'triste']), 800);
        });

        btnTheme.addEventListener('click', () => {
            sincronizarPerritoConTema();
            precargarTema(temaOscuroActivo());
        });
    }

    function textoPerrito(mensaje) {
        if (!mensaje) return t('filter_hello');
        const clave = Array.isArray(mensaje.clave) ? mensaje.clave[mensaje.variante] : mensaje.clave;
        return tFormato(clave, mensaje.valores);
    }

    function pintarPerrito() {
        perritoTexto.textContent = textoPerrito(perritoMensaje);
        perritoGlobo.dataset.tono = perritoMensaje ? perritoMensaje.tono : 'reposo';
    }

    function animarPerrito() {
        clearTimeout(perritoCambio);
        if (prefiereMenosMovimiento) {
            pintarPerrito();
            return;
        }
        perritoGlobo.classList.add('is-cambiando');
        perritoCambio = setTimeout(() => {
            pintarPerrito();
            perritoGlobo.classList.remove('is-cambiando', 'is-nuevo');
            void perritoGlobo.offsetWidth;
            perritoGlobo.classList.add('is-nuevo');
            if (perritoImg && perritoMensaje) {
                perritoImg.classList.remove('is-hablando');
                void perritoImg.offsetWidth;
                perritoImg.classList.add('is-hablando');
            }
        }, 180);
    }

    function mostrarPerritoFlotante(visible) {
        if (perritoFlotante) perritoFlotante.classList.toggle('is-visible', visible);
    }

    function perritoReposo() {
        perritoMensaje = null;
        mostrarPerritoFlotante(false);
        animarPerrito();
        cambiarCaraPerrito('base');
    }

    // clave puede ser un arreglo de variantes; se elige una al azar.
    function perritoDice(clave, valores = {}, tono = 'feliz') {
        if (!perritoGlobo) return;
        const modalProducto = document.getElementById('modal-producto');
        if (modalProducto && modalProducto.open) {
            perritoPendiente = [clave, valores, tono];
            return;
        }

        const variante = Array.isArray(clave) ? Math.floor(Math.random() * clave.length) : 0;
        perritoMensaje = { clave, valores, tono, variante };
        const texto = textoPerrito(perritoMensaje);
        const claveElegida = Array.isArray(clave) ? clave[variante] : clave;

        animarPerrito();
        cambiarCaraPerrito(CARA_POR_MENSAJE[claveElegida] || 'hablando');
        if (perritoFlotante) {
            perritoFlotanteTexto.textContent = texto;
            perritoFlotante.dataset.tono = tono;
        }
        mostrarPerritoFlotante(!mascotaVisible);

        clearTimeout(perritoTemporizador);
        perritoTemporizador = setTimeout(perritoReposo, Math.min(7000, Math.max(3800, texto.length * 65)));
    }

    function perritoTrasModal() {
        if (!perritoPendiente) return;
        const [clave, valores, tono] = perritoPendiente;
        perritoPendiente = null;
        setTimeout(() => perritoDice(clave, valores, tono), 250);
    }

    function perritoCambiarIdioma() {
        if (!perritoGlobo) return;
        pintarPerrito();
        if (perritoMensaje && perritoFlotanteTexto) {
            perritoFlotanteTexto.textContent = textoPerrito(perritoMensaje);
        }
    }

    function recortar(texto, max = 18) {
        return texto.length > max ? `${texto.slice(0, max - 1)}…` : texto;
    }

    if (perritoImg && 'IntersectionObserver' in window) {
        new IntersectionObserver(([entrada]) => {
            mascotaVisible = entrada.isIntersecting;
            mostrarPerritoFlotante(!mascotaVisible && perritoMensaje !== null);
        }, { threshold: 0.5, rootMargin: '-80px 0px 0px 0px' }).observe(perritoImg);
    }

    // ==========================================
    // CATEGORÍA ACTIVA (visible en la lista horizontal de móvil)
    // ==========================================
    const listaCategorias = document.querySelector('.filtros__lista');
    const enlacesCategoria = document.querySelectorAll('.filtros__enlace');
    const prefiereMenosMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function centrarCategoriaActiva(suave) {
        const activa = document.querySelector('.filtros__enlace--activo');
        if (!listaCategorias || !activa || listaCategorias.scrollWidth <= listaCategorias.clientWidth) return;
        listaCategorias.scrollTo({
            left: activa.offsetLeft - listaCategorias.offsetLeft
                - (listaCategorias.clientWidth - activa.offsetWidth) / 2,
            behavior: suave && !prefiereMenosMovimiento ? 'smooth' : 'auto'
        });
    }

    centrarCategoriaActiva(false);

    // ==========================================
    // FILTRADO DINÁMICO (categoría + búsqueda, sin recargar)
    // ==========================================
    const inputBusqueda = document.getElementById('busqueda-catalogo');
    const busquedaVacia = document.getElementById('busqueda-vacia');
    const categoriaVacia = document.getElementById('categoria-vacia');
    const gridProductos = document.querySelector('.grid-productos');
    const tituloCatalogo = document.getElementById('catalogo-titulo');
    const tarjetas = document.querySelectorAll('.grid-productos .tarjeta');

    function normalizar(texto) {
        return texto.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
    }

    const conteoVisible = document.getElementById('conteo-visible');
    const btnLimpiarBusqueda = document.getElementById('limpiar-busqueda');

    const activoInicial = document.querySelector('.filtros__enlace--activo');
    let categoriaActual = activoInicial ? activoInicial.dataset.categoria || '' : '';

    tarjetas.forEach(tarjeta => {
        tarjeta.addEventListener('animationend', () => tarjeta.classList.add('is-lista'));
    });

    const DURACION_SALIDA = prefiereMenosMovimiento ? 0 : 240;
    const DURACION_CAMBIO_CATEGORIA = prefiereMenosMovimiento ? 0 : 200;
    const temporizadoresTarjeta = new WeakMap();
    let temporizadorVacio = null;
    let temporizadorCategoria = null;

    function ocultarTarjeta(tarjeta) {
        if (tarjeta.hidden || tarjeta.classList.contains('is-oculta')) return;
        tarjeta.classList.add('is-oculta');
        temporizadoresTarjeta.set(tarjeta, setTimeout(() => { tarjeta.hidden = true; }, DURACION_SALIDA));
    }

    function mostrarTarjeta(tarjeta) {
        clearTimeout(temporizadoresTarjeta.get(tarjeta));
        if (!tarjeta.hidden && !tarjeta.classList.contains('is-oculta')) return;
        tarjeta.hidden = false;
        void tarjeta.offsetWidth;
        tarjeta.classList.remove('is-oculta');
    }

    function fijarVisibilidad(tarjeta, visible) {
        clearTimeout(temporizadoresTarjeta.get(tarjeta));
        tarjeta.classList.remove('is-oculta');
        tarjeta.hidden = !visible;
    }

    function reproducirEntrada(tarjetasVisibles) {
        if (prefiereMenosMovimiento) return;
        tarjetasVisibles.forEach((tarjeta, i) => {
            tarjeta.style.setProperty('--i', Math.min(i, 12));
            tarjeta.classList.remove('is-lista');
        });
    }

    // instantaneo: usado al cambiar de categoría, donde la transición la
    // hace el contenedor completo en lugar de cada tarjeta.
    function filtrarCatalogo(instantaneo = false) {
        const termino = inputBusqueda ? normalizar(inputBusqueda.value) : '';
        const visiblesLista = [];
        let enCategoria = 0;

        tarjetas.forEach(tarjeta => {
            const coincideCategoria = categoriaActual === '' || tarjeta.dataset.categoria === categoriaActual;
            const coincide = coincideCategoria && normalizar(tarjeta.dataset.nombre || '').includes(termino);
            if (coincideCategoria) enCategoria++;

            if (instantaneo) {
                fijarVisibilidad(tarjeta, coincide);
            } else if (coincide) {
                mostrarTarjeta(tarjeta);
            } else {
                ocultarTarjeta(tarjeta);
            }
            if (coincide) visiblesLista.push(tarjeta);
        });

        if (instantaneo) reproducirEntrada(visiblesLista);

        const sinCategoria = enCategoria === 0;
        if (categoriaVacia) categoriaVacia.hidden = !sinCategoria;

        if (busquedaVacia) {
            clearTimeout(temporizadorVacio);
            if (visiblesLista.length > 0 || sinCategoria || tarjetas.length === 0) {
                busquedaVacia.hidden = true;
            } else if (instantaneo) {
                busquedaVacia.hidden = false;
            } else {
                temporizadorVacio = setTimeout(() => { busquedaVacia.hidden = false; }, DURACION_SALIDA);
            }
        }
        if (conteoVisible) {
            conteoVisible.textContent = visiblesLista.length;
        }
        return visiblesLista.length;
    }

    function pintarTituloCategoria() {
        if (!tituloCatalogo) return;
        if (categoriaActual === '') {
            tituloCatalogo.innerHTML = `<span data-i18n="cat_title">${t('cat_title')}</span>`;
        } else {
            tituloCatalogo.textContent = categoriaActual;
        }
    }

    function marcarEnlaceActivo() {
        enlacesCategoria.forEach(enlace => {
            const activo = (enlace.dataset.categoria || '') === categoriaActual;
            enlace.classList.toggle('filtros__enlace--activo', activo);
            const check = enlace.querySelector('.filtros__check');
            if (activo) {
                enlace.setAttribute('aria-current', 'page');
                if (!check) {
                    const icono = document.createElement('i');
                    icono.className = 'ph-bold ph-check filtros__check';
                    icono.setAttribute('aria-hidden', 'true');
                    enlace.appendChild(icono);
                }
            } else {
                enlace.removeAttribute('aria-current');
                if (check) check.remove();
            }
        });
    }

    function urlDeCategoria(categoria) {
        const url = new URL(window.location.href);
        if (categoria === '') {
            url.searchParams.delete('categoria');
        } else {
            url.searchParams.set('categoria', categoria);
        }
        url.hash = '';
        return url;
    }

    function cambiarCategoria(categoria, { historial = true } = {}) {
        if (categoria === categoriaActual) return;
        categoriaActual = categoria;

        marcarEnlaceActivo();
        centrarCategoriaActiva(true);
        if (historial) {
            history.pushState({ categoria }, '', urlDeCategoria(categoria));
        }

        clearTimeout(temporizadorCategoria);
        body.classList.add('is-navegando');

        temporizadorCategoria = setTimeout(() => {
            pintarTituloCategoria();
            filtrarCatalogo(true);
            body.classList.remove('is-navegando');
            perritoDice(categoria === '' ? 'dog_category_all' : 'dog_category', { cat: categoria });

            // Si la nueva selección es tan corta que quedó por encima de la
            // vista, se lleva al usuario al inicio del catálogo.
            const contenedor = gridProductos || document.getElementById('catalogo');
            if (contenedor && contenedor.getBoundingClientRect().bottom < 120) {
                document.getElementById('catalogo').scrollIntoView({
                    behavior: prefiereMenosMovimiento ? 'auto' : 'smooth',
                    block: 'start'
                });
            }
        }, DURACION_CAMBIO_CATEGORIA);
    }

    if (tarjetas.length > 0) {
        enlacesCategoria.forEach(enlace => {
            enlace.addEventListener('click', e => {
                if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
                e.preventDefault();
                cambiarCategoria(enlace.dataset.categoria || '');
            });
        });

        history.replaceState({ categoria: categoriaActual }, '');

        window.addEventListener('popstate', e => {
            const desdeUrl = new URL(window.location.href).searchParams.get('categoria') || '';
            const categoria = e.state && typeof e.state.categoria === 'string' ? e.state.categoria : desdeUrl;
            const valida = categoria === '' || [...enlacesCategoria].some(a => a.dataset.categoria === categoria);
            cambiarCategoria(valida ? categoria : '', { historial: false });
        });
    }

    if (inputBusqueda) {
        let temporizadorPerritoBusqueda = null;
        inputBusqueda.addEventListener('input', () => {
            const visibles = filtrarCatalogo();
            const termino = inputBusqueda.value.trim();
            clearTimeout(temporizadorPerritoBusqueda);
            if (!termino || tarjetas.length === 0) return;
            temporizadorPerritoBusqueda = setTimeout(() => {
                const q = recortar(termino);
                if (visibles === 0) {
                    perritoDice('dog_search_empty', { q }, 'alerta');
                } else {
                    perritoDice(visibles === 1 ? 'dog_search_one' : 'dog_search_many', { n: visibles, q });
                }
            }, 650);
        });
    }

    if (btnLimpiarBusqueda && inputBusqueda) {
        btnLimpiarBusqueda.addEventListener('click', () => {
            inputBusqueda.value = '';
            filtrarCatalogo();
            inputBusqueda.focus();
        });
    }

    // ==========================================
    // LÓGICA DEL CARRITO (Con control preciso de Stock)
    // ==========================================
    let cart = JSON.parse(localStorage.getItem('kion-cart')) || [];
    const cartOverlay = document.getElementById('cart-overlay');
    const cartPanel = document.getElementById('cart-panel');
    const cartItemsContainer = document.getElementById('cart-items');

    function saveCart() {
        localStorage.setItem('kion-cart', JSON.stringify(cart));
    }

    function moneyFormat(val) {
        return '$' + Number(val).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escapeHtml(valor) {
        return String(valor).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    const cartBadge = document.getElementById('cart-count');
    let ultimoTotalBadge = null;

    function updateCartUI() {
        const totalQty = cart.reduce((sum, item) => sum + item.qty, 0);
        const totalPrice = cart.reduce((sum, item) => sum + (item.precio * item.qty), 0);

        cartBadge.textContent = totalQty > 99 ? '99+' : totalQty;
        cartBadge.classList.toggle('is-empty', totalQty === 0);

        if (ultimoTotalBadge !== null && totalQty !== ultimoTotalBadge && totalQty > 0) {
            cartBadge.classList.remove('is-bump');
            void cartBadge.offsetWidth;
            cartBadge.classList.add('is-bump');
        }
        ultimoTotalBadge = totalQty;

        document.getElementById('cart-total-price').textContent = moneyFormat(totalPrice);
    }

    function renderCart() {
        if (cart.length === 0) {
            cartItemsContainer.innerHTML = `<div style="text-align:center; color:var(--ink-soft); margin-top:40px;"><i class="ph ph-shopping-cart" style="font-size:40px; margin-bottom:10px;"></i><p>${t('cart_empty')}</p></div>`;
        } else {
            cartItemsContainer.innerHTML = cart.map(item => `
                <div class="cart-item">
                    <img src="${escapeHtml(item.img)}" class="cart-item__img" alt="${escapeHtml(item.nombre)}">
                    <div class="cart-item__info">
                        <div>
                            <div class="cart-item__title">${escapeHtml(item.nombre)}</div>
                            <div class="cart-item__price">${moneyFormat(item.precio)}</div>
                        </div>
                        <div class="cart-qty-controls">
                            <button class="qty-btn btn-minus" data-id="${escapeHtml(item.id)}">-</button>
                            <input type="number" class="qty-input" data-id="${escapeHtml(item.id)}" value="${item.qty}" min="1" max="${item.stock}">
                            <button class="qty-btn btn-plus" data-id="${escapeHtml(item.id)}">+</button>
                            <button class="btn-remove" data-id="${escapeHtml(item.id)}" title="Eliminar"><i class="ph ph-trash"></i></button>
                        </div>
                    </div>
                </div>
            `).join('');
        }
        updateCartUI();
    }

    const SESION = window.PETKO_SESION || { autenticado: false, loginUrl: '../../../inicioSesion.php' };
    let redireccionLoginPendiente = false;

    function requiereSesion() {
        if (SESION.autenticado) return true;
        showToast(t('alert_login'), 'warning');
        if (!redireccionLoginPendiente) {
            redireccionLoginPendiente = true;
            setTimeout(() => { window.location.href = SESION.loginUrl; }, 1800);
        }
        return false;
    }

    function addToCart(id, nombre, precio, img, maxStock) {
        if (!requiereSesion()) return false;
        const existing = cart.find(item => item.id === id);
        let agregado = true;
        if (existing) {
            if (existing.qty < maxStock) {
                existing.qty++;
                showToast(t('alert_added'));
                perritoDice(DOG_CART);
            } else {
                showToast(t('alert_max'), 'warning');
                perritoDice('dog_cart_max', {}, 'alerta');
                agregado = false;
            }
        } else {
            cart.push({ id, nombre, precio, img, stock: maxStock, qty: 1 });
            showToast(t('alert_added'));
            perritoDice(DOG_CART);
        }
        saveCart();
        renderCart();
        return agregado;
    }

    function changeQty(id, newQty) {
        const item = cart.find(i => i.id === id);
        if (!item) return;

        let parsedQty = parseInt(newQty);
        if (isNaN(parsedQty) || parsedQty < 1) parsedQty = 1;
        if (parsedQty > item.stock) {
            parsedQty = item.stock;
            showToast(t('alert_max'), 'warning');
        }

        item.qty = parsedQty;
        saveCart();
        renderCart();
    }

    document.addEventListener('click', e => {
        const addBtn = e.target.closest('.js-add-cart');
        if (addBtn && !addBtn.disabled) {
            addToCart(
                addBtn.dataset.id,
                addBtn.dataset.nombre,
                parseFloat(addBtn.dataset.precio),
                addBtn.dataset.img,
                parseInt(addBtn.dataset.stock)
            );
        }

        if (e.target.classList.contains('btn-minus')) {
            const id = e.target.dataset.id;
            const item = cart.find(i => i.id === id);
            if (item && item.qty > 1) changeQty(id, item.qty - 1);
        }

        if (e.target.classList.contains('btn-plus')) {
            const id = e.target.dataset.id;
            const item = cart.find(i => i.id === id);
            if (item) changeQty(id, item.qty + 1);
        }

        const removeBtn = e.target.closest('.btn-remove');
        if (removeBtn) {
            cart = cart.filter(item => item.id !== removeBtn.dataset.id);
            saveCart();
            renderCart();
        }
    });

    cartItemsContainer.addEventListener('change', e => {
        if (e.target.classList.contains('qty-input')) {
            changeQty(e.target.dataset.id, e.target.value);
        }
    });

    const openCart = () => {
        cartOverlay.classList.add('active');
        cartPanel.classList.add('active');
    };
    document.getElementById('open-cart').addEventListener('click', openCart);
    const closeCart = () => {
        cartOverlay.classList.remove('active');
        cartPanel.classList.remove('active');
    };
    document.getElementById('close-cart').addEventListener('click', closeCart);
    cartOverlay.addEventListener('click', closeCart);

    // ==========================================
    // APARTADOS (política PETKO: 1 pieza por producto, 48 h)
    // ==========================================
    const HORAS_APARTADO = 48;
    const apartadosSeccion = document.getElementById('apartados');
    const apartadosLista = document.getElementById('apartados-lista');
    const apartadosConteo = document.getElementById('apartados-conteo');

    let apartados = [];
    try {
        apartados = JSON.parse(localStorage.getItem('kion-apartados')) || [];
    } catch (e) {
        apartados = [];
    }

    function saveApartados() {
        localStorage.setItem('kion-apartados', JSON.stringify(apartados));
    }

    function depurarVencidos() {
        const antes = apartados.length;
        apartados = apartados.filter(a => a.vence > Date.now());
        if (apartados.length !== antes) saveApartados();
    }

    function tFormato(key, valores) {
        return t(key).replace(/\{(\w+)\}/g, (_, k) => valores[k] ?? '');
    }

    function estaApartado(id) {
        return apartados.some(a => a.id === id);
    }

    function generarFolio() {
        const azar = Math.floor(Math.random() * 1296).toString(36).padStart(2, '0');
        return `PK-${Date.now().toString(36).slice(-4)}${azar}`.toUpperCase();
    }

    function apartarProducto(p) {
        if (!requiereSesion()) return false;
        depurarVencidos();
        if (p.stock <= 0) {
            showToast(t('reserve_out'), 'warning');
            perritoDice('dog_reserve_out', {}, 'alerta');
            return false;
        }
        if (estaApartado(p.id)) {
            showToast(t('reserve_dup'), 'warning');
            perritoDice('dog_reserve_dup', {}, 'alerta');
            return false;
        }
        const ahora = Date.now();
        const folio = generarFolio();
        apartados.unshift({
            id: p.id, nombre: p.nombre, precio: p.precio, img: p.img, stock: p.stock,
            folio, creado: ahora, vence: ahora + HORAS_APARTADO * 3600000
        });
        saveApartados();
        renderApartados();
        showToast(tFormato('reserve_ok', { folio }), 'reserva');
        perritoDice('dog_reserve', { folio });
        return true;
    }

    function renderApartados() {
        if (!apartadosSeccion) return;
        depurarVencidos();
        apartadosSeccion.hidden = apartados.length === 0;
        apartadosConteo.textContent = apartados.length;
        apartadosLista.innerHTML = apartados.map(a => {
            const horas = Math.max(1, Math.ceil((a.vence - Date.now()) / 3600000));
            return `
                <div class="apartado">
                    <img src="${escapeHtml(a.img)}" class="cart-item__img" alt="${escapeHtml(a.nombre)}">
                    <div class="apartado__info">
                        <div class="cart-item__title">${escapeHtml(a.nombre)}</div>
                        <div class="apartado__meta">
                            <span class="apartado__folio">${t('reserve_folio')} ${escapeHtml(a.folio)}</span>
                            <span class="apartado__vence"><i class="ph ph-clock"></i> ${tFormato('reserve_expires', { h: horas })}</span>
                        </div>
                        <div class="apartado__acciones">
                            <span class="cart-item__price">${moneyFormat(a.precio)}</span>
                            <button type="button" class="apartado__btn js-apartado-carrito" data-id="${escapeHtml(a.id)}">
                                <i class="ph ph-shopping-cart-simple"></i> ${t('reserve_to_cart')}
                            </button>
                            <button type="button" class="apartado__cancelar js-apartado-cancelar" data-id="${escapeHtml(a.id)}" title="${t('reserve_cancel')}" aria-label="${t('reserve_cancel')}">
                                <i class="ph ph-x-circle"></i>
                            </button>
                        </div>
                    </div>
                </div>`;
        }).join('');
        sincronizarApartarTarjetas();
    }

    function sincronizarApartarTarjetas() {
        document.querySelectorAll('.tarjeta').forEach(tarjeta => {
            const btn = tarjeta.querySelector('.js-apartar');
            if (!btn) return;
            const apartado = estaApartado(tarjeta.dataset.id);
            const clave = apartado ? 'btn_reserved' : 'btn_reserve';
            const texto = btn.querySelector('span');
            btn.classList.toggle('is-apartado', apartado);
            btn.disabled = (parseInt(tarjeta.dataset.stock, 10) || 0) <= 0 && !apartado;
            btn.querySelector('i').className = `${apartado ? 'ph-fill' : 'ph'} ph-bookmark-simple`;
            texto.dataset.i18n = clave;
            texto.textContent = t(clave);
        });
    }

    document.addEventListener('click', e => {
        const btn = e.target.closest('.js-apartar');
        const tarjeta = btn && !btn.disabled && btn.closest('.tarjeta');
        if (!tarjeta) return;
        const producto = datosTarjeta(tarjeta);
        if (estaApartado(producto.id)) {
            openCart();
            return;
        }
        apartarProducto(producto);
    });

    if (apartadosLista) {
        apartadosLista.addEventListener('click', e => {
            const alCarrito = e.target.closest('.js-apartado-carrito');
            const cancelar = e.target.closest('.js-apartado-cancelar');
            const id = (alCarrito || cancelar)?.dataset.id;
            const apartado = apartados.find(a => a.id === id);
            if (!apartado) return;

            if (alCarrito && !addToCart(apartado.id, apartado.nombre, apartado.precio, apartado.img, apartado.stock)) {
                return;
            }
            apartados = apartados.filter(a => a.id !== id);
            saveApartados();
            renderApartados();
            if (cancelar) {
                showToast(t('reserve_cancelled'), 'warning');
                perritoDice('dog_reserve_cancel', {}, 'alerta');
            }
            if (productoModal && productoModal.id === id) pintarModal(productoModal);
        });
    }

    // ==========================================
    // VISTA AMPLIADA DEL PRODUCTO (modal)
    // ==========================================
    const modal = document.getElementById('modal-producto');
    const modalMedia = document.getElementById('modal-media');
    const modalImg = document.getElementById('modal-img');
    const modalStock = document.getElementById('modal-stock');
    const modalAgregar = document.getElementById('modal-agregar');
    const modalApartar = document.getElementById('modal-apartar');
    let productoModal = null;

    const NOTAS_CATEGORIA = [
        ['medic', 'note_medic'],
        ['aliment', 'note_food'],
        ['antiparas', 'note_parasite'],
        ['higien', 'note_hygiene'],
        ['acces', 'note_access']
    ];

    function datosTarjeta(tarjeta) {
        const d = tarjeta.dataset;
        return {
            id: d.id,
            nombre: d.nombre || '',
            precio: parseFloat(d.precio) || 0,
            img: d.img || '',
            stock: parseInt(d.stock, 10) || 0,
            categoria: d.categoria || '',
            codigo: d.codigo || '',
            descripcion: d.descripcion || ''
        };
    }

    function pintarModal(p) {
        const categoriaNorm = normalizar(p.categoria);
        const nota = NOTAS_CATEGORIA.find(([clave]) => categoriaNorm.includes(clave));

        modalImg.src = p.img.replace('/400x400/', '/900x900/');
        modalImg.alt = p.nombre;
        document.getElementById('modal-categoria').textContent = p.categoria || 'PETKO';
        const codigo = document.getElementById('modal-codigo');
        codigo.textContent = p.codigo ? `${t('modal_code')} ${p.codigo}` : '';
        codigo.hidden = !p.codigo;
        document.getElementById('modal-titulo').textContent = p.nombre;
        document.getElementById('modal-descripcion').textContent = p.descripcion || t('modal_desc_fallback');
        document.getElementById('modal-nota').textContent = t(nota ? nota[1] : 'note_default');
        document.getElementById('modal-precio').textContent = moneyFormat(p.precio);

        if (p.stock <= 0) {
            modalStock.className = 'tarjeta__stock stock-none';
            modalStock.textContent = t('stock_out');
        } else if (p.stock < 10) {
            modalStock.className = 'tarjeta__stock stock-low';
            modalStock.textContent = `🔥 ${t('stock_low_a')} ${p.stock} ${t('stock_low_b')}`;
        } else {
            modalStock.className = 'tarjeta__stock stock-ok';
            modalStock.textContent = `${t('stock_lbl')} ${p.stock}`;
        }

        Object.assign(modalAgregar.dataset, {
            id: p.id, nombre: p.nombre, precio: p.precio, img: p.img, stock: p.stock
        });
        modalAgregar.disabled = p.stock <= 0;
        modalAgregar.innerHTML = `<i class="ph ph-shopping-cart-plus"></i><span>${t(p.stock <= 0 ? 'btn_out' : 'btn_add')}</span>`;

        const apartado = estaApartado(p.id);
        modalApartar.classList.toggle('is-apartado', apartado);
        modalApartar.disabled = p.stock <= 0 && !apartado;
        modalApartar.innerHTML = apartado
            ? `<i class="ph-fill ph-bookmark-simple"></i><span>${t('btn_reserved')}</span>`
            : `<i class="ph ph-bookmark-simple"></i><span>${t('btn_reserve')}</span>`;
    }

    function abrirModal(tarjeta) {
        if (!modal || typeof modal.showModal !== 'function') return;
        productoModal = datosTarjeta(tarjeta);
        pintarModal(productoModal);
        modalMedia.classList.remove('is-zoom');
        modal.classList.remove('is-cerrando');
        modal.showModal();
        modal.scrollTop = 0;
        // El modal vive en la capa superior; los toasts deben ir dentro
        // para seguir viéndose por encima del fondo oscurecido.
        modal.appendChild(toastContainer);
        body.classList.add('modal-abierto');
    }

    function cerrarModal(despues) {
        if (!modal.open || modal.classList.contains('is-cerrando')) return;
        modal.classList.add('is-cerrando');
        setTimeout(() => {
            modal.close();
            if (typeof despues === 'function') despues();
        }, prefiereMenosMovimiento ? 0 : 260);
    }

    if (modal) {
        modal.addEventListener('close', () => {
            modal.classList.remove('is-cerrando');
            body.appendChild(toastContainer);
            body.classList.remove('modal-abierto');
            productoModal = null;
            perritoTrasModal();
        });

        modal.addEventListener('cancel', e => {
            e.preventDefault();
            cerrarModal();
        });

        modal.addEventListener('click', e => {
            if (e.target === modal) cerrarModal();
        });

        document.getElementById('modal-cerrar').addEventListener('click', () => cerrarModal());

        modalApartar.addEventListener('click', () => {
            if (!productoModal) return;
            if (estaApartado(productoModal.id)) {
                cerrarModal(openCart);
                return;
            }
            if (apartarProducto(productoModal)) pintarModal(productoModal);
        });

        document.addEventListener('click', e => {
            const disparador = e.target.closest('.js-ver-detalle');
            const tarjeta = disparador && disparador.closest('.tarjeta');
            if (tarjeta) abrirModal(tarjeta);
        });

        document.addEventListener('keydown', e => {
            if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('.tarjeta__imagen.js-ver-detalle')) {
                e.preventDefault();
                abrirModal(e.target.closest('.tarjeta'));
            }
        });

        if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
            modalMedia.addEventListener('mousemove', e => {
                const r = modalMedia.getBoundingClientRect();
                const x = ((e.clientX - r.left) / r.width) * 100;
                const y = ((e.clientY - r.top) / r.height) * 100;
                modalImg.style.transformOrigin = `${x}% ${y}%`;
                modalMedia.classList.add('is-zoom');
            });
            modalMedia.addEventListener('mouseleave', () => modalMedia.classList.remove('is-zoom'));
        }
    }

    // ==========================================
    // CHECKOUT · pagar ahora o apartar en sucursal (simulado)
    // ==========================================
    const checkoutModal = document.getElementById('modal-checkout');
    const checkoutOpciones = checkoutModal ? checkoutModal.querySelectorAll('.checkout__opcion') : [];
    let checkoutProcesando = false;

    function pintarCheckout() {
        const piezas = cart.reduce((sum, item) => sum + item.qty, 0);
        const total = cart.reduce((sum, item) => sum + item.precio * item.qty, 0);
        document.getElementById('checkout-articulos').textContent = piezas === 1
            ? t('checkout_items_1')
            : tFormato('checkout_items_n', { n: piezas });
        document.getElementById('checkout-total').textContent = moneyFormat(total);

        const visibles = cart.slice(0, 4);
        const extra = cart.length - visibles.length;
        document.getElementById('checkout-miniaturas').innerHTML =
            visibles.map(item => `<img src="${escapeHtml(item.img)}" alt="">`).join('')
            + (extra > 0 ? `<span>+${extra}</span>` : '');
    }

    function abrirCheckout() {
        if (!checkoutModal || typeof checkoutModal.showModal !== 'function') return;
        if (cart.length === 0) {
            showToast(t('cart_empty'), 'warning');
            return;
        }
        pintarCheckout();
        checkoutModal.classList.remove('is-cerrando');
        checkoutModal.showModal();
        checkoutModal.scrollTop = 0;
        checkoutModal.appendChild(toastContainer);
        body.classList.add('modal-abierto');
    }

    // El evento close llega de forma asíncrona: si ya se abrió otro diálogo,
    // no hay que quitarle los toasts ni el bloqueo de scroll.
    function liberarDialogo(dialogo) {
        if (toastContainer.parentElement === dialogo) body.appendChild(toastContainer);
        if (!document.querySelector('dialog[open]')) body.classList.remove('modal-abierto');
    }

    function cerrarCheckout(despues) {
        if (!checkoutModal.open || checkoutModal.classList.contains('is-cerrando')) return;
        checkoutModal.classList.add('is-cerrando');
        setTimeout(() => {
            checkoutModal.close();
            if (typeof despues === 'function') despues();
        }, prefiereMenosMovimiento ? 0 : 260);
    }

    function restablecerOpcionesCheckout() {
        checkoutProcesando = false;
        checkoutOpciones.forEach(btn => {
            btn.disabled = false;
            btn.classList.remove('is-procesando');
            btn.removeAttribute('aria-busy');
            const metodo = btn.querySelector('.checkout__opcion-metodo');
            metodo.textContent = t(metodo.dataset.i18n);
            btn.querySelector('.checkout__opcion-flecha').classList.replace('ph-circle-notch', 'ph-caret-right');
        });
    }

    function finalizarPedido(modo) {
        if (checkoutProcesando || cart.length === 0) return;
        checkoutProcesando = true;
        const boton = checkoutModal.querySelector(`[data-checkout="${modo}"]`);
        checkoutOpciones.forEach(btn => { btn.disabled = true; });
        boton.classList.add('is-procesando');
        boton.setAttribute('aria-busy', 'true');
        boton.querySelector('.checkout__opcion-metodo').textContent = t('checkout_processing');
        boton.querySelector('.checkout__opcion-flecha').classList.replace('ph-caret-right', 'ph-circle-notch');

        setTimeout(() => {
            const pedido = {
                modo,
                folio: `FOLIO-${Math.floor(1000 + Math.random() * 9000)}`,
                fecha: new Date(),
                items: cart.map(item => ({ nombre: item.nombre, qty: item.qty, precio: item.precio })),
                total: cart.reduce((sum, item) => sum + item.precio * item.qty, 0)
            };
            cart = [];
            saveCart();
            renderCart();
            cerrarCheckout(() => {
                closeCart();
                abrirTicket(pedido);
                perritoDice('dog_thanks');
            });
        }, prefiereMenosMovimiento ? 300 : 1100);
    }

    if (checkoutModal) {
        document.getElementById('btn-checkout').addEventListener('click', abrirCheckout);
        document.getElementById('checkout-cerrar').addEventListener('click', () => {
            if (!checkoutProcesando) cerrarCheckout();
        });

        checkoutModal.addEventListener('cancel', e => {
            e.preventDefault();
            if (!checkoutProcesando) cerrarCheckout();
        });

        checkoutModal.addEventListener('click', e => {
            if (e.target === checkoutModal && !checkoutProcesando) cerrarCheckout();
        });

        checkoutModal.addEventListener('close', () => {
            checkoutModal.classList.remove('is-cerrando');
            liberarDialogo(checkoutModal);
            restablecerOpcionesCheckout();
        });

        checkoutOpciones.forEach(btn => {
            btn.addEventListener('click', () => finalizarPedido(btn.dataset.checkout));
        });
    }

    // ==========================================
    // TICKET DE COMPRA (simulado)
    // ==========================================
    const ticketModal = document.getElementById('modal-ticket');
    let ultimoPedido = null;

    function pintarTicket() {
        if (!ultimoPedido) return;
        const p = ultimoPedido;
        const pagado = p.modo === 'pagar';

        document.getElementById('ticket-caja').dataset.modo = p.modo;
        document.getElementById('ticket-icono').className = pagado ? 'ph-bold ph-check' : 'ph-bold ph-calendar-check';
        document.getElementById('ticket-titulo').textContent = t(pagado ? 'ticket_paid_title' : 'ticket_store_title');
        document.getElementById('ticket-sub').textContent = t(pagado ? 'ticket_paid_sub' : 'ticket_store_sub');
        document.getElementById('ticket-folio').textContent = p.folio;
        document.getElementById('ticket-codigo').textContent = p.folio;
        document.getElementById('ticket-fecha').textContent = p.fecha.toLocaleString(currentLang === 'en' ? 'en-US' : 'es-MX', {
            day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'
        });
        document.getElementById('ticket-metodo').textContent = t(pagado ? 'pay_now_method' : 'pay_store_method');

        const estado = document.getElementById('ticket-estado');
        estado.innerHTML = pagado
            ? `<i class="ph-fill ph-check-circle"></i> ${t('ticket_status_paid')}`
            : `<i class="ph-fill ph-clock"></i> ${t('ticket_status_store')}`;

        document.getElementById('ticket-items').innerHTML = p.items.map((item, i) => `
            <li style="--i: ${i}">
                <span class="ticket__item-nombre">${escapeHtml(item.nombre)}</span>
                <span class="ticket__item-qty">×${item.qty}</span>
                <span class="ticket__item-precio">${moneyFormat(item.precio * item.qty)}</span>
            </li>`).join('');
        document.getElementById('ticket-total').textContent = moneyFormat(p.total);
        document.getElementById('ticket-perrito').src = fotoPerrito('feliz');
    }

    function abrirTicket(pedido) {
        if (!ticketModal || typeof ticketModal.showModal !== 'function') return;
        ultimoPedido = pedido;
        pintarTicket();
        ticketModal.classList.remove('is-cerrando');
        ticketModal.showModal();
        ticketModal.scrollTop = 0;
        ticketModal.appendChild(toastContainer);
        body.classList.add('modal-abierto');
        document.getElementById('ticket-seguir').focus({ preventScroll: true });
    }

    function cerrarTicket() {
        if (!ticketModal.open || ticketModal.classList.contains('is-cerrando')) return;
        ticketModal.classList.add('is-cerrando');
        setTimeout(() => ticketModal.close(), prefiereMenosMovimiento ? 0 : 260);
    }

    if (ticketModal) {
        document.getElementById('ticket-cerrar').addEventListener('click', cerrarTicket);
        document.getElementById('ticket-seguir').addEventListener('click', cerrarTicket);

        ticketModal.addEventListener('cancel', e => {
            e.preventDefault();
            cerrarTicket();
        });

        ticketModal.addEventListener('click', e => {
            if (e.target === ticketModal) cerrarTicket();
        });

        ticketModal.addEventListener('close', () => {
            ticketModal.classList.remove('is-cerrando');
            liberarDialogo(ticketModal);
            ultimoPedido = null;
        });
    }

    // ==========================================
    // ACCESIBILIDAD · tamaño de texto
    // ==========================================
    const ESCALAS_TEXTO = [0.9, 1, 1.1, 1.2, 1.3];
    const ESCALA_BASE = 1;
    const a11y = document.getElementById('a11y');
    const a11yToggle = document.getElementById('a11y-toggle');
    const a11yNivel = document.getElementById('a11y-nivel');
    const a11yBotones = document.querySelectorAll('.a11y__btn');
    let indiceEscala = ESCALAS_TEXTO.indexOf(parseFloat(localStorage.getItem('kion-font-scale')));
    if (indiceEscala === -1) indiceEscala = ESCALAS_TEXTO.indexOf(ESCALA_BASE);

    function aplicarEscalaTexto() {
        const escala = ESCALAS_TEXTO[indiceEscala];
        document.documentElement.style.setProperty('--font-scale', escala);
        document.documentElement.classList.toggle('texto-grande', escala >= 1.2);
        a11yNivel.textContent = `${Math.round(escala * 100)}%`;

        a11yBotones.forEach(btn => {
            const accion = btn.dataset.a11y;
            const activo = accion === 'base' ? escala === ESCALA_BASE
                : accion === 'menos' ? escala < ESCALA_BASE
                : escala > ESCALA_BASE;
            btn.classList.toggle('is-activo', activo);
            btn.setAttribute('aria-pressed', String(activo));
            btn.disabled = (accion === 'menos' && indiceEscala === 0)
                || (accion === 'mas' && indiceEscala === ESCALAS_TEXTO.length - 1);
        });
    }

    function cerrarA11y() {
        a11y.classList.remove('is-abierto');
        a11yToggle.setAttribute('aria-expanded', 'false');
    }

    if (a11y) {
        aplicarEscalaTexto();

        a11yBotones.forEach(btn => {
            btn.addEventListener('click', () => {
                const anterior = indiceEscala;
                if (btn.dataset.a11y === 'menos') indiceEscala = Math.max(0, indiceEscala - 1);
                if (btn.dataset.a11y === 'mas') indiceEscala = Math.min(ESCALAS_TEXTO.length - 1, indiceEscala + 1);
                if (btn.dataset.a11y === 'base') indiceEscala = ESCALAS_TEXTO.indexOf(ESCALA_BASE);
                if (indiceEscala === anterior) return;

                aplicarEscalaTexto();
                localStorage.setItem('kion-font-scale', String(ESCALAS_TEXTO[indiceEscala]));
                perritoDice('dog_text', { p: Math.round(ESCALAS_TEXTO[indiceEscala] * 100) });
            });
        });

        a11yToggle.addEventListener('click', () => {
            const abierto = a11y.classList.toggle('is-abierto');
            a11yToggle.setAttribute('aria-expanded', String(abierto));
        });

        document.addEventListener('click', e => {
            if (a11y.classList.contains('is-abierto') && !a11y.contains(e.target)) cerrarA11y();
        });

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && a11y.classList.contains('is-abierto')) {
                cerrarA11y();
                a11yToggle.focus();
            }
        });
    }

    applyLanguage();
    setTimeout(() => perritoDice('dog_welcome'), 700);
});
