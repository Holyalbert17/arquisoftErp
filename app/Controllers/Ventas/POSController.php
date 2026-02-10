<?php

namespace App\Controllers\Ventas;

use App\Controllers\BaseController;
use App\Models\ProductoModel;
use App\Models\ClienteModel;
use App\Models\VentaModel;
use App\Models\VentaDetalleModel;
use App\Models\PagoVentaModel;
use App\Models\StockModel;
use App\Models\ListaPrecioModel;
use App\Models\CajaModel;
use App\Models\MovimientoCajaModel;

class POSController extends BaseController
{
    protected $productoModel;
    protected $clienteModel;
    protected $ventaModel;
    protected $ventaDetalleModel;
    protected $pagoVentaModel;
    protected $stockModel;
    protected $listaPrecioModel;
    protected $cajaModel;
    protected $movimientoCajaModel;
    protected $ventaService;

    public function __construct()
    {
        $this->productoModel = new ProductoModel();
        $this->clienteModel = new ClienteModel();
        $this->ventaModel = new VentaModel();
        $this->ventaDetalleModel = new VentaDetalleModel();
        $this->pagoVentaModel = new PagoVentaModel();
        $this->stockModel = new StockModel();
        $this->listaPrecioModel = new ListaPrecioModel();
        $this->cajaModel = new CajaModel();
        $this->movimientoCajaModel = new MovimientoCajaModel();
        $this->ventaService = new \App\Services\VentaService();
        $this->ventaService->setModels($this->cajaModel, $this->movimientoCajaModel); // Inject if possible or instantiate inside
    }

    protected function checkAuth()
    {
        if (!$this->isLoggedIn()) {
            return redirect()->to('/auth/login');
        }
        $this->requirePermission('POS', 'ver');
    }

    public function index()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;

        if (!$empresaId) {
            return redirect()->to('/auth/login');
        }

        $sucursalId = $this->sucursal['id'] ?? null;

        if (!$sucursalId && $this->session->has('sucursal_id')) {
            $sucursalId = $this->session->get('sucursal_id');
        }

        if (!$sucursalId && isset($this->user['sucursal_id'])) {
            $sucursalId = $this->user['sucursal_id'];
        }

        // Verificar Caja Abierta
        $cajasAbiertas = $this->cajaModel->getCajasAbiertas($this->user['id'], $sucursalId);
        $cajaAbierta = null;
        $mensajeCaja = '';

        if (empty($cajasAbiertas)) {
            $mensajeCaja = 'No tiene una caja abierta. Debe abrir una caja para procesar ventas.';
        } else {
            // Asumimos la primera caja abierta si hay multiples (aunque deberia ser solo una)
            $cajaAbierta = $cajasAbiertas[0];
        }

        // Cargar clientes con información de crédito
        try {
            $db = \Config\Database::connect();
            $clientes = $db->query("
                SELECT 
                    id, tipo_cliente, nombre, apellido, razon_social, nombre_fantasia,
                    limite_credito, credito_utilizado, credito_disponible, dias_credito
                FROM clientes 
                WHERE empresa_id = ? AND estado = true 
                ORDER BY tipo_cliente ASC, COALESCE(razon_social, nombre) ASC 
                LIMIT 100
            ", [$empresaId])->getResultArray();

            // Calcular crédito disponible si no está calculado
            foreach ($clientes as &$cliente) {
                if (!isset($cliente['credito_disponible']) || $cliente['credito_disponible'] === null) {
                    $limiteCredito = (float) ($cliente['limite_credito'] ?? 0);
                    $creditoUtilizado = (float) ($cliente['credito_utilizado'] ?? 0);
                    $cliente['credito_disponible'] = max(0, $limiteCredito - $creditoUtilizado);
                }
            }
            log_message('debug', 'Clientes cargados: ' . count($clientes));
        } catch (\Exception $e) {
            log_message('error', 'Error al cargar clientes: ' . $e->getMessage());
            // Intentar sin ordenamiento complejo
            try {
                $clientes = $this->clienteModel->where('empresa_id', $empresaId)
                    ->where('estado', true)
                    ->orderBy('nombre', 'ASC')
                    ->limit(100)
                    ->findAll();
            } catch (\Exception $e2) {
                log_message('error', 'Error al cargar clientes (sin orden): ' . $e2->getMessage());
                $clientes = [];
            }
        }

        // Cargar listas de precios
        try {
            $listas_precios = $this->listaPrecioModel->where('empresa_id', $empresaId)
                ->where('estado', true)
                ->orderBy('nombre', 'ASC')
                ->findAll();
        } catch (\Exception $e) {
            log_message('error', 'Error al cargar listas de precios: ' . $e->getMessage());
            $listas_precios = [];
        }

        $data = [
            'title' => 'Punto de Venta',
            'productos' => [],
            'clientes' => $clientes,
            'listas_precios' => $listas_precios,
            'sucursal_id' => $sucursalId,
            'caja_abierta' => $cajaAbierta,
            'mensaje_caja' => $mensajeCaja
        ];

        return $this->render('ventas/pos/index', $data);
    }

