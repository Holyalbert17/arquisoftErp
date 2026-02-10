<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('admin') ?>">Administración</a></li>
<li class="breadcrumb-item active">Configuración General</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <!-- Configuración General -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-cog mr-1"></i>
                    Configuración General del Sistema
                </h3>
            </div>
            <div class="card-body">
                <form id="formConfiguracion">
                    <!-- Configuración de Sistema -->
                    <div class="card card-primary card-outline">
                        <div class="card-header">
                            <h3 class="card-title">Configuración del Sistema</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>Nombre de la Empresa</label>
                                <input type="text" 
                                       class="form-control" 
                                       name="configuraciones[nombre_empresa]"
                                       value="<?= esc($configuraciones['SISTEMA']['nombre_empresa'] ?? '') ?>"
                                       placeholder="Nombre de la empresa">
                            </div>
                            <div class="form-group">
                                <label>Moneda Principal</label>
                                <select class="form-control" name="configuraciones[moneda_principal]">
                                    <option value="PYG" <?= ($configuraciones['SISTEMA']['moneda_principal'] ?? 'PYG') == 'PYG' ? 'selected' : '' ?>>Guaraníes (PYG)</option>
                                    <option value="USD" <?= ($configuraciones['SISTEMA']['moneda_principal'] ?? '') == 'USD' ? 'selected' : '' ?>>Dólares (USD)</option>
                                    <option value="EUR" <?= ($configuraciones['SISTEMA']['moneda_principal'] ?? '') == 'EUR' ? 'selected' : '' ?>>Euros (EUR)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Formato de Fecha</label>
                                <select class="form-control" name="configuraciones[formato_fecha]">
                                    <option value="d/m/Y" <?= ($configuraciones['SISTEMA']['formato_fecha'] ?? 'd/m/Y') == 'd/m/Y' ? 'selected' : '' ?>>dd/mm/yyyy</option>
                                    <option value="Y-m-d" <?= ($configuraciones['SISTEMA']['formato_fecha'] ?? '') == 'Y-m-d' ? 'selected' : '' ?>>yyyy-mm-dd</option>
                                    <option value="m/d/Y" <?= ($configuraciones['SISTEMA']['formato_fecha'] ?? '') == 'm/d/Y' ? 'selected' : '' ?>>mm/dd/yyyy</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Zona Horaria</label>
                                <input type="text" 
                                       class="form-control" 
                                       name="configuraciones[zona_horaria]"
                                       value="<?= esc($configuraciones['SISTEMA']['zona_horaria'] ?? 'America/Asuncion') ?>"
                                       placeholder="America/Asuncion">
                            </div>
                        </div>
                    </div>

                    <!-- Configuración de Ventas -->
                    <div class="card card-success card-outline mt-3">
                        <div class="card-header">
                            <h3 class="card-title">Configuración de Ventas</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>Redondear Precios</label>
                                <select class="form-control" name="configuraciones[redondear_precios]">
                                    <option value="1" <?= ($configuraciones['VENTAS']['redondear_precios'] ?? '1') == '1' ? 'selected' : '' ?>>Sí</option>
                                    <option value="0" <?= ($configuraciones['VENTAS']['redondear_precios'] ?? '') == '0' ? 'selected' : '' ?>>No</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Permitir Descuentos</label>
                                <select class="form-control" name="configuraciones[permitir_descuentos]">
                                    <option value="1" <?= ($configuraciones['VENTAS']['permitir_descuentos'] ?? '1') == '1' ? 'selected' : '' ?>>Sí</option>
                                    <option value="0" <?= ($configuraciones['VENTAS']['permitir_descuentos'] ?? '') == '0' ? 'selected' : '' ?>>No</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Stock Mínimo (Alertas)</label>
                                <input type="number" 
                                       class="form-control" 
                                       name="configuraciones[stock_minimo]"
                                       value="<?= esc($configuraciones['VENTAS']['stock_minimo'] ?? '10') ?>"
                                       min="0"
                                       placeholder="10">
                            </div>
                        </div>
                    </div>

                    <!-- Configuración de Facturación -->
                    <div class="card card-info card-outline mt-3">
                        <div class="card-header">
                            <h3 class="card-title">Configuración de Facturación</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>Imprimir Automáticamente</label>
                                <select class="form-control" name="configuraciones[imprimir_automatico]">
                                    <option value="1" <?= ($configuraciones['FACTURACION']['imprimir_automatico'] ?? '0') == '1' ? 'selected' : '' ?>>Sí</option>
                                    <option value="0" <?= ($configuraciones['FACTURACION']['imprimir_automatico'] ?? '0') == '0' ? 'selected' : '' ?>>No</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Formato de Impresión</label>
                                <select class="form-control" name="configuraciones[formato_impresion]">
                                    <option value="TICKET" <?= ($configuraciones['FACTURACION']['formato_impresion'] ?? 'TICKET') == 'TICKET' ? 'selected' : '' ?>>Ticket</option>
                                    <option value="A4" <?= ($configuraciones['FACTURACION']['formato_impresion'] ?? '') == 'A4' ? 'selected' : '' ?>>A4</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="modulo" value="GENERAL">
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Guardar Configuración
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Resumen de Talonarios -->
        <div class="card mt-3">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-file-invoice mr-1"></i>
                    Talonarios Registrados
                </h3>
                <div class="card-tools">
                    <a href="<?= base_url('configuracion/talonarios') ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-cog"></i> Gestionar Talonarios
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($talonarios)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No hay talonarios registrados.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>Tipo</th>
                                    <th>Timbrado</th>
                                    <th>Número Actual</th>
                                    <th>Vigencia Hasta</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($talonarios as $talonario): ?>
                                    <tr>
                                        <td><?= esc($talonario['tipo_comprobante'] ?? 'N/A') ?></td>
                                        <td><?= esc($talonario['timbrado'] ?? 'N/A') ?></td>
                                        <td><?= esc($talonario['numero_actual'] ?? '0') ?></td>
                                        <td>
                                            <?php if (!empty($talonario['vigencia_hasta'])): ?>
                                                <?= date('d/m/Y', strtotime($talonario['vigencia_hasta'])) ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $estado = $talonario['estado'] ?? 'INACTIVO';
                                            $color = $estado == 'ACTIVO' ? 'success' : 'secondary';
                                            ?>
                                            <span class="badge badge-<?= $color ?>"><?= esc($estado) ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tipos de Cambio Recientes -->
        <div class="card mt-3">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-exchange-alt mr-1"></i>
                    Tipos de Cambio Recientes
                </h3>
                <div class="card-tools">
                    <a href="<?= base_url('configuracion/tipos-cambio') ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-cog"></i> Gestionar Tipos de Cambio
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($tipos_cambio)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No hay tipos de cambio registrados.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Moneda</th>
                                    <th>Compra</th>
                                    <th>Venta</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tipos_cambio as $tc): ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($tc['fecha'])) ?></td>
                                        <td><?= esc($tc['moneda'] ?? 'USD') ?></td>
                                        <td><?= number_format((float)($tc['compra'] ?? 0), 2, ',', '.') ?></td>
                                        <td><?= number_format((float)($tc['venta'] ?? 0), 2, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(document).ready(function() {
    $('#formConfiguracion').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        
        $.ajax({
            url: '<?= base_url('admin/configuracion/guardar') ?>',
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Éxito',
                        text: response.message || 'Configuración guardada exitosamente',
                        showConfirmButton: false,
                        timer: 1500
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || 'Error al guardar la configuración'
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
