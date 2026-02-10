<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('inventario') ?>">Inventario</a></li>
<li class="breadcrumb-item"><a href="<?= base_url('inventario/ajustes') ?>">Ajustes</a></li>
<li class="breadcrumb-item active">Nuevo Ajuste</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <form id="formAjuste">
            <!-- Información del Ajuste -->
            <div class="card card-outline card-warning">
                <div class="card-header bg-warning">
                    <h3 class="card-title text-white">
                        <i class="fas fa-edit mr-2"></i> Nuevo Ajuste de Inventario
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="tipoAjuste">
                                    <i class="fas fa-tags mr-1"></i> Tipo de Ajuste <span class="text-danger">*</span>
                                </label>
                                <select class="form-control" id="tipoAjuste" name="tipo_ajuste" required>
                                    <option value="">Seleccione un tipo...</option>
                                    <option value="ENTRADA">Entrada</option>
                                    <option value="SALIDA">Salida</option>
                                    <option value="FISICO">Físico (Conteo)</option>
                                    <option value="MERMA">Merma</option>
                                </select>
                                <small class="form-text text-muted">Seleccione el tipo de ajuste a realizar</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="motivo">
                                    <i class="fas fa-comment-alt mr-1"></i> Motivo <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="motivo" name="motivo" 
                                       placeholder="Ej: Conteo físico, Pérdida, etc." required>
                                <small class="form-text text-muted">Describa el motivo del ajuste</small>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="form-group">
                                <label for="observaciones">
                                    <i class="fas fa-sticky-note mr-1"></i> Observaciones
                                </label>
                                <textarea class="form-control" id="observaciones" name="observaciones" rows="3" 
                                          placeholder="Notas adicionales sobre el ajuste..."></textarea>
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
                        <i class="fas fa-boxes mr-2"></i> Productos a Ajustar
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
                                    <th style="width: 15%;">Código</th>
                                    <th style="width: 15%;" class="text-center">Stock Actual</th>
                                    <th style="width: 15%;" class="text-center">Stock Nuevo <span class="text-danger">*</span></th>
                                    <th style="width: 15%;" class="text-center">Diferencia</th>
                                    <th style="width: 10%;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="productosBody">
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="fas fa-box-open fa-2x mb-2 d-block"></i>
                                        No hay productos agregados. Busque y agregue productos para ajustar.
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
                            <a href="<?= base_url('inventario/ajustes') ?>" class="btn btn-secondary">
                                <i class="fas fa-times mr-1"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-warning btn-lg">
                                <i class="fas fa-save mr-1"></i> Guardar Ajuste
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
    $('#formAjuste').on('submit', function(e) {
        e.preventDefault();
        guardarAjuste();
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
    html += '<thead><tr><th>Producto</th><th>Código</th><th>Stock</th><th>Acción</th></tr></thead><tbody>';
    
    productos.forEach(function(producto) {
        const stock = parseFloat(producto.stock_disponible || 0);
        const stockClass = stock > 0 ? 'text-success' : 'text-danger';
        const yaAgregado = productosAgregados.find(p => p.id == producto.id);
        
        html += `
            <tr class="${yaAgregado ? 'table-secondary' : ''}">
                <td><strong>${producto.nombre}</strong></td>
                <td>${producto.codigo || '-'}</td>
                <td><span class="${stockClass}">${producto.controla_stock !== false ? stock.toFixed(2) : 'Sin control'}</span></td>
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

$(document).on('click', '.btn-agregar-producto', function() {
    const producto = $(this).data('producto');
    agregarProducto(producto);
    $('#modalBuscarProducto').modal('hide');
    $('#buscarProducto').val('').focus();
});

function agregarProducto(producto) {
    const stock = parseFloat(producto.stock_disponible || 0);
    
    productosAgregados.push({
        id: producto.id,
        nombre: producto.nombre,
        codigo: producto.codigo || '',
        cantidad_existente: stock,
        cantidad_nueva: stock,
        diferencia: 0,
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
            const diferencia = producto.cantidad_nueva - producto.cantidad_existente;
            const diferenciaClass = diferencia > 0 ? 'text-success' : (diferencia < 0 ? 'text-danger' : 'text-muted');
            const diferenciaIcon = diferencia > 0 ? 'fa-arrow-up' : (diferencia < 0 ? 'fa-arrow-down' : 'fa-equals');
            
            html += `
                <tr>
                    <td>${index + 1}</td>
                    <td><strong>${producto.nombre}</strong></td>
                    <td>${producto.codigo}</td>
                    <td class="text-center">
                        <span class="badge badge-secondary">${producto.cantidad_existente.toFixed(2)}</span>
                    </td>
                    <td class="text-center">
                        <input type="number" class="form-control form-control-sm cantidad-nueva text-center" 
                               data-index="${index}" value="${producto.cantidad_nueva}" 
                               min="0" step="0.01" style="width: 120px;" required>
                    </td>
                    <td class="text-center">
                        <span class="${diferenciaClass}">
                            <i class="fas ${diferenciaIcon}"></i> 
                            ${diferencia > 0 ? '+' : ''}${diferencia.toFixed(2)}
                        </span>
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

$(document).on('change', '.cantidad-nueva', function() {
    const index = $(this).data('index');
    const cantidadNueva = parseFloat($(this).val()) || 0;
    
    productosAgregados[index].cantidad_nueva = cantidadNueva;
    productosAgregados[index].diferencia = cantidadNueva - productosAgregados[index].cantidad_existente;
    
    actualizarTablaProductos();
});

$(document).on('click', '.btn-eliminar-producto', function() {
    const index = $(this).data('index');
    productosAgregados.splice(index, 1);
    actualizarTablaProductos();
});

function guardarAjuste() {
    if (productosAgregados.length === 0) {
        Swal.fire('Advertencia', 'Debe agregar al menos un producto', 'warning');
        return;
    }

    const tipoAjuste = $('#tipoAjuste').val();
    const motivo = $('#motivo').val().trim();

    if (!tipoAjuste) {
        Swal.fire('Advertencia', 'Debe seleccionar un tipo de ajuste', 'warning');
        return;
    }

    if (!motivo || motivo.length < 5) {
        Swal.fire('Advertencia', 'El motivo debe tener al menos 5 caracteres', 'warning');
        return;
    }

    const btnSubmit = $('#formAjuste button[type="submit"]');
    const originalText = btnSubmit.html();
    btnSubmit.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...');

    const data = {
        tipo_ajuste: tipoAjuste,
        motivo: motivo,
        productos: JSON.stringify(productosAgregados),
        observaciones: $('#observaciones').val()
    };

    $.ajax({
        url: '<?= base_url("inventario/ajustes/crear") ?>',
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
                        window.location.href = '<?= base_url("inventario/ajustes") ?>';
                    }
                });
            } else {
                Swal.fire('Error', response.message || 'Error al guardar el ajuste', 'error');
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
