<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= base_url('inventario') ?>">Inventario</a></li>
<li class="breadcrumb-item"><a href="<?= base_url('inventario/productos') ?>">Productos</a></li>
<li class="breadcrumb-item active">Ver Producto</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Imagen del Producto</h3>
            </div>
            <div class="card-body text-center">
                <?php if ($producto['imagen_principal']): ?>
                    <img src="<?= base_url('uploads/productos/' . $producto['imagen_principal']) ?>" 
                         alt="<?= esc($producto['nombre']) ?>" 
                         class="img-fluid" style="max-height: 300px;">
                <?php else: ?>
                    <i class="fas fa-image fa-5x text-muted"></i>
                    <p class="text-muted mt-2">Sin imagen</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h3 class="card-title">Información General</h3>
            </div>
            <div class="card-body">
                <dl>
                    <dt>Código</dt>
                    <dd><?= esc($producto['codigo']) ?></dd>
                    
                    <dt>Código de Barras</dt>
                    <dd><?= esc($producto['codigo_barra'] ?? '-') ?></dd>
                    
                    <dt>Tipo de Producto</dt>
                    <dd>
                        <span class="badge badge-info"><?= esc($producto['tipo_producto']) ?></span>
                    </dd>
                    
                    <dt>Estado</dt>
                    <dd>
                        <?php if ($producto['estado']): ?>
                            <span class="badge badge-success">Activo</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Inactivo</span>
                        <?php endif; ?>
                    </dd>
                </dl>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= esc($producto['nombre']) ?></h3>
                <div class="card-tools">
                    <a href="<?= base_url('inventario/productos/editar/' . $producto['id']) ?>" 
                       class="btn btn-sm btn-primary">
                        <i class="fas fa-edit"></i> Editar
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5>Descripción</h5>
                        <p><?= esc($producto['descripcion'] ?? 'Sin descripción') ?></p>
                        
                        <h5>Precios</h5>
                        <table class="table table-sm">
                            <tr>
                                <td>Precio de Compra:</td>
                                <td><strong><?= formatGs($producto['precio_compra']) ?></strong></td>
                            </tr>
                            <tr>
                                <td>Precio de Venta:</td>
                                <td><strong><?= formatGs($producto['precio_venta']) ?></strong></td>
                            </tr>
                            <tr>
                                <td>Precio Mayorista:</td>
                                <td><?= $producto['precio_mayorista'] ? formatGs($producto['precio_mayorista']) : '-' ?></td>
                            </tr>
                            <tr>
                                <td>Margen de Utilidad:</td>
                                <td><?= number_format($producto['margen_utilidad'] ?? 0, 2) ?>%</td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="col-md-6">
                        <h5>Control de Stock</h5>
                        <table class="table table-sm">
                            <tr>
                                <td>Controla Stock:</td>
                                <td>
                                    <?php if ($producto['controla_stock']): ?>
                                        <span class="badge badge-success">Sí</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">No</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Stock Mínimo:</td>
                                <td><?= number_format($producto['stock_minimo'], 0, ',', '.') ?></td>
                            </tr>
                            <tr>
                                <td>Stock Máximo:</td>
                                <td><?= number_format($producto['stock_maximo'], 0, ',', '.') ?></td>
                            </tr>
                            <tr>
                                <td>Punto de Reorden:</td>
                                <td><?= number_format($producto['punto_reorden'], 0, ',', '.') ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h3 class="card-title">Stock por Sucursal</h3>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Sucursal</th>
                            <th>Cantidad Total</th>
                            <th>Reservada</th>
                            <th>Disponible</th>
                            <th>Ubicación</th>
                            <th>Costo Promedio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($stock)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">No hay stock registrado</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($stock as $item): ?>
                                <tr>
                                    <td><?= esc($item['sucursal_nombre']) ?></td>
                                    <td><?= number_format($item['cantidad'], 2, ',', '.') ?></td>
                                    <td><?= number_format($item['cantidad_reservada'], 2, ',', '.') ?></td>
                                    <td>
                                        <strong><?= number_format($item['cantidad_disponible'], 2, ',', '.') ?></strong>
                                    </td>
                                    <td><?= esc($item['ubicacion'] ?? '-') ?></td>
                                    <td><?= formatGs($item['costo_promedio'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
