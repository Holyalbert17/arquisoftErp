<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('finanzas') ?>">Finanzas</a></li>
<li class="breadcrumb-item"><a href="<?= base_url('finanzas/cajas') ?>">Cajas</a></li>
<li class="breadcrumb-item active">Detalle de Caja</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-cash-register mr-1"></i>
                    Caja: <?= esc($caja['nombre']) ?>
                </h3>
                <div class="card-tools">
                    <?php if ($caja['estado'] == 'ABIERTA'): ?>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalMovimiento">
                            <i class="fas fa-plus"></i> Nuevo Movimiento
                        </button>
                        <a href="<?= base_url('finanzas/cajas/arqueo/' . $caja['id']) ?>" 
                           class="btn btn-sm btn-info">
                            <i class="fas fa-calculator"></i> Arqueo
                        </a>
                        <a href="<?= base_url('finanzas/cajas/cerrar/' . $caja['id']) ?>" 
                           class="btn btn-sm btn-warning">
                            <i class="fas fa-lock"></i> Cerrar
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <div class="info-box">
                            <span class="info-box-icon bg-info"><i class="fas fa-wallet"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Saldo Inicial</span>
                                <span class="info-box-number"><?= number_format($caja['saldo_inicial'], 0, ',', '.') ?> Gs.</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-box">
                            <span class="info-box-icon bg-success"><i class="fas fa-money-bill-wave"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Saldo Actual</span>
                                <span class="info-box-number"><?= number_format($caja['saldo_actual'], 0, ',', '.') ?> Gs.</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-box">
                            <span class="info-box-icon bg-warning"><i class="fas fa-calculator"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Estado</span>
                                <span class="info-box-number">
                                    <span class="badge badge-<?= $caja['estado'] == 'ABIERTA' ? 'success' : 'secondary' ?>">
                                        <?= esc($caja['estado']) ?>
                                    </span>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-box">
                            <span class="info-box-icon bg-primary"><i class="fas fa-list"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Movimientos Hoy</span>
                                <span class="info-box-number"><?= count($movimientos) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <h5 class="mb-3">Movimientos del Día</h5>
                <table class="table table-bordered table-striped data-table">
                    <thead>
                        <tr>
                            <th>Hora</th>
                            <th>Tipo</th>
                            <th>Concepto</th>
                            <th>Monto</th>
                            <th>Referencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movimientos as $movimiento): ?>
                            <tr>
                                <td><?= date('H:i:s', strtotime($movimiento['fecha_movimiento'])) ?></td>
                                <td>
                                    <span class="badge badge-info">
                                        <?= esc(str_replace('_', ' ', $movimiento['tipo_movimiento'])) ?>
                                    </span>
                                </td>
                                <td><?= esc($movimiento['concepto']) ?></td>
                                <td class="currency">
                                    <?php
                                    $montoPrincipal = (float) ($movimiento['monto_moneda_principal'] ?? 0);
                                    // El signo debe reflejar el efecto real sobre la caja
                                    $signo = ($montoPrincipal < 0) ? '-' : '+';
                                    ?>
                                    <span class="<?= $signo == '+' ? 'text-success' : 'text-danger' ?>">
                                        <?= $signo ?> <?= number_format(abs($montoPrincipal), 0, ',', '.') ?> Gs.
                                    </span>
                                </td>
                                <td><?= esc($movimiento['referencia'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal para nuevo movimiento -->
<div class="modal fade" id="modalMovimiento" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nuevo Movimiento</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="formMovimiento">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label>Tipo de Movimiento <span class="text-danger">*</span></label>
                        <select name="tipo_movimiento" class="form-control" required>
                            <option value="INGRESO">Ingreso</option>
                            <option value="EGRESO">Egreso</option>
                            <option value="TRANSFERENCIA_ENTRADA">Transferencia Entrada</option>
                            <option value="TRANSFERENCIA_SALIDA">Transferencia Salida</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Monto <span class="text-danger">*</span></label>
                        <input type="text" name="monto" class="form-control" required inputmode="numeric" autocomplete="off" placeholder="Ej: 200.000">
                    </div>
                    <div class="form-group">
                        <label>Concepto <span class="text-danger">*</span></label>
                        <input type="text" name="concepto" class="form-control" required minlength="5" maxlength="200">
                    </div>
                    <div class="form-group">
                        <label>Referencia</label>
                        <input type="text" name="referencia" class="form-control" maxlength="100">
                    </div>
                    <div class="form-group">
                        <label>Observaciones</label>
                        <textarea name="observaciones" class="form-control" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="btnGuardarMovimiento">Guardar</button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(document).ready(function() {
    $('.data-table').DataTable(datatablesEsConfig);

    function parseGs(value) {
        const raw = (value || '').toString().trim();
        if (raw === '') return 0;
        const cleaned = raw.replace(/\./g, '').replace(/,/g, '.');
        const n = parseFloat(cleaned);
        return Number.isNaN(n) ? 0 : n;
    }

    function formatGs(value) {
        const n = Math.round(parseGs(value));
        return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    $('input[name="monto"]').on('input', function() {
        const cursorPos = this.selectionStart;
        const formatted = formatGs($(this).val());
        $(this).val(formatted);
        try { this.setSelectionRange(cursorPos, cursorPos); } catch (e) {}
    });

    $('#btnGuardarMovimiento').on('click', function() {
        const form = $('#formMovimiento');
        const formData = new FormData(form[0]);

        const montoRaw = (form.find('input[name="monto"]').val() || '').toString().trim();
        if (montoRaw !== '' && parseGs(montoRaw) <= 0) {
            Swal.fire('Error', 'El monto debe ser mayor a cero', 'error');
            return;
        }

        $.ajax({
            url: '<?= base_url("finanzas/cajas/movimiento/" . $caja['id']) ?>',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Movimiento Registrado',
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
            error: function() {
                Swal.fire('Error', 'Error al registrar el movimiento', 'error');
            }
        });
    });
});
</script>
<?= $this->endSection() ?>
