<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('styles') ?>
<!-- Select2 -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">
<link rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/select2-bootstrap-theme/0.1.0-beta.10/select2-bootstrap.min.css">
<style>
    .form-group label {
        font-weight: 600;
        color: #495057;
    }

    .required-field::after {
        content: "*";
        color: red;
        margin-left: 2px;
    }

    .quick-actions {
        position: absolute;
        right: 15px;
        top: 0;
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('servicios/ordenes') ?>">Órdenes de Servicio</a></li>
<li class="breadcrumb-item active"><?= isset($orden) && $orden ? 'Editar' : 'Nueva' ?></li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-md-12">
        <div class="card card-primary card-outline">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-edit mr-1"></i>
                    <?= isset($orden) && $orden ? 'Editar Orden #' . esc($orden['numero_orden']) : 'Nueva Orden de Recepción' ?>
                </h3>
            </div>
            <form id="ordenForm" method="post" enctype="multipart/form-data"
                action="<?= isset($orden) && $orden ? base_url('servicios/ordenes/actualizar/' . $orden['id']) : base_url('servicios/ordenes/crear') ?>">
                <?php if (isset($orden) && $orden): ?>
                    <input type="hidden" name="orden_id" value="<?= $orden['id'] ?>">
                <?php endif; ?>

                <div class="card-body">
                    <!-- SECCIÓN 1: DATOS PRINCIPALES -->
                    <h5 class="text-primary border-bottom pb-2 mb-3">
                        <i class="fas fa-user-circle"></i> Datos del Cliente y Prioridad
                    </h5>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group position-relative">
                                <label for="cliente_id" class="required-field">Cliente</label>
                                <div class="input-group">
                                    <select class="form-control select2-ajax" id="cliente_id" name="cliente_id" required
                                        style="width: 100%;">
                                        <?php if (isset($orden) && $orden && isset($orden['cliente'])): ?>
                                            <option value="<?= $orden['cliente']['id'] ?>" selected>
                                                <?= esc($orden['cliente']['nombre'] . ' ' . ($orden['cliente']['apellido'] ?? '')) . ' - ' . ($orden['cliente']['documento'] ?? '') ?>
                                            </option>
                                        <?php endif; ?>
                                    </select>
                                    <div class="input-group-append">
                                        <a href="<?= base_url('clientes/crear') ?>" target="_blank"
                                            class="btn btn-outline-success" title="Nuevo Cliente">
                                            <i class="fas fa-user-plus"></i>
                                        </a>
                                    </div>
                                </div>
                                <small class="form-text text-muted">Busque por Nombre, RUC o CI.</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="prioridad">Prioridad</label>
                                <select class="form-control" id="prioridad" name="prioridad">
                                    <?php
                                    $prioridades = ['NORMAL', 'BAJA', 'ALTA', 'URGENTE'];
                                    $prioridadActual = isset($orden) ? ($orden['prioridad'] ?? 'NORMAL') : 'NORMAL';
                                    foreach ($prioridades as $p):
                                        ?>
                                        <option value="<?= $p ?>" <?= $p == $prioridadActual ? 'selected' : '' ?>><?= $p ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="tecnico_asignado">Técnico (Opcional)</label>
                                <select class="form-control select2-ajax" id="tecnico_asignado" name="tecnico_asignado" style="width: 100%;">
                                    <?php if (isset($orden) && $orden && !empty($orden['tecnico_asignado']) && !empty($orden['tecnico'])): ?>
                                        <option value="<?= (int) $orden['tecnico_asignado'] ?>" selected>
                                            <?= esc(trim(($orden['tecnico']['nombre'] ?? '') . ' ' . ($orden['tecnico']['apellido'] ?? ''))) ?>
                                            <?= !empty($orden['tecnico']['ci']) ? ' (' . esc($orden['tecnico']['ci']) . ')' : '' ?>
                                        </option>
                                    <?php endif; ?>
                                </select>
                                <small class="form-text text-muted">Busque por nombre, apellido o CI. Solo aparecen usuarios con rol Técnico.</small>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 2: EQUIPO -->
                    <h5 class="text-primary border-bottom pb-2 mb-3 mt-4">
                        <i class="fas fa-mobile-alt"></i> Datos del Equipo
                    </h5>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="tipo_equipo" class="required-field">Tipo</label>
                                <input type="text" class="form-control" id="tipo_equipo" name="tipo_equipo"
                                    value="<?= isset($orden) ? esc($orden['tipo_equipo']) : '' ?>"
                                    placeholder="Ej: Celular, Notebook" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="marca">Marca</label>
                                <input type="text" class="form-control" id="marca" name="marca"
                                    value="<?= isset($orden) ? esc($orden['marca']) : '' ?>"
                                    placeholder="Samsung, Apple...">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="modelo">Modelo</label>
                                <input type="text" class="form-control" id="modelo" name="modelo"
                                    value="<?= isset($orden) ? esc($orden['modelo']) : '' ?>"
                                    placeholder="Galaxy S20, iPhone 12...">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="color">Color</label>
                                <input type="text" class="form-control" id="color" name="color"
                                    value="<?= isset($orden) ? esc($orden['color']) : '' ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="numero_serie">Nro Serie / IMEI</label>
                                <input type="text" class="form-control" id="numero_serie" name="numero_serie"
                                    value="<?= isset($orden) ? esc($orden['numero_serie'] ?? $orden['imei']) : '' ?>"
                                    placeholder="Identificador único">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="clave_equipo" class="text-danger">Contraseña / Patrón</label>
                                <input type="text" class="form-control border-danger" id="clave_equipo"
                                    name="clave_equipo"
                                    value="<?= isset($orden) ? esc($orden['clave_equipo'] ?? $orden['patron_desbloqueo']) : '' ?>"
                                    placeholder="Importante para pruebas">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="estado_fisico">Estado Físico / Detalles</label>
                                <input type="text" class="form-control" id="estado_fisico" name="estado_fisico"
                                    value="<?= isset($orden) ? esc($orden['estado_fisico']) : '' ?>"
                                    placeholder="Rayones, pantalla rota...">
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 3: PROBLEMA -->
                    <h5 class="text-primary border-bottom pb-2 mb-3 mt-4">
                        <i class="fas fa-wrench"></i> Diagnóstico Inicial
                    </h5>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="problema_reportado" class="required-field">Problema Reportado (Motivo de
                                    Ingreso)</label>
                                <textarea class="form-control" id="problema_reportado" name="problema_reportado"
                                    rows="3" required
                                    placeholder="Describa el problema..."><?= isset($orden) ? esc($orden['problema_reportado']) : '' ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="accesorios">Accesorios Recibidos</label>
                                <input type="text" class="form-control" id="accesorios" name="accesorios"
                                    value="<?= isset($orden) ? esc($orden['accesorios']) : '' ?>"
                                    placeholder="Cargador, funda, caja...">
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label><i class="fas fa-camera"></i> Adjuntar Fotos (Opcional)</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="imagenes" name="imagenes[]"
                                        multiple accept="image/*">
                                    <label class="custom-file-label" for="imagenes">Elegir archivos</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer d-flex justify-content-between">
                    <a href="<?= base_url('servicios/ordenes') ?>" class="btn btn-default">Cancelar</a>
                    <button type="submit" class="btn btn-primary btn-lg px-5">
                        <i class="fas fa-save"></i> Guardar Orden
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bs-custom-file-input/dist/bs-custom-file-input.min.js"></script>
<script>
    $(document).ready(function () {
        bsCustomFileInput.init();

        // Select2 AJAX Client Search
        $('#cliente_id').select2({
            theme: 'bootstrap4',
            placeholder: 'Buscar Cliente...',
            allowClear: true,
            ajax: {
                url: '<?= base_url('servicios/ordenes/buscar-clientes') ?>',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        term: params.term // search term
                    };
                },
                processResults: function (data) {
                    return {
                        results: $.map(data, function (item) {
                            return {
                                text: item.nombre + ' ' + (item.apellido || '') + ' (' + (item.documento || 'Sin doc') + ')',
                                id: item.id
                            }
                        })
                    };
                },
                cache: true
            },
            minimumInputLength: 1
        });

        // Select2 AJAX Técnico (solo rol técnico)
        $('#tecnico_asignado').select2({
            theme: 'bootstrap4',
            placeholder: 'Buscar Técnico...',
            allowClear: true,
            dropdownParent: $('#tecnico_asignado').parent(),
            ajax: {
                url: '<?= base_url('servicios/ordenes/buscar-tecnicos') ?>',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        term: params.term
                    };
                },
                processResults: function (data) {
                    return {
                        results: $.map(data, function (item) {
                            var nombre = (item.nombre || '') + ' ' + (item.apellido || '');
                            nombre = nombre.trim();
                            var ci = item.ci ? (' (' + item.ci + ')') : '';
                            return {
                                text: nombre + ci,
                                id: item.id
                            };
                        })
                    };
                },
                cache: true
            },
            minimumInputLength: 1
        });

        // Auto-focus on client search
        // setTimeout(() => $('#cliente_id').select2('open'), 500);

        // Form Submit Handler
        $('#ordenForm').on('submit', function (e) {
            e.preventDefault();

            var form = this;

            var btn = $(this).find('button[type="submit"]');
            var originalText = btn.html();

            btn.html('<i class="fas fa-spinner fa-spin"></i> Guardando...').prop('disabled', true);

            var formData = new FormData(form);

            $.ajax({
                url: $(form).attr('action'),
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.success) {
                        toastr.success(response.message || 'Orden guardada correctamente');
                        // Redirigir
                        if (response.data && response.data.redirect) {
                            setTimeout(function () {
                                window.location.href = response.data.redirect;
                            }, 500);
                        } else {
                            window.location.href = '<?= base_url("servicios/ordenes") ?>';
                        }
                    } else {
                        toastr.error(response.message || 'Error al guardar');
                        btn.html(originalText).prop('disabled', false);
                    }
                },
                error: function (xhr) {
                    var msg = 'Error de conexión';
                    if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                    toastr.error(msg);
                    btn.html(originalText).prop('disabled', false);
                }
            });
        });
    });
</script>
<?= $this->endSection() ?>