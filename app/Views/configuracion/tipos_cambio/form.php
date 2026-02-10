<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('configuracion') ?>">Configuración</a></li>
<li class="breadcrumb-item"><a href="<?= base_url('configuracion/tipos-cambio') ?>">Tipos de Cambio</a></li>
<li class="breadcrumb-item active">Nuevo Tipo de Cambio</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<form id="formTipoCambio" method="POST" action="<?= base_url('configuracion/tipos-cambio/guardar') ?>">
    <?= csrf_field() ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-exchange-alt mr-1"></i>
                Nuevo Tipo de Cambio
            </h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="fecha">Fecha <span class="text-danger">*</span></label>
                        <input type="date" name="fecha" id="fecha" class="form-control" 
                               value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="moneda_destino">Moneda <span class="text-danger">*</span></label>
                        <select name="moneda_destino" id="moneda_destino" class="form-control" required>
                            <option value="">Seleccione...</option>
                            <option value="USD">Dólar Estadounidense (USD)</option>
                            <option value="BRL">Real Brasileño (BRL)</option>
                        </select>
                        <small class="form-text text-muted">Moneda de destino (origen: PYG)</small>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="compra">Tipo de Cambio Compra <span class="text-danger">*</span></label>
                        <input type="number" name="compra" id="compra" class="form-control" 
                               step="0.01" min="0" required>
                        <small class="form-text text-muted">Gs. por unidad de moneda</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="venta">Tipo de Cambio Venta <span class="text-danger">*</span></label>
                        <input type="number" name="venta" id="venta" class="form-control" 
                               step="0.01" min="0" required>
                        <small class="form-text text-muted">Gs. por unidad de moneda</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="promedio">Tipo de Cambio Promedio <span class="text-danger">*</span></label>
                        <input type="number" name="promedio" id="promedio" class="form-control" 
                               step="0.01" min="0" required>
                        <small class="form-text text-muted">Gs. por unidad de moneda</small>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="fuente">Fuente</label>
                        <select name="fuente" id="fuente" class="form-control">
                            <option value="MANUAL">Manual</option>
                            <option value="BCP">Banco Central del Paraguay (BCP)</option>
                            <option value="SET">Subsecretaría de Estado de Tributación (SET)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="observaciones">Observaciones</label>
                <textarea name="observaciones" id="observaciones" class="form-control" rows="3"></textarea>
            </div>

            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>Nota:</strong> El tipo de cambio promedio se calcula automáticamente como (compra + venta) / 2, 
                pero puede ser ajustado manualmente si es necesario.
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Guardar
            </button>
            <a href="<?= base_url('configuracion/tipos-cambio') ?>" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancelar
            </a>
        </div>
    </div>
</form>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(document).ready(function() {
    // Calcular promedio automáticamente
    $('#compra, #venta').on('input', function() {
        const compra = parseFloat($('#compra').val()) || 0;
        const venta = parseFloat($('#venta').val()) || 0;
        if (compra > 0 && venta > 0) {
            const promedio = (compra + venta) / 2;
            $('#promedio').val(promedio.toFixed(2));
        }
    });

    $('#formTipoCambio').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire('Éxito', response.message, 'success').then(() => {
                        window.location.href = response.data.redirect;
                    });
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                Swal.fire('Error', response?.message || 'Error al guardar el tipo de cambio', 'error');
            }
        });
    });
});
</script>
<?= $this->endSection() ?>
