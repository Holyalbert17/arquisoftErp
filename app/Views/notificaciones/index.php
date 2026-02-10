<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item active">Notificaciones</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-bell mr-1"></i> Notificaciones
                    <?php if ($noLeidas > 0): ?>
                        <span class="badge badge-danger ml-2"><?= $noLeidas ?> no leídas</span>
                    <?php endif; ?>
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-sm btn-primary" onclick="marcarTodasLeidas()">
                        <i class="fas fa-check-double"></i> Marcar Todas como Leídas
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <ul class="products-list product-list-in-card pl-2 pr-2">
                    <?php if (empty($notificaciones)): ?>
                        <li class="item">
                            <div class="text-center p-3">
                                <p class="text-muted">No hay notificaciones</p>
                            </div>
                        </li>
                    <?php else: ?>
                        <?php foreach ($notificaciones as $notif): ?>
                            <?php
                            $tipoColors = [
                                'INFO' => 'info',
                                'WARNING' => 'warning',
                                'SUCCESS' => 'success',
                                'ERROR' => 'danger',
                                'ALERT' => 'warning'
                            ];
                            $color = $tipoColors[$notif['tipo']] ?? 'info';
                            ?>
                            <li class="item <?= !$notif['leida'] ? 'bg-light' : '' ?>">
                                <div class="product-img">
                                    <i class="fas fa-<?= $notif['icono'] ?? 'bell' ?> fa-2x text-<?= $color ?>"></i>
                                </div>
                                <div class="product-info">
                                    <a href="<?= $notif['accion_url'] ? base_url($notif['accion_url']) : '#' ?>" 
                                       class="product-title <?= !$notif['leida'] ? 'font-weight-bold' : '' ?>">
                                        <?= esc($notif['titulo']) ?>
                                        <?php if (!$notif['leida']): ?>
                                            <span class="badge badge-<?= $color ?> float-right">Nueva</span>
                                        <?php endif; ?>
                                    </a>
                                    <span class="product-description">
                                        <?= esc($notif['mensaje']) ?>
                                        <br>
                                        <small class="text-muted">
                                            <i class="far fa-clock"></i> <?= date('d/m/Y H:i', strtotime($notif['fecha_creacion'])) ?>
                                        </small>
                                    </span>
                                </div>
                                <div class="product-actions">
                                    <?php if (!$notif['leida']): ?>
                                        <button type="button" class="btn btn-sm btn-primary" 
                                                onclick="marcarLeida(<?= $notif['id'] ?>)">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-danger" 
                                            onclick="eliminarNotificacion(<?= $notif['id'] ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
function marcarLeida(id) {
    $.ajax({
        url: '<?= base_url('notificaciones/marcar-leida') ?>/' + id,
        type: 'POST',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                location.reload();
            }
        }
    });
}

function marcarTodasLeidas() {
    if (confirm('¿Marcar todas las notificaciones como leídas?')) {
        $.ajax({
            url: '<?= base_url('notificaciones/marcar-todas-leidas') ?>',
            type: 'POST',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    location.reload();
                }
            }
        });
    }
}

function eliminarNotificacion(id) {
    if (confirm('¿Eliminar esta notificación?')) {
        $.ajax({
            url: '<?= base_url('notificaciones/eliminar') ?>/' + id,
            type: 'POST',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    location.reload();
                }
            }
        });
    }
}
</script>
<?= $this->endSection() ?>
