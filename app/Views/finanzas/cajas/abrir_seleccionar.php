<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('finanzas') ?>">Finanzas</a></li>
<li class="breadcrumb-item"><a href="<?= base_url('finanzas/cajas') ?>">Cajas</a></li>
<li class="breadcrumb-item active">Abrir Caja</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="card card-success card-outline">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-unlock-alt me-1"></i> Seleccione la caja a abrir
                </h3>
                <div class="card-tools">
                    <a href="<?= base_url('finanzas/cajas') ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus me-1"></i> Nueva Caja
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($cajas)): ?>
                <div class="alert alert-warning mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    No hay cajas cerradas disponibles. Cree una nueva caja y luego ábrala desde la lista.
                </div>
                <div class="mt-3">
                    <a href="<?= base_url('finanzas/cajas') ?>" class="btn btn-primary">
                        <i class="fas fa-arrow-left me-1"></i> Volver a Gestión de Cajas
                    </a>
                </div>
                <?php else: ?>
                <div class="row g-3">
                    <?php
                    $usuarioModel = new \App\Models\UsuarioModel();
                    foreach ($cajas as $caja):
                        $usuario = $usuarioModel->find($caja['usuario_id']);
                        $usuarioNombre = $usuario ? ($usuario['nombre'] . ' ' . $usuario['apellido']) : 'N/A';
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card border h-100">
                            <div class="card-body">
                                <h5 class="card-title text-primary"><?= esc($caja['nombre']) ?></h5>
                                <?php if (!empty($caja['descripcion'])): ?>
                                <p class="card-text text-muted small mb-2"><?= esc($caja['descripcion']) ?></p>
                                <?php endif; ?>
                                <p class="card-text small mb-2"><strong>Usuario:</strong> <?= esc($usuarioNombre) ?></p>
                                <a href="<?= base_url('finanzas/cajas/abrir/' . $caja['id']) ?>" class="btn btn-success btn-sm w-100">
                                    <i class="fas fa-lock-open me-1"></i> Abrir esta caja
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3">
                    <a href="<?= base_url('finanzas/cajas') ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i> Volver a la lista
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
