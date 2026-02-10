<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('configuracion') ?>">Configuración</a></li>
<li class="breadcrumb-item active">Talonarios SET</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-file-invoice mr-1"></i>
            Gestión de Talonarios SET
        </h3>
        <div class="card-tools">
            <a href="<?= base_url('configuracion/talonarios/crear') ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Nuevo Talonario
            </a>
        </div>
    </div>
    <div class="card-body">
        <?php if (!empty($sucursales)): ?>
            <form class="mb-3" method="get" action="<?= base_url('configuracion/talonarios') ?>">
                <div class="row">
                    <div class="col-md-4">
                        <label class="mb-1">Sucursal</label>
                        <select class="form-control" name="sucursal_id" onchange="this.form.submit()">
                            <option value="">Todas</option>
                            <?php foreach ($sucursales as $s): ?>
                                <option value="<?= esc($s['id']) ?>" <?= (!empty($sucursal_id) && (int) $sucursal_id === (int) $s['id']) ? 'selected' : '' ?>>
                                    <?= esc($s['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
        <?php endif; ?>
        <table class="table table-bordered table-striped data-table">
            <thead>
                <tr>
                    <th>Sucursal</th>
                    <th>Tipo</th>
                    <th>Timbrado</th>
                    <th>Establecimiento</th>
                    <th>Punto Exp.</th>
                    <th>Número Desde</th>
                    <th>Número Hasta</th>
                    <th>Número Actual</th>
                    <th>Vigencia Desde</th>
                    <th>Vigencia Hasta</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($talonarios as $talonario): ?>
                    <tr>
                        <td>
                            <?php
                            $sid = (int) ($talonario['sucursal_id'] ?? 0);
                            echo esc($sucursales_map[$sid] ?? ($talonario['sucursal_id'] ?? '-'));
                            ?>
                        </td>
                        <td><?= esc($talonario['tipo_comprobante']) ?></td>
                        <td><?= esc($talonario['timbrado']) ?></td>
                        <td><?= esc($talonario['establecimiento']) ?></td>
                        <td><?= esc($talonario['punto_expedicion']) ?></td>
                        <td><?= number_format($talonario['numero_desde'], 0, ',', '.') ?></td>
                        <td><?= number_format($talonario['numero_hasta'], 0, ',', '.') ?></td>
                        <td>
                            <strong><?= number_format($talonario['numero_actual'], 0, ',', '.') ?></strong>
                            <?php 
                            $porcentajeUsado = (($talonario['numero_actual'] - $talonario['numero_desde']) / ($talonario['numero_hasta'] - $talonario['numero_desde'])) * 100;
                            if ($porcentajeUsado >= 80) {
                                echo '<span class="badge badge-warning">Alto</span>';
                            }
                            ?>
                        </td>
                        <td><?= date('d/m/Y', strtotime($talonario['fecha_inicio_vigencia'])) ?></td>
                        <td><?= date('d/m/Y', strtotime($talonario['fecha_fin_vigencia'])) ?></td>
                        <td>
                            <?php
                            $badges = [
                                'ACTIVO' => 'success',
                                'VENCIDO' => 'danger',
                                'AGOTADO' => 'warning',
                                'ANULADO' => 'secondary'
                            ];
                            $badge = $badges[$talonario['estado']] ?? 'secondary';
                            ?>
                            <span class="badge badge-<?= $badge ?>"><?= esc($talonario['estado']) ?></span>
                        </td>
                        <td>
                            <a href="<?= base_url('configuracion/talonarios/editar/' . $talonario['id']) ?>" 
                               class="btn btn-sm btn-info" title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
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
        'order': [[7, 'desc']]
    });
});
</script>
<?= $this->endSection() ?>
