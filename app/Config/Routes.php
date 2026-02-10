<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// Redirigir raíz al dashboard si está logueado, sino al login
$routes->get('/', function () {
    $session = \Config\Services::session();
    if ($session->has('user_id')) {
        return redirect()->to('/dashboard');
    }
    return redirect()->to('/auth/login');
});

// Temporary debug route
$routes->get('debug-db', 'Debug_db::index');
$routes->get('fix-audit', 'Fix_sequences::fixAuditTrigger');

// Rutas de autenticación
$routes->group('auth', function ($routes) {
    $routes->get('login', 'AuthController::login');
    $routes->post('processLogin', 'AuthController::processLogin');
    $routes->get('logout', 'AuthController::logout');
    $routes->get('cambiar-password', 'AuthController::cambiarPassword');
    $routes->post('cambiar-password', 'AuthController::cambiarPassword');
});

// Dashboard
$routes->get('dashboard', 'DashboardController::index', ['filter' => 'auth']);

// Perfil de usuario
$routes->group('perfil', ['filter' => 'auth'], function ($routes) {
    $routes->get('/', 'PerfilController::index');
    $routes->post('actualizar-datos', 'PerfilController::actualizarDatos');
    $routes->post('cambiar-password', 'PerfilController::cambiarPassword');
    $routes->post('cambiar-foto', 'PerfilController::cambiarFoto');
    $routes->post('cerrar-sesion', 'PerfilController::cerrarSesion');
    $routes->post('cerrar-todas-sesiones', 'PerfilController::cerrarTodasSesiones');
    $routes->post('actualizar-preferencias', 'PerfilController::actualizarPreferencias');
    $routes->post('guardar-tema', 'PerfilController::guardarTema');
    $routes->get('historial', 'PerfilController::obtenerHistorial');
});

