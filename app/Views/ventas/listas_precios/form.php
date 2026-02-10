<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('ventas') ?>">Ventas</a></li>
<li class="breadcrumb-item"><a href="<?= base_url('ventas/listas-precios') ?>">Listas de Precios</a></li>
<li class="breadcrumb-item active"><?= $lista ? 'Editar' : 'Nueva' ?></li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<form id="formListaPrecio" method="POST" action="<?= base_url('ventas/listas-precios/' . ($lista ? 'actualizar/' . $lista['id'] : 'guardar')) ?>">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-tags mr-1"></i>
                        <?= $lista ? 'Editar Lista de Precios' : 'Nueva Lista de Precios' ?>
                    </h3>
                </div>
                <div class="card-body">
                    <?= csrf_field() ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Nombre de la Lista <span class="text-danger">*</span></label>
                                <input type="text" name="nombre" class="form-control" 
                                       value="<?= $lista ? esc($lista['nombre']) : '' ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Tipo de Lista <span class="text-danger">*</span></label>
                                <select name="tipo_lista" class="form-control" required>
                                    <option value="GENERAL" <?= $lista && $lista['tipo_lista'] == 'GENERAL' ? 'selected' : '' ?>>General</option>
                                    <option value="MAYORISTA" <?= $lista && $lista['tipo_lista'] == 'MAYORISTA' ? 'selected' : '' ?>>Mayorista</option>
                                    <option value="MINORISTA" <?= $lista && $lista['tipo_lista'] == 'MINORISTA' ? 'selected' : '' ?>>Minorista</option>
                                    <option value="ESPECIAL" <?= $lista && $lista['tipo_lista'] == 'ESPECIAL' ? 'selected' : '' ?>>Especial</option>
                                    <option value="PROMOCIONAL" <?= $lista && $lista['tipo_lista'] == 'PROMOCIONAL' ? 'selected' : '' ?>>Promocional</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Tipo de Descuento <span class="text-danger">*</span></label>
                                <select name="tipo_descuento" class="form-control" required>
                                    <option value="PORCENTAJE" <?= $lista && $lista['tipo_descuento'] == 'PORCENTAJE' ? 'selected' : '' ?>>Por Porcentaje</option>
                                    <option value="MONTO_FIJO" <?= $lista && $lista['tipo_descuento'] == 'MONTO_FIJO' ? 'selected' : '' ?>>Monto Fijo</option>
                                    <option value="PRECIO_ESPECIAL" <?= $lista && $lista['tipo_descuento'] == 'PRECIO_ESPECIAL' ? 'selected' : '' ?>>Precio Especial</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Descuento General (%)</label>
                                <input type="number" name="descuento_general" class="form-control" 
                                       value="<?= $lista ? esc($lista['descuento_general']) : '0' ?>" 
                                       step="0.01" min="0" max="100">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Fecha Vigencia Desde</label>
                                <input type="date" name="fecha_vigencia_desde" class="form-control" 
                                       value="<?= $lista && $lista['fecha_vigencia_desde'] ? date('Y-m-d', strtotime($lista['fecha_vigencia_desde'])) : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Fecha Vigencia Hasta</label>
                                <input type="date" name="fecha_vigencia_hasta" class="form-control" 
                                       value="<?= $lista && $lista['fecha_vigencia_hasta'] ? date('Y-m-d', strtotime($lista['fecha_vigencia_hasta'])) : '' ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Descripción</label>
                        <textarea name="descripcion" class="form-control" rows="3"><?= $lista ? esc($lista['descripcion']) : '' ?></textarea>
                    </div>

                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" name="es_predeterminada" class="form-check-input" 
                                   id="es_predeterminada" <?= $lista && $lista['es_predeterminada'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="es_predeterminada">
                                Marcar como lista predeterminada
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Productos -->
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-boxes mr-1"></i> Productos de la Lista</h3>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <input type="text" id="buscarProducto" class="form-control" 
                                   placeholder="Buscar producto por código o nombre...">
                        </div>
                        <div class="col-md-6">
                            <button type="button" class="btn btn-primary" onclick="agregarProducto()">
                                <i class="fas fa-plus"></i> Agregar Producto
                            </button>
                        </div>
                    </div>

                    <table class="table table-bordered" id="tablaProductos">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Producto</th>
                                <th>Precio Base</th>
                                <th>Tipo</th>
                                <th>Valor</th>
                                <th>Precio Final</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="productosLista">
                            <?php if ($lista && isset($detalles)): ?>
                                <?php foreach ($detalles as $detalle): ?>
                                        <?php
                                    $productoModel = new \App\Models\ProductoModel();
                                    $producto = $productoModel->find($detalle['producto_id']);
                                    if (!$producto) continue;
                                    ?>
                                    <tr data-producto-id="<?= $detalle['producto_id'] ?>" data-precio-base="<?= $producto['precio_venta'] ?>">
                                        <td><?= esc($producto['codigo']) ?></td>
                                        <td><?= esc($producto['nombre']) ?></td>
                                        <td><?= formatGsNumber($producto['precio_venta']) ?></td>
                                        <td>
                                            <select class="form-control tipo-aplicacion">
                                                <option value="PORCENTAJE" <?= $detalle['tipo_aplicacion'] == 'PORCENTAJE' ? 'selected' : '' ?>>%</option>
                                                <option value="MONTO_FIJO" <?= $detalle['tipo_aplicacion'] == 'MONTO_FIJO' ? 'selected' : '' ?>>Monto</option>
                                                <option value="PRECIO_ESPECIAL" <?= $detalle['tipo_aplicacion'] == 'PRECIO_ESPECIAL' ? 'selected' : '' ?>>Precio</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" class="form-control valor-descuento" 
                                                   value="<?= $detalle['tipo_aplicacion'] == 'PORCENTAJE' ? $detalle['descuento_porcentaje'] : ($detalle['tipo_aplicacion'] == 'MONTO_FIJO' ? $detalle['descuento_monto'] : $detalle['precio_especial']) ?>"
                                                   step="0.01" min="0">
                                        </td>
                                        <td class="precio-final"><?= formatGsNumber($detalle['precio_final']) ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-danger" onclick="eliminarProducto(this)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Configuración</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Moneda</label>
                        <select name="moneda" class="form-control">
                            <option value="PYG" <?= $lista && $lista['moneda'] == 'PYG' ? 'selected' : '' ?>>Guaraníes (PYG)</option>
                            <option value="USD" <?= $lista && $lista['moneda'] == 'USD' ? 'selected' : '' ?>>Dólares (USD)</option>
                            <option value="BRL" <?= $lista && $lista['moneda'] == 'BRL' ? 'selected' : '' ?>>Reales (BRL)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Cantidad Mínima</label>
                        <input type="number" name="cantidad_minima" class="form-control" 
                               value="<?= $lista ? esc($lista['cantidad_minima']) : '1' ?>" min="1">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-12">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Guardar
            </button>
            <a href="<?= base_url('ventas/listas-precios') ?>" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancelar
            </a>
        </div>
    </div>
</form>

<!-- Modal para agregar producto -->
<div class="modal fade" id="modalProducto" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Agregar Producto</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Buscar Producto</label>
                    <input type="text" id="buscarProductoModal" class="form-control" 
                           placeholder="Escriba código o nombre...">
                </div>
                <div id="resultadosProductos" style="max-height: 400px; overflow-y: auto;">
                    <!-- Resultados aquí -->
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
let productos = <?= json_encode($productos ?? []) ?>;
let productosAgregados = [];

// Validar que productos sea un array
if (!Array.isArray(productos)) {
    console.error('Error: productos no es un array');
    productos = [];
}

$(document).ready(function() {
    // Inicializar productos agregados desde la tabla
    $('#productosLista tr').each(function() {
        const productoId = parseInt($(this).data('producto-id'));
        if (productoId && !productosAgregados.includes(productoId)) {
            productosAgregados.push(productoId);
        }
    });

    // Buscar producto en modal
    $('#buscarProductoModal').on('keyup', function() {
        const termino = $(this).val().toLowerCase().trim();
        if (termino.length < 1) {
            $('#resultadosProductos').html('<p class="text-muted text-center p-3">Escriba al menos un carácter para buscar</p>');
            return;
        }

        const resultados = productos.filter(p => {
            const id = parseInt(p.id);
            const codigo = (p.codigo || '').toLowerCase();
            const nombre = (p.nombre || '').toLowerCase();
            
            return !productosAgregados.includes(id) &&
                   (codigo.includes(termino) || nombre.includes(termino));
        }).slice(0, 20);

        if (resultados.length === 0) {
            $('#resultadosProductos').html('<p class="text-muted text-center p-3">No se encontraron productos</p>');
            return;
        }

        let html = '<table class="table table-sm table-hover">';
        html += '<thead><tr><th>Código</th><th>Producto</th><th>Precio</th></tr></thead><tbody>';
        resultados.forEach(producto => {
            const precio = parseFloat(producto.precio_venta) || 0;
            html += `
                <tr style="cursor: pointer;" onclick="seleccionarProducto(${producto.id})" 
                    onmouseover="this.style.backgroundColor='#f0f0f0'" 
                    onmouseout="this.style.backgroundColor=''">
                    <td><strong>${producto.codigo || ''}</strong></td>
                    <td>${producto.nombre || ''}</td>
                    <td>${formatNumber(precio)} Gs.</td>
                </tr>
            `;
        });
        html += '</tbody></table>';
        $('#resultadosProductos').html(html);
    });

    // Calcular precio final al cambiar tipo o valor
    $(document).on('change', '.tipo-aplicacion', function() {
        const row = $(this).closest('tr');
        calcularPrecioFinal(row);
    });
    
    $(document).on('input change', '.valor-descuento', function() {
        const row = $(this).closest('tr');
        calcularPrecioFinal(row);
    });
    
    // Mostrar todos los productos disponibles cuando se abre el modal
    $('#modalProducto').on('show.bs.modal', function() {
        const termino = $('#buscarProductoModal').val().toLowerCase().trim();
        if (termino.length < 1) {
            // Mostrar todos los productos disponibles
            const disponibles = productos.filter(p => !productosAgregados.includes(parseInt(p.id))).slice(0, 50);
            if (disponibles.length > 0) {
                let html = '<p class="text-muted mb-2"><small>Productos disponibles (' + disponibles.length + '):</small></p>';
                html += '<table class="table table-sm table-hover">';
                html += '<thead><tr><th>Código</th><th>Producto</th><th>Precio</th></tr></thead><tbody>';
                disponibles.forEach(producto => {
                    const precio = parseFloat(producto.precio_venta) || 0;
                    html += `
                        <tr style="cursor: pointer;" onclick="seleccionarProducto(${producto.id})" 
                            onmouseover="this.style.backgroundColor='#f0f0f0'" 
                            onmouseout="this.style.backgroundColor=''">
                            <td><strong>${producto.codigo || ''}</strong></td>
                            <td>${producto.nombre || ''}</td>
                            <td>${formatNumber(precio)} Gs.</td>
                        </tr>
                    `;
                });
                html += '</tbody></table>';
                $('#resultadosProductos').html(html);
            } else {
                $('#resultadosProductos').html('<p class="text-muted text-center p-3">No hay productos disponibles para agregar</p>');
            }
        }
    });
});

function agregarProducto() {
    $('#modalProducto').modal('show');
    // El evento show.bs.modal se encargará de mostrar los productos disponibles
    setTimeout(function() {
        $('#buscarProductoModal').focus();
    }, 500);
}

function seleccionarProducto(productoId) {
    // Convertir a número para comparación consistente
    productoId = parseInt(productoId);
    
    const producto = productos.find(p => parseInt(p.id) === productoId);
    if (!producto) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Producto no encontrado'
        });
        return;
    }

    // Verificar si ya está agregado
    if (productosAgregados.includes(productoId)) {
        Swal.fire({
            icon: 'warning',
            title: 'Aviso',
            text: 'Este producto ya está en la lista',
            timer: 2000,
            showConfirmButton: false
        });
        return;
    }

    productosAgregados.push(productoId);

    const precioBase = parseFloat(producto.precio_venta) || 0;
    const row = `
        <tr data-producto-id="${producto.id}" data-precio-base="${precioBase}">
            <td>${producto.codigo || ''}</td>
            <td>${producto.nombre || ''}</td>
            <td>${formatNumber(precioBase)}</td>
            <td>
                <select class="form-control tipo-aplicacion">
                    <option value="PORCENTAJE">%</option>
                    <option value="MONTO_FIJO">Monto</option>
                    <option value="PRECIO_ESPECIAL">Precio</option>
                </select>
            </td>
            <td>
                <input type="number" class="form-control valor-descuento" 
                       value="0" step="0.01" min="0">
            </td>
            <td class="precio-final">${formatNumber(precioBase)}</td>
            <td>
                <button type="button" class="btn btn-sm btn-danger" onclick="eliminarProducto(this)">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `;

    $('#productosLista').append(row);
    $('#modalProducto').modal('hide');
    $('#buscarProductoModal').val('');
    $('#resultadosProductos').html('');
}

