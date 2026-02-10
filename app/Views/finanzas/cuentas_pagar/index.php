<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('finanzas') ?>">Finanzas</a></li>
<li class="breadcrumb-item active">Cuentas por Pagar</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-file-invoice-dollar mr-1"></i>
                    Cuentas por Pagar
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-sm btn-info" id="btnSincronizar"
                        title="Sincronizar compras pendientes">
                        <i class="fas fa-sync"></i> Sincronizar Compras
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php
                // Calcular totales
                $totalPendiente = 0;
                $totalPagado = 0;
                $totalGeneral = 0;
                foreach ($cuentas as $cuenta) {
                    $totalPendiente += (float) ($cuenta['monto_pendiente'] ?? 0);
                    $totalPagado += (float) ($cuenta['monto_pagado'] ?? 0);
                    $totalGeneral += (float) ($cuenta['monto_total'] ?? 0);
                }
                ?>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="info-box">
                            <span class="info-box-icon bg-info"><i class="fas fa-dollar-sign"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Total General</span>
                                <span class="info-box-number"><?= number_format($totalGeneral, 0, ',', '.') ?>
                                    Gs.</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-box">
                            <span class="info-box-icon bg-success"><i class="fas fa-check-circle"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Total Pagado</span>
                                <span class="info-box-number"><?= number_format($totalPagado, 0, ',', '.') ?> Gs.</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-box">
                            <span class="info-box-icon bg-warning"><i class="fas fa-exclamation-triangle"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Total Pendiente</span>
                                <span class="info-box-number"><?= number_format($totalPendiente, 0, ',', '.') ?>
                                    Gs.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <table class="table table-bordered table-striped auto-datatable">
                    <thead>
                        <tr>
                            <th>Proveedor</th>
                            <th>Documento</th>
                            <th>Tipo</th>
                            <th>Fecha Emisión</th>
                            <th>Fecha Vencimiento</th>
                            <th>Monto Total</th>
                            <th>Pagado</th>
                            <th>Pendiente</th>
                            <th>Días Vencido</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cuentas as $cuenta): ?>
                            <?php
                            $diasVencido = $cuenta['dias_vencido'] ?? 0;
                            $rowClass = $diasVencido > 0 ? 'table-danger' : '';
                            $proveedorNombre = $cuenta['proveedor'] ?
                                ($cuenta['proveedor']['razon_social'] ?? $cuenta['proveedor']['nombre_comercial'] ?? 'N/A') :
                                'N/A';
                            ?>
                            <tr class="<?= $rowClass ?>">
                                <td><?= esc($proveedorNombre) ?></td>
                                <td><strong><?= esc($cuenta['numero_documento'] ?? 'N/A') ?></strong></td>
                                <td>
                                    <span class="badge badge-secondary">
                                        <?= esc($cuenta['tipo_documento'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($cuenta['fecha_emision']): ?>
                                        <?= date('d/m/Y', strtotime($cuenta['fecha_emision'])) ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($cuenta['fecha_vencimiento']): ?>
                                        <?= date('d/m/Y', strtotime($cuenta['fecha_vencimiento'])) ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right currency">
                                    <?= number_format((float) ($cuenta['monto_total'] ?? 0), 0, ',', '.') ?> Gs.
                                </td>
                                <td class="text-right currency text-success">
                                    <?= number_format((float) ($cuenta['monto_pagado'] ?? 0), 0, ',', '.') ?> Gs.
                                </td>
                                <td class="text-right currency text-warning">
                                    <strong><?= number_format((float) ($cuenta['monto_pendiente'] ?? 0), 0, ',', '.') ?>
                                        Gs.</strong>
                                </td>
                                <td class="text-center">
                                    <?php if ($diasVencido > 0): ?>
                                        <span class="badge badge-danger"><?= $diasVencido ?> días</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">Al día</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $estado = $cuenta['estado'] ?? 'PENDIENTE';
                                    $estadoColors = [
                                        'PAGADO' => 'success',
                                        'PENDIENTE' => 'warning',
                                        'VENCIDO' => 'danger',
                                        'PARCIAL' => 'info'
                                    ];
                                    $color = $estadoColors[$estado] ?? 'secondary';
                                    ?>
                                    <span class="badge badge-<?= $color ?>"><?= esc($estado) ?></span>
                                </td>
                                <td>
                                    <?php if (($cuenta['monto_pendiente'] ?? 0) > 0): ?>
                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                            data-bs-target="#modalPago<?= $cuenta['id'] ?>" title="Registrar Pago">
                                            <i class="fas fa-money-bill-wave"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modales para registrar pagos -->
<?php foreach ($cuentas as $cuenta): ?>
    <?php if (($cuenta['monto_pendiente'] ?? 0) > 0): ?>
        <?php
        $proveedorNombre = $cuenta['proveedor'] ?
            ($cuenta['proveedor']['razon_social'] ?? $cuenta['proveedor']['nombre_comercial'] ?? 'N/A') :
            'N/A';
        ?>
        <div class="modal fade" id="modalPago<?= $cuenta['id'] ?>" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Registrar Pago</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <form id="formPago<?= $cuenta['id'] ?>">
                        <div class="modal-body">
                            <input type="hidden" name="cuenta_id" value="<?= $cuenta['id'] ?>">

                            <div class="form-group">
                                <label>Proveedor</label>
                                <input type="text" class="form-control" value="<?= esc($proveedorNombre) ?>" readonly>
                            </div>

                            <div class="form-group">
                                <label>Documento</label>
                                <input type="text" class="form-control" value="<?= esc($cuenta['numero_documento'] ?? '') ?>"
                                    readonly>
                            </div>

                            <div class="form-group">
                                <label for="caja_id<?= $cuenta['id'] ?>">Caja de Origen <span
                                        class="text-danger">*</span></label>
                                <select class="form-control" id="caja_id<?= $cuenta['id'] ?>" name="caja_id" required>
                                    <option value="">Seleccione una caja...</option>
                                    <?php foreach ($cajasAbiertas as $caja): ?>
                                        <option value="<?= $caja['id'] ?>">
                                            <?= esc($caja['nombre']) ?> (<?= esc($caja['usuario_apertura'] ?? 'Usuario Actual') ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($cajasAbiertas)): ?>
                                    <small class="text-danger">
                                        No hay cajas abiertas. Debe abrir una caja para realizar pagos.
                                    </small>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label>Monto Pendiente</label>
                                <input type="text" class="form-control"
                                    value="<?= number_format((float) ($cuenta['monto_pendiente'] ?? 0), 0, ',', '.') ?> Gs."
                                    readonly>
                            </div>

                            <div class="form-group">
                                <label for="monto<?= $cuenta['id'] ?>">Monto a Pagar <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="monto<?= $cuenta['id'] ?>" name="monto"
                                    step="0.01" min="0.01" max="<?= $cuenta['monto_pendiente'] ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="forma_pago<?= $cuenta['id'] ?>">Forma de Pago <span
                                        class="text-danger">*</span></label>
                                <select class="form-control" id="forma_pago<?= $cuenta['id'] ?>" name="forma_pago" required>
                                    <option value="">Seleccione...</option>
                                    <option value="EFECTIVO">Efectivo</option>
                                    <option value="TARJETA_DEBITO">Tarjeta Débito</option>
                                    <option value="TARJETA_CREDITO">Tarjeta Crédito</option>
                                    <option value="TRANSFERENCIA">Transferencia</option>
                                    <option value="CHEQUE">Cheque</option>
                                    <option value="OTRO">Otro</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="referencia<?= $cuenta['id'] ?>">Referencia</label>
                                <input type="text" class="form-control" id="referencia<?= $cuenta['id'] ?>" name="referencia"
                                    placeholder="Número de transacción, cheque, etc.">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Registrar Pago</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    $(document).ready(function () {
        // DataTable eliminada de aquí para usar auto-datatable global

        // Botón de sincronizar compras
        $('#btnSincronizar').on('click', function () {
            const btn = $(this);
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Sincronizando...');

            $.ajax({
                url: '<?= base_url('finanzas/cuentas-pagar/sincronizar') ?>',
                method: 'POST',
                headers: {
                    '<?= csrf_header() ?>': $('meta[name="<?= csrf_header() ?>"]').attr('content'),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                data: {
                    '<?= csrf_token() ?>': $('meta[name="<?= csrf_header() ?>"]').attr('content')
                },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Éxito',
                            text: response.message || 'Sincronización completada',
                            showConfirmButton: false,
                            timer: 1500
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'Error al sincronizar'
                        });
                        btn.prop('disabled', false).html('<i class="fas fa-sync"></i> Sincronizar Compras');
                    }
                },
                error: function (xhr) {
                    let mensaje = 'Error al procesar la solicitud';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        mensaje = xhr.responseJSON.message;
                    }
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: mensaje
                    });
                    btn.prop('disabled', false).html('<i class="fas fa-sync"></i> Sincronizar Compras');
                }
            });
        });

        // Manejar envío de formularios de pago
        <?php foreach ($cuentas as $cuenta): ?>
            <?php if (($cuenta['monto_pendiente'] ?? 0) > 0): ?>
                $('#formPago<?= $cuenta['id'] ?>').on('submit', function (e) {
                    e.preventDefault();

                    const formData = $(this).serialize();
                    const monto = parseFloat($('#monto<?= $cuenta['id'] ?>').val());
                    const montoPendiente = <?= (float) ($cuenta['monto_pendiente'] ?? 0) ?>;

                    if (monto <= 0) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'El monto debe ser mayor a cero'
                        });
                        return;
                    }

                    if (monto > montoPendiente) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'El monto excede el saldo pendiente'
                        });
                        return;
                    }

                    $.ajax({
                        url: '<?= base_url('finanzas/cuentas-pagar/registrar-pago') ?>',
                        method: 'POST',
                        data: formData,
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Éxito',
                                    text: response.message || 'Pago registrado exitosamente',
                                    showConfirmButton: false,
                                    timer: 1500
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: response.message || 'Error al registrar el pago'
                                });
                            }
                        },
                        error: function (xhr) {
                            let mensaje = 'Error al procesar la solicitud';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                mensaje = xhr.responseJSON.message;
                            }
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: mensaje
                            });
                        }
                    });
                });
            <?php endif; ?>
        <?php endforeach; ?>
    });
</script>
<?= $this->endSection() ?>