// Rutas de administración
$routes->group('admin', ['filter' => 'auth'], function ($routes) {
    $routes->group('empresas', function ($routes) {
        $routes->get('/', 'Admin\EmpresasController::index');
        $routes->get('crear', 'Admin\EmpresasController::crear');
        $routes->post('crear', 'Admin\EmpresasController::guardar');
        $routes->get('editar/(:num)', 'Admin\EmpresasController::editar/$1');
        $routes->post('editar/(:num)', 'Admin\EmpresasController::actualizar/$1');
        $routes->post('eliminar/(:num)', 'Admin\EmpresasController::eliminar/$1');
    });

    $routes->group('sucursales', function ($routes) {
        $routes->get('/', 'Admin\SucursalesController::index');
        $routes->get('crear', 'Admin\SucursalesController::crear');
        $routes->post('crear', 'Admin\SucursalesController::guardar');
        $routes->get('editar/(:num)', 'Admin\SucursalesController::editar/$1');
        $routes->post('editar/(:num)', 'Admin\SucursalesController::actualizar/$1');
        $routes->get('ver/(:num)', 'Admin\SucursalesController::ver/$1');
        $routes->post('eliminar/(:num)', 'Admin\SucursalesController::eliminar/$1');
        $routes->post('getPorEmpresa', 'Admin\SucursalesController::getPorEmpresa');
    });

    $routes->group('usuarios', function ($routes) {
        $routes->get('/', 'Admin\UsuariosController::index');
        $routes->get('crear', 'Admin\UsuariosController::crear');
        $routes->post('crear', 'Admin\UsuariosController::guardar');
        $routes->get('editar/(:num)', 'Admin\UsuariosController::editar/$1');
        $routes->post('editar/(:num)', 'Admin\UsuariosController::actualizar/$1');
        $routes->get('ver/(:num)', 'Admin\UsuariosController::ver/$1');
        $routes->post('eliminar/(:num)', 'Admin\UsuariosController::eliminar/$1');
    });

    $routes->group('modulos', function ($routes) {
        $routes->get('/', 'Admin\ModulosController::index');
        $routes->post('asignar-empresa', 'Admin\ModulosController::asignarEmpresa');
        $routes->post('asignar-sucursal', 'Admin\ModulosController::asignarSucursal');
        $routes->post('desactivar/(:num)', 'Admin\ModulosController::desactivar/$1');
        $routes->post('renovar/(:num)', 'Admin\ModulosController::renovar/$1');
    });

    $routes->group('roles', function ($routes) {
        $routes->get('/', 'Admin\RolesController::index');
        $routes->get('crear', 'Admin\RolesController::crear');
        $routes->post('crear', 'Admin\RolesController::guardar');
        $routes->get('editar/(:num)', 'Admin\RolesController::editar/$1');
        $routes->post('editar/(:num)', 'Admin\RolesController::actualizar/$1');
    });

    // Rutas para listas de precios (alias de ventas/listas-precios)
    $routes->group('listas-precios', function ($routes) {
        $routes->get('/', 'Ventas\ListasPreciosController::index');
        $routes->get('crear', 'Ventas\ListasPreciosController::crear');
        $routes->post('crear', 'Ventas\ListasPreciosController::guardar');
        $routes->post('guardar', 'Ventas\ListasPreciosController::guardar');
        $routes->get('editar/(:num)', 'Ventas\ListasPreciosController::editar/$1');
        $routes->post('editar/(:num)', 'Ventas\ListasPreciosController::actualizar/$1');
        $routes->post('actualizar/(:num)', 'Ventas\ListasPreciosController::actualizar/$1');
        $routes->post('eliminar/(:num)', 'Ventas\ListasPreciosController::eliminar/$1');
        $routes->post('inactivar/(:num)', 'Ventas\ListasPreciosController::inactivar/$1');
        $routes->post('activar/(:num)', 'Ventas\ListasPreciosController::activar/$1');
        $routes->post('obtener-precio', 'Ventas\ListasPreciosController::obtenerPrecio');
    });

    // Rutas legacy (redirigir a configuracion/* para evitar duplicados)
    $routes->group('talonarios', function ($routes) {
        $routes->get('/', static function () {
            return redirect()->to(base_url('configuracion/talonarios'));
        });
        $routes->get('crear', static function () {
            return redirect()->to(base_url('configuracion/talonarios/crear'));
        });
        $routes->post('guardar', static function () {
            return redirect()->to(base_url('configuracion/talonarios/guardar'));
        });
        $routes->get('editar/(:num)', static function ($id) {
            return redirect()->to(base_url('configuracion/talonarios/editar/' . $id));
        });
        $routes->post('actualizar/(:num)', static function ($id) {
            return redirect()->to(base_url('configuracion/talonarios/actualizar/' . $id));
        });
        $routes->post('eliminar/(:num)', static function ($id) {
            return redirect()->to(base_url('configuracion/talonarios/eliminar/' . $id));
        });
        $routes->post('obtener-siguiente-numero', static function () {
            return redirect()->to(base_url('configuracion/talonarios/obtener-siguiente-numero'));
        });
        $routes->post('incrementar-numero/(:num)', static function ($id) {
            return redirect()->to(base_url('configuracion/talonarios/incrementar-numero/' . $id));
        });
    });

    $routes->get('configuracion', static function () {
        return redirect()->to(base_url('configuracion'));
    });
    $routes->post('configuracion/guardar', static function () {
        return redirect()->to(base_url('configuracion/guardar'));
    });
});

