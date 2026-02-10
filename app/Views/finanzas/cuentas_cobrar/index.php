<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('finanzas') ?>">Finanzas</a></li>
<li class="breadcrumb-item active">Cuentas por Cobrar</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-file-invoice-dollar mr-1"></i>
                    Cuentas por Cobrar
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-sm btn-primary" id="btnSincronizar">
                        <i class="fas fa-sync-alt mr-1"></i> Sincronizar Ventas
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

                <!-- Resumen de Totales -->
                <div class="row mb-4">
                    <div class="col-lg-4 col-md-6 mb-3">
                        <div class="info-box mb-0">
                            <span class="info-box-icon bg-info elevation-1">
                                <i class="fas fa-file-invoice-dollar"></i>
                            </span>
                            <div class="info-box-content">
                                <span class="info-box-text">Total General</span>
                                <span class="info-box-number"><?= formatGs($totalGeneral) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6 mb-3">
                        <div class="info-box mb-0">
                            <span class="info-box-icon bg-success elevation-1">
                                <i class="fas fa-check-circle"></i>
                            </span>
                            <div class="info-box-content">
                                <span class="info-box-text">Total Pagado</span>
                                <span class="info-box-number"><?= number_format($totalPagado, 0, ',', '.') ?>
                                    <small>Gs.</small></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6 mb-3">
                        <div class="info-box mb-0">
                            <span class="info-box-icon bg-warning elevation-1">
                                <i class="fas fa-exclamation-triangle"></i>
                            </span>
                            <div class="info-box-content">
                                <span class="info-box-text">Total Pendiente</span>
                                <span class="info-box-number"><?= formatGs($totalPendiente) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover table-striped auto-datatable" style="width:100%">
                        <thead class="thead-light">
                            <tr>
                                <th><i class="fas fa-user mr-1"></i> Cliente</th>
                                <th><i class="fas fa-file-alt mr-1"></i> Documento</th>
                                <th><i class="fas fa-tag mr-1"></i> Tipo</th>
                                <th><i class="fas fa-calendar-alt mr-1"></i> Fecha Emisión</th>
                                <th><i class="fas fa-calendar-times mr-1"></i> Fecha Vencimiento</th>
                                <th class="text-right"><i class="fas fa-dollar-sign mr-1"></i> Monto Total</th>
                                <th class="text-right"><i class="fas fa-check-circle mr-1"></i> Pagado</th>
                                <th class="text-right"><i class="fas fa-exclamation-triangle mr-1"></i> Pendiente</th>
                                <th class="text-center"><i class="fas fa-clock mr-1"></i> Días Vencido</th>
                                <th class="text-center"><i class="fas fa-info-circle mr-1"></i> Estado</th>
                                <th class="text-center"><i class="fas fa-cog mr-1"></i> Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cuentas as $cuenta): ?>
                                <?php
                                $diasVencido = $cuenta['dias_vencido'] ?? 0;
                                $rowClass = $diasVencido > 0 ? 'table-danger' : '';
                                $clienteNombre = $cuenta['cliente'] ?
                                    ($cuenta['cliente']['razon_social'] ?? $cuenta['cliente']['nombre'] ?? 'N/A') :
                                    'N/A';
                                ?>
                                <tr class="<?= $rowClass ?>">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-user-circle text-muted mr-2"></i>
                                            <span><?= esc($clienteNombre) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <strong
                                            class="text-primary"><?= esc($cuenta['numero_documento'] ?? 'N/A') ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge badge-secondary badge-pill">
                                            <i class="fas fa-file-alt mr-1"></i>
                                            <?= esc($cuenta['tipo_documento'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($cuenta['fecha_emision']): ?>
                                            <span class="text-muted">
                                                <i class="fas fa-calendar mr-1"></i>
                                                <?= date('d/m/Y', strtotime($cuenta['fecha_emision'])) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($cuenta['fecha_vencimiento']): ?>
                                            <span
                                                class="<?= $diasVencido > 0 ? 'text-danger font-weight-bold' : 'text-muted' ?>">
                                                <i class="fas fa-calendar-times mr-1"></i>
                                                <?= date('d/m/Y', strtotime($cuenta['fecha_vencimiento'])) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-right">
                                        <span class="font-weight-bold text-dark">
                                            <?= formatGs($cuenta['monto_total'] ?? 0) ?>
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        <span class="text-success font-weight-semibold">
                                            <i class="fas fa-check-circle mr-1"></i>
                                            <?= formatGs($cuenta['monto_pagado'] ?? 0) ?>
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        <strong class="text-warning">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>
                                            <?= number_format((float) ($cuenta['monto_pendiente'] ?? 0), 0, ',', '.') ?>
                                            <small>Gs.</small>
                                        </strong>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($diasVencido > 0): ?>
                                            <span class="badge badge-danger badge-pill">
                                                <i class="fas fa-clock mr-1"></i>
                                                <?= $diasVencido ?> días
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-success badge-pill">
                                                <i class="fas fa-check mr-1"></i>
                                                Al día
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                        $estado = $cuenta['estado'] ?? 'PENDIENTE';
                                        $estadoColors = [
                                            'PAGADO' => 'success',
                                            'PENDIENTE' => 'warning',
                                            'VENCIDO' => 'danger',
                                            'PARCIAL' => 'info'
                                        ];
                                        $color = $estadoColors[$estado] ?? 'secondary';
                                        $estadoIcons = [
                                            'PAGADO' => 'check-circle',
                                            'PENDIENTE' => 'clock',
                                            'VENCIDO' => 'exclamation-triangle',
                                            'PARCIAL' => 'percent'
                                        ];
                                        $icon = $estadoIcons[$estado] ?? 'info-circle';
                                        ?>
                                        <span class="badge badge-<?= $color ?> badge-pill">
                                            <i class="fas fa-<?= $icon ?> mr-1"></i>
                                            <?= esc($estado) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            <?php if (($cuenta['monto_pendiente'] ?? 0) > 0): ?>
                                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                                    data-bs-target="#modalPago<?= $cuenta['id'] ?>" title="Registrar Pago">
                                                    <i class="fas fa-money-bill-wave mr-1"></i>
                                                    Pagar
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                data-bs-target="#modalHistorial<?= $cuenta['id'] ?>"
                                                title="Ver Historial de Pagos">
                                                <i class="fas fa-history mr-1"></i>
                                                Historial
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Modales para registrar pagos -->
<?php foreach ($cuentas as $cuenta): ?>
    <?php if (($cuenta['monto_pendiente'] ?? 0) > 0): ?>
        <?php
        $clienteNombre = $cuenta['cliente'] ?
            ($cuenta['cliente']['razon_social'] ?? $cuenta['cliente']['nombre'] ?? 'N/A') :
            'N/A';
        ?>
        <div class="modal fade" id="modalPago<?= $cuenta['id'] ?>" tabindex="-1" role="dialog"
            aria-labelledby="modalPagoLabel<?= $cuenta['id'] ?>" aria-hidden="true" data-bs-backdrop="true"
            data-bs-keyboard="true">
            <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 600px;">
                <div class="modal-content">
                    <div class="modal-header bg-gradient-primary text-white">
                        <h5 class="modal-title" id="modalPagoLabel<?= $cuenta['id'] ?>">
                            <i class="fas fa-money-bill-wave mr-2"></i>
                            Registrar Pago
                        </h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <form id="formPago<?= $cuenta['id'] ?>">
                        <div class="modal-body p-4">
                            <input type="hidden" name="cuenta_id" value="<?= $cuenta['id'] ?>">

                            <!-- Información de la cuenta -->
                            <div class="alert alert-info mb-4">
                                <h6 class="alert-heading mb-2">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    Información de la Cuenta
                                </h6>
                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <strong><i class="fas fa-user mr-2"></i> Cliente:</strong><br>
                                        <span class="ml-4"><?= esc($clienteNombre) ?></span>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <strong><i class="fas fa-file-alt mr-2"></i> Documento:</strong><br>
                                        <span class="ml-4"><?= esc($cuenta['numero_documento'] ?? '') ?></span>
                                    </div>
                                    <div class="col-md-12">
                                        <strong><i class="fas fa-exclamation-triangle mr-2 text-warning"></i> Monto
                                            Pendiente:</strong><br>
                                        <span class="ml-4 h5 text-warning mb-0">
                                            <?= formatGs($cuenta['monto_pendiente'] ?? 0) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Campos del formulario -->
                            <div class="form-group">
                                <label for="caja_id<?= $cuenta['id'] ?>" class="font-weight-bold">
                                    <i class="fas fa-cash-register mr-2 text-info"></i>
                                    Caja de Destino <span class="text-danger">*</span>
                                </label>
                                <select class="form-control form-control-lg select2" id="caja_id<?= $cuenta['id'] ?>"
                                    name="caja_id" required style="width: 100%;">
                                    <option value="">Seleccione una caja...</option>
                                    <?php foreach ($cajasAbiertas as $caja): ?>
                                        <option value="<?= $caja['id'] ?>">
                                            <?= esc($caja['nombre']) ?> (<?= esc($caja['usuario_apertura'] ?? 'Usuario Actual') ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($cajasAbiertas)): ?>
                                    <small class="text-danger font-weight-bold">
                                        <i class="fas fa-exclamation-circle"></i> No hay cajas abiertas. Debe abrir una caja para
                                        recibir pagos.
                                    </small>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label for="monto<?= $cuenta['id'] ?>" class="font-weight-bold">
                                    <i class="fas fa-dollar-sign mr-2 text-success"></i>
                                    Monto a Pagar <span class="text-danger">*</span>
                                </label>
                                <div class="input-group input-group-lg">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light">
                                            <i class="fas fa-money-bill-wave"></i>
                                        </span>
                                    </div>
                                    <input type="text" class="form-control form-control-lg text-right"
                                        id="monto<?= $cuenta['id'] ?>" name="monto"
                                        data-monto-max="<?= (float) ($cuenta['monto_pendiente'] ?? 0) ?>" placeholder="0"
                                        required autocomplete="off">
                                    <div class="input-group-append">
                                        <span class="input-group-text bg-light font-weight-bold">Gs.</span>
                                    </div>
                                </div>
                                <small class="form-text text-muted">
                                    Monto máximo: <strong><?= formatGs($cuenta['monto_pendiente'] ?? 0) ?></strong>
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="forma_pago<?= $cuenta['id'] ?>" class="font-weight-bold">
                                    <i class="fas fa-credit-card mr-2 text-primary"></i>
                                    Forma de Pago <span class="text-danger">*</span>
                                </label>
                                <select class="form-control form-control-lg select2" id="forma_pago<?= $cuenta['id'] ?>"
                                    name="forma_pago" required style="width: 100%;">
                                    <option value="">Seleccione una forma de pago...</option>
                                    <option value="EFECTIVO">Efectivo</option>
                                    <option value="TARJETA_DEBITO">Tarjeta Débito</option>
                                    <option value="TARJETA_CREDITO">Tarjeta Crédito</option>
                                    <option value="TRANSFERENCIA">Transferencia</option>
                                    <option value="CHEQUE">Cheque</option>
                                    <option value="OTRO">Otro</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="referencia<?= $cuenta['id'] ?>" class="font-weight-bold">
                                    <i class="fas fa-hashtag mr-2 text-secondary"></i>
                                    Referencia
                                </label>
                                <input type="text" class="form-control form-control-lg" id="referencia<?= $cuenta['id'] ?>"
                                    name="referencia" placeholder="Número de transacción, cheque, comprobante, etc.">
                                <small class="form-text text-muted">
                                    Opcional: Número de referencia del pago
                                </small>
                            </div>
                        </div>
                        <div class="modal-footer bg-light d-flex justify-content-between">
                            <button type="button" class="btn btn-secondary btn-lg btn-pill" data-dismiss="modal">
                                <i class="fas fa-times mr-2"></i>
                                Cancelar
                            </button>
                            <button type="submit" class="btn btn-primary btn-lg btn-pill">
                                <i class="fas fa-check mr-2"></i>
                                Registrar Pago
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<!-- Modales para ver historial de pagos -->
<?php foreach ($cuentas as $cuenta): ?>
    <?php
    $clienteNombre = $cuenta['cliente'] ?
        ($cuenta['cliente']['razon_social'] ?? $cuenta['cliente']['nombre'] ?? 'N/A') :
        'N/A';
    $pagos = $cuenta['pagos'] ?? [];
    $totalPagado = array_sum(array_column($pagos, 'monto'));
    ?>
    <div class="modal fade" id="modalHistorial<?= $cuenta['id'] ?>" tabindex="-1" role="dialog"
        aria-labelledby="modalHistorialLabel<?= $cuenta['id'] ?>" aria-hidden="true" data-bs-backdrop="true"
        data-bs-keyboard="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-gradient-info text-white">
                    <h5 class="modal-title" id="modalHistorialLabel<?= $cuenta['id'] ?>">
                        <i class="fas fa-history mr-2"></i>
                        Historial de Pagos
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4">
                    <!-- Información de la cuenta -->
                    <div class="alert alert-info mb-4">
                        <h6 class="alert-heading mb-3">
                            <i class="fas fa-file-invoice-dollar mr-2"></i>
                            Información de la Cuenta
                        </h6>
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <strong><i class="fas fa-user mr-2"></i> Cliente:</strong><br>
                                <span class="ml-4"><?= esc($clienteNombre) ?></span>
                            </div>
                            <div class="col-md-6 mb-2">
                                <strong><i class="fas fa-file-alt mr-2"></i> Documento:</strong><br>
                                <span class="ml-4"><?= esc($cuenta['numero_documento'] ?? '') ?></span>
                            </div>
                            <div class="col-md-4 mb-2">
                                <strong><i class="fas fa-dollar-sign mr-2"></i> Monto Total:</strong><br>
                                <span class="ml-4 h6 mb-0"><?= formatGs($cuenta['monto_total'] ?? 0) ?></span>
                            </div>
                            <div class="col-md-4 mb-2">
                                <strong><i class="fas fa-check-circle mr-2 text-success"></i> Total Pagado:</strong><br>
                                <span class="ml-4 h6 mb-0 text-success"><?= formatGs($totalPagado) ?></span>
                            </div>
                            <div class="col-md-4 mb-2">
                                <strong><i class="fas fa-exclamation-triangle mr-2 text-warning"></i>
                                    Pendiente:</strong><br>
                                <span
                                    class="ml-4 h6 mb-0 text-warning"><?= formatGs($cuenta['monto_pendiente'] ?? 0) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Tabla de pagos -->
                    <?php if (empty($pagos)): ?>
                        <div class="alert alert-warning text-center">
                            <i class="fas fa-info-circle mr-2"></i>
                            No se han registrado pagos para esta cuenta.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead class="thead-light">
                                    <tr>
                                        <th><i class="fas fa-calendar mr-1"></i> Fecha</th>
                                        <th><i class="fas fa-credit-card mr-1"></i> Forma de Pago</th>
                                        <th class="text-right"><i class="fas fa-dollar-sign mr-1"></i> Monto</th>
                                        <th><i class="fas fa-hashtag mr-1"></i> Referencia</th>
                                        <th><i class="fas fa-user mr-1"></i> Usuario</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pagos as $pago): ?>
                                        <tr>
                                            <td>
                                                <i class="fas fa-calendar-alt text-muted mr-1"></i>
                                                <?= date('d/m/Y', strtotime($pago['fecha_pago'])) ?>
                                                <br>
                                                <small class="text-muted">
                                                    <?= date('H:i:s', strtotime($pago['created_at'] ?? $pago['fecha_pago'])) ?>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge badge-primary badge-pill">
                                                    <i class="fas <?php
                                                    $iconos = [
                                                        'EFECTIVO' => 'fa-money-bill-wave',
                                                        'TARJETA_DEBITO' => 'fa-credit-card',
                                                        'TARJETA_CREDITO' => 'fa-credit-card',
                                                        'TRANSFERENCIA' => 'fa-exchange-alt',
                                                        'CHEQUE' => 'fa-file-invoice',
                                                        'OTRO' => 'fa-ellipsis-h'
                                                    ];
                                                    echo $iconos[$pago['forma_pago'] ?? 'OTRO'] ?? 'fa-circle';
                                                    ?> mr-1"></i>
                                                    <?= esc($pago['forma_pago'] ?? 'N/A') ?>
                                                </span>
                                            </td>
                                            <td class="text-right">
                                                <strong class="text-success">
                                                    <?= formatGs($pago['monto'] ?? 0) ?>
                                                </strong>
                                            </td>
                                            <td>
                                                <?php if (!empty($pago['referencia'])): ?>
                                                    <code><?= esc($pago['referencia']) ?></code>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= esc($pago['usuario_id'] ?? 'Sistema') ?>
                                                </small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="bg-light">
                                    <tr>
                                        <th colspan="2" class="text-right">Total Pagado:</th>
                                        <th class="text-right text-success">
                                            <strong><?= formatGs($totalPagado) ?></strong>
                                        </th>
                                        <th colspan="2"></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-lg btn-pill" data-dismiss="modal">
                        <i class="fas fa-times mr-2"></i>
                        Cerrar
                    </button>
                    <?php if (($cuenta['monto_pendiente'] ?? 0) > 0): ?>
                        <button type="button" class="btn btn-primary btn-lg btn-pill" data-dismiss="modal"
                            data-bs-toggle="modal" data-bs-target="#modalPago<?= $cuenta['id'] ?>">
                            <i class="fas fa-money-bill-wave mr-2"></i>
                            Registrar Nuevo Pago
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    $(document).ready(function () {
        // Inicializar Select2 en modales
        $('.select2').select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: 'Seleccione una opción...',
            allowClear: true
        });

        // Botón de sincronización
        $('#btnSincronizar').on('click', function () {
            const btn = $(this);
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Sincronizando...');

            $.ajax({
                url: '<?= base_url('finanzas/cuentas-cobrar/sincronizar') ?>',
                method: 'POST',
                dataType: 'json',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    '<?= csrf_header() ?>': '<?= csrf_hash() ?>'
                },
                success: function (response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Éxito',
                            text: response.message || 'Sincronización completada',
                            showConfirmButton: false,
                            timer: 2000
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'Error al sincronizar'
                        });
                        btn.prop('disabled', false).html('<i class="fas fa-sync-alt mr-2"></i> Sincronizar Ventas');
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
                    btn.prop('disabled', false).html('<i class="fas fa-sync-alt mr-1"></i> Sincronizar Ventas');
                }
            });
        });

        // Función para obtener icono de forma de pago
        function getFormaPagoIcono(valor) {
            const iconos = {
                'EFECTIVO': 'fa-money-bill-wave',
                'TARJETA_DEBITO': 'fa-credit-card',
                'TARJETA_CREDITO': 'fa-credit-card',
                'TRANSFERENCIA': 'fa-exchange-alt',
                'CHEQUE': 'fa-file-invoice',
                'OTRO': 'fa-ellipsis-h'
            };
            return iconos[valor] || 'fa-circle';
        }

        // Función para formatear número en guaraníes mientras se escribe
        function formatGsInput(value) {
            if (!value) return '';
            // Remover todo excepto dígitos
            const numStr = String(value).replace(/[^\d]/g, '');
            if (!numStr) return '';
            // Convertir a número y formatear con puntos como separadores de miles
            const num = parseInt(numStr, 10);
            if (isNaN(num) || num <= 0) return '';
            // Formatear manualmente con puntos como separadores de miles
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        // Función para obtener el valor numérico de un input formateado
        function getNumericValue(formattedValue) {
            if (!formattedValue) return 0;
            // Remover puntos (separadores de miles) y convertir a número
            const numStr = String(formattedValue).replace(/\./g, '').replace(/,/g, '').trim();
            return parseFloat(numStr) || 0;
        }

        // Aplicar formato a inputs de monto mientras se escribe
        $(document).on('input', 'input[name="monto"]', function () {
            const $input = $(this);
            const cursorPos = this.selectionStart || 0;
            const oldValue = $input.val() || '';

            // Obtener solo los dígitos del valor actual
            const numericValue = getNumericValue(oldValue);
            const formatted = formatGsInput(numericValue);

            if (formatted !== oldValue) {
                $input.val(formatted);

                // Calcular nueva posición del cursor
                // Contar cuántos dígitos hay antes del cursor en el valor anterior
                const digitsBeforeCursor = (oldValue.substring(0, cursorPos).match(/\d/g) || []).length;
                let newCursorPos = 0;
                let digitCount = 0;

                for (let i = 0; i < formatted.length; i++) {
                    if (/\d/.test(formatted[i])) {
                        digitCount++;
                        if (digitCount > digitsBeforeCursor) {
                            newCursorPos = i;
                            break;
                        }
                    }
                    newCursorPos = i + 1;
                }

                // Restaurar posición del cursor
                this.setSelectionRange(newCursorPos, newCursorPos);
            }
        });

        // Prevenir entrada de caracteres no numéricos
        $(document).on('keypress', 'input[name="monto"]', function (e) {
            // Permitir backspace, delete, tab, escape, enter, y teclas de control
            if ([8, 9, 27, 13, 46].indexOf(e.keyCode) !== -1 ||
                // Permitir Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
                (e.keyCode === 65 && e.ctrlKey === true) ||
                (e.keyCode === 67 && e.ctrlKey === true) ||
                (e.keyCode === 86 && e.ctrlKey === true) ||
                (e.keyCode === 88 && e.ctrlKey === true) ||
                // Permitir home, end, left, right
                (e.keyCode >= 35 && e.keyCode <= 39)) {
                return;
            }
            // Asegurar que es un número
            if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                e.preventDefault();
            }
        });

        // Inicializar Select2 cuando se abre el modal
        $('.modal').on('shown.bs.modal', function () {
            const modal = $(this);
            modal.find('.select2').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Seleccione una forma de pago...',
                allowClear: false,
                templateResult: function (data) {
                    if (!data.id) {
                        return data.text;
                    }
                    const icono = getFormaPagoIcono(data.id);
                    return $('<span><i class="fas ' + icono + ' mr-2"></i>' + data.text + '</span>');
                },
                templateSelection: function (data) {
                    if (!data.id) {
                        return data.text;
                    }
                    const icono = getFormaPagoIcono(data.id);
                    return $('<span><i class="fas ' + icono + ' mr-2"></i>' + data.text + '</span>');
                }
            });

            // Limpiar y enfocar el campo de monto cuando se abre el modal
            const montoInput = modal.find('input[name="monto"]');
            if (montoInput.length) {
                montoInput.val('').focus();
            }
        });

        // Manejar envío de formularios de pago
        <?php foreach ($cuentas as $cuenta): ?>
            <?php if (($cuenta['monto_pendiente'] ?? 0) > 0): ?>
                $('#formPago<?= $cuenta['id'] ?>').on('submit', function (e) {
                    e.preventDefault();

                    // Obtener monto del input formateado y convertir a número
                    const montoFormateado = $('#monto<?= $cuenta['id'] ?>').val() || '0';
                    const monto = getNumericValue(montoFormateado);

                    // Asegurar que montoPendiente sea un número correcto
                    const montoPendiente = parseFloat($('#monto<?= $cuenta['id'] ?>').data('monto-max')) || 0;

                    if (isNaN(monto) || monto <= 0) {
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
                            text: 'El monto ingresado supera el saldo pendiente. Monto máximo: <?= formatGs($cuenta['monto_pendiente'] ?? 0) ?>'
                        });
                        return;
                    }

                    // Crear objeto de datos para enviar (con el monto numérico, sin formato)
                    const formDataArray = $(this).serializeArray();
                    const dataToSend = {};
                    formDataArray.forEach(function (item) {
                        if (item.name === 'monto') {
                            // Enviar el monto numérico sin formato
                            dataToSend[item.name] = monto;
                        } else {
                            dataToSend[item.name] = item.value;
                        }
                    });

                    $.ajax({
                        url: '<?= base_url('finanzas/cuentas-cobrar/registrar-pago') ?>',
                        method: 'POST',
                        data: dataToSend,
                        dataType: 'json',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            '<?= csrf_header() ?>': '<?= csrf_hash() ?>'
                        },
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