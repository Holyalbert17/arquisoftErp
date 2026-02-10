<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('inventario') ?>">Inventario</a></li>
<li class="breadcrumb-item active">Ajustes</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
// Calcular estadísticas
$totalAjustes = count($ajustes);
$pendientes = 0;
$procesados = 0;
$entradas = 0;
$salidas = 0;

foreach ($ajustes as $ajuste) {
    switch ($ajuste['estado']) {
        case 'PENDIENTE':
            $pendientes++;
            break;
        case 'PROCESADO':
            $procesados++;
            break;
    }
    switch ($ajuste['tipo_ajuste']) {
        case 'ENTRADA':
        case 'FISICO':
            $entradas++;
            break;
        case 'SALIDA':
        case 'MERMA':
            $salidas++;
            break;
    }
}
?>

<!-- Cards de Estadísticas -->
<div class="row mb-4">
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3><?= number_format($totalAjustes, 0, ',', '.') ?></h3>
                <p>Total Ajustes</p>
            </div>
            <div class="icon">
                <i class="fas fa-edit"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3><?= number_format($pendientes, 0, ',', '.') ?></h3>
                <p>Pendientes</p>
            </div>
            <div class="icon">
                <i class="fas fa-clock"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner">
                <h3><?= number_format($procesados, 0, ',', '.') ?></h3>
                <p>Procesados</p>
            </div>
            <div class="icon">
                <i class="fas fa-check-double"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-primary">
            <div class="inner">
                <h3><?= number_format($entradas, 0, ',', '.') ?> / <?= number_format($salidas, 0, ',', '.') ?></h3>
                <p>Entradas / Salidas</p>
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
                <h3 class="card-title"><i class="fas fa-edit mr-1"></i> Ajustes de Inventario</h3>
                <div class="card-tools">
                    <a href="<?= base_url('inventario/ajustes/crear') ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Nuevo Ajuste
                    </a>
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover data-table" id="ajustesTable">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Tipo</th>
                                <th>Motivo</th>
                                <th>Estado</th>
                                <th>Fecha Solicitud</th>
                                <th>Fecha Aprobación</th>
                                <th>Fecha Procesamiento</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ajustes as $ajuste): ?>
                                <?php
                                $tipos = [
                                    'ENTRADA' => ['badge' => 'success', 'text' => 'Entrada', 'icon' => 'arrow-up'],
                                    'SALIDA' => ['badge' => 'danger', 'text' => 'Salida', 'icon' => 'arrow-down'],
                                    'FISICO' => ['badge' => 'info', 'text' => 'Físico', 'icon' => 'clipboard-check'],
                                    'MERMA' => ['badge' => 'warning', 'text' => 'Merma', 'icon' => 'exclamation-triangle']
                                ];
                                $tipo = $tipos[$ajuste['tipo_ajuste']] ?? ['badge' => 'secondary', 'text' => $ajuste['tipo_ajuste'], 'icon' => 'question'];
                                
                                $estados = [
                                    'PENDIENTE' => ['badge' => 'warning', 'text' => 'Pendiente', 'icon' => 'clock'],
                                    'APROBADO' => ['badge' => 'info', 'text' => 'Aprobado', 'icon' => 'check'],
                                    'PROCESADO' => ['badge' => 'success', 'text' => 'Procesado', 'icon' => 'check-double'],
                                    'CANCELADO' => ['badge' => 'danger', 'text' => 'Cancelado', 'icon' => 'times']
                                ];
                                $estado = $estados[$ajuste['estado']] ?? ['badge' => 'secondary', 'text' => $ajuste['estado'], 'icon' => 'question'];
                                ?>
                                <tr>
                                    <td><strong><?= esc($ajuste['numero_ajuste']) ?></strong></td>
                                    <td>
                                        <span class="badge badge-<?= $tipo['badge'] ?> badge-lg">
                                            <i class="fas fa-<?= $tipo['icon'] ?>"></i> 
                                            <?= $tipo['text'] ?>
                                        </span>
                                    </td>
                                    <td><?= esc($ajuste['motivo']) ?></td>
                                    <td>
                                        <span class="badge badge-<?= $estado['badge'] ?> badge-lg">
                                            <i class="fas fa-<?= $estado['icon'] ?>"></i> 
                                            <?= $estado['text'] ?>
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($ajuste['fecha_solicitud'])) ?></td>
                                    <td>
                                        <?= $ajuste['fecha_aprobacion'] ? date('d/m/Y H:i', strtotime($ajuste['fecha_aprobacion'])) : '-' ?>
                                    </td>
                                    <td>
                                        <?= $ajuste['fecha_procesamiento'] ? date('d/m/Y H:i', strtotime($ajuste['fecha_procesamiento'])) : '-' ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="<?= base_url('inventario/ajustes/ver/' . $ajuste['id']) ?>" 
                                               class="btn btn-sm btn-info" title="Ver Detalles">
                                                <i class="fas fa-eye"></i>
                                            </a>
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
.small-box .icon {
    color: rgba(0,0,0,.15);
    z-index: 0;
    position: absolute;
    right: 10px;
    top: 10px;
    font-size: 70px;
}
</style>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(document).ready(function() {
    var config = Object.assign({}, datatablesEsConfig, {
        'order': [[4, 'desc']],
        'pageLength': 25,
        'responsive': true
    });
    
    $('#ajustesTable').DataTable(config);
    
    // Filtro rápido por estado
    $('body').on('click', '.badge', function() {
        const estado = $(this).text().trim();
        $('#ajustesTable').DataTable().search(estado).draw();
    });
});
</script>
<?= $this->endSection() ?>
