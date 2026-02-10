<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('ventas') ?>">Ventas</a></li>
<li class="breadcrumb-item active">Listas de Precios</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-tags mr-1"></i> Listas de Precios</h3>
                <div class="card-tools">
                    <a href="<?= base_url('ventas/listas-precios/crear') ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Nueva Lista
                    </a>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped data-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Descuento General</th>
                            <th>Vigencia</th>
                            <th>Productos</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($listas as $lista): ?>
                            <tr>
                                <td>
                                    <strong><?= esc($lista['nombre']) ?></strong>
                                    <?php if ($lista['es_predeterminada']): ?>
                                        <span class="badge badge-success ml-2">Predeterminada</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?= esc($lista['tipo_lista']) ?></span>
                                </td>
                                <td>
                                    <?php if ($lista['tipo_descuento'] == 'PORCENTAJE'): ?>
                                        <?= number_format($lista['descuento_general'], 2) ?>%
                                    <?php else: ?>
                                        <?= number_format($lista['descuento_general'], 0, ',', '.') ?> Gs.
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($lista['fecha_vigencia_desde'] && $lista['fecha_vigencia_hasta']): ?>
                                        <?= date('d/m/Y', strtotime($lista['fecha_vigencia_desde'])) ?> - 
                                        <?= date('d/m/Y', strtotime($lista['fecha_vigencia_hasta'])) ?>
                                    <?php else: ?>
                                        <span class="text-muted">Sin límite</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $detalleModel = new \App\Models\ListaPrecioDetalleModel();
                                    $cantidad = $detalleModel->where('lista_id', $lista['id'])->countAllResults();
                                    ?>
                                    <span class="badge badge-secondary"><?= $cantidad ?> productos</span>
                                </td>
                                <td>
                                    <?php if ($lista['estado']): ?>
                                        <span class="badge badge-success">Activa</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactiva</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= base_url('ventas/listas-precios/editar/' . $lista['id']) ?>" 
                                       class="btn btn-sm btn-info" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($lista['estado']): ?>
                                        <button type="button" class="btn btn-sm btn-warning" 
                                                onclick="inactivarLista(<?= $lista['id'] ?>)" title="Inactivar">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-success" 
                                                onclick="activarLista(<?= $lista['id'] ?>)" title="Activar">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-danger" 
                                            onclick="eliminarLista(<?= $lista['id'] ?>)" title="Eliminar">
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
    $('.data-table').DataTable({
        ...datatablesEsConfig,
        'responsive': true,
        'pageLength': 25,
        'order': [[0, 'asc']]
    });
});

function eliminarLista(id) {
    Swal.fire({
        title: '¿Está seguro?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '<?= base_url('ventas/listas-precios/eliminar') ?>/' + id,
                type: 'POST',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Eliminada', response.message || 'Lista eliminada exitosamente', 'success')
                            .then(() => location.reload());
                    } else {
                        Swal.fire('Error', response.message || 'Error al eliminar', 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Error al eliminar la lista de precios', 'error');
                }
            });
        }
    });
}

function inactivarLista(id) {
    Swal.fire({
        title: '¿Inactivar lista?',
        text: 'La lista no estará disponible para nuevas ventas',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#ffc107',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, inactivar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '<?= base_url('ventas/listas-precios/inactivar') ?>/' + id,
                type: 'POST',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Inactivada', response.message || 'Lista inactivada exitosamente', 'success')
                            .then(() => location.reload());
                    } else {
                        Swal.fire('Error', response.message || 'Error al inactivar', 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Error al inactivar la lista de precios', 'error');
                }
            });
        }
    });
}

function activarLista(id) {
    $.ajax({
        url: '<?= base_url('ventas/listas-precios/activar') ?>/' + id,
        type: 'POST',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                Swal.fire('Activada', response.message || 'Lista activada exitosamente', 'success')
                    .then(() => location.reload());
            } else {
                Swal.fire('Error', response.message || 'Error al activar', 'error');
            }
        },
        error: function() {
            Swal.fire('Error', 'Error al activar la lista de precios', 'error');
        }
    });
}
</script>
<?= $this->endSection() ?>
