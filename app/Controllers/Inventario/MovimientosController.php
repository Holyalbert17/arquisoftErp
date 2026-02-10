<?php

namespace App\Controllers\Inventario;

use App\Controllers\BaseController;
use App\Models\MovimientoStockModel;
use App\Models\ProductoModel;
use App\Models\SucursalModel;

class MovimientosController extends BaseController
{
    protected $movimientoModel;
    protected $productoModel;
    protected $sucursalModel;

    public function __construct()
    {
        $this->movimientoModel = new MovimientoStockModel();
        $this->productoModel = new ProductoModel();
        $this->sucursalModel = new SucursalModel();
    }
    
    protected function checkAuth()
    {
        if (!$this->isLoggedIn()) {
            return redirect()->to('/auth/login');
        }
        $this->requirePermission('INVENTARIO', 'ver');
    }

    public function index()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;
        
        if (!$empresaId) {
            return redirect()->to('/auth/login');
        }
        
        $sucursalId = $this->request->getGet('sucursal') ?? $this->sucursal['id'] ?? null;
        
        if (!$sucursalId && $this->session->has('sucursal_id')) {
            $sucursalId = $this->session->get('sucursal_id');
        }
        
        $fechaDesde = $this->request->getGet('fecha_desde') ?? date('Y-m-01');
        $fechaHasta = $this->request->getGet('fecha_hasta') ?? date('Y-m-d');
        $tipoMovimiento = $this->request->getGet('tipo_movimiento');
        $productoId = $this->request->getGet('producto_id');
        
        $db = \Config\Database::connect();
        
        // Obtener movimientos con información relacionada
        $builder = $db->table('movimientos_stock m')
                     ->select('m.*, p.nombre as producto_nombre, p.codigo as producto_codigo, 
                              s.nombre as sucursal_nombre, u.nombre as usuario_nombre, u.apellido as usuario_apellido')
                     ->join('productos p', 'p.id = m.producto_id', 'left')
                     ->join('sucursales s', 's.id = m.sucursal_id', 'left')
                     ->join('usuarios u', 'u.id = m.usuario_id', 'left')
                     ->where('m.empresa_id', $empresaId)
                     ->where('m.fecha_movimiento >=', $fechaDesde . ' 00:00:00')
                     ->where('m.fecha_movimiento <=', $fechaHasta . ' 23:59:59');
        
        if ($sucursalId) {
            $builder->where('m.sucursal_id', $sucursalId);
        }
        
        if ($tipoMovimiento) {
            $builder->where('m.tipo_movimiento', $tipoMovimiento);
        }
        
        if ($productoId) {
            $builder->where('m.producto_id', $productoId);
        }
        
        $movimientos = $builder->orderBy('m.fecha_movimiento', 'DESC')
                              ->limit(1000)
                              ->get()
                              ->getResultArray();
        
        // Calcular estadísticas
        $estadisticas = $this->calcularEstadisticasMovimientos($movimientos);
        
        // Obtener sucursales para filtro
        $sucursales = $this->sucursalModel->where('empresa_id', $empresaId)
                                         ->where('estado', true)
                                         ->findAll();
        
        // Obtener productos para filtro
        $productos = $this->productoModel->where('empresa_id', $empresaId)
                                        ->where('estado', true)
                                        ->orderBy('nombre', 'ASC')
                                        ->findAll();
        
        // Tipos de movimiento para filtro
        $tiposMovimiento = [
            'COMPRA' => 'Compra',
            'VENTA' => 'Venta',
            'AJUSTE_ENTRADA' => 'Ajuste Entrada',
            'AJUSTE_SALIDA' => 'Ajuste Salida',
            'TRANSFERENCIA_ENTRADA' => 'Transferencia Entrada',
            'TRANSFERENCIA_SALIDA' => 'Transferencia Salida',
            'DEVOLUCION_COMPRA' => 'Devolución Compra',
            'DEVOLUCION_VENTA' => 'Devolución Venta',
            'MERMA' => 'Merma',
            'INVENTARIO_INICIAL' => 'Inventario Inicial',
            'USO_SERVICIO' => 'Uso en Servicio'
        ];
        
        $data = [
            'title' => 'Movimientos de Stock',
            'movimientos' => $movimientos,
            'sucursales' => $sucursales,
            'productos' => $productos,
            'tipos_movimiento' => $tiposMovimiento,
            'sucursal_actual' => $sucursalId,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'tipo_movimiento' => $tipoMovimiento,
            'producto_id' => $productoId,
            'estadisticas' => $estadisticas
        ];

        return $this->render('inventario/movimientos/index', $data);
    }
    
    /**
     * Calcular estadísticas de movimientos
     */
    private function calcularEstadisticasMovimientos($movimientos)
    {
        $totalMovimientos = count($movimientos);
        $totalEntradas = 0;
        $totalSalidas = 0;
        $cantidadEntradas = 0;
        $cantidadSalidas = 0;
        
        $tiposEntrada = ['COMPRA', 'AJUSTE_ENTRADA', 'TRANSFERENCIA_ENTRADA', 'DEVOLUCION_VENTA', 'INVENTARIO_INICIAL'];
        $tiposSalida = ['VENTA', 'AJUSTE_SALIDA', 'TRANSFERENCIA_SALIDA', 'DEVOLUCION_COMPRA', 'MERMA', 'USO_SERVICIO'];
        
        foreach ($movimientos as $mov) {
            $cantidad = floatval($mov['cantidad'] ?? 0);
            
            if (in_array($mov['tipo_movimiento'], $tiposEntrada)) {
                $totalEntradas++;
                $cantidadEntradas += $cantidad;
            } elseif (in_array($mov['tipo_movimiento'], $tiposSalida)) {
                $totalSalidas++;
                $cantidadSalidas += $cantidad;
            }
        }
        
        return [
            'total_movimientos' => $totalMovimientos,
            'total_entradas' => $totalEntradas,
            'total_salidas' => $totalSalidas,
            'cantidad_entradas' => $cantidadEntradas,
            'cantidad_salidas' => $cantidadSalidas,
            'diferencia' => $cantidadEntradas - $cantidadSalidas
        ];
    }
}
