<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('inventario') ?>">Inventario</a></li>
<li class="breadcrumb-item"><a href="<?= base_url('inventario/transferencias') ?>">Transferencias</a></li>
<li class="breadcrumb-item active">Ver Transferencia</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
$estados = [
    'PENDIENTE' => ['badge' => 'warning', 'text' => 'Pendiente', 'icon' => 'clock'],
    'EN_TRANSITO' => ['badge' => 'info', 'text' => 'En Tránsito', 'icon' => 'truck'],
    'RECIBIDA' => ['badge' => 'success', 'text' => 'Recibida', 'icon' => 'check-circle'],
    'RECHAZADA' => ['badge' => 'danger', 'text' => 'Rechazada', 'icon' => 'times-circle'],
    'CANCELADA' => ['badge' => 'secondary', 'text' => 'Cancelada', 'icon' => 'ban']
];
$estado = $estados[$transferencia['estado']] ?? ['badge' => 'secondary', 'text' => $transferencia['estado'], 'icon' => 'question'];
?>

<div class="row">
    <div class="col-md-12">
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-exchange-alt mr-2"></i> Transferencia de Stock
                </h3>
                <div class="card-tools">
                    <?php if ($transferencia['estado'] == 'PENDIENTE'): ?>
                        <button type="button" class="btn btn-sm btn-success" onclick="enviarTransferencia()">
                            <i class="fas fa-paper-plane mr-1"></i> Enviar
                        </button>
                    <?php elseif ($transferencia['estado'] == 'EN_TRANSITO'): ?>
                        <button type="button" class="btn btn-sm btn-primary" onclick="recibirTransferencia()">
                            <i class="fas fa-check mr-1"></i> Recibir
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <!-- Información General -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="info-box">
                            <span class="info-box-icon bg-primary"><i class="fas fa-hashtag"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Número de Transferencia</span>
                                <span class="info-box-number"><?= esc($transferencia['numero_transferencia']) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-box">
                            <span class="info-box-icon bg-<?= $estado['badge'] ?>">
                                <i class="fas fa-<?= $estado['icon'] ?>"></i>
                            </span>
                            <div class="info-box-content">
                                <span class="info-box-text">Estado</span>
                                <span class="info-box-number">
                                    <span class="badge badge-<?= $estado['badge'] ?> badge-lg">
                                        <?= $estado['text'] ?>
                                    </span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card card-outline card-info">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-arrow-up mr-1"></i> Sucursal Origen</h3>
                            </div>
                            <div class="card-body">
                                <p><strong>Nombre:</strong> <?= esc($sucursal_origen['nombre'] ?? '-') ?></p>
                                <p><strong>Dirección:</strong> <?= esc($sucursal_origen['direccion'] ?? '-') ?></p>
                                <p><strong>Ciudad:</strong> <?= esc($sucursal_origen['ciudad'] ?? '-') ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card card-outline card-success">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-arrow-down mr-1"></i> Sucursal Destino</h3>
                            </div>
                            <div class="card-body">
                                <p><strong>Nombre:</strong> <?= esc($sucursal_destino['nombre'] ?? '-') ?></p>
                                <p><strong>Dirección:</strong> <?= esc($sucursal_destino['direccion'] ?? '-') ?></p>
                                <p><strong>Ciudad:</strong> <?= esc($sucursal_destino['ciudad'] ?? '-') ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fechas -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <p><strong>Fecha Solicitud:</strong><br>
                        <?= date('d/m/Y H:i', strtotime($transferencia['fecha_solicitud'])) ?></p>
                    </div>
                    <?php if ($transferencia['fecha_envio']): ?>
                    <div class="col-md-4">
                        <p><strong>Fecha Envío:</strong><br>
                        <?= date('d/m/Y H:i', strtotime($transferencia['fecha_envio'])) ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if ($transferencia['fecha_recepcion']): ?>
                    <div class="col-md-4">
                        <p><strong>Fecha Recepción:</strong><br>
                        <?= date('d/m/Y H:i', strtotime($transferencia['fecha_recepcion'])) ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($transferencia['observaciones']): ?>
                <div class="alert alert-info">
                    <strong>Observaciones:</strong><br>
                    <?= nl2br(esc($transferencia['observaciones'])) ?>
                </div>
                <?php endif; ?>

                <!-- Detalles de Productos -->
                <div class="card card-outline card-secondary">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-boxes mr-1"></i> Productos Transferidos</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Producto</th>
                                        <th class="text-right">Cantidad Solicitada</th>
                                        <th class="text-right">Cantidad Enviada</th>
                                        <th class="text-right">Cantidad Recibida</th>
                                        <th class="text-right">Cantidad Pendiente</th>
                                        <th>Lote</th>
                                        <th>Observaciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($detalles as $detalle): 
                                        $producto = $detalle['producto'];
                                        $diferencia = $detalle['cantidad_solicitada'] - ($detalle['cantidad_recibida'] ?? 0);
                                    ?>
                                        <tr>
                                            <td><code><?= esc($producto['codigo'] ?? '-') ?></code></td>
                                            <td><strong><?= esc($producto['nombre'] ?? 'N/A') ?></strong></td>
                                            <td class="text-right"><?= number_format($detalle['cantidad_solicitada'], 0, ',', '.') ?></td>
                                            <td class="text-right">
                                                <?= $detalle['cantidad_enviada'] ? number_format($detalle['cantidad_enviada'], 0, ',', '.') : '-' ?>
                                            </td>
                                            <td class="text-right">
                                                <?php if ($detalle['cantidad_recibida']): ?>
                                                    <span class="text-success">
                                                        <?= number_format($detalle['cantidad_recibida'], 0, ',', '.') ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-right">
                                                <?php if ($diferencia > 0): ?>
                                                    <span class="badge badge-warning"><?= number_format($diferencia, 0, ',', '.') ?></span>
                                                <?php else: ?>
                                                    <span class="badge badge-success">0</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= esc($detalle['lote'] ?? '-') ?></td>
                                            <td><?= esc($detalle['observaciones'] ?? '-') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Recepción -->