    public function buscarProducto()
    {
        $this->checkAuth();
        $termino = $this->request->getPost('termino');
        $empresaId = $this->user['empresa_id'] ?? null;

        if (!$empresaId) {
            return $this->errorResponse('Usuario no tiene empresa asignada', 403);
        }

        $sucursalId = $this->sucursal['id'] ?? null;

        if (!$sucursalId && $this->session->has('sucursal_id')) {
            $sucursalId = $this->session->get('sucursal_id');
        }

        if (!$sucursalId && isset($this->user['sucursal_id'])) {
            $sucursalId = $this->user['sucursal_id'];
        }

        $listaPrecioId = $this->request->getPost('lista_precio_id');

        if (empty(trim($termino))) {
            $productos = $this->productoModel->where('empresa_id', $empresaId)
                ->where('estado', true)
                ->limit(50)
                ->findAll();
        } else {
            $productos = $this->productoModel->buscar($termino, $empresaId, 50);
        }

        $preciosLista = [];
        if ($listaPrecioId) {
            $db = \Config\Database::connect();
            $preciosDb = $db->table('listas_precios_detalle')
                ->where('lista_id', $listaPrecioId)
                ->get()->getResultArray();

            foreach ($preciosDb as $precioDetalle) {
                $preciosLista[$precioDetalle['producto_id']] = $precioDetalle;
            }
        }

        foreach ($productos as &$producto) {
            $producto['type'] = 'PRODUCTO';

            if ($producto['controla_stock'] && $sucursalId) {
                $stock = $this->stockModel->where('producto_id', $producto['id'])
                    ->where('sucursal_id', $sucursalId)
                    ->first();
                $producto['stock_disponible'] = $stock ? $stock['cantidad_disponible'] : 0;
            } else {
                $producto['stock_disponible'] = 999999;
            }

            if (isset($preciosLista[$producto['id']])) {
                $pDetalle = $preciosLista[$producto['id']];
                $precioFinal = null;
                $hoy = date('Y-m-d');

                if (
                    !empty($pDetalle['es_oferta']) &&
                    !empty($pDetalle['precio_oferta']) &&
                    ($pDetalle['fecha_inicio_oferta'] <= $hoy && $pDetalle['fecha_fin_oferta'] >= $hoy)
                ) {
                    $precioFinal = $pDetalle['precio_oferta'];
                } elseif (!empty($pDetalle['precio_final']) && $pDetalle['precio_final'] > 0) {
                    $precioFinal = $pDetalle['precio_final'];
                } elseif (!empty($pDetalle['precio_especial']) && $pDetalle['precio_especial'] > 0) {
                    $precioFinal = $pDetalle['precio_especial'];
                }

                if ($precioFinal !== null) {
                    $producto['precio_venta'] = $precioFinal;
                    $producto['precio_original'] = $producto['precio_venta'];
                    $producto['en_oferta'] = true;
                }
            }
        }

        $servicios = [];
        try {
            $ordenModel = new \App\Models\OrdenServicioModel();
            $db = \Config\Database::connect();
            $estadoRetiro = $db->table('estados_orden')
                ->groupStart()
                ->like('nombre', 'LISTO')
                ->orLike('nombre', 'RETIRO')
                ->orLike('nombre', 'TERMINADO')
                ->groupEnd()
                ->where('es_final', false)
                ->get()->getResultArray();

            $estadosIds = array_column($estadoRetiro, 'id');

            if (!empty($estadosIds)) {
                $builder = $ordenModel->select('ordenes_servicio.*, c.nombre as c_nombre, c.apellido as c_apellido, c.razon_social')
                    ->join('clientes c', 'c.id = ordenes_servicio.cliente_id', 'left')
                    ->where('ordenes_servicio.empresa_id', $empresaId)
                    ->whereIn('ordenes_servicio.estado_id', $estadosIds)
                    ->where('ordenes_servicio.saldo_pendiente >', 0);

                if (!empty(trim($termino))) {
                    $builder->groupStart()
                        ->like('ordenes_servicio.numero_orden', $termino)
                        ->orLike('c.nombre', $termino)
                        ->orLike('c.apellido', $termino)
                        ->orLike('c.razon_social', $termino)
                        ->groupEnd();
                }

                $ordenes = $builder->findAll(20);

                foreach ($ordenes as $orden) {
                    $clienteNombre = $orden['c_nombre'] . ' ' . $orden['c_apellido'];
                    if (empty(trim($clienteNombre)))
                        $clienteNombre = $orden['razon_social'];

                    $servicios[] = [
                        'id' => $orden['id'],
                        'codigo' => 'OS-' . $orden['numero_orden'],
                        'nombre' => 'Servicio #' . $orden['numero_orden'] . ' - ' . substr($clienteNombre, 0, 20) . '...',
                        'precio_venta' => $orden['saldo_pendiente'],
                        'stock_disponible' => 1,
                        'controla_stock' => false,
                        'codigo_barra' => '',
                        'imagen' => null,
                        'type' => 'SERVICIO'
                    ];
                }
            }
        } catch (\Exception $e) {
            log_message('error', 'Error buscando servicios en POS: ' . $e->getMessage());
        }

        return $this->successResponse('Productos y Servicios encontrados', array_merge($servicios, $productos));
    }

