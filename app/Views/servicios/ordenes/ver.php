<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('styles') ?>
<style>
    .order-header {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        margin-bottom: 20px;
        border-left: 5px solid <?= $orden['estado']['color'] ?>;
    }

    .status-badge {
        font-size: 1rem;
        padding: 8px 15px;
        border-radius: 20px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .timeline-steps {
        display: flex;
        justify-content: space-between;
        margin-top: 20px;
        position: relative;
    }
    
    .timeline-steps::before {
        content: '';
        position: absolute;
        top: 15px;
        left: 0;
        right: 0;
        height: 2px;
        background: #e9ecef;
        z-index: 1;
    }

    .step {
        background: #fff;
        z-index: 2;
        text-align: center;
        width: 100px;
    }

    .step-circle {
        width: 32px;
        height: 32px;
        background: #e9ecef;
        border-radius: 50%;
        margin: 0 auto 5px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #6c757d;
        font-weight: bold;
    }

    .step.active .step-circle {
        background: #007bff;
        color: #fff;
        box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.2);
    }
    
    .step.completed .step-circle {
        background: #28a745;
        color: #fff;
    }

    .action-button {
        width: 100%;
        margin-bottom: 10px;
        padding: 12px;
        border-radius: 8px;
        font-weight: 600;
        text-align: left;
        display: flex;
        align-items: center;
        justify-content: space-between;
        transition: transform 0.2s;
    }
    
    .action-button:hover {
        transform: translateY(-2px);
    }
    
    .action-button i {
        font-size: 1.2rem;
    }

    .info-group {
        margin-bottom: 15px;
    }
    
    .info-label {
        font-size: 0.85rem;
        color: #6c757d;
        text-transform: uppercase;
        font-weight: 600;
        margin-bottom: 3px;
    }
    
    .info-value {
        font-size: 1rem;
        color: #343a40;
        font-weight: 500;
    }
    
    .price-summary {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
    }
    
    .price-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 5px;
        font-size: 0.95rem;
    }
    
    .price-total {
        font-size: 1.2rem;
        font-weight: 700;
        color: #28a745;
        border-top: 1px solid #dee2e6;
        padding-top: 10px;
        margin-top: 5px;
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('servicios/ordenes') ?>">Órdenes DE SERVICIO</a></li>
<li class="breadcrumb-item active"><?= esc($orden['numero_orden']) ?></li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<!-- Header & Status -->
<div class="order-header d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h4 class="m-0 font-weight-bold text-dark">
            <i class="fas fa-file-invoice mr-2"></i> Orden #<?= esc($orden['numero_orden']) ?>
        </h4>
        <div class="mt-2 text-muted">
            <i class="far fa-calendar-alt mr-1"></i> Recepción: <?= date('d/m/Y H:i', strtotime($orden['fecha_recepcion'])) ?>
            <span class="mx-2">|</span>
            <i class="fas fa-user-tag mr-1"></i> Cliente: <?= esc($orden['cliente']['nombre'] . ' ' . ($orden['cliente']['apellido'] ?? '')) ?>
        </div>
    </div>
    <div class="text-right mt-3 mt-md-0">
        <span class="status-badge" style="background-color: <?= $orden['estado']['color'] ?>; color: #fff;">
            <?= esc($orden['estado']['nombre']) ?>
        </span>
        <div class="mt-2 text-danger font-weight-bold">
            <?php if ($orden['prioridad'] == 'URGENTE'): ?>
                <i class="fas fa-exclamation-triangle"></i> URGENTE
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row">
    <!-- Left Column: Details -->
    <div class="col-lg-3">
        <!-- Client Details -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h6 class="m-0 font-weight-bold text-primary">Cliente</h6>
            </div>
            <div class="card-body">
                <div class="info-group">
                    <div class="info-label">Nombre</div>
                    <div class="info-value"><?= esc($orden['cliente']['nombre'] . ' ' . ($orden['cliente']['apellido'] ?? '')) ?></div>
                </div>
                <div class="info-group">
                    <div class="info-label">Teléfono</div>
                    <div class="info-value">
                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $orden['cliente']['telefono'] ?? '') ?>" target="_blank" class="text-success">
                            <i class="fab fa-whatsapp"></i> <?= esc($orden['cliente']['telefono'] ?? 'N/A') ?>
                        </a>
                    </div>
                </div>
                <div class="info-group">
                    <div class="info-label">RUC/CI</div>
                    <div class="info-value"><?= esc($orden['cliente']['documento'] ?? 'N/A') ?></div>
                </div>
            </div>
        </div>
        
        <!-- Equipment Details -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h6 class="m-0 font-weight-bold text-primary">Equipo</h6>
            </div>
            <div class="card-body">
                <div class="info-group">
                    <div class="info-label">Tipo</div>
                    <div class="info-value"><?= esc($orden['tipo_equipo']) ?></div>
                </div>
                <div class="info-group">
                    <div class="info-label">Marca/Modelo</div>
                    <div class="info-value"><?= esc($orden['marca'] . ' ' . $orden['modelo']) ?></div>
                </div>
                <div class="info-group">
                    <div class="info-label">Nro Serie / IMEI</div>
                    <div class="info-value"><?= esc($orden['numero_serie'] ?? $orden['imei'] ?? 'N/A') ?></div>
                </div>
                 <div class="info-group pl-3 border-left-primary">
                    <div class="info-label text-warning">Contraseña / Patrón</div>
                    <div class="info-value"><?= esc($orden['clave_equipo'] ?? $orden['patron_desbloqueo'] ?? 'No especificado') ?></div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h6 class="m-0 font-weight-bold text-primary">Acciones Rápidas</h6>
            </div>
            <div class="card-body p-2">
                <button type="button" class="btn btn-outline-primary action-button" onclick="$('#modalCambiarEstado').modal('show')">
                    <span>Cambiar Estado</span>
                    <i class="fas fa-exchange-alt"></i>
                </button>
                
                <button type="button" class="btn btn-outline-success action-button" onclick="$('#modalAgregarRepuesto').modal('show')">
                    <span>Agregar Repuesto</span>
                    <i class="fas fa-box-open"></i>
                </button>
                
                <button type="button" class="btn btn-outline-info action-button" onclick="$('#modalAgregarManoObra').modal('show')">
                    <span>Agregar Mano Obra</span>
                    <i class="fas fa-tools"></i>
                </button>
                
                <hr>
                
                <?php if ($orden['estado']['permite_facturacion']): ?>
                    <button type="button" class="btn btn-success action-button text-white" onclick="facturarOrden()">
                        <span>Facturar y Entregar</span>
                        <i class="fas fa-file-invoice-dollar"></i>
                    </button>
                <?php endif; ?>
                
                <a href="<?= base_url('servicios/ordenes/generar-presupuesto/' . $orden['id']) ?>" target="_blank" class="btn btn-secondary action-button text-white">
                    <span>Imprimir Presupuesto</span>
                    <i class="fas fa-print"></i>
                </a>

                <a href="<?= base_url('servicios/ordenes/imprimir-recepcion/' . $orden['id']) ?>" target="_blank" class="btn btn-dark action-button text-white">
                    <span>Ticket Recepción</span>
                    <i class="fas fa-receipt"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Center/Right Column: Main Content -->
    <div class="col-lg-9">
        
        <!-- Problem Description -->
        <div class="card shadow-sm mb-4 border-left-danger">
            <div class="card-body">
                <h6 class="font-weight-bold text-danger"><i class="fas fa-exclamation-circle"></i> Problema Reportado</h6>
                <p class="mb-0 text-dark"><?= nl2br(esc($orden['problema_reportado'])) ?></p>
            </div>
        </div>

        <!-- Tabs -->
        <div class="card shadow-sm">
            <div class="card-header p-0 pt-1 border-bottom-0">
                <ul class="nav nav-tabs" id="custom-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="tab-diagnostico" data-bs-toggle="tab" href="#content-diagnostico" role="tab">Diagnóstico & Notas</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="tab-repuestos" data-bs-toggle="tab" href="#content-repuestos" role="tab">Repuestos y Costos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="tab-historial" data-bs-toggle="tab" href="#content-historial" role="tab">Historial</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="tab-imagenes" data-bs-toggle="tab" href="#content-imagenes" role="tab">Imágenes</a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content" id="custom-tabs-content">
                    
                    <!-- Tab: Diagnóstico -->
                    <div class="tab-pane fade show active" id="content-diagnostico" role="tabpanel">
                        <form id="formDiagnostico" action="<?= base_url('servicios/ordenes/actualizar/' . $orden['id']) ?>" method="POST">
                            <div class="form-group">
                                <label class="font-weight-bold">Diagnóstico Técnico:</label>
                                <textarea class="form-control" name="diagnostico" rows="3" placeholder="Detalle el diagnóstico..."><?= esc($orden['diagnostico']) ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="font-weight-bold">Solución Aplicada:</label>
                                <textarea class="form-control" name="solucion_aplicada" rows="3" placeholder="Describa la solución..."><?= esc($orden['solucion_aplicada']) ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="font-weight-bold">Observaciones Internas:</label>
                                <textarea class="form-control" name="observaciones_tecnico" rows="2"><?= esc($orden['observaciones_tecnico']) ?></textarea>
                            </div>
                            <!-- Hidden required fields for update validation -->
                            <input type="hidden" name="cliente_id" value="<?= $orden['cliente_id'] ?>">
                            <input type="hidden" name="tipo_equipo" value="<?= $orden['tipo_equipo'] ?>">
                            <input type="hidden" name="problema_reportado" value="<?= $orden['problema_reportado'] ?>">
                            
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Guardar Notas</button>
                        </form>
                    </div>

                    <!-- Tab: Repuestos y Costos -->
                    <div class="tab-pane fade" id="content-repuestos" role="tabpanel">
                        <div class="table-responsive mb-3">
                            <table class="table table-bordered table-sm">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Descripción</th>
                                        <th class="text-right">Cant.</th>
                                        <th class="text-right">Precio Unit.</th>
                                        <th class="text-right">Total</th>
                                        <th>Estado</th>
                                        <th class="text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($orden['repuestos']) && empty($orden['mano_obra'])): ?>
                                        <tr><td colspan="6" class="text-center text-muted">No hay items cargados</td></tr>
                                    <?php endif; ?>
                                    
                                    <?php foreach($orden['repuestos'] as $rep): ?>
                                    <tr id="row-rep-<?= $rep['id'] ?>">
                                        <td><span class="badge badge-secondary">REPUESTO</span></td>
                                        <td><?= esc($rep['producto_nombre'] ?? 'Desconocido') ?></td>
                                        <td class="text-right"><?= $rep['cantidad'] ?></td>
                                        <td class="text-right"><?= number_format($rep['precio_unitario'], 0, ',', '.') ?></td>
                                        <td class="text-right font-weight-bold"><?= number_format($rep['total'], 0, ',', '.') ?></td>
                                        <td class="text-center">
                                            <?= $rep['es_presupuesto'] ? '<span class="badge badge-warning">Presupuestado</span>' : '<span class="badge badge-success">Confirmado</span>' ?>
                                        </td>
                                        <td class="text-right">
                                            <button type="button" class="btn btn-danger btn-xs" onclick="eliminarRepuesto(<?= $rep['id'] ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php foreach($orden['mano_obra'] as $mo): ?>
                                    <tr id="row-mo-<?= $mo['id'] ?>">
                                        <td><span class="badge badge-info">MANO OBRA</span></td>
                                        <td><?= esc($mo['descripcion']) ?></td>
                                        <td class="text-right">1</td>
                                        <td class="text-right"><?= number_format($mo['total'], 0, ',', '.') ?></td>
                                        <td class="text-right font-weight-bold"><?= number_format($mo['total'], 0, ',', '.') ?></td>
                                        <td class="text-center">
                                            <?= $mo['es_presupuesto'] ? '<span class="badge badge-warning">Presupuestado</span>' : '<span class="badge badge-success">Confirmado</span>' ?>
                                        </td>
                                        <td class="text-right">
                                            <button type="button" class="btn btn-danger btn-xs" onclick="eliminarManoObra(<?= $mo['id'] ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="row justify-content-end">
                            <div class="col-md-5">
                                <div class="price-summary">
                                    <h6 class="font-weight-bold mb-3">Resumen Económico</h6>
                                    <div class="price-row">
                                        <span>Repuestos:</span>
                                        <span id="total-repuestos"><?= number_format($orden['presupuesto_repuestos'] ?? 0, 0, ',', '.') ?> Gs.</span>
                                    </div>
                                    <div class="price-row">
                                        <span>Mano de Obra:</span>
                                        <span id="total-mano-obra"><?= number_format($orden['presupuesto_mano_obra'] ?? 0, 0, ',', '.') ?> Gs.</span>
                                    </div>
                                    <div class="price-row price-total">
                                        <span>TOTAL:</span>
                                        <span id="total-general"><?= number_format($orden['presupuesto_total'] ?? 0, 0, ',', '.') ?> Gs.</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Historial -->
                    <div class="tab-pane fade" id="content-historial" role="tabpanel">
                        <div class="timeline">
                            <?php foreach ($orden['historial'] as $evento): ?>
                                <div>
                                    <i class="fas fa-history bg-blue"></i>
                                    <div class="timeline-item">
                                        <span class="time"><i class="fas fa-clock"></i> <?= date('d/m/Y H:i', strtotime($evento['fecha_cambio'])) ?></span>
                                        <h3 class="timeline-header no-border">
                                            <a href="#">Usuario</a> cambió el estado a <strong><?= $estadosMap[$evento['estado_nuevo_id']] ?? 'Desconocido' ?></strong>
                                        </h3>
                                        <?php if (!empty($evento['observaciones'])): ?>
                                            <div class="timeline-body">
                                                <?= esc($evento['observaciones']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                             <div>
                                <i class="fas fa-clock bg-gray"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab: Imágenes -->
                    <div class="tab-pane fade" id="content-imagenes" role="tabpanel">
                         <div class="mb-3">
                             <form action="<?= base_url('servicios/ordenes/subir-imagen') ?>" class="dropzone" id="myDropzone">
                                 <input type="hidden" name="orden_id" value="<?= $orden['id'] ?>">
                             </form>
                         </div>
                         <div class="row" id="galeria-imagenes">
                            <?php foreach ($orden['imagenes'] as $img): ?>
                                <div class="col-md-3 col-6 mb-3 image-container" id="img-<?= $img['id'] ?>">
                                    <div class="card">
                                        <a href="<?= base_url('uploads/ordenes/' . $img['url']) ?>" data-toggle="lightbox" data-title="Imagen Orden" data-gallery="gallery">
                                            <img src="<?= base_url('uploads/ordenes/' . $img['url']) ?>" class="card-img-top img-fluid" alt="Imagen">
                                        </a>
                                        <div class="card-body p-1 text-center">
                                            <button class="btn btn-danger btn-sm btn-block btn-delete-img" data-id="<?= $img['id'] ?>">
                                                <i class="fas fa-trash"></i> Eliminar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                         </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Cambiar Estado -->
<div class="modal fade" id="modalCambiarEstado" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Actualizar Estado</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form action="<?= base_url('servicios/ordenes/cambiar-estado') ?>" method="POST" id="formCambiarEstado">
                <div class="modal-body">
                    <input type="hidden" name="orden_id" value="<?= $orden['id'] ?>">
                    <div class="form-group">
                        <label>Nuevo Estado</label>
                        <select name="estado_id" class="form-control" required>
                            <?php foreach ($estados as $est): ?>
                                <option value="<?= $est['id'] ?>" <?= $orden['estado_id'] == $est['id'] ? 'selected' : '' ?>><?= esc($est['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Observaciones</label>
                        <textarea name="observaciones" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambio</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Agregar Repuesto -->
<div class="modal fade" id="modalAgregarRepuesto" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Agregar Repuesto</h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formAgregarRepuesto">
                <div class="modal-body">
                    <input type="hidden" name="orden_id" value="<?= $orden['id'] ?>">
                    <div class="form-group">
                        <label>Buscar Producto</label>
                        <select class="form-control select2-producto" id="productoSelect" name="producto_select" style="width: 100%;">
                            <option value="">Buscar...</option>
                        </select>
                        <input type="hidden" name="producto_id" id="prodId">
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Cantidad</label>
                                <input type="number" name="cantidad" class="form-control" value="1" min="1" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Precio Unitario</label>
                                <input type="text" name="precio" id="prodPrecio" class="form-control" required oninput="formatCurrency(this)">
                            </div>
                        </div>
                    </div>
                    <div class="form-check">
                         <input class="form-check-input" type="checkbox" name="es_presupuesto" value="1" id="checkPresupuesto" checked>
                         <label class="form-check-label" for="checkPresupuesto">
                             Es solo para Presupuesto (No consume stock aún)
                         </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Agregar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Agregar Mano Obra -->
<div class="modal fade" id="modalAgregarManoObra" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Agregar Mano de Obra</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="formAgregarManoObra">
                <div class="modal-body">
                    <input type="hidden" name="orden_id" value="<?= $orden['id'] ?>">
                    <div class="form-group">
                        <label>Descripción del Trabajo</label>
                        <input type="text" name="descripcion" class="form-control" required placeholder="Ej: Cambio de pantalla">
                    </div>
                    <div class="form-group">
                        <label>Costo</label>
                        <input type="text" name="precio" class="form-control" required placeholder="0" oninput="formatCurrency(this)">
                    </div>
                    <div class="form-check">
                         <input class="form-check-input" type="checkbox" name="es_presupuesto" value="1" id="checkPresupuestoMo" checked>
                         <label class="form-check-label" for="checkPresupuestoMo">
                             Es solo para Presupuesto
                         </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-info">Agregar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Facturar Orden -->
<div class="modal fade" id="modalFacturarOrden" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Facturar y Entregar</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="formFacturarOrden">
                <div class="modal-body">
                    <p>Se generará una venta y se descontará stock de los repuestos utilizados.</p>
                    
                    <div class="form-group">
                        <label>Condición de Pago</label>
                        <select name="tipo_pago" class="form-control">
                            <option value="CONTADO">Contado</option>
                            <option value="CREDITO">Crédito</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Confirmar Facturación</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<!-- Dropzone JS -->
<script src="https://unpkg.com/dropzone@5/dist/min/dropzone.min.js"></script>
<link rel="stylesheet" href="https://unpkg.com/dropzone@5/dist/min/dropzone.min.css" type="text/css" />

<script>
    // Configuración Dropzone
    Dropzone.autoDiscover = false;
    
    $(document).ready(function() {
        var myDropzone = new Dropzone("#myDropzone", {
            paramName: "imagenes[]", // The name that will be used to transfer the file
            maxFilesize: 5, // MB
            acceptedFiles: 'image/*',
            uploadMultiple: true,
            parallelUploads: 5,
            dictDefaultMessage: "Arrastra fotos aquí o haz click para subir",
            success: function(file, response) {
                if(response.success) {
                    toastr.success('Imágenes subidas correctamente');
                    setTimeout(function(){ location.reload(); }, 1000);
                } else {
                    toastr.error('Error al subir: ' + response.message);
                }
            },
            error: function(file, response) {
                toastr.error('Error subiendo imagen');
            }
        });

        // Eliminar Imagen
        $(document).on('click', '.btn-delete-img', function(e) {
            e.preventDefault();
            if(!confirm('¿Estás seguro de eliminar esta imagen?')) return;
            
            var btn = $(this);
            var id = btn.data('id');
            
            $.post('<?= base_url('servicios/ordenes/eliminar-imagen') ?>', {imagen_id: id}, function(response) {
                if(response.success) {
                    toastr.success('Imagen eliminada');
                    $('#img-' + id).fadeOut();
                } else {
                    toastr.error(response.message);
                }
            });
        });


        // Initial Load for Select2 in Modal
        $('.select2').each(function() {
            $(this).select2({ 
                theme: 'bootstrap4',
                dropdownParent: $(this).closest('.modal') 
            });
        });

        // AJAX Guardar Diagnostico
        $('#formDiagnostico').submit(function(e) {
            e.preventDefault();
            const btn = $(this).find('button[type="submit"]');
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Guardando...');

            $.ajax({
                url: $(this).attr('action'),
                method: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        toastr.success(response.message || 'Notas guardadas correctamente');
                    } else {
                        toastr.error(response.message || 'Error al guardar');
                    }
                },
                error: function() {
                    toastr.error('Ocurrió un error al procesar la solicitud');
                },
                complete: function() {
                    btn.prop('disabled', false).html('<i class="fas fa-save"></i> Guardar Notas');
                }
            });
        });
        // Inicializar Select2 para productos
        $('#productoSelect').select2({
            dropdownParent: $('#modalAgregarRepuesto'),
            theme: 'bootstrap-5',
            placeholder: 'Buscar repuesto por código o nombre...',
            allowClear: true,
            minimumInputLength: 1,
            language: {
                inputTooShort: function() {
                    return 'Ingrese al menos 1 carcter para buscar';
                },
                searching: function() {
                    return 'Buscando productos...';
                },
                noResults: function() {
                    return 'No se encontraron productos';
                },
                errorLoading: function() {
                    return 'Error al cargar productos';
                }
            },
            ajax: {
                url: '<?= base_url('servicios/ordenes/buscar-productos') ?>',
                dataType: 'json',
                delay: 300,
                data: function (params) {
                    return {
                        q: params.term, // search term
                        page: params.page || 1
                    };
                },
                processResults: function (data) {
                    return {
                        results: $.map(data, function (item) {
                            // Formatear texto con más información
                            var stockText = item.stock > 0 ? 
                                '<span class="badge badge-success">Stock: ' + item.stock + '</span>' : 
                                '<span class="badge badge-danger">Sin stock</span>';
                            
                            return {
                                text: item.codigo + ' - ' + item.nombre,
                                id: item.id,
                                precio: item.precio_venta,
                                stock: item.stock,
                                codigo: item.codigo,
                                nombre: item.nombre,
                                html: '<div>' + 
                                      '<strong>' + item.codigo + '</strong> - ' + item.nombre + 
                                      ' <span class="text-muted">(Gs. ' + parseFloat(item.precio_venta).toLocaleString('es-PY') + ')</span> ' +
                                      stockText +
                                      '</div>'
                            }
                        })
                    };
                },
                cache: true
            },
            templateResult: function(item) {
                if (item.loading) return item.text;
                if (!item.html) return item.text;
                return $(item.html);
            },
            templateSelection: function(item) {
                return item.text || item.codigo + ' - ' + item.nombre;
            }
        });

        $('#productoSelect').on('select2:select', function (e) {
            var data = e.params.data;
            $('#prodId').val(data.id);
            $('#prodPrecio').val(data.precio);
            
            // Mostrar advertencia si no hay stock
            if (data.stock <= 0) {
                toastr.warning('Este producto no tiene stock disponible');
            }
        });

        // AJAX para cambiar estado
        $('#formCambiarEstado').submit(function(e) {
            e.preventDefault();
            $.post($(this).attr('action'), $(this).serialize(), function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    toastr.error(response.message);
                }
            });
        });

        // AJAX Agregar Repuesto
        // ... (existing code for repuesto)

        // Definir función globalmente para que sea accesible por onclick
        window.facturarOrden = function() {
            if(!confirm('¿Está seguro de facturar y entregar esta orden? Se generará una venta y se descontará stock.')) return;

            $.get('<?= base_url('servicios/ordenes/facturar/' . $orden['id']) ?>', function(response) {
                if(response.success) {
                    toastr.success(response.message);
                    if(response.redirect) {
                        setTimeout(function() {
                            window.location.href = response.redirect;
                        }, 1000);
                    } else {
                        location.reload();
                    }
                } else {
                    toastr.error(response.message);
                }
            }).fail(function() {
                toastr.error('Error al procesar la facturación');
            });
        };
        $('#formAgregarRepuesto').submit(function(e) {
            e.preventDefault();
            $.post('<?= base_url('servicios/ordenes/agregar-repuesto') ?>', $(this).serialize(), function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    location.reload();
                } else {
                    toastr.error(response.message);
                }
            });
        });

        // AJAX Agregar Mano Obra
        $('#formAgregarManoObra').submit(function(e) {
            e.preventDefault();
            $.post('<?= base_url('servicios/ordenes/agregar-mano-obra') ?>', $(this).serialize(), function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    location.reload();
                } else {
                    toastr.error(response.message);
                }
            });
        });
        // Formato de Moneda en Tiempo Real
        window.formatCurrency = function(input) {
            let value = input.value.replace(/\D/g, '');
            if (value === '') {
                input.value = '';
                return;
            }
            input.value = parseInt(value).toLocaleString('es-PY');
        };

        // Aplicar formato a inputs de precio
        $('input[name="precio"]').on('input', function() {
            formatCurrency(this);
        });

        // Limpiar modales al abrir
        $('#modalAgregarRepuesto').on('show.bs.modal', function () {
            $('#formAgregarRepuesto')[0].reset();
            $('#productoSelect').val(null).trigger('change');
            $('#prodId').val('');
            $('#checkPresupuesto').prop('checked', true);
        });

        $('#modalAgregarManoObra').on('show.bs.modal', function () {
            $('#formAgregarManoObra')[0].reset();
            $('#checkPresupuestoMo').prop('checked', true);
        });

        // AJAX Facturar Orden
        $('#formFacturarOrden').submit(function(e) {
            e.preventDefault();
            $.post('<?= base_url('servicios/ordenes/facturar/' . $orden['id']) ?>', $(this).serialize(), function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    if (response.redirect) {
                         setTimeout(function() {
                            window.location.href = response.redirect;
                        }, 1000);
                    } else {
                        location.reload();
                    }
                } else {
                    toastr.error(response.message);
                }
            });
        });
    });

    function facturarOrden() {
        $('#modalFacturarOrden').modal('show');
    }

    function eliminarRepuesto(id) {
        Swal.fire({
            title: '¿Eliminar repuesto?',
            text: "Esta acción no se puede deshacer",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('<?= base_url('servicios/ordenes/eliminar-repuesto') ?>', {
                    id: id,
                    <?= csrf_token() ?>: '<?= csrf_hash() ?>'
                }, function(resp) {
                    if(resp.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Eliminado',
                            text: resp.message,
                            timer: 2000,
                            showConfirmButton: false
                        });
                        $(`#row-rep-${id}`).fadeOut(500, function(){ $(this).remove(); });
                        
                        if(resp.data && resp.data.nuevos_totales) {
                            $('#total-repuestos').text(new Intl.NumberFormat('es-PY').format(resp.data.nuevos_totales.repuestos) + ' Gs.');
                            $('#total-mano-obra').text(new Intl.NumberFormat('es-PY').format(resp.data.nuevos_totales.mano_obra) + ' Gs.');
                            $('#total-general').text(new Intl.NumberFormat('es-PY').format(resp.data.nuevos_totales.total) + ' Gs.');
                        }
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: resp.message
                        });
                    }
                });
            }
        });
    }

    function eliminarManoObra(id) {
        Swal.fire({
            title: '¿Eliminar mano de obra?',
            text: "Esta acción no se puede deshacer",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('<?= base_url('servicios/ordenes/eliminar-mano-obra') ?>', {
                    id: id,
                    <?= csrf_token() ?>: '<?= csrf_hash() ?>'
                }, function(resp) {
                    if(resp.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Eliminado',
                            text: resp.message,
                            timer: 2000,
                            showConfirmButton: false
                        });
                        $(`#row-mo-${id}`).fadeOut(500, function(){ $(this).remove(); });

                        if(resp.data && resp.data.nuevos_totales) {
                            $('#total-repuestos').text(new Intl.NumberFormat('es-PY').format(resp.data.nuevos_totales.repuestos) + ' Gs.');
                            $('#total-mano-obra').text(new Intl.NumberFormat('es-PY').format(resp.data.nuevos_totales.mano_obra) + ' Gs.');
                            $('#total-general').text(new Intl.NumberFormat('es-PY').format(resp.data.nuevos_totales.total) + ' Gs.');
                        }
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: resp.message
                        });
                    }
                });
            }
        });
    }
</script>
<?= $this->endSection() ?>