<?php

namespace App\Controllers\Ventas;

use App\Controllers\BaseController;
use App\Models\VentaModel;

class DevolucionesController extends BaseController
{
    protected $ventaModel;

    public function __construct()
    {
        $this->ventaModel = new VentaModel();
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

        // Determinar nivel del rol
        $rolModel = new \App\Models\RolModel();
        $rol = $rolModel->find($this->user['rol_id'] ?? 0);
        $esAdmin = $rol && (int)($rol['nivel'] ?? 99) <= 3;

        // Obtener ventas completadas que pueden tener devoluciones
        $builderVentas = $this->ventaModel->where('empresa_id', $empresaId)
            ->where('estado', 'COMPLETADA')
            ->where('tipo_comprobante !=', 'NOTA_CREDITO');
        // Empleados solo ven sus propias ventas
        if (!$esAdmin) {
            $builderVentas->groupStart()
                ->where('usuario_id', $this->user['id'])
                ->orWhere('vendedor_id', $this->user['id'])
                ->groupEnd();
        }
        $ventas = $builderVentas->orderBy('fecha_venta', 'DESC')
            ->limit(100)
            ->findAll();

        // Obtener devoluciones (notas de crédito)
        $builderDev = $this->ventaModel->where('empresa_id', $empresaId)
            ->where('tipo_comprobante', 'NOTA_CREDITO');
        if (!$esAdmin) {
            $builderDev->where('usuario_id', $this->user['id']);
        }
        $devoluciones = $builderDev->orderBy('fecha_venta', 'DESC')
            ->limit(50)
            ->findAll();

        $data = [
            'title' => 'Devoluciones',
            'ventas' => $ventas,
            'devoluciones' => $devoluciones
        ];

        return $this->render('ventas/devoluciones/index', $data);
    }

    public function crear()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;

        if (!$empresaId) {
            return redirect()->to('/auth/login');
        }

        $ventaId = $this->request->getGet('venta_id');

        if (!$ventaId) {
            return redirect()->to('/ventas/devoluciones');
        }

        $venta = $this->ventaModel->find($ventaId);

