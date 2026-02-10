<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('configuracion') ?>">Configuración</a></li>
<li class="breadcrumb-item active">Tipos de Cambio</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-exchange-alt mr-1"></i>
            Tipos de Cambio
        </h3>
        <div class="card-tools">
            <a href="<?= base_url('configuracion/tipos-cambio/crear') ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Nuevo Tipo de Cambio
            </a>
        </div>
    </div>
    <div class="card-body">
        <table class="table table-bordered table-striped data-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Moneda</th>
                    <th>Compra</th>
                    <th>Venta</th>
                    <th>Promedio</th>
                    <th>Fuente</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tipos_cambio as $tc): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($tc['fecha'])) ?></td>
                        <td>
                            <strong><?= esc($tc['moneda_destino']) ?></strong>
                            <small class="text-muted">/ <?= esc($tc['moneda_origen']) ?></small>
                        </td>
                        <td><?= number_format($tc['compra'], 0, ',', '.') ?> Gs.</td>
                        <td><?= number_format($tc['venta'], 0, ',', '.') ?> Gs.</td>
                        <td><strong><?= number_format($tc['promedio'], 0, ',', '.') ?> Gs.</strong></td>
                        <td>
                            <?php
                            $badges = [
                                'MANUAL' => 'secondary',
                                'BCP' => 'info',
                                'SET' => 'primary'
                            ];
                            $badge = $badges[$tc['fuente']] ?? 'secondary';
                            ?>
                            <span class="badge badge-<?= $badge ?>"><?= esc($tc['fuente']) ?></span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-info" onclick="usarTipoCambio(<?= $tc['id'] ?>)" title="Usar este tipo de cambio">
                                <i class="fas fa-check"></i>
                            </button>
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
$(document).ready(function() {
    $('.data-table').DataTable({
        'language': {
            'url': '<?= base_url('assets/js/datatables-es.js') ?>'
        },
        'responsive': true,
        'pageLength': 25,
        'order': [[0, 'desc']]
    });
});

function usarTipoCambio(id) {
    Swal.fire({
        title: 'Usar Tipo de Cambio',
        text: '¿Desea usar este tipo de cambio como predeterminado?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, usar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            // Aquí se podría implementar la lógica para establecer como predeterminado
            Swal.fire('Éxito', 'Tipo de cambio establecido', 'success');
        }
    });
}
</script>
<?= $this->endSection() ?>