    public function obtenerPrecios()
    {
        $this->checkAuth();
        $listaPrecioId = $this->request->getPost('lista_precio_id');
        $productoIds = $this->request->getPost('producto_ids');

        if (empty($listaPrecioId) || empty($productoIds) || !is_array($productoIds)) {
            return $this->errorResponse('Datos inválidos');
        }

        $db = \Config\Database::connect();
        $preciosDb = $db->table('listas_precios_detalle')
            ->where('lista_id', $listaPrecioId)
            ->whereIn('producto_id', $productoIds)
            ->get()->getResultArray();

        $preciosMap = [];
        $hoy = date('Y-m-d');

        foreach ($preciosDb as $pDetalle) {
            $precioFinal = null;
            if (
                !empty($pDetalle['es_oferta']) &&
                !empty($pDetalle['precio_oferta']) &&
                ($pDetalle['fecha_inicio_oferta'] <= $hoy && $pDetalle['fecha_fin_oferta'] >= $hoy)
            ) {
                $precioFinal = $pDetalle['precio_oferta'];
            } elseif (!empty($pDetalle['precio_final']) && $pDetalle['precio_final'] > 0) {
                $precioFinal = $pDetalle['precio_final'];
            } elseif (!empty($pDetalle['precio_especial']) && $pDetalle['precio_especial'] > 0) {
                $precioFinal = $pDetalle['precio_especial'];
            }

            if ($precioFinal !== null) {
                $preciosMap[$pDetalle['producto_id']] = (float) $precioFinal;
            }
        }

        $productosOriginales = $this->productoModel->whereIn('id', $productoIds)->findAll();
        foreach ($productosOriginales as $prod) {
            if (!isset($preciosMap[$prod['id']])) {
                $preciosMap[$prod['id']] = (float) $prod['precio_venta'];
            }
        }

        return $this->successResponse('Precios actualizados', $preciosMap);
    }

    // Previous comments where helper was mentioned
    // Proceeding to procesarVenta update.

