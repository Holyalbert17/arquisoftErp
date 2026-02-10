<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('reportes') ?>">Reportes</a></li>
<li class="breadcrumb-item active">Reporte de Inventario</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-boxes mr-1"></i>
            Reporte de Inventario
        </h3>
    </div>
    <div class="card-body">
        <!-- Filtros -->
        <div class="row mb-3">
            <div class="col-md-12">
                <form method="GET" action="<?= base_url('reportes/inventario') ?>" class="form-inline">
                    <?php if (count($sucursales) > 1): ?>
                    <div class="form-group mr-2">
                        <label class="mr-2">Sucursal:</label>
                        <select name="sucursal_id" class="form-control">
                            <option value="">Todas</option>
                            <?php foreach ($sucursales as $suc): ?>
                                <option value="<?= $suc['id'] ?>" <?= $sucursal_actual == $suc['id'] ? 'selected' : '' ?>>
                                    <?= esc($suc['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary mr-2">
                        <i class="fas fa-filter mr-1"></i> Filtrar
                    </button>
                    <a href="<?= base_url('reportes/inventario/exportar/inventario') ?><?= $sucursal_actual ? '?sucursal_id=' . $sucursal_actual : '' ?>" 
                       class="btn btn-success">
                        <i class="fas fa-file-excel mr-1"></i> Exportar Excel
                    </a>
                </form>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="row mb-3">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?= number_format($total_productos, 0, ',', '.') ?></h3>
                        <p>Total Productos</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-boxes"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-primary">
                    <div class="inner">
                        <h3><?= number_format($total_valorizacion_costo / 1000000, 2, ',', '.') ?>M</h3>
                        <p>Valorizado (Costo)</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?= number_format($utilidad_potencial / 1000000, 2, ',', '.') ?>M</h3>
                        <p>Utilidad Potencial</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3><?= number_format($productos_stock_bajo + $productos_stock_critico, 0, ',', '.') ?></h3>
                        <p>Stock Bajo/Crítico</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover data-table" id="inventarioTable">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th>Marca</th>
                        <th>Sucursal</th>
                        <th>Cantidad</th>
                        <th>Disponible</th>
                        <th>Stock Mín.</th>
                        <th>Valor Costo</th>
                        <th>Valor Venta</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productos as $item): 
                        $stockBajo = $item['controla_stock'] && $item['cantidad_disponible'] <= $item['stock_minimo'];
                        $stockCritico = $item['controla_stock'] && $item['cantidad_disponible'] <= $item['stock_minimo'];
                    ?>
                        <tr class="<?= $stockCritico ? 'table-danger' : ($stockBajo ? 'table-warning' : '') ?>">
                            <td><?= esc($item['codigo']) ?></td>
                            <td><strong><?= esc($item['producto_nombre']) ?></strong></td>
                            <td><?= esc($item['categoria_nombre'] ?? '-') ?></td>
                            <td><?= esc($item['marca_nombre'] ?? '-') ?></td>
                            <td><?= esc($item['sucursal_nombre']) ?></td>
                            <td class="text-right"><?= number_format($item['cantidad'], 2, ',', '.') ?></td>
                            <td class="text-right">
                                <strong><?= number_format($item['cantidad_disponible'], 2, ',', '.') ?></strong>
                            </td>
                            <td class="text-right"><?= number_format($item['stock_minimo'], 0, ',', '.') ?></td>
                            <td class="text-right"><?= number_format($item['valor_costo'], 0, ',', '.') ?> Gs.</td>
                            <td class="text-right"><?= number_format($item['valor_venta'], 0, ',', '.') ?> Gs.</td>
                            <td>
                                <?php if ($stockCritico): ?>
                                    <span class="badge badge-danger">Crítico</span>
                                <?php elseif ($stockBajo): ?>
                                    <span class="badge badge-warning">Bajo</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Normal</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(document).ready(function() {
    var config = Object.assign({}, datatablesEsConfig, {
        'order': [[5, 'asc']],
        'pageLength': 25,
        'responsive': true
    });
    
    $('#inventarioTable').DataTable(config);
});
</script>
<?= $this->endSection() ?>
