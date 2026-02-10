<?= $this->extend('layouts/adminlte') ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item active">Productos</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-boxes mr-1"></i> Lista de Productos</h3>
                <div class="card-tools">
                    <a href="<?= base_url('inventario/productos/crear') ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Nuevo Producto
                    </a>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Imagen</th>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Precio Venta</th>
                            <th>Stock</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productos as $producto): ?>
                            <tr>
                                <td><?= $producto['id'] ?></td>
                                <td>
                                    <?php if ($producto['imagen_principal']): ?>
                                        <img src="<?= base_url('uploads/productos/' . $producto['imagen_principal']) ?>"
                                            alt="Imagen" style="width: 50px; height: 50px; object-fit: cover;">
                                    <?php else: ?>
                                        <i class="fas fa-image fa-2x text-muted"></i>
                                    <?php endif; ?>
                                </td>
                                <td><?= esc($producto['codigo']) ?></td>
                                <td><?= esc($producto['nombre']) ?></td>
                                <td>
                                    <span class="badge badge-info"><?= esc($producto['tipo_producto']) ?></span>
                                </td>
                                <td class="currency" data-order="<?= (float) ($producto['precio_venta'] ?? 0) ?>">
                                    <?= formatGs($producto['precio_venta'] ?? 0) ?>
                                </td>
                                    <?php if ($producto['controla_stock']): ?>
                                        <?php 
                                            // Ensure stock is treated as a number
                                            $stockVal = (float)($producto['stock'] ?? 0);
                                            $badgeClass = $stockVal > 0 ? 'badge-primary' : 'badge-danger'; 
                                        ?>
                                    <td data-order="<?= $stockVal ?>">
                                        <span class="badge <?= $badgeClass ?>" style="font-size: 1rem;"><?= (float)$stockVal ?></span>
                                    <?php else: ?>
                                    <td data-order="0">
                                        <span class="badge badge-secondary">Sin Control</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($producto['estado']): ?>
                                        <span class="badge badge-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= base_url('inventario/productos/ver/' . $producto['id']) ?>"
                                        class="btn btn-sm btn-info" title="Ver">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="<?= base_url('inventario/productos/editar/' . $producto['id']) ?>"
                                        class="btn btn-sm btn-warning" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-secondary"
                                        onclick="window.open('<?= base_url('inventario/productos/imprimirCodigoBarra/' . $producto['id']) ?>', 'Barcode', 'width=400,height=300')"
                                        title="Código de Barras">
                                        <i class="fas fa-barcode"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger"
                                        onclick="eliminarProducto(<?= $producto['id'] ?>)" title="Eliminar">
                                        <i class="fas fa-trash"></i>
                                    </button>
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
    $(document).ready(function () {
        // Configurar DataTable con formateo de precios
        $('.data-table').DataTable({
            ...datatablesEsConfig,
            columnDefs: [
                {
                    targets: [5], // Columna de Precio Venta (índice 5)
                    render: function (data, type, row) {
                        if (type === 'display' || type === 'type') {
                            // Si ya viene formateado desde el servidor, mantenerlo
                            if (typeof data === 'string' && data.includes('Gs.')) {
                                return data;
                            }
                            // Si es un número, formatearlo
                            const precio = parseFloat(data) || 0;
                            if (precio <= 0) {
                                return '0 Gs.';
                            }
                            // Formatear manualmente para evitar problemas de precisión
                            const precioInt = Math.floor(precio);
                            const precioStr = precioInt.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                            return precioStr + ' Gs.';
                        }
                        // Para ordenamiento y búsqueda, usar el valor numérico
                        return parseFloat(data) || 0;
                    },
                    type: 'num'
                }
            ],
            order: [[0, 'desc']] // Ordenar por ID descendente por defecto
        });
    });

    function eliminarProducto(id) {
        if (confirm('¿Está seguro de eliminar este producto?')) {
            $.ajax({
                url: '<?= base_url('inventario/productos/eliminar') ?>/' + id,
                type: 'POST',
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.message || 'Error al eliminar');
                    }
                },
                error: function () {
                    alert('Error al eliminar el producto');
                }
            });
        }
    }
</script>
<?= $this->endSection() ?>