<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
<style>
    .kanban-board {
        display: flex;
        overflow-x: auto;
        padding-bottom: 20px;
        min-height: calc(100vh - 200px);
    }

    .kanban-column {
        min-width: 320px;
        max-width: 320px;
        margin-right: 15px;
        background-color: #f4f6f9;
        border-radius: 8px;
        display: flex;
        flex-direction: column;
        border: 1px solid #e0e0e0;
    }

    .kanban-header {
        font-weight: bold;
        padding: 12px 15px;
        color: white;
        border-radius: 8px 8px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .kanban-items {
        padding: 10px;
        flex-grow: 1;
        overflow-y: auto;
    }

    .kanban-card {
        background-color: white;
        border-radius: 6px;
        padding: 12px;
        margin-bottom: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        border-left: 5px solid transparent;
        cursor: grab;
        transition: transform 0.2s, box-shadow 0.2s;
        position: relative;
    }

    .kanban-card:active {
        cursor: grabbing;
    }

    .kanban-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
        z-index: 10;
    }

    /* Prioridad styling */
    .kanban-card[data-prioridad="URGENTE"] {
        border-left-color: #dc3545;
    }

    .kanban-card[data-prioridad="ALTA"] {
        border-left-color: #ffc107;
    }

    .kanban-card[data-prioridad="NORMAL"] {
        border-left-color: #17a2b8;
    }

    .kanban-card[data-prioridad="BAJA"] {
        border-left-color: #6c757d;
    }

    .card-meta {
        font-size: 0.85rem;
        color: #6c757d;
        margin-top: 6px;
        display: flex;
        align-items: center;
    }

    .card-meta i {
        margin-right: 5px;
        width: 16px;
        text-align: center;
    }

    .card-title {
        font-weight: 700;
        margin-bottom: 4px;
        font-size: 1.05rem;
        color: #343a40;
        display: flex;
        justify-content: space-between;
    }

    .card-client {
        font-weight: 600;
        color: #495057;
        margin-bottom: 6px;
        border-bottom: 1px solid #f0f0f0;
        padding-bottom: 4px;
    }

    .sortable-placeholder {
        background: #e9ecef;
        border: 2px dashed #ced4da;
        height: 100px;
        margin-bottom: 12px;
        border-radius: 6px;
    }

    /* Indicador de Prioridad en esquina */
    .badge-urgencia {
        font-size: 0.7rem;
        padding: 2px 6px;
        border-radius: 4px;
        text-transform: uppercase;
        font-weight: 800;
        letter-spacing: 0.5px;
    }

    .badge-urgencia.URGENTE {
        background-color: #ffebee;
        color: #dc3545;
    }

    .badge-urgencia.ALTA {
        background-color: #fff8e1;
        color: #ffc107;
    }

    .empty-state {
        text-align: center;
        color: #adb5bd;
        padding: 30px 10px;
        font-style: italic;
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('servicios/ordenes') ?>">Órdenes de Servicio</a></li>
<li class="breadcrumb-item active">Tablero Kanban</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row mb-3">
    <div class="col-12 d-flex justify-content-between">
        <div>
            <a href="<?= base_url('servicios/ordenes/crear') ?>" class="btn btn-primary shadow-sm">
                <i class="fas fa-plus"></i> Nueva Orden
            </a>
            <a href="<?= base_url('servicios/ordenes') ?>" class="btn btn-secondary shadow-sm ml-2">
                <i class="fas fa-list"></i> Vista Lista
            </a>
        </div>
        <div class="d-flex align-items-center">
            <span class="mr-2 text-muted"><i class="fas fa-info-circle"></i> Arrastra las tarjetas para cambiar el
                estado</span>
        </div>
    </div>
</div>

<div class="kanban-board-container" style="overflow-x: auto;">
    <div class="kanban-board">
        <?php foreach ($estados as $estado): ?>
            <div class="kanban-column" data-estado-id="<?= $estado['id'] ?>">
                <div class="kanban-header" style="background-color: <?= $estado['color'] ?>">
                    <span><?= esc($estado['nombre']) ?></span>
                    <span class="badge badge-light badge-pill text-dark count-badge">
                        <?= count($ordenesPorEstado[$estado['id']]['ordenes']) ?>
                    </span>
                </div>

                <div class="kanban-items connectedSortable" id="estado-<?= $estado['id'] ?>">
                    <?php if (empty($ordenesPorEstado[$estado['id']]['ordenes'])): ?>
                        <div class="empty-state">Sin órdenes</div>
                    <?php endif; ?>

                    <?php foreach ($ordenesPorEstado[$estado['id']]['ordenes'] as $orden): ?>
                        <div class="kanban-card" data-id="<?= $orden['id'] ?>" data-prioridad="<?= $orden['prioridad'] ?>"
                            onclick="window.location.href='<?= base_url('servicios/ordenes/ver/' . $orden['id']) ?>'">

                            <div class="card-title">
                                <span class="text-primary">#<?= esc($orden['numero_orden']) ?></span>
                                <?php if ($orden['prioridad'] != 'NORMAL' && $orden['prioridad'] != 'BAJA'): ?>
                                    <span class="badge-urgencia <?= $orden['prioridad'] ?>">
                                        <?= substr($orden['prioridad'], 0, 3) ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="card-client text-truncate">
                                <?php
                                $nombreCliente = $orden['nombre'] . ' ' . ($orden['apellido'] ?? '');
                                if (empty(trim($nombreCliente))) {
                                    $nombreCliente = $orden['nombre_fantasia'] ?? $orden['razon_social'];
                                }
                                echo esc($nombreCliente);
                                ?>
                            </div>

                            <div class="card-meta">
                                <i class="fas fa-mobile-alt text-secondary"></i>
                                <span class="text-truncate"><?= esc($orden['tipo_equipo'] . ' ' . $orden['marca']) ?></span>
                            </div>

                            <div class="card-meta text-truncate" title="<?= esc($orden['problema_reportado']) ?>">
                                <i class="fas fa-wrench text-secondary"></i>
                                <?= esc($orden['problema_reportado']) ?>
                            </div>

                            <div class="card-meta justify-content-between mt-2 pt-2 border-top">
                                <span class="text-muted" style="font-size: 0.75rem;">
                                    <i class="far fa-clock"></i> <?= date('d/m H:i', strtotime($orden['fecha_recepcion'])) ?>
                                </span>
                                <?php if ($orden['tecnico_asignado']): ?>
                                    <span class="badge badge-info" title="Técnico Asignado">
                                        <i class="fas fa-user-cog"></i>
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-warning" title="Sin Técnico">
                                        <i class="fas fa-user mb-0"></i> ?
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script>
    $(function () {
        $(".connectedSortable").sortable({
            connectWith: ".connectedSortable",
            placeholder: "sortable-placeholder",
            cursor: "grabbing",
            opacity: 0.8,
            tolerance: "pointer",
            receive: function (event, ui) {
                var ordenId = ui.item.data('id');
                var nuevoEstadoId = $(this).parent().data('estado-id');
                var sender = ui.sender; // Columna de origen por si hay que revertir

                // Loading visual
                ui.item.css('opacity', '0.5');

                $.ajax({
                    url: '<?= base_url('servicios/ordenes/cambiar-estado') ?>',
                    method: 'POST',
                    data: {
                        orden_id: ordenId,
                        estado_id: nuevoEstadoId,
                        observaciones: 'Cambio de estado desde Kanban (Drag & Drop)'
                    },
                    success: function (response) {
                        ui.item.css('opacity', '1');
                        if (response.success) {
                            toastr.success('Estado actualizado correctamente');
                            updateCounts();
                        } else {
                            toastr.error(response.message || 'Error al actualizar estado');
                            $(sender).sortable('cancel'); // Revertir
                        }
                    },
                    error: function () {
                        ui.item.css('opacity', '1');
                        toastr.error('Error de conexión');
                        $(sender).sortable('cancel'); // Revertir
                    }
                });
            }
        }).disableSelection();

        function updateCounts() {
            $('.kanban-column').each(function () {
                var count = $(this).find('.kanban-card').length;
                $(this).find('.count-badge').text(count);

                // Toggle empty state
                if (count === 0) {
                    if ($(this).find('.empty-state').length === 0) {
                        $(this).find('.kanban-items').append('<div class="empty-state">Sin órdenes</div>');
                    }
                } else {
                    $(this).find('.empty-state').remove();
                }
            });
        }
    });
</script>
<?= $this->endSection() ?>