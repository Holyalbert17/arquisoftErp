<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('compras') ?>">Compras</a></li>
<li class="breadcrumb-item"><a href="<?= base_url('compras/proveedores') ?>">Proveedores</a></li>
<li class="breadcrumb-item active"><?= $proveedor ? 'Editar' : 'Nuevo' ?></li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-md-12">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-truck mr-1"></i>
                    <?= $proveedor ? 'Editar Proveedor' : 'Nuevo Proveedor' ?>
                </h3>
            </div>
            <form id="proveedorForm" method="post">
                <div class="card-body">
                    <h5 class="mb-3"><i class="fas fa-info-circle"></i> Información Básica</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="codigo">Código</label>
                                <input type="text" class="form-control" id="codigo" name="codigo" 
                                       value="<?= $proveedor ? esc($proveedor['codigo']) : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="ruc">RUC <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="ruc" name="ruc" 
                                       value="<?= $proveedor ? esc($proveedor['ruc']) : '' ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="razon_social">Razón Social <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="razon_social" name="razon_social" 
                                       value="<?= $proveedor ? esc($proveedor['razon_social']) : '' ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="nombre_comercial">Nombre Comercial</label>
                                <input type="text" class="form-control" id="nombre_comercial" name="nombre_comercial" 
                                       value="<?= $proveedor ? esc($proveedor['nombre_comercial']) : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="pais">País</label>
                                <input type="text" class="form-control" id="pais" name="pais" 
                                       value="<?= $proveedor ? esc($proveedor['pais']) : 'Paraguay' ?>">
                            </div>
                        </div>
                    </div>
                    <h5 class="mb-3 mt-4"><i class="fas fa-map-marker-alt"></i> Dirección</h5>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="direccion">Dirección</label>
                                <textarea class="form-control" id="direccion" name="direccion" rows="2"><?= $proveedor ? esc($proveedor['direccion']) : '' ?></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="ciudad">Ciudad</label>
                                <input type="text" class="form-control" id="ciudad" name="ciudad" 
                                       value="<?= $proveedor ? esc($proveedor['ciudad']) : '' ?>">
                            </div>
                        </div>
                    </div>
                    <h5 class="mb-3 mt-4"><i class="fas fa-phone"></i> Contacto</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="telefono">Teléfono</label>
                                <input type="text" class="form-control" id="telefono" name="telefono" 
                                       value="<?= $proveedor ? esc($proveedor['telefono']) : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="celular">Celular</label>
                                <input type="text" class="form-control" id="celular" name="celular" 
                                       value="<?= $proveedor ? esc($proveedor['celular']) : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= $proveedor ? esc($proveedor['email']) : '' ?>">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="sitio_web">Sitio Web</label>
                                <input type="url" class="form-control" id="sitio_web" name="sitio_web" 
                                       value="<?= $proveedor ? esc($proveedor['sitio_web']) : '' ?>">
                            </div>
                        </div>
                    </div>
                    <h5 class="mb-3 mt-4"><i class="fas fa-user"></i> Contacto Principal</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="contacto_nombre">Nombre</label>
                                <input type="text" class="form-control" id="contacto_nombre" name="contacto_nombre" 
                                       value="<?= $proveedor ? esc($proveedor['contacto_nombre']) : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="contacto_cargo">Cargo</label>
                                <input type="text" class="form-control" id="contacto_cargo" name="contacto_cargo" 
                                       value="<?= $proveedor ? esc($proveedor['contacto_cargo']) : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="contacto_telefono">Teléfono</label>
                                <input type="text" class="form-control" id="contacto_telefono" name="contacto_telefono" 
                                       value="<?= $proveedor ? esc($proveedor['contacto_telefono']) : '' ?>">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="contacto_email">Email</label>
                                <input type="email" class="form-control" id="contacto_email" name="contacto_email" 
                                       value="<?= $proveedor ? esc($proveedor['contacto_email']) : '' ?>">
                            </div>
                        </div>
                    </div>
                    <h5 class="mb-3 mt-4"><i class="fas fa-money-bill"></i> Información Comercial</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="dias_pago">Días de Pago</label>
                                <input type="number" class="form-control" id="dias_pago" name="dias_pago" 
                                       value="<?= $proveedor ? $proveedor['dias_pago'] : '0' ?>" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="moneda_preferida">Moneda Preferida</label>
                                <select class="form-control" id="moneda_preferida" name="moneda_preferida">
                                    <option value="PYG" <?= (!$proveedor || $proveedor['moneda_preferida'] == 'PYG') ? 'selected' : '' ?>>Guaraníes (PYG)</option>
                                    <option value="USD" <?= $proveedor && $proveedor['moneda_preferida'] == 'USD' ? 'selected' : '' ?>>Dólares (USD)</option>
                                    <option value="BRL" <?= $proveedor && $proveedor['moneda_preferida'] == 'BRL' ? 'selected' : '' ?>>Reales (BRL)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="condiciones_pago">Condiciones de Pago</label>
                                <textarea class="form-control" id="condiciones_pago" name="condiciones_pago" rows="3"><?= $proveedor ? esc($proveedor['condiciones_pago']) : '' ?></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="cuenta_bancaria">Cuenta Bancaria</label>
                                <input type="text" class="form-control" id="cuenta_bancaria" name="cuenta_bancaria" 
                                       value="<?= $proveedor ? esc($proveedor['cuenta_bancaria']) : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="banco">Banco</label>
                                <input type="text" class="form-control" id="banco" name="banco" 
                                       value="<?= $proveedor ? esc($proveedor['banco']) : '' ?>">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="observaciones">Observaciones</label>
                                <textarea class="form-control" id="observaciones" name="observaciones" rows="3"><?= $proveedor ? esc($proveedor['observaciones']) : '' ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Guardar
                    </button>
                    <a href="<?= base_url('compras/proveedores') ?>" class="btn btn-default">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(document).ready(function() {
    $('#proveedorForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const proveedorId = <?= $proveedor ? $proveedor['id'] : 'null' ?>;
        const url = proveedorId ? 
            '<?= base_url('compras/proveedores/editar') ?>/' + proveedorId :
            '<?= base_url('compras/proveedores/guardar') ?>';
        
        $.ajax({
            url: url,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Éxito',
                        text: response.message || 'Proveedor guardado exitosamente',
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        if (response.data && response.data.redirect) {
                            window.location.href = response.data.redirect;
                        } else {
                            window.location.href = '<?= base_url('compras/proveedores') ?>';
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || 'Error al guardar el proveedor'
                    });
                }
            },
            error: function(xhr) {
                let mensaje = 'Error al procesar la solicitud';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    mensaje = xhr.responseJSON.message;
                }
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: mensaje
                });
            }
        });
    });
});
</script>
<?= $this->endSection() ?>
