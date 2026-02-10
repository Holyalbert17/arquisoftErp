<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item active">Ventas</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="card card-primary card-outline">
            <div class="card-header bg-gradient-primary">
                <h3 class="card-title text-white">
                    <i class="fas fa-shopping-cart mr-2"></i> Lista de Ventas
                </h3>
                <div class="card-tools">
                    <a href="<?= base_url('ventas/pos') ?>" class="btn btn-light btn-sm">
                        <i class="fas fa-cash-register mr-1"></i> Nueva Venta
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped auto-datatable mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th style="width: 10%;"><i class="fas fa-hashtag mr-1"></i> N° Venta</th>
                                <th style="width: 12%;"><i class="fas fa-calendar mr-1"></i> Fecha</th>
                                <th style="width: 20%;"><i class="fas fa-user mr-1"></i> Cliente</th>
                                <th style="width: 10%;"><i class="fas fa-file-invoice mr-1"></i> Tipo</th>
                                <th style="width: 12%;" class="text-right"><i class="fas fa-money-bill-wave mr-1"></i>
                                    Total</th>
                                <th style="width: 10%;" class="text-center"><i class="fas fa-info-circle mr-1"></i>
                                    Estado</th>
                                <th style="width: 8%;" class="text-center"><i class="fas fa-credit-card mr-1"></i> Pago
                                </th>
                                <th style="width: 18%;" class="text-center"><i class="fas fa-cog mr-1"></i> Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ventas as $venta): ?>
                                <?php
                                $clienteModel = new \App\Models\ClienteModel();
                                $cliente = null;
                                if ($venta['cliente_id']) {
                                    $cliente = $clienteModel->find($venta['cliente_id']);
                                }

                                $estadoColors = [
                                    'COMPLETADA' => 'success',
                                    'PENDIENTE' => 'warning',
                                    'ANULADA' => 'danger'
                                ];
                                $color = $estadoColors[$venta['estado']] ?? 'secondary';

                                $tipoPagoColors = [
                                    'CONTADO' => 'success',
                                    'CREDITO' => 'warning',
                                    'MIXTO' => 'info'
                                ];
                                $tipoPagoColor = $tipoPagoColors[$venta['tipo_pago']] ?? 'secondary';

                                $montoPendiente = (float) ($venta['monto_pendiente'] ?? 0);
                                $montoPagado = (float) ($venta['monto_pagado'] ?? 0);
                                $total = (float) $venta['total'];
                                ?>
                                <tr>
                                    <td>
                                        <strong class="text-primary"><?= esc($venta['numero_venta']) ?></strong>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <i class="far fa-clock mr-1"></i>
                                            <?= date('d/m/Y', strtotime($venta['fecha_venta'])) ?><br>
                                            <span
                                                class="text-muted"><?= date('H:i', strtotime($venta['fecha_venta'])) ?></span>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($cliente): ?>
                                            <i class="fas fa-user-circle mr-1 text-info"></i>
                                            <strong><?= esc($clienteModel->getNombreCompleto($cliente)) ?></strong>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">
                                                <i class="fas fa-walking mr-1"></i> Walk-in
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-info badge-pill">
                                            <?= esc($venta['tipo_comprobante']) ?>
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        <strong class="text-success">
                                            <?= number_format($total, 0, ',', '.') ?> <small>Gs.</small>
                                        </strong>
                                        <?php if ($montoPendiente > 0): ?>
                                            <br>
                                            <small class="text-danger">
                                                Pendiente: <?= number_format($montoPendiente, 0, ',', '.') ?> Gs.
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-<?= $color ?> badge-pill">
                                            <?= esc($venta['estado']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-<?= $tipoPagoColor ?> badge-pill">
                                            <?= esc($venta['tipo_pago']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            <a href="<?= base_url('ventas/ver/' . $venta['id']) ?>"
                                                class="btn btn-sm btn-info" title="Ver Detalles">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="<?= base_url('ventas/imprimir/' . $venta['id']) ?>"
                                                class="btn btn-sm btn-secondary" title="Imprimir" target="_blank">
                                                <i class="fas fa-print"></i>
                                            </a>
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
                                                <button class="btn btn-sm btn-danger btn-anular"
                                                    data-venta-id="<?= $venta['id'] ?>"
                                                    data-fecha-venta="<?= $venta['fecha_venta'] ?>"
                                                    data-es-admin="<?= $esAdmin ? '1' : '0' ?>" title="Anular Venta">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            <?php endif; ?>
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

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    $(document).ready(function () {
        // DataTable gestionada globalmente por .auto-datatable
        // Re-inicializar tooltips después de cada dibujado
        $('[title]').tooltip();

        // Anular venta
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
                    $.ajax({
                        url: '<?= base_url("ventas/anular") ?>/' + ventaId,
                        method: 'POST',
                        data: { motivo: result.value },
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
                                    location.reload();
                                });
                            } else {
                                Swal.fire('Error', response.message, 'error');
                            }
                        },
                        error: function (xhr) {
                            let mensaje = 'Error al anular la venta';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                mensaje = xhr.responseJSON.message;
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