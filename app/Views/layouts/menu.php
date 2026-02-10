<?php
// Permisos del usuario (pasados desde BaseController::render())
$perms = $userPermissions ?? [];
$fullAccess = !empty($perms['__full_access']);

/**
 * Verificar si el usuario puede ver un módulo
 */
function menuCanView($modulo, $perms, $fullAccess) {
    if ($fullAccess) return true;
    return isset($perms[$modulo]) && !empty($perms[$modulo]['ver']);
}

// Menú dinámico filtrado por permisos del rol
$menuItems = [
    [
        'icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard',
        'url' => base_url('dashboard'), 'active' => uri_string() == 'dashboard',
        'module' => 'DASHBOARD'
    ],
    [
        'icon' => 'fas fa-shopping-cart', 'text' => 'Punto de Venta',
        'url' => base_url('ventas/pos'), 'active' => strpos(uri_string(), 'ventas/pos') !== false,
        'badge' => 'POS', 'module' => 'VENTAS'
    ],
    [
        'icon' => 'fas fa-boxes', 'text' => 'Inventario',
        'url' => '#', 'active' => strpos(uri_string(), 'inventario') !== false,
        'module' => 'INVENTARIO',
        'submenu' => [
            ['text' => 'Productos', 'url' => base_url('inventario/productos'), 'icon' => 'fas fa-box', 'module' => 'PRODUCTOS'],
            ['text' => 'Categorías', 'url' => base_url('inventario/categorias'), 'icon' => 'fas fa-tags', 'module' => 'PRODUCTOS'],
            ['text' => 'Marcas', 'url' => base_url('inventario/marcas'), 'icon' => 'fas fa-certificate', 'module' => 'PRODUCTOS'],
            ['text' => 'Stock', 'url' => base_url('inventario/stock'), 'icon' => 'fas fa-warehouse', 'module' => 'INVENTARIO'],
            ['text' => 'Transferencias', 'url' => base_url('inventario/transferencias'), 'icon' => 'fas fa-exchange-alt', 'module' => 'INVENTARIO'],
            ['text' => 'Ajustes', 'url' => base_url('inventario/ajustes'), 'icon' => 'fas fa-sliders-h', 'module' => 'INVENTARIO'],
            ['text' => 'Movimientos', 'url' => base_url('inventario/movimientos'), 'icon' => 'fas fa-history', 'module' => 'INVENTARIO'],
        ]
    ],
    [
        'icon' => 'fas fa-users', 'text' => 'Clientes',
        'url' => base_url('clientes'), 'active' => strpos(uri_string(), 'clientes') !== false,
        'module' => 'CLIENTES'
    ],
    [
        'icon' => 'fas fa-truck', 'text' => 'Proveedores',
        'url' => base_url('proveedores'), 'active' => strpos(uri_string(), 'proveedores') !== false,
        'module' => 'PROVEEDORES'
    ],
    [
        'icon' => 'fas fa-tools', 'text' => 'Órdenes de Servicio',
        'url' => base_url('servicios/ordenes'), 'active' => strpos(uri_string(), 'servicios') !== false,
        'module' => 'SERVICIOS'
    ],
    [
        'icon' => 'fas fa-file-invoice-dollar', 'text' => 'Ventas',
        'url' => '#', 'active' => strpos(uri_string(), 'ventas') !== false,
        'module' => 'VENTAS',
        'submenu' => [
            ['text' => 'Lista de Ventas', 'url' => base_url('ventas'), 'icon' => 'fas fa-list', 'module' => 'VENTAS'],
            ['text' => 'Punto de Venta', 'url' => base_url('ventas/pos'), 'icon' => 'fas fa-cash-register', 'module' => 'VENTAS'],
            ['text' => 'Cotizaciones', 'url' => base_url('ventas/cotizaciones'), 'icon' => 'fas fa-file-alt', 'module' => 'VENTAS'],
            ['text' => 'Devoluciones', 'url' => base_url('ventas/devoluciones'), 'icon' => 'fas fa-undo', 'module' => 'VENTAS'],
            ['text' => 'Cajas', 'url' => base_url('finanzas/cajas'), 'icon' => 'fas fa-cash-register', 'module' => 'FINANZAS'],
        ]
    ],
    [
        'icon' => 'fas fa-shopping-bag', 'text' => 'Compras',
        'url' => base_url('compras'), 'active' => strpos(uri_string(), 'compras') !== false,
        'module' => 'COMPRAS'
    ],
    [
        'icon' => 'fas fa-money-bill-wave', 'text' => 'Finanzas',
        'url' => '#', 'active' => strpos(uri_string(), 'finanzas') !== false,
        'module' => 'FINANZAS',
        'submenu' => [
            ['text' => 'Cuentas por Cobrar', 'url' => base_url('finanzas/cuentas-cobrar'), 'icon' => 'fas fa-hand-holding-usd', 'module' => 'FINANZAS'],
            ['text' => 'Cuentas por Pagar', 'url' => base_url('finanzas/cuentas-pagar'), 'icon' => 'fas fa-file-invoice', 'module' => 'FINANZAS'],
        ]
    ],
    [
        'icon' => 'fas fa-chart-bar', 'text' => 'Reportes',
        'url' => base_url('reportes'), 'active' => strpos(uri_string(), 'reportes') !== false,
        'module' => 'REPORTES'
    ],
    [
        'icon' => 'fas fa-cog', 'text' => 'Configuración',
        'url' => '#', 'active' => (strpos(uri_string(), 'admin') !== false) || (strpos(uri_string(), 'configuracion') !== false),
        'module' => 'CONFIGURACION',
        'submenu' => [
            ['text' => 'Empresas', 'url' => base_url('admin/empresas'), 'icon' => 'fas fa-building', 'module' => 'EMPRESAS'],
            ['text' => 'Sucursales', 'url' => base_url('admin/sucursales'), 'icon' => 'fas fa-store', 'module' => 'SUCURSALES'],
            ['text' => 'Usuarios', 'url' => base_url('admin/usuarios'), 'icon' => 'fas fa-users', 'module' => 'USUARIOS'],
            ['text' => 'Roles y Permisos', 'url' => base_url('admin/roles'), 'icon' => 'fas fa-user-shield', 'module' => 'ROLES'],
            ['text' => 'Módulos del Sistema', 'url' => base_url('admin/modulos'), 'icon' => 'fas fa-puzzle-piece', 'module' => 'MODULOS'],
            ['text' => 'Listas de Precios', 'url' => base_url('admin/listas-precios'), 'icon' => 'fas fa-tags', 'module' => 'CONFIGURACION'],
            ['text' => 'Talonarios', 'url' => base_url('configuracion/talonarios'), 'icon' => 'fas fa-receipt', 'module' => 'CONFIGURACION'],
            ['text' => 'Configuración General', 'url' => base_url('configuracion'), 'icon' => 'fas fa-cogs', 'module' => 'CONFIGURACION'],
        ]
    ],
];
?>

