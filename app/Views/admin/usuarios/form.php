<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('admin/usuarios') ?>">Usuarios</a></li>
<li class="breadcrumb-item active"><?= $usuario ? 'Editar' : 'Nuevo' ?></li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-md-12">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-user mr-1"></i>
                    <?= $usuario ? 'Editar Usuario' : 'Nuevo Usuario' ?>
                </h3>
            </div>
            <form id="usuarioForm" method="post" enctype="multipart/form-data">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="empresa_id">Empresa *</label>
                                <select class="form-control select2" id="empresa_id" name="empresa_id" required <?= $empresa_id ? 'disabled' : '' ?>>
                                    <option value="">Seleccionar...</option>
                                    <?php foreach ($empresas as $emp): ?>
                                        <option value="<?= $emp['id'] ?>" 
                                                <?= ($usuario && $usuario['empresa_id'] == $emp['id']) || $empresa_id == $emp['id'] ? 'selected' : '' ?>>
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
                                <label for="sucursal_id">Sucursal</label>
                                <select class="form-control select2" id="sucursal_id" name="sucursal_id">
                                    <option value="">Seleccionar...</option>
                                    <?php foreach ($sucursales as $suc): ?>
                                        <option value="<?= $suc['id'] ?>" 
                                                <?= $usuario && $usuario['sucursal_id'] == $suc['id'] ? 'selected' : '' ?>>
                                            <?= esc($suc['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="rol_id">Rol *</label>
                                <select class="form-control" id="rol_id" name="rol_id" required>
                                    <option value="">Seleccionar...</option>
                                    <?php foreach ($roles as $rol): ?>
                                        <option value="<?= $rol['id'] ?>" 
                                                <?= $usuario && $usuario['rol_id'] == $rol['id'] ? 'selected' : '' ?>>
                                            <?= esc($rol['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="nombre">Nombre *</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" 
                                       value="<?= $usuario ? esc($usuario['nombre']) : '' ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="apellido">Apellido *</label>
                                <input type="text" class="form-control" id="apellido" name="apellido" 
                                       value="<?= $usuario ? esc($usuario['apellido']) : '' ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="ci">Cédula de Identidad *</label>
                                <input type="text" class="form-control" id="ci" name="ci" 
                                       value="<?= $usuario ? esc($usuario['ci']) : '' ?>" 
                                       pattern="[0-9]{1,8}" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="email">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= $usuario ? esc($usuario['email']) : '' ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="telefono">Teléfono</label>
                                <input type="text" class="form-control" id="telefono" name="telefono" 
                                       value="<?= $usuario ? esc($usuario['telefono']) : '' ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="username">Usuario *</label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?= $usuario ? esc($usuario['username']) : '' ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="password">Contraseña <?= $usuario ? '(dejar vacío para no cambiar)' : '*' ?></label>
                                <input type="password" class="form-control" id="password" name="password" 
                                       <?= !$usuario ? 'required' : '' ?> minlength="8">
                                <small class="form-text text-muted">Mínimo 8 caracteres</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="foto_perfil">Foto de Perfil</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="foto_perfil" name="foto_perfil" accept="image/*">
                                    <label class="custom-file-label" for="foto_perfil">Seleccionar archivo</label>
                                </div>
                                <?php if ($usuario && $usuario['foto_perfil']): ?>
                                    <small class="form-text text-muted">
                                        Foto actual: <a href="<?= base_url('uploads/usuarios/' . $usuario['foto_perfil']) ?>" target="_blank">Ver</a>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="direccion">Dirección</label>
                                <input type="text" class="form-control" id="direccion" name="direccion" 
                                       value="<?= $usuario ? esc($usuario['direccion']) : '' ?>">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Guardar
                    </button>
                    <a href="<?= base_url('admin/usuarios') ?>" class="btn btn-default">
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
            $.ajax({
                url: '<?= base_url("admin/sucursales/getPorEmpresa") ?>',
                method: 'POST',
                data: { empresa_id: empresaId },
                dataType: 'json',
                success: function(response) {
                    const select = $('#sucursal_id');
                    select.empty().append('<option value="">Seleccionar...</option>');
                    if (response.success && response.data) {
                        response.data.forEach(function(sucursal) {
                            select.append(`<option value="${sucursal.id}">${sucursal.nombre}</option>`);
                        });
                    }
                }
            });
        }
    });

    // Formato de archivo
    $('.custom-file-input').on('change', function() {
        let fileName = $(this).val().split('\\').pop();
        $(this).siblings('.custom-file-label').addClass('selected').html(fileName);
    });

    // Enviar formulario
    $('#usuarioForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const url = '<?= $usuario ? base_url("admin/usuarios/editar/" . $usuario["id"]) : base_url("admin/usuarios/crear") ?>';
        
        $.ajax({
            url: url,
            method: 'POST',
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