function eliminarProducto(btn) {
    const row = $(btn).closest('tr');
    const productoId = row.data('producto-id');
    productosAgregados = productosAgregados.filter(id => id != productoId);
    row.remove();
}

function calcularPrecioFinal(row) {
    const productoId = parseInt(row.data('producto-id'));
    const precioBase = parseFloat(row.data('precio-base')) || 0;
    
    // Intentar encontrar en el array, pero si no está, usar el precio base del data attribute
    const producto = productos.find(p => parseInt(p.id) === productoId);
    const precioBaseFinal = producto ? parseFloat(producto.precio_venta) || precioBase : precioBase;

    const tipoAplicacion = row.find('.tipo-aplicacion').val();
    const valor = parseFloat(row.find('.valor-descuento').val()) || 0;
    let precioFinal = precioBaseFinal;

    if (tipoAplicacion == 'PORCENTAJE') {
        precioFinal = precioBaseFinal * (1 - (valor / 100));
    } else if (tipoAplicacion == 'MONTO_FIJO') {
        precioFinal = Math.max(0, precioBaseFinal - valor);
    } else if (tipoAplicacion == 'PRECIO_ESPECIAL') {
        precioFinal = valor || precioBaseFinal;
    }

    row.find('.precio-final').text(formatNumber(precioFinal));
}

