<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('reportes') ?>">Reportes</a></li>
<li class="breadcrumb-item active">Stock Bajo</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Productos con Stock Bajo</h3>
                <div class="card-tools">
                    <a href="<?= base_url('reportes/inventario/exportar/stock_bajo') ?>" class="btn btn-tool"
                        title="Exportar a Excel">
                        <i class="fas fa-file-excel text-success"></i> Exportar
                    </a>
                </div>
            </div>
            <div class="card-body">
                <table id="table-stock-bajo" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th>Sucursal</th>
                            <th>Stock Mínimo</th>
                            <th>Punto Reorden</th>
                            <th>Disponible</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productos as $producto): ?>
                            <tr>
                                <td>
                                    <?= esc($producto['codigo']) ?>
                                </td>
                                <td>
                                    <?= esc($producto['producto_nombre']) ?>
                                </td>
                                <td>
                                    <?= esc($producto['categoria_nombre'] ?? '-') ?>
                                </td>
                                <td>
                                    <?= esc($producto['sucursal_nombre']) ?>
                                </td>
                                <td>
                                    <?= number_format($producto['stock_minimo'], 0, ',', '.') ?>
                                </td>
                                <td>
                                    <?= number_format($producto['punto_reorden'], 0, ',', '.') ?>
                                </td>
                                <td class="font-weight-bold text-danger">
                                    <?= number_format($producto['cantidad_disponible'], 2, ',', '.') ?>
                                </td>
                                <td>
                                    <?php if ($producto['cantidad_disponible'] <= $producto['stock_minimo']): ?>
                                        <span class="badge badge-danger">Crítico</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Bajo</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    $(function () {
        $('#table-stock-bajo').DataTable({
            "responsive": true,
            "autoWidth": false,
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/Spanish.json"
            }
        });
    });
</script>
<?= $this->endSection() ?>