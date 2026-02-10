<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('compras') ?>">Compras</a></li>
<li class="breadcrumb-item active"><?= $compra ? 'Editar Compra' : 'Nueva Compra' ?></li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <form id="formCompra">
            <!-- Información de la Compra -->
            <div class="card card-outline card-primary">
                <div class="card-header bg-primary">
                    <h3 class="card-title text-white">
                        <i class="fas fa-shopping-cart mr-2"></i> <?= $compra ? 'Editar Compra' : 'Nueva Compra' ?>
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="proveedor">
                                    <i class="fas fa-truck mr-1"></i> Proveedor <span class="text-danger">*</span>
                                </label>
                                <select class="form-control select2" id="proveedor" name="proveedor_id" required>
                                    <option value="">Seleccione un proveedor...</option>
                                    <?php foreach ($proveedores as $proveedor): ?>
                                        <option value="<?= $proveedor['id'] ?>" 
                                                <?= ($compra && $compra['proveedor_id'] == $proveedor['id']) ? 'selected' : '' ?>>
                                            <?= esc($proveedor['razon_social']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="tipoCompra">
                                    <i class="fas fa-tags mr-1"></i> Tipo de Compra <span class="text-danger">*</span>
                                </label>
                                <select class="form-control" id="tipoCompra" name="tipo_compra" required>
                                    <option value="LOCAL" <?= ($compra && $compra['tipo_compra'] == 'LOCAL') ? 'selected' : '' ?>>Local</option>
                                    <option value="IMPORTACION" <?= ($compra && $compra['tipo_compra'] == 'IMPORTACION') ? 'selected' : '' ?>>Importación</option>
                                    <option value="CONSIGNACION" <?= ($compra && $compra['tipo_compra'] == 'CONSIGNACION') ? 'selected' : '' ?>>Consignación</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="condicionPago">
                                    <i class="fas fa-money-bill-wave mr-1"></i> Condición de Pago <span class="text-danger">*</span>
                                </label>
                                <select class="form-control" id="condicionPago" name="condicion_pago" required>
                                    <option value="CONTADO" <?= ($compra && $compra['condicion_pago'] == 'CONTADO') ? 'selected' : '' ?>>Contado</option>
                                    <option value="CREDITO" <?= ($compra && $compra['condicion_pago'] == 'CREDITO') ? 'selected' : '' ?>>Crédito</option>
                                    <option value="MIXTO" <?= ($compra && $compra['condicion_pago'] == 'MIXTO') ? 'selected' : '' ?>>Mixto</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="numeroFactura">
                                    <i class="fas fa-file-invoice mr-1"></i> N° Factura Proveedor
                                </label>
                                <input type="text" class="form-control" id="numeroFactura" name="numero_factura_proveedor" 
                                       value="<?= $compra ? esc($compra['numero_factura_proveedor']) : '' ?>" 
                                       placeholder="Número de factura">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="timbrado">
                                    <i class="fas fa-stamp mr-1"></i> Timbrado
                                </label>
                                <input type="text" class="form-control" id="timbrado" name="timbrado_proveedor" 
                                       value="<?= $compra ? esc($compra['timbrado_proveedor']) : '' ?>" 
                                       placeholder="Número de timbrado">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="diasCredito">
                                    <i class="fas fa-calendar-alt mr-1"></i> Días de Crédito
                                </label>
                                <input type="number" class="form-control" id="diasCredito" name="dias_credito" 
                                       value="<?= $compra ? esc($compra['dias_credito']) : '0' ?>" 
                                       min="0" placeholder="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="otrosGastos">
                                    <i class="fas fa-dollar-sign mr-1"></i> Otros Gastos
                                </label>
                                <input type="number" class="form-control" id="otrosGastos" name="otros_gastos" 
                                       value="<?= $compra ? esc($compra['otros_gastos']) : '0' ?>" 
                                       min="0" step="0.01" placeholder="0.00">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="form-group">
                                <label for="observaciones">
                                    <i class="fas fa-sticky-note mr-1"></i> Observaciones
                                </label>
                                <textarea class="form-control" id="observaciones" name="observaciones" rows="2" 
                                          placeholder="Notas adicionales sobre la compra..."><?= $compra ? esc($compra['observaciones']) : '' ?></textarea>
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
                        <i class="fas fa-boxes mr-2"></i> Productos de la Compra
                        <span class="badge badge-light ml-2" id="contadorProductos">0</span>
                    </h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="tablaProductos">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 5%;">#</th>
                                    <th style="width: 25%;">Producto</th>
                                    <th style="width: 12%;">Código</th>
                                    <th style="width: 12%;" class="text-center">Cantidad <span class="text-danger">*</span></th>
                                    <th style="width: 12%;" class="text-right">Precio Unit. <span class="text-danger">*</span></th>
                                    <th style="width: 10%;" class="text-center">Desc. %</th>
                                    <th style="width: 12%;" class="text-right">Subtotal</th>
                                    <th style="width: 10%;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="productosBody">
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="fas fa-box-open fa-2x mb-2 d-block"></i>
                                        No hay productos agregados. Busque y agregue productos para la compra.
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="bg-light">
                                    <th colspan="6" class="text-right"><strong>TOTALES:</strong></th>
                                    <th class="text-right" id="totalCompra">0</th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Botones de Acción -->
            <div class="card mt-3">
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 text-right">
                            <a href="<?= base_url('compras') ?>" class="btn btn-secondary">
                                <i class="fas fa-times mr-1"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save mr-1"></i> Guardar Compra
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

$(document).ready(function() {
    $('.select2').select2({
        theme: 'bootstrap4',
        width: '100%'
    });

    // Buscar producto
    $('#btnBuscar, #buscarProducto').on('click keypress', function(e) {
        if (e.type === 'keypress' && e.which !== 13) return;
        e.preventDefault();
        
        const termino = $('#buscarProducto').val().trim();
        if (!termino) {
            Swal.fire('Advertencia', 'Ingrese un término de búsqueda', 'warning');
            return;
        }

        buscarProductos(termino);
    });

    // Enviar formulario
    $('#formCompra').on('submit', function(e) {
        e.preventDefault();
        guardarCompra();
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
    let html = '<div class="table-responsive"><table class="table table-hover">';
    html += '<thead><tr><th>Producto</th><th>Código</th><th>Precio Compra</th><th>Acción</th></tr></thead><tbody>';
    
    productos.forEach(function(producto) {
        const yaAgregado = productosAgregados.find(p => p.id == producto.id);
        
        html += `
            <tr class="${yaAgregado ? 'table-secondary' : ''}">
                <td><strong>${producto.nombre}</strong></td>
                <td>${producto.codigo || '-'}</td>
                <td>${formatGs(producto.precio_compra || 0)}</td>
                <td>
                    ${yaAgregado ? 
                        '<span class="badge badge-info">Ya agregado</span>' :
                        `<button class="btn btn-sm btn-success btn-agregar-producto" 
                                 data-producto='${JSON.stringify(producto)}'>
                            <i class="fas fa-plus"></i> Agregar
                         </button>`
                    }
                </td>
            </tr>
        `;
    });
    
    html += '</tbody></table></div>';
    $('#resultadosProductos').html(html);
}

// Función para formatear precios siempre en guaraníes
// NO redondea números enteros, solo elimina decimales si existen
function formatGs(amount) {
    if (amount === null || amount === undefined || amount === '' || isNaN(amount)) {
        return '0 Gs.';
    }
    
    // Manejar strings que pueden venir con formato
    let amountStr = String(amount).trim();
    
    // Remover cualquier formato existente (puntos, comas, espacios)
    amountStr = amountStr.replace(/[^\d.-]/g, '');
    
    // Convertir a número
    let amountNum = parseFloat(amountStr);
    
    if (isNaN(amountNum) || amountNum <= 0) {
        return '0 Gs.';
    }
    
    // Si tiene decimales, redondear SOLO a entero (guaraníes no tienen decimales)
    // Pero preservar el número completo si ya es entero
    if (amountNum % 1 !== 0) {
        amountNum = Math.round(amountNum);
    }
    
    // Convertir a string para formateo manual (evita problemas de precisión)
    // Usar Math.floor para asegurar que sea entero sin redondeo adicional
    let numStr = String(Math.floor(amountNum));
    
    // Formateo manual con puntos como separadores de miles (formato paraguayo)
    // Esto garantiza que no se pierdan dígitos
    numStr = numStr.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    
    return numStr + ' Gs.';
}

// Mantener formatCurrency para compatibilidad, pero ahora siempre en guaraníes
function formatCurrency(amount) {
    return formatGs(amount);
}

$(document).on('click', '.btn-agregar-producto', function() {
    const producto = $(this).data('producto');
    agregarProducto(producto);
    $('#modalBuscarProducto').modal('hide');
    $('#buscarProducto').val('').focus();
});

function agregarProducto(producto) {
    productosAgregados.push({
        id: producto.id,
        nombre: producto.nombre,
        codigo: producto.codigo || '',
        cantidad: 1,
        precio: parseFloat(producto.precio_compra || producto.precio_venta || 0),
        descuento: 0
    });
    
    actualizarTablaProductos();
}

function actualizarTablaProductos() {
    let html = '';
    let total = 0;
    
    if (productosAgregados.length === 0) {
        html = `
            <tr>
                <td colspan="8" class="text-center text-muted py-4">
                    <i class="fas fa-box-open fa-2x mb-2 d-block"></i>
                    No hay productos agregados
                </td>
            </tr>
        `;
    } else {
        productosAgregados.forEach(function(producto, index) {
            const subtotalLinea = producto.precio * producto.cantidad;
            const descuentoLinea = (subtotalLinea * producto.descuento) / 100;
            const subtotalConDescuento = subtotalLinea - descuentoLinea;
            total += subtotalConDescuento;
            
            html += `
                <tr>
                    <td>${index + 1}</td>
                    <td><strong>${producto.nombre}</strong></td>
                    <td>${producto.codigo}</td>
                    <td class="text-center">
                        <input type="number" class="form-control form-control-sm cantidad-producto text-center" 
                               data-index="${index}" value="${producto.cantidad}" 
                               min="0.01" step="0.01" style="width: 100px;" required>
                    </td>
                    <td>
                        <input type="number" class="form-control form-control-sm precio-producto text-right" 
                               data-index="${index}" value="${producto.precio}" 
                               min="0" step="0.01" style="width: 120px;" required>
                    </td>
                    <td class="text-center">
                        <input type="number" class="form-control form-control-sm descuento-producto text-center" 
                               data-index="${index}" value="${producto.descuento}" 
                               min="0" max="100" step="0.01" style="width: 80px;" placeholder="%">
                    </td>
                    <td class="text-right">
                        <strong class="text-primary">${formatCurrency(subtotalConDescuento)}</strong>
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
    $('#totalCompra').text(formatCurrency(total));
}

$(document).on('change', '.cantidad-producto, .precio-producto, .descuento-producto', function() {
    actualizarTablaProductos();
});

$(document).on('change', '.cantidad-producto', function() {
    const index = $(this).data('index');
    productosAgregados[index].cantidad = parseFloat($(this).val()) || 0;
    actualizarTablaProductos();
});

$(document).on('change', '.precio-producto', function() {
    const index = $(this).data('index');
    productosAgregados[index].precio = parseFloat($(this).val()) || 0;
    actualizarTablaProductos();
});

$(document).on('change', '.descuento-producto', function() {
    const index = $(this).data('index');
    const descuento = parseFloat($(this).val()) || 0;
    if (descuento > 100) {
        $(this).val(100);
        productosAgregados[index].descuento = 100;
    } else {
        productosAgregados[index].descuento = descuento;
    }
    actualizarTablaProductos();
});

$(document).on('click', '.btn-eliminar-producto', function() {
    const index = $(this).data('index');
    productosAgregados.splice(index, 1);
    actualizarTablaProductos();
});

function guardarCompra() {
    if (productosAgregados.length === 0) {
        Swal.fire('Advertencia', 'Debe agregar al menos un producto', 'warning');
        return;
    }

    const proveedor = $('#proveedor').val();
    if (!proveedor) {
        Swal.fire('Advertencia', 'Debe seleccionar un proveedor', 'warning');
        return;
    }

    const btnSubmit = $('#formCompra button[type="submit"]');
    const originalText = btnSubmit.html();
    btnSubmit.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...');

    const data = {
        proveedor_id: proveedor,
        tipo_compra: $('#tipoCompra').val(),
        condicion_pago: $('#condicionPago').val(),
        numero_factura_proveedor: $('#numeroFactura').val(),
        timbrado_proveedor: $('#timbrado').val(),
        dias_credito: $('#diasCredito').val() || 0,
        otros_gastos: $('#otrosGastos').val() || 0,
        productos: JSON.stringify(productosAgregados),
        observaciones: $('#observaciones').val()
    };

    $.ajax({
        url: '<?= base_url("compras/crear") ?>',
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
                        window.location.href = '<?= base_url("compras") ?>';
                    }
                });
            } else {
                Swal.fire('Error', response.message || 'Error al guardar la compra', 'error');
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
