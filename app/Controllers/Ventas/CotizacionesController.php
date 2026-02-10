<?php

namespace App\Controllers\Ventas;

use App\Controllers\BaseController;
use App\Models\CotizacionModel;
use App\Models\CotizacionDetalleModel;

class CotizacionesController extends BaseController
{
    protected $cotizacionModel;
    protected $cotizacionDetalleModel;

    public function __construct()
    {
        $this->cotizacionModel = new CotizacionModel();
        $this->cotizacionDetalleModel = new CotizacionDetalleModel();
    }
    
    protected function checkAuth()
    {
        if (!$this->isLoggedIn()) {
            return redirect()->to('/auth/login');
        }
        $this->requirePermission('VENTAS', 'ver');
    }

    public function index()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;
        
        if (!$empresaId) {
            return redirect()->to('/auth/login');
        }
        
        $cotizaciones = $this->cotizacionModel->where('empresa_id', $empresaId)
                                             ->orderBy('fecha_emision', 'DESC')
                                             ->findAll();
        
        $data = [
            'title' => 'Cotizaciones',
            'cotizaciones' => $cotizaciones
        ];

        return $this->render('ventas/cotizaciones/index', $data);
    }

    public function crear()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;
        
        if (!$empresaId) {
            return redirect()->to('/auth/login');
        }
        
        $clienteModel = new \App\Models\ClienteModel();
        $productoModel = new \App\Models\ProductoModel();
        $listaPrecioModel = new \App\Models\ListaPrecioModel();
        
        $data = [
            'title' => 'Nueva Cotización',
            'cotizacion' => null,
            'clientes' => $clienteModel->where('empresa_id', $empresaId)->where('estado', true)->findAll(),
            'productos' => $productoModel->where('empresa_id', $empresaId)->where('estado', true)->findAll(),
            'listas_precios' => $listaPrecioModel->where('empresa_id', $empresaId)->where('estado', true)->findAll()
        ];

        return $this->render('ventas/cotizaciones/form', $data);
    }

    public function guardar()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;
        
        if (!$empresaId) {
            return $this->errorResponse('Usuario no tiene empresa asignada', 403);
        }
        
        $sucursalId = $this->sucursal['id'] ?? null;
        if (!$sucursalId && $this->session->has('sucursal_id')) {
            $sucursalId = $this->session->get('sucursal_id');
        }
        
        $rules = [
            'cliente_id' => 'required|is_natural_no_zero',
            'productos' => 'required',
            'fecha_vencimiento' => 'required|valid_date'
        ];

        if (!$this->validate($rules)) {
            return $this->errorResponse('Error de validación: ' . implode(', ', $this->validator->getErrors()));
        }

        $productos = json_decode($this->request->getPost('productos'), true);
        
        if (empty($productos)) {
            return $this->errorResponse('Debe agregar al menos un producto');
        }

        // Calcular totales
        $subtotal = 0;
        $iva10 = 0;
        $iva5 = 0;
        $descuentoMonto = 0;
        $descuentoPorcentaje = (float)($this->request->getPost('descuento_porcentaje') ?? 0);

        foreach ($productos as $producto) {
            $precio = (float)($producto['precio'] ?? 0);
            $cantidad = (float)($producto['cantidad'] ?? 0);
            $descuento = (float)($producto['descuento'] ?? 0);
            
            $subtotalLinea = $precio * $cantidad;
            $descuentoLinea = ($subtotalLinea * $descuento) / 100;
            $subtotalLinea -= $descuentoLinea;
            
            $subtotal += $subtotalLinea;
            $descuentoMonto += $descuentoLinea;

            // Calcular IVA
            $tipoIva = $producto['tipo_iva'] ?? 'GRAVADO_10';
            if ($tipoIva == 'GRAVADO_10') {
                $iva10 += ($subtotalLinea * 10) / 110;
            } elseif ($tipoIva == 'GRAVADO_5') {
                $iva5 += ($subtotalLinea * 5) / 105;
            }
        }

        // Aplicar descuento general
        if ($descuentoPorcentaje > 0) {
            $descuentoMonto += ($subtotal * $descuentoPorcentaje) / 100;
            $subtotal -= ($subtotal * $descuentoPorcentaje) / 100;
        }

        $total = $subtotal + $iva10 + $iva5;

        // Generar número de cotización
        $numeroCotizacion = $this->generarNumeroCotizacion($empresaId);

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            // Crear cotización
            $cotizacionData = [
                'empresa_id' => $empresaId,
                'sucursal_id' => $sucursalId,
                'cliente_id' => $this->request->getPost('cliente_id'),
                'usuario_id' => $this->user['id'],
                'lista_precio_id' => $this->request->getPost('lista_precio_id') ?: null,
                'numero_cotizacion' => $numeroCotizacion,
                'estado' => 'PENDIENTE',
                'subtotal' => $subtotal,
                'descuento_porcentaje' => $descuentoPorcentaje,
                'descuento_monto' => $descuentoMonto,
                'iva_10' => $iva10,
                'iva_5' => $iva5,
                'total' => $total,
                'fecha_emision' => date('Y-m-d H:i:s'),
                'fecha_vencimiento' => $this->request->getPost('fecha_vencimiento'),
                'observaciones' => $this->request->getPost('observaciones'),
                'terminos_condiciones' => $this->request->getPost('terminos_condiciones')
            ];

            $this->cotizacionModel->insert($cotizacionData);
            $cotizacionId = $this->cotizacionModel->getInsertID();

            // Crear detalles
            $linea = 1;
            foreach ($productos as $producto) {
                $productoData = (new \App\Models\ProductoModel())->find($producto['id']);
                if (!$productoData) continue;

                $precio = (float)($producto['precio'] ?? 0);
                $cantidad = (float)($producto['cantidad'] ?? 0);
                $descuento = (float)($producto['descuento'] ?? 0);
                
                $subtotalLinea = $precio * $cantidad;
                $descuentoLinea = ($subtotalLinea * $descuento) / 100;
                $subtotalLinea -= $descuentoLinea;

                $tipoIva = $productoData['tipo_iva'] ?? 'GRAVADO_10';
                $porcentajeIva = $tipoIva == 'GRAVADO_10' ? 10 : ($tipoIva == 'GRAVADO_5' ? 5 : 0);
                $montoIva = $porcentajeIva > 0 ? ($subtotalLinea * $porcentajeIva) / (100 + $porcentajeIva) : 0;
                $totalLinea = $subtotalLinea;

                $detalleData = [
                    'cotizacion_id' => $cotizacionId,
                    'producto_id' => $producto['id'],
                    'descripcion' => $productoData['nombre'],
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio,
                    'descuento_porcentaje' => $descuento,
                    'descuento_monto' => $descuentoLinea,
                    'porcentaje_iva' => $porcentajeIva,
                    'subtotal' => $subtotalLinea,
                    'monto_iva' => $montoIva,
                    'total' => $totalLinea,
                    'linea_orden' => $linea++
                ];

                $this->cotizacionDetalleModel->insert($detalleData);
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                return $this->errorResponse('Error al crear la cotización');
            }

            return $this->successResponse('Cotización creada exitosamente', [
                'cotizacion_id' => $cotizacionId,
                'redirect' => base_url('ventas/cotizaciones/ver/' . $cotizacionId)
            ]);

        } catch (\Exception $e) {
            $db->transRollback();
            return $this->errorResponse('Error: ' . $e->getMessage());
        }
    }

    public function convertirAVenta($id)
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;
        
        if (!$empresaId) {
            return $this->errorResponse('Usuario no tiene empresa asignada', 403);
        }
        
        $cotizacion = $this->cotizacionModel->find($id);
        
        if (!$cotizacion || $cotizacion['empresa_id'] != $empresaId) {
            return $this->errorResponse('Cotización no encontrada', 404);
        }

        if ($cotizacion['estado'] == 'CONVERTIDA_VENTA') {
            return $this->errorResponse('La cotización ya fue convertida en venta');
        }

        // Redirigir al POS con los datos de la cotización
        $this->session->set('cotizacion_id', $id);
        return redirect()->to('ventas/pos?cotizacion=' . $id);
    }

    public function ver($id)
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;
        
        if (!$empresaId) {
            return redirect()->to('/auth/login');
        }
        
        $cotizacion = $this->cotizacionModel->find($id);
        
        if (!$cotizacion || $cotizacion['empresa_id'] != $empresaId) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Cotización no encontrada');
        }
        
        $detalles = $this->cotizacionDetalleModel->where('cotizacion_id', $id)->findAll();
        
        // Obtener cliente
        if ($cotizacion['cliente_id']) {
            $clienteModel = new \App\Models\ClienteModel();
            $cotizacion['cliente'] = $clienteModel->find($cotizacion['cliente_id']);
        }
        
        $data = [
            'title' => 'Ver Cotización',
            'cotizacion' => $cotizacion,
            'detalles' => $detalles
        ];

        return $this->render('ventas/cotizaciones/ver', $data);
    }

    private function generarNumeroCotizacion($empresaId)
    {
        $ultimo = $this->cotizacionModel->where('empresa_id', $empresaId)
                                       ->orderBy('id', 'DESC')
                                       ->first();
        
        $numero = $ultimo ? ((int)str_replace('COT-', '', $ultimo['numero_cotizacion'] ?? '0') + 1) : 1;
        return 'COT-' . str_pad($numero, 8, '0', STR_PAD_LEFT);
    }
}