// Rutas de inventario
$routes->group('inventario', ['filter' => 'auth'], function ($routes) {
    $routes->group('productos', function ($routes) {
        $routes->get('/', 'Inventario\ProductosController::index');
        $routes->get('crear', 'Inventario\ProductosController::crear');
        $routes->post('crear', 'Inventario\ProductosController::guardar');
        $routes->get('editar/(:num)', 'Inventario\ProductosController::editar/$1');
        $routes->post('editar/(:num)', 'Inventario\ProductosController::actualizar/$1');
        $routes->get('ver/(:num)', 'Inventario\ProductosController::ver/$1');
        $routes->get('imprimirCodigoBarra/(:num)', 'Inventario\ProductosController::imprimirCodigoBarra/$1');
        $routes->post('eliminar/(:num)', 'Inventario\ProductosController::eliminar/$1');
    });

    $routes->group('categorias', function ($routes) {
        $routes->get('/', 'Inventario\CategoriasController::index');
        $routes->get('crear', 'Inventario\CategoriasController::crear');
        $routes->post('crear', 'Inventario\CategoriasController::guardar');
        $routes->get('editar/(:num)', 'Inventario\CategoriasController::editar/$1');
        $routes->post('editar/(:num)', 'Inventario\CategoriasController::actualizar/$1');
        $routes->get('(:num)', 'Inventario\CategoriasController::obtener/$1');
        $routes->post('eliminar/(:num)', 'Inventario\CategoriasController::eliminar/$1');
    });

    $routes->group('marcas', function ($routes) {
        $routes->get('/', 'Inventario\MarcasController::index');
        $routes->get('crear', 'Inventario\MarcasController::crear');
        $routes->post('crear', 'Inventario\MarcasController::guardar');
        $routes->get('editar/(:num)', 'Inventario\MarcasController::editar/$1');
        $routes->post('editar/(:num)', 'Inventario\MarcasController::actualizar/$1');
        $routes->get('(:num)', 'Inventario\MarcasController::obtener/$1');
        $routes->post('eliminar/(:num)', 'Inventario\MarcasController::eliminar/$1');
    });

    $routes->group('stock', function ($routes) {
        $routes->get('/', 'Inventario\StockController::index');
        $routes->post('actualizar', 'Inventario\StockController::actualizar');
        $routes->get('kardex', 'Inventario\StockController::kardex');
        $routes->get('kardex/(:num)', 'Inventario\StockController::kardex/$1');
    });
    $routes->group('transferencias', function ($routes) {
        $routes->get('/', 'Inventario\TransferenciasController::index');
        $routes->get('crear', 'Inventario\TransferenciasController::crear');
        $routes->post('crear', 'Inventario\TransferenciasController::guardar');
        $routes->get('ver/(:num)', 'Inventario\TransferenciasController::ver/$1');
        $routes->post('enviar/(:num)', 'Inventario\TransferenciasController::enviar/$1');
        $routes->post('recibir/(:num)', 'Inventario\TransferenciasController::recibir/$1');
    });
    $routes->group('ajustes', function ($routes) {
        $routes->get('/', 'Inventario\AjustesController::index');
        $routes->get('crear', 'Inventario\AjustesController::crear');
        $routes->post('crear', 'Inventario\AjustesController::guardar');
        $routes->get('ver/(:num)', 'Inventario\AjustesController::ver/$1');
        $routes->post('aprobar/(:num)', 'Inventario\AjustesController::aprobar/$1');
    });
    $routes->get('movimientos', 'Inventario\MovimientosController::index');
});

// Rutas de clientes
$routes->group('clientes', ['filter' => 'auth'], function ($routes) {
    $routes->get('/', 'Clientes\ClientesController::index');
    $routes->get('crear', 'Clientes\ClientesController::crear');
    $routes->post('crear', 'Clientes\ClientesController::guardar');
    $routes->get('editar/(:num)', 'Clientes\ClientesController::editar/$1');
    $routes->post('editar/(:num)', 'Clientes\ClientesController::actualizar/$1');
    $routes->get('ver/(:num)', 'Clientes\ClientesController::ver/$1');
    $routes->post('eliminar/(:num)', 'Clientes\ClientesController::eliminar/$1');
});

