<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item active">Empresas</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-building mr-1"></i> Lista de Empresas</h3>
                <div class="card-tools">
                    <a href="<?= base_url('admin/empresas/crear') ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Nueva Empresa
                    </a>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Logo</th>
                            <th>Nombre</th>
                            <th>RUC</th>
                            <th>Ciudad</th>
                            <th>Teléfono</th>
                            <th>IVA</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($empresas as $empresa): ?>
                            <tr>
                                <td><?= $empresa['id'] ?></td>
                                <td>
                                    <?php if ($empresa['logo']): ?>
                                        <img src="<?= base_url('uploads/empresas/' . $empresa['logo']) ?>" 
                                             alt="Logo" class="img-circle" style="width: 40px; height: 40px;">
                                    <?php else: ?>
                                        <i class="fas fa-building fa-2x text-muted"></i>
                                    <?php endif; ?>
                                </td>
                                <td><?= esc($empresa['nombre']) ?></td>
                                <td><?= esc($empresa['ruc']) ?></td>
                                <td><?= esc($empresa['ciudad']) ?></td>
                                <td><?= esc($empresa['telefono']) ?></td>
                                <td><?= number_format($empresa['porcentaje_iva'], 0) ?>%</td>
                                <td>
                                    <?php if ($empresa['estado']): ?>
                                        <span class="badge badge-success">Activa</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactiva</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= base_url('admin/empresas/editar/' . $empresa['id']) ?>" 
                                       class="btn btn-sm btn-info" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" 
                                            class="btn btn-sm btn-danger btn-delete-empresa" 
                                            data-id="<?= $empresa['id'] ?>"
                                            data-nombre="<?= esc($empresa['nombre']) ?>"
                                            title="Eliminar">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(document).ready(function() {
    var table = $('.data-table').DataTable({
        'language': datatablesEsConfig.language,
        'responsive': true,
        'pageLength': 25
    });

    // Manejar eliminación de empresas usando delegación de eventos
    // Esto asegura que funcione incluso si DataTables recrea las filas
    $(document).on('click', '.btn-delete-empresa', function(e) {
        e.preventDefault();
        e.stopPropagation(); // Prevenir cualquier otro handler
        
        const btn = $(this);
        const id = btn.data('id');
        const nombre = btn.data('nombre');
        
        if (!id) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'ID de empresa no válido'
            });
            return;
        }
        
        Swal.fire({
            title: '¿Está seguro?',
            text: `¿Desea eliminar la empresa "${nombre}"? Esta acción la desactivará.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Deshabilitar el botón mientras se procesa
                btn.prop('disabled', true);
                
                $.ajax({
                    url: '<?= base_url('admin/empresas/eliminar') ?>/' + id,
                    method: 'POST',
                    headers: {
                        '<?= csrf_header() ?>': $('meta[name="<?= csrf_header() ?>"]').attr('content'),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    data: {
                        '<?= csrf_token() ?>': $('meta[name="<?= csrf_header() ?>"]').attr('content')
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Éxito',
                                text: response.message || 'Empresa eliminada exitosamente',
                                showConfirmButton: false,
                                timer: 1500
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            btn.prop('disabled', false);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message || 'Error al eliminar la empresa'
                            });
                        }
                    },
                    error: function(xhr) {
                        btn.prop('disabled', false);
                        let mensaje = 'Error al procesar la solicitud';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            mensaje = xhr.responseJSON.message;
                        } else if (xhr.status === 404) {
                            mensaje = 'Ruta no encontrada. Por favor, recarga la página.';
                        } else if (xhr.status === 403) {
                            mensaje = 'No tiene permisos para realizar esta acción';
                        } else if (xhr.status === 401) {
                            mensaje = 'Sesión expirada. Por favor, inicie sesión nuevamente';
                        }
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: mensaje
                        });
                    }
                });
            }
        });
    });
});
</script>
<?= $this->endSection() ?>
