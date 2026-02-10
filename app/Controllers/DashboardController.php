<?php

namespace App\Controllers;

class DashboardController extends BaseController
{
    public function index()
    {
        if (!$this->isLoggedIn()) {
            return redirect()->to('/auth/login');
        }
        
        $this->loadUserData();
        
        if (!$this->user) {
            return redirect()->to('/auth/login');
        }
        
        $empresaId = $this->user['empresa_id'] ?? null;
        $sucursalId = $this->sucursal['id'] ?? null;
        $userId = $this->user['id'] ?? null;
        
        if (!$sucursalId && $this->session->has('sucursal_id')) {
            $sucursalId = $this->session->get('sucursal_id');
        }

        // Determinar nivel del rol para filtrar datos
        $rolModel = new \App\Models\RolModel();
        $rol = $rolModel->find($this->user['rol_id'] ?? 0);
        $nivelRol = (int)($rol['nivel'] ?? 99);
        $esAdmin = $nivelRol <= 3; // Super Admin, Admin Empresa, Gerente

        // Inicializar data con valores por defecto
        $data = [
            'title' => 'Dashboard',
            'ventas_hoy' => ['cantidad' => 0, 'monto' => 0],
            'ventas_mes' => ['cantidad' => 0, 'monto' => 0],
            'stock_bajo' => [],
            'stock_critico' => [],
            'ordenes_pendientes' => [],
            'ordenes_urgentes' => [],
            'clientes_nuevos' => [],
            'cuentas_vencidas' => ['cantidad' => 0, 'monto' => 0],
            'cuentas_pagar_vencidas' => ['cantidad' => 0, 'monto' => 0],
            'productos_mas_vendidos' => [],
            'ventas_por_dia' => [],
            'ordenes_por_estado' => [],
            'empresa_id' => $empresaId,
            'sucursal_id' => $sucursalId,
            'esAdmin' => $esAdmin,
            'nivelRol' => $nivelRol,
            'nombreRol' => $rol['nombre'] ?? 'Usuario'
        ];

        if (!$empresaId) {
            return $this->render('dashboard/index', $data);
        }

        $db = \Config\Database::connect();
        $ventaModel = new \App\Models\VentaModel();
        $productoModel = new \App\Models\ProductoModel();
        $clienteModel = new \App\Models\ClienteModel();
        $ordenModel = new \App\Models\OrdenServicioModel();
        
        // Intentar cargar modelos opcionales
        $cuentaCobrarModel = null;
        $cuentaPagarModel = null;
        
        try {
            if (class_exists('\App\Models\CuentaCobrarModel')) {
                $cuentaCobrarModel = new \App\Models\CuentaCobrarModel();
            }
            if (class_exists('\App\Models\CuentaPagarModel')) {
                $cuentaPagarModel = new \App\Models\CuentaPagarModel();
            }
        } catch (\Exception $e) {
            log_message('error', 'Error loading financial models: ' . $e->getMessage());
        }

        // Ventas del día
        try {
            $ventasHoy = $ventaModel->getVentasHoy($empresaId, $sucursalId);
            // Empleados solo ven sus propias ventas
            if (!$esAdmin && $userId) {
                $ventasHoy = array_filter($ventasHoy, function($v) use ($userId) {
                    return ($v['usuario_id'] ?? null) == $userId || ($v['vendedor_id'] ?? null) == $userId;
                });
                $ventasHoy = array_values($ventasHoy);
            }
            $data['ventas_hoy'] = [
                'cantidad' => count($ventasHoy),
                'monto' => array_sum(array_column($ventasHoy, 'total') ?: [])
            ];
        } catch (\Exception $e) {
            log_message('error', 'Error fetching ventas_hoy: ' . $e->getMessage());
            $data['ventas_hoy'] = ['cantidad' => 0, 'monto' => 0];
        }

        // Ventas del mes
        try {
            $fechaInicio = date('Y-m-01');
            $fechaFin = date('Y-m-t');
            
            $builder = $ventaModel->where('empresa_id', $empresaId)
                                 ->where('fecha_venta >=', $fechaInicio)
                                 ->where('fecha_venta <=', $fechaFin . ' 23:59:59')
                                 ->where('estado', 'COMPLETADA');
            
            if ($sucursalId) {
                $builder->where('sucursal_id', $sucursalId);
            }
            // Empleados solo ven sus propias ventas
            if (!$esAdmin && $userId) {
                $builder->groupStart()
                        ->where('usuario_id', $userId)
                        ->orWhere('vendedor_id', $userId)
                        ->groupEnd();
            }
            
            $ventasMes = $builder->findAll();
            $data['ventas_mes'] = [
                'cantidad' => count($ventasMes),
                'monto' => array_sum(array_column($ventasMes, 'total') ?: [])
            ];
        } catch (\Exception $e) {
            log_message('error', 'Error fetching ventas_mes: ' . $e->getMessage());
            $data['ventas_mes'] = ['cantidad' => 0, 'monto' => 0];
        }

        // Productos con stock bajo y crítico (solo para admins/gerentes)
        if ($esAdmin) {
            try {
                $stockBajo = $productoModel->getStockBajo($empresaId, $sucursalId);
                $data['stock_bajo'] = [];
                $data['stock_critico'] = [];
                
                foreach ($stockBajo as $producto) {
                    $stockActual = $producto['stock_actual'] ?? 0;
                    $stockMinimo = $producto['stock_minimo'] ?? 0;
                    
                    if ($stockActual <= $stockMinimo) {
                        $data['stock_critico'][] = $producto;
                    } else {
                        $data['stock_bajo'][] = $producto;
                    }
                }
            } catch (\Exception $e) {
                log_message('error', 'Error fetching stock_bajo: ' . $e->getMessage());
                $data['stock_bajo'] = [];
                $data['stock_critico'] = [];
            }
        }

        // Órdenes de servicio pendientes (solo admins/gerentes)
        if ($esAdmin) {
            try {
                $data['ordenes_pendientes'] = $ordenModel->getPendientes($empresaId, $sucursalId);
                
                // Órdenes urgentes
                $data['ordenes_urgentes'] = array_filter($data['ordenes_pendientes'], function($orden) {
                    return isset($orden['prioridad']) && in_array($orden['prioridad'], ['ALTA', 'URGENTE']);
                });
            } catch (\Exception $e) {
                log_message('error', 'Error fetching ordenes_pendientes: ' . $e->getMessage());
                $data['ordenes_pendientes'] = [];
                $data['ordenes_urgentes'] = [];
            }

            // Clientes nuevos este mes
            try {
                $data['clientes_nuevos'] = $clienteModel->getNuevosEsteMes($empresaId);
            } catch (\Exception $e) {
                log_message('error', 'Error fetching clientes_nuevos: ' . $e->getMessage());
                $data['clientes_nuevos'] = [];
            }

            // Cuentas por cobrar vencidas
            try {
                if ($cuentaCobrarModel) {
                    $cuentasVencidas = $cuentaCobrarModel->where('empresa_id', $empresaId)
                                                        ->where('estado', 'VENCIDO')
                                                        ->findAll();
                    
                    $data['cuentas_vencidas'] = [
                        'cantidad' => count($cuentasVencidas),
                        'monto' => array_sum(array_column($cuentasVencidas, 'monto_pendiente') ?: [])
                    ];
                }
            } catch (\Exception $e) {
                log_message('error', 'Error fetching cuentas_vencidas: ' . $e->getMessage());
                $data['cuentas_vencidas'] = ['cantidad' => 0, 'monto' => 0];
            }

            // Cuentas por pagar vencidas
            try {
                if ($cuentaPagarModel) {
                    $cuentasPagarVencidas = $cuentaPagarModel->where('empresa_id', $empresaId)
                                                            ->where('estado', 'VENCIDO')
                                                            ->findAll();
                    
                    $data['cuentas_pagar_vencidas'] = [
                        'cantidad' => count($cuentasPagarVencidas),
                        'monto' => array_sum(array_column($cuentasPagarVencidas, 'monto_pendiente') ?: [])
                    ];
                }
            } catch (\Exception $e) {
                log_message('error', 'Error fetching cuentas_pagar_vencidas: ' . $e->getMessage());
                $data['cuentas_pagar_vencidas'] = ['cantidad' => 0, 'monto' => 0];
            }
        }

        // Productos más vendidos (últimos 7 días) - solo admins/gerentes
        if ($esAdmin) {
            try {
                $fechaInicio = date('Y-m-d', strtotime('-7 days'));
                
                $builder = $db->table('ventas_detalle vd')
                             ->select('vd.producto_id, p.nombre, p.codigo, SUM(vd.cantidad) as total_vendido, SUM(vd.total) as monto_total')
                             ->join('ventas v', 'v.id = vd.venta_id', 'inner')
                             ->join('productos p', 'p.id = vd.producto_id', 'inner')
                             ->where('v.empresa_id', $empresaId)
                             ->where('v.estado', 'COMPLETADA')
                             ->where('v.fecha_venta >=', $fechaInicio);
                
                if ($sucursalId) {
                    $builder->where('v.sucursal_id', $sucursalId);
                }
                
                // En PostgreSQL, todas las columnas no agregadas deben estar en GROUP BY
                $data['productos_mas_vendidos'] = $builder->groupBy('vd.producto_id')
                                                         ->groupBy('p.nombre')
                                                         ->groupBy('p.codigo')
                                                         ->orderBy('total_vendido', 'DESC')
                                                         ->limit(5)
                                                         ->get()
                                                         ->getResultArray();
            } catch (\Exception $e) {
                log_message('error', 'Error fetching productos_mas_vendidos: ' . $e->getMessage());
                $data['productos_mas_vendidos'] = [];
            }
        }

        // Ventas por día (últimos 7 días)
        try {
            $fechaInicio = date('Y-m-d', strtotime('-7 days'));
            
            $builder = $db->table('ventas')
                         ->select("TO_CHAR(fecha_venta, 'YYYY-MM-DD') as fecha, COUNT(*) as cantidad, SUM(total) as monto")
                         ->where('empresa_id', $empresaId)
                         ->where('estado', 'COMPLETADA')
                         ->where("TO_CHAR(fecha_venta, 'YYYY-MM-DD') >=", $fechaInicio);
            
            if ($sucursalId) {
                $builder->where('sucursal_id', $sucursalId);
            }
            // Empleados solo ven sus propias ventas en el gráfico
            if (!$esAdmin && $userId) {
                $builder->groupStart()
                        ->where('usuario_id', $userId)
                        ->orWhere('vendedor_id', $userId)
                        ->groupEnd();
            }
            
            $data['ventas_por_dia'] = $builder->groupBy("TO_CHAR(fecha_venta, 'YYYY-MM-DD')")
                                             ->orderBy('fecha', 'ASC')
                                             ->get()
                                             ->getResultArray();
        } catch (\Exception $e) {
            log_message('error', 'Error fetching ventas_por_dia: ' . $e->getMessage());
            $data['ventas_por_dia'] = [];
        }

        // Órdenes por estado (solo admins/gerentes)
        if ($esAdmin) {
            try {
                $builder = $db->table('ordenes_servicio o')
                             ->select('eo.nombre as estado, COUNT(*) as cantidad')
                             ->join('estados_orden eo', 'eo.id = o.estado_id', 'inner')
                             ->where('o.empresa_id', $empresaId)
                             ->where('o.estado_activo', true);
                
                if ($sucursalId) {
                    $builder->where('o.sucursal_id', $sucursalId);
                }
                
                $data['ordenes_por_estado'] = $builder->groupBy('eo.nombre')
                                                      ->get()
                                                      ->getResultArray();
            } catch (\Exception $e) {
                log_message('error', 'Error fetching ordenes_por_estado: ' . $e->getMessage());
                $data['ordenes_por_estado'] = [];
            }
        }

        return $this->render('dashboard/index', $data);
    }
}
