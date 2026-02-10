<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('admin/empresas') ?>">Empresas</a></li>
<li class="breadcrumb-item active"><?= $empresa ? 'Editar' : 'Nueva' ?></li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-md-12">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-building mr-1"></i>
                    <?= $empresa ? 'Editar Empresa' : 'Nueva Empresa' ?>
                </h3>
            </div>
            <form id="empresaForm" method="post" enctype="multipart/form-data">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="nombre">Nombre de la Empresa *</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" 
                                       value="<?= ($empresa && isset($empresa['nombre'])) ? esc($empresa['nombre']) : '' ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="ruc">RUC *</label>
                                <input type="text" class="form-control" id="ruc" name="ruc" 
                                       value="<?= ($empresa && isset($empresa['ruc'])) ? esc($empresa['ruc']) : '' ?>" 
                                       placeholder="80012345-1" required>
                                <small class="form-text text-muted">Formato: XXXXXXXX-X</small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="direccion">Dirección</label>
                                <input type="text" class="form-control" id="direccion" name="direccion" 
                                       value="<?= ($empresa && isset($empresa['direccion'])) ? esc($empresa['direccion']) : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="ciudad">Ciudad</label>
                                <input type="text" class="form-control" id="ciudad" name="ciudad" 
                                       value="<?= ($empresa && isset($empresa['ciudad'])) ? esc($empresa['ciudad']) : '' ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="telefono">Teléfono</label>
                                <input type="text" class="form-control" id="telefono" name="telefono" 
                                       value="<?= $empresa ? esc($empresa['telefono']) : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= ($empresa && isset($empresa['email'])) ? esc($empresa['email']) : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="logo">Logo</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="logo" name="logo" accept="image/*">
                                    <label class="custom-file-label" for="logo">Seleccionar archivo</label>
                                </div>
                                <?php if ($empresa && $empresa['logo']): ?>
                                    <small class="form-text text-muted">
                                        Logo actual: <a href="<?= base_url('uploads/empresas/' . $empresa['logo']) ?>" target="_blank">Ver</a>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="porcentaje_iva">Porcentaje IVA *</label>
                                <select class="form-control" id="porcentaje_iva" name="porcentaje_iva" required>
                                    <option value="10.00" <?= ($empresa && isset($empresa['porcentaje_iva']) && $empresa['porcentaje_iva'] == 10.00) ? 'selected' : '' ?>>10%</option>
                                    <option value="5.00" <?= ($empresa && isset($empresa['porcentaje_iva']) && $empresa['porcentaje_iva'] == 5.00) ? 'selected' : '' ?>>5%</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="moneda_principal">Moneda Principal *</label>
                                <select class="form-control" id="moneda_principal" name="moneda_principal" required>
                                    <option value="PYG" <?= (!$empresa || (isset($empresa['moneda_principal']) && $empresa['moneda_principal'] == 'PYG')) ? 'selected' : '' ?>>Guaraníes (PYG)</option>
                                    <option value="USD" <?= ($empresa && isset($empresa['moneda_principal']) && $empresa['moneda_principal'] == 'USD') ? 'selected' : '' ?>>Dólares (USD)</option>
                                    <option value="BRL" <?= ($empresa && isset($empresa['moneda_principal']) && $empresa['moneda_principal'] == 'BRL') ? 'selected' : '' ?>>Reales (BRL)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="timbrado">Timbrado</label>
                                <input type="text" class="form-control" id="timbrado" name="timbrado" 
                                       value="<?= ($empresa && isset($empresa['timbrado'])) ? esc($empresa['timbrado']) : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="dias_plazo_retiro">Días Plazo Retiro</label>
                                <input type="number" class="form-control" id="dias_plazo_retiro" name="dias_plazo_retiro" 
                                       value="<?= ($empresa && isset($empresa['dias_plazo_retiro'])) ? $empresa['dias_plazo_retiro'] : 90 ?>" min="1">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="establecimiento">Establecimiento</label>
                                <input type="text" class="form-control" id="establecimiento" name="establecimiento" 
                                       value="<?= ($empresa && isset($empresa['establecimiento'])) ? esc($empresa['establecimiento']) : '001' ?>" maxlength="3">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="punto_expedicion">Punto de Expedición</label>
                                <input type="text" class="form-control" id="punto_expedicion" name="punto_expedicion" 
                                       value="<?= ($empresa && isset($empresa['punto_expedicion'])) ? esc($empresa['punto_expedicion']) : '001' ?>" maxlength="3">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="form-check mt-4">
                                    <input type="checkbox" class="form-check-input" id="aplica_iva" name="aplica_iva" 
                                           <?= (!$empresa || (isset($empresa['aplica_iva']) && $empresa['aplica_iva'])) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="aplica_iva">
                                        Aplica IVA
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Guardar
                    </button>
                    <a href="<?= base_url('admin/empresas') ?>" class="btn btn-default">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(document).ready(function() {
    // Formato de archivo
    $('.custom-file-input').on('change', function() {
        let fileName = $(this).val().split('\\').pop();
        $(this).siblings('.custom-file-label').addClass('selected').html(fileName);
    });

    // Validar RUC
    $('#ruc').on('blur', function() {
        const ruc = $(this).val();
        if (ruc && !/^[0-9]{8}-[0-9]{1}$/.test(ruc)) {
            Swal.fire({
                icon: 'warning',
                title: 'Formato de RUC inválido',
                text: 'El RUC debe tener el formato XXXXXXXX-X'
            });
        }
    });

    // Enviar formulario
    $('#empresaForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        <?php if ($empresa): ?>
        const url = '<?= base_url("admin/empresas/editar/" . $empresa["id"]) ?>';
        <?php else: ?>
        const url = '<?= base_url("admin/empresas/crear") ?>';
        <?php endif; ?>
        const method = 'POST';
        
        $.ajax({
            url: url,
            method: method,
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Éxito',
                        text: response.message,
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        window.location.href = response.data.redirect;
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message
                    });
                }
            },
            error: function(xhr) {
                let message = 'Error al procesar la solicitud';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: message
                });
            }
        });
    });
});
</script>
<?= $this->endSection() ?>
