<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('content') ?>

<?php $esAdmin = $esAdmin ?? false; ?>

<!-- Bienvenida con rol -->
<div class="row mb-3">
    <div class="col-12">
        <div class="callout callout-info">
            <h5><i class="fas fa-user-circle mr-2"></i>Bienvenido, <?= esc($user['nombre'] ?? 'Usuario') ?> <?= esc($user['apellido'] ?? '') ?></h5>
            <p class="mb-0">
                <span class="badge badge-<?= $esAdmin ? 'primary' : 'secondary' ?> mr-2"><?= esc($nombreRol ?? 'Usuario') ?></span>
                <?php if (!$esAdmin): ?>
                    <small class="text-muted">Estás viendo tus datos personales de ventas y actividad.</small>
                <?php else: ?>
                    <small class="text-muted">Vista completa de administración.</small>
                <?php endif; ?>
            </p>
        </div>
    </div>
</div>

<!-- Info boxes - Ventas (visibles para todos, filtradas por usuario en el controller) -->
<div class="row">
    <!-- Ventas Hoy -->
    <div class="col-12 col-sm-6 <?= $esAdmin ? 'col-md-3' : 'col-md-6' ?>">
        <div class="info-box">
            <span class="info-box-icon bg-info elevation-1"><i class="fas fa-shopping-cart"></i></span>
            <div class="info-box-content">
                <span class="info-box-text"><?= $esAdmin ? 'Ventas Hoy' : 'Mis Ventas Hoy' ?></span>
                <span class="info-box-number">
                    <?= number_format($ventas_hoy['cantidad'] ?? 0) ?>
                    <small>ventas</small>
                </span>
                <span class="info-box-text">Total: <?= number_format($ventas_hoy['monto'] ?? 0, 0, ',', '.') ?> Gs.</span>
            </div>
        </div>
    </div>
    
    <!-- Ventas del Mes -->
    <div class="col-12 col-sm-6 <?= $esAdmin ? 'col-md-3' : 'col-md-6' ?>">
        <div class="info-box mb-3">
            <span class="info-box-icon bg-success elevation-1"><i class="fas fa-chart-line"></i></span>
            <div class="info-box-content">
                <span class="info-box-text"><?= $esAdmin ? 'Ventas del Mes' : 'Mis Ventas del Mes' ?></span>
                <span class="info-box-number">
                    <?= number_format($ventas_mes['cantidad'] ?? 0) ?>
                    <small>ventas</small>
                </span>
                <span class="info-box-text">Total: <?= number_format($ventas_mes['monto'] ?? 0, 0, ',', '.') ?> Gs.</span>
            </div>
        </div>
    </div>
    
    <?php if ($esAdmin): ?>
    <!-- Stock Crítico - Solo admins/gerentes -->
    <div class="col-12 col-sm-6 col-md-3">
        <div class="info-box mb-3">
            <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-exclamation-triangle"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Stock Crítico</span>
                <span class="info-box-number"><?= count($stock_critico ?? []) ?></span>
                <span class="info-box-text">productos</span>
            </div>
        </div>
    </div>
    
    <!-- Cuentas Vencidas - Solo admins/gerentes -->
    <div class="col-12 col-sm-6 col-md-3">
        <div class="info-box mb-3">
            <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-money-bill-wave"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Cuentas Vencidas</span>
                <span class="info-box-number"><?= number_format($cuentas_vencidas['cantidad'] ?? 0) ?></span>
                <span class="info-box-text">Total: <?= number_format($cuentas_vencidas['monto'] ?? 0, 0, ',', '.') ?> Gs.</span>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($esAdmin): ?>
