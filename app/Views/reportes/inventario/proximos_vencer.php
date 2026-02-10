<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('reportes') ?>">Reportes</a></li>
<li class="breadcrumb-item active">Próximos a Vencer</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Productos Próximos a Vencer (próximos
                    <?= $dias ?> días)
                </h3>
                <div class="card-tools">
                    <form action="<?= base_url('reportes/inventario/proximos-vencer') ?>" method="get"
                        class="form-inline d-inline-block mr-2">
                        <div class="input-group input-group-sm">
                            <input type="number" name="dias" class="form-control" placeholder="Días"
                                value="<?= $dias ?>" min="1" max="365">
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-default"><i class="fas fa-sync-alt"></i></button>
                            </div>
                        </div>
                    </form>
                    <a href="<?= base_url('reportes/inventario/exportar/proximos_vencer?dias=' . $dias) ?>"
                        class="btn btn-tool" title="Exportar a Excel">
                        <i class="fas fa-file-excel text-success"></i> Exportar
                    </a>
                </div>
            </div>
            <div class="card-body">
                <table id="table-vencimientos" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Sucursal</th>
                            <th>Lote</th>
                            <th>Fecha Vencimiento</th>
                            <th>Días Restantes</th>
                            <th>Cantidad</th>
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
                                    <?= esc($producto['sucursal_nombre']) ?>
                                </td>
                                <td>
                                    <?= esc($producto['lote'] ?? '-') ?>
                                </td>
                                <td>
                                    <?= date('d/m/Y', strtotime($producto['fecha_vencimiento'])) ?>
                                </td>
                                <td>
                                    <?php
                                    $diasRestantes = $producto['dias_restantes'];
                                    $class = $diasRestantes <= 7 ? 'text-danger font-weight-bold' : ($diasRestantes <= 15 ? 'text-warning' : '');
                                    ?>
                                    <span class="<?= $class ?>">
                                        <?= $diasRestantes ?> días
                                    </span>
                                </td>
                                <td>
                                    <?= number_format($producto['cantidad_disponible'], 2, ',', '.') ?>
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
        $('#table-vencimientos').DataTable({
            "responsive": true,
            "autoWidth": false,
            "order": [[5, "asc"]], // Ordenar por días restantes
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/Spanish.json"
            }
        });
    });
</script>
<?= $this->endSection() ?>