<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item active">Órdenes de Servicio</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-tools mr-1"></i> Lista de Órdenes de Servicio</h3>
                <div class="card-tools">
                    <a href="<?= base_url('servicios/ordenes/crear') ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Nueva Orden
                    </a>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped data-table">
                    <thead>
                        <tr>
                            <th>N° Orden</th>
                            <th>Cliente</th>
                            <th>Equipo</th>
                            <th>Problema</th>
                            <th>Estado</th>
                            <th>Prioridad</th>
                            <th>Fecha Recepción</th>
                            <th>Total</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ordenes as $orden): ?>
                            <?php 
                            $cliente = $orden['cliente'];
                            $estado = $orden['estado_nombre'];
                            $nombreCliente = $cliente ? 
                                ($cliente['tipo_cliente'] == 'PERSONA' 
                                    ? ($cliente['nombre'] . ' ' . ($cliente['apellido'] ?? ''))
                                    : ($cliente['nombre_fantasia'] ?? $cliente['razon_social']))
                                : 'N/A';
                            ?>
                            <tr>
                                <td><strong><?= esc($orden['numero_orden']) ?></strong></td>
                                <td><?= esc($nombreCliente) ?></td>
                                <td><?= esc($orden['tipo_equipo'] . ' ' . $orden['marca'] . ' ' . $orden['modelo']) ?></td>
                                <td><?= esc(substr($orden['problema_reportado'], 0, 50)) ?>...</td>
                                <td>
                                    <?php if ($estado): ?>
                                        <span class="badge" style="background-color: <?= $estado['color'] ?>">
                                            <?= esc($estado['nombre']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $prioridadColors = [
                                        'BAJA' => 'secondary',
                                        'NORMAL' => 'info',
                                        'ALTA' => 'warning',
                                        'URGENTE' => 'danger'
                                    ];
                                    $color = $prioridadColors[$orden['prioridad']] ?? 'secondary';
                                    ?>
                                    <span class="badge badge-<?= $color ?>"><?= esc($orden['prioridad']) ?></span>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($orden['fecha_recepcion'])) ?></td>
                                <td class="currency"><?= number_format($orden['presupuesto_total'] ?? 0, 0, ',', '.') ?> Gs.</td>
                                <td>
                                    <a href="<?= base_url('servicios/ordenes/ver/' . $orden['id']) ?>" 
                                       class="btn btn-sm btn-info" title="Ver">
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
    $('.data-table').DataTable({
        'language': datatablesEsConfig.language,
        'responsive': true,
        'pageLength': 25,
        'order': [[6, 'desc']]
    });
});
</script>
<?= $this->endSection() ?>
