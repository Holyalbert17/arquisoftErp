<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item active">Roles y Permisos</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user-shield mr-1"></i> Gestión de Roles y Permisos</h3>
                <div class="card-tools">
                    <a href="<?= base_url('admin/roles/crear') ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Nuevo Rol
                    </a>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Nivel</th>
                            <th>Rol Sistema</th>
                            <th>Estado</th>
                            <th>Permisos</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roles as $rol): ?>
                            <tr>
                                <td><?= $rol['id'] ?></td>
                                <td><strong><?= esc($rol['nombre']) ?></strong></td>
                                <td><?= esc($rol['descripcion'] ?? '-') ?></td>
                                <td>
                                    <span class="badge badge-primary"><?= $rol['nivel'] ?></span>
                                </td>
                                <td>
                                    <?php if ($rol['es_rol_sistema'] ?? false): ?>
                                        <span class="badge badge-warning">Sistema</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Personalizado</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($rol['estado']): ?>
                                        <span class="badge badge-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($rol['permisos'])): ?>
                                        <span class="badge badge-info"><?= count($rol['permisos']) ?> módulos</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Sin permisos</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= base_url('admin/roles/editar/' . $rol['id']) ?>" 
                                       class="btn btn-sm btn-primary" title="Editar Rol y Permisos">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
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
    $('.data-table').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json"
        },
        "responsive": true,
        "order": [[0, "asc"]]
    });
});
</script>
<?= $this->endSection() ?>
