<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item active">Usuarios</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-users mr-1"></i> Lista de Usuarios</h3>
                <div class="card-tools">
                    <a href="<?= base_url('admin/usuarios/crear') ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Nuevo Usuario
                    </a>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Foto</th>
                            <th>Nombre</th>
                            <th>Usuario</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Empresa</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $usuario): ?>
                            <tr>
                                <td><?= $usuario['id'] ?></td>
                                <td>
                                    <?php if ($usuario['foto_perfil']): ?>
                                        <img src="<?= base_url('uploads/usuarios/' . $usuario['foto_perfil']) ?>" 
                                             alt="Foto" class="img-circle" style="width: 40px; height: 40px;">
                                    <?php else: ?>
                                        <i class="fas fa-user-circle fa-2x text-muted"></i>
                                    <?php endif; ?>
                                </td>
                                <td><?= esc($usuario['nombre'] . ' ' . $usuario['apellido']) ?></td>
                                <td><?= esc($usuario['username']) ?></td>
                                <td><?= esc($usuario['email']) ?></td>
                                <td>
                                    <?php if ($usuario['rol']): ?>
                                        <span class="badge badge-info"><?= esc($usuario['rol']['nombre']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($usuario['empresa']): ?>
                                        <?= esc($usuario['empresa']['nombre']) ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($usuario['estado']): ?>
                                        <span class="badge badge-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= base_url('admin/usuarios/editar/' . $usuario['id']) ?>" 
                                       class="btn btn-sm btn-info" title="Editar">
                                        <i class="fas fa-edit"></i>
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
        'language': datatablesEsConfig.language,
        'responsive': true,
        'pageLength': 25
    });
});
</script>
<?= $this->endSection() ?>
