<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('admin/sucursales') ?>">Sucursales</a></li>
<li class="breadcrumb-item active"><?= $sucursal ? 'Editar' : 'Nueva' ?></li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-md-12">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-store mr-1"></i>
                    <?= $sucursal ? 'Editar Sucursal' : 'Nueva Sucursal' ?>
                </h3>
            </div>
            <form id="sucursalForm" method="post">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="empresa_id">Empresa *</label>
                                <select class="form-control select2" id="empresa_id" name="empresa_id" required <?= $empresa_id ? 'disabled' : '' ?>>
                                    <option value="">Seleccionar...</option>
                                    <?php foreach ($empresas as $emp): ?>
                                        <option value="<?= $emp['id'] ?>" 
                                                <?= ($sucursal && $sucursal['empresa_id'] == $emp['id']) || $empresa_id == $emp['id'] ? 'selected' : '' ?>>
                                            <?= esc($emp['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($empresa_id): ?>
                                    <input type="hidden" name="empresa_id" value="<?= $empresa_id ?>">
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="nombre">Nombre de la Sucursal *</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" 
                                       value="<?= $sucursal ? esc($sucursal['nombre']) : '' ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="codigo">Código</label>
                                <input type="text" class="form-control" id="codigo" name="codigo" 
                                       value="<?= $sucursal ? esc($sucursal['codigo']) : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="codigo_establecimiento">Código Establecimiento</label>
                                <input type="text" class="form-control" id="codigo_establecimiento" name="codigo_establecimiento" 
                                       value="<?= $sucursal ? esc($sucursal['codigo_establecimiento']) : '' ?>" maxlength="3">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="departamento">Departamento *</label>
                                <select class="form-control" id="departamento" name="departamento" required>
                                    <option value="">Seleccionar...</option>
                                    <?php 
                                    $departamentos = ['CAPITAL', 'CONCEPCIÓN', 'SAN PEDRO', 'CORDILLERA', 'GUAIRÁ', 
                                                     'CAAAGUAZÚ', 'CAAZAPÁ', 'ITAPÚA', 'MISIONES', 'PARAGUARÍ', 
                                                     'ALTO PARANÁ', 'CENTRAL', 'ÑEEMBUCÚ', 'AMAMBAY', 'CANINDEYÚ', 
                                                     'PRESIDENTE HAYES', 'BOQUERÓN', 'ALTO PARAGUAY'];
                                    foreach ($departamentos as $dept): 
                                    ?>
                                        <option value="<?= $dept ?>" <?= $sucursal && $sucursal['departamento'] == $dept ? 'selected' : '' ?>>
                                            <?= $dept ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="direccion">Dirección</label>
                                <input type="text" class="form-control" id="direccion" name="direccion" 
                                       value="<?= $sucursal ? esc($sucursal['direccion']) : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="ciudad">Ciudad</label>
                                <input type="text" class="form-control" id="ciudad" name="ciudad" 
                                       value="<?= $sucursal ? esc($sucursal['ciudad']) : '' ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="telefono">Teléfono</label>
                                <input type="text" class="form-control" id="telefono" name="telefono" 
                                       value="<?= $sucursal ? esc($sucursal['telefono']) : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= $sucursal ? esc($sucursal['email']) : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="responsable">Responsable</label>
                                <input type="text" class="form-control" id="responsable" name="responsable" 
                                       value="<?= $sucursal ? esc($sucursal['responsable']) : '' ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="horario_apertura">Horario Apertura</label>
                                <input type="time" class="form-control" id="horario_apertura" name="horario_apertura" 
                                       value="<?= $sucursal ? ($sucursal['horario_apertura'] ? date('H:i', strtotime($sucursal['horario_apertura'])) : '') : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="horario_cierre">Horario Cierre</label>
                                <input type="time" class="form-control" id="horario_cierre" name="horario_cierre" 
                                       value="<?= $sucursal ? ($sucursal['horario_cierre'] ? date('H:i', strtotime($sucursal['horario_cierre'])) : '') : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="latitud">Latitud</label>
                                <input type="text" class="form-control" id="latitud" name="latitud" 
                                       value="<?= $sucursal ? esc($sucursal['latitud']) : '' ?>" step="any">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="longitud">Longitud</label>
                                <input type="text" class="form-control" id="longitud" name="longitud" 
                                       value="<?= $sucursal ? esc($sucursal['longitud']) : '' ?>" step="any">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="es_matriz" name="es_matriz" 
                                           <?= $sucursal && $sucursal['es_matriz'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="es_matriz">
                                        Sucursal Matriz
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
                    <a href="<?= base_url('admin/sucursales') ?>" class="btn btn-default">
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
    $('.select2').select2({
        theme: 'bootstrap-5'
    });

    // Cargar sucursales cuando cambia la empresa
    $('#empresa_id').on('change', function() {
        const empresaId = $(this).val();
        if (empresaId) {
            // Aquí se podría cargar las sucursales de la empresa si fuera necesario
        }
    });

    // Enviar formulario
    $('#sucursalForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        const url = '<?= $sucursal ? base_url("admin/sucursales/editar/" . $sucursal["id"]) : base_url("admin/sucursales/crear") ?>';
        
        $.ajax({
            url: url,
            method: 'POST',
            data: formData,
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
