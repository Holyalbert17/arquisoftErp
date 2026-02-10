<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('reportes') ?>">Reportes</a></li>
<li class="breadcrumb-item active">Movimientos de Stock</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Filtros de Búsqueda</h3>
            </div>
            <div class="card-body">
                <form action="" method="get">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Fecha Desde</label>
                                <input type="date" name="fecha_desde" class="form-control" value="<?= $fecha_desde ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Fecha Hasta</label>
                                <input type="date" name="fecha_hasta" class="form-control" value="<?= $fecha_hasta ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Tipo Movimiento</label>
                                <select name="tipo_movimiento" class="form-control">
                                    <option value="">Todos</option>
                                    <option value="ENTRADA" <?= $tipo_movimiento == 'ENTRADA' ? 'selected' : '' ?>>Entrada
                                    </option>
                                    <option value="SALIDA" <?= $tipo_movimiento == 'SALIDA' ? 'selected' : '' ?>>Salida
                                    </option>
                                    <option value="AJUSTE" <?= $tipo_movimiento == 'AJUSTE' ? 'selected' : '' ?>>Ajuste
                                    </option>
                                    <option value="TRANSFERENCIA" <?= $tipo_movimiento == 'TRANSFERENCIA' ? 'selected' : '' ?>>Transferencia</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Producto</label>
                                <select name="producto_id" class="form-control select2">
                                    <option value="">Todos</option>
                                    <?php foreach ($productos as $p): ?>
                                        <option value="<?= $p['id'] ?>" <?= $producto_id == $p['id'] ? 'selected' : '' ?>>
                                            <?= esc($p['codigo']) ?> -
                                            <?= esc($p['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12 text-right">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filtrar</button>
                            <a href="<?= base_url('reportes/inventario/movimientos') ?>"
                                class="btn btn-secondary">Limpiar</a>
                            <a href="<?= base_url('reportes/inventario/exportar/movimientos?fecha_desde=' . $fecha_desde . '&fecha_hasta=' . $fecha_hasta . '&tipo_movimiento=' . $tipo_movimiento . '&producto_id=' . $producto_id) ?>"
                                class="btn btn-success ml-2">
                                <i class="fas fa-file-excel"></i> Exportar
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Resultados</h3>
            </div>
            <div class="card-body">
                <table id="table-movimientos" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Producto</th>
                            <th>Sucursal</th>
                            <th>Cantidad</th>
                            <th>Stock Anterior</th>
                            <th>Stock Nuevo</th>
                            <th>Usuario</th>
                            <th>Detalle</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movimientos as $mov): ?>
                            <tr>
                                <td>
                                    <?= date('d/m/Y H:i', strtotime($mov['fecha_movimiento'])) ?>
                                </td>
                                <td>
                                    <?php
                                    $badge = 'secondary';
                                    if ($mov['tipo_movimiento'] == 'ENTRADA')
                                        $badge = 'success';
                                    elseif ($mov['tipo_movimiento'] == 'SALIDA')
                                        $badge = 'danger';
                                    elseif ($mov['tipo_movimiento'] == 'AJUSTE')
                                        $badge = 'warning';
                                    elseif ($mov['tipo_movimiento'] == 'TRANSFERENCIA')
                                        $badge = 'info';
                                    ?>
                                    <span class="badge badge-<?= $badge ?>">
                                        <?= $mov['tipo_movimiento'] ?>
                                    </span>
                                </td>
                                <td>
                                    <?= esc($mov['producto_nombre']) ?>
                                </td>
                                <td>
                                    <?= esc($mov['sucursal_nombre']) ?>
                                </td>
                                <td class="<?= $mov['cantidad'] > 0 ? 'text-success' : 'text-danger' ?> font-weight-bold">
                                    <?= ($mov['cantidad'] > 0 ? '+' : '') . number_format($mov['cantidad'], 2, ',', '.') ?>
                                </td>
                                <td>
                                    <?= number_format($mov['stock_anterior'], 2, ',', '.') ?>
                                </td>
                                <td>
                                    <?= number_format($mov['stock_actual'], 2, ',', '.') ?>
                                </td>
                                <td>
                                    <?= esc($mov['usuario_nombre'] . ' ' . $mov['usuario_apellido']) ?>
                                </td>
                                <td>
                                    <?= esc($mov['referencia'] ?? $mov['concepto']) ?>
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
        $('.select2').select2({
            theme: 'bootstrap4'
        });

        $('#table-movimientos').DataTable({
            "responsive": true,
            "autoWidth": false,
            "order": [[0, "desc"]],
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/Spanish.json"
            }
        });
    });
</script>
<?= $this->endSection() ?>