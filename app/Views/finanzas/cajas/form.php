<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('finanzas') ?>">Finanzas</a></li>
<li class="breadcrumb-item"><a href="<?= base_url('finanzas/cajas') ?>">Cajas</a></li>
<li class="breadcrumb-item active"><?= $caja ? 'Editar' : 'Nueva' ?> Caja</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-cash-register mr-1"></i>
                    <?= $caja ? 'Editar Caja' : 'Nueva Caja' ?>
                </h3>
            </div>
            <div class="card-body">
                <form id="formCaja">
                    <?= csrf_field() ?>
                    
                    <div class="form-group">
                        <label>Nombre de la Caja <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" class="form-control" 
                               value="<?= $caja ? esc($caja['nombre']) : '' ?>" 
                               required minlength="3" maxlength="100"
                               placeholder="Ej: Caja Principal">
                    </div>

                    <div class="form-group">
                        <label>Descripción</label>
                        <textarea name="descripcion" class="form-control" rows="3"
                                  placeholder="Descripción opcional de la caja"><?= $caja ? esc($caja['descripcion']) : '' ?></textarea>
                    </div>
                </form>
            </div>
            <div class="card-footer">
                <button type="button" class="btn btn-primary" id="btnGuardar">
                    <i class="fas fa-save"></i> Guardar
                </button>
                <a href="<?= base_url('finanzas/cajas') ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(document).ready(function() {
    $('#btnGuardar').on('click', function() {
        const form = $('#formCaja');
        const formData = new FormData(form[0]);

        $.ajax({
            url: '<?= base_url("finanzas/cajas/guardar") ?>',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Caja Creada',
                        text: response.message,
                        showConfirmButton: false,
                        timer: 2000
                    }).then(() => {
                        window.location.href = response.data.redirect;
                    });
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Error al guardar la caja', 'error');
            }
        });
    });
});
</script>
<?= $this->endSection() ?>
