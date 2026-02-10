<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item active">Compras</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-shopping-cart mr-1"></i> Lista de Compras</h3>
                <div class="card-tools">
                    <a href="<?= base_url('compras/crear') ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Nueva Compra
                    </a>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped auto-datatable">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Nº Compra</th>
                            <th>Proveedor</th>
                            <th>Tipo</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Usuario</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($compras as $compra): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($compra['fecha_compra'])) ?></td>
                                <td><?= $compra['numero_compra'] ?></td>
                                <td><?= $compra['proveedor']['nombre_fantasia'] ?? $compra['proveedor']['razon_social'] ?>
                                </td>
                                <td><?= $compra['tipo_compra'] ?></td>
                                <td>Gs. <?= number_format($compra['total'], 0, ',', '.') ?></td>
                                <td>
                                    <?php
                                    $estadoClass = '';
                                    switch ($compra['estado']) {
                                        case 'PENDIENTE':
                                            $estadoClass = 'warning';
                                            break;
                                        case 'RECIBIDA':
                                            $estadoClass = 'success';
                                            break;
                                        case 'ANULADA':
                                            $estadoClass = 'danger';
                                            break;
                                        default:
                                            $estadoClass = 'secondary';
                                    }
                                    ?>
                                    <span class="badge badge-<?= $estadoClass ?>"><?= $compra['estado'] ?></span>
                                </td>
                                <td><?= $compra['usuario_id'] ?></td> <!-- Idealmente nombre de usuario -->
                                <td>
                                    <a href="<?= base_url('compras/ver/' . $compra['id']) ?>" class="btn btn-info btn-sm"
                                        title="Ver Detalle">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($compra['estado'] == 'PENDIENTE'): ?>
                                        <button type="button" class="btn btn-success btn-sm"
                                            onclick="recibirCompra(<?= $compra['id'] ?>)" title="Recibir Mercadería">
                                            <i class="fas fa-truck-loading"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    // Scripts específicos si los hay... pero DataTable ya es automático ;)
</script>
<?= $this->endSection() ?>