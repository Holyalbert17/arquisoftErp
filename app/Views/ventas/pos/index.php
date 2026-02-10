<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pos.css') ?>">
<style>
    /* Modern POS Styles */
    :root {
        --primary-color: #4f46e5;
        --secondary-color: #6366f1;
        --success-color: #22c55e;
        --danger-color: #ef4444;
        --bg-gray: #f3f4f6;
    }

    .pos-container {
        display: flex;
        gap: 1.5rem;
        height: calc(100vh - 100px);
        /* Adjust based on navbar */
        overflow: hidden;
    }

    /* Left Panel: Products */
    .pos-products-area {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 1rem;
        overflow: hidden;
    }

    .search-bar-container {
        background: white;
        padding: 1rem;
        border-radius: 0.75rem;
        box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
    }

    .products-grid {
        flex: 1;
        overflow-y: auto;
        padding: 0.5rem;
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 1rem;
        align-content: start;
    }

    .product-card {
        background: white;
        border-radius: 0.5rem;
        border: 1px solid #e5e7eb;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        position: relative;
    }

    .product-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1);
        border-color: var(--primary-color);
    }

    .product-card.service-card {
        border-left: 4px solid var(--secondary-color);
        background: #fdfcff;
    }

    .product-img-placeholder {
        height: 100px;
        background: #f3f4f6;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #9ca3af;
        font-size: 2rem;
    }

    .product-info {
        padding: 0.75rem;
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .product-code {
        font-size: 0.75rem;
        color: #6b7280;
        margin-bottom: 0.25rem;
    }

    .product-name {
        font-weight: 600;
        font-size: 0.9rem;
        margin-bottom: 0.5rem;
        line-height: 1.2;
        color: #1f2937;
        flex: 1;
    }

    .product-price {
        font-weight: 700;
        color: var(--primary-color);
        font-size: 1.1rem;
    }

    .stock-badge {
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        background: rgba(255, 255, 255, 0.9);
        padding: 0.25rem 0.5rem;
        border-radius: 1rem;
        font-size: 0.75rem;
        font-weight: 600;
        box-shadow: 0 2px 4px rgb(0 0 0 / 0.05);
    }

    /* Right Panel: Cart */
    .pos-cart-area {
        width: 400px;
        display: flex;
        flex-direction: column;
        background: white;
        border-radius: 0.75rem;
        box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
        overflow: hidden;
    }

    .cart-header {
        padding: 1rem;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }

    .cart-items-container {
        flex: 1;
        overflow-y: auto;
        padding: 0;
    }

    .cart-item {
        display: flex;
        align-items: center;
        padding: 0.75rem;
        border-bottom: 1px solid #f1f5f9;
        gap: 0.75rem;
    }

    .cart-item.service-item {
        background-color: #f0fdf4;
        /* Light green tint for services */
    }

    .cart-item-info {
        flex: 1;
    }

    .cart-item-title {
        font-weight: 600;
        font-size: 0.9rem;
        margin-bottom: 0.1rem;
    }

    .cart-item-price {
        font-size: 0.85rem;
        color: #64748b;
    }

    .cart-controls {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-qty {
        width: 24px;
        height: 24px;
        border-radius: 0.25rem;
        border: 1px solid #cbd5e1;
        display: flex;
        align-items: center;
        justify-content: center;
        background: white;
        cursor: pointer;
        padding: 0;
    }

    .btn-qty:hover {
        background: #f1f5f9;
    }

    .cart-footer {
        padding: 1rem;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
    }

    .total-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
    }

    .grand-total {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--primary-color);
        margin-top: 0.5rem;
        border-top: 1px dashed #cbd5e1;
        padding-top: 0.5rem;
    }

    /* Scrollbar styling */
    ::-webkit-scrollbar {
        width: 6px;
    }

    ::-webkit-scrollbar-track {
        background: #f1f1f1;
    }

    ::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 3px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item active">Punto de Venta</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="container-fluid p-0">
    <div class="pos-container">
        <!-- LEFT PANEL: PRODUCTS -->
        <div class="pos-products-area">
            <!-- Search Bar -->
            <div class="search-bar-container">
                <div class="input-group input-group-lg">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-white border-right-0 text-muted">
                            <i class="fas fa-search"></i>
                        </span>
                    </div>
                    <input type="text" class="form-control border-left-0 pl-0" id="globalSearch"
                        placeholder="Buscar productos o servicios (Lista Retiro)... [F2]" autofocus autocomplete="off">
                    <div class="input-group-append">
                        <button class="btn btn-outline-secondary" type="button" id="btnRefreshProducts">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>
                <div class="d-flex mt-2 gap-2">
                    <span class="badge badge-light border" id="resultCount">0 resultados</span>
                    <span class="badge badge-success ml-2">Servicios Listos</span>
                </div>
            </div>

            <!-- Products Grid -->
            <div class="products-grid" id="productsGrid">
                <!-- Items will be injected here via JS -->
                <div class="text-center w-100 py-5 text-muted">
                    <i class="fas fa-barcode fa-3x mb-3"></i>
                    <p>Escriba para buscar...</p>
                </div>
            </div>
        </div>

        <!-- RIGHT PANEL: CART -->
        <div class="pos-cart-area">
            <!-- Header: Customer & Settings -->
            <div class="cart-header">
                <div class="form-group mb-2">
                    <select class="form-control form-control-sm select2" id="clienteSelect" style="width: 100%;">
                        <option value="">Cliente Ocasional</option>
                        <?php foreach ($clientes as $cliente): ?>
                            <?php
                            $nombre = $cliente['tipo_cliente'] == 'PERSONA'
                                ? ($cliente['nombre'] . ' ' . ($cliente['apellido'] ?? ''))
                                : ($cliente['nombre_fantasia'] ?? $cliente['razon_social']);
                            ?>
                            <option value="<?= $cliente['id'] ?>"
                                data-limite-credito="<?= $cliente['limite_credito'] ?? 0 ?>"
                                data-credito-utilizado="<?= $cliente['credito_utilizado'] ?? 0 ?>">
                                <?= esc($nombre) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row">
                    <div class="col-6 pr-1">
                        <select class="form-control form-control-sm" id="tipoComprobante">
                            <option value="TICKET">Ticket</option>
                            <option value="FACTURA">Factura</option>
                        </select>
                    </div>
                    <div class="col-6 pl-1">
                        <select class="form-control form-control-sm" id="listaPrecioSelect">
                            <option value="">Precio Normal</option>
                            <?php foreach ($listas_precios ?? [] as $lista): ?>
                                <option value="<?= $lista['id'] ?>"
                                    data-descuento="<?= $lista['descuento_general'] ?? 0 ?>"><?= esc($lista['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Cart Items -->
            <div class="cart-items-container" id="cartItems">
                <!-- Items will be injected here -->
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-shopping-cart mb-2"></i>
                    <p>Carrito vacío</p>
                </div>
            </div>

            <!-- Footer: Totals & Actions -->
            <div class="cart-footer">
                <div class="total-row">
                    <span class="text-muted">Subtotal</span>
                    <span id="lblSubtotal">0</span>
                </div>
                <!-- Discount Row Visibility managed by JS -->
                <div class="total-row text-danger" id="rowDiscount" style="display:none;">
                    <span>Descuento (<span id="discountPercent">0</span>%)</span>
                    <span id="lblDescuento">0</span>
                </div>
                <div class="total-row">
                    <span class="text-muted">IVA Total</span>
                    <span id="lblIva">0</span>
                </div>

                <div class="d-flex justify-content-between align-items-end mb-3">
                    <h5 class="mb-0 font-weight-bold">TOTAL</h5>
                    <div class="grand-total" id="lblTotal">0</div>
                </div>

                <div class="row no-gutters gap-2">
                    <div class="col-12 mb-2">
                        <select id="tipoPago"
                            class="form-control form-control-lg font-weight-bold text-center bg-light">
                            <option value="CONTADO">CONTADO</option>
                            <option value="CREDITO">CRÉDITO</option>
                            <option value="MIXTO">MIXTO</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-success btn-lg btn-block shadow-sm" id="btnPay">
                            <i class="fas fa-check-circle mr-2"></i> COBRAR
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Pago (Mixed Payments) -->
<div class="modal fade" id="modalPago" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-cash-register mr-2"></i> Finalizar Venta</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light">
                <div class="row h-100">
                    <!-- Left: Totals Summary (Wider to prevent wrapping) -->
                    <div class="col-lg-5 mb-3 mb-lg-0">
                        <div class="card shadow-sm h-100 border-0">
                            <div class="card-body text-center d-flex flex-column justify-content-center p-4">
                                <h6 class="text-muted text-uppercase ls-1">Total a Pagar</h6>
                                <!-- Prevent wrapping with text-nowrap and slightly responsive font -->
                                <h1 id="modalTotalPay" class="font-weight-bold text-success mb-0 text-nowrap"
                                    style="font-size: 3rem;">0</h1>
                                <small class="text-muted" id="modalDiscountInfo" style="display:none;">(Incluye
                                    descuento)</small>

                                <hr class="w-75 mx-auto my-4">

                                <div class="d-flex justify-content-between mb-3 px-2">
                                    <span class="text-muted h5 mb-0">Total Ingresado:</span>
                                    <span id="lblTotalIngresado" class="font-weight-bold h5 mb-0">0</span>
                                </div>
                                <div class="d-flex justify-content-between px-2">
                                    <span class="text-muted h5 mb-0" id="lblRestanteTitulo">Restante:</span>
                                    <span id="lblRestante" class="font-weight-bold text-danger h5 mb-0">0</span>
                                </div>

                                <div id="changeContainer" class="mt-4 p-3 bg-success-light rounded"
                                    style="display:none; background-color: #d1e7dd;">
                                    <h6 class="text-success mb-0">VUELTO</h6>
                                    <h3 id="lblVuelto" class="font-weight-bold text-success mb-0">0</h3>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Payment Methods (Narrower) -->
                    <div class="col-lg-7">
                        <div class="card shadow-sm border-0 h-100">
                            <div
                                class="card-header bg-white border-bottom-0 d-flex justify-content-between align-items-center py-3">
                                <h5 class="font-weight-bold mb-0">Métodos de Pago</h5>
                                <button class="btn btn-outline-primary" id="btnAddPaymentMethod">
                                    <i class="fas fa-plus-circle mr-1"></i> Agregar Pago
                                </button>
                            </div>
                            <div class="card-body p-3" style="max-height: 400px; overflow-y: auto;">
                                <div id="paymentMethodsContainer">
                                    <!-- Payment Rows will be injected here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between bg-white py-3">
                <button type="button" class="btn btn-secondary btn-lg" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success btn-lg px-5 font-weight-bold shadow" id="btnConfirmPay"
                    style="font-size: 1.25rem;">
                    <i class="fas fa-check mr-2"></i> CONFIRMAR VENTA
                </button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    // Utility
    const formatGs = (num) => new Intl.NumberFormat('es-PY', { style: 'currency', currency: 'PYG', maximumFractionDigits: 0 }).format(num);
    const parseGs = (str) => {
        if (!str) return 0;
        return parseFloat(String(str).replace(/[^\d-]/g, '')) || 0;
    };
    // No decimals for PYG
    const formatNumber = (num) => new Intl.NumberFormat('es-PY', { maximumFractionDigits: 0 }).format(num);

    (function ($) {
        'use strict';

        let cart = [];
        let allProducts = [];
        let typingTimer;

        // Helper function for Toasts using SweetAlert2
        function showToast(icon, title) {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer)
                    toast.addEventListener('mouseleave', Swal.resumeTimer)
                }
            });

            Toast.fire({
                icon: icon,
                title: title
            });
        }

        $(document).ready(function () {
            // Auto-fix sequences on load to prevent ID errors
            $.get('<?= base_url("ventas/pos/fix-sequences") ?>');

            // Fix for ARIA accessibility error
            $('#modalPago').on('shown.bs.modal', function () {
                $(this).removeAttr('aria-hidden');
            });

            // Check for Open Box
            <?php if (!empty($mensaje_caja)): ?>
                Swal.fire({
                    icon: 'warning',
                    title: 'Atención',
                    text: '<?= $mensaje_caja ?>',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showCancelButton: true,
                    confirmButtonText: 'Ir a Abrir Caja',
                    cancelButtonText: 'Volver al Inicio',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = '<?= base_url("finanzas/cajas") ?>';
                    } else {
                        window.location.href = '<?= base_url("dashboard") ?>';
                    }
                });
            <?php endif; ?>

            // Initial Focus
            $('#globalSearch').focus();

            // Event Listeners
            $('#globalSearch').on('keyup', function () {
                clearTimeout(typingTimer);
                typingTimer = setTimeout(searchProducts, 300);
            });

            $('#btnRefreshProducts').click(searchProducts);

            // Reload products when Price List changes & Recalculate Cart
            $('#listaPrecioSelect').change(function () {
                showToast('info', 'Actualizando precios...');
                searchProducts();     // Update grid
                recalculateCartPrices(); // Update cart
            });

            $('#btnPay').click(openPayModal);
            $('#btnAddPaymentMethod').click(function () { addPaymentRow(0); });
            $('#btnConfirmPay').click(processSale);

            // Shortcuts
            $(document).on('keydown', function (e) {
                if (e.key === 'F2') { e.preventDefault(); $('#globalSearch').focus(); }
            });

            // --- SCANNER LOGIC ---
            let barcodeBuffer = '';
            let lastKeyTime = 0;
            const MAX_TIME_BETWEEN_KEYS = 100; // ms

            // Global Listener (for when focus is NOT on an input)
            $(document).on('keydown', function (e) {
                const target = $(e.target);
                // If user is typing in an input, let them type (except if it's body or button)
                if (target.is('input') || target.is('textarea') || target.is('select')) {
                    return;
                }

                const currentTime = new Date().getTime();

                // Check flow
                if (currentTime - lastKeyTime > MAX_TIME_BETWEEN_KEYS) {
                    // If too slow, reset buffer, assume manual typing or start of new scan
                    // But we must be careful not to reset if it's the first char of the scan
                    // We check if buffer was non-empty before.
                    if (barcodeBuffer.length > 0 && e.key !== 'Enter') {
                        barcodeBuffer = '';
                    }
                }
                lastKeyTime = currentTime;

                if (e.key === 'Enter') {
                    if (barcodeBuffer.length >= 3) {
                        e.preventDefault();
                        handleScannedCode(barcodeBuffer);
                    }
                    barcodeBuffer = '';
                } else if (e.key.length === 1) {
                    // Printable char
                    barcodeBuffer += e.key;
                }
            });

            // Search Box Listener (Scanner acts as keyboard typing here too)
            $('#globalSearch').on('keypress', function (e) {
                if (e.which === 13) { // Enter
                    e.preventDefault();
                    const val = $(this).val().trim();
                    if (val) {
                        handleScannedCode(val);
                        $(this).val(''); // Clear after scan
                    }
                }
            });

            function handleScannedCode(code) {
                // showToast('info', 'Buscando: ' + code);

                $.post('<?= base_url("ventas/pos/buscarProducto") ?>', {
                    termino: code,
                    lista_precio_id: $('#listaPrecioSelect').val()
                }).done(function (res) {
                    if (res.success && res.data && res.data.length > 0) {
                        // Priority: Exact Barcode Match > Exact Code Match > Single Result
                        let match = res.data.find(p => p.codigo_barra === code);
                        if (!match) match = res.data.find(p => p.codigo === code);

                        if (match) {
                            addToCart(match);
                            showToast('success', 'Agregado: ' + match.nombre);
                        } else if (res.data.length === 1) {
                            addToCart(res.data[0]);
                            showToast('success', 'Agregado: ' + res.data[0].nombre);
                        } else {
                            // Multiple partial matches
                            $('#globalSearch').val(code);
                            renderGrid(res.data);
                            showToast('warning', 'Múltiples coincidencias, seleccione uno.');
                        }
                    } else {
                        showToast('error', 'Producto no encontrado');
                    }
                });
            }
        });

        // Input Formatting Listener
        $(document).on('input', '.payment-amount', function () {
            let val = $(this).val();
            let num = val.replace(/\D/g, '');
            if (num === '') {
                $(this).val('');
            } else {
                $(this).val(formatNumber(parseInt(num, 10)));
            }
            calculatePaymentTotals();
        });

        $(document).on('click', '.btn-remove-payment', function () {
            $(this).closest('.payment-row').remove();
            calculatePaymentTotals();
        });

        $(document).on('click', '.btn-max-payment', function () {
            const total = getCartTotal();
            const currentEntered = getEnteredTotal();
            const rowInput = $(this).closest('.input-group').find('.payment-amount');

            const selfVal = parseGs(rowInput.val());
            const otherEntered = currentEntered - selfVal;

            const remaining = Math.max(0, total - otherEntered);
            rowInput.val(formatNumber(remaining));
            calculatePaymentTotals();
        });

        // --- Price List & Cart Logic ---
        function recalculateCartPrices() {
            const listaId = $('#listaPrecioSelect').val();
            if (!listaId || cart.length === 0) return;

            const productIds = cart.filter(i => i.type !== 'SERVICIO').map(i => i.id);
            if (productIds.length === 0) return;

            $.post('<?= base_url("ventas/pos/obtenerPrecios") ?>', {
                lista_precio_id: listaId,
                producto_ids: productIds
            })
                .done(function (res) {
                    if (res.success && res.data) {
                        let updatedCount = 0;
                        cart.forEach(item => {
                            if (item.type !== 'SERVICIO' && res.data[item.id] !== undefined) {
                                item.price = parseFloat(res.data[item.id]);
                                updatedCount++;
                            }
                        });
                        renderCart();
                        if (updatedCount > 0) showToast('success', 'Precios del carrito actualizados');
                    }
                });
        }

        // --- Search Logic ---
        function searchProducts() {
            const term = $('#globalSearch').val().trim();
            const listaId = $('#listaPrecioSelect').val();

            if (!term && allProducts.length > 0 && !listaId) { renderGrid(allProducts); return; }

            $('#productsGrid').html('<div class="col-12 text-center py-5"><div class="spinner-border text-primary"></div></div>');

            $.post('<?= base_url("ventas/pos/buscarProducto") ?>', {
                termino: term,
                lista_precio_id: listaId
            })
                .done(response => {
                    if (response.success) {
                        allProducts = response.data || [];
                        renderGrid(allProducts);
                    }
                })
                .fail(() => {
                    $('#productsGrid').html('<div class="text-center text-danger p-4">Error al buscar</div>');
                });
        }

        function renderGrid(products) {
            const container = $('#productsGrid');
            container.empty();
            $('#resultCount').text(`${products.length} resultados`);

            if (products.length === 0) {
                container.html('<div class="col-12 text-center text-muted py-5">No se encontraron productos</div>');
                return;
            }

            products.forEach(p => {
                const isService = p.type === 'SERVICIO';
                const hasStock = isService || p.stock_disponible > 0 || !p.controla_stock;
                const price = parseFloat(p.precio_venta || 0);

                const card = $(`
                    <div class="product-card ${isService ? 'service-card' : ''}" title="${p.nombre}">
                        <div class="stock-badge ${hasStock ? 'text-success' : 'text-danger'}">
                            ${isService ? '<i class="fas fa-tools"></i> Serv.' : (hasStock ? p.stock_disponible : 'Sin Stock')}
                        </div>
                        ${p.en_oferta ? '<div class="stock-badge bg-warning text-dark" style="right: auto; left: 0.5rem;"><i class="fas fa-tag"></i> Oferta</div>' : ''}
                        <div class="product-img-placeholder" style="overflow: hidden; padding: 0;">
                             ${isService
                        ? '<i class="fas fa-clipboard-list text-primary"></i>'
                        : (p.imagen_principal
                            ? `<img src="<?= base_url('uploads/productos/') ?>/${p.imagen_principal}" alt="${p.nombre}" style="width: 100%; height: 100%; object-fit: contain;">`
                            : '<i class="fas fa-box"></i>')
                    }
                        </div>
                        <div class="product-info">
                            <div class="product-code text-truncate">${p.codigo || ''}</div>
                            <div class="product-name text-truncate" style="max-height: 2.4em; overflow: hidden;">${p.nombre}</div>
                            <div class="product-price">${formatGs(price)}</div>
                        </div>
                    </div>
                `);

                if (hasStock) {
                    card.click(() => addToCart(p));
                } else {
                    card.css('opacity', '0.6').css('cursor', 'not-allowed');
                }

                container.append(card);
            });
        }

        function addToCart(product) {
            const existing = cart.find(i => i.id == product.id && i.type == product.type);

            if (product.type === 'SERVICIO') {
                if (existing) {
                    showToast('warning', 'La orden ya está en el ticket');
                    return;
                }
                cart.push({ ...product, qty: 1, price: parseFloat(product.precio_venta) });
            } else {
                if (existing) {
                    if (product.controla_stock && existing.qty + 1 > product.stock_disponible) {
                        showToast('error', 'Sin stock suficiente');
                        return;
                    }
                    existing.qty++;
                } else {
                    cart.push({ ...product, qty: 1, price: parseFloat(product.precio_venta), type: 'PRODUCTO' });
                }
            }
            renderCart();
        }

        function renderCart() {
            const container = $('#cartItems');
            container.empty();
            let subtotal = 0;

            if (cart.length === 0) {
                container.html('<div class="text-center py-5 text-muted"><p>Carrito vacío</p></div>');
                updateTotals(0, 0, 0, 0);
                return;
            }

            cart.forEach((item, idx) => {
                const lineTotal = item.qty * item.price;
                subtotal += lineTotal;
                const isService = item.type === 'SERVICIO';

                const row = $(`
                    <div class="cart-item ${isService ? 'service-item' : ''}">
                        <div class="cart-item-info text-truncate">
                            <div class="cart-item-title text-truncate" title="${item.nombre}">${item.nombre}</div>
                            <div class="cart-item-price">${formatGs(item.price)} x ${item.qty}</div>
                        </div>
                        <div class="cart-controls">
                            ${!isService ? `<button class="btn-qty minus" data-idx="${idx}"><i class="fas fa-minus small"></i></button>` : ''}
                            <span class="font-weight-bold mx-1">${item.qty}</span>
                            ${!isService ? `<button class="btn-qty plus" data-idx="${idx}"><i class="fas fa-plus small"></i></button>` : ''}
                            <button class="btn-qty text-danger remove ml-1" data-idx="${idx}"><i class="fas fa-trash-alt small"></i></button>
                        </div>
                    </div>
                `);
                container.append(row);
            });

            // Handlers
            $('.plus').click(function () {
                const idx = $(this).data('idx');
                const item = cart[idx];
                if (item.controla_stock && item.qty + 1 > item.stock_disponible) { showToast('error', 'Sin stock'); return; }
                item.qty++; renderCart();
            });
            $('.minus').click(function () {
                const idx = $(this).data('idx');
                if (cart[idx].qty > 1) { cart[idx].qty--; renderCart(); }
            });
            $('.remove').click(function () {
                cart.splice($(this).data('idx'), 1); renderCart();
            });

            // Calculate Discount
            const discountPercent = parseFloat($('#listaPrecioSelect option:selected').data('descuento')) || 0;
            const discountAmount = Math.round(subtotal * (discountPercent / 100));
            const total = subtotal - discountAmount;

            updateTotals(subtotal, discountAmount, total, discountPercent);
        }

        function updateTotals(sub, disc, tot, percent) {
            $('#lblSubtotal').text(formatGs(sub));
            $('#lblDescuento').text((disc > 0 ? '-' : '') + formatGs(disc));
            $('#lblTotal').text(formatGs(tot));
            $('#lblIva').text(formatGs(Math.round(tot / 11)));

            if (disc > 0 || percent > 0) {
                $('#rowDiscount').show();
                $('#discountPercent').text(percent);
                $('#modalDiscountInfo').show().text(`(Incluye ${percent}% descuento)`);
            } else {
                $('#rowDiscount').hide();
                $('#modalDiscountInfo').hide();
            }

            // Update Modal Total too
            $('#modalTotalPay').text(formatGs(tot));
        }

        function getCartTotal() {
            const subtotal = cart.reduce((acc, i) => acc + (i.qty * i.price), 0);
            const discountPercent = parseFloat($('#listaPrecioSelect option:selected').data('descuento')) || 0;
            const discountAmount = Math.round(subtotal * (discountPercent / 100));
            return subtotal - discountAmount;
        }

        // --- Mixed Payment Modal Logic ---
        function openPayModal() {
            if (cart.length === 0) { showToast('warning', 'Carrito vacío'); return; }

            $('#paymentMethodsContainer').empty();
            // Add default Cash row
            addPaymentRow(getCartTotal());

            calculatePaymentTotals();
            $('#modalPago').modal('show');
        }

        function addPaymentRow(amount = 0) {
            // Using type="text" for formatting
            const formattedAmount = amount > 0 ? formatNumber(amount) : '';

            const row = $(`
                <div class="payment-row d-flex gap-2 mb-2 align-items-center">
                    <select class="form-control payment-method" style="flex: 2;">
                        <option value="EFECTIVO">Efectivo</option>
                        <option value="TARJETA_CREDITO">Tarjeta Crédito</option>
                        <option value="TARJETA_DEBITO">Tarjeta Débito</option>
                        <option value="TRANSFERENCIA">Transferencia</option>
                        <option value="CHEQUE">Cheque</option>
                    </select>
                    <div class="input-group" style="flex: 3;">
                         <div class="input-group-prepend">
                             <button class="btn btn-outline-secondary btn-max-payment" type="button">Max</button>
                         </div>
                        <input type="text" class="form-control payment-amount text-end" value="${formattedAmount}" placeholder="0" autocomplete="off">
                    </div>
                    <button class="btn btn-outline-danger btn-remove-payment" style="width: 38px;"><i class="fas fa-trash"></i></button>
                </div>
            `);
            $('#paymentMethodsContainer').append(row);
            calculatePaymentTotals();
        }

        function calculatePaymentTotals() {
            let totalEntered = 0;
            $('.payment-amount').each(function () {
                totalEntered += parseGs($(this).val());
            });

            const totalToPay = getCartTotal();
            $('#lblTotalIngresado').text(formatGs(totalEntered));

            const diff = totalToPay - totalEntered;

            if (diff > 0) {
                $('#lblRestanteTitulo').text('Restante:').removeClass('text-success');
                $('#lblRestante').text(formatGs(diff)).removeClass('text-success').addClass('text-danger');
                $('#changeContainer').hide();
            } else {
                $('#lblRestanteTitulo').text('Cubierto').addClass('text-success');
                $('#lblRestante').text(formatGs(0)).removeClass('text-danger').addClass('text-success');

                if (diff < 0) {
                    $('#changeContainer').show();
                    $('#lblVuelto').text(formatGs(Math.abs(diff)));
                } else {
                    $('#changeContainer').hide();
                }
            }
        }

        function getEnteredTotal() {
            let t = 0;
            $('.payment-amount').each(function () {
                t += parseGs($(this).val());
            });
            return t;
        }

        function processSale() {
            const total = getCartTotal();
            const totalEntered = getEnteredTotal();

            // Validation
            if (totalEntered < total) {
                if ($('#tipoPago').val() !== 'CREDITO') {
                    showToast('error', 'El monto ingresado no cubre el total');
                    return;
                }
            }

            // Collect Payment Methods
            const formasPago = [];
            $('.payment-row').each(function () {
                const methodId = $(this).find('.payment-method').val();
                const amount = parseGs($(this).find('.payment-amount').val());
                if (amount > 0) {
                    formasPago.push({ forma_pago: methodId, monto: amount });
                }
            });

            if (formasPago.length === 0) {
                showToast('error', 'Ingrese al menos un pago'); return;
            }

            const data = {
                cliente_id: $('#clienteSelect').val(),
                productos: JSON.stringify(cart.map(i => ({
                    id: i.id,
                    cantidad: i.qty,
                    precio: i.price,
                    type: i.type || 'PRODUCTO'
                }))),
                formas_pago: JSON.stringify(formasPago),
                tipo_pago: (totalEntered >= total) ? 'CONTADO' : 'CREDITO',
                tipo_comprobante: $('#tipoComprobante').val(),
                moneda: 'PYG',
                lista_precio_id: $('#listaPrecioSelect').val(),
                descuento_general: parseFloat($('#listaPrecioSelect option:selected').data('descuento')) || 0
            };

            $('#btnConfirmPay').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Procesando...');

            $.post('<?= base_url("ventas/pos/procesar") ?>', data)
                .done(function (res) {
                    if (res.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Venta Exitosa',
                            html: `
                                <div class="mb-3">${res.data.vuelto > 0 ? `Vuelto: <b>${formatGs(res.data.vuelto)}</b>` : 'Venta completada'}</div>
                                ${res.data.print_url ? `<a href="${res.data.print_url}" target="_blank" class="btn btn-outline-primary btn-sm"><i class="fas fa-external-link-alt"></i> Abrir Comprobante Manualmente</a>` : ''}
                            `,
                            confirmButtonText: 'Imprimir Comprobante'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                console.log('Print URL:', res.data.print_url);
                                const url = res.data.print_url || res.data.redirect;
                                if (url) window.open(url, '_blank');
                            }
                            // Small delay to ensure window.open fires before reload
                            setTimeout(() => location.reload(), 1000);
                        });
                    } else {
                        Swal.fire('Error', res.message || 'Error desconocido', 'error');
                    }
                })
                .fail(function (xhr) {
                    Swal.fire('Error', 'Error servidor', 'error');
                })
                .always(() => {
                    $('#btnConfirmPay').prop('disabled', false).text('CONFIRMAR VENTA');
                });
        }
    })(jQuery);
</script>
<?= $this->endSection() ?>