<!-- Segunda fila de métricas - Solo admins/gerentes -->
<div class="row">
    <div class="col-12 col-sm-6 col-md-3">
        <div class="info-box mb-3">
            <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-boxes"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Stock Bajo</span>
                <span class="info-box-number"><?= count($stock_bajo ?? []) ?></span>
                <span class="info-box-text">productos</span>
            </div>
        </div>
    </div>
    
    <div class="col-12 col-sm-6 col-md-3">
        <div class="info-box mb-3">
            <span class="info-box-icon bg-primary elevation-1"><i class="fas fa-tools"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Órdenes Pendientes</span>
                <span class="info-box-number"><?= count($ordenes_pendientes ?? []) ?></span>
                <span class="info-box-text">en proceso</span>
            </div>
        </div>
    </div>
    
    <div class="col-12 col-sm-6 col-md-3">
        <div class="info-box mb-3">
            <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-exclamation-circle"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Órdenes Urgentes</span>
                <span class="info-box-number"><?= count($ordenes_urgentes ?? []) ?></span>
                <span class="info-box-text">requieren atención</span>
            </div>
        </div>
    </div>
    
    <div class="col-12 col-sm-6 col-md-3">
        <div class="info-box mb-3">
            <span class="info-box-icon bg-success elevation-1"><i class="fas fa-users"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Clientes Nuevos</span>
                <span class="info-box-number"><?= count($clientes_nuevos ?? []) ?></span>
                <span class="info-box-text">este mes</span>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <!-- Gráfico de Ventas -->
    <div class="<?= $esAdmin ? 'col-md-8' : 'col-md-12' ?>">
        <div class="card">
            <div class="card-header border-transparent">
                <h3 class="card-title">
                    <i class="fas fa-chart-line mr-1"></i>
                    <?= $esAdmin ? 'Ventas de los Últimos 7 Días' : 'Mis Ventas - Últimos 7 Días' ?>
                </h3>
            </div>
            <div class="card-body">
                <canvas id="ventasChart" style="height: 300px;"></canvas>
            </div>
        </div>
    </div>
    
    <?php if ($esAdmin): ?>
    <!-- Órdenes por Estado - Solo admins/gerentes -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-tasks mr-1"></i> Órdenes por Estado</h3>
            </div>
            <div class="card-body">
                <canvas id="ordenesChart" style="height: 300px;"></canvas>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($esAdmin): ?>
