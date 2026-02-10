<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('finanzas') ?>">Finanzas</a></li>
<li class="breadcrumb-item"><a href="<?= base_url('finanzas/cajas') ?>">Cajas</a></li>
<li class="breadcrumb-item"><a href="<?= base_url('finanzas/cajas/ver/' . $caja['id']) ?>">Detalle</a></li>
<li class="breadcrumb-item active">Arqueo</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3 class="card-title">
                    <i class="fas fa-calculator mr-1"></i>
                    Arqueo de Caja: <?= esc($caja['nombre']) ?>
                </h3>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <strong>Total del Sistema:</strong> <?= number_format($total_sistema, 0, ',', '.') ?> Gs.
                    <br>
                    <small>Desde: <?= date('d/m/Y H:i', strtotime($caja['fecha_apertura'])) ?></small>
                </div>

                <form id="formArqueo">
                    <?= csrf_field() ?>
                    <input type="hidden" name="es_final" id="esFinal" value="0">

                    <!-- EFECTIVO -->
                    <div class="card mb-3">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-money-bill-wave"></i> Efectivo</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-light">
                                <strong>Esperado del Sistema:</strong>
                                <?= number_format($totales_esperados['EFECTIVO'], 0, ',', '.') ?> Gs.
                            </div>

                            <h6>Conteo de Denominaciones</h6>
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Denominación</th>
                                        <th width="120">Cantidad</th>
                                        <th width="150">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $denominaciones = [
                                        '100000',
                                        '50000',
                                        '20000',
                                        '10000',
                                        '5000',
                                        '2000',
                                        '1000',
                                        '500',
                                        '100',
                                        '50'
                                    ];
                                    foreach ($denominaciones as $denom):
                                        ?>
                                        <tr>
                                            <td><strong><?= number_format($denom, 0, ',', '.') ?> Gs.</strong></td>
                                            <td>
                                                <input type="number" class="form-control form-control-sm cantidad-denom"
                                                    data-denominacion="<?= $denom ?>" min="0" value="0"
                                                    onchange="calcularTotales()">
                                            </td>
                                            <td class="subtotal-denom">0 Gs.</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="bg-light">
                                    <tr>
                                        <td colspan="2" class="text-right"><strong>TOTAL EFECTIVO:</strong></td>
                                        <td><strong id="totalEfectivo">0 Gs.</strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- TARJETAS -->
                    <div class="card mb-3">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-credit-card"></i> Tarjetas</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Tarjeta Débito</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">Esperado:
                                                    <?= number_format($totales_esperados['TARJETA_DEBITO'], 0, ',', '.') ?> Gs.</span>
                                            </div>
                                            <input type="text" id="tarjetaDebito" class="form-control medio-pago money-gs"
                                                data-medio="TARJETA_DEBITO" inputmode="numeric" autocomplete="off"
                                                value="<?= number_format($totales_esperados['TARJETA_DEBITO'], 0, ',', '.') ?>"
                                                onchange="calcularTotales()">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Tarjeta Crédito</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">Esperado:
                                                    <?= number_format($totales_esperados['TARJETA_CREDITO'], 0, ',', '.') ?> Gs.</span>
                                            </div>
                                            <input type="text" id="tarjetaCredito" class="form-control medio-pago money-gs"
                                                data-medio="TARJETA_CREDITO" inputmode="numeric" autocomplete="off"
                                                value="<?= number_format($totales_esperados['TARJETA_CREDITO'], 0, ',', '.') ?>"
                                                onchange="calcularTotales()">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-light p-2">
                                <strong>TOTAL TARJETAS:</strong> <span id="totalTarjetas">0 Gs.</span>
                            </div>
                        </div>
                    </div>

                    <!-- TRANSFERENCIAS -->
                    <div class="card mb-3">
                        <div class="card-header bg-warning">
                            <h5 class="mb-0"><i class="fas fa-exchange-alt"></i> Transferencias</h5>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>Total Transferencias</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">Esperado:
                                            <?= number_format($totales_esperados['TRANSFERENCIA'], 0, ',', '.') ?> Gs.</span>
                                    </div>
                                    <input type="text" id="transferencias" class="form-control medio-pago money-gs"
                                        data-medio="TRANSFERENCIA" inputmode="numeric" autocomplete="off"
                                        value="<?= number_format($totales_esperados['TRANSFERENCIA'], 0, ',', '.') ?>" onchange="calcularTotales()">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CHEQUES -->
                    <div class="card mb-3">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0"><i class="fas fa-money-check"></i> Cheques</h5>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>Total Cheques</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">Esperado:
                                            <?= number_format($totales_esperados['CHEQUE'], 0, ',', '.') ?> Gs.</span>
                                    </div>
                                    <input type="text" id="cheques" class="form-control medio-pago money-gs"
                                        data-medio="CHEQUE" inputmode="numeric" autocomplete="off" value="<?= number_format($totales_esperados['CHEQUE'], 0, ',', '.') ?>"
                                        onchange="calcularTotales()">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RESUMEN TOTAL -->
                    <div class="card">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"><i class="fas fa-calculator"></i> Resumen Total</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered">
                                <tr>
                                    <td><strong>Total del Sistema:</strong></td>
                                    <td class="text-right"><strong><?= number_format($total_sistema, 0, ',', '.') ?> Gs.</strong></td>
                                </tr>
                                <tr class="table-success">
                                    <td><strong>Total Físico Contado:</strong></td>
                                    <td class="text-right"><strong id="totalFisico">0 Gs.</strong></td>
                                </tr>
                                <tr id="rowDiferencia">
                                    <td><strong>Diferencia:</strong></td>
                                    <td class="text-right">
                                        <strong id="diferenciaTipo">Sin diferencia</strong>
                                        <strong id="diferenciaMonto">0 Gs.</strong>
                                    </td>
                                </tr>
                            </table>

                            <input type="hidden" name="saldo_fisico" id="saldoFisico">
                            <input type="hidden" name="total_sistema" value="<?= $total_sistema ?>">

                            <div class="form-group">
                                <label>Observaciones</label>
                                <textarea name="observaciones" class="form-control" rows="3"
                                    placeholder="Ingrese observaciones sobre el arqueo..."></textarea>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="card-footer">
                <button type="button" class="btn btn-secondary btn-lg" id="btnArqueoControl">
                    <i class="fas fa-clipboard-check"></i> Arqueo de Control
                </button>
                <button type="button" class="btn btn-primary btn-lg" id="btnArqueoFinal">
                    <i class="fas fa-check"></i> Arqueo Final
                </button>
                <a href="<?= base_url('finanzas/cajas/ver/' . $caja['id']) ?>" class="btn btn-secondary btn-lg">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    const totalesEsperados = <?= json_encode($totales_esperados) ?>;
    const totalSistema = <?= $total_sistema ?>;

    $(document).ready(function () {
        $('.money-gs').on('input', function () {
            const cursorPos = this.selectionStart;
            $(this).val(formatGs($(this).val()));
            try { this.setSelectionRange(cursorPos, cursorPos); } catch (e) {}
        });
        calcularTotales();
    });

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
        return formatGs(amount) + ' Gs.';
    }

    function getDiferenciaUI(diff) {
        const abs = Math.abs(diff);
        if (abs < 1) {
            return { tipo: 'Sin diferencia', monto: '0 Gs.' };
        }
        if (diff > 0) {
            return { tipo: 'Sobrante', monto: formatCurrency(abs) };
        }
        return { tipo: 'Faltante', monto: formatCurrency(abs) };
    }

    function calcularTotales() {
        // Calcular efectivo
        let totalEfectivo = 0;
        const denominacionesEfectivo = [];

        $('.cantidad-denom').each(function () {
            const cantidad = parseInt($(this).val()) || 0;
            const denominacion = parseFloat($(this).data('denominacion'));
            const subtotal = cantidad * denominacion;

            $(this).closest('tr').find('.subtotal-denom').text(formatCurrency(subtotal));

            if (cantidad > 0) {
                totalEfectivo += subtotal;
                denominacionesEfectivo.push({
                    tipo_medio_pago: 'EFECTIVO',
                    denominacion: denominacion.toString(),
                    cantidad: cantidad,
                    subtotal: subtotal
                });
            }
        });

        $('#totalEfectivo').text(formatCurrency(totalEfectivo));

        // Calcular tarjetas
        const totalDebito = parseGs($('#tarjetaDebito').val());
        const totalCredito = parseGs($('#tarjetaCredito').val());
        const totalTarjetas = totalDebito + totalCredito;
        $('#totalTarjetas').text(formatCurrency(totalTarjetas));

        // Obtener transferencias y cheques
        const totalTransferencias = parseGs($('#transferencias').val());
        const totalCheques = parseGs($('#cheques').val());

        // Calcular total físico
        const totalFisico = totalEfectivo + totalTarjetas + totalTransferencias + totalCheques;
        $('#totalFisico').text(formatCurrency(totalFisico));
        $('#saldoFisico').val(totalFisico);

        // Calcular diferencia
        const diferencia = totalFisico - totalSistema;
        const ui = getDiferenciaUI(diferencia);
        $('#diferenciaTipo').text(ui.tipo);
        $('#diferenciaMonto').text(ui.monto);

        // Colorear diferencia
        const $rowDif = $('#rowDiferencia');
        $rowDif.removeClass('table-success table-danger table-warning');
        if (Math.abs(diferencia) < 1) {
            $rowDif.addClass('table-success');
        } else if (Math.abs(diferencia) > 10000) {
            $rowDif.addClass('table-danger');
        } else {
            $rowDif.addClass('table-warning');
        }

        // Guardar datos para envío
        window.datosArqueo = {
            efectivo: denominacionesEfectivo,
            tarjeta_debito: totalDebito,
            tarjeta_credito: totalCredito,
            transferencias: totalTransferencias,
            cheques: totalCheques
        };
    }

    function enviarArqueo(esFinal) {
        if (!window.datosArqueo) {
            Swal.fire('Error', 'Debe completar el arqueo', 'error');
            return;
        }

        // Preparar datos para envío
        const detalles = [];

        // Agregar denominaciones de efectivo
        window.datosArqueo.efectivo.forEach(item => {
            detalles.push(item);
        });

        // Agregar tarjetas
        if (window.datosArqueo.tarjeta_debito > 0) {
            detalles.push({
                tipo_medio_pago: 'TARJETA_DEBITO',
                denominacion: 'TARJETA_DEBITO',
                cantidad: 1,
                subtotal: window.datosArqueo.tarjeta_debito
            });
        }

        if (window.datosArqueo.tarjeta_credito > 0) {
            detalles.push({
                tipo_medio_pago: 'TARJETA_CREDITO',
                denominacion: 'TARJETA_CREDITO',
                cantidad: 1,
                subtotal: window.datosArqueo.tarjeta_credito
            });
        }

        // Agregar transferencias
        if (window.datosArqueo.transferencias > 0) {
            detalles.push({
                tipo_medio_pago: 'TRANSFERENCIA',
                denominacion: 'TRANSFERENCIA',
                cantidad: 1,
                subtotal: window.datosArqueo.transferencias
            });
        }

        // Agregar cheques
        if (window.datosArqueo.cheques > 0) {
            detalles.push({
                tipo_medio_pago: 'CHEQUE',
                denominacion: 'CHEQUE',
                cantidad: 1,
                subtotal: window.datosArqueo.cheques
            });
        }

        const form = $('#formArqueo');
        const formData = new FormData(form[0]);
        formData.set('saldo_fisico', parseGs($('#saldoFisico').val()));
        formData.set('es_final', esFinal ? '1' : '0');
        formData.append('denominaciones', JSON.stringify(detalles));

        $.ajax({
            url: '<?= base_url("finanzas/cajas/arqueo/" . $caja['id']) ?>',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Arqueo Procesado',
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
            error: function () {
                Swal.fire('Error', 'Error al procesar el arqueo', 'error');
            }
        });
    }

    $('#btnArqueoControl').on('click', function () {
        enviarArqueo(false);
    });

    $('#btnArqueoFinal').on('click', function () {
        Swal.fire({
            title: 'Confirmar Arqueo Final',
            text: 'El arqueo final ajusta el saldo de la caja y solo puede realizarse una vez por caja abierta.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, registrar arqueo final',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                enviarArqueo(true);
            }
        });
    });
</script>
<?= $this->endSection() ?>