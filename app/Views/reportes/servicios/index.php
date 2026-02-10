<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('reportes') ?>">Reportes</a></li>
<li class="breadcrumb-item active">Reporte de Servicios</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-tools mr-1"></i>
            Reporte de Servicios
        </h3>
        <div class="card-tools">
            <form method="GET" class="form-inline">
                <input type="date" name="fecha_desde" class="form-control form-control-sm mr-2" 
                       value="<?= esc($fecha_desde) ?>">
                <input type="date" name="fecha_hasta" class="form-control form-control-sm mr-2" 
                       value="<?= esc($fecha_hasta) ?>">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="fas fa-search"></i> Filtrar
                </button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-3">
                <div class="info-box">
                    <span class="info-box-icon bg-info"><i class="fas fa-clipboard-list"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Órdenes</span>
                        <span class="info-box-number"><?= number_format($total_ordenes, 0, ',', '.') ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-box">
                    <span class="info-box-icon bg-warning"><i class="fas fa-clock"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Pendientes</span>
                        <span class="info-box-number"><?= number_format($ordenes_pendientes, 0, ',', '.') ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-box">
                    <span class="info-box-icon bg-success"><i class="fas fa-check-circle"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Completadas</span>
                        <span class="info-box-number"><?= number_format($ordenes_completadas, 0, ',', '.') ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-box">
                    <span class="info-box-icon bg-primary"><i class="fas fa-dollar-sign"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Ingresos</span>
                        <span class="info-box-number"><?= number_format($total_ingresos, 0, ',', '.') ?> Gs.</span>
                    </div>
                </div>
            </div>
        </div>

        <table class="table table-bordered table-striped data-table">
            <thead>
                <tr>
                    <th>Número</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Equipo</th>
                    <th>Estado</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ordenes as $orden): ?>
                    <tr>
                        <td><?= esc($orden['numero_orden']) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($orden['fecha_creacion'])) ?></td>
                        <td>
                            <?php
                            $clienteModel = new \App\Models\ClienteModel();
                            $cliente = $clienteModel->find($orden['cliente_id']);
                            if ($cliente) {
                                echo esc($cliente['tipo_cliente'] == 'PERSONA' 
                                    ? trim(($cliente['nombre'] ?? '') . ' ' . ($cliente['apellido'] ?? ''))
                                    : ($cliente['nombre_fantasia'] ?? $cliente['razon_social'] ?? ''));
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </td>
                        <td><?= esc($orden['equipo']) ?></td>
                        <td>
                            <?php
                            $badges = [
                                'PENDIENTE' => 'warning',
                                'EN_PROCESO' => 'info',
                                'COMPLETADA' => 'success',
                                'ENTREGADA' => 'primary',
                                'CANCELADA' => 'danger'
                            ];
                            $badge = $badges[$orden['estado']] ?? 'secondary';
                            ?>
                            <span class="badge badge-<?= $badge ?>"><?= esc($orden['estado']) ?></span>
                        </td>
                        <td><strong><?= number_format($orden['total'] ?? 0, 0, ',', '.') ?> Gs.</strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(document).ready(function() {
    $('.data-table').DataTable({
        'language': {
            'url': '<?= base_url('assets/js/datatables-es.js') ?>'
        },
        'responsive': true,
        'pageLength': 25,
        'order': [[1, 'desc']]
    });
});
</script>
<?= $this->endSection() ?>
