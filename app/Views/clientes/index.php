<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item active">Clientes</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-users mr-1"></i> Lista de Clientes</h3>
                <div class="card-tools">
                    <a href="<?= base_url('clientes/crear') ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Nuevo Cliente
                    </a>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Código</th>
                            <th>Nombre/Razón Social</th>
                            <th>Tipo</th>
                            <th>RUC/CI</th>
                            <th>Teléfono</th>
                            <th>Email</th>
                            <th>Categoría</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientes as $cliente): ?>
                            <tr>
                                <td><?= $cliente['id'] ?></td>
                                <td><?= esc($cliente['codigo']) ?></td>
                                <td>
                                    <?php
                                    if ($cliente['tipo_cliente'] == 'PERSONA') {
                                        $nombreCompleto = trim(($cliente['nombre'] ?? '') . ' ' . ($cliente['apellido'] ?? ''));
                                    } else {
                                        $nombreCompleto = $cliente['nombre_fantasia'] ?? $cliente['razon_social'] ?? '';
                                    }
                                    echo esc($nombreCompleto ?: 'Sin nombre');
                                    ?>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?= esc($cliente['tipo_cliente']) ?></span>
                                </td>
                                <td><?= esc($cliente['ruc'] ?? $cliente['ci'] ?? '-') ?></td>
                                <td><?= esc($cliente['celular'] ?? $cliente['telefono'] ?? '-') ?></td>
                                <td><?= esc($cliente['email'] ?? '-') ?></td>
                                <td>
                                    <span class="badge badge-secondary"><?= esc($cliente['categoria_cliente']) ?></span>
                                </td>
                                <td>
                                    <?php if ($cliente['estado']): ?>
                                        <span class="badge badge-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= base_url('clientes/ver/' . $cliente['id']) ?>" 
                                       class="btn btn-sm btn-info" title="Ver">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="<?= base_url('clientes/editar/' . $cliente['id']) ?>" 
                                       class="btn btn-sm btn-warning" title="Editar">
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
    $('.data-table').DataTable(datatablesEsConfig);
});
</script>
<?= $this->endSection() ?>
