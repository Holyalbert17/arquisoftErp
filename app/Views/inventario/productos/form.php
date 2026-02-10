<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('inventario/productos') ?>">Productos</a></li>
<li class="breadcrumb-item active"><?= $producto ? 'Editar' : 'Nuevo' ?></li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-md-12">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-box mr-1"></i>
                    <?= $producto ? 'Editar Producto' : 'Nuevo Producto' ?>
                </h3>
            </div>
            <form id="productoForm" method="post" enctype="multipart/form-data">
                <div class="card-body">
                    <!-- Información Básica -->
                    <h5 class="mb-3"><i class="fas fa-info-circle"></i> Información Básica</h5>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="codigo">Código *</label>
                                <input type="text" class="form-control" id="codigo" name="codigo" 
                                       value="<?= $producto ? esc($producto['codigo']) : '' ?>" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="codigo_barra">Código de Barras</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="codigo_barra" name="codigo_barra" 
                                           value="<?= $producto ? esc($producto['codigo_barra']) : '' ?>">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-default" id="btnGenerarBarra" title="Generar">
                                            <i class="fas fa-magic"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="tipo_producto">Tipo de Producto *</label>
                                <select class="form-control" id="tipo_producto" name="tipo_producto" required>
                                    <option value="PRODUCTO" <?= (!$producto || $producto['tipo_producto'] == 'PRODUCTO') ? 'selected' : '' ?>>Producto</option>
                                    <option value="SERVICIO" <?= $producto && $producto['tipo_producto'] == 'SERVICIO' ? 'selected' : '' ?>>Servicio</option>
                                    <option value="COMBO" <?= $producto && $producto['tipo_producto'] == 'COMBO' ? 'selected' : '' ?>>Combo</option>
                                    <option value="REPUESTO" <?= $producto && $producto['tipo_producto'] == 'REPUESTO' ? 'selected' : '' ?>>Repuesto</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="unidad_medida_id">Unidad de Medida</label>
                                <select class="form-control" id="unidad_medida_id" name="unidad_medida_id">
                                    <?php foreach ($unidades as $unidad): ?>
                                        <option value="<?= $unidad['id'] ?>" 
                                                <?= ($producto && $producto['unidad_medida_id'] == $unidad['id']) || (!$producto && $unidad['id'] == 1) ? 'selected' : '' ?>>
                                            <?= esc($unidad['nombre']) ?> (<?= esc($unidad['abreviatura']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="nombre">Nombre del Producto *</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" 
                                       value="<?= $producto ? esc($producto['nombre']) : '' ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="categoria_id">Categoría</label>
                                <select class="form-control select2" id="categoria_id" name="categoria_id">
                                    <option value="">Seleccionar...</option>
                                    <?php foreach ($categorias as $categoria): ?>
                                        <option value="<?= $categoria['id'] ?>" 
                                                <?= $producto && $producto['categoria_id'] == $categoria['id'] ? 'selected' : '' ?>>
                                            <?= esc($categoria['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="marca_id">Marca</label>
                                <select class="form-control select2" id="marca_id" name="marca_id">
                                    <option value="">Seleccionar...</option>
                                    <?php foreach ($marcas as $marca): ?>
                                        <option value="<?= $marca['id'] ?>" 
                                                <?= $producto && $producto['marca_id'] == $marca['id'] ? 'selected' : '' ?>>
                                            <?= esc($marca['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="descripcion">Descripción</label>
                                <textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?= $producto ? esc($producto['descripcion']) : '' ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Información Fiscal -->
                    <h5 class="mb-3 mt-4"><i class="fas fa-receipt"></i> Información Fiscal</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="tipo_iva">Tipo de IVA *</label>
                                <select class="form-control" id="tipo_iva" name="tipo_iva" required>
                                    <option value="GRAVADO_10" <?= (!$producto || $producto['tipo_iva'] == 'GRAVADO_10') ? 'selected' : '' ?>>Gravado 10%</option>
                                    <option value="GRAVADO_5" <?= $producto && $producto['tipo_iva'] == 'GRAVADO_5' ? 'selected' : '' ?>>Gravado 5%</option>
                                    <option value="EXENTO" <?= $producto && $producto['tipo_iva'] == 'EXENTO' ? 'selected' : '' ?>>Exento</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="moneda">Moneda *</label>
                                <select class="form-control" id="moneda" name="moneda" required>
                                    <option value="PYG" <?= (!$producto || $producto['moneda'] == 'PYG') ? 'selected' : '' ?>>Guaraníes (PYG)</option>
                                    <option value="USD" <?= $producto && $producto['moneda'] == 'USD' ? 'selected' : '' ?>>Dólares (USD)</option>
                                    <option value="BRL" <?= $producto && $producto['moneda'] == 'BRL' ? 'selected' : '' ?>>Reales (BRL)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Precios -->
                    <h5 class="mb-3 mt-4"><i class="fas fa-dollar-sign"></i> Precios</h5>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="precio_compra">Precio de Compra</label>
                                <input type="text" class="form-control precio-input" id="precio_compra" name="precio_compra" 
                                       value="<?= $producto ? $producto['precio_compra'] : '0' ?>" placeholder="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="precio_venta">Precio de Venta *</label>
                                <input type="text" class="form-control precio-input" id="precio_venta" name="precio_venta" 
                                       value="<?= $producto ? $producto['precio_venta'] : '0' ?>" required placeholder="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="precio_mayorista">Precio Mayorista</label>
                                <input type="text" class="form-control precio-input" id="precio_mayorista" name="precio_mayorista" 
                                       value="<?= $producto ? $producto['precio_mayorista'] : '0' ?>" placeholder="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="precio_minimo">Precio Mínimo</label>
                                <input type="text" class="form-control precio-input" id="precio_minimo" name="precio_minimo" 
                                       value="<?= $producto ? $producto['precio_minimo'] : '0' ?>" placeholder="0">
                            </div>
                        </div>
                    </div>

                    <!-- Control de Stock -->
                    <h5 class="mb-3 mt-4"><i class="fas fa-warehouse"></i> Control de Stock</h5>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="controla_stock" name="controla_stock" 
                                           <?= (!$producto || $producto['controla_stock']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="controla_stock">
                                        Controla Stock
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="permite_venta_sin_stock" name="permite_venta_sin_stock" 
                                           <?= $producto && $producto['permite_venta_sin_stock'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="permite_venta_sin_stock">
                                        Permitir Venta sin Stock
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="usa_lote" name="usa_lote" 
                                           <?= $producto && $producto['usa_lote'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="usa_lote">
                                        Usa Lote
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="usa_vencimiento" name="usa_vencimiento" 
                                           <?= $producto && $producto['usa_vencimiento'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="usa_vencimiento">
                                        Usa Vencimiento
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="stock_minimo">Stock Mínimo</label>
                                <input type="number" class="form-control" id="stock_minimo" name="stock_minimo" 
                                       value="<?= $producto ? $producto['stock_minimo'] : '0' ?>" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="stock_maximo">Stock Máximo</label>
                                <input type="number" class="form-control" id="stock_maximo" name="stock_maximo" 
                                       value="<?= $producto ? $producto['stock_maximo'] : '0' ?>" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="punto_reorden">Punto de Reorden</label>
                                <input type="number" class="form-control" id="punto_reorden" name="punto_reorden" 
                                       value="<?= $producto ? $producto['punto_reorden'] : '0' ?>" min="0">
                            </div>
                        </div>
                    </div>

                    <?php if (!$producto): ?>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="stock_inicial">Stock Inicial</label>
                                <input type="number" class="form-control" id="stock_inicial" name="stock_inicial" 
                                       value="0" min="0">
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Imagen -->
                    <h5 class="mb-3 mt-4"><i class="fas fa-image"></i> Imagen</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="imagen_principal">Imagen Principal</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="imagen_principal" name="imagen_principal" accept="image/*">
                                    <label class="custom-file-label" for="imagen_principal">Seleccionar archivo</label>
                                </div>
                                <?php if ($producto && $producto['imagen_principal']): ?>
                                    <small class="form-text text-muted">
                                        Imagen actual: <a href="<?= base_url('uploads/productos/' . $producto['imagen_principal']) ?>" target="_blank">Ver</a>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Guardar
                    </button>
                    <a href="<?= base_url('inventario/productos') ?>" class="btn btn-default">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(document).ready(function() {
    $('.select2').select2({
        theme: 'bootstrap-5'
    });

    // Formato de archivo
    $('.custom-file-input').on('change', function() {
        let fileName = $(this).val().split('\\').pop();
        $(this).siblings('.custom-file-label').addClass('selected').html(fileName);
    });

    // Formato de Moneda (Guaraníes)
    function formatCurrency(input) {
        // Remover todo lo que no sea dígito
        let value = input.value.replace(/\D/g, '');
        
        if (value === '') {
            input.value = '';
            return;
        }
        
        // Formatear con separadores de miles
        input.value = parseInt(value).toLocaleString('es-PY');
    }

    // Aplicar formato al escribir
    $('.precio-input').on('input', function() {
        formatCurrency(this);
    });
    
    // Aplicar formato inicial
    $('.precio-input').each(function() {
        formatCurrency(this);
    });

    // Calcular margen automáticamente (adaptado para inputs con formato)
    $('#precio_compra, #precio_venta').on('input blur', function() {
        // Obtener valores sin puntos
        const compraStr = $('#precio_compra').val().replace(/\./g, '');
        const ventaStr = $('#precio_venta').val().replace(/\./g, '');
        
        const compra = parseFloat(compraStr) || 0;
        const venta = parseFloat(ventaStr) || 0;
        
        // Aquí podrías actualizar un campo visual de margen si existiera
        if (compra > 0 && venta > 0) {
            const margen = ((venta - compra) / compra) * 100;
            console.log('Margen calculado:', margen.toFixed(2) + '%');
        }
    });

    // Generar Código de Barras
    $('#btnGenerarBarra').on('click', function() {
        // Generar un número aleatorio de 12 dígitos + checksum (simplificado: solo 13 digitos random)
        // Formato EAN-13 interno: 2 + 11 digitos random + checksum (opcional)
        // Usaremos simple 13 digitos random empezando con 2
        let random = '2' + Math.floor(Math.random() * 1000000000000).toString().padStart(12, '0');
        $('#codigo_barra').val(random);
    });

    // Mejorar experiencia con Lector de Barras en el formulario
    // Evitar que el 'Enter' del lector envíe el formulario inmediatamente
    $('#codigo_barra').on('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault(); // Prevenir submit
            // Mover foco al siguiente campo (Nombre)
            $('#nombre').focus();
        }
    });

    // Enviar formulario
    $('#productoForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const url = '<?= $producto ? base_url("inventario/productos/editar/" . $producto["id"]) : base_url("inventario/productos/crear") ?>';
        
        $.ajax({
            url: url,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Éxito',
                        text: response.message,
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        window.location.href = response.data.redirect;
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message
                    });
                }
            },
            error: function(xhr, status, error) {
                let message = 'Error al procesar la solicitud';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                } else if (xhr.responseText) {
                    // Try to parse if it's a JSON string but not automatically parsed
                    try {
                        let resp = JSON.parse(xhr.responseText);
                        if (resp.message) message = resp.message;
                    } catch(e) {
                         // If not JSON, it might be a PHP fatal error or HTML dump. 
                         // Check if it contains the specific duplicate key error text just in case
                         if (xhr.responseText.includes('llave duplicada')) {
                             message = 'El código del producto ya existe. Por favor utilice otro.';
                         } else {
                             console.error('Error response:', xhr.responseText);
                         }
                    }
                }
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error (' + xhr.status + ')',
                    text: message
                });
            }
        });
    });
});
</script>
<?= $this->endSection() ?>
