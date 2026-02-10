<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('ventas') ?>">Ventas</a></li>
<li class="breadcrumb-item active">Devoluciones</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-undo mr-1"></i> Devoluciones</h3>
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs" id="devolucionesTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="ventas-tab" data-bs-toggle="tab" href="#ventas" role="tab">
                            Ventas para Devolver
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="devoluciones-tab" data-bs-toggle="tab" href="#devoluciones" role="tab">
                            Devoluciones Realizadas
                        </a>
                    </li>
                </ul>

                <div class="tab-content mt-3" id="devolucionesTabContent">
                    <!-- Tab Ventas -->
                    <div class="tab-pane fade show active" id="ventas" role="tabpanel">
                        <p class="text-muted mb-3">Seleccione una venta para procesar una devolución.</p>
                        <table class="table table-bordered table-striped data-table" id="tablaVentas">
                            <thead>
                                <tr>
                                    <th>Número Venta</th>
                                    <th>Cliente</th>
                                    <th>Fecha</th>
                                    <th>Total</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ventas as $venta): ?>
                                    <tr>
                                        <td><?= esc($venta['numero_venta']) ?></td>
                                        <td>
                                            <?php
                                            if ($venta['cliente_id']) {
                                                $clienteModel = new \App\Models\ClienteModel();
                                                $cliente = $clienteModel->find($venta['cliente_id']);
                                                if ($cliente) {
                                                    if ($cliente['tipo_cliente'] == 'PERSONA') {
                                                        echo esc(trim(($cliente['nombre'] ?? '') . ' ' . ($cliente['apellido'] ?? '')));
                                                    } else {
                                                        echo esc($cliente['nombre_fantasia'] ?? $cliente['razon_social'] ?? '');
                                                    }
                                                } else {
                                                    echo '-';
                                                }
                                            } else {
                                                echo 'Cliente Walk-in';
                                            }
                                            ?>
                                        </td>
                                        <td><?= date('d/m/Y H:i', strtotime($venta['fecha_venta'])) ?></td>
                                        <td><?= number_format((float) $venta['total'], 0, ',', '.') ?> Gs.</td>
                                        <td>
                                            <a href="<?= base_url('ventas/devoluciones/crear?venta_id=' . $venta['id']) ?>"
                                                class="btn btn-sm btn-primary">
                                                <i class="fas fa-undo"></i> Devolver
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Tab Devoluciones -->
                    <div class="tab-pane fade" id="devoluciones" role="tabpanel">
                        <table class="table table-bordered table-striped data-table" id="tablaDevoluciones">
                            <thead>
                                <tr>
                                    <th>Número Nota</th>
                                    <th>Cliente</th>
                                    <th>Fecha</th>
                                    <th>Total</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($devoluciones as $devolucion): ?>
                                    <tr>
                                        <td><?= esc($devolucion['numero_venta']) ?></td>
                                        <td>
                                            <?php
                                            if ($devolucion['cliente_id']) {
                                                $clienteModel = new \App\Models\ClienteModel();
                                                $cliente = $clienteModel->find($devolucion['cliente_id']);
                                                if ($cliente) {
                                                    if ($cliente['tipo_cliente'] == 'PERSONA') {
                                                        echo esc(trim(($cliente['nombre'] ?? '') . ' ' . ($cliente['apellido'] ?? '')));
                                                    } else {
                                                        echo esc($cliente['nombre_fantasia'] ?? $cliente['razon_social'] ?? '');
                                                    }
                                                } else {
                                                    echo '-';
                                                }
                                            } else {
                                                echo 'Cliente Walk-in';
                                            }
                                            ?>
                                        </td>
                                        <td><?= date('d/m/Y H:i', strtotime($devolucion['fecha_venta'])) ?></td>
                                        <td><?= number_format((float) $devolucion['total'], 0, ',', '.') ?> Gs.</td>
                                        <td>
                                            <a href="<?= base_url('ventas/devoluciones/ver/' . $devolucion['id']) ?>"
                                                class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i> Ver
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    $(document).ready(function () {
        // Inicializar DataTables solo si existe la configuración
        var config = (typeof datatablesEsConfig !== 'undefined') ? datatablesEsConfig : {};

        $('#tablaVentas').DataTable(config);
        $('#tablaDevoluciones').DataTable(config);

        // Manejar tabs vía hash
        var hash = window.location.hash;
        if (hash) {
            var triggerEl = document.querySelector('a[href="' + hash + '"]');
            if (triggerEl) {
                var tab = new bootstrap.Tab(triggerEl);
                tab.show();
            }
        }

        // Actualizar hash al cambiar tab
        var tabEls = document.querySelectorAll('a[data-bs-toggle="tab"]');
        tabEls.forEach(function (tabEl) {
            tabEl.addEventListener('shown.bs.tab', function (event) {
                var target = event.target.getAttribute('href');
                if (history.pushState) {
                    history.pushState(null, null, target);
                } else {
                    window.location.hash = target;
                }
            });
        });
    });
</script>
<?= $this->endSection() ?>