<?php

namespace App\Services;

use App\Models\CompraModel;
use App\Models\CompraDetalleModel;
use App\Models\ProveedorModel;
use App\Models\ProductoModel;
use App\Models\StockModel;
use App\Models\MovimientoStockModel;
use App\Models\CuentaPagarModel;
use App\Models\AuditoriaModel;
use Config\Database;

class CompraService
{
    protected $compraModel;
    protected $compraDetalleModel;
    protected $proveedorModel;
    protected $productoModel;
    protected $stockModel;
    protected $movimientoStockModel;
    protected $cuentaPagarModel;
    protected $auditoriaModel;
    protected $db;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->compraModel = new CompraModel($this->db);
        $this->compraDetalleModel = new CompraDetalleModel($this->db);
        $this->proveedorModel = new ProveedorModel($this->db);
        $this->productoModel = new ProductoModel($this->db);
        $this->stockModel = new StockModel($this->db);
        $this->movimientoStockModel = new MovimientoStockModel($this->db);
        $this->cuentaPagarModel = new CuentaPagarModel($this->db);
        $this->auditoriaModel = new AuditoriaModel($this->db);
    }

    /**
     * Crear una nueva compra con sus detalles y cuenta por pagar
     */
    public function crearCompra(array $compraData, array $productos, int $usuarioId)
    {
        $this->db->transStart();

        try {
            // Insertar Cabecera
            $this->compraModel->insert($compraData);
            $compraId = $this->compraModel->getInsertID();

            // Insertar Detalles
            foreach ($productos as $producto) {
                // Validar producto
                $productoData = $this->productoModel->find($producto['id']);
                if (!$productoData)
                    continue;

                // Cálculos de línea
                $precio = $producto['precio'];
                $cantidad = $producto['cantidad'];
                $descuento = $producto['descuento'] ?? 0;

                $subtotalLinea = $precio * $cantidad;
                $descuentoLinea = ($subtotalLinea * $descuento) / 100;
                $subtotalLinea -= $descuentoLinea;

                // Cálculos IVA
                $tipoIva = $productoData['tipo_iva'] ?? 'GRAVADO_10';
                $porcentajeIva = 0;
                switch ($tipoIva) {
                    case 'GRAVADO_10':
                        $porcentajeIva = 10;
                        break;
                    case 'GRAVADO_5':
                        $porcentajeIva = 5;
                        break;
                    default:
                        $porcentajeIva = 0;
                        break;
                }

                $montoIva = $porcentajeIva > 0 ? ($subtotalLinea * $porcentajeIva) / (100 + $porcentajeIva) : 0;

                $detalleData = [
                    'compra_id' => $compraId,
                    'producto_id' => $producto['id'],
                    'descripcion' => $productoData['nombre'],
                    'cantidad_solicitada' => $cantidad,
                    'cantidad_recibida' => 0,
                    'precio_unitario' => $precio,
                    'descuento_porcentaje' => $descuento,
                    'descuento_monto' => $descuentoLinea,
                    'porcentaje_iva' => $porcentajeIva,
                    'subtotal' => $subtotalLinea,
                    'monto_iva' => $montoIva,
                    'total' => $subtotalLinea, // Total línea suele ser igual al subtotal neto
                    'lote' => $producto['lote'] ?? null,
                    'fecha_vencimiento' => $producto['fecha_vencimiento'] ?? null
                ];

                $this->compraDetalleModel->insert($detalleData);
            }

            // Crear cuenta por pagar si corresponde
            if ($compraData['monto_pendiente'] > 0) {
                $this->generarCuentaPagar($compraId, $compraData, $usuarioId);
            }

            // Auditoría
            $this->auditoriaModel->registrar([
                'usuario_id' => $usuarioId,
                'empresa_id' => $compraData['empresa_id'],
                'tabla_afectada' => 'compras',
                'accion' => 'INSERT',
                'registro_id' => $compraId,
                'datos_nuevos' => $compraData,
                'ip_address' => service('request')->getIPAddress()
            ]);

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                throw new \Exception('Error al confirmar transacción de compra');
            }

            return ['success' => true, 'id' => $compraId, 'message' => 'Compra registrada exitosamente'];

        } catch (\Exception $e) {
            $this->db->transRollback();
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Procesar recepción de mercadería
     */
    public function recibirMercaderia(int $compraId, array $productosRecibidos, int $usuarioId)
    {
        $compra = $this->compraModel->find($compraId);
        if (!$compra) {
            return ['success' => false, 'message' => 'Compra no encontrada'];
        }

        $this->db->transStart();

        try {
            $detallesActualizados = false;

            foreach ($productosRecibidos as $producto) {
                $detalle = $this->compraDetalleModel->where('compra_id', $compraId)
                    ->where('producto_id', $producto['id'])
                    ->first();

                if ($detalle) {
                    $cantidadRecibidaNueva = $producto['cantidad_recibida'];

                    // Solo procesar si hay recepción
                    if ($cantidadRecibidaNueva > 0) {
                        // Actualizar detalle (acumulativo o reemplazo? Asumimos reemplazo/ajuste del valor total recibido hasta el momento, 
                        // o delta? El controlador original hacía update directo del valor, así que asumimos que el frontend envía el TOTAL recibido.)
                        // PERO cuidado: si ya se recibió parcialmente antes, ¿envían el delta o el total?
                        // Lógica original: update('cantidad_recibida' => $cantidadRecibida). 
                        // Esto implica que si recibí 5 ayer y hoy recibo 5 más, debo enviar 10? O son recepciones parciales independientes?
                        // El stock suma $cantidadRecibida. Si envío 10 (total) y ya procesé 5, sumaría 10 más = 15. Error.
                        // CORRECCIÓN: El controlador original sumaba al stock ($stock['cantidad'] + $cantidadRecibida).
                        // Esto sugiere que el input es el "delta" (lo que se recibe AHORA).
                        // PERO el update al detalle setea el valor: 'cantidad_recibida' => $cantidadRecibida.
                        // Esto es contradictorio en el código legacy. Si recibo 2 veces, la 2da vez sobrescribe la cantidad recibida en el detalle
                        // pero suma al stock.
                        // DECISIÓN: Asumiremos que $cantidadRecibida es lo que se recibe EN ESTE MOMENTO (Delta).
                        // Y debemos actualizar el detalle sumando, no reemplazando, para mantener coherencia.

                        $nuevaCantidadTotal = $detalle['cantidad_recibida'] + $cantidadRecibidaNueva;

                        $this->compraDetalleModel->update($detalle['id'], [
                            'cantidad_recibida' => $nuevaCantidadTotal
                        ]);

                        // Actualizar Stock
                        $this->actualizarStockCompra($producto['id'], $compra['sucursal_id'], $cantidadRecibidaNueva, $detalle['precio_unitario'], $compra, $detalle, $usuarioId);

                        $detallesActualizados = true;
                    }
                }
            }

            if ($detallesActualizados) {
                // Recalcular estado de la compra
                $this->actualizarEstadoCompra($compraId);
            }

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                throw new \Exception('Error al procesar recepción');
            }

            return ['success' => true, 'message' => 'Recepción procesada correctamente'];

        } catch (\Exception $e) {
            $this->db->transRollback();
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    // --- Helpers Privados ---

    private function generarCuentaPagar($compraId, $compraData, $usuarioId)
    {
        $diasCredito = (int) ($compraData['dias_credito'] ?? 30);
        $fechaVencimiento = date('Y-m-d', strtotime("+{$diasCredito} days"));

        // Estado inicial
        $estado = 'PENDIENTE'; // Al crear, nunca debería estar vencida salvo hacks de fecha

        $cuentaPagarData = [
            'empresa_id' => $compraData['empresa_id'],
            'sucursal_id' => $compraData['sucursal_id'],
            'proveedor_id' => $compraData['proveedor_id'],
            'compra_id' => $compraId,
            'numero_documento' => $compraData['numero_factura_proveedor'] ?? $compraData['numero_compra'],
            'tipo_documento' => $compraData['tipo_compra'] ?? 'COMPRA',
            'fecha_emision' => date('Y-m-d'),
            'fecha_vencimiento' => $fechaVencimiento,
            'monto_total' => $compraData['total'],
            'monto_pagado' => 0,
            'monto_pendiente' => $compraData['monto_pendiente'],
            'dias_vencido' => 0,
            'estado' => $estado
        ];

        $this->cuentaPagarModel->insert($cuentaPagarData);
    }

    private function actualizarStockCompra($productoId, $sucursalId, $cantidad, $costoUnitario, $compra, $detalle, $usuarioId)
    {
        $productoData = $this->productoModel->find($productoId);

        if ($productoData && $productoData['controla_stock']) {
            $stock = $this->stockModel->where('producto_id', $productoId)
                ->where('sucursal_id', $sucursalId)
                ->first();

            $cantidadAnterior = $stock ? $stock['cantidad'] : 0;
            $nuevoCostoPromedio = $costoUnitario;

            if ($stock) {
                $nuevaCantidad = $stock['cantidad'] + $cantidad;
                // Costo Promedio Ponderado
                if ($nuevaCantidad > 0) {
                    $nuevoCostoPromedio = (($stock['cantidad'] * $stock['costo_promedio']) + ($cantidad * $costoUnitario)) / $nuevaCantidad;
                }

                $this->stockModel->update($stock['id'], [
                    'cantidad' => $nuevaCantidad,
                    'costo_promedio' => $nuevoCostoPromedio
                ]);
            } else {
                $this->stockModel->insert([
                    'producto_id' => $productoId,
                    'sucursal_id' => $sucursalId,
                    'cantidad' => $cantidad,
                    'costo_promedio' => $costoUnitario
                ]);
                $nuevaCantidad = $cantidad;
            }

            // Movimiento Stock
            $this->movimientoStockModel->insert([
                'empresa_id' => $compra['empresa_id'],
                'sucursal_id' => $sucursalId,
                'producto_id' => $productoId,
                'tipo_movimiento' => 'COMPRA',
                'cantidad' => $cantidad,
                'cantidad_anterior' => $cantidadAnterior,
                'cantidad_nueva' => $nuevaCantidad,
                'costo_unitario' => $costoUnitario,
                'documento_referencia' => $compra['numero_compra'],
                'tipo_documento' => 'COMPRA',
                'lote' => $detalle['lote'] ?? null,
                'fecha_vencimiento' => $detalle['fecha_vencimiento'] ?? null,
                'usuario_id' => $usuarioId
            ]);
        }
    }

    private function actualizarEstadoCompra($compraId)
    {
        $detalles = $this->compraDetalleModel->where('compra_id', $compraId)->findAll();
        $totalSolicitado = array_sum(array_column($detalles, 'cantidad_solicitada'));
        $totalRecibido = array_sum(array_column($detalles, 'cantidad_recibida'));

        if ($totalRecibido >= $totalSolicitado) {
            $estado = 'RECIBIDA';
        } elseif ($totalRecibido > 0) {
            $estado = 'PARCIALMENTE_RECIBIDA';
        } else {
            $estado = 'PENDIENTE';
        }

        $this->compraModel->update($compraId, [
            'estado' => $estado,
            'fecha_recepcion' => date('Y-m-d H:i:s')
        ]);
    }
}