function formatNumber(num) {
    if (isNaN(num) || num === null || num === undefined) {
        return '0';
    }
    return new Intl.NumberFormat('es-PY', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(parseFloat(num));
}

$('#formListaPrecio').on('submit', function(e) {
    e.preventDefault();
    
    const productosData = [];
    $('#productosLista tr').each(function() {
        const productoId = $(this).data('producto-id');
        if (!productoId) return;
        
        const tipoAplicacion = $(this).find('.tipo-aplicacion').val();
        const valor = parseFloat($(this).find('.valor-descuento').val()) || 0;
        const precioBase = parseFloat($(this).data('precio-base')) || 0;
        
        const productoData = {
            id: parseInt(productoId),
            tipo_aplicacion: tipoAplicacion
        };

        if (tipoAplicacion == 'PORCENTAJE') {
            productoData.descuento_porcentaje = valor;
        } else if (tipoAplicacion == 'MONTO_FIJO') {
            productoData.descuento_monto = valor;
        } else if (tipoAplicacion == 'PRECIO_ESPECIAL') {
            productoData.precio_especial = valor || precioBase;
        }

        productosData.push(productoData);
    });

    const formData = new FormData(this);
    formData.append('productos', JSON.stringify(productosData));

    $.ajax({
        url: $(this).attr('action'),
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Éxito',
                    text: response.message || 'Lista de precios guardada correctamente',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = response.data.redirect || '<?= base_url('ventas/listas-precios') ?>';
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.message || 'Error al guardar la lista de precios'
                });
            }
        },
        error: function(xhr) {
            let message = 'Error al procesar la solicitud';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: message
            });
        }
    });
});
</script>
<?= $this->endSection() ?>
