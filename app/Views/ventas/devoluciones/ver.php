<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('ventas') ?>">Ventas</a></li>
<li class="breadcrumb-item"><a href="<?= base_url('ventas/devoluciones') ?>">Devoluciones</a></li>
<li class="breadcrumb-item active">Ver Devolución</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-undo mr-1"></i>
                    Nota de Crédito: <?= esc($devolucion['numero_venta']) ?>
                </h3>
                <div class="card-tools">
                    <a href="<?= base_url('ventas/imprimir/' . $devolucion['id']) ?>" 
                       class="btn btn-sm btn-secondary" target="_blank">
                        <i class="fas fa-print"></i> Imprimir
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Información de la Devolución -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($devolucion['fecha_venta'])) ?><br>
                        <strong>Estado:</strong> 
                        <span class="badge badge-success"><?= esc($devolucion['estado']) ?></span>
                    </div>
                    <div class="col-md-6">
                        <?php if ($devolucion['cliente']): ?>
                            <strong>Cliente:</strong> 
                            <?php
                            if ($devolucion['cliente']['tipo_cliente'] == 'PERSONA') {
                                echo esc(trim(($devolucion['cliente']['nombre'] ?? '') . ' ' . ($devolucion['cliente']['apellido'] ?? '')));
                            } else {
                                echo esc($devolucion['cliente']['nombre_fantasia'] ?? $devolucion['cliente']['razon_social'] ?? '');
                            }
                            ?>
                            <br>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Detalles -->
                <h5 class="mb-3">Productos Devueltos</h5>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Precio Unit.</th>
                            <th>IVA</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detalles as $detalle): ?>
                            <tr>
                                <td><?= esc($detalle['descripcion']) ?></td>
                                <td><?= number_format((float)$detalle['cantidad'], 2, ',', '.') ?></td>
                                <td class="currency"><?= formatGs($detalle['precio_unitario']) ?></td>
                                <td class="currency"><?= formatGs($detalle['monto_iva']) ?></td>
                                <td class="currency"><strong><?= number_format(abs((float)$detalle['total']), 0, ',', '.') ?> Gs.</strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="text-right"><strong>Subtotal:</strong></td>
                            <td class="currency"><strong><?= number_format((float)$devolucion['subtotal'], 0, ',', '.') ?> Gs.</strong></td>
                        </tr>
                        <tr>
                            <td colspan="4" class="text-right"><strong>IVA:</strong></td>
                            <td class="currency"><strong><?= number_format((float)$devolucion['total_iva'], 0, ',', '.') ?> Gs.</strong></td>
                        </tr>
                        <tr class="table-danger">
                            <td colspan="4" class="text-right"><h4><strong>TOTAL DEVOLUCIÓN:</strong></h4></td>
                            <td class="currency"><h4><strong><?= number_format((float)$devolucion['total'], 0, ',', '.') ?> Gs.</strong></h4></td>
                        </tr>
                    </tfoot>
                </table>

                <?php if ($devolucion['observaciones']): ?>
                    <div class="mt-3">
                        <strong>Observaciones:</strong>
                        <p><?= nl2br(esc($devolucion['observaciones'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Acciones</h3>
            </div>
            <div class="card-body">
                <a href="<?= base_url('ventas/imprimir/' . $devolucion['id']) ?>" 
                   class="btn btn-block btn-secondary mb-2" target="_blank">
                    <i class="fas fa-print"></i> Imprimir Nota de Crédito
                </a>
                <a href="<?= base_url('ventas/devoluciones') ?>" class="btn btn-block btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
