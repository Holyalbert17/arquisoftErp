<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('ventas') ?>">Ventas</a></li>
<li class="breadcrumb-item"><a href="<?= base_url('ventas/devoluciones') ?>">Devoluciones</a></li>
<li class="breadcrumb-item active">Nueva Devolución</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-undo mr-1"></i>
                    Nueva Devolución - Venta #<?= esc($venta['numero_venta']) ?>
                </h3>
            </div>
            <div class="card-body">
                <form id="formDevolucion">
                    <?= csrf_field() ?>
                    <input type="hidden" name="venta_id" value="<?= $venta['id'] ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Fecha de Venta:</strong> <?= date('d/m/Y H:i', strtotime($venta['fecha_venta'])) ?><br>
                            <strong>Total Venta:</strong> <?= number_format((float)$venta['total'], 0, ',', '.') ?> Gs.
                        </div>
                        <div class="col-md-6">
                            <strong>Cliente:</strong> 
                            <?php
                            $clienteModel = new \App\Models\ClienteModel();
                            $cliente = $clienteModel->find($venta['cliente_id']);
                            if ($cliente) {
                                echo esc($clienteModel->getNombreCompleto($cliente));
                            } else {
                                echo 'Walk-in';
                            }
                            ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Motivo de Devolución <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="motivo" rows="3" required 
                                  placeholder="Describa el motivo de la devolución (mínimo 10 caracteres)"></textarea>
                    </div>

                    <div class="form-group">
                        <label>Tipo de Devolución</label>
                        <select class="form-control" name="tipo_devolucion">
                            <option value="DEVOLUCION_PARCIAL">Parcial</option>
                            <option value="DEVOLUCION_TOTAL">Total</option>
                        </select>
                    </div>

                    <h5 class="mt-4 mb-3">Productos de la Venta</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Cantidad Vendida</th>
                                    <th>Cantidad a Devolver</th>
                                    <th>Precio Unit.</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($detalles as $detalle): ?>
                                    <tr>
                                        <td><?= esc($detalle['descripcion']) ?></td>
                                        <td><?= number_format($detalle['cantidad'], 2) ?></td>
                                        <td>
                                            <input type="number" 
                                                   class="form-control form-control-sm cantidad-devolver" 
                                                   data-detalle-id="<?= $detalle['id'] ?>"
                                                   data-cantidad-max="<?= $detalle['cantidad'] ?>"
                                                   data-precio="<?= $detalle['precio_unitario'] ?>"
                                                   data-total="<?= $detalle['total'] ?>"
                                                   min="0" 
                                                   max="<?= $detalle['cantidad'] ?>" 
                                                   step="0.01" 
                                                   value="0"
                                                   style="width: 100px;">
                                        </td>
                                        <td class="currency"><?= formatGs($detalle['precio_unitario']) ?></td>
                                        <td class="currency" id="total-detalle-<?= $detalle['id'] ?>">0 Gs.</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-primary">
                                    <td colspan="4" class="text-right"><h4><strong>Total Devolución:</strong></h4></td>
                                    <td class="currency"><h4><strong id="totalDevolucion">0 Gs.</strong></h4></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </form>
            </div>
            <div class="card-footer">
                <button type="button" class="btn btn-primary" id="btnProcesarDevolucion">
                    <i class="fas fa-check"></i> Procesar Devolución
                </button>
                <a href="<?= base_url('ventas/devoluciones') ?>" class="btn btn-secondary">
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
    // Calcular totales al cambiar cantidad
    $(document).on('change', '.cantidad-devolver', function() {
        calcularTotales();
    });

    // Procesar devolución
    $('#btnProcesarDevolucion').on('click', function() {
        procesarDevolucion();
    });
});

function calcularTotales() {
    let totalDevolucion = 0;
    const productos = [];

    $('.cantidad-devolver').each(function() {
        const cantidad = parseFloat($(this).val()) || 0;
        const cantidadMax = parseFloat($(this).data('cantidad-max'));
        const detalleId = $(this).data('detalle-id');
        const precio = parseFloat($(this).data('precio'));
        const totalOriginal = parseFloat($(this).data('total'));

        if (cantidad > 0 && cantidad <= cantidadMax) {
            const proporcion = cantidad / cantidadMax;
            const totalLinea = totalOriginal * proporcion;
            totalDevolucion += totalLinea;

            $('#total-detalle-' + detalleId).text(formatCurrency(totalLinea));

            productos.push({
                detalle_id: detalleId,
                cantidad: cantidad
            });
        } else {
            $('#total-detalle-' + detalleId).text('0 Gs.');
        }
    });

    $('#totalDevolucion').text(formatCurrency(totalDevolucion));
    return productos;
}

function procesarDevolucion() {
    const productos = calcularTotales();
    
    if (productos.length === 0) {
        Swal.fire('Advertencia', 'Debe seleccionar al menos un producto a devolver', 'warning');
        return;
    }

    const motivo = $('textarea[name="motivo"]').val().trim();
    if (!motivo || motivo.length < 10) {
        Swal.fire('Error', 'Debe ingresar un motivo de devolución (mínimo 10 caracteres)', 'error');
        return;
    }

    const data = {
        venta_id: $('input[name="venta_id"]').val(),
        productos: JSON.stringify(productos),
        motivo: motivo,
        tipo_devolucion: $('select[name="tipo_devolucion"]').val()
    };

    $.ajax({
        url: '<?= base_url("ventas/devoluciones/guardar") ?>',
        method: 'POST',
        data: data,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Devolución Procesada',
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
        error: function(xhr) {
            Swal.fire('Error', 'Error al procesar la devolución', 'error');
        }
    });
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('es-PY', {
        style: 'currency',
        currency: 'PYG',
        minimumFractionDigits: 0
    }).format(amount);
}
</script>
<?= $this->endSection() ?>
