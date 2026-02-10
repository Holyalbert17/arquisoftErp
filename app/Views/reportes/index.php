<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item active">Reportes</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-bar mr-1"></i>
                    Reportes del Sistema
                </h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Reportes de Ventas -->
                    <div class="col-md-4">
                        <div class="card card-primary card-outline">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-shopping-cart mr-1"></i>
                                    Reportes de Ventas
                                </h3>
                            </div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item">
                                        <a href="<?= base_url('reportes/ventas') ?>" class="btn btn-block btn-sm btn-primary">
                                            <i class="fas fa-chart-line"></i> Reporte de Ventas
                                        </a>
                                    </li>
                                    <li class="list-group-item">
                                        <a href="#" class="btn btn-block btn-sm btn-secondary" onclick="generarReporte('VENTAS_DIARIAS')">
                                            <i class="fas fa-calendar-day"></i> Ventas Diarias
                                        </a>
                                    </li>
                                    <li class="list-group-item">
                                        <a href="#" class="btn btn-block btn-sm btn-secondary" onclick="generarReporte('VENTAS_MENSUALES')">
                                            <i class="fas fa-calendar-alt"></i> Ventas Mensuales
                                        </a>
                                    </li>
                                    <li class="list-group-item">
                                        <a href="#" class="btn btn-block btn-sm btn-secondary" onclick="generarReporte('PRODUCTOS_MAS_VENDIDOS')">
                                            <i class="fas fa-star"></i> Productos Más Vendidos
                                        </a>
                                    </li>
                                    <li class="list-group-item">
                                        <a href="#" class="btn btn-block btn-sm btn-secondary" onclick="generarReporte('VENTAS_POR_VENDEDOR')">
                                            <i class="fas fa-user-tie"></i> Ventas por Vendedor
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Reportes de Inventario -->
                    <div class="col-md-4">
                        <div class="card card-success card-outline">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-boxes mr-1"></i>
                                    Reportes de Inventario
                                </h3>
                            </div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item">
                                        <a href="<?= base_url('reportes/inventario') ?>" class="btn btn-block btn-sm btn-success">
                                            <i class="fas fa-list"></i> Reporte de Inventario
                                        </a>
                                    </li>
                                    <li class="list-group-item">
                                        <a href="#" class="btn btn-block btn-sm btn-secondary" onclick="generarReporte('STOCK_BAJO')">
                                            <i class="fas fa-exclamation-triangle"></i> Stock Bajo
                                        </a>
                                    </li>
                                    <li class="list-group-item">
                                        <a href="#" class="btn btn-block btn-sm btn-secondary" onclick="generarReporte('MOVIMIENTOS_STOCK')">
                                            <i class="fas fa-exchange-alt"></i> Movimientos de Stock
                                        </a>
                                    </li>
                                    <li class="list-group-item">
                                        <a href="#" class="btn btn-block btn-sm btn-secondary" onclick="generarReporte('VALORIZACION_INVENTARIO')">
                                            <i class="fas fa-dollar-sign"></i> Valorización de Inventario
                                        </a>
                                    </li>
                                    <li class="list-group-item">
                                        <a href="#" class="btn btn-block btn-sm btn-secondary" onclick="generarReporte('ROTACION_PRODUCTOS')">
                                            <i class="fas fa-sync-alt"></i> Rotación de Productos
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Reportes de Servicios -->
                    <div class="col-md-4">
                        <div class="card card-info card-outline">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-tools mr-1"></i>
                                    Reportes de Servicios
                                </h3>
                            </div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item">
                                        <a href="<?= base_url('reportes/servicios') ?>" class="btn btn-block btn-sm btn-info">
                                            <i class="fas fa-wrench"></i> Reporte de Servicios
                                        </a>
                                    </li>
                                    <li class="list-group-item">
                                        <a href="#" class="btn btn-block btn-sm btn-secondary" onclick="generarReporte('ORDENES_PENDIENTES')">
                                            <i class="fas fa-clock"></i> Órdenes Pendientes
                                        </a>
                                    </li>
                                    <li class="list-group-item">
                                        <a href="#" class="btn btn-block btn-sm btn-secondary" onclick="generarReporte('ORDENES_COMPLETADAS')">
                                            <i class="fas fa-check-circle"></i> Órdenes Completadas
                                        </a>
                                    </li>
                                    <li class="list-group-item">
                                        <a href="#" class="btn btn-block btn-sm btn-secondary" onclick="generarReporte('TIEMPO_PROMEDIO_SERVICIO')">
                                            <i class="fas fa-hourglass-half"></i> Tiempo Promedio
                                        </a>
                                    </li>
                                    <li class="list-group-item">
                                        <a href="#" class="btn btn-block btn-sm btn-secondary" onclick="generarReporte('INGRESOS_SERVICIOS')">
                                            <i class="fas fa-money-bill-wave"></i> Ingresos por Servicios
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Formulario para generar reportes personalizados -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card card-warning">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-cog mr-1"></i>
                                    Generar Reporte Personalizado
                                </h3>
                            </div>
                            <div class="card-body">
                                <form id="formReporte" method="POST" action="<?= base_url('reportes/generar') ?>">
                                    <?= csrf_field() ?>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="tipo_reporte">Tipo de Reporte <span class="text-danger">*</span></label>
                                                <select name="tipo_reporte" id="tipo_reporte" class="form-control" required>
                                                    <option value="">Seleccione...</option>
                                                    <optgroup label="Ventas">
                                                        <option value="VENTAS">Reporte General de Ventas</option>
                                                        <option value="VENTAS_DIARIAS">Ventas Diarias</option>
                                                        <option value="VENTAS_MENSUALES">Ventas Mensuales</option>
                                                        <option value="PRODUCTOS_MAS_VENDIDOS">Productos Más Vendidos</option>
                                                    </optgroup>
                                                    <optgroup label="Inventario">
                                                        <option value="INVENTARIO">Reporte General de Inventario</option>
                                                        <option value="STOCK_BAJO">Stock Bajo</option>
                                                        <option value="MOVIMIENTOS_STOCK">Movimientos de Stock</option>
                                                        <option value="VALORIZACION_INVENTARIO">Valorización</option>
                                                    </optgroup>
                                                    <optgroup label="Servicios">
                                                        <option value="SERVICIOS">Reporte General de Servicios</option>
                                                        <option value="ORDENES_PENDIENTES">Órdenes Pendientes</option>
                                                        <option value="ORDENES_COMPLETADAS">Órdenes Completadas</option>
                                                    </optgroup>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="fecha_desde">Fecha Desde</label>
                                                <input type="date" name="fecha_desde" id="fecha_desde" class="form-control" 
                                                       value="<?= date('Y-m-01') ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="fecha_hasta">Fecha Hasta</label>
                                                <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control" 
                                                       value="<?= date('Y-m-d') ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label for="formato">Formato</label>
                                                <select name="formato" id="formato" class="form-control">
                                                    <option value="PDF">PDF</option>
                                                    <option value="EXCEL">Excel</option>
                                                    <option value="CSV">CSV</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-file-download"></i> Generar Reporte
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
function generarReporte(tipo) {
    $('#tipo_reporte').val(tipo);
    $('#formReporte').submit();
}

$(document).ready(function() {
    $('#formReporte').on('submit', function(e) {
        e.preventDefault();
        
        const tipo = $('#tipo_reporte').val();
        if (!tipo) {
            Swal.fire('Advertencia', 'Por favor seleccione un tipo de reporte', 'warning');
            return;
        }
        
        Swal.fire({
            title: 'Generando Reporte',
            text: 'Por favor espere...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        const formData = new FormData(this);
        
        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                Swal.close();
                if (response.success) {
                    Swal.fire('Éxito', 'Reporte generado exitosamente', 'success');
                } else {
                    Swal.fire('Error', response.message || 'Error al generar el reporte', 'error');
                }
            },
            error: function(xhr) {
                Swal.close();
                const response = xhr.responseJSON;
                Swal.fire('Error', response?.message || 'Error al generar el reporte', 'error');
            }
        });
    });
});
</script>
<?= $this->endSection() ?>