    public function procesarVenta()
    {
        $this->checkAuth();

        // Validación básica de Request
        $rules = [
            'productos' => 'required',
            'tipo_pago' => 'required|in_list[CONTADO,CREDITO,MIXTO]'
        ];

        if (!$this->validate($rules)) {
            log_message('error', 'POSValidation: ' . implode(', ', $this->validator->getErrors()));
            return $this->errorResponse('Datos inválidos: ' . implode(', ', $this->validator->getErrors()));
        }

        try {
            $empresaId = $this->user['empresa_id'];
            $sucursalId = $this->sucursal['id'] ?? $this->user['sucursal_id'] ?? null;

            log_message('debug', 'POS: Procesando venta. Empresa: ' . $empresaId . ', Sucursal: ' . ($sucursalId ?? 'NULL'));

            if (!$sucursalId) {
                return $this->errorResponse('Error: No se pudo determinar la sucursal del usuario.');
            }

            // Verificar Caja Abierta al momento de procesar (Seguridad)
            $cajasAbiertas = $this->cajaModel->getCajasAbiertas($this->user['id'], $sucursalId);
            if (empty($cajasAbiertas)) {
                return $this->errorResponse('No tiene una caja abierta. Debe abrir una caja para procesar ventas.');
            }
            $cajaAbierta = $cajasAbiertas[0];

            // Decodificar JSONs
            $productos = $this->decodedJson($this->request->getPost('productos'));
            $formasPago = $this->decodedJson($this->request->getPost('formas_pago'));

            if (empty($productos)) {
                return $this->errorResponse('Sin productos para procesar');
            }

            // Datos cabecera
            $datosCabecera = [
                'empresa_id' => $empresaId,
                'sucursal_id' => $sucursalId,
                'cliente_id' => $this->request->getPost('cliente_id') ?: null,
                'usuario_id' => $this->user['id'],
                'vendedor_id' => $this->user['id'],
                'tipo_pago' => $this->request->getPost('tipo_pago'),
                'tipo_comprobante' => $this->request->getPost('tipo_comprobante') ?? 'TICKET',
                'moneda' => $this->request->getPost('moneda') ?? (new \Config\Business())->currencyCode ?? 'PYG',
                'observaciones' => $this->request->getPost('observaciones'),
                'descuento_general' => $this->request->getPost('descuento_general') ?? 0,
                'caja_id' => $cajaAbierta['id'] // Inject active Caja ID
            ];

            log_message('debug', 'POS: Datos Cabecera: ' . json_encode($datosCabecera));

            // ... rest of logic ...

            // Validaciones previas de negocio (ej. Factura o Crédito)
            // 1. Crédito
            if ($datosCabecera['tipo_pago'] != 'CONTADO' && !empty($datosCabecera['cliente_id'])) {
                // Calcular total temporal para validar
                $dummyCalc = $this->ventaService->calcularTotales($productos, $datosCabecera['descuento_general']);
                // Asignamos monto pendiente total por defecto para validar, luego se ajustará con pagos
                $resCredito = $this->ventaService->validarCredito($datosCabecera['cliente_id'], $dummyCalc['total']);
                if (!$resCredito['valido']) {
                    return $this->errorResponse($resCredito['mensaje']);
                }
            }

            // 2. Talonario Factura (si aplica)
            // Por ahora: Opción A (manual) por defecto.
            // Preparado para Opción B: si la empresa usa facturación electrónica, intentamos FACTURA_ELECTRONICA
            // solo si existe talonario activo; si no existe, hacemos fallback a FACTURA.
            if (in_array(($datosCabecera['tipo_comprobante'] ?? ''), ['FACTURA', 'FACTURA_ELECTRONICA'], true)) {
                $talonarioModel = new \App\Models\TalonarioModel();

                $tipoComprobanteFinal = $datosCabecera['tipo_comprobante'];
                if ($tipoComprobanteFinal === 'FACTURA') {
                    $empresaModel = new \App\Models\EmpresaModel();
                    $empresa = $empresaModel->find($empresaId);
                    $usaFE = (bool) ($empresa['usa_facturacion_electronica'] ?? false);
                    if ($usaFE) {
                        $talonarioFE = $talonarioModel->where('empresa_id', $empresaId)
                            ->where('sucursal_id', $sucursalId)
                            ->where('tipo_comprobante', 'FACTURA_ELECTRONICA')
                            ->where('estado', 'ACTIVO')
                            ->first();

                        if ($talonarioFE) {
                            $tipoComprobanteFinal = 'FACTURA_ELECTRONICA';
                        }
                    }
                }

                $datosCabecera['tipo_comprobante'] = $tipoComprobanteFinal;

                $talonario = $talonarioModel->where('empresa_id', $empresaId)
                    ->where('sucursal_id', $sucursalId)
                    ->where('tipo_comprobante', $tipoComprobanteFinal)
                    ->where('estado', 'ACTIVO')
                    ->first();

                if (!$talonario) {
                    return $this->errorResponse('No hay talonario activo para el comprobante seleccionado');
                }

                $datosCabecera['talonario_id'] = $talonario['id'];
                $datosCabecera['timbrado'] = $talonario['timbrado'];
                $datosCabecera['numero_factura'] = $talonario['numero_actual'] + 1;

                if ($datosCabecera['numero_factura'] > $talonario['numero_hasta']) {
                    return $this->errorResponse('Talonario agotado');
                }
            }

            // Procesar Venta via Service
            $resultado = $this->ventaService->crearVenta($datosCabecera, $productos, $formasPago, $this->user);

            if ($resultado['success']) {
                return $this->successResponse('Venta registrada con éxito', [
                    'venta_id' => $resultado['venta_id'],
                    'redirect' => base_url('ventas/ver/' . $resultado['venta_id']),
                    'print_url' => $datosCabecera['tipo_comprobante'] == 'FACTURA' 
                                    ? base_url('ventas/imprimirFactura/' . $resultado['venta_id']) 
                                    : base_url('ventas/imprimir/' . $resultado['venta_id']),
                    'imprimir' => $datosCabecera['tipo_comprobante'] == 'TICKET', // Flag para imprimir directo
                    'vuelto' => $resultado['vuelto'] ?? 0 // Si servicio retorna vuelto
                ]);
            } else {
                log_message('error', 'POS: Error en Service: ' . $resultado['message']);
                return $this->errorResponse('Error al procesar: ' . $resultado['message']);
            }
        } catch (\Exception $e) {
            log_message('critical', 'POS: Excepción en procesarVenta: ' . $e->getMessage());
            log_message('critical', 'Stack Trace: ' . $e->getTraceAsString());
            return $this->errorResponse('Error interno del servidor al procesar la venta. Verifique los logs.');
        }
    }

