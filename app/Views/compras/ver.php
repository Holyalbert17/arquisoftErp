<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('compras') ?>">Compras</a></li>
<li class="breadcrumb-item active">Ver Compra</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-shopping-cart mr-1"></i>
                    Compra: <?= esc($compra['numero_compra']) ?>
                </h3>
            </div>
            <div class="card-body">
                <!-- Información de la Compra -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($compra['fecha_compra'])) ?><br>
                        <strong>Tipo:</strong> <?= esc($compra['tipo_compra']) ?><br>
                        <strong>Estado:</strong> 
                        <?php
                        $estadoColors = [
                            'PAGADA' => 'success',
                            'RECIBIDA' => 'info',
                            'PARCIALMENTE_RECIBIDA' => 'warning',
                            'PENDIENTE' => 'secondary',
                            'ANULADA' => 'danger'
                        ];
                        $color = $estadoColors[$compra['estado']] ?? 'secondary';
                        ?>
                        <span class="badge badge-<?= $color ?>">
                            <?= esc($compra['estado']) ?>
                        </span>
                    </div>
                    <div class="col-md-6">
                        <?php if ($compra['numero_factura_proveedor']): ?>
                            <strong>Factura N°:</strong> <?= esc($compra['numero_factura_proveedor']) ?><br>
                        <?php endif; ?>
                        <?php if ($compra['timbrado_proveedor']): ?>
                            <strong>Timbrado:</strong> <?= esc($compra['timbrado_proveedor']) ?><br>
                        <?php endif; ?>
                        <?php if ($proveedor): ?>
                            <strong>Proveedor:</strong> <?= esc($proveedor['razon_social']) ?><br>
                        <?php endif; ?>
                        <?php if ($sucursal): ?>
                            <strong>Sucursal:</strong> <?= esc($sucursal['nombre']) ?><br>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Detalles -->
                <h5 class="mb-3">Detalle de Productos</h5>
                <?php 
                // formatGs() está disponible como helper global
                
                // Función para formatear cantidad (sin decimales si es entero)
                $formatCantidad = function($cantidad) {
                    $cantidad = (float)$cantidad;
                    if ($cantidad == floor($cantidad)) {
                        return number_format($cantidad, 0, ',', '.');
                    }
                    return number_format($cantidad, 2, ',', '.');
                };
                ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Precio Unit.</th>
                            <th>Descuento</th>
                            <th>IVA</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detalles as $detalle): ?>
                            <tr>
                                <td><?= esc($detalle['producto']['nombre'] ?? $detalle['descripcion'] ?? 'Producto') ?></td>
                                <td><?= $formatCantidad($detalle['cantidad_recibida'] ?? $detalle['cantidad_solicitada'] ?? 0) ?></td>
                                <td class="currency"><?= formatGs($detalle['precio_unitario'] ?? 0) ?></td>
                                <td class="currency"><?= formatGs($detalle['descuento_monto'] ?? 0) ?></td>
                                <td class="currency"><?= formatGs($detalle['monto_iva'] ?? 0) ?></td>
                                <td class="currency"><strong><?= formatGs($detalle['total'] ?? 0) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" class="text-right"><strong>Subtotal:</strong></td>
                            <td class="currency"><strong><?= formatGs($compra['subtotal'] ?? 0) ?></strong></td>
                        </tr>
                        <?php if (($compra['descuento_monto'] ?? 0) > 0): ?>
                            <tr>
                                <td colspan="5" class="text-right"><strong>Descuento:</strong></td>
                                <td class="currency"><strong><?= formatGs($compra['descuento_monto']) ?></strong></td>
                            </tr>
                        <?php endif; ?>
                        <?php if (($compra['total_iva'] ?? 0) > 0): ?>
                            <tr>
                                <td colspan="5" class="text-right"><strong>IVA:</strong></td>
                                <td class="currency"><strong><?= formatGs($compra['total_iva']) ?></strong></td>
                            </tr>
                        <?php endif; ?>
                        <?php if (($compra['otros_gastos'] ?? 0) > 0): ?>
                            <tr>
                                <td colspan="5" class="text-right"><strong>Otros Gastos:</strong></td>
                                <td class="currency"><strong><?= formatGs($compra['otros_gastos']) ?></strong></td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <td colspan="5" class="text-right"><h4><strong>TOTAL:</strong></h4></td>
                            <td class="currency"><h4><strong><?= formatGs($compra['total'] ?? 0) ?></strong></h4></td>
                        </tr>
                    </tfoot>
                </table>

                <!-- Información adicional -->
                <?php if ($compra['observaciones']): ?>
                    <div class="mt-4">
                        <h5>Observaciones</h5>
                        <p><?= nl2br(esc($compra['observaciones'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Información Adicional</h3>
            </div>
            <div class="card-body">
                <?php if ($compra['condicion_pago']): ?>
                    <p><strong>Condición de Pago:</strong><br><?= esc($compra['condicion_pago']) ?></p>
                <?php endif; ?>
                <?php if ($compra['fecha_vencimiento']): ?>
                    <p><strong>Fecha de Vencimiento:</strong><br><?= date('d/m/Y', strtotime($compra['fecha_vencimiento'])) ?></p>
                <?php endif; ?>
                <?php if ($compra['monto_pagado'] ?? 0 > 0): ?>
                    <p><strong>Monto Pagado:</strong><br><?= formatGs($compra['monto_pagado']) ?></p>
                <?php endif; ?>
                <?php if ($compra['monto_pendiente'] ?? 0 > 0): ?>
                    <p><strong>Monto Pendiente:</strong><br><?= formatGs($compra['monto_pendiente']) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
