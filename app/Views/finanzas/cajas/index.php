<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('finanzas') ?>">Finanzas</a></li>
<li class="breadcrumb-item active">Cajas</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php if (session()->getFlashdata('info')): ?>
<div class="row">
    <div class="col-12">
        <div class="alert alert-info alert-dismissible mb-3" role="alert">
            <i class="fas fa-info-circle me-2"></i><?= esc(session()->getFlashdata('info')) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($tiene_cajas_abiertas)): ?>
<div class="row">
    <div class="col-12">
        <div class="alert alert-info alert-dismissible mb-3" role="alert">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Tiene una caja abierta.</strong> Puede realizar arqueo o cerrar la caja actual. No puede abrir otra caja hasta cerrar la actual.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-cash-register me-1"></i> Gestión de Cajas</h3>
                <div class="card-tools">
                    <?php if (empty($tiene_cajas_abiertas)): ?>
                    <a href="<?= base_url('finanzas/cajas/abrir') ?>" class="btn btn-success btn-sm me-1">
                        <i class="fas fa-lock-open"></i> Abrir Caja
                    </a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-primary btn-sm" id="btnAbrirNuevaCaja">
                        <i class="fas fa-plus"></i> Nueva Caja
                    </button>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped data-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Usuario</th>
                            <th>Estado</th>
                            <th class="text-end">Saldo Inicial</th>
                            <th class="text-end">Saldo Actual</th>
                            <th class="text-nowrap">Fecha Apertura</th>
                            <th>Movimientos Hoy</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cajas as $caja): ?>
                            <tr>
                                <td>
                                    <strong><?= esc($caja['nombre']) ?></strong>
                                    <?php if ($caja['descripcion']): ?>
                                        <br><small class="text-muted"><?= esc($caja['descripcion']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $usuarioModel = new \App\Models\UsuarioModel();
                                    $usuario = $usuarioModel->find($caja['usuario_id']);
                                    echo $usuario ? esc($usuario['nombre'] . ' ' . $usuario['apellido']) : 'N/A';
                                    ?>
                                </td>
                                <td>
                                    <?php if ($caja['estado'] == 'ABIERTA'): ?>
                                        <span class="badge badge-success">ABIERTA</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">CERRADA</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap">Gs. <?= number_format((float) ($caja['saldo_inicial'] ?? 0), 0, ',', '.') ?></td>
                                <td class="text-end text-nowrap">Gs. <?= number_format((float) ($caja['saldo_actual'] ?? 0), 0, ',', '.') ?></td>
                                <td class="text-nowrap"><?= isset($caja['fecha_apertura']) ? date('d/m/Y H:i', strtotime($caja['fecha_apertura'])) : 'N/A' ?>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?= $caja['movimientos_hoy'] ?></span>
                                </td>
                                <td>
                                    <a href="<?= base_url('finanzas/cajas/ver/' . $caja['id']) ?>"
                                        class="btn btn-info btn-sm">
                                        <i class="fas fa-eye"></i> Ver
                                    </a>
                                    <?php if ($caja['estado'] == 'CERRADA'): ?>
                                        <?php if (!empty($tiene_cajas_abiertas)): ?>
                                        <button type="button" class="btn btn-success btn-sm" disabled title="Cierre la caja actual antes de abrir otra">
                                            <i class="fas fa-lock-open"></i> Abrir
                                        </button>
                                        <?php else: ?>
                                        <a href="<?= base_url('finanzas/cajas/abrir/' . $caja['id']) ?>"
                                            class="btn btn-success btn-sm">
                                            <i class="fas fa-lock-open"></i> Abrir
                                        </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <a href="<?= base_url('finanzas/cajas/arqueo/' . $caja['id']) ?>"
                                            class="btn btn-warning btn-sm">
                                            <i class="fas fa-clipboard-check"></i> Arqueo
                                        </a>
                                        <a href="<?= base_url('finanzas/cajas/cerrar/' . $caja['id']) ?>"
                                            class="btn btn-danger btn-sm">
                                            <i class="fas fa-lock"></i> Cerrar
                                        </a>
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

<!-- Plantilla para modal dinámico Nueva Caja (contenido clonado al abrir) -->
<div id="templateNuevaCaja" style="display: none;">
    <form id="formNuevaCaja">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label for="inputNombreCaja" class="form-label">Nombre de la Caja <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="inputNombreCaja" name="nombre" required
                minlength="3" maxlength="100" placeholder="Ej. Caja Principal">
        </div>
        <div class="mb-3">
            <label for="inputDescripcion" class="form-label">Descripción</label>
            <textarea class="form-control" id="inputDescripcion" name="descripcion" rows="2"
                placeholder="Descripción opcional"></textarea>
        </div>
        <div class="border-top pt-3 mt-3">
            <div class="form-check form-check-inline mb-3">
                <input type="checkbox" class="form-check-input" id="checkAbrirInmediatamente" name="abrir_inmediatamente">
                <label class="form-check-label fw-bold text-success" for="checkAbrirInmediatamente">
                    <i class="fas fa-unlock-alt me-1"></i> Abrir Caja Inmediatamente
                </label>
            </div>
            <div id="panelApertura" class="rounded p-3 bg-light d-none">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Saldo Inicial (Efectivo) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">Gs.</span>
                            <input type="number" class="form-control fw-bold text-success"
                                name="saldo_inicial" id="inputSaldoInicial" value="0" min="0" readonly>
                        </div>
                        <small class="text-muted">Calculado desde la tabla de denominaciones</small>
                        <div class="mt-2">
                            <label class="form-label">Observaciones de Apertura</label>
                            <textarea class="form-control" name="observaciones_apertura" rows="2" placeholder="Opcional"></textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="fas fa-coins me-1"></i> Billetes y Monedas</label>
                        <div class="table-responsive" style="max-height: 220px;">
                            <table class="table table-sm table-striped mb-0">
                                <thead class="table-light sticky-top">
                                    <tr><th>Valor</th><th style="width:70px;">Cant.</th><th class="text-end">Total</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ([100000, 50000, 20000, 10000, 5000, 2000, 1000, 500, 100, 50] as $valor): ?>
                                    <tr>
                                        <td class="align-middle"><?= number_format($valor, 0, ',', '.') ?></td>
                                        <td><input type="number" class="form-control form-control-sm px-1 text-center input-denominacion-new" data-valor="<?= $valor ?>" min="0" placeholder="0"></td>
                                        <td class="align-middle text-end total-fila-new small">0</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
    <div class="d-flex justify-content-end gap-2 mt-3 pt-2 border-top">
        <button type="button" class="btn btn-secondary" id="btnCerrarNuevaCaja">Cancelar</button>
        <button type="button" class="btn btn-primary" id="btnGuardarCaja"><i class="fas fa-save me-1"></i> Guardar</button>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(document).ready(function () {
    $('.data-table').DataTable(datatablesEsConfig);

    $('#btnAbrirNuevaCaja').on('click', function () {
        AppModal.open({
            title: 'Nueva Caja',
            contentSelector: '#templateNuevaCaja',
            size: 'md',
            onShow: function () {
                var $body = $('#app-modal-overlay .app-modal-body');
                $body.find('#inputNombreCaja').trigger('focus');

                $body.find('#btnCerrarNuevaCaja').off('click').on('click', function () { AppModal.close(); });

                $body.find('#checkAbrirInmediatamente').off('change').on('change', function () {
                    if ($(this).is(':checked')) {
                        $body.find('#panelApertura').removeClass('d-none');
                    } else {
                        $body.find('#panelApertura').addClass('d-none');
                        $body.find('.input-denominacion-new').val('');
                        $body.find('#inputSaldoInicial').val(0);
                        $body.find('.total-fila-new').text('0');
                    }
                });

                $body.find('.input-denominacion-new').off('input').on('input', function () {
                    var total = 0;
                    $body.find('.input-denominacion-new').each(function () {
                        var c = parseInt($(this).val(), 10) || 0, v = parseInt($(this).data('valor'), 10);
                        $(this).closest('tr').find('.total-fila-new').text(new Intl.NumberFormat('es-PY').format(c * v));
                        total += c * v;
                    });
                    $body.find('#inputSaldoInicial').val(total);
                });

                $body.find('#btnGuardarCaja').off('click').on('click', function () {
                    var form = $body.find('#formNuevaCaja')[0];
                    if (!form.checkValidity()) { form.reportValidity(); return; }
                    var btn = $(this);
                    var orig = btn.html();
                    btn.html('<i class="fas fa-spinner fa-spin"></i> Procesando...').prop('disabled', true);
                    $.ajax({
                        url: '<?= base_url("finanzas/cajas/guardar") ?>',
                        method: 'POST',
                        data: $body.find('#formNuevaCaja').serialize(),
                        dataType: 'json',
                        success: function (r) {
                            if (r.success) {
                                Swal.fire({ icon: 'success', title: 'Éxito', text: r.message, timer: 1500, showConfirmButton: false }).then(function () {
                                    window.location.href = (r.data && r.data.redirect) ? r.data.redirect : window.location.href;
                                });
                            } else {
                                Swal.fire('Error', r.message, 'error');
                                btn.html(orig).prop('disabled', false);
                            }
                        },
                        error: function (xhr) {
                            var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error al procesar la solicitud';
                            Swal.fire('Error', msg, 'error');
                            btn.html(orig).prop('disabled', false);
                        }
                    });
                });
            }
        });
    });
});
</script>
<?= $this->endSection() ?>