// Rutas de ventas
$routes->group('ventas', ['filter' => 'auth'], function ($routes) {
    $routes->get('/', 'Ventas\VentasController::index');
    $routes->get('pos', 'Ventas\POSController::index');
    $routes->post('pos/procesar', 'Ventas\POSController::procesarVenta');
    $routes->post('pos/buscarProducto', 'Ventas\POSController::buscarProducto');
    $routes->post('pos/obtenerPrecios', 'Ventas\POSController::obtenerPrecios');
    $routes->get('pos/fix-sequences', 'Ventas\POSController::fixSequences');
    // Ruta temporal para corregir trigger - ELIMINAR DESPUÉS
    $routes->get('pos/fix-trigger', 'Ventas\POSController::fixTrigger');
    $routes->group('cotizaciones', function ($routes) {
        $routes->get('/', 'Ventas\CotizacionesController::index');
        $routes->get('crear', 'Ventas\CotizacionesController::crear');
        $routes->post('guardar', 'Ventas\CotizacionesController::guardar');
        $routes->get('ver/(:num)', 'Ventas\CotizacionesController::ver/$1');
        $routes->post('convertir/(:num)', 'Ventas\CotizacionesController::convertirAVenta/$1');
    });
    $routes->get('ver/(:num)', 'Ventas\VentasController::ver/$1');
    $routes->group('devoluciones', function ($routes) {
        $routes->get('/', 'Ventas\DevolucionesController::index');
        $routes->get('crear', 'Ventas\DevolucionesController::crear');
        $routes->post('guardar', 'Ventas\DevolucionesController::guardar');
        $routes->get('ver/(:num)', 'Ventas\DevolucionesController::ver/$1');
    });
    $routes->get('imprimir/(:num)', 'Ventas\VentasController::imprimirTicket/$1');
    $routes->get('imprimirFactura/(:num)', 'Ventas\VentasController::imprimirFactura/$1');
    $routes->post('anular/(:num)', 'Ventas\VentasController::anular/$1');
    $routes->group('listas-precios', function ($routes) {
        $routes->get('/', 'Ventas\ListasPreciosController::index');
        $routes->get('crear', 'Ventas\ListasPreciosController::crear');
        $routes->post('crear', 'Ventas\ListasPreciosController::guardar');
        $routes->post('guardar', 'Ventas\ListasPreciosController::guardar');
        $routes->get('editar/(:num)', 'Ventas\ListasPreciosController::editar/$1');
        $routes->post('editar/(:num)', 'Ventas\ListasPreciosController::actualizar/$1');
        $routes->post('actualizar/(:num)', 'Ventas\ListasPreciosController::actualizar/$1');
        $routes->post('eliminar/(:num)', 'Ventas\ListasPreciosController::eliminar/$1');
        $routes->post('inactivar/(:num)', 'Ventas\ListasPreciosController::inactivar/$1');
        $routes->post('activar/(:num)', 'Ventas\ListasPreciosController::activar/$1');
        $routes->post('obtener-precio', 'Ventas\ListasPreciosController::obtenerPrecio');
    });
});

// Rutas de compras
$routes->group('compras', ['filter' => 'auth'], function ($routes) {
    $routes->get('/', 'Compras\ComprasController::index');
    $routes->get('crear', 'Compras\ComprasController::crear');
    $routes->post('crear', 'Compras\ComprasController::guardar');
    $routes->get('ver/(:num)', 'Compras\ComprasController::ver/$1');
    $routes->post('recibir/(:num)', 'Compras\ComprasController::recibir/$1');
    $routes->group('proveedores', function ($routes) {
        $routes->get('/', 'Compras\ProveedoresController::index');
        $routes->get('crear', 'Compras\ProveedoresController::crear');
        $routes->post('crear', 'Compras\ProveedoresController::guardar');
        $routes->post('guardar', 'Compras\ProveedoresController::guardar');
        $routes->get('editar/(:num)', 'Compras\ProveedoresController::editar/$1');
        $routes->post('editar/(:num)', 'Compras\ProveedoresController::actualizar/$1');
        $routes->post('eliminar/(:num)', 'Compras\ProveedoresController::eliminar/$1');
    });
});