<!-- Tablas detalladas - Solo admins/gerentes -->
<div class="row">
    <!-- Stock Bajo -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-exclamation-triangle mr-1"></i> Productos con Stock Bajo</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Stock</th>
                            <th>Mínimo</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($stock_bajo) && empty($stock_critico)): ?>
                            <tr>
                                <td colspan="5" class="text-center">No hay productos con stock bajo</td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            $todosStockBajo = array_merge($stock_critico ?? [], $stock_bajo ?? []);
                            foreach (array_slice($todosStockBajo, 0, 10) as $producto): 
                            ?>
                                <tr>
                                    <td><?= esc($producto['codigo'] ?? 'N/A') ?></td>
                                    <td><?= esc($producto['nombre'] ?? 'N/A') ?></td>
                                    <td><span class="badge badge-<?= ($producto['stock_actual'] ?? 0) <= ($producto['stock_minimo'] ?? 0) ? 'danger' : 'warning' ?>"><?= number_format($producto['stock_actual'] ?? 0) ?></span></td>
                                    <td><?= number_format($producto['stock_minimo'] ?? 0) ?></td>
                                    <td>
                                        <?php if (($producto['stock_actual'] ?? 0) <= ($producto['stock_minimo'] ?? 0)): ?>
                                            <span class="badge badge-danger">Crítico</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Bajo</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                <a href="<?= base_url('inventario/stock') ?>" class="btn btn-sm btn-primary">Ver Todo el Stock</a>
            </div>
        </div>
    </div>
    
    <!-- Órdenes Pendientes -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-tools mr-1"></i> Órdenes de Servicio Pendientes</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>N° Orden</th>
                            <th>Cliente</th>
                            <th>Equipo</th>
                            <th>Prioridad</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ordenes_pendientes)): ?>
                            <tr>
                                <td colspan="5" class="text-center">No hay órdenes pendientes</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach (array_slice($ordenes_pendientes, 0, 10) as $orden): ?>
                                <tr>
                                    <td><a href="<?= base_url('servicios/ordenes/ver/' . ($orden['id'] ?? '')) ?>"><?= esc($orden['numero_orden'] ?? 'N/A') ?></a></td>
                                    <td><?= esc($orden['cliente_nombre'] ?? 'N/A') ?></td>
                                    <td><?= esc($orden['tipo_equipo'] ?? 'N/A') ?></td>
                                    <td>
                                        <?php
                                        $prioridad = $orden['prioridad'] ?? 'NORMAL';
                                        $badgeClass = [
                                            'URGENTE' => 'danger',
                                            'ALTA' => 'warning',
                                            'NORMAL' => 'info',
                                            'BAJA' => 'secondary'
                                        ][$prioridad] ?? 'secondary';
                                        ?>
                                        <span class="badge badge-<?= $badgeClass ?>"><?= esc($prioridad) ?></span>
                                    </td>
                                    <td><span class="badge badge-info"><?= esc($orden['estado'] ?? 'N/A') ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                <a href="<?= base_url('servicios/ordenes') ?>" class="btn btn-sm btn-primary">Ver Todas las Órdenes</a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Productos Más Vendidos -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-star mr-1"></i> Productos Más Vendidos (7 días)</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($productos_mas_vendidos)): ?>
                            <tr>
                                <td colspan="5" class="text-center">No hay datos disponibles</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($productos_mas_vendidos as $index => $producto): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= esc($producto['codigo']) ?></td>
                                    <td><?= esc($producto['nombre']) ?></td>
                                    <td><span class="badge badge-primary"><?= number_format($producto['total_vendido']) ?></span></td>
                                    <td><?= number_format($producto['monto_total'], 0, ',', '.') ?> Gs.</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Cuentas por Cobrar Vencidas -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-money-bill-wave mr-1"></i> Cuentas por Cobrar Vencidas</h3>
            </div>
            <div class="card-body">
                <div class="info-box mb-3">
                    <span class="info-box-icon bg-warning"><i class="fas fa-exclamation-triangle"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total de Cuentas Vencidas</span>
                        <span class="info-box-number"><?= number_format($cuentas_vencidas['cantidad'] ?? 0) ?></span>
                        <span class="info-box-text">Monto Total: <?= number_format($cuentas_vencidas['monto'] ?? 0, 0, ',', '.') ?> Gs.</span>
                    </div>
                </div>
                <a href="<?= base_url('finanzas/cuentas-cobrar') ?>" class="btn btn-sm btn-primary btn-block">
                    <i class="fas fa-eye"></i> Ver Cuentas por Cobrar
                </a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
$(document).ready(function() {
    // Gráfico de Ventas
    const ventasData = <?= json_encode($ventas_por_dia ?? []) ?>;
    const ventasLabels = ventasData.map(v => {
        const fecha = new Date(v.fecha);
        return fecha.toLocaleDateString('es-PY', { weekday: 'short', day: 'numeric' });
    });
    const ventasMontos = ventasData.map(v => parseFloat(v.monto || 0));
    
    const ventasCtx = document.getElementById('ventasChart');
    if (ventasCtx) {
        new Chart(ventasCtx, {
            type: 'line',
            data: {
                labels: ventasLabels,
                datasets: [{
                    label: '<?= $esAdmin ? "Ventas (Gs.)" : "Mis Ventas (Gs.)" ?>',
                    data: ventasMontos,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat('es-PY', {
                                    style: 'currency',
                                    currency: 'PYG',
                                    minimumFractionDigits: 0
                                }).format(value);
                            }
                        }
                    }
                }
            }
        });
    }
    
    <?php if ($esAdmin): ?>
    // Gráfico de Órdenes por Estado - Solo admins/gerentes
    const ordenesData = <?= json_encode($ordenes_por_estado ?? []) ?>;
    const ordenesLabels = ordenesData.map(o => o.estado);
    const ordenesCantidades = ordenesData.map(o => parseInt(o.cantidad || 0));
    
    const ordenesCtx = document.getElementById('ordenesChart');
    if (ordenesCtx) {
        new Chart(ordenesCtx, {
            type: 'doughnut',
            data: {
                labels: ordenesLabels,
                datasets: [{
                    data: ordenesCantidades,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 206, 86, 0.8)',
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(153, 102, 255, 0.8)',
                        'rgba(255, 159, 64, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }
    <?php endif; ?>
});
</script>
<?= $this->endSection() ?>
