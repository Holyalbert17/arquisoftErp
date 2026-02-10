<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('configuracion') ?>">Configuración</a></li>
<li class="breadcrumb-item"><a href="<?= base_url('configuracion/talonarios') ?>">Talonarios</a></li>
<li class="breadcrumb-item active"><?= $talonario ? 'Editar' : 'Nuevo' ?> Talonario</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<form id="formTalonario" method="POST" action="<?= base_url('configuracion/talonarios/' . ($talonario ? 'actualizar/' . $talonario['id'] : 'guardar')) ?>">
    <?= csrf_field() ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-file-invoice mr-1"></i>
                <?= $talonario ? 'Editar Talonario' : 'Nuevo Talonario' ?>
            </h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="sucursal_id">Sucursal <span class="text-danger">*</span></label>
                        <select name="sucursal_id" id="sucursal_id" class="form-control" required>
                            <option value="">Seleccione...</option>
                            <?php if (!empty($sucursales)): ?>
                                <?php foreach ($sucursales as $s): ?>
                                    <option value="<?= esc($s['id']) ?>" <?= (!empty($sucursal_id) && (int) $sucursal_id === (int) $s['id']) ? 'selected' : '' ?>>
                                        <?= esc($s['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="tipo_comprobante">Tipo de Comprobante <span class="text-danger">*</span></label>
                        <select name="tipo_comprobante" id="tipo_comprobante" class="form-control" required>
                            <option value="">Seleccione...</option>
                            <option value="FACTURA" <?= $talonario && $talonario['tipo_comprobante'] == 'FACTURA' ? 'selected' : '' ?>>Factura</option>
                            <option value="FACTURA_ELECTRONICA" <?= $talonario && $talonario['tipo_comprobante'] == 'FACTURA_ELECTRONICA' ? 'selected' : '' ?>>Factura Electrónica</option>
                            <option value="TICKET" <?= $talonario && $talonario['tipo_comprobante'] == 'TICKET' ? 'selected' : '' ?>>Ticket</option>
                            <option value="NOTA_CREDITO" <?= $talonario && $talonario['tipo_comprobante'] == 'NOTA_CREDITO' ? 'selected' : '' ?>>Nota de Crédito</option>
                            <option value="NOTA_DEBITO" <?= $talonario && $talonario['tipo_comprobante'] == 'NOTA_DEBITO' ? 'selected' : '' ?>>Nota de Débito</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="timbrado">Timbrado <span class="text-danger">*</span></label>
                        <input type="text" name="timbrado" id="timbrado" class="form-control" 
                               value="<?= $talonario ? esc($talonario['timbrado']) : '' ?>" 
                               required maxlength="20">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="establecimiento">Establecimiento <span class="text-danger">*</span></label>
                        <input type="text" name="establecimiento" id="establecimiento" class="form-control" 
                               value="<?= $talonario ? esc($talonario['establecimiento']) : '' ?>" 
                               required maxlength="3" pattern="[0-9]{3}">
                        <small class="form-text text-muted">3 dígitos</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="punto_expedicion">Punto de Expedición <span class="text-danger">*</span></label>
                        <input type="text" name="punto_expedicion" id="punto_expedicion" class="form-control" 
                               value="<?= $talonario ? esc($talonario['punto_expedicion']) : '' ?>" 
                               required maxlength="3" pattern="[0-9]{3}">
                        <small class="form-text text-muted">3 dígitos</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="estado">Estado</label>
                        <select name="estado" id="estado" class="form-control" <?= !$talonario ? 'disabled' : '' ?>>
                            <option value="ACTIVO" <?= $talonario && $talonario['estado'] == 'ACTIVO' ? 'selected' : '' ?>>Activo</option>
                            <option value="VENCIDO" <?= $talonario && $talonario['estado'] == 'VENCIDO' ? 'selected' : '' ?>>Vencido</option>
                            <option value="AGOTADO" <?= $talonario && $talonario['estado'] == 'AGOTADO' ? 'selected' : '' ?>>Agotado</option>
                            <option value="ANULADO" <?= $talonario && $talonario['estado'] == 'ANULADO' ? 'selected' : '' ?>>Anulado</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="numero_desde">Número Desde <span class="text-danger">*</span></label>
                        <input type="number" name="numero_desde" id="numero_desde" class="form-control" 
                               value="<?= $talonario ? esc($talonario['numero_desde']) : '1' ?>" 
                               required min="1">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="numero_hasta">Número Hasta <span class="text-danger">*</span></label>
                        <input type="number" name="numero_hasta" id="numero_hasta" class="form-control" 
                               value="<?= $talonario ? esc($talonario['numero_hasta']) : '' ?>" 
                               required min="1">
                    </div>
                </div>
                <?php if ($talonario): ?>
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="numero_actual">Número Actual <span class="text-danger">*</span></label>
                        <input type="number" name="numero_actual" id="numero_actual" class="form-control" 
                               value="<?= esc($talonario['numero_actual']) ?>" 
                               required min="<?= $talonario['numero_desde'] ?>" 
                               max="<?= $talonario['numero_hasta'] ?>">
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="fecha_inicio_vigencia">Fecha Inicio Vigencia <span class="text-danger">*</span></label>
                        <input type="date" name="fecha_inicio_vigencia" id="fecha_inicio_vigencia" class="form-control" 
                               value="<?= $talonario ? date('Y-m-d', strtotime($talonario['fecha_inicio_vigencia'])) : date('Y-m-d') ?>" 
                               required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="fecha_fin_vigencia">Fecha Fin Vigencia <span class="text-danger">*</span></label>
                        <input type="date" name="fecha_fin_vigencia" id="fecha_fin_vigencia" class="form-control" 
                               value="<?= $talonario ? date('Y-m-d', strtotime($talonario['fecha_fin_vigencia'])) : date('Y-m-d', strtotime('+1 year')) ?>" 
                               required>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="observaciones">Observaciones</label>
                <textarea name="observaciones" id="observaciones" class="form-control" rows="3"><?= $talonario ? esc($talonario['observaciones']) : '' ?></textarea>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Guardar
            </button>
            <a href="<?= base_url('configuracion/talonarios') ?>" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancelar
            </a>
        </div>
    </div>
</form>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(document).ready(function() {
    $('#formTalonario').on('submit', function(e) {
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
                Swal.fire('Error', response?.message || 'Error al guardar el talonario', 'error');
            }
        });
    });
});
</script>
<?= $this->endSection() ?>
