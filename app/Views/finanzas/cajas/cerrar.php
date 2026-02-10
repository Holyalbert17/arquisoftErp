<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('finanzas') ?>">Finanzas</a></li>
<li class="breadcrumb-item"><a href="<?= base_url('finanzas/cajas') ?>">Cajas</a></li>
<li class="breadcrumb-item active">Cerrar Caja</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-lock mr-1"></i>
                    Cerrar Caja: <?= esc($caja['nombre']) ?>
                </h3>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="info-box">
                            <span class="info-box-icon bg-info"><i class="fas fa-wallet"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Saldo Inicial</span>
                                <span class="info-box-number"><?= number_format($caja['saldo_inicial'], 0, ',', '.') ?> Gs.</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-box">
                            <span class="info-box-icon bg-success"><i class="fas fa-calculator"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Saldo Esperado</span>
                                <span class="info-box-number"><?= number_format($saldo_esperado, 0, ',', '.') ?> Gs.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($ventas_pendientes)): ?>
                <div class="alert alert-warning alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    <h5><i class="icon fas fa-exclamation-triangle"></i> ¡Atención! Tiene ventas pendientes</h5>
                    <p>Existen <strong><?= count($ventas_pendientes) ?></strong> ventas en estado PENDIENTE creadas por usted. 
                    Se recomienda finalizarlas o anularlas antes de cerrar la caja para evitar diferencias.</p>
                    <ul class="mb-0">
                        <?php foreach(array_slice($ventas_pendientes, 0, 5) as $vp): ?>
                            <li>Venta #<?= esc($vp['numero_venta'] ?: $vp['id']) ?> - Total: <?= number_format($vp['total'], 0, ',', '.') ?> Gs.</li>
                        <?php endforeach; ?>
                        <?php if(count($ventas_pendientes) > 5): ?>
                            <li>... y <?= count($ventas_pendientes) - 5 ?> más.</li>
                        <?php endif; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <div class="row mb-3">

                <h5 class="mb-3">Resumen de Movimientos</h5>
                <table class="table table-bordered table-sm">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resumen as $item): ?>
                            <tr>
                                <td><?= esc(str_replace('_', ' ', $item['tipo_movimiento'])) ?></td>
                                <td class="currency"><?= number_format($item['total'], 0, ',', '.') ?> Gs.</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <form id="formCerrarCaja" class="mt-4">
                    <?= csrf_field() ?>
                    
                    <div class="form-group">
                        <label>Saldo Físico (Efectivo Real) <span class="text-danger">*</span></label>
                        <input type="text" name="saldo_fisico" class="form-control" 
                               required inputmode="numeric" autocomplete="off"
                               placeholder="Ej: 617.000">
                        <small class="form-text text-muted">
                            Cuente el efectivo real en la caja
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Observaciones</label>
                        <textarea name="observaciones" class="form-control" rows="3"
                                  placeholder="Observaciones sobre el cierre de caja"></textarea>
                    </div>

                    <div class="alert alert-info">
                        <strong>Diferencia:</strong>
                        <span id="diferenciaTipo">Sin diferencia</span>
                        <span id="diferenciaMonto">0 Gs.</span>
                    </div>
                </form>
            </div>
            <div class="card-footer">
                <button type="button" class="btn btn-warning" id="btnCerrar">
                    <i class="fas fa-lock"></i> Cerrar Caja
                </button>
                <a href="<?= base_url('finanzas/cajas') ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(document).ready(function() {
    const saldoEsperado = <?= $saldo_esperado ?>;

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

    function formatCurrency(amount) {
        const n = Math.round(parseGs(amount));
        return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ' Gs.';
    }

    function getDiferenciaUI(diff) {
        const abs = Math.abs(diff);
        if (abs < 1) {
            return { tipo: 'Sin diferencia', monto: '0 Gs.' };
        }
        if (diff > 0) {
            return { tipo: 'Sobrante:', monto: formatCurrency(abs) };
        }
        return { tipo: 'Faltante:', monto: formatCurrency(abs) };
    }

    $('input[name="saldo_fisico"]').on('input', function() {
        const cursorPos = this.selectionStart;
        $(this).val(formatGs($(this).val()));
        try { this.setSelectionRange(cursorPos, cursorPos); } catch (e) {}
    });
    
    $('input[name="saldo_fisico"]').on('input', function() {
        const saldoFisico = parseGs($(this).val());
        const diferencia = saldoFisico - saldoEsperado;
        const ui = getDiferenciaUI(diferencia);
        $('#diferenciaTipo').text(ui.tipo);
        $('#diferenciaMonto').text(ui.monto);
        
        const $alert = $('#diferenciaMonto').closest('.alert');
        $alert.removeClass('alert-info alert-success alert-warning alert-danger');
        if (Math.abs(diferencia) < 1) {
            $alert.addClass('alert-success');
        } else if (Math.abs(diferencia) > 10000) {
            $alert.addClass('alert-danger');
        } else {
            $alert.addClass('alert-warning');
        }
    });

    $('#btnCerrar').on('click', function() {
        const form = $('#formCerrarCaja');
        const formData = new FormData(form[0]);

        $.ajax({
            url: '<?= base_url("finanzas/cajas/cerrar/" . $caja['id']) ?>',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Caja Cerrada',
                        text: response.message,
                        showConfirmButton: false,
                        timer: 2000
                    }).then(() => {
                        window.location.href = response.data.redirect;
                    });
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Error al cerrar la caja', 'error');
            }
        });
    });
});
</script>
<?= $this->endSection() ?>
