<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('inventario') ?>">Inventario</a></li>
<li class="breadcrumb-item active">Stock</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
// Usar estadísticas del controlador si están disponibles
$estadisticas = $estadisticas ?? [
    'total_productos' => count($stock),
    'stock_critico' => 0,
    'stock_bajo' => 0,
    'stock_normal' => 0,
    'productos_sin_stock' => 0,
    'productos_sin_registro' => 0,
    'productos_proximos_vencer' => 0,
    'total_valorizado_costo' => 0,
    'total_valorizado_venta' => 0,
    'utilidad_potencial' => 0
];
?>

<!-- Cards de Estadísticas -->
<div class="row mb-4">
    <div class="col-lg-2 col-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3><?= number_format($estadisticas['total_productos'], 0, ',', '.') ?></h3>
                <p>Total Productos</p>
            </div>
            <div class="icon">
                <i class="fas fa-boxes"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-6">
        <div class="small-box bg-success">
            <div class="inner">
                <h3><?= number_format($estadisticas['stock_normal'], 0, ',', '.') ?></h3>
                <p>Stock Normal</p>
            </div>
            <div class="icon">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-6">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3><?= number_format($estadisticas['stock_bajo'], 0, ',', '.') ?></h3>
                <p>Stock Bajo</p>
            </div>
            <div class="icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-6">
        <div class="small-box bg-danger">
            <div class="inner">
                <h3><?= number_format($estadisticas['stock_critico'], 0, ',', '.') ?></h3>
                <p>Stock Crítico</p>
            </div>
            <div class="icon">
                <i class="fas fa-exclamation-circle"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-6">
        <div class="small-box bg-primary">
            <div class="inner">
                <h3><?= number_format($estadisticas['total_valorizado_costo'] / 1000000, 2, ',', '.') ?>M</h3>
                <p>Valorizado (Costo)</p>
            </div>
            <div class="icon">
                <i class="fas fa-dollar-sign"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-6">
        <div class="small-box bg-success">
            <div class="inner">
                <h3><?= number_format($estadisticas['utilidad_potencial'] / 1000000, 2, ',', '.') ?>M</h3>
                <p>Utilidad Potencial</p>
            </div>
            <div class="icon">
                <i class="fas fa-chart-line"></i>
            </div>
        </div>
    </div>
</div>

