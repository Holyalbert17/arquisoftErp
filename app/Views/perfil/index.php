<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('styles') ?>
<style>
    .profile-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 2rem;
        border-radius: 10px;
        margin-bottom: 2rem;
    }
    .profile-img {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        border: 5px solid white;
        object-fit: cover;
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }
    .stat-card {
        border-left: 4px solid #007bff;
        transition: all 0.3s;
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .session-item {
        border-left: 3px solid #28a745;
        padding: 10px;
        margin-bottom: 10px;
        background: #f8f9fa;
        border-radius: 5px;
    }
    .session-item.inactive {
        border-left-color: #dc3545;
        opacity: 0.7;
    }
    .activity-item {
        padding: 10px;
        border-bottom: 1px solid #dee2e6;
    }
    .activity-item:last-child {
        border-bottom: none;
    }
    .badge-action {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Dashboard</a></li>
<li class="breadcrumb-item active">Mi Perfil</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<!-- Header del Perfil -->
<div class="profile-header">
    <div class="row align-items-center">
        <div class="col-md-3 text-center">
            <img src="<?= !empty($usuario['foto_perfil']) 
                ? base_url('uploads/usuarios/' . $usuario['foto_perfil']) 
                : 'https://via.placeholder.com/150?text=Usuario' ?>" 
                 alt="Foto de perfil" 
                 class="profile-img" 
                 id="profileImage">
            <div class="mt-3">
                <button class="btn btn-light btn-sm" onclick="$('#fotoInput').click()">
                    <i class="fas fa-camera"></i> Cambiar Foto
                </button>
                <input type="file" id="fotoInput" style="display: none;" accept="image/*">
            </div>
        </div>
        <div class="col-md-9">
            <h2 class="mb-1">
                <?= esc($usuario['nombre'] . ' ' . $usuario['apellido']) ?>
            </h2>
            <p class="mb-2">
                <i class="fas fa-envelope"></i> <?= esc($usuario['email']) ?>
            </p>
            <p class="mb-2">
                <i class="fas fa-phone"></i> <?= esc($usuario['telefono'] ?? 'No especificado') ?>
            </p>
            <p class="mb-0">
                <i class="fas fa-id-card"></i> CI: <?= esc($usuario['ci']) ?>
            </p>
        </div>
    </div>
</div>

<!-- Estadísticas -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">Ventas Realizadas</h6>
                        <h3 class="mb-0"><?= number_format($estadisticas['total_ventas'] ?? 0) ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-shopping-cart fa-2x text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">Compras Realizadas</h6>
                        <h3 class="mb-0"><?= number_format($estadisticas['total_compras'] ?? 0) ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-box fa-2x text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">Órdenes de Servicio</h6>
                        <h3 class="mb-0"><?= number_format($estadisticas['total_ordenes'] ?? 0) ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-tools fa-2x text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">Días Activo</h6>
                        <h3 class="mb-0"><?= number_format($estadisticas['dias_activo'] ?? 0) ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-calendar-check fa-2x text-info"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Columna Izquierda -->
    <div class="col-md-8">
        <!-- Datos Personales -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user mr-2"></i> Datos Personales</h3>
                <div class="card-tools">
                    <button class="btn btn-sm btn-primary" id="btnEditarDatos">
                        <i class="fas fa-edit"></i> Editar
                    </button>
                </div>
            </div>
            <div class="card-body">
                <form id="formDatosPersonales">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Nombre</label>
                                <input type="text" class="form-control" name="nombre" 
                                       value="<?= esc($usuario['nombre']) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Apellido</label>
                                <input type="text" class="form-control" name="apellido" 
                                       value="<?= esc($usuario['apellido']) ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" class="form-control" name="email" 
                                       value="<?= esc($usuario['email']) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Cédula de Identidad</label>
                                <input type="text" class="form-control" name="ci" 
                                       value="<?= esc($usuario['ci']) ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Teléfono</label>
                                <input type="text" class="form-control" name="telefono" 
                                       value="<?= esc($usuario['telefono'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Dirección</label>
                                <input type="text" class="form-control" name="direccion" 
                                       value="<?= esc($usuario['direccion'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Guardar Cambios
                        </button>
                        <button type="button" class="btn btn-secondary" id="btnCancelarDatos">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Cambiar Contraseña -->
        <div class="card mt-4">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-lock mr-2"></i> Cambiar Contraseña</h3>
            </div>
            <div class="card-body">
                <form id="formCambiarPassword">
                    <div class="form-group">
                        <label>Contraseña Actual</label>
                        <input type="password" class="form-control" name="password_actual" required>
                    </div>
                    <div class="form-group">
                        <label>Nueva Contraseña</label>
                        <input type="password" class="form-control" name="password_nuevo" 
                               minlength="8" required>
                        <small class="form-text text-muted">
                            Mínimo 8 caracteres, debe incluir mayúsculas, minúsculas y números
                        </small>
                    </div>
                    <div class="form-group">
                        <label>Confirmar Nueva Contraseña</label>
                        <input type="password" class="form-control" name="password_confirmar" 
                               minlength="8" required>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-key"></i> Cambiar Contraseña
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Historial de Actividad -->
        <div class="card mt-4">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-history mr-2"></i> Historial de Actividad</h3>
            </div>
            <div class="card-body">
                <div id="historialList">
                    <?php if (empty($historial)): ?>
                        <p class="text-center text-muted">No hay actividad registrada</p>
                    <?php else: ?>
                        <?php foreach ($historial as $actividad): ?>
                            <div class="activity-item">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <strong>
                                            <span class="badge badge-action badge-<?= 
                                                ($actividad['accion'] ?? '') == 'INSERT' ? 'success' : 
                                                (($actividad['accion'] ?? '') == 'UPDATE' ? 'warning' : 'danger') 
                                            ?>">
                                                <?= esc($actividad['accion'] ?? 'N/A') ?>
                                            </span>
                                            <?= esc($actividad['tabla_afectada'] ?? 'N/A') ?>
                                        </strong>
                                        <br>
                                        <small class="text-muted">
                                            <i class="fas fa-clock"></i> 
                                            <?= date('d/m/Y H:i', strtotime($actividad['fecha_evento'] ?? $actividad['fecha_accion'] ?? 'now')) ?>
                                        </small>
                                    </div>
                                    <div>
                                        <small class="text-muted">
                                            <i class="fas fa-map-marker-alt"></i> 
                                            <?= esc($actividad['ip_address'] ?? 'N/A') ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="text-center mt-3">
                    <button class="btn btn-sm btn-outline-primary" id="btnCargarMasHistorial">
                        <i class="fas fa-chevron-down"></i> Cargar Más
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Columna Derecha -->
    <div class="col-md-4">
        <!-- Sesiones Activas -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-desktop mr-2"></i> Sesiones Activas</h3>
                <div class="card-tools">
                    <button class="btn btn-sm btn-danger" id="btnCerrarTodasSesiones">
                        <i class="fas fa-sign-out-alt"></i> Cerrar Todas
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($sesiones)): ?>
                    <p class="text-center text-muted">No hay sesiones activas</p>
                <?php else: ?>
                    <?php foreach ($sesiones as $sesion): ?>
                        <div class="session-item <?= !$sesion['activa'] ? 'inactive' : '' ?>">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <strong>
                                        <i class="fas fa-desktop"></i> 
                                        <?= esc($sesion['user_agent'] ?? 'Navegador desconocido') ?>
                                    </strong>
                                    <br>
                                    <small class="text-muted">
                                        <i class="fas fa-map-marker-alt"></i> 
                                        <?= esc($sesion['ip_address'] ?? 'N/A') ?>
                                    </small>
                                    <br>
                                    <small class="text-muted">
                                        <i class="fas fa-clock"></i> 
                                        Inicio: <?= date('d/m/Y H:i', strtotime($sesion['fecha_inicio'])) ?>
                                    </small>
                                </div>
                                <?php 
                                $currentSessionId = session_id();
                                $isCurrentSession = isset($sesion['session_id']) && $sesion['session_id'] == $currentSessionId;
                                ?>
                                <?php if ($sesion['activa'] && !$isCurrentSession): ?>
                                    <button class="btn btn-sm btn-danger btn-cerrar-sesion" 
                                            data-sesion-id="<?= $sesion['session_id'] ?? $sesion['id'] ?>">
                                        <i class="fas fa-times"></i>
                                    </button>
                                <?php elseif ($isCurrentSession): ?>
                                    <span class="badge badge-success">Sesión Actual</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Preferencias -->
        <div class="card mt-4">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-cog mr-2"></i> Preferencias</h3>
            </div>
            <div class="card-body">
                <form id="formPreferencias">
                    <?= csrf_field() ?>
                    <?php 
                    $preferencias = json_decode($usuario['preferencias'] ?? '{}', true);
                    ?>
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="notificacionesEmail" 
                                   name="notificaciones_email" 
                                   <?= ($preferencias['notificaciones_email'] ?? true) ? 'checked' : '' ?>>
                            <label class="custom-control-label" for="notificacionesEmail">
                                Notificaciones por Email
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="notificacionesPush" 
                                   name="notificaciones_push" 
                                   <?= ($preferencias['notificaciones_push'] ?? true) ? 'checked' : '' ?>>
                            <label class="custom-control-label" for="notificacionesPush">
                                Notificaciones Push
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="temaOscuro" 
                                   name="tema_oscuro" 
                                   <?= ($preferencias['tema_oscuro'] ?? false) ? 'checked' : '' ?>>
                            <label class="custom-control-label" for="temaOscuro">
                                Tema Oscuro
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Idioma</label>
                        <select class="form-control" name="idioma">
                            <option value="es" <?= ($preferencias['idioma'] ?? 'es') == 'es' ? 'selected' : '' ?>>Español</option>
                            <option value="en" <?= ($preferencias['idioma'] ?? 'es') == 'en' ? 'selected' : '' ?>>English</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Zona Horaria</label>
                        <select class="form-control" name="zona_horaria">
                            <option value="America/Asuncion" <?= ($preferencias['zona_horaria'] ?? 'America/Asuncion') == 'America/Asuncion' ? 'selected' : '' ?>>
                                Asunción (GMT-4)
                            </option>
                            <option value="America/New_York" <?= ($preferencias['zona_horaria'] ?? '') == 'America/New_York' ? 'selected' : '' ?>>
                                New York (GMT-5)
                            </option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Formato de Fecha</label>
                        <select class="form-control" name="formato_fecha">
                            <option value="d/m/Y" <?= ($preferencias['formato_fecha'] ?? 'd/m/Y') == 'd/m/Y' ? 'selected' : '' ?>>DD/MM/YYYY</option>
                            <option value="Y-m-d" <?= ($preferencias['formato_fecha'] ?? '') == 'Y-m-d' ? 'selected' : '' ?>>YYYY-MM-DD</option>
                            <option value="m/d/Y" <?= ($preferencias['formato_fecha'] ?? '') == 'm/d/Y' ? 'selected' : '' ?>>MM/DD/YYYY</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-save"></i> Guardar Preferencias
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(document).ready(function() {
    // Cambiar foto de perfil
    $('#fotoInput').on('change', function() {
        const file = this.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('foto', file);

        $.ajax({
            url: '<?= base_url("perfil/cambiar-foto") ?>',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#profileImage').attr('src', response.data.url + '?t=' + new Date().getTime());
                    Swal.fire('Éxito', response.message, 'success');
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Error al cambiar la foto', 'error');
            }
        });
    });

    // Actualizar datos personales
    $('#formDatosPersonales').on('submit', function(e) {
        e.preventDefault();
        
        $.ajax({
            url: '<?= base_url("perfil/actualizar-datos") ?>',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire('Éxito', response.message, 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            }
        });
    });

    // Cambiar contraseña
    $('#formCambiarPassword').on('submit', function(e) {
        e.preventDefault();
        
        const passwordNuevo = $('input[name="password_nuevo"]').val();
        const passwordConfirmar = $('input[name="password_confirmar"]').val();
        
        if (passwordNuevo !== passwordConfirmar) {
            Swal.fire('Error', 'Las contraseñas no coinciden', 'error');
            return;
        }

        $.ajax({
            url: '<?= base_url("perfil/cambiar-password") ?>',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire('Éxito', response.message, 'success').then(() => {
                        $('#formCambiarPassword')[0].reset();
                    });
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            }
        });
    });

    // Cerrar sesión específica
    $(document).on('click', '.btn-cerrar-sesion', function() {
        const sesionId = $(this).data('sesion-id');
        
        Swal.fire({
            title: '¿Cerrar esta sesión?',
            text: 'Se cerrará la sesión en el dispositivo seleccionado',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, cerrar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '<?= base_url("perfil/cerrar-sesion") ?>',
                    method: 'POST',
                    data: { sesion_id: sesionId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Éxito', response.message, 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    }
                });
            }
        });
    });

    // Cerrar todas las sesiones
    $('#btnCerrarTodasSesiones').on('click', function() {
        Swal.fire({
            title: '¿Cerrar todas las sesiones?',
            text: 'Se cerrarán todas las sesiones excepto la actual',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, cerrar todas',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '<?= base_url("perfil/cerrar-todas-sesiones") ?>',
                    method: 'POST',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Éxito', response.message, 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    }
                });
            }
        });
    });

    // Actualizar preferencias
    $('#formPreferencias').on('submit', function(e) {
        e.preventDefault();
        
        $.ajax({
            url: '<?= base_url("perfil/actualizar-preferencias") ?>',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire('Éxito', response.message, 'success');
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            },
            error: function(xhr) {
                let errorMsg = 'Error al actualizar preferencias';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                } else if (xhr.status === 400) {
                    errorMsg = 'Error de validación. Por favor, verifique los datos.';
                } else if (xhr.status === 401) {
                    errorMsg = 'Sesión expirada. Por favor, recargue la página.';
                }
                Swal.fire('Error', errorMsg, 'error');
            }
        });
    });

    // Cargar más historial
    let paginaHistorial = 1;
    $('#btnCargarMasHistorial').on('click', function() {
        paginaHistorial++;
        $.ajax({
            url: '<?= base_url("perfil/historial") ?>',
            method: 'GET',
            data: { pagina: paginaHistorial },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    response.data.forEach(function(actividad) {
                        const badgeClass = actividad.accion == 'INSERT' ? 'success' : 
                                          (actividad.accion == 'UPDATE' ? 'warning' : 'danger');
                        const html = `
                            <div class="activity-item">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <strong>
                                            <span class="badge badge-action badge-${badgeClass}">
                                                ${actividad.accion}
                                            </span>
                                            ${actividad.tabla_afectada}
                                        </strong>
                                        <br>
                                        <small class="text-muted">
                                            <i class="fas fa-clock"></i> 
                                            ${new Date(actividad.fecha_evento || actividad.fecha_accion).toLocaleString('es-PY')}
                                        </small>
                                    </div>
                                    <div>
                                        <small class="text-muted">
                                            <i class="fas fa-map-marker-alt"></i> 
                                            ${actividad.ip_address || 'N/A'}
                                        </small>
                                    </div>
                                </div>
                            </div>
                        `;
                        $('#historialList').append(html);
                    });
                } else {
                    $('#btnCargarMasHistorial').prop('disabled', true).text('No hay más registros');
                }
            }
        });
    });
});
</script>
<?= $this->endSection() ?>
