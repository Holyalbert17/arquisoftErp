<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('inventario') ?>">Inventario</a></li>
<li class="breadcrumb-item"><a href="<?= base_url('inventario/transferencias') ?>">Transferencias</a></li>
<li class="breadcrumb-item active">Nueva Transferencia</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <form id="formTransferencia">
            <!-- Información de la Transferencia -->
            <div class="card card-outline card-primary">
                <div class="card-header bg-primary">
                    <h3 class="card-title text-white">
                        <i class="fas fa-exchange-alt mr-2"></i> Nueva Transferencia de Stock
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="sucursalDestino">
                                    <i class="fas fa-store mr-1"></i> Sucursal Destino <span class="text-danger">*</span>
                                </label>
                                <select class="form-control select2" id="sucursalDestino" name="sucursal_destino_id" required>
                                    <option value="">Seleccione una sucursal...</option>
                                    <?php foreach ($sucursales_destino as $sucursal): ?>
                                        <option value="<?= $sucursal['id'] ?>"><?= esc($sucursal['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted">Seleccione la sucursal destino para la transferencia</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="observaciones">
                                    <i class="fas fa-sticky-note mr-1"></i> Observaciones
                                </label>
                                <textarea class="form-control" id="observaciones" name="observaciones" rows="3" 
                                          placeholder="Notas adicionales sobre la transferencia..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Búsqueda de Productos -->
            <div class="card card-outline card-success mt-3">
                <div class="card-header bg-success">
                    <h3 class="card-title text-white">
                        <i class="fas fa-search mr-2"></i> Buscar Productos
                    </h3>
                </div>
                <div class="card-body">
                    <div class="input-group input-group-lg">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white">
                                <i class="fas fa-barcode text-success"></i>
                            </span>
                        </div>
                        <input type="text" class="form-control" id="buscarProducto" 
                               placeholder="Escanee código de barras o escriba código/nombre del producto..." autofocus>
                        <div class="input-group-append">
                            <button class="btn btn-success btn-lg" type="button" id="btnBuscar">
                                <i class="fas fa-search mr-1"></i> Buscar
                            </button>
                        </div>
                    </div>
                    <small class="form-text text-muted mt-2">
                        <i class="fas fa-info-circle"></i> Presione Enter para buscar rápidamente
                    </small>
                </div>
            </div>

            <!-- Productos Agregados -->
            <div class="card card-outline card-info mt-3">
                <div class="card-header bg-info">
                    <h3 class="card-title text-white">
                        <i class="fas fa-boxes mr-2"></i> Productos a Transferir
                        <span class="badge badge-light ml-2" id="contadorProductos">0</span>
                    </h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="tablaProductos">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 5%;">#</th>
                                    <th style="width: 30%;">Producto</th>
                                    <th style="width: 15%;">Código</th>
                                    <th style="width: 15%;" class="text-center">Stock Disponible</th>
                                    <th style="width: 15%;" class="text-center">Cantidad <span class="text-danger">*</span></th>
                                    <th style="width: 10%;">Lote</th>
                                    <th style="width: 10%;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="productosBody">
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="fas fa-box-open fa-2x mb-2 d-block"></i>
                                        No hay productos agregados. Busque y agregue productos para transferir.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Botones de Acción -->
            <div class="card mt-3">
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 text-right">
                            <a href="<?= base_url('inventario/transferencias') ?>" class="btn btn-secondary">
                                <i class="fas fa-times mr-1"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save mr-1"></i> Guardar Transferencia
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal de Búsqueda de Productos -->
<div class="modal fade" id="modalBuscarProducto" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success">
                <h5 class="modal-title text-white">
                    <i class="fas fa-search mr-2"></i> Resultados de Búsqueda
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="resultadosProductos">
                    <p class="text-center text-muted">Realice una búsqueda para ver resultados</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
let productosAgregados = [];
let productos = [];
let productosResultados = [];

$(document).ready(function() {
    $('.select2').select2({
        theme: 'bootstrap4',
        width: '100%'
    });

    // Buscar producto
    function ejecutarBusquedaProducto(e) {
        if (e) {
            e.preventDefault();
        }

        const termino = $('#buscarProducto').val().trim();
        if (!termino) {
            $('#buscarProducto').focus();
            return;
        }

        buscarProductos(termino);
    }

    $('#btnBuscar').on('click', ejecutarBusquedaProducto);

    $('#buscarProducto').on('keypress', function(e) {
        if (e.which !== 13) return;
        ejecutarBusquedaProducto(e);
    });

    // Enviar formulario
    $('#formTransferencia').on('submit', function(e) {
        e.preventDefault();
        guardarTransferencia();
    });
});

function buscarProductos(termino) {
    $.ajax({
        url: '<?= base_url("ventas/pos/buscarProducto") ?>',
        method: 'POST',
        data: { termino: termino },
        dataType: 'json',
        beforeSend: function() {
            $('#resultadosProductos').html('<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i></div>');
        },
        success: function(response) {
            if (response.success && response.data.length > 0) {
                mostrarResultados(response.data);
                $('#modalBuscarProducto').modal('show');
            } else {
                Swal.fire('Sin resultados', 'No se encontraron productos', 'info');
            }
        },
        error: function() {
            Swal.fire('Error', 'Error al buscar productos', 'error');
        }
    });
}

function mostrarResultados(productos) {
    productosResultados = Array.isArray(productos) ? productos : [];
    let html = '<div class="table-responsive"><table class="table table-hover">';
    html = '<div class="table-responsive">';
    html += '<table class="table table-hover table-sm mb-0">';
    html += '<thead class="thead-light"><tr><th>Producto</th><th>Código</th><th class="text-center">Stock</th><th class="text-right">Acción</th></tr></thead><tbody>';
    
    productosResultados.forEach(function(producto, idx) {
        const stock = Math.floor(parseFloat(producto.stock_disponible || 0));
        const puedeAgregar = !producto.controla_stock || stock > 0;
        const yaAgregado = productosAgregados.find(p => p.id == producto.id);

        const badgeStock = producto.controla_stock !== false
            ? (stock > 0
                ? `<span class="badge badge-success">${stock.toFixed(0)}</span>`
                : `<span class="badge badge-danger">0</span>`)
            : '<span class="badge badge-secondary">Sin control</span>';
        
        html += `
            <tr class="${yaAgregado ? 'table-secondary' : ''}">
                <td><strong>${producto.nombre}</strong></td>
                <td>${producto.codigo || '-'}</td>
                <td class="text-center">${badgeStock}</td>
                <td class="text-right">
                    ${yaAgregado ? 
                        '<span class="badge badge-info">Ya agregado</span>' :
                        `<button class="btn btn-sm btn-success btn-agregar-producto" data-index="${idx}" ${!puedeAgregar ? 'disabled' : ''}>
                            <i class="fas fa-plus mr-1"></i> Agregar
                         </button>`
                    }
                </td>
            </tr>
        `;
    });
    
    html += '</tbody></table></div>';
    $('#resultadosProductos').html(html);
}

$(document).on('click', '.btn-agregar-producto', function() {
    const index = parseInt($(this).data('index'), 10);
    if (Number.isNaN(index) || !productosResultados[index]) {
        return;
    }
    const producto = productosResultados[index];
    agregarProducto(producto);
    $('#modalBuscarProducto').modal('hide');
    $('#buscarProducto').val('').focus();
});

function agregarProducto(producto) {
    const stock = Math.floor(parseFloat(producto.stock_disponible || 0));
    
    productosAgregados.push({
        id: producto.id,
        nombre: producto.nombre,
        codigo: producto.codigo || '',
        stock_disponible: stock,
        cantidad: 1,
        lote: '',
        observaciones: ''
    });
    
    actualizarTablaProductos();
}

function actualizarTablaProductos() {
    let html = '';
    
    if (productosAgregados.length === 0) {
        html = `
            <tr>
                <td colspan="7" class="text-center text-muted py-4">
                    <i class="fas fa-box-open fa-2x mb-2 d-block"></i>
                    No hay productos agregados
                </td>
            </tr>
        `;
    } else {
        productosAgregados.forEach(function(producto, index) {
            html += `
                <tr>
                    <td>${index + 1}</td>
                    <td><strong>${producto.nombre}</strong></td>
                    <td>${producto.codigo}</td>
                    <td class="text-center">
                        <span class="badge badge-${producto.stock_disponible > 0 ? 'success' : 'danger'}">
                            ${producto.stock_disponible.toFixed(0)}
                        </span>
                    </td>
                    <td class="text-center">
                        <input type="number" class="form-control form-control-sm cantidad-producto text-center" 
                               data-index="${index}" value="${producto.cantidad}" 
                               min="1" step="1" max="${Math.floor(producto.stock_disponible)}" 
                               style="width: 100px;" required>
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm lote-producto" 
                               data-index="${index}" value="${producto.lote}" 
                               placeholder="Opcional">
                    </td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-danger btn-eliminar-producto" data-index="${index}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
    }
    
    $('#productosBody').html(html);
    $('#contadorProductos').text(productosAgregados.length);
}

$(document).on('change', '.cantidad-producto', function() {
    const index = $(this).data('index');
    const cantidad = parseInt($(this).val(), 10) || 0;
    const maxStock = Math.floor(productosAgregados[index].stock_disponible);
    
    if (cantidad > maxStock) {
        Swal.fire('Error', `La cantidad no puede ser mayor al stock disponible (${maxStock})`, 'error');
        $(this).val(maxStock);
        productosAgregados[index].cantidad = maxStock;
    } else {
        productosAgregados[index].cantidad = cantidad;
    }
});

$(document).on('change', '.lote-producto', function() {
    const index = $(this).data('index');
    productosAgregados[index].lote = $(this).val();
});

$(document).on('click', '.btn-eliminar-producto', function() {
    const index = $(this).data('index');
    productosAgregados.splice(index, 1);
    actualizarTablaProductos();
});

function guardarTransferencia() {
    if (productosAgregados.length === 0) {
        Swal.fire('Advertencia', 'Debe agregar al menos un producto', 'warning');
        return;
    }

    const sucursalDestino = $('#sucursalDestino').val();
    if (!sucursalDestino) {
        Swal.fire('Advertencia', 'Debe seleccionar una sucursal destino', 'warning');
        return;
    }

    // Validar cantidades
    let hayErrores = false;
    productosAgregados.forEach(function(producto, index) {
        if (producto.cantidad <= 0 || producto.cantidad > producto.stock_disponible) {
            hayErrores = true;
        }
    });

    if (hayErrores) {
        Swal.fire('Error', 'Revise las cantidades de los productos', 'error');
        return;
    }

    const btnSubmit = $('#formTransferencia button[type="submit"]');
    const originalText = btnSubmit.html();
    btnSubmit.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...');

    const data = {
        sucursal_destino_id: sucursalDestino,
        productos: JSON.stringify(productosAgregados),
        observaciones: $('#observaciones').val()
    };

    $.ajax({
        url: '<?= base_url("inventario/transferencias/crear") ?>',
        method: 'POST',
        data: data,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: response.message,
                    showConfirmButton: true
                }).then(() => {
                    if (response.data && response.data.redirect) {
                        window.location.href = response.data.redirect;
                    } else {
                        window.location.href = '<?= base_url("inventario/transferencias") ?>';
                    }
                });
            } else {
                Swal.fire('Error', response.message || 'Error al guardar la transferencia', 'error');
                btnSubmit.prop('disabled', false).html(originalText);
            }
        },
        error: function(xhr) {
            let errorMsg = 'Error al procesar la solicitud';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMsg = xhr.responseJSON.message;
            }
            Swal.fire('Error', errorMsg, 'error');
            btnSubmit.prop('disabled', false).html(originalText);
        }
    });
}
</script>
<?= $this->endSection() ?>
