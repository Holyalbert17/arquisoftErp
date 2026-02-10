<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('admin/roles') ?>">Roles y Permisos</a></li>
<li class="breadcrumb-item active"><?= !empty($rol['id']) ? 'Editar Rol' : 'Nuevo Rol' ?></li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-md-4">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user-shield mr-1"></i> Información del Rol</h3>
            </div>
            <form id="formRol">
                <div class="card-body">
                    <input type="hidden" name="id" value="<?= $rol['id'] ?>">
                    
                    <div class="form-group">
                        <label for="nombre">Nombre del Rol *</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" 
                               value="<?= esc($rol['nombre']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="descripcion">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?= esc($rol['descripcion'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="nivel">Nivel *</label>
                        <input type="number" class="form-control" id="nivel" name="nivel" 
                               value="<?= $rol['nivel'] ?>" min="1" max="9" required>
                        <small class="form-text text-muted">1 = Administrador, 2 = Gerente, 3+ = Otros</small>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="estado" name="estado" 
                                   <?= ($rol['estado'] ?? false) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="estado">Activo</label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-save"></i> Guardar
                        </button>
                        <a href="<?= base_url('admin/roles') ?>" class="btn btn-secondary btn-block">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-key mr-1"></i> Permisos por Módulo</h3>
            </div>
            <form id="formPermisos">
                <div class="card-body">
                    <input type="hidden" name="id" value="<?= $rol['id'] ?>">

                    <?php if (empty($rol['id'])): ?>
                        <div class="alert alert-info mb-0">
                            Primero guarde el rol para habilitar la configuración de permisos.
                        </div>
                    <?php else: ?>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Módulo</th>
                                    <th class="text-center">Ver</th>
                                    <th class="text-center">Crear</th>
                                    <th class="text-center">Editar</th>
                                    <th class="text-center">Eliminar</th>
                                    <th class="text-center">Anular</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $permisosArray = [];
                                foreach ($rol['permisos'] ?? [] as $permiso) {
                                    $permisosArray[$permiso['modulo']] = $permiso;
                                }
                                ?>
                                <?php foreach ($modulos as $modulo): ?>
                                    <?php 
                                    $permiso = $permisosArray[$modulo] ?? null;
                                    ?>
                                    <tr>
                                        <td><strong><?= esc($modulo) ?></strong></td>
                                        <td class="text-center">
                                            <input type="checkbox" name="permisos[<?= esc($modulo) ?>][ver]" 
                                                   <?= ($permiso && $permiso['ver']) ? 'checked' : '' ?>>
                                        </td>
                                        <td class="text-center">
                                            <input type="checkbox" name="permisos[<?= esc($modulo) ?>][crear]" 
                                                   <?= ($permiso && $permiso['crear']) ? 'checked' : '' ?>>
                                        </td>
                                        <td class="text-center">
                                            <input type="checkbox" name="permisos[<?= esc($modulo) ?>][editar]" 
                                                   <?= ($permiso && $permiso['editar']) ? 'checked' : '' ?>>
                                        </td>
                                        <td class="text-center">
                                            <input type="checkbox" name="permisos[<?= esc($modulo) ?>][eliminar]" 
                                                   <?= ($permiso && $permiso['eliminar']) ? 'checked' : '' ?>>
                                        </td>
                                        <td class="text-center">
                                            <input type="checkbox" name="permisos[<?= esc($modulo) ?>][anular]" 
                                                   <?= ($permiso && $permiso['anular']) ? 'checked' : '' ?>>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="form-group mt-3">
                        <button type="submit" class="btn btn-success btn-block">
                            <i class="fas fa-save"></i> Guardar Permisos
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(document).ready(function() {
    // Formulario de Rol
    $('#formRol').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        const rolId = $('input[name="id"]', this).val();
        const isNew = !rolId;
        const url = isNew ? '<?= base_url('admin/roles/crear') ?>' : ('<?= base_url('admin/roles/editar/') ?>' + rolId);
        
        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Éxito',
                        text: response.message || 'Rol actualizado correctamente',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        if (response.data && response.data.redirect) {
                            window.location.href = response.data.redirect;
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || 'Error al actualizar el rol'
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
    
    // Formulario de Permisos
    $('#formPermisos').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        const rolId = $('input[name="id"]', this).val();

        if (!rolId) {
            Swal.fire({
                icon: 'info',
                title: 'Atención',
                text: 'Primero debe guardar el rol.'
            });
            return;
        }
        
        $.ajax({
            url: '<?= base_url('admin/roles/editar/') ?>' + rolId,
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Éxito',
                        text: response.message || 'Permisos actualizados correctamente',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || 'Error al actualizar los permisos'
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
