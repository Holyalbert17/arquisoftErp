<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('reportes') ?>">Reportes</a></li>
<li class="breadcrumb-item active">Reporte de Ventas</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-chart-line mr-1"></i>
            Reporte de Ventas
        </h3>
        <div class="card-tools">
            <form method="GET" class="form-inline">
                <input type="date" name="fecha_desde" class="form-control form-control-sm mr-2"
                    value="<?= esc($fecha_desde) ?>">
                <input type="date" name="fecha_hasta" class="form-control form-control-sm mr-2"
                    value="<?= esc($fecha_hasta) ?>">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="fas fa-search"></i> Filtrar
                </button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-3">
                <div class="info-box">
                    <span class="info-box-icon bg-info"><i class="fas fa-shopping-cart"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Ventas</span>
                        <span class="info-box-number"><?= number_format($total_ventas, 0, ',', '.') ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-box">
                    <span class="info-box-icon bg-success"><i class="fas fa-dollar-sign"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Monto</span>
                        <span class="info-box-number"><?= number_format($total_monto, 0, ',', '.') ?> Gs.</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-box">
                    <span class="info-box-icon bg-primary"><i class="fas fa-money-check"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Pagado</span>
                        <span class="info-box-number"><?= number_format($total_pagado, 0, ',', '.') ?> Gs.</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-box">
                    <span class="info-box-icon bg-warning"><i class="fas fa-clock"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Pendiente</span>
                        <span class="info-box-number"><?= number_format($total_monto - $total_pagado, 0, ',', '.') ?>
                            Gs.</span>
                    </div>
                </div>
            </div>
        </div>

        <table class="table table-bordered table-striped data-table">
            <thead>
                <tr>
                    <th>Número</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Subtotal</th>
                    <th>IVA</th>
                    <th>Total</th>
                    <th>Pagado</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ventas as $venta): ?>
                    <tr>
                        <td><?= esc($venta['numero_venta']) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($venta['fecha_venta'])) ?></td>
                        <td>
                            <?php
                            $clienteModel = new \App\Models\ClienteModel();
                            $cliente = $clienteModel->find($venta['cliente_id']);
                            if ($cliente) {
                                echo esc($cliente['tipo_cliente'] == 'PERSONA'
                                    ? trim(($cliente['nombre'] ?? '') . ' ' . ($cliente['apellido'] ?? ''))
                                    : ($cliente['nombre_fantasia'] ?? $cliente['razon_social'] ?? ''));
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </td>
                        <td><?= number_format($venta['subtotal'], 0, ',', '.') ?> Gs.</td>
                        <td><?= number_format($venta['total_iva'], 0, ',', '.') ?> Gs.</td>
                        <td><strong><?= number_format($venta['total'], 0, ',', '.') ?> Gs.</strong></td>
                        <td><?= number_format($venta['monto_pagado'], 0, ',', '.') ?> Gs.</td>
                        <td>
                            <?php
                            $badges = [
                                'COMPLETADA' => 'success',
                                'PENDIENTE' => 'warning',
                                'ANULADA' => 'danger'
                            ];
                            $badge = $badges[$venta['estado']] ?? 'secondary';
                            ?>
                            <span class="badge badge-<?= $badge ?>"><?= esc($venta['estado']) ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    $(document).ready(function () {
        $('.data-table').DataTable({
            'language': {
                'url': '<?= base_url('assets/js/datatables-es.js') ?>'
            },
            'responsive': true,
            'pageLength': 25,
            'order': [[1, 'desc']]
        });
    });
</script>
<?= $this->endSection() ?>