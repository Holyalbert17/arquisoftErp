<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('compras') ?>">Compras</a></li>
<li class="breadcrumb-item active">Proveedores</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-truck mr-1"></i> Lista de Proveedores</h3>
                <div class="card-tools">
                    <a href="<?= base_url('compras/proveedores/crear') ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Nuevo Proveedor
                    </a>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped data-table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>RUC</th>
                            <th>Razón Social</th>
                            <th>Teléfono</th>
                            <th>Email</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($proveedores as $proveedor): ?>
                            <tr>
                                <td><?= esc($proveedor['codigo']) ?></td>
                                <td><?= esc($proveedor['ruc']) ?></td>
                                <td><strong><?= esc($proveedor['razon_social']) ?></strong></td>
                                <td><?= esc($proveedor['celular'] ?? $proveedor['telefono'] ?? '-') ?></td>
                                <td><?= esc($proveedor['email'] ?? '-') ?></td>
                                <td>
                                    <?php if ($proveedor['estado']): ?>
                                        <span class="badge badge-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= base_url('compras/proveedores/editar/' . $proveedor['id']) ?>" 
                                       class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-danger" 
                                            onclick="eliminarProveedor(<?= $proveedor['id'] ?>)">
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
    $('.data-table').DataTable(datatablesEsConfig);
});

function eliminarProveedor(id) {
    if (confirm('¿Está seguro de eliminar este proveedor?')) {
        $.ajax({
            url: '<?= base_url('compras/proveedores/eliminar') ?>/' + id,
            type: 'POST',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.message || 'Error al eliminar');
                }
            },
            error: function() {
                alert('Error al eliminar el proveedor');
            }
        });
    }
}
</script>
<?= $this->endSection() ?>
