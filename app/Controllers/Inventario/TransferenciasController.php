<?php

namespace App\Controllers\Inventario;

use App\Controllers\BaseController;
use App\Models\TransferenciaStockModel;
use App\Models\TransferenciaStockDetalleModel;
use App\Models\ProductoModel;
use App\Models\StockModel;
use App\Models\MovimientoStockModel;

class TransferenciasController extends BaseController
{
    protected $transferenciaModel;
    protected $transferenciaDetalleModel;
    protected $productoModel;
    protected $stockModel;
    protected $movimientoStockModel;

    public function __construct()
    {
        $this->transferenciaModel = new TransferenciaStockModel();
        $this->transferenciaDetalleModel = new TransferenciaStockDetalleModel();
        $this->productoModel = new ProductoModel();
        $this->stockModel = new StockModel();
        $this->movimientoStockModel = new MovimientoStockModel();
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
        
        $sucursalId = $this->sucursal['id'] ?? null;
        
        if (!$sucursalId && $this->session->has('sucursal_id')) {
            $sucursalId = $this->session->get('sucursal_id');
        }
        
        $builder = $this->transferenciaModel->where('empresa_id', $empresaId);
        if ($sucursalId) {
            $builder->groupStart()
                   ->where('sucursal_origen_id', $sucursalId)
                   ->orWhere('sucursal_destino_id', $sucursalId)
                   ->groupEnd();
        }
        
        $transferencias = $builder->orderBy('fecha_solicitud', 'DESC')
                                 ->limit(100)
                                 ->findAll();
        
        $data = [
            'title' => 'Transferencias de Stock',
            'transferencias' => $transferencias
        ];

        return $this->render('inventario/transferencias/index', $data);
    }

    public function crear()
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
        
        $sucursalModel = new \App\Models\SucursalModel();
        $sucursales = $sucursalModel->where('empresa_id', $empresaId)
                                   ->where('id !=', $sucursalId)
                                   ->where('estado', true)
                                   ->findAll();
        
        $data = [
            'title' => 'Nueva Transferencia',
            'sucursal_origen_id' => $sucursalId,
            'sucursales_destino' => $sucursales,
            'productos' => []
        ];

