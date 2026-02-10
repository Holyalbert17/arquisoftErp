<?php

namespace App\Controllers\Inventario;

use App\Controllers\BaseController;
use App\Models\StockModel;
use App\Models\ProductoModel;
use App\Models\SucursalModel;

class StockController extends BaseController
{
    protected $stockModel;
    protected $productoModel;
    protected $sucursalModel;

    public function __construct()
    {
        $this->stockModel = new StockModel();
        $this->productoModel = new ProductoModel();
        $this->sucursalModel = new SucursalModel();
    }
    
    protected function checkAuth()
    {
        if (!$this->isLoggedIn()) {
            return redirect()->to('/auth/login');
        }
        if (!$this->user) {
            $this->loadUserData();
        }
        $this->requirePermission('INVENTARIO', 'ver');
    }

    public function index()
    {
        $this->checkAuth();
        
        if (!$this->user) {
            return redirect()->to('/auth/login');
        }
        
        $empresaId = $this->user['empresa_id'] ?? null;
        $sucursalId = $this->sucursal['id'] ?? null;
        
        if (!$empresaId) {
            return redirect()->to('/auth/login');
        }

        // Si viene filtro por sucursal en la URL, usarlo con prioridad.
        // Nota: valor vacío significa "todas las sucursales".
        $sucursalFiltro = $this->request->getGet('sucursal');
        if ($sucursalFiltro !== null) {
            $sucursalFiltro = trim((string) $sucursalFiltro);
            if ($sucursalFiltro === '') {
                $sucursalId = null;
            } elseif (ctype_digit($sucursalFiltro)) {
                $sucursalId = (int) $sucursalFiltro;
            }
        }
        
        // Obtener sucursalId de la sesión si no está en $this->sucursal
        if (!$sucursalId && $this->session->has('sucursal_id')) {
            $sucursalId = $this->session->get('sucursal_id');
        }
        
        try {
            $db = \Config\Database::connect();
            
            // Verificar si la tabla stock existe
            if (!$db->tableExists('stock')) {
                $data = [
                    'title' => 'Gestión de Stock',
                    'stock' => [],
                    'sucursales' => [],
                    'sucursal_actual' => $sucursalId,
                    'error' => 'La tabla de stock no existe en la base de datos'
                ];
                return $this->render('inventario/stock/index', $data);
            }
            
            // Obtener stock con información de productos y valorización
            $builder = $db->table('stock s')
                         ->select('s.*, p.codigo, p.nombre as producto_nombre, p.stock_minimo, p.punto_reorden, 
                                  p.precio_compra, p.precio_venta, p.controla_stock,
                                  suc.nombre as sucursal_nombre, c.nombre as categoria_nombre, m.nombre as marca_nombre,
                                  (s.cantidad * COALESCE(s.costo_promedio, p.precio_compra, 0)) as valor_costo,
                                  (s.cantidad * p.precio_venta) as valor_venta')
                         ->join('productos p', 'p.id = s.producto_id', 'inner')
                         ->join('sucursales suc', 'suc.id = s.sucursal_id', 'inner')
                         ->join('categorias c', 'c.id = p.categoria_id', 'left')
                         ->join('marcas m', 'm.id = p.marca_id', 'left')
                         ->where('p.empresa_id', $empresaId)
                         ->where('p.estado', true);

            if ($sucursalId) {
                // Validar que la sucursal pertenezca a la empresa
                $sucursalValida = $this->sucursalModel->where('empresa_id', $empresaId)
                                                     ->where('estado', true)
                                                     ->find($sucursalId);
                if (!$sucursalValida) {
                    throw new \Exception('Sucursal no válida para la empresa');
                }
                $builder->where('s.sucursal_id', $sucursalId);
            }
            
            $stock = $builder->orderBy('p.nombre', 'ASC')
                            ->get()
                            ->getResultArray();
            
            // Calcular estadísticas avanzadas
            $estadisticas = $this->calcularEstadisticasStock($stock, $empresaId, $sucursalId);
            
            // Obtener sucursales para filtro
            $sucursales = $this->sucursalModel->where('empresa_id', $empresaId)
                                             ->where('estado', true)
                                             ->findAll();
            
            $data = [
                'title' => 'Gestión de Stock',
                'stock' => $stock,
                'sucursales' => $sucursales,
                'sucursal_actual' => $sucursalId,
                'estadisticas' => $estadisticas
            ];

            return $this->render('inventario/stock/index', $data);
        } catch (\Exception $e) {
            log_message('error', 'Error en StockController::index: ' . $e->getMessage());
            log_message('error', 'Stack trace: ' . $e->getTraceAsString());
            
            $data = [
                'title' => 'Gestión de Stock',
                'stock' => [],
                'sucursales' => [],
                'sucursal_actual' => $sucursalId,
                'error' => 'Error al cargar el stock: ' . $e->getMessage()
            ];
            return $this->render('inventario/stock/index', $data);
        }
    }
    
    /**
     * Calcular estadísticas avanzadas de stock
     */
    private function calcularEstadisticasStock($stock, $empresaId, $sucursalId = null)
    {
        $db = \Config\Database::connect();
        
        $totalProductos = count($stock);
        $stockCritico = 0;
        $stockBajo = 0;
        $stockNormal = 0;
        $totalValorizadoCosto = 0;
        $totalValorizadoVenta = 0;
        $productosSinStock = 0;
        $productosProximosVencer = 0;
        
        foreach ($stock as $item) {
            $valorCosto = floatval($item['valor_costo'] ?? 0);
            $valorVenta = floatval($item['valor_venta'] ?? 0);
            $totalValorizadoCosto += $valorCosto;
            $totalValorizadoVenta += $valorVenta;
            
            if ($item['cantidad_disponible'] <= 0) {
                $productosSinStock++;
            } elseif ($item['cantidad_disponible'] <= $item['stock_minimo']) {
                $stockCritico++;
            } elseif ($item['cantidad_disponible'] <= $item['punto_reorden']) {
                $stockBajo++;
            } else {
                $stockNormal++;
            }
            
            // Verificar productos próximos a vencer (30 días)
            if (!empty($item['fecha_vencimiento'])) {
                $fechaVencimiento = new \DateTime($item['fecha_vencimiento']);
                $hoy = new \DateTime();
                $diasRestantes = $hoy->diff($fechaVencimiento)->days;
                
                if ($diasRestantes <= 30 && $diasRestantes >= 0) {
                    $productosProximosVencer++;
                }
            }
        }
        
        // Obtener productos sin stock registrado
        $builder = $db->table('productos p')
                     ->select('COUNT(*) as total')
                     ->where('p.empresa_id', $empresaId)
                     ->where('p.estado', true)
                     ->where('p.controla_stock', true);
        
        if ($sucursalId) {
            $builder->whereNotIn('p.id', function($builder) use ($sucursalId) {
                return $builder->select('producto_id')
                              ->from('stock')
                              ->where('sucursal_id', $sucursalId);
            });
        } else {
            $builder->whereNotIn('p.id', function($builder) {
                return $builder->select('producto_id')->from('stock');
            });
        }
        
        $productosSinRegistro = $builder->get()->getRowArray();
        $productosSinRegistro = intval($productosSinRegistro['total'] ?? 0);
        
        return [
            'total_productos' => $totalProductos,
            'stock_critico' => $stockCritico,
            'stock_bajo' => $stockBajo,
            'stock_normal' => $stockNormal,
            'productos_sin_stock' => $productosSinStock,
            'productos_sin_registro' => $productosSinRegistro,
            'productos_proximos_vencer' => $productosProximosVencer,
            'total_valorizado_costo' => $totalValorizadoCosto,
            'total_valorizado_venta' => $totalValorizadoVenta,
            'utilidad_potencial' => $totalValorizadoVenta - $totalValorizadoCosto
        ];
    }
    
    /**
     * Obtener kardex de un producto
     */
    public function kardex($productoId = null)
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;
        $sucursalId = $this->sucursal['id'] ?? null;
        
        if (!$empresaId) {
            return redirect()->to('/auth/login');
        }
        
        if (!$productoId) {
            $productoId = $this->request->getGet('producto_id');
        }
        
        if (!$productoId) {
            return $this->errorResponse('Producto no especificado', 400);
        }
        
        // Verificar que el producto pertenece a la empresa
        $producto = $this->productoModel->find($productoId);
        if (!$producto || $producto['empresa_id'] != $empresaId) {
            return $this->errorResponse('Producto no encontrado', 404);
        }
        
        $fechaDesde = $this->request->getGet('fecha_desde') ?? date('Y-m-01');
        $fechaHasta = $this->request->getGet('fecha_hasta') ?? date('Y-m-d');
        
        $db = \Config\Database::connect();
        
        // Obtener movimientos del producto
        $builder = $db->table('movimientos_stock m')
                     ->select('m.*, s.nombre as sucursal_nombre, u.nombre as usuario_nombre, u.apellido as usuario_apellido')
                     ->join('sucursales s', 's.id = m.sucursal_id', 'left')
                     ->join('usuarios u', 'u.id = m.usuario_id', 'left')
                     ->where('m.producto_id', $productoId)
                     ->where('m.empresa_id', $empresaId)
                     ->where('m.fecha_movimiento >=', $fechaDesde . ' 00:00:00')
                     ->where('m.fecha_movimiento <=', $fechaHasta . ' 23:59:59')
                     ->orderBy('m.fecha_movimiento', 'ASC');
        
        if ($sucursalId) {
            $builder->where('m.sucursal_id', $sucursalId);
        }
        
        $movimientos = $builder->get()->getResultArray();
        
        // Calcular saldos acumulados
        $saldoAnterior = 0;
        $saldoActual = 0;
        
        // Obtener saldo inicial (antes de la fecha desde)
        $saldoInicialBuilder = $db->table('movimientos_stock')
                                 ->select('SUM(CASE 
                                     WHEN tipo_movimiento IN (\'COMPRA\', \'AJUSTE_ENTRADA\', \'TRANSFERENCIA_ENTRADA\', \'DEVOLUCION_VENTA\', \'INVENTARIO_INICIAL\') THEN cantidad
                                     WHEN tipo_movimiento IN (\'VENTA\', \'AJUSTE_SALIDA\', \'TRANSFERENCIA_SALIDA\', \'DEVOLUCION_COMPRA\', \'MERMA\', \'USO_SERVICIO\') THEN -cantidad
                                     ELSE 0
                                 END) as saldo')
                                 ->where('producto_id', $productoId)
                                 ->where('empresa_id', $empresaId)
                                 ->where('fecha_movimiento <', $fechaDesde . ' 00:00:00');
        
        if ($sucursalId) {
            $saldoInicialBuilder->where('sucursal_id', $sucursalId);
        }
        
        $saldoInicialResult = $saldoInicialBuilder->get()->getRowArray();
        $saldoAnterior = floatval($saldoInicialResult['saldo'] ?? 0);
        $saldoActual = $saldoAnterior;
        
        // Agregar saldo acumulado a cada movimiento
        foreach ($movimientos as &$movimiento) {
            $cantidad = floatval($movimiento['cantidad']);
            if (in_array($movimiento['tipo_movimiento'], ['COMPRA', 'AJUSTE_ENTRADA', 'TRANSFERENCIA_ENTRADA', 'DEVOLUCION_VENTA', 'INVENTARIO_INICIAL'])) {
                $saldoActual += $cantidad;
            } else {
                $saldoActual -= $cantidad;
            }
            $movimiento['saldo_acumulado'] = $saldoActual;
        }
        
        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'success' => true,
                'data' => [
                    'producto' => $producto,
                    'movimientos' => $movimientos,
                    'saldo_inicial' => $saldoAnterior,
                    'saldo_final' => $saldoActual,
                    'fecha_desde' => $fechaDesde,
                    'fecha_hasta' => $fechaHasta
                ]
            ]);
        }
        