<?php if ($transferencia['estado'] == 'EN_TRANSITO'): ?>
<div class="modal fade" id="modalRecibir" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title text-white">
                    <i class="fas fa-check mr-2"></i> Recibir Transferencia
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="formRecibir">
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Solicitada</th>
                                    <th>Enviada</th>
                                    <th>Recibida</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($detalles as $detalle): ?>
                                    <tr>
                                        <td><?= esc($detalle['producto']['nombre'] ?? 'N/A') ?></td>
                                        <td><?= number_format($detalle['cantidad_solicitada'], 0, ',', '.') ?></td>
                                        <td><?= number_format($detalle['cantidad_enviada'] ?? 0, 0, ',', '.') ?></td>
                                        <td>
                                            <input type="hidden" name="productos[<?= $detalle['producto_id'] ?>][id]" 
                                                   value="<?= $detalle['producto_id'] ?>">
                                            <input type="number" 
                                                   name="productos[<?= $detalle['producto_id'] ?>][cantidad_recibida]" 
                                                   class="form-control form-control-sm" 
                                                   value="<?= $detalle['cantidad_enviada'] ?? $detalle['cantidad_solicitada'] ?>"
                                                   min="0" 
                                                   max="<?= $detalle['cantidad_enviada'] ?? $detalle['cantidad_solicitada'] ?>"
                                                   step="1" required>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check mr-1"></i> Confirmar Recepción
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
function enviarTransferencia() {
    Swal.fire({
        title: '¿Enviar Transferencia?',
        text: 'Esta acción descontará el stock de la sucursal origen',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, Enviar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '<?= base_url('inventario/transferencias/enviar/' . $transferencia['id']) ?>',
                type: 'POST',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Éxito!',
                            text: response.message || 'Transferencia enviada exitosamente',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', response.message || 'Error al enviar la transferencia', 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Error al procesar la solicitud', 'error');
                }
            });
        }
    });
}

function recibirTransferencia() {
    $('#modalRecibir').modal('show');
}

$('#formRecibir').on('submit', function(e) {
    e.preventDefault();
    
    const productos = [];
    $(this).find('input[type="number"]').each(function() {
        const productoId = $(this).siblings('input[type="hidden"]').val();
        productos.push({
            id: productoId,
            cantidad_recibida: parseInt($(this).val(), 10) || 0
        });
    });
    
    $.ajax({
        url: '<?= base_url('inventario/transferencias/recibir/' . $transferencia['id']) ?>',
        type: 'POST',
        data: {
            productos: JSON.stringify(productos)
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: response.message || 'Transferencia recibida exitosamente',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire('Error', response.message || 'Error al recibir la transferencia', 'error');
            }
        },
        error: function() {
            Swal.fire('Error', 'Error al procesar la solicitud', 'error');
        }
    });
});
</script>
<?= $this->endSection() ?>