<?php foreach ($menuItems as $item): ?>
    <?php
    // Filtrar por permiso del módulo principal
    $mod = $item['module'] ?? null;
    if ($mod && !menuCanView($mod, $perms, $fullAccess)) continue;
    ?>
    <?php if (isset($item['submenu'])): ?>
        <?php
        // Filtrar submenú por permisos
        $visibleSub = [];
        foreach ($item['submenu'] as $sub) {
            $subMod = $sub['module'] ?? $mod;
            if (!$subMod || menuCanView($subMod, $perms, $fullAccess)) {
                $visibleSub[] = $sub;
            }
        }
        if (empty($visibleSub)) continue;
        ?>
        <li class="nav-item has-treeview <?= $item['active'] ? 'menu-open' : '' ?>">
            <a href="#" class="nav-link <?= $item['active'] ? 'active' : '' ?>">
                <i class="nav-icon <?= $item['icon'] ?>"></i>
                <p>
                    <?= esc($item['text']) ?>
                    <?php if (isset($item['badge'])): ?>
                        <span class="right badge badge-info"><?= esc($item['badge']) ?></span>
                    <?php endif; ?>
                    <i class="right fas fa-angle-left"></i>
                </p>
            </a>
            <ul class="nav nav-treeview">
                <?php foreach ($visibleSub as $subitem): ?>
                    <li class="nav-item">
                        <a href="<?= $subitem['url'] ?>"
                            class="nav-link <?= uri_string() == str_replace(base_url(), '', $subitem['url']) ? 'active' : '' ?>">
                            <i class="nav-icon <?= isset($subitem['icon']) ? $subitem['icon'] : 'fas fa-circle' ?>"></i>
                            <p><?= esc($subitem['text']) ?></p>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </li>
    <?php else: ?>
        <li class="nav-item">
            <a href="<?= $item['url'] ?>" class="nav-link <?= $item['active'] ? 'active' : '' ?>">
                <i class="nav-icon <?= $item['icon'] ?>"></i>
                <p>
                    <?= esc($item['text']) ?>
                    <?php if (isset($item['badge'])): ?>
                        <span class="right badge badge-info"><?= esc($item['badge']) ?></span>
                    <?php endif; ?>
                </p>
            </a>
        </li>
    <?php endif; ?>
<?php endforeach; ?>