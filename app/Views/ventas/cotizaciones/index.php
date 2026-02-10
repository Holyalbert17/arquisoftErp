<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('ventas') ?>">Ventas</a></li>
<li class="breadcrumb-item active">Cotizaciones</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-file-invoice-dollar mr-1"></i> Cotizaciones</h3>
                <div class="card-tools">
                    <a href="<?= base_url('ventas/cotizaciones/crear') ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Nueva Cotización
                    </a>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped data-table">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Cliente</th>
                            <th>Fecha Emisión</th>
                            <th>Fecha Vencimiento</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cotizaciones as $cotizacion): ?>
                            <tr>
                                <td><?= esc($cotizacion['numero_cotizacion']) ?></td>
                                <td>
                                    <?php
                                    $clienteModel = new \App\Models\ClienteModel();
                                    $cliente = $clienteModel->find($cotizacion['cliente_id']);
                                    if ($cliente) {
                                        if ($cliente['tipo_cliente'] == 'PERSONA') {
                                            echo esc(trim(($cliente['nombre'] ?? '') . ' ' . ($cliente['apellido'] ?? '')));
                                        } else {
                                            echo esc($cliente['nombre_fantasia'] ?? $cliente['razon_social'] ?? '');
                                        }
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td><?= date('d/m/Y', strtotime($cotizacion['fecha_emision'])) ?></td>
                                <td><?= date('d/m/Y', strtotime($cotizacion['fecha_vencimiento'])) ?></td>
                                <td><?= number_format((float)$cotizacion['total'], 0, ',', '.') ?> Gs.</td>
                                <td>
                                    <?php
                                    $estados = [
                                        'PENDIENTE' => 'warning',
                                        'ENVIADA' => 'info',
                                        'VENCIDA' => 'secondary',
                                        'APROBADA' => 'success',
                                        'RECHAZADA' => 'danger',
                                        'CONVERTIDA_VENTA' => 'primary'
                                    ];
                                    $badge = $estados[$cotizacion['estado']] ?? 'secondary';
                                    ?>
                                    <span class="badge badge-<?= $badge ?>"><?= esc($cotizacion['estado']) ?></span>
                                </td>
                                <td>
                                    <a href="<?= base_url('ventas/cotizaciones/ver/' . $cotizacion['id']) ?>" 
                                       class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i>
                                    </a>
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
$(document).ready(function() {
    $('.data-table').DataTable(datatablesEsConfig);
});
</script>
<?= $this->endSection() ?>
