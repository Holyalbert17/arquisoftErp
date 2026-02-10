<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('inventario') ?>">Inventario</a></li>
<li class="breadcrumb-item"><a href="<?= base_url('inventario/ajustes') ?>">Ajustes</a></li>
<li class="breadcrumb-item active">Ver Ajuste</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

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

<div class="row">
    <div class="col-md-12">
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-edit mr-2"></i> Ajuste de Inventario
                </h3>
                <div class="card-tools">
                    <?php if ($ajuste['estado'] == 'PENDIENTE'): ?>
                        <button type="button" class="btn btn-sm btn-success" onclick="aprobarAjuste()">
                            <i class="fas fa-check mr-1"></i> Aprobar y Procesar
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <!-- Información General -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="info-box">
                            <span class="info-box-icon bg-primary"><i class="fas fa-hashtag"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Número de Ajuste</span>
                                <span class="info-box-number"><?= esc($ajuste['numero_ajuste']) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-box">
                            <span class="info-box-icon bg-<?= $tipo['badge'] ?>">
                                <i class="fas fa-<?= $tipo['icon'] ?>"></i>
                            </span>
                            <div class="info-box-content">
                                <span class="info-box-text">Tipo de Ajuste</span>
                                <span class="info-box-number">
                                    <span class="badge badge-<?= $tipo['badge'] ?> badge-lg">
                                        <?= $tipo['text'] ?>
                                    </span>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
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
                    <div class="col-md-3">
                        <div class="info-box">
                            <span class="info-box-icon bg-info"><i class="fas fa-store"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Sucursal</span>
                                <span class="info-box-number"><?= esc($sucursal['nombre'] ?? '-') ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información del Ajuste -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card card-outline card-secondary">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-info-circle mr-1"></i> Información</h3>
                            </div>
                            <div class="card-body">
                                <p><strong>Motivo:</strong><br>
                                <?= nl2br(esc($ajuste['motivo'])) ?></p>
                                <?php if ($ajuste['observaciones']): ?>
                                    <p><strong>Observaciones:</strong><br>
                                    <?= nl2br(esc($ajuste['observaciones'])) ?></p>
                                <?php endif; ?>
                                <p><strong>Fecha Solicitud:</strong><br>
                                <?= date('d/m/Y H:i', strtotime($ajuste['fecha_solicitud'])) ?></p>
                                <?php if ($ajuste['fecha_aprobacion']): ?>
                                    <p><strong>Fecha Aprobación:</strong><br>
                                    <?= date('d/m/Y H:i', strtotime($ajuste['fecha_aprobacion'])) ?></p>
                                <?php endif; ?>
                                <?php if ($ajuste['fecha_procesamiento']): ?>
                                    <p><strong>Fecha Procesamiento:</strong><br>
                                    <?= date('d/m/Y H:i', strtotime($ajuste['fecha_procesamiento'])) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card card-outline card-info">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-user mr-1"></i> Usuarios</h3>
                            </div>
                            <div class="card-body">
                                <?php if ($ajuste['usuario_solicita']): ?>
                                    <?php
                                    $usuarioModel = new \App\Models\UsuarioModel();
                                    $usuarioSolicita = $usuarioModel->find($ajuste['usuario_solicita']);
                                    ?>
                                    <p><strong>Solicitado por:</strong><br>
                                    <?= $usuarioSolicita ? esc($usuarioSolicita['nombre'] . ' ' . $usuarioSolicita['apellido']) : '-' ?></p>
                                <?php endif; ?>
                                <?php if ($ajuste['usuario_aprueba']): ?>
                                    <?php
                                    $usuarioAprueba = $usuarioModel->find($ajuste['usuario_aprueba']);
                                    ?>
                                    <p><strong>Aprobado por:</strong><br>
                                    <?= $usuarioAprueba ? esc($usuarioAprueba['nombre'] . ' ' . $usuarioAprueba['apellido']) : '-' ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detalles de Productos -->
                <div class="card card-outline card-secondary">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-boxes mr-1"></i> Productos Ajustados</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Producto</th>
                                        <th class="text-right">Cantidad Existente</th>
                                        <th class="text-right">Cantidad Nueva</th>
                                        <th class="text-right">Diferencia</th>
                                        <th class="text-right">Costo Unitario</th>
                                        <th>Observaciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $totalDiferencia = 0;
                                    foreach ($detalles as $detalle): 
                                        $producto = $detalle['producto'];
                                        $diferencia = $detalle['diferencia'];
                                        $totalDiferencia += abs($diferencia);
                                        $esEntrada = $diferencia > 0;
                                    ?>
                                        <tr class="<?= $esEntrada ? 'table-success' : 'table-danger' ?>">
                                            <td><code><?= esc($producto['codigo'] ?? '-') ?></code></td>
                                            <td><strong><?= esc($producto['nombre'] ?? 'N/A') ?></strong></td>
                                            <td class="text-right"><?= number_format($detalle['cantidad_existente'], 2, ',', '.') ?></td>
                                            <td class="text-right">
                                                <strong><?= number_format($detalle['cantidad_nueva'], 2, ',', '.') ?></strong>
                                            </td>
                                            <td class="text-right">
                                                <?php if ($esEntrada): ?>
                                                    <span class="badge badge-success">
                                                        <i class="fas fa-arrow-up"></i> +<?= number_format($diferencia, 2, ',', '.') ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">
                                                        <i class="fas fa-arrow-down"></i> <?= number_format($diferencia, 2, ',', '.') ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-right">
                                                <?= number_format($detalle['costo_unitario'] ?? 0, 0, ',', '.') ?> Gs.
                                            </td>
                                            <td><?= esc($detalle['observaciones'] ?? '-') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="4" class="text-right">Total Movimientos:</th>
                                        <th class="text-right"><?= number_format($totalDiferencia, 2, ',', '.') ?></th>
                                        <th colspan="2"></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
function aprobarAjuste() {
    Swal.fire({
        title: '¿Aprobar y Procesar Ajuste?',
        text: 'Esta acción actualizará el stock y registrará los movimientos. Esta acción no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, Aprobar y Procesar',
        cancelButtonText: 'Cancelar',
        input: 'textarea',
        inputPlaceholder: 'Observaciones adicionales (opcional)',
        inputAttributes: {
            'aria-label': 'Observaciones adicionales'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '<?= base_url('inventario/ajustes/aprobar/' . $ajuste['id']) ?>',
                type: 'POST',
                data: {
                    observaciones: result.value || ''
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Éxito!',
                            text: response.message || 'Ajuste procesado exitosamente',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', response.message || 'Error al procesar el ajuste', 'error');
                    }
                },
                error: function(xhr) {
                    let errorMsg = 'Error al procesar la solicitud';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    Swal.fire('Error', errorMsg, 'error');
                }
            });
        }
    });
}
</script>
<?= $this->endSection() ?>