        return $this->render('inventario/transferencias/form', $data);
    }

    public function guardar()
    {
        $this->checkAuth();
        $rules = [
            'sucursal_destino_id' => 'required|is_natural_no_zero',
            'productos' => 'required'
        ];

        if (!$this->validate($rules)) {
            return $this->errorResponse('Error de validación: ' . implode(', ', $this->validator->getErrors()));
        }

        $empresaId = $this->user['empresa_id'] ?? null;
        
        if (!$empresaId) {
            return $this->errorResponse('Usuario no tiene empresa asignada', 403);
        }
        
        $sucursalOrigenId = $this->sucursal['id'] ?? null;
        
        if (!$sucursalOrigenId && $this->session->has('sucursal_id')) {
            $sucursalOrigenId = $this->session->get('sucursal_id');
        }
        
        if (!$sucursalOrigenId && isset($this->user['sucursal_id'])) {
            $sucursalOrigenId = $this->user['sucursal_id'];
        }
        $sucursalDestinoId = $this->request->getPost('sucursal_destino_id');
        $productos = json_decode($this->request->getPost('productos'), true);

        if ($sucursalOrigenId == $sucursalDestinoId) {
            return $this->errorResponse('La sucursal destino debe ser diferente a la origen');
        }

        if (empty($productos)) {
            return $this->errorResponse('Debe agregar al menos un producto');
        }

        // Verificar stock disponible y que los productos existan
        foreach ($productos as $producto) {
            // Validar que el producto existe
            $productoData = $this->productoModel->find($producto['id']);
            if (!$productoData || $productoData['empresa_id'] != $empresaId) {
                return $this->errorResponse("Producto con ID {$producto['id']} no encontrado o no pertenece a la empresa");
            }
            
            // Validar cantidad
            $cantidad = (int) round($this->normalizarNumero($producto['cantidad'] ?? 0));
            if ($cantidad <= 0) {
                return $this->errorResponse("La cantidad debe ser mayor a cero para el producto: {$productoData['nombre']}");
            }
            
            // Verificar stock si el producto controla stock
            if ($productoData['controla_stock'] && !($productoData['permite_venta_sin_stock'] ?? false)) {
                $stock = $this->stockModel->where('producto_id', $producto['id'])
                                         ->where('sucursal_id', $sucursalOrigenId)
                                         ->first();
                
                $stockDisponible = $stock ? (int) round(floatval($stock['cantidad_disponible'] ?? 0)) : 0;
                
                if ($stockDisponible < $cantidad) {
                    return $this->errorResponse("Stock insuficiente para el producto: {$productoData['nombre']}. Disponible: {$stockDisponible}, Solicitado: {$cantidad}");
                }
            }
        }

        // Generar número de transferencia
        $numeroTransferencia = $this->generarNumeroTransferencia($empresaId);

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            // Crear transferencia
            $transferenciaData = [
                'empresa_id' => $empresaId,
                'numero_transferencia' => $numeroTransferencia,
                'sucursal_origen_id' => $sucursalOrigenId,
                'sucursal_destino_id' => $sucursalDestinoId,
                'estado' => 'PENDIENTE',
                'usuario_solicita' => $this->user['id'],
                'observaciones' => $this->request->getPost('observaciones')
            ];

            $this->transferenciaModel->insert($transferenciaData);
            $transferenciaId = $this->transferenciaModel->getInsertID();

            // Crear detalles
            foreach ($productos as $producto) {
                $cantidad = (int) round($this->normalizarNumero($producto['cantidad'] ?? 0));
                
                $this->transferenciaDetalleModel->insert([
                    'transferencia_id' => $transferenciaId,
                    'producto_id' => $producto['id'],
                    'cantidad_solicitada' => $cantidad,
                    'lote' => !empty($producto['lote']) ? $producto['lote'] : null,
                    'observaciones' => !empty($producto['observaciones']) ? $producto['observaciones'] : null
                ]);
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                return $this->errorResponse('Error al crear la transferencia');
            }

            return $this->successResponse('Transferencia creada exitosamente', [
                'redirect' => base_url('inventario/transferencias/ver/' . $transferenciaId)
            ]);

        } catch (\Exception $e) {
            $db->transRollback();
            return $this->errorResponse('Error: ' . $e->getMessage());
        }
    }

    public function enviar($id)
    {
        $this->checkAuth();
        $transferencia = $this->transferenciaModel->find($id);
        
        if (!$transferencia || $transferencia['empresa_id'] != $this->user['empresa_id']) {
            return $this->errorResponse('Transferencia no encontrada', 404);
        }

        if ($transferencia['estado'] != 'PENDIENTE') {
            return $this->errorResponse('Solo se pueden enviar transferencias pendientes');
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $detalles = $this->transferenciaDetalleModel->where('transferencia_id', $id)->findAll();

            foreach ($detalles as $detalle) {
                // Descontar stock de origen
                $stockOrigen = $this->stockModel->where('producto_id', $detalle['producto_id'])
                                               ->where('sucursal_id', $transferencia['sucursal_origen_id'])
                                               ->first();

                if (!$stockOrigen) {
                    throw new \Exception('No existe stock de origen para el producto ID: ' . $detalle['producto_id']);
                }

                $cantidadAnterior = (int) round(floatval($stockOrigen['cantidad'] ?? 0));
                $cantidadReservada = (int) round(floatval($stockOrigen['cantidad_reservada'] ?? 0));
                $cantidadSolicitada = (int) round(floatval($detalle['cantidad_solicitada'] ?? 0));
                $cantidadNueva = $cantidadAnterior - $cantidadSolicitada;
                if ($cantidadNueva < 0) {
                    $cantidadNueva = 0;
                }
                $disponibleNueva = $cantidadNueva - $cantidadReservada;
                if ($disponibleNueva < 0) {
                    $disponibleNueva = 0;
                }

                $this->stockModel->update($stockOrigen['id'], [
                    'cantidad' => $cantidadNueva,
                    'cantidad_disponible' => $disponibleNueva
                ]);

                // Registrar movimiento de salida
                $this->movimientoStockModel->insert([
                    'empresa_id' => $transferencia['empresa_id'],
                    'sucursal_id' => $transferencia['sucursal_origen_id'],
                    'producto_id' => $detalle['producto_id'],
                    'tipo_movimiento' => 'TRANSFERENCIA_SALIDA',
                    'cantidad' => $cantidadSolicitada,
                    'cantidad_anterior' => $cantidadAnterior,
                    'cantidad_nueva' => $cantidadNueva,
                    'documento_referencia' => $transferencia['numero_transferencia'],
                    'tipo_documento' => 'TRANSFERENCIA',
                    'lote' => $detalle['lote'],
                    'usuario_id' => $this->user['id']
                ]);

                // Actualizar detalle con cantidad enviada
                $this->transferenciaDetalleModel->update($detalle['id'], [
                    'cantidad_enviada' => $cantidadSolicitada
                ]);
            }

            // Actualizar estado de transferencia
            $this->transferenciaModel->update($id, [
                'estado' => 'EN_TRANSITO',
                'fecha_envio' => date('Y-m-d H:i:s'),
                'usuario_autoriza' => $this->user['id']
            ]);

            $db->transComplete();

            if ($db->transStatus() === false) {
                return $this->errorResponse('Error al enviar la transferencia');
            }

            return $this->successResponse('Transferencia enviada exitosamente');

        } catch (\Exception $e) {
            $db->transRollback();
            return $this->errorResponse('Error: ' . $e->getMessage());
        }
    }

    public function recibir($id)
    {
        $this->checkAuth();
        $transferencia = $this->transferenciaModel->find($id);
        
        if (!$transferencia || $transferencia['empresa_id'] != $this->user['empresa_id']) {
            return $this->errorResponse('Transferencia no encontrada', 404);
        }

        if ($transferencia['estado'] != 'EN_TRANSITO') {
            return $this->errorResponse('Solo se pueden recibir transferencias en tránsito');
        }

        $productos = json_decode($this->request->getPost('productos'), true);

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            foreach ($productos as $producto) {
                $detalle = $this->transferenciaDetalleModel->where('transferencia_id', $id)
                                                           ->where('producto_id', $producto['id'])
                                                           ->first();
                
                if ($detalle) {
                    $cantidadRecibida = (int) round($this->normalizarNumero($producto['cantidad_recibida'] ?? 0));
                    
                    if ($cantidadRecibida < 0) {
                        throw new \Exception("La cantidad recibida no puede ser negativa");
                    }
                    
                    $cantidadMax = (int) round(floatval(($detalle['cantidad_enviada'] ?? $detalle['cantidad_solicitada']) ?? 0));
                    if ($cantidadRecibida > $cantidadMax) {
                        throw new \Exception("La cantidad recibida no puede ser mayor a la cantidad enviada");
                    }
                    
                    $this->transferenciaDetalleModel->update($detalle['id'], [
                        'cantidad_recibida' => $cantidadRecibida
                    ]);

                    // Agregar stock a destino
                    $stockDestino = $this->stockModel->where('producto_id', $producto['id'])
                                                    ->where('sucursal_id', $transferencia['sucursal_destino_id'])
                                                    ->first();
                    
                    if ($stockDestino) {
                        $cantidadDestinoAnterior = (int) round(floatval($stockDestino['cantidad'] ?? 0));
                        $cantidadDestinoReservada = (int) round(floatval($stockDestino['cantidad_reservada'] ?? 0));
                        $nuevaCantidad = $cantidadDestinoAnterior + $cantidadRecibida;
                        $disponibleNueva = $nuevaCantidad - $cantidadDestinoReservada;
                        if ($disponibleNueva < 0) {
                            $disponibleNueva = 0;
                        }
                        $this->stockModel->update($stockDestino['id'], [
                            'cantidad' => $nuevaCantidad,
                            'cantidad_disponible' => $disponibleNueva
                        ]);
                    } else {
                        // Obtener costo del stock de origen para usar en destino
                        $stockOrigen = $this->stockModel->where('producto_id', $producto['id'])
                                                         ->where('sucursal_id', $transferencia['sucursal_origen_id'])
                                                         ->first();
                        
                        $this->stockModel->insert([
                            'producto_id' => $producto['id'],
                            'sucursal_id' => $transferencia['sucursal_destino_id'],
                            'cantidad' => $cantidadRecibida,
                            'cantidad_reservada' => 0,
                            'cantidad_disponible' => $cantidadRecibida,
                            'costo_promedio' => $stockOrigen ? floatval($stockOrigen['costo_promedio'] ?? 0) : 0
                        ]);
                    }

                    // Registrar movimiento de entrada
                    $this->movimientoStockModel->insert([
                        'empresa_id' => $transferencia['empresa_id'],
                        'sucursal_id' => $transferencia['sucursal_destino_id'],
                        'producto_id' => $producto['id'],
                        'tipo_movimiento' => 'TRANSFERENCIA_ENTRADA',
                        'cantidad' => $cantidadRecibida,
                        'cantidad_anterior' => $stockDestino ? (int) round(floatval($stockDestino['cantidad'] ?? 0)) : 0,
                        'cantidad_nueva' => ($stockDestino ? (int) round(floatval($stockDestino['cantidad'] ?? 0)) : 0) + $cantidadRecibida,
                        'documento_referencia' => $transferencia['numero_transferencia'],
                        'tipo_documento' => 'TRANSFERENCIA',
                        'lote' => $detalle['lote'],
                        'usuario_id' => $this->user['id']
                    ]);
                }
            }

            // Actualizar estado de transferencia
            $this->transferenciaModel->update($id, [
                'estado' => 'RECIBIDA',
                'fecha_recepcion' => date('Y-m-d H:i:s'),
                'usuario_recibe' => $this->user['id']
            ]);

            $db->transComplete();

            if ($db->transStatus() === false) {
                return $this->errorResponse('Error al recibir la transferencia');
            }

            return $this->successResponse('Transferencia recibida exitosamente');

        } catch (\Exception $e) {
            $db->transRollback();
            return $this->errorResponse('Error: ' . $e->getMessage());
        }
    }

    public function ver($id)
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;
        
        if (!$empresaId) {
            return redirect()->to('/auth/login');
        }
        
        $transferencia = $this->transferenciaModel->find($id);
        
        if (!$transferencia || $transferencia['empresa_id'] != $empresaId) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Transferencia no encontrada');
        }
        
        // Obtener detalles
        $detalles = $this->transferenciaDetalleModel->where('transferencia_id', $id)->findAll();
        
        // Obtener información de productos y sucursales
        $sucursalModel = new \App\Models\SucursalModel();
        $sucursalOrigen = $sucursalModel->find($transferencia['sucursal_origen_id']);
        $sucursalDestino = $sucursalModel->find($transferencia['sucursal_destino_id']);
        
        foreach ($detalles as &$detalle) {
            $detalle['producto'] = $this->productoModel->find($detalle['producto_id']);
        }
        
        $data = [
            'title' => 'Ver Transferencia',
            'transferencia' => $transferencia,
            'detalles' => $detalles,
            'sucursal_origen' => $sucursalOrigen,
            'sucursal_destino' => $sucursalDestino
        ];

        return $this->render('inventario/transferencias/ver', $data);
    }

    private function generarNumeroTransferencia($empresaId)
    {
        $ultimo = $this->transferenciaModel->where('empresa_id', $empresaId)
                                           ->orderBy('id', 'DESC')
                                           ->first();
        
        $numero = $ultimo ? ((int)str_replace('TRANSF-', '', $ultimo['numero_transferencia'] ?? '0') + 1) : 1;
        return 'TRANSF-' . str_pad($numero, 8, '0', STR_PAD_LEFT);
    }
}
