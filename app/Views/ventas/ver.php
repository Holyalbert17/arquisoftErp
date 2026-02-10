<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('ventas') ?>">Ventas</a></li>
<li class="breadcrumb-item active">Ver Venta</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-shopping-cart mr-1"></i>
                    Venta: <?= esc($venta['numero_venta']) ?>
                </h3>
                <div class="card-tools">
                    <?php if ($venta['tipo_comprobante'] == 'FACTURA'): ?>
                        <a href="<?= base_url('ventas/imprimirFactura/' . $venta['id']) ?>" class="btn btn-sm btn-primary"
                            target="_blank">
                            <i class="fas fa-file-invoice"></i> Imprimir Factura
                        </a>
                    <?php else: ?>
                        <a href="<?= base_url('ventas/imprimir/' . $venta['id']) ?>" class="btn btn-sm btn-secondary"
                            target="_blank">
                            <i class="fas fa-print"></i> Imprimir Ticket
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <!-- Información de la Venta -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($venta['fecha_venta'])) ?><br>
                        <strong>Tipo:</strong> <?= esc($venta['tipo_comprobante']) ?><br>
                        <strong>Estado:</strong>
                        <span
                            class="badge badge-<?= $venta['estado'] == 'COMPLETADA' ? 'success' : ($venta['estado'] == 'ANULADA' ? 'danger' : 'warning') ?>">
                            <?= esc($venta['estado']) ?>
                        </span><br>
                        <?php if ($venta['usuario']): ?>
                            <strong>Realizado por:</strong>
                            <?= esc(($venta['usuario']['nombre'] ?? '') . ' ' . ($venta['usuario']['apellido'] ?? '')) ?><br>
                        <?php endif; ?>
                        <?php if ($venta['vendedor']): ?>
                            <strong>Vendedor:</strong>
                            <?= esc(($venta['vendedor']['nombre'] ?? '') . ' ' . ($venta['vendedor']['apellido'] ?? '')) ?><br>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <?php if ($venta['numero_factura']): ?>
                            <strong>Factura N°:</strong> <?= esc($venta['numero_factura']) ?><br>
                        <?php endif; ?>
                        <?php if ($venta['timbrado']): ?>
                            <strong>Timbrado:</strong> <?= esc($venta['timbrado']) ?><br>
                        <?php endif; ?>
                        <?php if ($venta['cliente']): ?>
                            <strong>Cliente:</strong>
                            <?= esc($venta['cliente']['nombre'] ?? $venta['cliente']['razon_social']) ?><br>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Detalles -->
                <h5 class="mb-3">Detalle de Productos</h5>
                <?php
                // formatGs() está disponible como helper global
                ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Precio Unit.</th>
                            <th>Descuento</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Función para formatear cantidad (sin decimales si es entero)
                        $formatCantidad = function ($cantidad) {
                            $cantidad = (float) $cantidad;
                            // Si es un número entero, mostrar sin decimales
                            if ($cantidad == floor($cantidad)) {
                                return number_format($cantidad, 0, ',', '.');
                            }
                            // Si tiene decimales, mostrar con 2 decimales
                            return number_format($cantidad, 2, ',', '.');
                        };
                        foreach ($venta['detalles'] as $detalle): ?>
                            <tr>
                                <td><?= esc($detalle['descripcion'] ?? 'Producto') ?></td>
                                <td><?= $formatCantidad($detalle['cantidad'] ?? 0) ?></td>
                                <td class="currency"><?= formatGs($detalle['precio_unitario'] ?? 0) ?></td>
                                <td class="currency"><?= formatGs($detalle['descuento_total_monto'] ?? 0) ?></td>
                                <td class="currency"><strong><?= formatGs($detalle['total'] ?? 0) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="text-right"><strong>Subtotal:</strong></td>
                            <td class="currency"><strong><?= formatGs($venta['subtotal'] ?? 0) ?></strong></td>
                        </tr>
                        <?php if (($venta['descuento_monto'] ?? 0) > 0): ?>
                            <tr>
                                <td colspan="4" class="text-right"><strong>Descuento:</strong></td>
                                <td class="currency"><strong><?= formatGs($venta['descuento_monto']) ?></strong></td>
                            </tr>
                        <?php endif; ?>
                        <?php if (($venta['iva_10'] ?? 0) > 0): ?>
                            <tr>
                                <td colspan="4" class="text-right"><strong>IVA 10%:</strong></td>
                                <td class="currency"><strong><?= formatGs($venta['iva_10']) ?></strong></td>
                            </tr>
                        <?php endif; ?>
                        <?php if (($venta['iva_5'] ?? 0) > 0): ?>
                            <tr>
                                <td colspan="4" class="text-right"><strong>IVA 5%:</strong></td>
                                <td class="currency"><strong><?= formatGs($venta['iva_5']) ?></strong></td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <td colspan="4" class="text-right">
                                <h4><strong>TOTAL:</strong></h4>
                            </td>
                            <td class="currency">
                                <h4><strong><?= formatGs($venta['subtotal'] ?? 0) ?></strong>
                                </h4>
                            </td>
                        </tr>
                    </tfoot>
                </table>

                <!-- Pagos -->
                <?php if (!empty($venta['pagos'])): ?>
                    <h5 class="mb-3 mt-4">Formas de Pago</h5>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Forma de Pago</th>
                                <th>Monto</th>
                                <th>Referencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($venta['pagos'] as $pago): ?>
                                <tr>
                                    <td><?= esc(str_replace('_', ' ', $pago['forma_pago'])) ?></td>
                                    <td class="currency"><?= formatGs($pago['monto'] ?? 0) ?></td>
                                    <td><?= esc($pago['referencia'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td><strong>Total Pagado:</strong></td>
                                <td class="currency"><strong><?= formatGs($venta['monto_pagado'] ?? 0) ?></strong></td>
                                <td></td>
                            </tr>
                            <?php if (($venta['vuelto'] ?? 0) > 0): ?>
                                <tr>
                                    <td><strong>Vuelto:</strong></td>
                                    <td class="currency"><strong><?= formatGs($venta['vuelto']) ?></strong></td>
                                    <td></td>
                                </tr>
                            <?php endif; ?>
                        </tfoot>
                    </table>
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
                <?php if ($venta['tipo_comprobante'] == 'FACTURA'): ?>
                    <a href="<?= base_url('ventas/imprimirFactura/' . $venta['id']) ?>"
                        class="btn btn-block btn-primary mb-2" target="_blank">
                        <i class="fas fa-file-invoice"></i> Imprimir Factura
                    </a>
                <?php else: ?>
                    <a href="<?= base_url('ventas/imprimir/' . $venta['id']) ?>" class="btn btn-block btn-secondary mb-2"
                        target="_blank">
                        <i class="fas fa-print"></i> Imprimir Ticket
                    </a>
                <?php endif; ?>
                <?php
                // Verificar si se puede anular
                $puedeAnular = false;
                $esAdmin = false;

                if ($venta['estado'] != 'ANULADA' && isset($user) && isset($user['rol_id'])) {
                    // Verificar si es administrador
                    $rolModel = new \App\Models\RolModel();
                    $rol = $rolModel->find($user['rol_id']);
                    if ($rol && $rol['nivel'] <= 2) {
                        $esAdmin = true;
                    }

                    if ($esAdmin) {
                        // Administrador puede anular sin límite de tiempo
                        $puedeAnular = true;
                    } else {
                        // Cajero solo puede anular dentro de 24 horas
                        $fechaVenta = strtotime($venta['fecha_venta']);
                        $fechaActual = time();
                        $diferenciaDias = ($fechaActual - $fechaVenta) / (60 * 60 * 24);
                        $puedeAnular = $diferenciaDias <= 1;
                    }
                }
                ?>
                <?php if ($puedeAnular): ?>
                    <button class="btn btn-block btn-danger btn-anular" data-venta-id="<?= $venta['id'] ?>"
                        data-fecha-venta="<?= $venta['fecha_venta'] ?>" data-es-admin="<?= $esAdmin ? '1' : '0' ?>">
                        <i class="fas fa-ban"></i> Anular y Eliminar Venta
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    $(document).ready(function () {
        $('.btn-anular').on('click', function () {
            const ventaId = $(this).data('venta-id');
            const fechaVenta = $(this).data('fecha-venta');
            const esAdmin = $(this).data('es-admin') == '1';

            // Validar tiempo para cajeros
            if (!esAdmin) {
                const fechaVentaObj = new Date(fechaVenta);
                const fechaActual = new Date();
                const diferenciaMs = fechaActual - fechaVentaObj;
                const diferenciaDias = diferenciaMs / (1000 * 60 * 60 * 24);

                if (diferenciaDias > 1) {
                    Swal.fire({
                        icon: 'error',
                        title: 'No se puede anular',
                        text: 'Los cajeros solo pueden anular ventas dentro de las primeras 24 horas. Contacte a un administrador.',
                        confirmButtonText: 'Aceptar'
                    });
                    return;
                }
            }

            Swal.fire({
                title: '¿Anular y eliminar venta?',
                html: '<p>Esta acción <strong>eliminará permanentemente</strong> la venta y todos sus datos asociados.</p>' +
                    '<p>Se revertirá el stock de los productos.</p>',
                input: 'text',
                inputLabel: 'Motivo de anulación',
                inputPlaceholder: 'Ingrese el motivo...',
                showCancelButton: true,
                confirmButtonText: 'Sí, anular y eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#d33',
                inputValidator: (value) => {
                    if (!value) {
                        return 'Debe ingresar un motivo';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Obtener token CSRF del meta tag o input hidden
                    const csrfToken = $('meta[name="<?= csrf_header() ?>"]').attr('content') ||
                        $('input[name="<?= csrf_token() ?>"]').val() ||
                        '<?= csrf_hash() ?>';

                    $.ajax({
                        url: '<?= base_url("ventas/anular") ?>/' + ventaId,
                        method: 'POST',
                        headers: {
                            '<?= csrf_header() ?>': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        data: {
                            motivo: result.value,
                            '<?= csrf_token() ?>': csrfToken
                        },
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Éxito',
                                    text: response.message,
                                    showConfirmButton: false,
                                    timer: 1500
                                }).then(() => {
                                    window.location.href = '<?= base_url("ventas") ?>';
                                });
                            } else {
                                Swal.fire('Error', response.message, 'error');
                            }
                        },
                        error: function (xhr) {
                            let mensaje = 'Error al anular la venta';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                mensaje = xhr.responseJSON.message;
                            } else if (xhr.status === 403) {
                                mensaje = 'No tiene permisos para realizar esta acción';
                            } else if (xhr.status === 401) {
                                mensaje = 'Sesión expirada. Por favor, recargue la página.';
                            }
                            Swal.fire('Error', mensaje, 'error');
                        }
                    });
                }
            });
        });
    });
</script>
<?= $this->endSection() ?>