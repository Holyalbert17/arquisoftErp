<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('ventas') ?>">Ventas</a></li>
<li class="breadcrumb-item"><a href="<?= base_url('ventas/cotizaciones') ?>">Cotizaciones</a></li>
<li class="breadcrumb-item active">Ver Cotización</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-file-invoice-dollar mr-1"></i>
                    Cotización: <?= esc($cotizacion['numero_cotizacion']) ?>
                </h3>
                <div class="card-tools">
                    <?php if ($cotizacion['estado'] == 'PENDIENTE' || $cotizacion['estado'] == 'APROBADA'): ?>
                        <button class="btn btn-sm btn-success" id="btnConvertirVenta">
                            <i class="fas fa-shopping-cart"></i> Convertir a Venta
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <!-- Información de la Cotización -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Cliente:</strong> 
                        <?php if ($cotizacion['cliente']): ?>
                            <?php
                            if ($cotizacion['cliente']['tipo_cliente'] == 'PERSONA') {
                                echo esc(trim(($cotizacion['cliente']['nombre'] ?? '') . ' ' . ($cotizacion['cliente']['apellido'] ?? '')));
                            } else {
                                echo esc($cotizacion['cliente']['nombre_fantasia'] ?? $cotizacion['cliente']['razon_social'] ?? '');
                            }
                            ?>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                        <br>
                        <strong>Fecha Emisión:</strong> <?= date('d/m/Y H:i', strtotime($cotizacion['fecha_emision'])) ?><br>
                        <strong>Fecha Vencimiento:</strong> <?= date('d/m/Y', strtotime($cotizacion['fecha_vencimiento'])) ?><br>
                        <strong>Estado:</strong> 
                        <span class="badge badge-<?= $cotizacion['estado'] == 'APROBADA' ? 'success' : ($cotizacion['estado'] == 'VENCIDA' ? 'secondary' : ($cotizacion['estado'] == 'RECHAZADA' ? 'danger' : 'warning')) ?>">
                            <?= esc($cotizacion['estado']) ?>
                        </span>
                    </div>
                    <div class="col-md-6">
                        <?php if ($cotizacion['lista_precio_id']): ?>
                            <strong>Lista de Precios:</strong> 
                            <?php
                            $listaPrecioModel = new \App\Models\ListaPrecioModel();
                            $lista = $listaPrecioModel->find($cotizacion['lista_precio_id']);
                            echo $lista ? esc($lista['nombre']) : 'N/A';
                            ?>
                            <br>
                        <?php endif; ?>
                        <?php if ($cotizacion['venta_id']): ?>
                            <strong>Venta Generada:</strong> 
                            <a href="<?= base_url('ventas/ver/' . $cotizacion['venta_id']) ?>">
                                Ver Venta
                            </a>
                            <br>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Detalles -->
                <h5 class="mb-3">Detalle de Productos</h5>
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
                                <td><?= esc($detalle['descripcion']) ?></td>
                                <td><?= number_format((float)$detalle['cantidad'], 2, ',', '.') ?></td>
                                <td class="currency"><?= formatGs($detalle['precio_unitario']) ?></td>
                                <td class="currency"><?= number_format((float)$detalle['descuento_monto'], 0, ',', '.') ?> Gs.</td>
                                <td class="currency"><?= number_format((float)$detalle['monto_iva'], 0, ',', '.') ?> Gs.</td>
                                <td class="currency"><strong><?= number_format((float)$detalle['total'], 0, ',', '.') ?> Gs.</strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" class="text-right"><strong>Subtotal:</strong></td>
                            <td class="currency"><strong><?= number_format((float)$cotizacion['subtotal'], 0, ',', '.') ?> Gs.</strong></td>
                        </tr>
                        <?php if (($cotizacion['descuento_monto'] ?? 0) > 0): ?>
                            <tr>
                                <td colspan="5" class="text-right"><strong>Descuento:</strong></td>
                                <td class="currency"><strong><?= number_format((float)$cotizacion['descuento_monto'], 0, ',', '.') ?> Gs.</strong></td>
                            </tr>
                        <?php endif; ?>
                        <?php if (($cotizacion['iva_10'] ?? 0) > 0): ?>
                            <tr>
                                <td colspan="5" class="text-right"><strong>IVA 10%:</strong></td>
                                <td class="currency"><strong><?= number_format((float)$cotizacion['iva_10'], 0, ',', '.') ?> Gs.</strong></td>
                            </tr>
                        <?php endif; ?>
                        <?php if (($cotizacion['iva_5'] ?? 0) > 0): ?>
                            <tr>
                                <td colspan="5" class="text-right"><strong>IVA 5%:</strong></td>
                                <td class="currency"><strong><?= number_format((float)$cotizacion['iva_5'], 0, ',', '.') ?> Gs.</strong></td>
                            </tr>
                        <?php endif; ?>
                        <tr class="table-primary">
                            <td colspan="5" class="text-right"><h4><strong>TOTAL:</strong></h4></td>
                            <td class="currency"><h4><strong><?= number_format((float)$cotizacion['total'], 0, ',', '.') ?> Gs.</strong></h4></td>
                        </tr>
                    </tfoot>
                </table>

                <?php if ($cotizacion['observaciones']): ?>
                    <div class="mt-3">
                        <strong>Observaciones:</strong>
                        <p><?= nl2br(esc($cotizacion['observaciones'])) ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($cotizacion['terminos_condiciones']): ?>
                    <div class="mt-3">
                        <strong>Términos y Condiciones:</strong>
                        <p><?= nl2br(esc($cotizacion['terminos_condiciones'])) ?></p>
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
                <?php if ($cotizacion['estado'] == 'PENDIENTE' || $cotizacion['estado'] == 'APROBADA'): ?>
                    <button class="btn btn-block btn-success mb-2" id="btnConvertirVenta2">
                        <i class="fas fa-shopping-cart"></i> Convertir a Venta
                    </button>
                <?php endif; ?>
                <a href="<?= base_url('ventas/cotizaciones') ?>" class="btn btn-block btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </div>
        </div>
        
        <?php if ($cotizacion['cliente']): ?>
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">Información del Cliente</h3>
                </div>
                <div class="card-body">
                    <p><strong>Nombre:</strong><br>
                    <?php
                    if ($cotizacion['cliente']['tipo_cliente'] == 'PERSONA') {
                        echo esc(trim(($cotizacion['cliente']['nombre'] ?? '') . ' ' . ($cotizacion['cliente']['apellido'] ?? '')));
                    } else {
                        echo esc($cotizacion['cliente']['nombre_fantasia'] ?? $cotizacion['cliente']['razon_social'] ?? '');
                    }
                    ?>
                    </p>
                    <?php if ($cotizacion['cliente']['telefono'] || $cotizacion['cliente']['celular']): ?>
                        <p><strong>Teléfono:</strong><br>
                        <?= esc($cotizacion['cliente']['celular'] ?? $cotizacion['cliente']['telefono'] ?? '-') ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($cotizacion['cliente']['email']): ?>
                        <p><strong>Email:</strong><br>
                        <?= esc($cotizacion['cliente']['email']) ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(document).ready(function() {
    $('#btnConvertirVenta, #btnConvertirVenta2').on('click', function() {
        Swal.fire({
            title: 'Convertir a Venta',
            text: '¿Desea convertir esta cotización en una venta?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, convertir',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '<?= base_url("ventas/pos?cotizacion=" . $cotizacion['id']) ?>';
            }
        });
    });
});
</script>
<?= $this->endSection() ?>
