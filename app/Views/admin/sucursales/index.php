<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item active">Sucursales</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-store mr-1"></i> Lista de Sucursales</h3>
                <div class="card-tools">
                    <a href="<?= base_url('admin/sucursales/crear') ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Nueva Sucursal
                    </a>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Empresa</th>
                            <th>Nombre</th>
                            <th>Ciudad</th>
                            <th>Departamento</th>
                            <th>Teléfono</th>
                            <th>Matriz</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sucursales as $sucursal): ?>
                            <?php 
                            $empresa = $empresas[array_search($sucursal['empresa_id'], array_column($empresas, 'id'))] ?? null;
                            ?>
                            <tr>
                                <td><?= $sucursal['id'] ?></td>
                                <td><?= $empresa ? esc($empresa['nombre']) : '-' ?></td>
                                <td><?= esc($sucursal['nombre']) ?></td>
                                <td><?= esc($sucursal['ciudad']) ?></td>
                                <td><?= esc($sucursal['departamento']) ?></td>
                                <td><?= esc($sucursal['telefono']) ?></td>
                                <td>
                                    <?php if ($sucursal['es_matriz']): ?>
                                        <span class="badge badge-success">Sí</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">No</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($sucursal['estado']): ?>
                                        <span class="badge badge-success">Activa</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactiva</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= base_url('admin/sucursales/editar/' . $sucursal['id']) ?>" 
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
