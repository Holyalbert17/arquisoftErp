<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('inventario') ?>">Inventario</a></li>
<li class="breadcrumb-item active">Categorías</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-tags mr-1"></i> Categorías de Productos</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-primary btn-sm" id="btnNuevaCategoria">
                        <i class="fas fa-plus"></i> Nueva Categoría
                    </button>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped data-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Categoría Padre</th>
                            <th>Orden</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categorias as $categoria): ?>
                            <tr>
                                <td>
                                    <?php if ($categoria['icono']): ?>
                                        <i class="fas fa-<?= esc($categoria['icono']) ?>"></i>
                                    <?php endif; ?>
                                    <strong><?= esc($categoria['nombre']) ?></strong>
                                </td>
                                <td><?= esc($categoria['descripcion'] ?? '') ?></td>
                                <td>
                                    <?php if ($categoria['categoria_padre_id']): ?>
                                        <?php
                                        $categoriaModel = new \App\Models\CategoriaModel();
                                        $padre = $categoriaModel->find($categoria['categoria_padre_id']);
                                        echo $padre ? esc($padre['nombre']) : '-';
                                        ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= esc($categoria['orden']) ?></td>
                                <td>
                                    <?php if ($categoria['estado']): ?>
                                        <span class="badge badge-success">Activa</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactiva</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info" 
                                            onclick="editarCategoria(<?= $categoria['id'] ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger" 
                                            onclick="eliminarCategoria(<?= $categoria['id'] ?>)">
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

<!-- Plantilla para modal dinámico Categoría -->
<div id="templateCategoria" style="display: none;">
    <form id="formCategoria">
        <input type="hidden" name="categoria_id" id="categoria_id">
        <div class="mb-3">
            <label class="form-label">Nombre <span class="text-danger">*</span></label>
            <input type="text" name="nombre" id="categoria_nombre" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Descripción</label>
            <textarea name="descripcion" id="categoria_descripcion" class="form-control" rows="3"></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Categoría Padre</label>
            <select name="categoria_padre_id" id="categoria_padre_id" class="form-control">
                <option value="">Ninguna (Categoría Principal)</option>
                <?php foreach ($categorias as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= esc($cat['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">Orden</label>
                    <input type="number" name="orden" id="categoria_orden" class="form-control" value="0">
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">Color</label>
                    <input type="color" name="color" id="categoria_color" class="form-control">
                </div>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Icono (Font Awesome)</label>
            <input type="text" name="icono" id="categoria_icono" class="form-control" placeholder="Ej: box, tag, folder">
        </div>
        <div class="d-flex justify-content-end gap-2 pt-2 border-top">
            <button type="button" class="btn btn-secondary" id="btnCerrarCategoria">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </form>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(document).ready(function() {
    $('.data-table').DataTable(datatablesEsConfig);

    function openModalCategoria(editData) {
        AppModal.open({
            title: editData ? 'Editar Categoría' : 'Nueva Categoría',
            contentSelector: '#templateCategoria',
            size: 'md',
            onShow: function() {
                var $body = $('#app-modal-overlay .app-modal-body');
                if (editData) {
                    $body.find('#categoria_id').val(editData.id);
                    $body.find('#categoria_nombre').val(editData.nombre);
                    $body.find('#categoria_descripcion').val(editData.descripcion || '');
                    $body.find('#categoria_padre_id').val(editData.categoria_padre_id || '');
                    $body.find('#categoria_orden').val(editData.orden || 0);
                    $body.find('#categoria_color').val(editData.color || '#007bff');
                    $body.find('#categoria_icono').val(editData.icono || '');
                } else {
                    $body.find('#formCategoria')[0].reset();
                    $body.find('#categoria_id').val('');
                }
                $body.find('#btnCerrarCategoria').off('click').on('click', function() { AppModal.close(); });
                $body.find('#formCategoria').off('submit').on('submit', function(e) {
                    e.preventDefault();
                    var formData = new FormData(this);
                    var categoriaId = $body.find('#categoria_id').val();
                    var url = categoriaId ? '<?= base_url('inventario/categorias/editar') ?>/' + categoriaId : '<?= base_url('inventario/categorias/crear') ?>';
                    $.ajax({
                        url: url,
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) location.reload();
                            else alert(response.message || 'Error al guardar');
                        },
                        error: function() { alert('Error al procesar la solicitud'); }
                    });
                });
            }
        });
    }

    $('#btnNuevaCategoria').on('click', function() { openModalCategoria(null); });
});

function editarCategoria(id) {
    $.ajax({
        url: '<?= base_url('inventario/categorias') ?>/' + id,
        type: 'GET',
        dataType: 'json',
        success: function(categoria) {
            AppModal.open({
                title: 'Editar Categoría',
                contentSelector: '#templateCategoria',
                size: 'md',
                onShow: function() {
                    var $body = $('#app-modal-overlay .app-modal-body');
                    $body.find('#categoria_id').val(categoria.id);
                    $body.find('#categoria_nombre').val(categoria.nombre);
                    $body.find('#categoria_descripcion').val(categoria.descripcion || '');
                    $body.find('#categoria_padre_id').val(categoria.categoria_padre_id || '');
                    $body.find('#categoria_orden').val(categoria.orden || 0);
                    $body.find('#categoria_color').val(categoria.color || '#007bff');
                    $body.find('#categoria_icono').val(categoria.icono || '');
                    $body.find('#btnCerrarCategoria').off('click').on('click', function() { AppModal.close(); });
                    $body.find('#formCategoria').off('submit').on('submit', function(e) {
                        e.preventDefault();
                        $.ajax({
                            url: '<?= base_url('inventario/categorias/editar') ?>/' + categoria.id,
                            type: 'POST',
                            data: new FormData(this),
                            processData: false,
                            contentType: false,
                            dataType: 'json',
                            success: function(r) { if (r.success) location.reload(); else alert(r.message || 'Error'); },
                            error: function() { alert('Error al procesar'); }
                        });
                    });
                }
            });
        },
        error: function() { alert('Error al cargar la categoría'); }
    });
}

function eliminarCategoria(id) {
    if (confirm('¿Está seguro de eliminar esta categoría?')) {
        $.ajax({
            url: '<?= base_url('inventario/categorias/eliminar') ?>/' + id,
            type: 'POST',
            dataType: 'json',
            success: function(response) {
                if (response.success) location.reload();
                else alert(response.message || 'Error al eliminar');
            },
            error: function() { alert('Error al eliminar la categoría'); }
        });
    }
}
</script>
<?= $this->endSection() ?>