    private function decodedJson($input)
    {
        if (is_string($input))
            return json_decode($input, true) ?? [];
        return is_array($input) ? $input : [];
    }

    private function generarNumeroVenta($empresaId)
    {
        $ultimo = $this->ventaModel->where('empresa_id', $empresaId)
            ->orderBy('id', 'DESC')
            ->first();

        $numero = $ultimo ? ((int) str_replace('VTA-', '', $ultimo['numero_venta'] ?? '0') + 1) : 1;
        return 'VTA-' . str_pad($numero, 8, '0', STR_PAD_LEFT);
    }

    /**
     * Método temporal para ejecutar el script de corrección del trigger
     * ELIMINAR DESPUÉS DE USAR
     */
    public function fixTrigger()
    {
        $this->checkAuth();

        $db = \Config\Database::connect();

        try {
            // 1. Eliminar trigger existente
            $db->query('DROP TRIGGER IF EXISTS trg_completar_stock_venta ON ventas');

            // 2. Eliminar función existente
            $db->query('DROP FUNCTION IF EXISTS completar_stock_venta()');

            // 3. Recrear la función con la corrección
            $functionSql = "
            CREATE OR REPLACE FUNCTION completar_stock_venta()
            RETURNS TRIGGER AS \$\$
            BEGIN
                -- Solo procesar si el estado es COMPLETADA
                IF NEW.estado = 'COMPLETADA' AND (OLD.estado IS NULL OR OLD.estado != 'COMPLETADA') THEN
                    -- Actualizar stock restando las cantidades vendidas
                    UPDATE stock s
                    SET cantidad = s.cantidad - vd.cantidad,
                        cantidad_reservada = s.cantidad_reservada - vd.cantidad,
                        fecha_actualizacion = CURRENT_TIMESTAMP
                    FROM ventas_detalle vd
                    WHERE s.producto_id = vd.producto_id
                      AND s.sucursal_id = NEW.sucursal_id
                      AND vd.venta_id = NEW.id;
                END IF;
                
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
            ";
            $db->query($functionSql);

            // 4. Recrear el trigger
            $triggerSql = "
            CREATE TRIGGER trg_completar_stock_venta
            AFTER UPDATE ON ventas
            FOR EACH ROW
            WHEN (NEW.estado = 'COMPLETADA' AND (OLD.estado IS NULL OR OLD.estado != 'COMPLETADA'))
            EXECUTE FUNCTION completar_stock_venta();
            ";
            $db->query($triggerSql);

            return $this->successResponse('Trigger corregido exitosamente');
        } catch (\Exception $e) {
            log_message('error', 'Error al ejecutar fixTrigger: ' . $e->getMessage());
            return $this->errorResponse('Error al ejecutar script: ' . $e->getMessage());
        }
    }

    public function fixSequences()
    {
        $this->checkAuth();
        $db = \Config\Database::connect();

        try {
            $tables = ['ventas', 'ventas_detalle', 'pagos_ventas', 'cuentas_cobrar', 'stock', 'movimientos_stock'];
            $results = [];

            foreach ($tables as $table) {
                // Determine sequence name (default naming convention)
                $seqName = "{$table}_id_seq";

                // Check if sequence exists (optional, or just try-catch)
                // We'll try to setval to max(id)
                $sql = "SELECT setval(?, (SELECT COALESCE(MAX(id), 1) FROM {$table}))";

                try {
                    $db->query($sql, [$seqName]);
                    $results[] = "$table: OK";
                } catch (\Exception $e) {
                    $results[] = "$table: " . $e->getMessage();
                    log_message('error', "Error fixing sequence for $table: " . $e->getMessage());
                }
            }

            return $this->successResponse('Secuencias actualizadas', $results);

        } catch (\Exception $e) {
            return $this->errorResponse('Error general: ' . $e->getMessage());
        }
    }
}
