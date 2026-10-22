document.addEventListener('DOMContentLoaded', function () {
    // -------------------------------------------------------------
    // 1. NAVEGACIÓN ENTRE SECCIONES DEL SIDEBAR Y TOGGLE
    // -------------------------------------------------------------
    const navItems = document.querySelectorAll('.sidebar-nav .nav-item');
    const secciones = document.querySelectorAll('.modulo-seccion');
    const tituloSeccion = document.getElementById('titulo-seccion');
    const sidebar = document.getElementById('sidebar');
    const toggleSidebarBtn = document.getElementById('toggleSidebarBtn');

    if (toggleSidebarBtn && sidebar) {
        toggleSidebarBtn.addEventListener('click', function () {
            sidebar.classList.toggle('collapsed');
        });
    }

    navItems.forEach(item => {
        item.addEventListener('click', function (e) {
            e.preventDefault();
            const targetId = this.getAttribute('data-target');

            navItems.forEach(i => i.classList.remove('active'));
            this.classList.add('active');

            secciones.forEach(sec => {
                if (sec.id === targetId) {
                    sec.classList.add('active');
                } else {
                    sec.classList.remove('active');
                }
            });

            if (tituloSeccion) {
                const textoNav = this.querySelector('.nav-text').textContent;
                tituloSeccion.textContent = textoNav;
            }

            if (targetId === 'seccion-historial') {
                cargarHistorial();
            }
        });
    });

    // -------------------------------------------------------------
    // 2. LÓGICA DEL CARRITO DE COMPRAS Y PUNTO DE VENTA (POS)
    // -------------------------------------------------------------
    let carrito = [];

    const btnAgregarPrueba = document.getElementById('btnAgregarPrueba');
    const inputCodigoBarras = document.getElementById('inputCodigoBarras');
    const tbCarrito = document.getElementById('tbCarrito');
    const txtTotalVenta = document.getElementById('txtTotalVenta');
    const txtTotalPanel = document.getElementById('txtTotalPanel');
    const txtCantArticulos = document.getElementById('txtCantArticulos');
    const btnVaciarCarrito = document.getElementById('btnVaciarCarrito');
    const formFinalizarVenta = document.getElementById('formFinalizarVenta');

    // Botón "+ Producto Demo"
    if (btnAgregarPrueba) {
        btnAgregarPrueba.addEventListener('click', function () {
            const productoDemo = {
                id: 'DEMO-' + Math.floor(Math.random() * 1000),
                nombre: 'Producto Demo KION',
                precio: 50.00,
                cantidad: 1
            };
            agregarAlCarrito(productoDemo);
        });
    }

    // Escanear/Buscar Producto
    if (inputCodigoBarras) {
        inputCodigoBarras.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const codigo = this.value.trim();
                if (codigo !== '') {
                    buscarProducto(codigo);
                    this.value = '';
                }
            }
        });
    }

    function buscarProducto(codigo) {
        fetch(`?action=buscar_producto&codigo=${encodeURIComponent(codigo)}`)
            .then(res => res.json())
            .then(res => {
                if (res.ok) {
                    agregarAlCarrito({
                        id: res.data.id_producto,
                        nombre: res.data.nombre,
                        precio: parseFloat(res.data.precio),
                        cantidad: 1
                    });
                } else {
                    alert(res.msg || 'Producto no encontrado');
                }
            })
            .catch(err => console.error('Error al buscar producto:', err));
    }

    function agregarAlCarrito(producto) {
        const index = carrito.findIndex(item => item.id === producto.id);
        if (index !== -1) {
            carrito[index].cantidad += 1;
        } else {
            carrito.push(producto);
        }
        renderizarCarrito();
    }

    function renderizarCarrito() {
        if (!tbCarrito) return;
        tbCarrito.innerHTML = '';

        if (carrito.length === 0) {
            tbCarrito.innerHTML = `
                <tr>
                    <td colspan="5" class="empty-cart-msg">Escanea un producto para comenzar...</td>
                </tr>
            `;
            actualizarTotales();
            return;
        }

        carrito.forEach((item, index) => {
            const subtotal = item.precio * item.cantidad;
            const tr = document.createElement('tr');

            tr.innerHTML = `
                <td><b>${item.nombre}</b></td>
                <td style="text-align: center;">
                    <button type="button" class="btn-sm btn-secondary btn-restar" data-index="${index}">-</button>
                    <span style="margin: 0 8px; font-weight: bold;">${item.cantidad}</span>
                    <button type="button" class="btn-sm btn-secondary btn-sumar" data-index="${index}">+</button>
                </td>
                <td style="text-align: right;">$${item.precio.toFixed(2)}</td>
                <td style="text-align: right;">$${subtotal.toFixed(2)}</td>
                <td style="text-align: center;">
                    <button type="button" class="btn-sm btn-danger btn-eliminar" data-index="${index}" style="width: auto; margin:0;">✕</button>
                </td>
            `;

            tbCarrito.appendChild(tr);
        });

        // Eventos para botones de la tabla
        document.querySelectorAll('.btn-sumar').forEach(btn => {
            btn.addEventListener('click', function () {
                const idx = this.getAttribute('data-index');
                carrito[idx].cantidad++;
                renderizarCarrito();
            });
        });

        document.querySelectorAll('.btn-restar').forEach(btn => {
            btn.addEventListener('click', function () {
                const idx = this.getAttribute('data-index');
                if (carrito[idx].cantidad > 1) {
                    carrito[idx].cantidad--;
                } else {
                    carrito.splice(idx, 1);
                }
                renderizarCarrito();
            });
        });

        document.querySelectorAll('.btn-eliminar').forEach(btn => {
            btn.addEventListener('click', function () {
                const idx = this.getAttribute('data-index');
                carrito.splice(idx, 1);
                renderizarCarrito();
            });
        });

        actualizarTotales();
    }

    function actualizarTotales() {
        let total = 0;
        let cantArticulos = 0;

        carrito.forEach(item => {
            total += item.precio * item.cantidad;
            cantArticulos += item.cantidad;
        });

        const totalFormateado = `$${total.toFixed(2)}`;
        if (txtTotalVenta) txtTotalVenta.textContent = totalFormateado;
        if (txtTotalPanel) txtTotalPanel.textContent = totalFormateado;
        if (txtCantArticulos) txtCantArticulos.textContent = cantArticulos;
    }

    if (btnVaciarCarrito) {
        btnVaciarCarrito.addEventListener('click', function () {
            carrito = [];
            renderizarCarrito();
        });
    }

    // Registrar Venta
    if (formFinalizarVenta) {
        formFinalizarVenta.addEventListener('submit', function (e) {
            e.preventDefault();

            if (carrito.length === 0) {
                alert('El carrito está vacío');
                return;
            }

            const clienteNombre = document.getElementById('clienteNombre').value.trim() || 'Cliente General';
            const metodoPago = document.getElementById('metodoPago').value;
            const total = carrito.reduce((sum, item) => sum + (item.precio * item.cantidad), 0);

            const payload = {
                cliente_nombre: clienteNombre,
                metodo_pago: metodoPago,
                total: total,
                items: carrito
            };

            fetch('?action=registrar_venta_completa', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                if (data.ok) {
                    alert('¡Venta realizada exitosamente!');
                    carrito = [];
                    renderizarCarrito();
                    document.getElementById('clienteNombre').value = '';
                } else {
                    alert('Error: ' + data.msg);
                }
            })
            .catch(err => console.error('Error al registrar venta:', err));
        });
    }

    // -------------------------------------------------------------
    // 3. CARGAR HISTORIAL DE PAGOS Y CORTE DE CAJA
    // -------------------------------------------------------------
    function cargarHistorial() {
        fetch('?action=get_cobros')
            .then(res => res.json())
            .then(res => {
                if (!res.ok) return;

                const tbCobros = document.getElementById('tbCobros');
                if (tbCobros) {
                    tbCobros.innerHTML = '';
                    let totalEfectivo = 0;
                    let totalTarjeta = 0;

                    res.data.forEach(cobro => {
                        const monto = parseFloat(cobro.monto);
                        if (cobro.metodo_pago === 'EFECTIVO') {
                            totalEfectivo += monto;
                        } else {
                            totalTarjeta += monto;
                        }

                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>#${cobro.id_venta}</td>
                            <td>${cobro.cliente_nombre}</td>
                            <td>$${monto.toFixed(2)}</td>
                            <td><span class="badge badge-info">${cobro.metodo_pago}</span></td>
                            <td>${cobro.fecha_pago}</td>
                        `;
                        tbCobros.appendChild(tr);
                    });

                    // Actualizar Corte de Caja
                    const elEfectivo = document.getElementById('totalEfectivoCaja');
                    const elTarjeta = document.getElementById('totalTarjetaCaja');
                    const elGeneral = document.getElementById('totalGeneralCaja');

                    if (elEfectivo) elEfectivo.textContent = `$${totalEfectivo.toFixed(2)}`;
                    if (elTarjeta) elTarjeta.textContent = `$${totalTarjeta.toFixed(2)}`;
                    if (elGeneral) elGeneral.textContent = `$${(totalEfectivo + totalTarjeta).toFixed(2)}`;
                }
            })
            .catch(err => console.error('Error al cargar historial:', err));
    }
});