        $data = [
            'title' => 'Kardex de Producto',
            'producto' => $producto,
            'movimientos' => $movimientos,
            'saldo_inicial' => $saldoAnterior,
            'saldo_final' => $saldoActual,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'sucursal_actual' => $sucursalId
        ];
        
        return $this->render('inventario/stock/kardex', $data);
    }

    public function actualizar()
    {
        try {
            $this->checkAuth();
            $empresaId = $this->user['empresa_id'] ?? null;
            
            if (!$empresaId) {
                return $this->errorResponse('Usuario no tiene empresa asignada', 403);
            }

        $stockId = $this->request->getPost('stock_id');
        $cantidad = $this->request->getPost('cantidad');
        $ubicacion = $this->request->getPost('ubicacion');
        $lote = $this->request->getPost('lote');
        $fechaVencimiento = $this->request->getPost('fecha_vencimiento');

        if (!$stockId || !is_numeric($cantidad)) {
            return $this->errorResponse('Datos inválidos');
        }

        $stock = $this->stockModel->find($stockId);
        
        if (!$stock) {
            return $this->errorResponse('Registro de stock no encontrado', 404);
        }

        // Verificar que el producto pertenece a la empresa
        $producto = $this->productoModel->find($stock['producto_id']);
        if (!$producto || $producto['empresa_id'] != $empresaId) {
            return $this->errorResponse('No tiene permisos para modificar este stock', 403);
        }

        $data = [
            'cantidad' => $cantidad,
            'ubicacion' => $ubicacion,
            'lote' => $lote ?: null,
            'fecha_vencimiento' => $fechaVencimiento ?: null
        ];

            if ($this->stockModel->update($stockId, $data)) {
                // Registrar movimiento con manejo de errores
                try {
                    $movimientoModel = new \App\Models\MovimientoStockModel();
                    $movimientoModel->insert([
                        'empresa_id' => $empresaId,
                        'sucursal_id' => $stock['sucursal_id'],
                        'producto_id' => $stock['producto_id'],
                        'tipo_movimiento' => 'AJUSTE_ENTRADA',
                        'cantidad' => abs($cantidad - $stock['cantidad']),
                        'cantidad_anterior' => $stock['cantidad'],
                        'cantidad_nueva' => $cantidad,
                        'costo_unitario' => $stock['costo_promedio'],
                        'motivo' => 'Ajuste manual de stock',
                        'usuario_id' => $this->user['id'] ?? null
                    ]);
                } catch (\Exception $e) {
                    log_message('error', 'Error al registrar movimiento de stock: ' . $e->getMessage());
                    // Continuar aunque falle el registro del movimiento
                }

                return $this->successResponse('Stock actualizado exitosamente');
            }

            return $this->errorResponse('Error al actualizar el stock');
        } catch (\Exception $e) {
            log_message('error', 'Error en actualizar stock: ' . $e->getMessage());
            return $this->errorResponse('Error al actualizar el stock: ' . $e->getMessage());
        }
    }
}
