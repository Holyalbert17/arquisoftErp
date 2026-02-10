<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('clientes') ?>">Clientes</a></li>
<li class="breadcrumb-item active">Ver Cliente</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
// Obtener nombre completo del cliente
$nombreCompleto = '';
if ($cliente['tipo_cliente'] == 'PERSONA') {
    $nombreCompleto = trim(($cliente['nombre'] ?? '') . ' ' . ($cliente['apellido'] ?? ''));
} else {
    $nombreCompleto = $cliente['nombre_fantasia'] ?? $cliente['razon_social'] ?? $cliente['nombre'] ?? 'Sin nombre';
}

// Calcular estadísticas de ventas
$totalVentas = count($ventas);
$totalMonto = array_sum(array_column($ventas, 'total'));
$totalPagado = array_sum(array_column($ventas, 'monto_pagado'));
$totalPendiente = $totalMonto - $totalPagado;
?>

<div class="row">
    <!-- Información Principal -->
    <div class="col-md-4">
        <div class="card card-primary card-outline">
            <div class="card-body box-profile">
                <div class="text-center">
                    <?php if ($cliente['foto']): ?>
                        <img class="profile-user-img img-fluid img-circle" 
                             src="<?= base_url('uploads/clientes/' . $cliente['foto']) ?>" 
                             alt="Foto del cliente">
                    <?php else: ?>
                        <div class="profile-user-img img-fluid img-circle bg-primary d-flex align-items-center justify-content-center" 
                             style="width: 100px; height: 100px; margin: 0 auto;">
                            <i class="fas fa-user fa-3x text-white"></i>
                        </div>
                    <?php endif; ?>
                    <h3 class="profile-username text-center"><?= esc($nombreCompleto) ?></h3>
                    <p class="text-muted text-center">
                        <span class="badge badge-<?= $cliente['tipo_cliente'] == 'PERSONA' ? 'info' : 'primary' ?>">
                            <?= esc($cliente['tipo_cliente']) ?>
                        </span>
                        <span class="badge badge-secondary ml-1">
                            <?= esc($cliente['categoria_cliente']) ?>
                        </span>
                    </p>
                </div>

                <ul class="list-group list-group-unbordered mb-3">
                    <li class="list-group-item">
                        <b>Código</b> <a class="float-right"><?= esc($cliente['codigo']) ?></a>
                    </li>
                    <?php if ($cliente['tipo_cliente'] == 'PERSONA'): ?>
                        <li class="list-group-item">
                            <b>CI</b> <a class="float-right"><?= esc($cliente['ci'] ?? '-') ?></a>
                        </li>
                        <?php if ($cliente['fecha_nacimiento']): ?>
                            <li class="list-group-item">
                                <b>Fecha Nacimiento</b> 
                                <a class="float-right"><?= date('d/m/Y', strtotime($cliente['fecha_nacimiento'])) ?></a>
                            </li>
                        <?php endif; ?>
                    <?php else: ?>
                        <li class="list-group-item">
                            <b>RUC</b> <a class="float-right"><?= esc($cliente['ruc'] ?? '-') ?></a>
                        </li>
                        <li class="list-group-item">
                            <b>Razón Social</b> 
                            <a class="float-right"><?= esc($cliente['razon_social'] ?? '-') ?></a>
                        </li>
                        <?php if ($cliente['nombre_fantasia']): ?>
                            <li class="list-group-item">
                                <b>Nombre Fantasía</b> 
                                <a class="float-right"><?= esc($cliente['nombre_fantasia']) ?></a>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                    <li class="list-group-item">
                        <b>Teléfono</b> 
                        <a class="float-right"><?= esc($cliente['celular'] ?? $cliente['telefono'] ?? '-') ?></a>
                    </li>
                    <li class="list-group-item">
                        <b>Email</b> 
                        <a class="float-right"><?= esc($cliente['email'] ?? '-') ?></a>
                    </li>
                    <li class="list-group-item">
                        <b>Estado</b> 
                        <a class="float-right">
                            <?php if ($cliente['estado']): ?>
                                <span class="badge badge-success">Activo</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Inactivo</span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>

                <div class="row">
                    <div class="col-12">
                        <a href="<?= base_url('clientes/editar/' . $cliente['id']) ?>" class="btn btn-primary btn-block">
                            <i class="fas fa-edit mr-1"></i> Editar Cliente
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información de Crédito -->
        <div class="card card-outline card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-credit-card mr-1"></i> Información de Crédito</h3>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-unbordered">
                    <li class="list-group-item">
                        <b>Límite de Crédito</b> 
                        <a class="float-right"><?= number_format($cliente['limite_credito'] ?? 0, 0, ',', '.') ?> Gs.</a>
                    </li>
                    <li class="list-group-item">
                        <b>Crédito Utilizado</b> 
                        <a class="float-right text-danger">
                            <?= number_format($cliente['credito_utilizado'] ?? 0, 0, ',', '.') ?> Gs.
                        </a>
                    </li>
                    <li class="list-group-item">
                        <b>Crédito Disponible</b> 
                        <a class="float-right text-success">
                            <?= number_format($cliente['credito_disponible'] ?? 0, 0, ',', '.') ?> Gs.
                        </a>
                    </li>
                    <li class="list-group-item">
                        <b>Días de Crédito</b> 
                        <a class="float-right"><?= $cliente['dias_credito'] ?? 0 ?> días</a>
                    </li>
                    <li class="list-group-item">
                        <b>Descuento</b> 
                        <a class="float-right"><?= number_format($cliente['descuento_porcentaje'] ?? 0, 2, ',', '.') ?>%</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Información Detallada y Ventas -->
    <div class="col-md-8">
        <!-- Información de Contacto -->
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-map-marker-alt mr-1"></i> Información de Contacto</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Dirección:</strong><br>
                        <?= esc($cliente['direccion'] ?? '-') ?></p>
                        <?php if ($cliente['barrio']): ?>
                            <p><strong>Barrio:</strong><br>
                            <?= esc($cliente['barrio']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Ciudad:</strong><br>
                        <?= esc($cliente['ciudad'] ?? '-') ?></p>
                        <p><strong>Departamento:</strong><br>
                        <?= esc($cliente['departamento'] ?? '-') ?></p>
                    </div>
                </div>
                <?php if ($cliente['referencia_direccion']): ?>
                    <p><strong>Referencia:</strong><br>
                    <?= esc($cliente['referencia_direccion']) ?></p>
                <?php endif; ?>
                <?php if ($cliente['observaciones']): ?>
                    <hr>
                    <p><strong>Observaciones:</strong><br>
                    <?= nl2br(esc($cliente['observaciones'])) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Estadísticas de Ventas -->
        <div class="row mb-3">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?= number_format($totalVentas, 0, ',', '.') ?></h3>
                        <p>Total Ventas</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?= number_format($totalMonto / 1000000, 2, ',', '.') ?>M</h3>
                        <p>Total Compras</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-primary">
                    <div class="inner">
                        <h3><?= number_format($totalPagado / 1000000, 2, ',', '.') ?>M</h3>
                        <p>Total Pagado</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box <?= $totalPendiente > 0 ? 'bg-warning' : 'bg-secondary' ?>">
                    <div class="inner">
                        <h3><?= number_format($totalPendiente / 1000000, 2, ',', '.') ?>M</h3>
                        <p>Pendiente</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Historial de Ventas -->
        <div class="card card-outline card-success">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-history mr-1"></i> Historial de Ventas (Últimas 10)</h3>
            </div>
            <div class="card-body">
                <?php if (empty($ventas)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-2"></i>
                        Este cliente no tiene ventas registradas.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>N° Venta</th>
                                    <th>Fecha</th>
                                    <th>Tipo</th>
                                    <th>Total</th>
                                    <th>Pagado</th>
                                    <th>Pendiente</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ventas as $venta): ?>
                                    <tr>
                                        <td><strong><?= esc($venta['numero_venta']) ?></strong></td>
                                        <td><?= date('d/m/Y H:i', strtotime($venta['fecha_venta'])) ?></td>
                                        <td>
                                            <span class="badge badge-info">
                                                <?= esc($venta['tipo_comprobante']) ?>
                                            </span>
                                        </td>
                                        <td class="text-right">
                                            <strong><?= number_format($venta['total'], 0, ',', '.') ?> Gs.</strong>
                                        </td>
                                        <td class="text-right text-success">
                                            <?= number_format($venta['monto_pagado'] ?? 0, 0, ',', '.') ?> Gs.
                                        </td>
                                        <td class="text-right text-warning">
                                            <?= number_format(($venta['total'] - ($venta['monto_pagado'] ?? 0)), 0, ',', '.') ?> Gs.
                                        </td>
                                        <td>
                                            <?php
                                            $badges = [
                                                'COMPLETADA' => 'success',
                                                'PENDIENTE' => 'warning',
                                                'ANULADA' => 'danger',
                                                'DEVUELTA_TOTAL' => 'danger',
                                                'DEVUELTA_PARCIAL' => 'warning'
                                            ];
                                            $badge = $badges[$venta['estado']] ?? 'secondary';
                                            ?>
                                            <span class="badge badge-<?= $badge ?>">
                                                <?= esc($venta['estado']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="<?= base_url('ventas/ver/' . $venta['id']) ?>" 
                                               class="btn btn-sm btn-info" title="Ver Venta">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-2">
                        <a href="<?= base_url('ventas?cliente_id=' . $cliente['id']) ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-list mr-1"></i> Ver Todas las Ventas
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Cuentas por Cobrar -->
        <?php if (!empty($cuentas_cobrar)): ?>
            <div class="card card-outline card-warning mt-3">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-file-invoice-dollar mr-1"></i> 
                        Cuentas por Cobrar Pendientes
                        <span class="badge badge-warning ml-2"><?= count($cuentas_cobrar) ?></span>
                    </h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <strong>Total Pendiente:</strong> 
                        <?= number_format($total_pendiente ?? 0, 0, ',', '.') ?> Gs.
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>Documento</th>
                                    <th>Fecha Emisión</th>
                                    <th>Fecha Vencimiento</th>
                                    <th>Monto Total</th>
                                    <th>Pagado</th>
                                    <th>Pendiente</th>
                                    <th>Días Vencido</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cuentas_cobrar as $cuenta): ?>
                                    <tr class="<?= ($cuenta['dias_vencido'] ?? 0) > 0 ? 'table-danger' : '' ?>">
                                        <td><?= esc($cuenta['numero_documento']) ?></td>
                                        <td><?= date('d/m/Y', strtotime($cuenta['fecha_emision'])) ?></td>
                                        <td><?= date('d/m/Y', strtotime($cuenta['fecha_vencimiento'])) ?></td>
                                        <td class="text-right"><?= number_format($cuenta['monto_total'], 0, ',', '.') ?> Gs.</td>
                                        <td class="text-right text-success"><?= number_format($cuenta['monto_pagado'], 0, ',', '.') ?> Gs.</td>
                                        <td class="text-right text-warning">
                                            <strong><?= number_format($cuenta['monto_pendiente'], 0, ',', '.') ?> Gs.</strong>
                                        </td>
                                        <td class="text-center">
                                            <?php if (($cuenta['dias_vencido'] ?? 0) > 0): ?>
                                                <span class="badge badge-danger"><?= $cuenta['dias_vencido'] ?> días</span>
                                            <?php else: ?>
                                                <span class="badge badge-success">Al día</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= $cuenta['estado'] == 'VENCIDO' ? 'danger' : ($cuenta['estado'] == 'PAGADO' ? 'success' : 'warning') ?>">
                                                <?= esc($cuenta['estado']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-2">
                        <a href="<?= base_url('finanzas/cuentas-cobrar?cliente_id=' . $cliente['id']) ?>" class="btn btn-sm btn-warning">
                            <i class="fas fa-list mr-1"></i> Ver Todas las Cuentas
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Información Adicional -->
        <?php if ($cliente['total_compras'] > 0 || $cliente['cantidad_compras'] > 0 || $cliente['fecha_ultima_compra']): ?>
            <div class="card card-outline card-secondary mt-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-chart-line mr-1"></i> Estadísticas</h3>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-unbordered">
                        <?php if ($cliente['fecha_ultima_compra']): ?>
                            <li class="list-group-item">
                                <b>Última Compra</b> 
                                <a class="float-right"><?= date('d/m/Y H:i', strtotime($cliente['fecha_ultima_compra'])) ?></a>
                            </li>
                        <?php endif; ?>
                        <li class="list-group-item">
                            <b>Cantidad de Compras</b> 
                            <a class="float-right"><?= number_format($cliente['cantidad_compras'] ?? 0, 0, ',', '.') ?></a>
                        </li>
                        <li class="list-group-item">
                            <b>Total Histórico de Compras</b> 
                            <a class="float-right">
                                <strong><?= number_format($cliente['total_compras'] ?? 0, 0, ',', '.') ?> Gs.</strong>
                            </a>
                        </li>
                        <?php if ($cliente['cantidad_compras'] > 0): ?>
                            <li class="list-group-item">
                                <b>Ticket Promedio</b> 
                                <a class="float-right">
                                    <?= number_format(($cliente['total_compras'] ?? 0) / ($cliente['cantidad_compras'] ?? 1), 0, ',', '.') ?> Gs.
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('styles') ?>
<style>
.profile-user-img {
    width: 100px;
    height: 100px;
    object-fit: cover;
}
.box-profile {
    border-top: 3px solid #007bff;
}
</style>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(document).ready(function() {
    // Inicializar tooltips si es necesario
    $('[title]').tooltip();
});
</script>
<?= $this->endSection() ?>
