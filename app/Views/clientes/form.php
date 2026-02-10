<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('clientes') ?>">Clientes</a></li>
<li class="breadcrumb-item active"><?= $cliente ? 'Editar' : 'Nuevo' ?></li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-md-12">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-user mr-1"></i>
                    <?= $cliente ? 'Editar Cliente' : 'Nuevo Cliente' ?>
                </h3>
            </div>
            <form id="clienteForm" method="post" enctype="multipart/form-data">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="tipo_cliente">Tipo de Cliente *</label>
                                <select class="form-control" id="tipo_cliente" name="tipo_cliente" required>
                                    <option value="PERSONA" <?= (!$cliente || $cliente['tipo_cliente'] == 'PERSONA') ? 'selected' : '' ?>>Persona</option>
                                    <option value="EMPRESA" <?= $cliente && $cliente['tipo_cliente'] == 'EMPRESA' ? 'selected' : '' ?>>Empresa</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="codigo">Código</label>
                                <input type="text" class="form-control" id="codigo" name="codigo" 
                                       value="<?= $cliente ? esc($cliente['codigo']) : '' ?>" readonly>
                                <small class="form-text text-muted">Se genera automáticamente</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="categoria_cliente">Categoría</label>
                                <select class="form-control" id="categoria_cliente" name="categoria_cliente">
                                    <option value="NORMAL" <?= (!$cliente || $cliente['categoria_cliente'] == 'NORMAL') ? 'selected' : '' ?>>Normal</option>
                                    <option value="VIP" <?= $cliente && $cliente['categoria_cliente'] == 'VIP' ? 'selected' : '' ?>>VIP</option>
                                    <option value="MAYORISTA" <?= $cliente && $cliente['categoria_cliente'] == 'MAYORISTA' ? 'selected' : '' ?>>Mayorista</option>
                                    <option value="DISTRIBUIDOR" <?= $cliente && $cliente['categoria_cliente'] == 'DISTRIBUIDOR' ? 'selected' : '' ?>>Distribuidor</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Campos para PERSONA -->
                    <div id="campos_persona" style="display: <?= (!$cliente || $cliente['tipo_cliente'] == 'PERSONA') ? 'block' : 'none' ?>;">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="nombre">Nombre *</label>
                                    <input type="text" class="form-control" id="nombre" name="nombre" 
                                           value="<?= $cliente ? esc($cliente['nombre']) : '' ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="apellido">Apellido</label>
                                    <input type="text" class="form-control" id="apellido" name="apellido" 
                                           value="<?= $cliente ? esc($cliente['apellido']) : '' ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="ci">Cédula de Identidad</label>
                                    <input type="text" class="form-control" id="ci" name="ci" 
                                           value="<?= $cliente ? esc($cliente['ci']) : '' ?>" pattern="[0-9]{1,8}">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="fecha_nacimiento">Fecha de Nacimiento</label>
                                    <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" 
                                           value="<?= $cliente && $cliente['fecha_nacimiento'] ? date('Y-m-d', strtotime($cliente['fecha_nacimiento'])) : '' ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="sexo">Sexo</label>
                                    <select class="form-control" id="sexo" name="sexo">
                                        <option value="">Seleccionar...</option>
                                        <option value="M" <?= $cliente && $cliente['sexo'] == 'M' ? 'selected' : '' ?>>Masculino</option>
                                        <option value="F" <?= $cliente && $cliente['sexo'] == 'F' ? 'selected' : '' ?>>Femenino</option>
                                        <option value="OTRO" <?= $cliente && $cliente['sexo'] == 'OTRO' ? 'selected' : '' ?>>Otro</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Campos para EMPRESA -->
                    <div id="campos_empresa" style="display: <?= $cliente && $cliente['tipo_cliente'] == 'EMPRESA' ? 'block' : 'none' ?>;">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="razon_social">Razón Social *</label>
                                    <input type="text" class="form-control" id="razon_social" name="razon_social" 
                                           value="<?= $cliente ? esc($cliente['razon_social']) : '' ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="nombre_fantasia">Nombre de Fantasía</label>
                                    <input type="text" class="form-control" id="nombre_fantasia" name="nombre_fantasia" 
                                           value="<?= $cliente ? esc($cliente['nombre_fantasia']) : '' ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="ruc">RUC</label>
                                    <input type="text" class="form-control" id="ruc" name="ruc" 
                                           value="<?= $cliente ? esc($cliente['ruc']) : '' ?>" 
                                           placeholder="80012345-1">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Datos de Contacto -->
                    <h5 class="mb-3 mt-4"><i class="fas fa-address-book"></i> Datos de Contacto</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="direccion">Dirección</label>
                                <input type="text" class="form-control" id="direccion" name="direccion" 
                                       value="<?= $cliente ? esc($cliente['direccion']) : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="ciudad">Ciudad</label>
                                <input type="text" class="form-control" id="ciudad" name="ciudad" 
                                       value="<?= $cliente ? esc($cliente['ciudad']) : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="departamento">Departamento</label>
                                <select class="form-control" id="departamento" name="departamento">
                                    <option value="">Seleccionar...</option>
                                    <?php 
                                    $departamentos = ['CAPITAL', 'CONCEPCIÓN', 'SAN PEDRO', 'CORDILLERA', 'GUAIRÁ', 
                                                     'CAAAGUAZÚ', 'CAAZAPÁ', 'ITAPÚA', 'MISIONES', 'PARAGUARÍ', 
                                                     'ALTO PARANÁ', 'CENTRAL', 'ÑEEMBUCÚ', 'AMAMBAY', 'CANINDEYÚ', 
                                                     'PRESIDENTE HAYES', 'BOQUERÓN', 'ALTO PARAGUAY'];
                                    foreach ($departamentos as $dept): 
                                    ?>
                                        <option value="<?= $dept ?>" <?= $cliente && $cliente['departamento'] == $dept ? 'selected' : '' ?>>
                                            <?= $dept ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="telefono">Teléfono</label>
                                <input type="text" class="form-control" id="telefono" name="telefono" 
                                       value="<?= $cliente ? esc($cliente['telefono']) : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="celular">Celular</label>
                                <input type="text" class="form-control" id="celular" name="celular" 
                                       value="<?= $cliente ? esc($cliente['celular']) : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= $cliente ? esc($cliente['email']) : '' ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Información Comercial -->
                    <h5 class="mb-3 mt-4"><i class="fas fa-handshake"></i> Información Comercial</h5>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="lista_precio_id">Lista de Precios</label>
                                <select class="form-control select2" id="lista_precio_id" name="lista_precio_id">
                                    <option value="">Seleccionar...</option>
                                    <?php foreach ($listas_precios as $lista): ?>
                                        <option value="<?= $lista['id'] ?>" 
                                                <?= $cliente && $cliente['lista_precio_id'] == $lista['id'] ? 'selected' : '' ?>>
                                            <?= esc($lista['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="limite_credito">Límite de Crédito (Gs.)</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="limite_credito" name="limite_credito" 
                                           value="<?= $cliente ? number_format((int)($cliente['limite_credito'] ?? 0), 0, '', '.') : '0' ?>" 
                                           placeholder="0" data-max-value="9999999999999">
                                    <input type="hidden" id="limite_credito_raw" name="limite_credito_raw">
                                    <div class="input-group-append">
                                        <span class="input-group-text">Gs.</span>
                                    </div>
                                </div>
                                <small class="form-text text-muted">Máximo: 9.999.999.999.999 Gs.</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="dias_credito">Días de Crédito</label>
                                <input type="number" class="form-control" id="dias_credito" name="dias_credito" 
                                       value="<?= $cliente ? $cliente['dias_credito'] : '0' ?>" min="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="descuento_porcentaje">Descuento %</label>
                                <input type="number" class="form-control" id="descuento_porcentaje" name="descuento_porcentaje" 
                                       value="<?= $cliente ? $cliente['descuento_porcentaje'] : '0' ?>" step="0.01" min="0" max="100">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="tipo_contribuyente">Tipo de Contribuyente</label>
                                <select class="form-control" id="tipo_contribuyente" name="tipo_contribuyente">
                                    <option value="CONTRIBUYENTE" <?= (!$cliente || $cliente['tipo_contribuyente'] == 'CONTRIBUYENTE') ? 'selected' : '' ?>>Contribuyente</option>
                                    <option value="NO_CONTRIBUYENTE" <?= $cliente && $cliente['tipo_contribuyente'] == 'NO_CONTRIBUYENTE' ? 'selected' : '' ?>>No Contribuyente</option>
                                    <option value="EXONERADO" <?= $cliente && $cliente['tipo_contribuyente'] == 'EXONERADO' ? 'selected' : '' ?>>Exonerado</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="foto">Foto</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="foto" name="foto" accept="image/*">
                                    <label class="custom-file-label" for="foto">Seleccionar archivo</label>
                                </div>
                                <?php if ($cliente && $cliente['foto']): ?>
                                    <small class="form-text text-muted">
                                        Foto actual: <a href="<?= base_url('uploads/clientes/' . $cliente['foto']) ?>" target="_blank">Ver</a>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="observaciones">Observaciones</label>
                                <textarea class="form-control" id="observaciones" name="observaciones" rows="3"><?= $cliente ? esc($cliente['observaciones']) : '' ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Guardar
                    </button>
                    <a href="<?= base_url('clientes') ?>" class="btn btn-default">
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
    $('.select2').select2({
        theme: 'bootstrap-5'
    });

    // Mostrar/ocultar campos según tipo de cliente
    $('#tipo_cliente').on('change', function() {
        const tipo = $(this).val();
        if (tipo == 'PERSONA') {
            $('#campos_persona').show();
            $('#campos_empresa').hide();
            $('#nombre').prop('required', true);
            $('#razon_social').prop('required', false);
        } else {
            $('#campos_persona').hide();
            $('#campos_empresa').show();
            $('#nombre').prop('required', false);
            $('#razon_social').prop('required', true);
        }
    });

    // Formato de archivo
    $('.custom-file-input').on('change', function() {
        let fileName = $(this).val().split('\\').pop();
        $(this).siblings('.custom-file-label').addClass('selected').html(fileName);
    });

    // Función para formatear número en guaraníes (sin decimales para límite de crédito)
    function formatearGuaranies(valor) {
        if (!valor || valor === '') return '0';
        // Remover todo excepto números y puntos
        let numero = valor.toString().replace(/[^\d.]/g, '');
        // Remover todos los puntos (separadores de miles)
        numero = numero.replace(/\./g, '');
        // Convertir a número entero
        let num = parseInt(numero) || 0;
        // Validar máximo (9,999,999,999,999.99 = 9999999999999.99)
        const MAX_VALUE = 9999999999999;
        if (num > MAX_VALUE) {
            num = MAX_VALUE;
        }
        // Formatear con separadores de miles (puntos)
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    // Función para convertir formato de guaraníes a número
    function convertirGuaraniesANumero(valor) {
        if (!valor || valor === '') return 0;
        // Remover puntos (separadores de miles)
        let numero = valor.toString().replace(/\./g, '');
        let num = parseFloat(numero) || 0;
        // Validar máximo
        const MAX_VALUE = 9999999999999.99;
        if (num > MAX_VALUE) {
            num = MAX_VALUE;
        }
        return num;
    }

    // Formatear límite de crédito mientras se escribe
    $('#limite_credito').on('input', function() {
        let valor = $(this).val();
        let cursorPos = this.selectionStart;
        let valorAntes = valor.substring(0, cursorPos);
        let longitudAntes = valorAntes.replace(/\./g, '').length;
        
        let valorFormateado = formatearGuaranies(valor);
        $(this).val(valorFormateado);
        
        // Actualizar campo oculto con el valor numérico
        $('#limite_credito_raw').val(convertirGuaraniesANumero(valorFormateado));
        
        // Restaurar posición del cursor (aproximada)
        let nuevaPos = Math.min(cursorPos, valorFormateado.length);
        this.setSelectionRange(nuevaPos, nuevaPos);
    });

    // Al perder el foco, asegurar formato correcto
    $('#limite_credito').on('blur', function() {
        let valor = $(this).val();
        let valorFormateado = formatearGuaranies(valor);
        $(this).val(valorFormateado);
        $('#limite_credito_raw').val(convertirGuaraniesANumero(valorFormateado));
    });

    // Inicializar campo oculto con el valor actual
    $('#limite_credito_raw').val(convertirGuaraniesANumero($('#limite_credito').val()));

    // Enviar formulario
    $('#clienteForm').on('submit', function(e) {
        e.preventDefault();
        
        // Convertir límite de crédito a número antes de enviar
        let limiteCreditoValor = $('#limite_credito_raw').val() || convertirGuaraniesANumero($('#limite_credito').val());
        $('#limite_credito').val(limiteCreditoValor);
        
        const formData = new FormData(this);
        const url = '<?= $cliente ? base_url("clientes/editar/" . $cliente["id"]) : base_url("clientes/crear") ?>';
        
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
                        text: response.message,
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        window.location.href = response.data.redirect;
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message
                    });
                }
            },
            error: function(xhr) {
                let message = 'Error al procesar la solicitud';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: message
                });
            }
        });
    });
});
</script>
<?= $this->endSection() ?>