        if (!$venta || $venta['empresa_id'] != $empresaId) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Venta no encontrada');
        }

        // Empleados solo pueden devolver sus propias ventas
        $rolModel = new \App\Models\RolModel();
        $rol = $rolModel->find($this->user['rol_id'] ?? 0);
        $esAdmin = $rol && (int)($rol['nivel'] ?? 99) <= 3;
        if (!$esAdmin) {
            $userId = $this->user['id'];
            if (($venta['usuario_id'] ?? null) != $userId && ($venta['vendedor_id'] ?? null) != $userId) {
                throw new \CodeIgniter\Exceptions\PageNotFoundException('No tiene acceso a esta venta');
            }
        }

        $ventaDetalleModel = new \App\Models\VentaDetalleModel();
        $detalles = $ventaDetalleModel->where('venta_id', $ventaId)->findAll();

        $data = [
            'title' => 'Nueva Devolución',
            'venta' => $venta,
            'detalles' => $detalles
        ];

        return $this->render('ventas/devoluciones/form', $data);
    }

    public function guardar()
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;

        if (!$empresaId) {
            return $this->errorResponse('Usuario no tiene empresa asignada', 403);
        }

        $rules = [
            'venta_id' => 'required|is_natural_no_zero',
            'productos' => 'required',
            'motivo' => 'required|min_length[10]'
        ];

        if (!$this->validate($rules)) {
            return $this->errorResponse('Error de validación: ' . implode(', ', $this->validator->getErrors()));
        }

        $ventaId = $this->request->getPost('venta_id');
        $venta = $this->ventaModel->find($ventaId);

        if (!$venta || $venta['empresa_id'] != $empresaId) {
            return $this->errorResponse('Venta no encontrada', 404);
        }

        // Empleados solo pueden devolver sus propias ventas
        $rolModel = new \App\Models\RolModel();
        $rol = $rolModel->find($this->user['rol_id'] ?? 0);
        $esAdmin = $rol && (int)($rol['nivel'] ?? 99) <= 3;
        if (!$esAdmin) {
            $userId = $this->user['id'];
            if (($venta['usuario_id'] ?? null) != $userId && ($venta['vendedor_id'] ?? null) != $userId) {
                return $this->errorResponse('No tiene permiso para devolver esta venta', 403);
            }
        }

        if ($venta['estado'] != 'COMPLETADA') {
            return $this->errorResponse('Solo se pueden devolver ventas completadas');
        }

        $productos = json_decode($this->request->getPost('productos'), true);
        $motivo = $this->request->getPost('motivo');
        $tipoDevolucion = $this->request->getPost('tipo_devolucion') ?? 'DEVOLUCION_PARCIAL';

        if (empty($productos)) {
            return $this->errorResponse('Debe seleccionar al menos un producto a devolver');
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $ventaDetalleModel = new \App\Models\VentaDetalleModel();
            $stockModel = new \App\Models\StockModel();
            $productoModel = new \App\Models\ProductoModel();

            $totalDevolucion = 0;
            $ivaDevolucion = 0;

            // Procesar cada producto devuelto
            foreach ($productos as $productoDev) {
                $detalle = $ventaDetalleModel->find($productoDev['detalle_id']);
                if (!$detalle || $detalle['venta_id'] != $ventaId)
                    continue;

                $cantidadDevuelta = (float) ($productoDev['cantidad'] ?? 0);
                if ($cantidadDevuelta <= 0 || $cantidadDevuelta > $detalle['cantidad']) {
                    continue;
                }

                // Calcular monto proporcional
                $proporcion = $cantidadDevuelta / $detalle['cantidad'];
                $montoDevolucion = $detalle['total'] * $proporcion;
                $ivaDevolucion += $detalle['monto_iva'] * $proporcion;
                $totalDevolucion += $montoDevolucion;

                // Revertir stock si controla stock
                if ($detalle['producto_id']) {
                    $producto = $productoModel->find($detalle['producto_id']);
                    if ($producto && $producto['controla_stock'] && $venta['sucursal_id']) {
                        $stock = $stockModel->where('producto_id', $detalle['producto_id'])
                            ->where('sucursal_id', $venta['sucursal_id'])
                            ->first();

                        if ($stock) {
                            $stockModel->update($stock['id'], [
                                'cantidad' => $stock['cantidad'] + $cantidadDevuelta
                            ]);
                        }
                    }
                }
            }

            // Crear nota de crédito (venta con tipo NOTA_CREDITO)
            $notaCreditoData = [
                'empresa_id' => $empresaId,
                'sucursal_id' => $venta['sucursal_id'],
                'cliente_id' => $venta['cliente_id'],
                'usuario_id' => $this->user['id'],
                'numero_venta' => $this->generarNumeroNotaCredito($empresaId),
                'tipo_comprobante' => 'NOTA_CREDITO',
                'tipo_venta' => 'PRODUCTO',
                'tipo_pago' => 'CONTADO',
                'estado' => 'COMPLETADA',
                'subtotal' => $totalDevolucion - $ivaDevolucion,
                'total_iva' => $ivaDevolucion,
                'total' => $totalDevolucion,
                'monto_pagado' => $totalDevolucion,
                'monto_pendiente' => 0,
                'observaciones' => 'Devolución de venta #' . $venta['numero_venta'] . '. Motivo: ' . $motivo,
                'fecha_venta' => date('Y-m-d H:i:s')
            ];

            if (!$this->ventaModel->insert($notaCreditoData)) {
                $errors = $this->ventaModel->errors();
                throw new \Exception('Error al crear nota de crédito: ' . implode(', ', $errors));
            }
            $notaCreditoId = $this->ventaModel->getInsertID();

            if (!$notaCreditoId) {
                throw new \Exception('No se pudo obtener el ID de la nota de crédito creada');
            }

            // Verificación: asegurar que la venta existe antes de insertar detalles
            // Esto previene el error 500 de violación de Foreign Key
            $verifyVenta = $this->ventaModel->find($notaCreditoId);
            if (!$verifyVenta) {
                // Si no se encuentra por ID, intentar buscar por numero_venta (fallback)
                $verifyVentaByNum = $this->ventaModel->where('numero_venta', $notaCreditoData['numero_venta'])->first();
                if ($verifyVentaByNum) {
                    $notaCreditoId = $verifyVentaByNum['id'];
                } else {
                    throw new \Exception("Error crítico: La Nota de Crédito no se generó correctamente. ID esperado: $notaCreditoId. Transacción abortada.");
                }
            }

            // Crear detalles de nota de crédito
            foreach ($productos as $productoDev) {
                $detalle = $ventaDetalleModel->find($productoDev['detalle_id']);
                if (!$detalle)
                    continue;

                $cantidadDevuelta = (float) ($productoDev['cantidad'] ?? 0);
                $proporcion = $cantidadDevuelta / $detalle['cantidad'];

                $detalleNotaData = [
                    'venta_id' => $notaCreditoId,
                    'producto_id' => $detalle['producto_id'],
                    'descripcion' => $detalle['descripcion'] . ' (DEVUELTO)',
                    'cantidad' => $cantidadDevuelta,
                    'precio_unitario' => $detalle['precio_unitario'],
                    'tipo_iva' => $detalle['tipo_iva'],
                    'porcentaje_iva' => $detalle['porcentaje_iva'],
                    'subtotal' => $detalle['subtotal'] * $proporcion,
                    'monto_iva' => $detalle['monto_iva'] * $proporcion,
                    'total' => $detalle['total'] * $proporcion * -1 // Negativo para nota de crédito
                ];

                $ventaDetalleModel->insert($detalleNotaData);
            }

            // Actualizar estado de venta original
            $ventaDetalleModel = new \App\Models\VentaDetalleModel();
            $detallesOriginales = $ventaDetalleModel->where('venta_id', $ventaId)->findAll();
            $totalDevuelto = array_sum(array_column($productos, 'cantidad'));
            $totalOriginal = array_sum(array_column($detallesOriginales, 'cantidad'));

            if ($totalDevuelto >= $totalOriginal) {
                $this->ventaModel->update($ventaId, ['estado' => 'DEVUELTA_TOTAL']);
            } else {
                $this->ventaModel->update($ventaId, ['estado' => 'DEVUELTA_PARCIAL']);
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                return $this->errorResponse('Error al procesar la devolución');
            }

            return $this->successResponse('Devolución procesada exitosamente', [
                'redirect' => base_url('ventas/devoluciones/ver/' . $notaCreditoId)
            ]);

        } catch (\Exception $e) {
            $db->transRollback();
            return $this->errorResponse('Error: ' . $e->getMessage());
        }
    }

    private function generarNumeroNotaCredito($empresaId)
    {
        $ultimo = $this->ventaModel->where('empresa_id', $empresaId)
            ->where('tipo_comprobante', 'NOTA_CREDITO')
            ->orderBy('id', 'DESC')
            ->first();

        $numero = $ultimo ? ((int) str_replace('NC-', '', $ultimo['numero_venta'] ?? '0') + 1) : 1;
        return 'NC-' . str_pad($numero, 8, '0', STR_PAD_LEFT);
    }

    public function ver($id)
    {
        $this->checkAuth();
        $empresaId = $this->user['empresa_id'] ?? null;

        if (!$empresaId) {
            return redirect()->to('/auth/login');
        }

        // La devolución es una venta de tipo NOTA_CREDITO
        $devolucion = $this->ventaModel->find($id);

        if (!$devolucion || $devolucion['empresa_id'] != $empresaId || $devolucion['tipo_comprobante'] != 'NOTA_CREDITO') {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Devolución no encontrada');
        }

        $ventaDetalleModel = new \App\Models\VentaDetalleModel();
        $detalles = $ventaDetalleModel->where('venta_id', $id)->findAll();

        // Obtener cliente
        if ($devolucion['cliente_id']) {
            $clienteModel = new \App\Models\ClienteModel();
            $devolucion['cliente'] = $clienteModel->find($devolucion['cliente_id']);
        }

        $data = [
            'title' => 'Ver Devolución',
            'devolucion' => $devolucion,
            'detalles' => $detalles
        ];

        return $this->render('ventas/devoluciones/ver', $data);
    }
}
