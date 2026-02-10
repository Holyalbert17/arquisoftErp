<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('admin') ?>">Administración</a></li>
<li class="breadcrumb-item active">Módulos y Licencias</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
// Preparar datos
$modulosActivosEmpresa = array_filter($modulosEmpresa, fn($m) => $m['activo']);
$modulosInactivosEmpresa = array_filter($modulosEmpresa, fn($m) => !$m['activo']);
$totalActivos = count($modulosActivosEmpresa);
$totalInactivos = count($modulosInactivosEmpresa);

// Detectar módulos con licencia vencida o por vencer
$vencidos = 0;
$porVencer = 0;
foreach ($modulosActivosEmpresa as $me) {
    $fechaVenc = $me['fecha_fin_licencia'] ?? $me['fecha_vencimiento'] ?? null;
    if (!empty($fechaVenc)) {
        $dias = (strtotime($fechaVenc) - time()) / 86400;
        if ($dias <= 0) $vencidos++;
        elseif ($dias <= 15) $porVencer++;
    }
}

// Detectar módulos obsoletos (asignados pero el módulo fue desactivado en la tabla modulos)
$modulosObsoletos = array_filter($modulosEmpresa, fn($m) => !($m['modulo_estado'] ?? true));
?>

<!-- Selector de Empresa (Super Admin) -->
<?php if (!empty($esSuperAdmin) && count($empresas) > 1): ?>
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="<?= base_url('admin/modulos') ?>" class="row align-items-center">
            <div class="col-auto">
                <label class="form-label mb-0 fw-bold"><i class="fas fa-building me-1"></i> Empresa:</label>
            </div>
            <div class="col-md-4">
                <select name="empresa_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($empresas as $emp): ?>
                        <option value="<?= $emp['id'] ?>" <?= ($emp['id'] == $empresaIdSeleccionada) ? 'selected' : '' ?>>
                            <?= esc($emp['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <span class="badge bg-primary"><?= count($empresas) ?> empresas registradas</span>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Info de empresa -->
<div class="row mb-3">
    <div class="col-md-3">
        <div class="small-box bg-info">
            <div class="inner">
                <h3><?= $totalActivos ?></h3>
                <p>Módulos Activos</p>
            </div>
            <div class="icon"><i class="fas fa-puzzle-piece"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="small-box bg-secondary">
            <div class="inner">
                <h3><?= $totalInactivos ?></h3>
                <p>Módulos Inactivos</p>
            </div>
            <div class="icon"><i class="fas fa-ban"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="small-box bg-<?= $porVencer > 0 ? 'warning' : 'success' ?>">
            <div class="inner">
                <h3><?= $porVencer ?></h3>
                <p>Por Vencer (15 días)</p>
            </div>
            <div class="icon"><i class="fas fa-clock"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="small-box bg-<?= $vencidos > 0 ? 'danger' : 'success' ?>">
            <div class="inner">
                <h3><?= $vencidos ?></h3>
                <p>Licencias Vencidas</p>
            </div>
            <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
        </div>
    </div>
</div>

<!-- Alertas de módulos obsoletos -->
<?php if (count($modulosObsoletos) > 0): ?>
<div class="alert alert-warning alert-dismissible fade show">
    <i class="fas fa-exclamation-circle me-1"></i>
    <strong>Módulos obsoletos detectados:</strong> Los siguientes módulos fueron eliminados del sistema y deberían desactivarse:
    <?php foreach ($modulosObsoletos as $obs): ?>
        <span class="badge bg-dark ms-1"><?= esc($obs['nombre']) ?></span>
    <?php endforeach; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Módulos asignados a la empresa -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-building me-1"></i>
            Módulos de <strong><?= esc($empresa['nombre'] ?? 'Mi Empresa') ?></strong>
        </h3>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover table-striped align-middle mb-0">
            <thead class="table-dark">
                <tr>
                    <th>Módulo</th>
                    <th>Categoría</th>
                    <th>Licencia</th>
                    <th>Vencimiento</th>
                    <th class="text-center">Estado</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($modulosEmpresa)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No hay módulos asignados a esta empresa</td></tr>
                <?php endif; ?>
                <?php foreach ($modulosEmpresa as $moduloEmp): ?>
                    <?php
                    $fechaVenc = $moduloEmp['fecha_fin_licencia'] ?? $moduloEmp['fecha_vencimiento'] ?? null;
                    $diasRestantes = null;
                    $estadoLicencia = 'ok';
                    if (!empty($fechaVenc)) {
                        $diasRestantes = (strtotime($fechaVenc) - time()) / 86400;
                        if ($diasRestantes <= 0) $estadoLicencia = 'vencido';
                        elseif ($diasRestantes <= 7) $estadoLicencia = 'critico';
                        elseif ($diasRestantes <= 30) $estadoLicencia = 'advertencia';
                    }
                    $esObsoleto = !($moduloEmp['modulo_estado'] ?? true);
                    $tipoLic = $moduloEmp['tipo_licencia'] ?? 'N/A';
                    ?>
                    <tr class="<?= $esObsoleto ? 'table-secondary' : ($estadoLicencia == 'vencido' ? 'table-danger' : '') ?>">
                        <td>
                            <strong><?= esc($moduloEmp['nombre']) ?></strong>
                            <br><small class="text-muted"><?= esc($moduloEmp['codigo']) ?></small>
                            <?php if ($esObsoleto): ?>
                                <br><span class="badge bg-dark">Obsoleto</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $catColors = ['VENTAS'=>'primary','INVENTARIO'=>'success','FINANZAS'=>'warning','COMPRAS'=>'info','SERVICIOS'=>'secondary','REPORTES'=>'dark','CONFIGURACION'=>'light'];
                            $catColor = $catColors[$moduloEmp['categoria']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $catColor ?>"><?= esc($moduloEmp['categoria']) ?></span>
                        </td>
                        <td>
                            <?php
                            $licIcons = ['PERPETUA'=>'infinity','ANUAL'=>'calendar-check','MENSUAL'=>'calendar','PRUEBA'=>'flask'];
                            $licIcon = $licIcons[$tipoLic] ?? 'key';
                            ?>
                            <i class="fas fa-<?= $licIcon ?> me-1"></i> <?= esc($tipoLic) ?>
                        </td>
                        <td>
                            <?php if (empty($fechaVenc) || $tipoLic === 'PERPETUA'): ?>
                                <span class="text-success"><i class="fas fa-infinity me-1"></i> Sin vencimiento</span>
                            <?php else: ?>
                                <?= date('d/m/Y', strtotime($fechaVenc)) ?>
                                <br>
                                <?php if ($estadoLicencia == 'vencido'): ?>
                                    <span class="badge bg-danger"><i class="fas fa-exclamation-triangle me-1"></i> VENCIDO hace <?= abs(round($diasRestantes)) ?> días</span>
                                <?php elseif ($estadoLicencia == 'critico'): ?>
                                    <span class="badge bg-danger"><?= round($diasRestantes) ?> días restantes</span>
                                <?php elseif ($estadoLicencia == 'advertencia'): ?>
                                    <span class="badge bg-warning text-dark"><?= round($diasRestantes) ?> días restantes</span>
                                <?php else: ?>
                                    <small class="text-muted"><?= round($diasRestantes) ?> días restantes</small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if (!$moduloEmp['activo']): ?>
                                <span class="badge bg-secondary">Inactivo</span>
                            <?php elseif ($estadoLicencia == 'vencido'): ?>
                                <span class="badge bg-danger">Bloqueado</span>
                            <?php else: ?>
                                <span class="badge bg-success">Activo</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($moduloEmp['activo']): ?>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-warning" title="Renovar licencia"
                                            onclick="renovarLicencia(<?= $moduloEmp['id'] ?>, '<?= addslashes(esc($moduloEmp['nombre'])) ?>', '<?= esc($tipoLic) ?>')">
                                        <i class="fas fa-sync"></i>
                                    </button>
                                    <button type="button" class="btn btn-danger" title="Desactivar módulo"
                                            onclick="confirmarDesactivar(<?= $moduloEmp['id'] ?>, '<?= addslashes(esc($moduloEmp['nombre'])) ?>')">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                </div>
                            <?php else: ?>
                                <button type="button" class="btn btn-sm btn-success" title="Reactivar"
                                        onclick="asignarModulo(<?= $moduloEmp['modulo_id'] ?>, '<?= addslashes(esc($moduloEmp['nombre'])) ?>')">
                                    <i class="fas fa-check me-1"></i> Activar
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Módulos por Sucursal -->
<?php if (!empty($sucursales)): ?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-store me-1"></i> Módulos por Sucursal</h3>
    </div>
    <div class="card-body">
        <div class="row">
            <?php foreach ($sucursales as $suc): ?>
                <?php $modsSuc = $modulosPorSucursal[$suc['id']] ?? []; ?>
                <div class="col-md-6 mb-3">
                    <div class="card card-outline card-primary h-100">
                        <div class="card-header py-2">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-store me-1"></i> <?= esc($suc['nombre']) ?>
                                <span class="badge bg-info ms-2"><?= count($modsSuc) ?> módulos</span>
                            </h5>
                        </div>
                        <div class="card-body py-2">
                            <?php if (empty($modsSuc)): ?>
                                <p class="text-muted mb-0"><i class="fas fa-info-circle me-1"></i> Sin módulos asignados</p>
                            <?php else: ?>
                                <div class="d-flex flex-wrap gap-1">
                                    <?php foreach ($modsSuc as $ms): ?>
                                        <span class="badge bg-light text-dark border"><?= esc($ms['nombre']) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Módulos Disponibles para Asignar -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-plus-circle me-1"></i> Módulos Disponibles</h3>
    </div>
    <div class="card-body">
        <?php
        $categorias = [];
        foreach ($modulos as $modulo) {
            $categorias[$modulo['categoria']][] = $modulo;
        }
        $catLabels = [
            'VENTAS'=>['Ventas','primary'], 'INVENTARIO'=>['Inventario','success'],
            'FINANZAS'=>['Finanzas','warning'], 'COMPRAS'=>['Compras','info'],
            'SERVICIOS'=>['Servicios','secondary'], 'REPORTES'=>['Reportes','dark'],
            'CONFIGURACION'=>['Configuración','light']
        ];
        ?>
        <?php foreach ($categorias as $categoria => $mods): ?>
            <?php $catInfo = $catLabels[$categoria] ?? [$categoria, 'secondary']; ?>
            <h6 class="mt-3 mb-2"><span class="badge bg-<?= $catInfo[1] ?>"><?= esc($catInfo[0]) ?></span></h6>
            <div class="row">
                <?php foreach ($mods as $modulo): ?>
                    <?php
                    $yaAsignado = false;
                    $asignacionActiva = false;
                    foreach ($modulosEmpresa as $me) {
                        if (($me['modulo_id'] ?? null) == $modulo['id']) {
                            $yaAsignado = true;
                            $asignacionActiva = (bool)$me['activo'];
                            break;
                        }
                    }
                    ?>
                    <div class="col-md-4 col-lg-3 mb-2">
                        <div class="card h-100 <?= $asignacionActiva ? 'border-success' : ($yaAsignado ? 'border-secondary' : '') ?>" style="font-size:0.9rem">
                            <div class="card-body py-2 px-3">
                                <strong><?= esc($modulo['nombre']) ?></strong>
                                <?php if ($asignacionActiva): ?>
                                    <span class="badge bg-success float-end">Activo</span>
                                <?php elseif ($yaAsignado): ?>
                                    <span class="badge bg-secondary float-end">Inactivo</span>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary float-end py-0"
                                            onclick="asignarModulo(<?= $modulo['id'] ?>, '<?= addslashes(esc($modulo['nombre'])) ?>')">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal Asignar Módulo -->
<div class="modal fade" id="modalAsignarModulo" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-1"></i> Asignar Módulo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formAsignarModulo">
                <input type="hidden" name="modulo_id" id="modulo_id_asignar">
                <input type="hidden" name="empresa_id" value="<?= $empresaIdSeleccionada ?>">
                <div class="modal-body">
                    <div class="mb-3 p-3 bg-light rounded">
                        <strong id="asignar_modulo_nombre"></strong>
                        <br><small class="text-muted">Empresa: <?= esc($empresa['nombre'] ?? '') ?></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-key me-1"></i> Tipo de Licencia</label>
                        <select name="tipo_licencia" id="asignar_tipo_licencia" class="form-select" required>
                            <option value="PRUEBA">Prueba (evaluación)</option>
                            <option value="MENSUAL" selected>Mensual</option>
                            <option value="ANUAL">Anual</option>
                            <option value="PERPETUA">Perpetua (sin vencimiento)</option>
                        </select>
                    </div>
                    <div class="mb-3" id="asignar_dias_group">
                        <label class="form-label"><i class="fas fa-calendar-alt me-1"></i> Días de Vigencia</label>
                        <input type="number" name="dias_vigencia" id="asignar_dias" class="form-control" value="30" min="1" max="3650" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus me-1"></i> Asignar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Renovar Licencia -->
<div class="modal fade" id="modalRenovarLicencia" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="fas fa-sync me-1"></i> Renovar Licencia</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formRenovarLicencia">
                <input type="hidden" id="renovar_id">
                <div class="modal-body">
                    <div class="mb-3 p-3 bg-light rounded">
                        <strong id="renovar_modulo_nombre"></strong>
                        <br><small class="text-muted" id="renovar_modulo_info"></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-key me-1"></i> Tipo de Licencia</label>
                        <select id="renovar_tipo_licencia" class="form-select" required>
                            <option value="PRUEBA">Prueba (evaluación)</option>
                            <option value="MENSUAL" selected>Mensual</option>
                            <option value="ANUAL">Anual</option>
                            <option value="PERPETUA">Perpetua (sin vencimiento)</option>
                        </select>
                    </div>
                    <div class="mb-3" id="renovar_dias_group">
                        <label class="form-label"><i class="fas fa-calendar-alt me-1"></i> Días de Vigencia</label>
                        <input type="number" id="renovar_dias" class="form-control" value="30" min="1" max="3650" required>
                        <div class="form-text">La nueva vigencia se calcula desde hoy</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-sync me-1"></i> Renovar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Desactivar Módulo -->
<div class="modal fade" id="modalDesactivar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-ban me-1"></i> Desactivar Módulo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <i class="fas fa-exclamation-triangle text-danger" style="font-size:3rem"></i>
                </div>
                <p class="text-center">¿Está seguro de desactivar el módulo <strong id="desactivar_nombre"></strong>?</p>
                <p class="text-center text-muted mb-2">Empresa: <strong><?= esc($empresa['nombre'] ?? '') ?></strong></p>
                <div class="alert alert-warning mb-0">
                    <i class="fas fa-info-circle me-1"></i>
                    Los usuarios no podrán acceder a las funciones de este módulo hasta que se reactive.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnConfirmarDesactivar">
                    <i class="fas fa-ban me-1"></i> Sí, Desactivar
                </button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
// === Asignar Módulo ===
function asignarModulo(moduloId, nombre) {
    $('#modulo_id_asignar').val(moduloId);
    $('#asignar_modulo_nombre').text(nombre || 'Módulo');
    $('#asignar_tipo_licencia').val('MENSUAL').trigger('change');
    new bootstrap.Modal(document.getElementById('modalAsignarModulo')).show();
}

$('#asignar_tipo_licencia').on('change', function() {
    if ($(this).val() === 'PERPETUA') {
        $('#asignar_dias_group').hide();
        $('#asignar_dias').removeAttr('required');
    } else {
        $('#asignar_dias_group').show();
        $('#asignar_dias').attr('required', true);
        var map = {'PRUEBA':15,'MENSUAL':30,'ANUAL':365};
        $('#asignar_dias').val(map[$(this).val()] || 30);
    }
});

$('#formAsignarModulo').on('submit', function(e) {
    e.preventDefault();
    var tipo = $('#asignar_tipo_licencia').val();
    var data = $(this).serialize();
    if (tipo === 'PERPETUA') data += '&dias_vigencia=0';
    $.ajax({
        url: '<?= base_url('admin/modulos/asignar-empresa') ?>',
        type: 'POST', data: data, dataType: 'json',
        success: function(r) {
            if (r.success) location.reload();
            else toastr.error(r.message || 'Error al asignar');
        },
        error: function(xhr) {
            var msg = 'Error al procesar';
            try { msg = JSON.parse(xhr.responseText).message || msg; } catch(e) {}
            toastr.error(msg);
        }
    });
});

// === Renovar Licencia ===
function renovarLicencia(id, nombre, tipoActual) {
    $('#renovar_id').val(id);
    $('#renovar_modulo_nombre').text(nombre);
    $('#renovar_modulo_info').text('Licencia actual: ' + (tipoActual || 'N/A'));
    $('#renovar_tipo_licencia').val(tipoActual || 'MENSUAL').trigger('change');
    new bootstrap.Modal(document.getElementById('modalRenovarLicencia')).show();
}

$('#renovar_tipo_licencia').on('change', function() {
    if ($(this).val() === 'PERPETUA') {
        $('#renovar_dias_group').hide();
        $('#renovar_dias').removeAttr('required');
    } else {
        $('#renovar_dias_group').show();
        $('#renovar_dias').attr('required', true);
        var map = {'PRUEBA':15,'MENSUAL':30,'ANUAL':365};
        $('#renovar_dias').val(map[$(this).val()] || 30);
    }
});

$('#formRenovarLicencia').on('submit', function(e) {
    e.preventDefault();
    var id = $('#renovar_id').val();
    var tipo = $('#renovar_tipo_licencia').val();
    var dias = tipo === 'PERPETUA' ? 0 : $('#renovar_dias').val();
    $.ajax({
        url: '<?= base_url('admin/modulos/renovar') ?>/' + id,
        type: 'POST',
        data: { tipo_licencia: tipo, dias_vigencia: dias },
        dataType: 'json',
        success: function(r) {
            if (r.success) { bootstrap.Modal.getInstance(document.getElementById('modalRenovarLicencia')).hide(); location.reload(); }
            else toastr.error(r.message || 'Error al renovar');
        },
        error: function(xhr) {
            var msg = 'Error al procesar';
            try { msg = JSON.parse(xhr.responseText).message || msg; } catch(e) {}
            toastr.error(msg);
        }
    });
});

// === Desactivar Módulo ===
var desactivarId = null;
function confirmarDesactivar(id, nombre) {
    desactivarId = id;
    $('#desactivar_nombre').text(nombre);
    new bootstrap.Modal(document.getElementById('modalDesactivar')).show();
}

$('#btnConfirmarDesactivar').on('click', function() {
    if (!desactivarId) return;
    $.ajax({
        url: '<?= base_url('admin/modulos/desactivar') ?>/' + desactivarId,
        type: 'POST', dataType: 'json',
        success: function(r) {
            if (r.success) { bootstrap.Modal.getInstance(document.getElementById('modalDesactivar')).hide(); location.reload(); }
            else toastr.error(r.message || 'Error al desactivar');
        },
        error: function(xhr) {
            var msg = 'Error al procesar';
            try { msg = JSON.parse(xhr.responseText).message || msg; } catch(e) {}
            toastr.error(msg);
        }
    });
});
</script>
<?= $this->endSection() ?>
