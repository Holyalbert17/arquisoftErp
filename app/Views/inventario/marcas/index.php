<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('inventario') ?>">Inventario</a></li>
<li class="breadcrumb-item active">Marcas</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-certificate mr-1"></i> Marcas de Productos</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalMarca">
                        <i class="fas fa-plus"></i> Nueva Marca
                    </button>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped data-table">
                    <thead>
                        <tr>
                            <th>Logo</th>
                            <th>Nombre</th>
                            <th>País de Origen</th>
                            <th>Sitio Web</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($marcas as $marca): ?>
                            <tr>
                                <td>
                                    <?php if ($marca['logo']): ?>
                                        <img src="<?= base_url('uploads/marcas/' . $marca['logo']) ?>" 
                                             alt="<?= esc($marca['nombre']) ?>" 
                                             style="max-width: 50px; max-height: 50px;">
                                    <?php else: ?>
                                        <i class="fas fa-image fa-2x text-muted"></i>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= esc($marca['nombre']) ?></strong></td>
                                <td><?= esc($marca['pais_origen'] ?? '') ?></td>
                                <td>
                                    <?php if ($marca['sitio_web']): ?>
                                        <a href="<?= esc($marca['sitio_web']) ?>" target="_blank">
                                            <?= esc($marca['sitio_web']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($marca['estado']): ?>
                                        <span class="badge badge-success">Activa</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactiva</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info" 
                                            onclick="editarMarca(<?= $marca['id'] ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger" 
                                            onclick="eliminarMarca(<?= $marca['id'] ?>)">
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

<!-- Modal Marca -->
<div class="modal fade" id="modalMarca" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalMarcaTitle">Nueva Marca</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="formMarca" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="marca_id" id="marca_id">
                    <div class="form-group">
                        <label>Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" id="marca_nombre" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Descripción</label>
                        <textarea name="descripcion" id="marca_descripcion" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>País de Origen</label>
                                <input type="text" name="pais_origen" id="marca_pais" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Sitio Web</label>
                                <input type="url" name="sitio_web" id="marca_web" class="form-control" 
                                       placeholder="https://...">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Logo</label>
                        <input type="file" name="logo" id="marca_logo" class="form-control" accept="image/*">
                        <small class="form-text text-muted">Formatos: JPG, PNG, GIF, WEBP. Máximo 2MB</small>
                        <div id="logo_preview" class="mt-2"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(document).ready(function() {
    $('.data-table').DataTable(datatablesEsConfig);
    
    $('#modalMarca').on('hidden.bs.modal', function() {
        $('#formMarca')[0].reset();
        $('#marca_id').val('');
        $('#modalMarcaTitle').text('Nueva Marca');
        $('#logo_preview').html('');
    });
    
    $('#marca_logo').on('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#logo_preview').html('<img src="' + e.target.result + '" style="max-width: 200px; max-height: 200px;">');
            };
            reader.readAsDataURL(file);
        }
    });
});

$('#formMarca').on('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const marcaId = $('#marca_id').val();
    const url = marcaId ? 
        '<?= base_url('inventario/marcas/editar') ?>/' + marcaId :
        '<?= base_url('inventario/marcas/crear') ?>';
    
    $.ajax({
        url: url,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.message || 'Error al guardar');
            }
        },
        error: function() {
            alert('Error al procesar la solicitud');
        }
    });
});

function editarMarca(id) {
    $.ajax({
        url: '<?= base_url('inventario/marcas') ?>/' + id,
        type: 'GET',
        dataType: 'json',
        success: function(marca) {
            $('#marca_id').val(marca.id);
            $('#marca_nombre').val(marca.nombre);
            $('#marca_descripcion').val(marca.descripcion || '');
            $('#marca_pais').val(marca.pais_origen || '');
            $('#marca_web').val(marca.sitio_web || '');
            if (marca.logo) {
                $('#logo_preview').html('<img src="<?= base_url('uploads/marcas') ?>/' + marca.logo + '" style="max-width: 200px; max-height: 200px;">');
            }
            $('#modalMarcaTitle').text('Editar Marca');
            $('#modalMarca').modal('show');
        },
        error: function() {
            alert('Error al cargar la marca');
        }
    });
}

function eliminarMarca(id) {
    if (confirm('¿Está seguro de eliminar esta marca?')) {
        $.ajax({
            url: '<?= base_url('inventario/marcas/eliminar') ?>/' + id,
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
                alert('Error al eliminar la marca');
            }
        });
    }
}
</script>
<?= $this->endSection() ?>
