<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('ventas') ?>">Ventas</a></li>
<li class="breadcrumb-item"><a href="<?= base_url('ventas/cotizaciones') ?>">Cotizaciones</a></li>
<li class="breadcrumb-item active"><?= $cotizacion ? 'Editar' : 'Nueva' ?> Cotización</li>
<?= $this->endSection() ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pos.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-file-invoice-dollar mr-1"></i>
                    <?= $cotizacion ? 'Editar' : 'Nueva' ?> Cotización
                </h3>
            </div>
            <div class="card-body">
                <form id="formCotizacion">
                    <?= csrf_field() ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Cliente <span class="text-danger">*</span></label>
                                <select class="form-control" id="clienteSelect" name="cliente_id" required>
                                    <option value="">Seleccione un cliente</option>
                                    <?php foreach ($clientes as $cliente): ?>
                                        <option value="<?= $cliente['id'] ?>" 
                                                data-credito-disponible="<?= $cliente['credito_disponible'] ?? 0 ?>">
                                            <?php
                                            if ($cliente['tipo_cliente'] == 'PERSONA') {
                                                echo esc(trim(($cliente['nombre'] ?? '') . ' ' . ($cliente['apellido'] ?? '')));
                                            } else {
                                                echo esc($cliente['nombre_fantasia'] ?? $cliente['razon_social'] ?? '');
                                            }
                                            ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Fecha Vencimiento <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="fecha_vencimiento" 
                                       value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Lista de Precios</label>
                                <select class="form-control" id="listaPrecioSelect" name="lista_precio_id">
                                    <option value="">General</option>
                                    <?php foreach ($listas_precios as $lista): ?>
                                        <option value="<?= $lista['id'] ?>"><?= esc($lista['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="input-group">
                                <input type="text" class="form-control" id="buscarProducto" 
                                       placeholder="Buscar producto por código, nombre o código de barras...">
                                <div class="input-group-append">
                                    <button class="btn btn-primary" type="button" id="btnBuscar">
                                        <i class="fas fa-search"></i> Buscar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Cantidad</th>
                                    <th>Precio</th>
                                    <th>Descuento %</th>
                                    <th>Subtotal</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody id="carritoBody">
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No hay productos agregados</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Descuento General (%)</label>
                                <input type="number" class="form-control" id="descuentoGeneral" 
                                       name="descuento_porcentaje" min="0" max="100" step="0.01" value="0">
                            </div>
                            <div class="form-group">
                                <label>Observaciones</label>
                                <textarea class="form-control" name="observaciones" rows="3"></textarea>
                            </div>
                            <div class="form-group">
                                <label>Términos y Condiciones</label>
                                <textarea class="form-control" name="terminos_condiciones" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Totales</h5>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm">
                                        <tr>
                                            <td><strong>Subtotal:</strong></td>
                                            <td class="text-right" id="subtotal">0 Gs.</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Descuento:</strong></td>
                                            <td class="text-right" id="descuento">0 Gs.</td>
                                        </tr>
                                        <tr>
                                            <td><strong>IVA 10%:</strong></td>
                                            <td class="text-right" id="iva10">0 Gs.</td>
                                        </tr>
                                        <tr>
                                            <td><strong>IVA 5%:</strong></td>
                                            <td class="text-right" id="iva5">0 Gs.</td>
                                        </tr>
                                        <tr class="table-primary">
                                            <td><strong>TOTAL:</strong></td>
                                            <td class="text-right"><strong id="total">0 Gs.</strong></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="card-footer">
                <button type="button" class="btn btn-primary" id="btnGuardar">
                    <i class="fas fa-save"></i> Guardar Cotización
                </button>
                <a href="<?= base_url('ventas/cotizaciones') ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Productos Disponibles</h3>
            </div>
            <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                <div id="productosGrid" class="pos-product-grid">
                    <?php foreach ($productos as $producto): ?>
                        <div class="pos-product-card" data-producto-id="<?= $producto['id'] ?>"
                             data-nombre="<?= esc($producto['nombre']) ?>"
                             data-precio="<?= $producto['precio_venta'] ?>"
                             data-tipo-iva="<?= $producto['tipo_iva'] ?>">
                            <div class="product-name"><?= esc($producto['nombre']) ?></div>
                            <div class="product-price"><?= formatGs($producto['precio_venta']) ?></div>
                            <div class="product-code"><?= esc($producto['codigo']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
let carrito = [];

$(document).ready(function() {
    // Buscar producto
    $('#btnBuscar, #buscarProducto').on('keypress', function(e) {
        if (e.which === 13 || $(this).attr('id') === 'btnBuscar') {
            e.preventDefault();
            buscarProducto();
        }
    });

    // Agregar producto desde grid
    $('.pos-product-card').on('click', function() {
        const productoId = $(this).data('producto-id');
        const nombre = $(this).data('nombre');
        const precio = parseFloat($(this).data('precio'));
        const tipoIva = $(this).data('tipo-iva');
        
        agregarAlCarrito({
            id: productoId,
            nombre: nombre,
            precio: precio,
            cantidad: 1,
            descuento: 0,
            tipo_iva: tipoIva
        });
    });

    // Cambios en carrito
    $(document).on('change', '.cantidad-item', function() {
        const index = $(this).data('index');
        carrito[index].cantidad = parseFloat($(this).val()) || 1;
        actualizarCarrito();
    });

    $(document).on('change', '.descuento-item', function() {
        const index = $(this).data('index');
        carrito[index].descuento = parseFloat($(this).val()) || 0;
        actualizarCarrito();
    });

    $(document).on('click', '.btn-eliminar-item', function() {
        const index = $(this).data('index');
        carrito.splice(index, 1);
        actualizarCarrito();
    });

    // Cambio en descuento general
    $('#descuentoGeneral').on('change', function() {
        actualizarCarrito();
    });

    // Guardar cotización
    $('#btnGuardar').on('click', function() {
        guardarCotizacion();
    });
});

function buscarProducto() {
    const termino = $('#buscarProducto').val().trim();
    if (!termino) return;

    $.ajax({
        url: '<?= base_url("ventas/pos/buscarProducto") ?>',
        method: 'POST',
        data: { termino: termino },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data.length > 0) {
                const producto = response.data[0];
                agregarAlCarrito({
                    id: producto.id,
                    nombre: producto.nombre,
                    precio: producto.precio_venta,
                    cantidad: 1,
                    descuento: 0,
                    tipo_iva: producto.tipo_iva
                });
                $('#buscarProducto').val('');
            } else {
                Swal.fire('No encontrado', 'No se encontró ningún producto', 'info');
            }
        }
    });
}

function agregarAlCarrito(producto) {
    const existente = carrito.findIndex(p => p.id === producto.id);
    if (existente >= 0) {
        carrito[existente].cantidad += producto.cantidad;
    } else {
        carrito.push(producto);
    }
    actualizarCarrito();
}

function actualizarCarrito() {
    let html = '';
    let subtotal = 0;
    let descuentoTotal = 0;
    let subtotalGravado10 = 0;
    let subtotalGravado5 = 0;
    let subtotalExento = 0;
    let iva10 = 0;
    let iva5 = 0;

    if (carrito.length === 0) {
        html = '<tr><td colspan="6" class="text-center text-muted">No hay productos agregados</td></tr>';
    } else {
        carrito.forEach(function(item, index) {
            const subtotalLinea = item.precio * item.cantidad;
            const descuentoLinea = (subtotalLinea * item.descuento) / 100;
            const subtotalConDescuento = subtotalLinea - descuentoLinea;
            
            subtotal += subtotalConDescuento;
            descuentoTotal += descuentoLinea;

            if (item.tipo_iva == 'GRAVADO_10') {
                subtotalGravado10 += subtotalConDescuento;
                iva10 += (subtotalConDescuento * 10) / 110;
            } else if (item.tipo_iva == 'GRAVADO_5') {
                subtotalGravado5 += subtotalConDescuento;
                iva5 += (subtotalConDescuento * 5) / 105;
            } else {
                subtotalExento += subtotalConDescuento;
            }

            html += `
                <tr>
                    <td>${item.nombre}</td>
                    <td>
                        <input type="number" class="form-control form-control-sm cantidad-item" 
                               value="${item.cantidad}" min="1" data-index="${index}" style="width: 60px;">
                    </td>
                    <td>${formatCurrency(item.precio)}</td>
                    <td>
                        <input type="number" class="form-control form-control-sm descuento-item" 
                               value="${item.descuento}" min="0" max="100" step="0.01" 
                               data-index="${index}" style="width: 70px;" placeholder="%">
                    </td>
                    <td>${formatCurrency(subtotalConDescuento)}</td>
                    <td>
                        <button class="btn btn-sm btn-danger btn-eliminar-item" data-index="${index}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
    }

    // Aplicar descuento general
    const descuentoGeneral = parseFloat($('#descuentoGeneral').val()) || 0;
    if (descuentoGeneral > 0) {
        const descuentoGeneralMonto = (subtotal * descuentoGeneral) / 100;
        subtotal -= descuentoGeneralMonto;
        descuentoTotal += descuentoGeneralMonto;
    }

    $('#carritoBody').html(html);
    const total = subtotal + iva10 + iva5;
    
    $('#subtotal').text(formatCurrency(subtotal));
    $('#descuento').text(formatCurrency(descuentoTotal));
    $('#iva10').text(formatCurrency(iva10));
    $('#iva5').text(formatCurrency(iva5));
    $('#total').text(formatCurrency(total));
}

function guardarCotizacion() {
    if (carrito.length === 0) {
        Swal.fire('Advertencia', 'Debe agregar al menos un producto', 'warning');
        return;
    }

    const clienteId = $('#clienteSelect').val();
    if (!clienteId) {
        Swal.fire('Error', 'Debe seleccionar un cliente', 'error');
        return;
    }

    const fechaVencimiento = $('input[name="fecha_vencimiento"]').val();
    if (!fechaVencimiento) {
        Swal.fire('Error', 'Debe especificar la fecha de vencimiento', 'error');
        return;
    }

    const data = {
        cliente_id: clienteId,
        lista_precio_id: $('#listaPrecioSelect').val() || null,
        fecha_vencimiento: fechaVencimiento,
        descuento_porcentaje: parseFloat($('#descuentoGeneral').val()) || 0,
        productos: JSON.stringify(carrito),
        observaciones: $('textarea[name="observaciones"]').val() || '',
        terminos_condiciones: $('textarea[name="terminos_condiciones"]').val() || ''
    };

    $.ajax({
        url: '<?= base_url("ventas/cotizaciones/guardar") ?>',
        method: 'POST',
        data: data,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Cotización Creada',
                    text: response.message,
                    showConfirmButton: false,
                    timer: 2000
                }).then(() => {
                    window.location.href = response.data.redirect;
                });
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        },
        error: function(xhr) {
            Swal.fire('Error', 'Error al guardar la cotización', 'error');
        }
    });
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('es-PY', {
        style: 'currency',
        currency: 'PYG',
        minimumFractionDigits: 0
    }).format(amount);
}
</script>
<?= $this->endSection() ?>