// Ruta directa para proveedores (compatibilidad)
$routes->group('proveedores', ['filter' => 'auth'], function ($routes) {
    $routes->get('/', 'Compras\ProveedoresController::index');
    $routes->get('crear', 'Compras\ProveedoresController::crear');
    $routes->post('crear', 'Compras\ProveedoresController::guardar');
    $routes->get('editar/(:num)', 'Compras\ProveedoresController::editar/$1');
    $routes->post('editar/(:num)', 'Compras\ProveedoresController::actualizar/$1');
    $routes->post('eliminar/(:num)', 'Compras\ProveedoresController::eliminar/$1');
});

// Rutas de finanzas
$routes->group('finanzas', ['filter' => 'auth'], function ($routes) {
    $routes->group('cuentas-cobrar', function ($routes) {
        $routes->get('/', 'Finanzas\CuentasCobrarController::index');
        $routes->post('registrar-pago', 'Finanzas\CuentasCobrarController::registrarPago');
        $routes->post('sincronizar', 'Finanzas\CuentasCobrarController::sincronizar');
        $routes->get('historial/(:num)', 'Finanzas\CuentasCobrarController::obtenerHistorialPagos/$1');
    });
    $routes->group('cuentas-pagar', function ($routes) {
        $routes->get('/', 'Finanzas\CuentasPagarController::index');
        $routes->post('registrar-pago', 'Finanzas\CuentasPagarController::registrarPago');
        $routes->post('sincronizar', 'Finanzas\CuentasPagarController::sincronizar');
    });
    $routes->group('cajas', function ($routes) {
        $routes->get('/', 'Finanzas\CajasController::index');
        $routes->get('crear', 'Finanzas\CajasController::crear');
        $routes->post('guardar', 'Finanzas\CajasController::guardar');
        $routes->get('abrir', 'Finanzas\CajasController::abrirSeleccionar');
        $routes->get('abrir/(:num)', 'Finanzas\CajasController::abrir/$1');
        $routes->post('abrir/(:num)', 'Finanzas\CajasController::procesarApertura/$1');
        $routes->get('cerrar/(:num)', 'Finanzas\CajasController::cerrar/$1');
        $routes->post('cerrar/(:num)', 'Finanzas\CajasController::procesarCierre/$1');
        $routes->get('ver/(:num)', 'Finanzas\CajasController::ver/$1');
        $routes->get('arqueo/(:num)', 'Finanzas\CajasController::arqueo/$1');
        $routes->post('arqueo/(:num)', 'Finanzas\CajasController::procesarArqueo/$1');
        $routes->post('movimiento/(:num)', 'Finanzas\CajasController::registrarMovimiento/$1');
    });
});

// Rutas de servicios
$routes->group('servicios', ['filter' => 'auth'], function ($routes) {
    $routes->group('ordenes', function ($routes) {
        $routes->get('/', 'Servicios\OrdenesController::index');
        $routes->get('crear', 'Servicios\OrdenesController::crear');
        $routes->post('crear', 'Servicios\OrdenesController::guardar');
        $routes->get('editar/(:num)', 'Servicios\OrdenesController::editar/$1');
        $routes->post('actualizar/(:num)', 'Servicios\OrdenesController::actualizar/$1');
        $routes->get('ver/(:num)', 'Servicios\OrdenesController::ver/$1');
        $routes->post('cambiar-estado', 'Servicios\OrdenesController::cambiarEstado');
        $routes->post('agregar-repuesto', 'Servicios\OrdenesController::agregarRepuesto');
        $routes->post('agregar-mano-obra', 'Servicios\OrdenesController::agregarManoObra');
        $routes->post('agregar-comentario', 'Servicios\OrdenesController::agregarComentario');
        $routes->post('subir-imagen', 'Servicios\OrdenesController::subirImagen');
        $routes->post('eliminar-imagen', 'Servicios\OrdenesController::eliminarImagen');
        $routes->post('eliminar-repuesto', 'Servicios\OrdenesController::eliminarRepuesto');
        $routes->post('eliminar-mano-obra', 'Servicios\OrdenesController::eliminarManoObra');
        $routes->get('facturar/(:num)', 'Servicios\OrdenesController::facturar/$1');
        $routes->get('buscar-clientes', 'Servicios\OrdenesController::buscarClientes');
        $routes->get('buscar-tecnicos', 'Servicios\OrdenesController::buscarTecnicos');
        $routes->get('buscar-productos', 'Servicios\OrdenesController::buscarProductos');
        $routes->get('generar-presupuesto/(:num)', 'Servicios\OrdenesController::generarPresupuesto/$1');
        $routes->get('imprimir-recepcion/(:num)', 'Servicios\OrdenesController::imprimirRecepcion/$1');
    });
});