<?php if ($estadisticas['productos_proximos_vencer'] > 0 || $estadisticas['productos_sin_stock'] > 0): ?>
<div class="row mb-3">
    <div class="col-12">
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            <strong>Atención:</strong>
            <?php if ($estadisticas['productos_proximos_vencer'] > 0): ?>
                <span class="mr-3"><?= $estadisticas['productos_proximos_vencer'] ?> productos próximos a vencer</span>
            <?php endif; ?>
            <?php if ($estadisticas['productos_sin_stock'] > 0): ?>
                <span><?= $estadisticas['productos_sin_stock'] ?> productos sin stock disponible</span>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-12">
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-boxes mr-2"></i> Control de Stock</h3>
                <div class="card-tools">
                    <?php if (count($sucursales) > 1): ?>
                        <div class="input-group input-group-sm" style="width: 250px;">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-store"></i></span>
                            </div>
                            <select class="form-control" id="filtroSucursal">
                                <option value="">Todas las sucursales</option>
                                <?php foreach ($sucursales as $suc): ?>
                                    <option value="<?= $suc['id'] ?>" <?= $sucursal_actual == $suc['id'] ? 'selected' : '' ?>>
                                        <?= esc($suc['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover data-table" id="stockTable">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th>Marca</th>
                            <th>Sucursal</th>
                            <th>Cantidad</th>
                            <th>Reservada</th>
                            <th>Disponible</th>
                            <th>Stock Mínimo</th>
                            <th>Punto Reorden</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stock as $item): ?>
                            <?php
                            $nivelAlerta = 'NORMAL';
                            if ($item['cantidad_disponible'] <= $item['stock_minimo']) {
                                $nivelAlerta = 'CRITICO';
                            } elseif ($item['cantidad_disponible'] <= $item['punto_reorden']) {
                                $nivelAlerta = 'BAJO';
                            }
                            ?>
                            <tr class="<?= $nivelAlerta == 'CRITICO' ? 'table-danger' : ($nivelAlerta == 'BAJO' ? 'table-warning' : '') ?>">
                                <td><?= esc($item['codigo']) ?></td>
                                <td><strong><?= esc($item['producto_nombre']) ?></strong></td>
                                <td><?= esc($item['categoria_nombre'] ?? '-') ?></td>
                                <td><?= esc($item['marca_nombre'] ?? '-') ?></td>
                                <td><?= esc($item['sucursal_nombre']) ?></td>
                                <td><?= number_format($item['cantidad'], 0, ',', '.') ?></td>
                                <td><?= number_format($item['cantidad_reservada'], 0, ',', '.') ?></td>
                                <td>
                                    <strong><?= number_format($item['cantidad_disponible'], 0, ',', '.') ?></strong>
                                </td>
                                <td><?= number_format($item['stock_minimo'], 0, ',', '.') ?></td>
                                <td><?= number_format($item['punto_reorden'], 0, ',', '.') ?></td>
                                <td>
                                    <?php if ($nivelAlerta == 'CRITICO'): ?>
                                        <span class="badge badge-danger badge-lg">
                                            <i class="fas fa-exclamation-circle"></i> Crítico
                                        </span>
                                    <?php elseif ($nivelAlerta == 'BAJO'): ?>
                                        <span class="badge badge-warning badge-lg">
                                            <i class="fas fa-exclamation-triangle"></i> Bajo
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-success badge-lg">
                                            <i class="fas fa-check-circle"></i> Normal
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-primary" 
                                                onclick="editarStock(<?= $item['id'] ?>, '<?= esc($item['producto_nombre']) ?>', <?= $item['cantidad'] ?>, <?= $item['cantidad_disponible'] ?>)"
                                                title="Editar Stock">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-info" 
                                                onclick="verKardex(<?= $item['producto_id'] ?>)"
                                                title="Ver Kardex">
                                            <i class="fas fa-list-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Stock -->
<div class="modal fade" id="modalStock" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title">
                    <i class="fas fa-edit mr-2"></i> Editar Stock
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formStock">
                <div class="modal-body">
                    <input type="hidden" name="stock_id" id="stock_id">
                    <div class="alert alert-info">
                        <strong>Producto:</strong> <span id="modal_producto_nombre"></span><br>
                        <strong>Stock Disponible Actual:</strong> <span id="modal_stock_disponible" class="font-weight-bold"></span>
                    </div>
                    <div class="form-group">
                        <label>Cantidad <span class="text-danger">*</span></label>
                        <input type="number" name="cantidad" id="stock_cantidad" class="form-control form-control-lg" 
                               step="1" min="0" required>
                        <small class="form-text text-muted">Ingrese la nueva cantidad de stock</small>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt mr-1"></i> Ubicación</label>
                        <input type="text" name="ubicacion" id="stock_ubicacion" class="form-control" 
                               placeholder="Ej: Estante A-5, Almacén 2">
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><i class="fas fa-barcode mr-1"></i> Lote</label>
                                <input type="text" name="lote" id="stock_lote" class="form-control" 
                                       placeholder="Número de lote">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><i class="fas fa-calendar-alt mr-1"></i> Fecha de Vencimiento</label>
                                <input type="date" name="fecha_vencimiento" id="stock_fecha_vencimiento" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('styles') ?>
<style>
.badge-lg {
    padding: 0.5em 0.75em;
    font-size: 0.875rem;
}
.small-box {
    border-radius: 0.25rem;
    box-shadow: 0 0 1px rgba(0,0,0,.125), 0 1px 3px rgba(0,0,0,.2);
    display: block;
    margin-bottom: 20px;
    position: relative;
}
.small-box > .inner {
    padding: 10px;
}
.small-box > .small-box-footer {
    background-color: rgba(0,0,0,.1);
    color: rgba(255,255,255,.8);
    display: block;
    padding: 3px 0;
    position: relative;
    text-align: center;
    text-decoration: none;
    z-index: 10;
}
.small-box .icon {
    color: rgba(0,0,0,.15);
    z-index: 0;
    position: absolute;
    right: 10px;
    top: 10px;
    font-size: 70px;
}
.table-hover tbody tr:hover {
    background-color: rgba(0,123,255,.075);
    cursor: pointer;
}
</style>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(document).ready(function() {
    var config = Object.assign({}, datatablesEsConfig, {
        'order': [[7, 'asc']], // Ordenar por cantidad disponible
        'pageLength': 25,
        'responsive': true,
        'columnDefs': [
            { 'orderable': false, 'targets': -1 } // Deshabilitar ordenamiento en columna de acciones
        ]
    });
    
    var table = $('#stockTable').DataTable(config);
    
    // Búsqueda rápida
    $('#stockTable_filter input').attr('placeholder', 'Buscar producto, código, categoría...');
    
    // Filtro por sucursal
    $('#filtroSucursal').on('change', function() {
        const sucursalId = $(this).val();
        const url = sucursalId ? 
            '<?= base_url('inventario/stock') ?>?sucursal=' + sucursalId :
            '<?= base_url('inventario/stock') ?>';
        window.location.href = url;
    });
    
    // Filtro rápido por estado
    $('body').on('click', '.badge', function() {
        const estado = $(this).text().trim();
        table.search(estado).draw();
    });
});

$('#formStock').on('submit', function(e) {
    e.preventDefault();
    
    const btnSubmit = $(this).find('button[type="submit"]');
    const originalText = btnSubmit.html();
    btnSubmit.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...');
    
    $.ajax({
        url: '<?= base_url('inventario/stock/actualizar') ?>',
        type: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: 'Stock actualizado correctamente',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire('Error', response.message || 'Error al actualizar el stock', 'error');
                btnSubmit.prop('disabled', false).html(originalText);
            }
        },
        error: function(xhr) {
            Swal.fire('Error', 'Error al procesar la solicitud', 'error');
            btnSubmit.prop('disabled', false).html(originalText);
        }
    });
});

function editarStock(id, productoNombre, cantidad, disponible) {
    $('#stock_id').val(id);
    $('#stock_cantidad').val(cantidad);
    $('#modal_producto_nombre').text(productoNombre);
    $('#modal_stock_disponible').text(parseFloat(disponible).toLocaleString('es-PY', {minimumFractionDigits: 0, maximumFractionDigits: 0}));
    $('#stock_ubicacion').val('');
    $('#stock_lote').val('');
    $('#stock_fecha_vencimiento').val('');
    $('#modalStock').modal('show');
}

function verKardex(productoId) {
    window.open('<?= base_url('inventario/stock/kardex') ?>?producto_id=' + productoId, '_blank');
}
</script>
<?= $this->endSection() ?>
