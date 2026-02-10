<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('inventario') ?>">Inventario</a></li>
<li class="breadcrumb-item active">Transferencias</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
// Calcular estadísticas
$totalTransferencias = count($transferencias);
$pendientes = 0;
$enTransito = 0;
$recibidas = 0;

foreach ($transferencias as $transf) {
    switch ($transf['estado']) {
        case 'PENDIENTE':
            $pendientes++;
            break;
        case 'EN_TRANSITO':
            $enTransito++;
            break;
        case 'RECIBIDA':
            $recibidas++;
            break;
    }
}
?>

<!-- Cards de Estadísticas -->
<div class="row mb-4">
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3><?= number_format($totalTransferencias, 0, ',', '.') ?></h3>
                <p>Total Transferencias</p>
            </div>
            <div class="icon">
                <i class="fas fa-exchange-alt"></i>
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
        <div class="small-box bg-primary">
            <div class="inner">
                <h3><?= number_format($enTransito, 0, ',', '.') ?></h3>
                <p>En Tránsito</p>
            </div>
            <div class="icon">
                <i class="fas fa-truck"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner">
                <h3><?= number_format($recibidas, 0, ',', '.') ?></h3>
                <p>Recibidas</p>
            </div>
            <div class="icon">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-exchange-alt mr-1"></i> Transferencias de Stock</h3>
                <div class="card-tools">
                    <a href="<?= base_url('inventario/transferencias/crear') ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Nueva Transferencia
                    </a>
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover data-table" id="transferenciasTable">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Sucursal Origen</th>
                                <th>Sucursal Destino</th>
                                <th>Estado</th>
                                <th>Fecha Solicitud</th>
                                <th>Fecha Envío</th>
                                <th>Fecha Recepción</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transferencias as $transferencia): ?>
                                <?php
                                $sucursalModel = new \App\Models\SucursalModel();
                                $sucOrigen = $sucursalModel->find($transferencia['sucursal_origen_id']);
                                $sucDestino = $sucursalModel->find($transferencia['sucursal_destino_id']);
                                
                                $estados = [
                                    'PENDIENTE' => ['badge' => 'warning', 'text' => 'Pendiente', 'icon' => 'clock'],
                                    'EN_TRANSITO' => ['badge' => 'info', 'text' => 'En Tránsito', 'icon' => 'truck'],
                                    'RECIBIDA' => ['badge' => 'success', 'text' => 'Recibida', 'icon' => 'check-circle'],
                                    'RECHAZADA' => ['badge' => 'danger', 'text' => 'Rechazada', 'icon' => 'times-circle'],
                                    'CANCELADA' => ['badge' => 'secondary', 'text' => 'Cancelada', 'icon' => 'ban']
                                ];
                                $estado = $estados[$transferencia['estado']] ?? ['badge' => 'secondary', 'text' => $transferencia['estado'], 'icon' => 'question'];
                                ?>
                                <tr>
                                    <td><strong><?= esc($transferencia['numero_transferencia']) ?></strong></td>
                                    <td>
                                        <i class="fas fa-arrow-up text-primary mr-1"></i>
                                        <?= $sucOrigen ? esc($sucOrigen['nombre']) : '-' ?>
                                    </td>
                                    <td>
                                        <i class="fas fa-arrow-down text-success mr-1"></i>
                                        <?= $sucDestino ? esc($sucDestino['nombre']) : '-' ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $estado['badge'] ?> badge-lg">
                                            <i class="fas fa-<?= $estado['icon'] ?>"></i> 
                                            <?= $estado['text'] ?>
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($transferencia['fecha_solicitud'])) ?></td>
                                    <td>
                                        <?= $transferencia['fecha_envio'] ? date('d/m/Y H:i', strtotime($transferencia['fecha_envio'])) : '-' ?>
                                    </td>
                                    <td>
                                        <?= $transferencia['fecha_recepcion'] ? date('d/m/Y H:i', strtotime($transferencia['fecha_recepcion'])) : '-' ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="<?= base_url('inventario/transferencias/ver/' . $transferencia['id']) ?>" 
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
    
    $('#transferenciasTable').DataTable(config);
    
    // Filtro rápido por estado
    $('body').on('click', '.badge', function() {
        const estado = $(this).text().trim();
        $('#transferenciasTable').DataTable().search(estado).draw();
    });
});
</script>
<?= $this->endSection() ?>
