<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('inventario') ?>">Inventario</a></li>
<li class="breadcrumb-item active">Movimientos de Stock</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
// Usar estadísticas del controlador
$estadisticas = $estadisticas ?? [
    'total_movimientos' => count($movimientos),
    'total_entradas' => 0,
    'total_salidas' => 0,
    'cantidad_entradas' => 0,
    'cantidad_salidas' => 0,
    'diferencia' => 0
];
?>

<!-- Filtros -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card card-outline card-secondary">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-filter mr-2"></i> Filtros</h3>
            </div>
            <div class="card-body">
                <form method="GET" action="<?= base_url('inventario/movimientos') ?>" class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Fecha Desde</label>
                            <input type="date" name="fecha_desde" class="form-control" value="<?= $fecha_desde ?? date('Y-m-01') ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Fecha Hasta</label>
                            <input type="date" name="fecha_hasta" class="form-control" value="<?= $fecha_hasta ?? date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Tipo Movimiento</label>
                            <select name="tipo_movimiento" class="form-control">
                                <option value="">Todos</option>
                                <?php foreach ($tipos_movimiento ?? [] as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= ($tipo_movimiento ?? '') == $key ? 'selected' : '' ?>>
                                        <?= esc($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Producto</label>
                            <select name="producto_id" class="form-control select2" style="width: 100%;">
                                <option value="">Todos</option>
                                <?php foreach ($productos ?? [] as $prod): ?>
                                    <option value="<?= $prod['id'] ?>" <?= ($producto_id ?? '') == $prod['id'] ? 'selected' : '' ?>>
                                        <?= esc($prod['codigo'] . ' - ' . $prod['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-search mr-1"></i> Buscar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Cards de Estadísticas -->
<div class="row mb-4">
    <div class="col-lg-2 col-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3><?= number_format($estadisticas['total_movimientos'], 0, ',', '.') ?></h3>
                <p>Total Movimientos</p>
            </div>
            <div class="icon">
                <i class="fas fa-exchange-alt"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-6">
        <div class="small-box bg-success">
            <div class="inner">
                <h3><?= number_format($estadisticas['total_entradas'], 0, ',', '.') ?></h3>
                <p>Entradas</p>
            </div>
            <div class="icon">
                <i class="fas fa-arrow-down"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-6">
        <div class="small-box bg-danger">
            <div class="inner">
                <h3><?= number_format($estadisticas['total_salidas'], 0, ',', '.') ?></h3>
                <p>Salidas</p>
            </div>
            <div class="icon">
                <i class="fas fa-arrow-up"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-6">
        <div class="small-box bg-primary">
            <div class="inner">
                <h3><?= number_format($estadisticas['cantidad_entradas'], 2, ',', '.') ?></h3>
                <p>Cant. Entradas</p>
            </div>
            <div class="icon">
                <i class="fas fa-plus-circle"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-6">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3><?= number_format($estadisticas['cantidad_salidas'], 2, ',', '.') ?></h3>
                <p>Cant. Salidas</p>
            </div>
            <div class="icon">
                <i class="fas fa-minus-circle"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-6">
        <div class="small-box <?= $estadisticas['diferencia'] >= 0 ? 'bg-success' : 'bg-danger' ?>">
            <div class="inner">
                <h3><?= number_format($estadisticas['diferencia'], 2, ',', '.') ?></h3>
                <p>Diferencia</p>
            </div>
            <div class="icon">
                <i class="fas fa-balance-scale"></i>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-list mr-2"></i> Historial de Movimientos</h3>
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
                    <table class="table table-bordered table-striped table-hover data-table" id="movimientosTable">
                        <thead>
                            <tr>
                                <th>Fecha/Hora</th>
                                <th>Producto</th>
                                <th>Código</th>
                                <th>Tipo</th>
                                <th class="text-center">Cantidad</th>
                                <th>Documento</th>
                                <th>Sucursal</th>
                                <th>Usuario</th>
                                <th>Motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($movimientos as $mov): ?>
                                <?php
                                $tipoClass = 'secondary';
                                $tipoIcon = 'fa-exchange-alt';
                                
                                $tiposEntrada = ['COMPRA', 'AJUSTE_ENTRADA', 'TRANSFERENCIA_ENTRADA', 'DEVOLUCION_VENTA', 'INVENTARIO_INICIAL'];
                                $tiposSalida = ['VENTA', 'AJUSTE_SALIDA', 'TRANSFERENCIA_SALIDA', 'DEVOLUCION_COMPRA', 'MERMA', 'USO_SERVICIO'];
                                
                                if (in_array($mov['tipo_movimiento'], $tiposEntrada)) {
                                    $tipoClass = 'success';
                                    $tipoIcon = 'fa-arrow-down';
                                } elseif (in_array($mov['tipo_movimiento'], $tiposSalida)) {
                                    $tipoClass = 'danger';
                                    $tipoIcon = 'fa-arrow-up';
                                }
                                
                                $cantidadClass = $tipoClass == 'success' ? 'text-success' : 'text-danger';
                                $cantidadSigno = $tipoClass == 'success' ? '+' : '-';
                                ?>
                                <tr>
                                    <td>
                                        <small><?= date('d/m/Y', strtotime($mov['fecha_movimiento'])) ?></small><br>
                                        <small class="text-muted"><?= date('H:i:s', strtotime($mov['fecha_movimiento'])) ?></small>
                                    </td>
                                    <td><strong><?= esc($mov['producto_nombre'] ?? 'N/A') ?></strong></td>
                                    <td><code><?= esc($mov['producto_codigo'] ?? '-') ?></code></td>
                                    <td>
                                        <span class="badge badge-<?= $tipoClass ?> badge-lg">
                                            <i class="fas <?= $tipoIcon ?>"></i> 
                                            <?= esc(str_replace('_', ' ', $mov['tipo_movimiento'])) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <strong class="<?= $cantidadClass ?>">
                                            <?= $cantidadSigno ?> <?= number_format(abs($mov['cantidad']), 2, ',', '.') ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <small><?= esc($mov['documento_referencia'] ?? '-') ?></small>
                                        <?php if ($mov['tipo_documento']): ?>
                                            <br><span class="badge badge-secondary badge-sm"><?= esc($mov['tipo_documento']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= esc($mov['sucursal_nombre'] ?? '-') ?></td>
                                    <td>
                                        <small><?= esc(($mov['usuario_nombre'] ?? '') . ' ' . ($mov['usuario_apellido'] ?? '')) ?></small>
                                    </td>
                                    <td>
                                        <small><?= esc($mov['motivo'] ?? '-') ?></small>
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
}
</style>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(document).ready(function() {
    var config = Object.assign({}, datatablesEsConfig, {
        'order': [[0, 'desc']], // Ordenar por fecha descendente
        'pageLength': 50,
        'responsive': true
    });
    
    $('#movimientosTable').DataTable(config);
    
    // Filtro por sucursal
    $('#filtroSucursal').on('change', function() {
        const sucursalId = $(this).val();
        const url = sucursalId ? 
            '<?= base_url('inventario/movimientos') ?>?sucursal=' + sucursalId :
            '<?= base_url('inventario/movimientos') ?>';
        window.location.href = url;
    });
});
</script>
<?= $this->endSection() ?>
