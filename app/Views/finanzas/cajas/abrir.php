<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('finanzas') ?>">Finanzas</a></li>
<li class="breadcrumb-item"><a href="<?= base_url('finanzas/cajas') ?>">Cajas</a></li>
<li class="breadcrumb-item active">Abrir Caja</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card card-success card-outline">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-unlock-alt me-1"></i>
                    Abrir Caja: <?= esc($caja['nombre']) ?>
                </h3>
            </div>
            <div class="card-body">
                <!-- Stepper -->
                <ul class="stepper mb-4">
                    <li class="step active" data-step="1">
                        <span class="step-number">1</span>
                        <span class="step-label">Confirmar caja</span>
                    </li>
                    <li class="step" data-step="2">
                        <span class="step-number">2</span>
                        <span class="step-label">Conteo inicial</span>
                    </li>
                    <li class="step" data-step="3">
                        <span class="step-number">3</span>
                        <span class="step-label">Confirmar apertura</span>
                    </li>
                </ul>

                <!-- Paso 1: Confirmar caja -->
                <div id="paso1" class="wizard-paso">
                    <div class="card border-0 bg-light">
                        <div class="card-body">
                            <h5 class="card-title text-primary"><i class="fas fa-cash-register me-1"></i> Caja a abrir</h5>
                            <p class="mb-1"><strong>Nombre:</strong> <?= esc($caja['nombre']) ?></p>
                            <?php if (!empty($caja['descripcion'])): ?>
                                <p class="mb-0 text-muted"><small><?= esc($caja['descripcion']) ?></small></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="mt-3 text-end">
                        <a href="<?= base_url('finanzas/cajas') ?>" class="btn btn-secondary">Cancelar</a>
                        <button type="button" class="btn btn-primary" id="btnPaso1Siguiente">
                            Siguiente <i class="fas fa-arrow-right ms-1"></i>
                        </button>
                    </div>
                </div>

                <!-- Paso 2: Conteo inicial -->
                <div id="paso2" class="wizard-paso" style="display: none;">
                    <form id="formAbrirCaja">
                        <?= csrf_field() ?>
                        <input type="hidden" name="saldo_inicial" id="saldo_inicial" value="0">

                        <div class="row">
                            <div class="col-md-5">
                                <div class="card border-success">
                                    <div class="card-header bg-success text-white py-2">
                                        <strong><i class="fas fa-wallet me-1"></i> Saldo inicial (Gs.)</strong>
                                    </div>
                                    <div class="card-body">
                                        <p class="h4 text-success mb-0" id="displaySaldoInicial">0 Gs.</p>
                                        <small class="text-muted">Calculado desde la tabla de denominaciones</small>
                                        <div class="mt-3">
                                            <label class="form-label">Monto inicial (Gs.)</label>
                                            <input type="text" class="form-control" id="monto_inicial_manual"
                                                inputmode="numeric" autocomplete="off"
                                                placeholder="Ej: 600 o 600000">
                                            <small class="text-muted">Si completa este campo, se usará este monto directamente.</small>
                                        </div>
                                        <div class="mt-3">
                                            <label class="form-label">Observaciones de apertura</label>
                                            <textarea name="observaciones" class="form-control" rows="3"
                                                placeholder="Observaciones opcionales sobre la apertura"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-7">
                                <div class="card">
                                    <div class="card-header bg-light py-2">
                                        <strong><i class="fas fa-coins me-1"></i> Conteo de efectivo inicial</strong>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-striped mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Denominación</th>
                                                        <th style="width: 100px;">Cantidad</th>
                                                        <th class="text-end">Subtotal</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                    $billetes = [100000, 50000, 20000, 10000, 5000, 2000];
                                                    $monedas = [1000, 500, 100, 50];
                                                    $denominaciones = array_merge($billetes, $monedas);
                                                    foreach ($denominaciones as $valor):
                                                    ?>
                                                    <tr>
                                                        <td class="align-middle fw-bold"><?= number_format($valor, 0, ',', '.') ?> Gs.</td>
                                                        <td>
                                                            <input type="number" class="form-control form-control-sm input-denominacion text-center"
                                                                data-valor="<?= $valor ?>" min="0" value="" aria-label="Cantidad <?= number_format($valor, 0, ',', '.') ?>">
                                                        </td>
                                                        <td class="align-middle text-end total-fila fw-bold">0 Gs.</td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                                <tfoot class="table-light">
                                                    <tr>
                                                        <th colspan="2" class="text-end">TOTAL EFECTIVO:</th>
                                                        <th class="text-end h5 text-success fw-bold" id="totalEfectivo">0 Gs.</th>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                    <div class="mt-3 d-flex justify-content-between">
                        <button type="button" class="btn btn-outline-secondary" id="btnPaso2Atras">
                            <i class="fas fa-arrow-left me-1"></i> Atrás
                        </button>
                        <button type="button" class="btn btn-primary" id="btnPaso2Siguiente">
                            Siguiente <i class="fas fa-arrow-right ms-1"></i>
                        </button>
                    </div>
                </div>

                <!-- Paso 3: Resumen y confirmar -->
                <div id="paso3" class="wizard-paso" style="display: none;">
                    <div class="card border-success">
                        <div class="card-header bg-success text-white py-2">
                            <strong><i class="fas fa-check-circle me-1"></i> Resumen de apertura</strong>
                        </div>
                        <div class="card-body">
                            <table class="table table-borderless mb-0">
                                <tr>
                                    <th style="width: 180px;">Caja</th>
                                    <td id="resumenCaja"><?= esc($caja['nombre']) ?></td>
                                </tr>
                                <tr>
                                    <th>Saldo inicial</th>
                                    <td class="text-success fw-bold" id="resumenSaldo">0 Gs.</td>
                                </tr>
                                <tr>
                                    <th>Observaciones</th>
                                    <td id="resumenObservaciones" class="text-muted">—</td>
                                </tr>
                                <tr>
                                    <th>Fecha y hora</th>
                                    <td id="resumenFecha">—</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <div class="mt-3 d-flex justify-content-between">
                        <button type="button" class="btn btn-outline-secondary" id="btnPaso3Atras">
                            <i class="fas fa-arrow-left me-1"></i> Atrás
                        </button>
                        <button type="button" class="btn btn-success btn-lg" id="btnConfirmarApertura">
                            <i class="fas fa-unlock-alt me-1"></i> Confirmar apertura
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.stepper { list-style: none; padding: 0; margin: 0; display: flex; justify-content: space-between; gap: 0.5rem; }
.stepper .step { flex: 1; text-align: center; position: relative; }
.stepper .step::after { content: ''; position: absolute; top: 1rem; left: 50%; right: -50%; height: 2px; background: #dee2e6; z-index: 0; }
.stepper .step:last-child::after { display: none; }
.stepper .step.active .step-number { background: #0d6efd; color: #fff; border-color: #0d6efd; }
.stepper .step.done .step-number { background: #198754; color: #fff; border-color: #198754; }
.stepper .step.done::after { background: #198754; }
.step-number { display: inline-flex; align-items: center; justify-content: center; width: 2rem; height: 2rem; border-radius: 50%; border: 2px solid #dee2e6; background: #fff; font-weight: bold; position: relative; z-index: 1; }
.step-label { display: block; font-size: 0.85rem; margin-top: 0.25rem; color: #6c757d; }
.stepper .step.active .step-label { color: #0d6efd; font-weight: 600; }
</style>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(function () {
    var pasoActual = 1;
    var urlAbrir = '<?= base_url("finanzas/cajas/abrir/" . $caja['id']) ?>';
    var urlCajas = '<?= base_url("finanzas/cajas") ?>';

    function formatGs(amount) {
        return new Intl.NumberFormat('es-PY', { maximumFractionDigits: 0 }).format(amount) + ' Gs.';
    }

    function irPaso(paso) {
        $('.wizard-paso').hide();
        $('#paso' + paso).show();
        $('.stepper .step').removeClass('active done');
        $('.stepper .step').each(function () {
            var n = parseInt($(this).data('step'), 10);
            if (n < paso) $(this).addClass('done');
            if (n === paso) $(this).addClass('active');
        });
        pasoActual = paso;
    }

    function calcularTotal() {
        var manualRaw = ($('#monto_inicial_manual').val() || '').toString().trim();
        if (manualRaw !== '') {
            var manual = manualRaw.replace(/\./g, '').replace(/,/g, '.');
            var manualVal = parseFloat(manual);
            if (!Number.isNaN(manualVal) && manualVal >= 0) {
                var manualInt = Math.round(manualVal);
                $('#totalEfectivo').text(formatGs(manualInt));
                $('#saldo_inicial').val(manualInt);
                $('#displaySaldoInicial').text(formatGs(manualInt));
                return manualInt;
            }
        }

        var total = 0;
        $('.input-denominacion').each(function () {
            var cant = parseInt($(this).val(), 10) || 0;
            var valor = parseInt($(this).data('valor'), 10);
            var subtotal = cant * valor;
            $(this).closest('tr').find('.total-fila').text(formatGs(subtotal));
            total += subtotal;
        });
        $('#totalEfectivo').text(formatGs(total));
        $('#saldo_inicial').val(total);
        $('#displaySaldoInicial').text(formatGs(total));
        return total;
    }

    function actualizarResumen() {
        $('#resumenSaldo').text(formatGs(parseInt($('#saldo_inicial').val(), 10) || 0));
        var obs = $('textarea[name="observaciones"]').val().trim();
        $('#resumenObservaciones').text(obs || '—');
        var now = new Date();
        $('#resumenFecha').text(now.toLocaleString('es-PY', { dateStyle: 'short', timeStyle: 'short' }));
    }

    $('.input-denominacion').on('input', function () {
        if (($('#monto_inicial_manual').val() || '').toString().trim() !== '') {
            $('#monto_inicial_manual').val('');
        }
        calcularTotal();
    });

    $('#monto_inicial_manual').on('input', function () {
        $('.input-denominacion').val('');
        $('.total-fila').text(formatGs(0));
        calcularTotal();
    });

    $('#btnPaso1Siguiente').on('click', function () { irPaso(2); });

    $('#btnPaso2Atras').on('click', function () { irPaso(1); });
    $('#btnPaso2Siguiente').on('click', function () {
        calcularTotal();
        actualizarResumen();
        irPaso(3);
    });

    $('#btnPaso3Atras').on('click', function () { irPaso(2); });

    $('#btnConfirmarApertura').on('click', function () {
        var form = $('#formAbrirCaja');
        var formData = new FormData(form[0]);
        var btn = $(this);
        var textoOriginal = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Procesando...');

        $.ajax({
            url: urlAbrir,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Caja abierta',
                    text: response.message,
                    showConfirmButton: false,
                    timer: 2000
                }).then(function () {
                    window.location.href = response.data.redirect || urlCajas;
                });
            } else {
                Swal.fire('Error', response.message || 'Error al abrir la caja', 'error');
                btn.prop('disabled', false).html(textoOriginal);
            }
        }).fail(function (xhr) {
            var msg = 'Error al abrir la caja';
            if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
            Swal.fire('Error', msg, 'error');
            btn.prop('disabled', false).html(textoOriginal);
        });
    });
});
</script>
<?= $this->endSection() ?>
