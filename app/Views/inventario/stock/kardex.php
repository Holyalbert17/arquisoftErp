<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('inventario') ?>">Inventario</a></li>
<li class="breadcrumb-item"><a href="<?= base_url('inventario/stock') ?>">Stock</a></li>
<li class="breadcrumb-item active">Kardex</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-list-alt mr-2"></i> Kardex de Producto
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-sm btn-success" onclick="exportarKardex()">
                        <i class="fas fa-file-excel mr-1"></i> Exportar Excel
                    </button>
                </div>
            </div>
            <div class="card-body">
                <!-- Información del Producto -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="info-box">
                            <span class="info-box-icon bg-info"><i class="fas fa-box"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Producto</span>
                                <span class="info-box-number"><?= esc($producto['codigo']) ?> - <?= esc($producto['nombre']) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-box">
                            <span class="info-box-icon bg-success"><i class="fas fa-arrow-up"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Saldo Inicial</span>
                                <span class="info-box-number"><?= number_format($saldo_inicial ?? 0, 2, ',', '.') ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-box">
                            <span class="info-box-icon bg-primary"><i class="fas fa-arrow-down"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Saldo Final</span>
                                <span class="info-box-number"><?= number_format($saldo_final ?? 0, 2, ',', '.') ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="row mb-3">
                    <div class="col-md-12">
                        <form method="GET" action="<?= base_url('inventario/stock/kardex') ?>" class="form-inline">
                            <input type="hidden" name="producto_id" value="<?= $producto['id'] ?>">
                            <div class="form-group mr-2">
                                <label class="mr-2">Desde:</label>
                                <input type="date" name="fecha_desde" class="form-control" value="<?= $fecha_desde ?>" required>
                            </div>
                            <div class="form-group mr-2">
                                <label class="mr-2">Hasta:</label>
                                <input type="date" name="fecha_hasta" class="form-control" value="<?= $fecha_hasta ?>" required>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search mr-1"></i> Buscar
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Tabla de Movimientos -->
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover data-table" id="kardexTable">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo Movimiento</th>
                                <th>Sucursal</th>
                                <th>Documento</th>
                                <th>Entrada</th>
                                <th>Salida</th>
                                <th>Saldo</th>
                                <th>Costo Unit.</th>
                                <th>Usuario</th>
                                <th>Motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($movimientos as $mov): ?>
                                <?php
                                $esEntrada = in_array($mov['tipo_movimiento'], [
                                    'COMPRA', 'AJUSTE_ENTRADA', 'TRANSFERENCIA_ENTRADA', 
                                    'DEVOLUCION_VENTA', 'INVENTARIO_INICIAL'
                                ]);
                                ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($mov['fecha_movimiento'])) ?></td>
                                    <td>
                                        <span class="badge badge-<?= $esEntrada ? 'success' : 'danger' ?>">
                                            <?= esc($mov['tipo_movimiento']) ?>
                                        </span>
                                    </td>
                                    <td><?= esc($mov['sucursal_nombre'] ?? '-') ?></td>
                                    <td><?= esc($mov['documento_referencia'] ?? '-') ?></td>
                                    <td class="text-success text-right">
                                        <?= $esEntrada ? number_format($mov['cantidad'], 2, ',', '.') : '-' ?>
                                    </td>
                                    <td class="text-danger text-right">
                                        <?= !$esEntrada ? number_format($mov['cantidad'], 2, ',', '.') : '-' ?>
                                    </td>
                                    <td class="text-right font-weight-bold">
                                        <?= number_format($mov['saldo_acumulado'], 2, ',', '.') ?>
                                    </td>
                                    <td class="text-right">
                                        <?= $mov['costo_unitario'] ? number_format($mov['costo_unitario'], 0, ',', '.') : '-' ?>
                                    </td>
                                    <td><?= esc(($mov['usuario_nombre'] ?? '') . ' ' . ($mov['usuario_apellido'] ?? '')) ?></td>
                                    <td><?= esc($mov['motivo'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(document).ready(function() {
    var config = Object.assign({}, datatablesEsConfig, {
        'order': [[0, 'desc']],
        'pageLength': 25,
        'responsive': true
    });
    
    $('#kardexTable').DataTable(config);
});

function exportarKardex() {
    const productoId = <?= $producto['id'] ?>;
    const fechaDesde = $('input[name="fecha_desde"]').val();
    const fechaHasta = $('input[name="fecha_hasta"]').val();
    
    window.location.href = '<?= base_url('reportes/inventario/exportar/kardex') ?>?producto_id=' + productoId + 
                          '&fecha_desde=' + fechaDesde + '&fecha_hasta=' + fechaHasta;
}
</script>
<?= $this->endSection() ?>