// Rutas de reportes
$routes->group('reportes', ['filter' => 'auth'], function ($routes) {
    $routes->get('/', 'Reportes\ReportesController::index');
    $routes->get('ventas', 'Reportes\VentasController::index');
    $routes->group('inventario', function ($routes) {
        $routes->get('/', 'Reportes\InventarioController::index');
        $routes->get('stock-bajo', 'Reportes\InventarioController::stockBajo');
        $routes->get('proximos-vencer', 'Reportes\InventarioController::proximosVencer');
        $routes->get('movimientos', 'Reportes\InventarioController::movimientos');
        $routes->get('exportar/(:segment)', 'Reportes\InventarioController::exportarExcel/$1');
    });
    $routes->get('servicios', 'Reportes\ServiciosController::index');
    $routes->post('generar', 'Reportes\ReportesController::generar');
});

// Rutas de notificaciones
$routes->group('notificaciones', ['filter' => 'auth'], function ($routes) {
    $routes->get('/', 'Notificaciones\NotificacionesController::index');
    $routes->post('marcar-leida/(:num)', 'Notificaciones\NotificacionesController::marcarLeida/$1');
    $routes->post('marcar-todas-leidas', 'Notificaciones\NotificacionesController::marcarTodasLeidas');
    $routes->post('eliminar/(:num)', 'Notificaciones\NotificacionesController::eliminar/$1');
    $routes->get('no-leidas', 'Notificaciones\NotificacionesController::obtenerNoLeidas');
});

// Rutas de configuración
$routes->group('configuracion', ['filter' => 'auth'], function ($routes) {
    $routes->get('/', 'Configuracion\ConfiguracionController::index');
    $routes->post('guardar', 'Configuracion\ConfiguracionController::guardar');

    // Rutas de configuración
    $routes->group('talonarios', function ($routes) {
        $routes->get('/', 'Configuracion\TalonariosController::index');
        $routes->get('crear', 'Configuracion\TalonariosController::crear');
        $routes->post('guardar', 'Configuracion\TalonariosController::guardar');
        $routes->get('editar/(:num)', 'Configuracion\TalonariosController::editar/$1');
        $routes->post('actualizar/(:num)', 'Configuracion\TalonariosController::actualizar/$1');
        $routes->post('eliminar/(:num)', 'Configuracion\TalonariosController::eliminar/$1');
        $routes->post('obtener-siguiente-numero', 'Configuracion\TalonariosController::obtenerSiguienteNumero');
        $routes->post('incrementar-numero/(:num)', 'Configuracion\TalonariosController::incrementarNumero/$1');
    });

    $routes->group('tipos-cambio', function ($routes) {
        $routes->get('/', 'Configuracion\TiposCambioController::index');
        $routes->get('crear', 'Configuracion\TiposCambioController::crear');
        $routes->post('guardar', 'Configuracion\TiposCambioController::guardar');
        $routes->get('editar/(:num)', 'Configuracion\TiposCambioController::editar/$1');
        $routes->post('actualizar/(:num)', 'Configuracion\TiposCambioController::actualizar/$1');
        $routes->post('eliminar/(:num)', 'Configuracion\TiposCambioController::eliminar/$1');
        $routes->post('obtener-tipo-cambio', 'Configuracion\TiposCambioController::obtenerTipoCambio');
    });
});

